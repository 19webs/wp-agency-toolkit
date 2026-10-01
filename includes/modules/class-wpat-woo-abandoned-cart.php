<?php
/**
 * Módulo: Recuperador de Carritos Abandonados (WooCommerce) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Abandoned_Cart {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Abandoned_Cart
	 */
	private static $instance = null;

	/**
	 * Nombre de la tabla de la base de datos.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Abandoned_Cart
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wpat_abandoned_carts';

		// Crear o verificar la tabla al inicializar
		$this->check_database_table();

		// Frontend: Encolar assets de captura y escuchar eventos
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Captura de carritos para usuarios conectados
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_user_cart' ), 10 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'track_user_cart' ), 10 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'track_user_cart' ), 10 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'track_user_cart' ), 10 );

		// Captura AJAX silenciosa para usuarios invitados
		add_action( 'wp_ajax_wpat_capture_guest_cart', array( $this, 'ajax_capture_guest_cart' ) );
		add_action( 'wp_ajax_nopriv_wpat_capture_guest_cart', array( $this, 'ajax_capture_guest_cart' ) );

		// Marcar carrito como recuperado/completado al finalizar pedido
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_completed' ), 10, 3 );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 10 );

		// Restauración de carrito en 1 clic mediante URL mágica
		add_action( 'template_redirect', array( $this, 'handle_cart_recovery_link' ) );

		// Cron Job periódico para envíos automáticos
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
		add_action( 'wpat_abandoned_cart_cron_event', array( $this, 'process_abandoned_carts_cron' ) );
		$this->schedule_cron_job();

		// Acciones AJAX del panel de administración
		add_action( 'wp_ajax_wpat_send_test_abandoned_email', array( $this, 'ajax_send_test_email' ) );
		add_action( 'wp_ajax_wpat_delete_abandoned_cart_record', array( $this, 'ajax_delete_cart_record' ) );
	}

	/**
	 * Comprueba y crea la tabla en la base de datos si no existe.
	 */
	public function check_database_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) DEFAULT 0,
			user_email varchar(255) NOT NULL,
			user_name varchar(255) DEFAULT '',
			user_phone varchar(50) DEFAULT '',
			cart_contents longtext NOT NULL,
			cart_total decimal(10,2) NOT NULL DEFAULT 0.00,
			cart_hash varchar(64) DEFAULT '',
			recovery_token varchar(64) NOT NULL,
			status varchar(30) NOT NULL DEFAULT 'in_progress',
			emails_sent int(11) NOT NULL DEFAULT 0,
			email_history longtext DEFAULT '',
			coupon_code varchar(50) DEFAULT '',
			last_modified datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY recovery_token (recovery_token),
			KEY user_email (user_email(191)),
			KEY status (status),
			KEY last_modified (last_modified)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Encola el script de captura de checkout para usuarios invitados.
	 */
	public function enqueue_frontend_assets() {
		if ( is_admin() || ! function_exists( 'is_checkout' ) ) {
			return;
		}

		if ( is_checkout() && ! is_order_received_page() ) {
			wp_enqueue_script(
				'wpat-abandoned-cart-js',
				WPAT_URL . 'assets/js/wpat-abandoned-cart.js',
				array( 'jquery' ),
				WPAT_VERSION,
				true
			);

			wp_localize_script( 'wpat-abandoned-cart-js', 'wpatAbandonedCartParams', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpat-abandoned-cart-nonce' ),
			) );
		}
	}

	/**
	 * Rastrea y guarda el carrito de un usuario conectado.
	 */
	public function track_user_cart() {
		if ( ! is_user_logged_in() || ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$current_user = wp_get_current_user();
		if ( ! $current_user || empty( $current_user->user_email ) ) {
			return;
		}

		$user_id   = $current_user->ID;
		$email     = sanitize_email( $current_user->user_email );
		$name      = trim( $current_user->first_name . ' ' . $current_user->last_name );
		if ( empty( $name ) ) {
			$name = $current_user->display_name;
		}

		$this->save_cart_record( $email, $name, '', $user_id );
	}

	/**
	 * Handler AJAX para capturar el carrito de usuarios invitados.
	 */
	public function ajax_capture_guest_cart() {
		check_ajax_referer( 'wpat-abandoned-cart-nonce', 'security' );

		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Email no válido' ) );
		}

		$name = trim( $first_name . ' ' . $last_name );
		$user_id = is_user_logged_in() ? get_current_user_id() : 0;

		$this->save_cart_record( $email, $name, $phone, $user_id );
		wp_send_json_success();
	}

	/**
	 * Guarda o actualiza el registro de carrito en la base de datos.
	 *
	 * @param string $email
	 * @param string $name
	 * @param string $phone
	 * @param int $user_id
	 */
	public function save_cart_record( $email, $name = '', $phone = '', $user_id = 0 ) {
		global $wpdb;

		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$email = sanitize_email( $email );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}

		$cart_contents = array();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			if ( ! $_product || ! $_product->exists() ) {
				continue;
			}

			$image_id  = $_product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

			$meta_str = '';
			if ( ! empty( $cart_item['variation'] ) && is_array( $cart_item['variation'] ) ) {
				$formatted_vars = array();
				foreach ( $cart_item['variation'] as $k => $v ) {
					$k_clean = str_replace( 'attribute_', '', $k );
					$taxonomy = wc_attribute_taxonomy_name( $k_clean );
					$label = wc_attribute_label( $taxonomy );
					$formatted_vars[] = $label . ': ' . $v;
				}
				$meta_str = implode( ', ', $formatted_vars );
			}

			$cart_contents[] = array(
				'product_id'   => $cart_item['product_id'],
				'variation_id' => isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0,
				'variation'    => isset( $cart_item['variation'] ) ? $cart_item['variation'] : array(),
				'quantity'     => $cart_item['quantity'],
				'name'         => $_product->get_name(),
				'price_html'   => wc_price( $_product->get_price() ),
				'price'        => floatval( $_product->get_price() ),
				'image_url'    => $image_url,
				'meta'         => $meta_str,
			);
		}

		if ( empty( $cart_contents ) ) {
			return;
		}

		$cart_total = floatval( WC()->cart->total );
		if ( $cart_total <= 0 ) {
			$cart_total = floatval( WC()->cart->get_total( 'edit' ) );
		}

		$cart_hash = md5( wp_json_encode( $cart_contents ) );
		$now       = current_time( 'mysql' );

		// Buscar si ya existe un carrito activo 'in_progress' o 'abandoned' para este email
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE user_email = %s AND status IN ('in_progress', 'abandoned') ORDER BY id DESC LIMIT 1",
			$email
		) );

		if ( $existing ) {
			$wpdb->update(
				$this->table_name,
				array(
					'user_id'       => $user_id ? $user_id : $existing->user_id,
					'user_name'     => ! empty( $name ) ? sanitize_text_field( $name ) : $existing->user_name,
					'user_phone'    => ! empty( $phone ) ? sanitize_text_field( $phone ) : $existing->user_phone,
					'cart_contents' => wp_json_encode( $cart_contents ),
					'cart_total'    => $cart_total,
					'cart_hash'     => $cart_hash,
					'last_modified' => $now,
				),
				array( 'id' => $existing->id ),
				array( '%d', '%s', '%s', '%s', '%f', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$token = wp_generate_password( 32, false );
			$wpdb->insert(
				$this->table_name,
				array(
					'user_id'        => $user_id,
					'user_email'     => $email,
					'user_name'      => sanitize_text_field( $name ),
					'user_phone'     => sanitize_text_field( $phone ),
					'cart_contents'  => wp_json_encode( $cart_contents ),
					'cart_total'     => $cart_total,
					'cart_hash'      => $cart_hash,
					'recovery_token' => $token,
					'status'         => 'in_progress',
					'emails_sent'    => 0,
					'email_history'  => wp_json_encode( array() ),
					'coupon_code'    => '',
					'last_modified'  => $now,
					'created_at'     => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Marca el carrito como recuperado/completado al tramitar un pedido con éxito.
	 */
	public function on_order_completed( $order_id, $posted_data, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$email = sanitize_email( $order->get_billing_email() );
		if ( empty( $email ) ) {
			return;
		}

		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->table_name} SET status = 'recovered', last_modified = %s WHERE user_email = %s AND status IN ('in_progress', 'abandoned')",
			current_time( 'mysql' ),
			$email
		) );
	}

	/**
	 * Marca el carrito como recuperado al completarse el pago.
	 */
	public function on_payment_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$this->on_order_completed( $order_id, array(), $order );
		}
	}

	/**
	 * Maneja el clic en el enlace de recuperación del correo (?wpat_recover_cart=TOKEN).
	 */
	public function handle_cart_recovery_link() {
		if ( ! isset( $_GET['wpat_recover_cart'] ) || empty( $_GET['wpat_recover_cart'] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['wpat_recover_cart'] ) );
		global $wpdb;

		$cart_row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE recovery_token = %s LIMIT 1",
			$token
		) );

		if ( ! $cart_row || empty( $cart_row->cart_contents ) ) {
			wc_add_notice( __( 'Lo sentimos, este enlace de recuperación ha caducado o no es válido.', 'wp-agency-toolkit' ), 'error' );
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}

		$items = json_decode( $cart_row->cart_contents, true );
		if ( empty( $items ) || ! is_array( $items ) ) {
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();

			foreach ( $items as $item ) {
				$product_id   = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
				$quantity     = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
				$variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
				$variation    = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();

				if ( $product_id > 0 ) {
					WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
				}
			}

			// Aplicar cupón si existe
			if ( ! empty( $cart_row->coupon_code ) && wc_coupons_enabled() ) {
				WC()->cart->apply_coupon( sanitize_text_field( $cart_row->coupon_code ) );
			}

			// Pre-rellenar datos de facturación si es invitado
			if ( ! is_user_logged_in() && WC()->customer ) {
				if ( ! empty( $cart_row->user_email ) ) {
					WC()->customer->set_billing_email( $cart_row->user_email );
				}
				if ( ! empty( $cart_row->user_name ) ) {
					$parts = explode( ' ', $cart_row->user_name, 2 );
					WC()->customer->set_billing_first_name( $parts[0] );
					if ( isset( $parts[1] ) ) {
						WC()->customer->set_billing_last_name( $parts[1] );
					}
				}
				if ( ! empty( $cart_row->user_phone ) ) {
					WC()->customer->set_billing_phone( $cart_row->user_phone );
				}
			}

			wc_add_notice( __( '¡Tu carrito ha sido restaurado con éxito! Puedes finalizar tu compra ahora.', 'wp-agency-toolkit' ), 'success' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Añade intervalos personalizados para el WP-Cron.
	 */
	public function add_cron_intervals( $schedules ) {
		$schedules['wpat_every_15_minutes'] = array(
			'interval' => 15 * 60,
			'display'  => __( 'Cada 15 minutos (WPAT Carritos Abandonados)', 'wp-agency-toolkit' ),
		);
		return $schedules;
	}

	/**
	 * Programa el evento WP-Cron si no está activo.
	 */
	public function schedule_cron_job() {
		if ( ! wp_next_scheduled( 'wpat_abandoned_cart_cron_event' ) ) {
			wp_schedule_event( time() + 120, 'wpat_every_15_minutes', 'wpat_abandoned_cart_cron_event' );
		}
	}

	/**
	 * Proceso en segundo plano disparado por el Cron Job.
	 */
	public function process_abandoned_carts_cron() {
		global $wpdb;

		$settings = WPAT_Main::get_instance()->get_settings();
		$enabled  = ! empty( $settings['woo-abandoned-cart'] ) && '1' === $settings['woo-abandoned-cart'];

		if ( ! $enabled ) {
			return;
		}

		$cutoff_minutes = isset( $settings['wpat_ac_cutoff_time'] ) ? max( 10, intval( $settings['wpat_ac_cutoff_time'] ) ) : 20;
		$now_time       = current_time( 'timestamp' );
		$cutoff_date    = gmdate( 'Y-m-d H:i:s', $now_time - ( $cutoff_minutes * 60 ) );

		// 1. Pasar carritos 'in_progress' a 'abandoned' si han superado el tiempo de corte
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$this->table_name} SET status = 'abandoned' WHERE status = 'in_progress' AND last_modified <= %s",
			$cutoff_date
		) );

		// 2. Obtener secuencias de correos configuradas
		$sequences  = $this->get_email_sequences_config( $settings );
		$batch_size = isset( $settings['wpat_ac_batch_size'] ) ? max( 5, min( 100, intval( $settings['wpat_ac_batch_size'] ) ) ) : 15;

		// 3. Procesar carritos abandonados en lotes controlados (por paquetes para no sobrecargar el servidor)
		$abandoned_carts = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE status = 'abandoned' AND emails_sent < 3 ORDER BY last_modified ASC LIMIT %d",
			$batch_size
		) );

		if ( empty( $abandoned_carts ) ) {
			$this->auto_prune_old_records( $settings );
			return;
		}

		foreach ( $abandoned_carts as $cart ) {
			$emails_sent  = intval( $cart->emails_sent );
			$next_step    = $emails_sent + 1; // 1, 2 o 3

			if ( ! isset( $sequences[ $next_step ] ) || ! $sequences[ $next_step ]['enabled'] ) {
				continue;
			}

			$step_config    = $sequences[ $next_step ];
			$delay_seconds  = $step_config['delay_seconds'];
			$cart_time      = strtotime( $cart->last_modified );
			$time_passed    = $now_time - $cart_time;

			// Verificar si ha transcurrido el tiempo requerido para este correo
			if ( $time_passed >= $delay_seconds ) {
				$sent = $this->send_recovery_email( $cart, $step_config, $next_step );
				if ( $sent ) {
					$history = json_decode( $cart->email_history, true );
					if ( ! is_array( $history ) ) {
						$history = array();
					}
					$history[] = array(
						'step'    => $next_step,
						'sent_at' => current_time( 'mysql' ),
					);

					$wpdb->update(
						$this->table_name,
						array(
							'emails_sent'   => $next_step,
							'email_history' => wp_json_encode( $history ),
							'coupon_code'   => ! empty( $step_config['coupon_code'] ) ? $step_config['coupon_code'] : $cart->coupon_code,
						),
						array( 'id' => $cart->id ),
						array( '%d', '%s', '%s' ),
						array( '%d' )
					);
				}
			}
		}

		// 4. Limpieza de registros antiguos
		$this->auto_prune_old_records( $settings );
	}

	/**
	 * Devuelve la configuración de las 3 etapas de correos con conversión de tiempo a segundos.
	 */
	public function get_email_sequences_config( $settings ) {
		$sequences = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$enabled_default = ( 1 === $i ) ? '1' : ( ( 2 === $i ) ? '1' : '0' );
			$enabled         = isset( $settings[ "wpat_ac_email_{$i}_enabled" ] ) ? ( '1' === $settings[ "wpat_ac_email_{$i}_enabled" ] ) : ( '1' === $enabled_default );

			// Tiempos por defecto: Email 1 = 1 hora; Email 2 = 24 horas; Email 3 = 72 horas
			$def_value = ( 1 === $i ) ? 1 : ( ( 2 === $i ) ? 24 : 72 );
			$def_unit  = ( 1 === $i ) ? 'hours' : ( ( 2 === $i ) ? 'hours' : 'hours' );

			$val  = isset( $settings[ "wpat_ac_email_{$i}_delay_val" ] ) ? max( 1, intval( $settings[ "wpat_ac_email_{$i}_delay_val" ] ) ) : $def_value;
			$unit = isset( $settings[ "wpat_ac_email_{$i}_delay_unit" ] ) ? $settings[ "wpat_ac_email_{$i}_delay_unit" ] : $def_unit;

			$seconds = $val * 60;
			if ( 'hours' === $unit ) {
				$seconds = $val * 3600;
			} elseif ( 'days' === $unit ) {
				$seconds = $val * 86400;
			}

			// Asuntos y mensajes por defecto
			$def_subject = ( 1 === $i ) ? '¿Olvidaste algo? Tu carrito te está esperando' : ( ( 2 === $i ) ? '¿Aún interesado? Tus artículos se están agotando' : '¡Última oportunidad! 10% de descuento en tu carrito' );
			$def_heading = ( 1 === $i ) ? 'Has dejado artículos en tu carrito' : ( ( 2 === $i ) ? 'Tu carrito está a punto de caducar' : 'Te regalamos un cupón exclusivo para completar tu compra' );
			$def_message = ( 1 === $i ) 
				? 'Hola {customer_name}, notamos que agregaste productos a tu carrito pero no completaste tu pedido. No te preocupes, los hemos guardado para ti para que puedas continuar cuando quieras.'
				: ( ( 2 === $i )
					? 'Hola {customer_name}, los productos que guardaste en tu carrito tienen alta demanda. Haz clic en el botón de abajo para recuperarlos antes de que se agoten las existencias.'
					: 'Hola {customer_name}, queremos que disfrutes de tu compra. Por eso hemos creado un cupón exclusivo para ti con descuento que puedes aplicar inmediatamente.' );

			$subject = ! empty( $settings[ "wpat_ac_email_{$i}_subject" ] ) ? $settings[ "wpat_ac_email_{$i}_subject" ] : $def_subject;
			$heading = ! empty( $settings[ "wpat_ac_email_{$i}_heading" ] ) ? $settings[ "wpat_ac_email_{$i}_heading" ] : $def_heading;
			$message = ! empty( $settings[ "wpat_ac_email_{$i}_message" ] ) ? $settings[ "wpat_ac_email_{$i}_message" ] : $def_message;

			// Cupones dinámicos
			$coupon_enabled = ! empty( $settings[ "wpat_ac_email_{$i}_coupon_enable" ] ) && '1' === $settings[ "wpat_ac_email_{$i}_coupon_enable" ];
			$coupon_type    = isset( $settings[ "wpat_ac_email_{$i}_coupon_type" ] ) ? $settings[ "wpat_ac_email_{$i}_coupon_type" ] : 'percent';
			$coupon_amount  = isset( $settings[ "wpat_ac_email_{$i}_coupon_amount" ] ) ? floatval( $settings[ "wpat_ac_email_{$i}_coupon_amount" ] ) : 10;
			$coupon_expiry  = isset( $settings[ "wpat_ac_email_{$i}_coupon_expiry" ] ) ? intval( $settings[ "wpat_ac_email_{$i}_coupon_expiry" ] ) : 48;

			$sequences[ $i ] = array(
				'enabled'        => $enabled,
				'delay_val'      => $val,
				'delay_unit'     => $unit,
				'delay_seconds'  => $seconds,
				'subject'        => $subject,
				'heading'        => $heading,
				'message'        => $message,
				'coupon_enabled' => $coupon_enabled,
				'coupon_type'    => $coupon_type,
				'coupon_amount'  => $coupon_amount,
				'coupon_expiry'  => $coupon_expiry,
				'button_text'    => ! empty( $settings[ "wpat_ac_email_{$i}_btn_text" ] ) ? $settings[ "wpat_ac_email_{$i}_btn_text" ] : 'Recuperar mi pedido →',
			);
		}

		return $sequences;
	}

	/**
	 * Genera un cupón de un solo uso en WooCommerce para el correo de recuperación.
	 */
	public function generate_recovery_coupon( $cart, $step_config ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return '';
		}

		$amount = $step_config['coupon_amount'];
		$type   = $step_config['coupon_type']; // percent o fixed_cart
		$expiry = max( 1, $step_config['coupon_expiry'] ); // en horas

		$code = 'REC-' . strtoupper( wp_generate_password( 6, false ) );

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( $amount );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_date_expires( time() + ( $expiry * 3600 ) );
		$coupon->set_description( 'Cupón automático de recuperación de carrito para ' . $cart->user_email );
		$coupon->save();

		return $code;
	}

	/**
	 * Envía el correo de recuperación a un carrito específico.
	 */
	public function send_recovery_email( $cart, $step_config, $step_index ) {
		$to = sanitize_email( $cart->user_email );
		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$site_name  = get_bloginfo( 'name' );
		$site_url   = home_url( '/' );
		$cart_items = json_decode( $cart->cart_contents, true );
		if ( ! is_array( $cart_items ) ) {
			$cart_items = array();
		}

		$customer_name = ! empty( $cart->user_name ) ? $cart->user_name : 'Cliente';
		$cart_total    = wc_price( $cart->cart_total );

		// Generar cupón si está configurado en esta etapa
		$coupon_code   = '';
		$discount_text = '';
		if ( $step_config['coupon_enabled'] ) {
			$coupon_code = $this->generate_recovery_coupon( $cart, $step_config );
			$step_config['coupon_code'] = $coupon_code;
			$discount_text = ( 'percent' === $step_config['coupon_type'] )
				? $step_config['coupon_amount'] . '% DE DESCUENTO'
				: wc_price( $step_config['coupon_amount'] ) . ' DE DESCUENTO';
		}

		$recovery_url = add_query_arg( 'wpat_recover_cart', $cart->recovery_token, home_url( '/' ) );

		// Reemplazar variables dinámicas
		$find = array( '{customer_name}', '{cart_total}', '{site_title}', '{coupon_code}', '{discount_value}' );
		$replace = array(
			$customer_name,
			$cart_total,
			$site_name,
			$coupon_code,
			$discount_text,
		);

		$subject       = str_replace( $find, $replace, $step_config['subject'] );
		$email_title   = str_replace( $find, $replace, $step_config['heading'] );
		$email_content = str_replace( $find, $replace, $step_config['message'] );
		$button_text   = str_replace( $find, $replace, $step_config['button_text'] );
		$button_color  = ! empty( $settings['wpat_ac_email_btn_color'] ) ? $settings['wpat_ac_email_btn_color'] : '#2563eb';
		$logo_url      = ! empty( $settings['wpat_ac_email_logo'] ) ? $settings['wpat_ac_email_logo'] : '';
		$footer_text   = ! empty( $settings['wpat_ac_email_footer'] ) ? $settings['wpat_ac_email_footer'] : '';

		// Renderizar la plantilla HTML
		ob_start();
		$template = WPAT_PATH . 'templates/emails/abandoned-cart-email.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		$email_html = ob_get_clean();

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $to, $subject, $email_html, $headers );
	}

	/**
	 * Envía un correo de prueba desde el panel de administración.
	 */
	public function ajax_send_test_email() {
		check_ajax_referer( 'wpat_save_settings_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ) );
		}

		$to_email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : get_option( 'admin_email' );
		$step_index = isset( $_POST['step'] ) ? max( 1, min( 3, intval( $_POST['step'] ) ) ) : 1;

		if ( empty( $to_email ) || ! is_email( $to_email ) ) {
			wp_send_json_error( array( 'message' => 'Por favor introduce un email válido.' ) );
		}

		$settings  = WPAT_Main::get_instance()->get_settings();
		$sequences = $this->get_email_sequences_config( $settings );
		$config    = isset( $sequences[ $step_index ] ) ? $sequences[ $step_index ] : $sequences[1];

		// Objeto falso de carrito de prueba
		$dummy_cart = (object) array(
			'user_email'     => $to_email,
			'user_name'      => 'Juan Pérez (Prueba)',
			'cart_total'     => 79.90,
			'recovery_token' => 'test-preview-token',
			'coupon_code'    => 'OFERTA10',
			'cart_contents'  => wp_json_encode( array(
				array(
					'name'       => 'Producto de Ejemplo para Tienda',
					'quantity'   => 1,
					'price_html' => '49,90 €',
					'image_url'  => 'https://picsum.photos/120/120?random=1',
					'meta'       => 'Talla: M, Color: Azul',
				),
				array(
					'name'       => 'Accesorio Premium Adicional',
					'quantity'   => 2,
					'price_html' => '15,00 €',
					'image_url'  => 'https://picsum.photos/120/120?random=2',
					'meta'       => '',
				),
			) ),
		);

		$sent = $this->send_recovery_email( $dummy_cart, $config, $step_index );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => "¡Correo de prueba de la Etapa {$step_index} enviado con éxito a {$to_email}!" ) );
		} else {
			wp_send_json_error( array( 'message' => 'No se pudo enviar el correo. Revisa la configuración de correo de tu WordPress o plugin SMTP.' ) );
		}
	}

	/**
	 * Elimina un registro de carrito abandonado desde el panel de administración.
	 */
	public function ajax_delete_cart_record() {
		check_ajax_referer( 'wpat_save_settings_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ) );
		}

		$cart_id = isset( $_POST['cart_id'] ) ? intval( $_POST['cart_id'] ) : 0;
		if ( $cart_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'ID de carrito no válido.' ) );
		}

		global $wpdb;
		$wpdb->delete( $this->table_name, array( 'id' => $cart_id ), array( '%d' ) );

		wp_send_json_success( array( 'message' => 'Registro eliminado con éxito.' ) );
	}

	/**
	 * Limpieza automática de registros antiguos.
	 */
	public function auto_prune_old_records( $settings ) {
		global $wpdb;
		$prune_days = isset( $settings['wpat_ac_prune_days'] ) ? max( 7, intval( $settings['wpat_ac_prune_days'] ) ) : 30;
		$old_date   = gmdate( 'Y-m-d H:i:s', time() - ( $prune_days * 86400 ) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE last_modified < %s",
			$old_date
		) );
	}

	/**
	 * Obtiene el resumen de métricas y analíticas para el panel de administración.
	 *
	 * @return array
	 */
	public function get_analytics_summary() {
		global $wpdb;

		$total_abandoned = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status IN ('abandoned', 'recovered')" );
		$total_recovered = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'recovered'" );
		$revenue_recovered = (float) $wpdb->get_var( "SELECT SUM(cart_total) FROM {$this->table_name} WHERE status = 'recovered'" );

		$rate = ( $total_abandoned > 0 ) ? round( ( $total_recovered / $total_abandoned ) * 100, 1 ) : 0;

		return array(
			'total_abandoned'   => $total_abandoned,
			'total_recovered'   => $total_recovered,
			'revenue_recovered' => $revenue_recovered,
			'recovery_rate'     => $rate,
		);
	}

	/**
	 * Obtiene los carritos recientes para la tabla de administración.
	 *
	 * @param int $limit
	 * @param string $status
	 * @return array
	 */
	public function get_recent_carts( $limit = 20, $status = '' ) {
		global $wpdb;
		$where = '';
		if ( ! empty( $status ) ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} {$where} ORDER BY last_modified DESC LIMIT %d",
			$limit
		) );
	}
}

<?php
/**
 * Módulo: Venta Directa & Pagos Rápidos sin WooCommerce (Quick Pay)
 *
 * Permite vender productos digitales, servicios, membresías y productos físicos
 * directamente mediante botones de compra, enlaces de pago y formularios emergentes
 * con Stripe, Redsys TPV, Bizum, PayPal y Transferencia, con facturación PDF y cupones.
 *
 * @package WP_Agency_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAT_Quick_Pay {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_Quick_Pay|null
	 */
	private static $instance = null;

	/**
	 * Nombre de la tabla de pedidos en la BD.
	 *
	 * @var string
	 */
	private $table_orders;

	/**
	 * Obtiene la instancia única.
	 *
	 * @return WPAT_Quick_Pay
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado.
	 */
	private function __construct() {
		global $wpdb;
		$this->table_orders = $wpdb->prefix . 'wpat_quick_pay_orders';

		// Inicialización de la base de datos
		add_action( 'init', array( $this, 'maybe_create_tables' ) );

		// Shortcodes
		add_shortcode( 'wpat_pay', array( $this, 'render_pay_shortcode' ) );
		add_shortcode( 'wpat_buy_button', array( $this, 'render_pay_shortcode' ) );
		add_shortcode( 'wpat_quick_pay', array( $this, 'render_pay_shortcode' ) );

		// Enqueue de scripts y estilos frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Endpoints AJAX Frontend
		add_action( 'wp_ajax_wpat_qp_get_checkout_data', array( $this, 'ajax_get_checkout_data' ) );
		add_action( 'wp_ajax_nopriv_wpat_qp_get_checkout_data', array( $this, 'ajax_get_checkout_data' ) );

		add_action( 'wp_ajax_wpat_qp_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
		add_action( 'wp_ajax_nopriv_wpat_qp_apply_coupon', array( $this, 'ajax_apply_coupon' ) );

		add_action( 'wp_ajax_wpat_qp_create_stripe_session', array( $this, 'ajax_create_stripe_session' ) );
		add_action( 'wp_ajax_nopriv_wpat_qp_create_stripe_session', array( $this, 'ajax_create_stripe_session' ) );

		add_action( 'wp_ajax_wpat_qp_process_manual_order', array( $this, 'ajax_process_manual_order' ) );
		add_action( 'wp_ajax_nopriv_wpat_qp_process_manual_order', array( $this, 'ajax_process_manual_order' ) );

		add_action( 'wp_ajax_wpat_qp_process_redsys_order', array( $this, 'ajax_process_redsys_order' ) );
		add_action( 'wp_ajax_nopriv_wpat_qp_process_redsys_order', array( $this, 'ajax_process_redsys_order' ) );

		add_action( 'wp_ajax_wpat_qp_process_paypal_order', array( $this, 'ajax_process_paypal_order' ) );
		add_action( 'wp_ajax_nopriv_wpat_qp_process_paypal_order', array( $this, 'ajax_process_paypal_order' ) );

		// Endpoints AJAX Administración
		add_action( 'wp_ajax_wpat_qp_admin_save_product', array( $this, 'ajax_admin_save_product' ) );
		add_action( 'wp_ajax_wpat_qp_admin_delete_product', array( $this, 'ajax_admin_delete_product' ) );
		add_action( 'wp_ajax_wpat_qp_admin_save_coupon', array( $this, 'ajax_admin_save_coupon' ) );
		add_action( 'wp_ajax_wpat_qp_admin_delete_coupon', array( $this, 'ajax_admin_delete_coupon' ) );
		add_action( 'wp_ajax_wpat_qp_admin_update_order_status', array( $this, 'ajax_admin_update_order_status' ) );
		add_action( 'wp_ajax_wpat_qp_admin_delete_order', array( $this, 'ajax_admin_delete_order' ) );
		add_action( 'wp_ajax_wpat_qp_admin_export_csv', array( $this, 'ajax_admin_export_csv' ) );
		add_action( 'wp_ajax_wpat_qp_admin_download_pdf', array( $this, 'ajax_admin_download_pdf' ) );

		// Webhooks y URLs de Retorno
		add_action( 'template_redirect', array( $this, 'handle_payment_return_and_webhooks' ) );

		// Descarga segura de archivo digital
		add_action( 'template_redirect', array( $this, 'handle_secure_digital_download' ) );
	}

	/**
	 * Crea la tabla de pedidos si no existe en la base de datos o añade nuevas columnas.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$table_name = $this->table_orders;
		$charset_collate = $wpdb->get_charset_collate();

		$current_db_version = get_option( 'wpat_quick_pay_db_ver', '0' );
		$target_version = '1.1.0';

		if ( $current_db_version !== $target_version || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$sql = "CREATE TABLE $table_name (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_key varchar(64) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				product_id varchar(64) NOT NULL DEFAULT '',
				product_name varchar(255) NOT NULL DEFAULT '',
				product_type varchar(50) NOT NULL DEFAULT 'service',
				amount decimal(10,2) NOT NULL DEFAULT 0.00,
				currency varchar(10) NOT NULL DEFAULT 'EUR',
				customer_name varchar(255) NOT NULL DEFAULT '',
				customer_email varchar(255) NOT NULL DEFAULT '',
				customer_phone varchar(50) NOT NULL DEFAULT '',
				customer_dni varchar(50) NOT NULL DEFAULT '',
				billing_company varchar(255) DEFAULT '',
				billing_address text DEFAULT NULL,
				billing_city varchar(100) DEFAULT NULL,
				billing_postcode varchar(20) DEFAULT NULL,
				billing_state varchar(100) DEFAULT NULL,
				billing_country varchar(100) DEFAULT 'ES',
				shipping_address text DEFAULT NULL,
				shipping_city varchar(100) DEFAULT NULL,
				shipping_postcode varchar(20) DEFAULT NULL,
				shipping_state varchar(100) DEFAULT NULL,
				shipping_country varchar(100) DEFAULT 'ES',
				shipping_cost decimal(10,2) NOT NULL DEFAULT 0.00,
				tax_rate decimal(5,2) NOT NULL DEFAULT 0.00,
				tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
				coupon_code varchar(50) NOT NULL DEFAULT '',
				discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
				gateway varchar(50) NOT NULL DEFAULT '',
				transaction_id varchar(255) NOT NULL DEFAULT '',
				status varchar(50) NOT NULL DEFAULT 'pending',
				is_recurring tinyint(1) NOT NULL DEFAULT 0,
				subscription_id varchar(255) NOT NULL DEFAULT '',
				download_token varchar(64) NOT NULL DEFAULT '',
				download_count int(11) NOT NULL DEFAULT 0,
				invoice_number varchar(64) NOT NULL DEFAULT '',
				metadata longtext DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY order_key (order_key),
				KEY customer_email (customer_email),
				KEY status (status)
			) $charset_collate;";

			dbDelta( $sql );

			// Verificación de columnas de facturación por si la tabla ya existía
			$existing_columns = $wpdb->get_col( "DESCRIBE $table_name", 0 );
			if ( is_array( $existing_columns ) ) {
				$new_columns = array(
					'billing_company'  => "VARCHAR(255) DEFAULT '' AFTER customer_dni",
					'billing_address'  => "TEXT DEFAULT NULL AFTER billing_company",
					'billing_city'     => "VARCHAR(100) DEFAULT NULL AFTER billing_address",
					'billing_postcode' => "VARCHAR(20) DEFAULT NULL AFTER billing_city",
					'billing_state'    => "VARCHAR(100) DEFAULT NULL AFTER billing_postcode",
					'billing_country'  => "VARCHAR(100) DEFAULT 'ES' AFTER billing_state",
					'shipping_state'   => "VARCHAR(100) DEFAULT NULL AFTER shipping_postcode",
					'shipping_country' => "VARCHAR(100) DEFAULT 'ES' AFTER shipping_state",
				);

				foreach ( $new_columns as $col => $col_def ) {
					if ( ! in_array( $col, $existing_columns, true ) ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
						$wpdb->query( "ALTER TABLE $table_name ADD $col $col_def" );
					}
				}
			}

			update_option( 'wpat_quick_pay_db_ver', $target_version );
		}
	}

	/**
	 * Encola estilos y scripts para el frontend.
	 */
	public function enqueue_frontend_assets() {
		$settings = WPAT_Main::get_instance()->get_settings();

		if ( empty( $settings['quick-pay'] ) || '1' !== $settings['quick-pay'] ) {
			return;
		}

		wp_enqueue_style(
			'wpat-quick-pay-css',
			WPAT_URL . 'assets/css/wpat-quick-pay.css',
			array(),
			WPAT_VERSION
		);

		wp_enqueue_script(
			'wpat-quick-pay-js',
			WPAT_URL . 'assets/js/wpat-quick-pay.js',
			array( 'jquery' ),
			WPAT_VERSION,
			true
		);

		$stripe_mode = isset( $settings['qp_stripe_mode'] ) ? $settings['qp_stripe_mode'] : 'test';
		$stripe_pub_key = ( 'live' === $stripe_mode ) ? ( isset( $settings['qp_stripe_live_pub_key'] ) ? $settings['qp_stripe_live_pub_key'] : '' ) : ( isset( $settings['qp_stripe_test_pub_key'] ) ? $settings['qp_stripe_test_pub_key'] : '' );

		wp_localize_script(
			'wpat-quick-pay-js',
			'wpatQuickPay',
			array(
				'ajax_url'                => admin_url( 'admin-ajax.php' ),
				'nonce'                   => wp_create_nonce( 'wpat_quick_pay_nonce' ),
				'stripe_pub_key'          => esc_js( $stripe_pub_key ),
				'currency_symbol'         => esc_js( isset( $settings['qp_currency_symbol'] ) ? $settings['qp_currency_symbol'] : '€' ),
				'currency_pos'            => esc_js( isset( $settings['qp_currency_pos'] ) ? $settings['qp_currency_pos'] : 'right' ),
				'primary_color'           => esc_js( isset( $settings['qp_primary_color'] ) ? $settings['qp_primary_color'] : '#2563eb' ),
				'bizum_type'              => esc_js( isset( $settings['qp_bizum_type'] ) ? $settings['qp_bizum_type'] : 'redsys' ),
				'require_billing_address' => ! empty( $settings['qp_require_billing_address'] ) ? 1 : 0,
				'require_phone'           => ! empty( $settings['qp_require_phone'] ) ? 1 : 0,
				'require_dni'             => ! empty( $settings['qp_require_dni'] ) ? 1 : 0,
				'i18n'                    => array(
					'loading'        => 'Procesando...',
					'apply'          => 'Aplicar',
					'coupon_applied' => '¡Cupón aplicado con éxito!',
					'coupon_invalid' => 'El código de cupón no es válido o ha expirado.',
					'field_required' => 'Por favor completa todos los campos obligatorios.',
					'invalid_email'  => 'Introduce una dirección de correo electrónico válida.',
					'redirecting'    => 'Redirigiendo a la pasarela de pago segura...',
					'order_success'  => '¡Pedido registrado con éxito!',
				),
			)
		);
	}

	/**
	 * Obtiene los productos configurados.
	 *
	 * @return array
	 */
	public static function get_products() {
		$saved    = get_option( 'wpat_settings', array() );
		$products = isset( $saved['qp_products'] ) && is_array( $saved['qp_products'] ) ? $saved['qp_products'] : array();

		// Si no hay productos, proveer uno de demostración
		if ( empty( $products ) ) {
			$demo_id = 'prod_demo_1';
			$products[ $demo_id ] = array(
				'id'               => $demo_id,
				'name'             => 'Consultoría Estratégica 1 a 1',
				'price'            => 99.00,
				'currency'         => 'EUR',
				'type'             => 'service', // service, digital, physical
				'desc'             => 'Sesión de 60 minutos de auditoría y estrategia digital para tu negocio.',
				'image_url'        => '',
				'download_file'    => '',
				'shipping_cost'    => 0.00,
				'tax_rate'         => 21.00,
				'button_text'      => 'Reservar y Pagar (99 €)',
				'is_recurring'     => 0,
				'billing_interval' => 'month',
				'require_phone'    => 1,
				'require_dni'      => 1,
			);
		}

		return $products;
	}

	/**
	 * Obtiene un producto por su ID.
	 *
	 * @param string $product_id ID del producto.
	 * @return array|null
	 */
	public static function get_product( $product_id ) {
		if ( empty( $product_id ) ) {
			return null;
		}

		$products = self::get_products();
		if ( isset( $products[ $product_id ] ) ) {
			return $products[ $product_id ];
		}

		// Buscar en transient si es un producto dinámico
		$transient = get_transient( 'wpat_qp_' . $product_id );
		if ( is_array( $transient ) && ! empty( $transient ) ) {
			return $transient;
		}

		return null;
	}

	/**
	 * Resuelve un producto por ID o por payload de respaldo.
	 *
	 * @param string $product_id      ID del producto.
	 * @param mixed  $product_payload JSON string o array del producto.
	 * @return array|null
	 */
	public static function resolve_product( $product_id, $product_payload = '' ) {
		$product = ! empty( $product_id ) ? self::get_product( $product_id ) : null;

		if ( ! $product && ! empty( $product_payload ) ) {
			$decoded = is_string( $product_payload ) ? json_decode( stripslashes( $product_payload ), true ) : $product_payload;
			if ( is_array( $decoded ) && ! empty( $decoded['name'] ) && isset( $decoded['price'] ) ) {
				$p_id = ! empty( $decoded['id'] ) ? sanitize_key( $decoded['id'] ) : ( $product_id ? sanitize_key( $product_id ) : 'custom_' . sanitize_key( $decoded['name'] ) );
				$product = array(
					'id'               => $p_id,
					'name'             => sanitize_text_field( $decoded['name'] ),
					'price'            => floatval( $decoded['price'] ),
					'currency'         => ! empty( $decoded['currency'] ) ? sanitize_text_field( $decoded['currency'] ) : 'EUR',
					'type'             => ! empty( $decoded['type'] ) ? sanitize_key( $decoded['type'] ) : 'service',
					'desc'             => ! empty( $decoded['desc'] ) ? sanitize_textarea_field( $decoded['desc'] ) : '',
					'image_url'        => ! empty( $decoded['image_url'] ) ? esc_url_raw( $decoded['image_url'] ) : '',
					'download_file'    => ! empty( $decoded['download_file'] ) ? esc_url_raw( $decoded['download_file'] ) : '',
					'shipping_cost'    => isset( $decoded['shipping_cost'] ) ? floatval( $decoded['shipping_cost'] ) : 0.0,
					'tax_rate'         => isset( $decoded['tax_rate'] ) ? floatval( $decoded['tax_rate'] ) : 21.0,
					'button_text'      => ! empty( $decoded['button_text'] ) ? sanitize_text_field( $decoded['button_text'] ) : '',
					'is_recurring'     => ! empty( $decoded['is_recurring'] ) ? 1 : 0,
					'billing_interval' => ! empty( $decoded['billing_interval'] ) ? sanitize_key( $decoded['billing_interval'] ) : 'month',
					'require_phone'    => 1,
					'require_dni'      => 1,
				);
				set_transient( 'wpat_qp_' . $p_id, $product, DAY_IN_SECONDS );
			}
		}

		if ( ! $product ) {
			$all = self::get_products();
			if ( ! empty( $all ) ) {
				$product = reset( $all );
			}
		}

		return $product;
	}

	/**
	 * Obtiene los cupones configurados.
	 *
	 * @return array
	 */
	public static function get_coupons() {
		$saved = get_option( 'wpat_settings', array() );
		return isset( $saved['qp_coupons'] ) && is_array( $saved['qp_coupons'] ) ? $saved['qp_coupons'] : array();
	}

	/**
	 * Renderiza el shortcode [wpat_pay id="prod_xxx"].
	 *
	 * @param array $atts Atributos del shortcode.
	 * @return string
	 */
	public function render_pay_shortcode( $atts ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['quick-pay'] ) || '1' !== $settings['quick-pay'] ) {
			return '';
		}

		$a = shortcode_atts(
			array(
				'id'               => '',
				'name'             => '',
				'title'            => '',
				'amount'           => '',
				'price'            => '',
				'currency'         => 'EUR',
				'type'             => 'service',
				'layout'           => 'button', // 'button', 'card', 'box', 'inline'
				'button_text'      => '',
				'btn_text'         => '',
				'btn_class'        => '',
				'class'            => '',
				'shipping'         => '0',
				'shipping_cost'    => '',
				'tax'              => '21',
				'tax_rate'         => '',
				'color'            => '',
				'bg_color'         => '',
				'desc'             => '',
				'description'      => '',
				'image'            => '',
				'image_url'        => '',
				'file'             => '',
				'download_file'    => '',
				'recurring'        => '0',
				'is_recurring'     => '0',
				'interval'         => 'month',
				'billing_interval' => 'month',
			),
			$atts,
			'wpat_pay'
		);

		$p_id         = ! empty( $a['id'] ) ? sanitize_key( $a['id'] ) : '';
		$name         = ! empty( $a['name'] ) ? sanitize_text_field( $a['name'] ) : ( ! empty( $a['title'] ) ? sanitize_text_field( $a['title'] ) : '' );
		$price_raw    = '' !== $a['price'] ? $a['price'] : ( '' !== $a['amount'] ? $a['amount'] : '' );
		$shipping_raw = '' !== $a['shipping_cost'] ? $a['shipping_cost'] : $a['shipping'];
		$tax_raw      = '' !== $a['tax_rate'] ? $a['tax_rate'] : $a['tax'];
		$btn_text     = ! empty( $a['button_text'] ) ? sanitize_text_field( $a['button_text'] ) : ( ! empty( $a['btn_text'] ) ? sanitize_text_field( $a['btn_text'] ) : '' );
		$btn_class    = ! empty( $a['btn_class'] ) ? sanitize_html_class( $a['btn_class'] ) : ( ! empty( $a['class'] ) ? sanitize_html_class( $a['class'] ) : '' );
		$color        = ! empty( $a['color'] ) ? sanitize_hex_color( $a['color'] ) : ( ! empty( $a['bg_color'] ) ? sanitize_hex_color( $a['bg_color'] ) : '' );
		$desc         = ! empty( $a['desc'] ) ? sanitize_textarea_field( $a['desc'] ) : ( ! empty( $a['description'] ) ? sanitize_textarea_field( $a['description'] ) : '' );
		$image        = ! empty( $a['image_url'] ) ? esc_url_raw( $a['image_url'] ) : ( ! empty( $a['image'] ) ? esc_url_raw( $a['image'] ) : '' );
		$file         = ! empty( $a['download_file'] ) ? esc_url_raw( $a['download_file'] ) : ( ! empty( $a['file'] ) ? esc_url_raw( $a['file'] ) : '' );
		$recurring    = ( ! empty( $a['recurring'] ) || ! empty( $a['is_recurring'] ) ) ? 1 : 0;
		$interval     = ! empty( $a['interval'] ) ? sanitize_key( $a['interval'] ) : sanitize_key( $a['billing_interval'] );

		$product = null;
		if ( ! empty( $p_id ) ) {
			$product = self::get_product( $p_id );
		}

		if ( $product ) {
			if ( ! empty( $name ) ) {
				$product['name'] = $name;
			}
			if ( '' !== $price_raw ) {
				$product['price'] = floatval( $price_raw );
			}
			if ( ! empty( $desc ) ) {
				$product['desc'] = $desc;
			}
			if ( ! empty( $image ) ) {
				$product['image_url'] = $image;
			}
			if ( ! empty( $file ) ) {
				$product['download_file'] = $file;
			}
			if ( ! empty( $btn_text ) ) {
				$product['button_text'] = $btn_text;
			}
		} else {
			if ( '' !== $price_raw || ! empty( $name ) ) {
				$custom_id = ! empty( $p_id ) ? $p_id : ( 'custom_' . ( $name ? sanitize_key( $name ) : wp_generate_password( 6, false, false ) ) );
				$product = array(
					'id'               => $custom_id,
					'name'             => ! empty( $name ) ? $name : 'Servicio / Producto',
					'price'            => floatval( $price_raw > 0 ? $price_raw : 10.0 ),
					'currency'         => sanitize_text_field( $a['currency'] ),
					'type'             => sanitize_key( $a['type'] ),
					'desc'             => $desc,
					'image_url'        => $image,
					'download_file'    => $file,
					'shipping_cost'    => floatval( $shipping_raw ),
					'tax_rate'         => floatval( $tax_raw ),
					'button_text'      => $btn_text,
					'is_recurring'     => $recurring,
					'billing_interval' => $interval,
					'require_phone'    => 1,
					'require_dni'      => 0,
				);
				set_transient( 'wpat_qp_' . $product['id'], $product, DAY_IN_SECONDS );
			} else {
				$all_prods = self::get_products();
				$product   = reset( $all_prods );
			}
		}

		if ( ! $product ) {
			return '<div class="wpat-qp-error" style="color:red; font-size:12px;">Producto no encontrado.</div>';
		}

		$curr_sym  = isset( $settings['qp_currency_symbol'] ) ? $settings['qp_currency_symbol'] : '€';
		$curr_pos  = isset( $settings['qp_currency_pos'] ) ? $settings['qp_currency_pos'] : 'right';
		$formatted_price = ( 'left' === $curr_pos ) ? $curr_sym . number_format( $product['price'], 2, ',', '.' ) : number_format( $product['price'], 2, ',', '.' ) . ' ' . $curr_sym;

		$button_display_text = ! empty( $product['button_text'] ) ? $product['button_text'] : ( ! empty( $btn_text ) ? $btn_text : 'Comprar por ' . $formatted_price );
		$layout              = ! empty( $a['layout'] ) ? $a['layout'] : 'button';
		$btn_color           = ! empty( $color ) ? $color : ( isset( $settings['qp_primary_color'] ) ? $settings['qp_primary_color'] : '#2563eb' );
		$payload_json        = wp_json_encode( $product );

		ob_start();

		if ( 'card' === $layout || 'box' === $layout ) {
			?>
			<div class="wpat-qp-product-card" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-product-payload="<?php echo esc_attr( $payload_json ); ?>" style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; max-width: 380px; background: #fff; box-shadow: 0 4px 15px -2px rgba(0,0,0,0.06); text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
				<?php if ( ! empty( $product['image_url'] ) ) : ?>
					<div class="wpat-qp-card-image" style="margin-bottom: 16px; border-radius: 8px; overflow: hidden; max-height: 200px;">
						<img src="<?php echo esc_url( $product['image_url'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" style="width: 100%; height: auto; display: block; object-fit: cover;" />
					</div>
				<?php endif; ?>

				<div class="wpat-qp-card-badge" style="display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #eff6ff; color: #1d4ed8; padding: 3px 8px; border-radius: 20px; margin-bottom: 10px;">
					<?php echo ( 'digital' === $product['type'] ) ? '📥 Descarga Digital' : ( ( 'physical' === $product['type'] ) ? '📦 Envío Físico' : '🎓 Servicio / Acceso' ); ?>
				</div>

				<h3 class="wpat-qp-card-title" style="margin: 0 0 8px 0; font-size: 19px; font-weight: 800; color: #0f172a; line-height: 1.3;">
					<?php echo esc_html( $product['name'] ); ?>
				</h3>

				<?php if ( ! empty( $product['desc'] ) ) : ?>
					<p class="wpat-qp-card-desc" style="margin: 0 0 16px 0; font-size: 13.5px; color: #64748b; line-height: 1.5;">
						<?php echo esc_html( $product['desc'] ); ?>
					</p>
				<?php endif; ?>

				<div class="wpat-qp-card-price" style="margin-bottom: 20px;">
					<span style="font-size: 32px; font-weight: 900; color: #0f172a;"><?php echo esc_html( $formatted_price ); ?></span>
					<?php if ( ! empty( $product['is_recurring'] ) ) : ?>
						<span style="font-size: 13px; color: #64748b; font-weight: 600;">/ <?php echo ( 'year' === $product['billing_interval'] ) ? 'año' : 'mes'; ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $product['shipping_cost'] ) && floatval( $product['shipping_cost'] ) > 0 ) : ?>
						<div style="font-size: 12px; color: #64748b; margin-top: 4px;">+ <?php echo esc_html( number_format( $product['shipping_cost'], 2, ',', '.' ) . ' ' . $curr_sym ); ?> de gastos de envío</div>
					<?php endif; ?>
				</div>

				<button type="button" class="wpat-qp-trigger-btn <?php echo esc_attr( $btn_class ); ?>" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-product-payload="<?php echo esc_attr( $payload_json ); ?>" style="width: 100%; background: <?php echo esc_attr( $btn_color ); ?>; color: #ffffff; border: none; border-radius: 8px; padding: 13px 22px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(37,99,235,0.25); display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
					<span class="dashicons dashicons-cart" style="font-size: 18px; width: 18px; height: 18px; line-height: 1;"></span>
					<span><?php echo esc_html( $button_display_text ); ?></span>
				</button>
				<div style="margin-top: 10px; font-size: 11px; color: #94a3b8; display: flex; align-items: center; justify-content: center; gap: 5px;">
					<span>🔒 Pago 100% Seguro SSL</span>
				</div>
			</div>
			<?php
		} else {
			// Botón Simple
			?>
			<button type="button" class="wpat-qp-trigger-btn <?php echo esc_attr( $btn_class ); ?>" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-product-payload="<?php echo esc_attr( $payload_json ); ?>" style="background: <?php echo esc_attr( $btn_color ); ?>; color: #ffffff; border: none; border-radius: 8px; padding: 12px 24px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(37,99,235,0.2); display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
				<span class="dashicons dashicons-cart" style="font-size: 18px; width: 18px; height: 18px; line-height: 1;"></span>
				<span><?php echo esc_html( $button_display_text ); ?></span>
			</button>
			<?php
		}

		// Inyectar el modal una sola vez en el footer
		add_action( 'wp_footer', array( $this, 'render_frontend_modal_template' ) );

		return ob_get_clean();
	}

	/**
	 * Renderiza la plantilla del modal de checkout en el footer del frontend.
	 */
	public function render_frontend_modal_template() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		$settings = WPAT_Main::get_instance()->get_settings();
		$stripe_enabled = ! empty( $settings['qp_stripe_enabled'] ) && '1' === $settings['qp_stripe_enabled'];
		$redsys_enabled = ! empty( $settings['qp_redsys_enabled'] ) && '1' === $settings['qp_redsys_enabled'];
		$bizum_enabled  = ! empty( $settings['qp_bizum_enabled'] ) && '1' === $settings['qp_bizum_enabled'];
		$bizum_type     = isset( $settings['qp_bizum_type'] ) ? $settings['qp_bizum_type'] : 'redsys';
		$paypal_enabled = ! empty( $settings['qp_paypal_enabled'] ) && '1' === $settings['qp_paypal_enabled'];
		$bank_enabled   = ! empty( $settings['qp_bank_enabled'] ) && '1' === $settings['qp_bank_enabled'];

		$bizum_phone    = isset( $settings['qp_bizum_phone'] ) ? $settings['qp_bizum_phone'] : '';
		$bank_iban      = isset( $settings['qp_bank_iban'] ) ? $settings['qp_bank_iban'] : '';
		$bank_holder    = isset( $settings['qp_bank_holder'] ) ? $settings['qp_bank_holder'] : '';

		$req_billing    = ! empty( $settings['qp_require_billing_address'] );
		$req_phone      = ! empty( $settings['qp_require_phone'] );
		$req_dni        = ! empty( $settings['qp_require_dni'] );

		// Método por defecto seleccionado
		$default_gw = 'stripe';
		if ( $stripe_enabled ) {
			$default_gw = 'stripe';
		} elseif ( $bizum_enabled ) {
			$default_gw = 'bizum';
		} elseif ( $redsys_enabled ) {
			$default_gw = 'redsys';
		} elseif ( $paypal_enabled ) {
			$default_gw = 'paypal';
		} elseif ( $bank_enabled ) {
			$default_gw = 'transfer';
		}
		?>
		<div id="wpat_qp_checkout_modal" class="wpat-qp-modal-overlay" style="display: none;">
			<div class="wpat-qp-modal-container">
				<button type="button" class="wpat-qp-close-modal" title="Cerrar">&times;</button>

				<div class="wpat-qp-modal-header">
					<div class="wpat-qp-modal-title-wrap">
						<h3 id="wpat_qp_m_product_title">Finalizar Compra</h3>
						<span id="wpat_qp_m_product_badge" class="wpat-qp-type-pill">Servicio</span>
					</div>
					<div class="wpat-qp-modal-price-wrap">
						<span id="wpat_qp_m_total_display" class="wpat-qp-price-tag">0,00 €</span>
					</div>
				</div>

				<form id="wpat_qp_checkout_form" method="post" action="">
					<input type="hidden" id="wpat_qp_f_product_id" name="product_id" value="" />
					<input type="hidden" id="wpat_qp_f_product_payload" name="product_payload" value="" />
					<input type="hidden" id="wpat_qp_f_applied_coupon" name="coupon_code" value="" />
					<input type="hidden" id="wpat_qp_f_gateway" name="gateway" value="<?php echo esc_attr( $default_gw ); ?>" />

					<!-- DATOS DEL COMPRADOR -->
					<div class="wpat-qp-section-title">1. Tus Datos de Contacto y Facturación</div>
					<div class="wpat-qp-form-grid">
						<div class="wpat-qp-form-field">
							<label for="wpat_qp_f_name">Nombre y Apellidos *</label>
							<input type="text" id="wpat_qp_f_name" name="customer_name" required placeholder="Ej: Laura García" />
						</div>
						<div class="wpat-qp-form-field">
							<label for="wpat_qp_f_email">Correo Electrónico *</label>
							<input type="email" id="wpat_qp_f_email" name="customer_email" required placeholder="tu@email.com" />
						</div>
					</div>

					<div class="wpat-qp-form-grid" id="wpat_qp_extra_buyer_fields">
						<div class="wpat-qp-form-field" id="wpat_qp_field_phone_wrap">
							<label for="wpat_qp_f_phone">Teléfono / WhatsApp <?php echo $req_phone ? '*' : ''; ?></label>
							<input type="tel" id="wpat_qp_f_phone" name="customer_phone" <?php echo $req_phone ? 'required' : ''; ?> placeholder="Ej: 600 000 000" />
						</div>
						<div class="wpat-qp-form-field" id="wpat_qp_field_dni_wrap">
							<label for="wpat_qp_f_dni">DNI / NIF / CIF <?php echo $req_dni ? '*' : '(Para Factura)'; ?></label>
							<input type="text" id="wpat_qp_f_dni" name="customer_dni" <?php echo $req_dni ? 'required' : ''; ?> placeholder="Ej: 12345678Z / B12345678" />
						</div>
					</div>

					<!-- CAMPOS DE FACTURACIÓN FISCAL COMPLETA -->
					<div class="wpat-qp-billing-fields-box" id="wpat_qp_billing_fields_wrapper" style="<?php echo $req_billing ? '' : 'display:none;'; ?>">
						<div class="wpat-qp-form-field" style="margin-bottom: 8px;">
							<label for="wpat_qp_f_billing_company">Empresa / Razón Social (Opcional)</label>
							<input type="text" id="wpat_qp_f_billing_company" name="billing_company" placeholder="Ej: Mi Empresa S.L." />
						</div>
						<div class="wpat-qp-form-field" style="margin-bottom: 8px;">
							<label for="wpat_qp_f_billing_address">Dirección Fiscal / Facturación <?php echo $req_billing ? '*' : ''; ?></label>
							<input type="text" id="wpat_qp_f_billing_address" name="billing_address" <?php echo $req_billing ? 'required' : ''; ?> placeholder="Ej: Calle Alcalá 45, 2º A" />
						</div>
						<div class="wpat-qp-form-grid">
							<div class="wpat-qp-form-field">
								<label for="wpat_qp_f_billing_postcode">Código Postal <?php echo $req_billing ? '*' : ''; ?></label>
								<input type="text" id="wpat_qp_f_billing_postcode" name="billing_postcode" <?php echo $req_billing ? 'required' : ''; ?> placeholder="28014" />
							</div>
							<div class="wpat-qp-form-field">
								<label for="wpat_qp_f_billing_city">Población / Localidad <?php echo $req_billing ? '*' : ''; ?></label>
								<input type="text" id="wpat_qp_f_billing_city" name="billing_city" <?php echo $req_billing ? 'required' : ''; ?> placeholder="Madrid" />
							</div>
						</div>
						<div class="wpat-qp-form-grid">
							<div class="wpat-qp-form-field">
								<label for="wpat_qp_f_billing_state">Provincia / Región</label>
								<input type="text" id="wpat_qp_f_billing_state" name="billing_state" placeholder="Madrid" />
							</div>
							<div class="wpat-qp-form-field">
								<label for="wpat_qp_f_billing_country">País</label>
								<select id="wpat_qp_f_billing_country" name="billing_country" style="height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 0 10px; font-size: 13.5px; background:#fff;">
									<option value="ES" selected>España</option>
									<option value="PT">Portugal</option>
									<option value="FR">Francia</option>
									<option value="IT">Italia</option>
									<option value="DE">Alemania</option>
									<option value="MX">México</option>
									<option value="CO">Colombia</option>
									<option value="AR">Argentina</option>
									<option value="CL">Chile</option>
									<option value="US">Estados Unidos</option>
									<option value="OTHER">Otro país</option>
								</select>
							</div>
						</div>
					</div>

					<!-- CAMPOS DE ENVÍO FÍSICO (SI ES PRODUCTO FÍSICO) -->
					<div id="wpat_qp_shipping_fields_wrapper" style="display: none;">
						<div class="wpat-qp-section-title" style="margin-top: 15px;">Dirección de Envío</div>
						<div style="margin-bottom: 10px; font-size: 12.5px;">
							<label style="cursor: pointer; display: flex; align-items: center; gap: 6px;">
								<input type="checkbox" id="wpat_qp_same_as_billing" checked style="accent-color: #2563eb;" />
								<span>Usar la misma dirección de facturación</span>
							</label>
						</div>
						<div id="wpat_qp_shipping_subfields" style="display: none;">
							<div class="wpat-qp-form-field" style="margin-bottom: 8px;">
								<label for="wpat_qp_f_address">Dirección de Entrega *</label>
								<input type="text" id="wpat_qp_f_address" name="shipping_address" placeholder="Ej: Calle Gran Vía 28, 4º B" />
							</div>
							<div class="wpat-qp-form-grid">
								<div class="wpat-qp-form-field">
									<label for="wpat_qp_f_city">Población / Ciudad *</label>
									<input type="text" id="wpat_qp_f_city" name="shipping_city" placeholder="Madrid" />
								</div>
								<div class="wpat-qp-form-field">
									<label for="wpat_qp_f_postcode">Código Postal *</label>
									<input type="text" id="wpat_qp_f_postcode" name="shipping_postcode" placeholder="28013" />
								</div>
							</div>
						</div>
					</div>

					<!-- SECCIÓN CUPÓN DE DESCUENTO -->
					<div class="wpat-qp-coupon-block" style="margin: 15px 0 18px 0; padding: 10px 14px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">
						<div id="wpat_qp_coupon_toggle_wrap" style="display: flex; justify-content: space-between; align-items: center;">
							<a href="#" id="wpat_qp_open_coupon_btn" style="font-size: 12.5px; font-weight: 600; color: #2563eb; text-decoration: none;">¿Tienes un cupón de descuento?</a>
							<span id="wpat_qp_coupon_status_badge" style="display: none; font-size: 11px; font-weight: 700; color: #059669; background: #ecfdf5; padding: 2px 8px; border-radius: 12px;"></span>
						</div>
						<div id="wpat_qp_coupon_input_row" style="display: none; margin-top: 8px; gap: 8px;">
							<input type="text" id="wpat_qp_coupon_input" placeholder="Introduce tu código" style="flex: 1; height: 32px; border-radius: 6px; border: 1px solid #cbd5e1; padding: 0 10px; font-size: 12px;" />
							<button type="button" id="wpat_qp_apply_coupon_btn" class="button" style="height: 32px; font-size: 12px; font-weight: 600;">Aplicar</button>
						</div>
					</div>

					<!-- DESGLOSE DEL TOTAL -->
					<div class="wpat-qp-breakdown-box" style="margin-bottom: 18px; padding: 12px 14px; background: #f1f5f9; border-radius: 8px; font-size: 12.5px; color: #334155;">
						<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
							<span>Precio base:</span>
							<strong id="wpat_qp_b_subtotal">0,00 €</strong>
						</div>
						<div id="wpat_qp_b_discount_row" style="display: none; justify-content: space-between; margin-bottom: 4px; color: #059669;">
							<span>Descuento aplicado:</span>
							<strong id="wpat_qp_b_discount">-0,00 €</strong>
						</div>
						<div id="wpat_qp_b_shipping_row" style="display: none; justify-content: space-between; margin-bottom: 4px;">
							<span>Gastos de envío:</span>
							<strong id="wpat_qp_b_shipping">0,00 €</strong>
						</div>
						<div id="wpat_qp_b_tax_row" style="display: flex; justify-content: space-between; margin-bottom: 4px; color: #64748b; font-size: 11.5px;">
							<span>IVA incluido (<span id="wpat_qp_b_tax_rate">21</span>%):</span>
							<span id="wpat_qp_b_tax_amount">0,00 €</span>
						</div>
						<div style="display: flex; justify-content: space-between; border-top: 1px solid #cbd5e1; padding-top: 6px; margin-top: 6px; font-size: 15px; font-weight: 800; color: #0f172a;">
							<span>Total a Pagar:</span>
							<span id="wpat_qp_b_final_total" style="color: #2563eb;">0,00 €</span>
						</div>
					</div>

					<!-- SELECCIÓN DE MÉTODO DE PAGO -->
					<div class="wpat-qp-section-title">2. Elige tu Método de Pago</div>
					<div class="wpat-qp-gateway-selector" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px;">
						<?php if ( $bizum_enabled ) : ?>
							<label class="wpat-qp-gateway-option <?php echo ( 'bizum' === $default_gw ) ? 'active' : ''; ?>" data-gw="bizum">
								<input type="radio" name="wpat_gw_radio" value="bizum" <?php checked( 'bizum' === $default_gw ); ?> />
								<div class="wpat-qp-gw-info">
									<?php if ( 'redsys' === $bizum_type ) : ?>
										<strong>📱 Bizum (Instantáneo por Pasarela Bancaria)</strong>
										<span>Paga de forma rápida y segura desde tu móvil con la pasarela bancaria oficial de Bizum.</span>
									<?php else : ?>
										<strong>📱 Bizum Directo (Manual)</strong>
										<span>Envía el importe por Bizum al <strong><?php echo esc_html( $bizum_phone ? $bizum_phone : 'tu número configurado' ); ?></strong> y procesaremos tu pedido al instante.</span>
									<?php endif; ?>
								</div>
							</label>
						<?php endif; ?>

						<?php if ( $stripe_enabled ) : ?>
							<label class="wpat-qp-gateway-option <?php echo ( 'stripe' === $default_gw ) ? 'active' : ''; ?>" data-gw="stripe">
								<input type="radio" name="wpat_gw_radio" value="stripe" <?php checked( 'stripe' === $default_gw ); ?> />
								<div class="wpat-qp-gw-info">
									<strong>💳 Tarjeta de Crédito / Débito (Stripe)</strong>
									<span>Paga de forma instantánea y segura con Tarjeta, Apple Pay o Google Pay.</span>
								</div>
							</label>
						<?php endif; ?>

						<?php if ( $redsys_enabled ) : ?>
							<label class="wpat-qp-gateway-option <?php echo ( 'redsys' === $default_gw ) ? 'active' : ''; ?>" data-gw="redsys">
								<input type="radio" name="wpat_gw_radio" value="redsys" <?php checked( 'redsys' === $default_gw ); ?> />
								<div class="wpat-qp-gw-info">
									<strong>🏦 Tarjeta Bancaria (TPV Virtual Redsys)</strong>
									<span>Pago seguro mediante la pasarela bancaria oficial Redsys (Servired / 4B / Euro6000).</span>
								</div>
							</label>
						<?php endif; ?>

						<?php if ( $paypal_enabled ) : ?>
							<label class="wpat-qp-gateway-option <?php echo ( 'paypal' === $default_gw ) ? 'active' : ''; ?>" data-gw="paypal">
								<input type="radio" name="wpat_gw_radio" value="paypal" <?php checked( 'paypal' === $default_gw ); ?> />
								<div class="wpat-qp-gw-info">
									<strong>🅿️ PayPal</strong>
									<span>Paga con tu cuenta o saldo de PayPal o tarjeta asociada.</span>
								</div>
							</label>
						<?php endif; ?>

						<?php if ( $bank_enabled ) : ?>
							<label class="wpat-qp-gateway-option <?php echo ( 'transfer' === $default_gw ) ? 'active' : ''; ?>" data-gw="transfer">
								<input type="radio" name="wpat_gw_radio" value="transfer" <?php checked( 'transfer' === $default_gw ); ?> />
								<div class="wpat-qp-gw-info">
									<strong>🏛️ Transferencia Bancaria</strong>
									<span>Recibirás los datos bancarios (IBAN) para emitir la transferencia.</span>
								</div>
							</label>
						<?php endif; ?>
					</div>

					<div class="wpat-qp-submit-area">
						<button type="submit" id="wpat_qp_submit_btn" class="wpat-qp-submit-button">
							<span>Pagar Ahora</span> &rarr;
						</button>
						<p class="wpat-qp-legal-notice">Al realizar el pedido aceptas los términos de compra y la política de privacidad de este sitio.</p>
					</div>
				</form>

				<!-- PANTALLA DE ÉXITO / INSTRUCCIONES MANUALES -->
				<div id="wpat_qp_success_screen" style="display: none; text-align: center; padding: 20px 10px;">
					<div style="width: 54px; height: 54px; background: #ecfdf5; color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; font-size: 28px;">✓</div>
					<h3 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0;" id="wpat_qp_success_title">¡Pedido Registrado con Éxito!</h3>
					<p style="color: #64748b; font-size: 13.5px; margin: 0 0 16px 0;" id="wpat_qp_success_msg">Hemos enviado los detalles y confirmación a tu correo electrónico.</p>

					<div id="wpat_qp_manual_instructions_box" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin-bottom: 20px; text-align: left; font-size: 13px; color: #1e40af;">
					</div>

					<div id="wpat_qp_digital_download_box" style="display: none; margin-bottom: 20px;">
						<a href="#" id="wpat_qp_download_btn_link" class="button button-primary" style="padding: 10px 24px; font-size: 14px; font-weight: 700; border-radius: 6px; text-decoration: none;">
							📥 Descargar Archivo Ahora
						</a>
					</div>

					<button type="button" class="button wpat-qp-close-modal" style="height: 36px; padding: 0 20px; font-weight: 600;">Cerrar Ventana</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Endpoint AJAX para obtener datos de un producto al abrir el checkout modal.
	 */
	public function ajax_get_checkout_data() {
		check_ajax_referer( 'wpat_quick_pay_nonce', 'security' );

		$product_id      = isset( $_POST['product_id'] ) ? sanitize_key( $_POST['product_id'] ) : '';
		$product_payload = isset( $_POST['product_payload'] ) ? $_POST['product_payload'] : '';
		$product         = self::resolve_product( $product_id, $product_payload );

		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Producto no encontrado.' ) );
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$curr_sym   = isset( $settings['qp_currency_symbol'] ) ? $settings['qp_currency_symbol'] : '€';
		$curr_pos   = isset( $settings['qp_currency_pos'] ) ? $settings['qp_currency_pos'] : 'right';
		$global_tax = isset( $settings['qp_default_tax_rate'] ) ? floatval( $settings['qp_default_tax_rate'] ) : 21.0;
		$tax_rate   = isset( $product['tax_rate'] ) ? floatval( $product['tax_rate'] ) : $global_tax;
		$shipping   = isset( $product['shipping_cost'] ) ? floatval( $product['shipping_cost'] ) : 0.0;

		$total = $product['price'] + $shipping;
		$tax_amount = ( $total * $tax_rate ) / ( 100 + $tax_rate ); // IVA incluido

		wp_send_json_success(
			array(
				'product'          => $product,
				'price'            => $product['price'],
				'shipping'         => $shipping,
				'tax_rate'         => $tax_rate,
				'tax_amount'       => round( $tax_amount, 2 ),
				'total'            => round( $total, 2 ),
				'currency_symbol'  => $curr_sym,
				'currency_pos'     => $curr_pos,
			)
		);
	}

	/**
	 * Endpoint AJAX para aplicar y validar un cupón de descuento.
	 */
	public function ajax_apply_coupon() {
		check_ajax_referer( 'wpat_quick_pay_nonce', 'security' );

		$product_id      = isset( $_POST['product_id'] ) ? sanitize_key( $_POST['product_id'] ) : '';
		$product_payload = isset( $_POST['product_payload'] ) ? $_POST['product_payload'] : '';
		$coupon_code     = isset( $_POST['coupon_code'] ) ? strtoupper( sanitize_text_field( trim( $_POST['coupon_code'] ) ) ) : '';

		$product = self::resolve_product( $product_id, $product_payload );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Producto no encontrado.' ) );
		}

		$coupons = self::get_coupons();
		if ( empty( $coupons[ $coupon_code ] ) ) {
			wp_send_json_error( array( 'message' => 'El código de cupón no existe.' ) );
		}

		$coupon = $coupons[ $coupon_code ];

		// Validar fecha de expiración
		if ( ! empty( $coupon['expiry'] ) && strtotime( $coupon['expiry'] ) < time() ) {
			wp_send_json_error( array( 'message' => 'Este cupón ha caducado.' ) );
		}

		// Validar límite de usos
		if ( ! empty( $coupon['usage_limit'] ) && intval( $coupon['usage_limit'] ) > 0 ) {
			$usages = isset( $coupon['usage_count'] ) ? intval( $coupon['usage_count'] ) : 0;
			if ( $usages >= intval( $coupon['usage_limit'] ) ) {
				wp_send_json_error( array( 'message' => 'Este cupón ha alcanzado el límite de usos permitidos.' ) );
			}
		}

		$base_price = floatval( $product['price'] );
		$discount = 0.0;

		if ( 'percent' === $coupon['type'] ) {
			$discount = ( $base_price * floatval( $coupon['amount'] ) ) / 100;
		} else {
			$discount = floatval( $coupon['amount'] );
		}

		$discount = min( $discount, $base_price );
		$new_price = max( 0, $base_price - $discount );
		$shipping = isset( $product['shipping_cost'] ) ? floatval( $product['shipping_cost'] ) : 0.0;
		$new_total = $new_price + $shipping;

		$tax_rate = isset( $product['tax_rate'] ) ? floatval( $product['tax_rate'] ) : 21.0;
		$tax_amount = ( $new_total * $tax_rate ) / ( 100 + $tax_rate );

		wp_send_json_success(
			array(
				'coupon_code'     => $coupon_code,
				'discount_amount' => round( $discount, 2 ),
				'new_subtotal'    => round( $new_price, 2 ),
				'new_total'       => round( $new_total, 2 ),
				'tax_amount'      => round( $tax_amount, 2 ),
			)
		);
	}

	/**
	 * Endpoint AJAX para crear Checkout Session de Stripe.
	 */
	public function ajax_create_stripe_session() {
		check_ajax_referer( 'wpat_quick_pay_nonce', 'security' );

		$settings = WPAT_Main::get_instance()->get_settings();
		$mode     = isset( $settings['qp_stripe_mode'] ) ? $settings['qp_stripe_mode'] : 'test';
		$sec_key  = ( 'live' === $mode ) ? ( isset( $settings['qp_stripe_live_sec_key'] ) ? $settings['qp_stripe_live_sec_key'] : '' ) : ( isset( $settings['qp_stripe_test_sec_key'] ) ? $settings['qp_stripe_test_sec_key'] : '' );

		if ( empty( $sec_key ) ) {
			wp_send_json_error( array( 'message' => 'La pasarela de Stripe no está configurada correctamente en el panel.' ) );
		}

		$product_id      = isset( $_POST['product_id'] ) ? sanitize_key( $_POST['product_id'] ) : '';
		$product_payload = isset( $_POST['product_payload'] ) ? $_POST['product_payload'] : '';
		$customer_name   = isset( $_POST['customer_name'] ) ? sanitize_text_field( $_POST['customer_name'] ) : '';
		$customer_email  = isset( $_POST['customer_email'] ) ? sanitize_email( $_POST['customer_email'] ) : '';
		$customer_phone  = isset( $_POST['customer_phone'] ) ? sanitize_text_field( $_POST['customer_phone'] ) : '';
		$customer_dni    = isset( $_POST['customer_dni'] ) ? sanitize_text_field( $_POST['customer_dni'] ) : '';
		$coupon_code     = isset( $_POST['coupon_code'] ) ? strtoupper( sanitize_text_field( $_POST['coupon_code'] ) ) : '';

		$billing_company  = isset( $_POST['billing_company'] ) ? sanitize_text_field( $_POST['billing_company'] ) : '';
		$billing_address  = isset( $_POST['billing_address'] ) ? sanitize_text_field( $_POST['billing_address'] ) : '';
		$billing_city     = isset( $_POST['billing_city'] ) ? sanitize_text_field( $_POST['billing_city'] ) : '';
		$billing_postcode = isset( $_POST['billing_postcode'] ) ? sanitize_text_field( $_POST['billing_postcode'] ) : '';
		$billing_state    = isset( $_POST['billing_state'] ) ? sanitize_text_field( $_POST['billing_state'] ) : '';
		$billing_country  = isset( $_POST['billing_country'] ) ? sanitize_text_field( $_POST['billing_country'] ) : 'ES';

		$shipping_addr    = isset( $_POST['shipping_address'] ) ? sanitize_text_field( $_POST['shipping_address'] ) : $billing_address;
		$shipping_city    = isset( $_POST['shipping_city'] ) ? sanitize_text_field( $_POST['shipping_city'] ) : $billing_city;
		$shipping_post    = isset( $_POST['shipping_postcode'] ) ? sanitize_text_field( $_POST['shipping_postcode'] ) : $billing_postcode;
		$shipping_state   = isset( $_POST['shipping_state'] ) ? sanitize_text_field( $_POST['shipping_state'] ) : $billing_state;
		$shipping_country = isset( $_POST['shipping_country'] ) ? sanitize_text_field( $_POST['shipping_country'] ) : $billing_country;

		$product = self::resolve_product( $product_id, $product_payload );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Producto no encontrado.' ) );
		}

		// Calcular importe con cupón si existe
		$base_price = floatval( $product['price'] );
		$discount   = 0.0;
		if ( ! empty( $coupon_code ) ) {
			$coupons = self::get_coupons();
			if ( ! empty( $coupons[ $coupon_code ] ) ) {
				$c = $coupons[ $coupon_code ];
				$discount = ( 'percent' === $c['type'] ) ? ( $base_price * floatval( $c['amount'] ) ) / 100 : floatval( $c['amount'] );
				$discount = min( $discount, $base_price );
			}
		}

		$shipping_cost = ( 'physical' === $product['type'] && ! empty( $product['shipping_cost'] ) ) ? floatval( $product['shipping_cost'] ) : 0.0;
		$final_amount  = max( 0.50, ( $base_price - $discount ) + $shipping_cost );
		$amount_cents  = intval( round( $final_amount * 100 ) );

		// Crear pedido pendiente en BD
		$order_id = $this->create_order_record(
			array(
				'product_id'        => $product['id'],
				'product_name'      => $product['name'],
				'product_type'      => $product['type'],
				'amount'            => $final_amount,
				'currency'          => isset( $product['currency'] ) ? $product['currency'] : 'EUR',
				'customer_name'     => $customer_name,
				'customer_email'    => $customer_email,
				'customer_phone'    => $customer_phone,
				'customer_dni'      => $customer_dni,
				'billing_company'   => $billing_company,
				'billing_address'   => $billing_address,
				'billing_city'      => $billing_city,
				'billing_postcode'  => $billing_postcode,
				'billing_state'     => $billing_state,
				'billing_country'   => $billing_country,
				'shipping_address'  => $shipping_addr,
				'shipping_city'     => $shipping_city,
				'shipping_postcode' => $shipping_post,
				'shipping_state'    => $shipping_state,
				'shipping_country'  => $shipping_country,
				'shipping_cost'     => $shipping_cost,
				'coupon_code'       => $coupon_code,
				'discount_amount'   => $discount,
				'gateway'           => 'stripe',
				'status'            => 'pending',
				'is_recurring'      => ! empty( $product['is_recurring'] ) ? 1 : 0,
			)
		);

		$order_key = $this->get_order_key_by_id( $order_id );

		$return_url = add_query_arg(
			array(
				'wpat_qp_action' => 'stripe_return',
				'order_key'      => $order_key,
			),
			home_url( '/' )
		);

		$cancel_url = add_query_arg(
			array(
				'wpat_qp_action' => 'stripe_cancel',
				'order_key'      => $order_key,
			),
			home_url( '/' )
		);

		// Llamada nativa a la API de Stripe
		$body_args = array(
			'payment_method_types[0]' => 'card',
			'customer_email'          => $customer_email,
			'client_reference_id'     => $order_id,
			'success_url'             => $return_url . '&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'              => $cancel_url,
			'mode'                    => ! empty( $product['is_recurring'] ) ? 'subscription' : 'payment',
			'line_items[0][price_data][currency]'     => strtolower( isset( $product['currency'] ) ? $product['currency'] : 'eur' ),
			'line_items[0][price_data][unit_amount]'   => $amount_cents,
			'line_items[0][price_data][product_data][name]' => $product['name'],
			'line_items[0][quantity]'                 => 1,
		);

		if ( ! empty( $product['is_recurring'] ) ) {
			$body_args['line_items[0][price_data][recurring][interval]'] = isset( $product['billing_interval'] ) ? $product['billing_interval'] : 'month';
		}

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $sec_key,
				),
				'body'    => $body_args,
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Error al conectar con Stripe: ' . $response->get_error_message() ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $data['id'] ) && ! empty( $data['url'] ) ) {
			// Guardar ID de sesión en el pedido
			$this->update_order_meta( $order_id, 'stripe_session_id', $data['id'] );
			wp_send_json_success( array( 'checkout_url' => $data['url'] ) );
		} else {
			$err_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Respuesta no válida de Stripe.';
			wp_send_json_error( array( 'message' => $err_msg ) );
		}
	}

	/**
	 * Endpoint AJAX para procesar pedidos manuales (Bizum Manual o Transferencia).
	 */
	public function ajax_process_manual_order() {
		check_ajax_referer( 'wpat_quick_pay_nonce', 'security' );

		$gateway         = isset( $_POST['gateway'] ) ? sanitize_key( $_POST['gateway'] ) : 'bizum';
		$product_id      = isset( $_POST['product_id'] ) ? sanitize_key( $_POST['product_id'] ) : '';
		$product_payload = isset( $_POST['product_payload'] ) ? $_POST['product_payload'] : '';

		$product = self::resolve_product( $product_id, $product_payload );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Producto no encontrado.' ) );
		}

		$customer_name   = isset( $_POST['customer_name'] ) ? sanitize_text_field( $_POST['customer_name'] ) : '';
		$customer_email  = isset( $_POST['customer_email'] ) ? sanitize_email( $_POST['customer_email'] ) : '';
		$customer_phone  = isset( $_POST['customer_phone'] ) ? sanitize_text_field( $_POST['customer_phone'] ) : '';
		$customer_dni    = isset( $_POST['customer_dni'] ) ? sanitize_text_field( $_POST['customer_dni'] ) : '';
		$coupon_code     = isset( $_POST['coupon_code'] ) ? strtoupper( sanitize_text_field( $_POST['coupon_code'] ) ) : '';

		$billing_company  = isset( $_POST['billing_company'] ) ? sanitize_text_field( $_POST['billing_company'] ) : '';
		$billing_address  = isset( $_POST['billing_address'] ) ? sanitize_text_field( $_POST['billing_address'] ) : '';
		$billing_city     = isset( $_POST['billing_city'] ) ? sanitize_text_field( $_POST['billing_city'] ) : '';
		$billing_postcode = isset( $_POST['billing_postcode'] ) ? sanitize_text_field( $_POST['billing_postcode'] ) : '';
		$billing_state    = isset( $_POST['billing_state'] ) ? sanitize_text_field( $_POST['billing_state'] ) : '';
		$billing_country  = isset( $_POST['billing_country'] ) ? sanitize_text_field( $_POST['billing_country'] ) : 'ES';

		$shipping_addr    = isset( $_POST['shipping_address'] ) ? sanitize_text_field( $_POST['shipping_address'] ) : $billing_address;
		$shipping_city    = isset( $_POST['shipping_city'] ) ? sanitize_text_field( $_POST['shipping_city'] ) : $billing_city;
		$shipping_post    = isset( $_POST['shipping_postcode'] ) ? sanitize_text_field( $_POST['shipping_postcode'] ) : $billing_postcode;
		$shipping_state   = isset( $_POST['shipping_state'] ) ? sanitize_text_field( $_POST['shipping_state'] ) : $billing_state;
		$shipping_country = isset( $_POST['shipping_country'] ) ? sanitize_text_field( $_POST['shipping_country'] ) : $billing_country;

		$base_price = floatval( $product['price'] );
		$discount   = 0.0;
		if ( ! empty( $coupon_code ) ) {
			$coupons = self::get_coupons();
			if ( ! empty( $coupons[ $coupon_code ] ) ) {
				$c = $coupons[ $coupon_code ];
				$discount = ( 'percent' === $c['type'] ) ? ( $base_price * floatval( $c['amount'] ) ) / 100 : floatval( $c['amount'] );
				$discount = min( $discount, $base_price );
			}
		}

		$shipping_cost = ( 'physical' === $product['type'] && ! empty( $product['shipping_cost'] ) ) ? floatval( $product['shipping_cost'] ) : 0.0;
		$final_amount  = max( 0, ( $base_price - $discount ) + $shipping_cost );

		$order_id = $this->create_order_record(
			array(
				'product_id'        => $product['id'],
				'product_name'      => $product['name'],
				'product_type'      => $product['type'],
				'amount'            => $final_amount,
				'currency'          => isset( $product['currency'] ) ? $product['currency'] : 'EUR',
				'customer_name'     => $customer_name,
				'customer_email'    => $customer_email,
				'customer_phone'    => $customer_phone,
				'customer_dni'      => $customer_dni,
				'billing_company'   => $billing_company,
				'billing_address'   => $billing_address,
				'billing_city'      => $billing_city,
				'billing_postcode'  => $billing_postcode,
				'billing_state'     => $billing_state,
				'billing_country'   => $billing_country,
				'shipping_address'  => $shipping_addr,
				'shipping_city'     => $shipping_city,
				'shipping_postcode' => $shipping_post,
				'shipping_state'    => $shipping_state,
				'shipping_country'  => $shipping_country,
				'shipping_cost'     => $shipping_cost,
				'coupon_code'       => $coupon_code,
				'discount_amount'   => $discount,
				'gateway'           => $gateway,
				'status'            => 'pending',
				'is_recurring'      => 0,
			)
		);

		$settings = WPAT_Main::get_instance()->get_settings();
		$instructions = '';

		if ( 'bizum' === $gateway ) {
			$phone = isset( $settings['qp_bizum_phone'] ) && ! empty( $settings['qp_bizum_phone'] ) ? $settings['qp_bizum_phone'] : '600 000 000';
			$custom_inst = isset( $settings['qp_bizum_instructions'] ) ? trim( $settings['qp_bizum_instructions'] ) : '';

			if ( ! empty( $custom_inst ) ) {
				$instructions = str_replace(
					array( '{order_id}', '{amount}', '{phone}' ),
					array( '#WPAT-' . $order_id, number_format( $final_amount, 2, ',', '.' ) . ' €', esc_html( $phone ) ),
					nl2br( esc_html( $custom_inst ) )
				);
			} else {
				$instructions = '<strong>Instrucciones para completar tu pago por Bizum:</strong><br>'
					. '1. Abre la app de tu banco y realiza un Bizum de <strong>' . number_format( $final_amount, 2, ',', '.' ) . ' €</strong> al número: <strong style="font-size:15px; color:#2563eb;">' . esc_html( $phone ) . '</strong><br>'
					. '2. En el concepto pon tu número de pedido: <strong style="color:#0f172a;">#WPAT-' . $order_id . '</strong><br>'
					. '3. Una vez recibido el aviso, activaremos tu compra y te enviaremos la confirmación oficial a tu email.';
			}
		} else {
			$iban        = isset( $settings['qp_bank_iban'] ) && ! empty( $settings['qp_bank_iban'] ) ? $settings['qp_bank_iban'] : 'ES00 0000 0000 0000 0000 0000';
			$holder      = isset( $settings['qp_bank_holder'] ) && ! empty( $settings['qp_bank_holder'] ) ? $settings['qp_bank_holder'] : get_bloginfo( 'name' );
			$custom_inst = isset( $settings['qp_bank_instructions'] ) ? trim( $settings['qp_bank_instructions'] ) : '';

			if ( ! empty( $custom_inst ) ) {
				$instructions = str_replace(
					array( '{order_id}', '{amount}', '{iban}', '{holder}' ),
					array( '#WPAT-' . $order_id, number_format( $final_amount, 2, ',', '.' ) . ' €', esc_html( $iban ), esc_html( $holder ) ),
					nl2br( esc_html( $custom_inst ) )
				);
			} else {
				$instructions = '<strong>Instrucciones para la Transferencia Bancaria:</strong><br>'
					. 'IBAN: <strong style="font-family:monospace; color:#2563eb;">' . esc_html( $iban ) . '</strong><br>'
					. 'Titular: <strong>' . esc_html( $holder ) . '</strong><br>'
					. 'Importe exacto: <strong>' . number_format( $final_amount, 2, ',', '.' ) . ' €</strong><br>'
					. 'Concepto obligatorio: <strong>#WPAT-' . $order_id . '</strong>';
			}
		}

		// Notificar al admin y cliente del pedido pendiente
		$this->send_order_emails( $order_id );

		wp_send_json_success(
			array(
				'order_id'     => $order_id,
				'instructions' => $instructions,
			)
		);
	}

	/**
	 * Endpoint AJAX para preparar el formulario de Redsys TPV (Tarjeta o Bizum SIS).
	 */
	public function ajax_process_redsys_order() {
		check_ajax_referer( 'wpat_quick_pay_nonce', 'security' );

		$product_id      = isset( $_POST['product_id'] ) ? sanitize_key( $_POST['product_id'] ) : '';
		$product_payload = isset( $_POST['product_payload'] ) ? $_POST['product_payload'] : '';

		$product = self::resolve_product( $product_id, $product_payload );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Producto no encontrado.' ) );
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$gateway  = isset( $_POST['gateway'] ) ? sanitize_key( $_POST['gateway'] ) : 'redsys';
		$is_bizum = ( 'bizum' === $gateway || ( isset( $_POST['pay_method'] ) && 'z' === $_POST['pay_method'] ) );

		// Determinar credenciales (Redsys estándar vs Bizum dedicado)
		if ( $is_bizum && ! empty( $settings['qp_bizum_redsys_custom'] ) && '1' === $settings['qp_bizum_redsys_custom'] ) {
			$fuc      = isset( $settings['qp_bizum_redsys_fuc'] ) && ! empty( $settings['qp_bizum_redsys_fuc'] ) ? trim( $settings['qp_bizum_redsys_fuc'] ) : trim( $settings['qp_redsys_fuc'] ?? '' );
			$terminal = isset( $settings['qp_bizum_redsys_terminal'] ) && ! empty( $settings['qp_bizum_redsys_terminal'] ) ? trim( $settings['qp_bizum_redsys_terminal'] ) : ( trim( $settings['qp_redsys_terminal'] ?? '1' ) );
			$key      = isset( $settings['qp_bizum_redsys_key'] ) && ! empty( $settings['qp_bizum_redsys_key'] ) ? trim( $settings['qp_bizum_redsys_key'] ) : trim( $settings['qp_redsys_key'] ?? '' );
		} else {
			$fuc      = isset( $settings['qp_redsys_fuc'] ) ? trim( $settings['qp_redsys_fuc'] ) : '';
			$terminal = isset( $settings['qp_redsys_terminal'] ) ? trim( $settings['qp_redsys_terminal'] ) : '1';
			$key      = isset( $settings['qp_redsys_key'] ) ? trim( $settings['qp_redsys_key'] ) : '';
		}

		$mode = isset( $settings['qp_redsys_mode'] ) ? $settings['qp_redsys_mode'] : 'test';

		if ( empty( $fuc ) || empty( $key ) ) {
			$error_msg = $is_bizum ? 'Configuración de Bizum / Redsys incompleta en el panel.' : 'Configuración de Redsys incompleta en el panel.';
			wp_send_json_error( array( 'message' => $error_msg ) );
		}

		$base_price = floatval( $product['price'] );
		$discount   = 0.0;
		$coupon_code = isset( $_POST['coupon_code'] ) ? strtoupper( sanitize_text_field( $_POST['coupon_code'] ) ) : '';
		if ( ! empty( $coupon_code ) ) {
			$coupons = self::get_coupons();
			if ( ! empty( $coupons[ $coupon_code ] ) ) {
				$c = $coupons[ $coupon_code ];
				$discount = ( 'percent' === $c['type'] ) ? ( $base_price * floatval( $c['amount'] ) ) / 100 : floatval( $c['amount'] );
				$discount = min( $discount, $base_price );
			}
		}

		$shipping_cost = ( 'physical' === $product['type'] && ! empty( $product['shipping_cost'] ) ) ? floatval( $product['shipping_cost'] ) : 0.0;
		$final_amount  = max( 0.10, ( $base_price - $discount ) + $shipping_cost );
		$amount_cents  = intval( round( $final_amount * 100 ) );

		$billing_company  = isset( $_POST['billing_company'] ) ? sanitize_text_field( $_POST['billing_company'] ) : '';
		$billing_address  = isset( $_POST['billing_address'] ) ? sanitize_text_field( $_POST['billing_address'] ) : '';
		$billing_city     = isset( $_POST['billing_city'] ) ? sanitize_text_field( $_POST['billing_city'] ) : '';
		$billing_postcode = isset( $_POST['billing_postcode'] ) ? sanitize_text_field( $_POST['billing_postcode'] ) : '';
		$billing_state    = isset( $_POST['billing_state'] ) ? sanitize_text_field( $_POST['billing_state'] ) : '';
		$billing_country  = isset( $_POST['billing_country'] ) ? sanitize_text_field( $_POST['billing_country'] ) : 'ES';

		$shipping_addr    = isset( $_POST['shipping_address'] ) ? sanitize_text_field( $_POST['shipping_address'] ) : $billing_address;
		$shipping_city    = isset( $_POST['shipping_city'] ) ? sanitize_text_field( $_POST['shipping_city'] ) : $billing_city;
		$shipping_post    = isset( $_POST['shipping_postcode'] ) ? sanitize_text_field( $_POST['shipping_postcode'] ) : $billing_postcode;
		$shipping_state   = isset( $_POST['shipping_state'] ) ? sanitize_text_field( $_POST['shipping_state'] ) : $billing_state;
		$shipping_country = isset( $_POST['shipping_country'] ) ? sanitize_text_field( $_POST['shipping_country'] ) : $billing_country;

		$order_id = $this->create_order_record(
			array(
				'product_id'        => $product['id'],
				'product_name'      => $product['name'],
				'product_type'      => $product['type'],
				'amount'            => $final_amount,
				'currency'          => 'EUR',
				'customer_name'     => isset( $_POST['customer_name'] ) ? sanitize_text_field( $_POST['customer_name'] ) : '',
				'customer_email'    => isset( $_POST['customer_email'] ) ? sanitize_email( $_POST['customer_email'] ) : '',
				'customer_phone'    => isset( $_POST['customer_phone'] ) ? sanitize_text_field( $_POST['customer_phone'] ) : '',
				'customer_dni'      => isset( $_POST['customer_dni'] ) ? sanitize_text_field( $_POST['customer_dni'] ) : '',
				'billing_company'   => $billing_company,
				'billing_address'   => $billing_address,
				'billing_city'      => $billing_city,
				'billing_postcode'  => $billing_postcode,
				'billing_state'     => $billing_state,
				'billing_country'   => $billing_country,
				'shipping_address'  => $shipping_addr,
				'shipping_city'     => $shipping_city,
				'shipping_postcode' => $shipping_post,
				'shipping_state'    => $shipping_state,
				'shipping_country'  => $shipping_country,
				'shipping_cost'     => $shipping_cost,
				'coupon_code'       => $coupon_code,
				'discount_amount'   => $discount,
				'gateway'           => $is_bizum ? 'bizum_redsys' : 'redsys',
				'status'            => 'pending',
				'is_recurring'      => 0,
			)
		);

		$order_key = $this->get_order_key_by_id( $order_id );

		// Redsys Order ID (Máx 12 caracteres alfanuméricos, primeros 4 dígitos numéricos)
		$redsys_order_num = date( 'ymd' ) . str_pad( (string) ( $order_id % 1000000 ), 6, '0', STR_PAD_LEFT );

		$url_ok = add_query_arg(
			array(
				'wpat_qp_action' => 'redsys_return',
				'order_key'      => $order_key,
			),
			home_url( '/' )
		);

		$url_ko = add_query_arg(
			array(
				'wpat_qp_action' => 'redsys_cancel',
				'order_key'      => $order_key,
			),
			home_url( '/' )
		);

		$webhook_url = add_query_arg( 'wpat_qp_webhook', $is_bizum ? 'bizum_redsys' : 'redsys', home_url( '/' ) );

		$merchant_params = array(
			'DS_MERCHANT_AMOUNT'             => (string) $amount_cents,
			'DS_MERCHANT_ORDER'              => $redsys_order_num,
			'DS_MERCHANT_MERCHANTCODE'       => $fuc,
			'DS_MERCHANT_CURRENCY'           => '978', // EUR
			'DS_MERCHANT_TRANSACTIONTYPE'    => '0',
			'DS_MERCHANT_TERMINAL'           => $terminal,
			'DS_MERCHANT_MERCHANTURL'        => $webhook_url,
			'DS_MERCHANT_URLOK'              => $url_ok,
			'DS_MERCHANT_URLKO'              => $url_ko,
			'DS_MERCHANT_PRODUCTDESCRIPTION' => substr( $product['name'], 0, 125 ),
			'DS_MERCHANT_MERCHANTDATA'       => $order_key,
		);

		// Si es Bizum oficial de Redsys, forzar el método de pago 'z'
		if ( $is_bizum ) {
			$merchant_params['DS_MERCHANT_PAYMETHODS'] = 'z';
		}

		$params_base64 = base64_encode( wp_json_encode( $merchant_params ) );
		$signature     = $this->generate_redsys_signature( $params_base64, $redsys_order_num, $key );

		$action_url = ( 'live' === $mode ) ? 'https://sis.redsys.es/sis/realizarPago' : 'https://sis-t.redsys.es:25443/sis/realizarPago';

		wp_send_json_success(
			array(
				'action_url'          => $action_url,
				'params_base64'       => $params_base64,
				'signature'           => $signature,
				'signature_version'   => 'HMAC_SHA256_V1',
			)
		);
	}

	/**
	 * Endpoint AJAX para preparar redirección a PayPal.
	 */
	public function ajax_process_paypal_order() {
		check_ajax_referer( 'wpat_quick_pay_nonce', 'security' );

		$product_id      = isset( $_POST['product_id'] ) ? sanitize_key( $_POST['product_id'] ) : '';
		$product_payload = isset( $_POST['product_payload'] ) ? $_POST['product_payload'] : '';

		$product = self::resolve_product( $product_id, $product_payload );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Producto no encontrado.' ) );
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$email    = isset( $settings['qp_paypal_email'] ) ? trim( $settings['qp_paypal_email'] ) : '';
		$mode     = isset( $settings['qp_paypal_mode'] ) ? $settings['qp_paypal_mode'] : 'test';

		if ( empty( $email ) ) {
			wp_send_json_error( array( 'message' => 'Email de PayPal no configurado.' ) );
		}

		$base_price = floatval( $product['price'] );
		$discount   = 0.0;
		$coupon_code = isset( $_POST['coupon_code'] ) ? strtoupper( sanitize_text_field( $_POST['coupon_code'] ) ) : '';
		if ( ! empty( $coupon_code ) ) {
			$coupons = self::get_coupons();
			if ( ! empty( $coupons[ $coupon_code ] ) ) {
				$c = $coupons[ $coupon_code ];
				$discount = ( 'percent' === $c['type'] ) ? ( $base_price * floatval( $c['amount'] ) ) / 100 : floatval( $c['amount'] );
				$discount = min( $discount, $base_price );
			}
		}

		$shipping_cost = ( 'physical' === $product['type'] && ! empty( $product['shipping_cost'] ) ) ? floatval( $product['shipping_cost'] ) : 0.0;
		$final_amount  = max( 0.50, ( $base_price - $discount ) + $shipping_cost );

		$billing_company  = isset( $_POST['billing_company'] ) ? sanitize_text_field( $_POST['billing_company'] ) : '';
		$billing_address  = isset( $_POST['billing_address'] ) ? sanitize_text_field( $_POST['billing_address'] ) : '';
		$billing_city     = isset( $_POST['billing_city'] ) ? sanitize_text_field( $_POST['billing_city'] ) : '';
		$billing_postcode = isset( $_POST['billing_postcode'] ) ? sanitize_text_field( $_POST['billing_postcode'] ) : '';
		$billing_state    = isset( $_POST['billing_state'] ) ? sanitize_text_field( $_POST['billing_state'] ) : '';
		$billing_country  = isset( $_POST['billing_country'] ) ? sanitize_text_field( $_POST['billing_country'] ) : 'ES';

		$shipping_addr    = isset( $_POST['shipping_address'] ) ? sanitize_text_field( $_POST['shipping_address'] ) : $billing_address;
		$shipping_city    = isset( $_POST['shipping_city'] ) ? sanitize_text_field( $_POST['shipping_city'] ) : $billing_city;
		$shipping_post    = isset( $_POST['shipping_postcode'] ) ? sanitize_text_field( $_POST['shipping_postcode'] ) : $billing_postcode;
		$shipping_state   = isset( $_POST['shipping_state'] ) ? sanitize_text_field( $_POST['shipping_state'] ) : $billing_state;
		$shipping_country = isset( $_POST['shipping_country'] ) ? sanitize_text_field( $_POST['shipping_country'] ) : $billing_country;

		$order_id = $this->create_order_record(
			array(
				'product_id'        => $product['id'],
				'product_name'      => $product['name'],
				'product_type'      => $product['type'],
				'amount'            => $final_amount,
				'currency'          => isset( $product['currency'] ) ? $product['currency'] : 'EUR',
				'customer_name'     => isset( $_POST['customer_name'] ) ? sanitize_text_field( $_POST['customer_name'] ) : '',
				'customer_email'    => isset( $_POST['customer_email'] ) ? sanitize_email( $_POST['customer_email'] ) : '',
				'customer_phone'    => isset( $_POST['customer_phone'] ) ? sanitize_text_field( $_POST['customer_phone'] ) : '',
				'customer_dni'      => isset( $_POST['customer_dni'] ) ? sanitize_text_field( $_POST['customer_dni'] ) : '',
				'billing_company'   => $billing_company,
				'billing_address'   => $billing_address,
				'billing_city'      => $billing_city,
				'billing_postcode'  => $billing_postcode,
				'billing_state'     => $billing_state,
				'billing_country'   => $billing_country,
				'shipping_address'  => $shipping_addr,
				'shipping_city'     => $shipping_city,
				'shipping_postcode' => $shipping_post,
				'shipping_state'    => $shipping_state,
				'shipping_country'  => $shipping_country,
				'shipping_cost'     => $shipping_cost,
				'coupon_code'       => $coupon_code,
				'discount_amount'   => $discount,
				'gateway'           => 'paypal',
				'status'            => 'pending',
				'is_recurring'      => 0,
			)
		);

		$order_key = $this->get_order_key_by_id( $order_id );

		$return_url = add_query_arg(
			array(
				'wpat_qp_action' => 'paypal_return',
				'order_key'      => $order_key,
			),
			home_url( '/' )
		);

		$cancel_url = add_query_arg(
			array(
				'wpat_qp_action' => 'paypal_cancel',
				'order_key'      => $order_key,
			),
			home_url( '/' )
		);

		$paypal_base = ( 'live' === $mode ) ? 'https://www.paypal.com/cgi-bin/webscr' : 'https://www.sandbox.paypal.com/cgi-bin/webscr';

		$query_args = array(
			'cmd'           => '_xclick',
			'business'      => $email,
			'item_name'     => $product['name'],
			'item_number'   => $order_id,
			'amount'        => number_format( $final_amount, 2, '.', '' ),
			'currency_code' => isset( $product['currency'] ) ? $product['currency'] : 'EUR',
			'return'        => $return_url,
			'cancel_return' => $cancel_url,
			'custom'        => $order_key,
		);

		$redirect_url = add_query_arg( $query_args, $paypal_base );

		wp_send_json_success( array( 'redirect_url' => $redirect_url ) );
	}

	/**
	 * Genera la firma SHA-256 HMAC para Redsys con clave diversificada en 3DES.
	 *
	 * @param string $merchant_params_b64 Parámetros en Base64.
	 * @param string $order_num           Número de pedido de Redsys.
	 * @param string $secret_key          Clave secreta SHA-256 de Redsys.
	 * @return string
	 */
	private function generate_redsys_signature( $merchant_params_b64, $order_num, $secret_key ) {
		$key = base64_decode( $secret_key );
		// Cifrado 3DES (DES-EDE3-CBC) de la clave con el número de pedido (relleno de ceros a múltiplos de 8)
		$padding = 8 - ( strlen( $order_num ) % 8 );
		$order_padded = $order_num . str_repeat( "\0", $padding );
		$diversified_key = openssl_encrypt( $order_padded, 'DES-EDE3-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, "\0\0\0\0\0\0\0\0" );
		// Hash HMAC SHA-256
		$res = hash_hmac( 'sha256', $merchant_params_b64, $diversified_key, true );
		return base64_encode( $res );
	}

	/**
	 * Maneja las redirecciones de retorno de pasarelas y webhooks IPN de Redsys / Stripe / PayPal.
	 */
	public function handle_payment_return_and_webhooks() {
		// 1. Webhook IPN oficial de Redsys / Bizum SIS (Server-to-Server)
		if ( isset( $_GET['wpat_qp_webhook'] ) && in_array( $_GET['wpat_qp_webhook'], array( 'redsys', 'bizum_redsys' ), true ) ) {
			$params_b64 = isset( $_POST['Ds_MerchantParameters'] ) ? $_POST['Ds_MerchantParameters'] : ( isset( $_GET['Ds_MerchantParameters'] ) ? $_GET['Ds_MerchantParameters'] : '' );
			$signature  = isset( $_POST['Ds_Signature'] ) ? $_POST['Ds_Signature'] : ( isset( $_GET['Ds_Signature'] ) ? $_GET['Ds_Signature'] : '' );

			if ( ! empty( $params_b64 ) && ! empty( $signature ) ) {
				$decoded_json = base64_decode( strtr( $params_b64, '-_', '+/' ) );
				$params = json_decode( $decoded_json, true );

				if ( is_array( $params ) && isset( $params['Ds_Order'] ) && isset( $params['Ds_Response'] ) ) {
					$order_num = $params['Ds_Order'];
					$response_code = intval( $params['Ds_Response'] );
					$auth_code = isset( $params['Ds_AuthorisationCode'] ) ? sanitize_text_field( $params['Ds_AuthorisationCode'] ) : '';
					$merchant_data = isset( $params['Ds_MerchantData'] ) ? sanitize_key( urldecode( $params['Ds_MerchantData'] ) ) : '';

					$settings = WPAT_Main::get_instance()->get_settings();
					$keys_to_test = array();

					if ( 'bizum_redsys' === $_GET['wpat_qp_webhook'] && ! empty( $settings['qp_bizum_redsys_key'] ) ) {
						$keys_to_test[] = trim( $settings['qp_bizum_redsys_key'] );
					}
					if ( ! empty( $settings['qp_redsys_key'] ) ) {
						$keys_to_test[] = trim( $settings['qp_redsys_key'] );
					}

					$sig_valid = false;
					foreach ( $keys_to_test as $test_key ) {
						$calculated_sig = $this->generate_redsys_signature( $params_b64, $order_num, $test_key );
						$calc_url_safe  = strtr( $calculated_sig, '+/', '-_' );
						$recv_url_safe  = strtr( $signature, '+/', '-_' );

						if ( hash_equals( $calculated_sig, $signature ) || hash_equals( $calc_url_safe, $recv_url_safe ) ) {
							$sig_valid = true;
							break;
						}
					}

					// Si la firma es correcta y la respuesta está entre 0000 y 0099 (Transacción aprobada)
					if ( $sig_valid && $response_code >= 0 && $response_code <= 99 ) {
						$order = null;
						if ( ! empty( $merchant_data ) ) {
							$order = $this->get_order_by_key( $merchant_data );
						}

						if ( ! $order ) {
							// Extraer ID del pedido del número Redsys (últimos 6 dígitos)
							$order_id = intval( substr( $order_num, 6 ) );
							$order = $this->get_order_by_id( $order_id );
						}

						if ( $order && 'completed' !== $order->status ) {
							$this->complete_order( $order->id, $auth_code ? $auth_code : 'REDSYS-' . $order_num );
						}

						status_header( 200 );
						echo 'OK';
						exit;
					}
				}
			}

			status_header( 400 );
			echo 'BAD REQUEST';
			exit;
		}

		// 2. Retorno en frontend del cliente
		if ( isset( $_GET['wpat_qp_action'] ) ) {
			$action    = sanitize_key( $_GET['wpat_qp_action'] );
			$order_key = isset( $_GET['order_key'] ) ? sanitize_key( $_GET['order_key'] ) : '';

			if ( ! empty( $order_key ) ) {
				$order = $this->get_order_by_key( $order_key );
				if ( $order ) {
					if ( 'stripe_return' === $action || 'redsys_return' === $action || 'paypal_return' === $action ) {
						$tx_id = sanitize_text_field( isset( $_GET['session_id'] ) ? $_GET['session_id'] : ( 'RET-' . time() ) );
						if ( 'completed' !== $order->status ) {
							$this->complete_order( $order->id, $tx_id );
						}
					}
				}
			}
		}
	}

	/**
	 * Maneja la descarga protegida de archivos digitales.
	 */
	public function handle_secure_digital_download() {
		if ( isset( $_GET['wpat_download_token'] ) && ! empty( $_GET['wpat_download_token'] ) ) {
			$token = sanitize_key( $_GET['wpat_download_token'] );
			global $wpdb;

			$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_orders} WHERE download_token = %s AND status = 'completed'", $token ) );

			if ( $order ) {
				$product = self::get_product( $order->product_id );
				if ( $product && ! empty( $product['download_file'] ) ) {
					// Incrementar contador
					$wpdb->query( $wpdb->prepare( "UPDATE {$this->table_orders} SET download_count = download_count + 1 WHERE id = %d", $order->id ) );

					$file_url = $product['download_file'];
					wp_redirect( $file_url );
					exit;
				}
			}

			wp_die( '<h1>Enlace de descarga no válido o caducado</h1><p>No se ha encontrado un archivo válido asociado a este enlace de compra.</p>', 'Error de Descarga', array( 'response' => 403 ) );
		}
	}

	/**
	 * Crea un nuevo registro de pedido en la base de datos.
	 *
	 * @param array $args Datos del pedido.
	 * @return int ID del pedido insertado.
	 */
	public function create_order_record( $args ) {
		global $wpdb;

		$order_key = 'wpat_' . wp_generate_password( 24, false );
		$token     = wp_generate_password( 32, false );

		$settings = WPAT_Main::get_instance()->get_settings();
		$prefix   = isset( $settings['qp_invoice_prefix'] ) && ! empty( $settings['qp_invoice_prefix'] ) ? $settings['qp_invoice_prefix'] : 'FAC-' . date( 'Y' ) . '-';

		$amount = isset( $args['amount'] ) ? floatval( $args['amount'] ) : 0.0;
		$tax_rate = isset( $args['tax_rate'] ) ? floatval( $args['tax_rate'] ) : 21.0;
		$tax_amount = ( $amount * $tax_rate ) / ( 100 + $tax_rate );

		$data = array(
			'order_key'         => $order_key,
			'created_at'        => current_time( 'mysql' ),
			'product_id'        => isset( $args['product_id'] ) ? $args['product_id'] : '',
			'product_name'      => isset( $args['product_name'] ) ? $args['product_name'] : '',
			'product_type'      => isset( $args['product_type'] ) ? $args['product_type'] : 'service',
			'amount'            => $amount,
			'currency'          => isset( $args['currency'] ) ? $args['currency'] : 'EUR',
			'customer_name'     => isset( $args['customer_name'] ) ? $args['customer_name'] : '',
			'customer_email'    => isset( $args['customer_email'] ) ? $args['customer_email'] : '',
			'customer_phone'    => isset( $args['customer_phone'] ) ? $args['customer_phone'] : '',
			'customer_dni'      => isset( $args['customer_dni'] ) ? $args['customer_dni'] : '',
			'billing_company'   => isset( $args['billing_company'] ) ? $args['billing_company'] : '',
			'billing_address'   => isset( $args['billing_address'] ) ? $args['billing_address'] : '',
			'billing_city'      => isset( $args['billing_city'] ) ? $args['billing_city'] : '',
			'billing_postcode'  => isset( $args['billing_postcode'] ) ? $args['billing_postcode'] : '',
			'billing_state'     => isset( $args['billing_state'] ) ? $args['billing_state'] : '',
			'billing_country'   => isset( $args['billing_country'] ) ? $args['billing_country'] : 'ES',
			'shipping_address'  => isset( $args['shipping_address'] ) ? $args['shipping_address'] : '',
			'shipping_city'     => isset( $args['shipping_city'] ) ? $args['shipping_city'] : '',
			'shipping_postcode' => isset( $args['shipping_postcode'] ) ? $args['shipping_postcode'] : '',
			'shipping_state'    => isset( $args['shipping_state'] ) ? $args['shipping_state'] : '',
			'shipping_country'  => isset( $args['shipping_country'] ) ? $args['shipping_country'] : 'ES',
			'shipping_cost'     => isset( $args['shipping_cost'] ) ? floatval( $args['shipping_cost'] ) : 0.0,
			'tax_rate'          => $tax_rate,
			'tax_amount'        => round( $tax_amount, 2 ),
			'coupon_code'       => isset( $args['coupon_code'] ) ? $args['coupon_code'] : '',
			'discount_amount'   => isset( $args['discount_amount'] ) ? floatval( $args['discount_amount'] ) : 0.0,
			'gateway'           => isset( $args['gateway'] ) ? $args['gateway'] : 'manual',
			'status'            => isset( $args['status'] ) ? $args['status'] : 'pending',
			'is_recurring'      => isset( $args['is_recurring'] ) ? intval( $args['is_recurring'] ) : 0,
			'download_token'    => $token,
		);

		$wpdb->insert( $this->table_orders, $data );
		$order_id = $wpdb->insert_id;

		// Asignar número de factura correlativo
		$invoice_num = $prefix . str_pad( (string) $order_id, 4, '0', STR_PAD_LEFT );
		$wpdb->update( $this->table_orders, array( 'invoice_number' => $invoice_num ), array( 'id' => $order_id ) );

		return $order_id;
	}

	/**
	 * Marca un pedido como completado y dispara los correos y generación de factura.
	 *
	 * @param int    $order_id       ID del pedido.
	 * @param string $transaction_id ID de transacción externa.
	 */
	public function complete_order( $order_id, $transaction_id = '' ) {
		global $wpdb;

		$order = $this->get_order_by_id( $order_id );
		if ( ! $order ) {
			return;
		}

		$wpdb->update(
			$this->table_orders,
			array(
				'status'         => 'completed',
				'transaction_id' => ! empty( $transaction_id ) ? $transaction_id : $order->transaction_id,
			),
			array( 'id' => $order_id )
		);

		// Incrementar contador de uso de cupón
		if ( ! empty( $order->coupon_code ) ) {
			$this->increment_coupon_usage( $order->coupon_code );
		}

		// Enviar emails de confirmación y factura
		$this->send_order_emails( $order_id, true );
	}

	/**
	 * Incrementa el contador de uso de un cupón.
	 *
	 * @param string $code Código del cupón.
	 */
	private function increment_coupon_usage( $code ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		$coupons  = isset( $settings['qp_coupons'] ) && is_array( $settings['qp_coupons'] ) ? $settings['qp_coupons'] : array();

		if ( isset( $coupons[ $code ] ) ) {
			$coupons[ $code ]['usage_count'] = isset( $coupons[ $code ]['usage_count'] ) ? intval( $coupons[ $code ]['usage_count'] ) + 1 : 1;
			$settings['qp_coupons']          = $coupons;
			update_option( 'wpat_settings', $settings );
		}
	}

	/**
	 * Envía las notificaciones por correo electrónico.
	 *
	 * @param int  $order_id     ID del pedido.
	 * @param bool $is_completed Si el pedido ya está pagado.
	 */
	public function send_order_emails( $order_id, $is_completed = false ) {
		$order = $this->get_order_by_id( $order_id );
		if ( ! $order || empty( $order->customer_email ) ) {
			return;
		}

		$settings  = WPAT_Main::get_instance()->get_settings();
		$site_name = get_bloginfo( 'name' );

		// 1. Email para el Comprador
		$subject = $is_completed ? "¡Confirmación de tu compra en $site_name! [Pedido #WPAT-{$order->id}]" : "Detalles de tu pedido pendiente en $site_name [#WPAT-{$order->id}]";

		$download_link = '';
		if ( 'digital' === $order->product_type && ! empty( $order->download_token ) ) {
			$download_url = add_query_arg( 'wpat_download_token', $order->download_token, home_url( '/' ) );
			$download_link = '<div style="margin: 20px 0; padding: 16px; background: #ecfdf5; border-radius: 8px; border: 1px solid #a7f3d0; text-align: center;">'
				. '<p style="margin: 0 0 10px 0; font-weight: 700; color: #065f46;">Tu producto digital está listo para descargar:</p>'
				. '<a href="' . esc_url( $download_url ) . '" style="display: inline-block; background: #059669; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 700;">📥 Descargar ' . esc_html( $order->product_name ) . '</a>'
				. '</div>';
		}

		$billing_info_html = '';
		if ( ! empty( $order->billing_address ) || ! empty( $order->customer_dni ) || ! empty( $order->billing_company ) ) {
			$billing_info_html = '<div style="margin: 16px 0; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px;">'
				. '<strong style="color: #475569; text-transform: uppercase; font-size: 11px;">Datos de Facturación:</strong><br>'
				. ( $order->billing_company ? '<strong>' . esc_html( $order->billing_company ) . '</strong><br>' : '' )
				. ( $order->customer_dni ? 'NIF/CIF: ' . esc_html( $order->customer_dni ) . '<br>' : '' )
				. ( $order->billing_address ? esc_html( $order->billing_address ) . '<br>' : '' )
				. ( $order->billing_postcode || $order->billing_city ? esc_html( $order->billing_postcode . ' ' . $order->billing_city ) . '<br>' : '' )
				. '</div>';
		}

		$body = '<div style="font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">'
			. '<div style="background: #2563eb; color: #ffffff; padding: 24px; text-align: center;">'
			. '<h1 style="margin: 0; font-size: 22px; font-weight: 800;">' . esc_html( $site_name ) . '</h1>'
			. '<p style="margin: 6px 0 0 0; font-size: 14px; opacity: 0.9;">' . ( $is_completed ? '¡Gracias por tu compra!' : 'Hemos recibido tu pedido' ) . '</p>'
			. '</div>'
			. '<div style="padding: 24px; color: #334155; font-size: 14.5px; line-height: 1.6;">'
			. '<p>Hola <strong>' . esc_html( $order->customer_name ) . '</strong>,</p>'
			. '<p>' . ( $is_completed ? 'Tu pago ha sido procesado correctamente. Aquí tienes el resumen de tu compra:' : 'Tu pedido ha sido registrado con éxito y se encuentra pendiente de verificación de pago.' ) . '</p>'
			. '<table style="width: 100%; border-collapse: collapse; margin: 18px 0;">'
			. '<tr style="border-bottom: 2px solid #e2e8f0; background: #f8fafc;"><th style="padding: 10px; text-align: left;">Concepto</th><th style="padding: 10px; text-align: right;">Total</th></tr>'
			. '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 10px;"><strong>' . esc_html( $order->product_name ) . '</strong><br><small style="color: #64748b;">Nº Factura: ' . esc_html( $order->invoice_number ) . '</small></td><td style="padding: 12px 10px; text-align: right; font-weight: 700;">' . number_format( $order->amount, 2, ',', '.' ) . ' ' . esc_html( $order->currency ) . '</td></tr>'
			. '</table>'
			. $billing_info_html
			. $download_link
			. '<p style="color: #64748b; font-size: 12.5px; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 16px;">Si tienes alguna pregunta sobre tu pedido, responde directamente a este correo.</p>'
			. '</div>'
			. '</div>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $order->customer_email, $subject, $body, $headers );

		// 2. Email para el Administrador
		$admin_email = get_option( 'admin_email' );
		$admin_subject = "🛒 [Nueva Venta] #WPAT-{$order->id} - " . number_format( $order->amount, 2, ',', '.' ) . ' ' . $order->currency;
		$admin_body = "Se ha registrado una nueva compra en $site_name:\n\n"
			. "ID Pedido: #WPAT-{$order->id}\n"
			. "Factura: {$order->invoice_number}\n"
			. "Cliente: {$order->customer_name} ({$order->customer_email})\n"
			. "Teléfono: {$order->customer_phone}\n"
			. "DNI/NIF/CIF: {$order->customer_dni}\n"
			. "Empresa: {$order->billing_company}\n"
			. "Dirección Facturación: {$order->billing_address}, {$order->billing_postcode} {$order->billing_city} ({$order->billing_country})\n"
			. "Producto: {$order->product_name}\n"
			. "Importe: " . number_format( $order->amount, 2, ',', '.' ) . " {$order->currency}\n"
			. "Pasarela: {$order->gateway}\n"
			. "Estado: {$order->status}\n\n"
			. "Puedes gestionar este pedido desde el panel de WP Agency Toolkit.";

		wp_mail( $admin_email, $admin_subject, $admin_body );
	}

	/**
	 * Obtiene un pedido por su ID.
	 *
	 * @param int $order_id ID del pedido.
	 * @return object|null
	 */
	public function get_order_by_id( $order_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_orders} WHERE id = %d", $order_id ) );
	}

	/**
	 * Obtiene el order_key a partir del ID.
	 *
	 * @param int $order_id ID del pedido.
	 * @return string
	 */
	public function get_order_key_by_id( $order_id ) {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT order_key FROM {$this->table_orders} WHERE id = %d", $order_id ) );
	}

	/**
	 * Obtiene un pedido por su clave única.
	 *
	 * @param string $order_key Clave del pedido.
	 * @return object|null
	 */
	public function get_order_by_key( $order_key ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_orders} WHERE order_key = %s", $order_key ) );
	}

	/**
	 * Actualiza metadatos de un pedido.
	 *
	 * @param int    $order_id ID del pedido.
	 * @param string $meta_key Clave meta.
	 * @param mixed  $value    Valor meta.
	 */
	public function update_order_meta( $order_id, $meta_key, $value ) {
		global $wpdb;
		$order = $this->get_order_by_id( $order_id );
		if ( ! $order ) {
			return;
		}

		$meta = ! empty( $order->metadata ) ? json_decode( $order->metadata, true ) : array();
		$meta[ $meta_key ] = $value;

		$wpdb->update(
			$this->table_orders,
			array( 'metadata' => wp_json_encode( $meta ) ),
			array( 'id' => $order_id )
		);
	}

	/**
	 * Obtiene todos los pedidos para la tabla de administración.
	 *
	 * @param int $limit Número de pedidos.
	 * @return array
	 */
	public static function get_all_orders( $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wpat_quick_pay_orders';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", $limit ) );
	}

	/**
	 * AJAX Admin: Guardar / Editar Producto.
	 */
	public function ajax_admin_save_product() {
		check_ajax_referer( 'wpat_quick_pay_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? sanitize_key( $_POST['product_id'] ) : '';
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$price      = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0.0;
		$type       = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'service';
		$desc       = isset( $_POST['desc'] ) ? sanitize_textarea_field( $_POST['desc'] ) : '';
		$image_url  = isset( $_POST['image_url'] ) ? esc_url_raw( $_POST['image_url'] ) : '';
		$file_url   = isset( $_POST['download_file'] ) ? esc_url_raw( $_POST['download_file'] ) : '';
		$shipping   = isset( $_POST['shipping_cost'] ) ? floatval( $_POST['shipping_cost'] ) : 0.0;
		$tax_rate   = isset( $_POST['tax_rate'] ) ? floatval( $_POST['tax_rate'] ) : 21.0;
		$btn_text   = isset( $_POST['button_text'] ) ? sanitize_text_field( $_POST['button_text'] ) : '';
		$recurring  = ! empty( $_POST['is_recurring'] ) ? 1 : 0;
		$interval   = isset( $_POST['billing_interval'] ) ? sanitize_key( $_POST['billing_interval'] ) : 'month';

		if ( empty( $name ) || $price <= 0 ) {
			wp_send_json_error( array( 'message' => 'Introduce un nombre válido y un precio mayor a 0.' ) );
		}

		if ( empty( $product_id ) ) {
			$product_id = 'prod_' . wp_generate_password( 8, false, false );
		}

		$settings = get_option( 'wpat_settings', array() );
		$products = isset( $settings['qp_products'] ) && is_array( $settings['qp_products'] ) ? $settings['qp_products'] : array();

		$products[ $product_id ] = array(
			'id'               => $product_id,
			'name'             => $name,
			'price'            => $price,
			'currency'         => isset( $settings['qp_currency'] ) ? $settings['qp_currency'] : 'EUR',
			'type'             => $type,
			'desc'             => $desc,
			'image_url'        => $image_url,
			'download_file'    => $file_url,
			'shipping_cost'    => $shipping,
			'tax_rate'         => $tax_rate,
			'button_text'      => $btn_text,
			'is_recurring'     => $recurring,
			'billing_interval' => $interval,
			'require_phone'    => 1,
			'require_dni'      => 1,
		);

		$settings['qp_products'] = $products;
		update_option( 'wpat_settings', $settings );

		wp_send_json_success( array( 'message' => 'Producto guardado con éxito.', 'product_id' => $product_id ) );
	}

	/**
	 * AJAX Admin: Eliminar Producto.
	 */
	public function ajax_admin_delete_product() {
		check_ajax_referer( 'wpat_quick_pay_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? sanitize_key( $_POST['product_id'] ) : '';

		$settings = get_option( 'wpat_settings', array() );
		$products = isset( $settings['qp_products'] ) && is_array( $settings['qp_products'] ) ? $settings['qp_products'] : array();

		if ( isset( $products[ $product_id ] ) ) {
			unset( $products[ $product_id ] );
			$settings['qp_products'] = $products;
			update_option( 'wpat_settings', $settings );
			wp_send_json_success( array( 'message' => 'Producto eliminado correctamente.' ) );
		}

		wp_send_json_error( array( 'message' => 'Producto no encontrado.' ) );
	}

	/**
	 * AJAX Admin: Guardar Cupón.
	 */
	public function ajax_admin_save_coupon() {
		check_ajax_referer( 'wpat_quick_pay_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$code   = isset( $_POST['code'] ) ? strtoupper( sanitize_text_field( trim( $_POST['code'] ) ) ) : '';
		$type   = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'percent';
		$amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0.0;
		$expiry = isset( $_POST['expiry'] ) ? sanitize_text_field( $_POST['expiry'] ) : '';
		$limit  = isset( $_POST['usage_limit'] ) ? intval( $_POST['usage_limit'] ) : 0;

		if ( empty( $code ) || $amount <= 0 ) {
			wp_send_json_error( array( 'message' => 'Introduce un código válido y un importe de descuento mayor a 0.' ) );
		}

		$settings = get_option( 'wpat_settings', array() );
		$coupons  = isset( $settings['qp_coupons'] ) && is_array( $settings['qp_coupons'] ) ? $settings['qp_coupons'] : array();

		$usage_count = isset( $coupons[ $code ]['usage_count'] ) ? intval( $coupons[ $code ]['usage_count'] ) : 0;

		$coupons[ $code ] = array(
			'code'        => $code,
			'type'        => $type,
			'amount'      => $amount,
			'expiry'      => $expiry,
			'usage_limit' => $limit,
			'usage_count' => $usage_count,
		);

		$settings['qp_coupons'] = $coupons;
		update_option( 'wpat_settings', $settings );

		wp_send_json_success( array( 'message' => 'Cupón guardado con éxito.' ) );
	}

	/**
	 * AJAX Admin: Eliminar Cupón.
	 */
	public function ajax_admin_delete_coupon() {
		check_ajax_referer( 'wpat_quick_pay_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$code = isset( $_POST['code'] ) ? strtoupper( sanitize_text_field( trim( $_POST['code'] ) ) ) : '';

		$settings = get_option( 'wpat_settings', array() );
		$coupons  = isset( $settings['qp_coupons'] ) && is_array( $settings['qp_coupons'] ) ? $settings['qp_coupons'] : array();

		if ( isset( $coupons[ $code ] ) ) {
			unset( $coupons[ $code ] );
			$settings['qp_coupons'] = $coupons;
			update_option( 'wpat_settings', $settings );
			wp_send_json_success( array( 'message' => 'Cupón eliminado correctamente.' ) );
		}

		wp_send_json_error( array( 'message' => 'Cupón no encontrado.' ) );
	}

	/**
	 * AJAX Admin: Actualizar Estado de Pedido.
	 */
	public function ajax_admin_update_order_status() {
		check_ajax_referer( 'wpat_quick_pay_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';

		if ( empty( $order_id ) || empty( $status ) ) {
			wp_send_json_error( array( 'message' => 'Datos de pedido no válidos.' ) );
		}

		global $wpdb;

		if ( 'completed' === $status ) {
			$this->complete_order( $order_id );
		} else {
			$wpdb->update(
				$this->table_orders,
				array( 'status' => $status ),
				array( 'id' => $order_id )
			);
		}

		wp_send_json_success( array( 'message' => 'Estado del pedido actualizado con éxito.' ) );
	}

	/**
	 * AJAX Admin: Eliminar Pedido.
	 */
	public function ajax_admin_delete_order() {
		check_ajax_referer( 'wpat_quick_pay_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

		global $wpdb;
		$wpdb->delete( $this->table_orders, array( 'id' => $order_id ) );

		wp_send_json_success( array( 'message' => 'Pedido eliminado de la base de datos.' ) );
	}

	/**
	 * AJAX Admin: Exportar ventas a CSV.
	 */
	public function ajax_admin_export_csv() {
		check_ajax_referer( 'wpat_quick_pay_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permisos insuficientes.' );
		}

		$orders = self::get_all_orders( 1000 );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wpat_ventas_' . date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv(
			$output,
			array(
				'ID Pedido',
				'Nº Factura',
				'Fecha',
				'Cliente',
				'Email',
				'Teléfono',
				'DNI/NIF/CIF',
				'Empresa',
				'Dirección Facturación',
				'CP Facturación',
				'Ciudad Facturación',
				'Provincia',
				'País',
				'Producto',
				'Tipo',
				'Importe Total',
				'IVA (%)',
				'Cuota IVA',
				'Moneda',
				'Pasarela',
				'Estado',
				'Transacción ID',
				'Cupón',
				'Descuento',
			)
		);

		foreach ( $orders as $o ) {
			fputcsv(
				$output,
				array(
					'#WPAT-' . $o->id,
					$o->invoice_number,
					$o->created_at,
					$o->customer_name,
					$o->customer_email,
					$o->customer_phone,
					$o->customer_dni,
					$o->billing_company ?? '',
					$o->billing_address ?? '',
					$o->billing_postcode ?? '',
					$o->billing_city ?? '',
					$o->billing_state ?? '',
					$o->billing_country ?? 'ES',
					$o->product_name,
					$o->product_type,
					number_format( (float) $o->amount, 2, '.', '' ),
					$o->tax_rate,
					number_format( (float) $o->tax_amount, 2, '.', '' ),
					$o->currency,
					$o->gateway,
					$o->status,
					$o->transaction_id,
					$o->coupon_code,
					number_format( (float) $o->discount_amount, 2, '.', '' ),
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX Admin: Descargar Factura PDF de un pedido.
	 */
	public function ajax_admin_download_pdf() {
		check_ajax_referer( 'wpat_quick_pay_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permisos insuficientes.' );
		}

		$order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
		$order = $this->get_order_by_id( $order_id );

		if ( ! $order ) {
			wp_die( 'Pedido no encontrado.' );
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$company_name = isset( $settings['qp_company_name'] ) && ! empty( $settings['qp_company_name'] ) ? $settings['qp_company_name'] : get_bloginfo( 'name' );
		$company_cif  = isset( $settings['qp_company_cif'] ) ? $settings['qp_company_cif'] : '';
		$company_addr = isset( $settings['qp_company_address'] ) ? $settings['qp_company_address'] : '';

		// Generación de HTML imprimible / Factura en PDF nativo de navegador
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
		<!DOCTYPE html>
		<html lang="es">
		<head>
			<meta charset="UTF-8">
			<title>Factura <?php echo esc_html( $order->invoice_number ); ?></title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; padding: 40px; margin: 0; background: #fff; }
				.invoice-box { max-width: 750px; margin: auto; border: 1px solid #e2e8f0; padding: 30px; border-radius: 8px; }
				.header { display: flex; justify-content: space-between; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 20px; }
				.title { font-size: 24px; font-weight: 800; color: #2563eb; }
				.cols { display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 13.5px; }
				table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
				th { background: #f8fafc; border-bottom: 2px solid #cbd5e1; padding: 10px; text-align: left; font-size: 13px; }
				td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; font-size: 13.5px; }
				.totals { margin-left: auto; width: 280px; font-size: 14px; }
				.totals-row { display: flex; justify-content: space-between; padding: 4px 0; }
				.total-final { font-size: 18px; font-weight: 800; color: #2563eb; border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 6px; }
				@media print { body { padding: 0; } .invoice-box { border: none; } .print-btn { display: none; } }
			</style>
		</head>
		<body>
			<div style="text-align: right; max-width: 750px; margin: 0 auto 15px auto;">
				<button type="button" class="print-btn" onclick="window.print()" style="background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer;">🖨️ Imprimir / Guardar en PDF</button>
			</div>
			<div class="invoice-box">
				<div class="header">
					<div>
						<div class="title"><?php echo esc_html( $company_name ); ?></div>
						<div style="font-size: 12.5px; color: #64748b; margin-top: 4px;"><?php echo nl2br( esc_html( $company_addr ) ); ?><br>CIF/NIF: <?php echo esc_html( $company_cif ); ?></div>
					</div>
					<div style="text-align: right;">
						<h2 style="margin: 0; font-size: 20px; color: #0f172a;">FACTURA</h2>
						<div style="font-weight: 700; color: #2563eb; margin-top: 4px;"><?php echo esc_html( $order->invoice_number ); ?></div>
						<div style="font-size: 12px; color: #64748b;">Fecha: <?php echo esc_html( date( 'd/m/Y', strtotime( $order->created_at ) ) ); ?></div>
					</div>
				</div>

				<div class="cols">
					<div>
						<strong style="color: #64748b; text-transform: uppercase; font-size: 11px;">Facturar a:</strong><br>
						<strong style="font-size: 15px; color: #0f172a;"><?php echo esc_html( $order->customer_name ); ?></strong><br>
						<?php if ( ! empty( $order->billing_company ) ) : ?>
							<strong><?php echo esc_html( $order->billing_company ); ?></strong><br>
						<?php endif; ?>
						<?php if ( ! empty( $order->customer_dni ) ) : ?>
							NIF/CIF: <?php echo esc_html( $order->customer_dni ); ?><br>
						<?php endif; ?>
						<?php if ( ! empty( $order->billing_address ) ) : ?>
							<?php echo esc_html( $order->billing_address ); ?><br>
							<?php echo esc_html( ( $order->billing_postcode ? $order->billing_postcode . ' ' : '' ) . ( $order->billing_city ?? '' ) . ( $order->billing_state ? ' (' . $order->billing_state . ')' : '' ) ); ?><br>
							<?php echo esc_html( $order->billing_country ?? 'ES' ); ?><br>
						<?php endif; ?>
						Email: <?php echo esc_html( $order->customer_email ); ?><br>
						<?php if ( ! empty( $order->customer_phone ) ) : ?>
							Tel: <?php echo esc_html( $order->customer_phone ); ?><br>
						<?php endif; ?>
					</div>
					<div style="text-align: right;">
						<strong style="color: #64748b; text-transform: uppercase; font-size: 11px;">Detalles del Pago:</strong><br>
						Método: <?php echo esc_html( strtoupper( str_replace( '_', ' ', $order->gateway ) ) ); ?><br>
						Estado: <strong><?php echo ( 'completed' === $order->status ) ? 'PAGADO' : 'PENDIENTE'; ?></strong><br>
						Transacción: <code><?php echo esc_html( $order->transaction_id ? $order->transaction_id : '#WPAT-' . $order->id ); ?></code>
					</div>
				</div>

				<table>
					<thead>
						<tr>
							<th>Descripción</th>
							<th>Tipo</th>
							<th style="text-align: right;">Importe</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php echo esc_html( $order->product_name ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $order->product_type ) ); ?></td>
							<td style="text-align: right; font-weight: 700;"><?php echo number_format( $order->amount, 2, ',', '.' ) . ' ' . esc_html( $order->currency ); ?></td>
						</tr>
					</tbody>
				</table>

				<div class="totals">
					<?php
					$tax_rate = floatval( $order->tax_rate ) > 0 ? floatval( $order->tax_rate ) : 21.0;
					$base_imponible = $order->amount / ( 1 + ( $tax_rate / 100 ) );
					$cuota_iva = $order->amount - $base_imponible;
					?>
					<div class="totals-row">
						<span>Base Imponible:</span>
						<span><?php echo number_format( $base_imponible, 2, ',', '.' ) . ' ' . esc_html( $order->currency ); ?></span>
					</div>
					<div class="totals-row">
						<span>IVA (<?php echo esc_html( $tax_rate ); ?>%):</span>
						<span><?php echo number_format( $cuota_iva, 2, ',', '.' ) . ' ' . esc_html( $order->currency ); ?></span>
					</div>
					<div class="totals-row total-final">
						<span>TOTAL FACTURA:</span>
						<span><?php echo number_format( $order->amount, 2, ',', '.' ) . ' ' . esc_html( $order->currency ); ?></span>
					</div>
				</div>
			</div>
		</body>
		</html>
		<?php
		exit;
	}
}

// Inicializar módulo
WPAT_Quick_Pay::get_instance();

<?php
/**
 * Módulo: Diseñador de Plantillas de Email para WooCommerce - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Email_Designer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Email_Designer
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Email_Designer
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
		$settings = WPAT_Main::get_instance()->get_settings();
		$enabled  = isset( $settings['woo-email-designer'] ) && '1' === $settings['woo-email-designer'];

		if ( $enabled ) {
			add_filter( 'woocommerce_locate_template', array( $this, 'override_email_template' ), 9999, 3 );
			add_filter( 'woocommerce_email_styles', array( $this, 'custom_email_styles' ), 9999, 2 );
			add_filter( 'woocommerce_email_header_image', array( $this, 'custom_email_header_image' ), 9999 );
			add_filter( 'woocommerce_email_footer_text', array( $this, 'filter_email_footer_text' ), 9999, 1 );

			add_action( 'woocommerce_email_before_order_table', array( $this, 'render_email_body_intro' ), 10, 4 );
			add_action( 'woocommerce_email_after_order_table', array( $this, 'render_email_promo_text' ), 20, 4 );
		}

		// AJAX handlers para envío de correo de prueba y vista previa en vivo (para administradores siempre)
		add_action( 'wp_ajax_wpat_send_test_email', array( $this, 'ajax_send_test_email' ) );
		add_action( 'wp_ajax_wpat_get_email_preview_html', array( $this, 'ajax_get_email_preview_html' ) );
	}

	/**
	 * Intercepta la carga de plantillas de cabecera y pie de correo de WooCommerce.
	 */
	public function override_email_template( $template, $template_name, $template_path ) {
		if ( 'emails/email-header.php' === $template_name ) {
			$custom_header = WPAT_PATH . 'templates/emails/email-header.php';
			if ( file_exists( $custom_header ) ) {
				return $custom_header;
			}
		}

		if ( 'emails/email-footer.php' === $template_name ) {
			$custom_footer = WPAT_PATH . 'templates/emails/email-footer.php';
			if ( file_exists( $custom_footer ) ) {
				return $custom_footer;
			}
		}

		return $template;
	}

	/**
	 * Personaliza la imagen de cabecera si se ha configurado un logotipo en el módulo.
	 */
	public function custom_email_header_image( $header_image ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! empty( $settings['woo_email_logo_url'] ) ) {
			return esc_url( $settings['woo_email_logo_url'] );
		}
		return $header_image;
	}

	/**
	 * Reemplaza las etiquetas dinámicas ({customer_name}, {order_number}, {order_date}, {order_total}, {site_title}, {tracking_code})
	 *
	 * @param string   $text  Texto con las etiquetas.
	 * @param WC_Order $order Objeto del pedido opcional.
	 * @return string
	 */
	public function replace_email_placeholders( $text, $order = null ) {
		if ( empty( $text ) ) {
			return '';
		}

		$site_title    = get_bloginfo( 'name' );
		$customer_name = 'Juan Pérez';
		$order_number  = '9999';
		$order_date    = date_i18n( get_option( 'date_format' ) );
		$order_total   = '49,00 €';
		$tracking_code = 'ES123456789';

		if ( $order && is_a( $order, 'WC_Order' ) ) {
			$customer_name = $order->get_formatted_billing_full_name();
			if ( empty( trim( $customer_name ) ) ) {
				$customer_name = $order->get_billing_first_name() ? $order->get_billing_first_name() : 'Cliente';
			}
			$order_number  = $order->get_order_number();
			$order_date    = wc_format_datetime( $order->get_date_created() );
			$order_total   = $order->get_formatted_order_total();

			$meta_tracking = $order->get_meta( '_tracking_number' );
			if ( ! empty( $meta_tracking ) ) {
				$tracking_code = $meta_tracking;
			}
		}

		$replacements = array(
			'{site_title}'    => $site_title,
			'{customer_name}' => $customer_name,
			'{order_number}'  => $order_number,
			'{order_date}'    => $order_date,
			'{order_total}'   => $order_total,
			'{tracking_code}' => $tracking_code,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
	}

	/**
	 * Reemplaza etiquetas dinámicas en el texto del pie de página de los correos.
	 */
	public function filter_email_footer_text( $text ) {
		return $this->replace_email_placeholders( $text );
	}

	/**
	 * Muestra el mensaje de introducción al cuerpo del correo antes de la tabla del pedido.
	 */
	public function render_email_body_intro( $order = null, $sent_to_admin = false, $plain_text = false, $email = null ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo_email_body_intro'] ) ) {
			return;
		}

		$intro_text = $this->replace_email_placeholders( $settings['woo_email_body_intro'], $order );

		if ( $plain_text ) {
			echo "\n" . esc_html( wp_strip_all_tags( $intro_text ) ) . "\n\n";
		} else {
			echo '<div style="margin-bottom: 24px; font-size: 14px; line-height: 1.6; color: inherit;">' . wp_kses_post( wpautop( $intro_text ) ) . '</div>';
		}
	}

	/**
	 * Muestra el bloque o banner promocional después de la tabla de detalles del pedido.
	 */
	public function render_email_promo_text( $order = null, $sent_to_admin = false, $plain_text = false, $email = null ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo_email_promo_text'] ) ) {
			return;
		}

		$promo_text    = $this->replace_email_placeholders( $settings['woo_email_promo_text'], $order );
		$primary_color = isset( $settings['woo_email_primary_color'] ) ? $settings['woo_email_primary_color'] : '#2563eb';

		if ( $plain_text ) {
			echo "\n--- PROMO ---\n" . esc_html( wp_strip_all_tags( $promo_text ) ) . "\n\n";
		} else {
			echo '<div style="margin-top: 28px; padding: 16px 20px; background-color: #f1f5f9; border-left: 4px solid ' . esc_attr( $primary_color ) . '; border-radius: 6px; font-size: 13.5px; font-weight: 600; color: #0f172a;">' . wp_kses_post( $promo_text ) . '</div>';
		}
	}

	/**
	 * Inyecta CSS personalizado dinámico para los correos electrónicos de WooCommerce.
	 */
	public function custom_email_styles( $css, $email ) {
		$settings       = WPAT_Main::get_instance()->get_settings();
		$template_style = isset( $settings['woo_email_template_style'] ) ? $settings['woo_email_template_style'] : 'modern';
		$primary_color  = isset( $settings['woo_email_primary_color'] ) ? $settings['woo_email_primary_color'] : '#2563eb';
		$body_bg        = isset( $settings['woo_email_body_bg'] ) ? $settings['woo_email_body_bg'] : '#f8fafc';
		$card_bg        = isset( $settings['woo_email_card_bg'] ) ? $settings['woo_email_card_bg'] : '#ffffff';
		$text_color     = isset( $settings['woo_email_text_color'] ) ? $settings['woo_email_text_color'] : '#1e293b';
		$logo_width     = isset( $settings['woo_email_logo_width'] ) ? absint( $settings['woo_email_logo_width'] ) : 150;

		$custom_css = "
			/* Reset e Inyección General de Estilos WPAT Email Designer */
			#body_content, body, #wrapper {
				background-color: {$body_bg} !important;
				font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
				color: {$text_color} !important;
			}
			#template_container {
				background-color: {$card_bg} !important;
				border-radius: " . ( 'modern' === $template_style ? '12px' : '0px' ) . " !important;
				box-shadow: " . ( 'modern' === $template_style ? '0 4px 15px rgba(0,0,0,0.06)' : 'none' ) . " !important;
				border: " . ( 'minimalist' === $template_style ? '1px solid #e2e8f0' : 'none' ) . " !important;
				overflow: hidden !important;
			}
			#template_header {
				background-color: " . ( 'classic' === $template_style ? $primary_color : $card_bg ) . " !important;
				border-bottom: " . ( 'minimalist' === $template_style ? '1px solid #e2e8f0' : 'none' ) . " !important;
				padding: 28px 32px !important;
			}
			#template_header h1 {
				color: " . ( 'classic' === $template_style ? '#ffffff' : $primary_color ) . " !important;
				font-size: 22px !important;
				font-weight: 700 !important;
				margin: 0 !important;
			}
			#template_header #header_wrapper img {
				max-width: {$logo_width}px !important;
				height: auto !important;
			}
			#body_content_inner {
				color: {$text_color} !important;
				font-size: 14px !important;
				line-height: 1.6 !important;
				padding: 32px !important;
			}
			#body_content_inner h2, #body_content_inner h3 {
				color: {$primary_color} !important;
			}
			.wpat-email-btn, a.wpat-email-btn, a.button {
				background-color: {$primary_color} !important;
				color: #ffffff !important;
				padding: 10px 20px !important;
				border-radius: 6px !important;
				text-decoration: none !important;
				font-weight: 600 !important;
				display: inline-block !important;
			}
			table.td {
				border-color: #e2e8f0 !important;
			}
			th.td {
				color: {$text_color} !important;
				border-color: #e2e8f0 !important;
			}
			td.td {
				color: {$text_color} !important;
				border-color: #e2e8f0 !important;
			}
			#template_footer {
				background-color: " . ( 'classic' === $template_style ? '#0f172a' : $body_bg ) . " !important;
				color: " . ( 'classic' === $template_style ? '#94a3b8' : '#64748b' ) . " !important;
				padding: 24px 32px !important;
				text-align: center !important;
				font-size: 12px !important;
			}
			#template_footer a {
				color: " . ( 'classic' === $template_style ? '#38bdf8' : $primary_color ) . " !important;
				text-decoration: none !important;
			}
		";

		return $css . $custom_css;
	}

	/**
	 * Genera el cuerpo del mensaje de correo de demostración.
	 *
	 * @return string HTML del cuerpo.
	 */
	private function build_demo_email_body() {
		$settings      = WPAT_Main::get_instance()->get_settings();
		$user          = wp_get_current_user();
		$welcome_msg   = ! empty( $settings['woo_email_welcome_msg'] ) ? $this->replace_email_placeholders( $settings['woo_email_welcome_msg'] ) : '';
		$body_intro    = ! empty( $settings['woo_email_body_intro'] ) ? $this->replace_email_placeholders( $settings['woo_email_body_intro'] ) : '';
		$promo_text    = ! empty( $settings['woo_email_promo_text'] ) ? $this->replace_email_placeholders( $settings['woo_email_promo_text'] ) : '';
		$primary_color = isset( $settings['woo_email_primary_color'] ) ? $settings['woo_email_primary_color'] : '#2563eb';

		$content = '';

		if ( $welcome_msg ) {
			$content .= '<div style="background: #f1f5f9; border-left: 4px solid ' . esc_attr( $primary_color ) . '; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 700; color: #0f172a;">' . wp_kses_post( $welcome_msg ) . '</div>';
		}

		if ( $body_intro ) {
			$content .= '<div style="margin-bottom: 20px; font-size: 14px; line-height: 1.6;">' . wp_kses_post( wpautop( $body_intro ) ) . '</div>';
		} else {
			$content .= '<p>¡Hola <strong>' . esc_html( $user->display_name ) . '</strong>!</p><p>Este es un correo de prueba generado dinámicamente por el módulo <strong>Diseñador de Plantillas de Email</strong> de WP Agency Toolkit.</p>';
		}

		$content .= '
			<div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0;">
				<h3 style="margin-top:0; color:#0f172a; font-size: 15px;">Detalles del Pedido de Demostración #9999</h3>
				<table style="width:100%; border-collapse:collapse; font-size: 13px;">
					<thead>
						<tr style="border-bottom:2px solid #cbd5e1; text-align:left;">
							<th style="padding:8px 0; color:#475569;">Producto</th>
							<th style="padding:8px 0; color:#475569; text-align:center;">Cant.</th>
							<th style="padding:8px 0; color:#475569; text-align:right;">Precio</th>
						</tr>
					</thead>
					<tbody>
						<tr style="border-bottom:1px solid #e2e8f0;">
							<td style="padding:10px 0; font-weight: 600;">Licencia Anual WP Agency Toolkit Pro</td>
							<td style="padding:10px 0; text-align:center;">1</td>
							<td style="padding:10px 0; text-align:right;">49,00 €</td>
						</tr>
					</tbody>
				</table>
			</div>
		';

		if ( $promo_text ) {
			$content .= '<div style="margin-top: 24px; padding: 14px 18px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; color: #1e40af; font-weight: 600; font-size: 13.5px;">' . wp_kses_post( $promo_text ) . '</div>';
		}

		$content .= '
			<p style="text-align:center; margin-top:28px;">
				<a href="' . esc_url( admin_url( 'admin.php?page=wp-agency-toolkit' ) ) . '" class="wpat-email-btn" style="background:' . esc_attr( $primary_color ) . '; color:#ffffff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block;">Ver Ajustes en el Panel &rarr;</a>
			</p>
		';

		return $content;
	}

	/**
	 * Envía un correo electrónico transaccional de prueba a la dirección especificada o al administrador.
	 */
	public function ajax_send_test_email() {
		if ( ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce inválido).' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$recipient_input = isset( $_POST['recipient'] ) ? sanitize_email( $_POST['recipient'] ) : '';
		$user            = wp_get_current_user();
		$to              = ! empty( $recipient_input ) && is_email( $recipient_input ) ? $recipient_input : $user->user_email;

		if ( empty( $to ) || ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => 'Por favor, introduce una dirección de correo válida para el envío.' ) );
		}

		$settings       = WPAT_Main::get_instance()->get_settings();
		$template_style = isset( $settings['woo_email_template_style'] ) ? ucfirst( $settings['woo_email_template_style'] ) : 'Modern';
		$subject        = '📧 Correo de Prueba - WP Agency Toolkit (' . $template_style . ')';

		if ( function_exists( 'WC' ) ) {
			$mailer  = WC()->mailer();
			$content = $this->build_demo_email_body();

			$wrapped_email = $mailer->wrap_message( $subject, $content );
			$sent          = $mailer->send( $to, $subject, $wrapped_email );

			if ( $sent ) {
				wp_send_json_success( array( 'message' => '¡Correo de prueba enviado con éxito a ' . $to . '!' ) );
			} else {
				wp_send_json_error( array( 'message' => 'Error al enviar el correo vía WooCommerce Mailer. Verifica tu configuración SMTP de WordPress.' ) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'WooCommerce no está activo.' ) );
		}
	}

	/**
	 * Devuelve la estructura HTML completa del correo para la Vista Previa en Vivo.
	 */
	public function ajax_get_email_preview_html() {
		if ( ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce inválido).' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		if ( function_exists( 'WC' ) ) {
			$mailer        = WC()->mailer();
			$subject       = 'Vista Previa en Vivo - WooCommerce Email';
			$content       = $this->build_demo_email_body();
			$wrapped_email = $mailer->wrap_message( $subject, $content );

			wp_send_json_success( array( 'html' => $wrapped_email ) );
		} else {
			wp_send_json_error( array( 'message' => 'WooCommerce no está activo.' ) );
		}
	}
}

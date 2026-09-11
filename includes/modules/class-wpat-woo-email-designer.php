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
		}

		// AJAX handler para el correo de prueba (disponible para administradores siempre)
		add_action( 'wp_ajax_wpat_send_test_email', array( $this, 'ajax_send_test_email' ) );
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
	 * Inyecta CSS personalizado dinámico para los correos electrónicos de WooCommerce.
	 */
	public function custom_email_styles( $css, $email ) {
		$settings     = WPAT_Main::get_instance()->get_settings();
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
	 * Envía un correo electrónico transaccional de prueba al administrador actual.
	 */
	public function ajax_send_test_email() {
		if ( ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce inválido).' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$user = wp_get_current_user();
		$to   = $user->user_email;

		if ( empty( $to ) || ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => 'No se encontró un correo válido para el envío.' ) );
		}

		$settings       = WPAT_Main::get_instance()->get_settings();
		$template_style = isset( $settings['woo_email_template_style'] ) ? ucfirst( $settings['woo_email_template_style'] ) : 'Modern';
		$subject        = '📧 Correo de Prueba - WP Agency Toolkit (' . $template_style . ')';

		// Cargar el emulador de correos de WooCommerce
		if ( function_exists( 'WC' ) ) {
			$mailer = WC()->mailer();

			$content = '
				<p>¡Hola <strong>' . esc_html( $user->display_name ) . '</strong>!</p>
				<p>Este es un correo de prueba generado dinámicamente por el módulo <strong>Diseñador de Plantillas de Email</strong> de WP Agency Toolkit.</p>
				<div style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2563eb;">
					<h3 style="margin-top:0; color:#0f172a;">Detalles del Pedido de Demostración #9999</h3>
					<table style="width:100%; border-collapse:collapse;">
						<thead>
							<tr style="border-bottom:1px solid #cbd5e1; text-align:left;">
								<th style="padding:8px 0;">Producto</th>
								<th style="padding:8px 0;">Cantidad</th>
								<th style="padding:8px 0;">Precio</th>
							</tr>
						</thead>
						<tbody>
							<tr style="border-bottom:1px solid #e2e8f0;">
								<td style="padding:8px 0;">Licencia Anual WP Agency Toolkit Pro</td>
								<td style="padding:8px 0;">1</td>
								<td style="padding:8px 0;">49,00 €</td>
							</tr>
						</tbody>
					</table>
				</div>
				<p style="text-align:center; margin-top:25px;">
					<a href="' . esc_url( admin_url( 'admin.php?page=wp-agency-toolkit' ) ) . '" class="wpat-email-btn" style="background:#2563eb; color:#fff; padding:12px 24px; border-radius:6px; text-decoration:none; font-weight:bold; display:inline-block;">Ver Ajustes en el Panel &rarr;</a>
				</p>
			';

			$wrapped_email = $mailer->wrap_message( $subject, $content );
			$sent = $mailer->send( $to, $subject, $wrapped_email );

			if ( $sent ) {
				wp_send_json_success( array( 'message' => '¡Correo de prueba enviado con éxito a ' . $to . '!' ) );
			} else {
				wp_send_json_error( array( 'message' => 'Error al enviar el correo vía WooCommerce Mailer. Verifica tu configuración SMTP.' ) );
			}
		} else {
			wp_send_json_error( array( 'message' => 'WooCommerce no está activo.' ) );
		}
	}
}

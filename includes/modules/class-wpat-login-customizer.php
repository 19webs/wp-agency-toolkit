<?php
/**
 * Módulo: Personalizador de Login - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Login_Customizer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Login_Customizer
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Login_Customizer
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
		add_action( 'login_enqueue_scripts', array( $this, 'custom_login_styles' ) );
		add_filter( 'login_headerurl', array( $this, 'custom_login_logo_url' ) );
		add_filter( 'login_headertext', array( $this, 'custom_login_logo_title' ) );
		
		if ( is_admin() ) {
			add_filter( 'admin_footer_text', array( $this, 'custom_admin_footer_text' ) );
			add_filter( 'update_footer', array( $this, 'custom_admin_footer_version' ), 99 );
		}
	}

	/**
	 * Inyecta los estilos personalizados en la cabecera de la página de Login.
	 */
	public function custom_login_styles() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$style    = isset( $settings['login_style'] ) ? $settings['login_style'] : 'default';
		$logo     = $settings['login_logo'];
		$bg_color = ! empty( $settings['login_bg_color'] ) ? $settings['login_bg_color'] : '#f0f0f0';
		$hide_langs = isset( $settings['login_hide_languages'] ) ? $settings['login_hide_languages'] : '0';

		echo '<style type="text/css">';
		
		// Ocultar selector de idiomas si está activo
		if ( '1' === $hide_langs ) {
			echo '.language-switcher { display: none !important; }';
		}

		if ( 'modern' === $style ) {
			$bg_type      = isset( $settings['login_bg_type'] ) ? $settings['login_bg_type'] : 'image';
			$bg_image     = ! empty( $settings['login_bg_image'] ) ? $settings['login_bg_image'] : WPAT_URL . 'assets/images/wpat-login-bg.jpg';
			$accent_color = ! empty( $settings['login_accent_color'] ) ? $settings['login_accent_color'] : '#2563eb';
			$footer_text  = isset( $settings['login_footer_text'] ) ? $settings['login_footer_text'] : '';
			
			// Calcular RGBA para el foco
			$hex = str_replace( '#', '', $accent_color );
			if ( strlen( $hex ) == 3 ) {
				$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
				$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
				$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
			} else {
				$r = hexdec( substr( $hex, 0, 2 ) );
				$g = hexdec( substr( $hex, 2, 2 ) );
				$b = hexdec( substr( $hex, 4, 2 ) );
			}
			$accent_rgba = "rgba({$r}, {$g}, {$b}, 0.25)";
			?>
			body.login {
				<?php if ( 'image' === $bg_type ) : ?>
				background-image: url('<?php echo esc_url( $bg_image ); ?>') !important;
				background-size: cover !important;
				background-position: center !important;
				background-repeat: no-repeat !important;
				<?php endif; ?>
				background-color: <?php echo esc_attr( $bg_color ); ?> !important;
				display: flex !important;
				flex-direction: column !important;
				align-items: center !important;
				justify-content: center !important;
				min-height: 100vh !important;
				margin: 0 !important;
				padding: 0 !important;
			}
			
			/* Contenedor principal alineado y centrado como tarjeta blanca */
			.login #login {
				background: #ffffff !important;
				border-radius: 30px !important;
				box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12) !important;
				padding: 45px 35px 35px 35px !important;
				margin: auto !important;
				width: 100% !important;
				max-width: 440px !important;
				box-sizing: border-box !important;
				position: relative !important;
			}
			
			/* Estilo del logo dentro de la tarjeta */
			.login h1 a {
				background-image: url('<?php echo esc_url( $logo ); ?>') !important;
				background-size: contain !important;
				background-position: center !important;
				background-repeat: no-repeat !important;
				width: 100% !important;
				height: 70px !important;
				margin: 0 auto 20px auto !important;
				display: block !important;
				text-indent: -9999px !important;
			}
			<?php if ( empty( $logo ) ) : ?>
			.login h1 {
				display: none !important;
			}
			<?php endif; ?>

			/* Quitar fondos de WordPress en el formulario */
			#loginform {
				background: none !important;
				padding: 0 !important;
				box-shadow: none !important;
				border: none !important;
				box-sizing: border-box !important;
			}
			
			#loginform::before {
				content: "Bienvenido" !important;
				display: block !important;
				font-size: 24px !important;
				font-weight: 700 !important;
				color: #1f2937 !important;
				text-align: center !important;
				margin-bottom: 25px !important;
				font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif !important;
			}
			
			.login label {
				color: #4b5563 !important;
				font-size: 13px !important;
				font-weight: 600 !important;
				display: block !important;
				margin-bottom: 6px !important;
				font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif !important;
			}
			
			.login input[type="text"],
			.login input[type="password"] {
				background: #f3f4f6 !important;
				border: 1px solid #e5e7eb !important;
				border-radius: 8px !important;
				color: #1f2937 !important;
				font-size: 15px !important;
				padding: 12px 14px !important;
				width: 100% !important;
				box-sizing: border-box !important;
				box-shadow: none !important;
				transition: all 0.2s ease !important;
				font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif !important;
			}
			
			.login input[type="text"]:focus,
			.login input[type="password"]:focus {
				border-color: <?php echo esc_attr( $accent_color ); ?> !important;
				background: #ffffff !important;
				box-shadow: 0 0 0 3px <?php echo $accent_rgba; ?> !important;
				outline: none !important;
			}
			
			.login .forgetmenot {
				float: none !important;
				margin-bottom: 20px !important;
				margin-top: 10px !important;
			}
			
			.login .forgetmenot label {
				font-weight: normal !important;
				color: #6b7280 !important;
				font-size: 13px !important;
				display: inline-flex !important;
				align-items: center !important;
				cursor: pointer !important;
			}
			
			.login .forgetmenot input[type="checkbox"] {
				margin-right: 8px !important;
				border-radius: 4px !important;
				border: 1px solid #d1d5db !important;
				margin-top: 0 !important;
			}
			
			.login .forgetmenot input[type="checkbox"]:checked {
				background-color: <?php echo esc_attr( $accent_color ); ?> !important;
				border-color: <?php echo esc_attr( $accent_color ); ?> !important;
			}
			
			.login .button-primary {
				background: <?php echo esc_attr( $accent_color ); ?> !important;
				border: none !important;
				border-radius: 8px !important;
				color: #ffffff !important;
				font-size: 14px !important;
				font-weight: 600 !important;
				height: 46px !important;
				line-height: 46px !important;
				padding: 0 20px !important;
				width: 100% !important;
				float: none !important;
				box-shadow: 0 4px 12px <?php echo $accent_rgba; ?> !important;
				text-shadow: none !important;
				cursor: pointer !important;
				transition: all 0.2s ease !important;
				font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif !important;
			}
			
			.login .button-primary:hover {
				background: <?php echo esc_attr( $accent_color ); ?> !important;
				box-shadow: 0 6px 16px <?php echo $accent_rgba; ?> !important;
				filter: brightness(1.08) !important;
			}
			
			.login .button-primary:focus {
				box-shadow: 0 0 0 3px <?php echo $accent_rgba; ?> !important;
			}
			
			/* Enlaces de pie de login alineados dentro de la tarjeta */
			#nav, #backtoblog {
				text-align: center !important;
				margin: 15px 0 0 0 !important;
				padding: 0 !important;
				float: none !important;
			}
			#nav a, #backtoblog a {
				color: #6b7280 !important;
				font-size: 13px !important;
				transition: color 0.2s !important;
				font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif !important;
				text-decoration: none !important;
			}
			#nav a:hover, #backtoblog a:hover {
				color: <?php echo esc_attr( $accent_color ); ?> !important;
			}
			
			/* Texto de créditos opcional */
			<?php if ( ! empty( $footer_text ) ) : ?>
			.login #login::after {
				content: "<?php echo esc_js( $footer_text ); ?>" !important;
				display: block !important;
				text-align: center !important;
				font-size: 12px !important;
				color: #94a3b8 !important;
				margin-top: 25px !important;
				font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif !important;
			}
			<?php endif; ?>
			
			#login_error, .login .message, .login .success {
				border-left: 4px solid #ef4444 !important;
				background: #fef2f2 !important;
				color: #991b1b !important;
				border-radius: 8px !important;
				box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
				padding: 12px 16px !important;
				margin-bottom: 20px !important;
				font-size: 13px !important;
			}
			
			.login .message {
				border-left-color: #3b82f6 !important;
				background: #eff6ff !important;
				color: #1e40af !important;
			}

			/* Ajustes específicos para el modal de sesión caducada (interim-login) */
			body.login.interim-login {
				background: #ffffff !important;
				background-image: none !important;
				min-height: 0 !important;
				height: auto !important;
				display: block !important;
				padding: 0 !important;
			}
			
			.login.interim-login #login {
				padding: 15px 20px !important;
				margin: 0 auto !important;
				max-width: 100% !important;
				box-shadow: none !important;
				border-radius: 0 !important;
				background: transparent !important;
			}
			
			.login.interim-login h1 {
				display: none !important;
			}
			
			.login.interim-login #loginform::before {
				font-size: 18px !important;
				margin-bottom: 12px !important;
			}
			
			.login.interim-login #loginform {
				margin: 0 !important;
			}
			
			.login.interim-login input[type="text"],
			.login.interim-login input[type="password"] {
				padding: 8px 12px !important;
				font-size: 14px !important;
			}
			
			.login.interim-login .forgetmenot {
				margin-bottom: 10px !important;
				margin-top: 5px !important;
			}
			
			.login.interim-login .button-primary {
				height: 38px !important;
				line-height: 38px !important;
			}
			
			.login.interim-login #nav,
			.login.interim-login #backtoblog {
				display: none !important;
			}
			
			.login.interim-login #login_error,
			.login.interim-login .message,
			.login.interim-login .success {
				padding: 8px 12px !important;
				margin-bottom: 12px !important;
				font-size: 12.5px !important;
			}
			<?php
		} else {
			// Estilo Clásico: Color de fondo
			if ( ! empty( $bg_color ) ) {
				echo 'body.login { background-color: ' . esc_attr( $bg_color ) . ' !important; }';
			}

			// Estilo Clásico: Logotipo personalizado
			if ( ! empty( $logo ) ) {
				echo '
				body.login h1 a {
					background-image: url(' . esc_url( $logo ) . ') !important;
					background-size: contain !important;
					background-position: center bottom !important;
					width: 100% !important;
					height: 100px !important;
					margin-bottom: 25px !important;
				}';
			}
		}
		
		echo '</style>';
	}

	/**
	 * Cambia el enlace del logo del login para que redirija a la Home del sitio.
	 *
	 * @return string
	 */
	public function custom_login_logo_url() {
		return home_url();
	}

	/**
	 * Cambia el atributo de texto descriptivo del logo para que muestre el nombre del sitio.
	 *
	 * @return string
	 */
	public function custom_login_logo_title() {
		return get_bloginfo( 'name' );
	}

	/**
	 * Personaliza el texto de pie de página de la izquierda en la administración.
	 *
	 * @param string $default_text
	 * @return string
	 */
	public function custom_admin_footer_text( $default_text ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		$footer_text = isset( $settings['admin_footer_text'] ) ? $settings['admin_footer_text'] : '';

		if ( ! empty( $footer_text ) ) {
			return wp_kses_post( $footer_text );
		}

		return $default_text;
	}

	/**
	 * Personaliza el texto de la derecha (versión de WordPress) en la administración.
	 *
	 * @param string $default_version
	 * @return string
	 */
	public function custom_admin_footer_version( $default_version ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		$footer_text = isset( $settings['admin_footer_text'] ) ? $settings['admin_footer_text'] : '';

		// Si se ha definido un texto de pie de página personalizado, ocultamos la versión nativa de WP
		if ( ! empty( $footer_text ) ) {
			return '';
		}

		return $default_version;
	}
}

<?php
/**
 * Módulo: Integraciones de Terceros - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Integrations {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Integrations
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Integrations
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
		// Inyección de códigos en la cabecera de la web pública (prioridad alta 1)
		add_action( 'wp_head', array( $this, 'inject_integration_codes' ), 1 );
	}

	/**
	 * Inyecta las etiquetas de Search Console y los scripts de Google Analytics en el frontend.
	 */
	public function inject_integration_codes() {
		// Evitar inyección en paneles de administración
		if ( is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// 1. Google Search Console Verification Meta Tag
		if ( ! empty( $settings['google_search_console_code'] ) ) {
			$code = $settings['google_search_console_code'];
			
			// Si el usuario pegó la etiqueta HTML completa, extraer solo el valor del atributo content
			if ( preg_match( '/content=["\']([^"\']+)["\']/i', $code, $matches ) ) {
				$code = $matches[1];
			}
			
			echo '<!-- Google Search Console Verification - WP Agency Toolkit -->' . "\n";
			echo '<meta name="google-site-verification" content="' . esc_attr( trim( $code ) ) . '" />' . "\n";
		}

		// 2. Google Analytics (GA4) Tracking Script
		if ( ! empty( $settings['google_analytics_id'] ) ) {
			$ga_id = trim( sanitize_text_field( $settings['google_analytics_id'] ) );
			
			// Validar formato estándar de GA4 (comienza con G- seguido de caracteres alfanuméricos)
			if ( preg_match( '/^G-[A-Z0-9]+$/i', $ga_id ) ) {
				?>
				<!-- Google Analytics (gtag.js) - WP Agency Toolkit -->
				<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga_id ); ?>"></script>
				<script>
					window.dataLayer = window.dataLayer || [];
					function gtag(){dataLayer.push(arguments);}
					gtag('js', new Date());
					gtag('config', '<?php echo esc_attr( $ga_id ); ?>');
				</script>
				<?php
			}
		}
	}
}

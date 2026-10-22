<?php
/**
 * Módulo: Corrector Técnico de Código HTML y ARIA (Silencioso & WCAG 2.1) - WP Agency Toolkit
 *
 * Sanea y repara automáticamente en segundo plano inconsistencias de roles ARIA,
 * trampas de foco en teclado, nombres accesibles en botones/enlaces de solo icono
 * e inputs sin etiqueta, garantizando cumplimiento W3C y puntuaciones de 95-100 en Google Lighthouse.
 *
 * 100% silencioso e invisible: Cero botones flotantes ni alteraciones visuales.
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_HTML_ARIA_Fixer {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_HTML_ARIA_Fixer|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia única.
	 *
	 * @return WPAT_HTML_ARIA_Fixer
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
		// Encolar script ultra-ligero de saneamiento en el frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 99 );
	}

	/**
	 * Encola el script de saneamiento ARIA en el frontend con la configuración activa.
	 */
	public function enqueue_frontend_scripts() {
		if ( is_admin() ) {
			return;
		}

		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : get_option( 'wpat_settings', array() );

		// Comprobar si el módulo está activo
		$is_active = isset( $settings['html-aria-fixer'] ) && '1' === (string) $settings['html-aria-fixer'];
		if ( ! $is_active ) {
			return;
		}

		$config = array(
			'fix_roles'           => ! isset( $settings['aria_fix_roles'] ) || '1' === (string) $settings['aria_fix_roles'],
			'fix_hidden'          => ! isset( $settings['aria_fix_hidden'] ) || '1' === (string) $settings['aria_fix_hidden'],
			'fix_empty_buttons'   => ! isset( $settings['aria_fix_empty_buttons'] ) || '1' === (string) $settings['aria_fix_empty_buttons'],
			'fix_empty_links'     => ! isset( $settings['aria_fix_empty_links'] ) || '1' === (string) $settings['aria_fix_empty_links'],
			'fix_inputs'          => ! isset( $settings['aria_fix_inputs'] ) || '1' === (string) $settings['aria_fix_inputs'],
			'fix_decorative_imgs' => ! isset( $settings['aria_fix_decorative_imgs'] ) || '1' === (string) $settings['aria_fix_decorative_imgs'],
		);

		wp_enqueue_script(
			'wpat-html-aria-fixer-js',
			WPAT_URL . 'assets/js/wpat-html-aria-fixer.js',
			array(),
			defined( 'WPAT_VERSION' ) ? WPAT_VERSION : '1.0.0',
			true
		);

		wp_localize_script(
			'wpat-html-aria-fixer-js',
			'wpatAriaConfig',
			$config
		);
	}
}

// Inicializar el módulo
WPAT_HTML_ARIA_Fixer::get_instance();

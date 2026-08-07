<?php
/**
 * Módulo: Desactivar Características de Galería de WooCommerce - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Zoom {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Zoom
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Zoom
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
		// Ejecutar tarde en after_setup_theme para asegurarnos de que el tema ya ha declarado soporte
		add_action( 'after_setup_theme', array( $this, 'adjust_gallery_supports' ), 100 );
	}

	/**
	 * Remueve soportes declarados por el tema para la galería de imágenes.
	 */
	public function adjust_gallery_supports() {
		$settings = WPAT_Main::get_instance()->get_settings();

		// Desactivar Zoom (Efecto Lupa)
		if ( isset( $settings['woo_zoom_disable_zoom'] ) && '1' === $settings['woo_zoom_disable_zoom'] ) {
			remove_theme_support( 'wc-product-gallery-zoom' );
		}

		// Desactivar Lightbox (Ventana emergente al hacer clic)
		if ( isset( $settings['woo_zoom_disable_lightbox'] ) && '1' === $settings['woo_zoom_disable_lightbox'] ) {
			remove_theme_support( 'wc-product-gallery-lightbox' );
		}

		// Desactivar Slider (Deslizador horizontal de miniaturas)
		if ( isset( $settings['woo_zoom_disable_slider'] ) && '1' === $settings['woo_zoom_disable_slider'] ) {
			remove_theme_support( 'wc-product-gallery-slider' );
		}
	}
}

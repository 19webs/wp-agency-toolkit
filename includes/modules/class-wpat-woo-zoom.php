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
		// Ejecutar tarde en after_setup_theme y wp_loaded para abarcar temas que declaran soporte en distintos hooks
		add_action( 'after_setup_theme', array( $this, 'adjust_gallery_supports' ), 100 );
		add_action( 'wp_loaded', array( $this, 'adjust_gallery_supports' ), 100 );

		// Filtros nativos de WooCommerce (prioridad alta 999 para temas modernos)
		add_filter( 'woocommerce_single_product_zoom_enabled', array( $this, 'filter_zoom_support' ), 999 );
		add_filter( 'woocommerce_single_product_photoswipe_enabled', array( $this, 'filter_lightbox_support' ), 999 );
		add_filter( 'woocommerce_single_product_flexslider_enabled', array( $this, 'filter_slider_support' ), 999 );

		// Des-encolar scripts y estilos para mejorar PageSpeed
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_gallery_assets' ), 99 );
	}

	/**
	 * Remueve soportes declarados por el tema para la galería de imágenes.
	 */
	public function adjust_gallery_supports() {
		$settings = WPAT_Main::get_instance()->get_settings();

		// Desactivar Zoom (Efecto Lupa)
		if ( ! empty( $settings['woo_zoom_disable_zoom'] ) && '1' === $settings['woo_zoom_disable_zoom'] ) {
			remove_theme_support( 'wc-product-gallery-zoom' );
		}

		// Desactivar Lightbox (Ventana emergente al hacer clic)
		if ( ! empty( $settings['woo_zoom_disable_lightbox'] ) && '1' === $settings['woo_zoom_disable_lightbox'] ) {
			remove_theme_support( 'wc-product-gallery-lightbox' );
		}

		// Desactivar Slider (Deslizador horizontal de miniaturas)
		if ( ! empty( $settings['woo_zoom_disable_slider'] ) && '1' === $settings['woo_zoom_disable_slider'] ) {
			remove_theme_support( 'wc-product-gallery-slider' );
		}
	}

	/**
	 * Filtra si el zoom de galería está habilitado.
	 *
	 * @param bool $enabled Estado actual.
	 * @return bool
	 */
	public function filter_zoom_support( $enabled ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! empty( $settings['woo_zoom_disable_zoom'] ) && '1' === $settings['woo_zoom_disable_zoom'] ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Filtra si el lightbox (PhotoSwipe) de galería está habilitado.
	 *
	 * @param bool $enabled Estado actual.
	 * @return bool
	 */
	public function filter_lightbox_support( $enabled ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! empty( $settings['woo_zoom_disable_lightbox'] ) && '1' === $settings['woo_zoom_disable_lightbox'] ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Filtra si el slider (FlexSlider) de galería está habilitado.
	 *
	 * @param bool $enabled Estado actual.
	 * @return bool
	 */
	public function filter_slider_support( $enabled ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! empty( $settings['woo_zoom_disable_slider'] ) && '1' === $settings['woo_zoom_disable_slider'] ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Des-encola librerías y CSS pesados no requeridos en la página de producto para optimizar WPO.
	 */
	public function dequeue_gallery_assets() {
		if ( ! is_product() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$custom_css = '';

		// Si el zoom está desactivado
		if ( ! empty( $settings['woo_zoom_disable_zoom'] ) && '1' === $settings['woo_zoom_disable_zoom'] ) {
			wp_dequeue_script( 'zoom' );
			$custom_css .= ' .woocommerce-product-gallery .zoomImg { display: none !important; }';
		}

		// Si el lightbox está desactivado
		if ( ! empty( $settings['woo_zoom_disable_lightbox'] ) && '1' === $settings['woo_zoom_disable_lightbox'] ) {
			wp_dequeue_script( 'photoswipe' );
			wp_dequeue_script( 'photoswipe-ui-default' );
			wp_dequeue_style( 'photoswipe' );
			wp_dequeue_style( 'photoswipe-default-skin' );
			$custom_css .= ' .woocommerce-product-gallery__trigger { display: none !important; }';
		}

		// Si el slider está desactivado
		if ( ! empty( $settings['woo_zoom_disable_slider'] ) && '1' === $settings['woo_zoom_disable_slider'] ) {
			wp_dequeue_script( 'flexslider' );
			wp_dequeue_style( 'flexslider' );
		}

		if ( ! empty( $custom_css ) ) {
			wp_register_style( 'wpat-gallery-fixes', false );
			wp_enqueue_style( 'wpat-gallery-fixes' );
			wp_add_inline_style( 'wpat-gallery-fixes', $custom_css );
		}
	}
}

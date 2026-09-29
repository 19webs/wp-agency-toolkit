<?php
/**
 * Módulo: Desactivar Características de Galería de WooCommerce - WP Agency Toolkit
 *
 * @package WP_Agency_Toolkit
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
	 * Comprueba si el módulo está activo.
	 *
	 * @return bool
	 */
	public function is_active() {
		$settings = WPAT_Main::get_instance()->get_settings();
		return ( isset( $settings['woo-zoom'] ) && '1' === (string) $settings['woo-zoom'] );
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

		// Des-encolar scripts pesados y añadir CSS de anulación de forma segura
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_gallery_assets' ), 99 );
		add_action( 'wp_head', array( $this, 'output_gallery_css' ), 100 );
	}

	/**
	 * Remueve soportes declarados por el tema para la galería de imágenes.
	 */
	public function adjust_gallery_supports() {
		if ( ! $this->is_active() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Desactivar Zoom (Efecto Lupa)
		if ( ! empty( $settings['woo_zoom_disable_zoom'] ) && '1' === (string) $settings['woo_zoom_disable_zoom'] ) {
			remove_theme_support( 'wc-product-gallery-zoom' );
		}

		// Desactivar Lightbox (Ventana emergente al hacer clic)
		if ( ! empty( $settings['woo_zoom_disable_lightbox'] ) && '1' === (string) $settings['woo_zoom_disable_lightbox'] ) {
			remove_theme_support( 'wc-product-gallery-lightbox' );
		}

		// Desactivar Slider (Deslizador horizontal de miniaturas)
		if ( ! empty( $settings['woo_zoom_disable_slider'] ) && '1' === (string) $settings['woo_zoom_disable_slider'] ) {
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
		if ( ! $this->is_active() ) {
			return $enabled;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! empty( $settings['woo_zoom_disable_zoom'] ) && '1' === (string) $settings['woo_zoom_disable_zoom'] ) {
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
		if ( ! $this->is_active() ) {
			return $enabled;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! empty( $settings['woo_zoom_disable_lightbox'] ) && '1' === (string) $settings['woo_zoom_disable_lightbox'] ) {
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
		if ( ! $this->is_active() ) {
			return $enabled;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! empty( $settings['woo_zoom_disable_slider'] ) && '1' === (string) $settings['woo_zoom_disable_slider'] ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * Des-encola librerías JS no requeridas en la página de producto.
	 */
	public function dequeue_gallery_assets() {
		if ( ! $this->is_active() ) {
			return;
		}

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Si el zoom está desactivado
		if ( ! empty( $settings['woo_zoom_disable_zoom'] ) && '1' === (string) $settings['woo_zoom_disable_zoom'] ) {
			wp_dequeue_script( 'zoom' );
		}

		// Si el lightbox está desactivado
		if ( ! empty( $settings['woo_zoom_disable_lightbox'] ) && '1' === (string) $settings['woo_zoom_disable_lightbox'] ) {
			wp_dequeue_script( 'photoswipe' );
			wp_dequeue_script( 'photoswipe-ui-default' );
			wp_dequeue_style( 'photoswipe' );
			wp_dequeue_style( 'photoswipe-default-skin' );
		}

		// Si el slider está desactivado
		if ( ! empty( $settings['woo_zoom_disable_slider'] ) && '1' === (string) $settings['woo_zoom_disable_slider'] ) {
			wp_dequeue_script( 'flexslider' );
			wp_dequeue_style( 'flexslider' );
		}
	}

	/**
	 * Imprime reglas CSS inline seguras en el head para ocultar elementos residuales.
	 */
	public function output_gallery_css() {
		if ( ! $this->is_active() ) {
			return;
		}

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$custom_css = '';

		if ( ! empty( $settings['woo_zoom_disable_zoom'] ) && '1' === (string) $settings['woo_zoom_disable_zoom'] ) {
			$custom_css .= ' .woocommerce-product-gallery .zoomImg { display: none !important; }';
		}

		if ( ! empty( $settings['woo_zoom_disable_lightbox'] ) && '1' === (string) $settings['woo_zoom_disable_lightbox'] ) {
			$custom_css .= ' .woocommerce-product-gallery__trigger { display: none !important; }';
		}

		if ( ! empty( $custom_css ) ) {
			echo '<style id="wpat-gallery-overrides">' . $custom_css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

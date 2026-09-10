<?php
/**
 * Módulo: Diseñador de Checkout High-Conversion - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Checkout_Designer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Checkout_Designer
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Checkout_Designer
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		// Interceptar la plantilla global de la página de checkout con prioridad máxima (9999)
		add_filter( 'template_include', array( $this, 'override_checkout_page_template' ), 9999 );

		// Interceptar también la función de plantillas de WooCommerce por compatibilidad adicional
		add_filter( 'woocommerce_locate_template', array( $this, 'override_checkout_template' ), 9999, 3 );

		// Interceptar el contenido para convertir bloques WooCommerce Checkout a shortcode si aplica
		add_filter( 'the_content', array( $this, 'filter_checkout_content' ), 1 );

		// Filtros para las miniaturas y clases de body
		add_filter( 'woocommerce_cart_item_name', array( $this, 'add_product_thumbnail_to_checkout' ), 10, 3 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Intercepta la inclusión de la plantilla de WordPress para la página de checkout.
	 */
	public function override_checkout_page_template( $template ) {
		if ( is_admin() ) {
			return $template;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			$custom_page_template = WPAT_PATH . 'templates/checkout/page-checkout.php';
			if ( file_exists( $custom_page_template ) ) {
				return $custom_page_template;
			}
		}

		return $template;
	}

	/**
	 * Intercepta la carga de la plantilla checkout/form-checkout.php de WooCommerce.
	 */
	public function override_checkout_template( $template, $template_name, $template_path ) {
		if ( 'checkout/form-checkout.php' === $template_name ) {
			$custom_template = WPAT_PATH . 'templates/checkout/form-checkout.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}
		return $template;
	}

	/**
	 * Filtra el contenido de la página de checkout si usa el bloque Gutenberg.
	 */
	public function filter_checkout_content( $content ) {
		if ( is_admin() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return $content;
		}

		if ( has_block( 'woocommerce/checkout', $content ) || strpos( $content, 'wp:woocommerce/checkout' ) !== false ) {
			return do_shortcode( '[woocommerce_checkout]' );
		}

		return $content;
	}

	/**
	 * Añade clase al body en la página de checkout.
	 */
	public function add_body_class( $classes ) {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			$classes[] = 'wpat-checkout-designer-active';
		}
		return $classes;
	}

	/**
	 * Encola los estilos y scripts del diseñador de checkout.
	 */
	public function enqueue_checkout_assets() {
		if ( is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		wp_enqueue_style(
			'wpat-checkout-designer-css',
			WPAT_URL . 'assets/css/wpat-checkout-designer.css',
			array(),
			WPAT_VERSION
		);

		wp_enqueue_script(
			'wpat-checkout-designer-js',
			WPAT_URL . 'assets/js/wpat-checkout-designer.js',
			array( 'jquery' ),
			WPAT_VERSION,
			true
		);

		$layout = isset( $settings['woo_checkout_designer_layout'] ) ? $settings['woo_checkout_designer_layout'] : 'wpat-classic';

		wp_localize_script( 'wpat-checkout-designer-js', 'wpatCheckoutOptions', array(
			'layout'               => $layout,
			'email_autocorrect'    => isset( $settings['woo_checkout_designer_email_fix'] ) ? $settings['woo_checkout_designer_email_fix'] : '1',
			'mobile_summary_label' => __( 'Resumen del pedido', 'wp-agency-toolkit' ),
		) );
	}

	/**
	 * Agrega la miniatura del producto y badge de cantidad en el resumen del checkout.
	 */
	public function add_product_thumbnail_to_checkout( $product_name, $cart_item, $cart_item_key ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return $product_name;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$show_thumbs = ! isset( $settings['woo_checkout_designer_product_thumbs'] ) || '1' === $settings['woo_checkout_designer_product_thumbs'];

		if ( ! $show_thumbs ) {
			return $product_name;
		}

		$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

		if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 ) {
			$thumbnail = $_product->get_image( array( 50, 50 ) );
			$quantity  = '<span class="wpat-checkout-product-qty">' . esc_html( $cart_item['quantity'] ) . '</span>';
			
			$thumb_wrapper = '<div class="wpat-checkout-thumb-box">' . $thumbnail . $quantity . '</div>';
			return '<div class="wpat-checkout-item-title-wrap">' . $thumb_wrapper . '<span class="wpat-checkout-item-name">' . $product_name . '</span></div>';
		}

		return $product_name;
	}
}

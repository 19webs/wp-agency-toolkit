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

		// Mover el formulario de cupón fuera de la parte superior del checkout
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

		// Evitar duplicación de pasarelas de pago y botón de pago
		remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );

		// Interceptar la plantilla global de WordPress para aislar el checkout de Elementor (prioridad máxima 9999)
		add_filter( 'template_include', array( $this, 'override_checkout_page_template' ), 9999 );

		// Interceptar también la función de plantillas de WooCommerce por compatibilidad adicional
		add_filter( 'woocommerce_locate_template', array( $this, 'override_checkout_template' ), 9999, 3 );

		// Filtros para las miniaturas y clases de body
		add_filter( 'woocommerce_cart_item_name', array( $this, 'add_product_thumbnail_to_checkout' ), 10, 3 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Comprueba si la petición actual corresponde a la página de checkout.
	 *
	 * @return bool
	 */
	private function is_checkout_page() {
		if ( is_admin() ) {
			return false;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$checkout_id = wc_get_page_id( 'checkout' );
			if ( $checkout_id && is_page( $checkout_id ) && ! is_order_received_page() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Intercepta la inclusión de la plantilla de WordPress para aislar el checkout de Elementor.
	 */
	public function override_checkout_page_template( $template ) {
		if ( $this->is_checkout_page() ) {
			// Desactivar filtros de Elementor en el contenido del checkout
			if ( class_exists( '\Elementor\Plugin' ) ) {
				remove_all_filters( 'elementor/frontend/the_content' );
			}

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
	 * Añade clase al body en la página de checkout.
	 */
	public function add_body_class( $classes ) {
		if ( $this->is_checkout_page() ) {
			$classes[] = 'wpat-checkout-designer-active';
			$classes[] = 'wpat-standalone-checkout-page';
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
		if ( ! $this->is_checkout_page() ) {
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

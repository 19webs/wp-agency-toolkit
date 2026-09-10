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
		add_filter( 'template_include', array( $this, 'override_checkout_template' ), 99 );

		// Filtros para personalizar el resumen de pedido (miniaturas de productos)
		add_filter( 'woocommerce_cart_item_name', array( $this, 'add_product_thumbnail_to_checkout' ), 10, 3 );
	}

	/**
	 * Encola los estilos y scripts del diseñador de checkout solo en la página de checkout.
	 */
	public function enqueue_checkout_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
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
	 * Reemplaza la plantilla del checkout si el módulo está activo.
	 *
	 * @param string $template Ruta de la plantilla actual.
	 * @return string Ruta de la plantilla modificada.
	 */
	public function override_checkout_template( $template ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return $template;
		}

		// Interceptar form-checkout.php de WooCommerce mediante hooks
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_checkout_layout_header' ), 1 );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'render_checkout_layout_footer' ), 999 );

		return $template;
	}

	/**
	 * Inyecta el contenedor inicial y resumen móvil antes del formulario del checkout.
	 */
	public function render_checkout_layout_header( $checkout ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		$layout   = isset( $settings['woo_checkout_designer_layout'] ) ? $settings['woo_checkout_designer_layout'] : 'wpat-classic';
		$mobile_summary = ! isset( $settings['woo_checkout_designer_mobile_summary'] ) || '1' === $settings['woo_checkout_designer_mobile_summary'];

		?>
		<div class="wpat-checkout-wrapper <?php echo esc_attr( $layout ); ?>">

			<?php if ( $mobile_summary ) : ?>
				<!-- Barra Flotante de Resumen de Pedido en Móvil -->
				<div class="wpat-mobile-order-summary-toggle">
					<div class="wpat-mobile-summary-info">
						<span class="wpat-summary-icon">🛒</span>
						<span class="wpat-summary-text">Ver resumen del pedido</span>
						<span class="wpat-summary-arrow">▼</span>
					</div>
					<div class="wpat-mobile-summary-total">
						<?php echo WC()->cart ? WC()->cart->get_total() : ''; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( 'wpat-classic' === $layout || 'wpat-accordion' === $layout ) : ?>
				<!-- Pasos de Progreso / Breadcrumbs -->
				<div class="wpat-checkout-steps-bar">
					<div class="wpat-step-item active" data-step="1">
						<span class="wpat-step-num">1</span>
						<span class="wpat-step-title">Información & Envío</span>
					</div>
					<div class="wpat-step-separator">›</div>
					<div class="wpat-step-item" data-step="2">
						<span class="wpat-step-num">2</span>
						<span class="wpat-step-title">Método de Envío</span>
					</div>
					<div class="wpat-step-separator">›</div>
					<div class="wpat-step-item" data-step="3">
						<span class="wpat-step-num">3</span>
						<span class="wpat-step-title">Pago & Confirmación</span>
					</div>
				</div>
			<?php endif; ?>

			<div class="wpat-checkout-main-grid">
		<?php
	}

	/**
	 * Cierra los contenedores e inyecta sellos de confianza al final del checkout.
	 */
	public function render_checkout_layout_footer( $checkout ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		$trust_badges = ! isset( $settings['woo_checkout_designer_trust_badges'] ) || '1' === $settings['woo_checkout_designer_trust_badges'];

		?>
			</div> <!-- .wpat-checkout-main-grid -->

			<?php if ( $trust_badges ) : ?>
				<!-- Sellos de Confianza y Pago Seguro -->
				<div class="wpat-checkout-trust-badges">
					<div class="wpat-trust-item">
						<span class="wpat-trust-icon">🔒</span>
						<div class="wpat-trust-text">
							<strong>Pago 100% Seguro</strong>
							<span>Cifrado SSL de 256-bits de alta seguridad</span>
						</div>
					</div>
					<div class="wpat-trust-item">
						<span class="wpat-trust-icon">🚀</span>
						<div class="wpat-trust-text">
							<strong>Envío Garantizado</strong>
							<span>Seguimiento directo de tu paquete</span>
						</div>
					</div>
					<div class="wpat-trust-item">
						<span class="wpat-trust-icon">🛡️</span>
						<div class="wpat-trust-text">
							<strong>Garantía de Satisfacción</strong>
							<span>Soporte dedicado al cliente</span>
						</div>
					</div>
				</div>
			<?php endif; ?>

		</div> <!-- .wpat-checkout-wrapper -->
		<?php
	}

	/**
	 * Agrega la miniatura del producto y badge de cantidad en el resumen del checkout.
	 */
	public function add_product_thumbnail_to_checkout( $product_name, $cart_item, $cart_item_key ) {
		if ( ! is_checkout() ) {
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

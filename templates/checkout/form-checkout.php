<?php
/**
 * Plantilla de Checkout de Alta Conversión - WP Agency Toolkit
 * Estilo Shopify / CheckoutWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings     = WPAT_Main::get_instance()->get_settings();
$layout       = isset( $settings['woo_checkout_designer_layout'] ) ? $settings['woo_checkout_designer_layout'] : 'wpat-classic';
$trust_badges = ! isset( $settings['woo_checkout_designer_trust_badges'] ) || '1' === $settings['woo_checkout_designer_trust_badges'];
$trust_text   = ! empty( $settings['woo_checkout_designer_trust_text'] ) ? $settings['woo_checkout_designer_trust_text'] : 'Garantía de Devolución • Pago 100% Seguro • Envío Gratis';

// Imprimir avisos de WooCommerce (cupones, errores, avisos)
wc_print_notices();

do_action( 'woocommerce_before_checkout_form', $checkout );

// Si el carrito está vacío, no continuar
if ( ! $checkout->get_checkout_fields() && ! WC()->cart->needs_payment() ) {
	return;
}
?>

<div class="wpat-checkout-container <?php echo esc_attr( $layout ); ?>">

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

	<form name="checkout" method="post" class="checkout woocommerce-checkout wpat-main-checkout-form" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

		<!-- COLUMNA IZQUIERDA: Formulario de Datos del Cliente -->
		<div class="wpat-checkout-col-left">

			<?php if ( $checkout->get_checkout_fields() ) : ?>

				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<div class="col2-set wpat-customer-details-card" id="customer_details">
					<div class="col-1 wpat-billing-box">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>

					<div class="col-2 wpat-shipping-box">
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>
				</div>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<?php endif; ?>

		</div>

		<!-- COLUMNA DERECHA: Tarjeta Flotante de Resumen de Pedido -->
		<div class="wpat-checkout-col-right">
			<div class="wpat-order-review-card">
				<h3 class="wpat-summary-card-title">Resumen del pedido</h3>

				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
				
				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>

				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
			</div>
		</div>

	</form>

	<?php if ( $trust_badges ) : ?>
		<!-- Sellos de Confianza y Pago Seguro al pie del checkout -->
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
					<span><?php echo esc_html( $trust_text ); ?></span>
				</div>
			</div>
		</div>
	<?php endif; ?>

</div>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

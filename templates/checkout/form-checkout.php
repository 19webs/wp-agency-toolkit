<?php
/**
 * Plantilla de Checkout de Alta Conversión - WP Agency Toolkit
 * Estilo Shopify / CheckoutWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Inicializar de forma 100% segura el objeto $checkout para evitar Fatal Error PHP
if ( ! isset( $checkout ) || ! $checkout instanceof WC_Checkout ) {
	$checkout = function_exists( 'WC' ) ? WC()->checkout() : null;
}

if ( ! $checkout ) {
	return;
}

$settings          = WPAT_Main::get_instance()->get_settings();
$layout            = isset( $settings['woo_checkout_designer_layout'] ) ? $settings['woo_checkout_designer_layout'] : 'wpat-classic';
$trust_badges      = ! isset( $settings['woo_checkout_designer_trust_badges'] ) || '1' === $settings['woo_checkout_designer_trust_badges'];
$trust_text        = ! empty( $settings['woo_checkout_designer_trust_text'] ) ? $settings['woo_checkout_designer_trust_text'] : 'Garantía de Devolución • Pago 100% Seguro • Envío Gratis';
$order_button_text = apply_filters( 'woocommerce_order_button_text', __( 'Realizar el pedido', 'woocommerce' ) );

// Imprimir avisos de WooCommerce (errores, avisos)
wc_print_notices();

do_action( 'woocommerce_before_checkout_form', $checkout );

// Si el carrito está vacío o no requiere campos, salir
if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty() ) {
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

	<!-- Barra Flotante de Resumen Móvil -->
	<div class="wpat-mobile-order-summary-toggle">
		<div class="wpat-mobile-summary-info">
			<span class="wpat-summary-icon">🛒</span>
			<span class="wpat-summary-text">Ver resumen del pedido</span>
			<span class="wpat-summary-arrow">▼</span>
		</div>
		<div class="wpat-mobile-summary-total">
			<?php echo ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_total() : ''; ?>
		</div>
	</div>

	<form name="checkout" method="post" class="checkout woocommerce-checkout wpat-main-checkout-form" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

		<?php if ( 'wpat-classic' === $layout ) : ?>

			<!-- LAYOUT 1: CLASSIC MULTI-STEP 2 COLUMNS -->
			<div class="wpat-checkout-col-left">

				<!-- PASO 1: Datos de Contacto y Dirección -->
				<div class="wpat-step-panel active" data-step="1">
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

					<div class="wpat-step-actions">
						<button type="button" class="wpat-step-btn wpat-next-btn" data-next="2">Continuar a Opciones de Envío →</button>
					</div>
				</div>

				<!-- PASO 2: Métodos de Envío -->
				<div class="wpat-step-panel" data-step="2" style="display:none;">
					<div class="wpat-card-box">
						<h3 class="wpat-card-title">Opciones de Envío</h3>
						<div class="wpat-shipping-methods-container">
							<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
								<?php wc_cart_totals_shipping_html(); ?>
							<?php else : ?>
								<p>No se requieren opciones de envío especiales para este pedido.</p>
							<?php endif; ?>
						</div>
					</div>
					<div class="wpat-step-actions">
						<button type="button" class="wpat-step-btn wpat-prev-btn" data-prev="1">← Volver a Datos</button>
						<button type="button" class="wpat-step-btn wpat-next-btn" data-next="3">Continuar al Pago →</button>
					</div>
				</div>

				<!-- PASO 3: Pasarelas de Pago & Botón Único de Realizar Pedido -->
				<div class="wpat-step-panel" data-step="3" style="display:none;">
					<div class="wpat-card-box">
						<h3 class="wpat-card-title">Opciones de Pago</h3>
						<?php woocommerce_checkout_payment(); ?>
					</div>
					<div class="wpat-step-actions">
						<button type="button" class="wpat-step-btn wpat-prev-btn" data-prev="2">← Volver a Envío</button>
					</div>
				</div>

			</div>

			<!-- COLUMNA DERECHA: Sticky Order Summary -->
			<div class="wpat-checkout-col-right">
				<div class="wpat-order-review-card">
					<h3 class="wpat-summary-card-title">Resumen del pedido</h3>
					
					<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
					
					<div id="order_review" class="woocommerce-checkout-review-order">
						<?php woocommerce_order_review(); ?>
					</div>

					<!-- Formulario de Cupón Personalizado colocada limpia después del Total -->
					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="wpat-custom-coupon-box">
							<label class="wpat-coupon-label">¿Tienes un cupón? Introduce tu código</label>
							<div class="wpat-coupon-input-group">
								<input type="text" name="wpat_coupon_code" class="input-text wpat-coupon-field" id="wpat_coupon_code_field" placeholder="Código de cupón" value="" />
								<button type="button" class="button wpat-coupon-submit-btn" id="wpat_coupon_apply_btn">Aplicar cupón</button>
							</div>
						</div>
					<?php endif; ?>

					<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				</div>
			</div>

		<?php elseif ( 'wpat-shop-style' === $layout ) : ?>

			<!-- LAYOUT 5: WPAT SHOP-STYLE CHECKOUT -->
			<div class="wpat-checkout-col-left">

				<!-- SECCIÓN 1 & 2: CONTACTO & ENTREGA -->
				<div class="wpat-card-box wpat-shop-section-customer">
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

				<!-- SECCIÓN 3: MÉTODOS DE ENVÍO -->
				<div class="wpat-card-box wpat-shop-section-shipping" style="margin-top: 20px;">
					<h3 class="wpat-card-title">Métodos de envío</h3>
					<div class="wpat-shipping-methods-container">
						<?php if ( WC()->cart->needs_shipping() ) : ?>
							<table class="shop_table wpat-shop-style-shipping-table" style="width: 100%; border-collapse: collapse;">
								<tbody>
									<?php
									$packages = WC()->shipping()->get_packages();
									foreach ( $packages as $i => $package ) {
										$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
										$product_names = array();
										if ( count( $packages ) > 1 ) {
											foreach ( $package['contents'] as $item_id => $values ) {
												$product_names[ $item_id ] = $values['data']->get_name() . ' &times;' . $values['quantity'];
											}
										}
										wc_get_template(
											'cart/cart-shipping.php',
											array(
												'package'                  => $package,
												'available_methods'        => $package['rates'],
												'show_package_details'     => count( $packages ) > 1,
												'package_details'          => implode( ', ', $product_names ),
												'package_name'             => apply_filters( 'woocommerce_shipping_package_name', ( $i + 1 > 1 ) ? sprintf( _x( 'Shipping %d', 'shipping packages', 'woocommerce' ), ( $i + 1 ) ) : '', $i, $package ),
												'index'                    => $i,
												'chosen_method'            => $chosen_method,
												'formatted_destination'    => WC()->countries->get_formatted_address( $package['destination'] ),
												'has_calculated_shipping' => ! empty( $package['has_calculated_shipping'] ),
											)
										);
									}
									?>
								</tbody>
							</table>
						<?php else : ?>
							<p style="font-size: 13px; color: #64748b;">No se requieren opciones de envío especiales para este pedido.</p>
						<?php endif; ?>
					</div>
				</div>

				<!-- SECCIÓN 4: PAGO -->
				<div class="wpat-card-box wpat-shop-section-payment" style="margin-top: 20px;">
					<h3 class="wpat-card-title">Pago</h3>
					<p class="wpat-card-subtitle" style="font-size: 13px; color: #64748b; margin-top: -6px; margin-bottom: 15px;">Todas las transacciones son seguras y están encriptadas.</p>
					<?php woocommerce_checkout_payment(); ?>
				</div>

			</div>

			<!-- COLUMNA DERECHA: STICKY ORDER REVIEW & CUPÓN -->
			<div class="wpat-checkout-col-right">
				<div class="wpat-order-review-card wpat-shop-sidebar-card">
					
					<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
					
					<div id="order_review" class="woocommerce-checkout-review-order">
						<?php woocommerce_order_review(); ?>
					</div>

					<!-- Formulario de Cupón Personalizado colocada limpia antes/después del Total -->
					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="wpat-custom-coupon-box">
							<div class="wpat-coupon-input-group">
								<input type="text" name="wpat_coupon_code" class="input-text wpat-coupon-field" id="wpat_coupon_code_field" placeholder="Código de descuento" value="" />
								<button type="button" class="button wpat-coupon-submit-btn" id="wpat_coupon_apply_btn">Aplicar</button>
							</div>
						</div>
					<?php endif; ?>

					<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				</div>
			</div>

		<?php else : ?>

			<!-- LAYOUTS 2, 3, 4: EXPRESS, ACCORDION, MINIMALIST -->
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

				<div class="wpat-card-box" style="margin-top: 20px;">
					<h3 class="wpat-card-title">Opciones de Pago</h3>
					<?php woocommerce_checkout_payment(); ?>
				</div>
			</div>

			<div class="wpat-checkout-col-right">
				<div class="wpat-order-review-card">
					<h3 class="wpat-summary-card-title">Resumen del pedido</h3>
					
					<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
					
					<div id="order_review" class="woocommerce-checkout-review-order">
						<?php woocommerce_order_review(); ?>
					</div>

					<!-- Formulario de Cupón Personalizado colocada limpia después del Total -->
					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="wpat-custom-coupon-box">
							<label class="wpat-coupon-label">¿Tienes un cupón? Introduce tu código</label>
							<div class="wpat-coupon-input-group">
								<input type="text" name="wpat_coupon_code" class="input-text wpat-coupon-field" id="wpat_coupon_code_field" placeholder="Código de cupón" value="" />
								<button type="button" class="button wpat-coupon-submit-btn" id="wpat_coupon_apply_btn">Aplicar cupón</button>
							</div>
						</div>
					<?php endif; ?>

					<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				</div>
			</div>

		<?php endif; ?>

	</form>

	<?php if ( $trust_badges ) : ?>
		<!-- Sellos de Confianza -->
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

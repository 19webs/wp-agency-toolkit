<?php
/**
 * Plantilla Personalizada de Carrito - WP Agency Toolkit
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 7.9.0
 */

defined( 'ABSPATH' ) || exit;

$settings    = WPAT_Main::get_instance()->get_settings();
$cart_layout = isset( $settings['woo_cart_designer_layout'] ) ? $settings['woo_cart_designer_layout'] : 'wpat-cart-classic';
$designer    = WPAT_Woo_Checkout_Designer::get_instance();

do_action( 'woocommerce_before_cart' ); ?>

<div class="wpat-cart-container <?php echo esc_attr( $cart_layout ); ?>">

	<?php echo $designer->get_free_shipping_progress_html(); ?>

	<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
		<?php do_action( 'woocommerce_before_cart_table' ); ?>

		<div class="wpat-cart-layout-wrapper">

			<!-- Columna Principal: Lista de Productos -->
			<div class="wpat-cart-items-column">
				<?php if ( 'wpat-cart-modern' === $cart_layout ) : ?>

					<!-- VISTA EN TARJETAS MODERNAS -->
					<div class="wpat-cart-modern-cards-list">
						<?php
						foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
							$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
							$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

							if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
								$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
								?>
								<div class="wpat-cart-modern-card <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
									<div class="wpat-cart-modern-thumb">
										<?php
										$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( array( 90, 90 ) ), $cart_item, $cart_item_key );
										if ( ! $product_permalink ) {
											echo $thumbnail;
										} else {
											printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail );
										}
										?>
									</div>
									<div class="wpat-cart-modern-details">
										<div class="wpat-cart-modern-title-row">
											<h4 class="wpat-cart-modern-title">
												<?php
												if ( ! $product_permalink ) {
													echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;' );
												} else {
													echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
												}
												?>
											</h4>
											<div class="wpat-cart-modern-remove">
												<?php
												echo apply_filters(
													'woocommerce_cart_item_remove_link',
													sprintf(
														'<a href="%s" class="remove wpat-modern-remove-btn" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
														esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
														esc_attr( sprintf( __( 'Eliminar %s del carrito', 'woocommerce' ), $_product->get_name() ) ),
														esc_attr( $product_id ),
														esc_attr( $_product->get_sku() )
													),
													$cart_item_key
												);
												?>
											</div>
										</div>

										<?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>

										<?php
										if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
											echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Disponible para reserva', 'woocommerce' ) . '</p>', $product_id ) );
										}
										?>

										<div class="wpat-cart-modern-price-qty-row">
											<div class="wpat-cart-modern-price">
												<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?>
											</div>
											<div class="wpat-cart-modern-qty-box">
												<button type="button" class="wpat-qty-btn wpat-qty-minus">-</button>
												<?php
												if ( $_product->is_sold_individually() ) {
													$min_quantity = 1;
													$max_quantity = 1;
												} else {
													$min_quantity = 0;
													$max_quantity = $_product->get_max_purchase_quantity();
												}

												$product_quantity = woocommerce_quantity_input(
													array(
														'input_name'   => "cart[{$cart_item_key}][qty]",
														'input_value'  => $cart_item['quantity'],
														'max_value'    => $max_quantity,
														'min_value'    => $min_quantity,
														'product_name' => $_product->get_name(),
													),
													$_product,
													false
												);

												echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item );
												?>
												<button type="button" class="wpat-qty-btn wpat-qty-plus">+</button>
											</div>
											<div class="wpat-cart-modern-subtotal">
												<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
											</div>
										</div>
									</div>
								</div>
								<?php
							}
						}
						?>
					</div>

				<?php else : ?>

					<!-- VISTA EN TABLA CLÁSICA ESTILIZADA -->
					<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents wpat-cart-table" cellspacing="0">
						<thead>
							<tr>
								<th class="product-remove">&nbsp;</th>
								<th class="product-thumbnail">&nbsp;</th>
								<th class="product-name"><?php esc_html_e( 'Producto', 'woocommerce' ); ?></th>
								<th class="product-price"><?php esc_html_e( 'Precio', 'woocommerce' ); ?></th>
								<th class="product-quantity"><?php esc_html_e( 'Cantidad', 'woocommerce' ); ?></th>
								<th class="product-subtotal"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
								$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
								$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

								if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
									$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
									?>
									<tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

										<td class="product-remove">
											<?php
											echo apply_filters(
												'woocommerce_cart_item_remove_link',
												sprintf(
													'<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
													esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
													esc_attr( sprintf( __( 'Eliminar %s del carrito', 'woocommerce' ), $_product->get_name() ) ),
													esc_attr( $product_id ),
													esc_attr( $_product->get_sku() )
												),
												$cart_item_key
											);
											?>
										</td>

										<td class="product-thumbnail">
											<?php
											$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( array( 70, 70 ) ), $cart_item, $cart_item_key );
											if ( ! $product_permalink ) {
												echo $thumbnail;
											} else {
												printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail );
											}
											?>
										</td>

										<td class="product-name" data-title="<?php esc_attr_e( 'Producto', 'woocommerce' ); ?>">
											<?php
											if ( ! $product_permalink ) {
												echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) . '&nbsp;' );
											} else {
												echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
											}

											do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
											echo wc_get_formatted_cart_item_data( $cart_item );
											?>
										</td>

										<td class="product-price" data-title="<?php esc_attr_e( 'Precio', 'woocommerce' ); ?>">
											<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?>
										</td>

										<td class="product-quantity" data-title="<?php esc_attr_e( 'Cantidad', 'woocommerce' ); ?>">
											<div class="wpat-cart-table-qty-box">
												<button type="button" class="wpat-qty-btn wpat-qty-minus">-</button>
												<?php
												if ( $_product->is_sold_individually() ) {
													$min_quantity = 1;
													$max_quantity = 1;
												} else {
													$min_quantity = 0;
													$max_quantity = $_product->get_max_purchase_quantity();
												}

												$product_quantity = woocommerce_quantity_input(
													array(
														'input_name'   => "cart[{$cart_item_key}][qty]",
														'input_value'  => $cart_item['quantity'],
														'max_value'    => $max_quantity,
														'min_value'    => $min_quantity,
														'product_name' => $_product->get_name(),
													),
													$_product,
													false
												);

												echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item );
												?>
												<button type="button" class="wpat-qty-btn wpat-qty-plus">+</button>
											</div>
										</td>

										<td class="product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
											<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
										</td>
									</tr>
									<?php
								}
							}
							?>
						</tbody>
					</table>
				<?php endif; ?>

				<!-- Botones de Acción / Cupones en Carrito -->
				<div class="wpat-cart-actions-bar">
					<?php if ( wc_coupons_enabled() ) { ?>
						<div class="wpat-cart-coupon-box">
							<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Código de cupón', 'woocommerce' ); ?>" />
							<button type="submit" class="button wpat-cart-coupon-btn" name="apply_coupon" value="<?php esc_attr_e( 'Aplicar cupón', 'woocommerce' ); ?>"><?php esc_attr_e( 'Aplicar', 'woocommerce' ); ?></button>
							<?php do_action( 'woocommerce_cart_coupon' ); ?>
						</div>
					<?php } ?>

					<button type="submit" class="button wpat-cart-update-btn" name="update_cart" value="<?php esc_attr_e( 'Actualizar carrito', 'woocommerce' ); ?>"><?php esc_html_e( 'Actualizar carrito', 'woocommerce' ); ?></button>
					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
				</div>
			</div>

			<!-- Columna Secundaria: Resumen de Totales y Checkout (Sticky) -->
			<div class="wpat-cart-sidebar-column">
				<div class="wpat-cart-totals-card">
					<h3><?php esc_html_e( 'Resumen del Carrito', 'woocommerce' ); ?></h3>
					<?php
					/**
					 * Cart totals template
					 */
					woocommerce_cart_totals();
					?>
				</div>
			</div>
		</div>

		<?php do_action( 'woocommerce_after_cart_table' ); ?>
	</form>

</div>

<?php do_action( 'woocommerce_after_cart' ); ?>

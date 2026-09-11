<?php
/**
 * Contenido Interior del Mini-Carrito Deslizable (Slide-Out / Drawer Cart) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) : ?>
	<div class="wpat-drawer-items-list">
		<?php
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
				?>
				<div class="wpat-drawer-item">
					<div class="wpat-drawer-item-thumb">
						<?php
						$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( array( 65, 65 ) ), $cart_item, $cart_item_key );
						if ( ! $product_permalink ) {
							echo $thumbnail;
						} else {
							printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail );
						}
						?>
					</div>
					<div class="wpat-drawer-item-info">
						<div class="wpat-drawer-item-head">
							<h5 class="wpat-drawer-item-title">
								<?php
								if ( ! $product_permalink ) {
									echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) );
								} else {
									echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
								}
								?>
							</h5>
							<?php
							echo apply_filters(
								'woocommerce_cart_item_remove_link',
								sprintf(
									'<a href="%s" class="remove wpat-drawer-remove-btn" aria-label="%s" data-product_id="%s" data-product_sku="%s" data-cart_item_key="%s">&times;</a>',
									esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
									esc_attr( sprintf( __( 'Eliminar %s del carrito', 'woocommerce' ), $_product->get_name() ) ),
									esc_attr( $product_id ),
									esc_attr( $_product->get_sku() ),
									esc_attr( $cart_item_key )
								),
								$cart_item_key
							);
							?>
						</div>

						<div class="wpat-drawer-item-price-qty">
							<span class="wpat-drawer-item-price">
								<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?>
							</span>

							<div class="wpat-drawer-qty-controls">
								<span class="wpat-drawer-qty-label"><?php echo esc_html( $cart_item['quantity'] ); ?> ud.</span>
							</div>
						</div>
					</div>
				</div>
				<?php
			}
		}
		?>
	</div>

	<!-- Pie del Drawer: Subtotal y Botones de Acción Directa -->
	<div class="wpat-drawer-footer">
		<div class="wpat-drawer-subtotal-row">
			<span><?php esc_html_e( 'Subtotal:', 'woocommerce' ); ?></span>
			<strong><?php echo WC()->cart->get_cart_subtotal(); ?></strong>
		</div>

		<div class="wpat-drawer-buttons">
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button wpat-drawer-btn-view-cart"><?php esc_html_e( 'Ver Carrito', 'woocommerce' ); ?></a>
			<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button checkout-button wpat-cart-drawer-checkout-btn"><?php esc_html_e( 'Finalizar Compra', 'woocommerce' ); ?> &rarr;</a>
		</div>
	</div>

<?php else : ?>
	<div class="wpat-drawer-empty-state">
		<span class="wpat-empty-icon">🛒</span>
		<h4><?php esc_html_e( 'Tu carrito está vacío', 'wp-agency-toolkit' ); ?></h4>
		<p><?php esc_html_e( 'Explora nuestra tienda y añade productos para comenzar tu compra.', 'wp-agency-toolkit' ); ?></p>
		<a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="button wpat-return-shop-btn">
			<?php esc_html_e( 'Volver a la tienda', 'woocommerce' ); ?>
		</a>
	</div>
<?php endif; ?>

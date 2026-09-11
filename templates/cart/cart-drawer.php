<?php
/**
 * Plantilla del Mini-Carrito Deslizable (Slide-Out / Drawer Cart) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

$designer = WPAT_Woo_Checkout_Designer::get_instance();
?>

<!-- Overlay Translúcido para Cerrar el Carrito -->
<div class="wpat-drawer-overlay"></div>

<!-- Panel Deslizable Lateral del Carrito -->
<div class="wpat-cart-drawer-panel">
	<div class="wpat-drawer-header">
		<div class="wpat-drawer-title">
			<span class="wpat-drawer-icon">🛒</span>
			<h3><?php esc_html_e( 'Tu Carrito', 'wp-agency-toolkit' ); ?></h3>
			<span class="wpat-drawer-cart-count"><?php echo function_exists( 'WC' ) && WC()->cart ? esc_html( WC()->cart->get_cart_contents_count() ) : '0'; ?></span>
		</div>
		<button type="button" class="wpat-drawer-close-btn" aria-label="<?php esc_attr_e( 'Cerrar carrito', 'wp-agency-toolkit' ); ?>">&times;</button>
	</div>

	<!-- Barra de Envío Gratis en el Drawer -->
	<div class="wpat-drawer-shipping-bar">
		<?php echo $designer->get_free_shipping_progress_html(); ?>
	</div>

	<!-- Contenido Dinámico Actualizado por AJAX -->
	<div class="wpat-drawer-cart-body">
		<?php
		$drawer_content_template = WPAT_PATH . 'templates/cart/cart-drawer-content.php';
		if ( file_exists( $drawer_content_template ) ) {
			include $drawer_content_template;
		}
		?>
	</div>
</div>

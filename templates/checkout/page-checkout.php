<?php
/**
 * Standalone High-Conversion Checkout Page Template - WP Agency Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div id="wpat-checkout-builder-wrapper" class="wpat-checkout-builder-wrapper">
	<?php
	if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) {
		echo do_shortcode( '[woocommerce_checkout]' );
	} elseif ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		echo do_shortcode( '[woocommerce_checkout]' );
	} else {
		$checkout      = function_exists( 'WC' ) ? WC()->checkout() : null;
		$template_file = WPAT_PATH . 'templates/checkout/form-checkout.php';
		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			echo do_shortcode( '[woocommerce_checkout]' );
		}
	}
	?>
</div>

<?php
get_footer();

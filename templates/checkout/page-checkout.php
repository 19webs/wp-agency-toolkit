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
	$template_file = WPAT_PATH . 'templates/checkout/form-checkout.php';
	if ( file_exists( $template_file ) ) {
		include $template_file;
	}
	?>
</div>

<?php
get_footer();

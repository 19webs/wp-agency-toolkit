<?php
/**
 * Standalone High-Conversion Cart Page Template - WP Agency Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div id="wpat-cart-builder-wrapper" class="wpat-cart-builder-wrapper" style="padding: 20px 15px;">
	<?php
	$template_file = WPAT_PATH . 'templates/cart/cart.php';
	if ( file_exists( $template_file ) ) {
		include $template_file;
	}
	?>
</div>

<?php
get_footer();

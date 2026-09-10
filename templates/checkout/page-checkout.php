<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_title( '|', true, 'right' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'wpat-standalone-checkout-page' ); ?>>

	<!-- CABECERA AISLADA TIPO CHECKOUTWC -->
	<header class="wpat-checkout-header">
		<div class="wpat-header-container">
			<div class="wpat-header-logo">
				<?php
				if ( has_custom_logo() ) {
					the_custom_logo();
				} else {
					echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="wpat-site-title">' . esc_html( get_bloginfo( 'name' ) ) . '</a>';
				}
				?>
			</div>
			<div class="wpat-header-secure-badge">
				<span class="lock-icon">🔒</span>
				<span>Pago 100% Seguro</span>
			</div>
		</div>
	</header>

	<!-- CONTENIDO PRINCIPAL DEL CHECKOUT -->
	<main class="wpat-checkout-main-content">
		<?php
		$template_file = WPAT_PATH . 'templates/checkout/form-checkout.php';
		if ( file_exists( $template_file ) ) {
			include $template_file;
		}
		?>
	</main>

	<!-- PIE DE PÁGINA AISLADO -->
	<footer class="wpat-checkout-footer">
		<div class="wpat-footer-container">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. Todos los derechos reservados.</p>
			<div class="wpat-footer-links">
				<?php
				$privacy_id = get_option( 'wp_page_for_privacy_policy' );
				if ( $privacy_id ) {
					echo '<a href="' . esc_url( get_permalink( $privacy_id ) ) . '" target="_blank">Política de Privacidad</a>';
					echo '<span>•</span>';
				}
				?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Volver a la tienda</a>
			</div>
		</div>
	</footer>

	<?php wp_footer(); ?>
</body>
</html>

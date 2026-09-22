<?php
/**
 * Plantilla de Cabecera de Email - WP Agency Toolkit
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates/Emails
 * @version 7.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings       = WPAT_Main::get_instance()->get_settings();
$logo_url       = ! empty( $settings['woo_email_logo_url'] ) ? esc_url( $settings['woo_email_logo_url'] ) : get_option( 'woocommerce_email_header_image' );
$logo_width     = ! empty( $settings['woo_email_logo_width'] ) ? absint( $settings['woo_email_logo_width'] ) : 150;
$body_bg        = ! empty( $settings['woo_email_body_bg'] ) ? esc_attr( $settings['woo_email_body_bg'] ) : '#f8fafc';
$card_bg        = ! empty( $settings['woo_email_card_bg'] ) ? esc_attr( $settings['woo_email_card_bg'] ) : '#ffffff';
$primary_color  = ! empty( $settings['woo_email_primary_color'] ) ? esc_attr( $settings['woo_email_primary_color'] ) : '#2563eb';
$template_style = isset( $settings['woo_email_template_style'] ) ? $settings['woo_email_template_style'] : 'modern';
$email_heading  = isset( $email_heading ) ? $email_heading : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<title><?php echo get_bloginfo( 'name', 'display' ); ?></title>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
		<table width="100%" id="outer_wrapper" style="background-color: <?php echo $body_bg; ?>; padding: 30px 0;">
			<tr>
				<td align="center" valign="top">
					<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
						<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
							<tr>
								<td align="center" valign="top">
									<div id="template_header_image">
										<?php
										if ( ! empty( $logo_url ) ) {
											echo '<p style="margin-top:0; margin-bottom:20px; text-align:center;"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '" style="max-width:' . $logo_width . 'px; height:auto; border:none; display:inline-block;" /></p>';
										}
										?>
									</div>
									<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container" style="border-radius: <?php echo 'modern' === $template_style ? '12px' : '0px'; ?>; background: <?php echo $card_bg; ?>;">
										<?php if ( ! empty( $email_heading ) ) : ?>
										<tr>
											<td align="center" valign="top">
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header">
													<tr>
														<td id="header_wrapper">
															<h1><?php echo wp_kses_post( $email_heading ); ?></h1>
														</td>
													</tr>
												</table>
											</td>
										</tr>
										<?php endif; ?>
										<tr>
											<td align="center" valign="top">
												<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_body">
													<tr>
														<td valign="top" id="body_content">
															<table border="0" cellpadding="20" cellspacing="0" width="100%">
																<tr>
																	<td valign="top">
																		<div id="body_content_inner">
																			<?php
																			$email_type_id = ( isset( $email ) && isset( $email->id ) ) ? $email->id : 'customer_processing_order';
																			$raw_welcome   = WPAT_Woo_Email_Designer::get_message_for_type( $email_type_id, 'welcome', $settings );
																			if ( ! empty( $raw_welcome ) ) {
																				$order_obj    = ( isset( $order ) && is_a( $order, 'WC_Order' ) ) ? $order : null;
																				$welcome_text = WPAT_Woo_Email_Designer::get_instance()->replace_email_placeholders( $raw_welcome, $order_obj );
																				echo '<div style="background: #f1f5f9; border-left: 4px solid ' . esc_attr( $primary_color ) . '; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 700; color: #0f172a;">' . wp_kses_post( $welcome_text ) . '</div>';
																			}
																			?>

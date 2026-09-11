<?php
/**
 * Plantilla de Pie de Página de Email - WP Agency Toolkit
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates/Emails
 * @version 7.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings    = WPAT_Main::get_instance()->get_settings();
$footer_text = ! empty( $settings['woo_email_footer_text'] ) ? $settings['woo_email_footer_text'] : get_option( 'woocommerce_email_footer_text' );

$social_fb   = isset( $settings['woo_email_social_fb'] ) ? trim( $settings['woo_email_social_fb'] ) : '';
$social_ig   = isset( $settings['woo_email_social_ig'] ) ? trim( $settings['woo_email_social_ig'] ) : '';
$social_tw   = isset( $settings['woo_email_social_tw'] ) ? trim( $settings['woo_email_social_tw'] ) : '';
$social_web  = isset( $settings['woo_email_social_web'] ) ? trim( $settings['woo_email_social_web'] ) : '';
?>
																		</div>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td align="center" valign="top">
									<table border="0" cellpadding="10" cellspacing="0" width="600" id="template_footer">
										<tr>
											<td valign="top" style="padding: 24px; text-align: center;">
												<?php if ( $social_fb || $social_ig || $social_tw || $social_web ) : ?>
													<p style="margin: 0 0 14px 0; font-size: 13px;">
														<?php if ( $social_fb ) : ?><a href="<?php echo esc_url( $social_fb ); ?>" style="margin: 0 8px; font-weight: bold;">Facebook</a><?php endif; ?>
														<?php if ( $social_ig ) : ?><a href="<?php echo esc_url( $social_ig ); ?>" style="margin: 0 8px; font-weight: bold;">Instagram</a><?php endif; ?>
														<?php if ( $social_tw ) : ?><a href="<?php echo esc_url( $social_tw ); ?>" style="margin: 0 8px; font-weight: bold;">X (Twitter)</a><?php endif; ?>
														<?php if ( $social_web ) : ?><a href="<?php echo esc_url( $social_web ); ?>" style="margin: 0 8px; font-weight: bold;">Sitio Web</a><?php endif; ?>
													</p>
												<?php endif; ?>

												<div id="credit" style="font-size: 12px; color: #64748b; line-height: 1.5;">
													<?php echo wp_kses_post( wpautop( wptexturize( $footer_text ) ) ); ?>
												</div>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</div>
				</td>
			</tr>
		</table>
	</body>
</html>

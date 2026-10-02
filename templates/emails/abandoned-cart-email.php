<?php
/**
 * Plantilla de Email Responsiva para Carrito Abandonado - WP Agency Toolkit
 *
 * Variables disponibles:
 * @var string $customer_name   Nombre del cliente.
 * @var string $email_title     Título / Encabezado principal.
 * @var string $email_content   Contenido o mensaje introductorio.
 * @var array  $cart_items      Array de productos en el carrito (nombre, cantidad, precio, imagen, enlace, variantes).
 * @var string $cart_total      Total del pedido formateado (ej. 49,99 €).
 * @var string $recovery_url    Enlace de recuperación directa en 1 clic.
 * @var string $coupon_code     Código de cupón generado (si aplica).
 * @var string $discount_text   Texto explicativo del descuento (si aplica).
 * @var string $button_text     Texto del botón CTA.
 * @var string $button_color    Color hexadecimal del botón principal.
 * @var string $site_name       Nombre de la tienda.
 * @var string $site_url        URL de la tienda.
 * @var string $logo_url        URL del logo de la tienda.
 * @var string $footer_text     Texto de pie de página.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo esc_html( $email_title ); ?></title>
	<style type="text/css">
		body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
		table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
		img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
		body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
		@media screen and (max-width: 600px) {
			.email-container { width: 100% !important; }
			.product-table td { display: block !important; width: 100% !important; box-sizing: border-box; }
			.product-img-col { text-align: center !important; padding-bottom: 10px !important; }
			.product-details-col { text-align: center !important; padding-bottom: 15px !important; }
			.product-price-col { text-align: center !important; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px !important; }
			.action-btn { width: 100% !important; box-sizing: border-box !important; }
		}
	</style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc;">
	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; padding: 30px 15px;">
		<tr>
			<td align="center">
				<table border="0" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
					
					<!-- CABECERA CON LOGO -->
					<tr>
						<td align="center" style="padding: 30px 20px 20px 20px; border-bottom: 1px solid #f1f5f9;">
							<?php if ( ! empty( $logo_url ) ) : ?>
								<a href="<?php echo esc_url( $site_url ); ?>" target="_blank">
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="max-height: 55px; max-width: 220px; height: auto;" />
								</a>
							<?php else : ?>
								<h2 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">
									<a href="<?php echo esc_url( $site_url ); ?>" target="_blank" style="color: #0f172a; text-decoration: none;">
										<?php echo esc_html( $site_name ); ?>
									</a>
								</h2>
							<?php endif; ?>
						</td>
					</tr>

					<!-- MENSAJE PRINCIPAL -->
					<tr>
						<td style="padding: 35px 30px 25px 30px; text-align: left;">
							<h1 style="margin: 0 0 15px 0; font-size: 22px; font-weight: 700; color: #1e293b; line-height: 1.3;">
								<?php echo esc_html( $email_title ); ?>
							</h1>
							
							<div style="font-size: 15px; line-height: 1.6; color: #475569; margin-bottom: 25px;">
								<?php echo wp_kses_post( wpautop( $email_content ) ); ?>
							</div>

							<!-- CAJA DE CUPÓN DESTACADA (SI EXISTE) -->
							<?php if ( ! empty( $coupon_code ) ) : ?>
								<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #eff6ff; border: 2px dashed #3b82f6; border-radius: 8px; margin-bottom: 30px;">
									<tr>
										<td align="center" style="padding: 18px 15px; text-align: center;">
											<span style="font-size: 13px; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">
												🎁 <?php echo esc_html( ! empty( $discount_text ) ? $discount_text : '¡Tu descuento exclusivo!' ); ?>
											</span>
											<span style="display: inline-block; background-color: #ffffff; padding: 6px 18px; border-radius: 6px; font-size: 19px; font-weight: 800; color: #1d4ed8; letter-spacing: 1.5px; border: 1px solid #bfdbfe;">
												<?php echo esc_html( $coupon_code ); ?>
											</span>
											<span style="display: block; font-size: 12px; color: #64748b; margin-top: 6px;">
												(Se aplicará automáticamente al pulsar el botón de abajo)
											</span>
										</td>
									</tr>
								</table>
							<?php endif; ?>

							<!-- TABLA DE PRODUCTOS EN EL CARRITO -->
							<table border="0" cellpadding="0" cellspacing="0" width="100%" class="product-table" style="border-collapse: collapse; margin-bottom: 25px; border: 1px solid #f1f5f9; border-radius: 8px; overflow: hidden;">
								<thead>
									<tr style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;">
										<th align="left" colspan="2" style="padding: 12px 15px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Producto</th>
										<th align="center" style="padding: 12px 10px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Cant.</th>
										<th align="right" style="padding: 12px 15px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Precio</th>
									</tr>
								</thead>
								<tbody>
									<?php if ( ! empty( $cart_items ) && is_array( $cart_items ) ) : ?>
										<?php foreach ( $cart_items as $item ) : ?>
											<tr style="border-bottom: 1px solid #f1f5f9;">
												<td width="60" class="product-img-col" style="padding: 12px 0 12px 15px; vertical-align: middle;">
													<?php if ( ! empty( $item['image_url'] ) ) : ?>
														<img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" width="55" height="55" style="border-radius: 6px; object-fit: cover; display: block; border: 1px solid #e2e8f0;" />
													<?php else : ?>
														<div style="width: 55px; height: 55px; background: #e2e8f0; border-radius: 6px; text-align: center; line-height: 55px; font-size: 20px;">🛍️</div>
													<?php endif; ?>
												</td>
												<td class="product-details-col" style="padding: 12px 15px; vertical-align: middle;">
													<strong style="font-size: 14px; color: #1e293b; display: block; line-height: 1.3;">
														<?php echo esc_html( $item['name'] ); ?>
													</strong>
													<?php if ( ! empty( $item['meta'] ) ) : ?>
														<span style="font-size: 12px; color: #64748b; display: block; margin-top: 3px;">
															<?php echo esc_html( $item['meta'] ); ?>
														</span>
													<?php endif; ?>
												</td>
												<td align="center" style="padding: 12px 10px; font-size: 14px; color: #475569; vertical-align: middle;">
													x<?php echo esc_html( $item['quantity'] ); ?>
												</td>
												<td align="right" class="product-price-col" style="padding: 12px 15px; font-size: 14px; font-weight: 700; color: #0f172a; vertical-align: middle;">
													<?php echo wp_strip_all_tags( html_entity_decode( $item['price_html'] ) ); ?>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
								<tfoot>
									<tr style="background-color: #f8fafc;">
										<td colspan="3" align="right" style="padding: 14px 15px; font-size: 14px; font-weight: 700; color: #475569;">
											Total de tu pedido:
										</td>
										<td align="right" style="padding: 14px 15px; font-size: 17px; font-weight: 800; color: #0f172a;">
											<?php echo wp_strip_all_tags( html_entity_decode( $cart_total ) ); ?>
										</td>
									</tr>
								</tfoot>
							</table>

							<!-- BOTÓN DE ACCIÓN / RECUPERACIÓN (CTA) -->
							<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 30px 0 10px 0;">
								<tr>
									<td align="center">
										<a href="<?php echo esc_url( $recovery_url ); ?>" target="_blank" class="action-btn" style="display: inline-block; background-color: <?php echo esc_attr( ! empty( $button_color ) ? $button_color : '#2563eb' ); ?>; color: #ffffff; font-size: 16px; font-weight: 700; text-decoration: none; padding: 15px 36px; border-radius: 8px; letter-spacing: 0.3px; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
											<?php echo esc_html( ! empty( $button_text ) ? $button_text : 'Recuperar mi carrito ahora →' ); ?>
										</a>
									</td>
								</tr>
							</table>

						</td>
					</tr>

					<!-- PIE DE PÁGINA -->
					<tr>
						<td style="padding: 25px 30px; background-color: #f1f5f9; text-align: center; border-top: 1px solid #e2e8f0;">
							<?php if ( ! empty( $footer_text ) ) : ?>
								<p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b; line-height: 1.5;">
									<?php echo wp_kses_post( $footer_text ); ?>
								</p>
							<?php endif; ?>
							<p style="margin: 0; font-size: 11px; color: #94a3b8;">
								Has recibido este email porque iniciaste una compra en <a href="<?php echo esc_url( $site_url ); ?>" style="color: #64748b; text-decoration: underline;"><?php echo esc_html( $site_name ); ?></a>. Si ya no estás interesado, puedes ignorar este mensaje.
							</p>
						</td>
					</tr>

				</table>
			</td>
		</tr>
	</table>
</body>
</html>

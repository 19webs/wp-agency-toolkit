<?php
/**
 * Módulo: Modo Catálogo - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Catalog {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Catalog
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Catalog
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Hook de depuración para inspeccionar el estado del servidor
		add_action( 'wp_head', array( $this, 'wpat_catalog_debug' ) );

		$settings = WPAT_Main::get_instance()->get_settings();

		// Ocultar Precios
		if ( isset( $settings['woo_catalog_hide_price'] ) && '1' === $settings['woo_catalog_hide_price'] ) {
			add_filter( 'woocommerce_get_price_html', array( $this, 'hide_prices' ), 10, 2 );
			
			// Ocultar también en el carrito / checkout por si acaso
			add_filter( 'woocommerce_cart_item_price', array( $this, 'hide_cart_prices' ), 10, 3 );
			add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'hide_cart_prices' ), 10, 3 );
			add_filter( 'woocommerce_cart_subtotal', array( $this, 'hide_totals' ) );
			add_filter( 'woocommerce_cart_totals_order_total_html', array( $this, 'hide_totals' ) );
		}

		// Ocultar Añadir al carrito
		if ( isset( $settings['woo_catalog_hide_cart'] ) && '1' === $settings['woo_catalog_hide_cart'] ) {
			// Remover los botones visualmente en el frontend
			add_action( 'wp', array( $this, 'remove_add_to_cart_elements' ) );

			// Inyectar CSS en el head para ocultar botones de compra originales de forma limpia y transparente
			add_action( 'wp_head', array( $this, 'inject_frontend_css' ) );

			// Inyectar acciones alternativas en la ficha individual de producto (múltiples ganchos para máxima compatibilidad)
			add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_alternative_actions' ), 10 );
			add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_alternative_actions' ), 10 );
			add_action( 'woocommerce_single_product_summary', array( $this, 'render_alternative_actions' ), 30 );
			add_action( 'woocommerce_single_product_summary', array( $this, 'render_alternative_actions' ), 35 );
			add_action( 'woocommerce_product_meta_start', array( $this, 'render_alternative_actions' ), 10 );
			add_filter( 'woocommerce_short_description', array( $this, 'append_actions_to_short_description' ), 99 );
			
			// Encolar los scripts del formulario en el footer del frontend
			add_action( 'wp_footer', array( $this, 'enqueue_frontend_scripts' ) );
		}

		// Registrar endpoint AJAX de envío del formulario
		add_action( 'wp_ajax_wpat_catalog_contact_form', array( $this, 'handle_contact_form_submit' ) );
		add_action( 'wp_ajax_nopriv_wpat_catalog_contact_form', array( $this, 'handle_contact_form_submit' ) );
	}

	/**
	 * Reemplaza el precio en el frontend por vacío o un texto personalizado.
	 *
	 * @param string     $price HTML del precio.
	 * @param WC_Product $product El producto.
	 * @return string
	 */
	public function hide_prices( $price, $product ) {
		if ( is_admin() ) {
			return $price;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$text     = ! empty( $settings['woo_catalog_price_text'] ) ? $settings['woo_catalog_price_text'] : '';

		return ! empty($text) ? '<span class="wpat-catalog-price-text">' . esc_html( $text ) . '</span>' : '';
	}

	/**
	 * Filtra los precios de los productos en el carrito.
	 */
	public function hide_cart_prices( $price, $cart_item, $cart_item_key ) {
		return '';
	}

	/**
	 * Filtra los totales en el carrito.
	 */
	public function hide_totals( $total ) {
		return '';
	}

	/**
	 * Remueve los botones de añadir al carrito de los hooks nativos de WooCommerce en el frontend.
	 */
	public function remove_add_to_cart_elements() {
		if ( is_admin() ) {
			return;
		}

		// En listados / bucles (tienda, categorías, etc.)
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

		// En la ficha individual del producto (simple y variable)
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	}

	/**
	 * Bandera para evitar la renderización duplicada de los botones de contacto.
	 *
	 * @var bool
	 */
	private $actions_rendered = false;

	/**
	 * Añade los botones de contacto al final de la descripción corta del producto si no se han pintado aún.
	 */
	public function append_actions_to_short_description( $post_excerpt ) {
		echo "\n<!-- WPAT DEBUG: append_actions_to_short_description entered -->\n";
		if ( is_admin() || ! is_product() ) {
			echo "<!-- WPAT DEBUG: append short desc returned: not product page or is admin -->\n";
			return $post_excerpt;
		}

		ob_start();
		$this->render_alternative_actions();
		$actions_html = ob_get_clean();

		return $post_excerpt . $actions_html;
	}

	/**
	 * Renderiza los botones alternativos de WhatsApp y/o el formulario de contacto por email.
	 */
	public function render_alternative_actions() {
		echo "\n<!-- WPAT DEBUG: render_alternative_actions entered -->\n";
		if ( is_admin() ) {
			echo "<!-- WPAT DEBUG: returned is_admin -->\n";
			return;
		}

		if ( $this->actions_rendered ) {
			echo "<!-- WPAT DEBUG: returned already rendered -->\n";
			return;
		}

		global $post;
		if ( ! $post ) {
			echo "<!-- WPAT DEBUG: returned post object empty -->\n";
			return;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			echo "<!-- WPAT DEBUG: returned product object empty for post ID: " . $post->ID . " -->\n";
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		
		// Verificar que al menos un canal esté activo
		$wa_enabled   = isset( $settings['woo_catalog_wa_enable'] ) && '1' === $settings['woo_catalog_wa_enable'] && ! empty( $settings['woo_catalog_wa_phone'] );
		$form_enabled = isset( $settings['woo_catalog_form_enable'] ) && '1' === $settings['woo_catalog_form_enable'];
		
		echo "<!-- WPAT DEBUG: wa_enabled: " . ($wa_enabled ? 'yes' : 'no') . ", form_enabled: " . ($form_enabled ? 'yes' : 'no') . " -->\n";

		if ( ! $wa_enabled && ! $form_enabled ) {
			echo "<!-- WPAT DEBUG: returned no channels active -->\n";
			return;
		}

		$this->actions_rendered = true;

		$title    = $product->get_name();
		$url      = get_permalink( $product->get_id() );
		echo "<!-- WPAT DEBUG: rendering actions for product: " . esc_html($title) . " -->\n";

		// 1. Mostrar Botón de WhatsApp
		if ( isset( $settings['woo_catalog_wa_enable'] ) && '1' === $settings['woo_catalog_wa_enable'] && ! empty( $settings['woo_catalog_wa_phone'] ) ) {
			$raw_message = ! empty( $settings['woo_catalog_wa_message'] ) ? $settings['woo_catalog_wa_message'] : 'Estoy interesado en el producto {product_title} ({product_url}). ¿Cómo podría comprarlo?';
			
			$message = str_replace(
				array( '{product_title}', '{product_url}' ),
				array( $title, $url ),
				$raw_message
			);

			$clean_phone = preg_replace( '/[^0-9]/', '', $settings['woo_catalog_wa_phone'] );
			$wa_url      = 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode( $message );
			?>
			<div class="wpat-catalog-wa-wrapper" style="margin: 20px 0;">
				<a href="<?php echo esc_url( $wa_url ); ?>" class="button wpat-wa-contact-btn" target="_blank" style="background-color: #25D366; color: #ffffff; display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 12px 24px; border-radius: 6px; font-weight: 700; text-decoration: none; border: none; font-size: 15px; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.25); transition: background-color 0.2s ease;">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="vertical-align: middle;"><path d="M12.012 2c-5.506 0-9.988 4.482-9.988 9.988 0 1.761.458 3.486 1.332 5.008L2 22l5.163-1.355c1.467.8 3.111 1.221 4.793 1.223h.004c5.505 0 9.986-4.482 9.986-9.988C21.996 6.482 17.514 2 12.012 2zm0 1.71c4.561 0 8.277 3.716 8.277 8.278 0 4.562-3.716 8.278-8.277 8.278-1.528-.001-3.025-.425-4.329-1.227l-.31-.184-3.218.844.859-3.135-.202-.322c-.88-1.402-1.345-3.036-1.345-4.717 0-4.562 3.716-8.278 8.277-8.278zm-1.897 2.457c-.244 0-.448.083-.65.289-.202.206-.774.757-.774 1.844s.79 2.132.9 2.28c.11.148 1.523 2.453 3.754 3.327.53.208.944.333 1.266.435.533.17.102.146.702.057.6-.089 1.844-.754 2.102-1.482.257-.728.257-1.353.18-1.482-.078-.129-.285-.206-.6-.364-.315-.158-1.844-.91-2.132-1.013-.289-.103-.499-.155-.707.155-.208.31-.796 1.013-.977 1.218-.18.206-.362.232-.677.074-.315-.158-1.332-.491-2.538-1.567-.938-.837-1.572-1.871-1.756-2.187-.184-.315-.02-.486.138-.642.142-.14.315-.367.473-.55.158-.184.21-.315.315-.526.105-.21.053-.394-.026-.55-.079-.158-.707-1.706-.977-2.35-.262-.628-.528-.544-.707-.544z"/></svg>
					Contactar por WhatsApp
				</a>
			</div>
			<?php
		}

		// 2. Mostrar Formulario de Contacto
		if ( isset( $settings['woo_catalog_form_enable'] ) && '1' === $settings['woo_catalog_form_enable'] ) {
			?>
			<div class="wpat-catalog-contact-form-wrapper" style="background:#f8fafc; border:1px solid #e2e8f0; padding:20px; border-radius:8px; margin: 25px 0; max-width:480px; box-sizing: border-box;">
				<h4 style="margin-top:0; font-size:15px; font-weight:700; color:#0f172a; margin-bottom:12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">Enviar Consulta del Producto</h4>
				
				<form class="wpat-catalog-contact-form" id="wpat_catalog_contact_form">
					<div style="display:none !important;">
						<input type="text" name="wpat_catalog_hp" value="" />
					</div>
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>" />
					
					<div style="margin-bottom:10px;">
						<label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#475569;">Tu Nombre</label>
						<input type="text" name="contact_name" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#ffffff; font-size:14px; box-sizing: border-box;" />
					</div>
					
					<div style="margin-bottom:10px;">
						<label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#475569;">Asunto</label>
						<input type="text" name="contact_subject" value="Consulta: <?php echo esc_attr( $title ); ?>" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#ffffff; font-size:14px; box-sizing: border-box;" />
					</div>

					<div style="margin-bottom:10px;">
						<label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#475569;">Enlace del producto</label>
						<input type="text" name="product_url" value="<?php echo esc_url( $url ); ?>" readonly style="width:100%; padding:8px 12px; border:1px solid #e2e8f0; border-radius:6px; background:#f1f5f9; font-size:13px; color:#64748b; box-sizing: border-box;" />
					</div>

					<div style="margin-bottom:15px;">
						<label style="display:block; font-size:12px; font-weight:600; margin-bottom:4px; color:#475569;">Mensaje</label>
						<textarea name="contact_message" rows="4" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; background:#ffffff; font-size:14px; box-sizing: border-box;" placeholder="Escribe aquí tu consulta..."></textarea>
					</div>

					<button type="submit" class="button wpat-form-submit-btn" style="background:#9333ea; color:#ffffff; padding:10px 20px; font-weight:700; border-radius:6px; width:100%; border:none; cursor:pointer; font-size:14px; display:inline-flex; align-items:center; justify-content:center; gap:8px; box-shadow: 0 4px 6px -1px rgba(147, 51, 234, 0.2);">
						Enviar consulta
					</button>
					<div class="wpat-catalog-form-response" style="margin-top:12px; font-size:13px; font-weight:600; display:none; padding: 10px; border-radius: 6px;"></div>
				</form>
			</div>
			<?php
		}
	}

	/**
	 * Carga el JS del formulario de contacto en el footer en las páginas de producto.
	 */
	public function enqueue_frontend_scripts() {
		if ( ! is_product() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! isset( $settings['woo_catalog_form_enable'] ) || '1' !== $settings['woo_catalog_form_enable'] ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#wpat_catalog_contact_form').on('submit', function(e) {
				e.preventDefault();
				var $form = $(this);
				var $btn = $form.find('.wpat-form-submit-btn');
				var $response = $form.find('.wpat-catalog-form-response');
				var originalBtnText = $btn.text();

				$btn.prop('disabled', true).text('Enviando...');
				$response.hide().removeClass('success error').css('background', 'none').css('color', 'inherit');

				$.ajax({
					url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
					type: 'POST',
					data: $form.serialize() + '&action=wpat_catalog_contact_form',
					success: function(response) {
						$btn.prop('disabled', false).text(originalBtnText);
						if (response.success) {
							$response.css('background', '#e6f4ea').css('color', '#137333').text(response.data.message).fadeIn(200);
							$form[0].reset();
						} else {
							$response.css('background', '#fce8e6').css('color', '#c5221f').text(response.data.message).fadeIn(200);
						}
					},
					error: function() {
						$btn.prop('disabled', false).text(originalBtnText);
						$response.css('background', '#fce8e6').css('color', '#c5221f').text('Error de conexión al enviar el formulario.').fadeIn(200);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Inyecta el CSS necesario para ocultar el botón nativo de añadir al carrito y las cantidades.
	 */
	public function inject_frontend_css() {
		if ( is_admin() || ! is_product() ) {
			return;
		}
		?>
		<style type="text/css">
			/* Ocultar botones nativos de compra y inputs de cantidad de WooCommerce */
			.single-product .quantity,
			.single-product .single_add_to_cart_button,
			.single-product .woocommerce-variation-add-to-cart,
			.single-product form.cart .single_add_to_cart_button,
			.single-product form.cart .quantity,
			.archive .add_to_cart_button,
			.archive .ajax_add_to_cart,
			.product .add_to_cart_button,
			.product .ajax_add_to_cart {
				display: none !important;
			}
		</style>
		<?php
	}

	/**
	 * AJAX: Procesa y envía el correo electrónico del formulario de contacto de producto.
	 */
	public function handle_contact_form_submit() {
		// Verificar anti-spam Honeypot (evita fallos de nonces caducados por caché)
		if ( ! empty( $_POST['wpat_catalog_hp'] ) ) {
			wp_send_json_success( array( 'message' => '¡Tu consulta ha sido enviada con éxito! Nos pondremos en contacto contigo lo antes posible.' ) );
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Campos sanitizados
		$name     = isset( $_POST['contact_name'] ) ? sanitize_text_field( $_POST['contact_name'] ) : '';
		$subject  = isset( $_POST['contact_subject'] ) ? sanitize_text_field( $_POST['contact_subject'] ) : '';
		$url      = isset( $_POST['product_url'] ) ? esc_url_raw( $_POST['product_url'] ) : '';
		$message  = isset( $_POST['contact_message'] ) ? sanitize_textarea_field( $_POST['contact_message'] ) : '';

		if ( empty( $name ) || empty( $subject ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Por favor, rellena todos los campos requeridos.' ) );
		}

		// Email destinatario
		$to = ! empty( $settings['woo_catalog_form_email'] ) ? sanitize_email( $settings['woo_catalog_form_email'] ) : get_option( 'admin_email' );

		// Cuerpo del mensaje
		$body  = "<h2>Nueva consulta de producto (Modo Catálogo)</h2>";
		$body .= "<p><strong>Cliente:</strong> " . esc_html( $name ) . "</p>";
		$body .= "<p><strong>Producto:</strong> <a href='" . esc_url( $url ) . "'>" . esc_url( $url ) . "</a></p>";
		$body .= "<p><strong>Consulta:</strong></p>";
		$body .= "<p style='background:#f1f5f9; padding: 15px; border-radius:6px; border:1px solid #cbd5e1; white-space: pre-wrap;'>" . esc_html( $message ) . "</p>";

		// Cabeceras HTML
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// Intentar enviar email
		$mail = wp_mail( $to, $subject, $body, $headers );

		if ( $mail ) {
			wp_send_json_success( array( 'message' => '¡Tu consulta ha sido enviada con éxito! Nos pondremos en contacto contigo lo antes posible.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'No se pudo enviar la consulta por un error del servidor de correo. Por favor, vuelve a intentarlo.' ) );
		}
	}

	/**
	 * Muestra un bloque de depuración HTML en el frontend para diagnosticar la configuración.
	 */
	public function wpat_catalog_debug() {
		if ( is_admin() ) {
			return;
		}
		$settings = WPAT_Main::get_instance()->get_settings();
		echo "\n<!-- WPAT CATALOG DEBUG START\n";
		echo "woo-catalog active: " . (isset($settings['woo-catalog']) ? $settings['woo-catalog'] : 'not set') . "\n";
		echo "woo_catalog_hide_cart: " . (isset($settings['woo_catalog_hide_cart']) ? $settings['woo_catalog_hide_cart'] : 'not set') . "\n";
		echo "woo_catalog_wa_enable: " . (isset($settings['woo_catalog_wa_enable']) ? $settings['woo_catalog_wa_enable'] : 'not set') . "\n";
		echo "woo_catalog_wa_phone: " . (isset($settings['woo_catalog_wa_phone']) ? $settings['woo_catalog_wa_phone'] : 'not set') . "\n";
		echo "woo_catalog_form_enable: " . (isset($settings['woo_catalog_form_enable']) ? $settings['woo_catalog_form_enable'] : 'not set') . "\n";
		echo "is_product: " . (is_product() ? 'yes' : 'no') . "\n";
		echo "WPAT CATALOG DEBUG END -->\n";
	}
}

// Inicializar el módulo si está activo
$settings = WPAT_Main::get_instance()->get_settings();
if ( isset( $settings['woo-catalog'] ) && '1' === $settings['woo-catalog'] ) {
	WPAT_Woo_Catalog::get_instance();
}

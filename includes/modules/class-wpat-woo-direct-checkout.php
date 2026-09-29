<?php
/**
 * Módulo: Compra Directa & Saltar Carrito (Direct Checkout) - WP Agency Toolkit
 *
 * Permite agilizar las compras en WooCommerce saltándose el carrito y redirigiendo
 * inmediatamente a la página de finalizar compra (/checkout), con soporte para
 * sustitución de botón, botón dual "Comprar Ahora", vaciado previo de carrito y filtros.
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Direct_Checkout {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Woo_Direct_Checkout|null
	 */
	private static $instance = null;

	/**
	 * Ajustes activos del plugin.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Direct_Checkout
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado.
	 */
	private function __construct() {
		$this->settings = WPAT_Main::get_instance()->get_settings();

		// Verificar que WooCommerce esté activo
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// 1. Redirección de la página del Carrito (/cart) al Checkout si está habilitado
		if ( ! empty( $this->settings['woo_dc_redirect_cart_page'] ) && '1' === (string) $this->settings['woo_dc_redirect_cart_page'] ) {
			add_action( 'template_redirect', array( $this, 'redirect_cart_page_to_checkout' ) );
		}

		// 2. Redirección al añadir al carrito
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'filter_add_to_cart_redirect' ), 99, 2 );

		// 3. Vaciado del carrito antes de añadir si está activado
		if ( ! empty( $this->settings['woo_dc_empty_cart'] ) && '1' === (string) $this->settings['woo_dc_empty_cart'] ) {
			add_action( 'woocommerce_add_to_cart', array( $this, 'handle_empty_cart_before_add' ), 10, 6 );
		}

		// 4. Modo de funcionamiento: Reemplazar botón vs Botón dual
		$mode = isset( $this->settings['woo_dc_mode'] ) ? $this->settings['woo_dc_mode'] : 'replace';

		if ( 'replace' === $mode ) {
			// Cambiar texto del botón añadir al carrito en single
			add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'custom_single_button_text' ), 20, 2 );

			// Cambiar texto del botón en archivos/tienda si está habilitado
			if ( ! empty( $this->settings['woo_dc_archives'] ) && '1' === (string) $this->settings['woo_dc_archives'] ) {
				add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'custom_archive_button_text' ), 20, 2 );
			}
		} elseif ( 'dual_button' === $mode ) {
			// Inyectar segundo botón "Comprar Ahora" en la ficha de producto
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_dual_buy_now_button' ), 15 );
		}

		// 5. Soporte para archivos/tienda con redirección por JS (AJAX add to cart)
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Comprueba si el producto actual cumple las reglas de inclusión/exclusión del módulo.
	 *
	 * @param int|WC_Product|null $product_or_id Producto o ID.
	 * @return bool True si debe aplicarse compra directa.
	 */
	public function is_product_eligible( $product_or_id = null ) {
		$product = null;
		if ( is_numeric( $product_or_id ) ) {
			$product = wc_get_product( $product_or_id );
		} elseif ( $product_or_id instanceof WC_Product ) {
			$product = $product_or_id;
		} else {
			global $product;
		}

		if ( ! $product ) {
			return true;
		}

		$applies_to = isset( $this->settings['woo_dc_applies_to'] ) ? $this->settings['woo_dc_applies_to'] : 'all';

		if ( 'all' === $applies_to ) {
			return true;
		}

		$product_id = $product->get_id();
		$categories = (array) wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

		if ( 'include_products' === $applies_to ) {
			$included = isset( $this->settings['woo_dc_included_products'] ) && is_array( $this->settings['woo_dc_included_products'] ) ? array_map( 'intval', $this->settings['woo_dc_included_products'] ) : array();
			return in_array( $product_id, $included, true );
		}

		if ( 'exclude_products' === $applies_to ) {
			$excluded = isset( $this->settings['woo_dc_excluded_products'] ) && is_array( $this->settings['woo_dc_excluded_products'] ) ? array_map( 'intval', $this->settings['woo_dc_excluded_products'] ) : array();
			return ! in_array( $product_id, $excluded, true );
		}

		if ( 'include_categories' === $applies_to ) {
			$included_cats = isset( $this->settings['woo_dc_included_categories'] ) && is_array( $this->settings['woo_dc_included_categories'] ) ? array_map( 'intval', $this->settings['woo_dc_included_categories'] ) : array();
			foreach ( $categories as $c_id ) {
				if ( in_array( (int) $c_id, $included_cats, true ) ) {
					return true;
				}
			}
			return false;
		}

		if ( 'exclude_categories' === $applies_to ) {
			$excluded_cats = isset( $this->settings['woo_dc_excluded_categories'] ) && is_array( $this->settings['woo_dc_excluded_categories'] ) ? array_map( 'intval', $this->settings['woo_dc_excluded_categories'] ) : array();
			foreach ( $categories as $c_id ) {
				if ( in_array( (int) $c_id, $excluded_cats, true ) ) {
					return false;
				}
			}
			return true;
		}

		return true;
	}

	/**
	 * Redirige la página de carrito directamente al checkout.
	 */
	public function redirect_cart_page_to_checkout() {
		if ( function_exists( 'is_cart' ) && is_cart() && ! is_checkout() ) {
			if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
		}
	}

	/**
	 * Filtra la URL de redirección tras añadir un producto al carrito.
	 *
	 * @param string $url URL destino por defecto.
	 * @param WC_Product|null $product Producto añadido.
	 * @return string URL del checkout o la original.
	 */
	public function filter_add_to_cart_redirect( $url, $product = null ) {
		$mode = isset( $this->settings['woo_dc_mode'] ) ? $this->settings['woo_dc_mode'] : 'replace';

		// Si se pulsó el botón específico "Comprar Ahora" del modo dual
		if ( isset( $_REQUEST['wpat_buy_now'] ) && '1' === (string) $_REQUEST['wpat_buy_now'] ) {
			if ( $this->is_product_eligible( $product ) ) {
				return wc_get_checkout_url();
			}
		}

		// Si el modo es 'replace' o 'redirect_only'
		if ( in_array( $mode, array( 'replace', 'redirect_only' ), true ) ) {
			// Comprobar si se añadió desde archivos y si está activado
			if ( ( ! is_singular( 'product' ) && empty( $this->settings['woo_dc_archives'] ) ) ) {
				return $url;
			}

			if ( $this->is_product_eligible( $product ) ) {
				return wc_get_checkout_url();
			}
		}

		return $url;
	}

	/**
	 * Vacía los productos previos del carrito si la opción está activa y el producto es elegible.
	 *
	 * @param string $cart_item_key Clave del ítem añadido.
	 * @param int $product_id ID del producto.
	 * @param int $quantity Cantidad.
	 * @param int $variation_id ID de variación.
	 * @param array $variation Atributos.
	 * @param array $cart_item_data Datos extra.
	 */
	public function handle_empty_cart_before_add( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$mode = isset( $this->settings['woo_dc_mode'] ) ? $this->settings['woo_dc_mode'] : 'replace';

		$should_empty = false;
		if ( 'replace' === $mode || 'redirect_only' === $mode ) {
			$should_empty = $this->is_product_eligible( $product_id );
		} elseif ( 'dual_button' === $mode && isset( $_REQUEST['wpat_buy_now'] ) && '1' === (string) $_REQUEST['wpat_buy_now'] ) {
			$should_empty = $this->is_product_eligible( $product_id );
		}

		if ( $should_empty && function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $key => $item ) {
				if ( $key !== $cart_item_key ) {
					WC()->cart->remove_cart_item( $key );
				}
			}
		}
	}

	/**
	 * Texto personalizado del botón en la ficha individual de producto.
	 *
	 * @param string $text Texto original.
	 * @param WC_Product $product Objeto producto.
	 * @return string Texto personalizado.
	 */
	public function custom_single_button_text( $text, $product ) {
		if ( $this->is_product_eligible( $product ) ) {
			$custom_text = ! empty( $this->settings['woo_dc_button_text'] ) ? $this->settings['woo_dc_button_text'] : __( 'Comprar Ahora', 'wp-agency-toolkit' );
			return esc_html( $custom_text );
		}
		return $text;
	}

	/**
	 * Texto personalizado del botón en las vistas de tienda y catálogo.
	 *
	 * @param string $text Texto original.
	 * @param WC_Product $product Objeto producto.
	 * @return string Texto personalizado.
	 */
	public function custom_archive_button_text( $text, $product ) {
		if ( $product && $product->is_type( 'simple' ) && $this->is_product_eligible( $product ) ) {
			$custom_text = ! empty( $this->settings['woo_dc_archive_button_text'] ) ? $this->settings['woo_dc_archive_button_text'] : __( 'Comprar Ya', 'wp-agency-toolkit' );
			return esc_html( $custom_text );
		}
		return $text;
	}

	/**
	 * Renderiza el botón secundario "Comprar Ahora" en el modo Dual Button.
	 */
	public function render_dual_buy_now_button() {
		global $product;
		if ( ! $product || ! $this->is_product_eligible( $product ) ) {
			return;
		}

		$btn_text  = ! empty( $this->settings['woo_dc_dual_button_text'] ) ? $this->settings['woo_dc_dual_button_text'] : __( '⚡ Comprar Ahora', 'wp-agency-toolkit' );
		$btn_bg    = ! empty( $this->settings['woo_dc_btn_bg'] ) ? $this->settings['woo_dc_btn_bg'] : '#059669';
		$btn_color = ! empty( $this->settings['woo_dc_btn_color'] ) ? $this->settings['woo_dc_btn_color'] : '#ffffff';
		?>
		<button type="submit" name="wpat_buy_now" value="1" class="button alt wpat-direct-buy-now-btn" style="background-color: <?php echo esc_attr( $btn_bg ); ?> !important; color: <?php echo esc_attr( $btn_color ); ?> !important; border-color: <?php echo esc_attr( $btn_bg ); ?> !important; margin-left: 8px; font-weight: 700; transition: all 0.2s ease;">
			<?php echo esc_html( $btn_text ); ?>
		</button>
		<?php
	}

	/**
	 * Encola scripts y estilos mínimos para frontend.
	 */
	public function enqueue_frontend_assets() {
		$redirect_archives = ! empty( $this->settings['woo_dc_archives'] ) && '1' === (string) $this->settings['woo_dc_archives'];
		$mode              = isset( $this->settings['woo_dc_mode'] ) ? $this->settings['woo_dc_mode'] : 'replace';

		// Inyectar CSS y script ligero
		$inline_css = '
			.wpat-direct-buy-now-btn:hover {
				opacity: 0.92;
				transform: translateY(-1px);
			}
			.woocommerce div.product form.cart .wpat-direct-buy-now-btn {
				vertical-align: middle;
			}
		';
		wp_register_style( 'wpat-direct-checkout-css', false );
		wp_enqueue_style( 'wpat-direct-checkout-css' );
		wp_add_inline_style( 'wpat-direct-checkout-css', $inline_css );

		if ( $redirect_archives && in_array( $mode, array( 'replace', 'redirect_only' ), true ) ) {
			$checkout_url = wc_get_checkout_url();
			$inline_js = "
				(function($){
					$(document).on('click', '.ajax_add_to_cart', function() {
						$(this).data('wpat-direct', '1');
					});
					$(document.body).on('added_to_cart', function(e, fragments, cart_hash, \$button) {
						if (\$button && \$button.data('wpat-direct') === '1') {
							window.location.href = " . wp_json_encode( $checkout_url ) . ";
						}
					});
				})(jQuery);
			";
			wp_add_inline_script( 'woocommerce', $inline_js );
		}
	}
}

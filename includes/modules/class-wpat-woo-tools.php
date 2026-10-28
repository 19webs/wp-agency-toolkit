<?php
/**
 * Módulo: Herramientas y Ajustes para WooCommerce - WP Agency Toolkit
 *
 * Proporciona ajustes rápidos, utilidades y optimizaciones ligeras para tiendas WooCommerce:
 * - Borrado automático de imágenes y galerías de productos eliminados.
 * - Acceso directo y redirección a ficha de producto mediante SKU (/sku/{sku}/ o ?sku=XYZ).
 * - Importe mínimo de pedido obligatorio en carrito y finalizar compra.
 * - Ocultar precios y botón de compra para usuarios no registrados.
 * - Actualización automática del carrito por AJAX al modificar cantidades.
 * - Ocultar métodos de envío de pago cuando el Envío Gratuito esté disponible.
 * - Botón "Vaciar Carrito" con 1 clic en la página del carrito.
 * - Ocultar el campo "Notas del pedido" en la pantalla de pago.
 * - Desactivar enlaces a productos en carrito y checkout para evitar fugas.
 * - Abrir productos del catálogo en una pestaña nueva (target="_blank").
 * - Ocultar selector de ordenación o contador de resultados de la tienda.
 * - Venta individualizada global (límite de 1 unidad por producto).
 * - Mostrar foto miniatura y SKU en las líneas de pedido de la administración.
 * - Redirecciones personalizadas tras Iniciar y Cerrar Sesión.
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Tools {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Woo_Tools|null
	 */
	private static $instance = null;

	/**
	 * Ajustes del plugin.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Tools
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

		/* =========================================================================
		   1. GESTIÓN DE MEDIOS & ENLACES
		   ========================================================================= */

		// 1.1 Borrar imágenes de producto al eliminar definitivamente el producto
		if ( ! empty( $this->settings['woo_tools_delete_images'] ) && '1' === (string) $this->settings['woo_tools_delete_images'] ) {
			add_action( 'before_delete_post', array( $this, 'delete_product_attachments' ), 10, 2 );
		}

		// 1.2 Acceso y redirección a producto por SKU
		if ( ! empty( $this->settings['woo_tools_sku_url'] ) && '1' === (string) $this->settings['woo_tools_sku_url'] ) {
			add_action( 'init', array( $this, 'register_sku_rewrites' ) );
			add_filter( 'query_vars', array( $this, 'register_sku_query_vars' ) );
			add_action( 'template_redirect', array( $this, 'handle_sku_redirection' ) );
		}

		/* =========================================================================
		   2. CARRITO Y CHECKOUT
		   ========================================================================= */

		// 2.1 Pedido mínimo obligatorio
		if ( ! empty( $this->settings['woo_tools_min_order_enabled'] ) && '1' === (string) $this->settings['woo_tools_min_order_enabled'] ) {
			add_action( 'woocommerce_check_cart_items', array( $this, 'validate_minimum_order' ) );
			add_action( 'woocommerce_checkout_process', array( $this, 'validate_minimum_order' ) );
		}

		// 2.2 Actualización automática del carrito por AJAX al cambiar cantidad
		if ( ! empty( $this->settings['woo_tools_auto_update_cart'] ) && '1' === (string) $this->settings['woo_tools_auto_update_cart'] ) {
			add_action( 'wp_footer', array( $this, 'render_auto_update_cart_script' ) );
		}

		// 2.3 Ocultar métodos de envío de pago cuando el Envío Gratuito esté disponible
		if ( ! empty( $this->settings['woo_tools_hide_shipping_when_free'] ) && '1' === (string) $this->settings['woo_tools_hide_shipping_when_free'] ) {
			add_filter( 'woocommerce_package_rates', array( $this, 'hide_shipping_when_free_is_available' ), 100, 2 );
		}

		// 2.4 Botón Vaciar Carrito en la página /cart
		if ( ! empty( $this->settings['woo_tools_empty_cart_btn'] ) && '1' === (string) $this->settings['woo_tools_empty_cart_btn'] ) {
			add_action( 'woocommerce_cart_actions', array( $this, 'render_empty_cart_button' ), 20 );
			add_action( 'init', array( $this, 'handle_empty_cart_action' ) );
		}

		// 2.5 Ocultar campo Notas del Pedido en Checkout
		if ( ! empty( $this->settings['woo_tools_hide_order_notes'] ) && '1' === (string) $this->settings['woo_tools_hide_order_notes'] ) {
			add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
			add_filter( 'woocommerce_checkout_fields', array( $this, 'remove_order_notes_field' ) );
		}

		// 2.6 Desactivar enlaces a productos en Carrito y Checkout
		if ( ! empty( $this->settings['woo_tools_disable_cart_links'] ) && '1' === (string) $this->settings['woo_tools_disable_cart_links'] ) {
			add_filter( 'woocommerce_cart_item_name', array( $this, 'disable_cart_item_link' ), 99, 3 );
			add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'disable_cart_item_thumbnail_link' ), 99, 3 );
		}

		/* =========================================================================
		   3. CATÁLOGO Y PRODUCTOS
		   ========================================================================= */

		// 3.1 Precios exclusivos para usuarios registrados
		if ( ! empty( $this->settings['woo_tools_login_for_prices_enabled'] ) && '1' === (string) $this->settings['woo_tools_login_for_prices_enabled'] ) {
			if ( ! is_user_logged_in() && ! is_admin() ) {
				add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html_for_guests' ), 999, 2 );
				add_filter( 'woocommerce_is_purchasable', array( $this, 'filter_is_purchasable_for_guests' ), 999, 2 );
				add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'filter_loop_add_to_cart_link_for_guests' ), 999, 3 );
				add_action( 'wp_head', array( $this, 'render_login_for_prices_styles' ) );
			}
		}

		// 3.2 Abrir enlaces de producto en pestaña nueva (target="_blank")
		if ( ! empty( $this->settings['woo_tools_open_products_new_tab'] ) && '1' === (string) $this->settings['woo_tools_open_products_new_tab'] ) {
			add_action( 'wp_footer', array( $this, 'render_open_products_new_tab_script' ) );
		}

		// 3.3 Ocultar selector de ordenación del catálogo
		if ( ! empty( $this->settings['woo_tools_hide_shop_ordering'] ) && '1' === (string) $this->settings['woo_tools_hide_shop_ordering'] ) {
			add_action( 'init', array( $this, 'remove_shop_ordering' ) );
		}

		// 3.4 Ocultar contador de resultados del catálogo
		if ( ! empty( $this->settings['woo_tools_hide_shop_results_count'] ) && '1' === (string) $this->settings['woo_tools_hide_shop_results_count'] ) {
			add_action( 'init', array( $this, 'remove_shop_results_count' ) );
		}

		// 3.5 Venta individualizada global (Límite 1 unidad por producto)
		if ( ! empty( $this->settings['woo_tools_sold_individually'] ) && '1' === (string) $this->settings['woo_tools_sold_individually'] ) {
			add_filter( 'woocommerce_is_sold_individually', '__return_true' );
		}

		/* =========================================================================
		   4. ADMINISTRACIÓN Y CUENTAS
		   ========================================================================= */

		// 4.1 Mostrar miniatura y SKU en el detalle del pedido en el panel de administración
		if ( ! empty( $this->settings['woo_tools_admin_order_sku_thumb'] ) && '1' === (string) $this->settings['woo_tools_admin_order_sku_thumb'] ) {
			if ( is_admin() ) {
				add_action( 'woocommerce_before_order_itemmeta', array( $this, 'render_admin_order_item_thumb_and_sku' ), 10, 3 );
			}
		}

		// 4.2 Redirección tras Login
		if ( ! empty( $this->settings['woo_tools_login_redirect_url'] ) ) {
			add_filter( 'woocommerce_login_redirect', array( $this, 'custom_login_redirect' ), 99, 2 );
		}

		// 4.3 Redirección tras Logout
		if ( ! empty( $this->settings['woo_tools_logout_redirect_url'] ) ) {
			add_filter( 'woocommerce_logout_default_redirect_url', array( $this, 'custom_logout_redirect' ), 99 );
		}
	}

	/* =========================================================================
	   1. BORRADO DE IMÁGENES AL ELIMINAR PRODUCTOS
	   ========================================================================= */

	/**
	 * Elimina las imágenes destacadas y de galería asociadas al producto que no estén en uso.
	 *
	 * @param int     $post_id ID del post que se va a eliminar.
	 * @param WP_Post $post    Objeto del post.
	 */
	public function delete_product_attachments( $post_id, $post = null ) {
		if ( ! $post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$attachment_ids = array();

		// Imagen destacada principal
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$attachment_ids[] = (int) $thumb_id;
		}

		// Galería de imágenes del producto
		$gallery_meta = get_post_meta( $post_id, '_product_image_gallery', true );
		if ( ! empty( $gallery_meta ) ) {
			$gallery_ids = array_filter( array_map( 'intval', explode( ',', $gallery_meta ) ) );
			$attachment_ids = array_merge( $attachment_ids, $gallery_ids );
		}

		// Variaciones del producto si es variable
		$variations = get_posts( array(
			'post_parent'    => $post_id,
			'post_type'      => 'product_variation',
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
			'suppress_filters' => true,
		) );

		$excluded_post_ids = array_merge( array( $post_id ), $variations );

		if ( ! empty( $variations ) ) {
			foreach ( $variations as $var_id ) {
				$var_thumb_id = get_post_thumbnail_id( $var_id );
				if ( $var_thumb_id ) {
					$attachment_ids[] = (int) $var_thumb_id;
				}
			}
		}

		$attachment_ids = array_unique( array_filter( $attachment_ids ) );

		if ( empty( $attachment_ids ) ) {
			return;
		}

		// Eliminar cada adjunto sólo si no está en uso por otro post/producto
		foreach ( $attachment_ids as $attachment_id ) {
			if ( ! $this->is_attachment_used_elsewhere( $attachment_id, $excluded_post_ids ) ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}
	}

	/**
	 * Comprueba si una imagen adjunta está en uso en otra entrada, producto, página o ajuste del sitio.
	 *
	 * @param int   $attachment_id ID del adjunto.
	 * @param array $excluded_ids  IDs de publicaciones que se están eliminando.
	 * @return bool True si está en uso en otra parte del sitio.
	 */
	private function is_attachment_used_elsewhere( $attachment_id, $excluded_ids = array() ) {
		// 1. Logo del tema o icono del sitio
		$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
		$site_icon_id   = (int) get_option( 'site_icon' );

		if ( $attachment_id === $custom_logo_id || $attachment_id === $site_icon_id ) {
			return true;
		}

		// 2. Usada como imagen destacada (_thumbnail_id) en otros posts
		$used_as_thumb = get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'exclude'        => $excluded_ids,
			'meta_query'     => array(
				array(
					'key'   => '_thumbnail_id',
					'value' => $attachment_id,
				),
			),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'suppress_filters' => true,
		) );

		if ( ! empty( $used_as_thumb ) ) {
			return true;
		}

		// 3. Usada en galerías de otros productos (_product_image_gallery)
		global $wpdb;
		$excluded_placeholders = implode( ',', array_fill( 0, count( $excluded_ids ), '%d' ) );
		$query = "
			SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = '_product_image_gallery'
			AND (
				meta_value = %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
				OR meta_value LIKE %s
			)
		";
		$params = array(
			(string) $attachment_id,
			$attachment_id . ',%',
			'%,' . $attachment_id,
			'%,' . $attachment_id . ',%'
		);

		if ( ! empty( $excluded_ids ) ) {
			$query .= " AND post_id NOT IN ($excluded_placeholders)";
			$params = array_merge( $params, $excluded_ids );
		}

		$query .= " LIMIT 1";

		$used_in_gallery = $wpdb->get_var( $wpdb->prepare( $query, $params ) );

		if ( ! empty( $used_in_gallery ) ) {
			return true;
		}

		return false;
	}

	/* =========================================================================
	   2. ACCESO Y REDIRECCIÓN POR SKU
	   ========================================================================= */

	/**
	 * Registra reglas de reescritura para URLs por SKU.
	 */
	public function register_sku_rewrites() {
		add_rewrite_rule( '^sku/([^/]+)/?$', 'index.php?wpat_sku=$matches[1]', 'top' );
		add_rewrite_rule( '^p/([^/]+)/?$', 'index.php?wpat_sku=$matches[1]', 'top' );
	}

	/**
	 * Añade el parámetro wpat_sku a las query vars reconocidas por WordPress.
	 *
	 * @param array $vars Query vars existentes.
	 * @return array Query vars ampliadas.
	 */
	public function register_sku_query_vars( $vars ) {
		$vars[] = 'wpat_sku';
		return $vars;
	}

	/**
	 * Captura peticiones con SKU y redirige al enlace canónico del producto.
	 */
	public function handle_sku_redirection() {
		$sku = get_query_var( 'wpat_sku' );

		if ( empty( $sku ) && isset( $_GET['sku'] ) ) {
			$sku = sanitize_text_field( wp_unslash( $_GET['sku'] ) );
		}

		if ( empty( $sku ) ) {
			return;
		}

		$product_id = wc_get_product_id_by_sku( $sku );

		if ( $product_id ) {
			$permalink = get_permalink( $product_id );
			if ( $permalink ) {
				wp_safe_redirect( $permalink, 301 );
				exit;
			}
		}
	}

	/* =========================================================================
	   3. PEDIDO MÍNIMO OBLIGATORIO
	   ========================================================================= */

	/**
	 * Valida que el pedido supere el importe mínimo en el carrito y en el checkout.
	 */
	public function validate_minimum_order() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$min_amount = isset( $this->settings['woo_tools_min_order_amount'] ) ? (float) $this->settings['woo_tools_min_order_amount'] : 0;
		if ( $min_amount <= 0 ) {
			return;
		}

		$calc_type = isset( $this->settings['woo_tools_min_order_type'] ) ? $this->settings['woo_tools_min_order_type'] : 'subtotal';
		$current_amount = ( 'total' === $calc_type ) ? (float) WC()->cart->get_total( 'edit' ) : (float) WC()->cart->get_subtotal();

		if ( $current_amount < $min_amount ) {
			$default_msg = __( 'El importe mínimo para realizar un pedido es de {min_order}. Tu pedido actual suma {current_order}.', 'wp-agency-toolkit' );
			$custom_msg  = ! empty( $this->settings['woo_tools_min_order_msg'] ) ? $this->settings['woo_tools_min_order_msg'] : $default_msg;

			$formatted_msg = str_replace(
				array( '{min_order}', '{current_order}' ),
				array( wc_price( $min_amount ), wc_price( $current_amount ) ),
				$custom_msg
			);

			if ( ! wc_has_notice( $formatted_msg, 'error' ) ) {
				wc_add_notice( $formatted_msg, 'error' );
			}
		}
	}

	/* =========================================================================
	   4. SOLO USUARIOS REGISTRADOS PUEDEN VER PRECIOS
	   ========================================================================= */

	/**
	 * Sustituye el precio del producto por una invitación a iniciar sesión.
	 *
	 * @param string     $price   HTML del precio nativo.
	 * @param WC_Product $product Objeto del producto.
	 * @return string HTML modificado.
	 */
	public function filter_price_html_for_guests( $price, $product ) {
		$login_text = ! empty( $this->settings['woo_tools_login_for_prices_text'] ) ? $this->settings['woo_tools_login_for_prices_text'] : __( 'Inicia sesión para ver precios', 'wp-agency-toolkit' );
		$target_url = $this->get_login_target_url();

		return '<span class="wpat-guest-price-notice"><a href="' . esc_url( $target_url ) . '" class="wpat-login-link-price">' . esc_html( $login_text ) . '</a></span>';
	}

	/**
	 * Deshabilita la opción de compra para usuarios no registrados.
	 *
	 * @param bool       $purchasable Estado de compra.
	 * @param WC_Product $product     Objeto del producto.
	 * @return bool False si no está conectado.
	 */
	public function filter_is_purchasable_for_guests( $purchasable, $product ) {
		return false;
	}

	/**
	 * Sustituye el botón de añadir al carrito en el bucle/catálogo por un botón de acceso.
	 *
	 * @param string     $html    HTML del botón.
	 * @param WC_Product $product Objeto del producto.
	 * @param array      $args    Argumentos del botón.
	 * @return string HTML de botón de login.
	 */
	public function filter_loop_add_to_cart_link_for_guests( $html, $product, $args ) {
		$login_text = ! empty( $this->settings['woo_tools_login_for_prices_text'] ) ? $this->settings['woo_tools_login_for_prices_text'] : __( 'Inicia sesión para comprar', 'wp-agency-toolkit' );
		$target_url = $this->get_login_target_url();

		return sprintf(
			'<a href="%s" class="button wpat-guest-login-btn">%s</a>',
			esc_url( $target_url ),
			esc_html( $login_text )
		);
	}

	/**
	 * Obtiene la URL de inicio de sesión configurada o la página de Mi Cuenta por defecto.
	 *
	 * @return string URL destino.
	 */
	private function get_login_target_url() {
		if ( ! empty( $this->settings['woo_tools_login_for_prices_url'] ) ) {
			return esc_url( $this->settings['woo_tools_login_for_prices_url'] );
		}

		$myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );
		if ( $myaccount_page_id ) {
			return get_permalink( $myaccount_page_id );
		}

		return wp_login_url();
	}

	/**
	 * Inyecta estilos CSS mínimos y elegantes para los botones y llamadas de login de precios.
	 */
	public function render_login_for_prices_styles() {
		?>
		<style id="wpat-login-for-prices-css">
			.wpat-guest-price-notice {
				display: inline-block;
				margin: 6px 0;
			}
			.wpat-login-link-price {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				font-size: 13.5px;
				font-weight: 600;
				color: #2563eb;
				text-decoration: underline;
				text-underline-offset: 3px;
				transition: color 0.2s ease;
			}
			.wpat-login-link-price:hover {
				color: #1d4ed8;
				text-decoration: none;
			}
			.wpat-guest-login-btn {
				display: inline-block !important;
				background: #2563eb !important;
				color: #ffffff !important;
				border: none !important;
				border-radius: 4px !important;
				padding: 8px 14px !important;
				font-size: 13px !important;
				font-weight: 600 !important;
				text-align: center !important;
				text-decoration: none !important;
				cursor: pointer !important;
				transition: background 0.2s ease !important;
			}
			.wpat-guest-login-btn:hover {
				background: #1d4ed8 !important;
				color: #ffffff !important;
			}
		</style>
		<?php
	}

	/* =========================================================================
	   5. ACTUALIZACIÓN AUTOMÁTICA DEL CARRITO (AJAX)
	   ========================================================================= */

	/**
	 * Inyecta script en la página del carrito para auto-actualizar cantidades sin pulsar el botón.
	 */
	public function render_auto_update_cart_script() {
		if ( ! is_cart() || is_cart() && WC()->cart->is_empty() ) {
			return;
		}
		?>
		<script type="text/javascript" id="wpat-auto-update-cart-js">
			(function($) {
				var cartTimeout;
				$(document).on('change input', 'div.woocommerce input.qty', function() {
					clearTimeout(cartTimeout);
					cartTimeout = setTimeout(function() {
						var $btn = $('[name="update_cart"]');
						if ($btn.length) {
							$btn.prop('disabled', false).trigger('click');
						}
					}, 600);
				});
			})(jQuery);
		</script>
		<?php
	}

	/* =========================================================================
	   6. OCULTAR OTROS MÉTODOS DE ENVÍO SI HAY ENVÍO GRATUITO
	   ========================================================================= */

	/**
	 * Filtra las tarifas de envío para ocultar las opciones estándar de pago si el envío gratis está disponible.
	 *
	 * @param array $rates Paquetes y tarifas de envío.
	 * @param array $package Información del paquete.
	 * @return array Tarifas filtradas.
	 */
	public function hide_shipping_when_free_is_available( $rates, $package ) {
		$free_rates = array();

		foreach ( $rates as $rate_id => $rate ) {
			if ( 'free_shipping' === $rate->method_id || false !== strpos( $rate_id, 'free_shipping' ) ) {
				$free_rates[ $rate_id ] = $rate;
			}
		}

		if ( ! empty( $free_rates ) ) {
			// Mantener también recogida local (local_pickup) si está configurada
			foreach ( $rates as $rate_id => $rate ) {
				if ( 'local_pickup' === $rate->method_id || false !== strpos( $rate_id, 'local_pickup' ) ) {
					$free_rates[ $rate_id ] = $rate;
				}
			}
			return $free_rates;
		}

		return $rates;
	}

	/* =========================================================================
	   7. BOTÓN VACIAR CARRITO CON 1 CLIC
	   ========================================================================= */

	/**
	 * Renderiza el botón "Vaciar Carrito" junto a los botones de acción del carrito.
	 */
	public function render_empty_cart_button() {
		$url = wp_nonce_url( add_query_arg( 'wpat_empty_cart', '1', wc_get_cart_url() ), 'wpat_empty_cart_action' );
		$confirm_text = esc_js( __( '¿Estás seguro de que deseas vaciar todos los productos del carrito?', 'wp-agency-toolkit' ) );
		echo '<a href="' . esc_url( $url ) . '" class="button wpat-btn-empty-cart" style="background:#fee2e2; color:#b91c1c; border-color:#fca5a5; font-weight:600; margin-left: 8px;" onclick="return confirm(\'' . $confirm_text . '\');">' . esc_html__( '🗑️ Vaciar Carrito', 'wp-agency-toolkit' ) . '</a>';
	}

	/**
	 * Procesa la acción de vaciado del carrito.
	 */
	public function handle_empty_cart_action() {
		if ( isset( $_GET['wpat_empty_cart'] ) && '1' === (string) $_GET['wpat_empty_cart'] ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpat_empty_cart_action' ) ) {
				return;
			}
			if ( WC()->cart ) {
				WC()->cart->empty_cart();
				wc_add_notice( __( 'El carrito ha sido vaciado correctamente.', 'wp-agency-toolkit' ), 'notice' );
				wp_safe_redirect( wc_get_cart_url() );
				exit;
			}
		}
	}

	/* =========================================================================
	   8. OCULTAR CAMPO NOTAS DEL PEDIDO EN CHECKOUT
	   ========================================================================= */

	/**
	 * Elimina el campo de notas del pedido de los campos del checkout.
	 *
	 * @param array $fields Campos del checkout.
	 * @return array Campos modificados.
	 */
	public function remove_order_notes_field( $fields ) {
		if ( isset( $fields['order']['order_comments'] ) ) {
			unset( $fields['order']['order_comments'] );
		}
		return $fields;
	}

	/* =========================================================================
	   9. DESACTIVAR ENLACES A PRODUCTOS EN CARRITO Y CHECKOUT
	   ========================================================================= */

	/**
	 * Elimina el enlace al producto en el título dentro del carrito/checkout.
	 *
	 * @param string $item_name HTML del nombre.
	 * @param array  $cart_item Datos del artículo.
	 * @param string $cart_item_key Clave del artículo.
	 * @return string Título sin enlace.
	 */
	public function disable_cart_item_link( $item_name, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
			return esc_html( $cart_item['data']->get_name() );
		}
		return strip_tags( $item_name );
	}

	/**
	 * Elimina el enlace que envuelve a la imagen en el carrito.
	 *
	 * @param string $thumbnail HTML de la miniatura.
	 * @param array  $cart_item Datos del artículo.
	 * @param string $cart_item_key Clave del artículo.
	 * @return string Imagen sin etiqueta <a>.
	 */
	public function disable_cart_item_thumbnail_link( $thumbnail, $cart_item, $cart_item_key ) {
		return preg_replace( '/<a[^>]*>(.*?)<\/a>/i', '$1', $thumbnail );
	}

	/* =========================================================================
	   10. ABRIR ENLACES DE PRODUCTOS EN PESTAÑA NUEVA
	   ========================================================================= */

	/**
	 * Inyecta script en el footer para añadir target="_blank" a los productos del catálogo.
	 */
	public function render_open_products_new_tab_script() {
		if ( is_admin() ) {
			return;
		}
		?>
		<script type="text/javascript" id="wpat-open-products-blank-js">
			document.addEventListener('DOMContentLoaded', function() {
				var productLinks = document.querySelectorAll('.woocommerce ul.products li.product a.woocommerce-LoopProduct-link, .woocommerce .products .product a.woocommerce-LoopProduct-link, .wc-block-grid__product-link, a.woocommerce-loop-product__link');
				productLinks.forEach(function(link) {
					link.setAttribute('target', '_blank');
					link.setAttribute('rel', 'noopener noreferrer');
				});
			});
		</script>
		<?php
	}

	/* =========================================================================
	   11. OCULTAR ORDENACIÓN Y CONTEO DEL CATÁLOGO
	   ========================================================================= */

	/**
	 * Elimina el desplegable de ordenación en tienda y archivos.
	 */
	public function remove_shop_ordering() {
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
	}

	/**
	 * Elimina el contador de resultados en tienda y archivos.
	 */
	public function remove_shop_results_count() {
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
	}

	/* =========================================================================
	   12. MINIATURA Y SKU EN PEDIDOS DEL PANEL DE ADMINISTRACIÓN
	   ========================================================================= */

	/**
	 * Renderiza miniatura y SKU en cada línea del pedido al editarlo en wp-admin.
	 *
	 * @param int           $item_id ID del elemento.
	 * @param WC_Order_Item $item    Objeto del elemento.
	 * @param WC_Product    $product Objeto del producto (si existe).
	 */
	public function render_admin_order_item_thumb_and_sku( $item_id, $item, $product ) {
		if ( ! $product ) {
			return;
		}

		$sku   = $product->get_sku();
		$image = $product->get_image( array( 36, 36 ), array(
			'style' => 'width:36px; height:36px; object-fit:cover; border-radius:4px; border:1px solid #cbd5e1; vertical-align:middle; margin-right:8px;',
		) );

		echo '<div style="display:flex; align-items:center; gap:8px; margin: 4px 0 6px 0;">';
		if ( $image ) {
			echo wp_kses_post( $image );
		}
		if ( ! empty( $sku ) ) {
			echo '<span style="display:inline-block; font-size:11px; background:#f1f5f9; color:#475569; padding:2px 6px; border-radius:3px; border:1px solid #e2e8f0; font-family:monospace; font-weight:600;">SKU: ' . esc_html( $sku ) . '</span>';
		}
		echo '</div>';
	}

	/* =========================================================================
	   13. REDIRECCIÓN TRAS LOGIN Y LOGOUT
	   ========================================================================= */

	/**
	 * Redirige al cliente a una URL personalizada tras hacer login.
	 *
	 * @param string  $redirect URL por defecto.
	 * @param WP_User $user     Usuario conectado.
	 * @return string URL destino.
	 */
	public function custom_login_redirect( $redirect, $user ) {
		if ( ! empty( $this->settings['woo_tools_login_redirect_url'] ) ) {
			return esc_url_raw( $this->settings['woo_tools_login_redirect_url'] );
		}
		return $redirect;
	}

	/**
	 * Redirige al cliente a una URL personalizada tras cerrar sesión.
	 *
	 * @param string $redirect URL por defecto.
	 * @return string URL destino.
	 */
	public function custom_logout_redirect( $redirect ) {
		if ( ! empty( $this->settings['woo_tools_logout_redirect_url'] ) ) {
			return esc_url_raw( $this->settings['woo_tools_logout_redirect_url'] );
		}
		return $redirect;
	}
}

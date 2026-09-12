<?php
/**
 * Módulo: Diseñador de Carrito y Checkout High-Conversion - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Checkout_Designer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Checkout_Designer
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Checkout_Designer
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		// Mover el formulario de cupón fuera de la parte superior del checkout
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

		// Evitar duplicación de pasarelas de pago y botón de pago
		remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );

		// Interceptación de plantillas globales de WordPress (prioridad máxima 9999)
		add_filter( 'template_include', array( $this, 'override_checkout_page_template' ), 9999 );
		add_filter( 'template_include', array( $this, 'override_cart_page_template' ), 9999 );
		add_filter( 'the_content', array( $this, 'override_cart_content' ), 9999 );

		// Interceptación de plantillas internas de WooCommerce por compatibilidad adicional
		add_filter( 'woocommerce_locate_template', array( $this, 'override_checkout_template' ), 9999, 3 );
		add_filter( 'woocommerce_locate_template', array( $this, 'override_cart_template' ), 9999, 3 );

		// Carrito Deslizable (Drawer) y Fragmentos AJAX
		add_action( 'wp_footer', array( $this, 'render_drawer_cart_footer' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_to_cart_fragments' ) );

		// Filtros para las miniaturas y clases de body
		add_filter( 'woocommerce_cart_item_name', array( $this, 'add_product_thumbnail_to_checkout' ), 10, 3 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		// Reordenar campos de dirección (País -> Provincia -> Población -> CP -> Dirección)
		add_filter( 'woocommerce_default_address_fields', array( $this, 'custom_default_address_fields_order' ), 9999 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_checkout_fields_order' ), 9999 );

		// Control de Envío Gratuito y Ocultar Calculadora
		add_filter( 'woocommerce_package_rates', array( $this, 'auto_select_free_shipping_and_hide_paid' ), 9999, 2 );
		add_filter( 'option_woocommerce_calc_shipping', array( $this, 'toggle_calc_shipping_option' ), 9999 );
		add_filter( 'option_woocommerce_enable_shipping_calc', array( $this, 'toggle_shipping_calculator_option' ), 9999 );
		add_filter( 'woocommerce_shipping_calculator_enable', array( $this, 'filter_shipping_calculator_enable' ), 9999 );
		add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'ensure_shipping_calculator_in_cart_totals' ), 10 );

		// Fragmento AJAX para actualización de métodos de envío en checkout (Shop-Style / Multi-Step)
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'update_shipping_methods_fragment' ), 9999 );
	}

	/**
	 * Comprueba si la petición actual corresponde a la página de checkout.
	 *
	 * @return bool
	 */
	public function is_checkout_page() {
		if ( is_admin() ) {
			return false;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$checkout_id = wc_get_page_id( 'checkout' );
			if ( $checkout_id && is_page( $checkout_id ) && ! is_order_received_page() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Comprueba si la petición actual corresponde a la página de carrito.
	 *
	 * @return bool
	 */
	public function is_cart_page() {
		if ( is_admin() ) {
			return false;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$cart_id = wc_get_page_id( 'cart' );
			if ( $cart_id && is_page( $cart_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Intercepta la inclusión de la plantilla de WordPress para aislar el checkout de Elementor.
	 */
	public function override_checkout_page_template( $template ) {
		if ( $this->is_checkout_page() ) {
			if ( class_exists( '\Elementor\Plugin' ) ) {
				remove_all_filters( 'elementor/frontend/the_content' );
			}

			$custom_page_template = WPAT_PATH . 'templates/checkout/page-checkout.php';
			if ( file_exists( $custom_page_template ) ) {
				return $custom_page_template;
			}
		}

		return $template;
	}

	/**
	 * Intercepta la carga de la plantilla checkout/form-checkout.php de WooCommerce.
	 */
	public function override_checkout_template( $template, $template_name, $template_path ) {
		if ( 'checkout/form-checkout.php' === $template_name ) {
			$custom_template = WPAT_PATH . 'templates/checkout/form-checkout.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}
		return $template;
	}

	/**
	 * Intercepta la carga de la plantilla cart/cart.php de WooCommerce.
	 */
	public function override_cart_template( $template, $template_name, $template_path ) {
		$settings              = WPAT_Main::get_instance()->get_settings();
		$cart_designer_enabled = ! isset( $settings['woo_cart_designer_enabled'] ) || '1' === $settings['woo_cart_designer_enabled'];

		if ( 'cart/cart.php' === $template_name && $cart_designer_enabled ) {
			$custom_cart_template = WPAT_PATH . 'templates/cart/cart.php';
			if ( file_exists( $custom_cart_template ) ) {
				return $custom_cart_template;
			}
		}
		return $template;
	}

	/**
	 * Intercepta la inclusión de la plantilla de WordPress para aislar la página de carrito de Elementor y constructores.
	 */
	public function override_cart_page_template( $template ) {
		$settings              = WPAT_Main::get_instance()->get_settings();
		$cart_designer_enabled = ! isset( $settings['woo_cart_designer_enabled'] ) || '1' === $settings['woo_cart_designer_enabled'];

		if ( $this->is_cart_page() && $cart_designer_enabled ) {
			if ( class_exists( '\Elementor\Plugin' ) ) {
				remove_all_filters( 'elementor/frontend/the_content' );
			}

			$custom_page_template = WPAT_PATH . 'templates/cart/page-cart.php';
			if ( file_exists( $custom_page_template ) ) {
				return $custom_page_template;
			}
		}

		return $template;
	}

	/**
	 * Intercepta el contenido de la página de carrito en temas estándar que renderizan shortcodes.
	 */
	public function override_cart_content( $content ) {
		$settings              = WPAT_Main::get_instance()->get_settings();
		$cart_designer_enabled = ! isset( $settings['woo_cart_designer_enabled'] ) || '1' === $settings['woo_cart_designer_enabled'];

		if ( $this->is_cart_page() && $cart_designer_enabled && ! is_admin() ) {
			static $rendered = false;
			if ( $rendered ) {
				return $content;
			}
			$rendered = true;

			ob_start();
			$template_file = WPAT_PATH . 'templates/cart/cart.php';
			if ( file_exists( $template_file ) ) {
				include $template_file;
			}
			return ob_get_clean();
		}

		return $content;
	}

	/**
	 * Genera el HTML de la Barra de Progreso para Envío Gratuito.
	 */
	public function get_free_shipping_progress_html() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$show_bar = ! isset( $settings['woo_cart_free_shipping_bar'] ) || '1' === $settings['woo_cart_free_shipping_bar'];

		if ( ! $show_bar ) {
			return '';
		}

		$min_amount = isset( $settings['woo_cart_free_shipping_min_amount'] ) ? floatval( $settings['woo_cart_free_shipping_min_amount'] ) : 50;

		// Intentar detectar si WooCommerce tiene una zona de envío gratuito con importe mínimo configurado
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$zones     = WC_Shipping_Zones::get_zones();
			$rest_zone = new WC_Shipping_Zone( 0 );
			$zones[]   = array( 'shipping_methods' => $rest_zone->get_shipping_methods() );

			foreach ( $zones as $zone ) {
				$methods = isset( $zone['shipping_methods'] ) ? $zone['shipping_methods'] : array();
				foreach ( $methods as $method ) {
					if ( isset( $method->id ) && 'free_shipping' === $method->id && 'yes' === $method->enabled ) {
						$wc_min = floatval( $method->get_option( 'min_amount' ) );
						if ( $wc_min > 0 ) {
							$min_amount = $wc_min;
							break 2;
						}
					}
				}
			}
		}

		if ( $min_amount <= 0 ) {
			return '';
		}

		// Calcular subtotal visible
		if ( method_exists( WC()->cart, 'get_displayed_subtotal' ) ) {
			$subtotal = floatval( WC()->cart->get_displayed_subtotal() );
		} else {
			$subtotal = floatval( WC()->cart->get_subtotal() );
			if ( WC()->cart->display_prices_including_tax() ) {
				$subtotal += floatval( WC()->cart->get_subtotal_tax() );
			}
		}

		$percentage = min( 100, max( 0, ( $subtotal / $min_amount ) * 100 ) );
		$remaining  = max( 0, $min_amount - $subtotal );

		ob_start();
		?>
		<div class="wpat-free-shipping-progress-box">
			<?php if ( $remaining > 0 ) : ?>
				<p class="wpat-free-shipping-text">
					🚚 Te faltan <strong><?php echo wc_price( $remaining ); ?></strong> para conseguir <span>ENVÍO GRATIS</span>
				</p>
			<?php else : ?>
				<p class="wpat-free-shipping-text wpat-shipping-unlocked">
					🎉 ¡Enhorabuena! Has conseguido <span>ENVÍO GRATIS</span> en tu pedido
				</p>
			<?php endif; ?>
			<div class="wpat-progress-bar-bg">
				<div class="wpat-progress-bar-fill" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renderiza el Mini-Carrito Deslizable (Slide-Out / Drawer Cart) en el footer.
	 */
	public function render_drawer_cart_footer() {
		if ( is_admin() || $this->is_checkout_page() ) {
			return;
		}

		$settings              = WPAT_Main::get_instance()->get_settings();
		$cart_designer_enabled = ! isset( $settings['woo_cart_designer_enabled'] ) || '1' === $settings['woo_cart_designer_enabled'];
		$cart_layout            = isset( $settings['woo_cart_designer_layout'] ) ? $settings['woo_cart_designer_layout'] : 'wpat-cart-classic';

		if ( ! $cart_designer_enabled || 'wpat-cart-drawer' !== $cart_layout ) {
			return;
		}

		$drawer_template = WPAT_PATH . 'templates/cart/cart-drawer.php';
		if ( file_exists( $drawer_template ) ) {
			include $drawer_template;
		}
	}

	/**
	 * Actualiza los fragmentos AJAX de WooCommerce al añadir o eliminar un producto.
	 */
	public function add_to_cart_fragments( $fragments ) {
		$settings              = WPAT_Main::get_instance()->get_settings();
		$cart_designer_enabled = ! isset( $settings['woo_cart_designer_enabled'] ) || '1' === $settings['woo_cart_designer_enabled'];
		$cart_layout            = isset( $settings['woo_cart_designer_layout'] ) ? $settings['woo_cart_designer_layout'] : 'wpat-cart-classic';

		if ( $cart_designer_enabled && 'wpat-cart-drawer' === $cart_layout ) {
			ob_start();
			$drawer_content_template = WPAT_PATH . 'templates/cart/cart-drawer-content.php';
			if ( file_exists( $drawer_content_template ) ) {
				include $drawer_content_template;
			}
			$fragments['div.wpat-drawer-cart-body'] = ob_get_clean();

			$cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
			$fragments['span.wpat-drawer-cart-count'] = '<span class="wpat-drawer-cart-count">' . esc_html( $cart_count ) . '</span>';
		}

		return $fragments;
	}

	/**
	 * Añade clases al body para checkout y carrito.
	 */
	public function add_body_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}

		if ( $this->is_checkout_page() ) {
			$classes[] = 'wpat-checkout-designer-active';
			$classes[] = 'wpat-standalone-checkout-page';
		}
		if ( $this->is_cart_page() ) {
			$classes[] = 'wpat-cart-designer-active';

			$settings           = WPAT_Main::get_instance()->get_settings();
			$show_shipping_calc = isset( $settings['woo_cart_show_shipping_calculator'] ) && '1' === $settings['woo_cart_show_shipping_calculator'];

			if ( $show_shipping_calc ) {
				$classes[] = 'wpat-show-shipping-calc';
			} else {
				$classes[] = 'wpat-hide-shipping-calc';
			}
		}
		return $classes;
	}

	/**
	 * Encola los estilos y scripts del diseñador de carrito y checkout.
	 */
	public function enqueue_checkout_assets() {
		if ( is_admin() ) {
			return;
		}

		$settings    = WPAT_Main::get_instance()->get_settings();
		$is_checkout = $this->is_checkout_page();
		$is_cart     = $this->is_cart_page();
		$cart_layout = isset( $settings['woo_cart_designer_layout'] ) ? $settings['woo_cart_designer_layout'] : 'wpat-cart-classic';
		$is_drawer   = 'wpat-cart-drawer' === $cart_layout;

		if ( ! $is_checkout && ! $is_cart && ! $is_drawer ) {
			return;
		}

		wp_enqueue_style(
			'wpat-checkout-designer-css',
			WPAT_URL . 'assets/css/wpat-checkout-designer.css',
			array(),
			WPAT_VERSION
		);

		$btn_bg_color       = isset( $settings['woo_checkout_btn_bg_color'] ) ? $settings['woo_checkout_btn_bg_color'] : '#2563eb';
		$btn_hover_bg_color = isset( $settings['woo_checkout_btn_hover_bg_color'] ) ? $settings['woo_checkout_btn_hover_bg_color'] : '#1d4ed8';
		$btn_txt_color      = isset( $settings['woo_checkout_btn_txt_color'] ) ? $settings['woo_checkout_btn_txt_color'] : '#ffffff';
		$step_accent_color  = isset( $settings['woo_checkout_step_accent_color'] ) ? $settings['woo_checkout_step_accent_color'] : '#2563eb';

		$custom_css = "
			.wpat-checkout-container .wpat-next-btn,
			.wpat-checkout-container .wpat-coupon-submit-btn,
			.wpat-checkout-container #place_order,
			.wpat-checkout-container button#place_order,
			.wpat-checkout-container input[type='submit'].button.alt,
			.wpat-cart-container .checkout-button,
			.wpat-cart-container a.checkout-button,
			.wpat-cart-container .wc-proceed-to-checkout a,
			.wpat-cart-totals-card .checkout-button,
			.wpat-cart-totals-card a.checkout-button,
			.wpat-cart-totals-card .wc-proceed-to-checkout a,
			.wpat-cart-drawer-checkout-btn {
				background: {$btn_bg_color} !important;
				color: {$btn_txt_color} !important;
			}
			.wpat-checkout-container .wpat-next-btn:hover,
			.wpat-checkout-container .wpat-coupon-submit-btn:hover,
			.wpat-checkout-container #place_order:hover,
			.wpat-checkout-container button#place_order:hover,
			.wpat-checkout-container input[type='submit'].button.alt:hover,
			.wpat-cart-container .checkout-button:hover,
			.wpat-cart-container a.checkout-button:hover,
			.wpat-cart-container .wc-proceed-to-checkout a:hover,
			.wpat-cart-totals-card .checkout-button:hover,
			.wpat-cart-totals-card a.checkout-button:hover,
			.wpat-cart-totals-card .wc-proceed-to-checkout a:hover,
			.wpat-cart-drawer-checkout-btn:hover {
				background: {$btn_hover_bg_color} !important;
				color: {$btn_txt_color} !important;
			}
			.wpat-checkout-steps-bar .wpat-step-item.active .wpat-step-number,
			.wpat-checkout-steps-bar .wpat-step-item.active .wpat-step-num {
				background: {$step_accent_color} !important;
				color: #ffffff !important;
			}
			.wpat-checkout-steps-bar .wpat-step-item.active .wpat-step-title {
				color: {$step_accent_color} !important;
			}
		";
		wp_add_inline_style( 'wpat-checkout-designer-css', $custom_css );

		wp_enqueue_script(
			'wpat-checkout-designer-js',
			WPAT_URL . 'assets/js/wpat-checkout-designer.js',
			array( 'jquery' ),
			WPAT_VERSION,
			true
		);

		$layout            = isset( $settings['woo_checkout_designer_layout'] ) ? $settings['woo_checkout_designer_layout'] : 'wpat-classic';
		$drawer_auto_open  = ! isset( $settings['woo_cart_drawer_auto_open'] ) || '1' === $settings['woo_cart_drawer_auto_open'];

		$spain_provinces = array(
			'01' => 'VI', '02' => 'AB', '03' => 'A',  '04' => 'AL', '05' => 'AV',
			'06' => 'BA', '07' => 'PM', '08' => 'B',  '09' => 'BU', '10' => 'CC',
			'11' => 'CA', '12' => 'CS', '13' => 'CR', '14' => 'CO', '15' => 'C',
			'16' => 'CU', '17' => 'GI', '18' => 'GR', '19' => 'GU', '20' => 'SS',
			'21' => 'H',  '22' => 'HU', '23' => 'J',  '24' => 'LE', '25' => 'L',
			'26' => 'LO', '27' => 'LU', '28' => 'M',  '29' => 'MA', '30' => 'MU',
			'31' => 'NA', '32' => 'OR', '33' => 'O',  '34' => 'P',  '35' => 'GC',
			'36' => 'PO', '37' => 'SA', '38' => 'TF', '39' => 'S',  '40' => 'SG',
			'41' => 'SE', '42' => 'SO', '43' => 'T',  '44' => 'TE', '45' => 'TO',
			'46' => 'V',  '47' => 'VA', '48' => 'BI', '49' => 'ZA', '50' => 'Z',
			'51' => 'CE', '52' => 'ML',
		);

		wp_localize_script( 'wpat-checkout-designer-js', 'wpatCheckoutOptions', array(
			'layout'               => $layout,
			'cart_layout'          => $cart_layout,
			'drawer_auto_open'     => $drawer_auto_open ? '1' : '0',
			'email_autocorrect'    => isset( $settings['woo_checkout_designer_email_fix'] ) ? $settings['woo_checkout_designer_email_fix'] : '1',
			'normalize_selects'    => isset( $settings['woo_checkout_designer_normalize_selects'] ) ? $settings['woo_checkout_designer_normalize_selects'] : '1',
			'autofill_spain_cp'    => isset( $settings['woo_checkout_designer_autofill_spain_cp'] ) ? $settings['woo_checkout_designer_autofill_spain_cp'] : '1',
			'spain_provinces'      => $spain_provinces,
			'mobile_summary_label' => __( 'Resumen del pedido', 'wp-agency-toolkit' ),
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
		) );
	}

	/**
	 * Agrega la miniatura del producto y badge de cantidad en el resumen del checkout.
	 */
	public function add_product_thumbnail_to_checkout( $product_name, $cart_item, $cart_item_key ) {
		if ( ! $this->is_checkout_page() ) {
			return $product_name;
		}

		$settings    = WPAT_Main::get_instance()->get_settings();
		$show_thumbs = ! isset( $settings['woo_checkout_designer_product_thumbs'] ) || '1' === $settings['woo_checkout_designer_product_thumbs'];

		if ( ! $show_thumbs ) {
			return $product_name;
		}

		$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

		if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 ) {
			$thumbnail = $_product->get_image( array( 50, 50 ) );
			$quantity  = '<span class="wpat-checkout-product-qty">' . esc_html( $cart_item['quantity'] ) . '</span>';
			
			$thumb_wrapper = '<div class="wpat-checkout-thumb-box">' . $thumbnail . $quantity . '</div>';
			return '<div class="wpat-checkout-item-title-wrap">' . $thumb_wrapper . '<span class="wpat-checkout-item-name">' . $product_name . '</span></div>';
		}

		return $product_name;
	}

	/**
	 * Reordena los campos por defecto de dirección de WooCommerce.
	 */
	public function custom_default_address_fields_order( $fields ) {
		if ( isset( $fields['first_name'] ) ) {
			$fields['first_name']['priority'] = 10;
		}
		if ( isset( $fields['last_name'] ) ) {
			$fields['last_name']['priority'] = 11;
		}
		if ( isset( $fields['country'] ) ) {
			$fields['country']['priority'] = 20;
		}
		if ( isset( $fields['state'] ) ) {
			$fields['state']['priority'] = 21;
		}
		if ( isset( $fields['city'] ) ) {
			$fields['city']['priority'] = 30;
		}
		if ( isset( $fields['postcode'] ) ) {
			$fields['postcode']['priority'] = 31;
		}
		if ( isset( $fields['address_1'] ) ) {
			$fields['address_1']['priority'] = 40;
		}
		if ( isset( $fields['address_2'] ) ) {
			$fields['address_2']['priority'] = 41;
		}

		return $fields;
	}

	/**
	 * Reordena los campos de billing y shipping del checkout de WooCommerce.
	 */
	public function custom_checkout_fields_order( $fields ) {
		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['priority'] = 1;
			$fields['billing']['billing_email']['class']    = array( 'form-row-wide' );
		}
		if ( isset( $fields['billing']['billing_first_name'] ) ) {
			$fields['billing']['billing_first_name']['priority'] = 10;
			$fields['billing']['billing_first_name']['class']    = array( 'form-row-first' );
		}
		if ( isset( $fields['billing']['billing_last_name'] ) ) {
			$fields['billing']['billing_last_name']['priority'] = 11;
			$fields['billing']['billing_last_name']['class']    = array( 'form-row-last' );
		}
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['priority'] = 50;
			$fields['billing']['billing_phone']['class']    = array( 'form-row-wide' );
		}

		$order = array(
			'first_name' => array( 'p' => 10, 'c' => 'form-row-first' ),
			'last_name'  => array( 'p' => 11, 'c' => 'form-row-last' ),
			'country'    => array( 'p' => 20, 'c' => 'form-row-first' ),
			'state'      => array( 'p' => 21, 'c' => 'form-row-last' ),
			'city'       => array( 'p' => 30, 'c' => 'form-row-first' ),
			'postcode'   => array( 'p' => 31, 'c' => 'form-row-last' ),
			'address_1'  => array( 'p' => 40, 'c' => 'form-row-wide' ),
			'address_2'  => array( 'p' => 41, 'c' => 'form-row-wide' ),
		);

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			if ( isset( $fields[ $type ] ) ) {
				foreach ( $order as $key => $info ) {
					$field_key = $type . '_' . $key;
					if ( isset( $fields[ $type ][ $field_key ] ) ) {
						$fields[ $type ][ $field_key ]['priority'] = $info['p'];
						$fields[ $type ][ $field_key ]['class']    = array( $info['c'] );
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Auto-selecciona el envío gratuito y oculta/deshabilita las tarifas de pago al alcanzar el importe mínimo.
	 *
	 * @param array $rates Tarifas disponibles para el paquete.
	 * @param array $package Paquete de envío.
	 * @return array
	 */
	public function auto_select_free_shipping_and_hide_paid( $rates, $package ) {
		if ( empty( $rates ) || ! is_array( $rates ) ) {
			return $rates;
		}

		$has_free_shipping = false;
		$free_rate_id      = '';

		foreach ( $rates as $rate_id => $rate ) {
			if ( 'free_shipping' === $rate->method_id ) {
				$has_free_shipping = true;
				$free_rate_id      = $rate_id;
				break;
			}
		}

		if ( $has_free_shipping ) {
			if ( function_exists( 'WC' ) && WC()->session ) {
				$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
				if ( empty( $chosen_methods ) || ( is_array( $chosen_methods ) && isset( $chosen_methods[0] ) && strpos( $chosen_methods[0], 'free_shipping' ) === false ) ) {
					WC()->session->set( 'chosen_shipping_methods', array( $free_rate_id ) );
				}
			}

			$free_rates = array();
			$free_rates[ $free_rate_id ] = $rates[ $free_rate_id ];
			return $free_rates;
		}

		return $rates;
	}

	/**
	 * Habilita el cálculo de envíos en WooCommerce cuando la calculadora del carrito está activa.
	 *
	 * @param string $value Valor de la opción ('yes' o 'no').
	 * @return string
	 */
	public function toggle_calc_shipping_option( $value ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $value;
		}

		$settings           = WPAT_Main::get_instance()->get_settings();
		$show_shipping_calc = isset( $settings['woo_cart_show_shipping_calculator'] ) && '1' === $settings['woo_cart_show_shipping_calculator'];

		if ( $show_shipping_calc ) {
			return 'yes';
		}

		return $value;
	}

	/**
	 * Controla la deshabilitación de la calculadora de envíos en el carrito cuando la opción del panel está desmarcada.
	 *
	 * @param string $value Valor de la opción ('yes' o 'no').
	 * @return string
	 */
	public function toggle_shipping_calculator_option( $value ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $value;
		}

		$settings           = WPAT_Main::get_instance()->get_settings();
		$show_shipping_calc = isset( $settings['woo_cart_show_shipping_calculator'] ) && '1' === $settings['woo_cart_show_shipping_calculator'];

		return $show_shipping_calc ? 'yes' : 'no';
	}

	/**
	 * Callback para el filtro nativo woocommerce_shipping_calculator_enable.
	 *
	 * @param bool $enabled Valor actual.
	 * @return bool
	 */
	public function filter_shipping_calculator_enable( $enabled ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $enabled;
		}

		$settings           = WPAT_Main::get_instance()->get_settings();
		$show_shipping_calc = isset( $settings['woo_cart_show_shipping_calculator'] ) && '1' === $settings['woo_cart_show_shipping_calculator'];

		return (bool) $show_shipping_calc;
	}

	/**
	 * Renderiza la fila de envío con la calculadora de envíos en los totales del carrito
	 * si WooCommerce no la imprimió (por ejemplo, si no hay dirección calculada o la plantilla del tema la omitió).
	 */
	public function ensure_shipping_calculator_in_cart_totals() {
		$settings           = WPAT_Main::get_instance()->get_settings();
		$show_shipping_calc = isset( $settings['woo_cart_show_shipping_calculator'] ) && '1' === $settings['woo_cart_show_shipping_calculator'];

		if ( ! $show_shipping_calc ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->cart->needs_shipping() ) {
			return;
		}

		if ( WC()->cart->show_shipping() ) {
			return;
		}

		?>
		<tr class="shipping wpat-forced-shipping-calculator-row">
			<th><?php esc_html_e( 'Envío', 'woocommerce' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Envío', 'woocommerce' ); ?>">
				<?php woocommerce_shipping_calculator(); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Actualiza el fragmento AJAX de la tarjeta de métodos de envío en el checkout.
	 *
	 * @param array $fragments Fragmentos AJAX de WooCommerce.
	 * @return array
	 */
	public function update_shipping_methods_fragment( $fragments ) {
		ob_start();
		echo '<div class="wpat-shipping-methods-container">';
		if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->needs_shipping() ) {
			echo '<table class="shop_table wpat-shop-style-shipping-table" style="width: 100%; border-collapse: collapse;"><tbody>';
			wc_cart_totals_shipping_html();
			echo '</tbody></table>';
		} else {
			echo '<p style="font-size: 13px; color: #64748b;">No se requieren opciones de envío para este pedido.</p>';
		}
		echo '</div>';

		$fragments['div.wpat-shipping-methods-container'] = ob_get_clean();
		return $fragments;
	}
}

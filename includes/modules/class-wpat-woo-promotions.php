<?php
/**
 * Módulo de Promociones Dinámicas y Descuentos para WooCommerce - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Singleton WPAT_Woo_Promotions.
 */
class WPAT_Woo_Promotions {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Promotions|null
	 */
	private static $instance = null;

	/**
	 * Bandera estática para prevenir bucles infinitos de recursión en los filtros de precio.
	 *
	 * @var bool
	 */
	private static $in_price_filter = false;

	/**
	 * Obtiene la instancia única (Singleton).
	 *
	 * @return WPAT_Woo_Promotions
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
		$settings = WPAT_Main::get_instance()->get_settings();

		// Permitir ejecución si el módulo está marcado activo o si existen reglas configuradas
		$has_rules = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) && ! empty( $settings['woo_promotions_rules'] );
		$is_on     = ( isset( $settings['woo-promotions'] ) && '1' === (string) $settings['woo-promotions'] )
				  || ( isset( $settings['woo_promotions'] ) && '1' === (string) $settings['woo_promotions'] );

		if ( ! $is_on && ! $has_rules ) {
			return;
		}

		// Filtros para modificar precios dinámicamente en catálogo, listas y fichas de producto
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'filter_product_sale_price' ), 20, 2 );
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_product_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'filter_product_sale_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_product_price' ), 20, 2 );

		// Filtros para rangos de precio en productos variables
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'filter_variation_price' ), 20, 3 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_price' ), 20, 3 );

		// Filtro para marcar el producto en oferta (activa badges de WooCommerce y de WPAT Badges)
		add_filter( 'woocommerce_product_is_on_sale', array( $this, 'filter_product_is_on_sale' ), 20, 2 );

		// Hook principal para cálculo de tarifas/descuentos a nivel de carrito
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_promotions_fees' ), 20, 1 );

		// Trazabilidad contable en checkout / HPOS (Order Fee Item Meta)
		add_action( 'woocommerce_checkout_create_order_fee_item', array( $this, 'save_fee_item_meta' ), 10, 4 );

		// Inyección de la barra de progreso interactiva en carrito
		add_action( 'woocommerce_before_cart_totals', array( $this, 'render_tiered_spend_progress_bar' ), 10 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'render_tiered_spend_progress_bar' ), 5 );

		// Hook para renderizar contador regresivo en la ficha de producto (Elementor, Hello theme, builders y estándar)
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_countdown_banner' ), 11 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_countdown_banner' ), 25 );
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_product_countdown_banner' ), 5 );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_product_countdown_banner' ), 5 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_product_countdown_banner' ), 15 );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_product_countdown_banner' ), 15 );
		add_action( 'woocommerce_product_meta_end', array( $this, 'render_product_countdown_banner' ), 15 );

		// Integración con el filtro de precio para inyectar automáticamente en maquetadores (Elementor Price widget, etc.)
		add_filter( 'woocommerce_get_price_html', array( $this, 'append_countdown_to_price_html' ), 99, 2 );

		// Catálogo y loops de productos
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_shop_loop_countdown_banner' ), 9 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_shop_loop_countdown_banner' ), 15 );
		add_action( 'woocommerce_shop_loop_item_title', array( $this, 'render_shop_loop_countdown_banner' ), 15 );

		add_shortcode( 'wpat_promo_countdown', array( $this, 'render_countdown_shortcode' ) );

		// Filtros para mostrar el Título Público de la promoción en Carrito y Checkout
		add_filter( 'woocommerce_cart_item_name', array( $this, 'display_promo_title_in_cart_and_checkout' ), 20, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_line_item_promo_meta' ), 10, 4 );

		// Carga de assets frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Shortcode [wpat_promo_countdown id="123"] para renderizar el contador en cualquier maquetador (Elementor, Divi, etc.).
	 *
	 * @param array $atts Atributos del shortcode.
	 * @return string HTML del contador.
	 */
	public function render_countdown_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'wpat_promo_countdown'
		);

		$product = self::resolve_product( ! empty( $atts['id'] ) ? absint( $atts['id'] ) : null );
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		return $this->get_countdown_banner_html( $product, false );
	}

	/**
	 * Inyecta el contador directamente tras el precio si aún no se ha renderizado por ningún hook de acción.
	 * Ideal para maquetadores como Elementor que reemplazan los hooks tradicionales por widgets sueltos.
	 *
	 * @param string     $price_html HTML del precio.
	 * @param WC_Product $product    Objeto producto.
	 * @return string
	 */
	public function append_countdown_to_price_html( $price_html, $product ) {
		if ( is_admin() || wp_doing_ajax() || ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			return $price_html;
		}

		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return $price_html;
		}

		$is_single = ( function_exists( 'is_product' ) && is_product() ) || ( function_exists( 'get_the_ID' ) && get_the_ID() === $product->get_id() );
		$banner_html = $this->get_countdown_banner_html( $product, ! $is_single );

		if ( ! empty( $banner_html ) ) {
			return $price_html . $banner_html;
		}

		return $price_html;
	}

	/**
	 * Encola estilos y scripts para el frontend.
	 */
	public function enqueue_frontend_assets() {
		$plugin_url = defined( 'WPAT_URL' ) ? WPAT_URL : plugin_dir_url( dirname( __DIR__ ) );
		$version    = defined( 'WPAT_VERSION' ) ? WPAT_VERSION : '1.0.0';

		wp_enqueue_style(
			'wpat-woo-promotions-css',
			$plugin_url . 'assets/css/wpat-woo-promotions.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'wpat-woo-promotions-js',
			$plugin_url . 'assets/js/wpat-woo-promotions.js',
			array( 'jquery' ),
			$version,
			true
		);
	}

	/**
	 * Comprueba si un producto tiene un precio de oferta nativo en WooCommerce (antes de promociones WPAT).
	 *
	 * @param WC_Product $product Objeto producto.
	 * @return bool True si tiene rebaja nativa previa en WooCommerce.
	 */
	public static function is_product_natively_on_sale( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}
		$sale_price = (float) $product->get_sale_price( 'edit' );
		if ( $sale_price <= 0 ) {
			$sale_price = (float) $product->get_sale_price();
		}
		return $sale_price > 0;
	}

	/**
	 * Convierte cualquier formato de fecha/hora (ISO, datetime-local, DD/MM/YYYY) a timestamp Unix.
	 *
	 * @param string $date_str Cadena de fecha.
	 * @param bool   $is_end_date Si es fecha de fin sin hora explícita.
	 * @return int Timestamp Unix o 0.
	 */
	public static function parse_date_to_timestamp( $date_str, $is_end_date = false ) {
		if ( empty( $date_str ) ) {
			return 0;
		}

		$date_str = str_replace( 'T', ' ', trim( $date_str ) );

		if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2}))?/', $date_str, $m ) ) {
			$day   = sprintf( '%02d', $m[1] );
			$month = sprintf( '%02d', $m[2] );
			$year  = $m[3];
			$has_t = isset( $m[4] ) && isset( $m[5] );
			$time  = $has_t ? sprintf( '%02d:%02d:00', $m[4], $m[5] ) : ( $is_end_date ? '23:59:59' : '00:00:00' );
			$date_str = "$year-$month-$day $time";
		} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_str ) ) {
			$date_str .= $is_end_date ? ' 23:59:59' : ' 00:00:00';
		} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2}$/', $date_str ) ) {
			$date_str .= $is_end_date ? ':59' : ':00';
		}

		try {
			$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
			$dt = new DateTime( $date_str, $tz );
			return $dt->getTimestamp();
		} catch ( Exception $e ) {
			$ts = strtotime( $date_str );
			return $ts ? $ts : 0;
		}
	}

	/**
	 * Filtra el precio directo del producto cuando aplica una regla global o por volumen.
	 *
	 * @param float      $price Precio actual.
	 * @param WC_Product $product Objeto producto.
	 * @return float Precio filtrado o precio original.
	 */
	public function filter_product_price( $price, $product ) {
		$promo_price = $this->get_promo_sale_price_for_product( $product );
		return false !== $promo_price ? $promo_price : $price;
	}

	/**
	 * Obtiene el precio de oferta promocional dinámico para un producto.
	 *
	 * @param WC_Product $product Objeto producto.
	 * @return float|false Precio rebajado o false si no aplica.
	 */
	public function get_promo_sale_price_for_product( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		if ( self::$in_price_filter ) {
			return false;
		}

		self::$in_price_filter = true;

		$regular_price = (float) $product->get_regular_price();
		if ( $regular_price <= 0 ) {
			$regular_price = (float) $product->get_price();
		}

		if ( $regular_price <= 0 ) {
			self::$in_price_filter = false;
			return false;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$rules    = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) ? $settings['woo_promotions_rules'] : array();

		if ( empty( $rules ) ) {
			self::$in_price_filter = false;
			return false;
		}

		$lowest_price = $regular_price;
		$applied      = false;

		$product_id   = $product->get_id();
		$parent_id    = $product->get_parent_id();
		$effective_id = $parent_id ? $parent_id : $product_id;

		$now_ts = time();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['active'] ) || '1' !== (string) $rule['active'] ) {
				continue;
			}

			$enable_sched = isset( $rule['enable_schedule'] ) ? ( '1' === (string) $rule['enable_schedule'] ) : ( ! empty( $rule['start_date'] ) || ! empty( $rule['end_date'] ) );
			if ( $enable_sched ) {
				if ( ! empty( $rule['start_date'] ) ) {
					$start_ts = self::parse_date_to_timestamp( $rule['start_date'] );
					if ( $start_ts && $now_ts < $start_ts ) {
						continue;
					}
				}
				if ( ! empty( $rule['end_date'] ) ) {
					$end_ts = self::parse_date_to_timestamp( $rule['end_date'], true );
					if ( $end_ts && $now_ts > $end_ts ) {
						continue;
					}
				}
			}

			$rule_type   = ! empty( $rule['type'] ) ? sanitize_key( $rule['type'] ) : 'global_discount';
			$scope       = ! empty( $rule['scope'] ) ? sanitize_key( $rule['scope'] ) : 'all';
			$ignore_sale = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];

			if ( $ignore_sale && self::is_product_natively_on_sale( $product ) ) {
				continue;
			}

			// Solo las reglas de descuento global modifican directamente el precio base/unitario del producto.
			// Las reglas por volumen (bulk_qty), tramos (tiered_spend) y 3x2 (bxgy) se calculan en el carrito según cantidades reales.
			if ( 'global_discount' !== $rule_type ) {
				continue;
			}

			$target_cats  = isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? array_map( 'absint', $rule['categories'] ) : array();
			$target_prods = isset( $rule['products'] ) && is_array( $rule['products'] ) ? array_map( 'absint', $rule['products'] ) : array();

			$matches = false;
			if ( 'all' === $scope ) {
				$matches = true;
			} elseif ( 'category' === $scope && ! empty( $target_cats ) ) {
				$prod_cats = wc_get_product_term_ids( $effective_id, 'product_cat' );
				if ( array_intersect( $target_cats, $prod_cats ) ) {
					$matches = true;
				}
			} elseif ( 'product' === $scope && ! empty( $target_prods ) ) {
				if ( in_array( $product_id, $target_prods, true ) || in_array( $effective_id, $target_prods, true ) ) {
					$matches = true;
				}
			}

			if ( $matches ) {
				$d_type        = ! empty( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent';
				$d_val         = isset( $rule['discount_value'] ) ? (float) $rule['discount_value'] : 0.0;
				$include_extra = ! empty( $rule['include_extra_options'] ) && '1' === (string) $rule['include_extra_options'];

				$extra_price = ( is_object( $product ) && isset( $product->wpat_extra_price ) ) ? floatval( $product->wpat_extra_price ) : 0.0;
				$base_price  = ( is_object( $product ) && isset( $product->wpat_base_price ) && floatval( $product->wpat_base_price ) > 0 ) ? floatval( $product->wpat_base_price ) : $regular_price;

				if ( $extra_price > 0 ) {
					if ( $include_extra ) {
						$total_base = $base_price + $extra_price;
						if ( 'percent' === $d_type ) {
							$calculated = $total_base - ( $total_base * ( $d_val / 100.0 ) );
						} else {
							$calculated = $total_base - $d_val;
						}
					} else {
						if ( 'percent' === $d_type ) {
							$calculated = ( $base_price - ( $base_price * ( $d_val / 100.0 ) ) ) + $extra_price;
						} else {
							$calculated = ( $base_price - $d_val ) + $extra_price;
						}
					}
				} else {
					if ( 'percent' === $d_type ) {
						$calculated = $regular_price - ( $regular_price * ( $d_val / 100.0 ) );
					} else {
						$calculated = $regular_price - $d_val;
					}
				}

				$calculated = max( 0, $calculated );
				if ( $calculated < $lowest_price ) {
					$lowest_price = $calculated;
					$applied      = true;
					$winning_rule = $rule;
				}
			}
		}

		if ( $applied && is_object( $product ) ) {
			$product->wpat_promo_applied     = true;
			$product->wpat_applied_promo_rule = isset( $winning_rule ) ? $winning_rule : null;
		}

		self::$in_price_filter = false;

		return $applied ? $lowest_price : false;
	}

	/**
	 * Filtra la propiedad is_on_sale para activar el icono/badge de oferta de WooCommerce y WPAT.
	 *
	 * @param bool       $on_sale Estado de oferta actual.
	 * @param WC_Product $product Objeto producto.
	 * @return bool True si tiene descuento de promoción o rebaja previa.
	 */
	public function filter_product_is_on_sale( $on_sale, $product ) {
		if ( $on_sale ) {
			return true;
		}
		$promo_price = $this->get_promo_sale_price_for_product( $product );
		return false !== $promo_price;
	}

	/**
	 * Filtra el precio de oferta del producto.
	 */
	public function filter_product_sale_price( $price, $product ) {
		$promo_price = $this->get_promo_sale_price_for_product( $product );
		if ( false !== $promo_price ) {
			return (string) $promo_price;
		}
		return $price;
	}

	/**
	 * Filtra los precios de variaciones para WooCommerce en catálogo.
	 */
	public function filter_variation_price( $price, $variation, $product ) {
		$promo_price = $this->get_promo_sale_price_for_product( $variation );
		if ( false !== $promo_price ) {
			return (string) $promo_price;
		}
		return $price;
	}

	/**
	 * Aplica descuentos en el carrito para reglas basadas en tramos de gasto o método de pago.
	 *
	 * @param WC_Cart $cart Objeto carrito de WooCommerce.
	 */
	public function calculate_promotions_fees( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$rules    = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) ? $settings['woo_promotions_rules'] : array();

		if ( empty( $rules ) ) {
			return;
		}

		$cart_items = $cart->get_cart();
		if ( empty( $cart_items ) ) {
			return;
		}

		$now_ts          = time();
		$chosen_gateways = WC()->session ? (array) WC()->session->get( 'chosen_payment_method' ) : array();
		$chosen_gw       = ! empty( $chosen_gateways ) ? reset( $chosen_gateways ) : '';

		foreach ( $rules as $rule ) {
			if ( empty( $rule['active'] ) || '1' !== (string) $rule['active'] ) {
				continue;
			}

			$enable_sched = isset( $rule['enable_schedule'] ) ? ( '1' === (string) $rule['enable_schedule'] ) : ( ! empty( $rule['start_date'] ) || ! empty( $rule['end_date'] ) );
			if ( $enable_sched ) {
				if ( ! empty( $rule['start_date'] ) ) {
					$start_ts = self::parse_date_to_timestamp( $rule['start_date'] );
					if ( $start_ts && $now_ts < $start_ts ) {
						continue;
					}
				}
				if ( ! empty( $rule['end_date'] ) ) {
					$end_ts = self::parse_date_to_timestamp( $rule['end_date'], true );
					if ( $end_ts && $now_ts > $end_ts ) {
						continue;
					}
				}
			}

			$rule_id       = ! empty( $rule['id'] ) ? sanitize_key( $rule['id'] ) : 'rule_' . md5( serialize( $rule ) );
			$rule_title    = ! empty( $rule['title'] ) ? sanitize_text_field( $rule['title'] ) : __( 'Descuento Promocional', 'wp-agency-toolkit' );
			$rule_type     = ! empty( $rule['type'] ) ? sanitize_key( $rule['type'] ) : 'global_discount';
			$scope         = ! empty( $rule['scope'] ) ? sanitize_key( $rule['scope'] ) : 'all';
			$ignore_sale   = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];
			$include_extra = ! empty( $rule['include_extra_options'] ) && '1' === (string) $rule['include_extra_options'];

			if ( 'global_discount' === $rule_type ) {
				continue;
			}

			$target_cats  = isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? array_map( 'absint', $rule['categories'] ) : array();
			$target_prods = isset( $rule['products'] ) && is_array( $rule['products'] ) ? array_map( 'absint', $rule['products'] ) : array();

			$qualifying_items    = array();
			$qualifying_subtotal = 0.0;
			$qualifying_qty      = 0;

			foreach ( $cart_items as $cart_item_key => $item ) {
				/** @var WC_Product|null $product */
				$product = isset( $item['data'] ) ? $item['data'] : null;
				if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
					continue;
				}

				if ( $ignore_sale && self::is_product_natively_on_sale( $product ) ) {
					continue;
				}

				$product_id   = $product->get_id();
				$parent_id    = $product->get_parent_id();
				$effective_id = $parent_id ? $parent_id : $product_id;

				$matches = false;
				if ( 'all' === $scope ) {
					$matches = true;
				} elseif ( 'category' === $scope && ! empty( $target_cats ) ) {
					$prod_cats = wc_get_product_term_ids( $effective_id, 'product_cat' );
					if ( array_intersect( $target_cats, $prod_cats ) ) {
						$matches = true;
					}
				} elseif ( 'product' === $scope && ! empty( $target_prods ) ) {
					if ( in_array( $product_id, $target_prods, true ) || in_array( $effective_id, $target_prods, true ) ) {
						$matches = true;
					}
				}

				if ( $matches ) {
					$item_price = (float) $item['line_subtotal'];
					if ( $include_extra && ! empty( $item['wpat_extra_options'] ) ) {
						$item_price = (float) $item['line_total'];
					}
					$qualifying_items[]   = $item;
					$qualifying_subtotal += $item_price;
					$qualifying_qty      += (int) $item['quantity'];
				}
			}

			if ( empty( $qualifying_items ) && 'payment_method' !== $rule_type ) {
				continue;
			}

			$discount_amount = 0.0;

			if ( 'tiered_spend' === $rule_type ) {
				$tiers = isset( $rule['tiers'] ) && is_array( $rule['tiers'] ) ? $rule['tiers'] : array();
				usort(
					$tiers,
					function( $a, $b ) {
						return (float) ( isset( $b['min_spend'] ) ? $b['min_spend'] : 0 ) <=> (float) ( isset( $a['min_spend'] ) ? $a['min_spend'] : 0 );
					}
				);

				foreach ( $tiers as $tier ) {
					$min_spend = isset( $tier['min_spend'] ) ? (float) $tier['min_spend'] : 0.0;
					if ( $qualifying_subtotal >= $min_spend ) {
						$t_type = isset( $tier['discount_type'] ) ? $tier['discount_type'] : 'percent';
						$t_val  = isset( $tier['discount_value'] ) ? (float) $tier['discount_value'] : 0.0;

						if ( 'percent' === $t_type ) {
							$discount_amount = $qualifying_subtotal * ( $t_val / 100.0 );
						} else {
							$discount_amount = $t_val;
						}
						break;
					}
				}
			} elseif ( 'bxgy' === $rule_type ) {
				$buy_qty = isset( $rule['buy_qty'] ) ? max( 1, (int) $rule['buy_qty'] ) : 3;
				$get_qty = isset( $rule['get_qty'] ) ? max( 1, (int) $rule['get_qty'] ) : 1;
				$d_val   = isset( $rule['discount_value'] ) ? (float) $rule['discount_value'] : 100.0;

				if ( $qualifying_qty >= $buy_qty ) {
					$unit_prices = array();
					foreach ( $qualifying_items as $item ) {
						$qty   = (int) $item['quantity'];
						$price = (float) $item['data']->get_price();
						for ( $i = 0; $i < $qty; $i++ ) {
							$unit_prices[] = $price;
						}
					}
					sort( $unit_prices );

					$sets           = (int) floor( $qualifying_qty / $buy_qty );
					$free_units_cnt = $sets * $get_qty;

					for ( $i = 0; $i < min( $free_units_cnt, count( $unit_prices ) ); $i++ ) {
						$discount_amount += $unit_prices[ $i ] * ( $d_val / 100.0 );
					}
				}
			} elseif ( 'bulk_qty' === $rule_type ) {
				$min_qty = isset( $rule['min_qty'] ) ? max( 1, (int) $rule['min_qty'] ) : 5;
				if ( $qualifying_qty >= $min_qty ) {
					$d_type = isset( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent';
					$d_val  = isset( $rule['discount_value'] ) ? (float) $rule['discount_value'] : 0.0;

					if ( 'percent' === $d_type ) {
						$discount_amount = $qualifying_subtotal * ( $d_val / 100.0 );
					} elseif ( 'fixed_unit' === $d_type ) {
						$discount_amount = $qualifying_qty * $d_val;
					} else {
						$discount_amount = $d_val;
					}
				}
			} elseif ( 'payment_method' === $rule_type ) {
				$eligible_gateways = isset( $rule['payment_methods'] ) && is_array( $rule['payment_methods'] ) ? $rule['payment_methods'] : array();
				if ( ! empty( $chosen_gw ) && in_array( $chosen_gw, $eligible_gateways, true ) ) {
					$d_type         = isset( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent';
					$d_val          = isset( $rule['discount_value'] ) ? (float) $rule['discount_value'] : 0.0;
					$cart_subtotal  = (float) $cart->get_subtotal();

					if ( 'percent' === $d_type ) {
						$discount_amount = $cart_subtotal * ( $d_val / 100.0 );
					} else {
						$discount_amount = $d_val;
					}
				}
			}

			if ( $discount_amount > 0 ) {
				$discount_amount = min( $discount_amount, (float) $cart->get_subtotal() );
				$fee_id          = 'wpat_promo_' . $rule_id;
				$cart->add_fee( $rule_title, -$discount_amount, true, '' );

				if ( WC()->session ) {
					$applied_fees = (array) WC()->session->get( 'wpat_promo_applied_fees', array() );
					$applied_fees[ sanitize_title( $rule_title ) ] = $rule_id;
					WC()->session->set( 'wpat_promo_applied_fees', $applied_fees );
				}
			}
		}
	}

	/**
	 * Guarda la meta _wpat_promo_rule_id en los items de tarifa del pedido (HPOS / COT Compatible).
	 */
	public function save_fee_item_meta( $item, $fee_key, $fee, $order ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$applied_fees = (array) WC()->session->get( 'wpat_promo_applied_fees', array() );
		$fee_title    = isset( $fee->name ) ? sanitize_title( $fee->name ) : '';

		if ( ! empty( $fee_title ) && isset( $applied_fees[ $fee_title ] ) ) {
			$item->add_meta_data( '_wpat_promo_rule_id', sanitize_key( $applied_fees[ $fee_title ] ), true );
		}
	}

	/**
	 * Genera barra de progreso dinámica para promociones por tramos de gasto en carrito.
	 */
	public function render_tiered_spend_progress_bar() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$rules    = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) ? $settings['woo_promotions_rules'] : array();

		if ( empty( $rules ) ) {
			return;
		}

		$now_ts      = time();
		$target_rule = null;

		foreach ( $rules as $rule ) {
			if ( empty( $rule['active'] ) || '1' !== (string) $rule['active'] || empty( $rule['type'] ) || 'tiered_spend' !== $rule['type'] ) {
				continue;
			}

			$enable_sched = isset( $rule['enable_schedule'] ) ? ( '1' === (string) $rule['enable_schedule'] ) : ( ! empty( $rule['start_date'] ) || ! empty( $rule['end_date'] ) );
			if ( $enable_sched ) {
				if ( ! empty( $rule['start_date'] ) ) {
					$start_ts = self::parse_date_to_timestamp( $rule['start_date'] );
					if ( $start_ts && $now_ts < $start_ts ) {
						continue;
					}
				}
				if ( ! empty( $rule['end_date'] ) ) {
					$end_ts = self::parse_date_to_timestamp( $rule['end_date'], true );
					if ( $end_ts && $now_ts > $end_ts ) {
						continue;
					}
				}
			}

			$target_rule = $rule;
			break;
		}

		if ( ! $target_rule || empty( $target_rule['tiers'] ) ) {
			return;
		}

		$tiers = (array) $target_rule['tiers'];
		usort(
			$tiers,
			function( $a, $b ) {
				return (float) ( isset( $a['min_spend'] ) ? $a['min_spend'] : 0 ) <=> (float) ( isset( $b['min_spend'] ) ? $b['min_spend'] : 0 );
			}
		);

		$current_spend = $this->calc_tiered_spend_qualifying_amount( $target_rule );
		$next_tier     = null;

		foreach ( $tiers as $tier ) {
			$min_spend = isset( $tier['min_spend'] ) ? (float) $tier['min_spend'] : 0.0;
			if ( $current_spend < $min_spend ) {
				$next_tier = $tier;
				break;
			}
		}

		$icon_key = ! empty( $target_rule['icon'] ) ? $target_rule['icon'] : 'gift';
		$symbol   = self::get_promo_icon_symbol( $icon_key );
		if ( empty( $symbol ) ) {
			$symbol = '🎁';
		}

		if ( ! $next_tier ) {
			$last_tier = end( $tiers );
			$d_val     = isset( $last_tier['discount_value'] ) ? $last_tier['discount_value'] : 0;
			$d_type    = isset( $last_tier['discount_type'] ) && 'fixed' === $last_tier['discount_type'] ? '€' : '%';

			echo '<div class="wpat-tiered-progress-card wpat-completed">';
			echo '<div class="wpat-tiered-progress-header">';
			echo '<span class="wpat-tiered-progress-icon">' . esc_html( $symbol ) . '</span>';
			echo '<span class="wpat-tiered-progress-message">¡Felicidades! Has alcanzado el máximo descuento de <strong>' . esc_html( $d_val . $d_type ) . '</strong> en tu pedido.</span>';
			echo '</div>';
			echo '<div class="wpat-tiered-progress-bar-bg"><div class="wpat-tiered-progress-bar-fill" style="width: 100%;"><span class="wpat-tiered-progress-pct-badge">100%</span></div></div>';
			echo '</div>';
		} else {
			$needed_spend = (float) $next_tier['min_spend'];
			$diff         = max( 0, $needed_spend - $current_spend );
			$pct          = min( 100, max( 0, round( ( $current_spend / $needed_spend ) * 100 ) ) );
			$d_val        = isset( $next_tier['discount_value'] ) ? $next_tier['discount_value'] : 0;
			$d_type       = isset( $next_tier['discount_type'] ) && 'fixed' === $next_tier['discount_type'] ? '€' : '%';

			echo '<div class="wpat-tiered-progress-card">';
			echo '<div class="wpat-tiered-progress-header">';
			echo '<span class="wpat-tiered-progress-icon">' . esc_html( $symbol ) . '</span>';
			echo '<span class="wpat-tiered-progress-message">¡Te faltan <strong>' . wc_price( $diff ) . '</strong> para conseguir un <strong>' . esc_html( $d_val . $d_type ) . ' de descuento</strong>!</span>';
			echo '</div>';
			echo '<div class="wpat-tiered-progress-bar-bg"><div class="wpat-tiered-progress-bar-fill" style="width: ' . esc_attr( $pct ) . '%;"><span class="wpat-tiered-progress-pct-badge">' . esc_html( $pct ) . '%</span></div></div>';
			echo '</div>';
		}
	}

	/**
	 * Calcula el subtotal acumulado elegible para la regla de tramos en el carrito.
	 */
	private function calc_tiered_spend_qualifying_amount( $target_rule ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		$cart          = WC()->cart;
		$cart_items    = $cart->get_cart();
		$scope         = ! empty( $target_rule['scope'] ) ? sanitize_key( $target_rule['scope'] ) : 'all';
		$ignore_sale   = ! empty( $target_rule['ignore_on_sale'] ) && '1' === (string) $target_rule['ignore_on_sale'];
		$include_extra = ! empty( $target_rule['include_extra_options'] ) && '1' === (string) $target_rule['include_extra_options'];
		$target_cats   = isset( $target_rule['categories'] ) && is_array( $target_rule['categories'] ) ? array_map( 'absint', $target_rule['categories'] ) : array();
		$target_prods  = isset( $target_rule['products'] ) && is_array( $target_rule['products'] ) ? array_map( 'absint', $target_rule['products'] ) : array();

		$current_spend = 0.0;
		foreach ( $cart_items as $item ) {
			/** @var WC_Product|null $product */
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			if ( $ignore_sale && self::is_product_natively_on_sale( $product ) ) {
				continue;
			}

			$product_id   = $product->get_id();
			$parent_id    = $product->get_parent_id();
			$effective_id = $parent_id ? $parent_id : $product_id;

			$matches = false;
			if ( 'all' === $scope ) {
				$matches = true;
			} elseif ( 'category' === $scope && ! empty( $target_cats ) ) {
				$prod_cats = wc_get_product_term_ids( $effective_id, 'product_cat' );
				if ( array_intersect( $target_cats, $prod_cats ) ) {
					$matches = true;
				}
			} elseif ( 'product' === $scope && ! empty( $target_prods ) ) {
				if ( in_array( $product_id, $target_prods, true ) || in_array( $effective_id, $target_prods, true ) ) {
					$matches = true;
				}
			}

			if ( $matches ) {
				$item_price = (float) $item['line_subtotal'];
				if ( $include_extra && ! empty( $item['wpat_extra_options'] ) ) {
					$item_price = (float) $item['line_total'];
				}
				$current_spend += $item_price;
			}
		}

		return $current_spend;
	}

	/**
	 * Resuelve de forma infalible el objeto WC_Product actual en cualquier contexto (Elementor, Gutenberg, Loops, Shortcodes).
	 *
	 * @param WC_Product|int|null $product_or_id Producto o ID opcional.
	 * @return WC_Product|null
	 */
	public static function resolve_product( $product_or_id = null ) {
		if ( $product_or_id && is_a( $product_or_id, 'WC_Product' ) ) {
			return $product_or_id;
		}

		if ( is_numeric( $product_or_id ) && $product_or_id > 0 ) {
			$p = wc_get_product( $product_or_id );
			if ( $p && is_a( $p, 'WC_Product' ) ) {
				return $p;
			}
		}

		global $product;
		if ( $product && is_a( $product, 'WC_Product' ) ) {
			return $product;
		}

		global $post;
		if ( $post && isset( $post->ID ) && 'product' === get_post_type( $post->ID ) ) {
			$p = wc_get_product( $post->ID );
			if ( $p && is_a( $p, 'WC_Product' ) ) {
				return $p;
			}
		}

		$the_id = function_exists( 'get_the_ID' ) ? get_the_ID() : 0;
		if ( $the_id && 'product' === get_post_type( $the_id ) ) {
			$p = wc_get_product( $the_id );
			if ( $p && is_a( $p, 'WC_Product' ) ) {
				return $p;
			}
		}

		$queried_id = function_exists( 'get_queried_object_id' ) ? get_queried_object_id() : 0;
		if ( $queried_id && 'product' === get_post_type( $queried_id ) ) {
			$p = wc_get_product( $queried_id );
			if ( $p && is_a( $p, 'WC_Product' ) ) {
				return $p;
			}
		}

		return null;
	}

	/**
	 * Encuentra la regla activa de mayor prioridad con contador de tiempo habilitado para un producto.
	 *
	 * @param WC_Product|int $product_or_id Objeto producto o ID.
	 * @return array|null Datos de la regla y timestamp de expiración o null.
	 */
	public function get_matching_countdown_rule_for_product( $product_or_id ) {
		$product = self::resolve_product( $product_or_id );
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return null;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$rules    = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) ? $settings['woo_promotions_rules'] : array();

		if ( empty( $rules ) ) {
			return null;
		}

		$product_id   = $product->get_id();
		$parent_id    = $product->get_parent_id();
		$effective_id = $parent_id ? $parent_id : $product_id;
		$now_ts       = time();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['active'] ) || '1' !== (string) $rule['active'] ) {
				continue;
			}

			if ( empty( $rule['show_countdown'] ) || '1' !== (string) $rule['show_countdown'] ) {
				continue;
			}

			$enable_sched = ! empty( $rule['enable_schedule'] ) && '1' === (string) $rule['enable_schedule'];

			if ( $enable_sched ) {
				if ( ! empty( $rule['start_date'] ) ) {
					$start_ts = self::parse_date_to_timestamp( $rule['start_date'] );
					if ( $start_ts && $now_ts < $start_ts ) {
						continue;
					}
				}

				if ( ! empty( $rule['end_date'] ) ) {
					$end_ts = self::parse_date_to_timestamp( $rule['end_date'], true );
					if ( $end_ts && $now_ts > $end_ts ) {
						continue;
					}
				}
			}

			$ignore_sale = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];
			if ( $ignore_sale && self::is_product_natively_on_sale( $product ) ) {
				continue;
			}

			$scope        = ! empty( $rule['scope'] ) ? sanitize_key( $rule['scope'] ) : 'all';
			$target_cats  = isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? array_map( 'absint', $rule['categories'] ) : array();
			$target_prods = isset( $rule['products'] ) && is_array( $rule['products'] ) ? array_map( 'absint', $rule['products'] ) : array();

			$matches = false;
			if ( 'all' === $scope ) {
				$matches = true;
			} elseif ( 'category' === $scope && ! empty( $target_cats ) ) {
				$prod_cats = wc_get_product_term_ids( $effective_id, 'product_cat' );
				if ( array_intersect( $target_cats, $prod_cats ) ) {
					$matches = true;
				}
			} elseif ( 'product' === $scope && ! empty( $target_prods ) ) {
				if ( in_array( $product_id, $target_prods, true ) || in_array( $effective_id, $target_prods, true ) ) {
					$matches = true;
				}
			}

			if ( $matches ) {
				$rule_end = 0;
				if ( $enable_sched && ! empty( $rule['end_date'] ) ) {
					$rule_end = self::parse_date_to_timestamp( $rule['end_date'], true );
				}

				if ( ! $rule_end || $rule_end <= $now_ts ) {
					$tz        = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
					$today_end = new DateTime( 'today 23:59:59', $tz );
					$rule_end  = $today_end->getTimestamp();
					// Si por alguna razón hoy ya pasó, poner fin de mañana
					if ( $rule_end <= $now_ts ) {
						$rule_end += 86400;
					}
				}

				return array(
					'rule'   => $rule,
					'end_ts' => $rule_end,
				);
			}
		}

		return null;
	}

	/**
	 * Mapea una clave de icono de promoción a su símbolo emoji correspondiente.
	 *
	 * @param string $icon_key Clave del icono (ej. 'gift', 'pumpkin', 'none').
	 * @return string Emoji o cadena vacía si está desactivado ('none').
	 */
	public static function get_promo_icon_symbol( $icon_key ) {
		$icons = array(
			'gift'          => '🎁',
			'fire'          => '🔥',
			'percent'       => '🏷️',
			'heart'         => '❤️',
			'pumpkin'       => '🎃',
			'skull'         => '💀',
			'tree'          => '🎄',
			'santa'         => '🎅',
			'shopping_bags' => '🛍️',
			'star'          => '⭐',
			'crown'         => '👑',
			'lightning'     => '⚡',
			'sun'           => '☀️',
			'flower'        => '🌸',
			'none'          => '',
		);
		return isset( $icons[ $icon_key ] ) ? $icons[ $icon_key ] : '🎁';
	}

	/**
	 * Genera el código HTML del contador regresivo para un producto.
	 *
	 * @param WC_Product|int $product_or_id Producto o ID.
	 * @param bool           $is_loop       Si es visualización compacta en catálogo/loop.
	 * @return string HTML generado o cadena vacía si no aplica.
	 */
	public function get_countdown_banner_html( $product_or_id, $is_loop = false ) {
		static $rendered_ids = array();

		$product = self::resolve_product( $product_or_id );
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		$cache_key = ( $is_loop ? 'loop_' : 'single_' ) . $product->get_id();
		if ( in_array( $cache_key, $rendered_ids, true ) ) {
			return '';
		}

		$data = $this->get_matching_countdown_rule_for_product( $product );
		if ( ! $data ) {
			return '';
		}

		$rendered_ids[] = $cache_key;

		$rule     = $data['rule'];
		$title    = ! empty( $rule['title'] ) ? $rule['title'] : __( 'Oferta por tiempo limitado', 'wp-agency-toolkit' );
		$icon_key = ! empty( $rule['icon'] ) ? $rule['icon'] : 'lightning';
		$symbol   = self::get_promo_icon_symbol( $icon_key );

		$cd_title   = ! empty( $rule['countdown_title'] ) ? $rule['countdown_title'] : __( '¡La oferta termina en!', 'wp-agency-toolkit' );
		$bg_color   = ! empty( $rule['countdown_bg_color'] ) ? $rule['countdown_bg_color'] : '#fff7ed';
		$border_col = ! empty( $rule['countdown_border_color'] ) ? $rule['countdown_border_color'] : '#fed7aa';
		$text_color = ! empty( $rule['countdown_text_color'] ) ? $rule['countdown_text_color'] : '#9a3412';
		$digit_bg   = ! empty( $rule['countdown_digit_bg'] ) ? $rule['countdown_digit_bg'] : '#ffffff';
		$digit_col  = ! empty( $rule['countdown_digit_color'] ) ? $rule['countdown_digit_color'] : '#ea580c';
		$font_size  = ! empty( $rule['countdown_font_size'] ) ? absint( $rule['countdown_font_size'] ) : 14;

		ob_start();

		if ( $is_loop ) :
			?>
			<div class="wpat-promo-countdown-card wpat-promo-shop-loop-card" data-end-timestamp="<?php echo esc_attr( $data['end_ts'] ); ?>" style="margin: 8px 0 !important; padding: 8px 10px !important; background: <?php echo esc_attr( $bg_color ); ?> !important; border: 1px solid <?php echo esc_attr( $border_col ); ?> !important;">
				<div class="wpat-promo-countdown-header" style="margin-bottom: 4px; gap: 5px;">
					<?php if ( ! empty( $symbol ) ) : ?>
						<span class="wpat-promo-countdown-icon" style="font-size: 14px;"><?php echo esc_html( $symbol ); ?></span>
					<?php endif; ?>
					<span class="wpat-promo-countdown-title" style="font-size: 11px; font-weight: 700; color: <?php echo esc_attr( $text_color ); ?> !important;"><?php echo esc_html( $cd_title ); ?></span>
				</div>
				<div class="wpat-promo-countdown-timer" style="gap: 3px; justify-content: center;">
					<div class="wpat-cd-block" style="min-width: 32px; padding: 3px 4px; background: <?php echo esc_attr( $digit_bg ); ?> !important; border-color: <?php echo esc_attr( $border_col ); ?> !important;"><span class="wpat-cd-val wpat-cd-days" style="font-size: 12px; color: <?php echo esc_attr( $digit_col ); ?> !important;">00</span><span class="wpat-cd-lbl" style="font-size: 7.5px; color: <?php echo esc_attr( $text_color ); ?> !important;">Días</span></div>
					<div class="wpat-cd-sep" style="font-size: 11px; color: <?php echo esc_attr( $digit_col ); ?> !important;">:</div>
					<div class="wpat-cd-block" style="min-width: 32px; padding: 3px 4px; background: <?php echo esc_attr( $digit_bg ); ?> !important; border-color: <?php echo esc_attr( $border_col ); ?> !important;"><span class="wpat-cd-val wpat-cd-hours" style="font-size: 12px; color: <?php echo esc_attr( $digit_col ); ?> !important;">00</span><span class="wpat-cd-lbl" style="font-size: 7.5px; color: <?php echo esc_attr( $text_color ); ?> !important;">Horas</span></div>
					<div class="wpat-cd-sep" style="font-size: 11px; color: <?php echo esc_attr( $digit_col ); ?> !important;">:</div>
					<div class="wpat-cd-block" style="min-width: 32px; padding: 3px 4px; background: <?php echo esc_attr( $digit_bg ); ?> !important; border-color: <?php echo esc_attr( $border_col ); ?> !important;"><span class="wpat-cd-val wpat-cd-mins" style="font-size: 12px; color: <?php echo esc_attr( $digit_col ); ?> !important;">00</span><span class="wpat-cd-lbl" style="font-size: 7.5px; color: <?php echo esc_attr( $text_color ); ?> !important;">Min</span></div>
					<div class="wpat-cd-sep" style="font-size: 11px; color: <?php echo esc_attr( $digit_col ); ?> !important;">:</div>
					<div class="wpat-cd-block" style="min-width: 32px; padding: 3px 4px; background: <?php echo esc_attr( $digit_bg ); ?> !important; border-color: <?php echo esc_attr( $border_col ); ?> !important;"><span class="wpat-cd-val wpat-cd-secs" style="font-size: 12px; color: <?php echo esc_attr( $digit_col ); ?> !important;">00</span><span class="wpat-cd-lbl" style="font-size: 7.5px; color: <?php echo esc_attr( $text_color ); ?> !important;">Seg</span></div>
				</div>
			</div>
			<?php
		else :
			?>
			<div class="wpat-promo-countdown-card" data-end-timestamp="<?php echo esc_attr( $data['end_ts'] ); ?>" style="background: <?php echo esc_attr( $bg_color ); ?> !important; border: 1px solid <?php echo esc_attr( $border_col ); ?> !important;">
				<div class="wpat-promo-countdown-header">
					<?php if ( ! empty( $symbol ) ) : ?>
						<span class="wpat-promo-countdown-icon"><?php echo esc_html( $symbol ); ?></span>
					<?php endif; ?>
					<span class="wpat-promo-countdown-title" style="color: <?php echo esc_attr( $text_color ); ?> !important; font-size: <?php echo esc_attr( $font_size ); ?>px !important;"><?php echo esc_html( $title ); ?> — <strong><?php echo esc_html( $cd_title ); ?></strong></span>
				</div>
				<div class="wpat-promo-countdown-timer">
					<div class="wpat-cd-block" style="background: <?php echo esc_attr( $digit_bg ); ?> !important; border-color: <?php echo esc_attr( $border_col ); ?> !important;"><span class="wpat-cd-val wpat-cd-days" style="color: <?php echo esc_attr( $digit_col ); ?> !important;">00</span><span class="wpat-cd-lbl" style="color: <?php echo esc_attr( $text_color ); ?> !important;">Días</span></div>
					<div class="wpat-cd-sep" style="color: <?php echo esc_attr( $digit_col ); ?> !important;">:</div>
					<div class="wpat-cd-block" style="background: <?php echo esc_attr( $digit_bg ); ?> !important; border-color: <?php echo esc_attr( $border_col ); ?> !important;"><span class="wpat-cd-val wpat-cd-hours" style="color: <?php echo esc_attr( $digit_col ); ?> !important;">00</span><span class="wpat-cd-lbl" style="color: <?php echo esc_attr( $text_color ); ?> !important;">Horas</span></div>
					<div class="wpat-cd-sep" style="color: <?php echo esc_attr( $digit_col ); ?> !important;">:</div>
					<div class="wpat-cd-block" style="background: <?php echo esc_attr( $digit_bg ); ?> !important; border-color: <?php echo esc_attr( $border_col ); ?> !important;"><span class="wpat-cd-val wpat-cd-mins" style="color: <?php echo esc_attr( $digit_col ); ?> !important;">00</span><span class="wpat-cd-lbl" style="color: <?php echo esc_attr( $text_color ); ?> !important;">Min</span></div>
					<div class="wpat-cd-sep" style="color: <?php echo esc_attr( $digit_col ); ?> !important;">:</div>
					<div class="wpat-cd-block" style="background: <?php echo esc_attr( $digit_bg ); ?> !important; border-color: <?php echo esc_attr( $border_col ); ?> !important;"><span class="wpat-cd-val wpat-cd-secs" style="color: <?php echo esc_attr( $digit_col ); ?> !important;">00</span><span class="wpat-cd-lbl" style="color: <?php echo esc_attr( $text_color ); ?> !important;">Seg</span></div>
				</div>
			</div>
			<?php
		endif;

		return ob_get_clean();
	}

	/**
	 * Renderiza la tarjeta de cuenta regresiva en la página de producto individual.
	 *
	 * @param WC_Product|int|null $product_or_id Producto opcional.
	 */
	public function render_product_countdown_banner( $product_or_id = null ) {
		$product = self::resolve_product( $product_or_id );
		if ( ! $product ) {
			return;
		}
		echo $this->get_countdown_banner_html( $product, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Renderiza una insignia/banner de cuenta regresiva compacta en los productos del catálogo de la tienda.
	 *
	 * @param WC_Product|int|null $product_or_id Producto opcional.
	 */
	public function render_shop_loop_countdown_banner( $product_or_id = null ) {
		$product = self::resolve_product( $product_or_id );
		if ( ! $product ) {
			return;
		}
		echo $this->get_countdown_banner_html( $product, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Obtiene la regla promocional directa aplicable a un producto.
	 *
	 * @param WC_Product $product Objeto producto.
	 * @return array|null Regla coincidente o null.
	 */
	public function get_matching_promo_rule_for_product( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return null;
		}

		if ( isset( $product->wpat_applied_promo_rule ) && is_array( $product->wpat_applied_promo_rule ) ) {
			return $product->wpat_applied_promo_rule;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$rules    = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) ? $settings['woo_promotions_rules'] : array();

		if ( empty( $rules ) ) {
			return null;
		}

		$product_id   = $product->get_id();
		$parent_id    = $product->get_parent_id();
		$effective_id = $parent_id ? $parent_id : $product_id;
		$now_ts       = time();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['active'] ) || '1' !== (string) $rule['active'] ) {
				continue;
			}

			$rule_type = ! empty( $rule['type'] ) ? sanitize_key( $rule['type'] ) : 'global_discount';
			if ( 'global_discount' !== $rule_type ) {
				continue;
			}

			$enable_sched = isset( $rule['enable_schedule'] ) ? ( '1' === (string) $rule['enable_schedule'] ) : ( ! empty( $rule['start_date'] ) || ! empty( $rule['end_date'] ) );

			if ( $enable_sched ) {
				if ( ! empty( $rule['start_date'] ) ) {
					$start_ts = self::parse_date_to_timestamp( $rule['start_date'] );
					if ( $start_ts && $now_ts < $start_ts ) {
						continue;
					}
				}

				if ( ! empty( $rule['end_date'] ) ) {
					$end_ts = self::parse_date_to_timestamp( $rule['end_date'], true );
					if ( $end_ts && $now_ts > $end_ts ) {
						continue;
					}
				}
			}

			$ignore_sale = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];
			if ( $ignore_sale && self::is_product_natively_on_sale( $product ) ) {
				continue;
			}

			$scope        = ! empty( $rule['scope'] ) ? sanitize_key( $rule['scope'] ) : 'all';
			$target_cats  = isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? array_map( 'absint', $rule['categories'] ) : array();
			$target_prods = isset( $rule['products'] ) && is_array( $rule['products'] ) ? array_map( 'absint', $rule['products'] ) : array();

			$matches = false;
			if ( 'all' === $scope ) {
				$matches = true;
			} elseif ( 'category' === $scope && ! empty( $target_cats ) ) {
				$prod_cats = wc_get_product_term_ids( $effective_id, 'product_cat' );
				if ( array_intersect( $target_cats, $prod_cats ) ) {
					$matches = true;
				}
			} elseif ( 'product' === $scope && ! empty( $target_prods ) ) {
				if ( in_array( $product_id, $target_prods, true ) || in_array( $effective_id, $target_prods, true ) ) {
					$matches = true;
				}
			}

			if ( $matches ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Muestra el Título Público de la promoción en los productos dentro del Carrito y del Checkout.
	 *
	 * @param string $name Nombre formateado del producto.
	 * @param array  $cart_item Datos del elemento en el carrito.
	 * @param string $cart_item_key Clave del elemento.
	 * @return string Nombre formateado con la etiqueta del Título Público de la promoción.
	 */
	public function display_promo_title_in_cart_and_checkout( $name, $cart_item, $cart_item_key ) {
		/** @var WC_Product|null $product */
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return $name;
		}

		$promo_rule = $this->get_matching_promo_rule_for_product( $product );
		if ( ! $promo_rule || empty( $promo_rule['title'] ) ) {
			return $name;
		}

		$title = sanitize_text_field( $promo_rule['title'] );

		// Prevenir duplicación en caso de filtros encadenados
		if ( false !== strpos( $name, 'wpat-cart-promo-tag' ) ) {
			return $name;
		}

		$icon_key = ! empty( $promo_rule['icon'] ) ? $promo_rule['icon'] : 'gift';
		$symbol   = self::get_promo_icon_symbol( $icon_key );

		$tag_html  = '<div class="wpat-cart-promo-tag" style="margin-top: 4px; font-size: 11.5px; font-weight: 700; color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 10px; display: inline-flex; align-items: center; gap: 4px;">';
		if ( ! empty( $symbol ) ) {
			$tag_html .= '<span>' . esc_html( $symbol ) . '</span> ';
		}
		$tag_html .= '<span>' . esc_html( $title ) . '</span>';
		$tag_html .= '</div>';

		return $name . $tag_html;
	}

	/**
	 * Guarda el Título Público de la promoción en los metadatos de la línea de pedido (HPOS / COT Compatible).
	 */
	public function save_line_item_promo_meta( $item, $cart_item_key, $values, $order ) {
		/** @var WC_Product|null $product */
		$product = isset( $values['data'] ) ? $values['data'] : null;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$promo_rule = $this->get_matching_promo_rule_for_product( $product );
		if ( $promo_rule && ! empty( $promo_rule['title'] ) ) {
			$item->add_meta_data( __( 'Promoción Aplicada', 'wp-agency-toolkit' ), sanitize_text_field( $promo_rule['title'] ), true );
		}
	}
}

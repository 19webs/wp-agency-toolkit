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

		// Hook para renderizar contador regresivo en la ficha de producto y catálogo de la tienda
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_countdown_banner' ), 25 );
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_product_countdown_banner' ), 10 );
		add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_product_countdown_banner' ), 10 );
		add_action( 'woocommerce_product_meta_end', array( $this, 'render_product_countdown_banner' ), 10 );

		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'render_shop_loop_countdown_banner' ), 9 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_shop_loop_countdown_banner' ), 15 );

		add_shortcode( 'wpat_promo_countdown', array( $this, 'render_countdown_shortcode' ) );

		// Filtros para mostrar el Título Público de la promoción en Carrito y Checkout
		add_filter( 'woocommerce_cart_item_name', array( $this, 'display_promo_title_in_cart_and_checkout' ), 20, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_line_item_promo_meta' ), 10, 4 );

		// Carga de assets frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Shortcode [wpat_promo_countdown] para renderizar el contador en cualquier maquetador.
	 */
	public function render_countdown_shortcode() {
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}
		ob_start();
		$this->render_product_countdown_banner();
		return ob_get_clean();
	}

	/**
	 * Encola estilos y scripts para el frontend.
	 */
	public function enqueue_frontend_assets() {
		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) || ! function_exists( 'is_product' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpat-woo-promotions-css',
			WPAT_PLUGIN_URL . 'assets/css/wpat-woo-promotions.css',
			array(),
			WPAT_VERSION
		);

		wp_enqueue_script(
			'wpat-woo-promotions-js',
			WPAT_PLUGIN_URL . 'assets/js/wpat-woo-promotions.js',
			array( 'jquery' ),
			WPAT_VERSION,
			true
		);
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
	 * Obtiene el precio promocional rebajado para un producto si coincide con reglas directas de producto.
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

			// Filtro por fecha/hora de inicio y fin
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

			$rule_type   = ! empty( $rule['type'] ) ? sanitize_key( $rule['type'] ) : 'global_discount';
			$scope       = ! empty( $rule['scope'] ) ? sanitize_key( $rule['scope'] ) : 'all';
			$ignore_sale = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];

			// Si la regla ignora productos en oferta y el producto ya tenía rebaja NATIVA previa
			if ( $ignore_sale ) {
				$native_sale_price = (float) $product->get_sale_price();
				if ( $native_sale_price > 0 ) {
					continue;
				}
			}

			// Reglas de descuento directo de producto
			if ( ! in_array( $rule_type, array( 'global_discount', 'bulk_qty' ), true ) ) {
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
				}
			}
		}

		if ( $applied && is_object( $product ) ) {
			$product->wpat_promo_applied = true;
		}

		self::$in_price_filter = false;

		return $applied ? $lowest_price : false;
	}

	/**
	 * Filtra la propiedad is_on_sale para activar el icono/badge de oferta de WooCommerce y WPAT.
	 */
	public function filter_product_is_on_sale( $on_sale, $product ) {
		if ( $on_sale ) {
			return true;
		}

		$promo_price = $this->get_promo_sale_price_for_product( $product );
		if ( false !== $promo_price && $promo_price < (float) $product->get_regular_price() ) {
			return true;
		}

		return $on_sale;
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
	 * Filtra el precio activo del producto.
	 */
	public function filter_product_price( $price, $product ) {
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
	 * Calcula y aplica todas las tarifas promocionales adicionales en el carrito (ej. tramos, 3x2, método de pago).
	 *
	 * @param WC_Cart $cart Objeto del carrito.
	 */
	public function calculate_promotions_fees( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart || $cart->is_empty() ) {
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

		// Calcular subtotal acumulado de los items directamente en memoria
		$all_items_subtotal = 0.0;
		foreach ( $cart_items as $c_item ) {
			/** @var WC_Product|null $prod */
			$prod = isset( $c_item['data'] ) ? $c_item['data'] : null;
			if ( $prod && is_a( $prod, 'WC_Product' ) ) {
				$qty  = max( 1, (int) ( isset( $c_item['quantity'] ) ? $c_item['quantity'] : 1 ) );
				$line = isset( $c_item['line_subtotal'] ) && (float) $c_item['line_subtotal'] > 0
					? (float) $c_item['line_subtotal']
					: ( (float) $prod->get_price() * $qty );
				$all_items_subtotal += $line;
			}
		}

		if ( $all_items_subtotal <= 0 ) {
			return;
		}

		$chosen_payment_method = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$chosen_payment_method = (string) WC()->session->get( 'chosen_payment_method' );
		}

		$applied_meta_map = array();
		$now_ts           = time();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['active'] ) || '1' !== (string) $rule['active'] ) {
				continue;
			}

			// Filtro por fecha/hora de inicio y fin
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

			$rule_id       = ! empty( $rule['id'] ) ? sanitize_key( $rule['id'] ) : 'rule_' . md5( serialize( $rule ) );
			$rule_title    = ! empty( $rule['title'] ) ? sanitize_text_field( $rule['title'] ) : __( 'Descuento Promocional', 'wp-agency-toolkit' );
			$rule_type     = ! empty( $rule['type'] ) ? sanitize_key( $rule['type'] ) : 'global_discount';
			$scope         = ! empty( $rule['scope'] ) ? sanitize_key( $rule['scope'] ) : 'all';
			$ignore_sale   = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];
			$include_extra = ! empty( $rule['include_extra_options'] ) && '1' === (string) $rule['include_extra_options'];

			// Si el descuento ya fue aplicado directamente en el precio del producto (global_discount), no duplicar como fee de carrito
			if ( 'global_discount' === $rule_type ) {
				continue;
			}

			$target_cats  = isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? array_map( 'absint', $rule['categories'] ) : array();
			$target_prods = isset( $rule['products'] ) && is_array( $rule['products'] ) ? array_map( 'absint', $rule['products'] ) : array();

			// Filtrar items del carrito elegibles para esta regla
			$qualifying_items    = array();
			$qualifying_subtotal = 0.0;
			$qualifying_qty      = 0;

			foreach ( $cart_items as $cart_item_key => $item ) {
				/** @var WC_Product|null $product */
				$product = isset( $item['data'] ) ? $item['data'] : null;
				if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
					continue;
				}

				// Exclusión de productos en oferta
				if ( $ignore_sale && $product->is_on_sale() ) {
					continue;
				}

				$product_id   = $product->get_id();
				$parent_id    = $product->get_parent_id();
				$effective_id = $parent_id ? $parent_id : $product_id;

				$matches_scope = false;

				if ( 'all' === $scope ) {
					$matches_scope = true;
				} elseif ( 'category' === $scope && ! empty( $target_cats ) ) {
					$prod_cats = wc_get_product_term_ids( $effective_id, 'product_cat' );
					if ( array_intersect( $target_cats, $prod_cats ) ) {
						$matches_scope = true;
					}
				} elseif ( 'product' === $scope && ! empty( $target_prods ) ) {
					if ( in_array( $product_id, $target_prods, true ) || in_array( $effective_id, $target_prods, true ) ) {
						$matches_scope = true;
					}
				}

				if ( $matches_scope ) {
					$item_qty = max( 1, (int) ( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) );

					// Calcular recargo de campos extras si existen
					$extra_surcharge = 0.0;
					if ( isset( $product->wpat_extra_price ) && (float) $product->wpat_extra_price > 0 ) {
						$extra_surcharge = (float) $product->wpat_extra_price;
					} elseif ( ! empty( $item['wpat_extra_options'] ) && is_array( $item['wpat_extra_options'] ) ) {
						foreach ( $item['wpat_extra_options'] as $opt ) {
							if ( isset( $opt['price'] ) && (float) $opt['price'] > 0 ) {
								$extra_surcharge += (float) $opt['price'];
							}
						}
					}

					$unit_price = (float) $product->get_price();
					if ( ! $include_extra && $extra_surcharge > 0 ) {
						$unit_price = max( 0, $unit_price - $extra_surcharge );
					}

					$line_subtotal        = $unit_price * $item_qty;
					$qualifying_items[]   = array(
						'cart_item'       => $item,
						'unit_price'      => $unit_price,
						'extra_surcharge' => $extra_surcharge,
						'quantity'        => $item_qty,
					);
					$qualifying_subtotal += $line_subtotal;
					$qualifying_qty      += $item_qty;
				}
			}

			if ( empty( $qualifying_items ) && 'payment_method' !== $rule_type ) {
				continue;
			}

			$discount_amount = 0.0;

			switch ( $rule_type ) {

				// 1. Descuento por tramos de gasto (tiered_spend)
				case 'tiered_spend':
					$tiers = isset( $rule['tiers'] ) && is_array( $rule['tiers'] ) ? $rule['tiers'] : array();
					if ( empty( $tiers ) && ! empty( $rule['min_spend'] ) ) {
						$tiers = array(
							array(
								'min_spend'      => (float) $rule['min_spend'],
								'discount_type'  => ! empty( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent',
								'discount_value' => ! empty( $rule['discount_value'] ) ? (float) $rule['discount_value'] : 0.0,
							),
						);
					}

					usort( $tiers, function( $a, $b ) {
						$val_a = isset( $a['min_spend'] ) ? (float) $a['min_spend'] : 0;
						$val_b = isset( $b['min_spend'] ) ? (float) $b['min_spend'] : 0;
						return ( $val_b <=> $val_a );
					} );

					foreach ( $tiers as $tier ) {
						$min_spend = isset( $tier['min_spend'] ) ? (float) $tier['min_spend'] : 0.0;
						if ( $qualifying_subtotal >= $min_spend && $min_spend > 0 ) {
							$d_type = ! empty( $tier['discount_type'] ) ? $tier['discount_type'] : 'percent';
							$d_val  = isset( $tier['discount_value'] ) ? (float) $tier['discount_value'] : 0.0;

							if ( 'percent' === $d_type ) {
								$discount_amount = $qualifying_subtotal * ( $d_val / 100.0 );
							} else {
								$discount_amount = $d_val;
							}
							break;
						}
					}
					break;

				// 2. Compra X, Paga Y / 3x2 (bxgy)
				case 'bxgy':
					$buy_qty   = ! empty( $rule['buy_qty'] ) ? max( 1, (int) $rule['buy_qty'] ) : 3;
					$get_qty   = ! empty( $rule['get_qty'] ) ? max( 1, (int) $rule['get_qty'] ) : 1;
					$d_percent = isset( $rule['discount_value'] ) && (float) $rule['discount_value'] > 0 ? (float) $rule['discount_value'] : 100.0;

					$unit_prices = array();
					foreach ( $qualifying_items as $q_item ) {
						$u_price  = isset( $q_item['unit_price'] ) ? (float) $q_item['unit_price'] : 0.0;
						$item_qty = isset( $q_item['quantity'] ) ? (int) $q_item['quantity'] : 1;

						for ( $i = 0; $i < $item_qty; $i++ ) {
							$unit_prices[] = $u_price;
						}
					}

					sort( $unit_prices, SORT_NUMERIC );
					$total_units    = count( $unit_prices );
					$num_free_units = (int) ( floor( $total_units / $buy_qty ) * $get_qty );

					if ( $num_free_units > 0 ) {
						for ( $i = 0; $i < $num_free_units && $i < $total_units; $i++ ) {
							$discount_amount += $unit_prices[ $i ] * ( $d_percent / 100.0 );
						}
					}
					break;

				// 3. Descuento por volumen (bulk_qty)
				case 'bulk_qty':
					$min_qty = ! empty( $rule['min_qty'] ) ? max( 1, (int) $rule['min_qty'] ) : 1;
					if ( $qualifying_qty >= $min_qty ) {
						$d_type = ! empty( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent';
						$d_val  = isset( $rule['discount_value'] ) ? (float) $rule['discount_value'] : 0.0;

						if ( 'percent' === $d_type ) {
							$discount_amount = $qualifying_subtotal * ( $d_val / 100.0 );
						} elseif ( 'fixed_unit' === $d_type ) {
							$discount_amount = $qualifying_qty * $d_val;
						} else {
							$discount_amount = $d_val;
						}
					}
					break;

				// 4. Descuento por Método de Pago (payment_method)
				case 'payment_method':
					$allowed_methods = isset( $rule['payment_methods'] ) && is_array( $rule['payment_methods'] ) ? $rule['payment_methods'] : array();
					if ( ! empty( $chosen_payment_method ) && in_array( $chosen_payment_method, $allowed_methods, true ) ) {
						$d_type        = ! empty( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent';
						$d_val         = isset( $rule['discount_value'] ) ? (float) $rule['discount_value'] : 0.0;
						$base_subtotal = $qualifying_subtotal > 0 ? $qualifying_subtotal : $all_items_subtotal;

						if ( 'percent' === $d_type ) {
							$discount_amount = $base_subtotal * ( $d_val / 100.0 );
						} else {
							$discount_amount = $d_val;
						}
					}
					break;
			}

			// Limitar el descuento al subtotal calculado de la cesta
			$discount_amount = min( $discount_amount, $all_items_subtotal );

			if ( $discount_amount > 0.001 ) {
				$cart->add_fee( $rule_title, -$discount_amount, true, '' );

				$slug_key                      = sanitize_title( $rule_title );
				$applied_meta_map[ $slug_key ] = $rule_id;
			}
		}

		if ( ! empty( $applied_meta_map ) && function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'wpat_promo_applied_fees', $applied_meta_map );
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
	 * Obtiene los datos estructurados para la barra de progreso de tramos (tiered_spend).
	 */
	public function get_progress_bar_data() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return null;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$rules    = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) ? $settings['woo_promotions_rules'] : array();

		$now_ts      = current_time( 'timestamp' );
		$target_rule = null;
		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['active'] ) && '1' === (string) $rule['active'] && isset( $rule['type'] ) && 'tiered_spend' === $rule['type'] ) {
				if ( ! empty( $rule['start_date'] ) ) {
					$start_ts = strtotime( $rule['start_date'] );
					if ( $start_ts && $now_ts < $start_ts ) {
						continue;
					}
				}
				if ( ! empty( $rule['end_date'] ) ) {
					$end_ts = strtotime( $rule['end_date'] );
					if ( $end_ts && $now_ts > $end_ts ) {
						continue;
					}
				}
				$target_rule = $rule;
				break;
			}
		}

		if ( ! $target_rule ) {
			return null;
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

			if ( $ignore_sale && $product->is_on_sale() ) {
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
				$item_qty        = max( 1, (int) ( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) );
				$extra_surcharge = 0.0;
				if ( isset( $product->wpat_extra_price ) && (float) $product->wpat_extra_price > 0 ) {
					$extra_surcharge = (float) $product->wpat_extra_price;
				} elseif ( ! empty( $item['wpat_extra_options'] ) && is_array( $item['wpat_extra_options'] ) ) {
					foreach ( $item['wpat_extra_options'] as $opt ) {
						if ( isset( $opt['price'] ) && (float) $opt['price'] > 0 ) {
							$extra_surcharge += (float) $opt['price'];
						}
					}
				}

				$unit_price = (float) $product->get_price();
				if ( ! $include_extra && $extra_surcharge > 0 ) {
					$unit_price = max( 0, $unit_price - $extra_surcharge );
				}

				$current_spend += $unit_price * $item_qty;
			}
		}

		$tiers = isset( $target_rule['tiers'] ) && is_array( $target_rule['tiers'] ) ? $target_rule['tiers'] : array();
		if ( empty( $tiers ) && ! empty( $target_rule['min_spend'] ) ) {
			$tiers = array(
				array(
					'min_spend'      => (float) $target_rule['min_spend'],
					'discount_type'  => ! empty( $target_rule['discount_type'] ) ? $target_rule['discount_type'] : 'percent',
					'discount_value' => ! empty( $target_rule['discount_value'] ) ? (float) $target_rule['discount_value'] : 0.0,
				),
			);
		}

		if ( empty( $tiers ) ) {
			return null;
		}

		usort( $tiers, function( $a, $b ) {
			$val_a = isset( $a['min_spend'] ) ? (float) $a['min_spend'] : 0;
			$val_b = isset( $b['min_spend'] ) ? (float) $b['min_spend'] : 0;
			return ( $val_a <=> $val_b );
		} );

		$current_tier  = null;
		$next_tier     = null;

		foreach ( $tiers as $tier ) {
			$threshold = (float) $tier['min_spend'];
			if ( $current_spend >= $threshold ) {
				$current_tier = $tier;
			} else {
				$next_tier = $tier;
				break;
			}
		}

		if ( $next_tier ) {
			$needed     = (float) $next_tier['min_spend'] - $current_spend;
			$target_val = (float) $next_tier['min_spend'];
			$pct        = min( 100, max( 0, ( $current_spend / $target_val ) * 100 ) );

			$d_type  = ! empty( $next_tier['discount_type'] ) ? $next_tier['discount_type'] : 'percent';
			$d_val   = (float) $next_tier['discount_value'];
			$reward  = 'percent' === $d_type ? $d_val . '%' : wc_price( $d_val );

			$message = sprintf(
				__( '¡Añade %s más para conseguir un %s de descuento!', 'wp-agency-toolkit' ),
				'<strong>' . wc_price( $needed ) . '</strong>',
				'<strong>' . $reward . '</strong>'
			);

			return array(
				'percentage'    => round( $pct, 1 ),
				'current_spend' => $current_spend,
				'target_spend'  => $target_val,
				'needed_spend'  => $needed,
				'message'       => $message,
				'completed'     => false,
			);
		} else {
			$d_type = ! empty( $current_tier['discount_type'] ) ? $current_tier['discount_type'] : 'percent';
			$d_val  = isset( $current_tier['discount_value'] ) ? (float) $current_tier['discount_value'] : 0.0;
			$reward = 'percent' === $d_type ? $d_val . '%' : wc_price( $d_val );

			$message = sprintf(
				__( '🎉 ¡Felicidades! Has alcanzado el máximo descuento del %s', 'wp-agency-toolkit' ),
				'<strong>' . $reward . '</strong>'
			);

			return array(
				'percentage'    => 100,
				'current_spend' => $current_spend,
				'target_spend'  => $current_spend,
				'needed_spend'  => 0,
				'message'       => $message,
				'completed'     => true,
			);
		}
	}

	/**
	 * Renderiza el widget de la barra de progreso en la página de carrito.
	 */
	public function render_tiered_spend_progress_bar() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}

		$data = $this->get_progress_bar_data();
		if ( ! $data ) {
			return;
		}

		$rendered = true;
		?>
		<div id="wpat-tiered-progress-container" class="wpat-tiered-progress-card <?php echo $data['completed'] ? 'wpat-completed' : ''; ?>">
			<div class="wpat-tiered-progress-header">
				<span class="wpat-tiered-progress-icon">🎁</span>
				<div class="wpat-tiered-progress-message">
					<?php echo wp_kses_post( $data['message'] ); ?>
				</div>
			</div>
			<div class="wpat-tiered-progress-bar-bg">
				<div class="wpat-tiered-progress-bar-fill" style="width: <?php echo esc_attr( $data['percentage'] ); ?>%;">
					<span class="wpat-tiered-progress-pct-badge"><?php echo esc_html( round( $data['percentage'] ) ); ?>%</span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Busca la regla promocional activa con cuenta regresiva habilitada para un producto.
	 *
	 * @param WC_Product $product Objeto producto.
	 * @return array|null Datos de la regla y timestamp de expiración o null.
	 */
	public function get_matching_countdown_rule_for_product( $product ) {
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

			$rule_start = ! empty( $rule['start_date'] ) ? self::parse_date_to_timestamp( $rule['start_date'] ) : 0;
			if ( $rule_start > 0 && $now_ts < $rule_start ) {
				continue;
			}

			if ( ! empty( $rule['end_date'] ) ) {
				$rule_end = self::parse_date_to_timestamp( $rule['end_date'], true );
			} else {
				// Si la opción de contador está activa pero no se especificó fecha de fin, usar el final del día de hoy como límite por defecto
				$tz        = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
				$today_end = new DateTime( 'today 23:59:59', $tz );
				$rule_end  = $today_end->getTimestamp();
			}

			if ( ! $rule_end || $now_ts > $rule_end ) {
				continue;
			}

			$ignore_sale = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];
			if ( $ignore_sale && $product->is_on_sale() ) {
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
	 * Renderiza la tarjeta de cuenta regresiva en la página de producto individual.
	 */
	public function render_product_countdown_banner() {
		static $rendered_ids = array();
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		if ( in_array( $product->get_id(), $rendered_ids, true ) ) {
			return;
		}

		$data = $this->get_matching_countdown_rule_for_product( $product );
		if ( ! $data ) {
			return;
		}

		$rendered_ids[] = $product->get_id();
		$title          = ! empty( $data['rule']['title'] ) ? $data['rule']['title'] : __( 'Oferta por tiempo limitado', 'wp-agency-toolkit' );
		$icon_key       = ! empty( $data['rule']['icon'] ) ? $data['rule']['icon'] : 'lightning';
		$symbol         = self::get_promo_icon_symbol( $icon_key );

		?>
		<div class="wpat-promo-countdown-card" data-end-timestamp="<?php echo esc_attr( $data['end_ts'] ); ?>">
			<div class="wpat-promo-countdown-header">
				<?php if ( ! empty( $symbol ) ) : ?>
					<span class="wpat-promo-countdown-icon"><?php echo esc_html( $symbol ); ?></span>
				<?php endif; ?>
				<span class="wpat-promo-countdown-title"><?php echo esc_html( $title ); ?> — <strong>¡La oferta termina en!</strong></span>
			</div>
			<div class="wpat-promo-countdown-timer">
				<div class="wpat-cd-block"><span class="wpat-cd-val wpat-cd-days">00</span><span class="wpat-cd-lbl">Días</span></div>
				<div class="wpat-cd-sep">:</div>
				<div class="wpat-cd-block"><span class="wpat-cd-val wpat-cd-hours">00</span><span class="wpat-cd-lbl">Horas</span></div>
				<div class="wpat-cd-sep">:</div>
				<div class="wpat-cd-block"><span class="wpat-cd-val wpat-cd-mins">00</span><span class="wpat-cd-lbl">Min</span></div>
				<div class="wpat-cd-sep">:</div>
				<div class="wpat-cd-block"><span class="wpat-cd-val wpat-cd-secs">00</span><span class="wpat-cd-lbl">Seg</span></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza una insignia/banner de cuenta regresiva compacta en los productos del catálogo de la tienda.
	 */
	public function render_shop_loop_countdown_banner() {
		global $product;
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		$data = $this->get_matching_countdown_rule_for_product( $product );
		if ( ! $data ) {
			return;
		}

		$icon_key = ! empty( $data['rule']['icon'] ) ? $data['rule']['icon'] : 'lightning';
		$symbol   = self::get_promo_icon_symbol( $icon_key );

		?>
		<div class="wpat-promo-countdown-card wpat-promo-shop-loop-card" data-end-timestamp="<?php echo esc_attr( $data['end_ts'] ); ?>" style="margin: 8px 0 !important; padding: 8px 10px !important;">
			<div class="wpat-promo-countdown-header" style="margin-bottom: 4px; gap: 5px;">
				<?php if ( ! empty( $symbol ) ) : ?>
					<span class="wpat-promo-countdown-icon" style="font-size: 14px;"><?php echo esc_html( $symbol ); ?></span>
				<?php endif; ?>
				<span class="wpat-promo-countdown-title" style="font-size: 11px; font-weight: 700; color: #9a3412;">¡Oferta termina en!</span>
			</div>
			<div class="wpat-promo-countdown-timer" style="gap: 3px; justify-content: center;">
				<div class="wpat-cd-block" style="min-width: 32px; padding: 3px 4px;"><span class="wpat-cd-val wpat-cd-days" style="font-size: 12px;">00</span><span class="wpat-cd-lbl" style="font-size: 7.5px;">Días</span></div>
				<div class="wpat-cd-sep" style="font-size: 11px;">:</div>
				<div class="wpat-cd-block" style="min-width: 32px; padding: 3px 4px;"><span class="wpat-cd-val wpat-cd-hours" style="font-size: 12px;">00</span><span class="wpat-cd-lbl" style="font-size: 7.5px;">Horas</span></div>
				<div class="wpat-cd-sep" style="font-size: 11px;">:</div>
				<div class="wpat-cd-block" style="min-width: 32px; padding: 3px 4px;"><span class="wpat-cd-val wpat-cd-mins" style="font-size: 12px;">00</span><span class="wpat-cd-lbl" style="font-size: 7.5px;">Min</span></div>
				<div class="wpat-cd-sep" style="font-size: 11px;">:</div>
				<div class="wpat-cd-block" style="min-width: 32px; padding: 3px 4px;"><span class="wpat-cd-val wpat-cd-secs" style="font-size: 12px;">00</span><span class="wpat-cd-lbl" style="font-size: 7.5px;">Seg</span></div>
			</div>
		</div>
		<?php
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

		$settings = WPAT_Main::get_instance()->get_settings();
		$rules    = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) ? $settings['woo_promotions_rules'] : array();

		if ( empty( $rules ) ) {
			return null;
		}

		$product_id   = $product->get_id();
		$parent_id    = $product->get_parent_id();
		$effective_id = $parent_id ? $parent_id : $product_id;
		$now_ts       = current_time( 'timestamp' );

		foreach ( $rules as $rule ) {
			if ( empty( $rule['active'] ) || '1' !== (string) $rule['active'] ) {
				continue;
			}

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

			$ignore_sale = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];
			if ( $ignore_sale && $product->is_on_sale() ) {
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

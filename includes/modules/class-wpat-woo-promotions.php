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

		// Hook principal para cálculo de tarifas/descuentos en carrito
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_promotions_fees' ), 20, 1 );

		// Trazabilidad contable en checkout / HPOS (Order Fee Item Meta)
		add_action( 'woocommerce_checkout_create_order_fee_item', array( $this, 'save_fee_item_meta' ), 10, 4 );

		// Inyección de la barra de progreso interactiva en carrito
		add_action( 'woocommerce_before_cart_totals', array( $this, 'render_tiered_spend_progress_bar' ), 10 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'render_tiered_spend_progress_bar' ), 5 );

		// Carga de assets frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Encola estilos y scripts para el frontend.
	 */
	public function enqueue_frontend_assets() {
		if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return;
		}

		if ( is_cart() || is_checkout() ) {
			wp_enqueue_style(
				'wpat-woo-promotions',
				WPAT_URL . 'assets/css/wpat-woo-promotions.css',
				array(),
				WPAT_VERSION
			);

			wp_enqueue_script(
				'wpat-woo-promotions',
				WPAT_URL . 'assets/js/wpat-woo-promotions.js',
				array( 'jquery' ),
				WPAT_VERSION,
				true
			);

			$promo_data = $this->get_progress_bar_data();

			wp_localize_script(
				'wpat-woo-promotions',
				'wpatPromoData',
				array(
					'ajaxurl'     => admin_url( 'admin-ajax.php' ),
					'currency'    => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '€',
					'progressBar' => $promo_data,
				)
			);
		}
	}

	/**
	 * Calcula y aplica todas las tarifas promocionales negativas en el carrito.
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

		foreach ( $rules as $rule ) {
			if ( empty( $rule['active'] ) || '1' !== (string) $rule['active'] ) {
				continue;
			}

			$rule_id     = ! empty( $rule['id'] ) ? sanitize_key( $rule['id'] ) : 'rule_' . md5( serialize( $rule ) );
			$rule_title  = ! empty( $rule['title'] ) ? sanitize_text_field( $rule['title'] ) : __( 'Descuento Promocional', 'wp-agency-toolkit' );
			$rule_type   = ! empty( $rule['type'] ) ? sanitize_key( $rule['type'] ) : 'global_discount';
			$scope       = ! empty( $rule['scope'] ) ? sanitize_key( $rule['scope'] ) : 'all';
			$ignore_sale = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];

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
					$item_qty             = max( 1, (int) ( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) );
					$line_subtotal        = isset( $item['line_subtotal'] ) && (float) $item['line_subtotal'] > 0
						? (float) $item['line_subtotal']
						: ( (float) $product->get_price() * $item_qty );
					$qualifying_items[]   = $item;
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

					// Ordenar tramos de mayor a menor umbral de gasto
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
						/** @var WC_Product|null $p */
						$p          = isset( $q_item['data'] ) ? $q_item['data'] : null;
						$item_qty   = max( 1, (int) ( isset( $q_item['quantity'] ) ? $q_item['quantity'] : 1 ) );
						$unit_price = isset( $q_item['line_subtotal'] ) && (float) $q_item['line_subtotal'] > 0
							? ( (float) $q_item['line_subtotal'] / $item_qty )
							: ( $p ? (float) $p->get_price() : 0.0 );

						for ( $i = 0; $i < $item_qty; $i++ ) {
							$unit_prices[] = $unit_price;
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

				// 4. Descuento Porcentual / Fijo Global (global_discount)
				case 'global_discount':
					$d_type = ! empty( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent';
					$d_val  = isset( $rule['discount_value'] ) ? (float) $rule['discount_value'] : 0.0;

					if ( 'percent' === $d_type ) {
						$discount_amount = $qualifying_subtotal * ( $d_val / 100.0 );
					} else {
						$discount_amount = $d_val;
					}
					break;

				// 5. Descuento por Método de Pago (payment_method)
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

		$target_rule = null;
		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['active'] ) && '1' === (string) $rule['active'] && isset( $rule['type'] ) && 'tiered_spend' === $rule['type'] ) {
				$target_rule = $rule;
				break;
			}
		}

		if ( ! $target_rule ) {
			return null;
		}

		$cart        = WC()->cart;
		$cart_items  = $cart->get_cart();
		$scope       = ! empty( $target_rule['scope'] ) ? sanitize_key( $target_rule['scope'] ) : 'all';
		$ignore_sale = ! empty( $target_rule['ignore_on_sale'] ) && '1' === (string) $target_rule['ignore_on_sale'];
		$target_cats = isset( $target_rule['categories'] ) && is_array( $target_rule['categories'] ) ? array_map( 'absint', $target_rule['categories'] ) : array();
		$target_prods = isset( $target_rule['products'] ) && is_array( $target_rule['products'] ) ? array_map( 'absint', $target_rule['products'] ) : array();

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
				$item_qty       = max( 1, (int) ( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) );
				$current_spend += isset( $item['line_subtotal'] ) && (float) $item['line_subtotal'] > 0
					? (float) $item['line_subtotal']
					: ( (float) $product->get_price() * $item_qty );
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
}

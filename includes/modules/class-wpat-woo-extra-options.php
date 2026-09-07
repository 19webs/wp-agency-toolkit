<?php
/**
 * Módulo: Opciones Extra en Productos y Swatches (WooCommerce) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Extra_Options {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Extra_Options
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Extra_Options
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
		// Renderizar campos en la página del producto
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_product_extra_fields' ), 20 );

		// Guardar datos de opciones en el elemento del carrito
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );

		// Ajustar precio dinámico en el carrito
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_extra_option_prices' ), 10, 1 );

		// Mostrar metadatos en el carrito y en el checkout
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_extra_options_in_cart' ), 10, 2 );

		// Guardar metadatos en la línea de pedido al finalizar compra
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_extra_options_to_order_items' ), 10, 4 );

		// Validar campos obligatorios al añadir al carrito
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_extra_fields' ), 10, 3 );
	}

	/**
	 * Obtiene las reglas de opciones extra aplicables a un producto.
	 *
	 * @param int $product_id ID del producto.
	 * @return array
	 */
	public function get_applicable_rules( $product_id ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-extra-options'] ) || empty( $settings['extra_options_enabled'] ) ) {
			return array();
		}

		$all_rules = isset( $settings['extra_options_rules'] ) && is_array( $settings['extra_options_rules'] ) ? $settings['extra_options_rules'] : array();
		$matching_rules = array();

		$product_cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $product_cats ) ) {
			$product_cats = array();
		}

		foreach ( $all_rules as $rule ) {
			if ( empty( $rule['enabled'] ) || '1' !== $rule['enabled'] ) {
				continue;
			}

			$scope = ! empty( $rule['scope'] ) ? $rule['scope'] : 'global';

			if ( 'global' === $scope ) {
				$matching_rules[] = $rule;
			} elseif ( 'category' === $scope && ! empty( $rule['categories'] ) && is_array( $rule['categories'] ) ) {
				if ( array_intersect( $product_cats, array_map( 'intval', $rule['categories'] ) ) ) {
					$matching_rules[] = $rule;
				}
			} elseif ( 'product' === $scope && ! empty( $rule['products'] ) && is_array( $rule['products'] ) ) {
				if ( in_array( intval( $product_id ), array_map( 'intval', $rule['products'] ), true ) ) {
					$matching_rules[] = $rule;
				}
			}
		}

		return $matching_rules;
	}

	/**
	 * Renderiza los campos extra en la página de producto.
	 */
	public function render_product_extra_fields() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$rules = $this->get_applicable_rules( $product->get_id() );
		if ( empty( $rules ) ) {
			return;
		}

		echo '<div class="wpat-extra-options-wrapper" style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">';

		foreach ( $rules as $rule_idx => $rule ) {
			$fields = isset( $rule['fields'] ) && is_array( $rule['fields'] ) ? $rule['fields'] : array();
			if ( empty( $fields ) ) {
				continue;
			}

			if ( ! empty( $rule['title'] ) ) {
				echo '<h4 style="margin: 0 0 12px 0; font-size: 15px; font-weight: 700; color: #0f172a;">' . esc_html( $rule['title'] ) . '</h4>';
			}

			foreach ( $fields as $f_idx => $field ) {
				if ( empty( $field['label'] ) ) {
					continue;
				}

				$field_id     = 'wpat_extra_' . $rule_idx . '_' . $f_idx;
				$type         = ! empty( $field['type'] ) ? $field['type'] : 'text';
				$label        = esc_html( $field['label'] );
				$required     = ! empty( $field['required'] ) && '1' === $field['required'];
				$price        = ! empty( $field['price'] ) ? floatval( $field['price'] ) : 0;
				$max_length   = ! empty( $field['max_length'] ) ? intval( $field['max_length'] ) : 0;

				$price_html = $price > 0 ? ' <span class="wpat-extra-price" style="color: #2563eb; font-weight: 600;">(+' . wc_price( $price ) . ')</span>' : '';
				$req_html   = $required ? ' <span class="required" style="color:#ef4444;">*</span>' : '';

				echo '<div class="wpat-extra-field-group" style="margin-bottom: 14px;">';
				echo '<label for="' . esc_attr( $field_id ) . '" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13.5px;">' . $label . $price_html . $req_html . '</label>';

				if ( 'text' === $type ) {
					$max_attr = $max_length > 0 ? ' maxlength="' . $max_length . '"' : '';
					echo '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" class="wpat-extra-input" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="' . esc_attr( ! empty( $field['placeholder'] ) ? $field['placeholder'] : '' ) . '"' . $max_attr . ' />';
					if ( $max_length > 0 ) {
						echo '<small style="display:block; color:#64748b; margin-top:2px;">Máximo ' . $max_length . ' caracteres</small>';
					}
				} elseif ( 'textarea' === $type ) {
					$max_attr = $max_length > 0 ? ' maxlength="' . $max_length . '"' : '';
					echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" class="wpat-extra-input" rows="3" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="' . esc_attr( ! empty( $field['placeholder'] ) ? $field['placeholder'] : '' ) . '"' . $max_attr . '></textarea>';
				} elseif ( 'select' === $type ) {
					$options = ! empty( $field['options'] ) ? preg_split( '/\r\n|\r|\n/', $field['options'] ) : array();
					echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" class="wpat-extra-input" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">';
					echo '<option value="">' . esc_html__( '-- Seleccionar --', 'wp-agency-toolkit' ) . '</option>';
					foreach ( $options as $opt ) {
						$opt = trim( $opt );
						if ( ! empty( $opt ) ) {
							$parts      = explode( '|', $opt );
							$opt_name   = trim( $parts[0] );
							$opt_price  = isset( $parts[1] ) ? floatval( trim( $parts[1] ) ) : $price;
							$opt_disp   = $opt_name;
							if ( $opt_price > 0 ) {
								$opt_disp .= ' (+' . wc_price( $opt_price ) . ')';
							}
							$value_attr = $opt_name . ( $opt_price > 0 ? '|' . $opt_price : '' );
							echo '<option value="' . esc_attr( $value_attr ) . '">' . esc_html( wp_strip_all_tags( $opt_disp ) ) . '</option>';
						}
					}
					echo '</select>';
				} elseif ( 'swatch' === $type ) {
					// Muestrario de Color (Color Swatches)
					$swatches = ! empty( $field['swatches'] ) ? preg_split( '/\r\n|\r|\n/', $field['swatches'] ) : array();
					echo '<div class="wpat-swatch-container" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 6px;">';
					echo '<input type="hidden" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" value="" />';

					foreach ( $swatches as $s_idx => $sw ) {
						$parts      = explode( '|', trim( $sw ) );
						$color_name = isset( $parts[0] ) ? trim( $parts[0] ) : '';
						$color_hex  = isset( $parts[1] ) ? trim( $parts[1] ) : '#2563eb';
						$color_price= isset( $parts[2] ) ? floatval( trim( $parts[2] ) ) : $price;

						if ( empty( $color_name ) ) {
							continue;
						}

						$val_attr = $color_name . ( $color_price > 0 ? '|' . $color_price : '' );
						$title_text = $color_name . ( $color_price > 0 ? ' (+' . wc_price( $color_price ) . ')' : '' );

						echo '<button type="button" class="wpat-swatch-btn" data-target="' . esc_attr( $field_id ) . '" data-value="' . esc_attr( $val_attr ) . '" data-name="' . esc_attr( $color_name ) . '" data-price="' . esc_attr( $color_price ) . '" title="' . esc_attr( wp_strip_all_tags( $title_text ) ) . '" style="width: 34px; height: 34px; border-radius: 50%; background-color: ' . esc_attr( $color_hex ) . '; border: 2px solid #ffffff; box-shadow: 0 0 0 1px #cbd5e1; cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease; padding:0; outline:none;"></button>';
					}
					echo '</div>';
					echo '<span class="wpat-swatch-selected-label" id="' . esc_attr( $field_id ) . '_label" style="font-size: 12px; font-weight: 600; color: #334155; margin-top: 6px; display: block; min-height: 18px;"></span>';
				} elseif ( 'checkbox' === $type ) {
					echo '<label style="font-weight: normal; cursor: pointer;">';
					echo '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" value="1" /> ' . esc_html( ! empty( $field['placeholder'] ) ? $field['placeholder'] : 'Activar esta opción' );
					echo '</label>';
				}

				echo '</div>';
			}
		}

		echo '</div>';

		// Script para interactividad de Swatches
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var swatchBtns = document.querySelectorAll('.wpat-swatch-btn');
			swatchBtns.forEach(function(btn) {
				btn.addEventListener('click', function() {
					var targetId = this.getAttribute('data-target');
					var valAttr = this.getAttribute('data-value');
					var colorName = this.getAttribute('data-name');
					var priceVal = parseFloat(this.getAttribute('data-price') || 0);
					var hiddenInput = document.getElementById(targetId);
					var labelSpan = document.getElementById(targetId + '_label');

					var container = this.closest('.wpat-swatch-container');
					if (container) {
						container.querySelectorAll('.wpat-swatch-btn').forEach(function(b) {
							b.style.boxShadow = '0 0 0 1px #cbd5e1';
							b.style.transform = 'scale(1)';
						});
					}

					if (hiddenInput && hiddenInput.value === valAttr) {
						// Deseleccionar si ya estaba marcado
						hiddenInput.value = '';
						if (labelSpan) labelSpan.textContent = '';
					} else {
						if (hiddenInput) hiddenInput.value = valAttr;
						this.style.boxShadow = '0 0 0 2.5px #2563eb';
						this.style.transform = 'scale(1.12)';
						if (labelSpan) {
							var labelText = 'Seleccionado: ' + colorName;
							if (priceVal > 0) {
								labelText += ' (+' + priceVal.toFixed(2) + ' €)';
							}
							labelSpan.textContent = labelText;
						}
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Valida que los campos obligatorios se hayan rellenado antes de añadir al carrito.
	 */
	public function validate_extra_fields( $passed, $product_id, $quantity ) {
		$rules = $this->get_applicable_rules( $product_id );
		if ( empty( $rules ) ) {
			return $passed;
		}

		foreach ( $rules as $rule_idx => $rule ) {
			$fields = isset( $rule['fields'] ) && is_array( $rule['fields'] ) ? $rule['fields'] : array();
			foreach ( $fields as $f_idx => $field ) {
				$field_id = 'wpat_extra_' . $rule_idx . '_' . $f_idx;
				$required = ! empty( $field['required'] ) && '1' === $field['required'];

				if ( $required && empty( $_POST[ $field_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					wc_add_notice( sprintf( __( 'Por favor, rellena el campo obligatorio: <strong>%s</strong>.', 'wp-agency-toolkit' ), esc_html( $field['label'] ) ), 'error' );
					return false;
				}
			}
		}

		return $passed;
	}

	/**
	 * Guarda los datos de las opciones extra en el elemento del carrito.
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$rules = $this->get_applicable_rules( $product_id );
		if ( empty( $rules ) ) {
			return $cart_item_data;
		}

		$extra_options = array();

		foreach ( $rules as $rule_idx => $rule ) {
			$fields = isset( $rule['fields'] ) && is_array( $rule['fields'] ) ? $rule['fields'] : array();
			foreach ( $fields as $f_idx => $field ) {
				$field_id = 'wpat_extra_' . $rule_idx . '_' . $f_idx;
				if ( isset( $_POST[ $field_id ] ) && '' !== trim( wp_unslash( $_POST[ $field_id ] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$raw_val = sanitize_text_field( wp_unslash( $_POST[ $field_id ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$base_price = ! empty( $field['price'] ) ? floatval( $field['price'] ) : 0;

					$opt_label_display = $raw_val;
					$opt_price         = $base_price;

					if ( strpos( $raw_val, '|' ) !== false ) {
						$parts = explode( '|', $raw_val );
						$opt_label_display = trim( $parts[0] );
						$opt_price         = isset( $parts[1] ) ? floatval( trim( $parts[1] ) ) : $base_price;
					}

					$extra_options[] = array(
						'label' => sanitize_text_field( $field['label'] ),
						'value' => $opt_label_display,
						'price' => $opt_price,
					);
				}
			}
		}

		if ( ! empty( $extra_options ) ) {
			$cart_item_data['wpat_extra_options'] = $extra_options;
		}

		return $cart_item_data;
	}

	/**
	 * Calcula el precio dinámico sumando el coste de las opciones extra.
	 */
	public function calculate_extra_option_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['wpat_extra_options'] ) && is_array( $cart_item['wpat_extra_options'] ) ) {
				$extra_price = 0;
				foreach ( $cart_item['wpat_extra_options'] as $opt ) {
					if ( ! empty( $opt['price'] ) ) {
						$extra_price += floatval( $opt['price'] );
					}
				}
				if ( $extra_price > 0 ) {
					$base_price = $cart_item['data']->get_price();
					$cart_item['data']->set_price( $base_price + $extra_price );
				}
			}
		}
	}

	/**
	 * Muestra las opciones extra seleccionadas en el carrito y en el checkout.
	 */
	public function display_extra_options_in_cart( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['wpat_extra_options'] ) && is_array( $cart_item['wpat_extra_options'] ) ) {
			foreach ( $cart_item['wpat_extra_options'] as $opt ) {
				$val_display = esc_html( $opt['value'] );
				if ( ! empty( $opt['price'] ) && $opt['price'] > 0 ) {
					$val_display .= ' (+' . wc_price( $opt['price'] ) . ')';
				}

				$item_data[] = array(
					'name'  => esc_html( $opt['label'] ),
					'value' => $val_display,
				);
			}
		}
		return $item_data;
	}

	/**
	 * Guarda las opciones extra en los metadatos de la línea del pedido para Admin, Emails y API REST (CRM).
	 */
	public function add_extra_options_to_order_items( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['wpat_extra_options'] ) && is_array( $values['wpat_extra_options'] ) ) {
			foreach ( $values['wpat_extra_options'] as $opt ) {
				$meta_value = $opt['value'];
				if ( ! empty( $opt['price'] ) && $opt['price'] > 0 ) {
					$meta_value .= ' (+' . wc_price( $opt['price'] ) . ')';
				}

				$item->add_meta_data( $opt['label'], $meta_value, true );
			}
		}
	}
}

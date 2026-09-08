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

		// Restaurar metadatos desde la sesión y asignar precio dinámico en cada petición
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );

		// Asignar precio dinámico inmediatamente al añadir al carrito
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );

		// Recalcular precio dinámico en el carrito (prioridad 99)
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_extra_option_prices' ), 99, 1 );

		// Filtros universales de precio para el objeto WC_Product (prioridad 99)
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_product_get_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_product_get_price' ), 99, 2 );

		// Filtros de formato HTML de precio y subtotal en tablas de carrito
		add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price' ), 99, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'filter_cart_item_subtotal' ), 99, 3 );

		// Mostrar metadatos en el carrito y en el checkout
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_extra_options_in_cart' ), 10, 2 );

		// Guardar metadatos en la línea de pedido al finalizar compra
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_extra_options_to_order_items' ), 10, 4 );

		// Validar campos obligatorios al añadir al carrito
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_extra_fields' ), 10, 3 );

		// Añadir enctype multipart al formulario de producto para soporte de subida de archivos
		add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'add_multipart_to_form' ) );
	}

	/**
	 * Asegura que el formulario de añadir al carrito soporte subida de archivos.
	 */
	public function add_multipart_to_form() {
		echo '<script>document.addEventListener("DOMContentLoaded", function() { var form = document.querySelector("form.cart"); if(form) form.setAttribute("enctype", "multipart/form-data"); });</script>';
	}

	/**
	 * Mapea nombres comunes de color en español e inglés a su valor Hexadecimal.
	 *
	 * @param string $raw_color Nombre de color o código hex.
	 * @param string $default_hex Color por defecto si no coincide.
	 * @return string Código Hex.
	 */
	public static function parse_color_hex( $raw_color, $default_hex = '#2563eb' ) {
		$color = strtolower( trim( $raw_color ) );
		if ( empty( $color ) ) {
			return $default_hex;
		}

		if ( preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $color ) ) {
			return $color;
		}

		$map = array(
			'rojo'     => '#ef4444',
			'red'      => '#ef4444',
			'azul'     => '#2563eb',
			'blue'     => '#2563eb',
			'verde'    => '#22c55e',
			'green'    => '#22c55e',
			'negro'    => '#0f172a',
			'black'    => '#0f172a',
			'blanco'   => '#ffffff',
			'white'    => '#ffffff',
			'amarillo' => '#eab308',
			'yellow'   => '#eab308',
			'naranja'  => '#f97316',
			'orange'   => '#f97316',
			'rosa'     => '#ec4899',
			'pink'     => '#ec4899',
			'gris'     => '#64748b',
			'gray'     => '#64748b',
			'morado'   => '#a855f7',
			'purple'   => '#a855f7',
			'violeta'  => '#8b5cf6',
			'oro'      => '#ffd700',
			'gold'     => '#ffd700',
			'plata'    => '#c0c0c0',
			'silver'   => '#c0c0c0',
			'marrón'   => '#78350f',
			'marron'   => '#78350f',
			'brown'    => '#78350f',
			'cyan'     => '#06b6d4',
			'turquesa' => '#14b8a6',
		);

		return isset( $map[ $color ] ) ? $map[ $color ] : $default_hex;
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
				$default_val  = isset( $field['default_val'] ) ? trim( $field['default_val'] ) : '';

				// Comprobar si las opciones tienen precios individuales por línea
				$has_option_prices = false;
				if ( in_array( $type, array( 'select', 'radio', 'swatch' ), true ) ) {
					$raw_opts_text = ( 'swatch' === $type ) ? ( ! empty( $field['swatches'] ) ? $field['swatches'] : '' ) : ( ! empty( $field['options'] ) ? $field['options'] : '' );
					if ( strpos( $raw_opts_text, '|' ) !== false ) {
						$has_option_prices = true;
					}
				}

				$price_html = ( ! $has_option_prices && $price > 0 ) ? ' <span class="wpat-extra-price" style="color: #2563eb; font-weight: 600;">(+' . wc_price( $price ) . ')</span>' : '';
				$req_html   = $required ? ' <span class="required" style="color:#ef4444;">*</span>' : '';

				echo '<div class="wpat-extra-field-group" data-type="' . esc_attr( $type ) . '" data-base-price="' . esc_attr( $price ) . '" style="margin-bottom: 14px;">';
				echo '<label for="' . esc_attr( $field_id ) . '" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13.5px;">' . $label . $price_html . $req_html . '</label>';

				if ( 'text' === $type ) {
					$max_attr = $max_length > 0 ? ' maxlength="' . $max_length . '"' : '';
					echo '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" value="' . esc_attr( $default_val ) . '" class="wpat-extra-input" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="' . esc_attr( ! empty( $field['placeholder'] ) ? $field['placeholder'] : '' ) . '"' . $max_attr . ' />';
					if ( $max_length > 0 ) {
						echo '<small style="display:block; color:#64748b; margin-top:2px;">Máximo ' . $max_length . ' caracteres</small>';
					}
				} elseif ( 'textarea' === $type ) {
					$max_attr = $max_length > 0 ? ' maxlength="' . $max_length . '"' : '';
					echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" class="wpat-extra-input" rows="3" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="' . esc_attr( ! empty( $field['placeholder'] ) ? $field['placeholder'] : '' ) . '"' . $max_attr . '>' . esc_textarea( $default_val ) . '</textarea>';
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
							$value_attr = $opt_name . '|' . $opt_price;
							$is_def     = ( ! empty( $default_val ) && ( strcasecmp( $default_val, $opt_name ) === 0 || strcasecmp( $default_val, $opt ) === 0 ) );
							$sel_attr   = $is_def ? ' selected="selected"' : '';
							echo '<option value="' . esc_attr( $value_attr ) . '"' . $sel_attr . '>' . esc_html( wp_strip_all_tags( $opt_disp ) ) . '</option>';
						}
					}
					echo '</select>';
				} elseif ( 'swatch' === $type ) {
					// Muestrario de Color (Color Swatches)
					$swatches = ! empty( $field['swatches'] ) ? preg_split( '/\r\n|\r|\n/', $field['swatches'] ) : array();
					$initial_val = '';
					$initial_lbl = '';

					// Determinar si hay swatch por defecto
					foreach ( $swatches as $sw_check ) {
						$parts_c = explode( '|', trim( $sw_check ) );
						$c_name  = isset( $parts_c[0] ) ? trim( $parts_c[0] ) : '';
						$c_p     = isset( $parts_c[2] ) ? floatval( trim( $parts_c[2] ) ) : $price;
						if ( ! empty( $c_name ) && ! empty( $default_val ) && ( strcasecmp( $default_val, $c_name ) === 0 || strcasecmp( $default_val, trim( $sw_check ) ) === 0 ) ) {
							$initial_val = $c_name . '|' . $c_p;
							$initial_lbl = 'Seleccionado: ' . $c_name . ( $c_p > 0 ? ' (+' . number_format( $c_p, 2, ',', '.' ) . ' €)' : '' );
							break;
						}
					}

					echo '<div class="wpat-swatch-container" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 6px;">';
					echo '<input type="hidden" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" value="' . esc_attr( $initial_val ) . '" class="wpat-extra-input" />';

					foreach ( $swatches as $s_idx => $sw ) {
						$parts       = explode( '|', trim( $sw ) );
						$color_name  = isset( $parts[0] ) ? trim( $parts[0] ) : '';
						$raw_hex     = isset( $parts[1] ) ? trim( $parts[1] ) : '';
						$color_price = isset( $parts[2] ) ? floatval( trim( $parts[2] ) ) : ( ( isset( $parts[1] ) && is_numeric( trim( $parts[1] ) ) ) ? floatval( trim( $parts[1] ) ) : $price );

						if ( empty( $color_name ) ) {
							continue;
						}

						$color_hex  = self::parse_color_hex( ! empty( $raw_hex ) && ! is_numeric( $raw_hex ) ? $raw_hex : $color_name );
						$val_attr   = $color_name . '|' . $color_price;
						$title_text = $color_name . ( $color_price > 0 ? ' (+' . wc_price( $color_price ) . ')' : '' );
						$is_def     = ( ! empty( $default_val ) && ( strcasecmp( $default_val, $color_name ) === 0 || strcasecmp( $default_val, trim( $sw ) ) === 0 ) );
						$sel_style  = $is_def ? 'box-shadow: 0 0 0 2.5px #2563eb; transform: scale(1.12);' : 'box-shadow: 0 0 0 1px #cbd5e1;';
						$sel_class  = $is_def ? ' selected' : '';

						echo '<button type="button" class="wpat-swatch-btn' . esc_attr( $sel_class ) . '" data-target="' . esc_attr( $field_id ) . '" data-value="' . esc_attr( $val_attr ) . '" data-name="' . esc_attr( $color_name ) . '" data-price="' . esc_attr( $color_price ) . '" title="' . esc_attr( wp_strip_all_tags( $title_text ) ) . '" style="width: 34px; height: 34px; border-radius: 50%; background-color: ' . esc_attr( $color_hex ) . '; border: 2px solid #ffffff; ' . esc_attr( $sel_style ) . ' cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease; padding:0; outline:none;"></button>';
					}
					echo '</div>';
					echo '<span class="wpat-swatch-selected-label" id="' . esc_attr( $field_id ) . '_label" style="font-size: 12px; font-weight: 600; color: #334155; margin-top: 6px; display: block; min-height: 18px;">' . esc_html( $initial_lbl ) . '</span>';
				} elseif ( 'radio' === $type ) {
					$options = ! empty( $field['options'] ) ? preg_split( '/\r\n|\r|\n/', $field['options'] ) : array();
					echo '<div class="wpat-extra-radio-group" style="display: flex; flex-direction: column; gap: 6px; margin-top: 4px;">';
					foreach ( $options as $r_idx => $opt ) {
						$opt = trim( $opt );
						if ( ! empty( $opt ) ) {
							$parts      = explode( '|', $opt );
							$opt_name   = trim( $parts[0] );
							$opt_price  = isset( $parts[1] ) ? floatval( trim( $parts[1] ) ) : $price;
							$opt_disp   = $opt_name;
							if ( $opt_price > 0 ) {
								$opt_disp .= ' (+' . wc_price( $opt_price ) . ')';
							}
							$value_attr = $opt_name . '|' . $opt_price;
							$radio_id   = $field_id . '_' . $r_idx;
							$is_def     = ( ! empty( $default_val ) && ( strcasecmp( $default_val, $opt_name ) === 0 || strcasecmp( $default_val, $opt ) === 0 ) );
							$chk_attr   = $is_def ? ' checked="checked"' : '';

							echo '<label for="' . esc_attr( $radio_id ) . '" style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">';
							echo '<input type="radio" id="' . esc_attr( $radio_id ) . '" name="' . esc_attr( $field_id ) . '" value="' . esc_attr( $value_attr ) . '" class="wpat-extra-input"' . $chk_attr . ' /> ' . wp_kses_post( $opt_disp );
							echo '</label>';
						}
					}
					echo '</div>';
				} elseif ( 'checkbox' === $type ) {
					$is_def   = ( '1' === $default_val || strcasecmp( $default_val, 'true' ) === 0 || strcasecmp( $default_val, 'si' ) === 0 || strcasecmp( $default_val, 'sí' ) === 0 );
					$chk_attr = $is_def ? ' checked="checked"' : '';

					echo '<label style="font-weight: normal; cursor: pointer;">';
					echo '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" value="1" class="wpat-extra-input"' . $chk_attr . ' /> ' . esc_html( ! empty( $field['placeholder'] ) ? $field['placeholder'] : 'Activar esta opción' );
					echo '</label>';
				} elseif ( 'file' === $type ) {
					echo '<input type="file" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_id ) . '" class="wpat-extra-input" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;" accept="image/*,.pdf,.zip" />';
					echo '<small style="display:block; color:#64748b; margin-top:3px;">Formatos admitidos: imágenes (JPG, PNG), PDF, ZIP (Máx. 5 MB)</small>';
				}

				echo '</div>';
			}
		}

		// Bloque de Total Dinámico en Vivo
		$base_prod_price = floatval( $product->get_price() );
		echo '<div class="wpat-live-price-box" style="margin-top: 18px; padding: 12px 16px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">';
		echo '<span style="font-weight: 600; color: #0369a1; font-size: 14px;">Precio Total Estimado:</span>';
		echo '<span id="wpat-live-total-amount" style="font-weight: 700; color: #0284c7; font-size: 18px;" data-base-price="' . esc_attr( $base_prod_price ) . '">' . wc_price( $base_prod_price ) . '</span>';
		echo '</div>';

		echo '</div>';

		// Script para interactividad de Swatches y Cálculo de Total en Vivo
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var wrapper = document.querySelector('.wpat-extra-options-wrapper');
			if (!wrapper) return;

			var liveTotalEl = document.getElementById('wpat-live-total-amount');
			var basePrice = liveTotalEl ? parseFloat(liveTotalEl.getAttribute('data-base-price') || 0) : 0;

			function updateLiveTotal() {
				if (!liveTotalEl) return;
				var extraSum = 0;

				var groups = wrapper.querySelectorAll('.wpat-extra-field-group');
				groups.forEach(function(group) {
					var type = group.getAttribute('data-type');
					var groupBasePrice = parseFloat(group.getAttribute('data-base-price') || 0);

					if (type === 'select') {
						var select = group.querySelector('select');
						if (select && select.value) {
							if (select.value.indexOf('|') !== -1) {
								var p = parseFloat(select.value.split('|')[1]);
								if (!isNaN(p)) extraSum += p;
							} else if (groupBasePrice > 0) {
								extraSum += groupBasePrice;
							}
						}
					} else if (type === 'radio') {
						var checkedRadio = group.querySelector('input[type="radio"]:checked');
						if (checkedRadio && checkedRadio.value) {
							if (checkedRadio.value.indexOf('|') !== -1) {
								var p = parseFloat(checkedRadio.value.split('|')[1]);
								if (!isNaN(p)) extraSum += p;
							} else if (groupBasePrice > 0) {
								extraSum += groupBasePrice;
							}
						}
					} else if (type === 'swatch') {
						var hidden = group.querySelector('input[type="hidden"]');
						if (hidden && hidden.value) {
							if (hidden.value.indexOf('|') !== -1) {
								var p = parseFloat(hidden.value.split('|')[1]);
								if (!isNaN(p)) extraSum += p;
							} else if (groupBasePrice > 0) {
								extraSum += groupBasePrice;
							}
						}
					} else if (type === 'checkbox') {
						var chk = group.querySelector('input[type="checkbox"]');
						if (chk && chk.checked && groupBasePrice > 0) {
							extraSum += groupBasePrice;
						}
					} else if (type === 'text' || type === 'textarea') {
						var input = group.querySelector('input[type="text"], textarea');
						if (input && input.value.trim() !== '' && groupBasePrice > 0) {
							extraSum += groupBasePrice;
						}
					}
				});

				var total = basePrice + extraSum;
				// Formatear precio de forma aproximada y rápida para live preview
				liveTotalEl.textContent = total.toFixed(2).replace('.', ',') + ' €';
			}

			// Escuchar eventos en todo el formulario de opciones extra
			wrapper.addEventListener('change', updateLiveTotal);
			wrapper.addEventListener('input', updateLiveTotal);

			// Interacción de Swatches
			var swatchBtns = wrapper.querySelectorAll('.wpat-swatch-btn');
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
							b.classList.remove('selected');
						});
					}

					if (hiddenInput && hiddenInput.value === valAttr) {
						hiddenInput.value = '';
						if (labelSpan) labelSpan.textContent = '';
					} else {
						if (hiddenInput) hiddenInput.value = valAttr;
						this.style.boxShadow = '0 0 0 2.5px #2563eb';
						this.style.transform = 'scale(1.12)';
						this.classList.add('selected');
						if (labelSpan) {
							var labelText = 'Seleccionado: ' + colorName;
							if (priceVal > 0) {
								labelText += ' (+' + priceVal.toFixed(2).replace('.', ',') + ' €)';
							}
							labelSpan.textContent = labelText;
						}
					}
					updateLiveTotal();
				});
			});

			// Inicializar precio al cargar
			updateLiveTotal();
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
				$field_id   = 'wpat_extra_' . $rule_idx . '_' . $f_idx;
				$field_type = ! empty( $field['type'] ) ? $field['type'] : 'text';

				if ( 'file' === $field_type ) {
					if ( isset( $_FILES[ $field_id ] ) && ! empty( $_FILES[ $field_id ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
						$file_info  = $_FILES[ $field_id ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
						$base_price = ! empty( $field['price'] ) ? floatval( $field['price'] ) : 0;

						require_once ABSPATH . 'wp-admin/includes/file.php';
						require_once ABSPATH . 'wp-admin/includes/media.php';
						require_once ABSPATH . 'wp-admin/includes/image.php';

						$upload_overrides = array( 'test_form' => false );
						$movefile         = wp_handle_upload( $file_info, $upload_overrides );

						if ( $movefile && ! isset( $movefile['error'] ) ) {
							$opt_label_display = basename( $movefile['file'] );
							$file_url          = $movefile['url'];

							// Sincronizar archivo con nubes configuradas (Google Drive, Dropbox, OneDrive)
							if ( class_exists( 'WPAT_Integrations' ) ) {
								WPAT_Integrations::get_instance()->sync_file_to_cloud( $movefile['file'], $opt_label_display );
							}

							$extra_options[] = array(
								'label'    => sanitize_text_field( $field['label'] ),
								'value'    => $opt_label_display,
								'file_url' => $file_url,
								'price'    => $base_price,
							);
						}
					}
				} elseif ( isset( $_POST[ $field_id ] ) && '' !== trim( wp_unslash( $_POST[ $field_id ] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$raw_val    = sanitize_text_field( wp_unslash( $_POST[ $field_id ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$base_price = ! empty( $field['price'] ) ? floatval( $field['price'] ) : 0;

					$opt_label_display = $raw_val;
					$opt_price         = $base_price;

					if ( 'checkbox' === $field_type ) {
						$opt_label_display = ! empty( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : __( 'Sí', 'wp-agency-toolkit' );
						$opt_price         = $base_price;
					} elseif ( strpos( $raw_val, '|' ) !== false ) {
						$parts             = explode( '|', $raw_val );
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

			$target_id = $variation_id ? $variation_id : $product_id;
			$prod_obj  = wc_get_product( $target_id );
			if ( $prod_obj ) {
				$cart_item_data['wpat_base_price'] = floatval( $prod_obj->get_price() );
			}
		}

		return $cart_item_data;
	}

	/**
	 * Restaura los metadatos de opciones extra desde la sesión de WooCommerce.
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {
		if ( isset( $values['wpat_extra_options'] ) ) {
			$cart_item['wpat_extra_options'] = $values['wpat_extra_options'];
		}
		if ( isset( $values['wpat_base_price'] ) ) {
			$cart_item['wpat_base_price'] = $values['wpat_base_price'];
		}

		return $this->apply_custom_price_to_cart_item( $cart_item );
	}

	/**
	 * Aplica el precio personalizado cuando el elemento es añadido al carrito.
	 */
	public function add_cart_item( $cart_item ) {
		return $this->apply_custom_price_to_cart_item( $cart_item );
	}

	/**
	 * Asigna el precio recalculado (Precio Base + Opciones Extra) al objeto WC_Product del carrito.
	 */
	public function apply_custom_price_to_cart_item( &$cart_item ) {
		if ( ! empty( $cart_item['wpat_extra_options'] ) && is_array( $cart_item['wpat_extra_options'] ) && isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
			$product = $cart_item['data'];

			// Registrar e inmovilizar el precio base en la estructura del elemento del carrito
			if ( ! isset( $cart_item['wpat_base_price'] ) || floatval( $cart_item['wpat_base_price'] ) <= 0 ) {
				$cart_item['wpat_base_price'] = floatval( $product->get_price( 'edit' ) );
				if ( $cart_item['wpat_base_price'] <= 0 ) {
					$cart_item['wpat_base_price'] = floatval( $product->get_price() );
				}
			}

			$base_price  = floatval( $cart_item['wpat_base_price'] );
			$extra_price = 0;

			foreach ( $cart_item['wpat_extra_options'] as $opt ) {
				if ( isset( $opt['price'] ) && floatval( $opt['price'] ) > 0 ) {
					$extra_price += floatval( $opt['price'] );
				}
			}

			if ( $extra_price > 0 ) {
				$new_price = $base_price + $extra_price;

				// Inyectar propiedades directas en el objeto WC_Product
				$product->wpat_base_price  = $base_price;
				$product->wpat_extra_price = $extra_price;
				$product->set_price( $new_price );
			}
		}

		return $cart_item;
	}

	/**
	 * Intercepta get_price() del producto si tiene un precio extra calculado.
	 */
	public function filter_product_get_price( $price, $product ) {
		if ( is_object( $product ) && isset( $product->wpat_extra_price ) && floatval( $product->wpat_extra_price ) > 0 ) {
			$base = isset( $product->wpat_base_price ) ? floatval( $product->wpat_base_price ) : floatval( $price );
			return $base + floatval( $product->wpat_extra_price );
		}
		return $price;
	}

	/**
	 * Asegura que el formato HTML de precio en la tabla del carrito muestre el precio recalculado.
	 */
	public function filter_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item['wpat_extra_options'] ) && is_array( $cart_item['wpat_extra_options'] ) && isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
			$extra_price = 0;
			foreach ( $cart_item['wpat_extra_options'] as $opt ) {
				if ( isset( $opt['price'] ) && floatval( $opt['price'] ) > 0 ) {
					$extra_price += floatval( $opt['price'] );
				}
			}
			if ( $extra_price > 0 ) {
				$base_price = isset( $cart_item['wpat_base_price'] ) && floatval( $cart_item['wpat_base_price'] ) > 0 ? floatval( $cart_item['wpat_base_price'] ) : floatval( $cart_item['data']->get_price() );
				return wc_price( $base_price + $extra_price );
			}
		}
		return $price_html;
	}

	/**
	 * Asegura que el formato HTML de subtotal en la tabla del carrito muestre el subtotal recalculado.
	 */
	public function filter_cart_item_subtotal( $subtotal_html, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item['wpat_extra_options'] ) && is_array( $cart_item['wpat_extra_options'] ) && isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
			$extra_price = 0;
			foreach ( $cart_item['wpat_extra_options'] as $opt ) {
				if ( isset( $opt['price'] ) && floatval( $opt['price'] ) > 0 ) {
					$extra_price += floatval( $opt['price'] );
				}
			}
			if ( $extra_price > 0 ) {
				$base_price = isset( $cart_item['wpat_base_price'] ) && floatval( $cart_item['wpat_base_price'] ) > 0 ? floatval( $cart_item['wpat_base_price'] ) : floatval( $cart_item['data']->get_price() );
				$quantity   = isset( $cart_item['quantity'] ) ? intval( $cart_item['quantity'] ) : 1;
				return wc_price( ( $base_price + $extra_price ) * $quantity );
			}
		}
		return $subtotal_html;
	}

	/**
	 * Recalcula el precio dinámico antes de calcular los totales del carrito.
	 */
	public function calculate_extra_option_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! is_object( $cart ) || ! method_exists( $cart, 'get_cart' ) ) {
			$cart = WC()->cart;
		}

		if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$this->apply_custom_price_to_cart_item( $cart_item );
		}
	}

	/**
	 * Muestra las opciones extra seleccionadas en el carrito y en el checkout.
	 */
	public function display_extra_options_in_cart( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['wpat_extra_options'] ) && is_array( $cart_item['wpat_extra_options'] ) ) {
			foreach ( $cart_item['wpat_extra_options'] as $opt ) {
				if ( ! empty( $opt['file_url'] ) ) {
					$val_display = '<a href="' . esc_url( $opt['file_url'] ) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:underline; font-weight:600;">' . esc_html( $opt['value'] ) . '</a>';
				} else {
					$val_display = esc_html( $opt['value'] );
				}

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
				$meta_value = ! empty( $opt['file_url'] ) ? $opt['file_url'] : $opt['value'];
				if ( ! empty( $opt['price'] ) && $opt['price'] > 0 ) {
					$meta_value .= ' (+' . wc_price( $opt['price'] ) . ')';
				}

				$item->add_meta_data( $opt['label'], $meta_value, true );
			}
		}
	}
}

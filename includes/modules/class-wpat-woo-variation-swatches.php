<?php
/**
 * Módulo: Swatches de Variación de Producto (WooCommerce) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Variation_Swatches {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Variation_Swatches
	 */
	private static $instance = null;

	/**
	 * Mapa por defecto de nombres/slugs de color a códigos HEX.
	 *
	 * @var array
	 */
	private $default_color_map = array(
		'blanco'      => '#ffffff',
		'white'       => '#ffffff',
		'negro'       => '#000000',
		'black'       => '#000000',
		'rojo'        => '#ef4444',
		'red'         => '#ef4444',
		'azul'        => '#2563eb',
		'blue'        => '#2563eb',
		'verde'       => '#10b981',
		'green'       => '#10b981',
		'amarillo'    => '#eab308',
		'yellow'      => '#eab308',
		'naranja'     => '#f97316',
		'orange'      => '#f97316',
		'gris'        => '#64748b',
		'grey'        => '#64748b',
		'gray'        => '#64748b',
		'rosa'        => '#ec4899',
		'pink'        => '#ec4899',
		'morado'      => '#a855f7',
		'purple'      => '#a855f7',
		'violeta'     => '#8b5cf6',
		'marron'      => '#78350f',
		'marrón'      => '#78350f',
		'brown'       => '#78350f',
		'beige'       => '#f5f5dc',
		'crema'       => '#fef3c7',
		'cream'       => '#fef3c7',
		'oro'         => '#ffd700',
		'gold'        => '#ffd700',
		'plata'       => '#c0c0c0',
		'silver'      => '#c0c0c0',
		'celeste'     => '#38bdf8',
		'sky-blue'    => '#38bdf8',
		'turquesa'    => '#14b8a6',
		'turquoise'   => '#14b8a6',
		'azul-marino' => '#1e3a8a',
		'azul marino' => '#1e3a8a',
		'navy'        => '#1e3a8a',
		'verde-oliva' => '#556b2f',
		'verde oliva' => '#556b2f',
		'olive'       => '#556b2f',
		'camo-green'  => '#78866b',
		'camo green'  => '#78866b',
		'burdeos'     => '#800020',
		'bordeaux'    => '#800020',
		'vino'        => '#722f37',
		'fucsia'      => '#d946ef',
		'fuchsia'     => '#d946ef',
		'mostaza'     => '#d97706',
		'mustard'     => '#d97706',
		'lavanda'     => '#c084fc',
		'lavender'    => '#c084fc',
		'coral'       => '#fb7185',
		'menta'       => '#6ee7b7',
		'mint'        => '#6ee7b7',
	);

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Variation_Swatches
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
		// Interceptar HTML del desplegable de variación nativo de WooCommerce
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( $this, 'render_variation_swatches' ), 100, 2 );

		// Inyectar estilos CSS y scripts JS en la página de producto
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Inyecta los activos de frontend para swatches.
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_product() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-variation-swatches'] ) || empty( $settings['variation_swatches_enabled'] ) ) {
			return;
		}

		// CSS inline para swatches de variación
		$shape_radius = ( isset( $settings['variation_swatches_shape'] ) && 'square' === $settings['variation_swatches_shape'] ) ? '6px' : '50%';

		$custom_css = "
			.wpat-variation-swatches-wrap { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; margin-bottom: 12px; }
			.wpat-vswatch-btn { position: relative; display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; padding: 0 10px; border-radius: {$shape_radius}; border: 1.5px solid #cbd5e1; background: #ffffff; color: #1e293b; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s ease; outline: none; user-select: none; }
			.wpat-vswatch-btn:hover { border-color: #94a3b8; transform: translateY(-1px); }
			.wpat-vswatch-btn.selected { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3); font-weight: 700; background-color: #f0f6ff; }
			.wpat-vswatch-btn.wpat-vswatch-color { padding: 0; min-width: 32px; width: 32px; height: 32px; border-radius: {$shape_radius}; border: 2px solid #ffffff; box-shadow: 0 0 0 1px #cbd5e1; }
			.wpat-vswatch-btn.wpat-vswatch-color.selected { box-shadow: 0 0 0 2.5px #2563eb; transform: scale(1.1); }
			.wpat-vswatch-btn.disabled { opacity: 0.4; cursor: not-allowed; text-decoration: line-through; }
		";
		wp_add_inline_style( 'woocommerce-inline', $custom_css );
	}

	/**
	 * Renderiza botones/swatches visuales reemplazando el select nativo de WooCommerce.
	 *
	 * @param string $html HTML del select nativo.
	 * @param array  $args Argumentos del atributo.
	 * @return string
	 */
	public function render_variation_swatches( $html, $args ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-variation-swatches'] ) || empty( $settings['variation_swatches_enabled'] ) ) {
			return $html;
		}

		$options   = $args['options'];
		$product   = $args['product'];
		$attribute = $args['attribute'];
		$name      = $args['name'] ? $args['name'] : 'attribute_' . sanitize_title( $attribute );
		$id        = $args['id'] ? $args['id'] : sanitize_title( $attribute );
		$selected  = $args['selected'];

		if ( empty( $options ) || ! $product ) {
			return $html;
		}

		// Detectar si es un atributo de tipo Color
		$attr_name_lower = strtolower( wc_attribute_label( $attribute, $product ) );
		$is_color_attr   = ( strpos( $attr_name_lower, 'color' ) !== false || strpos( $attr_name_lower, 'colour' ) !== false || strpos( $attr_name_lower, 'acabado' ) !== false );

		// Obtener mapa personalizado de colores configurado en admin
		$user_color_map = array();
		if ( ! empty( $settings['variation_swatches_colors'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $settings['variation_swatches_colors'] );
			foreach ( $lines as $l ) {
				$parts = explode( '|', trim( $l ) );
				if ( count( $parts ) >= 2 ) {
					$user_color_map[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
				}
			}
		}

		$swatches_html = '<div class="wpat-variation-swatches-wrap" data-select-id="' . esc_attr( $id ) . '">';

		if ( taxonomy_exists( $attribute ) ) {
			$terms = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );
			foreach ( $terms as $term ) {
				if ( ! in_array( $term->slug, $options, true ) ) {
					continue;
				}

				$term_name = $term->name;
				$term_slug = $term->slug;
				$is_selected = ( $selected === $term_slug );

				if ( $is_color_attr ) {
					$key_lower = strtolower( $term_name );
					$slug_lower = strtolower( $term_slug );

					$color_hex = '#cbd5e1';
					if ( isset( $user_color_map[ $key_lower ] ) ) {
						$color_hex = $user_color_map[ $key_lower ];
					} elseif ( isset( $user_color_map[ $slug_lower ] ) ) {
						$color_hex = $user_color_map[ $slug_lower ];
					} elseif ( isset( $this->default_color_map[ $key_lower ] ) ) {
						$color_hex = $this->default_color_map[ $key_lower ];
					} elseif ( isset( $this->default_color_map[ $slug_lower ] ) ) {
						$color_hex = $this->default_color_map[ $slug_lower ];
					}

					$sel_class = $is_selected ? ' selected' : '';
					$border_style = ( '#ffffff' === strtolower( $color_hex ) || '#fff' === strtolower( $color_hex ) ) ? 'box-shadow: 0 0 0 1px #94a3b8;' : '';

					$swatches_html .= sprintf(
						'<button type="button" class="wpat-vswatch-btn wpat-vswatch-color%s" data-value="%s" title="%s" style="background-color: %s; %s"></button>',
						esc_attr( $sel_class ),
						esc_attr( $term_slug ),
						esc_attr( $term_name ),
						esc_attr( $color_hex ),
						$border_style
					);
				} else {
					// Swatch de Botón / Pill (Talla, etc.)
					$sel_class = $is_selected ? ' selected' : '';
					$swatches_html .= sprintf(
						'<button type="button" class="wpat-vswatch-btn wpat-vswatch-label%s" data-value="%s">%s</button>',
						esc_attr( $sel_class ),
						esc_attr( $term_slug ),
						esc_html( $term_name )
					);
				}
			}
		} else {
			// Atributo personalizado no taxonómico
			foreach ( $options as $option ) {
				$is_selected = ( $selected === $option );
				$sel_class   = $is_selected ? ' selected' : '';

				if ( $is_color_attr ) {
					$key_lower = strtolower( trim( $option ) );
					$color_hex = isset( $user_color_map[ $key_lower ] ) ? $user_color_map[ $key_lower ] : ( isset( $this->default_color_map[ $key_lower ] ) ? $this->default_color_map[ $key_lower ] : '#cbd5e1' );
					$border_style = ( '#ffffff' === strtolower( $color_hex ) || '#fff' === strtolower( $color_hex ) ) ? 'box-shadow: 0 0 0 1px #94a3b8;' : '';

					$swatches_html .= sprintf(
						'<button type="button" class="wpat-vswatch-btn wpat-vswatch-color%s" data-value="%s" title="%s" style="background-color: %s; %s"></button>',
						esc_attr( $sel_class ),
						esc_attr( $option ),
						esc_attr( $option ),
						esc_attr( $color_hex ),
						$border_style
					);
				} else {
					$swatches_html .= sprintf(
						'<button type="button" class="wpat-vswatch-btn wpat-vswatch-label%s" data-value="%s">%s</button>',
						esc_attr( $sel_class ),
						esc_attr( $option ),
						esc_html( $option )
					);
				}
			}
		}

		$swatches_html .= '</div>';

		// Ocultar select original mediante estilo inline pero manteniéndolo en el DOM para compatibilidad con WooCommerce JS
		$hidden_select_html = str_replace( '<select ', '<select style="display:none !important;" ', $html );

		// Script para sincronizar el click del swatch con el select de WooCommerce
		$js_script = '
		<script>
		document.addEventListener("DOMContentLoaded", function() {
			var wrappers = document.querySelectorAll(".wpat-variation-swatches-wrap");
			wrappers.forEach(function(wrap) {
				var selectId = wrap.getAttribute("data-select-id");
				var selectEl = document.getElementById(selectId);
				if (!selectEl) return;

				wrap.querySelectorAll(".wpat-vswatch-btn").forEach(function(btn) {
					btn.addEventListener("click", function(e) {
						e.preventDefault();
						var val = this.getAttribute("data-value");

						if (this.classList.contains("selected")) {
							// Deseleccionar
							selectEl.value = "";
							wrap.querySelectorAll(".wpat-vswatch-btn").forEach(function(b) { b.classList.remove("selected"); });
						} else {
							// Seleccionar
							selectEl.value = val;
							wrap.querySelectorAll(".wpat-vswatch-btn").forEach(function(b) { b.classList.remove("selected"); });
							this.classList.add("selected");
						}

						// Disparar eventos nativos de WooCommerce para actualizar precio, foto principal e interfaz
						var event = document.createEvent("HTMLEvents");
						event.initEvent("change", true, false);
						selectEl.dispatchEvent(event);
						if (typeof jQuery !== "undefined") {
							jQuery(selectEl).trigger("change");
						}
					});
				});
			});
		});
		</script>';

		return $hidden_select_html . $swatches_html . $js_script;
	}
}

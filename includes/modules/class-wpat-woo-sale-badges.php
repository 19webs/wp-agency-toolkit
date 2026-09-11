<?php
/**
 * Módulo: Badges y Etiquetas de Oferta High-Impact - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Sale_Badges {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Sale_Badges
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Sale_Badges
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_sale_badge_assets' ) );

		// Interceptar la etiqueta de oferta nativa de WooCommerce
		add_filter( 'woocommerce_sale_flash', array( $this, 'custom_sale_flash' ), 999, 3 );
	}

	/**
	 * Encola los estilos CSS para las etiquetas de oferta.
	 */
	public function enqueue_sale_badge_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'wpat-sale-badges-css',
			WPAT_URL . 'assets/css/wpat-sale-badges.css',
			array(),
			WPAT_VERSION
		);

		// Generar CSS dinámico según los ajustes del usuario
		$settings   = WPAT_Main::get_instance()->get_settings();
		$bg_color   = ! empty( $settings['woo_sale_badge_bg_color'] ) ? $settings['woo_sale_badge_bg_color'] : '#ef4444';
		$txt_color  = ! empty( $settings['woo_sale_badge_txt_color'] ) ? $settings['woo_sale_badge_txt_color'] : '#ffffff';
		$font_size  = ! empty( $settings['woo_sale_badge_font_size'] ) ? intval( $settings['woo_sale_badge_font_size'] ) : 13;
		$position   = ! empty( $settings['woo_sale_badge_position'] ) ? $settings['woo_sale_badge_position'] : 'top-left';

		$pos_css = ( 'top-right' === $position ) ? 'right: 12px !important; left: auto !important;' : 'left: 12px !important; right: auto !important;';

		$custom_css = "
			.wpat-sale-badge {
				background-color: {$bg_color} !important;
				color: {$txt_color} !important;
				font-size: {$font_size}px !important;
				{$pos_css}
			}
			.wpat-sale-badge.badge-corner-ribbon {
				background-color: {$bg_color} !important;
				color: {$txt_color} !important;
			}
		";

		wp_add_inline_style( 'wpat-sale-badges-css', $custom_css );
	}

	/**
	 * Reemplaza el HTML de la etiqueta de oferta nativa de WooCommerce.
	 */
	public function custom_sale_flash( $html, $post, $product ) {
		if ( ! $product || ! $product->is_on_sale() ) {
			return $html;
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$shape      = ! empty( $settings['woo_sale_badge_shape'] ) ? $settings['woo_sale_badge_shape'] : 'soft';
		$calc_type  = ! empty( $settings['woo_sale_badge_text_type'] ) ? $settings['woo_sale_badge_text_type'] : 'custom';
		$custom_txt = ! empty( $settings['woo_sale_badge_custom_text'] ) ? $settings['woo_sale_badge_custom_text'] : '¡OFERTA!';
		$position   = ! empty( $settings['woo_sale_badge_position'] ) ? $settings['woo_sale_badge_position'] : 'top-left';

		$label_text = $custom_txt;

		// Si el usuario eligió mostrar el porcentaje de descuento real
		if ( 'percentage' === $calc_type ) {
			$percentage = 0;

			if ( $product->is_type( 'variable' ) ) {
				$percentages = array();
				$prices      = $product->get_variation_prices();

				foreach ( $prices['price'] as $key => $price ) {
					if ( isset( $prices['regular_price'][ $key ] ) && $prices['regular_price'][ $key ] > $price && $prices['regular_price'][ $key ] > 0 ) {
						$percentages[] = round( ( ( $prices['regular_price'][ $key ] - $price ) / $prices['regular_price'][ $key ] ) * 100 );
					}
				}
				$percentage = ! empty( $percentages ) ? max( $percentages ) : 0;
			} elseif ( $product->is_type( 'grouped' ) ) {
				$percentage = 0;
			} else {
				$regular_price = (float) $product->get_regular_price();
				$sale_price    = (float) $product->get_sale_price();

				if ( $regular_price > 0 && $sale_price > 0 && $regular_price > $sale_price ) {
					$percentage = round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 );
				}
			}

			if ( $percentage > 0 ) {
				$label_text = '-' . $percentage . '%';
			}
		}

		$shape_class = 'badge-' . sanitize_html_class( $shape );
		$pos_class   = 'pos-' . sanitize_html_class( $position );

		return '<span class="onsale wpat-sale-badge ' . esc_attr( $shape_class ) . ' ' . esc_attr( $pos_class ) . '">' . esc_html( $label_text ) . '</span>';
	}
}

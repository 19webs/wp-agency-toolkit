<?php
/**
 * Módulo: Buscador AJAX en Vivo para WooCommerce (Frontend) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Live_Search {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Live_Search
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Live_Search
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
		$settings = WPAT_Main::get_instance()->get_settings();

		if ( empty( $settings['woo-live-search'] ) && empty( $settings['live_search_enabled'] ) ) {
			return;
		}

		// Encolar assets en frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Shortcode
		add_shortcode( 'wpat_product_search', array( $this, 'render_shortcode' ) );

		// AJAX Endpoints (Logueados y No logueados)
		add_action( 'wp_ajax_wpat_frontend_product_search', array( $this, 'ajax_product_search' ) );
		add_action( 'wp_ajax_nopriv_wpat_frontend_product_search', array( $this, 'ajax_product_search' ) );

		// Auto-reemplazo opcional del formulario nativo de WooCommerce
		if ( ! empty( $settings['live_search_auto_replace'] ) && '1' === $settings['live_search_auto_replace'] ) {
			add_filter( 'get_product_search_form', array( $this, 'replace_product_search_form' ), 99 );
		}
	}

	/**
	 * Encola CSS y JS del buscador AJAX en el frontend.
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'wpat-woo-live-search', WPAT_URL . 'assets/css/wpat-woo-live-search.css', array(), WPAT_VERSION );
		wp_enqueue_script( 'wpat-woo-live-search', WPAT_URL . 'assets/js/wpat-woo-live-search.js', array( 'jquery' ), WPAT_VERSION, true );

		$settings = WPAT_Main::get_instance()->get_settings();

		wp_localize_script( 'wpat-woo-live-search', 'wpatWooSearch', array(
			'ajaxurl'     => admin_url( 'admin-ajax.php' ),
			'min_chars'   => 2,
			'max_results' => isset( $settings['live_search_max_results'] ) ? min( 8, max( 1, intval( $settings['live_search_max_results'] ) ) ) : 5,
			'no_results'  => __( 'No se encontraron productos coincidentes', 'wp-agency-toolkit' ),
			'searching'   => __( 'Buscando productos...', 'wp-agency-toolkit' ),
			'view_all'    => __( 'Ver todos los resultados', 'wp-agency-toolkit' ),
		) );
	}

	/**
	 * Renderiza la estructura del formulario de búsqueda en vivo.
	 *
	 * @param array $atts Parámetros del shortcode.
	 * @return string HTML del buscador.
	 */
	public function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array(
			'placeholder' => __( 'Buscar productos por nombre, SKU o categoría...', 'wp-agency-toolkit' ),
			'class'       => '',
		), $atts, 'wpat_product_search' );

		$settings    = WPAT_Main::get_instance()->get_settings();
		$placeholder = ! empty( $settings['live_search_placeholder'] ) ? esc_attr( $settings['live_search_placeholder'] ) : esc_attr( $atts['placeholder'] );
		$extra_class = esc_attr( $atts['class'] );

		ob_start();
		?>
		<div class="wpat-live-search-wrapper <?php echo $extra_class; ?>">
			<form role="search" method="get" class="wpat-live-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<div class="wpat-live-search-input-wrap">
					<input type="search" class="wpat-live-search-field" placeholder="<?php echo $placeholder; ?>" value="<?php echo get_search_query(); ?>" name="s" autocomplete="off" />
					<input type="hidden" name="post_type" value="product" />
					<button type="submit" class="wpat-live-search-submit" aria-label="<?php esc_attr_e( 'Buscar', 'wp-agency-toolkit' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
					</button>
					<span class="wpat-live-search-spinner" style="display:none;"></span>
				</div>
				<div class="wpat-live-search-dropdown" style="display:none;"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Reemplaza el formulario nativo de WooCommerce.
	 */
	public function replace_product_search_form( $form ) {
		return $this->render_shortcode();
	}

	/**
	 * Endpoint AJAX para consultar productos WooCommerce en tiempo real.
	 */
	public function ajax_product_search() {
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( mb_strlen( trim( $term ) ) < 2 ) {
			wp_send_json_success( array( 'results' => array(), 'total' => 0 ) );
		}

		$settings    = WPAT_Main::get_instance()->get_settings();
		$max_results = isset( $settings['live_search_max_results'] ) ? min( 8, max( 1, intval( $settings['live_search_max_results'] ) ) ) : 5;
		$show_thumb  = ! isset( $settings['live_search_show_thumb'] ) || '1' === $settings['live_search_show_thumb'];
		$show_price  = ! isset( $settings['live_search_show_price'] ) || '1' === $settings['live_search_show_price'];
		$show_stock  = ! isset( $settings['live_search_show_stock'] ) || '1' === $settings['live_search_show_stock'];
		$show_meta   = isset( $settings['live_search_show_meta'] ) ? $settings['live_search_show_meta'] : 'sku';

		$found_ids = array();
		$results   = array();

		// 1. Búsqueda por ID directo si el término es numérico
		if ( is_numeric( $term ) ) {
			$p_id = intval( $term );
			$post = get_post( $p_id );
			if ( $post && 'product' === $post->post_type && 'publish' === $post->post_status ) {
				$found_ids[] = $p_id;
			}
		}

		// 2. Búsqueda por SKU
		if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
			$sku_id = wc_get_product_id_by_sku( $term );
			if ( $sku_id && ! in_array( $sku_id, $found_ids, true ) ) {
				$found_ids[] = $sku_id;
			}
		}

		// 3. Consulta WP_Query por título y contenido
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $max_results,
			's'              => $term,
			'post__not_in'   => $found_ids,
		);

		// Excluir productos ocultos del catálogo si WooCommerce está activo
		if ( taxonomy_exists( 'product_visibility' ) ) {
			$product_visibility_term_ids = wc_get_product_visibility_term_ids();
			$args['tax_query']           = array(
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => array( $product_visibility_term_ids['exclude-from-search'], $product_visibility_term_ids['exclude-from-catalog'] ),
					'operator' => 'NOT IN',
				),
			);
		}

		// Si encontramos productos por ID o SKU, aseguramos meterlos primero
		if ( ! empty( $found_ids ) ) {
			$direct_posts = get_posts( array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post__in'    => $found_ids,
			) );
		} else {
			$direct_posts = array();
		}

		$query       = new WP_Query( $args );
		$all_posts   = array_merge( $direct_posts, $query->posts );
		$all_posts   = array_slice( $all_posts, 0, $max_results );
		$total_found = $query->found_posts + count( $direct_posts );

		foreach ( $all_posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}

			// Thumbnail
			$thumb_url = '';
			if ( $show_thumb ) {
				$thumb_id  = $product->get_image_id();
				$thumb_src = wp_get_attachment_image_src( $thumb_id, 'thumbnail' );
				$thumb_url = $thumb_src ? $thumb_src[0] : wc_placeholder_img_src( 'thumbnail' );
			}

			// Stock Status
			$stock_html = '';
			if ( $show_stock ) {
				if ( $product->is_in_stock() ) {
					$stock_html = '<span class="wpat-stock-badge in-stock">' . esc_html__( 'En stock', 'wp-agency-toolkit' ) . '</span>';
				} else {
					$stock_html = '<span class="wpat-stock-badge out-of-stock">' . esc_html__( 'Agotado', 'wp-agency-toolkit' ) . '</span>';
				}
			}

			// Meta (SKU o Categoría)
			$meta_text = '';
			if ( 'sku' === $show_meta || 'both' === $show_meta ) {
				$sku = $product->get_sku();
				if ( $sku ) {
					$meta_text .= 'SKU: ' . $sku;
				}
			}
			if ( 'cat' === $show_meta || 'both' === $show_meta ) {
				$cats = wc_get_product_category_list( $product->get_id(), ', ' );
				if ( $cats ) {
					$clean_cats = wp_strip_all_tags( $cats );
					$meta_text .= ( $meta_text ? ' | ' : '' ) . $clean_cats;
				}
			}

			$results[] = array(
				'id'         => $product->get_id(),
				'title'      => get_the_title( $product->get_id() ),
				'url'        => get_permalink( $product->get_id() ),
				'thumb'      => $thumb_url,
				'price_html' => $show_price ? $product->get_price_html() : '',
				'stock_html' => $stock_html,
				'meta_text'  => $meta_text,
			);
		}

		$shop_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/' );
		$view_all_url = add_query_arg( array(
			's'         => urlencode( $term ),
			'post_type' => 'product',
		), $shop_url );

		wp_send_json_success( array(
			'results'      => $results,
			'total'        => $total_found,
			'view_all_url' => $view_all_url,
		) );
	}
}

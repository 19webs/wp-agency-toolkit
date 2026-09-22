<?php
/**
 * Módulo: Filtro por Facetas AJAX para WooCommerce (Estilo FacetWP) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Facets {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Facets
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Facets
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

		if ( empty( $settings['woo-facets'] ) && empty( $settings['facets_enabled'] ) ) {
			return;
		}

		// Encolar assets en frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Shortcode
		add_shortcode( 'wpat_product_facets', array( $this, 'render_shortcode' ) );

		// AJAX Endpoints (Logueados y No logueados)
		add_action( 'wp_ajax_wpat_filter_products', array( $this, 'ajax_filter_products' ) );
		add_action( 'wp_ajax_nopriv_wpat_filter_products', array( $this, 'ajax_filter_products' ) );
	}

	/**
	 * Encola CSS y JS para las Facetas en el frontend.
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'wpat-woo-facets', WPAT_URL . 'assets/css/wpat-woo-facets.css', array(), WPAT_VERSION );
		wp_enqueue_script( 'wpat-woo-facets', WPAT_URL . 'assets/js/wpat-woo-facets.js', array( 'jquery' ), WPAT_VERSION, true );

		$settings     = WPAT_Main::get_instance()->get_settings();
		$accent_color = isset( $settings['woo_facets_accent_color'] ) ? $settings['woo_facets_accent_color'] : '#2563eb';
		$card_bg      = isset( $settings['woo_facets_card_bg'] ) ? $settings['woo_facets_card_bg'] : '#ffffff';
		$border_color = isset( $settings['woo_facets_border_color'] ) ? $settings['woo_facets_border_color'] : '#e2e8f0';
		$badge_bg     = isset( $settings['woo_facets_badge_bg'] ) ? $settings['woo_facets_badge_bg'] : '#f1f5f9';
		$radius       = isset( $settings['woo_facets_border_radius'] ) ? intval( $settings['woo_facets_border_radius'] ) : 12;

		$custom_css = "
			:root {
				--wpat-facet-accent: {$accent_color};
				--wpat-facet-bg: {$card_bg};
				--wpat-facet-border: {$border_color};
				--wpat-facet-badge-bg: {$badge_bg};
				--wpat-facet-radius: {$radius}px;
			}
		";
		wp_add_inline_style( 'wpat-woo-facets', $custom_css );

		wp_localize_script( 'wpat-woo-facets', 'wpatWooFacets', array(
			'ajaxurl'            => admin_url( 'admin-ajax.php' ),
			'security'           => wp_create_nonce( 'wpat_facets_nonce' ),
			'loading'            => __( 'Cargando productos...', 'wp-agency-toolkit' ),
			'active_filters_lbl' => __( 'Filtros Activos:', 'wp-agency-toolkit' ),
			'clear_all_lbl'      => __( 'Limpiar todo', 'wp-agency-toolkit' ),
			'currency_symbol'    => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '€',
		) );
	}

	/**
	 * Renderiza las facetas activas mediante Shortcode.
	 *
	 * @param array $atts Parámetros del shortcode.
	 * @return string HTML de las facetas.
	 */
	public function render_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array(
			'title'  => __( 'Filtrar Productos', 'wp-agency-toolkit' ),
			'facets' => 'price,category,stock,rating,sort',
		), $atts, 'wpat_product_facets' );

		$settings         = WPAT_Main::get_instance()->get_settings();
		$enabled_facets   = isset( $settings['facets_config'] ) && is_array( $settings['facets_config'] ) ? $settings['facets_config'] : explode( ',', $atts['facets'] );
		$show_active_tags = ! isset( $settings['woo_facets_show_active_tags'] ) || '1' === $settings['woo_facets_show_active_tags'];
		$is_sticky        = ! empty( $settings['woo_facets_sticky'] ) && '1' === $settings['woo_facets_sticky'];

		$wrapper_classes = 'wpat-facets-wrapper' . ( $is_sticky ? ' wpat-facets-sticky' : '' );

		// Capturar valores preexistentes de $_GET para restaurar filtros por URL
		$get_orderby   = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'menu_order';
		$get_min_price = isset( $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : '';
		$get_max_price = isset( $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : '';
		$get_cats      = isset( $_GET['product_cat'] ) ? ( is_array( $_GET['product_cat'] ) ? array_map( 'sanitize_text_field', $_GET['product_cat'] ) : explode( ',', sanitize_text_field( $_GET['product_cat'] ) ) ) : array();
		$get_in_stock  = ! empty( $_GET['in_stock'] ) && '1' === (string) $_GET['in_stock'];
		$get_on_sale   = ! empty( $_GET['on_sale'] ) && '1' === (string) $_GET['on_sale'];
		$get_rating    = isset( $_GET['rating'] ) ? intval( $_GET['rating'] ) : 0;

		ob_start();
		?>
		<!-- Botón Móvil para abrir filtros en drawer -->
		<div class="wpat-facets-mobile-trigger-wrap">
			<button type="button" class="wpat-facets-mobile-toggle-btn">
				<span class="dashicons dashicons-filter"></span> <?php echo esc_html( $atts['title'] ); ?>
			</button>
		</div>

		<div class="<?php echo esc_attr( $wrapper_classes ); ?>" id="wpat-facets-main-container">
			<div class="wpat-facets-header-row">
				<?php if ( ! empty( $atts['title'] ) ) : ?>
					<h3 class="wpat-facets-main-title"><?php echo esc_html( $atts['title'] ); ?></h3>
				<?php endif; ?>
				<button type="button" class="wpat-facets-mobile-close-btn" aria-label="<?php esc_attr_e( 'Cerrar filtros', 'wp-agency-toolkit' ); ?>">&times;</button>
			</div>

			<?php if ( $show_active_tags ) : ?>
				<div class="wpat-facets-active-tags-bar" style="display: none;">
					<span class="wpat-facets-active-label"><?php esc_html_e( 'Filtros Activos:', 'wp-agency-toolkit' ); ?></span>
					<div class="wpat-facets-tags-list"></div>
					<button type="button" class="wpat-clear-all-tags-btn"><?php esc_html_e( 'Limpiar todo', 'wp-agency-toolkit' ); ?></button>
				</div>
			<?php endif; ?>

			<form class="wpat-facets-form">

				<?php // 1. Ordenación ?>
				<?php if ( in_array( 'sort', $enabled_facets, true ) ) : ?>
					<div class="wpat-facet-group">
						<label class="wpat-facet-title"><?php esc_html_e( 'Ordenar por', 'wp-agency-toolkit' ); ?></label>
						<select name="orderby" class="wpat-facet-select wpat-facet-input">
							<option value="menu_order" <?php selected( $get_orderby, 'menu_order' ); ?>><?php esc_html_e( 'Relevancia por defecto', 'wp-agency-toolkit' ); ?></option>
							<option value="popularity" <?php selected( $get_orderby, 'popularity' ); ?>><?php esc_html_e( 'Más populares', 'wp-agency-toolkit' ); ?></option>
							<option value="date" <?php selected( $get_orderby, 'date' ); ?>><?php esc_html_e( 'Más recientes', 'wp-agency-toolkit' ); ?></option>
							<option value="price-asc" <?php selected( $get_orderby, 'price-asc' ); ?>><?php esc_html_e( 'Precio: Menor a Mayor', 'wp-agency-toolkit' ); ?></option>
							<option value="price-desc" <?php selected( $get_orderby, 'price-desc' ); ?>><?php esc_html_e( 'Precio: Mayor a Menor', 'wp-agency-toolkit' ); ?></option>
							<option value="rating" <?php selected( $get_orderby, 'rating' ); ?>><?php esc_html_e( 'Mejor valorados', 'wp-agency-toolkit' ); ?></option>
						</select>
					</div>
				<?php endif; ?>

				<?php // 2. Rango de Precio ?>
				<?php if ( in_array( 'price', $enabled_facets, true ) ) :
					global $wpdb;
					$min_price = floor( (float) $wpdb->get_var( "SELECT MIN(CAST(pm.meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key='_price' AND pm.meta_value != '' AND p.post_status='publish' AND p.post_type IN ('product', 'product_variation')" ) ?: 0 );
					$max_price = ceil( (float) $wpdb->get_var( "SELECT MAX(CAST(pm.meta_value AS DECIMAL(10,2))) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key='_price' AND pm.meta_value != '' AND p.post_status='publish' AND p.post_type IN ('product', 'product_variation')" ) ?: 500 );
					if ( $max_price <= $min_price ) {
						$max_price = $min_price + 100;
					}
					?>
					<div class="wpat-facet-group">
						<label class="wpat-facet-title"><?php printf( esc_html__( 'Rango de Precio (%s)', 'wp-agency-toolkit' ), function_exists( 'get_woocommerce_currency_symbol' ) ? esc_html( get_woocommerce_currency_symbol() ) : '€' ); ?></label>
						<div class="wpat-facet-price-range">
							<input type="number" name="min_price" class="wpat-facet-input min-price-input" min="<?php echo esc_attr( $min_price ); ?>" max="<?php echo esc_attr( $max_price ); ?>" value="<?php echo esc_attr( $get_min_price ); ?>" placeholder="<?php echo esc_attr( $min_price ); ?>" />
							<span>—</span>
							<input type="number" name="max_price" class="wpat-facet-input max-price-input" min="<?php echo esc_attr( $min_price ); ?>" max="<?php echo esc_attr( $max_price ); ?>" value="<?php echo esc_attr( $get_max_price ); ?>" placeholder="<?php echo esc_attr( $max_price ); ?>" />
						</div>
					</div>
				<?php endif; ?>

				<?php // 3. Categorías de Producto ?>
				<?php if ( in_array( 'category', $enabled_facets, true ) && taxonomy_exists( 'product_cat' ) ) :
					$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
					if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) : ?>
						<div class="wpat-facet-group">
							<label class="wpat-facet-title"><?php esc_html_e( 'Categorías', 'wp-agency-toolkit' ); ?></label>
							<div class="wpat-facet-checkbox-list">
								<?php foreach ( $cats as $cat ) : ?>
									<label class="wpat-facet-checkbox-item">
										<input type="checkbox" name="product_cat[]" value="<?php echo esc_attr( $cat->slug ); ?>" class="wpat-facet-input" <?php checked( in_array( $cat->slug, $get_cats, true ) ); ?> />
										<span class="wpat-facet-label"><?php echo esc_html( $cat->name ); ?></span>
										<span class="wpat-facet-count">(<?php echo esc_html( $cat->count ); ?>)</span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif;
				endif; ?>

				<?php // 4. Estado de Stock / Ofertas ?>
				<?php if ( in_array( 'stock', $enabled_facets, true ) ) : ?>
					<div class="wpat-facet-group">
						<label class="wpat-facet-title"><?php esc_html_e( 'Disponibilidad', 'wp-agency-toolkit' ); ?></label>
						<div class="wpat-facet-checkbox-list">
							<label class="wpat-facet-checkbox-item">
								<input type="checkbox" name="in_stock" value="1" class="wpat-facet-input" <?php checked( $get_in_stock ); ?> />
								<span class="wpat-facet-label"><?php esc_html_e( 'Solo en Stock', 'wp-agency-toolkit' ); ?></span>
							</label>
							<label class="wpat-facet-checkbox-item">
								<input type="checkbox" name="on_sale" value="1" class="wpat-facet-input" <?php checked( $get_on_sale ); ?> />
								<span class="wpat-facet-label"><?php esc_html_e( 'En Oferta', 'wp-agency-toolkit' ); ?></span>
							</label>
						</div>
					</div>
				<?php endif; ?>

				<?php // 5. Atributos de Producto (p. ej. Color, Talla...) ?>
				<?php if ( in_array( 'attribute', $enabled_facets, true ) && function_exists( 'wc_get_attribute_taxonomies' ) ) :
					$attribute_taxonomies = wc_get_attribute_taxonomies();
					if ( ! empty( $attribute_taxonomies ) ) :
						foreach ( $attribute_taxonomies as $tax ) :
							$tax_name = wc_attribute_taxonomy_name( $tax->attribute_name );
							if ( ! taxonomy_exists( $tax_name ) ) continue;
							$terms = get_terms( array( 'taxonomy' => $tax_name, 'hide_empty' => true ) );
							if ( empty( $terms ) || is_wp_error( $terms ) ) continue;
							$get_attr_vals = isset( $_GET[ 'attr_' . $tax->attribute_name ] ) ? ( is_array( $_GET[ 'attr_' . $tax->attribute_name ] ) ? $_GET[ 'attr_' . $tax->attribute_name ] : explode( ',', sanitize_text_field( $_GET[ 'attr_' . $tax->attribute_name ] ) ) ) : array();
							?>
							<div class="wpat-facet-group">
								<label class="wpat-facet-title"><?php echo esc_html( $tax->attribute_label ); ?></label>
								<div class="wpat-facet-checkbox-list">
									<?php foreach ( $terms as $term ) : ?>
										<label class="wpat-facet-checkbox-item">
											<input type="checkbox" name="attr_<?php echo esc_attr( $tax->attribute_name ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" class="wpat-facet-input" <?php checked( in_array( $term->slug, $get_attr_vals, true ) ); ?> />
											<span class="wpat-facet-label"><?php echo esc_html( $term->name ); ?></span>
											<span class="wpat-facet-count">(<?php echo esc_html( $term->count ); ?>)</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach;
					endif;
				endif; ?>

				<?php // 6. Valoración por Estrellas ?>
				<?php if ( in_array( 'rating', $enabled_facets, true ) ) : ?>
					<div class="wpat-facet-group">
						<label class="wpat-facet-title"><?php esc_html_e( 'Valoración', 'wp-agency-toolkit' ); ?></label>
						<div class="wpat-facet-checkbox-list">
							<?php for ( $r = 5; $r >= 3; $r-- ) : ?>
								<label class="wpat-facet-checkbox-item">
									<input type="radio" name="rating" value="<?php echo $r; ?>" class="wpat-facet-input" <?php checked( $get_rating === $r ); ?> />
									<span class="wpat-facet-label"><?php echo str_repeat( '★', $r ) . str_repeat( '☆', 5 - $r ); ?> <?php echo ( 5 === $r ) ? esc_html__( '5 estrellas', 'wp-agency-toolkit' ) : sprintf( esc_html__( '%d+ estrellas', 'wp-agency-toolkit' ), $r ); ?></span>
								</label>
							<?php endfor; ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="wpat-facets-actions">
					<button type="button" class="wpat-reset-facets-btn"><?php esc_html_e( 'Limpiar Filtros', 'wp-agency-toolkit' ); ?></button>
				</div>
			</form>
		</div>
		<div class="wpat-facets-backdrop"></div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Endpoint AJAX para filtrar productos WooCommerce según las facetas marcadas.
	 */
	public function ajax_filter_products() {
		check_ajax_referer( 'wpat_facets_nonce', 'security', false );

		$paged     = isset( $_POST['paged'] ) ? max( 1, intval( $_POST['paged'] ) ) : 1;
		$min_price = isset( $_POST['min_price'] ) && '' !== $_POST['min_price'] ? floatval( $_POST['min_price'] ) : null;
		$max_price = isset( $_POST['max_price'] ) && '' !== $_POST['max_price'] ? floatval( $_POST['max_price'] ) : null;
		$cats      = isset( $_POST['product_cat'] ) && is_array( $_POST['product_cat'] ) ? array_map( 'sanitize_text_field', $_POST['product_cat'] ) : array();
		$in_stock  = ! empty( $_POST['in_stock'] ) && '1' === (string) $_POST['in_stock'];
		$on_sale   = ! empty( $_POST['on_sale'] ) && '1' === (string) $_POST['on_sale'];
		$orderby   = isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'menu_order';
		$rating    = isset( $_POST['rating'] ) ? intval( $_POST['rating'] ) : 0;

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() ),
			'paged'          => $paged,
			'meta_query'     => array( 'relation' => 'AND' ),
			'tax_query'      => array( 'relation' => 'AND' ),
		);

		// Excluir productos ocultos del catálogo y búsquedas
		if ( taxonomy_exists( 'product_visibility' ) ) {
			$product_visibility_term_ids = wc_get_product_visibility_term_ids();
			$args['tax_query'][]         = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( $product_visibility_term_ids['exclude-from-search'], $product_visibility_term_ids['exclude-from-catalog'] ),
				'operator' => 'NOT IN',
			);
		}

		// Rango de precio
		if ( null !== $min_price || null !== $max_price ) {
			$price_query = array( 'key' => '_price', 'type' => 'NUMERIC' );
			if ( null !== $min_price && null !== $max_price ) {
				$price_query['value']   = array( $min_price, $max_price );
				$price_query['compare'] = 'BETWEEN';
			} elseif ( null !== $min_price ) {
				$price_query['value']   = $min_price;
				$price_query['compare'] = '>=';
			} elseif ( null !== $max_price ) {
				$price_query['value']   = $max_price;
				$price_query['compare'] = '<=';
			}
			$args['meta_query'][] = $price_query;
		}

		// Stock
		if ( $in_stock ) {
			$args['meta_query'][] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
		}

		// En oferta
		if ( $on_sale && function_exists( 'wc_get_product_ids_on_sale' ) ) {
			$on_sale_ids = wc_get_product_ids_on_sale();
			if ( ! empty( $on_sale_ids ) ) {
				$args['post__in'] = $on_sale_ids;
			} else {
				$args['post__in'] = array( 0 );
			}
		}

		// Categorías
		if ( ! empty( $cats ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $cats,
				'operator' => 'IN',
			);
		}

		// Atributos dinámicos (attr_color, attr_talla, etc.)
		foreach ( $_POST as $key => $val ) {
			if ( 0 === strpos( $key, 'attr_' ) && ! empty( $val ) && is_array( $val ) ) {
				$attr_slug = sanitize_text_field( substr( $key, 5 ) );
				$tax_name  = ( 0 === strpos( $attr_slug, 'pa_' ) ) ? $attr_slug : 'pa_' . $attr_slug;
				if ( taxonomy_exists( $tax_name ) ) {
					$args['tax_query'][] = array(
						'taxonomy' => $tax_name,
						'field'    => 'slug',
						'terms'    => array_map( 'sanitize_text_field', $val ),
						'operator' => 'IN',
					);
				}
			}
		}

		// Valoraciones (Rating)
		if ( $rating > 0 ) {
			$args['meta_query'][] = array(
				'key'     => '_wc_average_rating',
				'value'   => $rating,
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
		}

		// Ordenación nativa de WooCommerce
		switch ( $orderby ) {
			case 'price-asc':
				$args['meta_key'] = '_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'ASC';
				break;
			case 'price-desc':
				$args['meta_key'] = '_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'popularity':
				$args['meta_key'] = 'total_sales';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'rating':
				$args['meta_key'] = '_wc_average_rating';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			default:
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
				break;
		}

		$query = new WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) {
			woocommerce_product_loop_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			woocommerce_product_loop_end();
			wp_reset_postdata();
		} else {
			echo '<div class="wpat-no-products-wrapper"><p class="woocommerce-info wpat-no-products-found">' . esc_html__( 'No se encontraron productos que coincidan con los filtros seleccionados.', 'wp-agency-toolkit' ) . '</p></div>';
		}
		$grid_html = ob_get_clean();

		// Generar HTML de paginación
		$total_pages = $query->max_num_pages;
		$pagination_html = '';
		if ( $total_pages > 1 ) {
			$pagination_html = paginate_links( array(
				'base'      => '%_%',
				'format'    => '?paged=%#%',
				'current'   => $paged,
				'total'     => $total_pages,
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'type'      => 'list',
			) );
		}

		wp_send_json_success( array(
			'html'        => $grid_html,
			'pagination'  => $pagination_html,
			'found_posts' => $query->found_posts,
			'count_text'  => sprintf( __( 'Mostrando %d productos encontrados', 'wp-agency-toolkit' ), $query->found_posts ),
		) );
	}
}

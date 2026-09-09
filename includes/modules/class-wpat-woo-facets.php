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

		wp_localize_script( 'wpat-woo-facets', 'wpatWooFacets', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'loading' => __( 'Cargando productos...', 'wp-agency-toolkit' ),
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

		$settings       = WPAT_Main::get_instance()->get_settings();
		$enabled_facets = isset( $settings['facets_config'] ) && is_array( $settings['facets_config'] ) ? $settings['facets_config'] : explode( ',', $atts['facets'] );

		ob_start();
		?>
		<div class="wpat-facets-wrapper">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="wpat-facets-main-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<form class="wpat-facets-form">

				<?php // 1. Ordenación ?>
				<?php if ( in_array( 'sort', $enabled_facets, true ) ) : ?>
					<div class="wpat-facet-group">
						<label class="wpat-facet-title"><?php esc_html_e( 'Ordenar por', 'wp-agency-toolkit' ); ?></label>
						<select name="orderby" class="wpat-facet-select wpat-facet-input">
							<option value="menu_order"><?php esc_html_e( 'Relevancia por defecto', 'wp-agency-toolkit' ); ?></option>
							<option value="date"><?php esc_html_e( 'Más recientes', 'wp-agency-toolkit' ); ?></option>
							<option value="price-asc"><?php esc_html_e( 'Precio: Menor a Mayor', 'wp-agency-toolkit' ); ?></option>
							<option value="price-desc"><?php esc_html_e( 'Precio: Mayor a Menor', 'wp-agency-toolkit' ); ?></option>
							<option value="rating"><?php esc_html_e( 'Mejor valorados', 'wp-agency-toolkit' ); ?></option>
						</select>
					</div>
				<?php endif; ?>

				<?php // 2. Rango de Precio ?>
				<?php if ( in_array( 'price', $enabled_facets, true ) ) :
					global $wpdb;
					$min_price = floor( $wpdb->get_var( "SELECT MIN(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key='_price' AND meta_value != ''" ) ?: 0 );
					$max_price = ceil( $wpdb->get_var( "SELECT MAX(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key='_price' AND meta_value != ''" ) ?: 500 );
					?>
					<div class="wpat-facet-group">
						<label class="wpat-facet-title"><?php esc_html_e( 'Rango de Precio (€)', 'wp-agency-toolkit' ); ?></label>
						<div class="wpat-facet-price-range">
							<input type="number" name="min_price" class="wpat-facet-input min-price-input" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" placeholder="<?php echo $min_price; ?>" />
							<span>—</span>
							<input type="number" name="max_price" class="wpat-facet-input max-price-input" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" placeholder="<?php echo $max_price; ?>" />
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
										<input type="checkbox" name="product_cat[]" value="<?php echo esc_attr( $cat->slug ); ?>" class="wpat-facet-input" />
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
								<input type="checkbox" name="in_stock" value="1" class="wpat-facet-input" />
								<span class="wpat-facet-label"><?php esc_html_e( 'Solo en Stock', 'wp-agency-toolkit' ); ?></span>
							</label>
							<label class="wpat-facet-checkbox-item">
								<input type="checkbox" name="on_sale" value="1" class="wpat-facet-input" />
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
							?>
							<div class="wpat-facet-group">
								<label class="wpat-facet-title"><?php echo esc_html( $tax->attribute_label ); ?></label>
								<div class="wpat-facet-checkbox-list">
									<?php foreach ( $terms as $term ) : ?>
										<label class="wpat-facet-checkbox-item">
											<input type="checkbox" name="attr_<?php echo esc_attr( $tax->attribute_name ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" class="wpat-facet-input" />
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
									<input type="radio" name="rating" value="<?php echo $r; ?>" class="wpat-facet-input" />
									<span class="wpat-facet-label"><?php echo str_repeat( '★', $r ) . str_repeat( '☆', 5 - $r ); ?> <?php echo ( 5 === $r ) ? '5 estrellas' : $r . '+ estrellas'; ?></span>
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
		<?php
		return ob_get_clean();
	}

	/**
	 * Endpoint AJAX para filtrar productos WooCommerce según las facetas marcadas.
	 */
	public function ajax_filter_products() {
		$paged     = isset( $_POST['paged'] ) ? max( 1, intval( $_POST['paged'] ) ) : 1;
		$min_price = isset( $_POST['min_price'] ) && '' !== $_POST['min_price'] ? floatval( $_POST['min_price'] ) : null;
		$max_price = isset( $_POST['max_price'] ) && '' !== $_POST['max_price'] ? floatval( $_POST['max_price'] ) : null;
		$cats      = isset( $_POST['product_cat'] ) && is_array( $_POST['product_cat'] ) ? array_map( 'sanitize_text_field', $_POST['product_cat'] ) : array();
		$in_stock  = ! empty( $_POST['in_stock'] ) && '1' === $_POST['in_stock'];
		$on_sale   = ! empty( $_POST['on_sale'] ) && '1' === $_POST['on_sale'];
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

		// Excluir productos ocultos
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
				$tax_name  = wc_attribute_taxonomy_name( $attr_slug );
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

		// Ordenación
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
			echo '<p class="woocommerce-info wpat-no-products-found">' . esc_html__( 'No se encontraron productos que coincidan con los filtros seleccionados.', 'wp-agency-toolkit' ) . '</p>';
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

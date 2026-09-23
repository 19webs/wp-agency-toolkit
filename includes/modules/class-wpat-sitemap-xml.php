<?php
/**
 * Módulo: Generador de Sitemap XML Profesional - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Sitemap_XML {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Sitemap_XML|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Sitemap_XML
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
		// 1. Interceptar peticiones a /sitemap.xml, /sitemap.xsl y vistas visuales
		add_action( 'init', array( $this, 'intercept_sitemap_request' ), 99 );
		add_action( 'template_redirect', array( $this, 'intercept_sitemap_request' ), 1 );

		// 2. Desactivar el sitemap por defecto de WordPress 5.5+ para evitar duplicidades
		add_filter( 'wp_sitemaps_enabled', '__return_false' );

		// 3. Añadir la directiva Sitemap a robots.txt
		add_filter( 'robots_txt', array( $this, 'add_sitemap_to_robots_txt' ), 20, 2 );
	}

	/**
	 * Intercepta las peticiones de sitemap.xml, sitemap.xsl y vista visual.
	 */
	public function intercept_sitemap_request() {
		static $handled = false;
		if ( $handled ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$request_uri   = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_path  = parse_url( $request_uri, PHP_URL_PATH );
		$home_path     = parse_url( home_url(), PHP_URL_PATH );
		$relative_path = $request_path;

		// Si WordPress está en una subcarpeta
		if ( ! empty( $home_path ) && '/' !== $home_path ) {
			if ( 0 === strpos( $request_path, $home_path ) ) {
				$relative_path = substr( $request_path, strlen( $home_path ) );
			}
		}
		$relative_path = trim( (string) $relative_path, '/' );
		$lower_path    = strtolower( $relative_path );

		// 1. Redirigir wp-sitemap.xml de WP Core al sitemap nativo
		if ( 'wp-sitemap.xml' === $lower_path ) {
			$handled = true;
			wp_safe_redirect( home_url( '/sitemap.xml' ), 301 );
			exit;
		}

		// 2. Servir hoja de estilo XSL
		if ( 'sitemap.xsl' === $lower_path || isset( $_GET['wpat_sitemap_xsl'] ) ) {
			$handled = true;
			$this->render_xsl_stylesheet();
			exit;
		}

		// 3. Servir vista visual interactiva (HTML)
		$is_visual = ( isset( $_GET['view'] ) && 'visual' === $_GET['view'] ) ||
					( isset( $_GET['format'] ) && 'html' === $_GET['format'] ) ||
					( isset( $_GET['wpat_sitemap'] ) && 'visual' === $_GET['wpat_sitemap'] ) ||
					'sitemap-visual' === $lower_path ||
					'sitemap.html' === $lower_path;

		if ( $is_visual && ( 'sitemap.xml' === $lower_path || isset( $_GET['wpat_sitemap'] ) || isset( $_GET['sitemap'] ) || 'sitemap-visual' === $lower_path || 'sitemap.html' === $lower_path ) ) {
			$handled = true;
			$this->render_html_visual_sitemap();
			exit;
		}

		// 4. Servir sitemap.xml estándar o descarga
		if ( 'sitemap.xml' === $lower_path || isset( $_GET['wpat_sitemap'] ) || ( isset( $_GET['sitemap'] ) && '1' === (string) $_GET['sitemap'] ) ) {
			$handled = true;
			$this->generate_xml_sitemap();
			exit;
		}
	}

	/**
	 * Añade la URL del Sitemap XML al archivo robots.txt virtual.
	 *
	 * @param string $output Contenido actual de robots.txt.
	 * @param bool   $public Si el sitio es público.
	 * @return string
	 */
	public function add_sitemap_to_robots_txt( $output, $public ) {
		if ( '0' !== (string) $public ) {
			$sitemap_url = esc_url( home_url( '/sitemap.xml' ) );
			if ( false === strpos( $output, 'Sitemap:' ) ) {
				$output .= "\nSitemap: " . $sitemap_url . "\n";
			}
		}
		return $output;
	}

	/**
	 * Recopila todos los elementos que forman parte del sitemap según la configuración.
	 *
	 * @return array
	 */
	public function get_sitemap_items() {
		$settings       = WPAT_Main::get_instance()->get_settings();
		$home_url       = home_url( '/' );
		$include_posts  = ! isset( $settings['sitemap_include_posts'] ) || '1' === (string) $settings['sitemap_include_posts'];
		$include_pages  = ! isset( $settings['sitemap_include_pages'] ) || '1' === (string) $settings['sitemap_include_pages'];
		$include_prods  = ! isset( $settings['sitemap_include_products'] ) || '1' === (string) $settings['sitemap_include_products'];
		$include_tax    = ! isset( $settings['sitemap_include_taxonomies'] ) || '1' === (string) $settings['sitemap_include_taxonomies'];
		$include_images = ! isset( $settings['sitemap_include_images'] ) || '1' === (string) $settings['sitemap_include_images'];
		$excluded_raw   = isset( $settings['sitemap_exclude_urls'] ) ? (string) $settings['sitemap_exclude_urls'] : '';

		$excluded_lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $excluded_raw ) ) ) );
		$items          = array();

		// 1. Página de Portada (Home)
		$front_page_id = (int) get_option( 'page_on_front' );
		$home_noindex  = $front_page_id ? get_post_meta( $front_page_id, '_wpat_seo_noindex', true ) : '0';

		if ( '1' !== $home_noindex && ! $this->is_url_excluded( $home_url, $excluded_lines ) ) {
			$latest_post = get_posts(
				array(
					'post_type'      => 'any',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				)
			);
			$home_modified = ! empty( $latest_post ) ? get_the_modified_date( 'c', $latest_post[0]->ID ) : gmdate( 'c' );

			$items[] = array(
				'loc'        => $home_url,
				'lastmod'    => $home_modified,
				'changefreq' => 'daily',
				'priority'   => '1.0',
				'type'       => 'Portada',
				'type_slug'  => 'home',
				'title'      => get_bloginfo( 'name' ) . ' - Inicio',
				'image'      => '',
			);
		}

		// 2. Entradas, Páginas, Productos y CPTs
		$post_types = array();
		if ( $include_posts ) {
			$post_types[] = 'post';
		}
		if ( $include_pages ) {
			$post_types[] = 'page';
		}
		if ( $include_prods && class_exists( 'WooCommerce' ) ) {
			$post_types[] = 'product';
		}

		// Incluir otros CPTs públicos no excluidos
		$custom_cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		foreach ( $custom_cpts as $cpt ) {
			if ( 'product' !== $cpt && 'attachment' !== $cpt ) {
				$post_types[] = $cpt;
			}
		}

		if ( ! empty( $post_types ) ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_types,
					'post_status'    => 'publish',
					'posts_per_page' => 3000,
					'orderby'        => 'modified',
					'order'          => 'DESC',
				)
			);

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					// Comprobar directiva noindex de SEO
					$noindex = get_post_meta( $post->ID, '_wpat_seo_noindex', true );
					if ( '1' === $noindex ) {
						continue;
					}

					$permalink = get_permalink( $post->ID );
					if ( ! $permalink || $permalink === $home_url || $this->is_url_excluded( $permalink, $excluded_lines ) ) {
						continue;
					}

					$priority  = '0.8';
					$freq      = 'weekly';
					$type_name = 'Entrada';

					if ( 'page' === $post->post_type ) {
						$priority  = '0.7';
						$freq      = 'monthly';
						$type_name = 'Página';
					} elseif ( 'product' === $post->post_type ) {
						$priority  = '0.9';
						$freq      = 'weekly';
						$type_name = 'Producto';
					} elseif ( 'post' !== $post->post_type ) {
						$pt_obj    = get_post_type_object( $post->post_type );
						$type_name = $pt_obj ? $pt_obj->labels->singular_name : $post->post_type;
					}

					$last_mod  = get_the_modified_date( 'c', $post->ID );
					$thumb_url = '';
					if ( $include_images && has_post_thumbnail( $post->ID ) ) {
						$thumb_id = get_post_thumbnail_id( $post->ID );
						$t_src    = wp_get_attachment_image_url( $thumb_id, 'full' );
						if ( $t_src ) {
							$thumb_url = $t_src;
						}
					}

					$items[] = array(
						'loc'        => $permalink,
						'lastmod'    => $last_mod,
						'changefreq' => $freq,
						'priority'   => $priority,
						'type'       => $type_name,
						'type_slug'  => $post->post_type,
						'title'      => get_the_title( $post->ID ) ? get_the_title( $post->ID ) : '(Sin título)',
						'image'      => $thumb_url,
					);
				}
			}
		}

		// 3. Taxonomías Públicas (Categorías, Etiquetas, Categorías de Producto)
		if ( $include_tax ) {
			$taxonomies         = get_taxonomies( array( 'public' => true ), 'names' );
			$exclude_taxonomies = array( 'post_format', 'nav_menu', 'link_category' );
			$taxonomies         = array_diff( $taxonomies, $exclude_taxonomies );

			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => true,
					)
				);

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$tax_obj   = get_taxonomy( $taxonomy );
					$tax_label = $tax_obj ? $tax_obj->labels->singular_name : 'Taxonomía';

					foreach ( $terms as $term ) {
						$term_url = get_term_link( $term );
						if ( ! is_wp_error( $term_url ) && ! $this->is_url_excluded( $term_url, $excluded_lines ) ) {
							$items[] = array(
								'loc'        => $term_url,
								'lastmod'    => gmdate( 'c' ),
								'changefreq' => 'weekly',
								'priority'   => '0.5',
								'type'       => $tax_label,
								'type_slug'  => 'taxonomy',
								'title'      => $term->name,
								'image'      => '',
							);
						}
					}
				}
			}
		}

		return $items;
	}

	/**
	 * Genera el documento XML de Sitemap con soporte para imágenes, directivas noindex y exclusiones manuales.
	 */
	public function generate_xml_sitemap() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		if ( isset( $_GET['download'] ) && '1' === (string) $_GET['download'] ) {
			header( 'Content-Disposition: attachment; filename="sitemap.xml"' );
		}

		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );

		$items = $this->get_sitemap_items();

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		foreach ( $items as $item ) {
			echo "  <url>\n";
			echo '    <loc>' . esc_url( $item['loc'] ) . "</loc>\n";
			echo '    <lastmod>' . esc_html( $item['lastmod'] ) . "</lastmod>\n";
			echo '    <changefreq>' . esc_html( $item['changefreq'] ) . "</changefreq>\n";
			echo '    <priority>' . esc_html( $item['priority'] ) . "</priority>\n";

			if ( ! empty( $item['image'] ) ) {
				echo "    <image:image>\n";
				echo '      <image:loc>' . esc_url( $item['image'] ) . "</image:loc>\n";
				if ( ! empty( $item['title'] ) ) {
					echo '      <image:title>' . esc_html( $item['title'] ) . "</image:title>\n";
				}
				echo "    </image:image>\n";
			}

			echo "  </url>\n";
		}

		echo '</urlset>' . "\n";
	}

	/**
	 * Renderiza una vista visual HTML5 ultra-rápida, moderna e interactiva del Sitemap.
	 */
	public function render_html_visual_sitemap() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		$items       = $this->get_sitemap_items();
		$site_name   = get_bloginfo( 'name' );
		$raw_xml_url = add_query_arg( array( 'wpat_sitemap' => '1', 'format' => 'xml' ), home_url( '/' ) );
		$dl_url      = add_query_arg( array( 'wpat_sitemap' => '1', 'download' => '1' ), home_url( '/' ) );
		$admin_url   = admin_url( 'admin.php?page=wp-agency-toolkit#wpat-sitemap-xml' );
		?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sitemap Visual - <?php echo esc_html( $site_name ); ?></title>
	<meta name="robots" content="noindex, follow">
	<style>
		:root {
			--bg-body: #f1f5f9;
			--bg-card: #ffffff;
			--text-main: #0f172a;
			--text-muted: #64748b;
			--primary: #2563eb;
			--primary-hover: #1d4ed8;
			--border: #e2e8f0;
			--radius: 10px;
		}
		* { box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background-color: var(--bg-body);
			color: var(--text-main);
			margin: 0;
			padding: 30px 15px;
			line-height: 1.5;
		}
		.wpat-container {
			max-width: 1100px;
			margin: 0 auto;
		}
		.wpat-header {
			background: var(--bg-card);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			padding: 24px 28px;
			margin-bottom: 20px;
			box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04);
		}
		.wpat-header-top {
			display: flex;
			justify-content: space-between;
			align-items: center;
			flex-wrap: wrap;
			gap: 16px;
			border-bottom: 1px solid var(--border);
			padding-bottom: 18px;
			margin-bottom: 18px;
		}
		.wpat-title-area h1 {
			margin: 0;
			font-size: 22px;
			font-weight: 700;
			color: var(--text-main);
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.wpat-title-area p {
			margin: 4px 0 0 0;
			font-size: 13px;
			color: var(--text-muted);
		}
		.wpat-btn-group {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}
		.wpat-btn {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 8px 14px;
			border-radius: 6px;
			font-size: 13px;
			font-weight: 600;
			text-decoration: none;
			cursor: pointer;
			border: 1px solid transparent;
			transition: all 0.15s ease;
		}
		.wpat-btn-primary {
			background: var(--primary);
			color: #ffffff;
		}
		.wpat-btn-primary:hover {
			background: var(--primary-hover);
		}
		.wpat-btn-secondary {
			background: #ffffff;
			color: #334155;
			border-color: #cbd5e1;
		}
		.wpat-btn-secondary:hover {
			background: #f8fafc;
			border-color: #94a3b8;
		}
		.wpat-stats-bar {
			display: flex;
			align-items: center;
			gap: 20px;
			flex-wrap: wrap;
			font-size: 13px;
		}
		.wpat-stat-chip {
			background: #eff6ff;
			color: var(--primary);
			padding: 4px 12px;
			border-radius: 9999px;
			font-weight: 600;
		}
		.wpat-filter-card {
			background: var(--bg-card);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			padding: 16px 20px;
			margin-bottom: 20px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			flex-wrap: wrap;
			gap: 14px;
		}
		.wpat-search-input {
			flex: 1;
			min-width: 260px;
			padding: 9px 14px;
			font-size: 14px;
			border: 1px solid #cbd5e1;
			border-radius: 6px;
			outline: none;
		}
		.wpat-search-input:focus {
			border-color: var(--primary);
			box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
		}
		.wpat-tabs {
			display: flex;
			gap: 6px;
			flex-wrap: wrap;
		}
		.wpat-tab-btn {
			padding: 6px 12px;
			border-radius: 6px;
			font-size: 12px;
			font-weight: 600;
			background: #f1f5f9;
			color: #475569;
			border: 1px solid transparent;
			cursor: pointer;
		}
		.wpat-tab-btn.active, .wpat-tab-btn:hover {
			background: #e2e8f0;
			color: #0f172a;
		}
		.wpat-tab-btn.active {
			background: var(--primary);
			color: #ffffff;
		}
		.wpat-table-wrapper {
			background: var(--bg-card);
			border: 1px solid var(--border);
			border-radius: var(--radius);
			overflow: hidden;
			box-shadow: 0 4px 6px -1px rgba(0,0,0,0.04);
		}
		table {
			width: 100%;
			border-collapse: collapse;
			font-size: 13px;
		}
		th {
			background: #f8fafc;
			color: #475569;
			text-align: left;
			padding: 12px 16px;
			font-weight: 600;
			border-bottom: 2px solid var(--border);
		}
		td {
			padding: 12px 16px;
			border-bottom: 1px solid #f1f5f9;
			vertical-align: middle;
		}
		tr:last-child td { border-bottom: none; }
		tr:hover td { background-color: #f8fafc; }
		.wpat-url-link {
			color: var(--primary);
			text-decoration: none;
			font-weight: 500;
			word-break: break-all;
		}
		.wpat-url-link:hover {
			text-decoration: underline;
		}
		.wpat-badge-type {
			display: inline-block;
			background: #f1f5f9;
			color: #334155;
			padding: 2px 8px;
			border-radius: 4px;
			font-size: 11px;
			font-weight: 600;
		}
		.wpat-badge-prio {
			display: inline-block;
			background: #e0e7ff;
			color: #4338ca;
			padding: 2px 8px;
			border-radius: 4px;
			font-size: 11px;
			font-weight: 700;
		}
		.wpat-badge-img {
			display: inline-flex;
			align-items: center;
			gap: 3px;
			background: #ecfdf5;
			color: #059669;
			padding: 2px 6px;
			border-radius: 4px;
			font-size: 11px;
			font-weight: 600;
			margin-left: 6px;
		}
		.wpat-footer {
			margin-top: 24px;
			text-align: center;
			font-size: 12px;
			color: var(--text-muted);
		}
	</style>
</head>
<body>

<div class="wpat-container">
	<!-- Encabezado -->
	<div class="wpat-header">
		<div class="wpat-header-top">
			<div class="wpat-title-area">
				<h1>⚡ Sitemap XML Visual</h1>
				<p>Índice visual en vivo de URLs indexadas para <?php echo esc_html( $site_name ); ?></p>
			</div>
			<div class="wpat-btn-group">
				<a href="<?php echo esc_url( $raw_xml_url ); ?>" class="wpat-btn wpat-btn-secondary" target="_blank" title="Ver archivo XML puro">
					📄 Ver XML en Bruto
				</a>
				<a href="<?php echo esc_url( $dl_url ); ?>" class="wpat-btn wpat-btn-secondary" title="Descargar sitemap.xml">
					⬇️ Descargar XML
				</a>
				<a href="<?php echo esc_url( $admin_url ); ?>" class="wpat-btn wpat-btn-primary">
					⚙️ Ajustes en Toolkit
				</a>
			</div>
		</div>

		<div class="wpat-stats-bar">
			<span class="wpat-stat-chip">Total URLs: <strong id="wpat_total_count"><?php echo count( $items ); ?></strong></span>
			<span>Compatible con Google Search Console &amp; Bing Webmaster</span>
		</div>
	</div>

	<!-- Barra de Búsqueda y Filtros -->
	<div class="wpat-filter-card">
		<input type="text" id="wpat_search" class="wpat-search-input" placeholder="Buscar por URL o título en tiempo real..." onkeyup="filterSitemapTable()" />
		<div class="wpat-tabs">
			<button type="button" class="wpat-tab-btn active" onclick="filterType('all', this)">Todos</button>
			<button type="button" class="wpat-tab-btn" onclick="filterType('home', this)">Portada</button>
			<button type="button" class="wpat-tab-btn" onclick="filterType('post', this)">Entradas</button>
			<button type="button" class="wpat-tab-btn" onclick="filterType('page', this)">Páginas</button>
			<button type="button" class="wpat-tab-btn" onclick="filterType('product', this)">Productos</button>
			<button type="button" class="wpat-tab-btn" onclick="filterType('taxonomy', this)">Taxonomías</button>
		</div>
	</div>

	<!-- Tabla de URLs -->
	<div class="wpat-table-wrapper">
		<table id="wpat_sitemap_table">
			<thead>
				<tr>
					<th style="width: 48%;">URL / Contenido</th>
					<th style="width: 14%;">Tipo</th>
					<th style="width: 10%;">Prioridad</th>
					<th style="width: 12%;">Frecuencia</th>
					<th style="width: 16%;">Última Modificación</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr class="sitemap-row" data-type="<?php echo esc_attr( $item['type_slug'] ); ?>" data-search="<?php echo esc_attr( strtolower( $item['loc'] . ' ' . $item['title'] ) ); ?>">
						<td>
							<div>
								<a href="<?php echo esc_url( $item['loc'] ); ?>" target="_blank" class="wpat-url-link">
									<?php echo esc_html( $item['loc'] ); ?>
								</a>
								<?php if ( ! empty( $item['image'] ) ) : ?>
									<span class="wpat-badge-img" title="Contiene imagen destacada">📷 Imagen</span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $item['title'] ) && $item['title'] !== $item['loc'] ) : ?>
								<div style="font-size: 11px; color: #64748b; margin-top: 2px;">
									<?php echo esc_html( $item['title'] ); ?>
								</div>
							<?php endif; ?>
						</td>
						<td>
							<span class="wpat-badge-type"><?php echo esc_html( $item['type'] ); ?></span>
						</td>
						<td>
							<span class="wpat-badge-prio"><?php echo esc_html( $item['priority'] ); ?></span>
						</td>
						<td style="color: #64748b;">
							<?php echo esc_html( $item['changefreq'] ); ?>
						</td>
						<td style="color: #64748b; font-size: 12px;">
							<?php echo esc_html( substr( $item['lastmod'], 0, 10 ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="wpat-footer">
		Generado dinámicamente por <strong>WP Agency Toolkit</strong> • Optimizado para SEO Técnico y Core Web Vitals
	</div>
</div>

<script>
	let currentFilter = 'all';

	function filterType(type, btn) {
		currentFilter = type;
		document.querySelectorAll('.wpat-tab-btn').forEach(b => b.classList.remove('active'));
		btn.classList.add('active');
		filterSitemapTable();
	}

	function filterSitemapTable() {
		const query = document.getElementById('wpat_search').value.toLowerCase().trim();
		const rows = document.querySelectorAll('#wpat_sitemap_table tbody tr.sitemap-row');
		let visibleCount = 0;

		rows.forEach(row => {
			const type = row.getAttribute('data-type');
			const search = row.getAttribute('data-search') || '';

			const matchType = (currentFilter === 'all' || type === currentFilter);
			const matchQuery = (query === '' || search.indexOf(query) !== -1);

			if (matchType && matchQuery) {
				row.style.display = '';
				visibleCount++;
			} else {
				row.style.display = 'none';
			}
		});

		document.getElementById('wpat_total_count').textContent = visibleCount;
	}
</script>

</body>
</html>
		<?php
	}

	/**
	 * Comprueba si una URL coincide con la lista de exclusiones manuales.
	 *
	 * @param string $url            URL completa.
	 * @param array  $excluded_lines Líneas de exclusión.
	 * @return bool
	 */
	private function is_url_excluded( $url, $excluded_lines ) {
		if ( empty( $excluded_lines ) ) {
			return false;
		}

		foreach ( $excluded_lines as $pattern ) {
			$pattern = trim( $pattern );
			if ( empty( $pattern ) ) {
				continue;
			}

			// Coincidencia exacta o por subcadena
			if ( $url === $pattern || false !== strpos( $url, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Genera la plantilla XSL para visualización estética en el navegador.
	 */
	public function render_xsl_stylesheet() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/xml; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'X-Content-Type-Options: nosniff' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		?>
<xsl:stylesheet version="1.0" 
	xmlns:html="http://www.w3.org/TR/REC-html40"
	xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
	xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title>Sitemap XML - <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<style type="text/css">
					body {
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
						color: #334155;
						background-color: #f8fafc;
						margin: 0;
						padding: 40px 20px;
					}
					.container {
						max-width: 1000px;
						margin: 0 auto;
						background: #ffffff;
						padding: 30px;
						border-radius: 12px;
						box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
						border: 1px solid #e2e8f0;
					}
					h1 {
						font-size: 24px;
						color: #0f172a;
						margin-top: 0;
						margin-bottom: 8px;
						display: flex;
						align-items: center;
						gap: 10px;
					}
					p.desc {
						font-size: 14px;
						color: #64748b;
						line-height: 1.5;
						margin-bottom: 24px;
					}
					.stats {
						display: inline-block;
						background: #eff6ff;
						color: #1d4ed8;
						padding: 4px 12px;
						border-radius: 9999px;
						font-size: 13px;
						font-weight: 600;
						margin-bottom: 20px;
					}
					table {
						width: 100%;
						border-collapse: collapse;
						font-size: 13px;
					}
					th {
						background-color: #f1f5f9;
						color: #475569;
						text-align: left;
						padding: 12px 14px;
						font-weight: 600;
						border-bottom: 2px solid #e2e8f0;
					}
					td {
						padding: 10px 14px;
						border-bottom: 1px solid #f1f5f9;
						vertical-align: middle;
					}
					tr:hover td {
						background-color: #f8fafc;
					}
					a {
						color: #2563eb;
						text-decoration: none;
						word-break: break-all;
					}
					a:hover {
						text-decoration: underline;
					}
					.badge {
						background: #e0e7ff;
						color: #4338ca;
						padding: 2px 8px;
						border-radius: 4px;
						font-size: 11px;
						font-weight: 600;
					}
					.footer {
						margin-top: 24px;
						font-size: 12px;
						color: #94a3b8;
						text-align: center;
					}
				</style>
			</head>
			<body>
				<div class="container">
					<h1>⚡ Sitemap XML</h1>
					<p class="desc">
						Este es un índice de sitemap XML generado automáticamente por <strong>WP Agency Toolkit</strong> para motores de búsqueda como Google y Bing.
					</p>
					<div class="stats">
						Total de URLs indexadas: <xsl:value-of select="count(sitemap:urlset/sitemap:url)"/>
					</div>

					<table>
						<thead>
							<tr>
								<th style="width: 55%;">URL</th>
								<th style="width: 15%;">Prioridad</th>
								<th style="width: 15%;">Frecuencia</th>
								<th style="width: 15%;">Última Modificación</th>
							</tr>
						</thead>
						<tbody>
							<xsl:for-each select="sitemap:urlset/sitemap:url">
								<tr>
									<td>
										<a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a>
										<xsl:if select="image:image">
											<span style="margin-left: 8px; font-size: 11px; color: #10b981;">📷 Con imagen</span>
										</xsl:if>
									</td>
									<td>
										<span class="badge"><xsl:value-of select="sitemap:priority"/></span>
									</td>
									<td>
										<xsl:value-of select="sitemap:changefreq"/>
									</td>
									<td style="color: #64748b; font-size: 12px;">
										<xsl:value-of select="substring(sitemap:lastmod, 1, 10)"/>
									</td>
								</tr>
							</xsl:for-each>
						</tbody>
					</table>

					<div class="footer">
						Generado con WP Agency Toolkit • Compatible con Google Search Console &amp; Bing Webmaster
					</div>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
		<?php
	}

	/**
	 * Obtiene estadísticas del Sitemap para el panel de administración.
	 *
	 * @return array
	 */
	public static function get_sitemap_summary() {
		$posts_count = (int) wp_count_posts( 'post' )->publish;
		$pages_count = (int) wp_count_posts( 'page' )->publish;
		$prods_count = class_exists( 'WooCommerce' ) ? (int) wp_count_posts( 'product' )->publish : 0;

		$cats_count = wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => true ) );
		$tags_count = wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => true ) );
		$tax_total  = ( ! is_wp_error( $cats_count ) ? (int) $cats_count : 0 ) + ( ! is_wp_error( $tags_count ) ? (int) $tags_count : 0 );

		return array(
			'posts'       => $posts_count,
			'pages'       => $pages_count,
			'products'    => $prods_count,
			'taxonomies'  => $tax_total,
			'total_est'   => ( 1 + $posts_count + $pages_count + $prods_count + $tax_total ),
			'sitemap_url' => home_url( '/sitemap.xml' ),
			'raw_url'     => add_query_arg( array( 'wpat_sitemap' => '1', 'format' => 'xml' ), home_url( '/' ) ),
			'visual_url'  => add_query_arg( array( 'wpat_sitemap' => '1', 'view' => 'visual' ), home_url( '/' ) ),
			'dl_url'      => add_query_arg( array( 'wpat_sitemap' => '1', 'download' => '1' ), home_url( '/' ) ),
		);
	}
}

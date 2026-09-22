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
		// 1. Interceptar peticiones a /sitemap.xml y /sitemap.xsl
		add_action( 'init', array( $this, 'intercept_sitemap_request' ), 1 );

		// 2. Desactivar el sitemap por defecto de WordPress 5.5+ para evitar duplicidades
		add_filter( 'wp_sitemaps_enabled', '__return_false' );

		// 3. Añadir la directiva Sitemap a robots.txt
		add_filter( 'robots_txt', array( $this, 'add_sitemap_to_robots_txt' ), 20, 2 );
	}

	/**
	 * Intercepta las peticiones de sitemap.xml y hoja de estilos sitemap.xsl.
	 */
	public function intercept_sitemap_request() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$request_path  = parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$home_path     = parse_url( home_url(), PHP_URL_PATH );
		$relative_path = $request_path;

		// Si WordPress está en una subcarpeta
		if ( ! empty( $home_path ) && '/' !== $home_path ) {
			if ( 0 === strpos( $request_path, $home_path ) ) {
				$relative_path = substr( $request_path, strlen( $home_path ) );
			}
		}
		$relative_path = trim( $relative_path, '/' );
		$lower_path    = strtolower( $relative_path );

		// 1. Redirigir wp-sitemap.xml de WP Core al sitemap nativo
		if ( 'wp-sitemap.xml' === $lower_path ) {
			wp_safe_redirect( home_url( '/sitemap.xml' ), 301 );
			exit;
		}

		// 2. Servir hoja de estilo XSL
		if ( 'sitemap.xsl' === $lower_path ) {
			$this->render_xsl_stylesheet();
			exit;
		}

		// 3. Servir sitemap.xml
		if ( 'sitemap.xml' === $lower_path ) {
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
	 * Genera el documento XML de Sitemap con soporte para imágenes, directivas noindex y exclusiones manuales.
	 */
	public function generate_xml_sitemap() {
		$settings = WPAT_Main::get_instance()->get_settings();

		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, follow' );

		$xsl_url = home_url( '/sitemap.xsl' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		$home_url        = home_url( '/' );
		$include_posts   = ! isset( $settings['sitemap_include_posts'] ) || '1' === (string) $settings['sitemap_include_posts'];
		$include_pages   = ! isset( $settings['sitemap_include_pages'] ) || '1' === (string) $settings['sitemap_include_pages'];
		$include_prods   = ! isset( $settings['sitemap_include_products'] ) || '1' === (string) $settings['sitemap_include_products'];
		$include_tax     = ! isset( $settings['sitemap_include_taxonomies'] ) || '1' === (string) $settings['sitemap_include_taxonomies'];
		$include_images  = ! isset( $settings['sitemap_include_images'] ) || '1' === (string) $settings['sitemap_include_images'];
		$excluded_raw    = isset( $settings['sitemap_exclude_urls'] ) ? (string) $settings['sitemap_exclude_urls'] : '';

		$excluded_lines = array_filter( array_map( 'trim', explode( "\n", str_replace( "\r", '', $excluded_raw ) ) ) );

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

			echo "  <url>\n";
			echo '    <loc>' . esc_url( $home_url ) . "</loc>\n";
			echo '    <lastmod>' . esc_html( $home_modified ) . "</lastmod>\n";
			echo "    <changefreq>daily</changefreq>\n";
			echo "    <priority>1.0</priority>\n";
			echo "  </url>\n";
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

					// Prioridades y Frecuencias
					$priority = '0.8';
					$freq     = 'weekly';

					if ( 'page' === $post->post_type ) {
						$priority = '0.7';
						$freq     = 'monthly';
					} elseif ( 'product' === $post->post_type ) {
						$priority = '0.9';
						$freq     = 'weekly';
					}

					$last_mod = get_the_modified_date( 'c', $post->ID );

					echo "  <url>\n";
					echo '    <loc>' . esc_url( $permalink ) . "</loc>\n";
					echo '    <lastmod>' . esc_html( $last_mod ) . "</lastmod>\n";
					echo '    <changefreq>' . esc_html( $freq ) . "</changefreq>\n";
					echo '    <priority>' . esc_html( $priority ) . "</priority>\n";

					// Inclusión de imagen destacada (Google Image Sitemap)
					if ( $include_images && has_post_thumbnail( $post->ID ) ) {
						$thumb_id  = get_post_thumbnail_id( $post->ID );
						$thumb_url = wp_get_attachment_image_url( $thumb_id, 'full' );
						if ( $thumb_url ) {
							$thumb_title = get_the_title( $thumb_id );
							echo "    <image:image>\n";
							echo '      <image:loc>' . esc_url( $thumb_url ) . "</image:loc>\n";
							if ( ! empty( $thumb_title ) ) {
								echo '      <image:title>' . esc_html( $thumb_title ) . "</image:title>\n";
							}
							echo "    </image:image>\n";
						}
					}

					echo "  </url>\n";
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
					foreach ( $terms as $term ) {
						$term_url = get_term_link( $term );
						if ( ! is_wp_error( $term_url ) && ! $this->is_url_excluded( $term_url, $excluded_lines ) ) {
							echo "  <url>\n";
							echo '    <loc>' . esc_url( $term_url ) . "</loc>\n";
							echo "    <changefreq>weekly</changefreq>\n";
							echo "    <priority>0.5</priority>\n";
							echo "  </url>\n";
						}
					}
				}
			}
		}

		echo '</urlset>' . "\n";
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
		header( 'Content-Type: text/xsl; charset=utf-8' );
		?>
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" 
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
										<xsl:value-of select="concat(substring(sitemap:lastmod,0,11), ' ', substring(sitemap:lastmod,12,5))"/>
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
		);
	}
}

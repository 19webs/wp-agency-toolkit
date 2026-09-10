<?php
/**
 * Módulo: Generador de Sitemap XML - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Sitemap_XML {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Sitemap_XML
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
		// Sitemap XML integrado
		add_action( 'init', array( $this, 'intercept_sitemap_request' ) );
	}

	/**
	 * Intercepta la petición de sitemap.xml en el init y la procesa.
	 */
	public function intercept_sitemap_request() {
		$request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$home_path    = parse_url( home_url(), PHP_URL_PATH );
		$relative_path = $request_path;

		// Si WordPress está instalado en una subcarpeta
		if ( ! empty( $home_path ) && '/' !== $home_path ) {
			if ( 0 === strpos( $request_path, $home_path ) ) {
				$relative_path = substr( $request_path, strlen( $home_path ) );
			}
		}
		$relative_path = trim( $relative_path, '/' );

		// Si se pide exactamente sitemap.xml (insensible a mayúsculas)
		if ( 'sitemap.xml' === strtolower( $relative_path ) ) {
			$this->generate_xml_sitemap();
			exit;
		}
	}

	/**
	 * Genera el documento XML de Sitemap excluyendo los elementos configurados como noindex.
	 */
	public function generate_xml_sitemap() {
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: index, follow' );
		
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// 1. Página de Portada (Home)
		$home_url = home_url( '/' );
		$latest_post = get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		$home_modified = ! empty( $latest_post ) ? get_the_modified_date( 'c', $latest_post[0]->ID ) : date( 'c' );

		// Comprobar si la Home está excluida por noindex
		$front_page_id = get_option( 'page_on_front' );
		$home_noindex = $front_page_id ? get_post_meta( $front_page_id, '_wpat_seo_noindex', true ) : '0';

		if ( '1' !== $home_noindex ) {
			echo "  <url>\n";
			echo "    <loc>" . esc_url( $home_url ) . "</loc>\n";
			echo "    <lastmod>" . esc_html( $home_modified ) . "</lastmod>\n";
			echo "    <changefreq>daily</changefreq>\n";
			echo "    <priority>1.0</priority>\n";
			echo "  </url>\n";
		}

		// 2. Entradas, Páginas, Productos WooCommerce y CPTs Públicos
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		$posts = get_posts( array(
			'post_type'      => array_values( $post_types ),
			'post_status'    => 'publish',
			'posts_per_page' => 1500, // Límite amplio para un sitemap plano
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				// Comprobar noindex
				$noindex = get_post_meta( $post->ID, '_wpat_seo_noindex', true );
				if ( '1' === $noindex ) {
					continue; // Exclusión dinámica inteligente
				}

				// Evitar duplicar la home si se define una página estática
				$permalink = get_permalink( $post->ID );
				if ( $permalink === $home_url ) {
					continue;
				}

				// Prioridades
				$priority = '0.8';
				if ( 'page' === $post->post_type ) {
					$priority = '0.7';
				} elseif ( 'product' === $post->post_type ) {
					$priority = '0.9';
				}

				$last_mod = get_the_modified_date( 'c', $post->ID );

				echo "  <url>\n";
				echo "    <loc>" . esc_url( $permalink ) . "</loc>\n";
				echo "    <lastmod>" . esc_html( $last_mod ) . "</lastmod>\n";
				echo "    <changefreq>weekly</changefreq>\n";
				echo "    <priority>" . esc_html( $priority ) . "</priority>\n";
				echo "  </url>\n";
			}
		}

		// 3. Taxonomías Públicas
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		$exclude_taxonomies = array( 'post_format', 'nav_menu', 'link_category' );
		$taxonomies = array_diff( $taxonomies, $exclude_taxonomies );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			) );

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_url = get_term_link( $term );
					if ( ! is_wp_error( $term_url ) ) {
						echo "  <url>\n";
						echo "    <loc>" . esc_url( $term_url ) . "</loc>\n";
						echo "    <changefreq>weekly</changefreq>\n";
						echo "    <priority>0.5</priority>\n";
						echo "  </url>\n";
					}
				}
			}
		}

		echo '</urlset>' . "\n";
	}
}

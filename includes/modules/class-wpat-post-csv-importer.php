<?php
/**
 * Clase WPAT_Post_CSV_Importer.
 * Gestiona la importación y exportación masiva de entradas, páginas y CPTs desde y hacia archivos CSV y JSON.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAT_Post_CSV_Importer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Post_CSV_Importer
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Post_CSV_Importer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registra ganchos AJAX y acciones.
	 */
	private function __construct() {
		// Descargas de plantillas de ejemplo
		add_action( 'wp_ajax_wpat_csv_download_sample', array( $this, 'ajax_download_csv_sample' ) );
		add_action( 'wp_ajax_wpat_json_download_sample', array( $this, 'ajax_download_json_sample' ) );

		// Exportación
		add_action( 'wp_ajax_wpat_csv_export_posts', array( $this, 'ajax_export_csv_posts' ) );
		add_action( 'wp_ajax_wpat_json_export_posts', array( $this, 'ajax_export_json_posts' ) );

		// Importación masiva por lotes
		add_action( 'wp_ajax_wpat_csv_import_batch', array( $this, 'ajax_import_batch' ) );
	}

	/**
	 * AJAX: Descarga una plantilla de archivo CSV de ejemplo.
	 */
	public function ajax_download_csv_sample() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=plantilla_ejemplo_wpat.csv' );

		$output = fopen( 'php://output', 'w' );
		
		// Inyectar BOM para que Excel abra UTF-8 correctamente
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

		// Cabeceras del CSV
		fputcsv( $output, array(
			'titulo',
			'contenido',
			'extracto',
			'slug',
			'categorias',
			'etiquetas',
			'imagen_destacada_url',
			'estado',
			'fecha',
			'titulo_seo',
			'meta_descripcion_seo',
			'frase_clave_seo',
		), ';' );

		// Filas de ejemplo
		fputcsv( $output, array(
			'Guía Completa de Optimización Web en WordPress',
			'<p>Este es el contenido completo del primer artículo. Puedes usar etiquetas HTML completas como <strong>negritas</strong>, enlaces y párrafos.</p>',
			'Breve resumen de la guía de optimización web para listados y buscadores.',
			'guia-completa-optimizacion-web',
			'Desarrollo, Rendimiento',
			'wordpress, velocidad, hosting',
			'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800',
			'publish',
			date( 'Y-m-d H:i:s' ),
			'Guía Completa de Optimización Web - WP Agency',
			'Descubre en este tutorial exhaustivo las mejores técnicas para acelerar WordPress y mejorar tu puntuación Core Web Vitals.',
			'optimizacion web wordpress',
		), ';' );

		fputcsv( $output, array(
			'Fundamentos de Seguridad y Protección de Sitios Web',
			'<p>Aprende las medidas preventivas esenciales para blindar tu sitio web contra ataques automatizados, inyecciones SQL y fuerza bruta.</p>',
			'Resumen de buenas prácticas de seguridad para administradores de sitios.',
			'fundamentos-seguridad-proteccion-web',
			'Seguridad, Tutoriales',
			'firewall, seguridad, malware',
			'https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?w=800',
			'draft',
			date( 'Y-m-d H:i:s' ),
			'Fundamentos de Seguridad Web en 2026',
			'Tutorial práctico con las mejores pautas de seguridad para evitar accesos no autorizados y spam en WordPress.',
			'seguridad wordpress tutorial',
		), ';' );

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Descarga una plantilla de archivo JSON de ejemplo.
	 */
	public function ajax_download_json_sample() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
		}

		$sample_data = array(
			array(
				'titulo'               => 'Guía Completa de Optimización Web en WordPress',
				'contenido'            => '<p>Contenido completo del artículo con formato HTML y estructura semántica.</p>',
				'extracto'             => 'Breve extracto descriptivo para listados.',
				'slug'                 => 'guia-completa-optimizacion-web',
				'categorias'           => 'Desarrollo, Rendimiento',
				'etiquetas'            => 'wordpress, velocidad, hosting',
				'imagen_destacada_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800',
				'estado'               => 'publish',
				'fecha'                => date( 'Y-m-d H:i:s' ),
				'titulo_seo'           => 'Guía Completa de Optimización Web - WP Agency',
				'meta_descripcion_seo' => 'Descubre cómo optimizar la velocidad y rendimiento de tu sitio WordPress.',
				'frase_clave_seo'      => 'optimizacion web wordpress',
			),
			array(
				'titulo'               => 'Fundamentos de Seguridad y Protección Web',
				'contenido'            => '<p>Consejos y pautas para securizar tu instalación de WordPress.</p>',
				'extracto'             => 'Pautas fundamentales de seguridad.',
				'slug'                 => 'fundamentos-seguridad-proteccion-web',
				'categorias'           => 'Seguridad, Tutoriales',
				'etiquetas'            => 'firewall, seguridad, malware',
				'imagen_destacada_url' => 'https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?w=800',
				'estado'               => 'draft',
				'fecha'                => date( 'Y-m-d H:i:s' ),
				'titulo_seo'           => 'Fundamentos de Seguridad Web en 2026',
				'meta_descripcion_seo' => 'Aprende a proteger tu WordPress frente a ataques y spam.',
				'frase_clave_seo'      => 'seguridad wordpress tutorial',
			),
		);

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=plantilla_ejemplo_wpat.json' );

		echo wp_json_encode( $sample_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * AJAX: Exporta las entradas/páginas a un archivo CSV estructurado.
	 */
	public function ajax_export_csv_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
		$status    = isset( $_GET['post_status'] ) && in_array( $_GET['post_status'], array( 'publish', 'draft', 'pending', 'future', 'any' ), true ) ? $_GET['post_status'] : 'any';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=exportacion_' . $post_type . '_' . date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

		fputcsv( $output, array(
			'id',
			'titulo',
			'contenido',
			'extracto',
			'slug',
			'categorias',
			'etiquetas',
			'imagen_destacada_url',
			'estado',
			'fecha',
			'autor',
			'titulo_seo',
			'meta_descripcion_seo',
			'frase_clave_seo',
		), ';' );

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$posts = get_posts( $query_args );

		foreach ( $posts as $p ) {
			$cats = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
			$tags = wp_get_post_tags( $p->ID, array( 'fields' => 'names' ) );
			$thumb_url = get_the_post_thumbnail_url( $p->ID, 'full' );

			$seo_title   = get_post_meta( $p->ID, '_wpat_seo_title', true );
			$seo_desc    = get_post_meta( $p->ID, '_wpat_seo_desc', true );
			$seo_keyword = get_post_meta( $p->ID, '_wpat_seo_keyword', true );

			$author_name = get_the_author_meta( 'user_login', $p->post_author );

			fputcsv( $output, array(
				$p->ID,
				$p->post_title,
				$p->post_content,
				$p->post_excerpt,
				$p->post_name,
				implode( ', ', $cats ),
				implode( ', ', $tags ),
				$thumb_url ? $thumb_url : '',
				$p->post_status,
				$p->post_date,
				$author_name,
				$seo_title,
				$seo_desc,
				$seo_keyword,
			), ';' );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Exporta las entradas/páginas a un archivo JSON estructurado.
	 */
	public function ajax_export_json_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
		$status    = isset( $_GET['post_status'] ) && in_array( $_GET['post_status'], array( 'publish', 'draft', 'pending', 'future', 'any' ), true ) ? $_GET['post_status'] : 'any';

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$posts = get_posts( $query_args );
		$export_data = array();

		foreach ( $posts as $p ) {
			$cats = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
			$tags = wp_get_post_tags( $p->ID, array( 'fields' => 'names' ) );
			$thumb_url = get_the_post_thumbnail_url( $p->ID, 'full' );

			$seo_title   = get_post_meta( $p->ID, '_wpat_seo_title', true );
			$seo_desc    = get_post_meta( $p->ID, '_wpat_seo_desc', true );
			$seo_keyword = get_post_meta( $p->ID, '_wpat_seo_keyword', true );

			$author_name = get_the_author_meta( 'user_login', $p->post_author );

			$export_data[] = array(
				'id'                   => (int) $p->ID,
				'titulo'               => $p->post_title,
				'contenido'            => $p->post_content,
				'extracto'             => $p->post_excerpt,
				'slug'                 => $p->post_name,
				'categorias'           => implode( ', ', $cats ),
				'etiquetas'            => implode( ', ', $tags ),
				'imagen_destacada_url' => $thumb_url ? $thumb_url : '',
				'estado'               => $p->post_status,
				'fecha'                => $p->post_date,
				'autor'                => $author_name,
				'titulo_seo'           => $seo_title,
				'meta_descripcion_seo' => $seo_desc,
				'frase_clave_seo'      => $seo_keyword,
			);
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=exportacion_' . $post_type . '_' . date( 'Y-m-d' ) . '.json' );

		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * AJAX: Importa un lote de filas (CSV o JSON) desde el frontend.
	 */
	public function ajax_import_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) ) );
		}

		$rows             = isset( $_POST['rows'] ) ? $_POST['rows'] : array();
		$target_post_type = isset( $_POST['target_post_type'] ) ? sanitize_key( $_POST['target_post_type'] ) : 'post';
		$strategy         = isset( $_POST['strategy'] ) && in_array( $_POST['strategy'], array( 'update', 'skip', 'create_new' ), true ) ? $_POST['strategy'] : 'update';

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No se recibieron filas válidas para importar.', 'wp-agency-toolkit' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$imported_count = 0;
		$updated_count  = 0;
		$skipped_count  = 0;
		$errors         = array();

		foreach ( $rows as $row ) {
			// Soporte tanto para claves en español como en inglés
			$title = '';
			if ( isset( $row['titulo'] ) ) {
				$title = sanitize_text_field( $row['titulo'] );
			} elseif ( isset( $row['title'] ) ) {
				$title = sanitize_text_field( $row['title'] );
			}

			if ( empty( $title ) ) {
				continue;
			}

			$content = isset( $row['contenido'] ) ? wp_kses_post( $row['contenido'] ) : ( isset( $row['content'] ) ? wp_kses_post( $row['content'] ) : '' );
			$excerpt = isset( $row['extracto'] ) ? sanitize_textarea_field( $row['extracto'] ) : ( isset( $row['excerpt'] ) ? sanitize_textarea_field( $row['excerpt'] ) : '' );
			
			$raw_slug = isset( $row['slug'] ) && ! empty( $row['slug'] ) ? $row['slug'] : $title;
			$slug     = sanitize_title( $raw_slug );

			$status    = isset( $row['estado'] ) && in_array( $row['estado'], array( 'publish', 'draft', 'pending', 'private' ), true ) ? $row['estado'] : ( isset( $row['status'] ) && in_array( $row['status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ? $row['status'] : 'publish' );
			$post_date = isset( $row['fecha'] ) && ! empty( $row['fecha'] ) ? sanitize_text_field( $row['fecha'] ) : ( isset( $row['date'] ) && ! empty( $row['date'] ) ? sanitize_text_field( $row['date'] ) : current_time( 'mysql' ) );

			// Buscar si ya existe por slug o por ID para decidir según la estrategia
			$existing_id = 0;

			if ( 'create_new' !== $strategy ) {
				if ( isset( $row['id'] ) && ! empty( $row['id'] ) && absint( $row['id'] ) > 0 ) {
					$post_by_id = get_post( absint( $row['id'] ) );
					if ( $post_by_id && $post_by_id->post_type === $target_post_type ) {
						$existing_id = $post_by_id->ID;
					}
				}

				if ( ! $existing_id && ! empty( $slug ) ) {
					$existing_posts = get_posts( array(
						'name'           => $slug,
						'post_type'      => $target_post_type,
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'fields'         => 'ids',
					) );
					if ( ! empty( $existing_posts ) ) {
						$existing_id = $existing_posts[0];
					}
				}
			}

			// Manejar estrategia
			if ( $existing_id && 'skip' === $strategy ) {
				$skipped_count++;
				continue;
			}

			$post_data = array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_status'  => $status,
				'post_type'    => $target_post_type,
				'post_date'    => $post_date,
			);

			if ( 'create_new' !== $strategy && ! empty( $slug ) ) {
				$post_data['post_name'] = $slug;
			}

			if ( $existing_id && 'update' === $strategy ) {
				$post_data['ID'] = $existing_id;
				$result_id = wp_update_post( $post_data );
				if ( ! is_wp_error( $result_id ) ) {
					$updated_count++;
				}
			} else {
				$result_id = wp_insert_post( $post_data );
				if ( ! is_wp_error( $result_id ) ) {
					$imported_count++;
				}
			}

			if ( is_wp_error( $result_id ) ) {
				$errors[] = sprintf( 'Error en "%s": %s', esc_html( $title ), $result_id->get_error_message() );
				continue;
			}

			$post_id = $result_id;

			// Categorías (si el tipo de post las soporta)
			$raw_cats = isset( $row['categorias'] ) ? $row['categorias'] : ( isset( $row['categories'] ) ? $row['categories'] : '' );
			if ( ! empty( $raw_cats ) && is_object_in_taxonomy( $target_post_type, 'category' ) ) {
				$cat_names = array_map( 'trim', explode( ',', $raw_cats ) );
				$cat_ids   = array();
				foreach ( $cat_names as $cat_name ) {
					if ( empty( $cat_name ) ) {
						continue;
					}
					$term = get_term_by( 'name', $cat_name, 'category' );
					if ( ! $term ) {
						$new_term = wp_insert_term( $cat_name, 'category' );
						if ( ! is_wp_error( $new_term ) && isset( $new_term['term_id'] ) ) {
							$cat_ids[] = $new_term['term_id'];
						}
					} else {
						$cat_ids[] = $term->term_id;
					}
				}
				if ( ! empty( $cat_ids ) ) {
					wp_set_post_categories( $post_id, $cat_ids );
				}
			}

			// Etiquetas (tags)
			$raw_tags = isset( $row['etiquetas'] ) ? $row['etiquetas'] : ( isset( $row['tags'] ) ? $row['tags'] : '' );
			if ( ! empty( $raw_tags ) && is_object_in_taxonomy( $target_post_type, 'post_tag' ) ) {
				$tags = array_map( 'trim', explode( ',', $raw_tags ) );
				wp_set_post_tags( $post_id, $tags, true );
			}

			// SEO Meta
			$seo_title = isset( $row['titulo_seo'] ) ? $row['titulo_seo'] : ( isset( $row['seo_title'] ) ? $row['seo_title'] : '' );
			if ( ! empty( $seo_title ) ) {
				update_post_meta( $post_id, '_wpat_seo_title', sanitize_text_field( $seo_title ) );
			}

			$seo_desc = isset( $row['meta_descripcion_seo'] ) ? $row['meta_descripcion_seo'] : ( isset( $row['seo_desc'] ) ? $row['seo_desc'] : '' );
			if ( ! empty( $seo_desc ) ) {
				update_post_meta( $post_id, '_wpat_seo_desc', sanitize_text_field( $seo_desc ) );
			}

			$seo_kw = isset( $row['frase_clave_seo'] ) ? $row['frase_clave_seo'] : ( isset( $row['seo_keyword'] ) ? $row['seo_keyword'] : ( isset( $row['keyword'] ) ? $row['keyword'] : '' ) );
			if ( ! empty( $seo_kw ) ) {
				update_post_meta( $post_id, '_wpat_seo_keyword', sanitize_text_field( $seo_kw ) );
			}

			// Imagen Destacada remota
			$raw_image = isset( $row['imagen_destacada_url'] ) ? $row['imagen_destacada_url'] : ( isset( $row['featured_image'] ) ? $row['featured_image'] : '' );
			if ( ! empty( $raw_image ) && esc_url_raw( $raw_image ) ) {
				$image_url = esc_url_raw( $raw_image );
				if ( ! has_post_thumbnail( $post_id ) ) {
					$media_id = media_sideload_image( $image_url, $post_id, $title, 'id' );
					if ( ! is_wp_error( $media_id ) ) {
						set_post_thumbnail( $post_id, $media_id );
					}
				}
			}
		}

		wp_send_json_success( array(
			'imported' => $imported_count,
			'updated'  => $updated_count,
			'skipped'  => $skipped_count,
			'errors'   => $errors,
		) );
	}
}

// Inicializar singleton
WPAT_Post_CSV_Importer::get_instance();

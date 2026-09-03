<?php
/**
 * Clase WPAT_Post_CSV_Importer.
 * Gestiona la importación y exportación de entradas/artículos desde y hacia archivos CSV (compatibles con Excel y Google Sheets).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAT_Post_CSV_Importer {

	/**
	 * Instancia única de la clase.
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
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
		add_action( 'wp_ajax_wpat_csv_download_sample', array( $this, 'ajax_download_sample' ) );
		add_action( 'wp_ajax_wpat_csv_export_posts', array( $this, 'ajax_export_posts' ) );
		add_action( 'wp_ajax_wpat_csv_import_batch', array( $this, 'ajax_import_batch' ) );
	}

	/**
	 * AJAX: Descarga una plantilla de archivo CSV de ejemplo.
	 */
	public function ajax_download_sample() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No tienes permisos suficientes.' );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=plantilla_entradas_wpat.csv' );

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
		), ';' );

		// Filas de ejemplo
		fputcsv( $output, array(
			'Ejemplo de Artículo 1: Introducción a la Optimización Web',
			'<p>Este es el contenido completo del primer artículo de ejemplo. Puedes usar etiquetas HTML como <strong>negritas</strong> o parágrafos.</p>',
			'Breve resumen o extracto del artículo para listados.',
			'ejemplo-articulo-1-optimizacion-web',
			'Blog, Noticias',
			'wordpress, seo, rendimiento',
			'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800',
			'publish',
			date( 'Y-m-d H:i:s' ),
			'Ejemplo de Artículo 1 - Mi Sitio Web',
			'Descubre en este artículo las mejores técnicas de optimización web para potenciar tu sitio WordPress.',
		), ';' );

		fputcsv( $output, array(
			'Ejemplo de Artículo 2: Guía Completa de Seguridad WordPress',
			'<p>En esta guía explicamos cómo proteger tu sitio web de ataques automatizados y spambots de forma sencilla.</p>',
			'Consejos fundamentales de seguridad para administradores de WordPress.',
			'ejemplo-articulo-2-guia-seguridad',
			'Seguridad, Tutoriales',
			'seguridad, firewall, anti-spam',
			'https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?w=800',
			'draft',
			date( 'Y-m-d H:i:s' ),
			'Guía Completa de Seguridad WordPress',
			'Aprende a proteger tu WordPress frente a ataques de bots y vulnerabilidades comunes en este tutorial.',
		), ';' );

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Exporta todas las entradas del blog a un archivo CSV.
	 */
	public function ajax_export_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No tienes permisos suficientes.' );
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';

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
			'titulo_seo',
			'meta_descripcion_seo',
		), ';' );

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
		);

		$posts = get_posts( $query_args );

		foreach ( $posts as $p ) {
			$cats = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
			$tags = wp_get_post_tags( $p->ID, array( 'fields' => 'names' ) );
			$thumb_url = get_the_post_thumbnail_url( $p->ID, 'full' );

			$seo_title = get_post_meta( $p->ID, '_wpat_seo_title', true );
			$seo_desc  = get_post_meta( $p->ID, '_wpat_seo_desc', true );

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
				$seo_title,
				$seo_desc,
			), ';' );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Importa un lote de filas en formato array/JSON desde el frontend.
	 */
	public function ajax_import_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$rows = isset( $_POST['rows'] ) ? $_POST['rows'] : array();
		$target_post_type = isset( $_POST['target_post_type'] ) ? sanitize_key( $_POST['target_post_type'] ) : 'post';

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			wp_send_json_error( array( 'message' => 'No hay filas válidas para importar.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$imported_count = 0;
		$updated_count  = 0;
		$errors         = array();

		foreach ( $rows as $row ) {
			$title = isset( $row['titulo'] ) ? sanitize_text_field( $row['titulo'] ) : '';
			if ( empty( $title ) ) {
				continue;
			}

			$content   = isset( $row['contenido'] ) ? wp_kses_post( $row['contenido'] ) : '';
			$excerpt   = isset( $row['extracto'] ) ? sanitize_textarea_field( $row['extracto'] ) : '';
			$slug      = isset( $row['slug'] ) && ! empty( $row['slug'] ) ? sanitize_title( $row['slug'] ) : sanitize_title( $title );
			$status    = isset( $row['estado'] ) && in_array( $row['estado'], array( 'publish', 'draft', 'pending', 'private' ), true ) ? $row['estado'] : 'publish';
			$post_date = isset( $row['fecha'] ) && ! empty( $row['fecha'] ) ? sanitize_text_field( $row['fecha'] ) : current_time( 'mysql' );

			// Buscar si ya existe por slug para actualizar
			$existing = get_posts( array(
				'name'           => $slug,
				'post_type'      => $target_post_type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );

			$post_data = array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_name'    => $slug,
				'post_status'  => $status,
				'post_type'    => $target_post_type,
				'post_date'    => $post_date,
			);

			if ( ! empty( $existing ) ) {
				$post_id = $existing[0];
				$post_data['ID'] = $post_id;
				$result_id = wp_update_post( $post_data );
				$updated_count++;
			} else {
				$result_id = wp_insert_post( $post_data );
				$imported_count++;
			}

			if ( is_wp_error( $result_id ) ) {
				$errors[] = 'Error en "' . $title . '": ' . $result_id->get_error_message();
				continue;
			}

			$post_id = $result_id;

			// Categorías (solo si el tipo de post las soporta)
			if ( isset( $row['categorias'] ) && ! empty( $row['categorias'] ) && is_object_in_taxonomy( $target_post_type, 'category' ) ) {
				$cat_names = array_map( 'trim', explode( ',', $row['categorias'] ) );
				$cat_ids   = array();
				foreach ( $cat_names as $cat_name ) {
					if ( empty( $cat_name ) ) {
						continue;
					}
					$term = get_term_by( 'name', $cat_name, 'category' );
					if ( ! $term ) {
						$new_term = wp_insert_term( $cat_name, 'category' );
						if ( ! is_wp_error( $new_term ) ) {
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

			// Etiquetas
			if ( isset( $row['etiquetas'] ) && ! empty( $row['etiquetas'] ) && is_object_in_taxonomy( $target_post_type, 'post_tag' ) ) {
				$tags = array_map( 'trim', explode( ',', $row['etiquetas'] ) );
				wp_set_post_tags( $post_id, $tags, true );
			}

			// SEO Meta
			if ( isset( $row['titulo_seo'] ) && ! empty( $row['titulo_seo'] ) ) {
				update_post_meta( $post_id, '_wpat_seo_title', sanitize_text_field( $row['titulo_seo'] ) );
			}
			if ( isset( $row['meta_descripcion_seo'] ) && ! empty( $row['meta_descripcion_seo'] ) ) {
				update_post_meta( $post_id, '_wpat_seo_desc', sanitize_text_field( $row['meta_descripcion_seo'] ) );
			}

			// Imagen Destacada remota
			if ( isset( $row['imagen_destacada_url'] ) && ! empty( $row['imagen_destacada_url'] ) && esc_url_raw( $row['imagen_destacada_url'] ) ) {
				$image_url = esc_url_raw( $row['imagen_destacada_url'] );
				// Solo descargar si el post no tiene imagen o la URL es nueva
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
			'errors'   => $errors,
		) );
	}
}

// Inicializar
WPAT_Post_CSV_Importer::get_instance();

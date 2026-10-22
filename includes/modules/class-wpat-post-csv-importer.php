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

		// Acciones en lote y enlaces directos de fila en tablas de WordPress
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_admin_post_hooks' ) );
		}
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
	 * Registra acciones en lote y enlaces de fila en todos los tipos de contenido públicos.
	 */
	public function register_admin_post_hooks() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		foreach ( $post_types as $pt ) {
			// Acciones en lote en la parte superior e inferior de la tabla
			add_filter( "bulk_actions-edit-{$pt}", array( $this, 'register_bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$pt}", array( $this, 'handle_bulk_actions' ), 10, 3 );
		}

		// Enlaces rápidos en hover bajo el título
		add_filter( 'post_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
	}

	/**
	 * Registra las opciones de exportación en el desplegable de Acciones en lote.
	 *
	 * @param array $bulk_actions Acciones disponibles en lote.
	 * @return array
	 */
	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['wpat_export_json'] = __( 'Exportar a JSON (WPAT)', 'wp-agency-toolkit' );
		$bulk_actions['wpat_export_csv']  = __( 'Exportar a CSV (WPAT)', 'wp-agency-toolkit' );
		return $bulk_actions;
	}

	/**
	 * Procesa la acción en lote de exportación seleccionada por el usuario.
	 *
	 * @param string $redirect_to URL de redirección por defecto.
	 * @param string $doaction    Acción ejecutada.
	 * @param array  $post_ids    IDs de posts seleccionados.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'wpat_export_json' !== $doaction && 'wpat_export_csv' !== $doaction ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return $redirect_to;
		}

		$first_id  = absint( $post_ids[0] );
		$post_type = get_post_type( $first_id );
		if ( ! $post_type ) {
			$post_type = 'post';
		}

		$posts = get_posts( array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'post__in'       => array_map( 'absint', $post_ids ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );

		if ( empty( $posts ) ) {
			return $redirect_to;
		}

		$single_id = 1 === count( $post_ids ) ? $first_id : 0;

		if ( 'wpat_export_json' === $doaction ) {
			$this->output_json_download( $posts, $post_type, $single_id );
		} else {
			$this->output_csv_download( $posts, $post_type, $single_id );
		}
		exit;
	}

	/**
	 * Añade enlaces directos de exportación bajo el título de cada entrada/página en la tabla de WordPress.
	 *
	 * @param array   $actions Enlaces de acción de la fila.
	 * @param WP_Post $post    Objeto del post actual.
	 * @return array
	 */
	public function filter_row_actions( $actions, $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$json_url = wp_nonce_url(
			admin_url( 'admin-ajax.php?action=wpat_json_export_posts&post_type=' . esc_attr( $post->post_type ) . '&post_ids=' . (int) $post->ID ),
			'wpat_export_nonce'
		);
		$csv_url  = wp_nonce_url(
			admin_url( 'admin-ajax.php?action=wpat_csv_export_posts&post_type=' . esc_attr( $post->post_type ) . '&post_ids=' . (int) $post->ID ),
			'wpat_export_nonce'
		);

		$actions['wpat_export_json'] = sprintf(
			'<a href="%s" title="%s" style="color: #4f46e5; font-weight: 500;">%s</a>',
			esc_url( $json_url ),
			esc_attr__( 'Exportar esta publicación individual a JSON', 'wp-agency-toolkit' ),
			esc_html__( 'Exportar JSON', 'wp-agency-toolkit' )
		);

		$actions['wpat_export_csv'] = sprintf(
			'<a href="%s" title="%s" style="color: #059669; font-weight: 500;">%s</a>',
			esc_url( $csv_url ),
			esc_attr__( 'Exportar esta publicación individual a CSV', 'wp-agency-toolkit' ),
			esc_html__( 'Exportar CSV', 'wp-agency-toolkit' )
		);

		return $actions;
	}

	/**
	 * Genera y envía al navegador la descarga de un archivo CSV estructurado.
	 *
	 * @param array  $posts     Lista de objetos WP_Post a exportar.
	 * @param string $post_type Tipo de contenido.
	 * @param int    $single_id ID si es exportación individual, 0 si son múltiples.
	 */
	public function output_csv_download( $posts, $post_type = 'post', $single_id = 0 ) {
		if ( $single_id > 0 ) {
			$first_post = ! empty( $posts ) ? $posts[0] : null;
			$slug_part  = ( $first_post && ! empty( $first_post->post_name ) ) ? '-' . $first_post->post_name : '-id' . $single_id;
			$filename   = 'exportacion_' . $post_type . $slug_part . '_' . date( 'Y-m-d' ) . '.csv';
		} elseif ( count( $posts ) > 1 && ! empty( $_REQUEST['post_ids'] ) ) {
			$filename = 'exportacion_' . $post_type . '_seleccionados_' . date( 'Y-m-d' ) . '.csv';
		} else {
			$filename = 'exportacion_' . $post_type . '_' . date( 'Y-m-d' ) . '.csv';
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

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

		foreach ( $posts as $p ) {
			$cats        = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
			$tags        = wp_get_post_tags( $p->ID, array( 'fields' => 'names' ) );
			$thumb_url   = get_the_post_thumbnail_url( $p->ID, 'full' );
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
				is_array( $cats ) ? implode( ', ', $cats ) : '',
				is_array( $tags ) ? implode( ', ', $tags ) : '',
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
	 * Genera y envía al navegador la descarga de un archivo JSON estructurado.
	 *
	 * @param array  $posts     Lista de objetos WP_Post a exportar.
	 * @param string $post_type Tipo de contenido.
	 * @param int    $single_id ID si es exportación individual, 0 si son múltiples.
	 */
	public function output_json_download( $posts, $post_type = 'post', $single_id = 0 ) {
		if ( $single_id > 0 ) {
			$first_post = ! empty( $posts ) ? $posts[0] : null;
			$slug_part  = ( $first_post && ! empty( $first_post->post_name ) ) ? '-' . $first_post->post_name : '-id' . $single_id;
			$filename   = 'exportacion_' . $post_type . $slug_part . '_' . date( 'Y-m-d' ) . '.json';
		} elseif ( count( $posts ) > 1 && ! empty( $_REQUEST['post_ids'] ) ) {
			$filename = 'exportacion_' . $post_type . '_seleccionados_' . date( 'Y-m-d' ) . '.json';
		} else {
			$filename = 'exportacion_' . $post_type . '_' . date( 'Y-m-d' ) . '.json';
		}

		$export_data = array();

		foreach ( $posts as $p ) {
			$cats        = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
			$tags        = wp_get_post_tags( $p->ID, array( 'fields' => 'names' ) );
			$thumb_url   = get_the_post_thumbnail_url( $p->ID, 'full' );
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
				'categorias'           => is_array( $cats ) ? implode( ', ', $cats ) : '',
				'etiquetas'            => is_array( $tags ) ? implode( ', ', $tags ) : '',
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
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );

		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * AJAX: Exporta las entradas/páginas a un archivo CSV estructurado.
	 */
	public function ajax_export_csv_posts() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
		}

		$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( $_REQUEST['post_type'] ) : 'post';
		$status    = isset( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'], array( 'publish', 'draft', 'pending', 'future', 'any' ), true ) ? $_REQUEST['post_status'] : 'any';

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$single_id = 0;
		if ( ! empty( $_REQUEST['post_ids'] ) ) {
			$raw_ids   = is_array( $_REQUEST['post_ids'] ) ? $_REQUEST['post_ids'] : explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['post_ids'] ) ) );
			$clean_ids = array_filter( array_map( 'absint', $raw_ids ) );
			if ( ! empty( $clean_ids ) ) {
				$query_args['post__in'] = $clean_ids;
				if ( 1 === count( $clean_ids ) ) {
					$single_id = reset( $clean_ids );
				}
			}
		}

		$posts = get_posts( $query_args );
		$this->output_csv_download( $posts, $post_type, $single_id );
	}

	/**
	 * AJAX: Exporta las entradas/páginas a un archivo JSON estructurado.
	 */
	public function ajax_export_json_posts() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
		}

		$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( $_REQUEST['post_type'] ) : 'post';
		$status    = isset( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'], array( 'publish', 'draft', 'pending', 'future', 'any' ), true ) ? $_REQUEST['post_status'] : 'any';

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$single_id = 0;
		if ( ! empty( $_REQUEST['post_ids'] ) ) {
			$raw_ids   = is_array( $_REQUEST['post_ids'] ) ? $_REQUEST['post_ids'] : explode( ',', sanitize_text_field( wp_unslash( $_REQUEST['post_ids'] ) ) );
			$clean_ids = array_filter( array_map( 'absint', $raw_ids ) );
			if ( ! empty( $clean_ids ) ) {
				$query_args['post__in'] = $clean_ids;
				if ( 1 === count( $clean_ids ) ) {
					$single_id = reset( $clean_ids );
				}
			}
		}

		$posts = get_posts( $query_args );
		$this->output_json_download( $posts, $post_type, $single_id );
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
		$strategy         = isset( $_POST['strategy'] ) && in_array( $_POST['strategy'], array( 'update', 'skip', 'create_new', 'create_draft' ), true ) ? $_POST['strategy'] : 'update';

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

			// Buscar si ya existe por slug prioritariamente (evita colisión de IDs en migraciones entre webs)
			$existing_id = 0;

			if ( 'create_new' !== $strategy && 'create_draft' !== $strategy ) {
				// 1. Coincidencia por slug (el identificador más seguro y universal entre sitios)
				if ( ! empty( $slug ) ) {
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

				// 2. Si no se encontró por slug, verificar por ID únicamente si el slug coincide o está vacío
				if ( ! $existing_id && isset( $row['id'] ) && ! empty( $row['id'] ) && absint( $row['id'] ) > 0 ) {
					$post_by_id = get_post( absint( $row['id'] ) );
					if ( $post_by_id && $post_by_id->post_type === $target_post_type ) {
						if ( empty( $slug ) || $post_by_id->post_name === $slug ) {
							$existing_id = $post_by_id->ID;
						}
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
				'post_status'  => ( 'create_draft' === $strategy ) ? 'draft' : $status,
				'post_type'    => $target_post_type,
				'post_date'    => $post_date,
			);

			if ( 'create_new' !== $strategy && 'create_draft' !== $strategy && ! empty( $slug ) ) {
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

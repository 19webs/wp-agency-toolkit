<?php
/**
 * Módulo: Optimizador de Imágenes a WebP, Optimización Masiva & Limpiador Seguro de Huérfanas
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Image_Optimizer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Image_Optimizer
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Image_Optimizer
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

		// Interceptar la carga del archivo una vez subido con éxito (automatización en caliente)
		$auto_convert = ! isset( $settings['image_optimizer_auto_convert'] ) || '1' === $settings['image_optimizer_auto_convert'];
		if ( $auto_convert ) {
			add_filter( 'wp_handle_upload', array( $this, 'optimize_and_convert_to_webp' ), 10, 2 );
			add_filter( 'wp_handle_sideload', array( $this, 'optimize_and_convert_to_webp' ), 10, 2 );
		}

		// Endpoints AJAX para optimización masiva retroactiva
		add_action( 'wp_ajax_wpat_scan_images', array( $this, 'ajax_scan_images' ) );
		add_action( 'wp_ajax_wpat_optimize_image_batch', array( $this, 'ajax_optimize_image_batch' ) );

		// Endpoints AJAX para detector de imágenes huérfanas
		add_action( 'wp_ajax_wpat_scan_unused_images', array( $this, 'ajax_scan_unused_images' ) );
		add_action( 'wp_ajax_wpat_check_unused_images_batch', array( $this, 'ajax_check_unused_images_batch' ) );
		add_action( 'wp_ajax_wpat_delete_unused_images', array( $this, 'ajax_delete_unused_images' ) );

		// Hook para generación automática de textos ALT en subida
		$auto_alt = ! isset( $settings['image_optimizer_auto_alt'] ) || '1' === $settings['image_optimizer_auto_alt'];
		if ( $auto_alt ) {
			add_action( 'add_attachment', array( $this, 'maybe_auto_generate_alt' ) );
		}

		// Endpoints AJAX para escáner y reparación masiva de textos ALT
		add_action( 'wp_ajax_wpat_scan_empty_alt_images', array( $this, 'ajax_scan_empty_alt_images' ) );
		add_action( 'wp_ajax_wpat_fill_empty_alt_batch', array( $this, 'ajax_fill_empty_alt_batch' ) );
	}

	/**
	 * Comprueba si un archivo GIF contiene animación (múltiples frames).
	 *
	 * @param string $filename Ruta al archivo de imagen.
	 * @return bool
	 */
	public function is_animated_gif( $filename ) {
		if ( ! file_exists( $filename ) || ! ( $fh = @fopen( $filename, 'rb' ) ) ) {
			return false;
		}
		$count = 0;
		while ( ! feof( $fh ) && $count < 2 ) {
			$chunk = fread( $fh, 1024 * 100 );
			$count += preg_match_all( '#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches );
		}
		fclose( $fh );
		return $count > 1;
	}

	/**
	 * Optimiza, redimensiona y convierte la imagen subida a formato WebP.
	 *
	 * @param array  $upload Array de datos del archivo subido.
	 * @param string $context Contexto de la acción.
	 * @return array
	 */
	public function optimize_and_convert_to_webp( $upload, $context = 'upload' ) {
		// Validar que no haya habido errores previos en la subida
		if ( isset( $upload['error'] ) && ! empty( $upload['error'] ) ) {
			return $upload;
		}

		if ( empty( $upload['file'] ) || ! file_exists( $upload['file'] ) ) {
			return $upload;
		}

		$file_path = $upload['file'];
		$mime_type = isset( $upload['type'] ) ? $upload['type'] : '';
		$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		// Omitir de inmediato si la imagen ya es WebP o no es un formato compatible
		if ( 'image/webp' === $mime_type || 'webp' === $ext ) {
			return $upload;
		}

		// Si es un GIF animado, preservar el archivo original intacto para no perder la animación
		if ( ( 'image/gif' === $mime_type || 'gif' === $ext ) && $this->is_animated_gif( $file_path ) ) {
			return $upload;
		}

		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return $upload;
		}

		// Elevar el límite de memoria para manipulación de imágenes grandes
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}

		// Obtener configuración del módulo
		$settings      = WPAT_Main::get_instance()->get_settings();
		$quality       = isset( $settings['image_optimizer_quality'] ) ? max( 40, min( 100, intval( $settings['image_optimizer_quality'] ) ) ) : 82;
		$max_width     = isset( $settings['image_optimizer_max_width'] ) ? max( 0, intval( $settings['image_optimizer_max_width'] ) ) : 1920;
		$max_height    = isset( $settings['image_optimizer_max_height'] ) ? max( 0, intval( $settings['image_optimizer_max_height'] ) ) : 1920;
		$keep_original = isset( $settings['image_optimizer_keep_original'] ) && '1' === $settings['image_optimizer_keep_original'];

		// Inicializar el editor de imágenes de WordPress (GD o Imagick)
		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return $upload;
		}

		// 1. Redimensionar si supera las dimensiones máximas configuradas
		$sizes       = $editor->get_size();
		$width       = $sizes['width'];
		$height      = $sizes['height'];
		$was_resized = false;

		if ( ( $max_width > 0 && $width > $max_width ) || ( $max_height > 0 && $height > $max_height ) ) {
			$target_w = $max_width > 0 ? $max_width : $width;
			$target_h = $max_height > 0 ? $max_height : $height;
			$editor->resize( $target_w, $target_h, false );
			$was_resized = true;
		}

		// Verificar soporte del servidor para codificación WebP
		if ( ! $editor->supports_mime_type( 'image/webp' ) ) {
			// Si el servidor no soporta WebP, optimizamos con compresión en su formato original
			$editor->set_quality( $quality );
			$editor->save( $file_path );
			return $upload;
		}

		// 2. Convertir y Guardar en formato WebP con calidad configurada
		$editor->set_quality( $quality );

		// Preparar la nueva ruta y nombre de archivo .webp
		$path_info = pathinfo( $file_path );
		$directory = $path_info['dirname'];
		$filename  = $path_info['filename'];
		$webp_path = $directory . '/' . $filename . '.webp';

		// Guardar el nuevo archivo WebP
		$saved = $editor->save( $webp_path, 'image/webp' );

		if ( ! is_wp_error( $saved ) && file_exists( $webp_path ) && filesize( $webp_path ) > 0 ) {
			// Eliminar físicamente la imagen original pesada si no se configuró conservarla
			if ( ! $keep_original && file_exists( $file_path ) && $file_path !== $webp_path ) {
				@unlink( $file_path );
			}

			// Actualizar el array del upload para que WordPress registre la versión .webp
			$upload['file'] = $webp_path;
			$upload['type'] = 'image/webp';
			
			// Actualizar la URL pública de la imagen
			if ( isset( $upload['url'] ) ) {
				$url_info      = pathinfo( $upload['url'] );
				$upload['url'] = $url_info['dirname'] . '/' . $filename . '.webp';
			}
		} else {
			if ( $was_resized ) {
				$editor->save( $file_path );
			}
		}

		return $upload;
	}

	/**
	 * AJAX: Escanea la cantidad de imágenes pendientes de optimización en la Biblioteca usando filtros.
	 */
	public function ajax_scan_images() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		global $wpdb;

		$min_size   = isset( $_POST['min_size'] ) ? (int) $_POST['min_size'] : 0;
		$date_start = isset( $_POST['date_start'] ) ? sanitize_text_field( wp_unslash( $_POST['date_start'] ) ) : '';
		$date_end   = isset( $_POST['date_end'] ) ? sanitize_text_field( wp_unslash( $_POST['date_end'] ) ) : '';

		// Formatos seleccionados por el usuario
		$allowed_valid_formats = array( 'image/jpeg', 'image/png', 'image/gif' );
		$selected_formats      = array();

		if ( isset( $_POST['formats'] ) ) {
			$raw_formats = is_array( $_POST['formats'] ) ? $_POST['formats'] : explode( ',', sanitize_text_field( wp_unslash( $_POST['formats'] ) ) );
			foreach ( $raw_formats as $fmt ) {
				$fmt = sanitize_text_field( wp_unslash( $fmt ) );
				if ( in_array( $fmt, $allowed_valid_formats, true ) ) {
					$selected_formats[] = $fmt;
				}
			}
		}

		if ( empty( $selected_formats ) ) {
			$selected_formats = array( 'image/jpeg', 'image/png' );
		}

		$date_conditions = array();
		if ( ! empty( $date_start ) ) {
			$date_conditions[] = $wpdb->prepare( "post_date >= %s", $date_start . ' 00:00:00' );
		}
		if ( ! empty( $date_end ) ) {
			$date_conditions[] = $wpdb->prepare( "post_date <= %s", $date_end . ' 23:59:59' );
		}

		$date_query = ! empty( $date_conditions ) ? 'AND ' . implode( ' AND ', $date_conditions ) : '';

		$format_placeholders = implode( ', ', array_fill( 0, count( $selected_formats ), '%s' ) );

		// Obtener todos los candidatos no optimizados
		$query = $wpdb->prepare(
			"SELECT ID 
			FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_mime_type IN ({$format_placeholders}) 
			AND ID NOT IN (
				SELECT post_id 
				FROM {$wpdb->postmeta} 
				WHERE meta_key = '_wpat_optimized' AND meta_value = '1'
			)
			{$date_query}",
			$selected_formats
		);

		$ids          = array_map( 'intval', $wpdb->get_col( $query ) );
		$matching_ids = array();
		$total_bytes  = 0;

		foreach ( $ids as $id ) {
			$file_path = get_attached_file( $id );
			if ( $file_path && file_exists( $file_path ) ) {
				$mime_type = get_post_mime_type( $id );
				$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

				// Si la imagen ya es de tipo WebP o su extensión es .webp, marcarla como optimizada y omitirla
				if ( 'image/webp' === $mime_type || 'webp' === $ext ) {
					update_post_meta( $id, '_wpat_optimized', '1' );
					continue;
				}

				if ( ! in_array( $mime_type, $selected_formats, true ) ) {
					continue;
				}

				// Si es un GIF animado, omitir para no perder la animación
				if ( ( 'image/gif' === $mime_type || 'gif' === $ext ) && $this->is_animated_gif( $file_path ) ) {
					update_post_meta( $id, '_wpat_optimized', '1' );
					continue;
				}

				$size_bytes = filesize( $file_path );
				$size_kb    = $size_bytes / 1024;
				if ( $min_size > 0 && $size_kb < $min_size ) {
					continue;
				}
				$matching_ids[] = $id;
				$total_bytes   += $size_bytes;
			}
		}

		wp_send_json_success( array(
			'ids'              => $matching_ids,
			'count'            => count( $matching_ids ),
			'total_bytes'      => $total_bytes,
			'total_bytes_pref' => size_format( $total_bytes ),
			'est_opt_bytes'    => $total_bytes * 0.35,
			'est_opt_bytes_f'  => size_format( $total_bytes * 0.35 ),
		) );
	}

	/**
	 * AJAX: Procesa un lote de imágenes pendientes convirtiéndolas a WebP o comprimiéndolas.
	 */
	public function ajax_optimize_image_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}

		global $wpdb;

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_success( array(
				'processed' => 0,
				'failed'    => 0,
				'pending'   => 0,
				'done'      => true,
				'log'       => array( '[No hay imágenes pendientes de optimizar.]' )
			) );
		}

		$settings      = WPAT_Main::get_instance()->get_settings();
		$quality       = isset( $settings['image_optimizer_quality'] ) ? max( 40, min( 100, intval( $settings['image_optimizer_quality'] ) ) ) : 82;
		$max_width     = isset( $settings['image_optimizer_max_width'] ) ? max( 0, intval( $settings['image_optimizer_max_width'] ) ) : 1920;
		$max_height    = isset( $settings['image_optimizer_max_height'] ) ? max( 0, intval( $settings['image_optimizer_max_height'] ) ) : 1920;
		$keep_original = isset( $settings['image_optimizer_keep_original'] ) && '1' === $settings['image_optimizer_keep_original'];

		$processed_count = 0;
		$failed_count    = 0;
		$logs            = array();

		foreach ( $ids as $id ) {
			$file_path = get_attached_file( $id );

			if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
				update_post_meta( $id, '_wpat_optimized', '1' );
				$failed_count++;
				$logs[] = "Omitida: Imagen ID {$id} (El archivo físico original no existe).";
				continue;
			}

			$mime_type = get_post_mime_type( $id );
			$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

			// Si ya es WebP
			if ( 'image/webp' === $mime_type || 'webp' === $ext ) {
				update_post_meta( $id, '_wpat_optimized', '1' );
				$processed_count++;
				$logs[] = "Omitida: Imagen ID {$id} ya se encuentra en formato WebP.";
				continue;
			}

			// Si es GIF animado
			if ( ( 'image/gif' === $mime_type || 'gif' === $ext ) && $this->is_animated_gif( $file_path ) ) {
				update_post_meta( $id, '_wpat_optimized', '1' );
				$processed_count++;
				$logs[] = "Preservada: ID {$id} es un GIF animado (mantiene animación original).";
				continue;
			}

			$directory = pathinfo( $file_path, PATHINFO_DIRNAME );
			$filename  = pathinfo( $file_path, PATHINFO_FILENAME );
			$webp_path = $directory . '/' . $filename . '.webp';

			// Si la versión .webp YA existe previamente en el servidor
			if ( file_exists( $webp_path ) && filesize( $webp_path ) > 0 ) {
				if ( ! $keep_original && file_exists( $file_path ) && $file_path !== $webp_path ) {
					@unlink( $file_path );
				}
				update_attached_file( $id, $webp_path );
				$wpdb->update(
					$wpdb->posts,
					array( 'post_mime_type' => 'image/webp' ),
					array( 'ID' => $id )
				);

				require_once ABSPATH . 'wp-admin/includes/image.php';
				$new_metadata = wp_generate_attachment_metadata( $id, $webp_path );
				wp_update_attachment_metadata( $id, $new_metadata );

				update_post_meta( $id, '_wpat_optimized', '1' );
				$processed_count++;
				$logs[] = "Reutilizada: ID {$id} -> Se detectó '" . $filename . ".webp' existente y se actualizó el adjunto.";
				continue;
			}

			// Inicializar editor
			$editor = wp_get_image_editor( $file_path );
			if ( is_wp_error( $editor ) ) {
				update_post_meta( $id, '_wpat_optimized', '1' );
				$failed_count++;
				$logs[] = "Error en ID {$id}: No se pudo abrir la imagen (" . $editor->get_error_message() . ").";
				continue;
			}

			// Redimensionar si excede las dimensiones máximas
			$sizes       = $editor->get_size();
			$width       = $sizes['width'];
			$height      = $sizes['height'];
			$was_resized = false;

			if ( ( $max_width > 0 && $width > $max_width ) || ( $max_height > 0 && $height > $max_height ) ) {
				$target_w = $max_width > 0 ? $max_width : $width;
				$target_h = $max_height > 0 ? $max_height : $height;
				$editor->resize( $target_w, $target_h, false );
				$was_resized = true;
			}

			// Verificar si se puede codificar a WebP
			if ( $editor->supports_mime_type( 'image/webp' ) ) {
				$editor->set_quality( $quality );

				// Guardar WebP
				$saved = $editor->save( $webp_path, 'image/webp' );

				if ( ! is_wp_error( $saved ) && file_exists( $webp_path ) && filesize( $webp_path ) > 0 ) {
					// Eliminar imágenes de tamaños anteriores para no dejar basura huérfana en disco
					$old_metadata = wp_get_attachment_metadata( $id );
					if ( ! empty( $old_metadata['sizes'] ) ) {
						foreach ( $old_metadata['sizes'] as $size_info ) {
							$old_size_file = $directory . '/' . $size_info['file'];
							if ( file_exists( $old_size_file ) ) {
								@unlink( $old_size_file );
							}
						}
					}

					// Eliminar imagen original si no se configuró conservarla
					if ( ! $keep_original && file_exists( $file_path ) && $file_path !== $webp_path ) {
						@unlink( $file_path );
					}

					// Actualizar ruta adjunta y tipo mime en DB
					update_attached_file( $id, $webp_path );
					$wpdb->update(
						$wpdb->posts,
						array( 'post_mime_type' => 'image/webp' ),
						array( 'ID' => $id )
					);

					// Regenerar tamaños intermedios en WebP
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$new_metadata = wp_generate_attachment_metadata( $id, $webp_path );
					wp_update_attachment_metadata( $id, $new_metadata );

					// Marcar como optimizada
					update_post_meta( $id, '_wpat_optimized', '1' );
					$processed_count++;
					$logs[] = "Convertida: ID {$id} -> '" . $filename . ".webp' optimizada a WebP (" . $quality . "% calidad).";
				} else {
					update_post_meta( $id, '_wpat_optimized', '1' );
					$failed_count++;
					$err_msg = is_wp_error( $saved ) ? $saved->get_error_message() : 'Error desconocido al guardar WebP';
					$logs[]  = "Error en ID {$id}: No se pudo guardar como WebP (" . $err_msg . ").";
				}
			} else {
				// Servidor sin soporte WebP: guardar compresión original
				$old_metadata = wp_get_attachment_metadata( $id );
				if ( ! empty( $old_metadata['sizes'] ) ) {
					foreach ( $old_metadata['sizes'] as $size_info ) {
						$old_size_file = $directory . '/' . $size_info['file'];
						if ( file_exists( $old_size_file ) ) {
							@unlink( $old_size_file );
						}
					}
				}

				$editor->set_quality( $quality );
				$editor->save( $file_path );

				require_once ABSPATH . 'wp-admin/includes/image.php';
				$new_metadata = wp_generate_attachment_metadata( $id, $file_path );
				wp_update_attachment_metadata( $id, $new_metadata );

				update_post_meta( $id, '_wpat_optimized', '1' );
				$processed_count++;

				if ( $was_resized ) {
					$logs[] = "Optimizada: ID {$id} -> Escalada a máx " . max( $max_width, $max_height ) . "px y comprimida al " . $quality . "% (Sin WebP).";
				} else {
					$logs[] = "Optimizada: ID {$id} -> Comprimida al " . $quality . "% en su formato original (Sin WebP).";
				}
			}
		}

		wp_send_json_success( array(
			'processed' => $processed_count,
			'failed'    => $failed_count,
			'log'       => $logs
		) );
	}

	/**
	 * AJAX: Obtiene todos los IDs de imágenes en la biblioteca para el análisis de huérfanas.
	 */
	public function ajax_scan_unused_images() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		global $wpdb;
		$ids = $wpdb->get_col( "
			SELECT ID 
			FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/webp')
		" );

		wp_send_json_success( array( 'ids' => array_map( 'intval', $ids ) ) );
	}

	/**
	 * AJAX: Comprueba si un lote de IDs de adjunto está en uso y devuelve los huérfanos con seguridad multi-capa.
	 */
	public function ajax_check_unused_images_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		if ( empty( $ids ) ) {
			wp_send_json_success( array( 'unused' => array() ) );
		}

		$unused = array();

		foreach ( $ids as $id ) {
			if ( ! self::is_attachment_in_use( $id ) ) {
				$file_path = get_attached_file( $id );
				$size      = '0 KB';
				if ( $file_path && file_exists( $file_path ) ) {
					$size = size_format( filesize( $file_path ) );
				}
				
				$thumb_url = wp_get_attachment_image_src( $id, 'thumbnail' );
				$unused[]  = array(
					'id'    => $id,
					'name'  => basename( $file_path ? $file_path : 'Desconocido' ),
					'url'   => $thumb_url ? $thumb_url[0] : '',
					'size'  => $size,
					'date'  => get_the_date( 'Y-m-d', $id )
				);
			}
		}

		wp_send_json_success( array( 'unused' => $unused ) );
	}

	/**
	 * AJAX: Elimina una lista de adjuntos huérfanos seleccionados.
	 */
	public function ajax_delete_unused_images() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => 'No se especificaron imágenes para eliminar.' ) );
		}

		$deleted_count = 0;
		foreach ( $ids as $id ) {
			// Doble verificación de seguridad antes de borrar
			if ( ! self::is_attachment_in_use( $id ) ) {
				if ( wp_delete_attachment( $id, true ) ) {
					$deleted_count++;
				}
			}
		}

		wp_send_json_success( array( 'deleted' => $deleted_count ) );
	}

	/**
	 * Comprueba exhaustivamente si un adjunto específico está en uso referenciado en cualquier parte del sitio.
	 * Incluye: Post thumbnail, Galerías WooCommerce, Taxonomías, Logo/Favicon, Elementor (por ID y URL),
	 * Gutenberg Blocks, Custom Fields (ACF, JetEngine, MetaBox) y Widgets/Opciones Globales.
	 *
	 * @param int $attachment_id ID del adjunto.
	 * @return bool True si la imagen está en uso, False si es huérfana.
	 */
	public static function is_attachment_in_use( $attachment_id ) {
		global $wpdb;

		$attachment_id = intval( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return true; // Seguridad
		}

		// 1. Imagen destacada (Featured Image / Thumbnail) en posts, páginas o CPTs
		$is_featured = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s",
			(string) $attachment_id
		) );
		if ( $is_featured > 0 ) {
			return true;
		}

		// 2. Galería de productos WooCommerce
		$is_in_gallery = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND (meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)",
			(string) $attachment_id,
			$attachment_id . ',%',
			'%,' . $attachment_id,
			'%,' . $attachment_id . ',%'
		) );
		if ( $is_in_gallery > 0 ) {
			return true;
		}

		// 3. Miniaturas de taxonomías y categorías (WooCommerce / Termmeta)
		$is_in_term = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE (meta_key = 'thumbnail_id' OR meta_key LIKE '%image%' OR meta_key LIKE '%thumbnail%') AND meta_value = %s",
			(string) $attachment_id
		) );
		if ( $is_in_term > 0 ) {
			return true;
		}

		// 4. Logo del sitio, Favicon e Icono de Aplicación (Site Icon / Custom Logo / Theme Mods)
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		$site_icon_id   = get_option( 'site_icon' );
		$header_img_id  = get_theme_mod( 'header_image_data' );

		if ( (int) $custom_logo_id === $attachment_id || (int) $site_icon_id === $attachment_id ) {
			return true;
		}
		if ( is_array( $header_img_id ) && isset( $header_img_id['attachment_id'] ) && (int) $header_img_id['attachment_id'] === $attachment_id ) {
			return true;
		}

		// 5. Elementor: Búsqueda por ID directo en estructuras JSON (_elementor_data y _elementor_page_settings)
		$is_in_elementor_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE (meta_key = '_elementor_data' OR meta_key = '_elementor_page_settings') AND (meta_value LIKE %s OR meta_value LIKE %s)",
			'%"id":' . $attachment_id . '%',
			'%"id":"' . $attachment_id . '"%'
		) );
		if ( $is_in_elementor_id > 0 ) {
			return true;
		}

		// 6. Gutenberg / Shortcodes / Referencia por ID de adjunto en contenido (posts no eliminados)
		$is_in_block = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_status IN ('publish', 'draft', 'pending', 'future', 'private') 
			AND post_type NOT IN ('revision', 'auto-draft') 
			AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)",
			'%wp-image-' . $attachment_id . '%',
			'%"id":' . $attachment_id . '%',
			'%[gallery%ids=%' . $attachment_id . '%'
		) );
		if ( $is_in_block > 0 ) {
			return true;
		}

		// 7. Búsqueda por nombre de archivo físico (URL, Elementor, ACF, Slider, Widgets)
		$file_path = get_attached_file( $attachment_id );
		if ( ! empty( $file_path ) ) {
			$filename      = basename( $file_path );
			$filename_like = '%' . $wpdb->esc_like( $filename ) . '%';

			// En contenido de entradas y páginas
			$is_in_content = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} 
				WHERE post_status IN ('publish', 'draft', 'pending', 'future', 'private') 
				AND post_type NOT IN ('revision', 'auto-draft') 
				AND post_content LIKE %s",
				$filename_like
			) );
			if ( $is_in_content > 0 ) {
				return true;
			}

			// En metadatos de post (ACF, JetEngine, MetaBox, Elementor URL)
			$is_in_meta = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} 
				WHERE meta_key NOT IN ('_wp_attached_file', '_wp_attachment_metadata') 
				AND meta_value LIKE %s",
				$filename_like
			) );
			if ( $is_in_meta > 0 ) {
				return true;
			}

			// En opciones globales (Widgets, sliders, cabeceras de temas)
			$is_in_options = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} 
				WHERE option_name NOT LIKE '_transient_%' 
				AND option_value LIKE %s",
				$filename_like
			) );
			if ( $is_in_options > 0 ) {
				return true;
			}
		}

		// 8. Búsqueda de ID exacto en campos personalizados (ACF / JetEngine / Meta Box)
		$is_in_custom_field = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} 
			WHERE meta_key NOT IN ('_thumbnail_id', '_wp_attached_file', '_wp_attachment_metadata', '_wpat_optimized') 
			AND (meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s)",
			(string) $attachment_id,
			'%:"' . $attachment_id . '";%',
			'%:i:' . $attachment_id . ';%'
		) );
		if ( $is_in_custom_field > 0 ) {
			return true;
		}

		return false;
	}

	/* =========================================================================
	 * GENERADOR Y REPARADOR DE TEXTOS ALT (ACCESIBILIDAD & SEO)
	 * ========================================================================= */

	/**
	 * Genera un texto ALT limpio y accesible a partir del nombre del archivo o del post contenedor.
	 *
	 * @param int    $attachment_id ID del adjunto.
	 * @param string $source        Fuente: 'filename' o 'post_title'.
	 * @return string Texto ALT limpio y formateado.
	 */
	public static function generate_clean_alt_text( $attachment_id, $source = 'filename' ) {
		$alt = '';

		// Si se solicita prioritariamente usar el título del post contenedor
		if ( 'post_title' === $source ) {
			$post_parent = wp_get_post_parent_id( $attachment_id );
			if ( $post_parent ) {
				$parent_title = get_the_title( $post_parent );
				if ( ! empty( $parent_title ) && ! preg_match( '/^Sin título/i', $parent_title ) ) {
					$alt = $parent_title;
				}
			}
		}

		// Si la fuente es 'filename' o no había título en el post contenedor
		if ( empty( $alt ) ) {
			$file_path = get_attached_file( $attachment_id );
			if ( ! empty( $file_path ) ) {
				$base = pathinfo( $file_path, PATHINFO_FILENAME );
			} else {
				$post = get_post( $attachment_id );
				$base = $post ? $post->post_name : '';
			}

			if ( ! empty( $base ) ) {
				// 1. Quitar sufijos de medidas de WordPress (ej: -150x150, -scaled, -rotated, -300x200)
				$base = preg_replace( '/-\d+x\d+$/i', '', $base );
				$base = preg_replace( '/-(scaled|rotated)$/i', '', $base );

				// 2. Quitar patrones comunes de cámaras/móviles o números secuenciales tipo IMG_20241023_...
				$base = preg_replace( '/^(img|dsc|photo|foto|captura|screenshot|pexels|unsplash)[-_]?\d*[-_]?/i', '', $base );

				// 3. Reemplazar guiones, guiones bajos y puntos por espacios
				$base = str_replace( array( '-', '_', '.' ), ' ', $base );

				// 4. Limpiar múltiples espacios
				$base = trim( preg_replace( '/\s+/', ' ', $base ) );

				// 5. Capitalizar de forma legible tipo frase (primera letra de cada palabra)
				if ( ! empty( $base ) ) {
					$alt = mb_convert_case( $base, MB_CASE_TITLE, 'UTF-8' );
				}
			}
		}

		// Fallback: Si el nombre quedó vacío (ej: si era solo números), usar el título del propio attachment si existe
		if ( empty( $alt ) ) {
			$attachment_post = get_post( $attachment_id );
			if ( $attachment_post && ! empty( $attachment_post->post_title ) ) {
				$clean_title = sanitize_text_field( $attachment_post->post_title );
				if ( ! preg_match( '/^Sin título/i', $clean_title ) ) {
					$alt = $clean_title;
				}
			}
		}

		return trim( $alt );
	}

	/**
	 * Hook al subir una nueva imagen para auto-generar su texto ALT si está vacío.
	 *
	 * @param int $attachment_id ID del adjunto recién subido.
	 */
	public function maybe_auto_generate_alt( $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		// Solo asignar si el ALT actual está vacío (regla de oro: nunca sobrescribir ALT existente)
		$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( ! empty( $current_alt ) ) {
			return;
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$alt_source = isset( $settings['image_optimizer_alt_source'] ) ? $settings['image_optimizer_alt_source'] : 'filename';

		$new_alt = self::generate_clean_alt_text( $attachment_id, $alt_source );
		if ( ! empty( $new_alt ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $new_alt );
		}
	}

	/**
	 * AJAX: Escanea imágenes de la biblioteca con texto ALT vacío o no asignado.
	 */
	public function ajax_scan_empty_alt_images() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'wp-agency-toolkit' ) ) );
		}

		global $wpdb;

		// Buscar adjuntos de tipo imagen donde _wp_attachment_image_alt no existe o está en blanco
		$query = "
			SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt')
			WHERE p.post_type = 'attachment'
			  AND p.post_mime_type LIKE 'image/%'
			  AND (pm.meta_value IS NULL OR TRIM(pm.meta_value) = '')
			ORDER BY p.ID DESC
		";

		$ids = $wpdb->get_col( $query );
		$ids = array_map( 'intval', (array) $ids );

		wp_send_json_success( array(
			'ids'   => $ids,
			'count' => count( $ids ),
		) );
	}

	/**
	 * AJAX: Procesa un lote de IDs generando y guardando su texto ALT.
	 */
	public function ajax_fill_empty_alt_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'wp-agency-toolkit' ) ) );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_success( array(
				'processed' => 0,
				'log'       => array( __( 'No hay más imágenes pendientes.', 'wp-agency-toolkit' ) ),
			) );
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$alt_source = isset( $settings['image_optimizer_alt_source'] ) ? $settings['image_optimizer_alt_source'] : 'filename';

		$processed = 0;
		$logs      = array();

		foreach ( $ids as $id ) {
			// Doble comprobación de seguridad: jamás sobrescribir si ya tiene un ALT
			$existing_alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! empty( $existing_alt ) ) {
				continue;
			}

			$generated_alt = self::generate_clean_alt_text( $id, $alt_source );
			if ( ! empty( $generated_alt ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $generated_alt );
				$file_name = basename( (string) get_attached_file( $id ) );
				if ( empty( $file_name ) ) {
					$file_name = 'adjunto #' . $id;
				}
				$logs[]    = sprintf( __( 'ID #%d (%s) → ALT: "%s"', 'wp-agency-toolkit' ), $id, $file_name, $generated_alt );
				$processed++;
			}
		}

		wp_send_json_success( array(
			'processed' => $processed,
			'log'       => $logs,
		) );
	}
}

// Inicializar el módulo
WPAT_Image_Optimizer::get_instance();

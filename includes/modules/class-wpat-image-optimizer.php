<?php
/**
 * Módulo: Optimizador de Imágenes a WebP & Optimización Masiva - WP Agency Toolkit
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
		// Interceptar la carga del archivo una vez subido con éxito (automatización en caliente)
		add_filter( 'wp_handle_upload', array( $this, 'optimize_and_convert_to_webp' ), 10, 2 );
		add_filter( 'wp_handle_sideload', array( $this, 'optimize_and_convert_to_webp' ), 10, 2 );

		// Endpoints AJAX para optimización masiva retroactiva
		add_action( 'wp_ajax_wpat_scan_images', array( $this, 'ajax_scan_images' ) );
		add_action( 'wp_ajax_wpat_optimize_image_batch', array( $this, 'ajax_optimize_image_batch' ) );
	}

	/**
	 * Optimiza, redimensiona y convierte la imagen subida a formato WebP.
	 *
	 * @param array  $upload Array de datos del archivo subido.
	 * @param string $context Contexto de la acción.
	 * @return array
	 */
	public function optimize_and_convert_to_webp( $upload, $context ) {
		// Validar que no haya habido errores previos en la subida
		if ( isset( $upload['error'] ) && ! empty( $upload['error'] ) ) {
			return $upload;
		}

		$file_path = $upload['file'];
		$mime_type = $upload['type'];

		// Módulos válidos a procesar (saltar SVG, PDF, WebP ya subido, etc.)
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			return $upload;
		}

		// Inicializar el editor de imágenes de WordPress (GD o Imagick)
		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return $upload;
		}

		// 1. Redimensionar si supera 1920px (ancho o alto)
		$sizes       = $editor->get_size();
		$width       = $sizes['width'];
		$height      = $sizes['height'];
		$was_resized = false;

		if ( $width > 1920 || $height > 1920 ) {
			$editor->resize( 1920, 1920, false );
			$was_resized = true;
		}

		// Verificar soporte del servidor para codificación WebP
		if ( ! $editor->supports_mime_type( 'image/webp' ) ) {
			// Si no soporta WebP, optimizamos reduciendo el peso en su formato original
			$editor->set_quality( 82 );
			$editor->save( $file_path );
			return $upload;
		}

		// 2. Convertir y Guardar en formato WebP con calidad al 82%
		$editor->set_quality( 82 );

		// Preparar la nueva ruta y nombre de archivo .webp
		$path_info = pathinfo( $file_path );
		$directory = $path_info['dirname'];
		$filename  = $path_info['filename'];
		$webp_path = $directory . '/' . $filename . '.webp';

		// Evitar colisiones de nombres si el .webp ya existe
		if ( file_exists( $webp_path ) ) {
			$suffix = 1;
			while ( file_exists( $directory . '/' . $filename . '-' . $suffix . '.webp' ) ) {
				$suffix++;
			}
			$webp_path = $directory . '/' . $filename . '-' . $suffix . '.webp';
			$filename  = $filename . '-' . $suffix;
		}

		// Guardar el nuevo archivo WebP
		$saved = $editor->save( $webp_path, 'image/webp' );

		if ( ! is_wp_error( $saved ) ) {
			// Eliminar físicamente la imagen original (JPEG, PNG, GIF) pesada
			@unlink( $file_path );

			// Actualizar el array del upload para que WordPress procese la imagen .webp
			$upload['file'] = $webp_path;
			$upload['type'] = 'image/webp';
			
			// Actualizar la URL pública de la imagen
			$url_info      = pathinfo( $upload['url'] );
			$upload['url'] = $url_info['dirname'] . '/' . $filename . '.webp';
		} else {
			if ( $was_resized ) {
				$editor->save( $file_path );
			}
		}

		return $upload;
	}

	/**
	 * AJAX: Escanea la cantidad de imágenes JPEG/PNG/GIF pendientes de optimización en la Biblioteca usando filtros.
	 */
	public function ajax_scan_images() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		global $wpdb;

		$min_size = isset( $_POST['min_size'] ) ? (int) $_POST['min_size'] : 0;
		$date_start = isset( $_POST['date_start'] ) ? sanitize_text_field( $_POST['date_start'] ) : '';

		$date_query = "";
		if ( ! empty( $date_start ) ) {
			$date_query = $wpdb->prepare( "AND post_date >= %s", $date_start . ' 00:00:00' );
		}

		// Obtener todos los candidatos no optimizados y filtrar por fecha
		$query = "
			SELECT ID 
			FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_mime_type IN ('image/jpeg', 'image/png', 'image/gif') 
			AND ID NOT IN (
				SELECT post_id 
				FROM {$wpdb->postmeta} 
				WHERE meta_key = '_wpat_optimized' AND meta_value = '1'
			)
			{$date_query}
		";

		$ids = array_map( 'intval', $wpdb->get_col( $query ) );
		$matching_ids = array();
		$total_bytes = 0;

		foreach ( $ids as $id ) {
			$file_path = get_attached_file( $id );
			if ( $file_path && file_exists( $file_path ) ) {
				$size_bytes = filesize( $file_path );
				$size_kb = $size_bytes / 1024;
				if ( $min_size > 0 && $size_kb < $min_size ) {
					continue;
				}
				$matching_ids[] = $id;
				$total_bytes += $size_bytes;
			}
		}

		wp_send_json_success( array(
			'ids'               => $matching_ids,
			'count'             => count( $matching_ids ),
			'total_bytes'       => $total_bytes,
			'total_bytes_pref'  => size_format( $total_bytes ),
			'est_opt_bytes'     => $total_bytes * 0.30,
			'est_opt_bytes_f'   => size_format( $total_bytes * 0.30 ),
		) );
	}

	/**
	 * AJAX: Procesa un lote de imágenes pendientes convirtiéndolas a WebP o comprimiéndolas.
	 */
	public function ajax_optimize_image_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		global $wpdb;

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_success( array(
				'processed' => 0,
				'failed'    => 0,
				'pending'   => 0,
				'done'      => true,
				'log'       => array( '[No hay imágenes pendientes de optimizar.]' )
			) );
		}

		$processed_count = 0;
		$failed_count    = 0;
		$logs            = array();

		foreach ( $ids as $id ) {
			$file_path = get_attached_file( $id );

			// Si el archivo físico original no existe, omitir marcándolo como procesado para no bloquear el bucle
			if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
				update_post_meta( $id, '_wpat_optimized', '1' );
				$failed_count++;
				$logs[] = "Omitida: Imagen ID {$id} (El archivo físico original no existe).";
				continue;
			}

			// Intentar inicializar editor
			$editor = wp_get_image_editor( $file_path );
			if ( is_wp_error( $editor ) ) {
				update_post_meta( $id, '_wpat_optimized', '1' );
				$failed_count++;
				$logs[] = "Error en ID {$id}: No se pudo abrir la imagen (" . $editor->get_error_message() . ").";
				continue;
			}

			// 1. Redimensionar si excede 1920px
			$sizes       = $editor->get_size();
			$width       = $sizes['width'];
			$height      = $sizes['height'];
			$was_resized = false;

			if ( $width > 1920 || $height > 1920 ) {
				$editor->resize( 1920, 1920, false );
				$was_resized = true;
			}

			$directory = pathinfo( $file_path, PATHINFO_DIRNAME );
			$filename  = pathinfo( $file_path, PATHINFO_FILENAME );

			// Verificar si se puede codificar a WebP
			if ( $editor->supports_mime_type( 'image/webp' ) ) {
				$editor->set_quality( 82 );
				$webp_path = $directory . '/' . $filename . '.webp';

				// Evitar colisiones de nombres
				if ( file_exists( $webp_path ) ) {
					$suffix = 1;
					while ( file_exists( $directory . '/' . $filename . '-' . $suffix . '.webp' ) ) {
						$suffix++;
					}
					$webp_path = $directory . '/' . $filename . '-' . $suffix . '.webp';
					$filename  = $filename . '-' . $suffix;
				}

				// Guardar WebP
				$saved = $editor->save( $webp_path, 'image/webp' );

				if ( ! is_wp_error( $saved ) ) {
					// Eliminar imágenes de tamaños anteriores para no dejar basura huérfana en el disco
					$old_metadata = wp_get_attachment_metadata( $id );
					if ( ! empty( $old_metadata['sizes'] ) ) {
						foreach ( $old_metadata['sizes'] as $size_info ) {
							$old_size_file = $directory . '/' . $size_info['file'];
							if ( file_exists( $old_size_file ) ) {
								@unlink( $old_size_file );
							}
						}
					}

					// Eliminar imagen original
					@unlink( $file_path );

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
					$logs[] = "Convertida: ID {$id} -> '" . $filename . ".webp' optimizada a WebP.";
				} else {
					update_post_meta( $id, '_wpat_optimized', '1' );
					$failed_count++;
					$logs[] = "Error en ID {$id}: No se pudo guardar como WebP (" . $saved->get_error_message() . ").";
				}
			} else {
				// Servidor sin soporte WebP: guardar compresión original (y redimensión si aplica)
				// Eliminar tamaños de sub-imagen antiguos para regenerarlos comprimidos
				$old_metadata = wp_get_attachment_metadata( $id );
				if ( ! empty( $old_metadata['sizes'] ) ) {
					foreach ( $old_metadata['sizes'] as $size_info ) {
						$old_size_file = $directory . '/' . $size_info['file'];
						if ( file_exists( $old_size_file ) ) {
							@unlink( $old_size_file );
						}
					}
				}

				// Comprimir al 82%
				$editor->set_quality( 82 );
				$editor->save( $file_path );

				// Regenerar tamaños intermedios
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$new_metadata = wp_generate_attachment_metadata( $id, $file_path );
				wp_update_attachment_metadata( $id, $new_metadata );

				update_post_meta( $id, '_wpat_optimized', '1' );
				$processed_count++;

				if ( $was_resized ) {
					$logs[] = "Optimizada: ID {$id} -> Escalada a 1920px y comprimida al 82% (Sin WebP).";
				} else {
					$logs[] = "Optimizada: ID {$id} -> Comprimida al 82% en su formato original (Sin WebP).";
				}
			}
		}

		wp_send_json_success( array(
			'processed' => $processed_count,
			'failed'    => $failed_count,
			'log'       => $logs
		) );
	}
}

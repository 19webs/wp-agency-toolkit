<?php
/**
 * Módulo: Renombrador de Archivos de Medios (Media Renamer) - WP Agency Toolkit
 *
 * Permite cambiar el nombre físico de los archivos subidos a la Biblioteca de Medios
 * directamente desde WordPress, actualizando automáticamente el archivo principal,
 * todos los tamaños de miniaturas generadas, metadatos, GUID y referencias en el contenido.
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Media_Renamer {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_Media_Renamer|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia única.
	 *
	 * @return WPAT_Media_Renamer
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
		// Añadir campo personalizado en la modal y pantalla de edición de adjuntos
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_rename_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_rename_field' ), 10, 2 );

		// Acciones de fila en la vista de lista de Medios
		add_filter( 'media_row_actions', array( $this, 'add_media_row_action' ), 10, 2 );

		// AJAX para renombrado en vivo
		add_action( 'wp_ajax_wpat_rename_attachment', array( $this, 'ajax_rename_attachment' ) );

		// Inyectar scripts y estilos auxiliares en el pie de administración
		add_action( 'admin_footer', array( $this, 'render_admin_footer_scripts' ) );

		// Aviso de éxito tras guardar y redirigir
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Muestra aviso de éxito en la biblioteca de medios al volver tras renombrar.
	 */
	public function render_admin_notices() {
		if ( isset( $_GET['wpat_renamed'] ) && ! empty( $_GET['wpat_renamed'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_GET['wpat_renamed'] ) );
			?>
			<div class="notice notice-success is-dismissible" style="border-left-color: #10b981; padding: 10px 15px; margin: 15px 0;">
				<p style="font-size: 13.5px; margin: 0; display: flex; align-items: center; gap: 8px;">
					<span style="color: #10b981; font-size: 18px; font-weight: bold;">✓</span>
					<span><strong><?php esc_html_e( 'Archivo renombrado con éxito:', 'wp-agency-toolkit' ); ?></strong> <code><?php echo esc_html( $name ); ?></code> <?php esc_html_e( '(actualizado en disco, miniaturas y contenido)', 'wp-agency-toolkit' ); ?></span>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Añade el campo de renombrado en la vista de detalles del adjunto.
	 *
	 * @param array   $form_fields Campos del formulario de medios.
	 * @param WP_Post $post Objeto del adjunto.
	 * @return array
	 */
	public function add_attachment_rename_field( $form_fields, $post ) {
		$file_path = get_attached_file( $post->ID );
		if ( empty( $file_path ) ) {
			return $form_fields;
		}

		$filename_with_ext = basename( $file_path );
		$ext               = pathinfo( $filename_with_ext, PATHINFO_EXTENSION );
		$filename_only     = pathinfo( $filename_with_ext, PATHINFO_FILENAME );

		$html  = '<div class="wpat-rename-field-wrap" style="display: flex; gap: 8px; align-items: center; max-width: 100%; flex-wrap: wrap; margin-bottom: 4px;">';
		$html .= '<input type="text" class="text wpat-rename-input" id="wpat_rename_' . esc_attr( $post->ID ) . '" name="attachments[' . esc_attr( $post->ID ) . '][wpat_new_filename]" value="' . esc_attr( $filename_only ) . '" style="flex: 1; min-width: 180px; font-weight: 600;" />';
		$html .= '<span class="wpat-rename-ext-badge" style="font-weight: 700; color: #475569; font-size: 13px; background: #e2e8f0; padding: 4px 8px; border-radius: 4px;">.' . esc_html( $ext ) . '</span>';
		$html .= '<button type="button" class="button button-primary wpat-ajax-rename-btn" data-attachment-id="' . esc_attr( $post->ID ) . '" style="font-size: 12px; font-weight: 600;">' . esc_html__( 'Renombrar y Guardar', 'wp-agency-toolkit' ) . '</button>';
		$html .= '<span class="wpat-rename-feedback-' . esc_attr( $post->ID ) . '" style="display: none; width: 100%; font-size: 12px; margin-top: 4px;"></span>';
		$html .= '</div>';
		$html .= '<p class="description" style="margin-top: 2px; font-size: 11.5px; color: #64748b;">' . esc_html__( 'Cambia el nombre físico en disco, todas sus miniaturas y actualiza las referencias en posts y páginas.', 'wp-agency-toolkit' ) . '</p>';

		$form_fields['wpat_filename'] = array(
			'label' => __( 'Nombre de Archivo', 'wp-agency-toolkit' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $form_fields;
	}

	/**
	 * Procesa el guardado tradicional si se envió el formulario nativo de medios y redirige a la biblioteca.
	 *
	 * @param array $post Datos del post.
	 * @param array $attachment Datos del formulario de adjunto.
	 * @return array
	 */
	public function save_attachment_rename_field( $post, $attachment ) {
		if ( isset( $attachment['wpat_new_filename'] ) && ! empty( $attachment['wpat_new_filename'] ) ) {
			$result = $this->rename_attachment( $post['ID'], $attachment['wpat_new_filename'] );
			if ( ! is_wp_error( $result ) && isset( $result['filename'] ) ) {
				// Redirigir de vuelta a la Biblioteca de Medios tras guardar en la pantalla clásica
				add_filter( 'redirect_post_location', function( $location ) use ( $result ) {
					return admin_url( 'upload.php?wpat_renamed=' . urlencode( $result['filename'] ) );
				}, 99 );
			}
		}
		return $post;
	}

	/**
	 * Añade una acción rápida "Renombrar" en la tabla de listado de medios.
	 *
	 * @param array   $actions Acciones existentes.
	 * @param WP_Post $post Adjunto actual.
	 * @return array
	 */
	public function add_media_row_action( $actions, $post ) {
		if ( current_user_can( 'upload_files' ) ) {
			$file_path     = get_attached_file( $post->ID );
			$current_name  = $file_path ? pathinfo( basename( $file_path ), PATHINFO_FILENAME ) : '';
			$actions['wpat_rename'] = sprintf(
				'<a href="#" class="wpat-quick-rename-trigger" data-id="%d" data-current="%s" style="color: #2563eb; font-weight: 600;">%s</a>',
				esc_attr( $post->ID ),
				esc_attr( $current_name ),
				esc_html__( 'Renombrar', 'wp-agency-toolkit' )
			);
		}
		return $actions;
	}

	/**
	 * Endpoint AJAX para renombrar un archivo de medios en tiempo real.
	 */
	public function ajax_rename_attachment() {
		check_ajax_referer( 'wpat_media_rename_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'wp-agency-toolkit' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		$new_filename  = isset( $_POST['new_filename'] ) ? sanitize_text_field( wp_unslash( $_POST['new_filename'] ) ) : '';

		if ( ! $attachment_id || empty( $new_filename ) ) {
			wp_send_json_error( array( 'message' => __( 'Parámetros inválidos.', 'wp-agency-toolkit' ) ) );
		}

		$result = $this->rename_attachment( $attachment_id, $new_filename );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Sanitiza de forma estricta un nombre de archivo para SEO y seguridad.
	 *
	 * @param string $filename Nombre original o propuesto.
	 * @return string
	 */
	public static function sanitize_name( $filename ) {
		// Quitar acentos y caracteres especiales
		$name = remove_accents( $filename );
		// Minúsculas
		$name = strtolower( $name );
		// Reemplazar espacios y caracteres no deseados por guiones
		$name = preg_replace( '/[^a-z0-9\-_\.]/', '-', $name );
		// Evitar guiones múltiples seguidos
		$name = preg_replace( '/-+/', '-', $name );
		// Quitar guiones al principio o al final
		$name = trim( $name, '-._ ' );

		return ! empty( $name ) ? $name : 'archivo';
	}

	/**
	 * Realiza el renombrado físico del archivo, miniaturas y actualización en la BD.
	 *
	 * @param int    $attachment_id ID del adjunto.
	 * @param string $new_filename_raw Nuevo nombre sin extensión o con extensión.
	 * @return array|WP_Error
	 */
	public function rename_attachment( $attachment_id, $new_filename_raw ) {
		global $wpdb;

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'invalid_attachment', __( 'El archivo adjunto no existe.', 'wp-agency-toolkit' ) );
		}

		$old_full_path = get_attached_file( $attachment_id );
		if ( empty( $old_full_path ) || ! file_exists( $old_full_path ) ) {
			return new WP_Error( 'file_not_found', __( 'El archivo original no se encuentra en el servidor.', 'wp-agency-toolkit' ) );
		}

		$settings = get_option( 'wpat_settings', array() );
		$auto_sanitize = ! isset( $settings['wpat_media_auto_sanitize'] ) || '1' === (string) $settings['wpat_media_auto_sanitize'];
		$update_posts  = ! isset( $settings['wpat_media_update_posts'] ) || '1' === (string) $settings['wpat_media_update_posts'];
		$sync_title    = isset( $settings['wpat_media_sync_title'] ) && '1' === (string) $settings['wpat_media_sync_title'];
		$sync_alt      = isset( $settings['wpat_media_sync_alt'] ) && '1' === (string) $settings['wpat_media_sync_alt'];

		$file_dir          = pathinfo( $old_full_path, PATHINFO_DIRNAME );
		$old_filename_full = basename( $old_full_path );
		$extension         = strtolower( pathinfo( $old_filename_full, PATHINFO_EXTENSION ) );
		$old_base_name     = pathinfo( $old_filename_full, PATHINFO_FILENAME );

		// Extraer nombre limpio sin extensión
		$new_base_name = pathinfo( $new_filename_raw, PATHINFO_FILENAME );
		if ( $auto_sanitize ) {
			$new_base_name = self::sanitize_name( $new_base_name );
		}

		if ( empty( $new_base_name ) ) {
			return new WP_Error( 'empty_name', __( 'El nombre de archivo propuesto no es válido.', 'wp-agency-toolkit' ) );
		}

		// Si el nombre es exactamente igual al actual, no hacer nada
		if ( $old_base_name === $new_base_name ) {
			return array(
				'message'       => __( 'El archivo ya tiene este nombre.', 'wp-agency-toolkit' ),
				'filename'      => $old_filename_full,
				'old_filename'  => $old_filename_full,
				'new_url'       => wp_get_attachment_url( $attachment_id ),
				'thumbnails'    => 0,
				'posts_updated' => 0,
			);
		}

		// Obtener nombre de archivo único si ya existe otro con ese nombre en la carpeta
		$new_filename_full = $new_base_name . '.' . $extension;
		$new_full_path     = $file_dir . '/' . $new_filename_full;

		if ( file_exists( $new_full_path ) ) {
			$counter = 1;
			while ( file_exists( $file_dir . '/' . $new_base_name . '-' . $counter . '.' . $extension ) ) {
				$counter++;
			}
			$new_base_name     = $new_base_name . '-' . $counter;
			$new_filename_full = $new_base_name . '.' . $extension;
			$new_full_path     = $file_dir . '/' . $new_filename_full;
		}

		// Obtener URLs antiguas antes de renombrar para el reemplazo en BD
		$old_url = wp_get_attachment_url( $attachment_id );
		$url_replacements = array();

		// 1. Renombrar archivo físico principal
		if ( ! @rename( $old_full_path, $new_full_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'rename_failed', __( 'No se pudo renombrar el archivo principal en el disco. Comprueba los permisos de escritura.', 'wp-agency-toolkit' ) );
		}

		// 2. Renombrar miniaturas y metadatos
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$renamed_thumbs = 0;

		if ( is_array( $metadata ) ) {
			// Manejo de imagen original escalada de WordPress (ej: -scaled.jpg)
			if ( ! empty( $metadata['original_image'] ) ) {
				$old_orig_file = $file_dir . '/' . $metadata['original_image'];
				$orig_ext      = pathinfo( $metadata['original_image'], PATHINFO_EXTENSION );
				$new_orig_name = $new_base_name . '.' . $orig_ext;
				$new_orig_file = $file_dir . '/' . $new_orig_name;

				if ( file_exists( $old_orig_file ) ) {
					@rename( $old_orig_file, $new_orig_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$metadata['original_image'] = $new_orig_name;
				}
			}

			// Renombrar todas las miniaturas generadas
			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				$base_url = trailingslashit( pathinfo( $old_url, PATHINFO_DIRNAME ) );

				foreach ( $metadata['sizes'] as $size_key => $size_data ) {
					if ( empty( $size_data['file'] ) ) {
						continue;
					}

					$old_thumb_name = $size_data['file'];
					$old_thumb_path = $file_dir . '/' . $old_thumb_name;

					// Reemplazar la raíz del nombre antiguo por la nueva
					if ( 0 === strpos( $old_thumb_name, $old_base_name ) ) {
						$suffix = substr( $old_thumb_name, strlen( $old_base_name ) );
						$new_thumb_name = $new_base_name . $suffix;
					} else {
						$thumb_ext = pathinfo( $old_thumb_name, PATHINFO_EXTENSION );
						$new_thumb_name = $new_base_name . '-' . $size_data['width'] . 'x' . $size_data['height'] . '.' . $thumb_ext;
					}

					$new_thumb_path = $file_dir . '/' . $new_thumb_name;

					if ( file_exists( $old_thumb_path ) ) {
						if ( @rename( $old_thumb_path, $new_thumb_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
							$url_replacements[ $base_url . $old_thumb_name ] = $base_url . $new_thumb_name;
							$metadata['sizes'][ $size_key ]['file'] = $new_thumb_name;
							$renamed_thumbs++;
						}
					}
				}
			}

			// Actualizar metadatos
			if ( ! empty( $metadata['file'] ) ) {
				$rel_dir = pathinfo( $metadata['file'], PATHINFO_DIRNAME );
				$metadata['file'] = ( '.' === $rel_dir || empty( $rel_dir ) ) ? $new_filename_full : $rel_dir . '/' . $new_filename_full;
			}
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		// 3. Actualizar ruta relativa en _wp_attached_file
		$new_rel_path = _wp_relative_upload_path( $new_full_path );
		update_attached_file( $attachment_id, $new_rel_path );

		// 4. Actualizar Post adjunto (slug, guid, y opcionalmente título/alt)
		$new_url = wp_get_attachment_url( $attachment_id );
		$url_replacements[ $old_url ] = $new_url;

		$clean_readable_title = ucwords( str_replace( array( '-', '_' ), ' ', $new_base_name ) );

		$post_update_args = array(
			'ID'        => $attachment_id,
			'post_name' => $new_base_name,
			'guid'      => $new_url,
		);

		if ( $sync_title ) {
			$post_update_args['post_title'] = $clean_readable_title;
		}

		wp_update_post( $post_update_args );

		if ( $sync_alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $clean_readable_title );
		}

		// 5. Actualizar referencias en el contenido de Posts, Páginas y Productos
		$updated_posts_count = 0;
		if ( $update_posts && ! empty( $url_replacements ) ) {
			foreach ( $url_replacements as $old_img_url => $new_img_url ) {
				if ( $old_img_url === $new_img_url ) {
					continue;
				}
				$affected = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
						$old_img_url,
						$new_img_url,
						'%' . $wpdb->esc_like( $old_img_url ) . '%'
					)
				);
				if ( false !== $affected ) {
					$updated_posts_count += intval( $affected );
				}
			}
		}

		// Limpiar caché de post
		clean_post_cache( $attachment_id );

		return array(
			'message'       => __( 'Archivo y miniaturas renombrados correctamente.', 'wp-agency-toolkit' ),
			'filename'      => $new_filename_full,
			'old_filename'  => $old_filename_full,
			'new_url'       => $new_url,
			'title'         => $clean_readable_title,
			'thumbnails'    => $renamed_thumbs,
			'posts_updated' => $updated_posts_count,
		);
	}

	/**
	 * Imprime scripts JS y estilos necesarios para el renombrado en vivo en la Biblioteca de Medios y pantallas de edición.
	 */
	public function render_admin_footer_scripts() {
		$nonce = wp_create_nonce( 'wpat_media_rename_nonce' );
		?>
		<script type="text/javascript">
		(function($) {
			if (!$) return;

			var mediaNonce = '<?php echo esc_js( $nonce ); ?>';
			var ajaxEndpoint = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

			// 1. Renombrado AJAX desde la Modal o formulario de adjuntos
			$(document).on('click', '.wpat-ajax-rename-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var attachId = $btn.data('attachment-id');
				var $input = $('#wpat_rename_' + attachId);
				var newName = $input.val().trim();
				var $feedback = $('.wpat-rename-feedback-' + attachId);

				if (!newName) {
					alert('Por favor, escribe un nombre de archivo válido.');
					return;
				}

				var origText = $btn.text();
				$btn.prop('disabled', true).text('⏳ Guardando y Renombrando...');
				$feedback.hide().empty();

				$.ajax({
					url: ajaxEndpoint,
					type: 'POST',
					data: {
						action: 'wpat_rename_attachment',
						nonce: mediaNonce,
						attachment_id: attachId,
						new_filename: newName
					},
					success: function(res) {
						$btn.prop('disabled', false).text(origText);
						if (res.success && res.data) {
							var data = res.data;
							
							// Actualizar input con el nombre limpio sin extensión
							if (data.filename) {
								var dotPos = data.filename.lastIndexOf('.');
								var baseOnly = dotPos !== -1 ? data.filename.substring(0, dotPos) : data.filename;
								$input.val(baseOnly);
							}

							// Mostrar mensaje de éxito local
							$feedback.html('<span style="color: #059669; font-weight: 700;">✓ ¡Renombrado a: ' + data.filename + '!</span>').fadeIn(200);

							// Actualizar textos y enlaces en la Modal / Sidebar de WordPress
							var $modal = $btn.closest('.media-modal, .attachment-details, .media-sidebar, .edit-attachment-frame, body');
							$modal.find('.attachment-info .filename, .attachment-details .filename, .media-sidebar .filename, .details .filename, span.filename').text(data.filename);
							
							// Actualizar campos de URL y botones de copiado de URL
							$modal.find('input.url, .setting[data-setting="url"] input, #attachment-details-two-column-copy-link').val(data.new_url);
							$modal.find('.attachment-details-two-column-copy-link, .copy-attachment-url').attr('data-clipboard-text', data.new_url);

							// Actualizar imágenes y miniaturas con cache-buster
							var cacheBustUrl = data.new_url + '?t=' + Date.now();
							$modal.find('.thumbnail img, .details-image img, img.details-image').attr('src', cacheBustUrl);
							$('.attachment[data-id="' + attachId + '"] img').attr('src', cacheBustUrl);
							$('.attachment[data-id="' + attachId + '"] .filename').text(data.filename);

							// Actualizar modelo de Backbone en wp.media si existe
							if (typeof wp !== 'undefined' && wp.media && wp.media.model && wp.media.model.Attachment) {
								var att = wp.media.model.Attachment.get(attachId);
								if (att) {
									att.set({
										filename: data.filename,
										url: data.new_url
									});
									if (data.title) att.set('title', data.title);
								}
							}

							// Si estamos en la pantalla clásica de edición (post.php?post=ID), volver a la Biblioteca de Medios
							if (window.location.href.indexOf('post.php') !== -1 && window.location.href.indexOf('action=edit') !== -1) {
								window.location.href = '<?php echo esc_js( admin_url( 'upload.php?wpat_renamed=' ) ); ?>' + encodeURIComponent(data.filename);
								return;
							}

							// Si estamos en la Biblioteca de Medios (upload.php), recargar fluidamente tras 500ms para refrescar cuadrícula completa
							if (window.location.href.indexOf('upload.php') !== -1) {
								setTimeout(function() {
									window.location.href = '<?php echo esc_js( admin_url( 'upload.php?wpat_renamed=' ) ); ?>' + encodeURIComponent(data.filename);
								}, 600);
							}
						} else {
							$feedback.html('<span style="color: #dc2626; font-weight: 700;">✕ ' + (res.data ? res.data.message : 'Error al renombrar.') + '</span>').fadeIn(200);
							alert(res.data ? res.data.message : 'Error al renombrar.');
						}
					},
					error: function() {
						$btn.prop('disabled', false).text(origText);
						$feedback.html('<span style="color: #dc2626; font-weight: 700;">✕ Error de conexión con el servidor.</span>').fadeIn(200);
						alert('Error de conexión al renombrar el archivo.');
					}
				});
			});

			// 2. Renombrado rápido desde la vista de lista de Medios
			$(document).on('click', '.wpat-quick-rename-trigger', function(e) {
				e.preventDefault();
				var $link = $(this);
				var attachId = $link.data('id');
				var currentName = $link.data('current');

				var proposedName = prompt('Introduce el nuevo nombre del archivo (sin extensión):', currentName);
				if (proposedName === null || !proposedName.trim() || proposedName.trim() === currentName) {
					return;
				}

				$link.text('Renombrando...');

				$.ajax({
					url: ajaxEndpoint,
					type: 'POST',
					data: {
						action: 'wpat_rename_attachment',
						nonce: mediaNonce,
						attachment_id: attachId,
						new_filename: proposedName.trim()
					},
					success: function(res) {
						if (res.success && res.data) {
							window.location.href = '<?php echo esc_js( admin_url( 'upload.php?wpat_renamed=' ) ); ?>' + encodeURIComponent(res.data.filename);
						} else {
							$link.text('Renombrar');
							alert(res.data ? res.data.message : 'Error al renombrar.');
						}
					},
					error: function() {
						$link.text('Renombrar');
						alert('Error de conexión al renombrar el archivo.');
					}
				});
			});

		})(window.jQuery);
		</script>
		<?php
	}
}

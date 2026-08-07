<?php
/**
 * Panel de Administración - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Admin {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Admin
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Admin
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );

		// Acciones AJAX para Snippets
		add_action( 'wp_ajax_wpat_save_snippet', array( $this, 'ajax_save_snippet' ) );
		add_action( 'wp_ajax_wpat_delete_snippet', array( $this, 'ajax_delete_snippet' ) );
		add_action( 'wp_ajax_wpat_toggle_snippet', array( $this, 'ajax_toggle_snippet' ) );
		add_action( 'wp_ajax_wpat_clone_snippet', array( $this, 'ajax_clone_snippet' ) );

		// Acciones AJAX para Módulos y Base de Datos
		add_action( 'wp_ajax_wpat_toggle_module', array( $this, 'ajax_toggle_module' ) );
		add_action( 'wp_ajax_wpat_cleanup_database', array( $this, 'ajax_cleanup_database' ) );
		add_action( 'wp_ajax_wpat_get_health_status', array( $this, 'ajax_get_health_status' ) );
		add_action( 'wp_ajax_wpat_scan_unused_images', array( $this, 'ajax_scan_unused_images' ) );
		add_action( 'wp_ajax_wpat_check_unused_images_batch', array( $this, 'ajax_check_unused_images_batch' ) );
		add_action( 'wp_ajax_wpat_delete_unused_images', array( $this, 'ajax_delete_unused_images' ) );

		add_action( 'wp_ajax_wpat_seo_get_pages_to_scan', array( $this, 'ajax_seo_get_pages_to_scan' ) );
		add_action( 'wp_ajax_wpat_seo_audit_page', array( $this, 'ajax_seo_audit_page' ) );
		add_action( 'wp_ajax_wpat_seo_get_posts_to_fill', array( $this, 'ajax_seo_get_posts_to_fill' ) );
		add_action( 'wp_ajax_wpat_seo_fill_posts_batch', array( $this, 'ajax_seo_fill_posts_batch' ) );
		add_action( 'wp_ajax_wpat_force_update_check', array( $this, 'ajax_force_update_check' ) );
	}

	/**
	 * Añade el menú del plugin a la administración de WordPress.
	 */
	public function add_admin_menu() {
		add_menu_page(
			'WP Agency Toolkit',
			'Agency Toolkit',
			'manage_options',
			'wp-agency-toolkit',
			array( $this, 'render_admin_page' ),
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" fill="currentColor"><path d="M512.1 191l-8.2 14.3c-3 5.3-9.4 7.5-15.1 5.4-11.8-4.4-22.6-10.7-32.1-18.6-4.6-3.8-5.8-10.5-2.8-15.7l8.2-14.3c-6.9-8-12.3-17.3-15.9-27.4h-16.5c-6 0-11.2-4.3-12.2-10.3-2-12-2.1-24.6 0-37.1 1-6 6.2-10.4 12.2-10.4h16.5c3.6-10.1 9-19.4 15.9-27.4l-8.2-14.3c-3-5.2-1.9-11.9 2.8-15.7 9.5-7.9 20.4-14.2 32.1-18.6 5.7-2.1 12.1.1 15.1 5.4l8.2 14.3c10.5-1.9 21.2-1.9 31.7 0L552 6.3c3-5.3 9.4-7.5 15.1-5.4 11.8 4.4 22.6 10.7 32.1 18.6 4.6 3.8 5.8 10.5 2.8 15.7l-8.2 14.3c-6.9 8-12.3 17.3-15.9 27.4h16.5c6 0 11.2 4.3 12.2 10.3 2 12 2.1 24.6 0 37.1-1 6-6.2 10.4-12.2 10.4h-16.5c-3.6 10.1-9 19.4-15.9 27.4l8.2 14.3c3 5.2 1.9 11.9-2.8 15.7-9.5 7.9-20.4 14.2-32.1 18.6-5.7 2.1-12.1-.1-15.1-5.4l-8.2-14.3c-10.4 1.9-21.2 1.9-31.7 0zm-10.5-58.8c38.5 29.6 82.4-14.3 52.8-52.8-38.5-29.7-82.4 14.3-52.8 52.8zM386.3 286.1l33.7 16.8c10.1 5.8 14.5 18.1 10.5 29.1-8.9 24.2-26.4 46.4-42.6 65.8-7.4 8.9-20.2 11.1-30.3 5.3l-29.1-16.8c-16 13.7-34.6 24.6-54.9 31.7v33.6c0 11.6-8.3 21.6-19.7 23.6-24.6 4.2-50.4 4.4-75.9 0-11.5-2-20-11.9-20-23.6V418c-20.3-7.2-38.9-18-54.9-31.7L74 403c-10 5.8-22.9 3.6-30.3-5.3-16.2-19.4-33.3-41.6-42.2-65.7-4-10.9.4-23.2 10.5-29.1l33.3-16.8c-3.9-20.9-3.9-42.4 0-63.4L12 205.8c-10.1-5.8-14.6-18.1-10.5-29 8.9-24.2 26-46.4 42.2-65.8 7.4-8.9 20.2-11.1 30.3-5.3l29.1 16.8c16-13.7 34.6-24.6 54.9-31.7V57.1c0-11.5 8.2-21.5 19.6-23.5 24.6-4.2 50.5-4.4 76-.1 11.5 2 20 11.9 20 23.6v33.6c20.3 7.2 38.9 18 54.9 31.7l29.1-16.8c10-5.8 22.9-3.6 30.3 5.3 16.2 19.4 33.2 41.6 42.1 65.8 4 10.9.1 23.2-10 29.1l-33.7 16.8c3.9 21 3.9 42.5 0 63.5zm-117.6 21.1c59.2-77-28.7-164.9-105.7-105.7-59.2 77 28.7 164.9 105.7 105.7zm243.4 182.7l-8.2 14.3c-3 5.3-9.4 7.5-15.1 5.4-11.8-4.4-22.6-10.7-32.1-18.6-4.6-3.8-5.8-10.5-2.8-15.7l8.2-14.3c-6.9-8-12.3-17.3-15.9-27.4h-16.5c-6 0-11.2-4.3-12.2-10.3-2-12-2.1-24.6 0-37.1 1-6 6.2-10.4 12.2-10.4h16.5c3.6-10.1 9-19.4 15.9-27.4l-8.2-14.3c-3-5.2-1.9-11.9 2.8-15.7 9.5-7.9 20.4-14.2 32.1-18.6 5.7-2.1 12.1.1 15.1 5.4l8.2 14.3c10.5-1.9 21.2-1.9 31.7 0l8.2-14.3c3-5.3 9.4-7.5 15.1-5.4 11.8 4.4 22.6 10.7 32.1 18.6 4.6 3.8 5.8 10.5 2.8 15.7l-8.2 14.3c-6.9 8-12.3 17.3-15.9 27.4h16.5c6 0 11.2 4.3 12.2 10.3 2 12 2.1 24.6 0 37.1-1 6-6.2 10.4-12.2 10.4h-16.5c-3.6 10.1-9 19.4-15.9 27.4l8.2 14.3c3 5.2 1.9 11.9-2.8 15.7-9.5 7.9-20.4 14.2-32.1 18.6-5.7 2.1-12.1-.1-15.1-5.4l-8.2-14.3c-10.4 1.9-21.2 1.9-31.7 0zM501.6 431c38.5 29.6 82.4-14.3 52.8-52.8-38.5-29.6-82.4 14.3-52.8 52.8z" /></svg>' ),
			80
		);
	}

	/**
	 * Encola los scripts y estilos necesarios para el panel de administración.
	 *
	 * @param string $hook Pestaña actual de la administración.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_wp-agency-toolkit' !== $hook ) {
			return;
		}

		// Encolar soporte nativo de WP para carga de medios y selector de color
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Estilos y scripts propios (usamos time() temporalmente para evitar cualquier caché del navegador o del servidor)
		wp_enqueue_style( 'wpat-admin-css', WPAT_URL . 'assets/css/wpat-admin.css', array(), time() );
		wp_enqueue_script( 'wpat-admin-js', WPAT_URL . 'assets/js/wpat-admin.js', array( 'jquery', 'wp-color-picker' ), time(), true );

		// Localizar kits instalados para el JS de administración
		require_once WPAT_PATH . 'includes/modules/class-wpat-envato-importer.php';
		$kits = WPAT_Envato_Importer::get_instance()->get_kits_with_plugin_status();
		wp_localize_script( 'wpat-admin-js', 'wpatEnvatoEditor', array(
			'kits' => $kits
		) );
	}

	/**
	 * Procesa y guarda los ajustes de forma segura.
	 */
	public function save_settings() {
		// E. Manejar exportación de un único fragmento de código (GET)
		if ( isset( $_GET['wpat_export_single_snippet'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wpat_export_single_snippet_action' ) ) {
				wp_die( esc_html__( 'Error de seguridad. Operación no permitida.', 'wp-agency-toolkit' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
			}

			$snippet_id = intval( $_GET['wpat_export_single_snippet'] );
			$snippets   = get_option( 'wpat_snippets', array() );

			if ( ! isset( $snippets[ $snippet_id ] ) ) {
				wp_die( 'Fragmento no encontrado.' );
			}

			$single_snippet = $snippets[ $snippet_id ];
			$filename       = 'wpat-snippet-' . sanitize_title( $single_snippet['name'] ) . '-' . date( 'Y-m-d' ) . '.json';

			$payload = array(
				'generator'   => 'WP Agency Toolkit',
				'version'     => WPAT_VERSION,
				'exported_at' => current_time( 'mysql' ),
				'data'        => array(),
				'snippets'    => array( $single_snippet ),
			);

			$json_data = wp_json_encode( $payload );

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		// F. Forzar comprobación de actualizaciones de GitHub (GET)
		if ( isset( $_GET['wpat_force_update_check'] ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
			}
			
			// Borrar caché de transients
			delete_transient( 'wpat_github_update_check' );
			delete_site_transient( 'update_plugins' );
			
			// Redirigir de vuelta al panel para ver los resultados actualizados
			wp_safe_redirect( admin_url( 'admin.php?page=wp-agency-toolkit' ) );
			exit;
		}

		// C. Manejar exportación de SNIPPETS SOLAMENTE
		if ( isset( $_POST['wpat_export_snippets_only_btn'] ) ) {
			if ( ! isset( $_POST['wpat_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wpat_settings_nonce'], 'wpat_save_settings_action' ) ) {
				wp_die( esc_html__( 'Error de seguridad. Operación no permitida.', 'wp-agency-toolkit' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
			}

			$snippets_data = get_option( 'wpat_snippets', array() );

			$payload = array(
				'generator'   => 'WP Agency Toolkit',
				'version'     => WPAT_VERSION,
				'exported_at' => current_time( 'mysql' ),
				'data'        => array(),
				'snippets'    => $snippets_data,
			);

			$json_data = wp_json_encode( $payload );
			$filename = 'wpat-snippets-' . date( 'Y-m-d-H-i' ) . '.json';

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		// D. Manejar importación de SNIPPETS SOLAMENTE
		if ( isset( $_POST['wpat_execute_import_snippets_only_btn'] ) ) {
			if ( ! isset( $_POST['wpat_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wpat_settings_nonce'], 'wpat_save_settings_action' ) ) {
				wp_die( esc_html__( 'Error de seguridad. Operación no permitida.', 'wp-agency-toolkit' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
			}

			if ( empty( $_FILES['wpat_import_snippets_only_file']['tmp_name'] ) ) {
				wp_die( 'No se ha subido ningún archivo para importar.' );
			}

			$uploaded_file = $_FILES['wpat_import_snippets_only_file']['tmp_name'];
			$json_content  = file_get_contents( $uploaded_file );
			$payload       = json_decode( $json_content, true );

			if ( ! is_array( $payload ) || ! isset( $payload['generator'] ) || 'WP Agency Toolkit' !== $payload['generator'] ) {
				wp_die( 'El archivo subido no es un formato de exportación de WP Agency Toolkit válido.' );
			}

			$snippets_imported = 0;

			if ( isset( $payload['snippets'] ) && is_array( $payload['snippets'] ) && ! empty( $payload['snippets'] ) ) {
				$local_snippets = get_option( 'wpat_snippets', array() );
				
				foreach ( $payload['snippets'] as $snip ) {
					$slug = isset( $snip['slug'] ) ? sanitize_key( $snip['slug'] ) : sanitize_title( $snip['name'] );
					
					// Buscar en los snippets locales si ya existe
					$found_key = false;
					foreach ( $local_snippets as $lk => $local_snip ) {
						$l_slug = isset( $local_snip['slug'] ) ? sanitize_key( $local_snip['slug'] ) : sanitize_title( $local_snip['name'] );
						if ( $l_slug === $slug ) {
							$found_key = $lk;
							break;
						}
					}

					$snip_entry = array(
						'name'        => sanitize_text_field( $snip['name'] ),
						'code'        => $snip['code'], 
						'description' => sanitize_textarea_field( $snip['description'] ),
						'active'      => isset( $snip['active'] ) ? sanitize_key( $snip['active'] ) : '0',
						'location'    => isset( $snip['location'] ) ? sanitize_key( $snip['location'] ) : 'admin',
						'slug'        => $slug,
					);

					if ( false !== $found_key ) {
						$local_snippets[ $found_key ] = $snip_entry;
					} else {
						$local_snippets[] = $snip_entry;
					}
					$snippets_imported++;
				}

				update_option( 'wpat_snippets', $local_snippets );
			}

			$results = array(
				'success'  => 0,
				'errors'   => 0,
				'snippets' => $snippets_imported,
			);

			set_transient( 'wpat_import_results', $results, 60 );

			$active_tab = isset( $_POST['wpat_active_tab'] ) ? sanitize_key( $_POST['wpat_active_tab'] ) : 'tab-performance';
			wp_safe_redirect( add_query_arg( array(
				'import-done' => 'true',
				'tab'         => $active_tab,
			), menu_page_url( 'wp-agency-toolkit', false ) ) );
			exit;
		}

		// A. Manejar exportación de contenidos JSON
		if ( isset( $_POST['wpat_export_contents_btn'] ) ) {
			if ( ! isset( $_POST['wpat_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wpat_settings_nonce'], 'wpat_save_settings_action' ) ) {
				wp_die( esc_html__( 'Error de seguridad. Operación no permitida.', 'wp-agency-toolkit' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
			}

			$export_all      = isset( $_POST['wpat_export_all'] ) && '1' === $_POST['wpat_export_all'];
			$export_media    = isset( $_POST['wpat_export_media'] ) && '1' === $_POST['wpat_export_media'];
			$export_seo      = isset( $_POST['wpat_export_seo'] ) && '1' === $_POST['wpat_export_seo'];
			$export_snippets = isset( $_POST['wpat_export_snippets'] ) && '1' === $_POST['wpat_export_snippets'];

			$post_types = array();
			if ( $export_all ) {
				$post_types = get_post_types( array( 'public' => true ), 'names' );
				if ( isset( $post_types['attachment'] ) ) {
					unset( $post_types['attachment'] );
				}
				if ( post_type_exists( 'jet-engine' ) ) {
					$post_types[] = 'jet-engine';
				}
				$post_types = array_values( $post_types );
			} else {
				$post_types = isset( $_POST['wpat_export_post_types'] ) ? map_deep( $_POST['wpat_export_post_types'], 'sanitize_key' ) : array();
			}

			if ( empty( $post_types ) && ! $export_snippets ) {
				wp_die( 'Por favor, selecciona al menos un elemento para exportar (contenidos o fragmentos de código).' );
			}

			$export_data = array();
			if ( ! empty( $post_types ) ) {
				// Consultar posts
				$query_args = array(
					'post_type'      => $post_types,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
				);
				$posts = get_posts( $query_args );

				foreach ( $posts as $p ) {
					$post_entry = array(
						'post_title'   => $p->post_title,
						'post_content' => $p->post_content,
						'post_excerpt' => $p->post_excerpt,
						'post_name'    => $p->post_name,
						'post_type'    => $p->post_type,
						'post_status'  => $p->post_status,
						'post_date'    => $p->post_date,
						'menu_order'   => $p->menu_order,
					);

					// Imagen destacada (featured image URL)
					if ( $export_media && has_post_thumbnail( $p->ID ) ) {
						$thumb_id = get_post_thumbnail_id( $p->ID );
						$thumb_url = wp_get_attachment_url( $thumb_id );
						if ( $thumb_url ) {
							$post_entry['featured_image_url'] = $thumb_url;
						}
					}

					// Metadatos SEO (si se solicita)
					if ( $export_seo ) {
						$seo_keys = array(
							'_wpat_seo_keyword',
							'_wpat_seo_title',
							'_wpat_seo_desc',
							'_wpat_seo_noindex',
							'_wpat_seo_cornerstone',
							'_wpat_seo_canonical',
							'_wpat_seo_og_title',
							'_wpat_seo_og_desc',
							'_wpat_seo_og_image',
						);
						$meta_entry = array();
						foreach ( $seo_keys as $key ) {
							$val = get_post_meta( $p->ID, $key, true );
							if ( '' !== $val ) {
								$meta_entry[ $key ] = $val;
							}
						}
						$post_entry['seo_meta'] = $meta_entry;
					}

					$export_data[] = $post_entry;
				}
			}

			$snippets_data = array();
			if ( $export_snippets ) {
				$snippets_data = get_option( 'wpat_snippets', array() );
			}

			$payload = array(
				'generator'   => 'WP Agency Toolkit',
				'version'     => WPAT_VERSION,
				'exported_at' => current_time( 'mysql' ),
				'data'        => $export_data,
				'snippets'    => $snippets_data,
			);

			$json_data = wp_json_encode( $payload );
			$filename = 'wpat-export-' . date( 'Y-m-d-H-i' ) . '.json';

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		// B. Manejar importación de contenidos JSON
		if ( isset( $_POST['wpat_import_contents_btn'] ) ) {
			if ( ! isset( $_POST['wpat_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wpat_settings_nonce'], 'wpat_save_settings_action' ) ) {
				wp_die( esc_html__( 'Error de seguridad. Operación no permitida.', 'wp-agency-toolkit' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
			}

			if ( empty( $_FILES['wpat_import_file']['tmp_name'] ) ) {
				wp_die( 'No se ha subido ningún archivo para importar.' );
			}

			$uploaded_file = $_FILES['wpat_import_file']['tmp_name'];
			$json_content  = file_get_contents( $uploaded_file );
			$payload       = json_decode( $json_content, true );

			if ( ! is_array( $payload ) || ! isset( $payload['generator'] ) || 'WP Agency Toolkit' !== $payload['generator'] ) {
				wp_die( 'El archivo subido no es un formato de exportación de WP Agency Toolkit válido.' );
			}

			$imported_count = 0;
			$errors_count   = 0;
			$snippets_imported = 0;

			// Importar contenidos de posts/pages/CPTs
			if ( isset( $payload['data'] ) && is_array( $payload['data'] ) && ! empty( $payload['data'] ) ) {
				// Requerir funciones de medios de WordPress para sideloading
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';

				foreach ( $payload['data'] as $entry ) {
					// Buscar si ya existe un post con el mismo tipo y slug (post_name) para actualizarlo o crear uno nuevo
					$existing = get_posts( array(
						'name'           => $entry['post_name'],
						'post_type'      => $entry['post_type'],
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'fields'         => 'ids',
					) );

					$post_data = array(
						'post_title'   => sanitize_text_field( $entry['post_title'] ),
						'post_content' => wp_kses_post( $entry['post_content'] ),
						'post_excerpt' => sanitize_textarea_field( $entry['post_excerpt'] ),
						'post_name'    => sanitize_title( $entry['post_name'] ),
						'post_type'    => sanitize_key( $entry['post_type'] ),
						'post_status'  => sanitize_key( $entry['post_status'] ),
						'menu_order'   => intval( $entry['menu_order'] ),
					);

					if ( ! empty( $existing ) ) {
						$post_data['ID'] = intval( $existing[0] );
						$post_id = wp_update_post( $post_data );
					} else {
						$post_id = wp_insert_post( $post_data );
					}

					if ( is_wp_error( $post_id ) || ! $post_id ) {
						$errors_count++;
						continue;
					}

					// Importar metadatos SEO
					if ( isset( $entry['seo_meta'] ) && is_array( $entry['seo_meta'] ) ) {
						foreach ( $entry['seo_meta'] as $meta_key => $meta_val ) {
							update_post_meta( $post_id, sanitize_key( $meta_key ), sanitize_text_field( $meta_val ) );
						}
					}

					// Descargar e importar imagen destacada si existe y no existe ya localmente
					if ( isset( $entry['featured_image_url'] ) && ! empty( $entry['featured_image_url'] ) ) {
						$filename_only = basename( $entry['featured_image_url'] );
						
						// Buscar en la biblioteca por nombre de archivo
						global $wpdb;
						$query = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s", '%' . $wpdb->esc_like( $filename_only ) );
						$local_attachment_id = $wpdb->get_var( $query );

						if ( ! $local_attachment_id ) {
							$desc = $entry['post_title'];
							$att_id = media_sideload_image( $entry['featured_image_url'], $post_id, $desc, 'id' );
							if ( ! is_wp_error( $att_id ) && $att_id ) {
								set_post_thumbnail( $post_id, $att_id );
							}
						} else {
							set_post_thumbnail( $post_id, $local_attachment_id );
						}
					}

					$imported_count++;
				}
			}

			// Importar fragmentos de código (snippets)
			if ( isset( $payload['snippets'] ) && is_array( $payload['snippets'] ) && ! empty( $payload['snippets'] ) ) {
				$local_snippets = get_option( 'wpat_snippets', array() );
				
				foreach ( $payload['snippets'] as $snip ) {
					$slug = isset( $snip['slug'] ) ? sanitize_key( $snip['slug'] ) : sanitize_title( $snip['name'] );
					
					// Buscar en los snippets locales si ya existe
					$found_key = false;
					foreach ( $local_snippets as $lk => $local_snip ) {
						$l_slug = isset( $local_snip['slug'] ) ? sanitize_key( $local_snip['slug'] ) : sanitize_title( $local_snip['name'] );
						if ( $l_slug === $slug ) {
							$found_key = $lk;
							break;
						}
					}

					$snip_entry = array(
						'name'        => sanitize_text_field( $snip['name'] ),
						'code'        => $snip['code'], 
						'description' => sanitize_textarea_field( $snip['description'] ),
						'active'      => isset( $snip['active'] ) ? sanitize_key( $snip['active'] ) : '0',
						'location'    => isset( $snip['location'] ) ? sanitize_key( $snip['location'] ) : 'admin',
						'slug'        => $slug,
					);

					if ( false !== $found_key ) {
						$local_snippets[ $found_key ] = $snip_entry;
					} else {
						$local_snippets[] = $snip_entry;
					}
					$snippets_imported++;
				}

				update_option( 'wpat_snippets', $local_snippets );
			}

			$results = array(
				'success'  => $imported_count,
				'errors'   => $errors_count,
				'snippets' => $snippets_imported,
			);

			set_transient( 'wpat_import_results', $results, 60 );

			$active_tab = isset( $_POST['wpat_active_tab'] ) ? sanitize_key( $_POST['wpat_active_tab'] ) : 'tab-tools';
			wp_safe_redirect( add_query_arg( array(
				'import-done' => 'true',
				'tab'         => $active_tab,
			), menu_page_url( 'wp-agency-toolkit', false ) ) );
		}

		// 1. Manejar acción rápida de permitir indexación
		if ( isset( $_POST['wpat_enable_indexing'] ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				update_option( 'blog_public', '1' );
				wp_safe_redirect( add_query_arg( array(
					'settings-updated' => 'true',
					'tab'              => isset( $_POST['wpat_active_tab'] ) ? sanitize_key( $_POST['wpat_active_tab'] ) : 'tab-security',
				), menu_page_url( 'wp-agency-toolkit', false ) ) );
				exit;
			}
		}

		// 2. Manejar asistente de configuración inicial
		if ( isset( $_POST['wpat_run_initial_setup'] ) ) {
			if ( ! isset( $_POST['wpat_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wpat_settings_nonce'], 'wpat_save_settings_action' ) ) {
				wp_die( esc_html__( 'Error de seguridad. Operación no permitida.', 'wp-agency-toolkit' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) );
			}

			$actions = isset( $_POST['wpat_init'] ) ? $_POST['wpat_init'] : array();
			
			require_once WPAT_PATH . 'includes/modules/class-wpat-initial-setup.php';
			$setup_results = WPAT_Initial_Setup::get_instance()->run( $actions );

			set_transient( 'wpat_initial_setup_results', $setup_results, 60 );

			$active_tab = isset( $_POST['wpat_active_tab'] ) ? sanitize_key( $_POST['wpat_active_tab'] ) : 'tab-initial-setup';
			wp_safe_redirect( add_query_arg( array(
				'initial-setup-done' => 'true',
				'tab'                => $active_tab,
			), menu_page_url( 'wp-agency-toolkit', false ) ) );
			exit;
		}

		if ( ! isset( $_POST['wpat_save_settings'] ) ) {
			return;
		}

		// Verificar Nonce de seguridad
		if ( ! isset( $_POST['wpat_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wpat_settings_nonce'], 'wpat_save_settings_action' ) ) {
			wp_die( esc_html__( 'Error de seguridad. Operación no permitida.', 'wp-agency-toolkit' ) );
		}

		// Comprobar privilegios
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para modificar estos ajustes.', 'wp-agency-toolkit' ) );
		}

		$current_settings = WPAT_Main::get_instance()->get_settings();
		$input_settings   = isset( $_POST['wpat_settings'] ) ? $_POST['wpat_settings'] : array();

		$new_settings = array();

		// 1. Sanitizar Módulos ON/OFF (1 o 0)
		$modules = array(
			'login-customizer',
			'hide-login',
			'ssl-fixer',
			'woo-dni',
			'woo-catalog',
			'woo-zoom',
			'duplicator',
			'snippets',
			'performance',
			'svg-support',
			'image-optimizer',
			'seo',
			'disable-comments',
			'security-hardening',
			'envato-importer',
			'smtp',
			'hide_admin_bar',
			'dashboard_cleaner',
			'bot-blocker',
			'integrations',
			'initial-setup',
		);

		foreach ( $modules as $module_id ) {
			$new_settings[ $module_id ] = isset( $input_settings[ $module_id ] ) && '1' === $input_settings[ $module_id ] ? '1' : '0';
		}

		// 2. Sanitizar Login Customizer / Marca Blanca
		$new_settings['login_style']             = isset( $input_settings['login_style'] ) && in_array( $input_settings['login_style'], array( 'default', 'modern' ), true ) ? $input_settings['login_style'] : 'default';
		$new_settings['login_logo']              = isset( $input_settings['login_logo'] ) ? esc_url_raw( $input_settings['login_logo'] ) : '';
		$new_settings['login_bg_image']          = isset( $input_settings['login_bg_image'] ) ? esc_url_raw( $input_settings['login_bg_image'] ) : '';
		$new_settings['login_bg_type']           = isset( $input_settings['login_bg_type'] ) && in_array( $input_settings['login_bg_type'], array( 'image', 'color' ), true ) ? $input_settings['login_bg_type'] : 'image';
		$new_settings['login_bg_color']          = isset( $input_settings['login_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['login_bg_color'] ) ? $input_settings['login_bg_color'] : '#f0f0f0';
		$new_settings['login_accent_color']      = isset( $input_settings['login_accent_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['login_accent_color'] ) ? $input_settings['login_accent_color'] : '#2563eb';
		$new_settings['login_hide_languages']    = isset( $input_settings['login_hide_languages'] ) && '1' === $input_settings['login_hide_languages'] ? '1' : '0';
		$new_settings['login_footer_text']       = isset( $input_settings['login_footer_text'] ) ? sanitize_text_field( $input_settings['login_footer_text'] ) : '';
		$new_settings['admin_footer_text']       = isset( $input_settings['admin_footer_text'] ) ? sanitize_text_field( $input_settings['admin_footer_text'] ) : '';
		$new_settings['hide_admin_bar']          = isset( $input_settings['hide_admin_bar'] ) && '1' === $input_settings['hide_admin_bar'] ? '1' : '0';
		$new_settings['dashboard_cleaner']       = isset( $input_settings['dashboard_cleaner'] ) && '1' === $input_settings['dashboard_cleaner'] ? '1' : '0';
		$new_settings['dashboard_welcome_title'] = isset( $input_settings['dashboard_welcome_title'] ) ? sanitize_text_field( $input_settings['dashboard_welcome_title'] ) : 'Soporte y Gestión';
		$new_settings['dashboard_welcome_text']  = isset( $input_settings['dashboard_welcome_text'] ) ? sanitize_textarea_field( $input_settings['dashboard_welcome_text'] ) : '';
		$new_settings['dashboard_support_email'] = isset( $input_settings['dashboard_support_email'] ) ? sanitize_email( $input_settings['dashboard_support_email'] ) : '';

		// Sanitizar visibilidad de tarjetas del Escritorio
		$dashboard_cards = array( 'seo', 'pages', 'posts', 'plugins', 'themes', 'users', 'db', 'tools', 'smtp', 'jet', 'woo', 'media' );
		foreach ( $dashboard_cards as $card_key ) {
			$opt_key = 'db_card_' . $card_key;
			$new_settings[ $opt_key ] = isset( $input_settings[ $opt_key ] ) && '1' === $input_settings[ $opt_key ] ? '1' : '0';
		}

		// Sanitizar Bloqueador de Bots
		$new_settings['bot_blocker']           = isset( $input_settings['bot_blocker'] ) && '1' === $input_settings['bot_blocker'] ? '1' : '0';
		$new_settings['bot_blocker_limit']     = isset( $input_settings['bot_blocker_limit'] ) ? max( 1, absint( $input_settings['bot_blocker_limit'] ) ) : 15;
		$new_settings['bot_blocker_timeframe'] = isset( $input_settings['bot_blocker_timeframe'] ) ? max( 10, absint( $input_settings['bot_blocker_timeframe'] ) ) : 300;
		$new_settings['bot_blocker_duration']  = isset( $input_settings['bot_blocker_duration'] ) ? max( 1, absint( $input_settings['bot_blocker_duration'] ) ) : 24;
		
		$whitelist_raw = isset( $input_settings['bot_blocker_whitelist'] ) ? sanitize_text_field( $input_settings['bot_blocker_whitelist'] ) : '';
		$ips = array_filter( array_map( 'trim', explode( ',', $whitelist_raw ) ) );
		$valid_ips = array();
		foreach ( $ips as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				$valid_ips[] = $ip;
			}
		}
		$new_settings['bot_blocker_whitelist'] = implode( ', ', $valid_ips );

		// 3. Sanitizar Hide Login
		$new_settings['hide_login_slug']           = isset( $input_settings['hide_login_slug'] ) ? sanitize_title( $input_settings['hide_login_slug'] ) : 'acceso';
		$new_settings['hide_login_redirect']       = isset( $input_settings['hide_login_redirect'] ) && in_array( $input_settings['hide_login_redirect'], array( 'home', '404' ), true ) ? $input_settings['hide_login_redirect'] : 'home';
		$new_settings['hide_login_limit_attempts'] = isset( $input_settings['hide_login_limit_attempts'] ) && '1' === $input_settings['hide_login_limit_attempts'] ? '1' : '0';
		$new_settings['hide_login_max_attempts']   = isset( $input_settings['hide_login_max_attempts'] ) ? absint( $input_settings['hide_login_max_attempts'] ) : 3;
		$new_settings['hide_login_lockout']        = isset( $input_settings['hide_login_lockout'] ) ? absint( $input_settings['hide_login_lockout'] ) : 120;
		$new_settings['hide_login_captcha']        = isset( $input_settings['hide_login_captcha'] ) && '1' === $input_settings['hide_login_captcha'] ? '1' : '0';

		// Sanitizar Deshabilitar Comentarios
		$new_settings['disable_comments_global'] = isset( $input_settings['disable_comments_global'] ) && '1' === $input_settings['disable_comments_global'] ? '1' : '0';
		$new_settings['disable_comments_posts']  = isset( $input_settings['disable_comments_posts'] ) && '1' === $input_settings['disable_comments_posts'] ? '1' : '0';
		$new_settings['disable_comments_pages']  = isset( $input_settings['disable_comments_pages'] ) && '1' === $input_settings['disable_comments_pages'] ? '1' : '0';
		$new_settings['disable_comments_media']  = isset( $input_settings['disable_comments_media'] ) && '1' === $input_settings['disable_comments_media'] ? '1' : '0';

		// 4. Sanitizar WooCommerce Catalog
		$new_settings['woo_catalog_hide_price']  = isset( $input_settings['woo_catalog_hide_price'] ) && '1' === $input_settings['woo_catalog_hide_price'] ? '1' : '0';
		$new_settings['woo_catalog_price_text']  = isset( $input_settings['woo_catalog_price_text'] ) ? sanitize_text_field( $input_settings['woo_catalog_price_text'] ) : '';
		$new_settings['woo_catalog_hide_cart']   = isset( $input_settings['woo_catalog_hide_cart'] ) && '1' === $input_settings['woo_catalog_hide_cart'] ? '1' : '0';
		$new_settings['woo_catalog_wa_enable']   = isset( $input_settings['woo_catalog_wa_enable'] ) && '1' === $input_settings['woo_catalog_wa_enable'] ? '1' : '0';
		$new_settings['woo_catalog_wa_phone']    = isset( $input_settings['woo_catalog_wa_phone'] ) ? sanitize_text_field( $input_settings['woo_catalog_wa_phone'] ) : '';
		$new_settings['woo_catalog_wa_message']  = isset( $input_settings['woo_catalog_wa_message'] ) ? sanitize_textarea_field( $input_settings['woo_catalog_wa_message'] ) : '';
		$new_settings['woo_catalog_form_enable'] = isset( $input_settings['woo_catalog_form_enable'] ) && '1' === $input_settings['woo_catalog_form_enable'] ? '1' : '0';
		$new_settings['woo_catalog_form_email']  = isset( $input_settings['woo_catalog_form_email'] ) ? sanitize_email( $input_settings['woo_catalog_form_email'] ) : '';

		// 5. Sanitizar WooCommerce Gallery Zoom
		$new_settings['woo_zoom_disable_zoom']     = isset( $input_settings['woo_zoom_disable_zoom'] ) && '1' === $input_settings['woo_zoom_disable_zoom'] ? '1' : '0';
		$new_settings['woo_zoom_disable_lightbox'] = isset( $input_settings['woo_zoom_disable_lightbox'] ) && '1' === $input_settings['woo_zoom_disable_lightbox'] ? '1' : '0';
		$new_settings['woo_zoom_disable_slider']   = isset( $input_settings['woo_zoom_disable_slider'] ) && '1' === $input_settings['woo_zoom_disable_slider'] ? '1' : '0';

		// 6. Sanitizar Fortalecimiento de Seguridad
		$new_settings['sec_disable_file_edit']    = isset( $input_settings['sec_disable_file_edit'] ) && '1' === $input_settings['sec_disable_file_edit'] ? '1' : '0';
		$new_settings['sec_block_uploads_php']    = isset( $input_settings['sec_block_uploads_php'] ) && '1' === $input_settings['sec_block_uploads_php'] ? '1' : '0';
		$new_settings['sec_hide_wp_version']      = isset( $input_settings['sec_hide_wp_version'] ) && '1' === $input_settings['sec_hide_wp_version'] ? '1' : '0';
		$new_settings['sec_generic_login_errors'] = isset( $input_settings['sec_generic_login_errors'] ) && '1' === $input_settings['sec_generic_login_errors'] ? '1' : '0';
		$new_settings['sec_disable_indexes']      = isset( $input_settings['sec_disable_indexes'] ) && '1' === $input_settings['sec_disable_indexes'] ? '1' : '0';
		$new_settings['sec_disable_user_enum']    = isset( $input_settings['sec_disable_user_enum'] ) && '1' === $input_settings['sec_disable_user_enum'] ? '1' : '0';
		$new_settings['sec_disable_xmlrpc']       = isset( $input_settings['sec_disable_xmlrpc'] ) && '1' === $input_settings['sec_disable_xmlrpc'] ? '1' : '0';
		$new_settings['sec_block_admin_user']     = isset( $input_settings['sec_block_admin_user'] ) && '1' === $input_settings['sec_block_admin_user'] ? '1' : '0';

		// Sanitizar redirección SSL
		$new_settings['ssl_redirect_method'] = isset( $input_settings['ssl_redirect_method'] ) && in_array( $input_settings['ssl_redirect_method'], array( 'php', 'htaccess' ), true ) ? $input_settings['ssl_redirect_method'] : 'php';

		// 7. Sanitizar SMTP
		$new_settings['smtp']             = isset( $input_settings['smtp'] ) && '1' === $input_settings['smtp'] ? '1' : '0';
		$new_settings['smtp_host']        = isset( $input_settings['smtp_host'] ) ? sanitize_text_field( $input_settings['smtp_host'] ) : '';
		$new_settings['smtp_port']        = isset( $input_settings['smtp_port'] ) ? sanitize_text_field( $input_settings['smtp_port'] ) : '25';
		$new_settings['smtp_secure']      = isset( $input_settings['smtp_secure'] ) && in_array( $input_settings['smtp_secure'], array( 'none', 'ssl', 'tls' ), true ) ? $input_settings['smtp_secure'] : 'none';
		$new_settings['smtp_insecure']    = isset( $input_settings['smtp_insecure'] ) && '1' === $input_settings['smtp_insecure'] ? '1' : '0';
		$new_settings['smtp_auth']        = isset( $input_settings['smtp_auth'] ) && '1' === $input_settings['smtp_auth'] ? '1' : '0';
		$new_settings['smtp_username']    = isset( $input_settings['smtp_username'] ) ? sanitize_text_field( $input_settings['smtp_username'] ) : '';
		$new_settings['smtp_password']    = isset( $input_settings['smtp_password'] ) ? sanitize_text_field( $input_settings['smtp_password'] ) : '';
		$new_settings['smtp_from_email']  = isset( $input_settings['smtp_from_email'] ) ? sanitize_email( $input_settings['smtp_from_email'] ) : '';
		$new_settings['smtp_from_name']   = isset( $input_settings['smtp_from_name'] ) ? sanitize_text_field( $input_settings['smtp_from_name'] ) : '';

		// 8. Sanitizar Integraciones
		$new_settings['integrations']                 = isset( $input_settings['integrations'] ) && '1' === $input_settings['integrations'] ? '1' : '0';
		
		$gsc_raw = isset( $input_settings['google_search_console_code'] ) ? trim( $input_settings['google_search_console_code'] ) : '';
		if ( ! empty( $gsc_raw ) ) {
			if ( preg_match( '/content=["\']([^"\']+)["\']/i', $gsc_raw, $matches ) ) {
				$gsc_raw = $matches[1];
			}
			$new_settings['google_search_console_code'] = sanitize_text_field( $gsc_raw );
		} else {
			$new_settings['google_search_console_code'] = '';
		}
		$new_settings['google_analytics_id']          = isset( $input_settings['google_analytics_id'] ) ? sanitize_text_field( $input_settings['google_analytics_id'] ) : '';

		// Guardar en la base de datos
		update_option( 'wpat_settings', $new_settings );

		// Actualizar reglas del archivo .htaccess para SSL
		require_once WPAT_PATH . 'includes/modules/class-wpat-ssl-fixer.php';
		$ssl_active = isset( $new_settings['ssl-fixer'] ) && '1' === $new_settings['ssl-fixer'];
		$method_htaccess = 'htaccess' === $new_settings['ssl_redirect_method'];
		WPAT_SSL_Fixer::update_htaccess_rules( $ssl_active && $method_htaccess );

		// Redirigir para mostrar mensaje y evitar reenvíos de formulario
		$active_tab = isset( $_POST['wpat_active_tab'] ) ? sanitize_key( $_POST['wpat_active_tab'] ) : 'tab-security';
		wp_safe_redirect( add_query_arg( array(
			'settings-updated' => 'true',
			'tab'              => $active_tab,
		), menu_page_url( 'wp-agency-toolkit', false ) ) );
		exit;
	}

	/**
	 * Guarda o edita un fragmento de código vía AJAX.
	 */
	public function ajax_save_snippet() {
		check_ajax_referer( 'wpat_snippet_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$snippets = get_option( 'wpat_snippets', array() );

		$id = isset( $_POST['snippet_id'] ) ? sanitize_title( $_POST['snippet_id'] ) : '';
		if ( empty( $id ) ) {
			$id = uniqid( 'sn_' );
		}

		$name = isset( $_POST['snippet_name'] ) ? sanitize_text_field( $_POST['snippet_name'] ) : 'Fragmento sin nombre';
		$type = isset( $_POST['snippet_type'] ) && in_array( $_POST['snippet_type'], array( 'php', 'css', 'js' ), true ) ? $_POST['snippet_type'] : 'php';
		
		$code = '';
		if ( current_user_can( 'unfiltered_html' ) ) {
			$code = isset( $_POST['snippet_code'] ) ? wp_unslash( $_POST['snippet_code'] ) : '';
		} else {
			if ( isset( $snippets[ $id ] ) ) {
				$code = $snippets[ $id ]['code'];
			}
		}

		$active = isset( $_POST['snippet_active'] ) && '1' === $_POST['snippet_active'] ? '1' : '0';

		$snippets[ $id ] = array(
			'id'     => $id,
			'name'   => $name,
			'type'   => $type,
			'code'   => $code,
			'active' => $active,
		);

		update_option( 'wpat_snippets', $snippets );

		ob_start();
		$this->render_snippets_table_rows( $snippets );
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'    => $html,
			'message' => 'Fragmento guardado correctamente.'
		) );
	}

	/**
	 * Elimina un fragmento de código vía AJAX.
	 */
	public function ajax_delete_snippet() {
		check_ajax_referer( 'wpat_snippet_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$id = isset( $_POST['snippet_id'] ) ? sanitize_title( $_POST['snippet_id'] ) : '';
		if ( ! empty( $id ) ) {
			$snippets = get_option( 'wpat_snippets', array() );
			if ( isset( $snippets[ $id ] ) ) {
				unset( $snippets[ $id ] );
				update_option( 'wpat_snippets', $snippets );
			}
		}

		ob_start();
		$this->render_snippets_table_rows( get_option( 'wpat_snippets', array() ) );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Clona/Duplica un fragmento de código vía AJAX.
	 */
	public function ajax_clone_snippet() {
		check_ajax_referer( 'wpat_snippet_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$id = isset( $_POST['snippet_id'] ) ? sanitize_title( $_POST['snippet_id'] ) : '';
		if ( ! empty( $id ) ) {
			$snippets = get_option( 'wpat_snippets', array() );
			if ( isset( $snippets[ $id ] ) ) {
				$new_id = uniqid( 'sn_' );
				$snippets[ $new_id ] = $snippets[ $id ];
				$snippets[ $new_id ]['id'] = $new_id;
				$snippets[ $new_id ]['name'] .= ' (Copia)';
				$snippets[ $new_id ]['active'] = '0'; // Se clona como inactivo por seguridad
				update_option( 'wpat_snippets', $snippets );
			}
		}

		ob_start();
		$this->render_snippets_table_rows( get_option( 'wpat_snippets', array() ) );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Activa/Desactiva un fragmento de código vía AJAX.
	 */
	public function ajax_toggle_snippet() {
		check_ajax_referer( 'wpat_snippet_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$id = isset( $_POST['snippet_id'] ) ? sanitize_title( $_POST['snippet_id'] ) : '';
		if ( ! empty( $id ) ) {
			$snippets = get_option( 'wpat_snippets', array() );
			if ( isset( $snippets[ $id ] ) ) {
				$snippets[ $id ]['active'] = ( $snippets[ $id ]['active'] === '1' ) ? '0' : '1';
				update_option( 'wpat_snippets', $snippets );
			}
		}

		ob_start();
		$this->render_snippets_table_rows( get_option( 'wpat_snippets', array() ) );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Helper para renderizar las filas de la tabla de fragmentos.
	 *
	 * @param array $snippets Lista de fragmentos.
	 */
	public function render_snippets_table_rows( $snippets ) {
		if ( empty( $snippets ) ) {
			?>
			<tr>
				<td colspan="4" class="description" style="background: #f8fafc; border: 1px solid #dcdcde; padding: 20px; border-radius: 6px; text-align: center; margin: 0;">
					No tienes fragmentos creados todavía. ¡Crea uno haciendo clic en "Añadir Nuevo"!
				</td>
			</tr>
			<?php
			return;
		}

		foreach ( $snippets as $id => $item ) {
			$is_active = ( isset( $item['active'] ) && '1' === $item['active'] );
			?>
			<tr data-id="<?php echo esc_attr( $id ); ?>" data-name="<?php echo esc_attr( $item['name'] ); ?>" data-type="<?php echo esc_attr( $item['type'] ); ?>" data-active="<?php echo esc_attr( $item['active'] ); ?>">
				<td style="display:none;" class="wpat-snippet-raw-code"><?php echo esc_textarea( $item['code'] ); ?></td>
				<td style="padding: 10px; vertical-align: middle;">
					<span class="wpat-badge wpat-snippet-toggle-badge" style="background: <?php echo $is_active ? 'var(--wpat-success)' : '#646970'; ?>; display: inline-block; cursor: pointer; text-align: center; min-width: 55px; font-size:10px; padding: 2px 5px; border-radius: 4px; color:#fff; font-weight:bold;">
						<?php echo $is_active ? 'Activo' : 'Inactivo'; ?>
					</span>
				</td>
				<td style="padding: 10px; vertical-align: middle;">
					<strong><a href="#" class="wpat-snippet-edit-link" style="text-decoration:none; color: var(--wpat-text);"><?php echo esc_html( $item['name'] ); ?></a></strong>
				</td>
				<td style="padding: 10px; vertical-align: middle;">
					<span style="text-transform: uppercase; font-size: 10px; font-weight: 700; background: #e2e8f0; color: #475569; padding: 3px 6px; border-radius: 4px; display: inline-block;">
						<?php echo esc_html( $item['type'] ); ?>
					</span>
				</td>
				<td style="text-align: right; padding: 10px; vertical-align: middle;">
					<button type="button" class="button button-small wpat-snippet-edit-btn" title="Editar" style="padding: 3px 5px; min-width: 25px;"><span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span></button>
					<button type="button" class="button button-small wpat-snippet-clone-btn" title="Clonar" style="padding: 3px 5px; min-width: 25px; margin-left: 2px;"><span class="dashicons dashicons-admin-page" style="font-size:14px; width:14px; height:14px; margin-top:2px;"></span></button>
					<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=wp-agency-toolkit&wpat_export_single_snippet=' . $id ), 'wpat_export_single_snippet_action' ); ?>" class="button button-small wpat-snippet-export-single-btn" title="Exportar este fragmento" style="padding: 3px 5px; min-width: 25px; margin-left: 2px; display: inline-block; line-height: 20px; height: 26px; vertical-align: top;"><span class="dashicons dashicons-download" style="font-size:14px; width:14px; height:14px; margin-top:3px; color:#2563eb;"></span></a>
					<button type="button" class="button button-small button-link-delete wpat-snippet-delete-btn" title="Eliminar" style="padding: 3px 5px; min-width: 25px; margin-left: 2px;"><span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; margin-top:2px; color:#ea580c;"></span></button>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Guarda de forma aislada e instantánea el estado de un módulo general vía AJAX.
	 */
	public function ajax_toggle_module() {
		check_ajax_referer( 'wpat_save_settings_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$module_id = isset( $_POST['module_id'] ) ? sanitize_key( $_POST['module_id'] ) : '';
		$state     = isset( $_POST['state'] ) && '1' === $_POST['state'] ? '1' : '0';

		$modules = array(
			'login-customizer',
			'hide-login',
			'ssl-fixer',
			'woo-dni',
			'woo-catalog',
			'woo-zoom',
			'duplicator',
			'snippets',
			'performance',
			'svg-support',
			'image-optimizer',
			'seo',
			'disable-comments',
			'security-hardening',
			'envato-importer',
			'smtp',
			'hide_admin_bar',
			'dashboard_cleaner',
			'bot-blocker',
			'integrations',
			'initial-setup',
		);

		if ( ! in_array( $module_id, $modules, true ) ) {
			wp_send_json_error( array( 'message' => 'Módulo no válido.' ) );
		}

		$settings = get_option( 'wpat_settings', array() );
		$settings[ $module_id ] = $state;
		update_option( 'wpat_settings', $settings );

		wp_send_json_success( array( 'message' => 'Módulo actualizado correctamente.' ) );
	}

	/**
	 * Obtiene el conteo de elementos sobrantes de la Base de Datos para limpiar.
	 *
	 * @return array Estadísticas de elementos huérfanos/basura.
	 */
	public function get_db_cleanup_stats() {
		global $wpdb;

		// 1. Revisiones de entradas
		$revisions = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

		// 2. Borradores automáticos
		$auto_drafts = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );

		// 3. Entradas/Páginas en la papelera
		$trash_posts = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );

		// 4. Comentarios en SPAM y Papelera
		$trash_comments = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam' OR comment_approved = 'trash'" );

		// 5. Transients expirados
		$now = time();
		$expired_transients = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
			'_transient_timeout_%',
			$now
		) );

		// 6. Metadatos huérfanos
		$orphaned_postmeta = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );

		return array(
			'revisions'          => (int) $revisions,
			'auto_drafts'        => (int) $auto_drafts,
			'trash_posts'        => (int) $trash_posts,
			'trash_comments'     => (int) $trash_comments,
			'expired_transients' => (int) $expired_transients,
			'orphaned_postmeta'  => (int) $orphaned_postmeta,
		);
	}

	/**
	 * Ejecuta consultas SQL de limpieza sobre la base de datos vía AJAX.
	 */
	public function ajax_cleanup_database() {
		check_ajax_referer( 'wpat_cleanup_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		global $wpdb;
		$type = isset( $_POST['cleanup_type'] ) ? sanitize_key( $_POST['cleanup_type'] ) : '';
		$cleared = 0;

		switch ( $type ) {
			case 'revisions':
				$cleared = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				break;
			case 'auto_drafts':
				$cleared = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
				break;
			case 'trash_posts':
				$cleared = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );
				break;
			case 'trash_comments':
				$cleared = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam' OR comment_approved = 'trash'" );
				break;
			case 'expired_transients':
				$now = time();
				$transients = $wpdb->get_col( $wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
					'_transient_timeout_%',
					$now
				) );
				foreach ( $transients as $transient_timeout ) {
					$transient_name = str_replace( '_transient_timeout_', '', $transient_timeout );
					delete_transient( $transient_name );
					$cleared++;
				}
				break;
			case 'orphaned_postmeta':
				$cleared = $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );
				break;
			case 'all':
				// Revisiones
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				// Borradores automáticos
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
				// Papelera posts
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );
				// Comentarios spam/trash
				$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam' OR comment_approved = 'trash'" );
				// Transients expirados
				$now = time();
				$transients = $wpdb->get_col( $wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
					'_transient_timeout_%',
					$now
				) );
				foreach ( $transients as $transient_timeout ) {
					$transient_name = str_replace( '_transient_timeout_', '', $transient_timeout );
					delete_transient( $transient_name );
				}
				// Meta huérfanos
				$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );
				$cleared = 'all';
				break;
			default:
				wp_send_json_error( array( 'message' => 'Tipo de limpieza no válido.' ) );
		}

		$stats = $this->get_db_cleanup_stats();

		wp_send_json_success( array(
			'cleared' => $cleared,
			'stats'   => $stats,
			'message' => 'Mantenimiento ejecutado correctamente.'
		) );
	}

	/**
	 * AJAX: Escanea todos los IDs de adjuntos para el limpiador de imágenes huérfanas.
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
	 * AJAX: Comprueba si un lote de IDs de adjunto está en uso y devuelve los huérfanos.
	 */
	public function ajax_check_unused_images_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
		if ( empty( $ids ) ) {
			wp_send_json_success( array( 'unused' => array() ) );
		}

		$unused = array();

		foreach ( $ids as $id ) {
			if ( ! $this->is_attachment_in_use( $id ) ) {
				$file_path = get_attached_file( $id );
				$size = '0 KB';
				if ( $file_path && file_exists( $file_path ) ) {
					$size = size_format( filesize( $file_path ) );
				}
				
				$thumb_url = wp_get_attachment_image_src( $id, 'thumbnail' );
				$unused[] = array(
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

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => 'No se especificaron imágenes para eliminar.' ) );
		}

		$deleted_count = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_attachment( $id, true ) ) {
				$deleted_count++;
			}
		}

		wp_send_json_success( array( 'deleted' => $deleted_count ) );
	}

	/**
	 * Comprueba si un adjunto específico está en uso referenciado en la base de datos de WordPress.
	 *
	 * @param int $attachment_id ID del adjunto.
	 * @return bool
	 */
	public function is_attachment_in_use( $attachment_id ) {
		global $wpdb;

		// 1. Imagen destacada (Featured image)
		$is_featured = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s",
			$attachment_id
		) );
		if ( $is_featured > 0 ) {
			return true;
		}

		// 2. Galería de WooCommerce
		$is_in_gallery = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND (meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)",
			$attachment_id,
			$attachment_id . ',%',
			'%,' . $attachment_id,
			'%,' . $attachment_id . ',%'
		) );
		if ( $is_in_gallery > 0 ) {
			return true;
		}

		// Obtener nombre del archivo físico
		$file_path = get_attached_file( $attachment_id );
		if ( ! empty( $file_path ) ) {
			$filename = basename( $file_path );
			
			// 3. Contenido de posts (post_content)
			$filename_like = '%' . $wpdb->esc_like( $filename ) . '%';
			$id_like = '%wp-image-' . $attachment_id . '%';
			
			$is_in_content = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND (post_content LIKE %s OR post_content LIKE %s)",
				$filename_like,
				$id_like
			) );
			if ( $is_in_content > 0 ) {
				return true;
			}

			// 4. Datos de Elementor (_elementor_data)
			$is_in_elementor = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE %s",
				$filename_like
			) );
			if ( $is_in_elementor > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Callback AJAX para obtener el estado de salud y base de datos actualizado.
	 */
	public function ajax_get_health_status() {
		check_ajax_referer( 'wpat_cleanup_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		ob_start();
		$this->render_health_tab_content();
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Renderiza el contenido interno de la pestaña de Salud & Base de Datos.
	 */
	public function render_health_tab_content() {
		$stats = $this->get_db_cleanup_stats();
		?>
		<div class="wpat-health-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
			
			<!-- PHP Info Card -->
			<div class="wpat-module-card" style="margin: 0; padding: 20px;">
				<h3 style="margin-top:0; font-size:15px; font-weight:600;"><span class="dashicons dashicons-dashboard" style="vertical-align: middle;"></span> Servidor & PHP</h3>
				<table class="wpat-health-table" style="width:100%; border-collapse:collapse; margin-top:15px; font-size:13px;">
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Versión de PHP</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							$php_ver = PHP_VERSION;
							$class = 'wpat-badge';
							$bg = 'var(--wpat-success)';
							if ( version_compare( $php_ver, '7.4.0', '<' ) ) {
								$bg = '#ea580c';
							} elseif ( version_compare( $php_ver, '8.1.0', '<' ) ) {
								$bg = '#eab308';
							}
							echo '<span class="' . $class . '" style="background:' . $bg . '; color:#fff; font-size:11px; padding:2px 6px; border-radius:4px; font-weight:bold;">' . esc_html( $php_ver ) . '</span>';
							?>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Límite Memoria PHP</td>
						<td style="padding:8px 0; text-align:right;"><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">WP Límite Memoria</td>
						<td style="padding:8px 0; text-align:right;"><?php echo esc_html( WP_MEMORY_LIMIT ); ?></td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Uso de Memoria actual</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							$usage = memory_get_usage( true );
							echo esc_html( size_format( $usage ) );
							?>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Límite de Ejecución</td>
						<td style="padding:8px 0; text-align:right;"><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?>s</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Subida Máxima Archivo</td>
						<td style="padding:8px 0; text-align:right;"><?php echo esc_html( ini_get( 'upload_max_filesize' ) ); ?></td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Post Límite Máximo</td>
						<td style="padding:8px 0; text-align:right;"><?php echo esc_html( ini_get( 'post_max_size' ) ); ?></td>
					</tr>
				</table>
			</div>

			<!-- PHP Extensions Card -->
			<div class="wpat-module-card" style="margin: 0; padding: 20px;">
				<h3 style="margin-top:0; font-size:15px; font-weight:600;"><span class="dashicons dashicons-admin-plugins" style="vertical-align: middle;"></span> Extensiones & Entorno</h3>
				<table class="wpat-health-table" style="width:100%; border-collapse:collapse; margin-top:15px; font-size:13px;">
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Soporte WebP (GD)</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							$gd_info = function_exists( 'gd_info' ) ? gd_info() : array();
							$webp_gd = isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'] ? '1' : '0';
							echo $webp_gd === '1' ? '<span style="color:var(--wpat-success); font-weight:bold;">✔ Activo</span>' : '<span style="color:#ea580c; font-weight:bold;">✘ Inactivo</span>';
							?>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Extensión PHP Imagick</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							$imagick_loaded = extension_loaded( 'imagick' ) || class_exists( 'Imagick' );
							echo $imagick_loaded ? '<span style="color:var(--wpat-success); font-weight:bold;">✔ Activa</span>' : '<span style="color:#eab308; font-weight:bold;">✘ Inactiva (Opcional)</span>';
							?>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Soporte WebP (Imagick)</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							$webp_imagick = false;
							if ( class_exists( 'Imagick' ) ) {
								$formats = Imagick::queryFormats();
								if ( in_array( 'WEBP', $formats, true ) ) {
									$webp_imagick = true;
								}
							}
							echo $webp_imagick ? '<span style="color:var(--wpat-success); font-weight:bold;">✔ Activo</span>' : '<span style="color:#eab308; font-weight:bold;">✘ Inactivo (Opcional)</span>';
							?>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">ZipArchive (Backups)</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							echo class_exists( 'ZipArchive' ) ? '<span style="color:var(--wpat-success); font-weight:bold;">✔ Disponible</span>' : '<span style="color:#ea580c; font-weight:bold;">✘ No disponible</span>';
							?>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Servidor Web</td>
						<td style="padding:8px 0; text-align:right; font-size:11px; color:#475569; word-break:break-all;">
							<?php echo esc_html( isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Desconocido' ); ?>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Protocolo seguro HTTPS</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							echo is_ssl() ? '<span style="color:var(--wpat-success); font-weight:bold;">✔ Forzado (HTTPS)</span>' : '<span style="color:#ea580c; font-weight:bold;">✘ Inseguro (HTTP)</span>';
							?>
						</td>
					</tr>
				</table>
			</div>

		</div>

		<!-- Database Optimization Card -->
		<div class="wpat-module-card" style="padding: 20px;">
			<div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--wpat-border); padding-bottom: 15px; margin-bottom: 15px;">
				<h3 style="margin:0; font-size:15px; font-weight:600;"><span class="dashicons dashicons-database" style="vertical-align: middle;"></span> Limpiador de Base de Datos</h3>
				<button type="button" class="button button-primary" id="wpat_db_clean_all_btn">
					<span class="dashicons dashicons-admin-tools" style="vertical-align: middle; font-size:16px; width:16px; height:16px; margin-right:5px;"></span> Limpiar y Optimizar Todo
				</button>
			</div>
			
			<p class="description" style="margin-bottom:20px;">Elimina registros redundantes, transitorios huérfanos y datos residuales que ralentizan las consultas de tu base de datos de WordPress.</p>
			
			<table class="wp-list-table widefat fixed striped" style="box-shadow:none; border: 1px solid #dcdcde; border-radius:6px; overflow:hidden;">
				<thead>
					<tr>
						<th style="font-weight:700; padding:10px;">Tipo de Elemento / Tarea de Limpieza</th>
						<th style="width:120px; font-weight:700; text-align:center; padding:10px;">Elementos</th>
						<th style="width:130px; font-weight:700; text-align:right; padding:10px;">Acciones</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Revisiones obsoletas</strong>
							<p class="description" style="margin:2px 0 0 0;">Copias de seguridad automáticas previas de tus entradas y páginas.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px;" class="wpat-db-counter" data-type="revisions">
							<?php echo esc_html( $stats['revisions'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="revisions" <?php disabled( $stats['revisions'], 0 ); ?>>Limpiar</button>
						</td>
					</tr>
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Borradores automáticos</strong>
							<p class="description" style="margin:2px 0 0 0;">Borradores temporales huérfanos creados de forma automática al editar.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px;" class="wpat-db-counter" data-type="auto_drafts">
							<?php echo esc_html( $stats['auto_drafts'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="auto_drafts" <?php disabled( $stats['auto_drafts'], 0 ); ?>>Limpiar</button>
						</td>
					</tr>
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Páginas y Entradas en la Papelera</strong>
							<p class="description" style="margin:2px 0 0 0;">Contenido eliminado que aún está guardado en la papelera.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px;" class="wpat-db-counter" data-type="trash_posts">
							<?php echo esc_html( $stats['trash_posts'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="trash_posts" <?php disabled( $stats['trash_posts'], 0 ); ?>>Limpiar</button>
						</td>
					</tr>
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Comentarios de Spam y Papelera</strong>
							<p class="description" style="margin:2px 0 0 0;">Mensajes molestos marcados como spam o borrados.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px;" class="wpat-db-counter" data-type="trash_comments">
							<?php echo esc_html( $stats['trash_comments'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="trash_comments" <?php disabled( $stats['trash_comments'], 0 ); ?>>Limpiar</button>
						</td>
					</tr>
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Transients expirados</strong>
							<p class="description" style="margin:2px 0 0 0;">Opciones temporales en caché de WordPress cuyo tiempo límite de vida ya pasó.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px;" class="wpat-db-counter" data-type="expired_transients">
							<?php echo esc_html( $stats['expired_transients'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="expired_transients" <?php disabled( $stats['expired_transients'], 0 ); ?>>Limpiar</button>
						</td>
					</tr>
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Metadatos huérfanos</strong>
							<p class="description" style="margin:2px 0 0 0;">Ajustes de posts (postmeta) huérfanos de entradas que ya fueron borradas.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px;" class="wpat-db-counter" data-type="orphaned_postmeta">
							<?php echo esc_html( $stats['orphaned_postmeta'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="orphaned_postmeta" <?php disabled( $stats['orphaned_postmeta'], 0 ); ?>>Limpiar</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Muestra avisos de administración (éxito al guardar).
	 */
	public function render_admin_notices() {
		if ( isset( $_GET['import-done'] ) && 'true' === $_GET['import-done'] ) {
			$results = get_transient( 'wpat_import_results' );
			delete_transient( 'wpat_import_results' );
			if ( $results && is_array( $results ) ) {
				$success_count = intval( $results['success'] );
				$errors_count  = intval( $results['errors'] );
				?>
				<div class="notice notice-success is-dismissible" style="border-left-color: #10b981; padding: 12px 15px; margin-top: 15px;">
					<p style="margin: 0; font-weight: bold; font-size: 14px; color: #1e293b;">
						<span class="dashicons dashicons-yes-alt" style="color: #10b981; vertical-align: middle; margin-right: 5px;"></span> Importación finalizada con éxito:
					</p>
					<ul style="margin: 5px 0 0 20px; list-style-type: disc; color: #475569; font-size: 13px; line-height: 1.6;">
						<li>Contenidos importados/actualizados: <strong><?php echo esc_html( $success_count ); ?></strong> elementos.</li>
						<?php if ( isset( $results['snippets'] ) && $results['snippets'] > 0 ) : ?>
							<li>Fragmentos de código (Snippets) importados/actualizados: <strong><?php echo esc_html( intval( $results['snippets'] ) ); ?></strong>.</li>
						<?php endif; ?>
						<?php if ( $errors_count > 0 ) : ?>
							<li style="color: #ef4444; font-weight: 600;">Se detectaron <?php echo esc_html( $errors_count ); ?> errores durante la importación de posts.</li>
						<?php endif; ?>
					</ul>
				</div>
				<?php
			}
		}

		if ( isset( $_GET['initial-setup-done'] ) && 'true' === $_GET['initial-setup-done'] ) {
			$results = get_transient( 'wpat_initial_setup_results' );
			delete_transient( 'wpat_initial_setup_results' );
			?>
			<div class="notice notice-success is-dismissible wpat-init-notice" style="border-left-color: #10b981; padding: 12px 15px; margin-top: 15px;">
				<p style="margin: 0 0 5px 0; font-weight: bold; font-size: 14px; color: #1e293b;">
					<span class="dashicons dashicons-yes-alt" style="color: #10b981; vertical-align: middle; margin-right: 5px;"></span> Configuración inicial ejecutada con éxito:
				</p>
				<?php if ( ! empty( $results ) && is_array( $results ) ) : ?>
					<ul style="margin: 5px 0 0 20px; list-style-type: disc; color: #475569; font-size: 13px; line-height: 1.6;">
						<?php foreach ( $results as $msg ) : ?>
							<li><?php echo esc_html( $msg ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php
		}
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Configuración guardada correctamente.', 'wp-agency-toolkit' ); ?></strong></p>
			</div>
			<?php
		}
		if ( isset( $_GET['snippet-saved'] ) && 'true' === $_GET['snippet-saved'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Fragmento de código guardado correctamente.', 'wp-agency-toolkit' ); ?></strong></p>
			</div>
			<?php
		}
		if ( isset( $_GET['snippet-deleted'] ) && 'true' === $_GET['snippet-deleted'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Fragmento de código eliminado correctamente.', 'wp-agency-toolkit' ); ?></strong></p>
			</div>
			<?php
		}
		if ( isset( $_GET['snippet-cloned'] ) && 'true' === $_GET['snippet-cloned'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Fragmento de código clonado correctamente.', 'wp-agency-toolkit' ); ?></strong></p>
			</div>
			<?php
		}
		if ( isset( $_GET['snippet-toggled'] ) && 'true' === $_GET['snippet-toggled'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><strong><?php esc_html_e( 'Estado del fragmento actualizado correctamente.', 'wp-agency-toolkit' ); ?></strong></p>
			</div>
			<?php
		}
	}

	/**
	 * Pinta la página del panel de administración.
	 */
	public function render_admin_page() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$this->render_admin_notices();
		
		// Determinar la pestaña activa
		$active_tab = 'tab-security';
		if ( isset( $_GET['tab'] ) ) {
			$active_tab = sanitize_key( $_GET['tab'] );
		} elseif ( isset( $_POST['wpat_active_tab'] ) ) {
			$active_tab = sanitize_key( $_POST['wpat_active_tab'] );
		}
		?>
		<div class="wrap wpat-admin-wrapper">
			<div class="wpat-header">
				<div class="wpat-title-area">
					<h1>WP Agency Toolkit <span class="wpat-badge">v<?php echo esc_html( WPAT_VERSION ); ?></span></h1>
					<p class="description" style="color: #cbd5e1; margin: 0;">Optimiza, asegura y potencia tus sitios de WordPress con esta suite modular Zero-Bloat.</p>
				</div>

				<!-- Bloque de Actualización de GitHub -->
				<?php
				$new_version = '';
				$github_url = 'https://github.com/19webs/wp-agency-toolkit';
				if ( class_exists( 'WPAT_Updater' ) ) {
					$updater = WPAT_Updater::get_instance();
					$release = $updater->get_latest_github_release();
					if ( $release && isset( $release['tag_name'] ) ) {
						$new_version = ltrim( $release['tag_name'], 'v' );
						$github_url = isset( $release['html_url'] ) ? $release['html_url'] : $github_url;
					}
				}
				$has_update = ! empty( $new_version ) && version_compare( WPAT_VERSION, $new_version, '<' );
				?>
				<div id="wpat_updater_header_container" style="display: flex; align-items: center; gap: 15px; background: rgba(255,255,255,0.05); padding: 8px 16px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);">
					<div id="wpat_updater_widget_status" style="font-size: 12px; color: #e2e8f0; text-align: right; line-height: 1.3;">
						<?php if ( $has_update ) : ?>
							<span style="display:block; font-weight:700; color:#fb923c;">¡Nueva versión disponible!</span>
							Última versión: <strong>v<?php echo esc_html( $new_version ); ?></strong>
						<?php else : ?>
							<span style="color:#10b981; font-weight:600; display:flex; align-items:center; gap:4px; justify-content:flex-end;">
								<span style="width:7px; height:7px; background:#10b981; border-radius:50%; display:inline-block;"></span>
								Plugin actualizado
							</span>
						<?php endif; ?>
					</div>
					<div id="wpat_updater_widget_action">
						<?php if ( $has_update ) : ?>
							<a href="<?php echo wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=wp-agency-toolkit/wp-agency-toolkit.php' ), 'upgrade-plugin_wp-agency-toolkit/wp-agency-toolkit.php' ); ?>" class="button button-primary" style="background:#ea580c; border-color:#d97706; color:#fff; font-weight:700; height:32px; line-height:30px; border-radius:4px; margin:0; text-shadow:none; box-shadow:none; display: block; box-sizing: border-box;">
								Actualizar ahora
							</a>
						<?php else : ?>
							<button type="button" id="wpat_force_update_check_btn" class="button button-secondary" style="background:transparent; border-color:rgba(255,255,255,0.2); color:#fff; height:32px; line-height:30px; border-radius:4px; margin:0; cursor:pointer; font-weight:600; box-shadow:none; display: block; box-sizing: border-box;">
								Comprobar versión
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpat_save_settings_action', 'wpat_settings_nonce' ); ?>
				<input type="hidden" name="wpat_active_tab" id="wpat_active_tab_input" value="<?php echo esc_attr( $active_tab ); ?>" />

				<?php
				// Banner de aviso si los motores de búsqueda están disuadidos
				if ( '0' === get_option( 'blog_public' ) ) {
					?>
					<div class="notice notice-warning wpat-warning-banner" style="border-left-color: #ea580c; padding: 12px 15px; margin: 15px 0; background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
						<div style="display:flex; align-items:center; gap: 10px;">
							<span class="dashicons dashicons-warning" style="color: #ea580c; font-size: 20px; width: 20px; height: 20px; margin: 0;"></span>
							<span style="font-size: 13px; font-weight: 500; color: #1e293b;">
								<strong>Aviso de Indexación:</strong> Los motores de búsqueda tienen prohibido indexar este sitio. Recuerda activar la indexación en producción.
							</span>
						</div>
						<button type="submit" name="wpat_enable_indexing" class="button button-primary" style="background: #ea580c; border-color: #d97706; font-size: 11px; height: 28px; line-height: 26px;">Permitir Indexación</button>
					</div>
					<?php
				}
				?>

				<div class="wpat-container">
					<!-- Pestañas de Navegación -->
					<div class="wpat-tabs-nav">
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-security' ) ? 'active' : ''; ?>" data-tab="tab-security">
							<span class="dashicons dashicons-shield"></span> Seguridad & Acceso
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-woocommerce' ) ? 'active' : ''; ?>" data-tab="tab-woocommerce">
							<span class="dashicons dashicons-cart"></span> WooCommerce
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-performance' ) ? 'active' : ''; ?>" data-tab="tab-performance">
							<span class="dashicons dashicons-performance"></span> Rendimiento & Código
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-media' ) ? 'active' : ''; ?>" data-tab="tab-media">
							<span class="dashicons dashicons-admin-media"></span> Optimización de Medios
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-kits' ) ? 'active' : ''; ?>" data-tab="tab-kits">
							<span class="dashicons dashicons-download"></span> Importador de Kits
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-smtp' ) ? 'active' : ''; ?>" data-tab="tab-smtp">
							<span class="dashicons dashicons-email-alt"></span> Configuración SMTP
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-seo' ) ? 'active' : ''; ?>" data-tab="tab-seo">
							<span class="dashicons dashicons-google"></span> Optimización SEO
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-integrations' ) ? 'active' : ''; ?>" data-tab="tab-integrations">
							<span class="dashicons dashicons-admin-links"></span> Integraciones
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-initial-setup' ) ? 'active' : ''; ?>" data-tab="tab-initial-setup">
							<span class="dashicons dashicons-admin-settings"></span> Configuración Inicial
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-tools' ) ? 'active' : ''; ?>" data-tab="tab-tools">
							<span class="dashicons dashicons-admin-tools"></span> Exportación & importación
						</button>
						<button type="button" class="wpat-tab-link <?php echo ( $active_tab === 'tab-health' ) ? 'active' : ''; ?>" data-tab="tab-health">
							<span class="dashicons dashicons-database"></span> Salud & Base de Datos
						</button>

					</div>

					<!-- Contenido de las Pestañas -->
					<div class="wpat-tabs-content">

						<!-- PESTAÑA 1: SEGURIDAD Y ACCESO -->
						<div id="tab-security" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-security' ) ? 'active' : ''; ?>">
							<h2>Seguridad & Acceso</h2>
							<p class="section-desc">Gestiona el acceso al panel y fortalece la seguridad básica de tu sitio.</p>

							<!-- Módulo: Login Customizer -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Personalizador del Login</h3>
										<p>Personaliza el logotipo y el color de fondo de la pantalla de inicio de sesión (/wp-login.php).</p>
									</div>
									<?php $this->render_module_toggle( 'login-customizer', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<div class="wpat-field-group">
										<label for="wpat_login_style">Estilo de la Pantalla de Login</label>
										<select name="wpat_settings[login_style]" id="wpat_login_style" class="regular-text" style="display:block; margin-bottom:15px;">
											<option value="default" <?php selected( isset( $settings['login_style'] ) ? $settings['login_style'] : 'default', 'default' ); ?>>Predeterminado de WordPress</option>
											<option value="modern" <?php selected( isset( $settings['login_style'] ) ? $settings['login_style'] : 'default', 'modern' ); ?>>Diseño Moderno / Profesional</option>
										</select>
									</div>

									<div class="wpat-modern-login-subfields" style="display: none; border-left: 3px solid var(--wpat-primary); padding-left: 15px; margin-bottom: 20px; margin-top: 15px;">
										<div class="wpat-field-group" style="margin-bottom: 15px;">
											<label for="wpat_login_bg_type">Tipo de Fondo de la Pantalla</label>
											<select name="wpat_settings[login_bg_type]" id="wpat_login_bg_type" class="regular-text" style="display:block; margin-bottom:10px;">
												<option value="image" <?php selected( isset( $settings['login_bg_type'] ) ? $settings['login_bg_type'] : 'image', 'image' ); ?>>Imagen (Foto o Vectorial por Defecto)</option>
												<option value="color" <?php selected( isset( $settings['login_bg_type'] ) ? $settings['login_bg_type'] : 'image', 'color' ); ?>>Color Liso / Sólido</option>
											</select>
										</div>

										<div class="wpat-login-bg-image-group" style="display: none;">
											<div class="wpat-field-group">
												<label for="wpat_login_bg_image">Imagen de Fondo Personalizada (Opcional)</label>
												<div class="logo-uploader-container">
													<input type="text" name="wpat_settings[login_bg_image]" id="wpat_login_bg_image" value="<?php echo esc_attr( isset( $settings['login_bg_image'] ) ? $settings['login_bg_image'] : '' ); ?>" class="regular-text" placeholder="https://..." />
													<button type="button" class="button" id="wpat_login_bg_image_btn">Seleccionar Imagen</button>
													<button type="button" class="button button-link-delete" id="wpat_login_bg_image_remove" <?php echo empty( $settings['login_bg_image'] ) ? 'style="display:none;"' : ''; ?>>Eliminar</button>
													<div id="wpat_login_bg_image_preview" class="wpat-image-preview" <?php echo empty( $settings['login_bg_image'] ) ? 'style="display:none;"' : ''; ?> style="margin-top:10px;">
														<?php if ( ! empty( $settings['login_bg_image'] ) ) : ?>
															<img src="<?php echo esc_url( $settings['login_bg_image'] ); ?>" alt="Vista previa del fondo" style="max-width:200px; max-height:100px; display:block; border-radius:4px;" />
														<?php endif; ?>
													</div>
												</div>
												<p class="description">Sube una imagen para cubrir el fondo. Si se deja vacío, se cargará el fondo vectorial moderno predeterminado.</p>
											</div>
										</div>

										<div class="wpat-field-group" style="margin-top: 15px;">
											<label for="wpat_login_accent_color" style="display:block; margin-bottom:5px;">Color de Acento (Botón y Foco)</label>
											<input type="text" name="wpat_settings[login_accent_color]" id="wpat_login_accent_color" value="<?php echo esc_attr( isset( $settings['login_accent_color'] ) ? $settings['login_accent_color'] : '#2563eb' ); ?>" class="wpat-color-picker" />
											<p class="description">Establece el color del botón de acceder y del foco de los campos.</p>
										</div>
									</div>

									<div class="wpat-field-group">
										<label for="wpat_login_logo">Logotipo Personalizado</label>
										<div class="logo-uploader-container">
											<input type="text" name="wpat_settings[login_logo]" id="wpat_login_logo" value="<?php echo esc_attr( $settings['login_logo'] ); ?>" class="regular-text" placeholder="https://..." />
											<button type="button" class="button" id="wpat_login_logo_btn">Seleccionar Logo</button>
											<button type="button" class="button button-link-delete" id="wpat_login_logo_remove" <?php echo empty( $settings['login_logo'] ) ? 'style="display:none;"' : ''; ?>>Eliminar</button>
											<div id="wpat_login_logo_preview" class="wpat-image-preview" <?php echo empty( $settings['login_logo'] ) ? 'style="display:none;"' : ''; ?>>
												<?php if ( ! empty( $settings['login_logo'] ) ) : ?>
													<img src="<?php echo esc_url( $settings['login_logo'] ); ?>" alt="Vista previa del logo" />
												<?php endif; ?>
											</div>
										</div>
									</div>

									<div class="wpat-field-group">
										<label for="wpat_login_bg_color">Color de Fondo (Alternativo)</label>
										<input type="text" name="wpat_settings[login_bg_color]" id="wpat_login_bg_color" value="<?php echo esc_attr( $settings['login_bg_color'] ); ?>" class="wpat-color-picker" />
									</div>

									<div class="wpat-field-group" style="margin-bottom:15px;">
										<label for="wpat_login_hide_languages" style="display:inline-flex; align-items:center; font-weight:normal; cursor:pointer;">
											<input type="checkbox" name="wpat_settings[login_hide_languages]" id="wpat_login_hide_languages" value="1" <?php checked( isset( $settings['login_hide_languages'] ) && '1' === $settings['login_hide_languages'] ); ?> style="margin-right:8px;" />
											Ocultar selector de idioma en pantalla de login
										</label>
										<p class="description" style="margin-left:22px; margin-top:-5px;">Oculta el selector de idioma que WordPress muestra debajo del formulario.</p>
									</div>

									<div class="wpat-field-group">
										<label for="wpat_login_footer_text">Texto de Pie de Página en Login</label>
										<input type="text" name="wpat_settings[login_footer_text]" id="wpat_login_footer_text" value="<?php echo esc_attr( isset( $settings['login_footer_text'] ) ? $settings['login_footer_text'] : '' ); ?>" class="regular-text" placeholder="Ej: Hecho por Mi Agencia" />
										<p class="description">Añade un texto de créditos personalizado al final de la tarjeta de login.</p>
									</div>

									<div class="wpat-field-group">
										<label for="wpat_admin_footer_text">Texto de Pie de Página en Administración</label>
										<input type="text" name="wpat_settings[admin_footer_text]" id="wpat_admin_footer_text" value="<?php echo esc_attr( isset( $settings['admin_footer_text'] ) ? $settings['admin_footer_text'] : '' ); ?>" class="regular-text" placeholder="Ej: Desarrollado por Mi Agencia" />
										<p class="description">Reemplaza el texto predeterminado "Gracias por crear con WordPress." y oculta la versión en la esquina inferior derecha.</p>
									</div>
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

							<!-- Módulo: Restricción de Barra y Acceso de Admin -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Restringir Barra y Acceso de Admin</h3>
										<p>Oculta la barra superior negra de WordPress y bloquea el acceso a /wp-admin para usuarios con roles básicos (Suscriptores, Clientes de WooCommerce, etc.).</p>
									</div>
									<?php $this->render_module_toggle( 'hide_admin_bar', $settings, true ); ?>
								</div>
							</div>

							<!-- Módulo: Escritorio Personalizado & Limpiador -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Escritorio Personalizado & Limpiador</h3>
										<p>Sustituye el Escritorio estándar por un panel de control alternativo, limpio y moderno con accesos rápidos y estadísticas.</p>
									</div>
									<?php $this->render_module_toggle( 'dashboard_cleaner', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<div class="wpat-field-group" style="margin-top: 10px;">
										<label for="wpat_dashboard_support_email">Email de Destino de Consultas</label>
										<input type="email" name="wpat_settings[dashboard_support_email]" id="wpat_dashboard_support_email" value="<?php echo esc_attr( isset( $settings['dashboard_support_email'] ) ? $settings['dashboard_support_email'] : get_option( 'admin_email' ) ); ?>" class="regular-text" style="display:block; margin-bottom:10px;" />
										<p class="description">Las consultas enviadas a través del formulario de soporte en el escritorio se enviarán a esta dirección de correo.</p>
									</div>
									
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dotted var(--wpat-border); padding-top: 15px;">
										<label style="font-weight: 600; display: block; margin-bottom: 8px;">Tarjetas del Escritorio Visibles</label>
										<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_seo]" value="1" <?php checked( isset( $settings['db_card_seo'] ) ? $settings['db_card_seo'] : '1', '1' ); ?> />
												Salud SEO del Sitio
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_pages]" value="1" <?php checked( isset( $settings['db_card_pages'] ) ? $settings['db_card_pages'] : '1', '1' ); ?> />
												Estructura de Páginas
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_posts]" value="1" <?php checked( isset( $settings['db_card_posts'] ) ? $settings['db_card_posts'] : '1', '1' ); ?> />
												Artículos de Blog
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_plugins]" value="1" <?php checked( isset( $settings['db_card_plugins'] ) ? $settings['db_card_plugins'] : '1', '1' ); ?> />
												Plugins Instalados
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_themes]" value="1" <?php checked( isset( $settings['db_card_themes'] ) ? $settings['db_card_themes'] : '1', '1' ); ?> />
												Temas del Sitio
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_users]" value="1" <?php checked( isset( $settings['db_card_users'] ) ? $settings['db_card_users'] : '1', '1' ); ?> />
												Cuentas y Accesos
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_db]" value="1" <?php checked( isset( $settings['db_card_db'] ) ? $settings['db_card_db'] : '1', '1' ); ?> />
												Peso y Base de Datos
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_tools]" value="1" <?php checked( isset( $settings['db_card_tools'] ) ? $settings['db_card_tools'] : '1', '1' ); ?> />
												Copias y Contenidos
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_smtp]" value="1" <?php checked( isset( $settings['db_card_smtp'] ) ? $settings['db_card_smtp'] : '1', '1' ); ?> />
												Servidor SMTP
											</label>
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_media]" value="1" <?php checked( isset( $settings['db_card_media'] ) ? $settings['db_card_media'] : '1', '1' ); ?> />
												Biblioteca de Medios
											</label>
											<?php if ( class_exists( 'WooCommerce' ) ) : ?>
												<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
													<input type="checkbox" name="wpat_settings[db_card_woo]" value="1" <?php checked( isset( $settings['db_card_woo'] ) ? $settings['db_card_woo'] : '1', '1' ); ?> />
													Tienda WooCommerce
												</label>
											<?php endif; ?>
											<?php if ( class_exists( 'Jet_Engine' ) ) : ?>
												<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
													<input type="checkbox" name="wpat_settings[db_card_jet]" value="1" <?php checked( isset( $settings['db_card_jet'] ) ? $settings['db_card_jet'] : '1', '1' ); ?> />
													Estructura JetEngine
												</label>
											<?php endif; ?>
										</div>
									</div>
									
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

							<!-- Módulo: Bloqueador de Bots por 404 -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Bloqueador de Bots por 404</h3>
										<p>Detecta y bloquea temporalmente direcciones IPs de bots maliciosos que generan múltiples errores 404 buscando vulnerabilidades.</p>
									</div>
									<?php $this->render_module_toggle( 'bot-blocker', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<!-- Nonce para acciones AJAX del Bot Blocker -->
									<?php wp_nonce_field( 'wpat_bot_blocker_nonce_action', 'wpat_bot_blocker_ajax_nonce' ); ?>

									<div class="wpat-field-group-row" style="display: flex; gap: 20px; flex-wrap: wrap;">
										<div class="wpat-field-group" style="flex: 1; min-width: 150px;">
											<label for="wpat_bot_blocker_limit">Límite de errores 404</label>
											<input type="number" name="wpat_settings[bot_blocker_limit]" id="wpat_bot_blocker_limit" value="<?php echo esc_attr( isset( $settings['bot_blocker_limit'] ) ? $settings['bot_blocker_limit'] : 15 ); ?>" class="small-text" min="1" />
											<p class="description">Número máximo de 404s permitidos en la ventana de tiempo.</p>
										</div>
										<div class="wpat-field-group" style="flex: 1; min-width: 150px;">
											<label for="wpat_bot_blocker_timeframe">Ventana de tiempo (segundos)</label>
											<input type="number" name="wpat_settings[bot_blocker_timeframe]" id="wpat_bot_blocker_timeframe" value="<?php echo esc_attr( isset( $settings['bot_blocker_timeframe'] ) ? $settings['bot_blocker_timeframe'] : 300 ); ?>" class="small-text" min="10" step="10" />
											<p class="description">Tiempo en el que se evalúa la frecuencia de errores.</p>
										</div>
										<div class="wpat-field-group" style="flex: 1; min-width: 150px;">
											<label for="wpat_bot_blocker_duration">Duración del bloqueo (horas)</label>
											<input type="number" name="wpat_settings[bot_blocker_duration]" id="wpat_bot_blocker_duration" value="<?php echo esc_attr( isset( $settings['bot_blocker_duration'] ) ? $settings['bot_blocker_duration'] : 24 ); ?>" class="small-text" min="1" />
											<p class="description">Tiempo de exclusión temporal para la IP sospechosa.</p>
										</div>
									</div>

									<?php
									// Obtener la IP actual de forma segura para mostrarla
									$keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
									$visitor_ip = '';
									foreach ( $keys as $key ) {
										if ( ! empty( $_SERVER[ $key ] ) ) {
											$ips = explode( ',', $_SERVER[ $key ] );
											$ip  = trim( $ips[0] );
											if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
												$visitor_ip = $ip;
												break;
											}
										}
									}
									?>
									<div class="wpat-field-group" style="margin-top: 15px;">
										<label for="wpat_bot_blocker_whitelist">Lista blanca manual de IPs</label>
										<textarea name="wpat_settings[bot_blocker_whitelist]" id="wpat_bot_blocker_whitelist" class="large-text" rows="2" placeholder="Ej: 192.168.1.1, 8.8.8.8"><?php echo esc_textarea( isset( $settings['bot_blocker_whitelist'] ) ? $settings['bot_blocker_whitelist'] : '' ); ?></textarea>
										<p class="description">Introduce direcciones IPs separadas por comas que nunca deban ser bloqueadas. (Tu IP actual: <strong><?php echo esc_html( $visitor_ip ); ?></strong>).</p>
									</div>

									<?php
									global $wpdb;
									$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';
									$blocked_ips = array();
									if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_blocked'" ) === $table_blocked ) {
										$blocked_ips = $wpdb->get_results( "SELECT * FROM $table_blocked WHERE expires_at > NOW() ORDER BY blocked_at DESC" );
									}
									?>
									<div class="wpat-field-group" style="margin-top: 25px; border-top: 1px dotted var(--wpat-border); padding-top: 20px;">
										<label style="font-weight: 600; display: block; margin-bottom: 10px;">IPs Bloqueadas Actualmente</label>
										<div class="wpat-table-responsive" style="max-height: 250px; overflow-y: auto;">
											<table class="wp-list-table widefat fixed striped wpat-blocked-ips-table" style="width: 100%; border: 1px solid var(--wpat-border);">
												<thead>
													<tr>
														<th style="padding: 10px;">Dirección IP</th>
														<th style="padding: 10px;">Razón</th>
														<th style="padding: 10px;">Fecha de Bloqueo</th>
														<th style="padding: 10px;">Tiempo Restante</th>
														<th style="padding: 10px; width: 180px; text-align: right;">Acciones</th>
													</tr>
												</thead>
												<tbody>
													<?php if ( empty( $blocked_ips ) ) : ?>
														<tr class="no-blocked-ips-row">
															<td colspan="5" style="text-align: center; color: #94a3b8; padding: 25px 0;">No hay direcciones IPs bloqueadas en este momento.</td>
														</tr>
													<?php else : ?>
														<?php foreach ( $blocked_ips as $blocked ) : ?>
															<tr data-ip="<?php echo esc_attr( $blocked->ip ); ?>">
																<td style="padding: 10px; vertical-align: middle;"><strong><?php echo esc_html( $blocked->ip ); ?></strong></td>
																<td style="padding: 10px; vertical-align: middle;"><?php echo esc_html( $blocked->reason ); ?></td>
																<td style="padding: 10px; vertical-align: middle;"><?php echo esc_html( $blocked->blocked_at ); ?></td>
																<td style="padding: 10px; vertical-align: middle;">
																	<?php
																	$diff = strtotime( $blocked->expires_at ) - time();
																	if ( $diff > 0 ) {
																		echo esc_html( round( $diff / HOUR_IN_SECONDS, 1 ) . ' horas' );
																	} else {
																		echo 'Expirado';
																	}
																	?>
																</td>
																<td style="padding: 10px; text-align: right; vertical-align: middle;">
																	<button type="button" class="button wpat-unblock-ip-btn" data-ip="<?php echo esc_attr( $blocked->ip ); ?>">Desbloquear</button>
																	<button type="button" class="button button-primary wpat-whitelist-ip-btn" data-ip="<?php echo esc_attr( $blocked->ip ); ?>" style="font-size: 11px;">Lista Blanca</button>
																</td>
															</tr>
														<?php endforeach; ?>
													<?php endif; ?>
												</tbody>
											</table>
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

							<!-- Módulo: Hide Login -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Ocultar URL de Login</h3>
										<p>Cambia el slug predeterminado wp-login.php por uno personalizado para mitigar ataques de fuerza bruta.</p>
									</div>
									<?php $this->render_module_toggle( 'hide-login', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<div class="wpat-field-group">
										<label for="wpat_hide_login_slug">Slug Personalizado de Login</label>
										<div class="wpat-input-prefix-container">
											<span class="wpat-input-prefix"><?php echo esc_url( home_url( '/' ) ); ?></span>
											<input type="text" name="wpat_settings[hide_login_slug]" id="wpat_hide_login_slug" value="<?php echo esc_attr( $settings['hide_login_slug'] ); ?>" class="regular-text" placeholder="acceso" />
										</div>
										<p class="description">Accede a tu panel a través de esta nueva ruta. ¡No la olvides!</p>
									</div>
									<div class="wpat-field-group">
										<label for="wpat_hide_login_redirect">Acción al acceder a wp-login.php directo</label>
										<select name="wpat_settings[hide_login_redirect]" id="wpat_hide_login_redirect">
											<option value="home" <?php selected( $settings['hide_login_redirect'], 'home' ); ?>>Redirigir a Portada (Home)</option>
											<option value="404" <?php selected( $settings['hide_login_redirect'], '404' ); ?>>Mostrar página 404 (No Encontrado)</option>
										</select>
									</div>
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[hide_login_limit_attempts]" id="wpat_hide_login_limit_attempts" value="1" <?php checked( $settings['hide_login_limit_attempts'], '1' ); ?>>
											Limitar intentos de inicio de sesión fallidos (Protección fuerza bruta)
										</label>
									</div>
									<div class="wpat-field-group wpat-sub-field" <?php $this->style_conditional_display( $settings['hide_login_limit_attempts'] ); ?>>
										<label for="wpat_hide_login_max_attempts">Intentos máximos permitidos</label>
										<input type="number" name="wpat_settings[hide_login_max_attempts]" id="wpat_hide_login_max_attempts" value="<?php echo esc_attr( $settings['hide_login_max_attempts'] ); ?>" class="small-text" min="1" max="10" /> intentos
									</div>
									<div class="wpat-field-group wpat-sub-field" <?php $this->style_conditional_display( $settings['hide_login_limit_attempts'] ); ?>>
										<label for="wpat_hide_login_lockout">Duración del bloqueo temporal</label>
										<input type="number" name="wpat_settings[hide_login_lockout]" id="wpat_hide_login_lockout" value="<?php echo esc_attr( $settings['hide_login_lockout'] ); ?>" class="small-text" min="30" step="30" /> segundos
									</div>
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[hide_login_captcha]" id="wpat_hide_login_captcha" value="1" <?php checked( $settings['hide_login_captcha'], '1' ); ?>>
											Habilitar Captcha Matemático Simple en el Login
										</label>
									</div>
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

							<!-- Módulo: SSL Fixer -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Forzar SSL & Contenido Mixto</h3>
										<p>Fuerza la redirección a HTTPS, repara URLs del buffer de salida y añade cabeceras de seguridad HTTP básicas.</p>
									</div>
									<?php $this->render_module_toggle( 'ssl-fixer', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<div class="wpat-field-group">
										<label for="wpat_ssl_redirect_method">Método de redireccionamiento a HTTPS</label>
										<select name="wpat_settings[ssl_redirect_method]" id="wpat_ssl_redirect_method">
											<option value="php" <?php selected( $settings['ssl_redirect_method'], 'php' ); ?>>Redirección 301 por PHP (Segura, compatible con todos los servidores)</option>
											<option value="htaccess" <?php selected( $settings['ssl_redirect_method'], 'htaccess' ); ?>>Redirección 301 por .htaccess (Más rápida, solo Apache/LiteSpeed)</option>
										</select>
										<p class="description">Nota: La redirección por .htaccess es más veloz porque se ejecuta antes de cargar WordPress, pero solo funciona en servidores Apache o LiteSpeed. Si utilizas Nginx, mantén el método PHP.</p>
									</div>
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

							<!-- Módulo: Disable Comments -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Deshabilitar Comentarios</h3>
										<p>Desactiva globalmente o por tipos de contenido los comentarios, trackbacks y widgets para evitar spam.</p>
									</div>
									<?php $this->render_module_toggle( 'disable-comments', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[disable_comments_global]" id="wpat_disable_comments_global" value="1" <?php checked( $settings['disable_comments_global'], '1' ); ?>>
											Desactivar en todo el sitio web (Recomendado)
										</label>
									</div>
									<div class="wpat-field-group wpat-sub-field wpat-comments-options" <?php $this->style_conditional_display( '1' === $settings['disable_comments_global'] ? '0' : '1' ); ?>>
										<p style="font-weight:600; margin-bottom:10px; font-size:13px;">O desactivar solo en tipos de contenido específicos:</p>
										<label style="font-weight:normal; margin-bottom:8px;">
											<input type="checkbox" name="wpat_settings[disable_comments_posts]" value="1" <?php checked( $settings['disable_comments_posts'], '1' ); ?>>
											Entradas (Posts)
										</label>
										<label style="font-weight:normal; margin-bottom:8px;">
											<input type="checkbox" name="wpat_settings[disable_comments_pages]" value="1" <?php checked( $settings['disable_comments_pages'], '1' ); ?>>
											Páginas (Pages)
										</label>
										<label style="font-weight:normal; margin-bottom:8px;">
											<input type="checkbox" name="wpat_settings[disable_comments_media]" value="1" <?php checked( $settings['disable_comments_media'], '1' ); ?>>
											Archivos Multimedia (Medios)
										</label>
									</div>
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>
							<!-- Módulo: Fortalecimiento de Seguridad (Hardening) -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Fortalecimiento de Seguridad (Hardening)</h3>
										<p>Aplica directivas y ajustes de seguridad avanzados recomendados por expertos para blindar tu sitio WordPress contra exploits comunes.</p>
									</div>
									<?php $this->render_module_toggle( 'security-hardening', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_disable_file_edit]" value="1" <?php checked( $settings['sec_disable_file_edit'], '1' ); ?>>
											Desactivar los editores de archivos incorporados (Evita edición de temas/plugins desde el panel)
										</label>
									</div>

									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_block_uploads_php]" value="1" <?php checked( $settings['sec_block_uploads_php'], '1' ); ?>>
											Evitar la ejecución de código en la carpeta pública 'Uploads' (Crea protección .htaccess contra PHP malicioso)
										</label>
									</div>

									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_hide_wp_version]" value="1" <?php checked( $settings['sec_hide_wp_version'], '1' ); ?>>
											Ocultar tu versión de WordPress (Mitiga ataques dirigidos a exploits de versiones específicas)
										</label>
									</div>

									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_generic_login_errors]" value="1" <?php checked( $settings['sec_generic_login_errors'], '1' ); ?>>
											Evitar la respuesta del acceso (Muestra errores de acceso genéricos para no dar pistas a atacantes)
										</label>
									</div>

									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_disable_indexes]" value="1" <?php checked( $settings['sec_disable_indexes'], '1' ); ?>>
											Desactivar la búsqueda de directorios (Previene el listado de archivos en directorios sin index.html)
										</label>
									</div>

									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_disable_user_enum]" value="1" <?php checked( $settings['sec_disable_user_enum'], '1' ); ?>>
											Desactivar la enumeración de usuarios (Bloquea escaneos de ?author=N y endpoints de usuario de la REST API)
										</label>
									</div>

									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_disable_xmlrpc]" value="1" <?php checked( $settings['sec_disable_xmlrpc'], '1' ); ?>>
											Desactivar XML-RPC (Previene ataques de fuerza bruta y DDoS basados en XML-RPC)
										</label>
									</div>

									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_block_admin_user]" value="1" <?php checked( $settings['sec_block_admin_user'], '1' ); ?>>
											Bloquear el usuario 'admin' (Deniega el inicio de sesión y registro de este nombre de usuario por defecto)
										</label>
									</div>

									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>

								</div>
							</div>

						</div>

						<!-- PESTAÑA 2: WOOCOMMERCE -->
						<div id="tab-woocommerce" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-woocommerce' ) ? 'active' : ''; ?>">
							<h2>Funcionalidades de WooCommerce</h2>
							<p class="section-desc">Optimiza y complementa la experiencia de tu tienda en línea.</p>

							<!-- Módulo: DNI/CIF -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Campo DNI/CIF en el Checkout</h3>
										<p>Inyecta un campo obligatorio de DNI/CIF en los datos de facturación de WooCommerce. Se guarda y muestra en pedidos y correos.</p>
									</div>
									<?php $this->render_module_toggle( 'woo-dni', $settings, false ); ?>
								</div>
							</div>

							<!-- Módulo: Modo Catálogo -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Modo Catálogo</h3>
										<p>Desactiva la compra de productos, ocultando precios o los botones de añadir al carrito según tus necesidades.</p>
									</div>
									<?php $this->render_module_toggle( 'woo-catalog', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[woo_catalog_hide_price]" value="1" <?php checked( $settings['woo_catalog_hide_price'], '1' ); ?>>
											Ocultar precios de los productos
										</label>
									</div>
									<div class="wpat-field-group">
										<label for="wpat_woo_catalog_price_text">Texto alternativo al precio (opcional)</label>
										<input type="text" name="wpat_settings[woo_catalog_price_text]" id="wpat_woo_catalog_price_text" value="<?php echo esc_attr( $settings['woo_catalog_price_text'] ); ?>" class="regular-text" placeholder="Consultar precio" />
									</div>
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[woo_catalog_hide_cart]" value="1" <?php checked( $settings['woo_catalog_hide_cart'], '1' ); ?>>
											Desactivar y ocultar botón "Añadir al carrito"
										</label>
									</div>

									<!-- Acciones alternativas al carrito -->
									<div class="wpat-sub-fields-container wpat-catalog-actions-container" <?php $this->style_conditional_display( $settings['woo_catalog_hide_cart'] ); ?> style="margin-left: 20px; border-left: 2px solid var(--wpat-border); padding-left: 15px; margin-top: 15px;">
										
										<!-- WhatsApp Section -->
										<div class="wpat-field-group" style="margin-bottom:15px;">
											<label style="font-weight: 600;">
												<input type="checkbox" name="wpat_settings[woo_catalog_wa_enable]" id="wpat_woo_catalog_wa_enable" value="1" <?php checked( $settings['woo_catalog_wa_enable'], '1' ); ?>>
												Activar botón de contacto por WhatsApp
											</label>
										</div>

										<div class="wpat-catalog-wa-subfields" <?php $this->style_conditional_display( $settings['woo_catalog_wa_enable'] ); ?> style="margin-left: 20px; margin-bottom: 20px;">
											<div class="wpat-field-group" style="margin-bottom: 10px;">
												<label for="wpat_woo_catalog_wa_phone" style="display:block; font-size:12px; margin-bottom:4px; font-weight:600;">Número de WhatsApp (con prefijo de país, ej. 34600000000)</label>
												<input type="text" name="wpat_settings[woo_catalog_wa_phone]" id="wpat_woo_catalog_wa_phone" value="<?php echo esc_attr( $settings['woo_catalog_wa_phone'] ); ?>" class="regular-text" placeholder="Ej. 34600000000" />
											</div>
											<div class="wpat-field-group">
												<label for="wpat_woo_catalog_wa_message" style="display:block; font-size:12px; margin-bottom:4px; font-weight:600;">Mensaje Predeterminado (Soporta variables: {product_title} y {product_url})</label>
												<textarea name="wpat_settings[woo_catalog_wa_message]" id="wpat_woo_catalog_wa_message" rows="3" class="large-text" placeholder="Hola, estoy interesado en el producto {product_title} ({product_url}). ¿Cómo podría comprarlo?"><?php echo esc_textarea( $settings['woo_catalog_wa_message'] ); ?></textarea>
											</div>
										</div>

										<!-- Form Section -->
										<div class="wpat-field-group" style="margin-bottom:15px;">
											<label style="font-weight: 600;">
												<input type="checkbox" name="wpat_settings[woo_catalog_form_enable]" id="wpat_woo_catalog_form_enable" value="1" <?php checked( $settings['woo_catalog_form_enable'], '1' ); ?>>
												Activar formulario de contacto por Email
											</label>
										</div>

										<div class="wpat-catalog-form-subfields" <?php $this->style_conditional_display( $settings['woo_catalog_form_enable'] ); ?> style="margin-left: 20px; margin-bottom: 20px;">
											<div class="wpat-field-group">
												<label for="wpat_woo_catalog_form_email" style="display:block; font-size:12px; margin-bottom:4px; font-weight:600;">Correo electrónico de destino para las consultas</label>
												<input type="email" name="wpat_settings[woo_catalog_form_email]" id="wpat_woo_catalog_form_email" value="<?php echo esc_attr( $settings['woo_catalog_form_email'] ); ?>" class="regular-text" placeholder="Ej. info@tuweb.com (vacío = email del administrador)" />
											</div>
										</div>

									</div>

									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

							<!-- Módulo: Gallery Zoom Controls -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Controles de la Galería de Productos</h3>
										<p>Desactiva de forma independiente funciones nativas de la galería de producto de WooCommerce como el Zoom, Lightbox o Slider.</p>
									</div>
									<?php $this->render_module_toggle( 'woo-zoom', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[woo_zoom_disable_zoom]" value="1" <?php checked( $settings['woo_zoom_disable_zoom'], '1' ); ?>>
											Desactivar Efecto Zoom de Galería
										</label>
									</div>
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[woo_zoom_disable_lightbox]" value="1" <?php checked( $settings['woo_zoom_disable_lightbox'], '1' ); ?>>
											Desactivar Ventana Emergente (Lightbox)
										</label>
									</div>
									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[woo_zoom_disable_slider]" value="1" <?php checked( $settings['woo_zoom_disable_slider'], '1' ); ?>>
											Desactivar Deslizador de Galería (Slider)
										</label>
									</div>
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

						</div>

						<!-- PESTAÑA 3: RENDIMIENTO Y CÓDIGO -->
						<div id="tab-performance" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-performance' ) ? 'active' : ''; ?>">
							<h2>Rendimiento & Código</h2>
							<p class="section-desc">Ajustes para mejorar los tiempos de carga y flujos de trabajo en el desarrollo.</p>

							<!-- Módulo: Duplicator -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Clonador de Entradas y Páginas</h3>
										<p>Añade un enlace "Clonar" en los listados de administración para duplicar instantáneamente cualquier Entrada, Página o Custom Post Type conservando metadatos (ACF/JetEngine).</p>
									</div>
									<?php $this->render_module_toggle( 'duplicator', $settings, false ); ?>
								</div>
							</div>

							<!-- Módulo: Custom Code Snippets -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Fragmentos de Código Personalizados (Snippets)</h3>
										<p>Inyecta CSS en la cabecera, Javascript en el footer y ejecuta código PHP dinámico sin tocar los archivos de tu tema.</p>
									</div>
									<?php $this->render_module_toggle( 'snippets', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									
									<!-- Nonce para acciones AJAX -->
									<?php wp_nonce_field( 'wpat_snippet_nonce_action', 'wpat_snippet_ajax_nonce' ); ?>

									<!-- Contenedor del Editor de Snippets (oculto por defecto) -->
									<div class="wpat-snippet-editor" style="display: none; border-top: 1px dashed var(--wpat-border); padding-top: 20px;">
										<h4 class="wpat-editor-title" style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600;">Añadir Nuevo Fragmento</h4>
										
										<div style="background: #f8fafc; border: 1px solid var(--wpat-border); padding: 15px; border-radius: 6px; margin-top: 10px;">
											<input type="hidden" id="wpat_editor_id" value="" />
											
											<div class="wpat-field-group">
												<label for="wpat_editor_name" style="font-weight: 600;">Nombre del Fragmento</label>
												<input type="text" id="wpat_editor_name" class="regular-text" placeholder="Ej. Filtro de WooCommerce o Analytics Global" style="width: 100%; max-width: 400px; margin-top: 5px;" />
											</div>

											<div class="wpat-field-group" style="margin-top: 15px;">
												<label for="wpat_editor_type" style="font-weight: 600;">Tipo de Código</label>
												<select id="wpat_editor_type" style="display: block; margin-top: 5px;">
													<option value="php">PHP (Ejecución segura backend)</option>
													<option value="css">CSS (Inyección cabecera wp_head)</option>
													<option value="js">Javascript (Inyección pie wp_footer)</option>
												</select>
											</div>

											<div class="wpat-field-group" style="margin-top: 15px;">
												<label for="wpat_editor_code" style="font-weight: 600;">Código</label>
												<textarea id="wpat_editor_code" rows="12" class="large-text code" placeholder="/* Escribe tu código aquí. No incluyas etiquetas de apertura &lt;?php o scripts de JS */" style="width:100%; font-family: monospace; margin-top: 5px;"></textarea>
												<p class="description">Si es PHP, no añadas la etiqueta de apertura &lt;?php. Si es JS, no agregues etiquetas &lt;script&gt;.</p>
											</div>

											<div class="wpat-field-group" style="margin-top: 15px;">
												<label style="font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
													<input type="checkbox" id="wpat_editor_active" value="1" checked /> Activar este fragmento
												</label>
											</div>

											<div style="margin-top: 20px; display: flex; gap: 10px;">
												<button type="button" class="button button-primary" id="wpat_save_snippet_btn">Guardar Fragmento</button>
												<button type="button" class="button button-secondary" id="wpat_cancel_snippet_btn">Cancelar</button>
											</div>
										</div>
									</div>

									<!-- Contenedor del Listado (siempre visible al inicio) -->
									<div class="wpat-snippets-list" style="border-top: 1px dashed var(--wpat-border); padding-top: 20px;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
											<h4 style="margin: 0; font-size: 14px; font-weight: 600;">Fragmentos de Código</h4>
											<div style="display: flex; gap: 8px;">
												<input type="file" name="wpat_import_snippets_only_file" id="wpat_import_snippets_only_file" accept=".json" style="display:none;" />
												<input type="submit" name="wpat_execute_import_snippets_only_btn" id="wpat_execute_import_snippets_only_submit" style="display:none;" />
												
												<button type="button" class="button button-secondary" id="wpat_import_snippets_only_btn" title="Importar fragmentos (.json)">
													<span class="dashicons dashicons-download" style="vertical-align: middle; font-size: 16px; width:16px; height:16px; margin-right: 3px;"></span> Importar
												</button>
												<button type="submit" name="wpat_export_snippets_only_btn" class="button button-secondary" title="Exportar todos los fragmentos">
													<span class="dashicons dashicons-upload" style="vertical-align: middle; font-size: 16px; width:16px; height:16px; margin-right: 3px;"></span> Exportar
												</button>
												<button type="button" class="button button-primary" id="wpat_add_new_snippet_btn">
													<span class="dashicons dashicons-plus" style="vertical-align: middle; font-size: 16px; width:16px; height:16px; margin-right: 3px;"></span> Añadir Nuevo
												</button>
											</div>
										</div>

										<table class="wp-list-table widefat fixed striped table-view-list" style="border: 1px solid #dcdcde; border-radius: 6px; overflow: hidden; margin-top: 10px; box-shadow: none;">
											<thead>
												<tr>
													<th style="width: 80px; font-weight: 700; padding: 10px;">Estado</th>
													<th style="font-weight: 700; padding: 10px;">Nombre</th>
													<th style="width: 100px; font-weight: 700; padding: 10px;">Tipo</th>
													<th style="width: 130px; font-weight: 700; text-align: right; padding: 10px;">Acciones</th>
												</tr>
											</thead>
											<tbody id="wpat_snippets_table_body">
												<?php
												$snippets = get_option( 'wpat_snippets', array() );
												$this->render_snippets_table_rows( $snippets );
												?>
											</tbody>
										</table>
									</div>

								</div>
							</div>

							<!-- Módulo: Performance Fixes -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Ajustes de Rendimiento</h3>
										<p>Desactiva los emojis integrados, remueve etiquetas meta innecesarias del <head> de WordPress y limita las revisiones por entrada a un máximo de 5.</p>
									</div>
									<?php $this->render_module_toggle( 'performance', $settings, false ); ?>
								</div>
							</div>

						</div>

						<!-- PESTAÑA 4: OPTIMIZACIÓN DE MEDIOS -->
						<div id="tab-media" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-media' ) ? 'active' : ''; ?>">
							<h2>Optimización de Medios</h2>
							<p class="section-desc">Mejora la gestión y compresión de archivos multimedia del sitio.</p>

							<!-- Módulo: SVG Support -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Soporte para Archivos SVG</h3>
										<p>Habilita la subida de archivos SVG en la Biblioteca de Medios aplicando una sanitización básica de seguridad XML (remueve tags de script e inyecciones XSS).</p>
									</div>
									<?php $this->render_module_toggle( 'svg-support', $settings, false ); ?>
								</div>
							</div>

							<!-- Módulo: Image Optimizer (WebP) -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Optimizador de Imágenes a WebP</h3>
										<p>Intercepta las subidas de imágenes, las escala a un ancho/alto máximo de 1920px, las convierte automáticamente al formato óptimo .webp (calidad 82%) y descarta los archivos originales pesados.</p>
									</div>
									<?php $this->render_module_toggle( 'image-optimizer', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<div class="wpat-bulk-optimizer-section" style="border-top: 1px dashed var(--wpat-border); padding-top: 20px; margin-top: 10px;">
										<h4 style="margin: 0 0 5px 0; font-size: 15px; font-weight: 600;">Optimización Retroactiva (Masiva)</h4>
										<p class="description" style="margin: 0 0 15px 0;">Escanea y convierte las imágenes existentes en la Biblioteca de Medios que aún no han sido convertidas a WebP.</p>
										
										<!-- Filtros del Optimizador Masivo -->
										<div class="wpat-bulk-filters" style="display: flex; flex-wrap: wrap; gap: 15px; background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px; border: 1px solid var(--wpat-border);">
											<div style="flex: 1; min-width: 200px;">
												<label for="wpat_bulk_filter_min_size" style="display:block; font-weight:600; margin-bottom:5px; font-size:12px;">Peso Mínimo de Imagen (en KB)</label>
												<input type="number" id="wpat_bulk_filter_min_size" placeholder="Ej: 500" min="0" style="width: 100%;" />
												<p class="description" style="font-size:11px; margin-top:2px;">Solo optimizar imágenes con un peso de archivo mayor o igual a este valor.</p>
											</div>
											<div style="flex: 1; min-width: 200px;">
												<label for="wpat_bulk_filter_date_start" style="display:block; font-weight:600; margin-bottom:5px; font-size:12px;">Fecha Mínima de Subida</label>
												<input type="date" id="wpat_bulk_filter_date_start" style="width: 100%;" />
												<p class="description" style="font-size:11px; margin-top:2px;">Solo optimizar imágenes subidas en esta fecha o después.</p>
											</div>
										</div>

										<div class="wpat-bulk-actions" style="display: flex; gap: 10px; align-items: center;">
											<button type="button" class="button" id="wpat_scan_images_btn">Escanear Biblioteca</button>
											<button type="button" class="button button-primary" id="wpat_start_bulk_btn" style="display:none;">Iniciar Optimización</button>
										</div>

										<div id="wpat_bulk_status" class="wpat-bulk-status-container" style="display:none; margin-top: 20px;">
											<div class="wpat-progress-bar-wrapper" style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
												<div class="wpat-progress-bar" style="flex: 1; height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden; border: 1px solid var(--wpat-border);">
													<div class="wpat-progress-bar-fill" id="wpat_bulk_progress_fill" style="width: 0%; height: 100%; background: var(--wpat-success); transition: width 0.3s ease;"></div>
												</div>
												<span class="wpat-progress-percent" id="wpat_bulk_progress_percent" style="font-weight: 700; font-size: 14px; min-width: 40px; text-align: right;">0%</span>
											</div>
											<div class="wpat-bulk-stats" style="margin-bottom: 15px; font-size: 13px; color: var(--wpat-text-light);">
												<span>Pendientes de optimizar: <strong id="wpat_stat_pending" style="color: var(--wpat-text);">0</strong></span> | 
												<span>Procesadas con éxito: <strong id="wpat_stat_processed" style="color: var(--wpat-success);">0</strong></span> | 
												<span>Errores/Omitidas: <strong id="wpat_stat_failed" style="color: #ea580c;">0</strong></span>
												<span id="wpat_stat_weight_container" style="display: none; margin-left: 10px; padding-left: 10px; border-left: 1px solid var(--wpat-border);">
													| Peso total: <strong id="wpat_stat_total_weight" style="color: var(--wpat-text);">0 B</strong> 
													| Peso estimado optimizado (WebP): <strong id="wpat_stat_opt_weight" style="color: var(--wpat-success);">0 B</strong>
												</span>
											</div>
											<div id="wpat_bulk_log" class="wpat-bulk-log-box" style="max-height: 120px; overflow-y: auto; background: #1e293b; color: #f8fafc; padding: 12px; font-family: monospace; font-size: 11px; border-radius: 6px; line-height: 1.4; border: 1px solid #334155;">
												[Consola de estado lista...]
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Módulo: Limpiador de Imágenes Huérfanas -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Limpiador de Imágenes Huérfanas (No Usadas)</h3>
										<p>Escanea tu Biblioteca de Medios y detecta imágenes que no están referenciadas en ninguna entrada, página, producto de WooCommerce, imagen destacada o diseño de Elementor. Te permite eliminarlas de forma segura para liberar espacio en el disco.</p>
									</div>
								</div>
								<div class="wpat-module-body">
									<div class="wpat-bulk-actions" style="display: flex; gap: 10px; align-items: center;">
										<button type="button" class="button button-secondary" id="wpat_scan_orphans_btn">Buscar Imágenes Huérfanas</button>
										<button type="button" class="button button-link-delete" id="wpat_delete_selected_orphans_btn" style="display:none;">Eliminar Seleccionadas</button>
									</div>

									<div id="wpat_orphans_status" class="wpat-bulk-status-container" style="display:none; margin-top: 20px;">
										<div class="wpat-progress-bar-wrapper" style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
											<div class="wpat-progress-bar" style="flex: 1; height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden; border: 1px solid var(--wpat-border);">
												<div class="wpat-progress-bar-fill" id="wpat_orphans_progress_fill" style="width: 0%; height: 100%; background: #0284c7; transition: width 0.3s ease;"></div>
											</div>
											<span class="wpat-progress-percent" id="wpat_orphans_progress_percent" style="font-weight: 700; font-size: 14px; min-width: 40px; text-align: right;">0%</span>
										</div>
										<div class="wpat-bulk-stats" style="margin-bottom: 15px; font-size: 13px; color: var(--wpat-text-light);">
											<span>Analizadas: <strong id="wpat_orphans_stat_scanned" style="color: var(--wpat-text);">0</strong></span> | 
											<span>Huérfanas encontradas: <strong id="wpat_orphans_stat_found" style="color: #ea580c;">0</strong></span>
										</div>
									</div>

									<div id="wpat_orphans_results_wrapper" style="display:none; margin-top: 20px; border-top: 1px solid var(--wpat-border); padding-top: 15px;">
										<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600;">Listado de Imágenes Huérfanas Detectadas</h4>
										<div style="max-height: 300px; overflow-y: auto; border: 1px solid #dcdcde; border-radius: 6px;">
											<table class="wp-list-table widefat fixed striped" style="box-shadow:none; border:none;">
												<thead>
													<tr>
														<th style="width: 40px; padding: 10px; text-align: center;"><input type="checkbox" id="wpat_select_all_orphans" /></th>
														<th style="width: 60px; padding: 10px; text-align: center;">Miniatura</th>
														<th style="padding: 10px;">Nombre del Archivo / Ruta</th>
														<th style="width: 100px; padding: 10px;">Tamaño</th>
														<th style="width: 100px; padding: 10px;">Fecha</th>
													</tr>
												</thead>
												<tbody id="wpat_orphans_table_body">
													<!-- Fila dinámica -->
												</tbody>
											</table>
										</div>
									</div>
								</div>
							</div>

						</div>

						<!-- PESTAÑA: CONFIGURACIÓN SMTP -->
						<div id="tab-smtp" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-smtp' ) ? 'active' : ''; ?>">
							<h2>Configuración SMTP</h2>
							<p class="section-desc">Enruta de forma segura y fiable los correos salientes de tu sitio web.</p>

							<!-- Módulo: Configuración SMTP -->
							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Servidor de Correo SMTP</h3>
										<p>Activa y define la conexión con tu proveedor SMTP (Gmail, SendGrid, Outlook, Mailgun o tu propio servidor de hosting).</p>
									</div>
									<?php $this->render_module_toggle( 'smtp', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									
									<div style="display: flex; flex-wrap: wrap; gap: 20px;">
										<div class="wpat-field-group" style="flex: 2; min-width: 250px;">
											<label for="wpat_smtp_host">Servidor SMTP (Host)</label>
											<input type="text" name="wpat_settings[smtp_host]" id="wpat_smtp_host" value="<?php echo esc_attr( $settings['smtp_host'] ); ?>" class="regular-text" placeholder="smtp.ejemplo.com" style="width:100%;" />
											<p class="description">El host SMTP provisto por tu proveedor de correo.</p>
										</div>

										<div class="wpat-field-group" style="flex: 1; min-width: 120px;">
											<label for="wpat_smtp_port">Puerto SMTP</label>
											<input type="text" name="wpat_settings[smtp_port]" id="wpat_smtp_port" value="<?php echo esc_attr( $settings['smtp_port'] ); ?>" class="regular-text" placeholder="465" style="width:100%;" />
											<p class="description">Generalmente: 465 (SSL), 587 (TLS/STARTTLS) o 25.</p>
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label for="wpat_smtp_secure">Cifrado de Seguridad</label>
										<select name="wpat_settings[smtp_secure]" id="wpat_smtp_secure">
											<option value="none" <?php selected( $settings['smtp_secure'], 'none' ); ?>>Ninguno (Sin cifrado)</option>
											<option value="ssl" <?php selected( $settings['smtp_secure'], 'ssl' ); ?>>SSL (Recomendado para puerto 465)</option>
											<option value="tls" <?php selected( $settings['smtp_secure'], 'tls' ); ?>>TLS / STARTTLS (Recomendado para puerto 587)</option>
										</select>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label>
											<input type="checkbox" name="wpat_settings[smtp_insecure]" value="1" <?php checked( $settings['smtp_insecure'], '1' ); ?>>
											Desactivar verificación de certificados SSL (Útil si tu hosting tiene problemas de verificación SSL/TLS)
										</label>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label>
											<input type="checkbox" name="wpat_settings[smtp_auth]" value="1" id="wpat_smtp_auth" <?php checked( $settings['smtp_auth'], '1' ); ?>>
											El servidor SMTP requiere autenticación
										</label>
									</div>

									<div class="wpat-smtp-auth-fields wpat-sub-field" <?php $this->style_conditional_display( $settings['smtp_auth'] ); ?>>
										<div style="display: flex; flex-wrap: wrap; gap: 20px;">
											<div class="wpat-field-group" style="flex: 1; min-width: 200px;">
												<label for="wpat_smtp_username">Usuario SMTP (Email completo)</label>
												<input type="text" name="wpat_settings[smtp_username]" id="wpat_smtp_username" value="<?php echo esc_attr( $settings['smtp_username'] ); ?>" class="regular-text" placeholder="usuario@ejemplo.com" style="width:100%;" />
											</div>
											<div class="wpat-field-group" style="flex: 1; min-width: 200px;">
												<label for="wpat_smtp_password">Contraseña SMTP</label>
												<input type="password" name="wpat_settings[smtp_password]" id="wpat_smtp_password" value="<?php echo esc_attr( $settings['smtp_password'] ); ?>" class="regular-text" placeholder="••••••••••••" style="width:100%;" />
											</div>
										</div>
									</div>

									<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

									<div style="display: flex; flex-wrap: wrap; gap: 20px;">
										<div class="wpat-field-group" style="flex: 1; min-width: 200px;">
											<label for="wpat_smtp_from_email">Email del Remitente (Opcional)</label>
											<input type="email" name="wpat_settings[smtp_from_email]" id="wpat_smtp_from_email" value="<?php echo esc_attr( $settings['smtp_from_email'] ); ?>" class="regular-text" placeholder="webmaster@ejemplo.com" style="width:100%;" />
											<p class="description">Forzará esta dirección en todos los emails salientes (evita rebotes).</p>
										</div>
										<div class="wpat-field-group" style="flex: 1; min-width: 200px;">
											<label for="wpat_smtp_from_name">Nombre del Remitente (Opcional)</label>
											<input type="text" name="wpat_settings[smtp_from_name]" id="wpat_smtp_from_name" value="<?php echo esc_attr( $settings['smtp_from_name'] ); ?>" class="regular-text" placeholder="Mi Sitio Web" style="width:100%;" />
											<p class="description">Forzará el nombre del remitente en el correo.</p>
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 25px; border-top: 1px solid var(--wpat-border); padding-top: 20px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary wpat-save-btn" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

							<!-- Caja de Correo de Prueba -->
							<div class="wpat-module-card" style="margin-top: 30px;">
								<div class="wpat-module-header" style="background:#f8fafc;">
									<div class="wpat-module-info">
										<h3 style="color:#0f172a;">Diagnóstico: Enviar correo de prueba</h3>
										<p>Introduce una dirección de destino para verificar la correcta comunicación con tu servidor SMTP.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="background:#ffffff;">
									<!-- Nonce de Seguridad para el test SMTP -->
									<?php wp_nonce_field( 'wpat_smtp_test_nonce_action', 'wpat_smtp_test_nonce' ); ?>
									
									<div style="display:flex; gap:12px; align-items:flex-end; max-width: 500px;">
										<div style="flex:1;">
											<label for="wpat_smtp_test_email" style="font-weight: 600; font-size:12px; margin-bottom:5px; display:block;">Email Destinatario</label>
											<input type="email" id="wpat_smtp_test_email" class="regular-text" placeholder="tu-email@dominio.com" style="width:100%;" />
										</div>
										<button type="button" class="button button-secondary" id="wpat_smtp_send_test_btn" style="height:30px;">Enviar Prueba</button>
									</div>
									<div id="wpat_smtp_test_result" style="display:none; margin-top:15px; padding:15px; border-radius:6px; font-size:13px; line-height:1.5; font-family: monospace;"></div>
								</div>
							</div>
						</div>

						<!-- PESTAÑA: SEO -->
						<div id="tab-seo" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-seo' ) ? 'active' : ''; ?>">
							<h2>Optimización SEO</h2>
							<p class="section-desc">Gestiona las etiquetas meta, sitemap y realiza auditorías de indexación para tus buscadores.</p>

							<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Módulo SEO Ultra-Ligero</h3>
										<p>Activa los meta campos de títulos y descripciones en tus páginas, Open Graph y el sitemap XML integrado.</p>
									</div>
									<?php $this->render_module_toggle( 'seo', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: none;">
									<!-- Explicación de Funcionalidades -->
									<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
										<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
											<span class="dashicons dashicons-info" style="color: #3b82f6; font-size: 18px; width: 18px; height: 18px; margin: 0;"></span>
											¿Qué activa este módulo en tu sitio?
										</h4>
										<ul style="margin: 0; padding: 0 0 0 20px; list-style-type: disc; font-size: 12.5px; color: #475569; line-height: 1.6;">
											<li><strong>Campos SEO en el Editor:</strong> Añade una sección al final de la edición de tus páginas, entradas y tipos de contenido personalizados (CPT) para configurar el título SEO, el slug, la meta descripción y las directivas de rastreo (`noindex`).</li>
											<li><strong>Previsualización de Google en Vivo:</strong> Te permite ver en tiempo real cómo se mostrará tu enlace en los resultados de Google (tanto en su formato de teléfono móvil como en ordenador de escritorio) a medida que escribes.</li>
											<li><strong>Optimización en Redes Sociales (Open Graph):</strong> Inyecta de forma automática los metadatos necesarios para que, al compartir el enlace de tu web en WhatsApp, Telegram, LinkedIn o Facebook, este aparezca con una imagen de portada atractiva, título personalizado y descripción corta.</li>
											<li><strong>Sitemap XML Dinámico:</strong> Activa tu sitemap XML en la raíz de la web (`/sitemap.xml`) excluyendo automáticamente cualquier entrada o página que configures como `noindex`.</li>
										</ul>
									</div>

									<!-- Configuración del Sitemap XML -->
									<div class="wpat-field-group" style="margin-top: 15px;">
										<label>Sitemap XML Integrado</label>
										<p class="description" style="margin-bottom: 10px;">El sitemap se genera dinámicamente y excluye automáticamente las páginas configuradas como noindex.</p>
										<div style="display: flex; gap: 10px; flex-wrap: wrap;">
											<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" class="button button-secondary">
												<span class="dashicons dashicons-external" style="vertical-align: middle; font-size: 16px; width: 16px; height: 16px; margin-right: 5px;"></span> Ver Sitemap.xml
											</a>
											<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" download="sitemap.xml" class="button button-secondary">
												<span class="dashicons dashicons-download" style="vertical-align: middle; font-size: 16px; width: 16px; height: 16px; margin-right: 5px;"></span> Descargar Sitemap.xml
											</a>
										</div>
									</div>
									
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" />
									</div>
								</div>
							</div>

							<?php if ( isset( $settings['seo'] ) && '1' === $settings['seo'] ) : ?>
								<!-- Generador SEO en Masa (Auto-rellenado) -->
								<div class="wpat-module-card" style="margin-top: 20px;">
									<div style="padding: 20px;">
										<h3 style="margin-top:0; display: flex; align-items: center; gap: 8px;">
											<span class="dashicons dashicons-forms" style="color: var(--wpat-primary); font-size: 20px; width: 20px; height: 20px; margin: 0;"></span>
											Generador SEO en Masa
										</h3>
										<p class="description">Rellena automáticamente los campos vacíos de <strong>Título SEO</strong> (formato "[Título] - [Nombre Sitio]") y <strong>Meta Descripción</strong> (primeros 150 caracteres del contenido) de tus páginas ya existentes.</p>
										
										<div style="margin-top: 15px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0;">
											<div style="display: flex; flex-direction: column; gap: 4px;">
												<label style="font-weight: 700; font-size: 12px; color: #475569;">Optimizar en masa el tipo de contenido:</label>
												<select id="wpat_seo_bulk_post_type" style="height: 32px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 12px; background: #fff; min-width: 180px; margin:0;">
													<option value="all">Todos los tipos públicos</option>
													<?php
													$post_types = get_post_types( array( 'public' => true ), 'objects' );
													if ( isset( $post_types['attachment'] ) ) {
														unset( $post_types['attachment'] );
													}
													foreach ( $post_types as $pt ) {
														echo '<option value="' . esc_attr( $pt->name ) . '">' . esc_html( $pt->labels->name ) . '</option>';
													}
													?>
												</select>
											</div>
											<div style="display: flex; align-items: flex-end; margin-bottom: 0;">
												<button type="button" id="wpat_seo_bulk_fill_btn" class="button button-primary" style="height: 32px; line-height: 30px; background: #10b981; border-color: #059669;">Auto-rellenar Campos Vacíos</button>
											</div>
											<span id="wpat_seo_bulk_status" style="font-size: 12.5px; font-weight: 600; color: #64748b; line-height: 32px;"></span>
										</div>

										<div id="wpat_seo_bulk_progress" style="display: none; margin-top: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px;">
											<div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; font-weight: 600;">
												<span id="wpat_seo_bulk_progress_label">Identificando contenidos sin SEO...</span>
												<span id="wpat_seo_bulk_progress_percent">0%</span>
											</div>
											<div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; border: 1px solid rgba(0,0,0,0.03);">
												<div id="wpat_seo_bulk_progress_bar" style="width: 0%; height: 100%; background: #10b981; transition: width 0.3s ease; border-radius: 3px;"></div>
											</div>
										</div>
									</div>
								</div>

								<!-- Auditoría SEO del Sitio -->
								<div class="wpat-module-card" style="margin-top: 20px;">
									<div style="padding: 20px;">
										<h3 style="margin-top:0;">Auditoría SEO del Sitio</h3>
										<p class="description">Escanea las páginas y entradas del sitio para detectar problemas de indexación y campos SEO vacíos o incorrectos.</p>
										
										<!-- Selector de Tipo de Contenido (Pre-escaneo) -->
										<div style="margin-top: 15px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0;">
											<div style="display: flex; flex-direction: column; gap: 4px;">
												<label for="wpat_seo_filter_post_type" style="font-weight: 700; font-size: 12px; color: #475569;">Tipo de contenido a escanear:</label>
												<select id="wpat_seo_filter_post_type" style="height: 32px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 12px; background: #fff; min-width: 180px; margin:0;">
													<option value="all">Todos los tipos públicos</option>
													<?php
													$post_types = get_post_types( array( 'public' => true ), 'objects' );
													if ( isset( $post_types['attachment'] ) ) {
														unset( $post_types['attachment'] );
													}
													foreach ( $post_types as $pt ) {
														echo '<option value="' . esc_attr( $pt->name ) . '">' . esc_html( $pt->labels->name ) . '</option>';
													}
													?>
												</select>
											</div>
											<div style="display: flex; align-items: flex-end; margin-bottom: 0;">
												<button type="button" id="wpat_seo_scan_btn" class="button button-primary" style="height: 32px; line-height: 30px;">Escanear Sitio Ahora</button>
											</div>
											<span id="wpat_seo_scan_status" style="font-size: 12.5px; font-weight: 600; color: #64748b; line-height: 32px;"></span>
										</div>

										<div id="wpat_seo_scan_progress" style="display: none; margin-top: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px;">
											<div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; font-weight: 600;">
												<span id="wpat_seo_progress_label">Iniciando análisis...</span>
												<span id="wpat_seo_progress_percent">0%</span>
											</div>
											<div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; border: 1px solid rgba(0,0,0,0.03);">
												<div id="wpat_seo_progress_bar" style="width: 0%; height: 100%; background: #3b82f6; transition: width 0.3s ease; border-radius: 3px;"></div>
											</div>
										</div>

										<!-- Controles de Filtros Dinámicos de Tabla (Post-escaneo) -->
										<div id="wpat_seo_table_filters" style="display: none; justify-content: space-between; align-items: center; margin-top: 20px; margin-bottom: 12px; gap: 15px; flex-wrap: wrap;">
											<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex: 1;">
												<input type="text" id="wpat_seo_table_search" placeholder="🔍 Buscar página por título..." style="height: 32px; padding: 4px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 12.5px; flex: 1; max-width: 300px; margin:0;" />
												
												<select id="wpat_seo_table_status_filter" style="height: 32px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 12.5px; background: #fff; margin:0;">
													<option value="all">Todos los resultados</option>
													<option value="issues">Con problemas (Título o Meta Vacíos/Alertas)</option>
													<option value="no-keyword">Sin Frase Clave Objetivo</option>
													<option value="optimized">Completamente Optimizados</option>
												</select>
											</div>
											<div style="font-size: 12.5px; color: #64748b; font-weight: 600;" id="wpat_seo_table_counter_label">
												Mostrando <span id="wpat_seo_filtered_count">0</span> de <span id="wpat_seo_total_count">0</span> páginas.
											</div>
										</div>

										<div id="wpat_seo_audit_results" style="margin-top: 10px; overflow-x: auto; display:none;">
											<table class="wp-list-table widefat fixed striped" style="border: 1px solid #cbd5e1; border-radius: 4px; box-shadow: none; margin-bottom: 0;">
												<thead>
													<tr>
														<th style="font-weight:700; width: 35%; padding: 10px;">Página / Entrada</th>
														<th style="font-weight:700; width: 12%; padding: 10px;">Indexable</th>
														<th style="font-weight:700; width: 18%; padding: 10px;">Frase Clave</th>
														<th style="font-weight:700; width: 18%; padding: 10px;">Título SEO</th>
														<th style="font-weight:700; width: 17%; padding: 10px;">Meta Descripción</th>
													</tr>
												</thead>
												<tbody id="wpat_seo_audit_table_body">
													<!-- Rellenado por AJAX -->
												</tbody>
											</table>

											<!-- Paginación SEO Audit -->
											<div id="wpat_seo_audit_pagination" style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 10px 15px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 13px;">
												<div style="font-weight: 600; color: #475569; display: flex; align-items: center; gap: 8px;">
													Página <span id="wpat_seo_audit_page_num">1</span> de <span id="wpat_seo_audit_total_pages">1</span>
													<span style="color: #cbd5e1; margin: 0 4px;">|</span>
													Ir a la página:
													<input type="number" id="wpat_seo_audit_goto_page" min="1" max="1" value="1" style="width: 60px; height: 26px; padding: 0 5px; font-size: 12px; border-radius: 4px; border: 1px solid #cbd5e1; text-align: center; margin: 0; display: inline-block;">
												</div>
												<div style="display: flex; gap: 8px;">
													<button type="button" id="wpat_seo_audit_prev_btn" class="button button-small" disabled>Anterior</button>
													<button type="button" id="wpat_seo_audit_next_btn" class="button button-small" disabled>Siguiente</button>
												</div>
											</div>
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>

						<!-- PESTAÑA: INTEGRACIONES -->
						<div id="tab-integrations" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-integrations' ) ? 'active' : ''; ?>">
							<h2>Integraciones de Terceros</h2>
							<p class="section-desc">Gestiona e inyecta códigos de herramientas externas en tu web de forma ultra-ligera, limpia y sin sobrecargar la web.</p>

							<!-- Tarjeta: Google Search Console -->
							<div class="wpat-module-card">
								<?php
								// Comprobar si hay un archivo de verificación en el directorio raíz (ABSPATH)
								$google_files = glob( ABSPATH . 'google*.html' );
								$google_file_found = false;
								$google_file_name = '';
								if ( ! empty( $google_files ) ) {
									foreach ( $google_files as $file ) {
										$filename = basename( $file );
										if ( preg_match( '/^google[a-f0-9]+\.html$/i', $filename ) ) {
											$google_file_found = true;
											$google_file_name = $filename;
											break;
										}
									}
								}

								$sc_connected = ! empty( $settings['google_search_console_code'] ) || $google_file_found;
								?>
								<div class="wpat-module-header" style="border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Google Search Console</h3>
											<?php if ( $sc_connected ) : ?>
												<span class="wpat-status-indicator" style="background: #e6f4ea; color: #137333; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
													<span style="width: 6px; height: 6px; background: #137333; border-radius: 50%;"></span> 
													<?php 
													if ( $google_file_found ) {
														echo 'Conectado (Archivo: ' . esc_html( $google_file_name ) . ')';
													} else {
														echo 'Conectado (Metaetiqueta)';
													}
													?>
												</span>
											<?php else : ?>
												<span class="wpat-status-indicator" style="background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
													<span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span> Sin configurar
												</span>
											<?php endif; ?>
										</div>
										<p style="margin: 0 0 12px 0; color: #64748b; font-size: 13px;">El plugin detecta automáticamente si has subido un archivo de verificación de Google (tipo <code>googleXXXX.html</code>) a la carpeta raíz de tu hosting, o si prefieres puedes pegar el código meta abajo. Puedes conseguir tu código en <a href="https://search.google.com/search-console/welcome" target="_blank" rel="noopener noreferrer" style="color: var(--wpat-primary); font-weight: 600; text-decoration: underline;">Google Search Console</a>.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="padding: 0 20px 20px 20px;">
									<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
										<div style="flex: 1; min-width: 300px;">
											<input type="text" name="wpat_settings[google_search_console_code]" id="wpat_google_search_console_code" value="<?php echo esc_attr( $settings['google_search_console_code'] ); ?>" class="large-text" placeholder="Ej: <meta name=&quot;google-site-verification&quot; content=&quot;xyz123...&quot; />" style="width:100%; margin:0;" />
										</div>
										<a href="<?php echo esc_url( 'https://search.google.com/search-console?resource_id=' . urlencode( home_url( '/' ) ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="height: 30px; display: inline-flex; align-items: center; gap: 5px;">
											<span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px; margin: 0;"></span> Acceder a Search Console
										</a>
									</div>
								</div>
							</div>

							<!-- Tarjeta: Google Analytics (GA4) -->
							<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header" style="border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Google Analytics (GA4)</h3>
											<?php if ( ! empty( $settings['google_analytics_id'] ) && preg_match( '/^G-[A-Z0-9]+$/i', trim( $settings['google_analytics_id'] ) ) ) : ?>
												<span class="wpat-status-indicator" style="background: #e6f4ea; color: #137333; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
													<span style="width: 6px; height: 6px; background: #137333; border-radius: 50%;"></span> Conectado
												</span>
											<?php else : ?>
												<span class="wpat-status-indicator" style="background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
													<span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span> Sin configurar
												</span>
											<?php endif; ?>
										</div>
										<p style="margin: 0 0 12px 0; color: #64748b; font-size: 13px;">Introduce tu ID de medición de Google Analytics 4 (debe comenzar con <code>G-</code>). Puedes conseguir tu ID G-XXXX en la sección de flujos de datos de administración de <a href="https://analytics.google.com/analytics/web/#/admin" target="_blank" rel="noopener noreferrer" style="color: var(--wpat-primary); font-weight: 600; text-decoration: underline;">Google Analytics</a>.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="padding: 0 20px 20px 20px;">
									<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
										<div style="flex: 1; min-width: 300px;">
											<input type="text" name="wpat_settings[google_analytics_id]" id="wpat_google_analytics_id" value="<?php echo esc_attr( $settings['google_analytics_id'] ); ?>" class="large-text" placeholder="Ej: G-XXXXXXXXXX" style="width:100%; margin:0;" />
										</div>
										<a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="height: 30px; display: inline-flex; align-items: center; gap: 5px;">
											<span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px; margin: 0;"></span> Acceder a Google Analytics
										</a>
									</div>
								</div>
							</div>

							<!-- Tarjeta: Google PageSpeed Insights -->
							<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header" style="border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Google PageSpeed Insights</h3>
											<span class="wpat-status-indicator" style="background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
												<span style="width: 6px; height: 6px; background: #0369a1; border-radius: 50%;"></span> Listo
											</span>
										</div>
										<p style="margin: 0 0 12px 0; color: #64748b; font-size: 13px;">Audita el rendimiento, la velocidad de carga real y la optimización móvil/escritorio del sitio web de forma externa y gratuita en Google PageSpeed.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="padding: 0 20px 20px 20px;">
									<div>
										<a href="<?php echo esc_url( 'https://pagespeed.web.dev/analysis?url=' . urlencode( home_url( '/' ) ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" style="height: 32px; display: inline-flex; align-items: center; gap: 5px;">
											<span class="dashicons dashicons-performance" style="font-size: 16px; width: 16px; height: 16px; margin: 0;"></span> Analizar Velocidad del Sitio
										</a>
									</div>
								</div>
							</div>

							<div style="margin-top: 30px; border-top: 1px dashed var(--wpat-border); padding-top: 20px;">
								<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" style="height: 36px; padding: 0 20px;" />
							</div>
						</div>

						<!-- PESTAÑA: CONFIGURACIÓN INICIAL -->
						<div id="tab-initial-setup" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-initial-setup' ) ? 'active' : ''; ?>">
							<h2>Asistente de Configuración Inicial</h2>
							<p class="section-desc">Puesta a punto y limpieza rápida para nuevas instalaciones de WordPress. Selecciona las acciones que deseas realizar y ejecútalas en un solo paso.</p>

							<div class="wpat-module-card" style="padding: 20px;">
								<h3 style="margin-top: 0; font-size: 15px; border-bottom: 1px solid var(--wpat-border); padding-bottom: 12px; margin-bottom: 15px;">Acciones de Puesta a Punto</h3>

								<!-- Limpieza de contenido -->
								<div class="wpat-field-group" style="margin-bottom: 20px;">
									<label style="font-weight: 600; display: block; margin-bottom: 8px;">1. Limpieza de Contenido por Defecto</label>
									<div style="margin-left: 10px; display: flex; flex-direction: column; gap: 8px;">
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[delete_post]" value="1" class="wpat-init-action-checkbox" />
											Eliminar entrada de ejemplo "Hola mundo"
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[delete_page]" value="1" class="wpat-init-action-checkbox" />
											Eliminar "Página de ejemplo" (Sample Page)
										</label>
									</div>
								</div>

								<!-- Creación de páginas -->
								<div class="wpat-field-group" style="margin-bottom: 20px; border-top: 1px dotted var(--wpat-border); padding-top: 15px;">
									<label style="font-weight: 600; display: block; margin-bottom: 8px;">2. Crear Estructura de Páginas Básicas</label>
									
									<div style="display: flex; justify-content: space-between; align-items: center; margin: 0 0 12px 10px; flex-wrap: wrap; gap: 10px;">
										<p class="description" style="margin: 0;">Marca las páginas individuales que deseas que el asistente cree automáticamente en tu sitio:</p>
										<label style="font-weight: 600; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; padding: 4px 10px; border-radius: 4px; border: 1px solid var(--wpat-border); color: #475569;">
											<input type="checkbox" name="wpat_init[create_pages]" id="wpat_init_create_pages" value="1" class="wpat-init-action-checkbox" style="margin: 0;" />
											Seleccionar todo
										</label>
									</div>
									<div style="margin-left: 10px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid var(--wpat-border);">
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[pages_list][]" value="home" class="wpat-init-page-checkbox" />
											Inicio (y establecer como Portada)
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[pages_list][]" value="about" class="wpat-init-page-checkbox" />
											Quiénes somos
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[pages_list][]" value="services" class="wpat-init-page-checkbox" />
											Servicios
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[pages_list][]" value="contact" class="wpat-init-page-checkbox" />
											Contacto
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[pages_list][]" value="legal" class="wpat-init-page-checkbox" />
											Aviso legal
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[pages_list][]" value="privacy" class="wpat-init-page-checkbox" />
											Política de privacidad
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[pages_list][]" value="cookies" class="wpat-init-page-checkbox" />
											Política de cookies
										</label>
									</div>

									<div style="margin-top: 15px; margin-left: 10px;">
										<label for="wpat_init_custom_pages" style="font-weight: 500; font-size: 13px; display: block; margin-bottom: 6px; color: #475569;">
											Crear páginas personalizadas adicionales (separadas por comas):
										</label>
										<input type="text" name="wpat_init[custom_pages]" id="wpat_init_custom_pages" class="large-text" placeholder="Ej: Blog, Portfolio, Preguntas Frecuentes, Tienda" style="width: 100%; max-width: 600px; margin: 0; height: 32px;" />
										<p class="description" style="margin-top: 4px;">Introduce los nombres de las páginas adicionales que quieras crear separándolos con comas.</p>
									</div>
								</div>

								<!-- Temas y Plugins -->
								<div class="wpat-field-group" style="margin-bottom: 20px; border-top: 1px dotted var(--wpat-border); padding-top: 15px;">
									<label style="font-weight: 600; display: block; margin-bottom: 8px;">3. Temas y Plugins de Trabajo</label>
									<div style="margin-left: 10px; display: flex; flex-direction: column; gap: 8px;">
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[clean_themes]" value="1" class="wpat-init-action-checkbox" />
											Eliminar todos los temas inactivos (Conservar solo el tema activo actual)
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[install_hello]" value="1" class="wpat-init-action-checkbox" />
											Instalar y activar tema oficial "Hello Elementor"
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[install_elementor]" value="1" class="wpat-init-action-checkbox" />
											Instalar y activar plugin gratuito "Elementor"
										</label>
									</div>
								</div>

								<!-- Ajustes generales -->
								<div class="wpat-field-group" style="margin-bottom: 20px; border-top: 1px dotted var(--wpat-border); padding-top: 15px;">
									<label style="font-weight: 600; display: block; margin-bottom: 8px;">4. Optimización de Ajustes del Sistema</label>
									<div style="margin-left: 10px; display: flex; flex-direction: column; gap: 8px;">
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[media_sizes]" value="1" class="wpat-init-action-checkbox" />
											Optimizar tamaños de medios (Miniatura 300x300, Medio 800x800, Grande 1920x1080)
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[permalinks]" value="1" class="wpat-init-action-checkbox" />
											Cambiar Enlaces Permanentes a "Nombre de la entrada" (postname)
										</label>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_init[discourage_indexing]" value="1" class="wpat-init-action-checkbox" />
											Disuadir indexación a motores de búsqueda (Ajustes de Lectura)
										</label>
									</div>
								</div>

								<!-- Botón de ejecución -->
								<div style="margin-top: 25px; border-top: 1px dashed var(--wpat-border); padding-top: 20px;">
									<input type="submit" name="wpat_run_initial_setup" id="wpat_run_initial_setup_btn" class="button button-primary" value="Ejecutar Configuración Inicial" style="height: 38px; padding: 0 25px;" />
								</div>

							</div>
						</div>

						<!-- PESTAÑA 5: SALUD Y BASE DE DATOS -->
						<div id="tab-health" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-health' ) ? 'active' : ''; ?>">
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
								<h2 style="margin:0;">Salud & Base de Datos</h2>
								<button type="button" class="button" id="wpat_refresh_health_btn">
									<span class="dashicons dashicons-update" style="vertical-align: middle; font-size:16px; width:16px; height:16px; margin-right:5px;"></span> Actualizar Datos
								</button>
							</div>
							<p class="section-desc">Monitorea los límites clave del servidor PHP y limpia los residuos acumulados en tu base de datos de WordPress.</p>

							<!-- Nonce para acciones de Base de Datos -->
							<?php wp_nonce_field( 'wpat_cleanup_nonce_action', 'wpat_cleanup_ajax_nonce' ); ?>

							<div id="wpat_health_content_wrapper">
								<?php $this->render_health_tab_content(); ?>
							</div>
						</div>

						<!-- PESTAÑA 6: IMPORTADOR DE KITS -->
						<div id="tab-kits" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-kits' ) ? 'active' : ''; ?>">
							<h2>Importador de Kits de Plantillas (Envato)</h2>
							<p class="section-desc">Sube tus archivos ZIP de kits de plantillas de Envato Elements para gestionarlos e importarlos en Elementor.</p>

							<!-- Formulario de carga AJAX de ZIP -->
							<div class="wpat-module-card" style="padding: 24px; text-align: center; border: 2px dashed var(--wpat-border); background: #f8fafc; border-radius: 8px; margin-bottom: 24px;" id="wpat_kit_dragdrop_zone">
								<span class="dashicons dashicons-cloud-upload" style="font-size: 48px; width: 48px; height: 48px; color: #94a3b8; margin-bottom: 12px; display: inline-block;"></span>
								<h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600;">Sube tu archivo ZIP de Kit de Plantilla</h3>
								<p class="description" style="margin: 0 0 16px 0;">Arrastra tu archivo ZIP aquí o haz clic en el botón para seleccionarlo desde tu ordenador.</p>
								
								<input type="file" id="wpat_kit_file_input" style="display: none;" accept=".zip" />
								<button type="button" class="button button-primary" id="wpat_select_kit_zip_btn">Seleccionar ZIP de Kit</button>
								
								<div id="wpat_kit_upload_progress" style="display: none; margin-top: 15px; max-width: 400px; margin-left: auto; margin-right: auto;">
									<div class="wpat-progress-bar" style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; border: 1px solid var(--wpat-border);">
										<div class="wpat-progress-bar-fill" id="wpat_kit_upload_bar" style="width: 0%; height: 100%; background: var(--wpat-primary);"></div>
									</div>
									<span style="font-size: 12px; color: var(--wpat-text-light); margin-top: 5px; display: block;" id="wpat_kit_upload_status">Subiendo...</span>
								</div>
							</div>

							<!-- Nonce para acciones de importador -->
							<?php wp_nonce_field( 'wpat_envato_importer_nonce', 'wpat_envato_importer_nonce' ); ?>

							<!-- Listado de Kits Instalados -->
							<h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;"><span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Kits Instalados</h3>
							<div class="wpat-kits-list-wrapper" id="wpat_installed_kits_container">
								<?php
								$kits = get_option( 'wpat_envato_kits', array() );
								if ( empty( $kits ) ) {
									?>
									<div class="wpat-empty-kits" style="background:#ffffff; border: 1px solid var(--wpat-border); padding: 40px; border-radius: 8px; text-align: center; color: #64748b;">
										<p style="margin: 0; font-size:14px;">No hay ningún kit de plantilla instalado.</p>
										<p style="margin: 5px 0 0 0; font-size:12px; color: #94a3b8;">Usa el cargador de arriba para subir tu primer kit comprimido en ZIP.</p>
									</div>
									<?php
								} else {
									?>
									<div class="wpat-kits-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
										<?php foreach ( $kits as $slug => $kit ) : ?>
											<div class="wpat-module-card wpat-kit-card" style="margin: 0; padding: 0; overflow:hidden; display: flex; flex-direction: column; border: 1px solid var(--wpat-border);" data-slug="<?php echo esc_attr( $slug ); ?>">
												<?php if ( ! empty( $kit['thumbnail'] ) ) : ?>
													<div class="wpat-kit-cover" style="height: 140px; background-image: url('<?php echo esc_url( $kit['thumbnail'] ); ?>'); background-size: cover; background-position: center; border-bottom: 1px solid var(--wpat-border);"></div>
												<?php else : ?>
													<div class="wpat-kit-cover" style="height: 140px; background: linear-gradient(135deg, #f1f5f9 0%, #cbd5e1 100%); display: flex; align-items: center; justify-content: center; border-bottom: 1px solid var(--wpat-border);">
														<span class="dashicons dashicons-download" style="font-size: 48px; width:48px; height:48px; color: #94a3b8;"></span>
													</div>
												<?php endif; ?>
												<div style="padding: 20px; display: flex; flex-direction: column; flex-grow: 1;">
													<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 12px;">
														<h4 style="margin: 0; font-size: 15px; font-weight: 600; color: var(--wpat-text);"><?php echo esc_html( $kit['title'] ); ?></h4>
														<button type="button" class="wpat-delete-kit-btn button-link-delete" data-slug="<?php echo esc_attr( $slug ); ?>" title="Eliminar Kit Completo" style="border:none; background:none; cursor:pointer; padding:0; color:#ea580c;"><span class="dashicons dashicons-trash" style="font-size:18px;"></span></button>
													</div>
													<div style="display: flex; gap: 5px; margin-bottom: 15px; flex-wrap: wrap;">
														<span class="wpat-badge" style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
															<?php echo esc_html( count( $kit['templates'] ) ); ?> plantillas
														</span>
														<?php if ( ! empty( $kit['required_plugins'] ) ) : ?>
															<span class="wpat-badge" style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
																<?php echo esc_html( count( $kit['required_plugins'] ) ); ?> plugins necesarios
															</span>
														<?php else : ?>
															<span class="wpat-badge" style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
																0 plugins necesarios
															</span>
														<?php endif; ?>
													</div>
													<button type="button" class="button wpat-view-kit-templates-btn" data-slug="<?php echo esc_attr( $slug ); ?>" style="width: 100%; margin-top: auto;">Ver Plantillas</button>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
									<?php
								}
								?>
							</div>

							<!-- Contenedor Detalle de Plantillas de un Kit (oculto por defecto) -->
							<div id="wpat_kit_templates_detail_wrapper" style="display: none; margin-top: 35px; border-top: 1px solid var(--wpat-border); padding-top: 25px;">
								<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
									<h3 style="margin:0; font-size: 16px; font-weight: 600;" id="wpat_active_kit_title">Plantillas del Kit</h3>
									<button type="button" class="button button-secondary" id="wpat_close_kit_detail_btn">Volver a los Kits</button>
								</div>
								<!-- Contenedor de Plugins Requeridos -->
								<div id="wpat_kit_plugins_container" style="display:none; margin-bottom: 25px;"></div>

								<div class="wpat-templates-grid" id="wpat_templates_grid_container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
									<!-- Tarjetas dinámicas inyectadas vía JS -->
								</div>
							</div>

						</div>

						<!-- PESTAÑA: EXPORTACIÓN & IMPORTACIÓN -->
						<div id="tab-tools" class="wpat-tab-panel <?php echo ( $active_tab === 'tab-tools' ) ? 'active' : ''; ?>">
							<h2>Exportación & importación</h2>
							<p class="section-desc">Exporta e importa contenidos completos del sitio (páginas, entradas, productos WooCommerce y biblioteca de medios) en un archivo JSON estructurado para migraciones y backups.</p>

							<div class="wpat-field-group-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
								
								<!-- TARJETA: EXPORTAR -->
								<div class="wpat-module-card" style="padding: 20px; display: flex; flex-direction: column; justify-content: space-between; min-height: 380px;">
									<div>
										<h3 style="margin-top: 0; font-size:16px; font-weight:600; display: flex; align-items: center; gap: 8px;">
											<span class="dashicons dashicons-external" style="color: var(--wpat-primary); font-size: 20px; width: 20px; height: 20px; margin: 0;"></span>
											Exportador de Contenidos (JSON)
										</h3>
										<p class="description" style="margin-bottom: 20px;">Genera un archivo JSON con los datos estructurados. Útil para duplicar configuraciones o migrar posts de staging a producción.</p>
										
										<div style="background: #f8fafc; border: 1px solid var(--wpat-border); padding: 15px; border-radius: 6px; display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
											<label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_export_all" value="1" id="wpat_export_all_checkbox" />
												Exportar todo el contenido público
											</label>

											<div id="wpat_export_types_wrapper" style="display: flex; padding-left: 20px; flex-direction: column; gap: 8px; border-left: 2px solid #cbd5e1; margin-top: 5px;">
												<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
													<input type="checkbox" name="wpat_export_post_types[]" value="page" /> Páginas
												</label>
												<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
													<input type="checkbox" name="wpat_export_post_types[]" value="post" /> Entradas (Blog)
												</label>
												<?php if ( class_exists( 'WooCommerce' ) ) : ?>
													<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
														<input type="checkbox" name="wpat_export_post_types[]" value="product" /> Productos (WooCommerce)
													</label>
												<?php endif; ?>
												
												<?php
												$custom_post_types = get_post_types( array( '_builtin' => false ), 'objects' );
												if ( isset( $custom_post_types['product'] ) ) {
													unset( $custom_post_types['product'] );
												}
												if ( isset( $custom_post_types['jet-engine'] ) ) {
													unset( $custom_post_types['jet-engine'] );
												}
												foreach ( $custom_post_types as $cpt ) :
													?>
													<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
														<input type="checkbox" name="wpat_export_post_types[]" value="<?php echo esc_attr( $cpt->name ); ?>" /> <?php echo esc_html( $cpt->labels->name ); ?> (CPT)
													</label>
													<?php
												endforeach;
												?>
												
												<?php if ( post_type_exists( 'jet-engine' ) ) : ?>
													<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
														<input type="checkbox" name="wpat_export_post_types[]" value="jet-engine" /> Plantillas/Listings (JetEngine)
													</label>
												<?php endif; ?>
											</div>

											<hr style="border: none; border-top: 1px dotted var(--wpat-border); margin: 5px 0 0 0;" />

											<label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_export_media" value="1" />
												Incluir adjuntos de biblioteca multimedia
											</label>

											<label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_export_seo" value="1" />
												Incluir datos y optimizaciones SEO
											</label>

											<label style="font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_export_snippets" value="1" />
												Incluir fragmentos de código (Snippets)
											</label>
										</div>
									</div>
									<div style="margin-top: auto;">
										<input type="submit" name="wpat_export_contents_btn" class="button button-primary" value="Generar y Descargar JSON" style="width: 100%; height: 38px; font-weight:600;" />
									</div>
								</div>

								<!-- TARJETA: IMPORTAR -->
								<div class="wpat-module-card" style="padding: 20px; display: flex; flex-direction: column; justify-content: space-between; min-height: 380px;">
									<div>
										<h3 style="margin-top: 0; font-size:16px; font-weight:600; display: flex; align-items: center; gap: 8px;">
											<span class="dashicons dashicons-download" style="color: var(--wpat-primary); font-size: 20px; width: 20px; height: 20px; margin: 0;"></span>
											Importador de Contenidos (JSON)
										</h3>
										<p class="description" style="margin-bottom: 20px;">Sube el archivo JSON exportado por este plugin. Se crearán o actualizarán las páginas, entradas, productos y metas de forma nativa.</p>
										
										<div style="border: 2px dashed var(--wpat-border); background: #f8fafc; padding: 25px 15px; border-radius: 8px; text-align: center; margin-bottom: 15px;" id="wpat_import_dropzone">
											<span class="dashicons dashicons-document" style="font-size: 36px; width: 36px; height: 36px; color: #94a3b8; margin-bottom: 8px; display: inline-block;"></span>
											<h4 style="margin: 0 0 5px 0; font-size: 13.5px; font-weight: 600;" id="wpat_import_file_label">Selecciona tu archivo .json</h4>
											<p class="description" style="margin: 0 0 15px 0; font-size: 11px;">Tamaño máx: <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></p>
											
											<input type="file" name="wpat_import_file" id="wpat_import_file_field" style="display: none;" accept=".json" />
											<button type="button" class="button" id="wpat_select_import_file_btn">Elegir Archivo</button>
										</div>

										<div id="wpat_import_file_feedback" style="display: none; background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1; padding: 10px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 6px;">
											<span class="dashicons dashicons-yes" style="color: #0284c7; margin: 0;"></span>
											<span>Seleccionado: <strong id="wpat_import_feedback_name">ninguno</strong></span>
										</div>
									</div>
									<div style="margin-top: auto;">
										<input type="submit" name="wpat_import_contents_btn" id="wpat_import_contents_submit_btn" class="button button-primary" value="Iniciar Importación" style="width: 100%; height: 38px; font-weight:600;" disabled />
									</div>
								</div>

							</div>
						</div>

					</div>
				</div>

			</form>
		</div>
		<?php
	}

	/**
	 * Helper para imprimir un estilo condicional CSS en base a si una opción está activa.
	 *
	 * @param string $setting Valor de la configuración ('0' o '1').
	 */
	public function style_conditional_display( $setting ) {
		if ( '1' !== $setting ) {
			echo 'style="display:none;"';
		}
	}

	/**
	 * Renderiza el conmutador de switch y el botón de colapso si aplica.
	 *
	 * @param string $module_id ID del módulo.
	 * @param array  $settings  Ajustes actuales.
	 * @param bool   $has_body  Indica si el módulo tiene panel de opciones.
	 */
	public function render_module_toggle( $module_id, $settings, $has_body = false ) {
		$is_active = ( isset( $settings[ $module_id ] ) && '1' === $settings[ $module_id ] );
		?>
		<div class="wpat-module-toggle" style="display:flex; align-items:center; gap:12px;">
			<?php if ( $has_body ) : ?>
				<span class="wpat-collapse-btn collapsed" <?php echo $is_active ? '' : 'style="display:none;"'; ?> title="Colapsar/Desplegar ajustes">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
				</span>
			<?php endif; ?>
			<label class="wpat-switch">
				<input type="checkbox" name="wpat_settings[<?php echo esc_attr( $module_id ); ?>]" value="1" <?php checked( $is_active, true ); ?>>
				<span class="wpat-slider"></span>
			</label>
		</div>
		<?php
	}

	/**
	 * AJAX: Obtiene la lista de páginas y entradas públicas para auditar.
	 */
	public function ajax_seo_get_pages_to_scan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		$post_type_filter = isset( $_POST['post_type_filter'] ) ? sanitize_text_field( $_POST['post_type_filter'] ) : 'all';

		if ( 'all' !== $post_type_filter && in_array( $post_type_filter, $post_types, true ) ) {
			$scan_post_types = array( $post_type_filter );
		} else {
			$scan_post_types = array_values( $post_types );
		}

		$posts = get_posts( array(
			'post_type'      => $scan_post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		wp_send_json_success( array( 'ids' => $posts ) );
	}

	/**
	 * AJAX: Realiza la auditoría de un lote de páginas y entradas.
	 */
	public function ajax_seo_audit_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? map_deep( $_POST['post_ids'], 'intval' ) : array();

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => 'No se han especificado IDs.' ) );
		}

		$results = array();

		foreach ( $post_ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			// Frase clave
			$keyword = get_post_meta( $id, '_wpat_seo_keyword', true );

			// Título
			$title = get_post_meta( $id, '_wpat_seo_title', true );
			if ( empty( $title ) ) {
				$title_status = 'empty';
				$title_length = 0;
			} else {
				$title_length = mb_strlen( $title );
				if ( $title_length >= 50 && $title_length <= 60 ) {
					$title_status = 'correct';
				} else {
					$title_status = 'warning';
				}
			}

			// Descripción
			$desc = get_post_meta( $id, '_wpat_seo_desc', true );
			if ( empty( $desc ) ) {
				$desc_status = 'empty';
				$desc_length = 0;
			} else {
				$desc_length = mb_strlen( $desc );
				if ( $desc_length >= 120 && $desc_length <= 160 ) {
					$desc_status = 'correct';
				} else {
					$desc_status = 'warning';
				}
			}

			// Indexable
			$noindex = get_post_meta( $id, '_wpat_seo_noindex', true );
			$indexable = ( '1' === $noindex ) ? 'noindex' : 'index';

			$results[] = array(
				'id'           => $id,
				'title'        => get_the_title( $id ),
				'edit_url'     => get_edit_post_link( $id, 'raw' ),
				'type'         => get_post_type_object( get_post_type( $id ) )->labels->singular_name,
				'title_status' => $title_status,
				'title_length' => $title_length,
				'desc_status'  => $desc_status,
				'desc_length'  => $desc_length,
				'indexable'    => $indexable,
				'keyword'      => $keyword,
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX: Obtiene la lista de IDs de entradas/páginas que tienen títulos o descripciones SEO vacías.
	 */
	public function ajax_seo_get_posts_to_fill() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		$post_type_filter = isset( $_POST['post_type_filter'] ) ? sanitize_text_field( $_POST['post_type_filter'] ) : 'all';

		if ( 'all' !== $post_type_filter && in_array( $post_type_filter, $post_types, true ) ) {
			$scan_post_types = array( $post_type_filter );
		} else {
			$scan_post_types = array_values( $post_types );
		}

		// Obtener todos los IDs publicados
		$posts = get_posts( array(
			'post_type'      => $scan_post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$ids_to_fill = array();
		if ( ! empty( $posts ) ) {
			update_postmeta_cache( $posts );
			foreach ( $posts as $post_id ) {
				$title = get_post_meta( $post_id, '_wpat_seo_title', true );
				$desc  = get_post_meta( $post_id, '_wpat_seo_desc', true );
				if ( empty( $title ) || empty( $desc ) ) {
					$ids_to_fill[] = $post_id;
				}
			}
		}

		wp_send_json_success( array( 'ids' => $ids_to_fill ) );
	}

	/**
	 * AJAX: Rellena automáticamente los campos SEO vacíos de un lote de posts.
	 */
	public function ajax_seo_fill_posts_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$post_ids = isset( $_POST['post_ids'] ) ? map_deep( $_POST['post_ids'], 'intval' ) : array();

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => 'No se han especificado IDs.' ) );
		}

		$site_name = get_bloginfo( 'name' );
		$filled_count = 0;

		foreach ( $post_ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$title_filled = false;
			$desc_filled  = false;

			// 1. Rellenar Título SEO si está vacío
			$title = get_post_meta( $id, '_wpat_seo_title', true );
			if ( empty( $title ) ) {
				$default_title = get_the_title( $id );
				if ( empty( $default_title ) ) {
					$default_title = 'Página';
				}
				$new_title = $default_title . ' - ' . $site_name;
				update_post_meta( $id, '_wpat_seo_title', $new_title );
				$title_filled = true;
			}

			// 2. Rellenar Meta Descripción si está vacía
			$desc = get_post_meta( $id, '_wpat_seo_desc', true );
			if ( empty( $desc ) ) {
				$post_content = $post->post_content;
				$clean_text   = trim( strip_tags( strip_shortcodes( $post_content ) ) );
				// Quitar saltos de línea y espacios múltiples
				$clean_text   = preg_replace( '/\s+/', ' ', $clean_text );
				
				if ( empty( $clean_text ) ) {
					$clean_text = get_the_title( $id ) . '. Conoce todos los detalles y el contenido completo de esta sección en nuestra página web.';
				}

				// Limitar a 150 caracteres
				if ( mb_strlen( $clean_text ) > 150 ) {
					$clean_text = mb_substr( $clean_text, 0, 147 ) . '...';
				}

				update_post_meta( $id, '_wpat_seo_desc', $clean_text );
				$desc_filled = true;
			}

			// 3. Rellenar Canonical si está vacío
			$canonical = get_post_meta( $id, '_wpat_seo_canonical', true );
			if ( empty( $canonical ) ) {
				update_post_meta( $id, '_wpat_seo_canonical', get_permalink( $id ) );
			}

			if ( $title_filled || $desc_filled ) {
				$filled_count++;
			}
		}

		wp_send_json_success( array( 'filled' => $filled_count ) );
	}

	/**
	 * AJAX: Fuerza la comprobación de actualizaciones desde GitHub y devuelve el estado actual.
	 */
	public function ajax_force_update_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		// Borrar caché de transients
		delete_transient( 'wpat_github_update_check' );
		delete_site_transient( 'update_plugins' );

		$new_version = '';
		$github_url = 'https://github.com/19webs/wp-agency-toolkit';
		
		if ( class_exists( 'WPAT_Updater' ) ) {
			$updater = WPAT_Updater::get_instance();
			// Esto forzará una llamada real porque acabamos de borrar el transient
			$release = $updater->get_latest_github_release();
			if ( $release && isset( $release['tag_name'] ) ) {
				$new_version = ltrim( $release['tag_name'], 'v' );
				$github_url = isset( $release['html_url'] ) ? $release['html_url'] : $github_url;
			}
		}

		$has_update = ! empty( $new_version ) && version_compare( WPAT_VERSION, $new_version, '<' );
		$update_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=wp-agency-toolkit/wp-agency-toolkit.php' ), 'upgrade-plugin_wp-agency-toolkit/wp-agency-toolkit.php' );

		wp_send_json_success( array(
			'has_update'      => $has_update,
			'current_version' => WPAT_VERSION,
			'new_version'     => $new_version,
			'update_url'      => $update_url,
		) );
	}
}

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
		add_action( 'wp_ajax_wpat_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_wpat_autofill_import_csv', array( $this, 'ajax_autofill_import_csv' ) );
		add_action( 'wp_ajax_wpat_autofill_export_csv', array( $this, 'ajax_autofill_export_csv' ) );
		add_action( 'wp_ajax_wpat_autofill_toggle_country', array( $this, 'ajax_autofill_toggle_country' ) );
		add_action( 'wp_ajax_wpat_autofill_delete_country', array( $this, 'ajax_autofill_delete_country' ) );
	}

	/**
	 * Añade el menú del plugin a la administración de WordPress.
	 */
		public function add_admin_menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" fill="currentColor"><path d="M512.1 191l-8.2 14.3c-3 5.3-9.4 7.5-15.1 5.4-11.8-4.4-22.6-10.7-32.1-18.6-4.6-3.8-5.8-10.5-2.8-15.7l8.2-14.3c-6.9-8-12.3-17.3-15.9-27.4h-16.5c-6 0-11.2-4.3-12.2-10.3-2-12-2.1-24.6 0-37.1 1-6 6.2-10.4 12.2-10.4h16.5c3.6-10.1 9-19.4 15.9-27.4l-8.2-14.3c-3 5.2-1.9-11.9 2.8-15.7-9.5-7.9-20.4-14.2-32.1-18.6-5.7 2.1 12.1.1 15.1 5.4l8.2 14.3c10.5-1.9 21.2-1.9 31.7 0L552 6.3c3-5.3 9.4-7.5 15.1-5.4 11.8 4.4 22.6 10.7 32.1 18.6 4.6 3.8 5.8 10.5 2.8 15.7l-8.2 14.3c-6.9 8-12.3-17.3-15.9-27.4h16.5c6 0 11.2 4.3 12.2 10.3 2 12 2.1 24.6 0 37.1-1 6-6.2 10.4-12.2 10.4h-16.5c-3.6 10.1-9 19.4-15.9 27.4l8.2 14.3c3 5.2 1.9 11.9-2.8 15.7-9.5 7.9-20.4 14.2-32.1 18.6-5.7 2.1-12.1-.1-15.1-5.4l-8.2-14.3c-10.4 1.9-21.2 1.9-31.7 0zm-10.5-58.8c38.5 29.6 82.4-14.3 52.8-52.8-38.5-29.7-82.4 14.3-52.8 52.8zM386.3 286.1l33.7 16.8c10.1 5.8 14.5 18.1 10.5 29.1-8.9 24.2-26.4 46.4-42.6 65.8-7.4 8.9-20.2 11.1-30.3 5.3l-29.1-16.8c-16 13.7-34.6 24.6-54.9 31.7v33.6c0 11.6-8.3 21.6-19.7 23.6-24.6 4.2-50.4 4.4-75.9 0-11.5-2-20-11.9-20-23.6V418c-20.3-7.2-38.9-18-54.9-31.7L74 403c-10 5.8-22.9 3.6-30.3-5.3-16.2-19.4-33.3-41.6-42.2-65.7-4-10.9.4-23.2 10.5-29.1l33.3-16.8c-3.9-20.9-3.9-42.4 0-63.4L12 205.8c-10.1-5.8-14.6-18.1-10.5-29 8.9-24.2 26-46.4 42.2-65.8 7.4-8.9 20.2-11.1 30.3-5.3l29.1 16.8c16-13.7 34.6-24.6 54.9-31.7V57.1c0-11.5 8.2-21.5 19.6-23.5 24.6-4.2 50.5-4.4 76-.1 11.5 2 20 11.9 20 23.6v33.6c20.3 7.2 38.9 18 54.9 31.7l29.1-16.8c10-5.8 22.9-3.6 30.3 5.3 16.2 19.4 33.2 41.6 42.1 65.8 4 10.9.1 23.2-10 29.1l-33.7 16.8c3.9 21 3.9 42.5 0 63.5zm-117.6 21.1c59.2-77-28.7-164.9-105.7-105.7-59.2 77 28.7 164.9 105.7 105.7zm243.4 182.7l-8.2 14.3c-3 5.3-9.4 7.5-15.1 5.4-11.8-4.4-22.6-10.7-32.1-18.6-4.6-3.8-5.8-10.5-2.8-15.7l8.2-14.3c-6.9-8-12.3-17.3-15.9-27.4h-16.5c-6 0-11.2-4.3-12.2-10.3-2-12-2.1-24.6 0-37.1 1-6 6.2-10.4 12.2-10.4h16.5c3.6-10.1 9-19.4 15.9-27.4l-8.2-14.3c-3-5.2-1.9-11.9 2.8-15.7 9.5-7.9 20.4-14.2 32.1-18.6 5.7-2.1 12.1.1 15.1 5.4l8.2 14.3c10.5-1.9 21.2-1.9 31.7 0l8.2-14.3c3-5.3 9.4-7.5 15.1-5.4 11.8 4.4 22.6 10.7 32.1 18.6 4.6 3.8 5.8 10.5 2.8 15.7l-8.2 14.3c-6.9 8-12.3-17.3-15.9-27.4h16.5c6 0 11.2 4.3 12.2 10.3 2 12 2.1 24.6 0 37.1-1 6-6.2 10.4-12.2 10.4h-16.5c-3.6 10.1-9 19.4-15.9 27.4l8.2 14.3c3.6 5.2 1.9 11.9-2.8 15.7-9.5 7.9-20.4 14.2-32.1 18.6-5.7 2.1-12.1-.1-15.1-5.4l-8.2-14.3c-10.4 1.9-21.2 1.9-31.7 0zM501.6 431c38.5 29.6 82.4-14.3 52.8-52.8-38.5-29.6-82.4 14.3-52.8 52.8z" /></svg>' );

		add_menu_page(
			'WP Agency Toolkit',
			'Agency Toolkit',
			'manage_options',
			'wp-agency-toolkit',
			array( $this, 'render_admin_page' ),
			$icon,
			80
		);

		add_submenu_page(
			'wp-agency-toolkit',
			'Centro de Módulos',
			'Centro de Módulos',
			'manage_options',
			'wp-agency-toolkit',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			'wp-agency-toolkit',
			'Salud & Limpieza BD',
			'Salud & Limpieza BD',
			'manage_options',
			'wpat-tools',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Encola los scripts y estilos necesarios para el panel de administración.
	 *
	 * @param string $hook Pestaña actual de la administración.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'wp-agency-toolkit' ) === false && strpos( $hook, 'wpat' ) === false ) {
			return;
		}

		// Encolar soporte nativo de WP para carga de medios y selector de color
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Estilos y scripts propios (usamos time() temporalmente para evitar cualquier caché del navegador o del servidor)
		wp_enqueue_style( 'wpat-admin-css', WPAT_URL . 'assets/css/wpat-admin.css', array(), time() );
		wp_enqueue_script( 'wpat-admin-js', WPAT_URL . 'assets/js/wpat-admin.js', array( 'jquery', 'wp-color-picker' ), time(), true );

		wp_localize_script( 'wpat-admin-js', 'wpat_object', array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'wpat_save_settings_action' ),
			'cleanup_nonce' => wp_create_nonce( 'wpat_cleanup_nonce_action' ),
		) );

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
				'mod'                => 'initial-setup',
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
		$input_settings   = isset( $_POST['wpat_settings'] ) ? (array) $_POST['wpat_settings'] : array();

		// Detectar si la petición proviene de la vista independiente de un módulo específico
		$saving_module = isset( $_POST['wpat_saving_module'] ) ? sanitize_key( $_POST['wpat_saving_module'] ) : ( isset( $_GET['mod'] ) ? sanitize_key( $_GET['mod'] ) : '' );

		// Preservar todos los ajustes existentes para evitar borrar datos de otros módulos
		$new_settings = $current_settings;

		$all_modules = array(
			'login-customizer',
			'hide-login',
			'ssl-fixer',
			'woo-dni',
			'woo-catalog',
			'woo-checkout-designer',
			'woo-sale-badges',
			'woo-address-autofill',
			'woo-zoom',
			'duplicator',
			'snippets',
			'performance',
			'svg-support',
			'image-optimizer',
			'seo',
			'sitemap-xml',
			'disable-comments',
			'security-hardening',
			'envato-importer',
			'smtp',
			'hide_admin_bar',
			'dashboard_cleaner',
			'bot-blocker',
			'integrations',
			'initial-setup',
			'whatsapp',
			'reading-progress',
			'conflict-detector',
			'accessibility',
			'woo-checkout-editor',
			'woo-extra-options',
			'woo-variation-swatches',
			'woo-pdf-invoices',
			'woo-live-search',
			'woo-facets',
			'post-csv-importer',
			'anti-spam',
			'silent-skin',
			'tools',
		);

		// Actualizar estado ON/OFF del módulo o de todos si es el guardado global
		if ( ! empty( $saving_module ) ) {
			if ( in_array( $saving_module, $all_modules, true ) ) {
				$new_settings[ $saving_module ] = isset( $input_settings[ $saving_module ] ) && '1' === $input_settings[ $saving_module ] ? '1' : '0';
			}
		} else {
			foreach ( $all_modules as $m_id ) {
				if ( isset( $input_settings[ $m_id ] ) ) {
					$new_settings[ $m_id ] = '1' === $input_settings[ $m_id ] ? '1' : '0';
				}
			}
		}

		// 1. Sanitizar Login Customizer / Marca Blanca
		if ( empty( $saving_module ) || in_array( $saving_module, array( 'login-customizer', 'dashboard_cleaner', 'hide_admin_bar' ), true ) ) {
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

			$dashboard_cards = array( 'seo', 'pages', 'posts', 'plugins', 'themes', 'users', 'db', 'tools', 'smtp', 'jet', 'woo', 'media' );
			foreach ( $dashboard_cards as $card_key ) {
				$opt_key = 'db_card_' . $card_key;
				$new_settings[ $opt_key ] = isset( $input_settings[ $opt_key ] ) && '1' === $input_settings[ $opt_key ] ? '1' : '0';
			}
		}

		// 2. Sanitizar Bloqueador de Bots
		if ( empty( $saving_module ) || 'bot-blocker' === $saving_module ) {
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
		}

		// 3. Sanitizar Hide Login
		if ( empty( $saving_module ) || 'hide-login' === $saving_module ) {
			$new_settings['hide_login_slug']           = isset( $input_settings['hide_login_slug'] ) ? sanitize_title( $input_settings['hide_login_slug'] ) : 'acceso';
			$new_settings['hide_login_redirect']       = isset( $input_settings['hide_login_redirect'] ) && in_array( $input_settings['hide_login_redirect'], array( 'home', '404' ), true ) ? $input_settings['hide_login_redirect'] : 'home';
			$new_settings['hide_login_limit_attempts'] = isset( $input_settings['hide_login_limit_attempts'] ) && '1' === $input_settings['hide_login_limit_attempts'] ? '1' : '0';
			$new_settings['hide_login_max_attempts']   = isset( $input_settings['hide_login_max_attempts'] ) ? absint( $input_settings['hide_login_max_attempts'] ) : 3;
			$new_settings['hide_login_lockout']        = isset( $input_settings['hide_login_lockout'] ) ? absint( $input_settings['hide_login_lockout'] ) : 120;
			$new_settings['hide_login_captcha']        = isset( $input_settings['hide_login_captcha'] ) && '1' === $input_settings['hide_login_captcha'] ? '1' : '0';
		}

		// 4. Sanitizar Deshabilitar Comentarios
		if ( empty( $saving_module ) || 'disable-comments' === $saving_module ) {
			$new_settings['disable_comments_global'] = isset( $input_settings['disable_comments_global'] ) && '1' === $input_settings['disable_comments_global'] ? '1' : '0';
			$new_settings['disable_comments_posts']  = isset( $input_settings['disable_comments_posts'] ) && '1' === $input_settings['disable_comments_posts'] ? '1' : '0';
			$new_settings['disable_comments_pages']  = isset( $input_settings['disable_comments_pages'] ) && '1' === $input_settings['disable_comments_pages'] ? '1' : '0';
			$new_settings['disable_comments_media']  = isset( $input_settings['disable_comments_media'] ) && '1' === $input_settings['disable_comments_media'] ? '1' : '0';
		}

		// 5. Sanitizar WooCommerce Catalog
		// Sanitizar Diseñador de Checkout High-Conversion
		if ( empty( $saving_module ) || 'woo-checkout-designer' === $saving_module ) {
			$new_settings['woo-checkout-designer']                = isset( $input_settings['woo-checkout-designer'] ) && '1' === $input_settings['woo-checkout-designer'] ? '1' : '0';
			$new_settings['woo_checkout_designer_layout']         = isset( $input_settings['woo_checkout_designer_layout'] ) && in_array( $input_settings['woo_checkout_designer_layout'], array( 'wpat-classic', 'wpat-express', 'wpat-accordion', 'wpat-minimalist' ), true ) ? $input_settings['woo_checkout_designer_layout'] : 'wpat-classic';
			$new_settings['woo_checkout_designer_mobile_summary'] = isset( $input_settings['woo_checkout_designer_mobile_summary'] ) && '1' === $input_settings['woo_checkout_designer_mobile_summary'] ? '1' : '0';
			$new_settings['woo_checkout_designer_trust_badges']   = isset( $input_settings['woo_checkout_designer_trust_badges'] ) && '1' === $input_settings['woo_checkout_designer_trust_badges'] ? '1' : '0';
			$new_settings['woo_checkout_designer_email_fix']      = isset( $input_settings['woo_checkout_designer_email_fix'] ) && '1' === $input_settings['woo_checkout_designer_email_fix'] ? '1' : '0';
			$new_settings['woo_checkout_designer_product_thumbs'] = isset( $input_settings['woo_checkout_designer_product_thumbs'] ) && '1' === $input_settings['woo_checkout_designer_product_thumbs'] ? '1' : '0';
			$new_settings['woo_checkout_btn_bg_color']               = isset( $input_settings['woo_checkout_btn_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_checkout_btn_bg_color'] ) ? $input_settings['woo_checkout_btn_bg_color'] : '#2563eb';
			$new_settings['woo_checkout_btn_hover_bg_color']         = isset( $input_settings['woo_checkout_btn_hover_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_checkout_btn_hover_bg_color'] ) ? $input_settings['woo_checkout_btn_hover_bg_color'] : '#1d4ed8';
			$new_settings['woo_checkout_btn_txt_color']              = isset( $input_settings['woo_checkout_btn_txt_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_checkout_btn_txt_color'] ) ? $input_settings['woo_checkout_btn_txt_color'] : '#ffffff';
			$new_settings['woo_checkout_step_accent_color']          = isset( $input_settings['woo_checkout_step_accent_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_checkout_step_accent_color'] ) ? $input_settings['woo_checkout_step_accent_color'] : '#2563eb';
		}

		// Sanitizar Autocompletado de CP y Provincia (WooCommerce)
		if ( empty( $saving_module ) || 'woo-address-autofill' === $saving_module ) {
			$new_settings['woo-address-autofill']     = isset( $input_settings['woo-address-autofill'] ) && '1' === $input_settings['woo-address-autofill'] ? '1' : '0';
			$new_settings['woo_address_autofill_city'] = isset( $input_settings['woo_address_autofill_city'] ) && '1' === $input_settings['woo_address_autofill_city'] ? '1' : '0';
		}

		if ( empty( $saving_module ) || 'woo-catalog' === $saving_module ) {
			$new_settings['woo_catalog_hide_price']  = isset( $input_settings['woo_catalog_hide_price'] ) && '1' === $input_settings['woo_catalog_hide_price'] ? '1' : '0';
			$new_settings['woo_catalog_price_text']  = isset( $input_settings['woo_catalog_price_text'] ) ? sanitize_text_field( $input_settings['woo_catalog_price_text'] ) : '';
			$new_settings['woo_catalog_hide_cart']   = isset( $input_settings['woo_catalog_hide_cart'] ) && '1' === $input_settings['woo_catalog_hide_cart'] ? '1' : '0';
			$new_settings['woo_catalog_wa_enable']   = isset( $input_settings['woo_catalog_wa_enable'] ) && '1' === $input_settings['woo_catalog_wa_enable'] ? '1' : '0';
			$new_settings['woo_catalog_wa_phone']    = isset( $input_settings['woo_catalog_wa_phone'] ) ? sanitize_text_field( $input_settings['woo_catalog_wa_phone'] ) : '';
			$new_settings['woo_catalog_wa_message']  = isset( $input_settings['woo_catalog_wa_message'] ) ? sanitize_textarea_field( $input_settings['woo_catalog_wa_message'] ) : '';
			$new_settings['woo_catalog_form_enable'] = isset( $input_settings['woo_catalog_form_enable'] ) && '1' === $input_settings['woo_catalog_form_enable'] ? '1' : '0';
			$new_settings['woo_catalog_form_email']  = isset( $input_settings['woo_catalog_form_email'] ) ? sanitize_email( $input_settings['woo_catalog_form_email'] ) : '';
		}

		// 6. Sanitizar WooCommerce Gallery Zoom
		if ( empty( $saving_module ) || 'woo-zoom' === $saving_module ) {
			$new_settings['woo_zoom_disable_zoom']     = isset( $input_settings['woo_zoom_disable_zoom'] ) && '1' === $input_settings['woo_zoom_disable_zoom'] ? '1' : '0';
			$new_settings['woo_zoom_disable_lightbox'] = isset( $input_settings['woo_zoom_disable_lightbox'] ) && '1' === $input_settings['woo_zoom_disable_lightbox'] ? '1' : '0';
			$new_settings['woo_zoom_disable_slider']   = isset( $input_settings['woo_zoom_disable_slider'] ) && '1' === $input_settings['woo_zoom_disable_slider'] ? '1' : '0';
		}

		// 7. Sanitizar Fortalecimiento de Seguridad
		if ( empty( $saving_module ) || 'security-hardening' === $saving_module ) {
			$new_settings['sec_disable_file_edit']    = isset( $input_settings['sec_disable_file_edit'] ) && '1' === $input_settings['sec_disable_file_edit'] ? '1' : '0';
			$new_settings['sec_block_uploads_php']    = isset( $input_settings['sec_block_uploads_php'] ) && '1' === $input_settings['sec_block_uploads_php'] ? '1' : '0';
			$new_settings['sec_hide_wp_version']      = isset( $input_settings['sec_hide_wp_version'] ) && '1' === $input_settings['sec_hide_wp_version'] ? '1' : '0';
			$new_settings['sec_generic_login_errors'] = isset( $input_settings['sec_generic_login_errors'] ) && '1' === $input_settings['sec_generic_login_errors'] ? '1' : '0';
			$new_settings['sec_disable_indexes']      = isset( $input_settings['sec_disable_indexes'] ) && '1' === $input_settings['sec_disable_indexes'] ? '1' : '0';
			$new_settings['sec_disable_user_enum']    = isset( $input_settings['sec_disable_user_enum'] ) && '1' === $input_settings['sec_disable_user_enum'] ? '1' : '0';
			$new_settings['sec_disable_xmlrpc']       = isset( $input_settings['sec_disable_xmlrpc'] ) && '1' === $input_settings['sec_disable_xmlrpc'] ? '1' : '0';
			$new_settings['sec_block_admin_user']     = isset( $input_settings['sec_block_admin_user'] ) && '1' === $input_settings['sec_block_admin_user'] ? '1' : '0';
		}

		// Sanitizar redirección SSL
		if ( empty( $saving_module ) || 'ssl-fixer' === $saving_module ) {
			$new_settings['ssl_redirect_method'] = isset( $input_settings['ssl_redirect_method'] ) && in_array( $input_settings['ssl_redirect_method'], array( 'php', 'htaccess' ), true ) ? $input_settings['ssl_redirect_method'] : 'php';
		}

		// 8. Sanitizar SMTP
		if ( empty( $saving_module ) || 'smtp' === $saving_module ) {
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
		}

		// 9. Sanitizar Integraciones
		if ( empty( $saving_module ) || 'integrations' === $saving_module ) {
			$new_settings['integrations'] = isset( $input_settings['integrations'] ) && '1' === $input_settings['integrations'] ? '1' : '0';
			
			$gsc_raw = isset( $input_settings['google_search_console_code'] ) ? trim( $input_settings['google_search_console_code'] ) : '';
			if ( ! empty( $gsc_raw ) ) {
				if ( preg_match( '/content=["\']([^"\']+)["\']/i', $gsc_raw, $matches ) ) {
					$gsc_raw = $matches[1];
				}
				$new_settings['google_search_console_code'] = sanitize_text_field( $gsc_raw );
			} else if ( isset( $input_settings['google_search_console_code'] ) ) {
				$new_settings['google_search_console_code'] = '';
			}
			if ( isset( $input_settings['google_analytics_id'] ) ) $new_settings['google_analytics_id'] = sanitize_text_field( $input_settings['google_analytics_id'] );
			if ( isset( $input_settings['gtm_container_id'] ) ) $new_settings['gtm_container_id'] = sanitize_text_field( $input_settings['gtm_container_id'] );
			if ( isset( $input_settings['facebook_pixel_id'] ) ) $new_settings['facebook_pixel_id'] = sanitize_text_field( $input_settings['facebook_pixel_id'] );
			if ( isset( $input_settings['google_drive_token'] ) ) $new_settings['google_drive_token'] = sanitize_text_field( $input_settings['google_drive_token'] );
			if ( isset( $input_settings['google_drive_folder'] ) ) $new_settings['google_drive_folder'] = sanitize_text_field( $input_settings['google_drive_folder'] );
			if ( isset( $input_settings['dropbox_token'] ) ) $new_settings['dropbox_token'] = sanitize_text_field( $input_settings['dropbox_token'] );
			if ( isset( $input_settings['onedrive_token'] ) ) $new_settings['onedrive_token'] = sanitize_text_field( $input_settings['onedrive_token'] );
		}

		// 10. Sanitizar WhatsApp
		if ( empty( $saving_module ) || 'whatsapp' === $saving_module ) {
			$new_settings['whatsapp']          = isset( $input_settings['whatsapp'] ) && '1' === $input_settings['whatsapp'] ? '1' : '0';
			$new_settings['whatsapp_enabled']  = $new_settings['whatsapp'];
			$new_settings['whatsapp_phone']    = isset( $input_settings['whatsapp_phone'] ) ? sanitize_text_field( $input_settings['whatsapp_phone'] ) : '';
			$new_settings['whatsapp_message']  = isset( $input_settings['whatsapp_message'] ) ? sanitize_text_field( $input_settings['whatsapp_message'] ) : '¡Hola! Quisiera más información.';
			$new_settings['whatsapp_position'] = isset( $input_settings['whatsapp_position'] ) && in_array( $input_settings['whatsapp_position'], array( 'bottom-right', 'bottom-left' ), true ) ? $input_settings['whatsapp_position'] : 'bottom-right';
			$new_settings['whatsapp_tooltip']  = isset( $input_settings['whatsapp_tooltip'] ) ? sanitize_text_field( $input_settings['whatsapp_tooltip'] ) : '';
			$new_settings['whatsapp_agents']   = isset( $input_settings['whatsapp_agents'] ) ? sanitize_textarea_field( $input_settings['whatsapp_agents'] ) : '';
		}

		// 11. Sanitizar Barra y Tiempo de Lectura
		if ( empty( $saving_module ) || 'reading-progress' === $saving_module ) {
			$new_settings['reading-progress']     = isset( $input_settings['reading-progress'] ) && '1' === $input_settings['reading-progress'] ? '1' : '0';
			$new_settings['reading_bar_enabled']  = $new_settings['reading-progress'];
			$new_settings['reading_bar_color']    = isset( $input_settings['reading_bar_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['reading_bar_color'] ) ? $input_settings['reading_bar_color'] : '#2563eb';
			$new_settings['reading_bar_height']   = isset( $input_settings['reading_bar_height'] ) ? max( 1, min( 30, absint( $input_settings['reading_bar_height'] ) ) ) : 4;
			$new_settings['reading_time_enabled'] = isset( $input_settings['reading_time_enabled'] ) && '1' === $input_settings['reading_time_enabled'] ? '1' : '0';
		}

		// 12. Sanitizar Detector de Incompatibilidades
		if ( empty( $saving_module ) || 'conflict-detector' === $saving_module ) {
			$new_settings['conflict-detector'] = isset( $input_settings['conflict-detector'] ) && '1' === $input_settings['conflict-detector'] ? '1' : '0';
		}

		// 13. Sanitizar Herramientas de Accesibilidad
		if ( empty( $saving_module ) || 'accessibility' === $saving_module ) {
			$new_settings['accessibility']                   = isset( $input_settings['accessibility'] ) && '1' === $input_settings['accessibility'] ? '1' : '0';
			$new_settings['accessibility_enabled']           = $new_settings['accessibility'];
			$new_settings['accessibility_position']          = isset( $input_settings['accessibility_position'] ) && in_array( $input_settings['accessibility_position'], array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ), true ) ? $input_settings['accessibility_position'] : 'bottom-left';
			$new_settings['accessibility_offset_y']          = isset( $input_settings['accessibility_offset_y'] ) ? max( 0, min( 500, absint( $input_settings['accessibility_offset_y'] ) ) ) : 25;
			$new_settings['accessibility_bg_color']          = isset( $input_settings['accessibility_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['accessibility_bg_color'] ) ? $input_settings['accessibility_bg_color'] : '#2563eb';
			$new_settings['accessibility_text_zoom']         = isset( $input_settings['accessibility_text_zoom'] ) && '1' === $input_settings['accessibility_text_zoom'] ? '1' : '0';
			$new_settings['accessibility_grayscale']         = isset( $input_settings['accessibility_grayscale'] ) && '1' === $input_settings['accessibility_grayscale'] ? '1' : '0';
			$new_settings['accessibility_high_contrast']     = isset( $input_settings['accessibility_high_contrast'] ) && '1' === $input_settings['accessibility_high_contrast'] ? '1' : '0';
			$new_settings['accessibility_negative_contrast'] = isset( $input_settings['accessibility_negative_contrast'] ) && '1' === $input_settings['accessibility_negative_contrast'] ? '1' : '0';
			$new_settings['accessibility_light_bg']          = isset( $input_settings['accessibility_light_bg'] ) && '1' === $input_settings['accessibility_light_bg'] ? '1' : '0';
			$new_settings['accessibility_underline_links']   = isset( $input_settings['accessibility_underline_links'] ) && '1' === $input_settings['accessibility_underline_links'] ? '1' : '0';
			$new_settings['accessibility_readable_font']     = isset( $input_settings['accessibility_readable_font'] ) && '1' === $input_settings['accessibility_readable_font'] ? '1' : '0';
		}

		// 14. Sanitizar Editor de Campos de Checkout (WooCommerce)
		if ( empty( $saving_module ) || 'woo-checkout-editor' === $saving_module ) {
			$new_settings['woo-checkout-editor']      = isset( $input_settings['woo-checkout-editor'] ) && '1' === $input_settings['woo-checkout-editor'] ? '1' : '0';
			$new_settings['checkout_editor_enabled']  = $new_settings['woo-checkout-editor'];
			$new_settings['checkout_nif_enabled']     = isset( $input_settings['checkout_nif_enabled'] ) && '1' === $input_settings['checkout_nif_enabled'] ? '1' : '0';
			$new_settings['checkout_nif_required']    = isset( $input_settings['checkout_nif_required'] ) && '1' === $input_settings['checkout_nif_required'] ? '1' : '0';
			$new_settings['checkout_nif_position']    = isset( $input_settings['checkout_nif_position'] ) && in_array( $input_settings['checkout_nif_position'], array( 'after_names', 'after_company', 'at_end' ), true ) ? $input_settings['checkout_nif_position'] : 'after_names';

			if ( isset( $input_settings['checkout_disabled_fields'] ) ) {
				$new_settings['checkout_disabled_fields'] = is_array( $input_settings['checkout_disabled_fields'] ) ? array_map( 'sanitize_key', $input_settings['checkout_disabled_fields'] ) : array();
			}

			if ( isset( $input_settings['checkout_custom_fields'] ) && is_array( $input_settings['checkout_custom_fields'] ) ) {
				$custom_fields_clean = array();
				foreach ( $input_settings['checkout_custom_fields'] as $cf ) {
					if ( ! empty( $cf['label'] ) ) {
						$key = ! empty( $cf['key'] ) ? sanitize_key( $cf['key'] ) : 'cf_' . substr( md5( $cf['label'] ), 0, 8 );
						$custom_fields_clean[] = array(
							'key'         => $key,
							'label'       => sanitize_text_field( $cf['label'] ),
							'type'        => isset( $cf['type'] ) && in_array( $cf['type'], array( 'text', 'select', 'radio', 'checkbox', 'textarea', 'date' ), true ) ? $cf['type'] : 'text',
							'placeholder' => isset( $cf['placeholder'] ) ? sanitize_text_field( $cf['placeholder'] ) : '',
							'required'    => isset( $cf['required'] ) && '1' === $cf['required'] ? '1' : '0',
							'section'     => isset( $cf['section'] ) && in_array( $cf['section'], array( 'billing', 'shipping', 'order' ), true ) ? $cf['section'] : 'billing',
							'position'    => isset( $cf['position'] ) && in_array( $cf['position'], array( 'after_names', 'after_company', 'after_address', 'end_of_section' ), true ) ? $cf['position'] : 'after_names',
							'priority'    => isset( $cf['priority'] ) ? intval( $cf['priority'] ) : 100,
							'options'     => isset( $cf['options'] ) ? sanitize_textarea_field( $cf['options'] ) : '',
						);
					}
				}
				$new_settings['checkout_custom_fields'] = $custom_fields_clean;
			}
		}

		// 15. Sanitizar Opciones Extra y Swatches de Producto (WooCommerce)
		if ( empty( $saving_module ) || 'woo-extra-options' === $saving_module ) {
			$new_settings['woo-extra-options']     = isset( $input_settings['woo-extra-options'] ) && '1' === $input_settings['woo-extra-options'] ? '1' : '0';
			$new_settings['extra_options_enabled'] = $new_settings['woo-extra-options'];

			if ( isset( $input_settings['extra_options_rules'] ) && is_array( $input_settings['extra_options_rules'] ) ) {
				$rules_clean = array();
				foreach ( $input_settings['extra_options_rules'] as $rule ) {
					if ( ! empty( $rule['title'] ) ) {
						$fields_clean = array();
						if ( isset( $rule['fields'] ) && is_array( $rule['fields'] ) ) {
							foreach ( $rule['fields'] as $f ) {
								if ( ! empty( $f['label'] ) ) {
									$fields_clean[] = array(
										'label'       => sanitize_text_field( $f['label'] ),
										'type'        => isset( $f['type'] ) && in_array( $f['type'], array( 'text', 'textarea', 'select', 'radio', 'swatch', 'checkbox', 'file' ), true ) ? $f['type'] : 'text',
										'price'       => isset( $f['price'] ) ? floatval( $f['price'] ) : 0,
										'required'    => isset( $f['required'] ) && '1' === $f['required'] ? '1' : '0',
										'placeholder' => isset( $f['placeholder'] ) ? sanitize_text_field( $f['placeholder'] ) : '',
										'default_val' => isset( $f['default_val'] ) ? sanitize_text_field( $f['default_val'] ) : '',
										'max_length'  => isset( $f['max_length'] ) ? absint( $f['max_length'] ) : 0,
										'options'     => isset( $f['options'] ) ? sanitize_textarea_field( $f['options'] ) : '',
										'swatches'    => isset( $f['swatches'] ) ? sanitize_textarea_field( $f['swatches'] ) : '',
									);
								}
							}
						}

						$rules_clean[] = array(
							'title'      => sanitize_text_field( $rule['title'] ),
							'enabled'    => isset( $rule['enabled'] ) && '1' === $rule['enabled'] ? '1' : '0',
							'scope'      => isset( $rule['scope'] ) && in_array( $rule['scope'], array( 'global', 'category', 'product' ), true ) ? $rule['scope'] : 'global',
							'categories' => isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? array_map( 'absint', $rule['categories'] ) : array(),
							'products'   => isset( $rule['products'] ) ? ( is_array( $rule['products'] ) ? array_map( 'absint', $rule['products'] ) : array_filter( array_map( 'absint', explode( ',', $rule['products'] ) ) ) ) : array(),
							'fields'     => $fields_clean,
						);
					}
				}
				$new_settings['extra_options_rules'] = $rules_clean;
			}
		}

		// 16. Sanitizar Swatches de Variación de Producto (WooCommerce)
		if ( empty( $saving_module ) || 'woo-variation-swatches' === $saving_module ) {
			$new_settings['woo-variation-swatches']    = isset( $input_settings['woo-variation-swatches'] ) && '1' === $input_settings['woo-variation-swatches'] ? '1' : '0';
			$new_settings['variation_swatches_enabled'] = $new_settings['woo-variation-swatches'];
			$new_settings['variation_swatches_shape']   = isset( $input_settings['variation_swatches_shape'] ) && in_array( $input_settings['variation_swatches_shape'], array( 'round', 'square' ), true ) ? $input_settings['variation_swatches_shape'] : 'round';
			$new_settings['variation_swatches_colors']  = isset( $input_settings['variation_swatches_colors'] ) ? sanitize_textarea_field( $input_settings['variation_swatches_colors'] ) : '';
		}

		// 17. Sanitizar Facturas PDF y Albaranes Automáticos (WooCommerce)
		if ( empty( $saving_module ) || 'woo-pdf-invoices' === $saving_module ) {
			$new_settings['woo-pdf-invoices']     = isset( $input_settings['woo-pdf-invoices'] ) && '1' === $input_settings['woo-pdf-invoices'] ? '1' : '0';
			$new_settings['pdf_invoices_enabled'] = $new_settings['woo-pdf-invoices'];
			$new_settings['pdf_company_name']     = isset( $input_settings['pdf_company_name'] ) ? sanitize_text_field( $input_settings['pdf_company_name'] ) : get_bloginfo( 'name' );
			$new_settings['pdf_company_nif']      = isset( $input_settings['pdf_company_nif'] ) ? sanitize_text_field( $input_settings['pdf_company_nif'] ) : '';
			$new_settings['pdf_company_address']  = isset( $input_settings['pdf_company_address'] ) ? sanitize_textarea_field( $input_settings['pdf_company_address'] ) : '';
			$new_settings['pdf_company_footer']   = isset( $input_settings['pdf_company_footer'] ) ? sanitize_textarea_field( $input_settings['pdf_company_footer'] ) : '';
			$new_settings['pdf_invoice_prefix']   = isset( $input_settings['pdf_invoice_prefix'] ) ? sanitize_text_field( $input_settings['pdf_invoice_prefix'] ) : 'FACT-' . date( 'Y' ) . '-';
			$new_settings['pdf_invoice_next_num'] = isset( $input_settings['pdf_invoice_next_num'] ) ? max( 1, absint( $input_settings['pdf_invoice_next_num'] ) ) : 1;
		}

		// 18. Sanitizar Buscador AJAX en Vivo de WooCommerce (Frontend)
		if ( empty( $saving_module ) || 'woo-live-search' === $saving_module ) {
			$new_settings['woo-live-search']          = isset( $input_settings['woo-live-search'] ) && '1' === $input_settings['woo-live-search'] ? '1' : '0';
			$new_settings['live_search_enabled']      = $new_settings['woo-live-search'];
			$new_settings['live_search_max_results']  = isset( $input_settings['live_search_max_results'] ) ? min( 8, max( 1, absint( $input_settings['live_search_max_results'] ) ) ) : 5;
			$new_settings['live_search_show_thumb']   = isset( $input_settings['live_search_show_thumb'] ) && '1' === $input_settings['live_search_show_thumb'] ? '1' : '0';
			$new_settings['live_search_show_price']   = isset( $input_settings['live_search_show_price'] ) && '1' === $input_settings['live_search_show_price'] ? '1' : '0';
			$new_settings['live_search_show_stock']   = isset( $input_settings['live_search_show_stock'] ) && '1' === $input_settings['live_search_show_stock'] ? '1' : '0';
			$new_settings['live_search_show_meta']    = isset( $input_settings['live_search_show_meta'] ) && in_array( $input_settings['live_search_show_meta'], array( 'sku', 'cat', 'both', 'none' ), true ) ? $input_settings['live_search_show_meta'] : 'sku';
			$new_settings['live_search_auto_replace'] = isset( $input_settings['live_search_auto_replace'] ) && '1' === $input_settings['live_search_auto_replace'] ? '1' : '0';
			$new_settings['live_search_placeholder']  = isset( $input_settings['live_search_placeholder'] ) ? sanitize_text_field( $input_settings['live_search_placeholder'] ) : 'Buscar productos por nombre, SKU o categoría...';
		}

		// 20. Sanitizar Badges y Etiquetas de Oferta High-Impact (WooCommerce)
		if ( empty( $saving_module ) || 'woo-sale-badges' === $saving_module ) {
			$new_settings['woo-sale-badges']           = isset( $input_settings['woo-sale-badges'] ) && '1' === $input_settings['woo-sale-badges'] ? '1' : '0';
			$new_settings['woo_sale_badge_shape']       = isset( $input_settings['woo_sale_badge_shape'] ) && in_array( $input_settings['woo_sale_badge_shape'], array( 'soft', 'pill', 'rect', 'circle', 'corner-ribbon', 'price-tag' ), true ) ? $input_settings['woo_sale_badge_shape'] : 'soft';
			$new_settings['woo_sale_badge_text_type']   = isset( $input_settings['woo_sale_badge_text_type'] ) && in_array( $input_settings['woo_sale_badge_text_type'], array( 'custom', 'percentage' ), true ) ? $input_settings['woo_sale_badge_text_type'] : 'custom';
			$new_settings['woo_sale_badge_custom_text'] = isset( $input_settings['woo_sale_badge_custom_text'] ) ? sanitize_text_field( $input_settings['woo_sale_badge_custom_text'] ) : '¡OFERTA!';
			$new_settings['woo_sale_badge_bg_color']    = isset( $input_settings['woo_sale_badge_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_sale_badge_bg_color'] ) ? $input_settings['woo_sale_badge_bg_color'] : '#ef4444';
			$new_settings['woo_sale_badge_txt_color']   = isset( $input_settings['woo_sale_badge_txt_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_sale_badge_txt_color'] ) ? $input_settings['woo_sale_badge_txt_color'] : '#ffffff';
			$new_settings['woo_sale_badge_font_size']   = isset( $input_settings['woo_sale_badge_font_size'] ) ? max( 8, min( 32, absint( $input_settings['woo_sale_badge_font_size'] ) ) ) : 13;
			$new_settings['woo_sale_badge_position']    = isset( $input_settings['woo_sale_badge_position'] ) && in_array( $input_settings['woo_sale_badge_position'], array( 'top-left', 'top-right' ), true ) ? $input_settings['woo_sale_badge_position'] : 'top-left';
		}

		// 19. Sanitizar Filtro por Facetas AJAX de WooCommerce
		if ( empty( $saving_module ) || 'woo-facets' === $saving_module ) {
			$new_settings['woo-facets']     = isset( $input_settings['woo-facets'] ) && '1' === $input_settings['woo-facets'] ? '1' : '0';
			$new_settings['facets_enabled'] = $new_settings['woo-facets'];
			if ( isset( $input_settings['facets_config'] ) ) {
				$new_settings['facets_config'] = is_array( $input_settings['facets_config'] ) ? array_map( 'sanitize_key', $input_settings['facets_config'] ) : array( 'sort', 'price', 'category', 'stock', 'rating' );
			}
		}



		// Guardar en la base de datos
		update_option( 'wpat_settings', $new_settings );

		// Actualizar reglas del archivo .htaccess para SSL
		require_once WPAT_PATH . 'includes/modules/class-wpat-ssl-fixer.php';
		$ssl_active = isset( $new_settings['ssl-fixer'] ) && '1' === $new_settings['ssl-fixer'];
		$method_htaccess = isset( $new_settings['ssl_redirect_method'] ) && 'htaccess' === $new_settings['ssl_redirect_method'];
		WPAT_SSL_Fixer::update_htaccess_rules( $ssl_active && $method_htaccess );

		// Redirigir de vuelta a la vista adecuada
		if ( ! empty( $saving_module ) ) {
			$redirect_url = add_query_arg( array(
				'settings-updated' => 'true',
				'mod'              => $saving_module,
			), menu_page_url( 'wp-agency-toolkit', false ) );
		} else {
			$active_tab = isset( $_POST['wpat_active_tab'] ) ? sanitize_key( $_POST['wpat_active_tab'] ) : '';
			$args = array( 'settings-updated' => 'true' );
			if ( ! empty( $active_tab ) ) {
				$args['tab'] = $active_tab;
			}
			$redirect_url = add_query_arg( $args, menu_page_url( 'wp-agency-toolkit', false ) );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Búsqueda en vivo por AJAX de productos WooCommerce (para las reglas de campos extras).
	 */
	public function ajax_search_products() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Acceso denegado' ) );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( mb_strlen( trim( $term ) ) < 2 ) {
			wp_send_json_success( array() );
		}

		$results   = array();
		$found_ids = array();

		// 1. Búsqueda por ID directo si es numérico
		if ( is_numeric( $term ) ) {
			$p_id = intval( $term );
			$post = get_post( $p_id );
			if ( $post && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
				$found_ids[] = $p_id;
				$p_obj       = function_exists( 'wc_get_product' ) ? wc_get_product( $p_id ) : null;
				$sku         = $p_obj ? $p_obj->get_sku() : '';
				$results[]   = array(
					'id'    => $p_id,
					'title' => '#' . $p_id . ' - ' . get_the_title( $p_id ) . ( $sku ? ' (' . $sku . ')' : '' ),
				);
			}
		}

		// 2. Búsqueda por SKU
		if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
			$sku_id = wc_get_product_id_by_sku( $term );
			if ( $sku_id && ! in_array( $sku_id, $found_ids, true ) ) {
				$found_ids[] = $sku_id;
				$p_obj       = function_exists( 'wc_get_product' ) ? wc_get_product( $sku_id ) : null;
				$sku         = $p_obj ? $p_obj->get_sku() : '';
				$results[]   = array(
					'id'    => $sku_id,
					'title' => '#' . $sku_id . ' - ' . get_the_title( $sku_id ) . ( $sku ? ' (' . $sku . ')' : '' ),
				);
			}
		}

		// 3. Búsqueda por Nombre / Título
		$args = array(
			'post_type'      => array( 'product', 'product_variation' ),
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 15,
			's'              => $term,
			'post__not_in'   => $found_ids,
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$p_obj     = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
				$sku       = $p_obj ? $p_obj->get_sku() : '';
				$results[] = array(
					'id'    => $post->ID,
					'title' => '#' . $post->ID . ' - ' . $post->post_title . ( $sku ? ' (' . $sku . ')' : '' ),
				);
			}
		}

		wp_send_json_success( $results );
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
		if ( ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) { wp_send_json_error( array( 'message' => 'Error de seguridad (nonce de ajustes inválido). Por favor, recarga la página.' ) ); }

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
			'woo-checkout-designer',
			'woo-sale-badges',
			'woo-address-autofill',
			'woo-zoom',
			'duplicator',
			'snippets',
			'performance',
			'svg-support',
			'image-optimizer',
			'seo',
			'sitemap-xml',
			'disable-comments',
			'security-hardening',
			'envato-importer',
			'smtp',
			'hide_admin_bar',
			'dashboard_cleaner',
			'bot-blocker',
			'integrations',
			'initial-setup',
			'whatsapp',
			'reading-progress',
			'conflict-detector',
			'accessibility',
			'woo-checkout-editor',
			'woo-extra-options',
			'woo-variation-swatches',
			'woo-pdf-invoices',
			'woo-live-search',
			'woo-facets',
			'post-csv-importer',
			'anti-spam',
			'silent-skin',
			'tools',
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
		if ( ! check_ajax_referer( 'wpat_cleanup_nonce_action', 'security', false ) ) { wp_send_json_error( array( 'message' => 'Error de seguridad (nonce de limpieza inválido). Por favor, recarga la página.' ) ); }

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
		if ( ! check_ajax_referer( 'wpat_cleanup_nonce_action', 'security', false ) ) { wp_send_json_error( array( 'message' => 'Error de seguridad (nonce de salud inválido). Por favor, recarga la página.' ) ); }

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
		<?php wp_nonce_field( 'wpat_cleanup_nonce_action', 'wpat_cleanup_ajax_nonce' ); ?>
		<!-- Tarjeta de Diagnóstico: Detector de Incompatibilidades y Salud de Plugins -->
		<div class="wpat-module-card" style="margin-bottom: 25px; padding: 20px;">
			<h3 style="margin-top:0; font-size:15px; font-weight:600; display:flex; align-items:center; gap:8px;">
				<span class="dashicons dashicons-shield" style="color:var(--wpat-primary);"></span> Detector de Incompatibilidades y Salud de Plugins
			</h3>
			<p style="margin: 4px 0 15px 0; color: #64748b; font-size: 13px;">Supervisa activamente la instalación en busca de plugins de terceros que colisionen o dupliquen las funciones integradas en WP Agency Toolkit.</p>
			<?php
			if ( class_exists( 'WPAT_Conflict_Detector' ) ) {
				$active_conflicts = WPAT_Conflict_Detector::get_active_conflicts();
			} else {
				$active_conflicts = array();
			}
			?>
			<?php if ( ! empty( $active_conflicts ) ) : ?>
				<div style="background: #fffbe6; border: 1px solid #ffe58f; border-radius: 8px; padding: 16px;">
					<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700; color: #b45309; display: flex; align-items: center; gap: 8px;">
						⚠️ Conflictos o Duplicidades Detectadas (<?php echo count( $active_conflicts ); ?>)
					</h4>
					<?php foreach ( $active_conflicts as $plugin_file => $data ) : 
						$is_active = ( isset( $data['status'] ) && 'active' === $data['status'] );
						$badge_bg  = $is_active ? '#ef4444' : '#f59e0b';
						$badge_lbl = $is_active ? 'PLUGIN ACTIVO' : 'INSTALADO (INACTIVO)';
					?>
						<div style="background: #ffffff; border: 1px solid #fef3c7; border-radius: 6px; padding: 12px; margin-bottom: 8px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
								<strong style="color: #92400e; font-size: 13.5px;"><?php echo esc_html( $data['name'] ); ?></strong>
								<span style="background: <?php echo esc_attr( $badge_bg ); ?>; color: #ffffff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; text-transform: uppercase;">
									<?php echo esc_html( $badge_lbl ); ?>
								</span>
							</div>
							<p style="margin: 4px 0 8px 0; font-size: 12.5px; color: #4b5563; line-height: 1.5;"><?php echo esc_html( $data['reason'] ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-small button-secondary">
								<?php echo $is_active ? 'Desactivar / Gestionar Plugin' : 'Eliminar / Gestionar Plugin'; ?>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; display: flex; align-items: center; gap: 12px;">
					<span style="font-size: 24px;">✅</span>
					<div>
						<strong style="color: #166534; font-size: 14px; display: block;">No se han detectado conflictos de plugins</strong>
						<span style="color: #374151; font-size: 12.5px;">Tu instalación está limpia y no hay plugins activos de terceros que colisionen con las funciones nativas de WP Agency Toolkit.</span>
					</div>
				</div>
			<?php endif; ?>
		</div>

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
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				if (typeof showToast === 'function') {
					showToast('Configuración guardada correctamente', false);
				}
			});
			</script>
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

		// Determinar si se ha solicitado ver la pantalla de un módulo individual (Standalone View)
		$mod_id = '';
		if ( isset( $_GET['mod'] ) && ! empty( $_GET['mod'] ) ) {
			$mod_id = sanitize_key( $_GET['mod'] );
		} elseif ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'wpat-' ) === 0 ) {
			$page_slug = sanitize_key( $_GET['page'] );
			$map = array(
				'wpat-woo-extra-options' => 'woo-extra-options',
				'wpat-woo-checkout-designer' => 'woo-checkout-designer',
				'wpat-woo-sale-badges'   => 'woo-sale-badges',
				'wpat-woo-address-autofill' => 'woo-address-autofill',
				'wpat-snippets'          => 'snippets',
				'wpat-woo-pdf-invoices'  => 'woo-pdf-invoices',
				'wpat-login-customizer'  => 'login-customizer',
				'wpat-seo'               => 'seo',
				'wpat-sitemap-xml'       => 'sitemap-xml',
				'wpat-tools'             => 'tools',
			);
			if ( isset( $map[ $page_slug ] ) ) {
				$mod_id = $map[ $page_slug ];
			}
		}

		$is_single_module_view = ! empty( $mod_id );
		?>
		<?php $is_dark = ( isset( $_COOKIE['wpat_theme_mode'] ) && 'dark' === $_COOKIE['wpat_theme_mode'] ); ?>
		<script>
		(function() {
			var saved = localStorage.getItem('wpat_theme_mode');
			if (saved === 'dark' || (!saved && document.cookie.indexOf('wpat_theme_mode=dark') !== -1)) {
				document.documentElement.classList.add('wpat-dark-mode');
				document.write('<style id="wpat-early-dark">html.wpat-dark-mode, html.wpat-dark-mode body, html.wpat-dark-mode #wpbody-content, html.wpat-dark-mode .wpat-admin-wrapper { background-color: #0f172a !important; color: #f8fafc !important; }</style>');
			}
		})();
		</script>
		<div class="wrap wpat-admin-wrapper <?php echo $is_dark ? 'wpat-dark-mode' : ''; ?>">
			<div class="wpat-header" style="display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
				<div class="wpat-title-area">
					<h1>WP Agency Toolkit <span class="wpat-badge">v<?php echo esc_html( WPAT_VERSION ); ?></span></h1>
					<p class="description" style="color: #cbd5e1; margin: 0;">Optimiza, asegura y potencia tus sitios de WordPress con esta suite modular Zero-Bloat.</p>
				</div>

				<div class="wpat-header-actions" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
					<button type="button" id="wpat_theme_toggle_btn" class="wpat-theme-toggle-btn" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#fff; height:34px; line-height:32px; padding:0 14px; border-radius:6px; cursor:pointer; font-weight:600; font-size:12px; display:inline-flex; align-items:center; gap:6px; box-shadow:none;">
						<span id="wpat_theme_icon">☀️</span> <span id="wpat_theme_label">Modo Oscuro</span>
					</button>

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
					<div id="wpat_updater_header_container" style="display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,0.05); padding: 6px 14px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);">
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
			</div>

			<form method="post" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpat_save_settings_action', 'wpat_settings_nonce' ); ?>
				<?php wp_nonce_field( 'wpat_cleanup_nonce_action', 'wpat_cleanup_ajax_nonce' ); ?>

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

				<?php if ( $is_single_module_view ) : ?>
					<div class="wpat-back-bar" style="margin: 15px 0 20px 0; display: flex; align-items: center; justify-content: space-between; background: #fff; padding: 12px 20px; border-radius: 8px; border: 1px solid #dcdcde; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
						<a href="<?php echo esc_url( add_query_arg( array( 'cat' => isset( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : '' ), admin_url( 'admin.php?page=wp-agency-toolkit' ) ) ); ?>" class="button button-secondary" style="background: #f6f7f7; border-color: #cbd5e1; color: #1e293b; font-weight: 700; border-radius: 6px; height: 34px; line-height: 32px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
							<span class="dashicons dashicons-arrow-left-alt" style="font-size: 16px; width: 16px; height: 16px; margin: 0; line-height: 1;"></span> Volver al Centro de Módulos
						</a>
						<button type="submit" name="wpat_save_settings" value="1" class="button button-primary" style="background: #2271b1; border-color: #135e96; font-weight: 700; height: 34px; line-height: 32px; padding: 0 20px; border-radius: 6px;">
							Guardar Cambios
						</button>
					</div>
				<?php endif; ?>

				<div class="wpat-container">
					<div class="wpat-tabs-content">
						<?php if ( $is_single_module_view ) : ?>
							<div class="wpat-single-module-standalone-wrapper" style="width: 100%;">
								<?php $this->render_single_module_standalone_view( $mod_id, $settings ); ?>
							</div>
						<?php else : ?>
							<!-- CENTRO DE MÓDULOS (DASHBOARD GRID) -->
							<div id="tab-modules" class="wpat-tab-panel active">
								<div class="wpat-layout-container" style="display: flex; gap: 20px; align-items: flex-start;">
									<!-- COLUMNA VERTICAL NAVEGACIÓN (ESCRITORIO) -->
									<aside class="wpat-cat-sidebar" style="width: 230px; flex-shrink: 0; background: var(--wpat-card-bg, #fff); border: 1px solid var(--wpat-border, #e2e8f0); border-radius: 12px; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
										<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; padding: 6px 10px 10px 10px; border-bottom: 1px solid var(--wpat-border, #e2e8f0); margin-bottom: 8px;">
											Categorías
										</div>
										<div class="wpat-cat-nav-list" style="display: flex; flex-direction: column; gap: 4px;">
											<button type="button" class="wpat-cat-item active" data-cat="all">
												<span class="wpat-cat-label">📌 Todos</span>
												<span class="wpat-cat-badge">36</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="woocommerce">
												<span class="wpat-cat-label">🛍️ WooCommerce</span>
												<span class="wpat-cat-badge">10</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="security">
												<span class="wpat-cat-label">🛡️ Seguridad</span>
												<span class="wpat-cat-badge">6</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="performance">
												<span class="wpat-cat-label">⚡ Rendimiento & SEO</span>
												<span class="wpat-cat-badge">7</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="tools">
												<span class="wpat-cat-label">🛠️ Herramientas</span>
												<span class="wpat-cat-badge">6</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="system">
												<span class="wpat-cat-label">⚙️ Sistema & Admin</span>
												<span class="wpat-cat-badge">7</span>
											</button>
											<div class="wpat-sidebar-divider"></div>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&mod=tools' ) ); ?>" class="wpat-cat-direct-link">
												<span class="wpat-cat-label">🛠️ Salud & Limpieza BD</span>
											</a>
										</div>
									</aside>

									<!-- SELECTOR DESPLEGABLE MÓVIL (< 768px) -->
									<div class="wpat-mobile-cat-container" style="display: none; width: 100%; margin-bottom: 15px;">
										<label for="wpat_mobile_cat_select" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; display: block; margin-bottom: 6px;">Categoría:</label>
										<select id="wpat_mobile_cat_select" style="width: 100%; height: 38px; border-radius: 8px; border: 1px solid #cbd5e1; padding: 0 12px; font-weight: 600; font-size: 13px; background: #fff;">
											<option value="all">📌 Todos (36)</option>
											<option value="woocommerce">🛍️ WooCommerce (10)</option>
											<option value="security">🛡️ Seguridad (6)</option>
											<option value="performance">⚡ Rendimiento & SEO (7)</option>
											<option value="tools">🛠️ Herramientas (6)</option>
											<option value="system">⚙️ Sistema & Admin (7)</option>
										</select>
									</div>

									<!-- ÁREA PRINCIPAL CON BUSCADOR Y GRID -->
									<main class="wpat-main-content" style="flex: 1; min-width: 0;">
										<div class="wpat-dashboard-toolbar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap; background: var(--wpat-card-bg, #fff); border: 1px solid var(--wpat-border, #e2e8f0); padding: 14px 18px; border-radius: 12px;">
											<div>
												<h2 style="margin:0 0 4px 0; font-size:18px; font-weight:700;">Centro de Módulos & Herramientas</h2>
												<p class="section-desc" style="margin:0; color:#646970; font-size:13px;">Activa o desactiva utilidades de forma independiente para mantener tu sitio rápido y ligero.</p>
											</div>
											<div class="wpat-search-box" style="position: relative; min-width: 240px;">
												<span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 9px; color: #94a3b8; font-size: 16px;"></span>
												<input type="text" id="wpat_modules_search_input" placeholder="Buscar módulo..." style="padding-left: 32px; width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; height: 34px; font-size: 13px;" />
											</div>
										</div>

										<div class="wpat-modules-grid-container" id="wpat_modules_grid">
											<?php $this->render_all_modules_grid_cards( $settings ); ?>
										</div>
									</main>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

			</form>
		</div>
		<?php
	}

	/**
	 * Renderiza el grid completo de 35 tarjetas de módulos en el Centro de Módulos.
	 */
		public function render_all_modules_grid_cards( $settings ) {
				$modules_data = array(
			// WOOCOMMERCE (9)
			array(
				'id'          => 'woo-extra-options',
				'title'       => 'Campos Extra',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Añade opciones personalizadas a productos (textos, selects, archivos) y convierte desplegables en botones de color.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🛍️',
				'icon_bg'     => 'woo',
				'keywords'    => 'campos extras swatches woocommerce opciones producto'
			),
			array(
				'id'          => 'woo-pdf-invoices',
				'title'       => 'Facturación PDF',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Genera facturas y albaranes en PDF adjuntos automáticamente a los correos de pedido de WooCommerce.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '📄',
				'icon_bg'     => 'woo',
				'keywords'    => 'facturas pdf albaranes woocommerce'
			),
			array(
				'id'          => 'woo-live-search',
				'title'       => 'Buscador Live AJAX',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Reemplaza la búsqueda estándar por autocompletado ultra rápido por SKU, nombre e ID de producto.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🔍',
				'icon_bg'     => 'woo',
				'keywords'    => 'buscador live ajax woocommerce sku'
			),
			array(
				'id'          => 'woo-facets',
				'title'       => 'Filtros por Facetas',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Filtros ultrarrápidos para catálogo por precio, stock, categorías y atributos tipo FacetWP.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '⚡',
				'icon_bg'     => 'woo',
				'keywords'    => 'filtros facetas woocommerce facetwp catalogo'
			),
			array(
				'id'          => 'woo-checkout-editor',
				'title'       => 'Editor de Campos Checkout',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Añade, edita u oculta campos en el formulario de finalizar compra de WooCommerce.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🛒',
				'icon_bg'     => 'woo',
				'keywords'    => 'editor checkout campos finalizar compra'
			),
			array(
				'id'          => 'woo-dni',
				'title'       => 'Campo DNI / CIF',
				'badge'       => 'Automático',
				'badge_class' => 'tweak',
				'desc'        => 'Inyecta un campo obligatorio de DNI/CIF en los datos de facturación de WooCommerce.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🆔',
				'icon_bg'     => 'woo',
				'keywords'    => 'dni cif nif woocommerce checkout',
				'has_settings'=> false
			),
			array(
				'id'          => 'woo-catalog',
				'title'       => 'Modo Catálogo',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Desactiva la compra de productos, ocultando precios o los botones de añadir al carrito.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🏷️',
				'icon_bg'     => 'woo',
				'keywords'    => 'modo catalogo ocultar precios carrito whatsapp'
			),
			array(
				'id'          => 'woo-sale-badges',
			'title'       => 'Badges y Etiquetas de Oferta',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Añade badges y cintas de oferta de alto impacto personalizables (Soft, Pill, Rect, Circle, Corner Ribbon, Price Tag) con cálculo automático de % de descuento.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🏷️',
				'icon_bg'     => 'woo',
				'keywords'    => 'badges oferta etiquetas descuento sale flash ribbon woocommerce'
			),

			array(
				'id'          => 'woo-checkout-designer',
				'is_new'      => true,
				'title'       => 'Diseñador de Checkout',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Diseño de checkout de alta conversión con 4 plantillas (Classic, Express, Accordion, Minimalist) y optimizaciones.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🎨',
				'icon_bg'     => 'woo',
				'keywords'    => 'checkout diseñador plantillas woocommerce plantilla classic express minimalista acordeon'
			),
			array(
				'id'          => 'woo-address-autofill',
				'is_new'      => true,
				'title'       => 'Autocompletado de CP y Provincia',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Detecta automáticamente la Provincia y sugiere la Población según el Código Postal (España).',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '📍',
				'icon_bg'     => 'woo',
				'keywords'    => 'autocompletado codigo postal provincia poblacion españa woocommerce checkout'
			),
			array(
				'id'          => 'woo-zoom',
				'title'       => 'Zoom en Galería',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Desactiva de forma independiente funciones nativas de la galería de producto como Zoom, Lightbox o Slider.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🔍',
				'icon_bg'     => 'woo',
				'keywords'    => 'woo zoom galeria lightbox slider desactivar'
			),
			array(
				'id'          => 'woo-variation-swatches',
				'title'       => 'Swatches de Variación',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Transforma desplegables de variaciones en botones visuales de color, imagen o etiqueta.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🎨',
				'icon_bg'     => 'woo',
				'keywords'    => 'swatches variaciones botones color imagen atributos'
			),

			// SEGURIDAD (6)
			array(
				'id'          => 'security-hardening',
				'title'       => 'Fortalecimiento de Seguridad',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Protección activa contra inyecciones PHP, desactivación de XML-RPC, enumeración de usuarios y ocultación de versión.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🛡️',
				'icon_bg'     => 'sec',
				'keywords'    => 'seguridad hardening xmlrpc uploads php usuarios edicion'
			),
			array(
				'id'          => 'hide-login',
				'title'       => 'Ocultar Acceso Admin',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Cambia la URL nativa wp-login.php por un slug personalizado e incluye captcha anti fuerza bruta.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🔐',
				'icon_bg'     => 'sec',
				'keywords'    => 'ocultar login acceso wp-login slug captcha'
			),
			array(
				'id'          => 'bot-blocker',
				'title'       => 'Bloqueador de Bots Anti-DDoS',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Limita solicitudes maliciosas por IP por segundo y bloquea bots abusivos o ataques DDoS.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🤖',
				'icon_bg'     => 'sec',
				'keywords'    => 'bot blocker ddos ip whitelist solicitudes limite'
			),
			array(
				'id'          => 'anti-spam',
				'title'       => 'Anti-Spam en Formularios',
				'badge'       => 'Automático',
				'badge_class' => 'tweak',
				'desc'        => 'Protección Honeypot invisible sin captchas molestos para comentarios y formularios.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🚫',
				'icon_bg'     => 'sec',
				'keywords'    => 'antispam honeypot comentarios formularios',
				'has_settings'=> false
			),
			array(
				'id'          => 'conflict-detector',
				'title'       => 'Detector de Conflictos JS/CSS',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Supervisa errores de Javascript en la consola y problemas de carga de scripts de plugins.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '⚠️',
				'icon_bg'     => 'sec',
				'keywords'    => 'conflictos js css errores consola depuracion'
			),
			array(
				'id'          => 'ssl-fixer',
				'title'       => 'Forzar SSL & Contenido Mixto',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Fuerza redirección HTTPS y repara automáticamente imágenes o scripts cargados por HTTP.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🔒',
				'icon_bg'     => 'sec',
				'keywords'    => 'ssl https contenido mixto redireccion 301'
			),

			// RENDIMIENTO & SEO (7)
			array(
				'id'          => 'performance',
				'title'       => 'Optimización de Rendimiento',
				'badge'       => 'Automático',
				'badge_class' => 'tweak',
				'desc'        => 'Limpieza de cabeceras WP, control de Heartbeat API, límites de revisiones y desactivación de emojis.',
				'cat_class'   => 'cat-performance cat-perf',
				'icon'        => '⚡',
				'icon_bg'     => 'perf',
				'keywords'    => 'rendimiento performance heartbeat emojis cabeceras',
				'has_settings'=> false
			),
			array(
				'id'          => 'disable-comments',
				'title'       => 'Deshabilitar Comentarios',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Desactiva globalmente o por tipos de contenido los comentarios, trackbacks y widgets.',
				'cat_class'   => 'cat-performance cat-perf',
				'icon'        => '💬',
				'icon_bg'     => 'perf',
				'keywords'    => 'deshabilitar comentarios trackbacks spam'
			),
			array(
				'id'          => 'image-optimizer',
				'title'       => 'Optimización Medios & WebP',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Compresión en lote y conversión automática a formato WebP de nueva generación al subir imágenes.',
				'cat_class'   => 'cat-performance cat-perf',
				'icon'        => '🖼️',
				'icon_bg'     => 'perf',
				'keywords'    => 'optimizacion imagenes webp compresion biblioteca'
			),
			array(
				'id'          => 'svg-support',
				'title'       => 'Soporte Archivos SVG',
				'badge'       => 'Automático',
				'badge_class' => 'tweak',
				'desc'        => 'Permite la subida segura de archivos vectoriales SVG a la biblioteca multimedia con sanitización.',
				'cat_class'   => 'cat-performance cat-perf',
				'icon'        => '📐',
				'icon_bg'     => 'perf',
				'keywords'    => 'svg vectorial biblioteca medios soporte',
				'has_settings'=> false
			),
			array(
				'id'          => 'seo',
				'title'       => 'Optimización SEO Integrada',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Campos SEO en el editor (título, meta descripción, noindex), previsualización de Google y metadatos Open Graph.',
				'cat_class'   => 'cat-performance cat-perf',
				'icon'        => '🚀',
				'icon_bg'     => 'perf',
				'keywords'    => 'seo optimizacion meta titulos auditoria alt'
			),
			array(
				'id'          => 'sitemap-xml',
				'title'       => 'Generador Sitemap XML',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Genera automáticamente un sitemap XML dinámico en la raíz (/sitemap.xml) excluyendo contenido noindex.',
				'cat_class'   => 'cat-performance cat-perf cat-seo',
				'icon'        => '🗺️',
				'icon_bg'     => 'perf',
				'keywords'    => 'sitemap xml seo sitemaps noindex'
			),
			array(
				'id'          => 'reading-progress',
				'title'       => 'Progreso de Lectura',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Muestra una barra de progreso superior animada al hacer scroll en artículos del blog.',
				'cat_class'   => 'cat-performance cat-perf',
				'icon'        => '📏',
				'icon_bg'     => 'perf',
				'keywords'    => 'barra progreso lectura scroll blog'
			),

			// HERRAMIENTAS (6)
			array(
				'id'          => 'snippets',
				'title'       => 'Snippets de Código',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Gestor ligero de fragmentos PHP, CSS y JS sin editar functions.php con ejecución segura.',
				'cat_class'   => 'cat-tools',
				'icon'        => '💻',
				'icon_bg'     => 'perf',
				'keywords'    => 'snippets codigo php css js fragmentos'
			),
			array(
				'id'          => 'duplicator',
				'title'       => 'Duplicador Entradas/Páginas',
				'badge'       => 'Automático',
				'badge_class' => 'tweak',
				'desc'        => 'Duplica entradas, páginas o CPTs con 1-clic conservando la estructura y campos personalizados.',
				'cat_class'   => 'cat-tools',
				'icon'        => '📋',
				'icon_bg'     => 'perf',
				'keywords'    => 'duplicar clonar entradas paginas cpts',
				'has_settings'=> false
			),
			array(
				'id'          => 'post-csv-importer',
				'title'       => 'Exportar / Importar CSV & JSON',
				'badge'       => 'Herramienta',
				'badge_class' => 'tweak',
				'desc'        => 'Respalda o migra contenidos completos en JSON o edita páginas/entradas en masa vía CSV.',
				'cat_class'   => 'cat-tools',
				'icon'        => '📊',
				'icon_bg'     => 'admin',
				'keywords'    => 'exportador importador csv json respaldo migracion'
			),
			array(
				'id'          => 'accessibility',
				'title'       => 'Accesibilidad Web',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Botón flotante de accesibilidad para ajustar tamaño de letra, contraste y modo de lectura.',
				'cat_class'   => 'cat-tools',
				'icon'        => '♿',
				'icon_bg'     => 'perf',
				'keywords'    => 'accesibilidad fuente contraste lectura boton flotante'
			),
			array(
				'id'          => 'integrations',
				'title'       => 'Integraciones & Scripts',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Inyecta código de Google Analytics, Tag Manager, Facebook Pixel y scripts en Head/Body sin plugins.',
				'cat_class'   => 'cat-tools',
				'icon'        => '🔗',
				'icon_bg'     => 'admin',
				'keywords'    => 'integraciones analytics tag manager pixel scripts head body'
			),
			array(
				'id'          => 'whatsapp',
				'title'       => 'Botón Flotante WhatsApp',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Añade un botón flotante directo de contacto por WhatsApp en la esquina de tu sitio web.',
				'cat_class'   => 'cat-tools',
				'icon'        => '💬',
				'icon_bg'     => 'woo',
				'keywords'    => 'whatsapp boton flotante contacto chat'
			),

			// SISTEMA & ADMIN (7)
			array(
				'id'          => 'initial-setup',
				'title'       => 'Configuración Inicial Sitio',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Configura rápidamente zona horaria, enlaces permanentes, página de inicio y limpieza inicial.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '⚙️',
				'icon_bg'     => 'admin',
				'keywords'    => 'configuracion inicial sitio permalinks zona horaria'
			),
			array(
				'id'          => 'dashboard_cleaner',
				'title'       => 'Escritorio Personalizado',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Sustituye el Escritorio estándar por un panel de control limpio con accesos rápidos y estadísticas.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '📊',
				'icon_bg'     => 'admin',
				'keywords'    => 'escritorio personalizado limpiador widgets soporte'
			),
			array(
				'id'          => 'silent-skin',
				'title'       => 'Ocultar Huella WPAT',
				'badge'       => 'Automático',
				'badge_class' => 'tweak',
				'desc'        => 'Modo Marca Blanca para agencias: oculta las menciones de WP Agency Toolkit a los clientes final.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '🎭',
				'icon_bg'     => 'admin',
				'keywords'    => 'marca blanca marca agencia huella silent skin',
				'has_settings'=> false
			),
			array(
				'id'          => 'hide_admin_bar',
				'title'       => 'Restringir Barra & Acceso Admin',
				'badge'       => 'Automático',
				'badge_class' => 'tweak',
				'desc'        => 'Oculta la barra superior negra de WordPress y bloquea el acceso a wp-admin a clientes/suscriptores.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '🚫',
				'icon_bg'     => 'sec',
				'keywords'    => 'restringir barra admin wp-admin acceso clientes',
				'has_settings'=> false
			),
			array(
				'id'          => 'smtp',
				'title'       => 'Servidor SMTP',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Servicio seguro de envío de correo SMTP con soporte TLS/SSL y comprobación de envío.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '📧',
				'icon_bg'     => 'admin',
				'keywords'    => 'smtp correo envio email servidor tls ssl'
			),
			array(
				'id'          => 'envato-importer',
				'title'       => 'Importador Kits Template',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Importador masivo de kits de maquetación de Envato Elements y plantillas listas para Elementor.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '📥',
				'icon_bg'     => 'admin',
				'keywords'    => 'importador kits plantillas envato elementor'
			),
			array(
				'id'          => 'tools',
				'title'       => 'Salud & Limpieza BD',
				'badge'       => 'Herramienta',
				'badge_class' => 'subpage',
				'desc'        => 'Limpieza profunda de la base de datos, detector de conflictos, escaneo de imágenes huérfanas e informes.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '🛠️',
				'icon_bg'     => 'admin',
				'keywords'    => 'salud herramientas base de datos imagenes no usadas detector conflictos',
				'always_active'=> true
			),
		);

		$active_cat = isset( $_GET['cat'] ) && ! empty( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : 'all';

		foreach ( $modules_data as $mod ) {
			$is_active = ( isset( $settings[ $mod['id'] ] ) && '1' === $settings[ $mod['id'] ] );
			$has_settings = ! isset( $mod['has_settings'] ) || true === $mod['has_settings'];
			$is_always_active = isset( $mod['always_active'] ) && true === $mod['always_active'];
			$is_new = ! empty( $mod['is_new'] ) || $this->is_new_module( isset( $mod['id'] ) ? $mod['id'] : '' );
			$search_text = $mod['title'] . ' ' . $mod['desc'] . ' ' . ( isset( $mod['keywords'] ) ? $mod['keywords'] : '' ) . ' ' . $mod['id'] . ' ' . ( isset( $mod['badge'] ) ? $mod['badge'] : '' );

			$is_visible = ( 'all' === $active_cat || strpos( $mod['cat_class'], 'cat-' . $active_cat ) !== false || strpos( $mod['cat_class'], $active_cat ) !== false );
			$card_style = $is_visible ? '' : 'style="display:none;"';
			?>
			<div class="wpat-module-grid-card <?php echo esc_attr( $mod['cat_class'] ); ?>" <?php echo $card_style; ?> data-name="<?php echo esc_attr( isset( $mod['keywords'] ) ? $mod['keywords'] : '' ); ?>" data-search="<?php echo esc_attr( mb_strtolower( $search_text, 'UTF-8' ) ); ?>">
				<?php if ( $is_new ) : ?>
					<div class="wpat-card-badge-new">NUEVO</div>
				<?php endif; ?>
				<div class="wpat-card-top">
					<div class="wpat-card-icon-box <?php echo esc_attr( $mod['icon_bg'] ); ?>"><?php echo $mod['icon']; ?></div>
					<?php if ( $is_always_active ) : ?>
						<span class="wpat-badge" style="background: #e2e8f0; color: #475569; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 12px;">Siempre Activo</span>
					<?php else : ?>
						<label class="wpat-toggle-switch">
							<input type="checkbox" class="wpat-ajax-toggle-module" data-module="<?php echo esc_attr( $mod['id'] ); ?>" <?php checked( $is_active ); ?>>
							<span class="wpat-toggle-slider"></span>
						</label>
					<?php endif; ?>
				</div>
				<div class="wpat-card-content">
					<h3><?php echo esc_html( $mod['title'] ); ?> <span class="wpat-badge-<?php echo esc_attr( $mod['badge_class'] ); ?>"><?php echo esc_html( $mod['badge'] ); ?></span></h3>
					<p><?php echo esc_html( $mod['desc'] ); ?></p>
				</div>
				<div class="wpat-card-bottom">
					<span class="wpat-module-status-indicator <?php echo ( $is_always_active || $is_active ) ? 'active' : ''; ?>">
						<span class="dot"></span> <span class="text"><?php echo ( $is_always_active || $is_active ) ? 'Activo' : 'Inactivo'; ?></span>
					</span>
					<?php if ( $has_settings ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-agency-toolkit', 'mod' => $mod['id'], 'cat' => isset( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : '' ), admin_url( 'admin.php' ) ) ); ?>" class="wpat-card-action-btn primary wpat-remember-scroll-btn <?php echo ( $is_always_active || $is_active ) ? '' : 'disabled'; ?>">
							<?php echo ( $mod['id'] === 'tools' ) ? 'Herramientas ⚙️' : 'Ajustes ⚙️'; ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Renderiza el contenido del Importador de Kits de Envato.
	 */
	public function render_tab_kits_content( $settings ) {
		if ( ! class_exists( 'WPAT_Envato_Importer' ) ) {
			require_once WPAT_PATH . 'includes/modules/class-wpat-envato-importer.php';
		}
		$importer = WPAT_Envato_Importer::get_instance();
		$kits     = $importer->get_kits_with_plugin_status();

		$active_slug = isset( $_GET['kit_slug'] ) ? sanitize_title( $_GET['kit_slug'] ) : '';
		$active_kit  = ( ! empty( $active_slug ) && isset( $kits[ $active_slug ] ) ) ? $kits[ $active_slug ] : null;

		?>
		<div class="wpat-module-card" style="margin-bottom: 25px;">
			<div class="wpat-module-header">
				<div class="wpat-module-info">
					<h3>Importador de Template Kits (Envato & Elementor)</h3>
					<p>Sube tus archivos ZIP de kits de plantillas de Envato Elements para gestionarlos e importarlos en Elementor.</p>
				</div>
				<?php $this->render_module_toggle( 'envato-importer', $settings, true ); ?>
			</div>

			<div class="wpat-module-body" style="display: block; padding: 20px;">
				<?php if ( $active_kit ) : ?>
					<!-- VISTA DETALLADA DEL KIT SELECCIONADO (Subpágina de Plantillas) -->
					<div class="wpat-kit-detail-view">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--wpat-border, #e2e8f0); padding-bottom: 15px;">
							<div>
								<h3 style="margin: 0 0 5px 0; font-size: 18px; font-weight: 700;">Plantillas del Kit: <?php echo esc_html( $active_kit['title'] ); ?></h3>
								<p style="margin: 0; color: #64748b; font-size: 13px;">Explora e importa las plantillas individuales directamente a tu biblioteca de Elementor.</p>
							</div>
							<a href="<?php echo esc_url( remove_query_arg( 'kit_slug' ) ); ?>" class="button button-secondary">
								← Volver a los Kits
							</a>
						</div>

						<!-- REQUISITOS Y PLUGINS REQUERIDOS -->
						<?php if ( ! empty( $active_kit['required_plugins'] ) ) : ?>
							<div class="wpat-required-plugins-box" style="background: var(--wpat-card-bg, #f8fafc); border: 1px solid var(--wpat-border, #cbd5e1); border-radius: 8px; padding: 18px; margin-bottom: 25px;">
								<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 700; color: #1e293b;">🔌 Plugins Requeridos por el Kit</h4>
								<p style="margin: 0 0 12px 0; font-size: 12px; color: #64748b;">Para garantizar que las plantillas de este kit funcionen y se vean correctamente, es necesario tener instalados y activos los siguientes plugins:</p>
								<div style="display: flex; flex-wrap: wrap; gap: 10px;">
									<?php foreach ( $active_kit['required_plugins'] as $req ) : ?>
										<?php
										$is_active = ! empty( $req['active'] );
										$is_installed = ! empty( $req['installed'] );
										?>
										<div style="display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 6px; font-size: 12px;">
											<strong><?php echo esc_html( $req['name'] ); ?></strong>
											<?php if ( $is_active ) : ?>
												<span style="background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px;">Activo</span>
											<?php elseif ( $is_installed ) : ?>
												<span style="background: #fef9c3; color: #a16207; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px;">Instalado</span>
												<button type="button" class="button button-small wpat-activate-plugin-btn" data-slug="<?php echo esc_attr( $req['slug'] ); ?>" style="font-size: 11px; height: 22px; line-height: 20px; padding: 0 8px;">Activar</button>
											<?php else : ?>
												<span style="background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10px;">No Instalado</span>
												<button type="button" class="button button-small button-primary wpat-install-plugin-btn" data-slug="<?php echo esc_attr( $req['slug'] ); ?>" style="font-size: 11px; height: 22px; line-height: 20px; padding: 0 8px;">Instalar</button>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endif; ?>

						<!-- GRILLA DE PLANTILLAS DEL KIT -->
						<?php if ( ! empty( $active_kit['templates'] ) ) : ?>
							<div class="wpat-kit-templates-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;">
								<?php foreach ( $active_kit['templates'] as $tpl ) : ?>
									<?php
									$is_global = (
										false !== strpos( strtolower( $tpl['title'] ), 'global' ) ||
										'global-styles' === $tpl['type'] ||
										'kit-settings' === $tpl['type'] ||
										'global.json' === basename( $tpl['file'] )
									);
									?>
									<div class="wpat-template-card" style="background: #fff; border: 1px solid var(--wpat-border, #cbd5e1); border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
										<div>
											<div style="width: 100%; height: 170px; background: #f1f5f9; position: relative; overflow: hidden; border-bottom: 1px solid #e2e8f0;">
												<?php if ( ! empty( $tpl['thumbnail'] ) ) : ?>
													<img src="<?php echo esc_url( $tpl['thumbnail'] ); ?>" alt="<?php echo esc_attr( $tpl['title'] ); ?>" style="width: 100%; height: 100%; object-fit: cover;" />
												<?php else : ?>
													<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #94a3b8; font-size: 12px; font-weight: 600;">
														📄 <?php echo esc_html( strtoupper( $tpl['type'] ) ); ?>
													</div>
												<?php endif; ?>
												<span style="position: absolute; bottom: 8px; left: 8px; background: rgba(15,23,42,0.8); color: #fff; font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; backdrop-filter: blur(2px);">
													<?php echo esc_html( $tpl['type'] ); ?>
												</span>
											</div>
											<div style="padding: 12px 14px;">
												<h5 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 700; color: #1e293b;"><?php echo esc_html( $tpl['title'] ); ?></h5>
											</div>
										</div>
										<div style="padding: 12px 14px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; gap: 8px; align-items: center;">
											<?php if ( $is_global ) : ?>
												<button type="button" class="button button-primary wpat-admin-import-template-btn" data-kit="<?php echo esc_attr( $active_slug ); ?>" data-id="<?php echo esc_attr( $tpl['id'] ); ?>" style="flex: 1;">
													Aplicar Estilos Globales
												</button>
											<?php else : ?>
												<?php if ( ! empty( $tpl['preview_url'] ) ) : ?>
													<a href="<?php echo esc_url( $tpl['preview_url'] ); ?>" target="_blank" class="button button-secondary" style="padding: 0 8px;" title="Ver vista previa">👁️ Ver</a>
												<?php endif; ?>
												<button type="button" class="button button-primary wpat-admin-import-template-btn" data-kit="<?php echo esc_attr( $active_slug ); ?>" data-id="<?php echo esc_attr( $tpl['id'] ); ?>" style="flex: 1;">
													Importar a Elementor
												</button>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; text-align: center; border-radius: 6px; color: #64748b;">
								No se encontraron plantillas dentro de este kit.
							</div>
						<?php endif; ?>
					</div>

				<?php else : ?>
					<!-- VISTA PRINCIPAL (CARGADOR ZIP + LISTA DE KITS INSTALADOS) -->
					<div class="wpat-kit-upload-box" style="background: var(--wpat-card-bg, #f8fafc); border: 2px dashed var(--wpat-border, #cbd5e1); border-radius: 8px; padding: 25px; text-align: center; margin-bottom: 25px;">
						<span class="dashicons dashicons-cloud-upload" style="font-size: 42px; width: 42px; height: 42px; color: #2563eb; margin-bottom: 10px; display: inline-block;"></span>
						<h4 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 700;">Sube tu archivo ZIP de Kit de Plantilla</h4>
						<p style="margin: 0 0 15px 0; color: #64748b; font-size: 13px;">Arrastra tu archivo ZIP aquí o haz clic en el botón para seleccionarlo desde tu ordenador.</p>
						<form id="wpat_envato_upload_form" style="display: inline-flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center;">
							<?php wp_nonce_field( 'wpat_envato_importer_nonce', 'wpat_envato_nonce' ); ?>
							<input type="file" name="kit_zip" id="wpat_kit_zip_input" accept=".zip" style="font-size: 13px;" required />
							<button type="submit" class="button button-primary" id="wpat_upload_kit_btn">Seleccionar ZIP de Kit</button>
						</form>
						<div id="wpat_kit_upload_status" style="margin-top: 12px; font-size: 13px; font-weight: 600; display: none;"></div>
					</div>

					<h4 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 700;">Kits Instalados</h4>
					<?php if ( ! empty( $kits ) ) : ?>
						<div class="wpat-kits-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
							<?php foreach ( $kits as $slug => $kit ) : ?>
								<?php
								$num_templates = count( isset( $kit['templates'] ) ? $kit['templates'] : array() );
								$num_reqs      = count( isset( $kit['required_plugins'] ) ? $kit['required_plugins'] : array() );
								?>
								<div class="wpat-kit-card" style="background: #fff; border: 1px solid var(--wpat-border, #cbd5e1); border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between;">
									<div>
										<?php if ( ! empty( $kit['thumbnail'] ) ) : ?>
											<img src="<?php echo esc_url( $kit['thumbnail'] ); ?>" alt="<?php echo esc_attr( $kit['title'] ); ?>" style="width: 100%; height: 160px; object-fit: cover;" />
										<?php endif; ?>
										<div style="padding: 15px;">
											<h4 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 700;"><?php echo esc_html( $kit['title'] ); ?></h4>
											<div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
												<span style="background: #e0e7ff; color: #3730a3; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; border: 1px solid #c7d2fe;">
													<?php echo esc_html( $num_templates ); ?> plantillas
												</span>
												<?php if ( $num_reqs > 0 ) : ?>
													<span style="background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; border: 1px solid #fecaca;">
														<?php echo esc_html( $num_reqs ); ?> plugins necesarios
													</span>
												<?php endif; ?>
											</div>

											<?php if ( ! empty( $kit['required_plugins'] ) ) : ?>
												<div style="margin-bottom: 12px; font-size: 11px; background: #f8fafc; padding: 8px 10px; border-radius: 4px; border: 1px solid #e2e8f0;">
													<strong>Requisitos:</strong>
													<ul style="margin: 4px 0 0 15px; padding: 0; list-style-type: disc;">
														<?php foreach ( $kit['required_plugins'] as $req ) : ?>
															<li style="color: <?php echo ! empty( $req['active'] ) ? '#16a34a' : '#dc2626'; ?>;">
																<?php echo esc_html( $req['name'] ); ?> 
																(<?php echo ! empty( $req['active'] ) ? 'Activo' : ( ! empty( $req['installed'] ) ? 'Instalado' : 'No Instalado' ); ?>)
															</li>
														<?php endforeach; ?>
													</ul>
												</div>
											<?php endif; ?>
										</div>
									</div>
									<div style="padding: 12px 15px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
										<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-agency-toolkit', 'mod' => 'envato-importer', 'kit_slug' => $slug ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">Ver Plantillas</a>
										<button type="button" class="button button-link-delete wpat-delete-kit-btn" data-slug="<?php echo esc_attr( $slug ); ?>" style="color: #ef4444; text-decoration: none;">Eliminar Kit</button>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; text-align: center; border-radius: 6px; color: #64748b;">
							No hay kits de plantillas subidos actualmente. Sube un archivo .zip para comenzar.
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function render_tab_tools_content( $settings ) {
		if ( ! class_exists( 'WPAT_Post_CSV_Importer' ) ) {
			require_once WPAT_PATH . 'includes/modules/class-wpat-post-csv-importer.php';
		}
		?>
		<div class="wpat-module-card" style="margin-bottom: 25px;">
			<div class="wpat-module-header">
				<div class="wpat-module-info">
					<h3>Exportar e Importar Entradas/Páginas (CSV & JSON)</h3>
					<p>Exporta entradas, páginas o CPTs a un archivo CSV editable en Excel/Google Sheets, o importa contenidos masivamente con imágenes destacadas y taxonomías.</p>
				</div>
				<?php $this->render_module_toggle( 'post-csv-importer', $settings, true ); ?>
			</div>
			<div class="wpat-module-body" style="display: block; padding: 20px;">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px;">
						<h4 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 700;">📤 Exportar Contenidos</h4>
						<p style="margin: 0 0 15px 0; font-size: 13px; color: #64748b;">Descarga todas las entradas o páginas del sitio en un archivo CSV estructurado.</p>
						<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=wpat_csv_export_posts' ) ); ?>" class="button button-secondary">Descargar CSV de Entradas</a>
						<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=wpat_csv_download_sample' ) ); ?>" class="button button-link" style="margin-left: 10px;">Plantilla de Ejemplo CSV</a>
					</div>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px;">
						<h4 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 700;">📥 Importación Masiva en Lote</h4>
						<p style="margin: 0 0 15px 0; font-size: 13px; color: #64748b;">Sube un archivo CSV formateado para crear o actualizar publicaciones automáticamente.</p>
						<input type="file" id="wpat_csv_import_file" accept=".csv" style="margin-bottom: 10px; display: block;" />
						<button type="button" class="button button-primary" id="wpat_start_csv_import_btn">Iniciar Importación CSV</button>
						<div id="wpat_csv_import_status" style="margin-top: 10px; font-size: 13px; display: none;"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_single_module_standalone_view( $mod_id, $settings ) {
		echo '<input type="hidden" name="wpat_saving_module" value="' . esc_attr( $mod_id ) . '" />';
		echo '<style>.wpat-module-body { display: block !important; } .wpat-collapse-btn { display: none !important; }</style>';

		switch ( $mod_id ) {
			case 'login-customizer':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Personalizador del Login</h3>
										<p>Personaliza el logotipo y el color de fondo de la pantalla de inicio de sesión (/wp-login.php).</p>
									</div>
									<?php $this->render_module_toggle( 'login-customizer', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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
									
								</div>
				<?php
				break;
			case 'hide_admin_bar':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Restringir Barra y Acceso de Admin</h3>
										<p>Oculta la barra superior negra de WordPress y bloquea el acceso a /wp-admin para usuarios con roles básicos (Suscriptores, Clientes de WooCommerce, etc.).</p>
									</div>
									<?php $this->render_module_toggle( 'hide_admin_bar', $settings, true ); ?>
								</div>
				<?php
				break;
			case 'dashboard_cleaner':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Escritorio Personalizado & Limpiador</h3>
										<p>Sustituye el Escritorio estándar por un panel de control alternativo, limpio y moderno con accesos rápidos y estadísticas.</p>
									</div>
									<?php $this->render_module_toggle( 'dashboard_cleaner', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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
									
									
								</div>
							</div>

							<!-- Módulo: Bloqueador de Bots por 404 -->
							
				<?php
				break;
			case 'bot-blocker':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Bloqueador de Bots por 404</h3>
										<p>Detecta y bloquea temporalmente direcciones IPs de bots maliciosos que generan múltiples errores 404 buscando vulnerabilidades.</p>
									</div>
									<?php $this->render_module_toggle( 'bot-blocker', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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

									
								</div>
				<?php
				break;
			case 'anti-spam':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Protección Anti-Spam Zero-Bloat</h3>
										<p>Bloquea spam automatizado en Formularios de Elementor, MetForm, ElementsKit, Comentarios y Reseñas sin usar servicios externos ni Captchas molestos.</p>
									</div>
									<?php $this->render_module_toggle( 'anti-spam', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									<?php
									$antispam_blocked_count = get_option( 'wpat_antispam_blocked_count', 0 );
									?>
									<div style="background: #f8fafc; border: 1px solid var(--wpat-border); padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
										<span style="font-weight: 600; font-size: 13px; color: #475569;">Spam bloqueado hasta la fecha:</span>
										<span style="font-weight: 700; font-size: 16px; color: #10b981; background: #d1fae5; padding: 2px 10px; border-radius: 12px;"><?php echo esc_html( number_format_i18n( $antispam_blocked_count ) ); ?> envíos</span>
									</div>

									<div class="wpat-field-group" style="margin-bottom: 12px;">
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_settings[antispam_honeypot]" value="1" <?php checked( isset( $settings['antispam_honeypot'] ) ? $settings['antispam_honeypot'] : '1', '1' ); ?> style="margin-right:8px;" />
											Activar campo trampa Honeypot (Invisible para humanos, detecta bots)
										</label>
									</div>

									<div class="wpat-field-group" style="margin-bottom: 12px;">
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_settings[antispam_time_check]" value="1" <?php checked( isset( $settings['antispam_time_check'] ) ? $settings['antispam_time_check'] : '1', '1' ); ?> style="margin-right:8px;" />
											Activar verificación de tiempo de envío (Bloquea envíos instantáneos menores a 2.5 segundos)
										</label>
									</div>

									<div class="wpat-field-group" style="margin-bottom: 12px;">
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
											<input type="checkbox" name="wpat_settings[antispam_block_cyrillic]" value="1" <?php checked( isset( $settings['antispam_block_cyrillic'] ) ? $settings['antispam_block_cyrillic'] : '1', '1' ); ?> style="margin-right:8px;" />
											Bloquear envíos con caracteres cirílicos (rusos) o alfabetos no latinos
										</label>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label for="wpat_antispam_max_links">Límite máximo de enlaces/URLs por envío</label>
										<select name="wpat_settings[antispam_max_links]" id="wpat_antispam_max_links" class="regular-text" style="display:block; margin-bottom:5px;">
											<option value="0" <?php selected( isset( $settings['antispam_max_links'] ) ? $settings['antispam_max_links'] : '2', '0' ); ?>>Sin enlaces (Bloquear cualquier URL)</option>
											<option value="1" <?php selected( isset( $settings['antispam_max_links'] ) ? $settings['antispam_max_links'] : '2', '1' ); ?>>Máximo 1 enlace</option>
											<option value="2" <?php selected( isset( $settings['antispam_max_links'] ) ? $settings['antispam_max_links'] : '2', '2' ); ?>>Máximo 2 enlaces (Recomendado)</option>
											<option value="3" <?php selected( isset( $settings['antispam_max_links'] ) ? $settings['antispam_max_links'] : '2', '3' ); ?>>Máximo 3 enlaces</option>
											<option value="99" <?php selected( isset( $settings['antispam_max_links'] ) ? $settings['antispam_max_links'] : '2', '99' ); ?>>Permitir ilimitados</option>
										</select>
										<p class="description">Rechaza envíos de formularios que contengan más hipervínculos que el límite fijado.</p>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label for="wpat_antispam_keywords">Lista negra de palabras de spam (Una por línea)</label>
										<textarea name="wpat_settings[antispam_keywords]" id="wpat_antispam_keywords" class="large-text" rows="3" placeholder="casino, crypto, viagra, loans, seo ranking"><?php echo esc_textarea( isset( $settings['antispam_keywords'] ) ? $settings['antispam_keywords'] : '' ); ?></textarea>
										<p class="description">Si el contenido del formulario o comentario contiene cualquiera de estas palabras clave, el envío se rechazará.</p>
									</div>

									
								</div>
				<?php
				break;
			case 'hide-login':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Ocultar URL de Login</h3>
										<p>Cambia el slug predeterminado wp-login.php por uno personalizado para mitigar ataques de fuerza bruta.</p>
									</div>
									<?php $this->render_module_toggle( 'hide-login', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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
									
								</div>
				<?php
				break;
			case 'ssl-fixer':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Forzar SSL & Contenido Mixto</h3>
										<p>Fuerza la redirección a HTTPS, repara URLs del buffer de salida y añade cabeceras de seguridad HTTP básicas.</p>
									</div>
									<?php $this->render_module_toggle( 'ssl-fixer', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									<div class="wpat-field-group">
										<label for="wpat_ssl_redirect_method">Método de redireccionamiento a HTTPS</label>
										<select name="wpat_settings[ssl_redirect_method]" id="wpat_ssl_redirect_method">
											<option value="php" <?php selected( $settings['ssl_redirect_method'], 'php' ); ?>>Redirección 301 por PHP (Segura, compatible con todos los servidores)</option>
											<option value="htaccess" <?php selected( $settings['ssl_redirect_method'], 'htaccess' ); ?>>Redirección 301 por .htaccess (Más rápida, solo Apache/LiteSpeed)</option>
										</select>
										<p class="description">Nota: La redirección por .htaccess es más veloz porque se ejecuta antes de cargar WordPress, pero solo funciona en servidores Apache o LiteSpeed. Si utilizas Nginx, mantén el método PHP.</p>
									</div>
									
								</div>
				<?php
				break;
			case 'disable-comments':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Deshabilitar Comentarios</h3>
										<p>Desactiva globalmente o por tipos de contenido los comentarios, trackbacks y widgets para evitar spam.</p>
									</div>
									<?php $this->render_module_toggle( 'disable-comments', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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
									
								</div>
				<?php
				break;
			case 'security-hardening':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Fortalecimiento de Seguridad (Hardening)</h3>
										<p>Aplica directivas y ajustes de seguridad avanzados recomendados por expertos para blindar tu sitio WordPress contra exploits comunes.</p>
									</div>
									<?php $this->render_module_toggle( 'security-hardening', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									
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

									

								</div>
							</div>
				<?php
				break;
			case 'woo-dni':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Campo DNI/CIF en el Checkout</h3>
										<p>Inyecta un campo obligatorio de DNI/CIF en los datos de facturación de WooCommerce. Se guarda y muestra en pedidos y correos.</p>
									</div>
									<?php $this->render_module_toggle( 'woo-dni', $settings, false ); ?>
								</div>
				<?php
				break;
			case 'woo-catalog':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Modo Catálogo</h3>
										<p>Desactiva la compra de productos, ocultando precios o los botones de añadir al carrito según tus necesidades.</p>
									</div>
									<?php $this->render_module_toggle( 'woo-catalog', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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

									
								</div>
				<?php
				break;
			case 'woo-zoom':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Controles de la Galería de Productos</h3>
										<p>Desactiva de forma independiente funciones nativas de la galería de producto de WooCommerce como el Zoom, Lightbox o Slider.</p>
									</div>
									<?php $this->render_module_toggle( 'woo-zoom', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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
									
								</div>
				<?php
				break;
			case 'woo-checkout-editor':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Editor de Campos de Checkout (WooCommerce)</h3>
										<p>Modifica, oculta o añade nuevos campos en el formulario de finalizar pago (NIF/CIF, campos personalizados, fecha) y expónlos automáticamente en la API REST (CRM/ERP).</p>
									</div>
									<?php $this->render_module_toggle( 'woo-checkout-editor', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									

									<!-- NIF / CIF -->
									<div class="wpat-field-group" style="margin-top: 15px; background: #f8fafc; border: 1px solid var(--wpat-border); padding: 15px; border-radius: 6px;">
										<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">🆔 Campo NIF / CIF / DNI (Facturación)</h4>
										<div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[checkout_nif_enabled]" value="1" <?php checked( isset( $settings['checkout_nif_enabled'] ) ? $settings['checkout_nif_enabled'] : '1', '1' ); ?>>
												Activar Campo NIF / CIF / DNI en el Checkout
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[checkout_nif_required]" value="1" <?php checked( isset( $settings['checkout_nif_required'] ) ? $settings['checkout_nif_required'] : '1', '1' ); ?>>
												Campo NIF / CIF Obligatorio
											</label>
											<div style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
												<label style="font-size: 12px; font-weight: 600;">Ubicación en Checkout:</label>
												<?php $nif_pos = isset( $settings['checkout_nif_position'] ) ? $settings['checkout_nif_position'] : 'after_names'; ?>
												<select name="wpat_settings[checkout_nif_position]" style="font-size: 12px; height: 30px;">
													<option value="after_names" <?php selected( $nif_pos, 'after_names' ); ?>>Debajo de Apellidos (Recomendado)</option>
													<option value="after_company" <?php selected( $nif_pos, 'after_company' ); ?>>Debajo de Empresa</option>
													<option value="at_end" <?php selected( $nif_pos, 'at_end' ); ?>>Al final de Facturación</option>
												</select>
											</div>
										</div>
									</div>

									<!-- Ocultar Campos Nativos -->
									<div class="wpat-field-group" style="margin-top: 15px;">
										<label style="font-weight: 600; display: block; margin-bottom: 8px;">Ocultar o Desactivar Campos Nativos de WooCommerce:</label>
										<?php $disabled_fields = isset( $settings['checkout_disabled_fields'] ) && is_array( $settings['checkout_disabled_fields'] ) ? $settings['checkout_disabled_fields'] : array(); ?>
										<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[checkout_disabled_fields][]" value="billing_company" <?php checked( in_array( 'billing_company', $disabled_fields, true ) ); ?>>
												Ocultar Empresa (Facturación)
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[checkout_disabled_fields][]" value="billing_address_2" <?php checked( in_array( 'billing_address_2', $disabled_fields, true ) ); ?>>
												Ocultar Dirección Línea 2
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[checkout_disabled_fields][]" value="billing_phone" <?php checked( in_array( 'billing_phone', $disabled_fields, true ) ); ?>>
												Ocultar Teléfono
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[checkout_disabled_fields][]" value="shipping_company" <?php checked( in_array( 'shipping_company', $disabled_fields, true ) ); ?>>
												Ocultar Empresa (Envío)
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[checkout_disabled_fields][]" value="shipping_address_2" <?php checked( in_array( 'shipping_address_2', $disabled_fields, true ) ); ?>>
												Ocultar Dirección Línea 2 (Envío)
											</label>
										</div>
									</div>

									<!-- Campos Personalizados -->
									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
											<label style="font-weight: 600;">Campos Personalizados Adicionales:</label>
											<button type="button" class="button button-secondary" id="wpat_add_checkout_field_btn">+ Añadir Nuevo Campo</button>
										</div>

										<div id="wpat_checkout_fields_container">
											<?php
											$custom_fields = isset( $settings['checkout_custom_fields'] ) && is_array( $settings['checkout_custom_fields'] ) ? $settings['checkout_custom_fields'] : array();
											if ( empty( $custom_fields ) ) :
												?>
												<p class="description" id="wpat_no_custom_fields_msg">No hay campos personalizados adicionales creados aún.</p>
											<?php endif; ?>

											<?php
											foreach ( $custom_fields as $index => $cf ) :
												$f_key         = esc_attr( isset( $cf['key'] ) ? $cf['key'] : '' );
												$f_label       = esc_attr( isset( $cf['label'] ) ? $cf['label'] : '' );
												$f_type        = esc_attr( isset( $cf['type'] ) ? $cf['type'] : 'text' );
												$f_placeholder = esc_attr( isset( $cf['placeholder'] ) ? $cf['placeholder'] : '' );
												$f_required    = isset( $cf['required'] ) && '1' === $cf['required'] ? '1' : '0';
												$f_section     = esc_attr( isset( $cf['section'] ) ? $cf['section'] : 'billing' );
												$f_position    = esc_attr( isset( $cf['position'] ) ? $cf['position'] : 'after_names' );
												$f_options     = esc_textarea( isset( $cf['options'] ) ? $cf['options'] : '' );
												?>
												<div class="wpat-custom-field-row" style="background: #ffffff; border: 1px solid var(--wpat-border); padding: 12px 15px; border-radius: 6px; margin-bottom: 10px;">
													<div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr auto; gap: 10px; align-items: center;">
														<div>
															<label style="font-size: 11px; display: block; font-weight: 600;">Nombre del Campo (Etiqueta)</label>
															<input type="text" name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][label]" value="<?php echo $f_label; ?>" class="regular-text" placeholder="Ej. Horario de Preferencia" required style="width: 100%;" />
														</div>
														<div>
															<label style="font-size: 11px; display: block; font-weight: 600;">Identificador Único (Key)</label>
															<input type="text" name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][key]" value="<?php echo $f_key; ?>" class="regular-text" placeholder="ej. horario_entrega" style="width: 100%;" />
														</div>
														<div>
															<label style="font-size: 11px; display: block; font-weight: 600;">Tipo de Campo</label>
															<select name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][type]" class="wpat-field-type-select" style="width: 100%;">
																<option value="text" <?php selected( $f_type, 'text' ); ?>>Texto Corto</option>
																<option value="select" <?php selected( $f_type, 'select' ); ?>>Desplegable (Select)</option>
																<option value="radio" <?php selected( $f_type, 'radio' ); ?>>Radio (Botones de Opción)</option>
																<option value="textarea" <?php selected( $f_type, 'textarea' ); ?>>Área de Texto</option>
																<option value="checkbox" <?php selected( $f_type, 'checkbox' ); ?>>Casilla (Checkbox)</option>
																<option value="date" <?php selected( $f_type, 'date' ); ?>>Fecha (Calendario)</option>
															</select>
														</div>
														<div>
															<label style="font-size: 11px; display: block; font-weight: 600;">Sección</label>
															<select name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][section]" style="width: 100%;">
																<option value="billing" <?php selected( $f_section, 'billing' ); ?>>Facturación</option>
																<option value="shipping" <?php selected( $f_section, 'shipping' ); ?>>Envío</option>
																<option value="order" <?php selected( $f_section, 'order' ); ?>>Notas Adicionales</option>
															</select>
														</div>
														<div>
															<label style="font-size: 11px; display: block; font-weight: 600;">Posición</label>
															<select name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][position]" style="width: 100%;">
																<option value="after_names" <?php selected( $f_position, 'after_names' ); ?>>Después de Apellidos</option>
																<option value="after_company" <?php selected( $f_position, 'after_company' ); ?>>Después de Empresa</option>
																<option value="after_address" <?php selected( $f_position, 'after_address' ); ?>>Después de Dirección</option>
																<option value="end_of_section" <?php selected( $f_position, 'end_of_section' ); ?>>Al final de Sección</option>
															</select>
														</div>
														<div style="text-align: right; padding-top: 15px;">
															<button type="button" class="button button-link-delete wpat-remove-field-btn" style="color: #ef4444;">Eliminar</button>
														</div>
													</div>

													<div style="display: flex; gap: 15px; margin-top: 10px; align-items: center;">
														<label style="font-weight: normal; font-size: 12px;">
															<input type="checkbox" name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][required]" value="1" <?php checked( $f_required, '1' ); ?> />
															Campo Obligatorio
														</label>
														<input type="text" name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][placeholder]" value="<?php echo $f_placeholder; ?>" placeholder="Placeholder o texto de ayuda..." style="font-size: 12px; flex-grow: 1;" />
													</div>

													<div class="wpat-options-field-wrap" style="margin-top: 10px; <?php echo ( 'select' === $f_type || 'radio' === $f_type ) ? '' : 'display:none;'; ?>">
														<label style="font-size: 11px; display: block; font-weight: 600;">Opciones (Una opción por línea):</label>
														<textarea name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][options]" rows="2" style="width: 100%; font-size: 12px;" placeholder="Mañana (09:00 - 14:00)&#10;Tarde (16:00 - 20:00)"><?php echo $f_options; ?></textarea>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
									</div>

									<script>
									document.addEventListener('DOMContentLoaded', function() {
										var addBtn = document.getElementById('wpat_add_checkout_field_btn');
										var container = document.getElementById('wpat_checkout_fields_container');
										var noMsg = document.getElementById('wpat_no_custom_fields_msg');
										if (!addBtn || !container) return;

										addBtn.addEventListener('click', function() {
											if (noMsg) noMsg.style.display = 'none';
											var index = container.querySelectorAll('.wpat-custom-field-row').length;
											var html = '<div class="wpat-custom-field-row" style="background: #ffffff; border: 1px solid var(--wpat-border); padding: 12px 15px; border-radius: 6px; margin-bottom: 10px;">' +
												'<div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr auto; gap: 10px; align-items: center;">' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Nombre del Campo (Etiqueta)</label><input type="text" name="wpat_settings[checkout_custom_fields][' + index + '][label]" value="" class="regular-text" placeholder="Ej. Horario de Preferencia" required style="width: 100%;" /></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Identificador Único (Key)</label><input type="text" name="wpat_settings[checkout_custom_fields][' + index + '][key]" value="" class="regular-text" placeholder="ej. horario_entrega" style="width: 100%;" /></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Tipo de Campo</label><select name="wpat_settings[checkout_custom_fields][' + index + '][type]" class="wpat-field-type-select" style="width: 100%;"><option value="text">Texto Corto</option><option value="select">Desplegable (Select)</option><option value="radio">Radio (Botones de Opción)</option><option value="textarea">Área de Texto</option><option value="checkbox">Casilla (Checkbox)</option><option value="date">Fecha (Calendario)</option></select></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Sección</label><select name="wpat_settings[checkout_custom_fields][' + index + '][section]" style="width: 100%;"><option value="billing">Facturación</option><option value="shipping">Envío</option><option value="order">Notas Adicionales</option></select></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Posición</label><select name="wpat_settings[checkout_custom_fields][' + index + '][position]" style="width: 100%;"><option value="after_names">Después de Apellidos</option><option value="after_company">Después de Empresa</option><option value="after_address">Después de Dirección</option><option value="end_of_section">Al final de Sección</option></select></div>' +
													'<div style="text-align: right; padding-top: 15px;"><button type="button" class="button button-link-delete wpat-remove-field-btn" style="color: #ef4444;">Eliminar</button></div>' +
												'</div>' +
												'<div style="display: flex; gap: 15px; margin-top: 10px; align-items: center;">' +
													'<label style="font-weight: normal; font-size: 12px;"><input type="checkbox" name="wpat_settings[checkout_custom_fields][' + index + '][required]" value="1" /> Campo Obligatorio</label>' +
													'<input type="text" name="wpat_settings[checkout_custom_fields][' + index + '][placeholder]" value="" placeholder="Placeholder o texto de ayuda..." style="font-size: 12px; flex-grow: 1;" />' +
												'</div>' +
												'<div class="wpat-options-field-wrap" style="margin-top: 10px; display:none;">' +
													'<label style="font-size: 11px; display: block; font-weight: 600;">Opciones (Una opción por línea):</label>' +
													'<textarea name="wpat_settings[checkout_custom_fields][' + index + '][options]" rows="2" style="width: 100%; font-size: 12px;" placeholder="Mañana (09:00 - 14:00)\nTarde (16:00 - 20:00)"></textarea>' +
												'</div>' +
											'</div>';
											container.insertAdjacentHTML('beforeend', html);
										});

										container.addEventListener('change', function(e) {
											if (e.target && e.target.classList.contains('wpat-field-type-select')) {
												var row = e.target.closest('.wpat-custom-field-row');
												var optWrap = row.querySelector('.wpat-options-field-wrap');
												if (optWrap) {
													optWrap.style.display = (e.target.value === 'select' || e.target.value === 'radio') ? 'block' : 'none';
												}
											}
										});

										container.addEventListener('click', function(e) {
											if (e.target && e.target.classList.contains('wpat-remove-field-btn')) {
												var row = e.target.closest('.wpat-custom-field-row');
												if (row) row.remove();
											}
										});
									});
									</script>

									
								</div>
				<?php
				break;
			case 'woo-extra-options':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Campos extras en productos (Woocommerce)</h3>
										<p>Añade campos adicionales a los productos (grabados, papel regalo, muestrarios de color) con recargos de precio automáticos en el carrito y pedido.</p>
									</div>
									<?php $this->render_module_toggle( 'woo-extra-options', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									
									</div>

									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 8px;">
											<div style="display: flex; gap: 8px; align-items: center; flex-grow: 1;">
												<input type="text" id="wpat_rule_search_input" placeholder="🔍 Buscar grupo por título..." class="regular-text" style="font-size: 12px; height: 32px; max-width: 280px;" autocomplete="off" />
												<button type="button" class="button button-small" id="wpat_expand_all_rules_btn" title="Expandir todos los grupos">🔽 Desplegar Todos</button>
												<button type="button" class="button button-small" id="wpat_collapse_all_rules_btn" title="Plegar todos los grupos">🔼 Plegar Todos</button>
											</div>
											<button type="button" class="button button-primary" id="wpat_add_extra_rule_btn">+ Añadir Nuevo Grupo de Opciones</button>
										</div>

										<div id="wpat_extra_rules_container">
											<?php
											$extra_rules  = isset( $settings['extra_options_rules'] ) && is_array( $settings['extra_options_rules'] ) ? $settings['extra_options_rules'] : array();
											$product_cats = taxonomy_exists( 'product_cat' ) ? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) : array();

											$cat_options_js = '';
											if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) {
												foreach ( $product_cats as $cat ) {
													$cat_options_js .= '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_js( $cat->name ) . '</option>';
												}
											} else {
												$cat_options_js .= '<option value="" disabled>No hay categorías registradas</option>';
											}

											if ( empty( $extra_rules ) ) :
												?>
												<p class="description" id="wpat_no_extra_rules_msg">No hay grupos de opciones de producto creados aún.</p>
											<?php endif; ?>

											<?php
											foreach ( $extra_rules as $r_idx => $rule ) :
												$r_title      = esc_attr( isset( $rule['title'] ) ? $rule['title'] : '' );
												$r_enabled    = isset( $rule['enabled'] ) && '1' === $rule['enabled'] ? '1' : '0';
												$r_scope      = esc_attr( isset( $rule['scope'] ) ? $rule['scope'] : 'global' );
												$r_categories = isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? array_map( 'intval', $rule['categories'] ) : array();
												$r_products   = isset( $rule['products'] ) && is_array( $rule['products'] ) ? implode( ', ', $rule['products'] ) : ( isset( $rule['products'] ) ? esc_attr( $rule['products'] ) : '' );
												$r_fields     = isset( $rule['fields'] ) && is_array( $rule['fields'] ) ? $rule['fields'] : array();

												if ( 'category' === $r_scope ) {
													$scope_badge = '🏷️ Categorías (' . count( $r_categories ) . ')';
												} elseif ( 'product' === $r_scope ) {
													$prod_count  = count( array_filter( array_map( 'trim', explode( ',', $r_products ) ) ) );
													$scope_badge = '📦 Productos (' . $prod_count . ')';
												} else {
													$scope_badge = '🌐 Todos los Productos (Global)';
												}
												$field_count_badge = count( $r_fields ) . ' ' . ( count( $r_fields ) === 1 ? 'campo' : 'campos' );
												?>
												<div class="wpat-extra-rule-card" data-title="<?php echo esc_attr( strtolower( $r_title ) ); ?>" style="background: #ffffff; border: 1px solid var(--wpat-border); border-radius: 8px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
													<!-- Header Acordeón -->
													<div class="wpat-rule-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; cursor: pointer; user-select: none;">
														<div style="display: flex; gap: 12px; align-items: center; flex-grow: 1; min-width: 0;">
															<span class="wpat-rule-toggle-icon" style="font-size: 11px; transition: transform 0.2s; color: #64748b;">▶</span>
															<span class="wpat-rule-status-dot" style="width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; background: <?php echo '1' === $r_enabled ? '#10b981' : '#ef4444'; ?>;" title="<?php echo '1' === $r_enabled ? 'Grupo Activo' : 'Grupo Inactivo'; ?>"></span>
															<span class="wpat-rule-header-title" style="font-weight: 700; font-size: 13.5px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;"><?php echo ! empty( $r_title ) ? $r_title : 'Grupo sin título'; ?></span>
															<span class="wpat-rule-scope-badge" style="font-size: 11px; background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 12px; font-weight: 600;"><?php echo esc_html( $scope_badge ); ?></span>
															<span class="wpat-rule-fields-count-badge" style="font-size: 11px; background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 12px; font-weight: 600;"><?php echo esc_html( $field_count_badge ); ?></span>
														</div>
														<div style="display: flex; gap: 8px; align-items: center; flex-shrink: 0;" onclick="event.stopPropagation();">
															<button type="button" class="button button-small wpat-toggle-rule-btn" style="font-size: 11px;">Desplegar 🔽</button>
															<button type="button" class="button button-small button-link-delete wpat-remove-rule-btn" style="color: #ef4444; font-size: 11px;">Eliminar</button>
														</div>
													</div>

													<!-- Body Acordeón (oculto por defecto) -->
													<div class="wpat-rule-body-content" style="display: none; padding: 15px;">
														<div style="display: flex; gap: 15px; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 12px;">
															<input type="text" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][title]" value="<?php echo $r_title; ?>" placeholder="Título del Grupo (ej. Opciones de Personalización)" class="regular-text wpat-rule-title-input" style="font-weight: 700; font-size: 14px; flex-grow: 1;" required />
															<label style="font-weight: normal; font-size: 12px; margin: 0; white-space: nowrap;">
																<input type="checkbox" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][enabled]" value="1" class="wpat-rule-enabled-checkbox" <?php checked( $r_enabled, '1' ); ?> />
																Grupo Activo
															</label>
														</div>

													<div style="display: flex; gap: 20px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
														<div style="display: flex; gap: 10px; align-items: center;">
															<label style="font-size: 12px; font-weight: 600;">Aplicar este grupo a:</label>
															<select name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][scope]" class="wpat-rule-scope-select" style="height: 30px;">
																<option value="global" <?php selected( $r_scope, 'global' ); ?>>Todos los Productos (Global)</option>
																<option value="category" <?php selected( $r_scope, 'category' ); ?>>Categorías de Productos</option>
																<option value="product" <?php selected( $r_scope, 'product' ); ?>>Productos Específicos</option>
															</select>
														</div>

														<div class="wpat-scope-category-wrap" style="<?php echo ( 'category' === $r_scope ) ? '' : 'display:none;'; ?>">
															<label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;">Seleccionar Categorías:</label>
															<select name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][categories][]" multiple style="height: 65px; font-size: 11px; min-width: 200px;">
																<?php
																if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) {
																	foreach ( $product_cats as $cat ) {
																		$selected = in_array( (int) $cat->term_id, $r_categories, true ) ? 'selected' : '';
																		echo '<option value="' . esc_attr( $cat->term_id ) . '" ' . $selected . '>' . esc_html( $cat->name ) . '</option>';
																	}
																} else {
																	echo '<option value="" disabled>No hay categorías registradas</option>';
																}
																?>
															</select>
														</div>

														<div class="wpat-scope-product-wrap" style="position: relative; <?php echo ( 'product' === $r_scope ) ? '' : 'display:none;'; ?>">
															<label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;">Buscar Productos (por Nombre, SKU o ID):</label>
															<div style="position: relative; display: inline-block;">
																<input type="text" class="wpat-product-search-input regular-text" placeholder="Buscar por Nombre, SKU o ID..." style="font-size: 11px; width: 250px;" autocomplete="off" />
																<div class="wpat-product-search-results" style="display:none; position: absolute; top: 100%; left: 0; right: 0; background: #ffffff; border: 1px solid #cbd5e1; max-height: 200px; overflow-y: auto; z-index: 9999; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; font-size: 11px;"></div>
															</div>
															<input type="hidden" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][products]" class="wpat-products-hidden-ids" value="<?php echo esc_attr( $r_products ); ?>" />
															<div class="wpat-selected-products-tags" style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; max-width: 380px;">
																<?php
																$prod_ids_arr = array_filter( array_map( 'trim', explode( ',', $r_products ) ) );
																foreach ( $prod_ids_arr as $p_id ) {
																	$p_id_int = intval( $p_id );
																	if ( $p_id_int > 0 ) {
																		$p_title = get_the_title( $p_id_int );
																		$p_text  = ! empty( $p_title ) ? '#' . $p_id_int . ' - ' . $p_title : '#' . $p_id_int;
																		echo '<span class="wpat-product-tag" data-id="' . $p_id_int . '" style="background: #e2e8f0; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 6px; font-size: 11px; display: inline-flex; align-items: center; gap: 6px;">' . esc_html( $p_text ) . ' <a href="#" class="wpat-remove-product-tag" style="color: #ef4444; text-decoration: none; font-weight: bold;">&times;</a></span>';
																	}
																}
																?>
															</div>
														</div>
													</div>

													<!-- Lista de Campos dentro de la Regla -->
													<div class="wpat-rule-fields-wrapper" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px;">
														<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
															<span style="font-size: 12px; font-weight: 700; color: #475569;">Campos e Opciones de este Grupo:</span>
															<button type="button" class="button button-small wpat-add-field-to-rule-btn" data-rule="<?php echo $r_idx; ?>">+ Añadir Campo</button>
														</div>

														<div class="wpat-fields-list-container">
															<?php foreach ( $r_fields as $f_idx => $f ) :
																$f_label       = esc_attr( isset( $f['label'] ) ? $f['label'] : '' );
																$f_type        = esc_attr( isset( $f['type'] ) ? $f['type'] : 'text' );
																$f_price       = esc_attr( isset( $f['price'] ) ? $f['price'] : '0' );
																$f_required    = isset( $f['required'] ) && '1' === $f['required'] ? '1' : '0';
																$f_placeholder = esc_attr( isset( $f['placeholder'] ) ? $f['placeholder'] : '' );
																$f_default_val = esc_attr( isset( $f['default_val'] ) ? $f['default_val'] : '' );
																$f_max_length  = esc_attr( isset( $f['max_length'] ) ? $f['max_length'] : '' );
																$f_options     = esc_textarea( isset( $f['options'] ) ? $f['options'] : '' );
																$f_swatches    = esc_textarea( isset( $f['swatches'] ) ? $f['swatches'] : '' );
																?>
																<div class="wpat-field-item-row" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 10px 12px; border-radius: 6px; margin-bottom: 8px;">
																	<div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr auto; gap: 10px; align-items: center;">
																		<div>
																			<label style="font-size: 10px; font-weight: 600; display: block;">Etiqueta / Nombre</label>
																			<input type="text" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][label]" value="<?php echo $f_label; ?>" placeholder="Ej. Texto de Grabado" class="regular-text" style="width: 100%;" required />
																		</div>
																		<div>
																			<label style="font-size: 10px; font-weight: 600; display: block;">Tipo de Opción</label>
																			<select name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][type]" class="wpat-extra-type-select" style="width: 100%;">
																				<option value="text" <?php selected( $f_type, 'text' ); ?>>Texto Corto</option>
																				<option value="textarea" <?php selected( $f_type, 'textarea' ); ?>>Área de Texto</option>
																				<option value="select" <?php selected( $f_type, 'select' ); ?>>Desplegable (Select)</option>
																				<option value="radio" <?php selected( $f_type, 'radio' ); ?>>Radio (Botones de Opción)</option>
																				<option value="swatch" <?php selected( $f_type, 'swatch' ); ?>>Muestrario de Color (Swatch)</option>
																				<option value="checkbox" <?php selected( $f_type, 'checkbox' ); ?>>Casilla (Checkbox)</option>
																				<option value="file" <?php selected( $f_type, 'file' ); ?>>Subida de Archivo (File)</option>
																			</select>
																		</div>
																		<div class="wpat-field-price-wrap" style="<?php echo in_array( $f_type, array( 'select', 'radio', 'swatch' ), true ) ? 'opacity: 0.4; pointer-events: none;' : ''; ?>">
																			<label style="font-size: 10px; font-weight: 600; display: block;">Precio Extra (€)</label>
																			<input type="number" step="0.01" min="0" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][price]" value="<?php echo $f_price; ?>" placeholder="0.00" style="width: 100%;" />
																		</div>
																		<div>
																			<label style="font-size: 10px; font-weight: 600; display: block;">Máx Caracteres</label>
																			<input type="number" min="0" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][max_length]" value="<?php echo $f_max_length; ?>" placeholder="Sin límite" style="width: 100%;" />
																		</div>
																		<div style="text-align: right; padding-top: 12px;">
																			<button type="button" class="button button-link-delete wpat-remove-field-item-btn" style="color: #ef4444;">Eliminar</button>
																		</div>
																	</div>

																	<div style="display: flex; gap: 15px; margin-top: 8px; align-items: center;">
																		<label style="font-weight: normal; font-size: 11px;">
																			<input type="checkbox" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][required]" value="1" <?php checked( $f_required, '1' ); ?> />
																			Obligatorio
																		</label>
																		<input type="text" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][placeholder]" value="<?php echo $f_placeholder; ?>" placeholder="Texto de ayuda o placeholder..." style="font-size: 11px; flex-grow: 1;" />
																		<input type="text" name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][default_val]" value="<?php echo $f_default_val; ?>" placeholder="Opción por defecto (ej. Nombre opción / 1)" style="font-size: 11px; width: 220px;" title="Para Checkbox poner 1. Para Select/Radio/Swatch poner el Nombre exacto de la opción por defecto." />
																	</div>

																	<div class="wpat-extra-swatches-wrap" style="margin-top: 8px; <?php echo ( 'swatch' === $f_type ) ? '' : 'display:none;'; ?>">
																		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
																			<label style="font-size: 10px; font-weight: 600; margin: 0;">Configuración de Colores (Formato: Nombre | #HEX u opción por nombre como 'Azul' | Precio):</label>
																			<div style="display: flex; align-items: center; gap: 5px;">
																				<span style="font-size: 10px; color: #64748b;">🎨 Añadir color visualmente:</span>
																				<input type="color" class="wpat-swatch-picker-tool" value="#2563eb" title="Haz clic para seleccionar un color visualmente e insertarlo en la lista" style="cursor: pointer; width: 22px; height: 22px; padding: 0; border: 1px solid #cbd5e1; border-radius: 4px; vertical-align: middle;" />
																			</div>
																		</div>
																		<textarea name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][swatches]" class="wpat-swatches-textarea" rows="2" style="width: 100%; font-size: 11px;" placeholder="Azul Real | #2563eb | 0&#10;Rojo | 5.00&#10;Oro Metalizado | #ffd700 | 5.00"><?php echo $f_swatches; ?></textarea>
																	</div>

																	<div class="wpat-extra-options-wrap" style="margin-top: 8px; <?php echo ( 'select' === $f_type || 'radio' === $f_type ) ? '' : 'display:none;'; ?>">
																		<label style="font-size: 10px; font-weight: 600; display: block;">Opciones de Desplegable o Radio (Formato por línea: Nombre | Precio Opcional):</label>
																		<textarea name="wpat_settings[extra_options_rules][<?php echo $r_idx; ?>][fields][<?php echo $f_idx; ?>][options]" rows="2" style="width: 100%; font-size: 11px;" placeholder="Cena Sí | 15.00&#10;Cena No | 0"><?php echo $f_options; ?></textarea>
																	</div>
																</div>
															<?php endforeach; ?>
														</div>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
										</div>
									</div>

									<script>
									document.addEventListener('DOMContentLoaded', function() {
										var addRuleBtn = document.getElementById('wpat_add_extra_rule_btn');
										var rulesContainer = document.getElementById('wpat_extra_rules_container');
										var noRulesMsg = document.getElementById('wpat_no_extra_rules_msg');
										var searchInput = document.getElementById('wpat_rule_search_input');
										var expandAllBtn = document.getElementById('wpat_expand_all_rules_btn');
										var collapseAllBtn = document.getElementById('wpat_collapse_all_rules_btn');
										var catOptionsHtml = '<?php echo $cat_options_js; ?>';

										if (!rulesContainer) return;

										// 1. Añadir nuevo grupo de reglas (Se abre desplegado para editar)
										if (addRuleBtn) {
											addRuleBtn.addEventListener('click', function() {
												if (noRulesMsg) noRulesMsg.style.display = 'none';
												var rIndex = rulesContainer.querySelectorAll('.wpat-extra-rule-card').length;
												var html = '<div class="wpat-extra-rule-card" data-title="" style="background: #ffffff; border: 1px solid var(--wpat-border); border-radius: 8px; margin-bottom: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">' +
													'<div class="wpat-rule-header-bar" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; cursor: pointer; user-select: none;">' +
														'<div style="display: flex; gap: 12px; align-items: center; flex-grow: 1; min-width: 0;">' +
															'<span class="wpat-rule-toggle-icon" style="font-size: 11px; transform: rotate(90deg); transition: transform 0.2s; color: #64748b;">▶</span>' +
															'<span class="wpat-rule-status-dot" style="width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; background: #10b981;" title="Grupo Activo"></span>' +
															'<span class="wpat-rule-header-title" style="font-weight: 700; font-size: 13.5px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;">Nuevo Grupo de Opciones</span>' +
															'<span class="wpat-rule-scope-badge" style="font-size: 11px; background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 12px; font-weight: 600;">🌐 Todos los Productos (Global)</span>' +
															'<span class="wpat-rule-fields-count-badge" style="font-size: 11px; background: #eff6ff; color: #2563eb; padding: 2px 8px; border-radius: 12px; font-weight: 600;">0 campos</span>' +
														'</div>' +
														'<div style="display: flex; gap: 8px; align-items: center; flex-shrink: 0;" onclick="event.stopPropagation();">' +
															'<button type="button" class="button button-small wpat-toggle-rule-btn" style="font-size: 11px;">Plegar 🔼</button>' +
															'<button type="button" class="button button-small button-link-delete wpat-remove-rule-btn" style="color: #ef4444; font-size: 11px;">Eliminar</button>' +
														'</div>' +
													'</div>' +
													'<div class="wpat-rule-body-content" style="display: block; padding: 15px;">' +
														'<div style="display: flex; gap: 15px; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 12px;">' +
															'<input type="text" name="wpat_settings[extra_options_rules][' + rIndex + '][title]" value="" placeholder="Título del Grupo (ej. Opciones de Personalización)" class="regular-text wpat-rule-title-input" style="font-weight: 700; font-size: 14px; flex-grow: 1;" required />' +
															'<label style="font-weight: normal; font-size: 12px; margin: 0; white-space: nowrap;"><input type="checkbox" name="wpat_settings[extra_options_rules][' + rIndex + '][enabled]" value="1" class="wpat-rule-enabled-checkbox" checked /> Grupo Activo</label>' +
														'</div>' +
														'<div style="display: flex; gap: 20px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">' +
															'<div style="display: flex; gap: 10px; align-items: center;">' +
																'<label style="font-size: 12px; font-weight: 600;">Aplicar este grupo a:</label>' +
																'<select name="wpat_settings[extra_options_rules][' + rIndex + '][scope]" class="wpat-rule-scope-select" style="height: 30px;">' +
																	'<option value="global">Todos los Productos (Global)</option>' +
																	'<option value="category">Categorías de Productos</option>' +
																	'<option value="product">Productos Específicos</option>' +
																'</select>' +
															'</div>' +
															'<div class="wpat-scope-category-wrap" style="display:none;">' +
																'<label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;">Seleccionar Categorías:</label>' +
																'<select name="wpat_settings[extra_options_rules][' + rIndex + '][categories][]" multiple style="height: 65px; font-size: 11px; min-width: 200px;">' +
																	catOptionsHtml +
																'</select>' +
															'</div>' +
															'<div class="wpat-scope-product-wrap" style="position: relative; display:none;">' +
																'<label style="font-size: 11px; font-weight: 600; display: block; margin-bottom: 2px;">Buscar Productos (por Nombre, SKU o ID):</label>' +
																'<div style="position: relative; display: inline-block;">' +
																	'<input type="text" class="wpat-product-search-input regular-text" placeholder="Buscar por Nombre, SKU o ID..." style="font-size: 11px; width: 250px;" autocomplete="off" />' +
																	'<div class="wpat-product-search-results" style="display:none; position: absolute; top: 100%; left: 0; right: 0; background: #ffffff; border: 1px solid #cbd5e1; max-height: 200px; overflow-y: auto; z-index: 9999; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 4px; font-size: 11px;"></div>' +
																'</div>' +
																'<input type="hidden" name="wpat_settings[extra_options_rules][' + rIndex + '][products]" class="wpat-products-hidden-ids" value="" />' +
																'<div class="wpat-selected-products-tags" style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; max-width: 380px;"></div>' +
															'</div>' +
														'</div>' +
														'<div class="wpat-rule-fields-wrapper" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px;">' +
															'<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;"><span style="font-size: 12px; font-weight: 700; color: #475569;">Campos e Opciones de este Grupo:</span><button type="button" class="button button-small wpat-add-field-to-rule-btn" data-rule="' + rIndex + '">+ Añadir Campo</button></div>' +
															'<div class="wpat-fields-list-container"></div>' +
														'</div>' +
													'</div>' +
												'</div>';
												rulesContainer.insertAdjacentHTML('beforeend', html);
											});
										}

										// 2. Control de Acordeón al hacer clic en la cabecera del grupo
										rulesContainer.addEventListener('click', function(e) {
											var header = e.target.closest('.wpat-rule-header-bar');
											if (header) {
												var card = header.closest('.wpat-extra-rule-card');
												var body = card.querySelector('.wpat-rule-body-content');
												var btn = header.querySelector('.wpat-toggle-rule-btn');
												var icon = header.querySelector('.wpat-rule-toggle-icon');

												if (body.style.display === 'none' || !body.style.display) {
													body.style.display = 'block';
													if (btn) btn.textContent = 'Plegar 🔼';
													if (icon) icon.style.transform = 'rotate(90deg)';
												} else {
													body.style.display = 'none';
													if (btn) btn.textContent = 'Desplegar 🔽';
													if (icon) icon.style.transform = 'rotate(0deg)';
												}
											}

											if (e.target && e.target.classList.contains('wpat-remove-rule-btn')) {
												var card = e.target.closest('.wpat-extra-rule-card');
												if (card && confirm('¿Estás seguro de que deseas eliminar este grupo de opciones?')) {
													card.remove();
												}
											} else if (e.target && e.target.classList.contains('wpat-add-field-to-rule-btn')) {
												var card = e.target.closest('.wpat-extra-rule-card');
												var listContainer = card.querySelector('.wpat-fields-list-container');
												var rIndex = e.target.getAttribute('data-rule');
												var fIndex = listContainer.querySelectorAll('.wpat-field-item-row').length;

												var fHtml = '<div class="wpat-field-item-row" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 10px 12px; border-radius: 6px; margin-bottom: 8px;">' +
													'<div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr auto; gap: 10px; align-items: center;">' +
														'<div><label style="font-size: 10px; font-weight: 600; display: block;">Etiqueta / Nombre</label><input type="text" name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][label]" value="" placeholder="Ej. Texto de Grabado" class="regular-text" style="width: 100%;" required /></div>' +
														'<div><label style="font-size: 10px; font-weight: 600; display: block;">Tipo de Opción</label><select name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][type]" class="wpat-extra-type-select" style="width: 100%;"><option value="text">Texto Corto</option><option value="textarea">Área de Texto</option><option value="select">Desplegable (Select)</option><option value="radio">Radio (Botones de Opción)</option><option value="swatch">Muestrario de Color (Swatch)</option><option value="checkbox">Casilla (Checkbox)</option><option value="file">Subida de Archivo (File)</option></select></div>' +
														'<div class="wpat-field-price-wrap"><label style="font-size: 10px; font-weight: 600; display: block;">Precio Extra (€)</label><input type="number" step="0.01" min="0" name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][price]" value="0.00" placeholder="0.00" style="width: 100%;" /></div>' +
														'<div><label style="font-size: 10px; font-weight: 600; display: block;">Máx Caracteres</label><input type="number" min="0" name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][max_length]" value="" placeholder="Sin límite" style="width: 100%;" /></div>' +
														'<div style="text-align: right; padding-top: 12px;"><button type="button" class="button button-link-delete wpat-remove-field-item-btn" style="color: #ef4444;">Eliminar</button></div>' +
													'</div>' +
													'<div style="display: flex; gap: 15px; margin-top: 8px; align-items: center;">' +
														'<label style="font-weight: normal; font-size: 11px;"><input type="checkbox" name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][required]" value="1" /> Obligatorio</label>' +
														'<input type="text" name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][placeholder]" value="" placeholder="Texto de ayuda o placeholder..." style="font-size: 11px; flex-grow: 1;" />' +
														'<input type="text" name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][default_val]" value="" placeholder="Opción por defecto (ej. Nombre opción / 1)" style="font-size: 11px; width: 220px;" title="Para Checkbox poner 1. Para Select/Radio/Swatch poner el Nombre exacto de la opción por defecto." />' +
													'</div>' +
													'<div class="wpat-extra-swatches-wrap" style="margin-top: 8px; display:none;">' +
														'<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;"><label style="font-size: 10px; font-weight: 600; margin: 0;">Configuración de Colores (Formato: Nombre | #HEX u opción por nombre como \'Azul\' | Precio):</label><div style="display: flex; align-items: center; gap: 5px;"><span style="font-size: 10px; color: #64748b;">🎨 Añadir color visualmente:</span><input type="color" class="wpat-swatch-picker-tool" value="#2563eb" title="Haz clic para seleccionar un color visualmente e insertarlo en la lista" style="cursor: pointer; width: 22px; height: 22px; padding: 0; border: 1px solid #cbd5e1; border-radius: 4px; vertical-align: middle;" /></div></div>' +
														'<textarea name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][swatches]" class="wpat-swatches-textarea" rows="2" style="width: 100%; font-size: 11px;" placeholder="Azul Real | #2563eb | 0\nRojo | 5.00\nOro Metalizado | #ffd700 | 5.00"></textarea>' +
													'</div>' +
													'<div class="wpat-extra-options-wrap" style="margin-top: 8px; display:none;">' +
														'<label style="font-size: 10px; font-weight: 600; display: block;">Opciones de Desplegable o Radio (Formato por línea: Nombre | Precio Opcional):</label>' +
														'<textarea name="wpat_settings[extra_options_rules][' + rIndex + '][fields][' + fIndex + '][options]" rows="2" style="width: 100%; font-size: 11px;" placeholder="Cena Sí | 15.00\nCena No | 0"></textarea>' +
													'</div>' +
												'</div>';
												listContainer.insertAdjacentHTML('beforeend', fHtml);

												// Actualizar badge de conteo de campos en el header
												var badge = card.querySelector('.wpat-rule-fields-count-badge');
												if (badge) {
													var count = listContainer.querySelectorAll('.wpat-field-item-row').length;
													badge.textContent = count + (count === 1 ? ' campo' : ' campos');
												}
											} else if (e.target && e.target.classList.contains('wpat-remove-field-item-btn')) {
												var card = e.target.closest('.wpat-extra-rule-card');
												var fRow = e.target.closest('.wpat-field-item-row');
												if (fRow) fRow.remove();
												if (card) {
													var badge = card.querySelector('.wpat-rule-fields-count-badge');
													var listContainer = card.querySelector('.wpat-fields-list-container');
													if (badge && listContainer) {
														var count = listContainer.querySelectorAll('.wpat-field-item-row').length;
														badge.textContent = count + (count === 1 ? ' campo' : ' campos');
													}
												}
											}
										});

										// 3. Buscador en tiempo real de reglas por título
										if (searchInput) {
											searchInput.addEventListener('input', function() {
												var term = this.value.toLowerCase().trim();
												var cards = rulesContainer.querySelectorAll('.wpat-extra-rule-card');
												cards.forEach(function(card) {
													var titleInput = card.querySelector('.wpat-rule-title-input');
													var title = titleInput ? titleInput.value.toLowerCase() : '';
													if (!term || title.indexOf(term) !== -1) {
														card.style.display = 'block';
													} else {
														card.style.display = 'none';
													}
												});
											});
										}

										// 4. Desplegar Todos / Plegar Todos
										if (expandAllBtn) {
											expandAllBtn.addEventListener('click', function() {
												rulesContainer.querySelectorAll('.wpat-extra-rule-card').forEach(function(card) {
													var body = card.querySelector('.wpat-rule-body-content');
													var btn = card.querySelector('.wpat-toggle-rule-btn');
													var icon = card.querySelector('.wpat-rule-toggle-icon');
													if (body) body.style.display = 'block';
													if (btn) btn.textContent = 'Plegar 🔼';
													if (icon) icon.style.transform = 'rotate(90deg)';
												});
											});
										}

										if (collapseAllBtn) {
											collapseAllBtn.addEventListener('click', function() {
												rulesContainer.querySelectorAll('.wpat-extra-rule-card').forEach(function(card) {
													var body = card.querySelector('.wpat-rule-body-content');
													var btn = card.querySelector('.wpat-toggle-rule-btn');
													var icon = card.querySelector('.wpat-rule-toggle-icon');
													if (body) body.style.display = 'none';
													if (btn) btn.textContent = 'Desplegar 🔽';
													if (icon) icon.style.transform = 'rotate(0deg)';
												});
											});
										}

										// 5. Actualización en tiempo real del título y estado en la barra de la cabecera
										rulesContainer.addEventListener('input', function(e) {
											if (e.target && e.target.classList.contains('wpat-rule-title-input')) {
												var card = e.target.closest('.wpat-extra-rule-card');
												var headerTitle = card ? card.querySelector('.wpat-rule-header-title') : null;
												if (headerTitle) {
													headerTitle.textContent = e.target.value.trim() || 'Grupo sin título';
												}
												if (card) card.setAttribute('data-title', e.target.value.toLowerCase().trim());
											} else if (e.target && e.target.classList.contains('wpat-swatch-picker-tool')) {
												var fRow = e.target.closest('.wpat-field-item-row');
												var txtArea = fRow ? fRow.querySelector('.wpat-swatches-textarea') : null;
												if (txtArea) {
													var hex = e.target.value;
													var currentText = txtArea.value.trim();
													var newLine = 'Color ' + hex.toUpperCase() + ' | ' + hex + ' | 0';
													txtArea.value = currentText ? currentText + '\n' + newLine : newLine;
												}
											}
										});

										rulesContainer.addEventListener('change', function(e) {
											if (e.target && e.target.classList.contains('wpat-rule-enabled-checkbox')) {
												var card = e.target.closest('.wpat-extra-rule-card');
												var dot = card ? card.querySelector('.wpat-rule-status-dot') : null;
												if (dot) {
													dot.style.background = e.target.checked ? '#10b981' : '#ef4444';
													dot.title = e.target.checked ? 'Grupo Activo' : 'Grupo Inactivo';
												}
											} else if (e.target && e.target.classList.contains('wpat-extra-type-select')) {
												var fRow = e.target.closest('.wpat-field-item-row');
												var swatchWrap = fRow.querySelector('.wpat-extra-swatches-wrap');
												var optWrap = fRow.querySelector('.wpat-extra-options-wrap');
												var priceWrap = fRow.querySelector('.wpat-field-price-wrap');

												var isMultiOpt = (e.target.value === 'select' || e.target.value === 'radio' || e.target.value === 'swatch');

												if (swatchWrap) swatchWrap.style.display = (e.target.value === 'swatch') ? 'block' : 'none';
												if (optWrap) optWrap.style.display = (e.target.value === 'select' || e.target.value === 'radio') ? 'block' : 'none';
												if (priceWrap) {
													priceWrap.style.opacity = isMultiOpt ? '0.4' : '1';
													priceWrap.style.pointerEvents = isMultiOpt ? 'none' : 'auto';
												}
											} else if (e.target && e.target.classList.contains('wpat-rule-scope-select')) {
												var card = e.target.closest('.wpat-extra-rule-card');
												var catWrap = card.querySelector('.wpat-scope-category-wrap');
												var prodWrap = card.querySelector('.wpat-scope-product-wrap');
												var scopeBadge = card.querySelector('.wpat-rule-scope-badge');

												if (catWrap) catWrap.style.display = (e.target.value === 'category') ? 'block' : 'none';
												if (prodWrap) prodWrap.style.display = (e.target.value === 'product') ? 'block' : 'none';

												if (scopeBadge) {
													if (e.target.value === 'category') scopeBadge.textContent = '🏷️ Categorías';
													else if (e.target.value === 'product') scopeBadge.textContent = '📦 Productos';
													else scopeBadge.textContent = '🌐 Todos los Productos (Global)';
												}
											}
										});
									});
									</script>

									
								</div>
				<?php
				break;
			case 'woo-variation-swatches':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Swatches de Variación de Producto (WooCommerce)</h3>
										<p>Reemplaza los aburridos desplegables nativos de variaciones (Talla, Color, Textura) por atractivos botones de color circulares/cuadrados o botones estilo etiqueta (Pills).</p>
									</div>
									<?php $this->render_module_toggle( 'woo-variation-swatches', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label style="font-weight: 600; display: block; margin-bottom: 5px;">Forma de los Botones Swatch:</label>
										<?php $sw_shape = isset( $settings['variation_swatches_shape'] ) ? $settings['variation_swatches_shape'] : 'round'; ?>
										<select name="wpat_settings[variation_swatches_shape]" style="height: 32px; min-width: 200px;">
											<option value="round" <?php selected( $sw_shape, 'round' ); ?>>Circular (Redondeado)</option>
											<option value="square" <?php selected( $sw_shape, 'square' ); ?>>Cuadrado (Esquinas Suaves)</option>
										</select>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label style="font-weight: 600; display: block; margin-bottom: 5px;">Mapa Personalizado de Colores (Nombre del Color | Código #HEX):</label>
										<p class="description" style="margin-bottom: 8px;">El sistema detecta automáticamente los colores estándar en español e inglés (Rojo, Azul, Negro, Blanco, Verde, etc.). Puedes añadir mapeos adicionales a continuación:</p>
										<?php $sw_colors = isset( $settings['variation_swatches_colors'] ) ? esc_textarea( $settings['variation_swatches_colors'] ) : ''; ?>
										<textarea name="wpat_settings[variation_swatches_colors]" rows="4" style="width: 100%; font-family: monospace; font-size: 12px;" placeholder="Rojo Pasión | #ef4444&#10;Azul Marino | #1e3a8a&#10;Verde Oliva | #556b2f"><?php echo $sw_colors; ?></textarea>
									</div>

									
								</div>
				<?php
				break;
			case 'woo-pdf-invoices':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Facturas PDF y Albaranes Automáticos (WooCommerce)</h3>
										<p>Genera facturas en PDF y albaranes de entrega ultra-ligeros (motor FPDF) enviados como adjuntos en emails y descargables desde WP Admin y Mi Cuenta.</p>
									</div>
									<?php $this->render_module_toggle( 'woo-pdf-invoices', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									

									<!-- Datos de Empresa -->
									<div class="wpat-field-group" style="margin-top: 15px; background: #f8fafc; border: 1px solid var(--wpat-border); padding: 15px; border-radius: 6px;">
										<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">🏢 Datos Fiscales de la Empresa (Cabecera del PDF)</h4>
										<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 10px;">
											<div>
												<label for="wpat_pdf_company_name" style="display:block; margin-bottom:5px; font-weight:600; font-size:12px;">Nombre Comercial / Razón Social</label>
												<input type="text" name="wpat_settings[pdf_company_name]" id="wpat_pdf_company_name" value="<?php echo esc_attr( isset( $settings['pdf_company_name'] ) ? $settings['pdf_company_name'] : get_bloginfo( 'name' ) ); ?>" class="regular-text" style="width: 100%;" />
											</div>
											<div>
												<label for="wpat_pdf_company_nif" style="display:block; margin-bottom:5px; font-weight:600; font-size:12px;">NIF / CIF de la Empresa</label>
												<input type="text" name="wpat_settings[pdf_company_nif]" id="wpat_pdf_company_nif" value="<?php echo esc_attr( isset( $settings['pdf_company_nif'] ) ? $settings['pdf_company_nif'] : '' ); ?>" class="regular-text" placeholder="Ej. B12345678" style="width: 100%;" />
											</div>
										</div>

										<div style="margin-bottom: 10px;">
											<label for="wpat_pdf_company_address" style="display:block; margin-bottom:5px; font-weight:600; font-size:12px;">Dirección Completa</label>
											<textarea name="wpat_settings[pdf_company_address]" id="wpat_pdf_company_address" rows="2" style="width: 100%; font-size:12px;" placeholder="Calle Ejemplo, 123, 28001 Madrid"><?php echo esc_textarea( isset( $settings['pdf_company_address'] ) ? $settings['pdf_company_address'] : '' ); ?></textarea>
										</div>

										<div>
											<label for="wpat_pdf_company_footer" style="display:block; margin-bottom:5px; font-weight:600; font-size:12px;">Nota de Pie de Página en la Factura</label>
											<input type="text" name="wpat_settings[pdf_company_footer]" id="wpat_pdf_company_footer" value="<?php echo esc_attr( isset( $settings['pdf_company_footer'] ) ? $settings['pdf_company_footer'] : 'Gracias por su compra.' ); ?>" class="regular-text" style="width: 100%;" />
										</div>
									</div>

									<!-- Serie Numérica -->
									<div class="wpat-field-group" style="margin-top: 15px;">
										<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">🔢 Serie Numérica de Facturación</h4>
										<div style="display: flex; gap: 20px; flex-wrap: wrap;">
											<div>
												<label for="wpat_pdf_invoice_prefix" style="display:block; margin-bottom:5px; font-weight:600; font-size:12px;">Prefijo de Serie</label>
												<input type="text" name="wpat_settings[pdf_invoice_prefix]" id="wpat_pdf_invoice_prefix" value="<?php echo esc_attr( isset( $settings['pdf_invoice_prefix'] ) ? $settings['pdf_invoice_prefix'] : 'FACT-' . date( 'Y' ) . '-' ); ?>" class="regular-text" style="height: 32px;" />
											</div>
											<div>
												<label for="wpat_pdf_invoice_next_num" style="display:block; margin-bottom:5px; font-weight:600; font-size:12px;">Siguiente Número Correlativo</label>
												<input type="number" min="1" name="wpat_settings[pdf_invoice_next_num]" id="wpat_pdf_invoice_next_num" value="<?php echo esc_attr( isset( $settings['pdf_invoice_next_num'] ) ? $settings['pdf_invoice_next_num'] : '1' ); ?>" class="small-text" style="height: 32px; text-align: center;" />
											</div>
										</div>
									</div>

									
								</div>
				<?php
				break;
			case 'woo-live-search':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Buscador AJAX en Vivo para Frontend (WooCommerce)</h3>
										<p>Añade una barra de búsqueda ultra-rápida con autocompletado en tiempo real en la tienda pública (shortcode <code>[wpat_product_search]</code> o reemplazo del buscador nativo).</p>
									</div>
									<?php $this->render_module_toggle( 'woo-live-search', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									

									<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
										<div class="wpat-field-group">
											<label style="font-weight: 600; display: block; margin-bottom: 5px;">Máximo de Resultados Desplegables (Límite 1 a 8):</label>
											<?php $max_r = isset( $settings['live_search_max_results'] ) ? intval( $settings['live_search_max_results'] ) : 5; ?>
											<input type="number" min="1" max="8" name="wpat_settings[live_search_max_results]" value="<?php echo $max_r; ?>" style="width: 100px; height: 32px; text-align: center;" />
											<p class="description">Fijado entre 1 y 8 para garantizar la máxima velocidad de respuesta.</p>
										</div>

										<div class="wpat-field-group">
											<label style="font-weight: 600; display: block; margin-bottom: 5px;">Información Secundaria en Resultados:</label>
											<?php $show_meta = isset( $settings['live_search_show_meta'] ) ? $settings['live_search_show_meta'] : 'sku'; ?>
											<select name="wpat_settings[live_search_show_meta]" style="height: 32px; min-width: 220px;">
												<option value="sku" <?php selected( $show_meta, 'sku' ); ?>>Mostrar SKU</option>
												<option value="cat" <?php selected( $show_meta, 'cat' ); ?>>Mostrar Categoría</option>
												<option value="both" <?php selected( $show_meta, 'both' ); ?>>Mostrar Ambos (SKU y Categoría)</option>
												<option value="none" <?php selected( $show_meta, 'none' ); ?>>Ninguno</option>
											</select>
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px; background: #f8fafc; border: 1px solid var(--wpat-border); padding: 12px; border-radius: 6px;">
										<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700;">👁️ Elementos a Mostrar en el Desplegable:</h4>
										<div style="display: flex; gap: 20px; flex-wrap: wrap;">
											<label style="font-size: 12px; font-weight: 600;">
												<input type="checkbox" name="wpat_settings[live_search_show_thumb]" value="1" <?php checked( isset( $settings['live_search_show_thumb'] ) ? $settings['live_search_show_thumb'] : '1', '1' ); ?>>
												Imagen / Thumbnail
											</label>
											<label style="font-size: 12px; font-weight: 600;">
												<input type="checkbox" name="wpat_settings[live_search_show_price]" value="1" <?php checked( isset( $settings['live_search_show_price'] ) ? $settings['live_search_show_price'] : '1', '1' ); ?>>
												Precio (con Oferta)
											</label>
											<label style="font-size: 12px; font-weight: 600;">
												<input type="checkbox" name="wpat_settings[live_search_show_stock]" value="1" <?php checked( isset( $settings['live_search_show_stock'] ) ? $settings['live_search_show_stock'] : '1', '1' ); ?>>
												Insignia de Stock
											</label>
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label style="font-weight: 600;">
											<input type="checkbox" name="wpat_settings[live_search_auto_replace]" value="1" <?php checked( isset( $settings['live_search_auto_replace'] ) ? $settings['live_search_auto_replace'] : '0', '1' ); ?>>
											Reemplazar automáticamente el formulario de búsqueda por defecto de WooCommerce en la plantilla/tema
										</label>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label style="font-weight: 600; display: block; margin-bottom: 5px;">Uso mediante Shortcode:</label>
										<code>[wpat_product_search placeholder="Buscar productos..."]</code>
									</div>

									
								</div>
				<?php
				break;
			case 'woo-facets':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Filtro por Facetas AJAX para Productos (Estilo FacetWP)</h3>
										<p>Permite a los usuarios filtrar productos instantáneamente por Precio, Atributos (Colores/Tallas), Categorías, Stock, Rating u Ordenación sin recargar la página (shortcode <code>[wpat_product_facets]</code>).</p>
									</div>
									<?php $this->render_module_toggle( 'woo-facets', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									

									<div class="wpat-field-group" style="margin-top: 15px; background: #f8fafc; border: 1px solid var(--wpat-border); padding: 15px; border-radius: 6px;">
										<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700;">🎛️ Facetas a Habilitar en el Widget / Sidebar:</h4>
										<?php
										$facets_cfg = isset( $settings['facets_config'] ) && is_array( $settings['facets_config'] ) ? $settings['facets_config'] : array( 'sort', 'price', 'category', 'stock', 'rating' );
										?>
										<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
											<label style="font-size: 12px;">
												<input type="checkbox" name="wpat_settings[facets_config][]" value="sort" <?php checked( in_array( 'sort', $facets_cfg, true ) ); ?>>
												🔃 Selector de Ordenación
											</label>
											<label style="font-size: 12px;">
												<input type="checkbox" name="wpat_settings[facets_config][]" value="price" <?php checked( in_array( 'price', $facets_cfg, true ) ); ?>>
												💰 Rango de Precio (€)
											</label>
											<label style="font-size: 12px;">
												<input type="checkbox" name="wpat_settings[facets_config][]" value="category" <?php checked( in_array( 'category', $facets_cfg, true ) ); ?>>
												🏷️ Categorías de Producto
											</label>
											<label style="font-size: 12px;">
												<input type="checkbox" name="wpat_settings[facets_config][]" value="attribute" <?php checked( in_array( 'attribute', $facets_cfg, true ) ); ?>>
												🎨 Atributos (Color, Talla...)
											</label>
											<label style="font-size: 12px;">
												<input type="checkbox" name="wpat_settings[facets_config][]" value="stock" <?php checked( in_array( 'stock', $facets_cfg, true ) ); ?>>
												📦 Stock y En Oferta
											</label>
											<label style="font-size: 12px;">
												<input type="checkbox" name="wpat_settings[facets_config][]" value="rating" <?php checked( in_array( 'rating', $facets_cfg, true ) ); ?>>
												⭐️ Valoración (Estrellas)
											</label>
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label style="font-weight: 600; display: block; margin-bottom: 5px;">Uso mediante Shortcode:</label>
										<code>[wpat_product_facets title="Filtrar Productos"]</code>
										<p class="description" style="margin-top: 4px;">Inserta este shortcode en la barra lateral (Sidebar) o plantilla de la tienda.</p>
									</div>

									
								</div>
							</div>
				<?php
				break;
			case 'duplicator':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Clonador de Entradas y Páginas</h3>
										<p>Añade un enlace "Clonar" en los listados de administración para duplicar instantáneamente cualquier Entrada, Página o Custom Post Type conservando metadatos (ACF/JetEngine).</p>
									</div>
									<?php $this->render_module_toggle( 'duplicator', $settings, false ); ?>
								</div>
				<?php
				break;
			case 'snippets':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Fragmentos de Código Personalizados (Snippets)</h3>
										<p>Inyecta CSS en la cabecera, Javascript en el footer y ejecuta código PHP dinámico sin tocar los archivos de tu tema.</p>
									</div>
									<?php $this->render_module_toggle( 'snippets', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									
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
				<?php
				break;
			case 'performance':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Ajustes de Rendimiento</h3>
										<p>Desactiva los emojis integrados, remueve etiquetas meta innecesarias del <head> de WordPress y limita las revisiones por entrada a un máximo de 5.</p>
									</div>
									<?php $this->render_module_toggle( 'performance', $settings, false ); ?>
								</div>
				<?php
				break;
			case 'reading-progress':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Experiencia de Lectura & UX en Entradas</h3>
										<p>Muestra una barra superior de avance al hacer scroll y calcula automáticamente el tiempo estimado de lectura en las entradas (posts).</p>
									</div>
									<?php $this->render_module_toggle( 'reading-progress', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									

									<div class="wpat-field-group" style="margin-top: 15px; display: flex; gap: 25px; align-items: flex-start; flex-wrap: wrap;">
										<div>
											<label for="wpat_reading_bar_color" style="display:block; margin-bottom:5px; font-weight:600;">Color de la Barra de Lectura</label>
											<input type="text" name="wpat_settings[reading_bar_color]" id="wpat_reading_bar_color" value="<?php echo esc_attr( isset( $settings['reading_bar_color'] ) ? $settings['reading_bar_color'] : '#2563eb' ); ?>" class="wpat-color-picker" />
										</div>
										<div>
											<label for="wpat_reading_bar_height" style="display:block; margin-bottom:5px; font-weight:600;">Grosor de la Barra (píxeles)</label>
											<input type="number" name="wpat_settings[reading_bar_height]" id="wpat_reading_bar_height" min="1" max="30" value="<?php echo esc_attr( isset( $settings['reading_bar_height'] ) ? $settings['reading_bar_height'] : '4' ); ?>" class="small-text" style="height: 30px; text-align: center;" /> px
											<p class="description" style="margin-top:3px;">Por defecto: 4px. Auméntalo (ej. 6px o 8px) para que sea más visible.</p>
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<label style="font-weight: 600;">
											<input type="checkbox" name="wpat_settings[reading_time_enabled]" value="1" <?php checked( isset( $settings['reading_time_enabled'] ) ? $settings['reading_time_enabled'] : '0', '1' ); ?>>
											Mostrar Tiempo Estimado de Lectura (Badge automático al inicio del artículo)
										</label>
										<p class="description" style="margin-top:6px;">Calcula automáticamente el tiempo necesario en base a 200 palabras/minuto e inserta una etiqueta estilizada (ej. <code>⏱️ Tiempo estimado de lectura: 3 min</code>) justo antes del contenido de la entrada. También puedes insertarlo manualmente en cualquier maquetador (Elementor, Divi, Gutenberg) mediante los shortcodes <code>[tiempo_lectura]</code> o <code>[wpat_reading_time]</code>.</p>
									</div>

									
								</div>
				<?php
				break;
			case 'accessibility':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Herramientas de Accesibilidad Web (Zero-Bloat)</h3>
										<p>Añade un widget flotante ultra-ligero de accesibilidad (Aumentar texto, Escala de grises, Alto contraste, Fuente legible, etc.) sin cargar scripts ni librerías pesadas de terceros.</p>
									</div>
									<?php $this->render_module_toggle( 'accessibility', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									

									<div class="wpat-field-group" style="margin-top: 15px; display: flex; gap: 25px; align-items: flex-start; flex-wrap: wrap;">
										<div>
											<label for="wpat_accessibility_position" style="display:block; margin-bottom:5px; font-weight:600;">Posición del Botón Flotante</label>
											<select name="wpat_settings[accessibility_position]" id="wpat_accessibility_position" style="height: 32px;">
												<option value="bottom-left" <?php selected( isset( $settings['accessibility_position'] ) ? $settings['accessibility_position'] : 'bottom-left', 'bottom-left' ); ?>>Inferior Izquierda (Recomendado)</option>
												<option value="bottom-right" <?php selected( isset( $settings['accessibility_position'] ) ? $settings['accessibility_position'] : 'bottom-left', 'bottom-right' ); ?>>Inferior Derecha</option>
												<option value="top-left" <?php selected( isset( $settings['accessibility_position'] ) ? $settings['accessibility_position'] : 'bottom-left', 'top-left' ); ?>>Superior Izquierda</option>
												<option value="top-right" <?php selected( isset( $settings['accessibility_position'] ) ? $settings['accessibility_position'] : 'bottom-left', 'top-right' ); ?>>Superior Derecha</option>
											</select>
										</div>
										<div>
											<label for="wpat_accessibility_offset_y" style="display:block; margin-bottom:5px; font-weight:600;">Distancia Vertical (Margen en px)</label>
											<input type="number" name="wpat_settings[accessibility_offset_y]" id="wpat_accessibility_offset_y" min="0" max="500" value="<?php echo esc_attr( isset( $settings['accessibility_offset_y'] ) ? $settings['accessibility_offset_y'] : '25' ); ?>" class="small-text" style="height: 32px; text-align: center;" /> px
										</div>
										<div>
											<label for="wpat_accessibility_bg_color" style="display:block; margin-bottom:5px; font-weight:600;">Color del Icono Flotante</label>
											<input type="text" name="wpat_settings[accessibility_bg_color]" id="wpat_accessibility_bg_color" value="<?php echo esc_attr( isset( $settings['accessibility_bg_color'] ) ? $settings['accessibility_bg_color'] : '#2563eb' ); ?>" class="wpat-color-picker" />
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dashed var(--wpat-border); padding-top: 15px;">
										<label style="font-weight: 600; display: block; margin-bottom: 10px;">Herramientas Activas en el Menú de Accesibilidad:</label>
										<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[accessibility_text_zoom]" value="1" <?php checked( isset( $settings['accessibility_text_zoom'] ) ? $settings['accessibility_text_zoom'] : '1', '1' ); ?>>
												🔍 Aumentar / Disminuir Texto
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[accessibility_grayscale]" value="1" <?php checked( isset( $settings['accessibility_grayscale'] ) ? $settings['accessibility_grayscale'] : '1', '1' ); ?>>
												⏸️ Escala de Grises
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[accessibility_high_contrast]" value="1" <?php checked( isset( $settings['accessibility_high_contrast'] ) ? $settings['accessibility_high_contrast'] : '1', '1' ); ?>>
												🌓 Alto Contraste
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[accessibility_negative_contrast]" value="1" <?php checked( isset( $settings['accessibility_negative_contrast'] ) ? $settings['accessibility_negative_contrast'] : '1', '1' ); ?>>
												👁️ Contraste Negativo / Invertir
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[accessibility_light_bg]" value="1" <?php checked( isset( $settings['accessibility_light_bg'] ) ? $settings['accessibility_light_bg'] : '1', '1' ); ?>>
												💡 Fondo Claro
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[accessibility_underline_links]" value="1" <?php checked( isset( $settings['accessibility_underline_links'] ) ? $settings['accessibility_underline_links'] : '1', '1' ); ?>>
												🔗 Subrayar Enlaces
											</label>
											<label style="font-weight: normal;">
												<input type="checkbox" name="wpat_settings[accessibility_readable_font]" value="1" <?php checked( isset( $settings['accessibility_readable_font'] ) ? $settings['accessibility_readable_font'] : '1', '1' ); ?>>
												🅰️ Fuente Legible (Sans-Serif)
											</label>
										</div>
									</div>

									
								</div>
							</div>
				<?php
				break;
			case 'svg-support':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Soporte para Archivos SVG</h3>
										<p>Habilita la subida de archivos SVG en la Biblioteca de Medios aplicando una sanitización básica de seguridad XML (remueve tags de script e inyecciones XSS).</p>
									</div>
									<?php $this->render_module_toggle( 'svg-support', $settings, false ); ?>
								</div>
				<?php
				break;
			case 'image-optimizer':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Optimizador de Imágenes a WebP</h3>
										<p>Intercepta las subidas de imágenes, las escala a un ancho/alto máximo de 1920px, las convierte automáticamente al formato óptimo .webp (calidad 82%) y descarta los archivos originales pesados.</p>
									</div>
									<?php $this->render_module_toggle( 'image-optimizer', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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
				<?php
				break;
			case 'smtp':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Servidor de Correo SMTP</h3>
										<p>Activa y define la conexión con tu proveedor SMTP (Gmail, SendGrid, Outlook, Mailgun o tu propio servidor de hosting).</p>
									</div>
									<?php $this->render_module_toggle( 'smtp', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									
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

									
								</div>
							</div>

							<!-- Caja de Correo de Prueba -->
							<div class="wpat-module-card" style="margin-top: 30px;">
								<div class="wpat-module-header" style="border-bottom: 1px solid var(--wpat-border);">
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
				<?php
				break;
						case 'sitemap-xml':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Generador de Sitemap XML</h3>
										<p>Genera automáticamente un sitemap XML dinámico y ligero en la raíz de tu sitio (<code>/sitemap.xml</code>) excluyendo cualquier contenido configurado como noindex.</p>
									</div>
									<?php $this->render_module_toggle( 'sitemap-xml', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									<div class="wpat-field-group">
										<label>Sitemap XML Integrado</label>
										<p class="description" style="margin-bottom: 12px;">El sitemap se genera dinámicamente en <code><?php echo esc_url( home_url( '/sitemap.xml' ) ); ?></code> y excluye automáticamente las páginas o entradas configuradas con directiva <code>noindex</code>.</p>
										<div style="display: flex; gap: 10px; flex-wrap: wrap;">
											<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" class="button button-secondary">
												<span class="dashicons dashicons-external" style="vertical-align: middle; font-size: 16px; width: 16px; height: 16px; margin-right: 5px;"></span> Ver Sitemap.xml
											</a>
											<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" download="sitemap.xml" class="button button-secondary">
												<span class="dashicons dashicons-download" style="vertical-align: middle; font-size: 16px; width: 16px; height: 16px; margin-right: 5px;"></span> Descargar Sitemap.xml
											</a>
										</div>
									</div>
								</div>
							</div>
				<?php
				break;
			case 'woo-sale-badges':
				$shape      = isset( $settings['woo_sale_badge_shape'] ) ? $settings['woo_sale_badge_shape'] : 'soft';
				$text_type  = isset( $settings['woo_sale_badge_text_type'] ) ? $settings['woo_sale_badge_text_type'] : 'custom';
				$custom_txt = isset( $settings['woo_sale_badge_custom_text'] ) ? $settings['woo_sale_badge_custom_text'] : '¡OFERTA!';
				$bg_color   = isset( $settings['woo_sale_badge_bg_color'] ) ? $settings['woo_sale_badge_bg_color'] : '#ef4444';
				$txt_color  = isset( $settings['woo_sale_badge_txt_color'] ) ? $settings['woo_sale_badge_txt_color'] : '#ffffff';
				$font_size  = isset( $settings['woo_sale_badge_font_size'] ) ? $settings['woo_sale_badge_font_size'] : '13';
				$position   = isset( $settings['woo_sale_badge_position'] ) ? $settings['woo_sale_badge_position'] : 'top-left';
				$is_new_mod = $this->is_new_module( 'woo-sale-badges' );
				?>
				<div class="wpat-module-card" style="position: relative; overflow: hidden;">
					<?php if ( $is_new_mod ) : ?>
						<div class="wpat-new-module-ribbon" style="position: absolute; top: 12px; right: -28px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-size: 10px; font-weight: 800; padding: 3px 30px; transform: rotate(45deg); text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 2px 4px rgba(0,0,0,0.15); pointer-events: none; z-index: 5;">NUEVO</div>
					<?php endif; ?>
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Badges y Etiquetas de Oferta High-Impact</h3>
							<p>Reemplaza la etiqueta de oferta nativa de WooCommerce por botones, cintas y etiquetas personalizadas de alto impacto con cálculo automático de descuento.</p>
						</div>
						<?php $this->render_module_toggle( 'woo-sale-badges',
			$settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">
						<div class="wpat-field-group">
							<label style="font-weight: 700; display: block; margin-bottom: 12px;">Selecciona la Forma del Badge / Etiqueta:</label>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
								<label style="border: 2px solid <?php echo 'soft' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="soft" <?php checked( $shape, 'soft' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Bordes Suaves</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Esquinas ligeramente redondeadas (8px radius).</span>
								</label>
								<label style="border: 2px solid <?php echo 'pill' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="pill" <?php checked( $shape, 'pill' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Píldora (Pill)</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Totalmente redondeado estilo cápsula (20px radius).</span>
								</label>
								<label style="border: 2px solid <?php echo 'rect' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="rect" <?php checked( $shape, 'rect' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Rectangular</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Bordes rectos limpios (0px radius).</span>
								</label>
								<label style="border: 2px solid <?php echo 'circle' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="circle" <?php checked( $shape, 'circle' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Circular Compacto</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Badge circular concéntrico (50x50px).</span>
								</label>
								<label style="border: 2px solid <?php echo 'corner-ribbon' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="corner-ribbon" <?php checked( $shape, 'corner-ribbon' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Cinta Diagonal</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Cinta de esquina cruzada a 45 grados.</span>
								</label>
								<label style="border: 2px solid <?php echo 'price-tag' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="price-tag" <?php checked( $shape, 'price-tag' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Etiqueta de Precio</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Estilo ticket con muesca lateral.</span>
								</label>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
							<div class="wpat-field-group">
								<label style="font-weight: 600; display: block; margin-bottom: 6px;">Tipo de Texto a Mostrar:</label>
								<select name="wpat_settings[woo_sale_badge_text_type]" class="regular-text" style="width: 100%;">
									<option value="custom" <?php selected( $text_type, 'custom' ); ?>>Texto Personalizado (Ej: ¡OFERTA!)</option>
									<option value="percentage" <?php selected( $text_type, 'percentage' ); ?>>Porcentaje de Descuento Real (Ej: -25%)</option>
								</select>
							</div>
							<div class="wpat-field-group">
								<label style="font-weight: 600; display: block; margin-bottom: 6px;">Texto Personalizado:</label>
								<input type="text" name="wpat_settings[woo_sale_badge_custom_text]" value="<?php echo esc_attr( $custom_txt ); ?>" class="regular-text" style="width: 100%;" placeholder="¡OFERTA!">
							</div>
						</div>

						<div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-top: 15px;">
							<div class="wpat-field-group">
								<label style="font-weight: 600; display: block; margin-bottom: 6px;">Color de Fondo:</label>
								<input type="text" name="wpat_settings[woo_sale_badge_bg_color]" value="<?php echo esc_attr( $bg_color ); ?>" class="wpat-color-picker" data-default-color="#ef4444">
							</div>
							<div class="wpat-field-group">
								<label style="font-weight: 600; display: block; margin-bottom: 6px;">Color de Texto:</label>
								<input type="text" name="wpat_settings[woo_sale_badge_txt_color]" value="<?php echo esc_attr( $txt_color ); ?>" class="wpat-color-picker" data-default-color="#ffffff">
							</div>
							<div class="wpat-field-group">
								<label style="font-weight: 600; display: block; margin-bottom: 6px;">Tamaño Fuente (px):</label>
								<input type="number" name="wpat_settings[woo_sale_badge_font_size]" value="<?php echo esc_attr( $font_size ); ?>" min="8" max="32" class="small-text">
							</div>
							<div class="wpat-field-group">
								<label style="font-weight: 600; display: block; margin-bottom: 6px;">Posición en la Imagen:</label>
								<select name="wpat_settings[woo_sale_badge_position]" style="width: 100%;">
									<option value="top-left" <?php selected( $position, 'top-left' ); ?>>Superior Izquierda</option>
									<option value="top-right" <?php selected( $position, 'top-right' ); ?>>Superior Derecha</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<?php
				break;
			case 'woo-checkout-designer':
				$layout             = isset( $settings['woo_checkout_designer_layout'] ) ? $settings['woo_checkout_designer_layout'] : 'wpat-classic';
				$mobile_summary     = ! isset( $settings['woo_checkout_designer_mobile_summary'] ) || '1' === $settings['woo_checkout_designer_mobile_summary'];
				$trust_badges       = ! isset( $settings['woo_checkout_designer_trust_badges'] ) || '1' === $settings['woo_checkout_designer_trust_badges'];
				$email_fix           = ! isset( $settings['woo_checkout_designer_email_fix'] ) || '1' === $settings['woo_checkout_designer_email_fix'];
				$product_thumbs      = ! isset( $settings['woo_checkout_designer_product_thumbs'] ) || '1' === $settings['woo_checkout_designer_product_thumbs'];
				$btn_bg_color        = isset( $settings['woo_checkout_btn_bg_color'] ) ? $settings['woo_checkout_btn_bg_color'] : '#2563eb';
				$btn_hover_bg_color  = isset( $settings['woo_checkout_btn_hover_bg_color'] ) ? $settings['woo_checkout_btn_hover_bg_color'] : '#1d4ed8';
				$btn_txt_color       = isset( $settings['woo_checkout_btn_txt_color'] ) ? $settings['woo_checkout_btn_txt_color'] : '#ffffff';
				$step_accent_color   = isset( $settings['woo_checkout_step_accent_color'] ) ? $settings['woo_checkout_step_accent_color'] : '#2563eb';
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Diseño de Checkout High-Conversion</h3>
							<p>Transforma la página de pago (<em>Checkout</em>) de WooCommerce en una experiencia fluida de alta conversión inspirada en la mejor UX del mercado sin sobrecargar tu web.</p>
						</div>
						<?php $this->render_module_toggle( 'woo-checkout-designer', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">
						<div class="wpat-field-group">
							<label style="font-weight: 700; display: block; margin-bottom: 12px;">Selecciona la Plantilla de Checkout:</label>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
								<label style="border: 2px solid <?php echo 'wpat-classic' === $layout ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-classic" <?php checked( $layout, 'wpat-classic' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Classic Checkout</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Multi-Paso (3 Pasos) con migas de pan y columna de resumen fija (<em>sticky</em>).</span>
								</label>
								<label style="border: 2px solid <?php echo 'wpat-express' === $layout ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-express" <?php checked( $layout, 'wpat-express' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Express Checkout</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Vista rápida en 2 columnas agrupadas en tarjetas redondeadas limpias.</span>
								</label>
								<label style="border: 2px solid <?php echo 'wpat-accordion' === $layout ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-accordion" <?php checked( $layout, 'wpat-accordion' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Accordion Checkout</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Pasos desplegables secuenciales que avanzan conforme se valida cada paso.</span>
								</label>
								<label style="border: 2px solid <?php echo 'wpat-minimalist' === $layout ? '#2563eb' : '#e2e8f0'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
									<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-minimalist" <?php checked( $layout, 'wpat-minimalist' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Minimalist Checkout</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">1 Columna ultra-limpia enfocado en máxima conversión y sellos de confianza.</span>
								</label>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

						<div class="wpat-field-group">
							<label style="font-weight: 700; display: block; margin-bottom: 12px;">Personalización de Colores de Botones y Pasos:</label>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Fondo de Botones Principales:</label>
									<input type="text" name="wpat_settings[woo_checkout_btn_bg_color]" value="<?php echo esc_attr( $btn_bg_color ); ?>" class="wpat-color-picker" data-default-color="#2563eb">
								</div>
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Fondo Al Pasar el Ratón (Hover):</label>
									<input type="text" name="wpat_settings[woo_checkout_btn_hover_bg_color]" value="<?php echo esc_attr( $btn_hover_bg_color ); ?>" class="wpat-color-picker" data-default-color="#1d4ed8">
								</div>
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Texto de Botones Principales:</label>
									<input type="text" name="wpat_settings[woo_checkout_btn_txt_color]" value="<?php echo esc_attr( $btn_txt_color ); ?>" class="wpat-color-picker" data-default-color="#ffffff">
								</div>
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color del Paso Activo (Acento):</label>
									<input type="text" name="wpat_settings[woo_checkout_step_accent_color]" value="<?php echo esc_attr( $step_accent_color ); ?>" class="wpat-color-picker" data-default-color="#2563eb">
								</div>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

						<div class="wpat-field-group">
							<label style="font-weight: 600;">
								<input type="checkbox" name="wpat_settings[woo_checkout_designer_mobile_summary]" value="1" <?php checked( $mobile_summary ); ?>>
								Activar Barra Flotante de Resumen del Pedido en Smartphones (Estilo Desplegable Superior)
							</label>
						</div>

						<div class="wpat-field-group" style="margin-top: 12px;">
							<label style="font-weight: 600;">
								<input type="checkbox" name="wpat_settings[woo_checkout_designer_product_thumbs]" value="1" <?php checked( $product_thumbs ); ?>>
								Mostrar Miniaturas de Productos con Badge de Cantidad en el Resumen
							</label>
						</div>

						<div class="wpat-field-group" style="margin-top: 12px;">
							<label style="font-weight: 600;">
								<input type="checkbox" name="wpat_settings[woo_checkout_designer_trust_badges]" value="1" <?php checked( $trust_badges ); ?>>
								Mostrar Tarjetas y Sellos de Garantía y Pago Seguro (SSL, Envío Seguro, Soporte)
							</label>
						</div>

						<div class="wpat-field-group" style="margin-top: 12px;">
							<label style="font-weight: 600;">
								<input type="checkbox" name="wpat_settings[woo_checkout_designer_email_fix]" value="1" <?php checked( $email_fix ); ?>>
								Activar Corrección Inteligente de Erratas en Correos Electrónicos (Ej: <code>@gmai.com</code> &rarr; <code>@gmail.com</code>)
							</label>
						</div>
					</div>
				</div>
				<?php
				break;
			case 'woo-address-autofill':
				$autofill_city = ! isset( $settings['woo_address_autofill_city'] ) || '1' === $settings['woo_address_autofill_city'];
				$is_new_mod    = $this->is_new_module( 'woo-address-autofill' );
				require_once WPAT_PATH . 'includes/modules/class-wpat-woo-address-autofill.php';
				$configured_countries = WPAT_Woo_Address_Autofill::get_configured_countries();
				?>
				<div class="wpat-module-card" style="position: relative; overflow: hidden;">
					<?php if ( $is_new_mod ) : ?>
						<div class="wpat-new-module-ribbon" style="position: absolute; top: 12px; right: -28px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff; font-size: 10px; font-weight: 800; padding: 3px 30px; transform: rotate(45deg); text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 2px 4px rgba(0,0,0,0.15); pointer-events: none; z-index: 5;">NUEVO</div>
					<?php endif; ?>
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Autocompletado de CP, Provincia y Población</h3>
							<p>Gestión avanzada multi-país de autocompletado recíproco de Provincia, Población y Código Postal. Puedes activar o desactivar países e importar/exportar listados de nuevas regiones mediante archivos CSV.</p>
						</div>
						<?php $this->render_module_toggle( 'woo-address-autofill', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">
						<div class="wpat-field-group">
							<label style="font-weight: 600;">
								<input type="checkbox" name="wpat_settings[woo_address_autofill_city]" value="1" <?php checked( $autofill_city ); ?>>
								Autocompletar / Sugerir la Población al ingresar los dígitos del Código Postal
							</label>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 20px 0;" />

						<h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">Países Configurados y Gestión CSV</h4>

						<table class="wp-list-table widefat fixed striped" style="border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
							<thead>
								<tr>
									<th style="font-weight: 700; width: 140px;">País / Código</th>
									<th style="font-weight: 700;">Provincias</th>
									<th style="font-weight: 700;">Poblaciones</th>
									<th style="font-weight: 700; width: 100px;">Estado</th>
									<th style="font-weight: 700; width: 180px;">Acciones</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $configured_countries as $code => $cinfo ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $cinfo['name'] ); ?></strong> (<code><?php echo esc_html( $code ); ?></code>)</td>
										<td><?php echo esc_html( $cinfo['total_provinces'] ); ?> provincias</td>
										<td><?php echo esc_html( $cinfo['total_cities'] ); ?> poblaciones</td>
										<td>
											<label class="wpat-toggle-switch" style="transform: scale(0.85); transform-origin: left center;">
												<input type="checkbox" class="wpat-autofill-country-toggle" data-country="<?php echo esc_attr( $code ); ?>" <?php checked( isset( $cinfo['enabled'] ) && '1' === (string) $cinfo['enabled'] ); ?>>
												<span class="wpat-toggle-slider"></span>
											</label>
										</td>
										<td>
											<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=wpat_autofill_export_csv&country=' . $code . '&security=' . wp_create_nonce( 'wpat_save_settings_action' ) ) ); ?>" class="button button-small" title="Exportar a CSV">📥 CSV</a>
											<?php if ( empty( $cinfo['is_builtin'] ) ) : ?>
												<button type="button" class="button button-small wpat-autofill-delete-country-btn" data-country="<?php echo esc_attr( $code ); ?>" style="color:#ef4444; border-color:#fca5a5;">🗑️ Eliminar</button>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 18px;">
							<h5 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 700; color: #1e293b;">Importar Nuevo País o Actualizar Datos mediante CSV</h5>
							<p style="margin: 0 0 12px 0; font-size: 12.5px; color: #64748b;">El archivo CSV debe incluir las columnas: <code>codigo_pais</code>, <code>codigo_postal</code>, <code>codigo_provincia</code>, <code>nombre_provincia</code>, <code>nombre_poblacion</code>.</p>
							
							<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
								<input type="file" id="wpat_autofill_csv_input" accept=".csv" style="font-size: 13px; max-width: 260px;" />
								<button type="button" class="button button-primary" id="wpat_autofill_import_btn">📤 Importar Archivo CSV</button>
							</div>
							<div id="wpat_autofill_import_msg" style="margin-top: 10px; font-size: 13px; font-weight: 600;"></div>
						</div>
					</div>
				</div>
				<?php
				break;
			case 'seo':
				?>
<div class="wpat-module-card">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Módulo SEO Ultra-Ligero</h3>
										<p>Activa los meta campos de títulos y descripciones en tus páginas, previsualización en Google y metadatos Open Graph.</p>
									</div>
									<?php $this->render_module_toggle( 'seo', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
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
										</ul>
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
														<th style="font-weight:700; width: 30%; padding: 10px;">Página / Entrada</th>
														<th style="font-weight:700; width: 11%; padding: 10px;">Indexable</th>
														<th style="font-weight:700; width: 15%; padding: 10px;">Frase Clave</th>
														<th style="font-weight:700; width: 17%; padding: 10px;">Título SEO</th>
														<th style="font-weight:700; width: 17%; padding: 10px;">Meta Descripción</th>
														<th style="font-weight:700; width: 10%; padding: 10px; text-align: center;">Acciones</th>
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

						<?php
						break;

					case 'integrations':
						?>
						<div class="wpat-module-card" style="margin-bottom: 20px;">
							<div class="wpat-module-header">
								<div class="wpat-module-info">
									<h3>Integraciones de Terceros</h3>
									<p>Gestiona e inyecta códigos de herramientas externas en tu web de forma ultra-ligera, limpia y sin sobrecargar la web.</p>
								</div>
							</div>
						</div>
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
								<div class="wpat-module-header" style="cursor: pointer; border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Google Search Console</h3>
											<div style="display: flex; align-items: center; gap: 10px;">
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
												
												</span>
											</div>
										</div>
										<p style="margin: 0 0 4px 0; color: #64748b; font-size: 13px;">El plugin detecta automáticamente si has subido un archivo de verificación de Google (tipo <code>googleXXXX.html</code>) a la carpeta raíz de tu hosting, o si prefieres puedes pegar el código meta abajo. Puedes conseguir tu código en <a href="https://search.google.com/search-console/welcome" target="_blank" rel="noopener noreferrer" style="color: var(--wpat-primary); font-weight: 600; text-decoration: underline;">Google Search Console</a>.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="display: none; padding: 15px 20px 20px 20px;">
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
								<div class="wpat-module-header" style="cursor: pointer; border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Google Analytics (GA4)</h3>
											<div style="display: flex; align-items: center; gap: 10px;">
												<?php if ( ! empty( $settings['google_analytics_id'] ) && preg_match( '/^G-[A-Z0-9]+$/i', trim( $settings['google_analytics_id'] ) ) ) : ?>
													<span class="wpat-status-indicator" style="background: #e6f4ea; color: #137333; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #137333; border-radius: 50%;"></span> Conectado
													</span>
												<?php else : ?>
													<span class="wpat-status-indicator" style="background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span> Sin configurar
													</span>
												<?php endif; ?>
												
												</span>
											</div>
										</div>
										<p style="margin: 0 0 4px 0; color: #64748b; font-size: 13px;">Introduce tu ID de medición de Google Analytics 4 (debe comenzar con <code>G-</code>). Puedes conseguir tu ID G-XXXX en la sección de flujos de datos de administración de <a href="https://analytics.google.com/analytics/web/#/admin" target="_blank" rel="noopener noreferrer" style="color: var(--wpat-primary); font-weight: 600; text-decoration: underline;">Google Analytics</a>.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="display: none; padding: 15px 20px 20px 20px;">
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

							<!-- Tarjeta: Google Tag Manager (GTM) -->
							<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header" style="cursor: pointer; border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Google Tag Manager (GTM)</h3>
											<div style="display: flex; align-items: center; gap: 10px;">
												<?php if ( ! empty( $settings['gtm_container_id'] ) && preg_match( '/^GTM-[A-Z0-9]+$/i', trim( $settings['gtm_container_id'] ) ) ) : ?>
													<span class="wpat-status-indicator" style="background: #e6f4ea; color: #137333; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #137333; border-radius: 50%;"></span> Conectado
													</span>
												<?php else : ?>
													<span class="wpat-status-indicator" style="background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span> Sin configurar
													</span>
												<?php endif; ?>
											</div>
										</div>
										<p style="margin: 0 0 4px 0; color: #64748b; font-size: 13px;">Introduce el ID de contenedor de Google Tag Manager (debe comenzar con <code>GTM-</code>). Obtén tu ID en <a href="https://tagmanager.google.com/" target="_blank" rel="noopener noreferrer" style="color: var(--wpat-primary); font-weight: 600; text-decoration: underline;">Google Tag Manager</a>.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="display: none; padding: 15px 20px 20px 20px;">
									<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
										<div style="flex: 1; min-width: 300px;">
											<input type="text" name="wpat_settings[gtm_container_id]" id="wpat_gtm_container_id" value="<?php echo esc_attr( isset( $settings['gtm_container_id'] ) ? $settings['gtm_container_id'] : '' ); ?>" class="large-text" placeholder="Ej: GTM-XXXXXXX" style="width:100%; margin:0;" />
										</div>
										<a href="https://tagmanager.google.com/" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="height: 30px; display: inline-flex; align-items: center; gap: 5px;">
											<span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px; margin: 0;"></span> Acceder a GTM
										</a>
									</div>
								</div>
							</div>

							<!-- Tarjeta: Facebook / Meta Pixel -->
							<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header" style="cursor: pointer; border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Facebook / Meta Pixel</h3>
											<div style="display: flex; align-items: center; gap: 10px;">
												<?php if ( ! empty( $settings['facebook_pixel_id'] ) && preg_match( '/^[0-9]+$/', trim( $settings['facebook_pixel_id'] ) ) ) : ?>
													<span class="wpat-status-indicator" style="background: #e6f4ea; color: #137333; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #137333; border-radius: 50%;"></span> Conectado
													</span>
												<?php else : ?>
													<span class="wpat-status-indicator" style="background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span> Sin configurar
													</span>
												<?php endif; ?>
											</div>
										</div>
										<p style="margin: 0 0 4px 0; color: #64748b; font-size: 13px;">Introduce tu ID de Pixel de Meta/Facebook (sólo dígitos). Obtén tu ID en el <a href="https://eventsmanager.facebook.com/" target="_blank" rel="noopener noreferrer" style="color: var(--wpat-primary); font-weight: 600; text-decoration: underline;">Administrador de Eventos de Meta</a>.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="display: none; padding: 15px 20px 20px 20px;">
									<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
										<div style="flex: 1; min-width: 300px;">
											<input type="text" name="wpat_settings[facebook_pixel_id]" id="wpat_facebook_pixel_id" value="<?php echo esc_attr( isset( $settings['facebook_pixel_id'] ) ? $settings['facebook_pixel_id'] : '' ); ?>" class="large-text" placeholder="Ej: 123456789012345" style="width:100%; margin:0;" />
										</div>
										<a href="https://eventsmanager.facebook.com/" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="height: 30px; display: inline-flex; align-items: center; gap: 5px;">
											<span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px; margin: 0;"></span> Meta Events Manager
										</a>
									</div>
								</div>
							</div>

							<!-- Tarjeta: Google PageSpeed Insights -->
							<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header" style="cursor: pointer; border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Google PageSpeed Insights</h3>
											<div style="display: flex; align-items: center; gap: 10px;">
												<span class="wpat-status-indicator" style="background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
													<span style="width: 6px; height: 6px; background: #0369a1; border-radius: 50%;"></span> Listo
												</span>
												
												</span>
											</div>
										</div>
										<p style="margin: 0 0 4px 0; color: #64748b; font-size: 13px;">Audita el rendimiento, la velocidad de carga real y la optimización móvil/escritorio del sitio web de forma externa y gratuita en Google PageSpeed.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="display: none; padding: 15px 20px 20px 20px;">
									<div>
										<a href="<?php echo esc_url( 'https://pagespeed.web.dev/analysis?url=' . urlencode( home_url( '/' ) ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" style="height: 32px; display: inline-flex; align-items: center; gap: 5px;">
											<span class="dashicons dashicons-performance" style="font-size: 16px; width: 16px; height: 16px; margin: 0;"></span> Analizar Velocidad del Sitio
										</a>
									</div>
								</div>
							</div>

							<!-- Tarjeta: Almacenamiento en Google Drive -->
							<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header" style="cursor: pointer; border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Google Drive Storage</h3>
											<div style="display: flex; align-items: center; gap: 10px;">
												<?php if ( ! empty( $settings['google_drive_token'] ) ) : ?>
													<span class="wpat-status-indicator" style="background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #0369a1; border-radius: 50%;"></span> Listo
													</span>
												<?php else : ?>
													<span class="wpat-status-indicator" style="background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span> Sin configurar
													</span>
												<?php endif; ?>
												
												</span>
											</div>
										</div>
										<p style="margin: 0 0 4px 0; color: #64748b; font-size: 13px;">Sincroniza y sube automáticamente los archivos cargados por los clientes en los campos de producto extra directamente a tu cuenta de Google Drive.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="display: none; padding: 15px 20px 20px 20px;">
									<div class="wpat-field-group">
										<label for="wpat_google_drive_token" style="display:block; font-weight:600; margin-bottom:5px;">Token de Acceso (OAuth Bearer Token / API Key)</label>
										<input type="text" name="wpat_settings[google_drive_token]" id="wpat_google_drive_token" value="<?php echo esc_attr( isset( $settings['google_drive_token'] ) ? $settings['google_drive_token'] : '' ); ?>" class="large-text" placeholder="ya29.a0... / Token OAuth2 Google Drive API" style="width:100%; margin:0 0 10px 0;" />
									</div>
									<div class="wpat-field-group">
										<label for="wpat_google_drive_folder" style="display:block; font-weight:600; margin-bottom:5px;">ID de Carpeta Destino (Opcional)</label>
										<input type="text" name="wpat_settings[google_drive_folder]" id="wpat_google_drive_folder" value="<?php echo esc_attr( isset( $settings['google_drive_folder'] ) ? $settings['google_drive_folder'] : '' ); ?>" class="regular-text" placeholder="Ej: 1A2b3C4d5E6f7G8h9I0J" style="width:100%; margin:0;" />
										<p class="description" style="margin-top:4px;">Dejar en blanco para guardar en la raíz de Google Drive o indica el ID de la carpeta de tu unidad.</p>
									</div>
								</div>
							</div>

							<!-- Tarjeta: Almacenamiento en Dropbox -->
							<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header" style="cursor: pointer; border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Dropbox Storage</h3>
											<div style="display: flex; align-items: center; gap: 10px;">
												<?php if ( ! empty( $settings['dropbox_token'] ) ) : ?>
													<span class="wpat-status-indicator" style="background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #0369a1; border-radius: 50%;"></span> Listo
													</span>
												<?php else : ?>
													<span class="wpat-status-indicator" style="background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span> Sin configurar
													</span>
												<?php endif; ?>
												
												</span>
											</div>
										</div>
										<p style="margin: 0 0 4px 0; color: #64748b; font-size: 13px;">Almacena copias de seguridad de las imágenes, PDFs y archivos adjuntos por los compradores en tu cuenta de Dropbox.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="display: none; padding: 15px 20px 20px 20px;">
									<div class="wpat-field-group">
										<label for="wpat_dropbox_token" style="display:block; font-weight:600; margin-bottom:5px;">Token de Acceso Personal de Dropbox (OAuth Access Token)</label>
										<input type="text" name="wpat_settings[dropbox_token]" id="wpat_dropbox_token" value="<?php echo esc_attr( isset( $settings['dropbox_token'] ) ? $settings['dropbox_token'] : '' ); ?>" class="large-text" placeholder="sl.B... / Generated Access Token de Dropbox Developer Console" style="width:100%; margin:0;" />
									</div>
								</div>
							</div>

							<!-- Tarjeta: Almacenamiento en Microsoft OneDrive -->
							<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header" style="cursor: pointer; border-bottom: none;">
									<div class="wpat-module-info" style="width: 100%;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
											<h3 style="margin: 0; font-size: 15px;">Microsoft OneDrive Storage</h3>
											<div style="display: flex; align-items: center; gap: 10px;">
												<?php if ( ! empty( $settings['onedrive_token'] ) ) : ?>
													<span class="wpat-status-indicator" style="background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #0369a1; border-radius: 50%;"></span> Listo
													</span>
												<?php else : ?>
													<span class="wpat-status-indicator" style="background: #f1f5f9; color: #64748b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
														<span style="width: 6px; height: 6px; background: #64748b; border-radius: 50%;"></span> Sin configurar
													</span>
												<?php endif; ?>
												
												</span>
											</div>
										</div>
										<p style="margin: 0 0 4px 0; color: #64748b; font-size: 13px;">Envía los archivos recibidos durante el proceso de compra directamente a tu almacenamiento en la nube de Microsoft OneDrive.</p>
									</div>
								</div>
								<div class="wpat-module-body" style="display: none; padding: 15px 20px 20px 20px;">
									<div class="wpat-field-group">
										<label for="wpat_onedrive_token" style="display:block; font-weight:600; margin-bottom:5px;">Token de Acceso Microsoft Graph (OneDrive Access Token)</label>
										<input type="text" name="wpat_settings[onedrive_token]" id="wpat_onedrive_token" value="<?php echo esc_attr( isset( $settings['onedrive_token'] ) ? $settings['onedrive_token'] : '' ); ?>" class="large-text" placeholder="EwB... / Token OAuth Microsoft Graph API" style="width:100%; margin:0;" />
									</div>
								</div>
				<?php
				break;
			case 'whatsapp':
				?>
<div class="wpat-module-card" style="margin-top: 20px;">
								<div class="wpat-module-header">
									<div class="wpat-module-info">
										<h3>Botón Flotante de WhatsApp (Ultra-ligero)</h3>
										<p>Muestra un botón flotante directo a WhatsApp en tu web sin librerías pesadas, con soporte para mensaje personalizado y múltiples agentes.</p>
									</div>
									<?php $this->render_module_toggle( 'whatsapp', $settings, true ); ?>
								</div>
								<div class="wpat-module-body" style="display: block;">
									

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label for="wpat_whatsapp_phone">Número de Teléfono Principal (con prefijo de país)</label>
										<input type="text" name="wpat_settings[whatsapp_phone]" id="wpat_whatsapp_phone" value="<?php echo esc_attr( isset( $settings['whatsapp_phone'] ) ? $settings['whatsapp_phone'] : '' ); ?>" class="regular-text" placeholder="Ej: 34600000000" style="display:block; margin-top: 5px;" />
										<p class="description">Introduce el número internacional sin espacios ni signos +. Ejemplo para España: 34600000000.</p>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label for="wpat_whatsapp_message">Mensaje Predeterminado de Inicio</label>
										<input type="text" name="wpat_settings[whatsapp_message]" id="wpat_whatsapp_message" value="<?php echo esc_attr( isset( $settings['whatsapp_message'] ) ? $settings['whatsapp_message'] : '¡Hola! Quisiera más información.' ); ?>" class="large-text" style="display:block; margin-top: 5px;" />
										<p class="description">Texto inicial con el que el usuario empezará el chat.</p>
									</div>

									<div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px;">
										<div class="wpat-field-group" style="flex: 1; min-width: 220px;">
											<label for="wpat_whatsapp_position">Posición en la pantalla</label>
											<select name="wpat_settings[whatsapp_position]" id="wpat_whatsapp_position" style="display:block; margin-top: 5px; width: 100%;">
												<option value="bottom-right" <?php selected( isset( $settings['whatsapp_position'] ) ? $settings['whatsapp_position'] : 'bottom-right', 'bottom-right' ); ?>>Inferior Derecha</option>
												<option value="bottom-left" <?php selected( isset( $settings['whatsapp_position'] ) ? $settings['whatsapp_position'] : 'bottom-right', 'bottom-left' ); ?>>Inferior Izquierda</option>
											</select>
										</div>
										<div class="wpat-field-group" style="flex: 2; min-width: 260px;">
											<label for="wpat_whatsapp_tooltip">Globo de Saludo / Tooltip (Opcional)</label>
											<input type="text" name="wpat_settings[whatsapp_tooltip]" id="wpat_whatsapp_tooltip" value="<?php echo esc_attr( isset( $settings['whatsapp_tooltip'] ) ? $settings['whatsapp_tooltip'] : '' ); ?>" class="regular-text" placeholder="Ej. ¿Necesitas ayuda? ¡Escríbenos!" style="display:block; margin-top: 5px; width: 100%;" />
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label for="wpat_whatsapp_agents">Múltiples Agentes / Departamentos (Opcional)</label>
										<textarea name="wpat_settings[whatsapp_agents]" id="wpat_whatsapp_agents" rows="3" class="large-text" placeholder="Soporte | 34600000001 | Técnico&#10;Ventas | 34600000002 | Comercial" style="font-family: monospace; display:block; margin-top: 5px;"><?php echo esc_textarea( isset( $settings['whatsapp_agents'] ) ? $settings['whatsapp_agents'] : '' ); ?></textarea>
										<p class="description">Escribe un agente por línea en formato: <code>Nombre | Teléfono | Cargo/Departamento</code>. Si se define, al pulsar el icono de WhatsApp se desplegará una lista emergente para elegir agente.</p>
									</div>

									
								</div>
							</div>

							<div style="margin-top: 30px; border-top: 1px dashed var(--wpat-border); padding-top: 20px;">
								<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes" style="height: 36px; padding: 0 20px;" />
							</div>
						</div>
				<?php
				break;
			case 'initial-setup':
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Asistente de Configuración Inicial</h3>
							<p>Puesta a punto y limpieza rápida para nuevas instalaciones de WordPress. Selecciona las acciones que deseas realizar y ejecútalas en un solo paso.</p>
						</div>
						<?php $this->render_module_toggle( 'initial-setup', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">

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
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
									<input type="checkbox" name="wpat_init[delete_hello_dolly]" value="1" class="wpat-init-action-checkbox" />
									Eliminar plugin por defecto "Hello Dolly"
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
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
									<input type="checkbox" name="wpat_init[install_translatepress]" value="1" class="wpat-init-action-checkbox" />
									Instalar y activar plugin gratuito "TranslatePress"
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
				<?php
				break;

			case 'silent-skin':
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Ocultar Huella WPAT (Marca Blanca)</h3>
							<p>Oculta la presencia del plugin WP Agency Toolkit en el panel de administración para clientes, renombrando u ocultando menús y referencias de marca.</p>
						</div>
						<?php $this->render_module_toggle( 'silent-skin', $settings, false ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">
						<p style="margin: 0; color: #475569; font-size: 14px;">Este módulo opera de forma automática cuando se encuentra activado. Oculta la huella y marca del plugin WP Agency Toolkit en la interfaz de administración para mantener un entorno de marca blanca limpio para tus clientes.</p>
					</div>
				</div>
				<?php
				break;

			case 'conflict-detector':
				require_once WPAT_PATH . 'includes/modules/class-wpat-conflict-detector.php';
				$conflicts = WPAT_Conflict_Detector::get_active_conflicts();
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Detector de Incompatibilidades y Conflictos</h3>
							<p>Monitorea y detecta plugins duplicados o conflictivos que puedan causar interferencias con los módulos nativos de WP Agency Toolkit.</p>
						</div>
						<?php $this->render_module_toggle( 'conflict-detector', $settings, false ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">
						<?php if ( ! empty( $conflicts ) ) : ?>
							<div class="wpat-conflict-list" style="display: flex; flex-direction: column; gap: 15px;">
								<?php foreach ( $conflicts as $plugin_file => $conflict ) : ?>
									<div class="wpat-conflict-item" style="background: #fff1f2; border: 1px solid #fecdd3; border-left: 4px solid #e11d48; padding: 15px; border-radius: 6px;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<h4 style="margin: 0; font-size: 15px; color: #9f1239; font-weight: 600;"><?php echo esc_html( $conflict['name'] ); ?></h4>
											<span class="wpat-badge" style="background: <?php echo 'active' === $conflict['status'] ? '#e11d48' : '#f43f5e'; ?>; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase; font-weight: bold;">
												<?php echo 'active' === $conflict['status'] ? 'Plugin Activo' : 'Instalado'; ?>
											</span>
										</div>
										<p style="margin: 0 0 10px 0; color: #881337; font-size: 13px; line-height: 1.5;"><?php echo esc_html( $conflict['reason'] ); ?></p>
										<a href="<?php echo esc_url( $conflict['action_link'] ); ?>" class="button button-small" style="background: #be123c; border-color: #9f1239; color: #fff; text-shadow: none;">Gestionar Plugins</a>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a; padding: 15px; border-radius: 6px; display: flex; align-items: center; gap: 12px;">
								<span class="dashicons dashicons-yes-alt" style="font-size: 24px; width: 24px; height: 24px; color: #16a34a;"></span>
								<div>
									<h4 style="margin: 0 0 4px 0; font-size: 14px; color: #14532d; font-weight: 600;">Sin Incompatibilidades Detectadas</h4>
									<p style="margin: 0; color: #166534; font-size: 13px;">No se han detectado plugins conflictivos o duplicados en esta instalación de WordPress. Las funcionalidades nativas de WP Agency Toolkit funcionan de forma óptima.</p>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
				<?php
				break;

			case 'kits':
			case 'envato-importer':
				$this->render_tab_kits_content( $settings );
				break;
			case 'post-csv-importer':
				$this->render_tab_tools_content( $settings );
				break;
			case 'tools':
				$this->render_health_tab_content();
				break;
			default:
				echo '<div class="notice notice-info"><p>Módulo de configuración en preparación.</p></div>';
				break;
		}
	}

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

	/**
	 * Determina si un módulo es considerado NUEVO (duración de 30 días desde su lanzamiento).
	 *
	 * @param string $module_id ID del módulo.
	 * @return bool
	 */
	public function is_new_module( $module_id ) {
		$release_dates = array(
			'woo-checkout-designer' => '2026-09-10',
			'woo-sale-badges'       => '2026-09-11',
		);

		if ( ! isset( $release_dates[ $module_id ] ) ) {
			return false;
		}

		$release_time = strtotime( $release_dates[ $module_id ] );
		$days_elapsed = ( time() - $release_time ) / DAY_IN_SECONDS;

		return $days_elapsed <= 30;
	}

	/**
	 * AJAX: Importa un archivo CSV de autocompletado de direcciones.
	 */
	public function ajax_autofill_import_csv() {
		if ( ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce inválido).' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		if ( empty( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'Por favor, selecciona un archivo CSV para importar.' ) );
		}

		require_once WPAT_PATH . 'includes/modules/class-wpat-woo-address-autofill.php';
		$result = WPAT_Woo_Address_Autofill::import_csv_data( $_FILES['csv_file']['tmp_name'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => sprintf( '¡Importación completada con éxito! País: %s. Se han importado %d filas (%d provincias y %d poblaciones).', $result['country_code'], $result['total_rows'], $result['total_provinces'], $result['total_cities'] ),
		) );
	}

	/**
	 * AJAX: Exporta los datos a un archivo CSV.
	 */
	public function ajax_autofill_export_csv() {
		if ( ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$country_code = isset( $_GET['country'] ) ? sanitize_text_field( $_GET['country'] ) : 'ES';

		require_once WPAT_PATH . 'includes/modules/class-wpat-woo-address-autofill.php';
		$csv_content = WPAT_Woo_Address_Autofill::export_csv_data( $country_code );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="autofill_' . strtolower( $country_code ) . '.csv"' );
		echo "\xEF\xBB\xBF"; // UTF-8 BOM
		echo $csv_content;
		exit;
	}

	/**
	 * AJAX: Activa o desactiva un país.
	 */
	public function ajax_autofill_toggle_country() {
		if ( ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$country_code = isset( $_POST['country'] ) ? sanitize_text_field( $_POST['country'] ) : '';
		$status       = isset( $_POST['status'] ) && '1' === (string) $_POST['status'];

		require_once WPAT_PATH . 'includes/modules/class-wpat-woo-address-autofill.php';
		$success = WPAT_Woo_Address_Autofill::toggle_country( $country_code, $status );

		if ( $success ) {
			wp_send_json_success( array( 'message' => 'Estado del país actualizado.' ) );
		}

		wp_send_json_error( array( 'message' => 'No se pudo actualizar el estado del país.' ) );
	}

	/**
	 * AJAX: Elimina un país.
	 */
	public function ajax_autofill_delete_country() {
		if ( ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$country_code = isset( $_POST['country'] ) ? sanitize_text_field( $_POST['country'] ) : '';

		require_once WPAT_PATH . 'includes/modules/class-wpat-woo-address-autofill.php';
		$success = WPAT_Woo_Address_Autofill::delete_country( $country_code );

		if ( $success ) {
			wp_send_json_success( array( 'message' => 'País eliminado correctamente.' ) );
		}

		wp_send_json_error( array( 'message' => 'No se puede eliminar el país nativo o no fue encontrado.' ) );
	}

}

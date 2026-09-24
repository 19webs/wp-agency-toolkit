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

		$settings       = WPAT_Main::get_instance()->get_settings();
		$is_white_label = isset( $settings['silent-skin'] ) && '1' === (string) $settings['silent-skin'];

		// Ocultar de usuarios no autorizados si está activo
		if ( $is_white_label && isset( $settings['white_label_hide_from_non_admins'] ) && '1' === (string) $settings['white_label_hide_from_non_admins'] ) {
			if ( ! isset( $_GET['wpat_unlock'] ) || '1' !== $_GET['wpat_unlock'] ) {
				$current_user = wp_get_current_user();
				$allowed_raw  = isset( $settings['white_label_allowed_users'] ) ? $settings['white_label_allowed_users'] : '';
				$is_allowed   = false;

				if ( ! empty( $allowed_raw ) ) {
					$allowed_list = array_filter( array_map( 'trim', explode( ',', strtolower( $allowed_raw ) ) ) );
					$user_login   = strtolower( $current_user->user_login );
					$user_email   = strtolower( $current_user->user_email );
					if ( in_array( $user_login, $allowed_list, true ) || in_array( $user_email, $allowed_list, true ) ) {
						$is_allowed = true;
					}
				} else {
					if ( 1 === (int) $current_user->ID || is_super_admin() ) {
						$is_allowed = true;
					}
				}

				if ( ! $is_allowed ) {
					return;
				}
			}
		}

		$page_title = ( $is_white_label && ! empty( $settings['white_label_plugin_name'] ) ) ? $settings['white_label_plugin_name'] : 'WP Agency Toolkit';
		$menu_title = ( $is_white_label && ! empty( $settings['white_label_menu_title'] ) ) ? $settings['white_label_menu_title'] : 'Agency Toolkit';
		if ( $is_white_label && ! empty( $settings['white_label_menu_icon'] ) ) {
			$icon = $settings['white_label_menu_icon'];
		}

		add_menu_page(
			$page_title,
			$menu_title,
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
		wp_enqueue_script( 'wpat-qrcode-js', WPAT_URL . 'assets/js/qrcode.min.js', array(), WPAT_VERSION, true );
		wp_enqueue_script( 'wpat-admin-js', WPAT_URL . 'assets/js/wpat-admin.js', array( 'jquery', 'wp-color-picker', 'wpat-qrcode-js' ), time(), true );

		wp_localize_script( 'wpat-admin-js', 'wpat_object', array(
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'nonce'                => wp_create_nonce( 'wpat_save_settings_action' ),
			'cleanup_nonce'        => wp_create_nonce( 'wpat_cleanup_nonce_action' ),
			'error_log_nonce'      => wp_create_nonce( 'wpat_error_log_nonce_action' ),
			'role_manager_nonce'   => wp_create_nonce( 'wpat_role_manager_nonce_action' ),
			'cookie_consent_nonce' => wp_create_nonce( 'wpat_cookie_consent_nonce_action' ),
			'snippet_nonce'        => wp_create_nonce( 'wpat_snippet_nonce_action' ),
			'qr_nonce'             => wp_create_nonce( 'wpat_qr_nonce_action' ),
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
			'woo-email-designer',
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
			'woo-promotions',
			'woo_promotions',
			'post-csv-importer',
			'anti-spam',
			'silent-skin',
			'error-log-viewer',
			'role-manager',
			'cookie-consent',
			'quick-pay',
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
		if ( empty( $saving_module ) || in_array( $saving_module, array( 'login-customizer', 'dashboard_cleaner' ), true ) ) {
			$new_settings['login_style']             = isset( $input_settings['login_style'] ) && in_array( $input_settings['login_style'], array( 'default', 'modern' ), true ) ? $input_settings['login_style'] : 'default';
			$new_settings['login_logo']              = isset( $input_settings['login_logo'] ) ? esc_url_raw( $input_settings['login_logo'] ) : '';
			$new_settings['login_bg_image']          = isset( $input_settings['login_bg_image'] ) ? esc_url_raw( $input_settings['login_bg_image'] ) : '';
			$new_settings['login_bg_type']           = isset( $input_settings['login_bg_type'] ) && in_array( $input_settings['login_bg_type'], array( 'image', 'color' ), true ) ? $input_settings['login_bg_type'] : 'image';
			$new_settings['login_bg_color']          = isset( $input_settings['login_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['login_bg_color'] ) ? $input_settings['login_bg_color'] : '#f0f0f0';
			$new_settings['login_accent_color']      = isset( $input_settings['login_accent_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['login_accent_color'] ) ? $input_settings['login_accent_color'] : '#2563eb';
			$new_settings['login_hide_languages']    = isset( $input_settings['login_hide_languages'] ) && '1' === $input_settings['login_hide_languages'] ? '1' : '0';
			$new_settings['login_footer_text']       = isset( $input_settings['login_footer_text'] ) ? sanitize_text_field( $input_settings['login_footer_text'] ) : '';
			$new_settings['admin_footer_text']       = isset( $input_settings['admin_footer_text'] ) ? sanitize_text_field( $input_settings['admin_footer_text'] ) : '';
			$new_settings['dashboard_cleaner']       = isset( $input_settings['dashboard_cleaner'] ) && '1' === $input_settings['dashboard_cleaner'] ? '1' : '0';
			$new_settings['dashboard_welcome_title'] = isset( $input_settings['dashboard_welcome_title'] ) ? sanitize_text_field( $input_settings['dashboard_welcome_title'] ) : 'Soporte y Gestión';
			$new_settings['dashboard_welcome_text']  = isset( $input_settings['dashboard_welcome_text'] ) ? sanitize_textarea_field( $input_settings['dashboard_welcome_text'] ) : '';
			$new_settings['dashboard_support_email'] = isset( $input_settings['dashboard_support_email'] ) ? sanitize_email( $input_settings['dashboard_support_email'] ) : '';

			$dashboard_cards = array( 'seo', 'pages', 'posts', 'plugins', 'themes', 'users', 'db', 'tools', 'smtp', 'jet', 'woo', 'media', 'support' );
			foreach ( $dashboard_cards as $card_key ) {
				$opt_key = 'db_card_' . $card_key;
				$new_settings[ $opt_key ] = isset( $input_settings[ $opt_key ] ) && '1' === $input_settings[ $opt_key ] ? '1' : '0';
			}
		}

		// 1.1. Sanitizar Restringir Barra & Acceso Admin
		if ( empty( $saving_module ) || 'hide_admin_bar' === $saving_module ) {
			$new_settings['hide_admin_bar']                   = isset( $input_settings['hide_admin_bar'] ) && '1' === $input_settings['hide_admin_bar'] ? '1' : '0';
			$new_settings['admin_bar_hide_mode']              = isset( $input_settings['admin_bar_hide_mode'] ) && in_array( $input_settings['admin_bar_hide_mode'], array( 'all_except_admin', 'roles', 'all' ), true ) ? $input_settings['admin_bar_hide_mode'] : 'all_except_admin';
			$roles_raw                                        = isset( $input_settings['admin_bar_hidden_roles'] ) && is_array( $input_settings['admin_bar_hidden_roles'] ) ? $input_settings['admin_bar_hidden_roles'] : array( 'subscriber', 'customer' );
			$new_settings['admin_bar_hidden_roles']           = array_map( 'sanitize_key', $roles_raw );
			$new_settings['admin_access_restrict_enabled']    = isset( $input_settings['admin_access_restrict_enabled'] ) && '1' === $input_settings['admin_access_restrict_enabled'] ? '1' : '0';
			$restrict_roles_raw                               = isset( $input_settings['admin_access_restricted_roles'] ) && is_array( $input_settings['admin_access_restricted_roles'] ) ? $input_settings['admin_access_restricted_roles'] : array( 'subscriber', 'customer' );
			$new_settings['admin_access_restricted_roles']    = array_map( 'sanitize_key', $restrict_roles_raw );
			$new_settings['admin_access_redirect_to']         = isset( $input_settings['admin_access_redirect_to'] ) && in_array( $input_settings['admin_access_redirect_to'], array( 'home', 'woocommerce_myaccount', 'custom' ), true ) ? $input_settings['admin_access_redirect_to'] : 'home';
			$new_settings['admin_access_custom_redirect_url'] = isset( $input_settings['admin_access_custom_redirect_url'] ) ? esc_url_raw( $input_settings['admin_access_custom_redirect_url'] ) : '';
			$new_settings['admin_access_excluded_users']      = isset( $input_settings['admin_access_excluded_users'] ) ? sanitize_text_field( $input_settings['admin_access_excluded_users'] ) : '';
			$new_settings['admin_bar_remove_wp_logo']         = isset( $input_settings['admin_bar_remove_wp_logo'] ) && '1' === $input_settings['admin_bar_remove_wp_logo'] ? '1' : '0';
			$new_settings['admin_bar_remove_comments']        = isset( $input_settings['admin_bar_remove_comments'] ) && '1' === $input_settings['admin_bar_remove_comments'] ? '1' : '0';
			$new_settings['admin_bar_remove_new_content']     = isset( $input_settings['admin_bar_remove_new_content'] ) && '1' === $input_settings['admin_bar_remove_new_content'] ? '1' : '0';
			$new_settings['admin_bar_remove_updates']         = isset( $input_settings['admin_bar_remove_updates'] ) && '1' === $input_settings['admin_bar_remove_updates'] ? '1' : '0';
			$new_settings['admin_bar_remove_customize']       = isset( $input_settings['admin_bar_remove_customize'] ) && '1' === $input_settings['admin_bar_remove_customize'] ? '1' : '0';
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
			$new_settings['disable_comments_global']       = isset( $input_settings['disable_comments_global'] ) && '1' === $input_settings['disable_comments_global'] ? '1' : '0';
			$new_settings['disable_comments_posts']        = isset( $input_settings['disable_comments_posts'] ) && '1' === $input_settings['disable_comments_posts'] ? '1' : '0';
			$new_settings['disable_comments_pages']        = isset( $input_settings['disable_comments_pages'] ) && '1' === $input_settings['disable_comments_pages'] ? '1' : '0';
			$new_settings['disable_comments_media']        = isset( $input_settings['disable_comments_media'] ) && '1' === $input_settings['disable_comments_media'] ? '1' : '0';
			$new_settings['disable_comments_keep_reviews'] = isset( $input_settings['disable_comments_keep_reviews'] ) && '1' === $input_settings['disable_comments_keep_reviews'] ? '1' : '0';

			$cpts_raw = isset( $input_settings['disable_comments_cpts'] ) && is_array( $input_settings['disable_comments_cpts'] ) ? $input_settings['disable_comments_cpts'] : array();
			$new_settings['disable_comments_cpts'] = array_map( 'sanitize_key', $cpts_raw );
		}

		// 5. Sanitizar WooCommerce Catalog
		// Sanitizar Diseñador de Carrito y Checkout High-Conversion
		if ( empty( $saving_module ) || 'woo-checkout-designer' === $saving_module ) {
			$new_settings['woo-checkout-designer']                = isset( $input_settings['woo-checkout-designer'] ) && '1' === $input_settings['woo-checkout-designer'] ? '1' : '0';
			$new_settings['woo_checkout_designer_layout']         = isset( $input_settings['woo_checkout_designer_layout'] ) && in_array( $input_settings['woo_checkout_designer_layout'], array( 'wpat-classic', 'wpat-express', 'wpat-accordion', 'wpat-minimalist', 'wpat-shop-style' ), true ) ? $input_settings['woo_checkout_designer_layout'] : 'wpat-classic';
			$new_settings['woo_checkout_designer_mobile_summary'] = isset( $input_settings['woo_checkout_designer_mobile_summary'] ) && '1' === $input_settings['woo_checkout_designer_mobile_summary'] ? '1' : '0';
			$new_settings['woo_checkout_designer_trust_badges']   = isset( $input_settings['woo_checkout_designer_trust_badges'] ) && '1' === $input_settings['woo_checkout_designer_trust_badges'] ? '1' : '0';
			$new_settings['woo_checkout_designer_email_fix']      = isset( $input_settings['woo_checkout_designer_email_fix'] ) && '1' === $input_settings['woo_checkout_designer_email_fix'] ? '1' : '0';
			$new_settings['woo_checkout_designer_product_thumbs'] = isset( $input_settings['woo_checkout_designer_product_thumbs'] ) && '1' === $input_settings['woo_checkout_designer_product_thumbs'] ? '1' : '0';
			$new_settings['woo_checkout_btn_bg_color']               = isset( $input_settings['woo_checkout_btn_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_checkout_btn_bg_color'] ) ? $input_settings['woo_checkout_btn_bg_color'] : '#2563eb';
			$new_settings['woo_checkout_btn_hover_bg_color']         = isset( $input_settings['woo_checkout_btn_hover_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_checkout_btn_hover_bg_color'] ) ? $input_settings['woo_checkout_btn_hover_bg_color'] : '#1d4ed8';
			$new_settings['woo_checkout_btn_txt_color']              = isset( $input_settings['woo_checkout_btn_txt_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_checkout_btn_txt_color'] ) ? $input_settings['woo_checkout_btn_txt_color'] : '#ffffff';
			$new_settings['woo_checkout_step_accent_color']          = isset( $input_settings['woo_checkout_step_accent_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_checkout_step_accent_color'] ) ? $input_settings['woo_checkout_step_accent_color'] : '#2563eb';

			// Ajustes de Diseñador de Carrito
			$new_settings['woo_cart_designer_enabled']           = isset( $input_settings['woo_cart_designer_enabled'] ) && '1' === $input_settings['woo_cart_designer_enabled'] ? '1' : '0';
			$new_settings['woo_cart_designer_layout']            = isset( $input_settings['woo_cart_designer_layout'] ) && in_array( $input_settings['woo_cart_designer_layout'], array( 'wpat-cart-classic', 'wpat-cart-modern', 'wpat-cart-drawer' ), true ) ? $input_settings['woo_cart_designer_layout'] : 'wpat-cart-classic';
			$new_settings['woo_cart_free_shipping_bar']          = isset( $input_settings['woo_cart_free_shipping_bar'] ) && '1' === $input_settings['woo_cart_free_shipping_bar'] ? '1' : '0';
			$new_settings['woo_cart_free_shipping_min_amount']   = isset( $input_settings['woo_cart_free_shipping_min_amount'] ) ? max( 0, floatval( $input_settings['woo_cart_free_shipping_min_amount'] ) ) : 50;
			self::sync_wpat_min_amount_to_woocommerce( $new_settings['woo_cart_free_shipping_min_amount'] );
			$new_settings['woo_cart_drawer_auto_open']           = isset( $input_settings['woo_cart_drawer_auto_open'] ) && '1' === $input_settings['woo_cart_drawer_auto_open'] ? '1' : '0';
			$new_settings['woo_cart_show_shipping_calculator']  = isset( $input_settings['woo_cart_show_shipping_calculator'] ) && '1' === $input_settings['woo_cart_show_shipping_calculator'] ? '1' : '0';
		}

		// Sanitizar Diseñador de Plantillas de Email
		if ( empty( $saving_module ) || 'woo-email-designer' === $saving_module ) {
			$new_settings['woo-email-designer']         = isset( $input_settings['woo-email-designer'] ) && '1' === $input_settings['woo-email-designer'] ? '1' : '0';
			$new_settings['woo_email_template_style'] = isset( $input_settings['woo_email_template_style'] ) && in_array( $input_settings['woo_email_template_style'], array( 'classic', 'modern', 'minimalist' ), true ) ? $input_settings['woo_email_template_style'] : 'modern';
			$new_settings['woo_email_logo_url']       = isset( $input_settings['woo_email_logo_url'] ) ? esc_url_raw( $input_settings['woo_email_logo_url'] ) : '';
			$new_settings['woo_email_logo_width']     = isset( $input_settings['woo_email_logo_width'] ) ? max( 30, min( 500, absint( $input_settings['woo_email_logo_width'] ) ) ) : 150;
			$new_settings['woo_email_primary_color']  = isset( $input_settings['woo_email_primary_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_email_primary_color'] ) ? $input_settings['woo_email_primary_color'] : '#2563eb';
			$new_settings['woo_email_body_bg']        = isset( $input_settings['woo_email_body_bg'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_email_body_bg'] ) ? $input_settings['woo_email_body_bg'] : '#f8fafc';
			$new_settings['woo_email_card_bg']        = isset( $input_settings['woo_email_card_bg'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_email_card_bg'] ) ? $input_settings['woo_email_card_bg'] : '#ffffff';
			$new_settings['woo_email_text_color']     = isset( $input_settings['woo_email_text_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_email_text_color'] ) ? $input_settings['woo_email_text_color'] : '#1e293b';
			$new_settings['woo_email_welcome_msg']    = isset( $input_settings['woo_email_welcome_msg'] ) ? sanitize_textarea_field( $input_settings['woo_email_welcome_msg'] ) : '';
			$new_settings['woo_email_body_intro']     = isset( $input_settings['woo_email_body_intro'] ) ? sanitize_textarea_field( $input_settings['woo_email_body_intro'] ) : '';
			$new_settings['woo_email_promo_text']     = isset( $input_settings['woo_email_promo_text'] ) ? sanitize_textarea_field( $input_settings['woo_email_promo_text'] ) : '';
			$new_settings['woo_email_footer_text']    = isset( $input_settings['woo_email_footer_text'] ) ? sanitize_textarea_field( $input_settings['woo_email_footer_text'] ) : '';
			$new_settings['woo_email_social_fb']      = isset( $input_settings['woo_email_social_fb'] ) ? esc_url_raw( $input_settings['woo_email_social_fb'] ) : '';
			$new_settings['woo_email_social_ig']      = isset( $input_settings['woo_email_social_ig'] ) ? esc_url_raw( $input_settings['woo_email_social_ig'] ) : '';
			$new_settings['woo_email_social_tw']      = isset( $input_settings['woo_email_social_tw'] ) ? esc_url_raw( $input_settings['woo_email_social_tw'] ) : '';
			$new_settings['woo_email_social_web']     = isset( $input_settings['woo_email_social_web'] ) ? esc_url_raw( $input_settings['woo_email_social_web'] ) : '';
			$new_settings['woo_email_selected_type']  = isset( $input_settings['woo_email_selected_type'] ) ? sanitize_key( $input_settings['woo_email_selected_type'] ) : 'customer_processing_order';
			$new_settings['woo_email_test_recipient'] = isset( $input_settings['woo_email_test_recipient'] ) ? sanitize_email( $input_settings['woo_email_test_recipient'] ) : '';

			if ( isset( $input_settings['woo_email_messages'] ) && is_array( $input_settings['woo_email_messages'] ) ) {
				$clean_msgs = array();
				foreach ( $input_settings['woo_email_messages'] as $t_key => $fields ) {
					$clean_t_key = sanitize_key( $t_key );
					if ( is_array( $fields ) ) {
						$clean_msgs[ $clean_t_key ] = array(
							'welcome' => isset( $fields['welcome'] ) ? sanitize_textarea_field( $fields['welcome'] ) : '',
							'intro'   => isset( $fields['intro'] ) ? sanitize_textarea_field( $fields['intro'] ) : '',
							'promo'   => isset( $fields['promo'] ) ? sanitize_textarea_field( $fields['promo'] ) : '',
							'footer'  => isset( $fields['footer'] ) ? sanitize_textarea_field( $fields['footer'] ) : '',
						);
					}
				}
				$new_settings['woo_email_messages'] = $clean_msgs;
			}
		}

		// Sanitizar Filtro por Facetas
		if ( empty( $saving_module ) || 'woo-facets' === $saving_module ) {
			$new_settings['woo-facets']                  = isset( $input_settings['woo-facets'] ) && '1' === $input_settings['woo-facets'] ? '1' : '0';
			$new_settings['facets_config']               = isset( $input_settings['facets_config'] ) && is_array( $input_settings['facets_config'] ) ? array_map( 'sanitize_text_field', $input_settings['facets_config'] ) : array( 'sort', 'price', 'category', 'stock', 'rating' );
			$new_settings['woo_facets_accent_color']    = isset( $input_settings['woo_facets_accent_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_facets_accent_color'] ) ? $input_settings['woo_facets_accent_color'] : '#2563eb';
			$new_settings['woo_facets_card_bg']          = isset( $input_settings['woo_facets_card_bg'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_facets_card_bg'] ) ? $input_settings['woo_facets_card_bg'] : '#ffffff';
			$new_settings['woo_facets_border_color']     = isset( $input_settings['woo_facets_border_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_facets_border_color'] ) ? $input_settings['woo_facets_border_color'] : '#e2e8f0';
			$new_settings['woo_facets_badge_bg']         = isset( $input_settings['woo_facets_badge_bg'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_facets_badge_bg'] ) ? $input_settings['woo_facets_badge_bg'] : '#f1f5f9';
			$new_settings['woo_facets_border_radius']    = isset( $input_settings['woo_facets_border_radius'] ) ? max( 0, min( 30, absint( $input_settings['woo_facets_border_radius'] ) ) ) : 12;
			$new_settings['woo_facets_show_active_tags'] = isset( $input_settings['woo_facets_show_active_tags'] ) && '1' === $input_settings['woo_facets_show_active_tags'] ? '1' : '0';
			$new_settings['woo_facets_sticky']           = isset( $input_settings['woo_facets_sticky'] ) && '1' === $input_settings['woo_facets_sticky'] ? '1' : '0';
		}

		// Sanitizar Autocompletado de CP y Provincia (WooCommerce)
		if ( empty( $saving_module ) || 'woo-address-autofill' === $saving_module ) {
			$new_settings['woo-address-autofill']     = isset( $input_settings['woo-address-autofill'] ) && '1' === $input_settings['woo-address-autofill'] ? '1' : '0';
			$new_settings['woo_address_autofill_city'] = isset( $input_settings['woo_address_autofill_city'] ) && '1' === $input_settings['woo_address_autofill_city'] ? '1' : '0';
		}

		if ( empty( $saving_module ) || 'woo-catalog' === $saving_module ) {
			$new_settings['woo-catalog']             = isset( $input_settings['woo-catalog'] ) && '1' === $input_settings['woo-catalog'] ? '1' : '0';
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
			$new_settings['woo-zoom']                  = isset( $input_settings['woo-zoom'] ) && '1' === $input_settings['woo-zoom'] ? '1' : '0';
			$new_settings['woo_zoom_disable_zoom']     = isset( $input_settings['woo_zoom_disable_zoom'] ) && '1' === $input_settings['woo_zoom_disable_zoom'] ? '1' : '0';
			$new_settings['woo_zoom_disable_lightbox'] = isset( $input_settings['woo_zoom_disable_lightbox'] ) && '1' === $input_settings['woo_zoom_disable_lightbox'] ? '1' : '0';
			$new_settings['woo_zoom_disable_slider']   = isset( $input_settings['woo_zoom_disable_slider'] ) && '1' === $input_settings['woo_zoom_disable_slider'] ? '1' : '0';
		}

		// Sanitizar Clonador / Duplicador de Entradas y Páginas
		if ( empty( $saving_module ) || 'duplicator' === $saving_module ) {
			$new_settings['duplicator']                 = isset( $input_settings['duplicator'] ) && '1' === $input_settings['duplicator'] ? '1' : '0';
			$new_settings['duplicator_title_suffix']    = isset( $input_settings['duplicator_title_suffix'] ) ? sanitize_text_field( $input_settings['duplicator_title_suffix'] ) : ' (Copia)';
			$new_settings['duplicator_post_status']     = isset( $input_settings['duplicator_post_status'] ) && in_array( $input_settings['duplicator_post_status'], array( 'draft', 'pending', 'publish', 'private' ), true ) ? $input_settings['duplicator_post_status'] : 'draft';
			$new_settings['duplicator_redirect_to']     = isset( $input_settings['duplicator_redirect_to'] ) && in_array( $input_settings['duplicator_redirect_to'], array( 'edit', 'list' ), true ) ? $input_settings['duplicator_redirect_to'] : 'edit';
			$new_settings['duplicator_show_admin_bar']  = isset( $input_settings['duplicator_show_admin_bar'] ) && '1' === $input_settings['duplicator_show_admin_bar'] ? '1' : '0';
			$new_settings['duplicator_copy_taxonomies'] = isset( $input_settings['duplicator_copy_taxonomies'] ) && '1' === $input_settings['duplicator_copy_taxonomies'] ? '1' : '0';
			$new_settings['duplicator_copy_meta']       = isset( $input_settings['duplicator_copy_meta'] ) && '1' === $input_settings['duplicator_copy_meta'] ? '1' : '0';
			$new_settings['duplicator_copy_author']     = isset( $input_settings['duplicator_copy_author'] ) && in_array( $input_settings['duplicator_copy_author'], array( 'current', 'original' ), true ) ? $input_settings['duplicator_copy_author'] : 'current';
			$new_settings['duplicator_copy_date']       = isset( $input_settings['duplicator_copy_date'] ) && in_array( $input_settings['duplicator_copy_date'], array( 'current', 'original' ), true ) ? $input_settings['duplicator_copy_date'] : 'current';

			$pts_raw = isset( $input_settings['duplicator_post_types'] ) && is_array( $input_settings['duplicator_post_types'] ) ? $input_settings['duplicator_post_types'] : array( 'post', 'page', 'product' );
			$new_settings['duplicator_post_types'] = array_map( 'sanitize_key', $pts_raw );
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
			$new_settings['sec_security_headers']     = isset( $input_settings['sec_security_headers'] ) && '1' === $input_settings['sec_security_headers'] ? '1' : '0';
		}

		// Sanitizar redirección y opciones SSL
		if ( empty( $saving_module ) || 'ssl-fixer' === $saving_module ) {
			$new_settings['ssl_redirect_method']   = isset( $input_settings['ssl_redirect_method'] ) && in_array( $input_settings['ssl_redirect_method'], array( 'php', 'htaccess', 'none' ), true ) ? $input_settings['ssl_redirect_method'] : 'php';
			$new_settings['ssl_fix_mixed_content'] = isset( $input_settings['ssl_fix_mixed_content'] ) && '1' === $input_settings['ssl_fix_mixed_content'] ? '1' : '0';
			$new_settings['ssl_enable_hsts']       = isset( $input_settings['ssl_enable_hsts'] ) && '1' === $input_settings['ssl_enable_hsts'] ? '1' : '0';
			$new_settings['ssl_enable_csp']        = isset( $input_settings['ssl_enable_csp'] ) && '1' === $input_settings['ssl_enable_csp'] ? '1' : '0';
			$new_settings['ssl_proxy_fix']         = isset( $input_settings['ssl_proxy_fix'] ) && '1' === $input_settings['ssl_proxy_fix'] ? '1' : '0';
		}

		// Sanitizar Optimización de Rendimiento
		if ( empty( $saving_module ) || 'performance' === $saving_module ) {
			$new_settings['perf_disable_emojis']            = isset( $input_settings['perf_disable_emojis'] ) && '1' === $input_settings['perf_disable_emojis'] ? '1' : '0';
			$new_settings['perf_cleanup_head']              = isset( $input_settings['perf_cleanup_head'] ) && '1' === $input_settings['perf_cleanup_head'] ? '1' : '0';
			$new_settings['perf_heartbeat_control']         = isset( $input_settings['perf_heartbeat_control'] ) && in_array( $input_settings['perf_heartbeat_control'], array( 'default', 'slow', 'disable_frontend', 'disable_all' ), true ) ? $input_settings['perf_heartbeat_control'] : 'slow';
			$new_settings['perf_disable_jquery_migrate']    = isset( $input_settings['perf_disable_jquery_migrate'] ) && '1' === $input_settings['perf_disable_jquery_migrate'] ? '1' : '0';
			$new_settings['perf_disable_wc_cart_fragments'] = isset( $input_settings['perf_disable_wc_cart_fragments'] ) && '1' === $input_settings['perf_disable_wc_cart_fragments'] ? '1' : '0';
			$new_settings['perf_limit_revisions']           = isset( $input_settings['perf_limit_revisions'] ) && in_array( $input_settings['perf_limit_revisions'], array( '0', '3', '5', '10', 'unlimited' ), true ) ? $input_settings['perf_limit_revisions'] : '5';
			$new_settings['perf_autosave_interval']         = isset( $input_settings['perf_autosave_interval'] ) && in_array( $input_settings['perf_autosave_interval'], array( '60', '180', '300' ), true ) ? $input_settings['perf_autosave_interval'] : '180';
			$new_settings['perf_disable_dashicons']         = isset( $input_settings['perf_disable_dashicons'] ) && '1' === $input_settings['perf_disable_dashicons'] ? '1' : '0';
			$new_settings['perf_disable_embeds']            = isset( $input_settings['perf_disable_embeds'] ) && '1' === $input_settings['perf_disable_embeds'] ? '1' : '0';
		}

		// Sanitizar Soporte de Archivos SVG
		if ( empty( $saving_module ) || 'svg-support' === $saving_module ) {
			$new_settings['svg_admin_only']          = isset( $input_settings['svg_admin_only'] ) && '1' === $input_settings['svg_admin_only'] ? '1' : '0';
			$new_settings['svg_sanitize_strict']     = isset( $input_settings['svg_sanitize_strict'] ) && '1' === $input_settings['svg_sanitize_strict'] ? '1' : '0';
			$new_settings['svg_generate_dimensions'] = isset( $input_settings['svg_generate_dimensions'] ) && '1' === $input_settings['svg_generate_dimensions'] ? '1' : '0';
		}

		// Sanitizar Generador de Sitemap XML
		if ( empty( $saving_module ) || 'sitemap-xml' === $saving_module ) {
			$new_settings['sitemap_include_posts']      = isset( $input_settings['sitemap_include_posts'] ) && '1' === $input_settings['sitemap_include_posts'] ? '1' : '0';
			$new_settings['sitemap_include_pages']      = isset( $input_settings['sitemap_include_pages'] ) && '1' === $input_settings['sitemap_include_pages'] ? '1' : '0';
			$new_settings['sitemap_include_products']   = isset( $input_settings['sitemap_include_products'] ) && '1' === $input_settings['sitemap_include_products'] ? '1' : '0';
			$new_settings['sitemap_include_taxonomies'] = isset( $input_settings['sitemap_include_taxonomies'] ) && '1' === $input_settings['sitemap_include_taxonomies'] ? '1' : '0';
			$new_settings['sitemap_include_images']     = isset( $input_settings['sitemap_include_images'] ) && '1' === $input_settings['sitemap_include_images'] ? '1' : '0';
			$new_settings['sitemap_exclude_urls']       = isset( $input_settings['sitemap_exclude_urls'] ) ? sanitize_textarea_field( $input_settings['sitemap_exclude_urls'] ) : '';
		}

		// Sanitizar Optimización de Medios & WebP
		if ( empty( $saving_module ) || 'image-optimizer' === $saving_module ) {
			$new_settings['image-optimizer']               = isset( $input_settings['image-optimizer'] ) && '1' === $input_settings['image-optimizer'] ? '1' : '0';
			$new_settings['image_optimizer_quality']       = isset( $input_settings['image_optimizer_quality'] ) ? max( 40, min( 100, intval( $input_settings['image_optimizer_quality'] ) ) ) : 82;
			$new_settings['image_optimizer_max_width']     = isset( $input_settings['image_optimizer_max_width'] ) ? max( 0, intval( $input_settings['image_optimizer_max_width'] ) ) : 1920;
			$new_settings['image_optimizer_max_height']    = isset( $input_settings['image_optimizer_max_height'] ) ? max( 0, intval( $input_settings['image_optimizer_max_height'] ) ) : 1920;
			$new_settings['image_optimizer_auto_convert']   = isset( $input_settings['image_optimizer_auto_convert'] ) && '1' === $input_settings['image_optimizer_auto_convert'] ? '1' : '0';
			$new_settings['image_optimizer_keep_original']  = isset( $input_settings['image_optimizer_keep_original'] ) && '1' === $input_settings['image_optimizer_keep_original'] ? '1' : '0';
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
			$new_settings['smtp_force_from']  = isset( $input_settings['smtp_force_from'] ) && '1' === $input_settings['smtp_force_from'] ? '1' : '0';
			$new_settings['smtp_reply_to']    = isset( $input_settings['smtp_reply_to'] ) ? sanitize_email( $input_settings['smtp_reply_to'] ) : '';
			$new_settings['smtp_log_enabled'] = isset( $input_settings['smtp_log_enabled'] ) && '1' === $input_settings['smtp_log_enabled'] ? '1' : '0';
		}

		// Sanitizar Ocultar Huella WPAT / Marca Blanca
		if ( empty( $saving_module ) || 'silent-skin' === $saving_module ) {
			$new_settings['silent-skin']                      = isset( $input_settings['silent-skin'] ) && '1' === $input_settings['silent-skin'] ? '1' : '0';
			$new_settings['white_label_mode']                 = isset( $input_settings['white_label_mode'] ) && in_array( $input_settings['white_label_mode'], array( 'rename', 'hide' ), true ) ? $input_settings['white_label_mode'] : 'rename';
			$new_settings['white_label_plugin_name']          = isset( $input_settings['white_label_plugin_name'] ) ? sanitize_text_field( $input_settings['white_label_plugin_name'] ) : 'Herramientas del Sitio Web';
			$new_settings['white_label_plugin_desc']          = isset( $input_settings['white_label_plugin_desc'] ) ? sanitize_textarea_field( $input_settings['white_label_plugin_desc'] ) : '';
			$new_settings['white_label_author']               = isset( $input_settings['white_label_author'] ) ? sanitize_text_field( $input_settings['white_label_author'] ) : '';
			$new_settings['white_label_author_url']           = isset( $input_settings['white_label_author_url'] ) ? esc_url_raw( $input_settings['white_label_author_url'] ) : '';
			$new_settings['white_label_menu_title']           = isset( $input_settings['white_label_menu_title'] ) ? sanitize_text_field( $input_settings['white_label_menu_title'] ) : '';
			$new_settings['white_label_menu_icon']            = isset( $input_settings['white_label_menu_icon'] ) ? sanitize_text_field( $input_settings['white_label_menu_icon'] ) : 'dashicons-admin-generic';
			$new_settings['white_label_hide_from_non_admins'] = isset( $input_settings['white_label_hide_from_non_admins'] ) && '1' === $input_settings['white_label_hide_from_non_admins'] ? '1' : '0';
			$new_settings['white_label_allowed_users']        = isset( $input_settings['white_label_allowed_users'] ) ? sanitize_text_field( $input_settings['white_label_allowed_users'] ) : '';
			$new_settings['white_label_prevent_deactivation'] = isset( $input_settings['white_label_prevent_deactivation'] ) && '1' === $input_settings['white_label_prevent_deactivation'] ? '1' : '0';
		}

		// 9. Sanitizar Integraciones & Scripts
		if ( empty( $saving_module ) || 'integrations' === $saving_module ) {
			$new_settings['integrations']                = isset( $input_settings['integrations'] ) && '1' === $input_settings['integrations'] ? '1' : '0';
			$new_settings['integrations_exclude_admins'] = isset( $input_settings['integrations_exclude_admins'] ) && '1' === $input_settings['integrations_exclude_admins'] ? '1' : '0';

			// Google Search Console
			$gsc_raw = isset( $input_settings['google_search_console_code'] ) ? trim( $input_settings['google_search_console_code'] ) : '';
			if ( ! empty( $gsc_raw ) ) {
				if ( preg_match( '/content=["\']([^"\']+)["\']/i', $gsc_raw, $matches ) ) {
					$gsc_raw = $matches[1];
				}
				$new_settings['google_search_console_code'] = sanitize_text_field( $gsc_raw );
			} else if ( isset( $input_settings['google_search_console_code'] ) ) {
				$new_settings['google_search_console_code'] = '';
			}

			// Bing Webmaster
			$bing_raw = isset( $input_settings['bing_verification_code'] ) ? trim( $input_settings['bing_verification_code'] ) : '';
			if ( ! empty( $bing_raw ) ) {
				if ( preg_match( '/content=["\']([^"\']+)["\']/i', $bing_raw, $matches ) ) {
					$bing_raw = $matches[1];
				}
				$new_settings['bing_verification_code'] = sanitize_text_field( $bing_raw );
			} else if ( isset( $input_settings['bing_verification_code'] ) ) {
				$new_settings['bing_verification_code'] = '';
			}

			// Pinterest Verification
			$pin_raw = isset( $input_settings['pinterest_verification_code'] ) ? trim( $input_settings['pinterest_verification_code'] ) : '';
			if ( ! empty( $pin_raw ) ) {
				if ( preg_match( '/content=["\']([^"\']+)["\']/i', $pin_raw, $matches ) ) {
					$pin_raw = $matches[1];
				}
				$new_settings['pinterest_verification_code'] = sanitize_text_field( $pin_raw );
			} else if ( isset( $input_settings['pinterest_verification_code'] ) ) {
				$new_settings['pinterest_verification_code'] = '';
			}

			if ( isset( $input_settings['google_analytics_id'] ) ) $new_settings['google_analytics_id'] = sanitize_text_field( $input_settings['google_analytics_id'] );
			if ( isset( $input_settings['gtm_container_id'] ) )    $new_settings['gtm_container_id']    = sanitize_text_field( $input_settings['gtm_container_id'] );
			if ( isset( $input_settings['facebook_pixel_id'] ) )  $new_settings['facebook_pixel_id']  = sanitize_text_field( $input_settings['facebook_pixel_id'] );
			if ( isset( $input_settings['clarity_project_id'] ) ) $new_settings['clarity_project_id'] = sanitize_text_field( $input_settings['clarity_project_id'] );
			if ( isset( $input_settings['tiktok_pixel_id'] ) )    $new_settings['tiktok_pixel_id']    = sanitize_text_field( $input_settings['tiktok_pixel_id'] );
			if ( isset( $input_settings['pinterest_tag_id'] ) )   $new_settings['pinterest_tag_id']   = sanitize_text_field( $input_settings['pinterest_tag_id'] );

			// Scripts personalizados (Head, Body, Footer)
			if ( isset( $input_settings['header_custom_scripts'] ) ) {
				$new_settings['header_custom_scripts'] = current_user_can( 'unfiltered_html' ) ? wp_unslash( $input_settings['header_custom_scripts'] ) : wp_kses_post( wp_unslash( $input_settings['header_custom_scripts'] ) );
			}
			if ( isset( $input_settings['body_custom_scripts'] ) ) {
				$new_settings['body_custom_scripts'] = current_user_can( 'unfiltered_html' ) ? wp_unslash( $input_settings['body_custom_scripts'] ) : wp_kses_post( wp_unslash( $input_settings['body_custom_scripts'] ) );
			}
			if ( isset( $input_settings['footer_custom_scripts'] ) ) {
				$new_settings['footer_custom_scripts'] = current_user_can( 'unfiltered_html' ) ? wp_unslash( $input_settings['footer_custom_scripts'] ) : wp_kses_post( wp_unslash( $input_settings['footer_custom_scripts'] ) );
			}

			// Tokens Cloud Storage
			if ( isset( $input_settings['google_drive_token'] ) )  $new_settings['google_drive_token']  = sanitize_text_field( $input_settings['google_drive_token'] );
			if ( isset( $input_settings['google_drive_folder'] ) ) $new_settings['google_drive_folder'] = sanitize_text_field( $input_settings['google_drive_folder'] );
			if ( isset( $input_settings['dropbox_token'] ) )       $new_settings['dropbox_token']       = sanitize_text_field( $input_settings['dropbox_token'] );
			if ( isset( $input_settings['onedrive_token'] ) )      $new_settings['onedrive_token']      = sanitize_text_field( $input_settings['onedrive_token'] );
		}

		// 10. Sanitizar WhatsApp
		if ( empty( $saving_module ) || 'whatsapp' === $saving_module ) {
			$new_settings['whatsapp']                  = isset( $input_settings['whatsapp'] ) && '1' === $input_settings['whatsapp'] ? '1' : '0';
			$new_settings['whatsapp_enabled']          = $new_settings['whatsapp'];
			$new_settings['whatsapp_phone']            = isset( $input_settings['whatsapp_phone'] ) ? sanitize_text_field( $input_settings['whatsapp_phone'] ) : '';
			$new_settings['whatsapp_message']          = isset( $input_settings['whatsapp_message'] ) ? sanitize_text_field( $input_settings['whatsapp_message'] ) : '¡Hola! Quisiera más información sobre {title}.';
			$new_settings['whatsapp_position']         = isset( $input_settings['whatsapp_position'] ) && in_array( $input_settings['whatsapp_position'], array( 'bottom-right', 'bottom-left' ), true ) ? $input_settings['whatsapp_position'] : 'bottom-right';
			$new_settings['whatsapp_offset_x']         = isset( $input_settings['whatsapp_offset_x'] ) ? absint( $input_settings['whatsapp_offset_x'] ) : 20;
			$new_settings['whatsapp_offset_y']         = isset( $input_settings['whatsapp_offset_y'] ) ? absint( $input_settings['whatsapp_offset_y'] ) : 20;
			$new_settings['whatsapp_bg_color']         = isset( $input_settings['whatsapp_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['whatsapp_bg_color'] ) ? $input_settings['whatsapp_bg_color'] : '#25D366';
			$new_settings['whatsapp_tooltip']          = isset( $input_settings['whatsapp_tooltip'] ) ? sanitize_text_field( $input_settings['whatsapp_tooltip'] ) : '';
			$new_settings['whatsapp_agents']           = isset( $input_settings['whatsapp_agents'] ) ? sanitize_textarea_field( $input_settings['whatsapp_agents'] ) : '';
			$new_settings['whatsapp_devices']          = isset( $input_settings['whatsapp_devices'] ) && in_array( $input_settings['whatsapp_devices'], array( 'all', 'mobile', 'desktop' ), true ) ? $input_settings['whatsapp_devices'] : 'all';
			$new_settings['whatsapp_pulse']            = isset( $input_settings['whatsapp_pulse'] ) && '1' === $input_settings['whatsapp_pulse'] ? '1' : '0';
			$new_settings['whatsapp_track_events']     = isset( $input_settings['whatsapp_track_events'] ) && '1' === $input_settings['whatsapp_track_events'] ? '1' : '0';
			$new_settings['whatsapp_popup_title']      = isset( $input_settings['whatsapp_popup_title'] ) ? sanitize_text_field( $input_settings['whatsapp_popup_title'] ) : 'Contacta con nuestro equipo';
			$new_settings['whatsapp_popup_subtitle']   = isset( $input_settings['whatsapp_popup_subtitle'] ) ? sanitize_text_field( $input_settings['whatsapp_popup_subtitle'] ) : 'Selecciona un asesor para iniciar el chat';
			$new_settings['whatsapp_work_hours']       = isset( $input_settings['whatsapp_work_hours'] ) ? sanitize_text_field( $input_settings['whatsapp_work_hours'] ) : '';
			$new_settings['whatsapp_hide_on_checkout'] = isset( $input_settings['whatsapp_hide_on_checkout'] ) && '1' === $input_settings['whatsapp_hide_on_checkout'] ? '1' : '0';
		}

		// 11. Sanitizar Barra y Tiempo de Lectura
		if ( empty( $saving_module ) || 'reading-progress' === $saving_module ) {
			$new_settings['reading-progress']       = isset( $input_settings['reading-progress'] ) && '1' === $input_settings['reading-progress'] ? '1' : '0';
			$new_settings['reading_bar_enabled']    = isset( $input_settings['reading_bar_enabled'] ) && '1' === $input_settings['reading_bar_enabled'] ? '1' : '0';
			$new_settings['reading_bar_color']      = isset( $input_settings['reading_bar_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['reading_bar_color'] ) ? $input_settings['reading_bar_color'] : '#2563eb';
			$new_settings['reading_bar_color_end']  = isset( $input_settings['reading_bar_color_end'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['reading_bar_color_end'] ) ? $input_settings['reading_bar_color_end'] : '';
			$new_settings['reading_bar_height']     = isset( $input_settings['reading_bar_height'] ) ? max( 1, min( 30, absint( $input_settings['reading_bar_height'] ) ) ) : 4;
			$new_settings['reading_bar_position']   = isset( $input_settings['reading_bar_position'] ) && 'bottom' === $input_settings['reading_bar_position'] ? 'bottom' : 'top';
			$new_settings['reading_bar_scope']      = isset( $input_settings['reading_bar_scope'] ) && 'window' === $input_settings['reading_bar_scope'] ? 'window' : 'article';
			$new_settings['reading_time_enabled']   = isset( $input_settings['reading_time_enabled'] ) && '1' === $input_settings['reading_time_enabled'] ? '1' : '0';
			$new_settings['reading_time_wpm']       = isset( $input_settings['reading_time_wpm'] ) ? max( 100, min( 500, absint( $input_settings['reading_time_wpm'] ) ) ) : 200;
			$new_settings['reading_time_style']     = isset( $input_settings['reading_time_style'] ) && in_array( $input_settings['reading_time_style'], array( 'pill', 'minimal', 'bordered' ), true ) ? $input_settings['reading_time_style'] : 'pill';
			$new_settings['reading_time_label']     = isset( $input_settings['reading_time_label'] ) ? sanitize_text_field( $input_settings['reading_time_label'] ) : 'Tiempo estimado de lectura: {time} min';

			$pts_raw = isset( $input_settings['reading_bar_post_types'] ) && is_array( $input_settings['reading_bar_post_types'] ) ? $input_settings['reading_bar_post_types'] : array( 'post' );
			$new_settings['reading_bar_post_types'] = array_map( 'sanitize_key', $pts_raw );
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
			$new_settings['accessibility_reading_guide']     = isset( $input_settings['accessibility_reading_guide'] ) && '1' === $input_settings['accessibility_reading_guide'] ? '1' : '0';
			$new_settings['accessibility_big_cursor']        = isset( $input_settings['accessibility_big_cursor'] ) && '1' === $input_settings['accessibility_big_cursor'] ? '1' : '0';
			$new_settings['accessibility_stop_animations']   = isset( $input_settings['accessibility_stop_animations'] ) && '1' === $input_settings['accessibility_stop_animations'] ? '1' : '0';
			$new_settings['accessibility_text_spacing']      = isset( $input_settings['accessibility_text_spacing'] ) && '1' === $input_settings['accessibility_text_spacing'] ? '1' : '0';
			$new_settings['accessibility_dyslexic_font']     = isset( $input_settings['accessibility_dyslexic_font'] ) && '1' === $input_settings['accessibility_dyslexic_font'] ? '1' : '0';
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
							'position'    => isset( $cf['position'] ) && in_array( $cf['position'], array( 'after_email', 'after_names', 'after_country_state', 'after_city_postcode', 'after_address', 'after_phone', 'after_company', 'end_of_section' ), true ) ? $cf['position'] : 'end_of_section',
							'priority'    => isset( $cf['priority'] ) ? intval( $cf['priority'] ) : 100,
							'width'       => isset( $cf['width'] ) && in_array( $cf['width'], array( 'full', 'half' ), true ) ? $cf['width'] : 'full',
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
			$new_settings['woo_sale_badge_text_type']   = isset( $input_settings['woo_sale_badge_text_type'] ) && in_array( $input_settings['woo_sale_badge_text_type'], array( 'custom', 'percentage', 'amount' ), true ) ? $input_settings['woo_sale_badge_text_type'] : 'custom';
			$new_settings['woo_sale_badge_custom_text'] = isset( $input_settings['woo_sale_badge_custom_text'] ) ? sanitize_text_field( $input_settings['woo_sale_badge_custom_text'] ) : '¡OFERTA!';
			$new_settings['woo_sale_badge_bg_color']    = isset( $input_settings['woo_sale_badge_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_sale_badge_bg_color'] ) ? $input_settings['woo_sale_badge_bg_color'] : '#ef4444';
			$new_settings['woo_sale_badge_txt_color']   = isset( $input_settings['woo_sale_badge_txt_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_sale_badge_txt_color'] ) ? $input_settings['woo_sale_badge_txt_color'] : '#ffffff';
			$new_settings['woo_sale_badge_font_size']   = isset( $input_settings['woo_sale_badge_font_size'] ) ? max( 8, min( 32, absint( $input_settings['woo_sale_badge_font_size'] ) ) ) : 13;
			$new_settings['woo_sale_badge_position']    = isset( $input_settings['woo_sale_badge_position'] ) && in_array( $input_settings['woo_sale_badge_position'], array( 'top-left', 'top-right' ), true ) ? $input_settings['woo_sale_badge_position'] : 'top-left';
		}

		// 19. Sanitizar Filtro por Facetas AJAX de WooCommerce
		if ( empty( $saving_module ) || 'woo-facets' === $saving_module ) {
			$new_settings['woo-facets']                = isset( $input_settings['woo-facets'] ) && '1' === $input_settings['woo-facets'] ? '1' : '0';
			$new_settings['facets_enabled']            = $new_settings['woo-facets'];
			$new_settings['facets_config']             = isset( $input_settings['facets_config'] ) && is_array( $input_settings['facets_config'] ) ? array_map( 'sanitize_key', $input_settings['facets_config'] ) : array( 'sort', 'price', 'category', 'stock', 'rating' );
			$new_settings['woo_facets_accent_color']   = isset( $input_settings['woo_facets_accent_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_facets_accent_color'] ) ? $input_settings['woo_facets_accent_color'] : '#2563eb';
			$new_settings['woo_facets_card_bg']        = isset( $input_settings['woo_facets_card_bg'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_facets_card_bg'] ) ? $input_settings['woo_facets_card_bg'] : '#ffffff';
			$new_settings['woo_facets_border_color']   = isset( $input_settings['woo_facets_border_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_facets_border_color'] ) ? $input_settings['woo_facets_border_color'] : '#e2e8f0';
			$new_settings['woo_facets_badge_bg']      = isset( $input_settings['woo_facets_badge_bg'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['woo_facets_badge_bg'] ) ? $input_settings['woo_facets_badge_bg'] : '#f1f5f9';
			$new_settings['woo_facets_border_radius']  = isset( $input_settings['woo_facets_border_radius'] ) ? max( 0, min( 40, absint( $input_settings['woo_facets_border_radius'] ) ) ) : 12;
			$new_settings['woo_facets_show_active_tags'] = isset( $input_settings['woo_facets_show_active_tags'] ) && '1' === $input_settings['woo_facets_show_active_tags'] ? '1' : '0';
			$new_settings['woo_facets_sticky']         = isset( $input_settings['woo_facets_sticky'] ) && '1' === $input_settings['woo_facets_sticky'] ? '1' : '0';
		}

		// 20. Sanitizar Promociones Dinámicas y Descuentos de WooCommerce
		if ( empty( $saving_module ) || 'woo-promotions' === $saving_module || 'woo_promotions' === $saving_module ) {
			if ( isset( $input_settings['woo-promotions'] ) ) {
				$promo_enabled = '1' === (string) $input_settings['woo-promotions'] ? '1' : '0';
			} elseif ( isset( $input_settings['woo_promotions'] ) ) {
				$promo_enabled = '1' === (string) $input_settings['woo_promotions'] ? '1' : '0';
			} else {
				$promo_enabled = isset( $current_settings['woo-promotions'] ) ? (string) $current_settings['woo-promotions'] : ( isset( $current_settings['woo_promotions'] ) ? (string) $current_settings['woo_promotions'] : '1' );
			}
			$new_settings['woo-promotions'] = $promo_enabled;
			$new_settings['woo_promotions'] = $promo_enabled;

			$sanitized_rules = array();
			if ( isset( $input_settings['woo_promotions_rules'] ) && is_array( $input_settings['woo_promotions_rules'] ) ) {
				foreach ( $input_settings['woo_promotions_rules'] as $rule_raw ) {
					if ( ! is_array( $rule_raw ) ) {
						continue;
					}

					$rule_id = ! empty( $rule_raw['id'] ) ? sanitize_key( $rule_raw['id'] ) : 'rule_' . uniqid();
					$title   = ! empty( $rule_raw['title'] ) ? sanitize_text_field( $rule_raw['title'] ) : '';
					$active  = isset( $rule_raw['active'] ) && '1' === (string) $rule_raw['active'] ? '1' : '0';
					$type    = ! empty( $rule_raw['type'] ) && in_array( $rule_raw['type'], array( 'tiered_spend', 'bxgy', 'bulk_qty', 'global_discount', 'payment_method' ), true ) ? sanitize_key( $rule_raw['type'] ) : 'global_discount';
					$scope   = ! empty( $rule_raw['scope'] ) && in_array( $rule_raw['scope'], array( 'all', 'category', 'product' ), true ) ? sanitize_key( $rule_raw['scope'] ) : 'all';

					$categories = array();
					if ( isset( $rule_raw['categories'] ) ) {
						$categories = is_array( $rule_raw['categories'] ) ? array_map( 'absint', $rule_raw['categories'] ) : array_values( array_filter( array_map( 'absint', explode( ',', (string) $rule_raw['categories'] ) ) ) );
					}

					$products = array();
					if ( isset( $rule_raw['products'] ) ) {
						$products = is_array( $rule_raw['products'] ) ? array_map( 'absint', $rule_raw['products'] ) : array_values( array_filter( array_map( 'absint', explode( ',', (string) $rule_raw['products'] ) ) ) );
					}

					$payment_methods = array();
					if ( isset( $rule_raw['payment_methods'] ) ) {
						$payment_methods = is_array( $rule_raw['payment_methods'] ) ? array_map( 'sanitize_text_field', $rule_raw['payment_methods'] ) : array_values( array_filter( array_map( 'sanitize_text_field', explode( ',', (string) $rule_raw['payment_methods'] ) ) ) );
					}

					$tiers = array();
					if ( isset( $rule_raw['tiers'] ) && is_array( $rule_raw['tiers'] ) ) {
						foreach ( $rule_raw['tiers'] as $tier_raw ) {
							if ( ! is_array( $tier_raw ) ) {
								continue;
							}
							$tiers[] = array(
								'min_spend'      => isset( $tier_raw['min_spend'] ) ? max( 0, (float) $tier_raw['min_spend'] ) : 0.0,
								'discount_type'  => ! empty( $tier_raw['discount_type'] ) && in_array( $tier_raw['discount_type'], array( 'percent', 'fixed' ), true ) ? $tier_raw['discount_type'] : 'percent',
								'discount_value' => isset( $tier_raw['discount_value'] ) ? max( 0, (float) $tier_raw['discount_value'] ) : 0.0,
							);
						}
					}

					$allowed_icons = array( 'gift', 'fire', 'percent', 'heart', 'pumpkin', 'skull', 'tree', 'santa', 'shopping_bags', 'star', 'crown', 'lightning', 'sun', 'flower', 'none' );
					$icon          = ! empty( $rule_raw['icon'] ) && in_array( $rule_raw['icon'], $allowed_icons, true ) ? sanitize_key( $rule_raw['icon'] ) : 'gift';

					$s_date = ! empty( $rule_raw['start_date'] ) ? sanitize_text_field( $rule_raw['start_date'] ) : '';
					$s_time = ! empty( $rule_raw['start_time'] ) ? sanitize_text_field( $rule_raw['start_time'] ) : '';
					if ( isset( $rule_raw['start_hour'] ) && isset( $rule_raw['start_minute'] ) ) {
						$s_h    = str_pad( (string) min( 23, max( 0, (int) $rule_raw['start_hour'] ) ), 2, '0', STR_PAD_LEFT );
						$s_m    = str_pad( (string) min( 59, max( 0, (int) $rule_raw['start_minute'] ) ), 2, '0', STR_PAD_LEFT );
						$s_time = $s_h . ':' . $s_m;
					}
					if ( ! empty( $s_date ) ) {
						$s_parts     = explode( ' ', str_replace( 'T', ' ', $s_date ) );
						$s_date_only = $s_parts[0];
						if ( empty( $s_time ) && isset( $s_parts[1] ) ) {
							$s_time = substr( $s_parts[1], 0, 5 );
						}
						$start_date_full = trim( $s_date_only . ( ! empty( $s_time ) ? ' ' . $s_time : '' ) );
					} else {
						$start_date_full = '';
					}

					$e_date = ! empty( $rule_raw['end_date'] ) ? sanitize_text_field( $rule_raw['end_date'] ) : '';
					$e_time = ! empty( $rule_raw['end_time'] ) ? sanitize_text_field( $rule_raw['end_time'] ) : '';
					if ( isset( $rule_raw['end_hour'] ) && isset( $rule_raw['end_minute'] ) ) {
						$e_h    = str_pad( (string) min( 23, max( 0, (int) $rule_raw['end_hour'] ) ), 2, '0', STR_PAD_LEFT );
						$e_m    = str_pad( (string) min( 59, max( 0, (int) $rule_raw['end_minute'] ) ), 2, '0', STR_PAD_LEFT );
						$e_time = $e_h . ':' . $e_m;
					}
					if ( ! empty( $e_date ) ) {
						$e_parts     = explode( ' ', str_replace( 'T', ' ', $e_date ) );
						$e_date_only = $e_parts[0];
						if ( empty( $e_time ) && isset( $e_parts[1] ) ) {
							$e_time = substr( $e_parts[1], 0, 5 );
						}
						$end_date_full = trim( $e_date_only . ( ! empty( $e_time ) ? ' ' . $e_time : '' ) );
					} else {
						$end_date_full = '';
					}

					$sanitized_rules[] = array(
						'id'                    => $rule_id,
						'title'                 => $title,
						'icon'                  => $icon,
						'active'                => $active,
						'type'                  => $type,
						'scope'                 => $scope,
						'categories'            => $categories,
						'products'              => $products,
						'ignore_on_sale'        => isset( $rule_raw['ignore_on_sale'] ) && '1' === (string) $rule_raw['ignore_on_sale'] ? '1' : '0',
						'include_extra_options' => isset( $rule_raw['include_extra_options'] ) && '1' === (string) $rule_raw['include_extra_options'] ? '1' : '0',
						'show_countdown'        => isset( $rule_raw['show_countdown'] ) && '1' === (string) $rule_raw['show_countdown'] ? '1' : '0',
						'enable_schedule'       => isset( $rule_raw['enable_schedule'] ) && '1' === (string) $rule_raw['enable_schedule'] ? '1' : '0',
						'start_date'            => $start_date_full,
						'end_date'              => $end_date_full,
						'countdown_title'        => ! empty( $rule_raw['countdown_title'] ) ? sanitize_text_field( $rule_raw['countdown_title'] ) : '',
						'countdown_bg_color'     => ! empty( $rule_raw['countdown_bg_color'] ) ? sanitize_hex_color( $rule_raw['countdown_bg_color'] ) : '#fff7ed',
						'countdown_border_color' => ! empty( $rule_raw['countdown_border_color'] ) ? sanitize_hex_color( $rule_raw['countdown_border_color'] ) : '#fed7aa',
						'countdown_text_color'   => ! empty( $rule_raw['countdown_text_color'] ) ? sanitize_hex_color( $rule_raw['countdown_text_color'] ) : '#9a3412',
						'countdown_digit_bg'     => ! empty( $rule_raw['countdown_digit_bg'] ) ? sanitize_hex_color( $rule_raw['countdown_digit_bg'] ) : '#ffffff',
						'countdown_digit_color'  => ! empty( $rule_raw['countdown_digit_color'] ) ? sanitize_hex_color( $rule_raw['countdown_digit_color'] ) : '#ea580c',
						'countdown_font_size'    => ! empty( $rule_raw['countdown_font_size'] ) ? absint( $rule_raw['countdown_font_size'] ) : 14,
						'min_spend'             => isset( $rule_raw['min_spend'] ) ? max( 0, (float) $rule_raw['min_spend'] ) : 0.0,
						'discount_type'         => ! empty( $rule_raw['discount_type'] ) && in_array( $rule_raw['discount_type'], array( 'percent', 'fixed', 'fixed_unit', 'fixed_total' ), true ) ? sanitize_key( $rule_raw['discount_type'] ) : 'percent',
						'discount_value'        => isset( $rule_raw['discount_value'] ) ? max( 0, (float) $rule_raw['discount_value'] ) : 0.0,
						'buy_qty'               => isset( $rule_raw['buy_qty'] ) ? max( 1, absint( $rule_raw['buy_qty'] ) ) : 3,
						'get_qty'               => isset( $rule_raw['get_qty'] ) ? max( 1, absint( $rule_raw['get_qty'] ) ) : 1,
						'min_qty'               => isset( $rule_raw['min_qty'] ) ? max( 1, absint( $rule_raw['min_qty'] ) ) : 1,
						'payment_methods'       => $payment_methods,
						'tiers'                 => $tiers,
					);
				}
			}

			$new_settings['woo_promotions_rules'] = $sanitized_rules;

			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients();
			}
		}

		// 21. Sanitizar Autocompletado de Direcciones en Checkout (WooCommerce)
		if ( empty( $saving_module ) || 'woo-address-autofill' === $saving_module ) {
			$new_settings['woo-address-autofill']       = isset( $input_settings['woo-address-autofill'] ) && '1' === $input_settings['woo-address-autofill'] ? '1' : '0';
			$new_settings['woo_address_autofill_city'] = isset( $input_settings['woo_address_autofill_city'] ) && '1' === $input_settings['woo_address_autofill_city'] ? '1' : '0';
		}

		// 22. Sanitizar Protección Anti-Spam en Formularios
		if ( empty( $saving_module ) || 'anti-spam' === $saving_module ) {
			$new_settings['anti-spam']               = isset( $input_settings['anti-spam'] ) && '1' === $input_settings['anti-spam'] ? '1' : '0';
			$new_settings['antispam_honeypot']       = isset( $input_settings['antispam_honeypot'] ) && '1' === $input_settings['antispam_honeypot'] ? '1' : '0';
			$new_settings['antispam_time_check']     = isset( $input_settings['antispam_time_check'] ) && '1' === $input_settings['antispam_time_check'] ? '1' : '0';
			$new_settings['antispam_block_cyrillic'] = isset( $input_settings['antispam_block_cyrillic'] ) && '1' === $input_settings['antispam_block_cyrillic'] ? '1' : '0';
			$new_settings['antispam_max_links']      = isset( $input_settings['antispam_max_links'] ) ? absint( $input_settings['antispam_max_links'] ) : 2;
			$new_settings['antispam_keywords']       = isset( $input_settings['antispam_keywords'] ) ? sanitize_textarea_field( $input_settings['antispam_keywords'] ) : '';
		}

		// 23. Sanitizar Banner de Cookies y RGPD (cookie-consent)
		if ( empty( $saving_module ) || 'cookie-consent' === $saving_module ) {
			$new_settings['cookie-consent']                    = isset( $input_settings['cookie-consent'] ) && '1' === $input_settings['cookie-consent'] ? '1' : '0';
			$new_settings['cookie_consent_gcm']                = isset( $input_settings['cookie_consent_gcm'] ) && '1' === $input_settings['cookie_consent_gcm'] ? '1' : '0';
			$new_settings['cookie_consent_layout']             = isset( $input_settings['cookie_consent_layout'] ) && in_array( $input_settings['cookie_consent_layout'], array( 'layout-bar', 'layout-floating', 'layout-modal' ), true ) ? $input_settings['cookie_consent_layout'] : 'layout-bar';
			$new_settings['cookie_consent_revoke_badge']       = isset( $input_settings['cookie_consent_revoke_badge'] ) && '1' === $input_settings['cookie_consent_revoke_badge'] ? '1' : '0';
			$new_settings['cookie_consent_revoke_badge_pos']   = isset( $input_settings['cookie_consent_revoke_badge_pos'] ) && in_array( $input_settings['cookie_consent_revoke_badge_pos'], array( 'bottom-left', 'bottom-right' ), true ) ? $input_settings['cookie_consent_revoke_badge_pos'] : 'bottom-left';
			$new_settings['cookie_consent_version']            = isset( $input_settings['cookie_consent_version'] ) ? sanitize_text_field( $input_settings['cookie_consent_version'] ) : '1.0';
			$new_settings['cookie_consent_title']              = isset( $input_settings['cookie_consent_title'] ) ? sanitize_text_field( $input_settings['cookie_consent_title'] ) : '';
			$new_settings['cookie_consent_text']               = isset( $input_settings['cookie_consent_text'] ) ? wp_kses_post( $input_settings['cookie_consent_text'] ) : '';
			$new_settings['cookie_consent_btn_accept']         = isset( $input_settings['cookie_consent_btn_accept'] ) ? sanitize_text_field( $input_settings['cookie_consent_btn_accept'] ) : '';
			$new_settings['cookie_consent_btn_reject']         = isset( $input_settings['cookie_consent_btn_reject'] ) ? sanitize_text_field( $input_settings['cookie_consent_btn_reject'] ) : '';
			$new_settings['cookie_consent_btn_settings']       = isset( $input_settings['cookie_consent_btn_settings'] ) ? sanitize_text_field( $input_settings['cookie_consent_btn_settings'] ) : '';
			$new_settings['cookie_consent_cookie_policy_url']  = isset( $input_settings['cookie_consent_cookie_policy_url'] ) ? esc_url_raw( $input_settings['cookie_consent_cookie_policy_url'] ) : '';
			$new_settings['cookie_consent_privacy_policy_url'] = isset( $input_settings['cookie_consent_privacy_policy_url'] ) ? esc_url_raw( $input_settings['cookie_consent_privacy_policy_url'] ) : '';
			$new_settings['cookie_consent_legal_notice_url']   = isset( $input_settings['cookie_consent_legal_notice_url'] ) ? esc_url_raw( $input_settings['cookie_consent_legal_notice_url'] ) : '';
			$new_settings['cookie_consent_bg_color']           = isset( $input_settings['cookie_consent_bg_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['cookie_consent_bg_color'] ) ? $input_settings['cookie_consent_bg_color'] : '#1e293b';
			$new_settings['cookie_consent_text_color']         = isset( $input_settings['cookie_consent_text_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['cookie_consent_text_color'] ) ? $input_settings['cookie_consent_text_color'] : '#f8fafc';
			$new_settings['cookie_consent_btn_accept_bg']      = isset( $input_settings['cookie_consent_btn_accept_bg'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['cookie_consent_btn_accept_bg'] ) ? $input_settings['cookie_consent_btn_accept_bg'] : '#2563eb';
			$new_settings['cookie_consent_btn_accept_text']    = isset( $input_settings['cookie_consent_btn_accept_text'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['cookie_consent_btn_accept_text'] ) ? $input_settings['cookie_consent_btn_accept_text'] : '#ffffff';
			$new_settings['cookie_consent_btn_reject_bg']      = isset( $input_settings['cookie_consent_btn_reject_bg'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['cookie_consent_btn_reject_bg'] ) ? $input_settings['cookie_consent_btn_reject_bg'] : '#475569';
			$new_settings['cookie_consent_btn_reject_text']    = isset( $input_settings['cookie_consent_btn_reject_text'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['cookie_consent_btn_reject_text'] ) ? $input_settings['cookie_consent_btn_reject_text'] : '#ffffff';
		}

		// 24. Sanitizar Gestor de Roles y Permisos (role-manager)
		$target_role = '';
		if ( empty( $saving_module ) || 'role-manager' === $saving_module ) {
			$new_settings['role-manager'] = isset( $input_settings['role-manager'] ) && '1' === $input_settings['role-manager'] ? '1' : '0';

			$target_role = isset( $_POST['role_manager_active_role'] ) ? sanitize_key( $_POST['role_manager_active_role'] ) : ( isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '' );
			if ( ! empty( $target_role ) && current_user_can( 'promote_users' ) ) {
				if ( ! class_exists( 'WPAT_Role_Manager' ) ) {
					require_once WPAT_PATH . 'includes/modules/class-wpat-role-manager.php';
				}
				$wp_roles = wp_roles();
				$role_obj = $wp_roles ? $wp_roles->get_role( $target_role ) : null;
				if ( $role_obj ) {
					$caps_submitted = isset( $_POST['capabilities'] ) && is_array( $_POST['capabilities'] ) ? (array) $_POST['capabilities'] : array();

					// Protección Anti-Lockout para Administrador
					if ( 'administrator' === $target_role ) {
						foreach ( array( 'manage_options', 'edit_users', 'promote_users', 'activate_plugins', 'edit_plugins', 'edit_theme_options', 'read' ) as $crit_cap ) {
							$caps_submitted[ $crit_cap ] = 1;
						}
					}

					$all_known_caps = WPAT_Role_Manager::get_all_unique_capabilities();
					foreach ( $all_known_caps as $cap ) {
						if ( ! empty( $caps_submitted[ $cap ] ) && ( '1' === (string) $caps_submitted[ $cap ] || true === $caps_submitted[ $cap ] ) ) {
							$role_obj->add_cap( $cap, true );
						} else {
							$role_obj->remove_cap( $cap );
						}
					}
				}
			}
		}

		// Sanitizar Venta Directa & Pagos Rápidos (Quick Pay)
		if ( empty( $saving_module ) || 'quick-pay' === $saving_module ) {
			if ( isset( $input_settings['qp_currency'] ) ) {
				$new_settings['qp_currency'] = sanitize_text_field( $input_settings['qp_currency'] );
			}
			if ( isset( $input_settings['qp_currency_symbol'] ) ) {
				$new_settings['qp_currency_symbol'] = sanitize_text_field( $input_settings['qp_currency_symbol'] );
			}
			if ( isset( $input_settings['qp_currency_pos'] ) ) {
				$new_settings['qp_currency_pos'] = sanitize_key( $input_settings['qp_currency_pos'] );
			}
			if ( isset( $input_settings['qp_default_tax_rate'] ) ) {
				$new_settings['qp_default_tax_rate'] = floatval( $input_settings['qp_default_tax_rate'] );
			}
			if ( isset( $input_settings['qp_company_name'] ) ) {
				$new_settings['qp_company_name'] = sanitize_text_field( $input_settings['qp_company_name'] );
			}
			if ( isset( $input_settings['qp_company_cif'] ) ) {
				$new_settings['qp_company_cif'] = sanitize_text_field( $input_settings['qp_company_cif'] );
			}
			if ( isset( $input_settings['qp_company_address'] ) ) {
				$new_settings['qp_company_address'] = sanitize_textarea_field( $input_settings['qp_company_address'] );
			}
			if ( isset( $input_settings['qp_invoice_prefix'] ) ) {
				$new_settings['qp_invoice_prefix'] = sanitize_text_field( $input_settings['qp_invoice_prefix'] );
			}
			if ( isset( $input_settings['qp_primary_color'] ) ) {
				$new_settings['qp_primary_color'] = sanitize_hex_color( $input_settings['qp_primary_color'] );
			}

			// Pasarelas
			$new_settings['qp_stripe_enabled']      = isset( $input_settings['qp_stripe_enabled'] ) && '1' === $input_settings['qp_stripe_enabled'] ? '1' : '0';
			$new_settings['qp_stripe_mode']         = isset( $input_settings['qp_stripe_mode'] ) && 'live' === $input_settings['qp_stripe_mode'] ? 'live' : 'test';
			$new_settings['qp_stripe_test_pub_key'] = isset( $input_settings['qp_stripe_test_pub_key'] ) ? sanitize_text_field( $input_settings['qp_stripe_test_pub_key'] ) : '';
			$new_settings['qp_stripe_test_sec_key'] = isset( $input_settings['qp_stripe_test_sec_key'] ) ? sanitize_text_field( $input_settings['qp_stripe_test_sec_key'] ) : '';
			$new_settings['qp_stripe_live_pub_key'] = isset( $input_settings['qp_stripe_live_pub_key'] ) ? sanitize_text_field( $input_settings['qp_stripe_live_pub_key'] ) : '';
			$new_settings['qp_stripe_live_sec_key'] = isset( $input_settings['qp_stripe_live_sec_key'] ) ? sanitize_text_field( $input_settings['qp_stripe_live_sec_key'] ) : '';

			$new_settings['qp_redsys_enabled']  = isset( $input_settings['qp_redsys_enabled'] ) && '1' === $input_settings['qp_redsys_enabled'] ? '1' : '0';
			$new_settings['qp_redsys_mode']     = isset( $input_settings['qp_redsys_mode'] ) && 'live' === $input_settings['qp_redsys_mode'] ? 'live' : 'test';
			$new_settings['qp_redsys_fuc']      = isset( $input_settings['qp_redsys_fuc'] ) ? sanitize_text_field( $input_settings['qp_redsys_fuc'] ) : '';
			$new_settings['qp_redsys_terminal'] = isset( $input_settings['qp_redsys_terminal'] ) ? sanitize_text_field( $input_settings['qp_redsys_terminal'] ) : '1';
			$new_settings['qp_redsys_key']      = isset( $input_settings['qp_redsys_key'] ) ? sanitize_text_field( $input_settings['qp_redsys_key'] ) : '';

			$new_settings['qp_bizum_enabled']   = isset( $input_settings['qp_bizum_enabled'] ) && '1' === $input_settings['qp_bizum_enabled'] ? '1' : '0';
			$new_settings['qp_bizum_phone']     = isset( $input_settings['qp_bizum_phone'] ) ? sanitize_text_field( $input_settings['qp_bizum_phone'] ) : '';

			$new_settings['qp_paypal_enabled']  = isset( $input_settings['qp_paypal_enabled'] ) && '1' === $input_settings['qp_paypal_enabled'] ? '1' : '0';
			$new_settings['qp_paypal_mode']     = isset( $input_settings['qp_paypal_mode'] ) && 'live' === $input_settings['qp_paypal_mode'] ? 'live' : 'test';
			$new_settings['qp_paypal_email']    = isset( $input_settings['qp_paypal_email'] ) ? sanitize_email( $input_settings['qp_paypal_email'] ) : '';

			$new_settings['qp_bank_enabled']    = isset( $input_settings['qp_bank_enabled'] ) && '1' === $input_settings['qp_bank_enabled'] ? '1' : '0';
			$new_settings['qp_bank_iban']       = isset( $input_settings['qp_bank_iban'] ) ? sanitize_text_field( $input_settings['qp_bank_iban'] ) : '';
			$new_settings['qp_bank_holder']     = isset( $input_settings['qp_bank_holder'] ) ? sanitize_text_field( $input_settings['qp_bank_holder'] ) : '';
		}

		// Preservar colecciones gestionadas independientemente
		$saved_raw_wpat = get_option( 'wpat_settings', array() );
		if ( isset( $saved_raw_wpat['qp_products'] ) && is_array( $saved_raw_wpat['qp_products'] ) ) {
			$new_settings['qp_products'] = $saved_raw_wpat['qp_products'];
		}
		if ( isset( $saved_raw_wpat['qp_coupons'] ) && is_array( $saved_raw_wpat['qp_coupons'] ) ) {
			$new_settings['qp_coupons'] = $saved_raw_wpat['qp_coupons'];
		}

		update_option( 'wpat_settings', $new_settings );

		// Actualizar reglas del archivo .htaccess para SSL
		require_once WPAT_PATH . 'includes/modules/class-wpat-ssl-fixer.php';
		$ssl_active = isset( $new_settings['ssl-fixer'] ) && '1' === $new_settings['ssl-fixer'];
		$method_htaccess = isset( $new_settings['ssl_redirect_method'] ) && 'htaccess' === $new_settings['ssl_redirect_method'];
		WPAT_SSL_Fixer::update_htaccess_rules( $ssl_active && $method_htaccess );

		// Redirigir de vuelta a la vista adecuada
		$active_subtab = isset( $_POST['wpat_active_subtab'] ) ? sanitize_key( $_POST['wpat_active_subtab'] ) : '';

		if ( ! empty( $saving_module ) ) {
			$args = array(
				'settings-updated' => 'true',
				'mod'              => $saving_module,
			);
			if ( ! empty( $active_subtab ) ) {
				$args['subtab'] = $active_subtab;
			}
			if ( 'role-manager' === $saving_module && ! empty( $target_role ) ) {
				$args['role'] = $target_role;
			}
			$redirect_url = add_query_arg( $args, menu_page_url( 'wp-agency-toolkit', false ) );
		} else {
			$active_tab = isset( $_POST['wpat_active_tab'] ) ? sanitize_key( $_POST['wpat_active_tab'] ) : '';
			$args       = array( 'settings-updated' => 'true' );
			if ( ! empty( $active_tab ) ) {
				$args['tab'] = $active_tab;
			}
			if ( ! empty( $active_subtab ) ) {
				$args['subtab'] = $active_subtab;
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
		if ( ! check_ajax_referer( 'wpat_snippet_nonce_action', 'security', false ) && ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce inválido o sesión caducada). Por favor, recarga la página.' ) );
		}

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

		// Recuperar el código (soporta decodificación Base64 para eludir WAF/ModSecurity del hosting)
		$code = '';
		if ( isset( $_POST['snippet_code_b64'] ) && ! empty( $_POST['snippet_code_b64'] ) ) {
			$decoded = base64_decode( $_POST['snippet_code_b64'] );
			if ( false !== $decoded ) {
				$code = rawurldecode( $decoded );
			}
		}

		if ( empty( $code ) && isset( $_POST['snippet_code'] ) ) {
			$code = wp_unslash( $_POST['snippet_code'] );
		}

		$active = isset( $_POST['snippet_active'] ) && '1' === (string) $_POST['snippet_active'] ? '1' : '0';

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
		if ( ! check_ajax_referer( 'wpat_snippet_nonce_action', 'security', false ) && ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce inválido).' ) );
		}

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
		if ( ! check_ajax_referer( 'wpat_snippet_nonce_action', 'security', false ) && ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce inválido).' ) );
		}

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
		if ( ! check_ajax_referer( 'wpat_snippet_nonce_action', 'security', false ) && ! check_ajax_referer( 'wpat_save_settings_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce inválido).' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$id = isset( $_POST['snippet_id'] ) ? sanitize_title( $_POST['snippet_id'] ) : '';
		if ( ! empty( $id ) ) {
			$snippets = get_option( 'wpat_snippets', array() );
			if ( isset( $snippets[ $id ] ) ) {
				$snippets[ $id ]['active'] = ( (string) $snippets[ $id ]['active'] === '1' ) ? '0' : '1';
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
			'woo-email-designer',
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
			'woo-promotions',
			'woo_promotions',
			'post-csv-importer',
			'anti-spam',
			'silent-skin',
			'error-log-viewer',
			'role-manager',
			'cookie-consent',
			'quick-pay',
			'qr-generator',
			'admin-tables-ui',
			'tools',
		);

		if ( ! in_array( $module_id, $modules, true ) ) {
			wp_send_json_error( array( 'message' => 'Módulo no válido.' ) );
		}

		$settings = get_option( 'wpat_settings', array() );
		$settings[ $module_id ] = $state;

		if ( 'woo-promotions' === $module_id || 'woo_promotions' === $module_id ) {
			$settings['woo-promotions'] = $state;
			$settings['woo_promotions'] = $state;
		}

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
		$revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

		// 2. Borradores automáticos
		$auto_drafts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );

		// 3. Entradas/Páginas en la papelera
		$trash_posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );

		// 4. Comentarios en SPAM y Papelera
		$trash_comments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')" );

		// 5. Transients expirados (estándar y de sitio/red)
		$now = time();
		$expired_transients = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->options} WHERE (option_name LIKE %s OR option_name LIKE %s) AND option_value < %d",
			'_transient_timeout_%',
			'_site_transient_timeout_%',
			$now
		) );

		// 6. Metadatos de posts huérfanos
		$orphaned_postmeta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );

		// 7. Metadatos de comentarios huérfanos
		$orphaned_commentmeta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL" );

		// 8. Metadatos de usuarios huérfanos
		$orphaned_usermeta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID WHERE u.ID IS NULL" );

		// 9. Relaciones de términos/taxonomías huérfanas
		$orphaned_term_relationships = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID WHERE p.ID IS NULL" );

		// 10. Tablas con fragmentación / overhead
		$db_sizes = $this->get_database_sizes();

		return array(
			'revisions'                   => $revisions,
			'auto_drafts'                 => $auto_drafts,
			'trash_posts'                 => $trash_posts,
			'trash_comments'              => $trash_comments,
			'expired_transients'          => $expired_transients,
			'orphaned_postmeta'           => $orphaned_postmeta,
			'orphaned_commentmeta'        => $orphaned_commentmeta,
			'orphaned_usermeta'           => $orphaned_usermeta,
			'orphaned_term_relationships' => $orphaned_term_relationships,
			'overhead_raw'                => isset( $db_sizes['overhead_raw'] ) ? $db_sizes['overhead_raw'] : 0,
			'overhead_size'               => isset( $db_sizes['overhead_size'] ) ? $db_sizes['overhead_size'] : '0 B',
		);
	}

	/**
	 * Obtiene métricas del tamaño y tablas de la Base de Datos de WordPress.
	 *
	 * @return array
	 */
	public function get_database_sizes() {
		global $wpdb;
		$tables = $wpdb->get_results( "SHOW TABLE STATUS LIKE '{$wpdb->prefix}%'", ARRAY_A );
		$total_size    = 0;
		$data_size     = 0;
		$index_size    = 0;
		$overhead_size = 0;
		$table_count   = 0;
		$engine        = 'InnoDB';

		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$table_count++;
				if ( isset( $table['Engine'] ) && ! empty( $table['Engine'] ) ) {
					$engine = $table['Engine'];
				}
				$d_size         = isset( $table['Data_length'] ) ? (float) $table['Data_length'] : 0;
				$i_size         = isset( $table['Index_length'] ) ? (float) $table['Index_length'] : 0;
				$o_size         = isset( $table['Data_free'] ) ? (float) $table['Data_free'] : 0;
				$data_size     += $d_size;
				$index_size    += $i_size;
				$overhead_size += $o_size;
				$total_size    += ( $d_size + $i_size );
			}
		}

		return array(
			'table_count'   => $table_count,
			'engine'        => $engine,
			'mysql_version' => method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : 'Desconocida',
			'total_size'    => size_format( $total_size, 2 ),
			'data_size'     => size_format( $data_size, 2 ),
			'index_size'    => size_format( $index_size, 2 ),
			'overhead_size' => size_format( $overhead_size, 2 ),
			'overhead_raw'  => (int) $overhead_size,
		);
	}

	/**
	 * Ejecuta consultas SQL de limpieza sobre la base de datos vía AJAX.
	 */
	public function ajax_cleanup_database() {
		if ( ! check_ajax_referer( 'wpat_cleanup_nonce_action', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Error de seguridad (nonce de limpieza inválido). Por favor, recarga la página.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		global $wpdb;
		$type    = isset( $_POST['cleanup_type'] ) ? sanitize_key( $_POST['cleanup_type'] ) : '';
		$cleared = 0;

		switch ( $type ) {
			case 'revisions':
				$cleared = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				break;
			case 'auto_drafts':
				$cleared = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
				break;
			case 'trash_posts':
				$cleared = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );
				break;
			case 'trash_comments':
				$cleared = (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')" );
				break;
			case 'expired_transients':
				$now = time();
				// Transients estándar
				$transients = $wpdb->get_col( $wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
					'_transient_timeout_%',
					$now
				) );
				if ( ! empty( $transients ) ) {
					foreach ( $transients as $transient_timeout ) {
						$transient_name = str_replace( '_transient_timeout_', '', $transient_timeout );
						delete_transient( $transient_name );
						$cleared++;
					}
				}
				// Transients de red / sitio
				$site_transients = $wpdb->get_col( $wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
					'_site_transient_timeout_%',
					$now
				) );
				if ( ! empty( $site_transients ) ) {
					foreach ( $site_transients as $site_timeout ) {
						$transient_name = str_replace( '_site_transient_timeout_', '', $site_timeout );
						delete_site_transient( $transient_name );
						$cleared++;
					}
				}
				break;
			case 'orphaned_postmeta':
				$cleared = (int) $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );
				break;
			case 'orphaned_commentmeta':
				$cleared = (int) $wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL" );
				break;
			case 'orphaned_usermeta':
				$cleared = (int) $wpdb->query( "DELETE um FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID WHERE u.ID IS NULL" );
				break;
			case 'orphaned_term_relationships':
				$cleared = (int) $wpdb->query( "DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID WHERE p.ID IS NULL" );
				break;
			case 'optimize_tables':
				$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
				if ( ! empty( $tables ) ) {
					foreach ( $tables as $table ) {
						$wpdb->query( "OPTIMIZE TABLE `{$table}`" );
					}
				}
				$cleared = is_array( $tables ) ? count( $tables ) : 0;
				break;
			case 'all':
				// Revisiones
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
				// Borradores automáticos
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
				// Papelera posts
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );
				// Comentarios spam/trash
				$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')" );
				
				// Transients expirados
				$now = time();
				$transients = $wpdb->get_col( $wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
					'_transient_timeout_%',
					$now
				) );
				if ( ! empty( $transients ) ) {
					foreach ( $transients as $transient_timeout ) {
						$transient_name = str_replace( '_transient_timeout_', '', $transient_timeout );
						delete_transient( $transient_name );
					}
				}
				$site_transients = $wpdb->get_col( $wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
					'_site_transient_timeout_%',
					$now
				) );
				if ( ! empty( $site_transients ) ) {
					foreach ( $site_transients as $site_timeout ) {
						$transient_name = str_replace( '_site_transient_timeout_', '', $site_timeout );
						delete_site_transient( $transient_name );
					}
				}

				// Metadatos y relaciones huérfanas
				$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );
				$wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL" );
				$wpdb->query( "DELETE um FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID WHERE u.ID IS NULL" );
				$wpdb->query( "DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID WHERE p.ID IS NULL" );

				// Optimizar tablas
				$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
				if ( ! empty( $tables ) ) {
					foreach ( $tables as $table ) {
						$wpdb->query( "OPTIMIZE TABLE `{$table}`" );
					}
				}
				$cleared = 'all';
				break;
			default:
				wp_send_json_error( array( 'message' => 'Tipo de limpieza no válido.' ) );
		}

		$stats    = $this->get_db_cleanup_stats();
		$db_sizes = $this->get_database_sizes();

		wp_send_json_success( array(
			'cleared'  => $cleared,
			'stats'    => $stats,
			'db_sizes' => $db_sizes,
			'message'  => 'Mantenimiento y optimización de base de datos ejecutados correctamente.'
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
		require_once WPAT_PATH . 'includes/modules/class-wpat-image-optimizer.php';
		return WPAT_Image_Optimizer::is_attachment_in_use( $attachment_id );
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
		$stats    = $this->get_db_cleanup_stats();
		$db_sizes = $this->get_database_sizes();
		?>
		<?php wp_nonce_field( 'wpat_cleanup_nonce_action', 'wpat_cleanup_ajax_nonce' ); ?>
		
		<!-- Cabecera de la sección de Diagnóstico y Salud -->
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
			<div>
				<h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-heart" style="color: #ef4444; font-size: 22px; width: 22px; height: 22px;"></span>
					Salud del Sistema & Limpieza de Base de Datos
				</h2>
				<p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Diagnóstico integral de rendimiento del servidor, compatibilidad de módulos y mantenimiento profundo de la base de datos.</p>
			</div>
			<button type="button" class="button button-secondary" id="wpat_refresh_health_btn" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
				<span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px;"></span>
				Actualizar Diagnóstico
			</button>
		</div>

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
				<h3 style="margin-top:0; font-size:15px; font-weight:600;"><span class="dashicons dashicons-dashboard" style="vertical-align: middle; color: #6366f1;"></span> Servidor & PHP</h3>
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
						<td style="padding:8px 0; font-weight:600;">Protocolo HTTPS</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							echo is_ssl() ? '<span style="color:var(--wpat-success); font-weight:bold;">✔ Forzado (HTTPS)</span>' : '<span style="color:#ea580c; font-weight:bold;">✘ Inseguro (HTTP)</span>';
							?>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Servidor Web</td>
						<td style="padding:8px 0; text-align:right; font-size:11px; color:#475569; word-break:break-all;">
							<?php echo esc_html( isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Desconocido' ); ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Database & Extensions Card -->
			<div class="wpat-module-card" style="margin: 0; padding: 20px;">
				<h3 style="margin-top:0; font-size:15px; font-weight:600;"><span class="dashicons dashicons-admin-plugins" style="vertical-align: middle; color: #0ea5e9;"></span> Base de Datos & Extensiones</h3>
				<table class="wpat-health-table" style="width:100%; border-collapse:collapse; margin-top:15px; font-size:13px;">
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Versión MySQL / MariaDB</td>
						<td style="padding:8px 0; text-align:right;">
							<span style="background:#e0f2fe; color:#0369a1; font-size:11px; padding:2px 6px; border-radius:4px; font-weight:bold;"><?php echo esc_html( $db_sizes['mysql_version'] ); ?></span>
						</td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Motor y Tablas</td>
						<td style="padding:8px 0; text-align:right;"><?php echo esc_html( $db_sizes['engine'] . ' (' . $db_sizes['table_count'] . ' tablas)' ); ?></td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Tamaño Total BD</td>
						<td style="padding:8px 0; text-align:right; font-weight: 700; color: #0f172a;"><?php echo esc_html( $db_sizes['total_size'] ); ?></td>
					</tr>
					<tr style="border-bottom:1px solid #f1f5f9;">
						<td style="padding:8px 0; font-weight:600;">Espacio Desfragmentable (Overhead)</td>
						<td style="padding:8px 0; text-align:right;">
							<?php if ( $db_sizes['overhead_raw'] > 0 ) : ?>
								<span style="color: #ea580c; font-weight: bold;"><?php echo esc_html( $db_sizes['overhead_size'] ); ?></span>
							<?php else : ?>
								<span style="color: var(--wpat-success); font-weight: bold;">0 B (Optimizado)</span>
							<?php endif; ?>
						</td>
					</tr>
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
						<td style="padding:8px 0; font-weight:600;">ZipArchive (Plantillas & Backups)</td>
						<td style="padding:8px 0; text-align:right;">
							<?php 
							echo class_exists( 'ZipArchive' ) ? '<span style="color:var(--wpat-success); font-weight:bold;">✔ Disponible</span>' : '<span style="color:#ea580c; font-weight:bold;">✘ No disponible</span>';
							?>
						</td>
					</tr>
				</table>
			</div>

		</div>

		<!-- Database Optimization Card -->
		<div class="wpat-module-card" style="padding: 20px;">
			<div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--wpat-border); padding-bottom: 15px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
				<div>
					<h3 style="margin:0; font-size:16px; font-weight:700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-database" style="color: var(--wpat-primary);"></span>
						Mantenimiento y Limpieza de Base de Datos
					</h3>
					<p class="description" style="margin:4px 0 0 0;">Elimina registros redundantes, transitorios huérfanos y datos residuales que ralentizan las consultas de tu base de datos de WordPress.</p>
				</div>
				<button type="button" class="button button-primary" id="wpat_db_clean_all_btn" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; padding: 6px 14px; height: auto;">
					<span class="dashicons dashicons-admin-tools" style="font-size:16px; width:16px; height:16px;"></span>
					Limpiar y Optimizar Todo
				</button>
			</div>
			
			<table class="wp-list-table widefat fixed striped" style="box-shadow:none; border: 1px solid #dcdcde; border-radius:6px; overflow:hidden;">
				<thead>
					<tr>
						<th style="font-weight:700; padding:10px;">Tipo de Elemento / Tarea de Limpieza</th>
						<th style="width:120px; font-weight:700; text-align:center; padding:10px;">Elementos</th>
						<th style="width:130px; font-weight:700; text-align:right; padding:10px;">Acciones</th>
					</tr>
				</thead>
				<tbody>
					<!-- 1. Revisiones -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Revisiones obsoletas</strong>
							<p class="description" style="margin:2px 0 0 0;">Copias de seguridad y versiones previas automáticas de tus entradas y páginas.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['revisions'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="revisions">
							<?php echo esc_html( $stats['revisions'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="revisions" <?php disabled( $stats['revisions'], 0 ); ?>>
								<?php echo 0 === $stats['revisions'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 2. Borradores automáticos -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Borradores automáticos</strong>
							<p class="description" style="margin:2px 0 0 0;">Borradores temporales huérfanos creados de forma automática al editar.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['auto_drafts'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="auto_drafts">
							<?php echo esc_html( $stats['auto_drafts'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="auto_drafts" <?php disabled( $stats['auto_drafts'], 0 ); ?>>
								<?php echo 0 === $stats['auto_drafts'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 3. Papelera de posts -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Páginas y Entradas en la Papelera</strong>
							<p class="description" style="margin:2px 0 0 0;">Contenido eliminado que aún permanece ocupando espacio en la papelera.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['trash_posts'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="trash_posts">
							<?php echo esc_html( $stats['trash_posts'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="trash_posts" <?php disabled( $stats['trash_posts'], 0 ); ?>>
								<?php echo 0 === $stats['trash_posts'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 4. Spam y papelera comentarios -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Comentarios de Spam y Papelera</strong>
							<p class="description" style="margin:2px 0 0 0;">Comentarios no deseados marcados como spam o pendientes de purga.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['trash_comments'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="trash_comments">
							<?php echo esc_html( $stats['trash_comments'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="trash_comments" <?php disabled( $stats['trash_comments'], 0 ); ?>>
								<?php echo 0 === $stats['trash_comments'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 5. Transients expirados -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Transients expirados</strong>
							<p class="description" style="margin:2px 0 0 0;">Opciones y memorias temporales en caché cuyo periodo de validez ha vencido.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['expired_transients'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="expired_transients">
							<?php echo esc_html( $stats['expired_transients'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="expired_transients" <?php disabled( $stats['expired_transients'], 0 ); ?>>
								<?php echo 0 === $stats['expired_transients'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 6. Metadatos de Posts Huérfanos -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Metadatos huérfanos de Entradas (Postmeta)</strong>
							<p class="description" style="margin:2px 0 0 0;">Registros en <code>wp_postmeta</code> asociados a entradas o páginas ya eliminadas.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['orphaned_postmeta'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="orphaned_postmeta">
							<?php echo esc_html( $stats['orphaned_postmeta'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="orphaned_postmeta" <?php disabled( $stats['orphaned_postmeta'], 0 ); ?>>
								<?php echo 0 === $stats['orphaned_postmeta'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 7. Metadatos de Comentarios Huérfanos -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Metadatos huérfanos de Comentarios (Commentmeta)</strong>
							<p class="description" style="margin:2px 0 0 0;">Registros en <code>wp_commentmeta</code> asociados a comentarios inexistentes.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['orphaned_commentmeta'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="orphaned_commentmeta">
							<?php echo esc_html( $stats['orphaned_commentmeta'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="orphaned_commentmeta" <?php disabled( $stats['orphaned_commentmeta'], 0 ); ?>>
								<?php echo 0 === $stats['orphaned_commentmeta'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 8. Metadatos de Usuarios Huérfanos -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Metadatos huérfanos de Usuarios (Usermeta)</strong>
							<p class="description" style="margin:2px 0 0 0;">Registros en <code>wp_usermeta</code> pertenecientes a cuentas de usuario borradas.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['orphaned_usermeta'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="orphaned_usermeta">
							<?php echo esc_html( $stats['orphaned_usermeta'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="orphaned_usermeta" <?php disabled( $stats['orphaned_usermeta'], 0 ); ?>>
								<?php echo 0 === $stats['orphaned_usermeta'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 9. Relaciones de Términos Huérfanas -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Relaciones huérfanas de Taxonomías (Term Relationships)</strong>
							<p class="description" style="margin:2px 0 0 0;">Vínculos en <code>wp_term_relationships</code> que apuntan a posts o términos eliminados.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:14px; <?php echo 0 === $stats['orphaned_term_relationships'] ? 'color:#94a3b8;' : 'color:#0f172a;'; ?>" class="wpat-db-counter" data-type="orphaned_term_relationships">
							<?php echo esc_html( $stats['orphaned_term_relationships'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="orphaned_term_relationships" <?php disabled( $stats['orphaned_term_relationships'], 0 ); ?>>
								<?php echo 0 === $stats['orphaned_term_relationships'] ? 'Limpio' : 'Limpiar'; ?>
							</button>
						</td>
					</tr>
					<!-- 10. Optimización de Tablas -->
					<tr>
						<td style="padding:10px; vertical-align:middle;">
							<strong>Optimización y Desfragmentación de Tablas</strong>
							<p class="description" style="margin:2px 0 0 0;">Ejecuta <code>OPTIMIZE TABLE</code> en todas las tablas de WordPress para reorganizar índices y liberar espacio libre.</p>
						</td>
						<td style="text-align:center; padding:10px; vertical-align:middle; font-weight:bold; font-size:13px; color: <?php echo $stats['overhead_raw'] > 0 ? '#ea580c' : '#16a34a'; ?>;" class="wpat-db-counter" data-type="optimize_tables">
							<?php echo esc_html( $stats['overhead_size'] ); ?>
						</td>
						<td style="text-align:right; padding:10px; vertical-align:middle;">
							<button type="button" class="button button-small wpat-db-clean-btn" data-type="optimize_tables">
								Optimizar
							</button>
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
				if ( isset( $_GET['settings-updated'] ) && ( 'true' === $_GET['settings-updated'] || '1' === $_GET['settings-updated'] ) ) {
			?>
			<script>
			(function() {
				function triggerSavedToast() {
					if (typeof window.showToast === 'function') {
						window.showToast('Cambios guardados correctamente', false);
					} else {
						setTimeout(triggerSavedToast, 100);
					}
				}
				if (document.readyState === 'complete' || document.readyState === 'interactive') {
					triggerSavedToast();
				} else {
					document.addEventListener('DOMContentLoaded', triggerSavedToast);
				}
			})();
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
				'wpat-error-log-viewer'  => 'error-log-viewer',
				'wpat-role-manager'      => 'role-manager',
				'wpat-cookie-consent'    => 'cookie-consent',
				'wpat-quick-pay'         => 'quick-pay',
				'wpat-qr-generator'      => 'qr-generator',
				'wpat-admin-tables-ui'   => 'admin-tables-ui',
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
												<span class="wpat-cat-badge">39</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="woocommerce">
												<span class="wpat-cat-label">🛍️ WooCommerce</span>
												<span class="wpat-cat-badge">10</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="security">
												<span class="wpat-cat-label">🛡️ Seguridad</span>
												<span class="wpat-cat-badge">7</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="performance">
												<span class="wpat-cat-label">⚡ Rendimiento & SEO</span>
												<span class="wpat-cat-badge">8</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="tools">
												<span class="wpat-cat-label">🛠️ Herramientas</span>
												<span class="wpat-cat-badge">9</span>
											</button>
											<button type="button" class="wpat-cat-item" data-cat="system">
												<span class="wpat-cat-label">⚙️ Sistema & Admin</span>
												<span class="wpat-cat-badge">9</span>
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
											<option value="all">📌 Todos (39)</option>
											<option value="woocommerce">🛍️ WooCommerce (10)</option>
											<option value="security">🛡️ Seguridad (7)</option>
											<option value="performance">⚡ Rendimiento & SEO (8)</option>
											<option value="tools">🛠️ Herramientas (9)</option>
											<option value="system">⚙️ Sistema & Admin (9)</option>
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
				'title'       => 'Filtro por Facetas',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Filtros ultrarrápidos para catálogo por precio, stock, categorías, atributos u ordenación.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '⚡',
				'icon_bg'     => 'woo',
				'keywords'    => 'filtros facetas woocommerce catalogo'
			),
			array(
				'id'          => 'woo-checkout-editor',
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'id'          => 'woo-promotions',
				'is_updated'  => true,
				'title'       => 'Promociones Dinámicas y Descuentos',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Crea reglas avanzadas de descuento en el carrito: tramos por gasto, 3x2, volumen, descuento global y por método de pago.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🎁',
				'icon_bg'     => 'woo',
				'keywords'    => 'promociones descuentos 3x2 tramos volumen oferta pago woocommerce'
			),
			array(
				'id'          => 'woo-checkout-designer',
				'is_updated'  => true,
				'title'       => 'Diseñador de Carrito y Checkout',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Diseño de Carrito y Checkout de alta conversión con plantillas responsivas (Classic, Express, Accordion, Minimalist, Shop-Style, Slide-Out Drawer Cart) y barra de envío gratis.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🛒',
				'icon_bg'     => 'woo',
				'keywords'    => 'checkout carrito cart diseñador plantillas woocommerce plantilla classic express minimalista acordeon deslizable drawer'
			),

			array(
				'id'          => 'woo-email-designer',
				'is_updated'  => true,
				'title'       => 'Diseñador de Emails',
				'badge'       => 'Configuración',
				'badge_class' => 'config',
				'desc'        => 'Personaliza visualmente las plantillas de correo de WooCommerce con 3 diseños modernos (Clásica, Moderna, Minimalista), logotipo, colores corporativos y envío de pruebas.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '📧',
				'icon_bg'     => 'woo',
				'keywords'    => 'email correo plantilla plantillas diseñador woocommerce mail pedido enviado'
			),
			array(
				'id'          => 'woo-address-autofill',
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
				'title'       => 'Swatches de Variación',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Transforma desplegables de variaciones en botones visuales de color, imagen o etiqueta.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🎨',
				'icon_bg'     => 'woo',
				'keywords'    => 'swatches variaciones botones color imagen atributos'
			),
			array(
				'id'          => 'quick-pay',
				'is_new'      => true,
				'title'       => 'Venta Directa & Pagos Rápidos',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Vende servicios, cursos, productos digitales, físicos y suscripciones sin WooCommerce con Stripe, Redsys TPV, Bizum, PayPal y facturación PDF.',
				'cat_class'   => 'cat-woocommerce cat-woo cat-tools cat-performance',
				'icon'        => '💳',
				'icon_bg'     => 'woo',
				'keywords'    => 'venta directa pagos rapidos stripe redsys bizum paypal checkout sin woocommerce infoproductos servicios compras pedidos facturas cupones quick pay'
			),

			// SEGURIDAD (6)
			array(
				'id'          => 'security-hardening',
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
				'title'       => 'Forzar SSL & Contenido Mixto',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Fuerza redirección HTTPS y repara automáticamente imágenes o scripts cargados por HTTP.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🔒',
				'icon_bg'     => 'sec',
				'keywords'    => 'ssl https contenido mixto redireccion 301'
			),
			array(
				'id'          => 'cookie-consent',
				'is_new'      => true,
				'title'       => 'Banner de Cookies y RGPD',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Banner legal RGPD/AEPD con Google Consent Mode v2, bloqueo previo de scripts, modal de preferencias y escáner inteligente de cookies.',
				'cat_class'   => 'cat-security cat-sec cat-performance cat-perf cat-tools',
				'icon'        => '🍪',
				'icon_bg'     => 'sec',
				'keywords'    => 'cookies rgpd gdpr banner consentimiento consent mode v2 analytics aepd legal aviso privacidad'
			),

			// RENDIMIENTO & SEO (7)
			array(
				'id'          => 'performance',
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
				'title'       => 'Botón Flotante WhatsApp',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Añade un botón flotante directo de contacto por WhatsApp en la esquina de tu sitio web.',
				'cat_class'   => 'cat-tools',
				'icon'        => '💬',
				'icon_bg'     => 'woo',
				'keywords'    => 'whatsapp boton flotante contacto chat'
			),
			array(
				'id'          => 'qr-generator',
				'is_new'      => true,
				'title'       => 'Generador de Códigos QR',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Genera códigos QR dinámicos para WhatsApp, enlaces directos, Wi-Fi, vCard, emails y pagos con previsualización en vivo y descarga PNG/SVG.',
				'cat_class'   => 'cat-tools',
				'icon'        => '📱',
				'icon_bg'     => 'perf',
				'keywords'    => 'qr codigos qr generador whatsapp enlace wifi vcard bizum pago svg png'
			),

			// SISTEMA & ADMIN (8)
			array(
				'id'          => 'login-customizer',
				'is_updated'  => true,
				'title'       => 'Personalizador del Login',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Personaliza visualmente la pantalla de acceso wp-login.php con tu logotipo, fondo personalizado, colores corporativos y textos.',
				'cat_class'   => 'cat-system cat-admin cat-security cat-sec',
				'icon'        => '🎨',
				'icon_bg'     => 'admin',
				'keywords'    => 'personalizador login customizer wp-login acceso logo fondo imagen color inicio sesion'
			),
			array(
				'id'          => 'initial-setup',
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'is_updated'  => true,
				'title'       => 'Ocultar Huella WPAT',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Modo Marca Blanca para agencias: oculta las menciones de WP Agency Toolkit a los clientes final.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '🎭',
				'icon_bg'     => 'admin',
				'keywords'    => 'marca blanca marca agencia huella silent skin',
				'has_settings'=> true
			),
			array(
				'id'          => 'hide_admin_bar',
				'is_updated'  => true,
				'title'       => 'Restringir Barra & Acceso Admin',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Oculta la barra superior negra de WordPress y bloquea el acceso a wp-admin a clientes/suscriptores.',
				'cat_class'   => 'cat-system cat-admin',
				'icon'        => '🚫',
				'icon_bg'     => 'sec',
				'keywords'    => 'restringir barra admin wp-admin acceso clientes',
				'has_settings'=> true
			),
			array(
				'id'          => 'smtp',
				'is_updated'  => true,
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
				'is_updated'  => true,
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
				'id'          => 'error-log-viewer',
				'is_new'      => true,
				'title'       => 'Visor de Logs de Error',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Monitor en tiempo real de debug.log con detección de severidades, stack traces colapsables, filtrado y vaciado.',
				'cat_class'   => 'cat-system cat-admin cat-tools',
				'icon'        => '📜',
				'icon_bg'     => 'admin',
				'keywords'    => 'logs debug error fatal warning visor depuracion monitor registro errores'
			),
			array(
				'id'          => 'role-manager',
				'is_new'      => true,
				'title'       => 'Gestor de Roles y Permisos',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Administra, crea, clona y audita roles de usuario y permisos de WordPress y WooCommerce con matriz visual y protección anti-bloqueo.',
				'cat_class'   => 'cat-system cat-admin cat-tools',
				'icon'        => '👥',
				'icon_bg'     => 'admin',
				'keywords'    => 'roles permisos capabilities usuarios roles perfil editor administrador permisos capacidades clonar reset'
			),
			array(
				'id'          => 'admin-tables-ui',
				'is_new'      => true,
				'title'       => 'Diseño SaaS para Listados & CPTs',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Moderniza las pantallas de entradas, páginas, productos y CPTs con un diseño SaaS ergonómico, badges de estado, miniaturas y compatibilidad 100%.',
				'cat_class'   => 'cat-system cat-admin cat-tools',
				'icon'        => '✨',
				'icon_bg'     => 'admin',
				'keywords'    => 'saas tablas listados entradas paginas productos cpts modernizar look feel admin ui'
			),
			array(
				'id'          => 'tools',
				'is_updated'  => true,
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
			$is_new     = ! empty( $mod['is_new'] );
			$is_updated = ! empty( $mod['is_updated'] );
			$search_text = $mod['title'] . ' ' . $mod['desc'] . ' ' . ( isset( $mod['keywords'] ) ? $mod['keywords'] : '' ) . ' ' . $mod['id'] . ' ' . ( isset( $mod['badge'] ) ? $mod['badge'] : '' );

			$is_visible = ( 'all' === $active_cat || strpos( $mod['cat_class'], 'cat-' . $active_cat ) !== false || strpos( $mod['cat_class'], $active_cat ) !== false );
			$card_style = $is_visible ? '' : 'style="display:none;"';
			?>
			<div class="wpat-module-grid-card <?php echo esc_attr( $mod['cat_class'] ); ?>" <?php echo $card_style; ?> data-name="<?php echo esc_attr( isset( $mod['keywords'] ) ? $mod['keywords'] : '' ); ?>" data-search="<?php echo esc_attr( mb_strtolower( $search_text, 'UTF-8' ) ); ?>">
				<?php if ( $is_new ) : ?>
					<div class="wpat-card-badge-new">NUEVO</div>
				<?php elseif ( $is_updated ) : ?>
					<div class="wpat-card-badge-updated">ACTUALIZADO</div>
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
										<div style="padding: 12px 14px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 8px;">
											<?php if ( $is_global ) : ?>
												<button type="button" class="button button-primary wpat-admin-import-template-btn" data-kit="<?php echo esc_attr( $active_slug ); ?>" data-id="<?php echo esc_attr( $tpl['id'] ); ?>" style="width: 100%;">
													🎨 Aplicar Estilos Globales
												</button>
											<?php else : ?>
												<div style="display: flex; gap: 6px; width: 100%;">
													<?php if ( ! empty( $tpl['preview_url'] ) ) : ?>
														<a href="<?php echo esc_url( $tpl['preview_url'] ); ?>" target="_blank" class="button button-secondary" style="padding: 0 8px;" title="Ver vista previa">👁️ Ver</a>
													<?php endif; ?>
													<button type="button" class="button button-secondary wpat-admin-import-template-btn" data-kit="<?php echo esc_attr( $active_slug ); ?>" data-id="<?php echo esc_attr( $tpl['id'] ); ?>" style="flex: 1; font-size: 12px;">
														📥 A Biblioteca
													</button>
													<button type="button" class="button button-primary wpat-admin-create-page-btn" data-kit="<?php echo esc_attr( $active_slug ); ?>" data-id="<?php echo esc_attr( $tpl['id'] ); ?>" data-title="<?php echo esc_attr( $tpl['title'] ); ?>" style="flex: 1; font-size: 12px;">
														⚡ Crear Página
													</button>
												</div>
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

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}
		?>
		<div class="wpat-module-card" style="margin-bottom: 25px;">
			<div class="wpat-module-header">
				<div class="wpat-module-info">
					<h3>Exportar e Importar Contenidos (CSV & JSON)</h3>
					<p>Exporta entradas, páginas o CPTs a archivos CSV (compatibles con Excel y Google Sheets) o JSON estructurado, e importa publicaciones masivamente con imágenes destacadas y taxonomías.</p>
				</div>
				<?php $this->render_module_toggle( 'post-csv-importer', $settings, true ); ?>
			</div>
			<div class="wpat-module-body" style="display: block; padding: 24px;">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
					
					<!-- 1. CAJA DE EXPORTACIÓN -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 22px; border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between;">
						<div>
							<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
								<span class="dashicons dashicons-upload" style="color: #4f46e5; font-size: 20px; width: 20px; height: 20px;"></span>
								<h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Exportar Contenidos</h4>
							</div>
							<p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b; line-height: 1.4;">
								Descarga los contenidos del sitio en un archivo estructurado con metadatos SEO, taxonomías e imágenes.
							</p>

							<div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 18px;">
								<div>
									<label for="wpat_csv_export_post_type" style="font-weight: 600; font-size: 12.5px; color: #475569; display: block; margin-bottom: 4px;">Tipo de Contenido a Exportar</label>
									<select id="wpat_csv_export_post_type" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
										<?php foreach ( $post_types as $pt_slug => $pt_obj ) : ?>
											<option value="<?php echo esc_attr( $pt_slug ); ?>">
												<?php echo esc_html( $pt_obj->labels->singular_name ? $pt_obj->labels->singular_name : $pt_obj->label ); ?> (<?php echo esc_html( $pt_slug ); ?>)
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div>
									<label for="wpat_csv_export_status" style="font-weight: 600; font-size: 12.5px; color: #475569; display: block; margin-bottom: 4px;">Estado de las Publicaciones</label>
									<select id="wpat_csv_export_status" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
										<option value="any">Todos los estados (Publicados, Borradores, etc.)</option>
										<option value="publish">Solo Publicados</option>
										<option value="draft">Solo Borradores</option>
										<option value="pending">Solo Pendientes de revisión</option>
									</select>
								</div>

								<div>
									<label for="wpat_export_format_type" style="font-weight: 600; font-size: 12.5px; color: #475569; display: block; margin-bottom: 4px;">Formato de Archivo</label>
									<select id="wpat_export_format_type" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
										<option value="csv">Archivo CSV (.csv - Excel / Google Sheets)</option>
										<option value="json">Archivo JSON (.json - Migraciones / API)</option>
									</select>
								</div>
							</div>
						</div>

						<div>
							<button type="button" class="button button-primary" id="wpat_csv_export_btn" style="width: 100%; height: 38px; line-height: 36px; font-weight: 600; justify-content: center; display: inline-flex; align-items: center; gap: 6px;">
								<span class="dashicons dashicons-download" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
								<span>Descargar Archivo Exportado</span>
							</button>

							<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; font-size: 12px; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
								<span style="color: #64748b;">Plantillas de ejemplo:</span>
								<div style="display: flex; gap: 10px;">
									<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=wpat_csv_download_sample' ) ); ?>" class="button button-link" style="font-size: 12px; padding: 0;">📄 CSV Ejemplo</a>
									<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=wpat_json_download_sample' ) ); ?>" class="button button-link" style="font-size: 12px; padding: 0;">📋 JSON Ejemplo</a>
								</div>
							</div>
						</div>
					</div>

					<!-- 2. CAJA DE IMPORTACIÓN -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 22px; border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between;">
						<div>
							<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
								<span class="dashicons dashicons-download" style="color: #059669; font-size: 20px; width: 20px; height: 20px;"></span>
								<h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Importación Masiva en Lote</h4>
							</div>
							<p style="margin: 0 0 16px 0; font-size: 13px; color: #64748b; line-height: 1.4;">
								Sube un archivo CSV o JSON para crear o actualizar publicaciones automáticamente por lotes seguros.
							</p>

							<div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
								<div>
									<label for="wpat_csv_import_post_type" style="font-weight: 600; font-size: 12.5px; color: #475569; display: block; margin-bottom: 4px;">Tipo de Contenido de Destino</label>
									<select id="wpat_csv_import_post_type" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
										<?php foreach ( $post_types as $pt_slug => $pt_obj ) : ?>
											<option value="<?php echo esc_attr( $pt_slug ); ?>">
												<?php echo esc_html( $pt_obj->labels->singular_name ? $pt_obj->labels->singular_name : $pt_obj->label ); ?> (<?php echo esc_html( $pt_slug ); ?>)
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div>
									<label for="wpat_csv_import_strategy" style="font-weight: 600; font-size: 12.5px; color: #475569; display: block; margin-bottom: 4px;">Estrategia ante Duplicados (Mismo Slug o ID)</label>
									<select id="wpat_csv_import_strategy" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
										<option value="update">Actualizar publicaciones existentes (Recomendado)</option>
										<option value="skip">Omitir duplicados (solo crear las que no existan)</option>
										<option value="create_new">Crear siempre como nuevas publicaciones</option>
									</select>
								</div>

								<!-- Selector de archivo estilizado -->
								<div style="border: 2px dashed #cbd5e1; background: #ffffff; padding: 14px; border-radius: 6px; text-align: center;">
									<input type="file" id="wpat_csv_file_input" accept=".csv,.json,text/csv,application/json" style="display: none;" />
									<button type="button" class="button button-secondary" id="wpat_csv_select_file_btn" style="height: 32px; line-height: 30px;">
										<span class="dashicons dashicons-media-document" style="font-size: 15px; width: 15px; height: 15px; vertical-align: middle;"></span> Seleccionar Archivo CSV o JSON
									</button>
									<div id="wpat_csv_file_name" style="margin-top: 8px; font-size: 12.5px; font-weight: 600; color: #0284c7; display: none;"></div>
								</div>
							</div>
						</div>

						<div>
							<button type="button" class="button button-primary" id="wpat_csv_start_import_btn" disabled style="width: 100%; height: 38px; line-height: 36px; font-weight: 600; justify-content: center; display: inline-flex; align-items: center; gap: 6px;">
								<span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
								<span>Iniciar Importación</span>
							</button>

							<!-- Barra de Progreso AJAX -->
							<div id="wpat_csv_progress_wrapper" style="display: none; margin-top: 14px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px;">
								<div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">
									<span id="wpat_csv_progress_label">Preparando importación...</span>
									<span id="wpat_csv_progress_percent">0%</span>
								</div>
								<div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
									<div id="wpat_csv_progress_bar" style="width: 0%; height: 100%; background: #10b981; transition: width 0.3s ease; border-radius: 4px;"></div>
								</div>
							</div>
						</div>
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
				$wp_roles        = wp_roles();
				$available_roles = $wp_roles ? $wp_roles->role_names : array();
				$hidden_bar_roles = isset( $settings['admin_bar_hidden_roles'] ) && is_array( $settings['admin_bar_hidden_roles'] ) ? $settings['admin_bar_hidden_roles'] : array( 'subscriber', 'customer' );
				$blocked_admin_roles = isset( $settings['admin_access_restricted_roles'] ) && is_array( $settings['admin_access_restricted_roles'] ) ? $settings['admin_access_restricted_roles'] : array( 'subscriber', 'customer' );
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Restringir Barra y Acceso de Admin</h3>
							<p>Oculta la barra superior negra de WordPress en el front-end y bloquea el acceso a /wp-admin para usuarios con roles no autorizados (Suscriptores, Clientes de WooCommerce, etc.).</p>
						</div>
						<?php $this->render_module_toggle( 'hide_admin_bar', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">

						<!-- 1. OCULTACIÓN DE LA BARRA SUPERIOR -->
						<div class="wpat-field-group" style="margin-bottom: 20px;">
							<label for="wpat_admin_bar_hide_mode" style="font-weight: 600; display: block; margin-bottom: 6px;">Modo de Ocultación de la Barra Superior (Admin Bar)</label>
							<select name="wpat_settings[admin_bar_hide_mode]" id="wpat_admin_bar_hide_mode" class="regular-text" style="width: 100%; max-width: 450px;">
								<option value="all_except_admin" <?php selected( isset( $settings['admin_bar_hide_mode'] ) ? $settings['admin_bar_hide_mode'] : 'all_except_admin', 'all_except_admin' ); ?>>Ocultar para todos los usuarios excepto Administradores (Recomendado)</option>
								<option value="roles" <?php selected( isset( $settings['admin_bar_hide_mode'] ) ? $settings['admin_bar_hide_mode'] : 'all_except_admin', 'roles' ); ?>>Ocultar únicamente para los roles seleccionados abajo</option>
								<option value="all" <?php selected( isset( $settings['admin_bar_hide_mode'] ) ? $settings['admin_bar_hide_mode'] : 'all_except_admin', 'all' ); ?>>Ocultar para absolutamente todos los usuarios en el Front-end</option>
							</select>
							<p class="description">Selecciona qué usuarios dejarán de ver la barra negra superior de WordPress al navegar por la web.</p>
						</div>

						<div class="wpat-field-group" id="wpat-bar-roles-group" style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">
							<label style="font-weight: 600; display: block; margin-bottom: 8px;">Roles con la Barra Superior Oculta</label>
							<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px;">
								<?php foreach ( $available_roles as $role_key => $role_name ) : ?>
									<?php if ( 'administrator' === $role_key ) continue; ?>
									<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
										<input type="checkbox" name="wpat_settings[admin_bar_hidden_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $hidden_bar_roles, true ) ); ?> />
										<?php echo esc_html( translate_user_role( $role_name ) ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- 2. BLOQUEO DE ACCESO A /WP-ADMIN -->
						<div class="wpat-field-group" style="margin-top: 25px; border-top: 1px dotted var(--wpat-border); padding-top: 20px;">
							<label style="font-weight: 600; display: block; margin-bottom: 10px;">Bloqueo de Acceso a /wp-admin</label>
							
							<div style="margin-bottom: 15px;">
								<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px;">
									<input type="checkbox" name="wpat_settings[admin_access_restrict_enabled]" value="1" <?php checked( isset( $settings['admin_access_restrict_enabled'] ) ? $settings['admin_access_restrict_enabled'] : '1', '1' ); ?> />
									<strong>Bloquear panel de administración (/wp-admin):</strong> Redirige automáticamente a usuarios no autorizados cuando intentan entrar al backend.
								</label>
							</div>

							<div style="margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">
								<label style="font-weight: 600; display: block; margin-bottom: 8px;">Roles Bloqueados de Entrar al Backend</label>
								<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px;">
									<?php foreach ( $available_roles as $role_key => $role_name ) : ?>
										<?php if ( 'administrator' === $role_key ) continue; ?>
										<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
											<input type="checkbox" name="wpat_settings[admin_access_restricted_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $blocked_admin_roles, true ) ); ?> />
											<?php echo esc_html( translate_user_role( $role_name ) ); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="wpat-field-group-row" style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
								<div class="wpat-field-group" style="flex: 1; min-width: 250px;">
									<label for="wpat_admin_access_redirect_to" style="font-weight: 600; display: block; margin-bottom: 6px;">Destino de Redirección</label>
									<select name="wpat_settings[admin_access_redirect_to]" id="wpat_admin_access_redirect_to" class="regular-text" style="width: 100%;">
										<option value="home" <?php selected( isset( $settings['admin_access_redirect_to'] ) ? $settings['admin_access_redirect_to'] : 'home', 'home' ); ?>>Página de Inicio del Sitio Web (/)</option>
										<?php if ( class_exists( 'WooCommerce' ) ) : ?>
											<option value="woocommerce_myaccount" <?php selected( isset( $settings['admin_access_redirect_to'] ) ? $settings['admin_access_redirect_to'] : 'home', 'woocommerce_myaccount' ); ?>>Página "Mi Cuenta" de WooCommerce</option>
										<?php endif; ?>
										<option value="custom" <?php selected( isset( $settings['admin_access_redirect_to'] ) ? $settings['admin_access_redirect_to'] : 'home', 'custom' ); ?>>URL Personalizada</option>
									</select>
									<p class="description">A dónde enviar a los usuarios bloqueados al intentar entrar a /wp-admin.</p>
								</div>

								<div class="wpat-field-group" style="flex: 1; min-width: 250px;">
									<label for="wpat_admin_access_custom_redirect_url" style="font-weight: 600; display: block; margin-bottom: 6px;">URL Personalizada (opcional)</label>
									<input type="url" name="wpat_settings[admin_access_custom_redirect_url]" id="wpat_admin_access_custom_redirect_url" value="<?php echo esc_attr( isset( $settings['admin_access_custom_redirect_url'] ) ? $settings['admin_access_custom_redirect_url'] : '' ); ?>" placeholder="https://misitio.com/area-privada" class="regular-text" style="width: 100%;" />
									<p class="description">Utilizada únicamente si se selecciona la opción "URL Personalizada".</p>
								</div>
							</div>

							<div class="wpat-field-group" style="margin-bottom: 15px;">
								<label for="wpat_admin_access_excluded_users" style="font-weight: 600; display: block; margin-bottom: 4px;">Usuarios o IDs Excluidos de Restricción</label>
								<input type="text" name="wpat_settings[admin_access_excluded_users]" id="wpat_admin_access_excluded_users" value="<?php echo esc_attr( isset( $settings['admin_access_excluded_users'] ) ? $settings['admin_access_excluded_users'] : '' ); ?>" placeholder="admin, soporte@agencia.com, 2" class="regular-text" style="width: 100%; max-width: 500px;" />
								<p class="description">Nombres de usuario, correos o IDs de usuario separados por coma que nunca serán bloqueados ni redirigidos.</p>
							</div>
						</div>

						<!-- 3. LIMPIEZA DE ELEMENTOS EN LA BARRA SUPERIOR -->
						<div class="wpat-field-group" style="margin-top: 25px; border-top: 1px dotted var(--wpat-border); padding-top: 20px;">
							<label style="font-weight: 600; display: block; margin-bottom: 8px;">Limpieza de Nodos en la Barra Superior (para usuarios autorizados)</label>
							<p class="description" style="margin-bottom: 12px;">Elimina elementos innecesarios o marcas externas de la barra superior para mantenerla limpia y profesional:</p>
							
							<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">
								<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
									<input type="checkbox" name="wpat_settings[admin_bar_remove_wp_logo]" value="1" <?php checked( isset( $settings['admin_bar_remove_wp_logo'] ) ? $settings['admin_bar_remove_wp_logo'] : '1', '1' ); ?> />
									Ocultar Logo de WordPress
								</label>
								<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
									<input type="checkbox" name="wpat_settings[admin_bar_remove_comments]" value="1" <?php checked( isset( $settings['admin_bar_remove_comments'] ) ? $settings['admin_bar_remove_comments'] : '0', '1' ); ?> />
									Ocultar Burbuja de Comentarios
								</label>
								<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
									<input type="checkbox" name="wpat_settings[admin_bar_remove_new_content]" value="1" <?php checked( isset( $settings['admin_bar_remove_new_content'] ) ? $settings['admin_bar_remove_new_content'] : '0', '1' ); ?> />
									Ocultar Botón "+ Nuevo"
								</label>
								<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
									<input type="checkbox" name="wpat_settings[admin_bar_remove_updates]" value="1" <?php checked( isset( $settings['admin_bar_remove_updates'] ) ? $settings['admin_bar_remove_updates'] : '0', '1' ); ?> />
									Ocultar Aviso de Actualizaciones
								</label>
								<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 13px;">
									<input type="checkbox" name="wpat_settings[admin_bar_remove_customize]" value="1" <?php checked( isset( $settings['admin_bar_remove_customize'] ) ? $settings['admin_bar_remove_customize'] : '0', '1' ); ?> />
									Ocultar Enlace "Personalizar"
								</label>
							</div>
						</div>

					</div>
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
									<div class="wpat-field-group-row" style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px;">
										<div class="wpat-field-group" style="flex: 1; min-width: 250px;">
											<label for="wpat_dashboard_welcome_title">Título del Bloque de Soporte</label>
											<input type="text" name="wpat_settings[dashboard_welcome_title]" id="wpat_dashboard_welcome_title" value="<?php echo esc_attr( isset( $settings['dashboard_welcome_title'] ) ? $settings['dashboard_welcome_title'] : 'Soporte y Gestión' ); ?>" class="regular-text" style="width: 100%;" />
											<p class="description">Título de la tarjeta de contacto y asistencia en el escritorio.</p>
										</div>
										<div class="wpat-field-group" style="flex: 1; min-width: 250px;">
											<label for="wpat_dashboard_support_email">Email de Destino de Consultas</label>
											<input type="email" name="wpat_settings[dashboard_support_email]" id="wpat_dashboard_support_email" value="<?php echo esc_attr( isset( $settings['dashboard_support_email'] ) ? $settings['dashboard_support_email'] : get_option( 'admin_email' ) ); ?>" class="regular-text" style="width: 100%;" />
											<p class="description">Las consultas enviadas a través del formulario se remitirán a esta dirección.</p>
										</div>
									</div>

									<div class="wpat-field-group" style="margin-top: 15px;">
										<label for="wpat_dashboard_welcome_text">Mensaje de Bienvenida / Ayuda al Cliente</label>
										<textarea name="wpat_settings[dashboard_welcome_text]" id="wpat_dashboard_welcome_text" rows="3" class="large-text" placeholder="Bienvenido al panel de administración de tu sitio web..."><?php echo esc_textarea( isset( $settings['dashboard_welcome_text'] ) ? $settings['dashboard_welcome_text'] : 'Bienvenido al panel de administración de tu sitio web. Si necesitas asistencia, puedes ponerte en contacto con nosotros a través del formulario de soporte.' ); ?></textarea>
										<p class="description">Texto explicativo que verá el usuario en el módulo de soporte del escritorio.</p>
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
											<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[db_card_support]" value="1" <?php checked( isset( $settings['db_card_support'] ) ? $settings['db_card_support'] : '1', '1' ); ?> />
												Tarjeta Soporte & Entorno
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
				require_once WPAT_PATH . 'includes/modules/class-wpat-ssl-fixer.php';
				$ssl_status = WPAT_SSL_Fixer::get_ssl_status();
				$ssl_nonce  = wp_create_nonce( 'wpat_ssl_fixer_nonce' );
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Forzar SSL & Contenido Mixto</h3>
							<p>Garantiza una navegación 100% segura mediante redirección HTTPS automática, resolución de contenido mixto (buffer y directiva CSP) y cabeceras de transporte seguro.</p>
						</div>
						<?php $this->render_module_toggle( 'ssl-fixer', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">

						<!-- 1. Estado en Vivo y Diagnóstico SSL -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
							<h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-shield" style="color: <?php echo $ssl_status['is_https_live'] ? '#10b981' : '#ef4444'; ?>;"></span>
								<?php esc_html_e( 'Diagnóstico del Servidor y Certificado SSL', 'wp-agency-toolkit' ); ?>
							</h4>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 12px;">
								<div style="background: #fff; padding: 10px 14px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
										<?php esc_html_e( 'Conexión Actual', 'wp-agency-toolkit' ); ?>
									</div>
									<div style="font-size: 14px; font-weight: 600; color: <?php echo $ssl_status['is_https_live'] ? '#059669' : '#dc2626'; ?>;">
										<?php echo $ssl_status['is_https_live'] ? '🔒 HTTPS Activo y Seguro' : '⚠️ HTTP Inseguro'; ?>
									</div>
								</div>

								<div style="background: #fff; padding: 10px 14px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
										<?php esc_html_e( 'Detección de Servidor / CDN', 'wp-agency-toolkit' ); ?>
									</div>
									<div style="font-size: 13px; font-weight: 600; color: #334155;">
										<?php echo esc_html( $ssl_status['proxy_type'] ); ?>
									</div>
								</div>

								<div style="background: #fff; padding: 10px 14px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
										<?php esc_html_e( 'URLs Generales de WordPress', 'wp-agency-toolkit' ); ?>
									</div>
									<div style="font-size: 13px; font-weight: 600; color: <?php echo $ssl_status['is_fully_https'] ? '#059669' : '#d97706'; ?>;">
										<?php echo $ssl_status['is_fully_https'] ? '✓ Ambas con https://' : '⚠️ Contienen http://'; ?>
									</div>
								</div>
							</div>

							<?php if ( ! $ssl_status['is_fully_https'] ) : ?>
								<div id="wpat-fix-urls-alert" style="background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; padding: 12px 14px; border-radius: 6px; margin-top: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
									<div>
										<p style="margin: 0; font-size: 13px; color: #92400e; font-weight: 600;">
											<?php esc_html_e( 'La Dirección de WordPress o Dirección del Sitio en los Ajustes Generales todavía utilizan "http://".', 'wp-agency-toolkit' ); ?>
										</p>
										<p style="margin: 2px 0 0 0; font-size: 12px; color: #b45309;">
											<?php esc_html_e( 'Se recomienda actualizarlas a https:// para evitar redirecciones intermedias o alertas de candado roto.', 'wp-agency-toolkit' ); ?>
										</p>
									</div>
									<button type="button" id="wpat-fix-wp-urls-btn" class="button button-primary button-small">
										<?php esc_html_e( 'Actualizar URLs a HTTPS', 'wp-agency-toolkit' ); ?>
									</button>
								</div>
							<?php endif; ?>
						</div>

						<!-- 2. Ajustes de Redirección y Reparación -->
						<div class="wpat-field-group" style="margin-bottom: 20px;">
							<label for="wpat_ssl_redirect_method" style="font-weight: 600; display: block; margin-bottom: 6px;">
								<?php esc_html_e( 'Método de redireccionamiento forzado a HTTPS', 'wp-agency-toolkit' ); ?>
							</label>
							<select name="wpat_settings[ssl_redirect_method]" id="wpat_ssl_redirect_method" style="max-width: 500px; width: 100%;">
								<option value="php" <?php selected( isset( $settings['ssl_redirect_method'] ) ? $settings['ssl_redirect_method'] : 'php', 'php' ); ?>>
									<?php esc_html_e( 'Redirección 301 por PHP (Segura, compatible con Cloudflare, proxies y todos los servidores)', 'wp-agency-toolkit' ); ?>
								</option>
								<option value="htaccess" <?php selected( isset( $settings['ssl_redirect_method'] ) ? $settings['ssl_redirect_method'] : 'php', 'htaccess' ); ?>>
									<?php esc_html_e( 'Redirección 301 por .htaccess (Mayor velocidad, solo Apache / LiteSpeed)', 'wp-agency-toolkit' ); ?>
								</option>
								<option value="none" <?php selected( isset( $settings['ssl_redirect_method'] ) ? $settings['ssl_redirect_method'] : 'php', 'none' ); ?>>
									<?php esc_html_e( 'Desactivada (Solo reparar contenido mixto sin forzar redirección)', 'wp-agency-toolkit' ); ?>
								</option>
							</select>
							<p class="description" style="margin-top: 6px;">
								<?php esc_html_e( 'La redirección por PHP detecta automáticamente terminaciones SSL en balanceadores de carga y proxies como Cloudflare para evitar bucles.', 'wp-agency-toolkit' ); ?>
							</p>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 15px;">
							<label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
								<input type="checkbox" name="wpat_settings[ssl_proxy_fix]" value="1" <?php checked( ! isset( $settings['ssl_proxy_fix'] ) || '1' === (string) $settings['ssl_proxy_fix'] ); ?> />
								<?php esc_html_e( 'Detección inteligente de Proxy Inverso / Cloudflare (Recomendado)', 'wp-agency-toolkit' ); ?>
							</label>
							<p class="description" style="margin-left: 24px;">
								<?php esc_html_e( 'Normaliza las cabeceras HTTP_CF_VISITOR y HTTP_X_FORWARDED_PROTO evitando el error de redirecciones infinitas (ERR_TOO_MANY_REDIRECTS).', 'wp-agency-toolkit' ); ?>
							</p>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 15px;">
							<label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
								<input type="checkbox" name="wpat_settings[ssl_enable_csp]" value="1" <?php checked( ! isset( $settings['ssl_enable_csp'] ) || '1' === (string) $settings['ssl_enable_csp'] ); ?> />
								<?php esc_html_e( 'Activar directiva CSP "Upgrade-Insecure-Requests" (Solución Nativa de Navegador)', 'wp-agency-toolkit' ); ?>
							</label>
							<p class="description" style="margin-left: 24px;">
								<?php esc_html_e( 'Ordena automáticamente a los navegadores web que carguen todas las imágenes, scripts y estilos HTTP a través de HTTPS directamente sin generar avisos de contenido mixto.', 'wp-agency-toolkit' ); ?>
							</p>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 15px;">
							<label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
								<input type="checkbox" name="wpat_settings[ssl_fix_mixed_content]" value="1" <?php checked( ! isset( $settings['ssl_fix_mixed_content'] ) || '1' === (string) $settings['ssl_fix_mixed_content'] ); ?> />
								<?php esc_html_e( 'Corrección Dinámica de Contenido Mixto en Frontend (Buffer de Salida)', 'wp-agency-toolkit' ); ?>
							</label>
							<p class="description" style="margin-left: 24px;">
								<?php esc_html_e( 'Reescribe en tiempo real cualquier enlace o medio con protocolo http:// en el código HTML generado hacia https://.', 'wp-agency-toolkit' ); ?>
							</p>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 25px;">
							<label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
								<input type="checkbox" name="wpat_settings[ssl_enable_hsts]" value="1" <?php checked( ! isset( $settings['ssl_enable_hsts'] ) || '1' === (string) $settings['ssl_enable_hsts'] ); ?> />
								<?php esc_html_e( 'Activar cabecera HSTS (HTTP Strict Transport Security)', 'wp-agency-toolkit' ); ?>
							</label>
							<p class="description" style="margin-left: 24px;">
								<?php esc_html_e( 'Inyecta la directiva Strict-Transport-Security garantizando que los navegadores solo se comuniquen con tu web mediante HTTPS cifrado.', 'wp-agency-toolkit' ); ?>
							</p>
						</div>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- 3. Escáner de Enlaces HTTP en Base de Datos -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
							<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
								<h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-search" style="color: #6366f1;"></span>
									<?php esc_html_e( 'Escáner de Contenido Mixto en Base de Datos', 'wp-agency-toolkit' ); ?>
								</h4>
								<button type="button" id="wpat-scan-mixed-btn" class="button button-secondary button-small" style="display: inline-flex; align-items: center; gap: 4px;">
									<span class="dashicons dashicons-update" style="font-size: 15px; line-height: 20px; width: 15px; height: 15px;"></span>
									<?php esc_html_e( 'Escanear Entradas con HTTP', 'wp-agency-toolkit' ); ?>
								</button>
							</div>
							<p style="font-size: 13px; color: #64748b; margin-top: 0; margin-bottom: 12px;">
								<?php esc_html_e( 'Busca entradas, páginas o recursos antiguos guardados en la base de datos con enlaces "http://" hacia tu propio dominio.', 'wp-agency-toolkit' ); ?>
							</p>

							<div id="wpat-mixed-scan-results" style="display: none;"></div>
						</div>

						<script type="text/javascript">
							document.addEventListener('DOMContentLoaded', function() {
								// Botón corregir URLs generales
								var fixUrlsBtn = document.getElementById('wpat-fix-wp-urls-btn');
								if (fixUrlsBtn) {
									fixUrlsBtn.addEventListener('click', function() {
										if (!confirm('<?php echo esc_js( __( '¿Deseas actualizar la Dirección de WordPress y la Dirección del Sitio a HTTPS?', 'wp-agency-toolkit' ) ); ?>')) return;
										fixUrlsBtn.disabled = true;
										fixUrlsBtn.textContent = '<?php echo esc_js( __( 'Actualizando...', 'wp-agency-toolkit' ) ); ?>';

										var xhr = new XMLHttpRequest();
										xhr.open('POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', true);
										xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
										xhr.onload = function() {
											var resp = JSON.parse(xhr.responseText || '{}');
											var alertBox = document.getElementById('wpat-fix-urls-alert');
											if (resp.success && alertBox) {
												alertBox.style.background = '#f0fdf4';
												alertBox.style.borderColor = '#bbf7d0';
												alertBox.style.borderLeftColor = '#16a34a';
												alertBox.innerHTML = '<p style="margin:0; color:#14532d; font-size:13px; font-weight:600;">✓ ' + (resp.data.message || 'URLs actualizadas con éxito') + '</p>';
											} else {
												alert(resp.data && resp.data.message ? resp.data.message : 'Error al actualizar');
												fixUrlsBtn.disabled = false;
												fixUrlsBtn.textContent = '<?php echo esc_js( __( 'Actualizar URLs a HTTPS', 'wp-agency-toolkit' ) ); ?>';
											}
										};
										xhr.send('action=wpat_fix_wp_urls_https&security=<?php echo esc_js( $ssl_nonce ); ?>');
									});
								}

								// Botón escanear contenido mixto en BD
								var scanBtn = document.getElementById('wpat-scan-mixed-btn');
								if (scanBtn) {
									scanBtn.addEventListener('click', function() {
										scanBtn.disabled = true;
										scanBtn.textContent = '<?php echo esc_js( __( 'Escaneando...', 'wp-agency-toolkit' ) ); ?>';
										var resBox = document.getElementById('wpat-mixed-scan-results');
										resBox.style.display = 'block';
										resBox.innerHTML = '<p style="color:#64748b; font-size:13px;"><?php echo esc_js( __( 'Buscando contenidos con enlaces HTTP...', 'wp-agency-toolkit' ) ); ?></p>';

										var xhr = new XMLHttpRequest();
										xhr.open('POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', true);
										xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
										xhr.onload = function() {
											scanBtn.disabled = false;
											scanBtn.textContent = '<?php echo esc_js( __( 'Escanear Entradas con HTTP', 'wp-agency-toolkit' ) ); ?>';
											var resp = JSON.parse(xhr.responseText || '{}');
											if (resp.success) {
												if (resp.data.count === 0) {
													resBox.innerHTML = '<div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px 14px; border-radius:6px; color:#14532d; font-size:13px; font-weight:600;">✓ ¡Excelente! No se han encontrado entradas o páginas con enlaces HTTP en la base de datos.</div>';
												} else {
													var html = '<div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:6px; padding:12px; margin-top:8px;">';
													html += '<strong style="color:#9f1239; font-size:13px; display:block; margin-bottom:8px;">⚠️ Se han detectado ' + resp.data.count + ' contenidos con referencias "http://":</strong>';
													html += '<ul style="margin:0; padding-left:20px; font-size:13px;">';
													resp.data.items.forEach(function(it) {
														html += '<li style="margin-bottom:4px;"><a href="' + it.edit_link + '" target="_blank" style="font-weight:600; color:#be123c;">' + it.title + '</a> (' + it.type + ')</li>';
													});
													html += '</ul></div>';
													resBox.innerHTML = html;
												}
											} else {
												resBox.innerHTML = '<p style="color:#ef4444; font-size:13px;">Error en el escaneo.</p>';
											}
										};
										xhr.send('action=wpat_scan_mixed_content&security=<?php echo esc_js( $ssl_nonce ); ?>');
									});
								}
							});
						</script>
					</div>
				</div>
				<?php
				break;
			case 'disable-comments':
				$comments_nonce = wp_create_nonce( 'wpat_comments_nonce' );
				$public_cpts    = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );
				$saved_cpts     = isset( $settings['disable_comments_cpts'] ) && is_array( $settings['disable_comments_cpts'] ) ? $settings['disable_comments_cpts'] : array();
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Deshabilitar Comentarios Globales & Anti-Spam</h3>
							<p>Cierra el sistema de comentarios, bloquea inyecciones de spambots por REST API y wp-comments-post.php, y elimina avisos visuales en el panel.</p>
						</div>
						<?php $this->render_module_toggle( 'disable-comments', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">

						<!-- Barra Superior: Enlace Rápido de Gestión -->
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 18px;">
							<div>
								<strong style="color: #1e40af; font-size: 13.5px; display: block;"><?php esc_html_e( 'Gestión de Comentarios de WordPress', 'wp-agency-toolkit' ); ?></strong>
								<span style="color: #3b82f6; font-size: 12px;"><?php esc_html_e( 'Accede a la bandeja nativa de comentarios de WordPress para moderar, responder o eliminarlos.', 'wp-agency-toolkit' ); ?></span>
							</div>
							<a href="<?php echo esc_url( admin_url( 'edit-comments.php' ) ); ?>" class="button button-secondary" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; background: #ffffff; color: #1d4ed8; border-color: #93c5fd;">
								<span class="dashicons dashicons-admin-comments" style="color: #2563eb; font-size: 16px; width: 16px; height: 16px;"></span>
								<?php esc_html_e( 'Ver y Gestionar Comentarios en WordPress', 'wp-agency-toolkit' ); ?>
							</a>
						</div>

						<!-- 1. Alcance de Desactivación -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-admin-settings" style="color: #6366f1;"></span>
							<?php esc_html_e( 'Modo de Desactivación y Alcance', 'wp-agency-toolkit' ); ?>
						</h4>

						<?php
						$current_mode = isset( $settings['disable_comments_mode'] ) ? $settings['disable_comments_mode'] : ( ( ! isset( $settings['disable_comments_global'] ) || '1' === (string) $settings['disable_comments_global'] ) ? 'global' : 'selective' );
						?>

						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
							<div style="display: flex; flex-direction: column; gap: 14px;">
								<!-- Opción 1: Global -->
								<label style="font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
									<input type="radio" name="wpat_settings[disable_comments_mode]" class="wpat-comments-mode-radio" value="global" <?php checked( 'global', $current_mode ); ?> style="margin-top: 3px;" />
									<div>
										<span style="color: #1e293b;"><?php esc_html_e( 'Desactivar en TODO el sitio web (Global / Recomendado)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 4px 0 0 0; font-weight: normal;">
											<?php esc_html_e( 'Cierra completamente el sistema de discusión en entradas, páginas, medios y cualquier contenido, eliminando también la pestaña de comentarios de la administración.', 'wp-agency-toolkit' ); ?>
										</p>
									</div>
								</label>

								<!-- Opción 2: Selectivo -->
								<label style="font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
									<input type="radio" name="wpat_settings[disable_comments_mode]" class="wpat-comments-mode-radio" value="selective" <?php checked( 'selective', $current_mode ); ?> style="margin-top: 3px;" />
									<div>
										<span style="color: #1e293b;"><?php esc_html_e( 'Desactivar de forma selectiva por tipo de contenido (Personalizado)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 4px 0 0 0; font-weight: normal;">
											<?php esc_html_e( 'Elige exactamente en qué tipos de contenido deseas bloquear comentarios y permite que sigan abiertos en los demás.', 'wp-agency-toolkit' ); ?>
										</p>
									</div>
								</label>
							</div>

							<!-- Opciones granulares -->
							<div id="wpat-granular-comments-box" style="margin-top: 16px; padding: 16px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; <?php echo ( 'selective' !== $current_mode ) ? 'display: none;' : ''; ?>">
								<p style="font-weight: 700; margin: 0 0 10px 0; font-size: 13px; color: #1e293b;">
									<?php esc_html_e( 'Selecciona los tipos de contenido donde se desactivarán los comentarios:', 'wp-agency-toolkit' ); ?>
								</p>
								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
									<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
										<input type="checkbox" name="wpat_settings[disable_comments_posts]" value="1" <?php checked( ! isset( $settings['disable_comments_posts'] ) || '1' === (string) $settings['disable_comments_posts'] ); ?> />
										<span><?php esc_html_e( 'Entradas del Blog (Posts)', 'wp-agency-toolkit' ); ?></span>
									</label>
									<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
										<input type="checkbox" name="wpat_settings[disable_comments_pages]" value="1" <?php checked( ! isset( $settings['disable_comments_pages'] ) || '1' === (string) $settings['disable_comments_pages'] ); ?> />
										<span><?php esc_html_e( 'Páginas Estáticas (Pages)', 'wp-agency-toolkit' ); ?></span>
									</label>
									<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
										<input type="checkbox" name="wpat_settings[disable_comments_media]" value="1" <?php checked( ! isset( $settings['disable_comments_media'] ) || '1' === (string) $settings['disable_comments_media'] ); ?> />
										<span><?php esc_html_e( 'Archivos Adjuntos (Media)', 'wp-agency-toolkit' ); ?></span>
									</label>
									<?php foreach ( $public_cpts as $cpt_slug => $cpt_obj ) : ?>
										<?php if ( 'product' === $cpt_slug ) continue; ?>
										<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
											<input type="checkbox" name="wpat_settings[disable_comments_cpts][]" value="<?php echo esc_attr( $cpt_slug ); ?>" <?php checked( in_array( $cpt_slug, $saved_cpts, true ) ); ?> />
											<span><?php echo esc_html( $cpt_obj->labels->name ); ?> (<code><?php echo esc_html( $cpt_slug ); ?></code>)</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<?php if ( class_exists( 'WooCommerce' ) ) : ?>
							<div class="wpat-field-group" style="margin-bottom: 20px;">
								<label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
									<input type="checkbox" name="wpat_settings[disable_comments_keep_reviews]" value="1" <?php checked( ! isset( $settings['disable_comments_keep_reviews'] ) || '1' === (string) $settings['disable_comments_keep_reviews'] ); ?> />
									<?php esc_html_e( 'Preservar las Reseñas y Valoraciones de Productos de WooCommerce', 'wp-agency-toolkit' ); ?>
								</label>
								<p class="description" style="margin-left: 24px;">
									<?php esc_html_e( 'Permite que los clientes sigan dejando opiniones y estrellas en la tienda WooCommerce mientras los comentarios del resto del sitio permanecen cerrados.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>
						<?php endif; ?>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- 2. Protecciones Activas -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-shield-alt" style="color: #10b981;"></span>
							<?php esc_html_e( 'Protecciones Anti-Spam Integradas', 'wp-agency-toolkit' ); ?>
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-bottom: 24px;">
							<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px;">
								<strong style="color: #15803d; font-size: 13px; display: flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Bloqueo de wp-comments-post.php', 'wp-agency-toolkit' ); ?>
								</strong>
								<p style="margin: 4px 0 0 0; font-size: 12px; color: #166534;">
									<?php esc_html_e( 'Rechaza con HTTP 403 los envíos POST directos que los spambots envían sin pasar por el navegador.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>

							<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px;">
								<strong style="color: #15803d; font-size: 13px; display: flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Bloqueo en API REST', 'wp-agency-toolkit' ); ?>
								</strong>
								<p style="margin: 4px 0 0 0; font-size: 12px; color: #166534;">
									<?php esc_html_e( 'Desactiva las rutas /wp/v2/comments cerrando el vector de inyección de comentarios por JSON.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>

							<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px;">
								<strong style="color: #15803d; font-size: 13px; display: flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Bloqueo de Pingbacks XML-RPC', 'wp-agency-toolkit' ); ?>
								</strong>
								<p style="margin: 4px 0 0 0; font-size: 12px; color: #166534;">
									<?php esc_html_e( 'Neutraliza ataques de amplificación DDoS y trackbacks basura a través del protocolo XML-RPC.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>
						</div>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- 3. Mantenimiento y Purga de Comentarios en Base de Datos -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px;">
							<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
								<h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-trash" style="color: #ef4444;"></span>
									<?php esc_html_e( 'Limpieza de Comentarios Existentes en la Base de Datos', 'wp-agency-toolkit' ); ?>
								</h4>
								<button type="button" id="wpat-comments-refresh-btn" class="button button-secondary button-small" style="display: inline-flex; align-items: center; gap: 4px;">
									<span class="dashicons dashicons-update" style="font-size: 15px; line-height: 20px; width: 15px; height: 15px;"></span>
									<?php esc_html_e( 'Actualizar Conteo', 'wp-agency-toolkit' ); ?>
								</button>
							</div>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px;">
								<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
										<?php esc_html_e( 'Total en Base de Datos', 'wp-agency-toolkit' ); ?>
									</div>
									<div id="wpat-comment-stat-total" style="font-size: 18px; font-weight: bold; color: #1e293b;">--</div>
								</div>
								<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
										<?php esc_html_e( 'Aprobados', 'wp-agency-toolkit' ); ?>
									</div>
									<div id="wpat-comment-stat-approved" style="font-size: 18px; font-weight: bold; color: #059669;">--</div>
								</div>
								<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
										<?php esc_html_e( 'Detectados como Spam', 'wp-agency-toolkit' ); ?>
									</div>
									<div id="wpat-comment-stat-spam" style="font-size: 18px; font-weight: bold; color: #dc2626;">--</div>
								</div>
								<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
										<?php esc_html_e( 'En la Papelera', 'wp-agency-toolkit' ); ?>
									</div>
									<div id="wpat-comment-stat-trash" style="font-size: 18px; font-weight: bold; color: #d97706;">--</div>
								</div>
							</div>

							<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
								<p style="font-size: 13px; color: #64748b; margin: 0;">
									<?php esc_html_e( 'Si tu sitio acumuló spam o comentarios antiguos, puedes vaciar las tablas wp_comments y wp_commentmeta por completo.', 'wp-agency-toolkit' ); ?>
								</p>
								<button type="button" id="wpat-btn-delete-all-comments" class="button button-secondary" style="color: #be123c; border-color: #fecdd3; background: #fff1f2;">
									<span class="dashicons dashicons-trash" style="font-size: 16px; line-height: 24px; vertical-align: middle;"></span>
									<?php esc_html_e( 'Purgar Todos los Comentarios de la BD', 'wp-agency-toolkit' ); ?>
								</button>
							</div>

							<div id="wpat-comments-action-result" style="font-size: 13px; margin-top: 10px; font-weight: 600;"></div>
						</div>

						<script type="text/javascript">
							document.addEventListener('DOMContentLoaded', function() {
								var modeRadios = document.querySelectorAll('.wpat-comments-mode-radio');
								var granularBox = document.getElementById('wpat-granular-comments-box');
								if (modeRadios.length && granularBox) {
									modeRadios.forEach(function(r) {
										r.addEventListener('change', function() {
											if (this.checked) {
												granularBox.style.display = (this.value === 'selective') ? 'block' : 'none';
											}
										});
									});
								}

								var nonce = '<?php echo esc_js( $comments_nonce ); ?>';
								var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

								function updateCommentStats() {
									var statTotal = document.getElementById('wpat-comment-stat-total');
									var statApproved = document.getElementById('wpat-comment-stat-approved');
									var statSpam = document.getElementById('wpat-comment-stat-spam');
									var statTrash = document.getElementById('wpat-comment-stat-trash');

									if (statTotal) statTotal.textContent = '...';
									if (statApproved) statApproved.textContent = '...';
									if (statSpam) statSpam.textContent = '...';
									if (statTrash) statTrash.textContent = '...';

									var xhr = new XMLHttpRequest();
									xhr.open('POST', ajaxUrl, true);
									xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
									xhr.onload = function() {
										var resp = JSON.parse(xhr.responseText || '{}');
										if (resp.success) {
											if (statTotal) statTotal.textContent = resp.data.total;
											if (statApproved) statApproved.textContent = resp.data.approved;
											if (statSpam) statSpam.textContent = resp.data.spam;
											if (statTrash) statTrash.textContent = resp.data.trash;
										}
									};
									xhr.send('action=wpat_comments_get_stats&security=' + encodeURIComponent(nonce));
								}

								updateCommentStats();

								var refreshBtn = document.getElementById('wpat-comments-refresh-btn');
								if (refreshBtn) {
									refreshBtn.addEventListener('click', updateCommentStats);
								}

								var deleteBtn = document.getElementById('wpat-btn-delete-all-comments');
								if (deleteBtn) {
									deleteBtn.addEventListener('click', function() {
										if (!confirm('<?php echo esc_js( __( '¡ATENCIÓN! Esta acción eliminará permanentemente TODOS los comentarios y valoraciones existentes de la base de datos de WordPress. ¿Deseas continuar?', 'wp-agency-toolkit' ) ); ?>')) return;

										deleteBtn.disabled = true;
										deleteBtn.textContent = '<?php echo esc_js( __( 'Eliminando...', 'wp-agency-toolkit' ) ); ?>';
										var resBox = document.getElementById('wpat-comments-action-result');

										var xhr = new XMLHttpRequest();
										xhr.open('POST', ajaxUrl, true);
										xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
										xhr.onload = function() {
											deleteBtn.disabled = false;
											deleteBtn.textContent = '<?php echo esc_js( __( 'Purgar Todos los Comentarios de la BD', 'wp-agency-toolkit' ) ); ?>';
											var resp = JSON.parse(xhr.responseText || '{}');
											if (resp.success) {
												if (resBox) {
													resBox.style.color = '#15803d';
													resBox.textContent = '✓ ' + (resp.data.message || 'Comentarios eliminados con éxito.');
												}
												updateCommentStats();
											} else {
												if (resBox) {
													resBox.style.color = '#b91c1c';
													resBox.textContent = '⚠️ ' + (resp.data.message || 'Error al eliminar.');
												}
											}
										};
										xhr.send('action=wpat_comments_delete_all&security=' + encodeURIComponent(nonce));
									});
								}
							});
						</script>
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

									<div class="wpat-field-group">
										<label>
											<input type="checkbox" name="wpat_settings[sec_security_headers]" value="1" <?php checked( isset( $settings['sec_security_headers'] ) ? $settings['sec_security_headers'] : '0', '1' ); ?>>
											Cabeceras de seguridad HTTP (Añade X-Frame-Options, X-Content-Type-Options, Referrer-Policy y X-XSS-Protection para mitigar Clickjacking y MIME sniffing)
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
												$f_position    = esc_attr( isset( $cf['position'] ) ? $cf['position'] : 'end_of_section' );
												$f_width       = esc_attr( isset( $cf['width'] ) ? $cf['width'] : 'full' );
												$f_options     = esc_textarea( isset( $cf['options'] ) ? $cf['options'] : '' );
												?>
												<div class="wpat-custom-field-row" style="background: #ffffff; border: 1px solid var(--wpat-border); padding: 12px 15px; border-radius: 6px; margin-bottom: 10px;">
													<div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1.2fr auto; gap: 10px; align-items: center;">
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
																<option value="end_of_section" <?php selected( $f_position, 'end_of_section' ); ?>>Al final de Sección (Línea 7+)</option>
																<option value="after_email" <?php selected( $f_position, 'after_email' ); ?>>Después de Email (Línea 1)</option>
																<option value="after_names" <?php selected( $f_position, 'after_names' ); ?>>Después de Nombre/Apellidos (Línea 2)</option>
																<option value="after_country_state" <?php selected( $f_position, 'after_country_state' ); ?>>Después de País/Provincia (Línea 3)</option>
																<option value="after_city_postcode" <?php selected( $f_position, 'after_city_postcode' ); ?>>Después de Población/CP (Línea 4)</option>
																<option value="after_address" <?php selected( $f_position, 'after_address' ); ?>>Después de Dirección (Línea 5)</option>
																<option value="after_phone" <?php selected( $f_position, 'after_phone' ); ?>>Después de Teléfono (Línea 6)</option>
																<option value="after_company" <?php selected( $f_position, 'after_company' ); ?>>Después de Empresa</option>
															</select>
														</div>
														<div>
															<label style="font-size: 11px; display: block; font-weight: 600;">Ancho (Distribución)</label>
															<select name="wpat_settings[checkout_custom_fields][<?php echo $index; ?>][width]" style="width: 100%;">
																<option value="full" <?php selected( $f_width, 'full' ); ?>>100% (Línea sola)</option>
																<option value="half" <?php selected( $f_width, 'half' ); ?>>50% (Al lado)</option>
															</select>
														</div>
														<div style="text-align: right; padding-top: 15px; display: flex; gap: 4px; justify-content: flex-end;">
															<button type="button" class="button button-small wpat-move-up-btn" title="Subir posición">▲</button>
															<button type="button" class="button button-small wpat-move-down-btn" title="Bajar posición">▼</button>
															<button type="button" class="button button-link-delete wpat-remove-field-btn" style="color: #ef4444;" title="Eliminar campo">Eliminar</button>
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

										function reindexFields() {
											var rows = container.querySelectorAll('.wpat-custom-field-row');
											rows.forEach(function(row, idx) {
												row.querySelectorAll('input, select, textarea').forEach(function(input) {
													var name = input.getAttribute('name');
													if (name) {
														input.setAttribute('name', name.replace(/\[checkout_custom_fields\]\[\d+\]/, '[checkout_custom_fields][' + idx + ']'));
													}
												});
											});
										}

										addBtn.addEventListener('click', function() {
											if (noMsg) noMsg.style.display = 'none';
											var index = container.querySelectorAll('.wpat-custom-field-row').length;
											var html = '<div class="wpat-custom-field-row" style="background: #ffffff; border: 1px solid var(--wpat-border); padding: 12px 15px; border-radius: 6px; margin-bottom: 10px;">' +
												'<div style="display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1.2fr auto; gap: 10px; align-items: center;">' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Nombre del Campo (Etiqueta)</label><input type="text" name="wpat_settings[checkout_custom_fields][' + index + '][label]" value="" class="regular-text" placeholder="Ej. Horario de Preferencia" required style="width: 100%;" /></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Identificador Único (Key)</label><input type="text" name="wpat_settings[checkout_custom_fields][' + index + '][key]" value="" class="regular-text" placeholder="ej. horario_entrega" style="width: 100%;" /></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Tipo de Campo</label><select name="wpat_settings[checkout_custom_fields][' + index + '][type]" class="wpat-field-type-select" style="width: 100%;"><option value="text">Texto Corto</option><option value="select">Desplegable (Select)</option><option value="radio">Radio (Botones de Opción)</option><option value="textarea">Área de Texto</option><option value="checkbox">Casilla (Checkbox)</option><option value="date">Fecha (Calendario)</option></select></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Sección</label><select name="wpat_settings[checkout_custom_fields][' + index + '][section]" style="width: 100%;"><option value="billing">Facturación</option><option value="shipping">Envío</option><option value="order">Notas Adicionales</option></select></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Posición</label><select name="wpat_settings[checkout_custom_fields][' + index + '][position]" style="width: 100%;"><option value="end_of_section">Al final de Sección (Línea 7+)</option><option value="after_email">Después de Email (Línea 1)</option><option value="after_names">Después de Nombre/Apellidos (Línea 2)</option><option value="after_country_state">Después de País/Provincia (Línea 3)</option><option value="after_city_postcode">Después de Población/CP (Línea 4)</option><option value="after_address">Después de Dirección (Línea 5)</option><option value="after_phone">Después de Teléfono (Línea 6)</option><option value="after_company">Después de Empresa</option></select></div>' +
													'<div><label style="font-size: 11px; display: block; font-weight: 600;">Ancho (Distribución)</label><select name="wpat_settings[checkout_custom_fields][' + index + '][width]" style="width: 100%;"><option value="full">100% (Línea sola)</option><option value="half">50% (Al lado)</option></select></div>' +
													'<div style="text-align: right; padding-top: 15px; display: flex; gap: 4px; justify-content: flex-end;"><button type="button" class="button button-small wpat-move-up-btn" title="Subir posición">▲</button><button type="button" class="button button-small wpat-move-down-btn" title="Bajar posición">▼</button><button type="button" class="button button-link-delete wpat-remove-field-btn" style="color: #ef4444;" title="Eliminar campo">Eliminar</button></div>' +
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
											reindexFields();
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
											if (e.target && e.target.classList.contains('wpat-move-up-btn')) {
												var row = e.target.closest('.wpat-custom-field-row');
												if (row && row.previousElementSibling && row.previousElementSibling.classList.contains('wpat-custom-field-row')) {
													row.parentNode.insertBefore(row, row.previousElementSibling);
													reindexFields();
												}
											} else if (e.target && e.target.classList.contains('wpat-move-down-btn')) {
												var row = e.target.closest('.wpat-custom-field-row');
												if (row && row.nextElementSibling && row.nextElementSibling.classList.contains('wpat-custom-field-row')) {
													row.parentNode.insertBefore(row.nextElementSibling, row);
													reindexFields();
												}
											} else if (e.target && e.target.classList.contains('wpat-remove-field-btn')) {
												var row = e.target.closest('.wpat-custom-field-row');
												if (row) {
													row.remove();
													reindexFields();
												}
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
												var rIndex = new Date().getTime();
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
												var fIndex = new Date().getTime() + '_' + Math.floor(Math.random() * 1000);

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
										<label style="font-weight: 600; display: block; margin-bottom: 5px;">Texto de Marcador de Posición (Placeholder):</label>
										<?php $ls_ph = isset( $settings['live_search_placeholder'] ) ? esc_attr( $settings['live_search_placeholder'] ) : 'Buscar productos por nombre, SKU o categoría...'; ?>
										<input type="text" name="wpat_settings[live_search_placeholder]" value="<?php echo $ls_ph; ?>" class="regular-text" style="width: 100%; max-width: 500px;" />
										<p class="description">Texto visible en el campo de búsqueda antes de que el usuario empiece a escribir.</p>
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
				$facets_cfg          = isset( $settings['facets_config'] ) && is_array( $settings['facets_config'] ) ? $settings['facets_config'] : array( 'sort', 'price', 'category', 'stock', 'rating' );
				$facets_accent_color = isset( $settings['woo_facets_accent_color'] ) ? $settings['woo_facets_accent_color'] : '#2563eb';
				$facets_card_bg      = isset( $settings['woo_facets_card_bg'] ) ? $settings['woo_facets_card_bg'] : '#ffffff';
				$facets_border_color = isset( $settings['woo_facets_border_color'] ) ? $settings['woo_facets_border_color'] : '#e2e8f0';
				$facets_badge_bg     = isset( $settings['woo_facets_badge_bg'] ) ? $settings['woo_facets_badge_bg'] : '#f1f5f9';
				$facets_radius       = isset( $settings['woo_facets_border_radius'] ) ? intval( $settings['woo_facets_border_radius'] ) : 12;
				$facets_active_tags  = ! isset( $settings['woo_facets_show_active_tags'] ) || '1' === $settings['woo_facets_show_active_tags'];
				$facets_sticky       = ! empty( $settings['woo_facets_sticky'] ) && '1' === $settings['woo_facets_sticky'];
				?>
				<div class="wpat-module-card" style="margin-top: 20px;">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Filtro por Facetas</h3>
							<p>Permite a los usuarios filtrar productos instantáneamente por Precio, Atributos (Colores/Tallas), Categorías, Stock, Rating u Ordenación sin recargar la página (shortcode <code>[wpat_product_facets]</code>).</p>
						</div>
						<?php $this->render_module_toggle( 'woo-facets', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">

						<div class="wpat-field-group" style="margin-top: 15px; background: #f8fafc; border: 1px solid var(--wpat-border); padding: 15px; border-radius: 8px;">
							<h4 style="margin: 0 0 10px 0; font-size: 13.5px; font-weight: 700;">🎛️ Facetas a Habilitar en el Widget / Sidebar:</h4>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
								<label style="font-size: 13px; font-weight: 600;">
									<input type="checkbox" name="wpat_settings[facets_config][]" value="sort" <?php checked( in_array( 'sort', $facets_cfg, true ) ); ?>>
									🔃 Selector de Ordenación
								</label>
								<label style="font-size: 13px; font-weight: 600;">
									<input type="checkbox" name="wpat_settings[facets_config][]" value="price" <?php checked( in_array( 'price', $facets_cfg, true ) ); ?>>
									💰 Rango de Precio (€)
								</label>
								<label style="font-size: 13px; font-weight: 600;">
									<input type="checkbox" name="wpat_settings[facets_config][]" value="category" <?php checked( in_array( 'category', $facets_cfg, true ) ); ?>>
									🏷️ Categorías de Producto
								</label>
								<label style="font-size: 13px; font-weight: 600;">
									<input type="checkbox" name="wpat_settings[facets_config][]" value="attribute" <?php checked( in_array( 'attribute', $facets_cfg, true ) ); ?>>
									🎨 Atributos (Color, Talla...)
								</label>
								<label style="font-size: 13px; font-weight: 600;">
									<input type="checkbox" name="wpat_settings[facets_config][]" value="stock" <?php checked( in_array( 'stock', $facets_cfg, true ) ); ?>>
									📦 Stock y En Oferta
								</label>
								<label style="font-size: 13px; font-weight: 600;">
									<input type="checkbox" name="wpat_settings[facets_config][]" value="rating" <?php checked( in_array( 'rating', $facets_cfg, true ) ); ?>>
									⭐️ Valoración (Estrellas)
								</label>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 20px 0;" />

						<div class="wpat-field-group">
							<h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 700;">🎨 Personalización de Estilo y Colores del Widget:</h4>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color de Acento / Selección:</label>
									<input type="text" name="wpat_settings[woo_facets_accent_color]" value="<?php echo esc_attr( $facets_accent_color ); ?>" class="wpat-color-picker" data-default-color="#2563eb">
								</div>
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Fondo del Widget:</label>
									<input type="text" name="wpat_settings[woo_facets_card_bg]" value="<?php echo esc_attr( $facets_card_bg ); ?>" class="wpat-color-picker" data-default-color="#ffffff">
								</div>
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color del Borde:</label>
									<input type="text" name="wpat_settings[woo_facets_border_color]" value="<?php echo esc_attr( $facets_border_color ); ?>" class="wpat-color-picker" data-default-color="#e2e8f0">
								</div>
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Fondo de Contadores (Badges):</label>
									<input type="text" name="wpat_settings[woo_facets_badge_bg]" value="<?php echo esc_attr( $facets_badge_bg ); ?>" class="wpat-color-picker" data-default-color="#f1f5f9">
								</div>
								<div class="wpat-field-group">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Radio de Bordes (px):</label>
									<input type="number" min="0" max="30" name="wpat_settings[woo_facets_border_radius]" value="<?php echo esc_attr( $facets_radius ); ?>" class="regular-text" style="width: 100%;">
								</div>
							</div>

							<div class="wpat-field-group" style="margin-top: 15px;">
								<label style="font-weight: 600;">
									<input type="checkbox" name="wpat_settings[woo_facets_show_active_tags]" value="1" <?php checked( $facets_active_tags ); ?>>
									Mostrar barra de "Filtros Activos" con píldoras desmarcables (<code>×</code>) sobre el formulario
								</label>
							</div>
							<div class="wpat-field-group" style="margin-top: 10px;">
								<label style="font-weight: 600;">
									<input type="checkbox" name="wpat_settings[woo_facets_sticky]" value="1" <?php checked( $facets_sticky ); ?>>
									Hacer la sección de facetas pegajosa (Sticky Sidebar) al hacer scroll por la lista de productos
								</label>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 20px 0;" />

						<div class="wpat-field-group">
							<label style="font-weight: 600; display: block; margin-bottom: 5px;">Uso mediante Shortcode:</label>
							<code>[wpat_product_facets title="Filtrar Productos"]</code>
							<p class="description" style="margin-top: 4px;">Inserta este shortcode en la barra lateral (Sidebar) o plantilla de la tienda.</p>
						</div>

					</div>
				</div>
				<?php
				break;
			case 'woo-promotions':
			case 'woo_promotions':
				$promo_rules  = isset( $settings['woo_promotions_rules'] ) && is_array( $settings['woo_promotions_rules'] ) ? $settings['woo_promotions_rules'] : array();
				$product_cats = taxonomy_exists( 'product_cat' ) ? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) : array();
				$gateways     = function_exists( 'WC' ) && WC()->payment_gateways ? WC()->payment_gateways->get_available_payment_gateways() : array();
				if ( empty( $gateways ) && function_exists( 'WC' ) && WC()->payment_gateways ) {
					$gateways = WC()->payment_gateways->payment_gateways();
				}
				?>
				<div class="wpat-module-card" style="margin-top: 20px;">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Promociones Dinámicas y Descuentos</h3>
							<p>Crea y gestiona reglas de descuento inteligentes en el carrito: tramos de gasto, 3x2 / Compra X Paga Y, volumen por cantidad, descuento global y por método de pago.</p>
						</div>
						<?php $this->render_module_toggle( 'woo-promotions', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">

						<div class="wpat-field-group" style="margin-top: 15px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 16px; border-radius: 8px;">
								<div style="display: flex; gap: 8px; align-items: center; flex-grow: 1;">
									<input type="text" id="wpat_promo_search_input" placeholder="Buscar regla por título..." class="regular-text" style="font-size: 12.5px; height: 34px; max-width: 280px;" autocomplete="off" />
									<button type="button" class="button button-small" id="wpat_promo_expand_all_btn">Desplegar Todas</button>
									<button type="button" class="button button-small" id="wpat_promo_collapse_all_btn">Plegar Todas</button>
								</div>
								<button type="button" class="button button-primary" id="wpat_add_promo_rule_btn" style="background: var(--wpat-accent, #2563eb); border-color: #1d4ed8;">+ Añadir Nueva Regla Promocional</button>
							</div>

							<div id="wpat_promo_rules_container" style="display: flex; flex-direction: column; gap: 15px;">
								<?php
								if ( empty( $promo_rules ) ) :
									?>
									<div id="wpat_promo_empty_msg" style="text-align: center; padding: 30px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 10px; color: #64748b;">
										<p style="font-size: 15px; font-weight: 600; margin-bottom: 5px;">No hay reglas promocionales creadas todavía</p>
										<p style="font-size: 13px; margin: 0;">Haz clic en el botón <strong>"+ Añadir Nueva Regla Promocional"</strong> para crear tu primera promoción.</p>
									</div>
									<?php
								else :
									foreach ( $promo_rules as $idx => $rule ) :
										$r_id          = ! empty( $rule['id'] ) ? $rule['id'] : 'rule_' . $idx;
										$r_title       = ! empty( $rule['title'] ) ? $rule['title'] : 'Regla Promocional #' . ( $idx + 1 );
										$r_active      = isset( $rule['active'] ) && '1' === (string) $rule['active'];
										$r_type        = ! empty( $rule['type'] ) ? $rule['type'] : 'global_discount';
										$r_scope       = ! empty( $rule['scope'] ) ? $rule['scope'] : 'all';
										$r_cats        = isset( $rule['categories'] ) && is_array( $rule['categories'] ) ? $rule['categories'] : array();
										$r_prods       = isset( $rule['products'] ) && is_array( $rule['products'] ) ? implode( ', ', $rule['products'] ) : '';
										$r_ignore_sale   = ! empty( $rule['ignore_on_sale'] ) && '1' === (string) $rule['ignore_on_sale'];
										$r_include_extra = ! empty( $rule['include_extra_options'] ) && '1' === (string) $rule['include_extra_options'];
										$r_show_cd       = ! empty( $rule['show_countdown'] ) && '1' === (string) $rule['show_countdown'];
										$r_icon        = ! empty( $rule['icon'] ) ? $rule['icon'] : 'gift';
										$r_icon_symbol = class_exists( 'WPAT_Woo_Promotions' ) ? WPAT_Woo_Promotions::get_promo_icon_symbol( $r_icon ) : '🎁';

										$r_start_full = ! empty( $rule['start_date'] ) ? $rule['start_date'] : '';
										$r_start_date = '';
										$s_h          = '00';
										$s_m          = '00';
										if ( ! empty( $r_start_full ) ) {
											$s_parts      = explode( ' ', str_replace( 'T', ' ', trim( $r_start_full ) ) );
											$r_start_date = $s_parts[0];
											if ( isset( $s_parts[1] ) ) {
												$st_parts = explode( ':', $s_parts[1] );
												$s_h      = isset( $st_parts[0] ) ? str_pad( (string) min( 23, max( 0, (int) $st_parts[0] ) ), 2, '0', STR_PAD_LEFT ) : '00';
												$s_m      = isset( $st_parts[1] ) ? str_pad( (string) min( 59, max( 0, (int) $st_parts[1] ) ), 2, '0', STR_PAD_LEFT ) : '00';
											}
										}

										$r_end_full = ! empty( $rule['end_date'] ) ? $rule['end_date'] : '';
										$r_end_date = '';
										$e_h        = '23';
										$e_m        = '59';
										if ( ! empty( $r_end_full ) ) {
											$e_parts    = explode( ' ', str_replace( 'T', ' ', trim( $r_end_full ) ) );
											$r_end_date = $e_parts[0];
											if ( isset( $e_parts[1] ) ) {
												$et_parts = explode( ':', $e_parts[1] );
												$e_h      = isset( $et_parts[0] ) ? str_pad( (string) min( 23, max( 0, (int) $et_parts[0] ) ), 2, '0', STR_PAD_LEFT ) : '23';
												$e_m      = isset( $et_parts[1] ) ? str_pad( (string) min( 59, max( 0, (int) $et_parts[1] ) ), 2, '0', STR_PAD_LEFT ) : '59';
											}
										}

										$r_pay_methods = isset( $rule['payment_methods'] ) && is_array( $rule['payment_methods'] ) ? $rule['payment_methods'] : array();
										$r_tiers       = isset( $rule['tiers'] ) && is_array( $rule['tiers'] ) ? $rule['tiers'] : array();
										?>
										<div class="wpat-promo-rule-card" data-rule-idx="<?php echo esc_attr( $idx ); ?>" style="border: 1px solid #cbd5e1; border-radius: 10px; background: #ffffff; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
											<input type="hidden" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][id]" value="<?php echo esc_attr( $r_id ); ?>" />
											
											<!-- Header de la Tarjeta de Regla -->
											<div class="wpat-promo-rule-header" style="background: #f8fafc; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; border-bottom: 1px solid #e2e8f0; user-select: none;">
												<div style="display: flex; align-items: center; gap: 10px; flex-grow: 1;">
													<span class="wpat-promo-icon-display" style="font-size: 16px; min-width: 20px;"><?php echo esc_html( $r_icon_symbol ); ?></span>
													<strong class="wpat-promo-rule-header-title" style="font-size: 14px; color: #0f172a;"><?php echo esc_html( $r_title ); ?></strong>
													<span class="wpat-promo-type-badge" style="background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; text-transform: uppercase;">
														<?php
														$type_labels = array(
															'tiered_spend'    => 'Tramos Gasto',
															'bxgy'            => '3x2 / Compra X Paga Y',
															'bulk_qty'        => 'Volumen',
															'global_discount' => 'Descuento Global',
															'payment_method'  => 'Método Pago',
														);
														echo esc_html( isset( $type_labels[ $r_type ] ) ? $type_labels[ $r_type ] : $r_type );
														?>
													</span>
												</div>

												<div style="display: flex; align-items: center; gap: 12px;">
													<label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; margin: 0; cursor: pointer;">
														<input type="checkbox" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][active]" value="1" <?php checked( $r_active ); ?> />
														<span style="<?php echo $r_active ? 'color:#16a34a;' : 'color:#94a3b8;'; ?>"><?php echo $r_active ? 'Activa' : 'Inactiva'; ?></span>
													</label>
													<button type="button" class="wpat-promo-toggle-body-btn button button-small" style="font-size: 11px;">Plegar</button>
													<button type="button" class="wpat-promo-delete-rule-btn button button-small" style="color: #ef4444; border-color: #fca5a5;">Eliminar</button>
												</div>
											</div>

											<!-- Cuerpo de la Tarjeta de Regla (Desplegable) -->
											<div class="wpat-promo-rule-body" style="padding: 20px; display: block;">
												
												<!-- Fila 1: Título Público, Icono, Tipo de Promoción y Ámbito -->
												<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 15px;">
													<div>
														<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">Título Público (visible en carrito/checkout):</label>
														<input type="text" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][title]" value="<?php echo esc_attr( $r_title ); ?>" class="wpat-promo-title-input regular-text" style="width: 100%;" required />
													</div>
													<div>
														<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">Icono de Promoción (Opcional):</label>
														<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][icon]" class="wpat-promo-icon-select regular-text" style="width: 100%;">
															<option value="gift" <?php selected( $r_icon, 'gift' ); ?>>🎁 Regalo (Genérico)</option>
															<option value="fire" <?php selected( $r_icon, 'fire' ); ?>>🔥 Oferta Flash / Tendencia</option>
															<option value="percent" <?php selected( $r_icon, 'percent' ); ?>>🏷️ Descuento % (Black Friday / Cyber)</option>
															<option value="heart" <?php selected( $r_icon, 'heart' ); ?>>❤️ Corazón (San Valentín)</option>
															<option value="pumpkin" <?php selected( $r_icon, 'pumpkin' ); ?>>🎃 Calabaza (Halloween)</option>
															<option value="skull" <?php selected( $r_icon, 'skull' ); ?>>💀 Calavera / Esqueleto (Halloween)</option>
															<option value="tree" <?php selected( $r_icon, 'tree' ); ?>>🎄 Árbol de Navidad (Navidad)</option>
															<option value="santa" <?php selected( $r_icon, 'santa' ); ?>>🎅 Papá Noel (Navidad)</option>
															<option value="shopping_bags" <?php selected( $r_icon, 'shopping_bags' ); ?>>🛍️ Compras (Rebajas)</option>
															<option value="star" <?php selected( $r_icon, 'star' ); ?>>⭐ Estrella (Especial)</option>
															<option value="crown" <?php selected( $r_icon, 'crown' ); ?>>👑 VIP / Exclusivo</option>
															<option value="lightning" <?php selected( $r_icon, 'lightning' ); ?>>⚡ Relámpago</option>
															<option value="sun" <?php selected( $r_icon, 'sun' ); ?>>☀️ Verano</option>
															<option value="flower" <?php selected( $r_icon, 'flower' ); ?>>🌸 Primavera / Día de la Madre</option>
															<option value="none" <?php selected( $r_icon, 'none' ); ?>>Sin icono (Desactivado)</option>
														</select>
													</div>
													<div>
														<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">Tipo de Promoción:</label>
														<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][type]" class="wpat-promo-type-select regular-text" style="width: 100%;">
															<option value="tiered_spend" <?php selected( $r_type, 'tiered_spend' ); ?>>Descuento por tramos de gasto</option>
															<option value="bxgy" <?php selected( $r_type, 'bxgy' ); ?>>Compra X, Paga Y (3x2)</option>
															<option value="bulk_qty" <?php selected( $r_type, 'bulk_qty' ); ?>>Descuento por volumen / cantidad</option>
															<option value="global_discount" <?php selected( $r_type, 'global_discount' ); ?>>Descuento porcentual o fijo global</option>
															<option value="payment_method" <?php selected( $r_type, 'payment_method' ); ?>>Descuento por método de pago</option>
														</select>
													</div>
													<div>
														<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">Ámbito de Aplicación:</label>
														<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][scope]" class="wpat-promo-scope-select regular-text" style="width: 100%;">
															<option value="all" <?php selected( $r_scope, 'all' ); ?>>Toda la tienda</option>
															<option value="category" <?php selected( $r_scope, 'category' ); ?>>Categorías específicas</option>
															<option value="product" <?php selected( $r_scope, 'product' ); ?>>Productos / Variaciones específicas</option>
														</select>
													</div>
												</div>

												<!-- Fila 2: Categorías / Productos según Ámbito -->
												<div class="wpat-promo-scope-categories-box" style="margin-bottom: 15px; <?php echo 'category' === $r_scope ? '' : 'display:none;'; ?> background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px;">
													<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 6px;">Seleccionar Categorías de Producto Elegibles:</label>
													<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; max-height: 140px; overflow-y: auto; padding: 5px;">
														<?php
														if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) {
															foreach ( $product_cats as $cat ) {
																$is_checked = in_array( (int) $cat->term_id, array_map( 'intval', $r_cats ), true );
																echo '<label style="font-size: 12px; font-weight: 500;"><input type="checkbox" name="wpat_settings[woo_promotions_rules][' . esc_attr( $idx ) . '][categories][]" value="' . esc_attr( $cat->term_id ) . '" ' . checked( $is_checked, true, false ) . '> ' . esc_html( $cat->name ) . '</label>';
															}
														} else {
															echo '<span style="font-size: 12px; color: #94a3b8;">No se encontraron categorías de producto en WooCommerce.</span>';
														}
														?>
													</div>
												</div>

												<div class="wpat-promo-scope-products-box" style="margin-bottom: 15px; <?php echo 'product' === $r_scope ? '' : 'display:none;'; ?> background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px;">
													<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">IDs de Productos o Variaciones (separados por coma):</label>
													<input type="text" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][products]" value="<?php echo esc_attr( $r_prods ); ?>" placeholder="Ej: 102, 105, 120" class="regular-text" style="width: 100%;" />
													<p class="description" style="margin-top: 4px; font-size: 11px;">Introduce los IDs numéricos de los productos a los que se aplicará esta regla.</p>
												</div>

												<!-- Fila 3: Parámetros específicos por tipo -->
												<!-- 3.1 Tiered Spend -->
												<div class="wpat-promo-fields-tiered_spend" style="<?php echo 'tiered_spend' === $r_type ? '' : 'display:none;'; ?> background: #eff6ff; border: 1px solid #bfdbfe; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
													<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #1e40af;">Tramos de Descuento por Gasto Acumulado en Carrito:</h4>
													<div class="wpat-promo-tiers-list" style="display: flex; flex-direction: column; gap: 8px;">
														<?php
														if ( ! empty( $r_tiers ) ) :
															foreach ( $r_tiers as $t_idx => $tier ) :
																?>
																<div class="wpat-promo-tier-row" style="display: flex; gap: 10px; align-items: center; background: #ffffff; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
																	<span style="font-size: 12px; font-weight: 600;">Subtotal Mínimo:</span>
																	<input type="number" step="0.01" min="0" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][tiers][<?php echo esc_attr( $t_idx ); ?>][min_spend]" value="<?php echo esc_attr( isset( $tier['min_spend'] ) ? $tier['min_spend'] : 0 ); ?>" style="width: 90px;" placeholder="Ej: 100" /> €
																	<span style="font-size: 12px; font-weight: 600; margin-left: 10px;">Descuento:</span>
																	<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][tiers][<?php echo esc_attr( $t_idx ); ?>][discount_type]" style="width: 110px;">
																		<option value="percent" <?php selected( isset( $tier['discount_type'] ) ? $tier['discount_type'] : 'percent', 'percent' ); ?>>Porcentaje %</option>
																		<option value="fixed" <?php selected( isset( $tier['discount_type'] ) ? $tier['discount_type'] : '', 'fixed' ); ?>>Fijo €</option>
																	</select>
																	<input type="number" step="0.01" min="0" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][tiers][<?php echo esc_attr( $t_idx ); ?>][discount_value]" value="<?php echo esc_attr( isset( $tier['discount_value'] ) ? $tier['discount_value'] : 0 ); ?>" style="width: 80px;" placeholder="Ej: 10" />
																	<button type="button" class="wpat-promo-delete-tier-btn button button-small" style="color: #ef4444; margin-left: auto;">× Eliminar Tramo</button>
																</div>
																<?php
															endforeach;
														endif;
														?>
													</div>
													<button type="button" class="wpat-promo-add-tier-btn button button-small" style="margin-top: 10px;">+ Añadir Tramo</button>
												</div>

												<!-- 3.2 BXGY (3x2) -->
												<div class="wpat-promo-fields-bxgy" style="<?php echo 'bxgy' === $r_type ? '' : 'display:none;'; ?> background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
													<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #166534;">Configuración de Compra X, Paga Y (ej. 3x2 / 2x1):</h4>
													<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Por cada (Comprar Qty en lote):</label>
															<input type="number" min="1" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][buy_qty]" value="<?php echo esc_attr( isset( $rule['buy_qty'] ) ? $rule['buy_qty'] : 3 ); ?>" style="width: 100%;" />
														</div>
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Unidades regaladas / rebajadas (ej: 1 en 3x2, 1 en 2x1):</label>
															<input type="number" min="1" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][get_qty]" value="<?php echo esc_attr( isset( $rule['get_qty'] ) ? $rule['get_qty'] : 1 ); ?>" style="width: 100%;" />
														</div>
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">% Descuento en la(s) unidad(es) regalada(s):</label>
															<input type="number" step="0.1" min="1" max="100" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][discount_value]" value="<?php echo esc_attr( isset( $rule['discount_value'] ) ? $rule['discount_value'] : 100 ); ?>" style="width: 100%;" placeholder="100 = Gratis" />
														</div>
													</div>
													<p class="description" style="margin-top: 6px; font-size: 11px;">Nota: El motor descontará siempre el importe de las unidades de menor precio dentro del lote elegible.</p>
												</div>

												<!-- 3.3 Bulk Qty -->
												<div class="wpat-promo-fields-bulk_qty" style="<?php echo 'bulk_qty' === $r_type ? '' : 'display:none;'; ?> background: #fefce8; border: 1px solid #fef08a; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
													<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #854d0e;">Descuento por Volumen de Compra:</h4>
													<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">A partir de (Unidades mínimas):</label>
															<input type="number" min="1" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][min_qty]" value="<?php echo esc_attr( isset( $rule['min_qty'] ) ? $rule['min_qty'] : 5 ); ?>" style="width: 100%;" />
														</div>
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Tipo de Descuento:</label>
															<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][discount_type]" style="width: 100%;">
																<option value="percent" <?php selected( isset( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent', 'percent' ); ?>>% Porcentual sobre subtotal</option>
																<option value="fixed_unit" <?php selected( isset( $rule['discount_type'] ) ? $rule['discount_type'] : '', 'fixed_unit' ); ?>>€ Descuento fijo por unidad</option>
																<option value="fixed_total" <?php selected( isset( $rule['discount_type'] ) ? $rule['discount_type'] : '', 'fixed_total' ); ?>>€ Descuento fijo total</option>
															</select>
														</div>
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Valor del Descuento:</label>
															<input type="number" step="0.01" min="0" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][discount_value]" value="<?php echo esc_attr( isset( $rule['discount_value'] ) ? $rule['discount_value'] : 10 ); ?>" style="width: 100%;" />
														</div>
													</div>
												</div>

												<!-- 3.4 Global Discount -->
												<div class="wpat-promo-fields-global_discount" style="<?php echo 'global_discount' === $r_type ? '' : 'display:none;'; ?> background: #faf5ff; border: 1px solid #e9d5ff; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
													<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #6b21a8;">Descuento Directo Global o Filtrado:</h4>
													<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Tipo de Descuento:</label>
															<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][discount_type]" style="width: 100%;">
																<option value="percent" <?php selected( isset( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent', 'percent' ); ?>>% Porcentual</option>
																<option value="fixed" <?php selected( isset( $rule['discount_type'] ) ? $rule['discount_type'] : '', 'fixed' ); ?>>€ Fijo Directo</option>
															</select>
														</div>
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Valor del Descuento:</label>
															<input type="number" step="0.01" min="0" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][discount_value]" value="<?php echo esc_attr( isset( $rule['discount_value'] ) ? $rule['discount_value'] : 10 ); ?>" style="width: 100%;" />
														</div>
													</div>
												</div>

												<!-- 3.5 Payment Method -->
												<div class="wpat-promo-fields-payment_method" style="<?php echo 'payment_method' === $r_type ? '' : 'display:none;'; ?> background: #fff7ed; border: 1px solid #ffedd5; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
													<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #c2410c;">Incentivo por Método de Pago Seleccionado:</h4>
													<div style="margin-bottom: 10px;">
														<label style="font-size: 12px; font-weight: 700; display: block; margin-bottom: 6px;">Pasarelas de Pago Elegibles:</label>
														<div style="display: flex; flex-wrap: wrap; gap: 12px;">
															<?php
															if ( ! empty( $gateways ) ) {
																foreach ( $gateways as $gw_id => $gw ) {
																	$gw_title = is_object( $gw ) && isset( $gw->title ) ? $gw->title : $gw_id;
																	$is_checked = in_array( $gw_id, $r_pay_methods, true );
																	echo '<label style="font-size: 12px;"><input type="checkbox" name="wpat_settings[woo_promotions_rules][' . esc_attr( $idx ) . '][payment_methods][]" value="' . esc_attr( $gw_id ) . '" ' . checked( $is_checked, true, false ) . '> ' . esc_html( $gw_title ) . ' (<code>' . esc_html( $gw_id ) . '</code>)</label>';
																}
															} else {
																echo '<label style="font-size: 12px;"><input type="checkbox" name="wpat_settings[woo_promotions_rules][' . esc_attr( $idx ) . '][payment_methods][]" value="bacs" ' . checked( in_array( 'bacs', $r_pay_methods, true ), true, false ) . '> Transferencia Bancaria (<code>bacs</code>)</label>';
																echo '<label style="font-size: 12px;"><input type="checkbox" name="wpat_settings[woo_promotions_rules][' . esc_attr( $idx ) . '][payment_methods][]" value="cod" ' . checked( in_array( 'cod', $r_pay_methods, true ), true, false ) . '> Contrarreembolso (<code>cod</code>)</label>';
															}
															?>
														</div>
													</div>
													<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Tipo de Descuento:</label>
															<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][discount_type]" style="width: 100%;">
																<option value="percent" <?php selected( isset( $rule['discount_type'] ) ? $rule['discount_type'] : 'percent', 'percent' ); ?>>% Porcentual</option>
																<option value="fixed" <?php selected( isset( $rule['discount_type'] ) ? $rule['discount_type'] : '', 'fixed' ); ?>>€ Fijo Directo</option>
															</select>
														</div>
														<div>
															<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Valor del Descuento:</label>
															<input type="number" step="0.01" min="0" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][discount_value]" value="<?php echo esc_attr( isset( $rule['discount_value'] ) ? $rule['discount_value'] : 5 ); ?>" style="width: 100%;" />
														</div>
													</div>
												</div>

												<?php
												$r_enable_sched = isset( $rule['enable_schedule'] ) ? ( '1' === (string) $rule['enable_schedule'] ) : ( ! empty( $rule['start_date'] ) || ! empty( $rule['end_date'] ) );
												$r_cd_title     = ! empty( $rule['countdown_title'] ) ? $rule['countdown_title'] : '¡La oferta termina en!';
												$r_cd_bg        = ! empty( $rule['countdown_bg_color'] ) ? $rule['countdown_bg_color'] : '#fff7ed';
												$r_cd_border    = ! empty( $rule['countdown_border_color'] ) ? $rule['countdown_border_color'] : '#fed7aa';
												$r_cd_text      = ! empty( $rule['countdown_text_color'] ) ? $rule['countdown_text_color'] : '#9a3412';
												$r_cd_digit_bg  = ! empty( $rule['countdown_digit_bg'] ) ? $rule['countdown_digit_bg'] : '#ffffff';
												$r_cd_digit_col = ! empty( $rule['countdown_digit_color'] ) ? $rule['countdown_digit_color'] : '#ea580c';
												$r_cd_size      = ! empty( $rule['countdown_font_size'] ) ? absint( $rule['countdown_font_size'] ) : 14;
												?>
												<!-- Fila 4: Opciones adicionales y Programación -->
												<div style="border-top: 1px dashed #cbd5e1; padding-top: 12px; margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
													<div style="display: flex; flex-wrap: wrap; gap: 20px;">
														<label style="font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
															<input type="checkbox" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][ignore_on_sale]" value="1" <?php checked( $r_ignore_sale ); ?> />
															Excluir productos que ya tengan precio de oferta / rebaja activa
														</label>
														<label style="font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
															<input type="checkbox" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][include_extra_options]" value="1" <?php checked( $r_include_extra ); ?> />
															Incluir descuento en campos Extras en el cálculo del descuento
														</label>
														<label style="font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
															<input type="checkbox" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][show_countdown]" value="1" class="wpat-show-countdown-toggle" <?php checked( $r_show_cd ); ?> />
															Mostrar contador regresivo de tiempo (Countdown) en tienda y ficha de producto
														</label>
														<label style="font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
															<input type="checkbox" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][enable_schedule]" value="1" class="wpat-enable-schedule-toggle" <?php checked( $r_enable_sched ); ?> />
															Programar inicio y fin de la promoción (Opcional)
														</label>
													</div>

													<!-- Caja de Diseño del Contador Regresivo -->
													<div class="wpat-promo-cd-design-box" style="background: #fff7ed; border: 1px solid #fed7aa; padding: 12px; border-radius: 8px; margin-top: 6px; <?php echo $r_show_cd ? '' : 'display:none;'; ?>">
														<strong style="font-size: 12.5px; color: #9a3412; display: block; margin-bottom: 8px;">Ajustes de Diseño del Contador Regresivo:</strong>
														<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;">
															<div>
																<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Texto del Contador:</label>
																<input type="text" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][countdown_title]" value="<?php echo esc_attr( $r_cd_title ); ?>" placeholder="¡La oferta termina en!" style="width: 100%; font-size: 12px; height: 32px;" />
															</div>
															<div>
																<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Color Fondo:</label>
																<input type="color" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][countdown_bg_color]" value="<?php echo esc_attr( $r_cd_bg ); ?>" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
															</div>
															<div>
																<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Color Borde:</label>
																<input type="color" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][countdown_border_color]" value="<?php echo esc_attr( $r_cd_border ); ?>" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
															</div>
															<div>
																<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Color Texto:</label>
																<input type="color" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][countdown_text_color]" value="<?php echo esc_attr( $r_cd_text ); ?>" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
															</div>
															<div>
																<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Fondo Números:</label>
																<input type="color" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][countdown_digit_bg]" value="<?php echo esc_attr( $r_cd_digit_bg ); ?>" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
															</div>
															<div>
																<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Color Números:</label>
																<input type="color" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][countdown_digit_color]" value="<?php echo esc_attr( $r_cd_digit_col ); ?>" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
															</div>
															<div>
																<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Tamaño Texto (px):</label>
																<input type="number" min="10" max="30" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][countdown_font_size]" value="<?php echo esc_attr( $r_cd_size ); ?>" style="width: 100%; font-size: 12px; height: 32px;" />
															</div>
														</div>
													</div>

													<!-- Caja de Programación de Fechas -->
													<div class="wpat-promo-schedule-box" style="margin-top: 6px; <?php echo $r_enable_sched ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
														<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
															<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px;">
																<label style="font-size: 12px; font-weight: 700; display: block; margin-bottom: 6px; color: #334155;">Inicio de Promoción (Opcional):</label>
																<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
																	<input type="date" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][start_date]" value="<?php echo esc_attr( $r_start_date ); ?>" style="flex: 1; min-width: 130px; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px;" />
																	<div style="display: flex; align-items: center; gap: 3px;">
																		<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][start_hour]" class="wpat-time-select-hh" style="padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px; width: 55px;">
																			<?php for ( $h = 0; $h <= 23; $h++ ) : 
																				$val = str_pad( (string) $h, 2, '0', STR_PAD_LEFT );
																			?>
																				<option value="<?php echo $val; ?>" <?php selected( $s_h, $val ); ?>><?php echo $val; ?>h</option>
																			<?php endfor; ?>
																		</select>
																		<span style="font-weight: bold; color: #64748b;">:</span>
																		<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][start_minute]" class="wpat-time-select-mm" style="padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px; width: 55px;">
																			<?php for ( $m = 0; $m <= 59; $m++ ) : 
																				$val = str_pad( (string) $m, 2, '0', STR_PAD_LEFT );
																			?>
																				<option value="<?php echo $val; ?>" <?php selected( $s_m, $val ); ?>><?php echo $val; ?>m</option>
																			<?php endfor; ?>
																		</select>
																	</div>
																</div>
															</div>
															<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px;">
																<label style="font-size: 12px; font-weight: 700; display: block; margin-bottom: 6px; color: #334155;">Fin de Promoción (Opcional):</label>
																<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
																	<input type="date" name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][end_date]" value="<?php echo esc_attr( $r_end_date ); ?>" style="flex: 1; min-width: 130px; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px;" />
																	<div style="display: flex; align-items: center; gap: 3px;">
																		<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][end_hour]" class="wpat-time-select-hh" style="padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px; width: 55px;">
																			<?php for ( $h = 0; $h <= 23; $h++ ) : 
																				$val = str_pad( (string) $h, 2, '0', STR_PAD_LEFT );
																			?>
																				<option value="<?php echo $val; ?>" <?php selected( $e_h, $val ); ?>><?php echo $val; ?>h</option>
																			<?php endfor; ?>
																		</select>
																		<span style="font-weight: bold; color: #64748b;">:</span>
																		<select name="wpat_settings[woo_promotions_rules][<?php echo esc_attr( $idx ); ?>][end_minute]" class="wpat-time-select-mm" style="padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px; width: 55px;">
																			<?php for ( $m = 0; $m <= 59; $m++ ) : 
																				$val = str_pad( (string) $m, 2, '0', STR_PAD_LEFT );
																			?>
																				<option value="<?php echo $val; ?>" <?php selected( $e_m, $val ); ?>><?php echo $val; ?>m</option>
																			<?php endfor; ?>
																		</select>
																	</div>
																</div>
															</div>
														</div>
													</div>
												</div>

											</div>
										</div>
										<?php
									endforeach;
								endif;
								?>
							</div>
						</div>

					</div>
				</div>

				<!-- Script controlador del Repeater de Promociones Dinámicas -->
				<script>
				jQuery(document).ready(function($) {
					
					var iconSymbols = {
						'gift': '🎁',
						'fire': '🔥',
						'percent': '🏷️',
						'heart': '❤️',
						'pumpkin': '🎃',
						'skull': '💀',
						'tree': '🎄',
						'santa': '🎅',
						'shopping_bags': '🛍️',
						'star': '⭐',
						'crown': '👑',
						'lightning': '⚡',
						'sun': '☀️',
						'flower': '🌸',
						'none': ''
					};

					// Mostrar/Ocultar diseño de contador y programación de fechas
					$(document).on('change', '.wpat-show-countdown-toggle', function() {
						var $box = $(this).closest('.wpat-promo-rule-card').find('.wpat-promo-cd-design-box');
						if ($(this).is(':checked')) {
							$box.slideDown(200);
						} else {
							$box.slideUp(200);
						}
					});

					$(document).on('change', '.wpat-enable-schedule-toggle', function() {
						var $box = $(this).closest('.wpat-promo-rule-card').find('.wpat-promo-schedule-box');
						if ($(this).is(':checked')) {
							$box.css({ opacity: 1, 'pointer-events': 'auto' });
						} else {
							$box.css({ opacity: 0.5, 'pointer-events': 'none' });
						}
					});

					// Cambiar icono en cabecera al cambiar select de icono
					$(document).on('change', '.wpat-promo-icon-select', function() {
						var iconKey = $(this).val();
						var symbol = iconSymbols[iconKey] !== undefined ? iconSymbols[iconKey] : '🎁';
						$(this).closest('.wpat-promo-rule-card').find('.wpat-promo-icon-display').text(symbol);
					});

					// Cambiar visibilidad dinámica según Tipo de Promoción
					$(document).on('change', '.wpat-promo-type-select', function() {
						var $card = $(this).closest('.wpat-promo-rule-card');
						var type = $(this).val();
						
						$card.find('.wpat-promo-fields-tiered_spend, .wpat-promo-fields-bxgy, .wpat-promo-fields-bulk_qty, .wpat-promo-fields-global_discount, .wpat-promo-fields-payment_method').hide();
						$card.find('.wpat-promo-fields-' + type).show();

						var labels = {
							'tiered_spend': 'Tramos Gasto',
							'bxgy': '3x2 / Compra X Paga Y',
							'bulk_qty': 'Volumen',
							'global_discount': 'Descuento Global',
							'payment_method': 'Método Pago'
						};
						$card.find('.wpat-promo-type-badge').text(labels[type] || type);
					});

					// Cambiar visibilidad dinámica según Ámbito
					$(document).on('change', '.wpat-promo-scope-select', function() {
						var $card = $(this).closest('.wpat-promo-rule-card');
						var scope = $(this).val();
						
						$card.find('.wpat-promo-scope-categories-box, .wpat-promo-scope-products-box').hide();
						if (scope === 'category') {
							$card.find('.wpat-promo-scope-categories-box').show();
						} else if (scope === 'product') {
							$card.find('.wpat-promo-scope-products-box').show();
						}
					});

					// Actualizar título en cabecera al escribir
					$(document).on('input', '.wpat-promo-title-input', function() {
						var title = $(this).val() || 'Regla Promocional';
						$(this).closest('.wpat-promo-rule-card').find('.wpat-promo-rule-header-title').text(title);
					});

					// Plegar / Desplegar al hacer clic en cabecera (ignorando botones, inputs, labels)
					$(document).on('click', '.wpat-promo-rule-header', function(e) {
						if ($(e.target).closest('button, input, label, select').length > 0) {
							return;
						}
						var $card = $(this).closest('.wpat-promo-rule-card');
						var $body = $card.find('.wpat-promo-rule-body');
						var $btn = $card.find('.wpat-promo-toggle-body-btn');

						$body.slideToggle(200, function() {
							if ($body.is(':visible')) {
								$btn.text('Plegar');
							} else {
								$btn.text('Desplegar');
							}
						});
					});

					// Handler específico para el Botón Plegar / Desplegar
					$(document).on('click', '.wpat-promo-toggle-body-btn', function(e) {
						e.preventDefault();
						e.stopPropagation();
						var $card = $(this).closest('.wpat-promo-rule-card');
						var $body = $card.find('.wpat-promo-rule-body');
						var $btn = $(this);

						$body.slideToggle(200, function() {
							if ($body.is(':visible')) {
								$btn.text('Plegar');
							} else {
								$btn.text('Desplegar');
							}
						});
					});

					// Handler específico para el Botón Eliminar Regla
					$(document).on('click', '.wpat-promo-delete-rule-btn', function(e) {
						e.preventDefault();
						e.stopPropagation();
						if (confirm('¿Estás seguro de que deseas eliminar esta regla promocional?')) {
							$(this).closest('.wpat-promo-rule-card').remove();
							if ($('.wpat-promo-rule-card').length === 0) {
								$('#wpat_promo_empty_msg').show();
							}
						}
					});

					// Desplegar todas
					$('#wpat_promo_expand_all_btn').on('click', function() {
						$('.wpat-promo-rule-body').slideDown(200);
						$('.wpat-promo-toggle-body-btn').text('Plegar');
					});

					// Plegar todas
					$('#wpat_promo_collapse_all_btn').on('click', function() {
						$('.wpat-promo-rule-body').slideUp(200);
						$('.wpat-promo-toggle-body-btn').text('Desplegar');
					});

					// Buscar regla por título
					$('#wpat_promo_search_input').on('keyup', function() {
						var q = $(this).val().toLowerCase();
						$('.wpat-promo-rule-card').each(function() {
							var title = $(this).find('.wpat-promo-rule-header-title').text().toLowerCase();
							if (title.indexOf(q) !== -1) {
								$(this).show();
							} else {
								$(this).hide();
							}
						});
					});

					// Generar opciones de horas y minutos en HTML
					function buildHourOptions(selectedVal) {
						var html = '';
						for (var h = 0; h <= 23; h++) {
							var val = (h < 10 ? '0' : '') + h;
							var sel = (val === selectedVal) ? ' selected' : '';
							html += '<option value="' + val + '"' + sel + '>' + val + 'h</option>';
						}
						return html;
					}

					function buildMinuteOptions(selectedVal) {
						var html = '';
						for (var m = 0; m <= 59; m++) {
							var val = (m < 10 ? '0' : '') + m;
							var sel = (val === selectedVal) ? ' selected' : '';
							html += '<option value="' + val + '"' + sel + '>' + val + 'm</option>';
						}
						return html;
					}

					// Añadir nueva regla
					$('#wpat_add_promo_rule_btn').on('click', function() {
						$('#wpat_promo_empty_msg').hide();
						var idx = new Date().getTime();
						var startHours = buildHourOptions('00');
						var startMins  = buildMinuteOptions('00');
						var endHours   = buildHourOptions('23');
						var endMins    = buildMinuteOptions('59');

						var newCardHtml = `
							<div class="wpat-promo-rule-card" data-rule-idx="${idx}" style="border: 1px solid #cbd5e1; border-radius: 10px; background: #ffffff; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
								<input type="hidden" name="wpat_settings[woo_promotions_rules][${idx}][id]" value="rule_${idx}" />
								
								<div class="wpat-promo-rule-header" style="background: #f8fafc; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; border-bottom: 1px solid #e2e8f0; user-select: none;">
									<div style="display: flex; align-items: center; gap: 10px; flex-grow: 1;">
										<span class="wpat-promo-icon-display" style="font-size: 16px; min-width: 20px;">🎁</span>
										<strong class="wpat-promo-rule-header-title" style="font-size: 14px; color: #0f172a;">Nueva Regla Promocional</strong>
										<span class="wpat-promo-type-badge" style="background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; text-transform: uppercase;">Descuento Global</span>
									</div>
									<div style="display: flex; align-items: center; gap: 12px;">
										<label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; margin: 0; cursor: pointer;">
											<input type="checkbox" name="wpat_settings[woo_promotions_rules][${idx}][active]" value="1" checked />
											<span style="color:#16a34a;">Activa</span>
										</label>
										<button type="button" class="wpat-promo-toggle-body-btn button button-small" style="font-size: 11px;">Plegar</button>
										<button type="button" class="wpat-promo-delete-rule-btn button button-small" style="color: #ef4444; border-color: #fca5a5;">Eliminar</button>
									</div>
								</div>

								<div class="wpat-promo-rule-body" style="padding: 20px; display: block;">
									<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 15px;">
										<div>
											<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">Título Público (visible en carrito/checkout):</label>
											<input type="text" name="wpat_settings[woo_promotions_rules][${idx}][title]" value="Nueva Regla Promocional" class="wpat-promo-title-input regular-text" style="width: 100%;" required />
										</div>
										<div>
											<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">Icono de Promoción (Opcional):</label>
											<select name="wpat_settings[woo_promotions_rules][${idx}][icon]" class="wpat-promo-icon-select regular-text" style="width: 100%;">
												<option value="gift" selected>🎁 Regalo (Genérico)</option>
												<option value="fire">🔥 Oferta Flash / Tendencia</option>
												<option value="percent">🏷️ Descuento % (Black Friday / Cyber)</option>
												<option value="heart">❤️ Corazón (San Valentín)</option>
												<option value="pumpkin">🎃 Calabaza (Halloween)</option>
												<option value="skull">💀 Calavera / Esqueleto (Halloween)</option>
												<option value="tree">🎄 Árbol de Navidad</option>
												<option value="santa">🎅 Papá Noel (Navidad)</option>
												<option value="shopping_bags">🛍️ Compras (Rebajas)</option>
												<option value="star">⭐ Estrella (Especial)</option>
												<option value="crown">👑 VIP / Exclusivo</option>
												<option value="lightning">⚡ Relámpago</option>
												<option value="sun">☀️ Verano</option>
												<option value="flower">🌸 Primavera / Día de la Madre</option>
												<option value="none">Sin icono (Desactivado)</option>
											</select>
										</div>
										<div>
											<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">Tipo de Promoción:</label>
											<select name="wpat_settings[woo_promotions_rules][${idx}][type]" class="wpat-promo-type-select regular-text" style="width: 100%;">
												<option value="tiered_spend">Descuento por tramos de gasto</option>
												<option value="bxgy">Compra X, Paga Y (3x2)</option>
												<option value="bulk_qty">Descuento por volumen / cantidad</option>
												<option value="global_discount" selected>Descuento porcentual o fijo global</option>
												<option value="payment_method">Descuento por método de pago</option>
											</select>
										</div>
										<div>
											<label style="font-size: 12.5px; font-weight: 700; display: block; margin-bottom: 5px;">Ámbito de Aplicación:</label>
											<select name="wpat_settings[woo_promotions_rules][${idx}][scope]" class="wpat-promo-scope-select regular-text" style="width: 100%;">
												<option value="all" selected>Toda la tienda</option>
												<option value="category">Categorías específicas</option>
												<option value="product">Productos / Variaciones específicas</option>
											</select>
										</div>
									</div>

									<div class="wpat-promo-fields-global_discount" style="background: #faf5ff; border: 1px solid #e9d5ff; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
										<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #6b21a8;">Descuento Directo Global o Filtrado:</h4>
										<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
											<div>
												<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Tipo de Descuento:</label>
												<select name="wpat_settings[woo_promotions_rules][${idx}][discount_type]" style="width: 100%;">
													<option value="percent" selected>% Porcentual</option>
													<option value="fixed">€ Fijo Directo</option>
												</select>
											</div>
											<div>
												<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Valor del Descuento:</label>
												<input type="number" step="0.01" min="0" name="wpat_settings[woo_promotions_rules][${idx}][discount_value]" value="10" style="width: 100%;" />
											</div>
										</div>
									</div>

									<div style="border-top: 1px dashed #cbd5e1; padding-top: 12px; margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
										<div style="display: flex; flex-wrap: wrap; gap: 20px;">
											<label style="font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[woo_promotions_rules][${idx}][ignore_on_sale]" value="1" />
												Excluir productos que ya tengan precio de oferta / rebaja activa
											</label>
											<label style="font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[woo_promotions_rules][${idx}][include_extra_options]" value="1" />
												Incluir descuento en campos Extras en el cálculo del descuento
											</label>
											<label style="font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[woo_promotions_rules][${idx}][show_countdown]" value="1" class="wpat-show-countdown-toggle" checked />
												Mostrar contador regresivo de tiempo (Countdown) en tienda y ficha de producto
											</label>
											<label style="font-size: 12.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;">
												<input type="checkbox" name="wpat_settings[woo_promotions_rules][${idx}][enable_schedule]" value="1" class="wpat-enable-schedule-toggle" />
												Programar inicio y fin de la promoción (Opcional)
											</label>
										</div>

										<div class="wpat-promo-cd-design-box" style="background: #fff7ed; border: 1px solid #fed7aa; padding: 12px; border-radius: 8px; margin-top: 6px;">
											<strong style="font-size: 12.5px; color: #9a3412; display: block; margin-bottom: 8px;">Ajustes de Diseño del Contador Regresivo:</strong>
											<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;">
												<div>
													<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Texto del Contador:</label>
													<input type="text" name="wpat_settings[woo_promotions_rules][${idx}][countdown_title]" value="¡La oferta termina en!" placeholder="¡La oferta termina en!" style="width: 100%; font-size: 12px; height: 32px;" />
												</div>
												<div>
													<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Color Fondo:</label>
													<input type="color" name="wpat_settings[woo_promotions_rules][${idx}][countdown_bg_color]" value="#fff7ed" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
												</div>
												<div>
													<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Color Borde:</label>
													<input type="color" name="wpat_settings[woo_promotions_rules][${idx}][countdown_border_color]" value="#fed7aa" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
												</div>
												<div>
													<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Color Texto:</label>
													<input type="color" name="wpat_settings[woo_promotions_rules][${idx}][countdown_text_color]" value="#9a3412" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
												</div>
												<div>
													<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Fondo Números:</label>
													<input type="color" name="wpat_settings[woo_promotions_rules][${idx}][countdown_digit_bg]" value="#ffffff" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
												</div>
												<div>
													<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Color Números:</label>
													<input type="color" name="wpat_settings[woo_promotions_rules][${idx}][countdown_digit_color]" value="#ea580c" style="width: 100%; height: 32px; padding: 1px; cursor: pointer;" />
												</div>
												<div>
													<label style="font-size: 11.5px; font-weight: 600; display: block; margin-bottom: 3px;">Tamaño Texto (px):</label>
													<input type="number" min="10" max="30" name="wpat_settings[woo_promotions_rules][${idx}][countdown_font_size]" value="14" style="width: 100%; font-size: 12px; height: 32px;" />
												</div>
											</div>
										</div>

										<div class="wpat-promo-schedule-box" style="margin-top: 6px; opacity: 0.5; pointer-events: none;">
											<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
												<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px;">
													<label style="font-size: 12px; font-weight: 700; display: block; margin-bottom: 6px; color: #334155;">Inicio de Promoción (Opcional):</label>
													<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
														<input type="date" name="wpat_settings[woo_promotions_rules][${idx}][start_date]" value="" style="flex: 1; min-width: 130px; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px;" />
														<div style="display: flex; align-items: center; gap: 3px;">
															<select name="wpat_settings[woo_promotions_rules][${idx}][start_hour]" class="wpat-time-select-hh" style="padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px; width: 55px;">
																${startHours}
															</select>
															<span style="font-weight: bold; color: #64748b;">:</span>
															<select name="wpat_settings[woo_promotions_rules][${idx}][start_minute]" class="wpat-time-select-mm" style="padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px; width: 55px;">
																${startMins}
															</select>
														</div>
													</div>
												</div>
												<div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px;">
													<label style="font-size: 12px; font-weight: 700; display: block; margin-bottom: 6px; color: #334155;">Fin de Promoción (Opcional):</label>
													<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
														<input type="date" name="wpat_settings[woo_promotions_rules][${idx}][end_date]" value="" style="flex: 1; min-width: 130px; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px;" />
														<div style="display: flex; align-items: center; gap: 3px;">
															<select name="wpat_settings[woo_promotions_rules][${idx}][end_hour]" class="wpat-time-select-hh" style="padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px; width: 55px;">
																${endHours}
															</select>
															<span style="font-weight: bold; color: #64748b;">:</span>
															<select name="wpat_settings[woo_promotions_rules][${idx}][end_minute]" class="wpat-time-select-mm" style="padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; height: 34px; width: 55px;">
																${endMins}
															</select>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						`;

						$('#wpat_promo_rules_container').append(newCardHtml);
					});

					// Añadir tramo en Tiered Spend
					$(document).on('click', '.wpat-promo-add-tier-btn', function() {
						var $card = $(this).closest('.wpat-promo-rule-card');
						var ruleIdx = $card.data('rule-idx');
						var tierIdx = new Date().getTime();
						var tierHtml = `
							<div class="wpat-promo-tier-row" style="display: flex; gap: 10px; align-items: center; background: #ffffff; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
								<span style="font-size: 12px; font-weight: 600;">Subtotal Mínimo:</span>
								<input type="number" step="0.01" min="0" name="wpat_settings[woo_promotions_rules][${ruleIdx}][tiers][${tierIdx}][min_spend]" value="100" style="width: 90px;" placeholder="Ej: 100" /> €
								<span style="font-size: 12px; font-weight: 600; margin-left: 10px;">Descuento:</span>
								<select name="wpat_settings[woo_promotions_rules][${ruleIdx}][tiers][${tierIdx}][discount_type]" style="width: 110px;">
									<option value="percent" selected>Porcentaje %</option>
									<option value="fixed">Fijo €</option>
								</select>
								<input type="number" step="0.01" min="0" name="wpat_settings[woo_promotions_rules][${ruleIdx}][tiers][${tierIdx}][discount_value]" value="10" style="width: 80px;" placeholder="Ej: 10" />
								<button type="button" class="wpat-promo-delete-tier-btn button button-small" style="color: #ef4444; margin-left: auto;">× Eliminar Tramo</button>
							</div>
						`;
						$card.find('.wpat-promo-tiers-list').append(tierHtml);
					});

					// Eliminar tramo
					$(document).on('click', '.wpat-promo-delete-tier-btn', function() {
						$(this).closest('.wpat-promo-tier-row').remove();
					});

				});
				</script>
				<?php
				break;
			case 'woo-address-autofill':
				?>
				<div class="wpat-module-card" style="margin-top: 20px;">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Autocompletado de Código Postal, Provincia y Población (Checkout)</h3>
							<p>Rellena automáticamente la Provincia y sugiere las poblaciones correspondientes según el Código Postal introducido, agilizando el checkout y reduciendo errores de entrega.</p>
						</div>
						<?php $this->render_module_toggle( 'woo-address-autofill', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">
						<div class="wpat-field-group" style="margin-top: 15px;">
							<label style="font-weight: 600; display: flex; align-items: center; gap: 8px;">
								<input type="checkbox" name="wpat_settings[woo_address_autofill_city]" value="1" <?php checked( isset( $settings['woo_address_autofill_city'] ) ? $settings['woo_address_autofill_city'] : '1', '1' ); ?>>
								Convertir el campo de Población en un desplegable inteligente filtrado por provincia
							</label>
							<p class="description" style="margin-left: 24px;">Permite al cliente seleccionar su municipio de una lista verificada, con opción de escritura manual si no estuviera listado.</p>
						</div>

						<div class="wpat-field-group" style="margin-top: 15px; background: #f8fafc; border: 1px solid var(--wpat-border); padding: 15px; border-radius: 8px;">
							<h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 700;">🇪🇸 Base de Datos de España Incorporada</h4>
							<p style="margin: 0; font-size: 12.5px; color: #475569;">Incluye las <strong>52 provincias</strong> y más de <strong>540 municipios</strong> de España con relaciones bidireccionales automáticas entre CP ↔ Provincia ↔ Población.</p>
						</div>
					</div>
				</div>
				<?php
				break;
			case 'duplicator':
				$dup_suffix      = isset( $settings['duplicator_title_suffix'] ) ? $settings['duplicator_title_suffix'] : ' (Copia)';
				$dup_status      = isset( $settings['duplicator_post_status'] ) ? $settings['duplicator_post_status'] : 'draft';
				$dup_redirect    = isset( $settings['duplicator_redirect_to'] ) ? $settings['duplicator_redirect_to'] : 'edit';
				$dup_admin_bar   = isset( $settings['duplicator_show_admin_bar'] ) ? $settings['duplicator_show_admin_bar'] : '1';
				$dup_taxonomies  = isset( $settings['duplicator_copy_taxonomies'] ) ? $settings['duplicator_copy_taxonomies'] : '1';
				$dup_meta        = isset( $settings['duplicator_copy_meta'] ) ? $settings['duplicator_copy_meta'] : '1';
				$dup_author      = isset( $settings['duplicator_copy_author'] ) ? $settings['duplicator_copy_author'] : 'current';
				$dup_date        = isset( $settings['duplicator_copy_date'] ) ? $settings['duplicator_copy_date'] : 'current';
				$dup_post_types  = isset( $settings['duplicator_post_types'] ) && is_array( $settings['duplicator_post_types'] ) ? $settings['duplicator_post_types'] : array( 'post', 'page', 'product' );

				$all_post_types = get_post_types( array( 'public' => true ), 'objects' );
				if ( isset( $all_post_types['attachment'] ) ) {
					unset( $all_post_types['attachment'] );
				}
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Clonador de Entradas, Páginas y CPTs</h3>
							<p>Duplica cualquier Entrada, Página, Producto o Custom Post Type con 1 clic conservando metadatos (ACF, Elementor, Divi), taxonomías y atributos.</p>
						</div>
						<?php $this->render_module_toggle( 'duplicator', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">
						<form method="post" action="">
							<?php wp_nonce_field( 'wpat_save_settings_action', 'wpat_save_settings_nonce' ); ?>
							<input type="hidden" name="wpat_saving_module" value="duplicator" />

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
								
								<!-- Formato del Título -->
								<div class="wpat-field-group">
									<label for="wpat_duplicator_title_suffix" style="font-weight: 600; display: block; margin-bottom: 6px;">
										Formato del Título Duplicado
									</label>
									<input type="text" id="wpat_duplicator_title_suffix" name="wpat_settings[duplicator_title_suffix]" value="<?php echo esc_attr( $dup_suffix ); ?>" placeholder=" (Copia)" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
									<p class="description" style="margin-top: 4px;">Puedes escribir un sufijo como <code> (Copia)</code> o usar la etiqueta <code>{title} (Clon)</code>.</p>
								</div>

								<!-- Estado del Post Duplicado -->
								<div class="wpat-field-group">
									<label for="wpat_duplicator_post_status" style="font-weight: 600; display: block; margin-bottom: 6px;">
										Estado Inicial del Clon
									</label>
									<select id="wpat_duplicator_post_status" name="wpat_settings[duplicator_post_status]" style="width: 100%; height: 36px; border-radius: 6px;">
										<option value="draft" <?php selected( $dup_status, 'draft' ); ?>>Borrador (Recomendado por seguridad)</option>
										<option value="pending" <?php selected( $dup_status, 'pending' ); ?>>Pendiente de revisión</option>
										<option value="publish" <?php selected( $dup_status, 'publish' ); ?>>Publicado inmediatamente</option>
										<option value="private" <?php selected( $dup_status, 'private' ); ?>>Privado</option>
									</select>
									<p class="description" style="margin-top: 4px;">El estado con el que se guardará el nuevo elemento clonado.</p>
								</div>

								<!-- Destino tras Duplicar -->
								<div class="wpat-field-group">
									<label for="wpat_duplicator_redirect_to" style="font-weight: 600; display: block; margin-bottom: 6px;">
										Acción tras Duplicar
									</label>
									<select id="wpat_duplicator_redirect_to" name="wpat_settings[duplicator_redirect_to]" style="width: 100%; height: 36px; border-radius: 6px;">
										<option value="edit" <?php selected( $dup_redirect, 'edit' ); ?>>Abrir directamente el nuevo clon en el Editor (Más rápido)</option>
										<option value="list" <?php selected( $dup_redirect, 'list' ); ?>>Permanecer en la lista de entradas / páginas</option>
									</select>
									<p class="description" style="margin-top: 4px;">Elige a dónde te redirige WordPress al hacer clic en duplicar.</p>
								</div>

								<!-- Asignación de Autor -->
								<div class="wpat-field-group">
									<label for="wpat_duplicator_copy_author" style="font-weight: 600; display: block; margin-bottom: 6px;">
										Autor del Clon
									</label>
									<select id="wpat_duplicator_copy_author" name="wpat_settings[duplicator_copy_author]" style="width: 100%; height: 36px; border-radius: 6px;">
										<option value="current" <?php selected( $dup_author, 'current' ); ?>>Usuario actual (el que hace clic en duplicar)</option>
										<option value="original" <?php selected( $dup_author, 'original' ); ?>>Conservar el autor original de la entrada</option>
									</select>
								</div>
							</div>

							<!-- Selector de Tipos de Contenido Habilitados -->
							<div style="border-top: 1px solid #e2e8f0; padding-top: 20px; margin-bottom: 20px;">
								<label style="font-weight: 700; font-size: 13.5px; color: #1e293b; display: block; margin-bottom: 8px;">
									Tipos de Contenido Habilitados (Post Types)
								</label>
								<p class="description" style="margin-bottom: 12px;">Marca en qué tipos de contenido deseas mostrar el botón "Duplicar" y las acciones en lote:</p>
								<div style="display: flex; flex-wrap: wrap; gap: 15px;">
									<?php foreach ( $all_post_types as $pt_slug => $pt_obj ) : ?>
										<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 6px; cursor: pointer;">
											<input type="checkbox" name="wpat_settings[duplicator_post_types][]" value="<?php echo esc_attr( $pt_slug ); ?>" <?php checked( in_array( $pt_slug, $dup_post_types, true ) ); ?> style="border-radius: 4px;" />
											<span><?php echo esc_html( $pt_obj->labels->singular_name ? $pt_obj->labels->singular_name : $pt_obj->label ); ?> (<code><?php echo esc_html( $pt_slug ); ?></code>)</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<!-- Opciones Avanzadas de Clonación -->
							<div style="border-top: 1px solid #e2e8f0; padding-top: 20px; margin-bottom: 20px;">
								<label style="font-weight: 700; font-size: 13.5px; color: #1e293b; display: block; margin-bottom: 12px;">
									Opciones de Metadatos y Accesos Directos
								</label>
								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
										<input type="checkbox" name="wpat_settings[duplicator_copy_taxonomies]" value="1" <?php checked( $dup_taxonomies, '1' ); ?> style="border-radius: 4px;" />
										<span><strong>Copiar Taxonomías:</strong> Clona categorías, etiquetas y taxonomías asignadas.</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
										<input type="checkbox" name="wpat_settings[duplicator_copy_meta]" value="1" <?php checked( $dup_meta, '1' ); ?> style="border-radius: 4px;" />
										<span><strong>Copiar Metadatos & Constructores:</strong> Clona campos de ACF, JetEngine, Elementor y Divi.</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
										<input type="checkbox" name="wpat_settings[duplicator_show_admin_bar]" value="1" <?php checked( $dup_admin_bar, '1' ); ?> style="border-radius: 4px;" />
										<span><strong>Barra Superior de WordPress:</strong> Añadir botón "Duplicar" en el Admin Bar.</span>
									</label>
								</div>
							</div>

							<div style="border-top: 1px solid #e2e8f0; padding-top: 15px; display: flex; justify-content: flex-end;">
								<button type="submit" class="button button-primary" style="height: 36px; padding: 0 20px; font-weight: 600;">
									Guardar Ajustes de Duplicador
								</button>
							</div>
						</div>
					</div>
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
					<div class="wpat-module-body" style="display: block; padding: 20px;">
						
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
				<?php
				break;
			case 'performance':
				$perf_nonce = wp_create_nonce( 'wpat_perf_nonce' );
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Ajustes de Rendimiento & Optimización</h3>
							<p>Acelera la velocidad de carga (Core Web Vitals), reduce el consumo de CPU y memoria del servidor, y mantén la base de datos optimizada.</p>
						</div>
						<?php $this->render_module_toggle( 'performance', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">

						<!-- 1. Limpieza de Assets y Frontend -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-dashboard" style="color: #6366f1;"></span>
							<?php esc_html_e( 'Optimización de Assets & Frontend (Core Web Vitals)', 'wp-agency-toolkit' ); ?>
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin-bottom: 24px;">
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[perf_disable_emojis]" value="1" <?php checked( ! isset( $settings['perf_disable_emojis'] ) || '1' === (string) $settings['perf_disable_emojis'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Desactivar Emojis Nativos de WP', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Elimina wp-emoji.js, estilos CSS inline y DNS prefetch a s.w.org (~15KB de ahorro).', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[perf_cleanup_head]" value="1" <?php checked( ! isset( $settings['perf_cleanup_head'] ) || '1' === (string) $settings['perf_cleanup_head'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Limpieza de Cabeceras <head>', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Remueve RSD, WLW Manifest, generador de versión de WP y enlaces cortos redundantes.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[perf_disable_jquery_migrate]" value="1" <?php checked( ! isset( $settings['perf_disable_jquery_migrate'] ) || '1' === (string) $settings['perf_disable_jquery_migrate'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Desactivar jQuery Migrate en Frontend', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Evita cargar la librería de compatibilidad obsoleta de jQuery en temas modernos.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[perf_disable_dashicons]" value="1" <?php checked( ! isset( $settings['perf_disable_dashicons'] ) || '1' === (string) $settings['perf_disable_dashicons'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Desactivar Dashicons para Visitantes', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;"><?php esc_html_e( 'No carga la fuente dashicons.min.css en el frontend a menos que el usuario esté identificado.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[perf_disable_embeds]" value="1" <?php checked( ! isset( $settings['perf_disable_embeds'] ) || '1' === (string) $settings['perf_disable_embeds'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Desactivar Scripts de Incrustación (oEmbed)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Remueve wp-embed.min.js y llamadas de descubrimiento de incrustaciones de terceros.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[perf_disable_wc_cart_fragments]" value="1" <?php checked( ! isset( $settings['perf_disable_wc_cart_fragments'] ) || '1' === (string) $settings['perf_disable_wc_cart_fragments'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Optimizar WooCommerce Cart Fragments', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Bloquea la pesada llamada AJAX de actualización de carrito en páginas que no son de tienda cuando el carrito está vacío.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>
						</div>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- 2. CPU del Servidor y Base de Datos -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-admin-generic" style="color: #6366f1;"></span>
							<?php esc_html_e( 'Ajustes del Servidor, Heartbeat & Revisiones', 'wp-agency-toolkit' ); ?>
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
							<div class="wpat-field-group">
								<label for="wpat_perf_heartbeat_control" style="font-weight: 600; display: block; margin-bottom: 6px;">
									<?php esc_html_e( 'Frecuencia de Heartbeat API de WordPress', 'wp-agency-toolkit' ); ?>
								</label>
								<select name="wpat_settings[perf_heartbeat_control]" id="wpat_perf_heartbeat_control" style="width: 100%;">
									<option value="slow" <?php selected( isset( $settings['perf_heartbeat_control'] ) ? $settings['perf_heartbeat_control'] : 'slow', 'slow' ); ?>>
										<?php esc_html_e( 'Ralentizar a 60s/120s (Recomendado - Ahorra CPU)', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="disable_frontend" <?php selected( isset( $settings['perf_heartbeat_control'] ) ? $settings['perf_heartbeat_control'] : 'slow', 'disable_frontend' ); ?>>
										<?php esc_html_e( 'Desactivar solo en Frontend', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="disable_all" <?php selected( isset( $settings['perf_heartbeat_control'] ) ? $settings['perf_heartbeat_control'] : 'slow', 'disable_all' ); ?>>
										<?php esc_html_e( 'Desactivar en todo el sitio (excepto edición de posts)', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="default" <?php selected( isset( $settings['perf_heartbeat_control'] ) ? $settings['perf_heartbeat_control'] : 'slow', 'default' ); ?>>
										<?php esc_html_e( 'Por defecto de WordPress (15s en edición)', 'wp-agency-toolkit' ); ?>
									</option>
								</select>
								<p class="description" style="margin-top: 4px;">
									<?php esc_html_e( 'Reduce drásticamente las peticiones repetitivas a admin-ajax.php generadas en segundo plano.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>

							<div class="wpat-field-group">
								<label for="wpat_perf_limit_revisions" style="font-weight: 600; display: block; margin-bottom: 6px;">
									<?php esc_html_e( 'Límite de Revisiones de Entradas y Páginas', 'wp-agency-toolkit' ); ?>
								</label>
								<select name="wpat_settings[perf_limit_revisions]" id="wpat_perf_limit_revisions" style="width: 100%;">
									<option value="5" <?php selected( isset( $settings['perf_limit_revisions'] ) ? $settings['perf_limit_revisions'] : '5', '5' ); ?>>
										<?php esc_html_e( 'Máximo 5 revisiones por entrada (Recomendado)', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="3" <?php selected( isset( $settings['perf_limit_revisions'] ) ? $settings['perf_limit_revisions'] : '5', '3' ); ?>>
										<?php esc_html_e( 'Máximo 3 revisiones', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="10" <?php selected( isset( $settings['perf_limit_revisions'] ) ? $settings['perf_limit_revisions'] : '5', '10' ); ?>>
										<?php esc_html_e( 'Máximo 10 revisiones', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="0" <?php selected( isset( $settings['perf_limit_revisions'] ) ? $settings['perf_limit_revisions'] : '5', '0' ); ?>>
										<?php esc_html_e( 'Desactivar revisiones por completo', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="unlimited" <?php selected( isset( $settings['perf_limit_revisions'] ) ? $settings['perf_limit_revisions'] : '5', 'unlimited' ); ?>>
										<?php esc_html_e( 'Ilimitadas (Por defecto WP - Engorda la BD)', 'wp-agency-toolkit' ); ?>
									</option>
								</select>
								<p class="description" style="margin-top: 4px;">
									<?php esc_html_e( 'Evita que la tabla wp_posts se sature con cientos de versiones antiguas de cada post.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>

							<div class="wpat-field-group">
								<label for="wpat_perf_autosave_interval" style="font-weight: 600; display: block; margin-bottom: 6px;">
									<?php esc_html_e( 'Intervalo de Autoguardado en el Editor', 'wp-agency-toolkit' ); ?>
								</label>
								<select name="wpat_settings[perf_autosave_interval]" id="wpat_perf_autosave_interval" style="width: 100%;">
									<option value="180" <?php selected( isset( $settings['perf_autosave_interval'] ) ? $settings['perf_autosave_interval'] : '180', '180' ); ?>>
										<?php esc_html_e( 'Cada 180 segundos (3 minutos - Recomendado)', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="300" <?php selected( isset( $settings['perf_autosave_interval'] ) ? $settings['perf_autosave_interval'] : '180', '300' ); ?>>
										<?php esc_html_e( 'Cada 300 segundos (5 minutos)', 'wp-agency-toolkit' ); ?>
									</option>
									<option value="60" <?php selected( isset( $settings['perf_autosave_interval'] ) ? $settings['perf_autosave_interval'] : '180', '60' ); ?>>
										<?php esc_html_e( 'Cada 60 segundos (Por defecto WP)', 'wp-agency-toolkit' ); ?>
									</option>
								</select>
								<p class="description" style="margin-top: 4px;">
									<?php esc_html_e( 'Reduce las escrituras constantes en base de datos mientras los autores redactan contenido.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>
						</div>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- 3. Mantenimiento y Limpieza de Base de Datos en 1 Clic -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px;">
							<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
								<h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-database-view" style="color: #10b981;"></span>
									<?php esc_html_e( 'Mantenimiento y Optimización de Base de Datos en 1 Clic', 'wp-agency-toolkit' ); ?>
								</h4>
								<button type="button" id="wpat-perf-refresh-stats-btn" class="button button-secondary button-small" style="display: inline-flex; align-items: center; gap: 4px;">
									<span class="dashicons dashicons-update" style="font-size: 15px; line-height: 20px; width: 15px; height: 15px;"></span>
									<?php esc_html_e( 'Consultar Estado', 'wp-agency-toolkit' ); ?>
								</button>
							</div>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 12px;">
								<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
									<div>
										<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
											<?php esc_html_e( 'Revisiones Almacenadas', 'wp-agency-toolkit' ); ?>
										</div>
										<div id="wpat-stat-revisions" style="font-size: 18px; font-weight: bold; color: #1e293b; margin-bottom: 8px;">--</div>
									</div>
									<button type="button" id="wpat-btn-clean-revisions" class="button button-small" style="width: 100%;">
										<?php esc_html_e( 'Purgar Revisiones', 'wp-agency-toolkit' ); ?>
									</button>
								</div>

								<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
									<div>
										<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
											<?php esc_html_e( 'Transients Caducados', 'wp-agency-toolkit' ); ?>
										</div>
										<div id="wpat-stat-transients" style="font-size: 18px; font-weight: bold; color: #1e293b; margin-bottom: 8px;">--</div>
									</div>
									<button type="button" id="wpat-btn-clean-transients" class="button button-small" style="width: 100%;">
										<?php esc_html_e( 'Limpiar Transients', 'wp-agency-toolkit' ); ?>
									</button>
								</div>

								<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
									<div>
										<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
											<?php esc_html_e( 'Elementos en Papelera', 'wp-agency-toolkit' ); ?>
										</div>
										<div id="wpat-stat-trash" style="font-size: 18px; font-weight: bold; color: #1e293b; margin-bottom: 8px;">--</div>
									</div>
									<button type="button" id="wpat-btn-clean-trash" class="button button-small" style="width: 100%;">
										<?php esc_html_e( 'Vaciar Papelera', 'wp-agency-toolkit' ); ?>
									</button>
								</div>

								<div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">
									<div>
										<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
											<?php esc_html_e( 'Espacio Recuperable', 'wp-agency-toolkit' ); ?>
										</div>
										<div id="wpat-stat-overhead" style="font-size: 18px; font-weight: bold; color: #059669; margin-bottom: 8px;">--</div>
									</div>
									<button type="button" id="wpat-btn-optimize-tables" class="button button-primary button-small" style="width: 100%;">
										<?php esc_html_e( 'Optimizar Tablas', 'wp-agency-toolkit' ); ?>
									</button>
								</div>
							</div>

							<div id="wpat-perf-action-result" style="font-size: 13px; margin-top: 8px; font-weight: 600;"></div>
						</div>

						<script type="text/javascript">
							document.addEventListener('DOMContentLoaded', function() {
								var nonce = '<?php echo esc_js( $perf_nonce ); ?>';
								var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

								function updateStats() {
									var statRev = document.getElementById('wpat-stat-revisions');
									var statTrans = document.getElementById('wpat-stat-transients');
									var statTrash = document.getElementById('wpat-stat-trash');
									var statOver = document.getElementById('wpat-stat-overhead');

									if (statRev) statRev.textContent = '...';
									if (statTrans) statTrans.textContent = '...';
									if (statTrash) statTrash.textContent = '...';
									if (statOver) statOver.textContent = '...';

									var xhr = new XMLHttpRequest();
									xhr.open('POST', ajaxUrl, true);
									xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
									xhr.onload = function() {
										var resp = JSON.parse(xhr.responseText || '{}');
										if (resp.success) {
											if (statRev) statRev.textContent = resp.data.revisions;
											if (statTrans) statTrans.textContent = resp.data.expired_transients;
											if (statTrash) statTrash.textContent = resp.data.trashed_total;
											if (statOver) statOver.textContent = resp.data.overhead;
										}
									};
									xhr.send('action=wpat_perf_get_db_stats&security=' + encodeURIComponent(nonce));
								}

								updateStats();

								var refreshBtn = document.getElementById('wpat-perf-refresh-stats-btn');
								if (refreshBtn) {
									refreshBtn.addEventListener('click', updateStats);
								}

								function runDbAction(actionName, btnId, confirmMsg, successMsg) {
									var btn = document.getElementById(btnId);
									if (!btn) return;
									btn.addEventListener('click', function() {
										if (confirmMsg && !confirm(confirmMsg)) return;
										btn.disabled = true;
										var oldText = btn.textContent;
										btn.textContent = '<?php echo esc_js( __( 'Procesando...', 'wp-agency-toolkit' ) ); ?>';
										var resBox = document.getElementById('wpat-perf-action-result');

										var xhr = new XMLHttpRequest();
										xhr.open('POST', ajaxUrl, true);
										xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
										xhr.onload = function() {
											btn.disabled = false;
											btn.textContent = oldText;
											var resp = JSON.parse(xhr.responseText || '{}');
											if (resp.success) {
												if (resBox) {
													resBox.style.color = '#15803d';
													resBox.textContent = '✓ ' + (successMsg || 'Acción completada con éxito.');
												}
												updateStats();
											} else {
												if (resBox) {
													resBox.style.color = '#b91c1c';
													resBox.textContent = '⚠️ Error al ejecutar la acción.';
												}
											}
										};
										xhr.send('action=' + encodeURIComponent(actionName) + '&security=' + encodeURIComponent(nonce));
									});
								}

								runDbAction('wpat_perf_clean_revisions', 'wpat-btn-clean-revisions', '<?php echo esc_js( __( '¿Deseas purgar todas las revisiones antiguas de la base de datos?', 'wp-agency-toolkit' ) ); ?>', '<?php echo esc_js( __( 'Revisiones purgadas correctamente.', 'wp-agency-toolkit' ) ); ?>');
								runDbAction('wpat_perf_clean_transients', 'wpat-btn-clean-transients', '<?php echo esc_js( __( '¿Deseas eliminar todos los transients caducados?', 'wp-agency-toolkit' ) ); ?>', '<?php echo esc_js( __( 'Transients limpiados correctamente.', 'wp-agency-toolkit' ) ); ?>');
								runDbAction('wpat_perf_clean_trash', 'wpat-btn-clean-trash', '<?php echo esc_js( __( '¿Deseas vaciar permanentemente las entradas y comentarios de la papelera?', 'wp-agency-toolkit' ) ); ?>', '<?php echo esc_js( __( 'Papelera vaciada correctamente.', 'wp-agency-toolkit' ) ); ?>');
								runDbAction('wpat_perf_optimize_tables', 'wpat-btn-optimize-tables', '<?php echo esc_js( __( '¿Deseas optimizar y desfragmentar todas las tablas de WordPress?', 'wp-agency-toolkit' ) ); ?>', '<?php echo esc_js( __( 'Tablas optimizadas y desfragmentadas con éxito.', 'wp-agency-toolkit' ) ); ?>');
							});
						</script>
					</div>
				</div>
				<?php
				break;
			case 'reading-progress':
				$saved_post_types = isset( $settings['reading_bar_post_types'] ) && is_array( $settings['reading_bar_post_types'] ) ? $settings['reading_bar_post_types'] : array( 'post' );
				$public_pts       = get_post_types( array( 'public' => true ), 'objects' );
				unset( $public_pts['attachment'] );
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Progreso de Lectura & Tiempo Estimado (UX)</h3>
							<p>Mejora la experiencia de usuario y la retención con una barra de scroll de lectura fluida con compensación de barra de administración y distintivo de tiempo de lectura.</p>
						</div>
						<?php $this->render_module_toggle( 'reading-progress', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">

						<!-- 1. Barra de Progreso de Scroll -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-image-rotate-right" style="color: #6366f1;"></span>
							<?php esc_html_e( 'Barra de Progreso de Scroll', 'wp-agency-toolkit' ); ?>
						</h4>

						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin-bottom: 22px;">
							<div class="wpat-field-group" style="margin-bottom: 16px;">
								<label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
									<input type="checkbox" name="wpat_settings[reading_bar_enabled]" id="wpat_reading_bar_enabled" value="1" <?php checked( ! isset( $settings['reading_bar_enabled'] ) || '1' === (string) $settings['reading_bar_enabled'] ); ?> />
									<?php esc_html_e( 'Activar Barra de Progreso de Lectura', 'wp-agency-toolkit' ); ?>
								</label>
								<p class="description" style="margin-left: 24px;">
									<?php esc_html_e( 'Muestra una barra fluida sin librerías pesadas que avanza conforme el usuario hace scroll hacia abajo.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>

							<!-- Previsualizador de la barra -->
							<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
								<div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 8px;">
									<?php esc_html_e( 'Vista Previa en Vivo de la Barra', 'wp-agency-toolkit' ); ?>
								</div>
								<div style="background: #e2e8f0; border-radius: 4px; overflow: hidden; width: 100%; height: 16px; position: relative;">
									<div id="wpat-reading-bar-preview" style="height: 100%; width: 65%; border-radius: 4px; transition: all 0.2s ease;"></div>
								</div>
							</div>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
								<div>
									<label for="wpat_reading_bar_color" style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
										<?php esc_html_e( 'Color Principal', 'wp-agency-toolkit' ); ?>
									</label>
									<input type="text" name="wpat_settings[reading_bar_color]" id="wpat_reading_bar_color" value="<?php echo esc_attr( isset( $settings['reading_bar_color'] ) ? $settings['reading_bar_color'] : '#2563eb' ); ?>" class="wpat-color-picker" />
								</div>

								<div>
									<label for="wpat_reading_bar_color_end" style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
										<?php esc_html_e( 'Color Final (Degradado Opcional)', 'wp-agency-toolkit' ); ?>
									</label>
									<input type="text" name="wpat_settings[reading_bar_color_end]" id="wpat_reading_bar_color_end" value="<?php echo esc_attr( isset( $settings['reading_bar_color_end'] ) ? $settings['reading_bar_color_end'] : '' ); ?>" class="wpat-color-picker" placeholder="#9333ea" />
								</div>

								<div>
									<label for="wpat_reading_bar_height" style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
										<?php esc_html_e( 'Grosor de la Barra', 'wp-agency-toolkit' ); ?>
									</label>
									<div style="display: flex; align-items: center; gap: 6px;">
										<input type="number" name="wpat_settings[reading_bar_height]" id="wpat_reading_bar_height" min="1" max="30" value="<?php echo esc_attr( isset( $settings['reading_bar_height'] ) ? $settings['reading_bar_height'] : '4' ); ?>" class="small-text" style="height: 32px; text-align: center;" /> px
									</div>
								</div>

								<div>
									<label for="wpat_reading_bar_position" style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
										<?php esc_html_e( 'Posición en Pantalla', 'wp-agency-toolkit' ); ?>
									</label>
									<select name="wpat_settings[reading_bar_position]" id="wpat_reading_bar_position" style="width: 100%; height: 32px;">
										<option value="top" <?php selected( isset( $settings['reading_bar_position'] ) ? $settings['reading_bar_position'] : 'top', 'top' ); ?>>
											<?php esc_html_e( 'Superior (Top - Debajo de Admin Bar)', 'wp-agency-toolkit' ); ?>
										</option>
										<option value="bottom" <?php selected( isset( $settings['reading_bar_position'] ) ? $settings['reading_bar_position'] : 'top', 'bottom' ); ?>>
											<?php esc_html_e( 'Inferior (Bottom)', 'wp-agency-toolkit' ); ?>
										</option>
									</select>
								</div>
							</div>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; padding-top: 14px; border-top: 1px dashed #e2e8f0;">
								<div>
									<label for="wpat_reading_bar_scope" style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
										<?php esc_html_e( 'Cálculo del Progreso (Alcance)', 'wp-agency-toolkit' ); ?>
									</label>
									<select name="wpat_settings[reading_bar_scope]" id="wpat_reading_bar_scope" style="width: 100%; height: 32px;">
										<option value="article" <?php selected( isset( $settings['reading_bar_scope'] ) ? $settings['reading_bar_scope'] : 'article', 'article' ); ?>>
											<?php esc_html_e( 'Contenido del Artículo únicamente (Recomendado)', 'wp-agency-toolkit' ); ?>
										</option>
										<option value="window" <?php selected( isset( $settings['reading_bar_scope'] ) ? $settings['reading_bar_scope'] : 'article', 'window' ); ?>>
											<?php esc_html_e( 'Toda la Página (Incluyendo pie de página y comentarios)', 'wp-agency-toolkit' ); ?>
										</option>
									</select>
									<p class="description" style="margin-top: 4px;">
										<?php esc_html_e( 'El modo artículo alcanza el 100% justo cuando el lector termina de leer el texto principal, antes de los comentarios o el footer.', 'wp-agency-toolkit' ); ?>
									</p>
								</div>

								<div>
									<label style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
										<?php esc_html_e( 'Mostrar en Tipos de Contenido', 'wp-agency-toolkit' ); ?>
									</label>
									<div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 4px;">
										<?php foreach ( $public_pts as $pt_key => $pt_obj ) : ?>
											<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 13px;">
												<input type="checkbox" name="wpat_settings[reading_bar_post_types][]" value="<?php echo esc_attr( $pt_key ); ?>" <?php checked( in_array( $pt_key, $saved_post_types, true ) ); ?> />
												<?php echo esc_html( $pt_obj->labels->name ); ?>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- 2. Distintivo de Tiempo Estimado de Lectura -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-clock" style="color: #6366f1;"></span>
							<?php esc_html_e( 'Distintivo de Tiempo Estimado de Lectura', 'wp-agency-toolkit' ); ?>
						</h4>

						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px;">
							<div class="wpat-field-group" style="margin-bottom: 16px;">
								<label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
									<input type="checkbox" name="wpat_settings[reading_time_enabled]" id="wpat_reading_time_enabled" value="1" <?php checked( ! isset( $settings['reading_time_enabled'] ) || '1' === (string) $settings['reading_time_enabled'] ); ?> />
									<?php esc_html_e( 'Insertar Distintivo Automático al Inicio del Artículo', 'wp-agency-toolkit' ); ?>
								</label>
								<p class="description" style="margin-left: 24px;">
									<?php esc_html_e( 'Calcula las palabras del contenido y muestra una etiqueta con el tiempo estimado justo antes del primer párrafo.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px;">
								<div>
									<label for="wpat_reading_time_style" style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
										<?php esc_html_e( 'Estilo Visual del Badge', 'wp-agency-toolkit' ); ?>
									</label>
									<select name="wpat_settings[reading_time_style]" id="wpat_reading_time_style" style="width: 100%; height: 32px;">
										<option value="pill" <?php selected( isset( $settings['reading_time_style'] ) ? $settings['reading_time_style'] : 'pill', 'pill' ); ?>>
											<?php esc_html_e( 'Pastilla Moderna (Bordes redondeados con fondo)', 'wp-agency-toolkit' ); ?>
										</option>
										<option value="bordered" <?php selected( isset( $settings['reading_time_style'] ) ? $settings['reading_time_style'] : 'pill', 'bordered' ); ?>>
											<?php esc_html_e( 'Borde Lateral Acentuado', 'wp-agency-toolkit' ); ?>
										</option>
										<option value="minimal" <?php selected( isset( $settings['reading_time_style'] ) ? $settings['reading_time_style'] : 'pill', 'minimal' ); ?>>
											<?php esc_html_e( 'Minimalista (Texto e icono plano)', 'wp-agency-toolkit' ); ?>
										</option>
									</select>
								</div>

								<div>
									<label for="wpat_reading_time_wpm" style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
										<?php esc_html_e( 'Velocidad de Lectura (Palabras por Minuto)', 'wp-agency-toolkit' ); ?>
									</label>
									<select name="wpat_settings[reading_time_wpm]" id="wpat_reading_time_wpm" style="width: 100%; height: 32px;">
										<option value="180" <?php selected( isset( $settings['reading_time_wpm'] ) ? (int) $settings['reading_time_wpm'] : 200, 180 ); ?>>
											<?php esc_html_e( '180 PPM (Lectura pausada)', 'wp-agency-toolkit' ); ?>
										</option>
										<option value="200" <?php selected( isset( $settings['reading_time_wpm'] ) ? (int) $settings['reading_time_wpm'] : 200, 200 ); ?>>
											<?php esc_html_e( '200 PPM (Promedio estándar internacional)', 'wp-agency-toolkit' ); ?>
										</option>
										<option value="250" <?php selected( isset( $settings['reading_time_wpm'] ) ? (int) $settings['reading_time_wpm'] : 200, 250 ); ?>>
											<?php esc_html_e( '250 PPM (Lectura rápida)', 'wp-agency-toolkit' ); ?>
										</option>
									</select>
								</div>
							</div>

							<div class="wpat-field-group" style="margin-bottom: 14px;">
								<label for="wpat_reading_time_label" style="display:block; margin-bottom:5px; font-weight:600; font-size:13px;">
									<?php esc_html_e( 'Texto Personalizado del Distintivo', 'wp-agency-toolkit' ); ?>
								</label>
								<input type="text" name="wpat_settings[reading_time_label]" id="wpat_reading_time_label" value="<?php echo esc_attr( isset( $settings['reading_time_label'] ) ? $settings['reading_time_label'] : 'Tiempo estimado de lectura: {time} min' ); ?>" class="large-text" style="height: 32px;" />
								<p class="description" style="margin-top: 4px;">
									<?php esc_html_e( 'Utiliza la etiqueta {time} para insertar el número calculado de minutos.', 'wp-agency-toolkit' ); ?>
								</p>
							</div>

							<div style="background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 12px;">
								<div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 6px;">
									<?php esc_html_e( 'Shortcodes Disponibles para Maquetadores (Elementor / Gutenberg / Divi)', 'wp-agency-toolkit' ); ?>
								</div>
								<p style="margin: 0; font-size: 13px; color: #334155;">
									<code>[tiempo_lectura]</code> o <code>[wpat_reading_time]</code>
								</p>
							</div>
						</div>

						<script type="text/javascript">
							document.addEventListener('DOMContentLoaded', function() {
								function updateBarPreview() {
									var bar = document.getElementById('wpat-reading-bar-preview');
									var c1 = document.getElementById('wpat_reading_bar_color').value || '#2563eb';
									var c2 = document.getElementById('wpat_reading_bar_color_end').value;
									if (bar) {
										if (c2) {
											bar.style.background = 'linear-gradient(90deg, ' + c1 + ' 0%, ' + c2 + ' 100%)';
										} else {
											bar.style.background = c1;
										}
									}
								}
								var inputC1 = document.getElementById('wpat_reading_bar_color');
								var inputC2 = document.getElementById('wpat_reading_bar_color_end');
								if (inputC1) inputC1.addEventListener('input', updateBarPreview);
								if (inputC2) inputC2.addEventListener('input', updateBarPreview);
								updateBarPreview();
							});
						</script>
					</div>
				</div>
				<?php
				break;
			case 'accessibility':
				$a11y_pos         = isset( $settings['accessibility_position'] ) ? $settings['accessibility_position'] : 'bottom-left';
				$a11y_offset      = isset( $settings['accessibility_offset_y'] ) ? $settings['accessibility_offset_y'] : '25';
				$a11y_color       = isset( $settings['accessibility_bg_color'] ) ? $settings['accessibility_bg_color'] : '#2563eb';
				$a11y_zoom        = ! isset( $settings['accessibility_text_zoom'] ) || '1' === $settings['accessibility_text_zoom'];
				$a11y_guide       = ! isset( $settings['accessibility_reading_guide'] ) || '1' === $settings['accessibility_reading_guide'];
				$a11y_cursor      = ! isset( $settings['accessibility_big_cursor'] ) || '1' === $settings['accessibility_big_cursor'];
				$a11y_anim        = ! isset( $settings['accessibility_stop_animations'] ) || '1' === $settings['accessibility_stop_animations'];
				$a11y_spacing     = ! isset( $settings['accessibility_text_spacing'] ) || '1' === $settings['accessibility_text_spacing'];
				$a11y_dyslexic    = ! isset( $settings['accessibility_dyslexic_font'] ) || '1' === $settings['accessibility_dyslexic_font'];
				$a11y_gray        = ! isset( $settings['accessibility_grayscale'] ) || '1' === $settings['accessibility_grayscale'];
				$a11y_contrast    = ! isset( $settings['accessibility_high_contrast'] ) || '1' === $settings['accessibility_high_contrast'];
				$a11y_neg         = ! isset( $settings['accessibility_negative_contrast'] ) || '1' === $settings['accessibility_negative_contrast'];
				$a11y_light       = ! isset( $settings['accessibility_light_bg'] ) || '1' === $settings['accessibility_light_bg'];
				$a11y_links       = ! isset( $settings['accessibility_underline_links'] ) || '1' === $settings['accessibility_underline_links'];
				$a11y_font        = ! isset( $settings['accessibility_readable_font'] ) || '1' === $settings['accessibility_readable_font'];
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Herramientas de Accesibilidad Web (WCAG & ADA Compliant)</h3>
							<p>Widget flotante ultra-ligero de accesibilidad con 12 herramientas inclusivas, guía de lectura, cursor grande, modo dislexia y persistencia en navegador.</p>
						</div>
						<?php $this->render_module_toggle( 'accessibility', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 22px;">
						<form method="post" action="">
							<?php wp_nonce_field( 'wpat_save_settings_action', 'wpat_save_settings_nonce' ); ?>
							<input type="hidden" name="wpat_saving_module" value="accessibility" />

							<!-- Posición y Aspecto del Botón -->
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px;">
								<div class="wpat-field-group">
									<label for="wpat_accessibility_position" style="display:block; margin-bottom:6px; font-weight:600;">Posición del Botón Flotante</label>
									<select name="wpat_settings[accessibility_position]" id="wpat_accessibility_position" style="width: 100%; height: 36px; border-radius: 6px;">
										<option value="bottom-left" <?php selected( $a11y_pos, 'bottom-left' ); ?>>Inferior Izquierda (Recomendado)</option>
										<option value="bottom-right" <?php selected( $a11y_pos, 'bottom-right' ); ?>>Inferior Derecha</option>
										<option value="top-left" <?php selected( $a11y_pos, 'top-left' ); ?>>Superior Izquierda</option>
										<option value="top-right" <?php selected( $a11y_pos, 'top-right' ); ?>>Superior Derecha</option>
									</select>
								</div>

								<div class="wpat-field-group">
									<label for="wpat_accessibility_offset_y" style="display:block; margin-bottom:6px; font-weight:600;">Margen Vertical (Distancia en px)</label>
									<input type="number" name="wpat_settings[accessibility_offset_y]" id="wpat_accessibility_offset_y" min="0" max="500" value="<?php echo esc_attr( $a11y_offset ); ?>" class="small-text" style="width: 100%; height: 36px; border-radius: 6px;" />
									<p class="description" style="margin-top:4px;">Separación en píxeles respecto al borde superior o inferior de la pantalla.</p>
								</div>

								<div class="wpat-field-group">
									<label for="wpat_accessibility_bg_color" style="display:block; margin-bottom:6px; font-weight:600;">Color del Icono Flotante</label>
									<input type="text" name="wpat_settings[accessibility_bg_color]" id="wpat_accessibility_bg_color" value="<?php echo esc_attr( $a11y_color ); ?>" class="wpat-color-picker" />
								</div>
							</div>

							<!-- Herramientas Activas -->
							<div style="border-top: 1px solid #e2e8f0; padding-top: 20px; margin-bottom: 20px;">
								<label style="font-weight: 700; font-size: 13.5px; color: #1e293b; display: block; margin-bottom: 8px;">
									Herramientas Habilitadas en el Menú de Accesibilidad
								</label>
								<p class="description" style="margin-bottom: 15px;">Selecciona las utilidades que los usuarios podrán activar desde el widget flotante:</p>

								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_text_zoom]" value="1" <?php checked( $a11y_zoom ); ?> style="border-radius: 4px;" />
										<span>🔍 <strong>Aumentar / Disminuir Texto</strong> (+40px máx)</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_reading_guide]" value="1" <?php checked( $a11y_guide ); ?> style="border-radius: 4px;" />
										<span>🎯 <strong>Guía de Lectura</strong> (Línea para dislexia)</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_big_cursor]" value="1" <?php checked( $a11y_cursor ); ?> style="border-radius: 4px;" />
										<span>🖱️ <strong>Cursor Grande</strong> (Puntero ampliado)</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_stop_animations]" value="1" <?php checked( $a11y_anim ); ?> style="border-radius: 4px;" />
										<span>⏸️ <strong>Detener Animaciones</strong> (Anti-mareos)</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_text_spacing]" value="1" <?php checked( $a11y_spacing ); ?> style="border-radius: 4px;" />
										<span>↔️ <strong>Espaciado de Texto</strong> (WCAG 1.4.12)</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_dyslexic_font]" value="1" <?php checked( $a11y_dyslexic ); ?> style="border-radius: 4px;" />
										<span>🔤 <strong>Fuente para Dislexia</strong> (OpenDyslexic)</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_grayscale]" value="1" <?php checked( $a11y_gray ); ?> style="border-radius: 4px;" />
										<span>⚪ <strong>Escala de Grises</strong></span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_high_contrast]" value="1" <?php checked( $a11y_contrast ); ?> style="border-radius: 4px;" />
										<span>🌓 <strong>Alto Contraste</strong> (Fondo negro)</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_negative_contrast]" value="1" <?php checked( $a11y_neg ); ?> style="border-radius: 4px;" />
										<span>👁️ <strong>Contraste Negativo</strong> (Invertir)</span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_light_bg]" value="1" <?php checked( $a11y_light ); ?> style="border-radius: 4px;" />
										<span>💡 <strong>Fondo Claro</strong></span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_underline_links]" value="1" <?php checked( $a11y_links ); ?> style="border-radius: 4px;" />
										<span>🔗 <strong>Subrayar Enlaces</strong></span>
									</label>

									<label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; background: #f8fafc; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
										<input type="checkbox" name="wpat_settings[accessibility_readable_font]" value="1" <?php checked( $a11y_font ); ?> style="border-radius: 4px;" />
										<span>🅰️ <strong>Fuente Legible</strong> (Sans-Serif)</span>
									</label>
								</div>
							</div>

							<!-- Integración y Shortcode -->
							<div style="border-top: 1px solid #e2e8f0; padding-top: 16px; margin-bottom: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 14px 18px; border-radius: 6px;">
								<strong style="color: #166534; font-size: 13px; display: block; margin-bottom: 4px;">Disparador Personalizado y Shortcodes:</strong>
								<span style="font-size: 12.5px; color: #374151;">
									Puedes abrir el menú de accesibilidad desde cualquier enlace o menú de tu tema añadiendo la clase CSS <code>wpat-open-a11y</code> o usando el shortcode <code>[wpat_accessibility text="Accesibilidad"]</code>.
								</span>
							</div>

							<div style="display: flex; justify-content: flex-end;">
								<button type="submit" class="button button-primary" style="height: 36px; padding: 0 20px; font-weight: 600;">
									Guardar Ajustes de Accesibilidad
								</button>
							</div>
						</form>
					</div>
				</div>
				<?php
				break;
			case 'svg-support':
				$xml_available = extension_loaded( 'xml' ) || class_exists( 'SimpleXMLElement' );
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Soporte para Archivos SVG & Sanitización Segura</h3>
							<p>Permite subir gráficos vectoriales SVG directamente a la Biblioteca de Medios con sanitización profunda contra inyecciones XSS/XXE, generación automática de dimensiones y previsualización nítida.</p>
						</div>
						<?php $this->render_module_toggle( 'svg-support', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">

						<!-- 1. Estado del Motor SVG -->
						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 22px;">
							<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 14px;">
								<div style="font-size: 11px; color: #166534; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
									<?php esc_html_e( 'Soporte MIME', 'wp-agency-toolkit' ); ?>
								</div>
								<div style="font-size: 14px; font-weight: 600; color: #15803d; display: flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-yes-alt"></span> image/svg+xml (.svg, .svgz)
								</div>
							</div>

							<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 14px;">
								<div style="font-size: 11px; color: #166534; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
									<?php esc_html_e( 'Motor XML del Servidor', 'wp-agency-toolkit' ); ?>
								</div>
								<div style="font-size: 14px; font-weight: 600; color: #15803d; display: flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-yes-alt"></span> <?php echo $xml_available ? 'PHP XML & Parser Activo' : 'XML Básico'; ?>
								</div>
							</div>

							<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px 14px;">
								<div style="font-size: 11px; color: #166534; text-transform: uppercase; font-weight: 600; margin-bottom: 2px;">
									<?php esc_html_e( 'Previsualización en Medios', 'wp-agency-toolkit' ); ?>
								</div>
								<div style="font-size: 14px; font-weight: 600; color: #15803d; display: flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-yes-alt"></span> Renderizado CSS/JS Habilitado
								</div>
							</div>
						</div>

						<!-- 2. Ajustes de Seguridad y Roles -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-shield" style="color: #6366f1;"></span>
							<?php esc_html_e( 'Reglas de Seguridad y Control de Subida', 'wp-agency-toolkit' ); ?>
						</h4>

						<div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[svg_admin_only]" value="1" <?php checked( ! isset( $settings['svg_admin_only'] ) || '1' === (string) $settings['svg_admin_only'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Restringir la subida de SVG exclusivamente a Administradores (Recomendado)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;">
											<?php esc_html_e( 'Evita que usuarios con roles inferiores (Autores, Editores, Clientes) puedan subir archivos vectoriales SVG, bloqueando cualquier riesgo de inyección desde cuentas comprometidas.', 'wp-agency-toolkit' ); ?>
										</p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[svg_sanitize_strict]" value="1" <?php checked( ! isset( $settings['svg_sanitize_strict'] ) || '1' === (string) $settings['svg_sanitize_strict'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Sanitización Estricta Anti-XSS y Anti-XXE en Tiempo de Subida', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;">
											<?php esc_html_e( 'Elimina etiquetas <script>, <foreignObject>, <iframe>, eventos Javascript inline (onload, onclick) y entidades DTD maliciosas antes de guardar el archivo en el servidor.', 'wp-agency-toolkit' ); ?>
										</p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[svg_generate_dimensions]" value="1" <?php checked( ! isset( $settings['svg_generate_dimensions'] ) || '1' === (string) $settings['svg_generate_dimensions'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Cálculo Automático de Dimensiones (viewBox / Ancho / Alto)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 3px 0 0 0; font-weight: normal;">
											<?php esc_html_e( 'Extrae las proporciones originales del SVG para registrarlas en los metadatos de WordPress, garantizando compatibilidad total con Gutenberg, Elementor y la cuadrícula de medios.', 'wp-agency-toolkit' ); ?>
										</p>
									</div>
								</label>
							</div>
						</div>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- 3. Previsualizador / Probador de SVG en Vivo -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
							<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-format-image" style="color: #6366f1;"></span>
								<?php esc_html_e( 'Probador y Validador Rápido de Código SVG', 'wp-agency-toolkit' ); ?>
							</h4>
							<p style="font-size: 13px; color: #64748b; margin-top: 0; margin-bottom: 12px;">
								<?php esc_html_e( 'Pega cualquier fragmento de código <svg>...</svg> para comprobar cómo se renderiza en el navegador antes de subirlo.', 'wp-agency-toolkit' ); ?>
							</p>

							<div style="display: flex; gap: 15px; flex-wrap: wrap;">
								<div style="flex: 2; min-width: 280px;">
									<textarea id="wpat_svg_test_input" rows="4" style="width: 100%; font-family: monospace; font-size: 12px;" placeholder="<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; viewBox=&quot;0 0 24 24&quot; width=&quot;48&quot; height=&quot;48&quot; fill=&quot;#6366f1&quot;><circle cx=&quot;12&quot; cy=&quot;12&quot; r=&quot;10&quot;/></svg>"></textarea>
									<button type="button" id="wpat_svg_test_btn" class="button button-secondary button-small" style="margin-top: 6px;">
										<?php esc_html_e( 'Validar & Previsualizar', 'wp-agency-toolkit' ); ?>
									</button>
								</div>
								<div style="flex: 1; min-width: 150px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 6px; display: flex; align-items: center; justify-content: center; padding: 12px; min-height: 100px;">
									<div id="wpat_svg_preview_box" style="max-width: 120px; max-height: 120px; text-align: center; color: #94a3b8; font-size: 12px;">
										<em><?php esc_html_e( 'Vista previa aquí', 'wp-agency-toolkit' ); ?></em>
									</div>
								</div>
							</div>
						</div>

						<script type="text/javascript">
							document.addEventListener('DOMContentLoaded', function() {
								var testBtn = document.getElementById('wpat_svg_test_btn');
								if (testBtn) {
									testBtn.addEventListener('click', function() {
										var raw = (document.getElementById('wpat_svg_test_input').value || '').trim();
										var box = document.getElementById('wpat_svg_preview_box');
										if (!raw || raw.indexOf('<svg') === -1) {
											box.innerHTML = '<span style="color:#ef4444; font-size:12px;">Código SVG no válido</span>';
											return;
										}
										// Sanitización cliente de prueba
										var clean = raw.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
										               .replace(/on\w+="[^"]*"/gi, '')
										               .replace(/on\w+='[^']*'/gi, '');
										box.innerHTML = clean;
									});
								}
							});
						</script>
					</div>
				</div>
				<?php
				break;
			case 'image-optimizer':
				require_once WPAT_PATH . 'includes/modules/class-wpat-image-optimizer.php';
				$server_webp_support = function_exists( 'imagick_is_format_supported' ) || function_exists( 'imagick_read_image' ) || ( function_exists( 'imagewebp' ) );
				if ( function_exists( 'wp_image_editor_supports' ) ) {
					$server_webp_support = wp_image_editor_supports( array( 'methods' => array( 'rotate' ), 'mime_type' => 'image/webp' ) );
				}
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Optimización de Medios, Conversión a WebP & Limpiador Seguro</h3>
							<p>Convierte automáticamente imágenes a formato WebP de última generación, escala dimensiones desproporcionadas en subida para ahorrar espacio, y analiza de forma 100% segura imágenes huérfanas sin romper Elementor ni WooCommerce.</p>
						</div>
						<?php $this->render_module_toggle( 'image-optimizer', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">

						<!-- Estado del Servidor -->
						<div style="background: <?php echo $server_webp_support ? '#f0fdf4' : '#fef2f2'; ?>; border: 1px solid <?php echo $server_webp_support ? '#bbf7d0' : '#fecaca'; ?>; border-radius: 8px; padding: 14px 18px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
							<div style="display: flex; align-items: center; gap: 10px;">
								<span class="dashicons <?php echo $server_webp_support ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="font-size: 22px; width: 22px; height: 22px; color: <?php echo $server_webp_support ? '#16a34a' : '#dc2626'; ?>;"></span>
								<div>
									<strong style="color: <?php echo $server_webp_support ? '#166534' : '#991b1b'; ?>; font-size: 14px;">
										<?php echo $server_webp_support ? 'Soporte WebP Nativo Activo en el Servidor (GD / Imagick)' : 'Tu servidor no tiene soporte nativo para codificar WebP'; ?>
									</strong>
									<p style="margin: 2px 0 0 0; font-size: 12px; color: <?php echo $server_webp_support ? '#15803d' : '#b91c1c'; ?>;">
										<?php echo $server_webp_support ? 'Todas las imágenes subidas y procesadas se convertirán a formato WebP ligero de alta eficiencia sin pérdidas perceptibles.' : 'El módulo optimizará y comprimirá automáticamente las imágenes en sus formatos originales (JPG/PNG).'; ?>
									</p>
								</div>
							</div>
							<span style="font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 9999px; background: <?php echo $server_webp_support ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $server_webp_support ? '#15803d' : '#b91c1c'; ?>;">
								<?php echo $server_webp_support ? 'WebP Listo' : 'Modo Compresión JPG/PNG'; ?>
							</span>
						</div>

						<!-- 1. Ajustes de Optimización en Caliente (Subidas nuevas) -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-admin-settings" style="color: var(--wpat-primary, #2563eb);"></span>
							Ajustes de Conversión y Redimensionamiento Automático
						</h4>

						<div class="wpat-field-group" style="margin-bottom: 18px;">
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[image_optimizer_auto_convert]" value="1" <?php checked( ! isset( $settings['image_optimizer_auto_convert'] ) || '1' === $settings['image_optimizer_auto_convert'] ); ?>>
								<span><strong>Convertir automáticamente a WebP al subir nuevas imágenes a la biblioteca</strong></span>
							</label>
						</div>

						<div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 18px;">
							<div class="wpat-field-group" style="flex: 1; min-width: 180px;">
								<label for="wpat_image_optimizer_quality" style="font-weight: 600;">Calidad de Compresión WebP (60 - 100%)</label>
								<input type="number" name="wpat_settings[image_optimizer_quality]" id="wpat_image_optimizer_quality" value="<?php echo esc_attr( isset( $settings['image_optimizer_quality'] ) ? $settings['image_optimizer_quality'] : '82' ); ?>" min="40" max="100" class="regular-text" style="width: 100%;" />
								<p class="description">Recomendado: 82%. Equilibrio ideal entre peso ultraligero y fidelidad visual.</p>
							</div>

							<div class="wpat-field-group" style="flex: 1; min-width: 180px;">
								<label for="wpat_image_optimizer_max_width" style="font-weight: 600;">Ancho Máximo (px)</label>
								<input type="number" name="wpat_settings[image_optimizer_max_width]" id="wpat_image_optimizer_max_width" value="<?php echo esc_attr( isset( $settings['image_optimizer_max_width'] ) ? $settings['image_optimizer_max_width'] : '1920' ); ?>" min="0" step="10" class="regular-text" style="width: 100%;" />
								<p class="description">Escala si supera este ancho (Pon 0 para no limitar).</p>
							</div>

							<div class="wpat-field-group" style="flex: 1; min-width: 180px;">
								<label for="wpat_image_optimizer_max_height" style="font-weight: 600;">Alto Máximo (px)</label>
								<input type="number" name="wpat_settings[image_optimizer_max_height]" id="wpat_image_optimizer_max_height" value="<?php echo esc_attr( isset( $settings['image_optimizer_max_height'] ) ? $settings['image_optimizer_max_height'] : '1920' ); ?>" min="0" step="10" class="regular-text" style="width: 100%;" />
								<p class="description">Escala si supera este alto (Pon 0 para no limitar).</p>
							</div>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 22px;">
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[image_optimizer_keep_original]" value="1" <?php checked( isset( $settings['image_optimizer_keep_original'] ) ? $settings['image_optimizer_keep_original'] : '0', '1' ); ?>>
								<span><strong>Conservar copia del archivo original (JPG/PNG) en el servidor</strong> (Desactivado por defecto para ahorrar hasta un 70% de espacio en disco).</span>
							</label>
						</div>

						<hr style="border:none; border-top: 1px solid #e2e8f0; margin: 25px 0;" />

						<!-- 2. Optimización Retroactiva Masiva -->
						<div class="wpat-bulk-optimizer-section">
							<h4 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-images-alt2" style="color: #0284c7;"></span>
								Optimización Retroactiva Masiva (Biblioteca Existente)
							</h4>
							<p class="description" style="margin: 0 0 16px 0;">Escanea y convierte por lotes las imágenes que ya tienes en WordPress sin riesgo de saturar la memoria del servidor.</p>
							
							<!-- Filtros del Optimizador Masivo -->
							<div class="wpat-bulk-filters" style="display: flex; flex-wrap: wrap; gap: 15px; background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 16px; border: 1px solid #e2e8f0;">
								<div style="flex: 1; min-width: 160px;">
									<label for="wpat_bulk_filter_min_size" style="display:block; font-weight:600; margin-bottom:5px; font-size:12px;">Peso Mínimo (en KB)</label>
									<input type="number" id="wpat_bulk_filter_min_size" placeholder="Ej: 300" min="0" style="width: 100%;" />
									<p class="description" style="font-size:11px; margin-top:2px;">Opcional: solo optimizar imágenes que pesen más de este valor.</p>
								</div>
								<div style="flex: 1; min-width: 160px;">
									<label for="wpat_bulk_filter_date_start" style="display:block; font-weight:600; margin-bottom:5px; font-size:12px;">Fecha Desde</label>
									<input type="date" id="wpat_bulk_filter_date_start" style="width: 100%;" />
								</div>
								<div style="flex: 1; min-width: 160px;">
									<label for="wpat_bulk_filter_date_end" style="display:block; font-weight:600; margin-bottom:5px; font-size:12px;">Fecha Hasta</label>
									<input type="date" id="wpat_bulk_filter_date_end" style="width: 100%;" />
								</div>
								<div style="flex: 1 1 100%; border-top: 1px dashed #cbd5e1; padding-top: 12px; margin-top: 4px;">
									<label style="display:block; font-weight:600; margin-bottom:8px; font-size:12px;">Formatos de imagen a escanear:</label>
									<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center;">
										<label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
											<input type="checkbox" class="wpat-bulk-format-cb" value="image/jpeg" checked />
											<span>JPG / JPEG (<code>.jpg</code>, <code>.jpeg</code>)</span>
										</label>
										<label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
											<input type="checkbox" class="wpat-bulk-format-cb" value="image/png" checked />
											<span>PNG (<code>.png</code>)</span>
										</label>
										<label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
											<input type="checkbox" class="wpat-bulk-format-cb" value="image/gif" />
											<span>GIF (<code>.gif</code>)</span>
											<span style="font-size:11px; color:#64748b; font-weight:normal;">(Desmarcado por defecto para no perder animación en GIFs animados)</span>
										</label>
									</div>
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
									<span>Pendientes: <strong id="wpat_stat_pending" style="color: var(--wpat-text);">0</strong></span> | 
									<span>Procesadas: <strong id="wpat_stat_processed" style="color: var(--wpat-success);">0</strong></span> | 
									<span>Omitidas/Errores: <strong id="wpat_stat_failed" style="color: #ea580c;">0</strong></span>
									<span id="wpat_stat_weight_container" style="display: none; margin-left: 10px; padding-left: 10px; border-left: 1px solid var(--wpat-border);">
										| Peso original: <strong id="wpat_stat_total_weight" style="color: var(--wpat-text);">0 B</strong> 
										| Ahorro estimado (WebP): <strong id="wpat_stat_opt_weight" style="color: var(--wpat-success);">0 B</strong>
									</span>
								</div>
								<div id="wpat_bulk_log" class="wpat-bulk-log-box" style="max-height: 140px; overflow-y: auto; background: #0f172a; color: #f8fafc; padding: 14px; font-family: monospace; font-size: 11px; border-radius: 6px; line-height: 1.5; border: 1px solid #1e293b;">
									[Consola de estado lista...]
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- 3. Módulo: Limpiador de Imágenes Huérfanas (Sin Falsos Positivos) -->
				<div class="wpat-module-card" style="margin-top: 24px;">
					<div class="wpat-module-header" style="border-bottom: 1px solid var(--wpat-border);">
						<div class="wpat-module-info">
							<h3 style="color:#0f172a; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-trash" style="color: #e11d48;"></span>
								Limpiador Seguro de Imágenes Huérfanas (No Usadas)
							</h3>
							<p>Escanea tu base de datos y detecta imágenes que no están referenciadas en ninguna entrada, página, borrador, producto o galería WooCommerce, logo, categoría o diseño de Elementor.</p>
						</div>
					</div>
					<div class="wpat-module-body" style="padding: 20px;">
						<div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; font-size: 13px; color: #9f1239;">
							<span class="dashicons dashicons-shield" style="color: #e11d48; vertical-align: middle; margin-right: 4px;"></span>
							<strong>Protección multi-capa activa:</strong> El motor verifica 7 fuentes distintas (miniaturas destacadas, galerías Woo, taxonomías, logos, Elementor JSON/URL, bloques Gutenberg y campos ACF/JetEngine) para garantizar cero falsos positivos antes de marcar un archivo como huérfano.
						</div>

						<div class="wpat-bulk-actions" style="display: flex; gap: 10px; align-items: center;">
							<button type="button" class="button button-secondary" id="wpat_scan_orphans_btn">Buscar Imágenes Huérfanas</button>
							<button type="button" class="button button-link-delete" id="wpat_delete_selected_orphans_btn" style="display:none; color: #b91c1c; font-weight: 600;">Eliminar Seleccionadas</button>
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
							<div style="max-height: 340px; overflow-y: auto; border: 1px solid #dcdcde; border-radius: 6px;">
								<table class="wp-list-table widefat fixed striped" style="box-shadow:none; border:none;">
									<thead>
										<tr>
											<th style="width: 40px; padding: 10px; text-align: center;"><input type="checkbox" id="wpat_select_all_orphans" /></th>
											<th style="width: 60px; padding: 10px; text-align: center;">Miniatura</th>
											<th style="padding: 10px;">Nombre del Archivo / ID</th>
											<th style="width: 100px; padding: 10px;">Tamaño</th>
											<th style="width: 110px; padding: 10px;">Fecha Subida</th>
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
				require_once WPAT_PATH . 'includes/modules/class-wpat-smtp.php';
				$smtp_logs = WPAT_SMTP::get_delivery_log();
				$site_domain = wp_parse_url( home_url(), PHP_URL_HOST );
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Servidor de Correo SMTP & Registro de Envíos</h3>
							<p>Enruta de forma segura y fiable todos los correos transaccionales y notificaciones de WordPress mediante conexión SMTP autenticada, con presets de proveedores y registro de entregas.</p>
						</div>
						<?php $this->render_module_toggle( 'smtp', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">
						
						<!-- 1. Plantillas Rápidas de Proveedores SMTP (Presets) -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px;">
							<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-flash" style="color: #f59e0b;"></span>
								Plantillas Rápidas de Configuración (1 Clic)
							</h4>
							<p style="margin: 0 0 14px 0; font-size: 13px; color: #64748b;">
								Haz clic en tu proveedor para autocompletar el Servidor (Host), Puerto y Cifrado recomendados al instante:
							</p>
							<div style="display: flex; flex-wrap: wrap; gap: 8px;" id="wpat_smtp_presets_wrapper">
								<button type="button" class="button wpat-smtp-preset-btn" data-host="smtp.gmail.com" data-port="587" data-secure="tls" data-auth="1" data-provider="Gmail / Workspace" title="Requiere contraseña de aplicación de Google">
									<span class="dashicons dashicons-google" style="vertical-align: middle; margin-right: 2px;"></span> Gmail / Google Workspace
								</button>
								<button type="button" class="button wpat-smtp-preset-btn" data-host="smtp.office365.com" data-port="587" data-secure="tls" data-auth="1" data-provider="Microsoft 365">
									<span class="dashicons dashicons-microsoft" style="vertical-align: middle; margin-right: 2px;"></span> Microsoft 365 / Outlook
								</button>
								<button type="button" class="button wpat-smtp-preset-btn" data-host="smtp.sendgrid.net" data-port="587" data-secure="tls" data-auth="1" data-user="apikey" data-provider="SendGrid">
									<span class="dashicons dashicons-email-alt2" style="vertical-align: middle; margin-right: 2px;"></span> SendGrid
								</button>
								<button type="button" class="button wpat-smtp-preset-btn" data-host="smtp-relay.brevo.com" data-port="587" data-secure="tls" data-auth="1" data-provider="Brevo">
									<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 2px;"></span> Brevo (Sendinblue)
								</button>
								<button type="button" class="button wpat-smtp-preset-btn" data-host="smtp.mailgun.org" data-port="587" data-secure="tls" data-auth="1" data-provider="Mailgun">
									<span class="dashicons dashicons-cloud" style="vertical-align: middle; margin-right: 2px;"></span> Mailgun
								</button>
								<button type="button" class="button wpat-smtp-preset-btn" data-host="smtp.hostinger.com" data-port="465" data-secure="ssl" data-auth="1" data-provider="Hostinger">
									<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 2px;"></span> Hostinger
								</button>
								<button type="button" class="button wpat-smtp-preset-btn" data-host="mail.<?php echo esc_attr( $site_domain ); ?>" data-port="465" data-secure="ssl" data-auth="1" data-provider="cPanel / Hosting">
									<span class="dashicons dashicons-server" style="vertical-align: middle; margin-right: 2px;"></span> cPanel / Hosting Propio
								</button>
							</div>
							<div id="wpat_smtp_preset_notice" style="display:none; margin-top: 10px; font-size: 12px; color: #0284c7; background: #e0f2fe; padding: 6px 12px; border-radius: 4px;"></div>
						</div>

						<!-- 2. Credenciales y Conexión SMTP -->
						<div style="display: flex; flex-wrap: wrap; gap: 20px;">
							<div class="wpat-field-group" style="flex: 2; min-width: 250px;">
								<label for="wpat_smtp_host" style="font-weight: 600;">Servidor SMTP (Host)</label>
								<input type="text" name="wpat_settings[smtp_host]" id="wpat_smtp_host" value="<?php echo esc_attr( $settings['smtp_host'] ); ?>" class="regular-text" placeholder="smtp.ejemplo.com" style="width:100%;" />
								<p class="description">El host del servidor de correo saliente.</p>
							</div>

							<div class="wpat-field-group" style="flex: 1; min-width: 120px;">
								<label for="wpat_smtp_port" style="font-weight: 600;">Puerto SMTP</label>
								<input type="number" name="wpat_settings[smtp_port]" id="wpat_smtp_port" value="<?php echo esc_attr( ! empty( $settings['smtp_port'] ) ? $settings['smtp_port'] : '587' ); ?>" class="regular-text" placeholder="587" style="width:100%;" />
								<p class="description">587 (TLS), 465 (SSL) o 25.</p>
							</div>

							<div class="wpat-field-group" style="flex: 1; min-width: 150px;">
								<label for="wpat_smtp_secure" style="font-weight: 600;">Cifrado de Seguridad</label>
								<select name="wpat_settings[smtp_secure]" id="wpat_smtp_secure" style="width: 100%;">
									<option value="none" <?php selected( $settings['smtp_secure'], 'none' ); ?>>Ninguno (Sin cifrado)</option>
									<option value="ssl" <?php selected( $settings['smtp_secure'], 'ssl' ); ?>>SSL (Recomendado para 465)</option>
									<option value="tls" <?php selected( $settings['smtp_secure'], 'tls' ); ?>>TLS / STARTTLS (Recomendado para 587)</option>
								</select>
							</div>
						</div>

						<div class="wpat-field-group" style="margin-top: 15px;">
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[smtp_insecure]" value="1" <?php checked( $settings['smtp_insecure'], '1' ); ?>>
								<span><strong>Ignorar verificación de certificados SSL/TLS</strong> (Útil en servidores con certificados auto-firmados o desarrollo local).</span>
							</label>
						</div>

						<div class="wpat-field-group" style="margin-top: 15px;">
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[smtp_auth]" value="1" id="wpat_smtp_auth" <?php checked( $settings['smtp_auth'], '1' ); ?>>
								<span><strong>El servidor SMTP requiere autenticación</strong> (Recomendado para la mayoría de proveedores).</span>
							</label>
						</div>

						<div class="wpat-smtp-auth-fields wpat-sub-field" <?php $this->style_conditional_display( $settings['smtp_auth'] ); ?> style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin-top: 15px;">
							<div style="display: flex; flex-wrap: wrap; gap: 20px;">
								<div class="wpat-field-group" style="flex: 1; min-width: 220px;">
									<label for="wpat_smtp_username" style="font-weight: 600;">Usuario SMTP (Email o API Key)</label>
									<input type="text" name="wpat_settings[smtp_username]" id="wpat_smtp_username" value="<?php echo esc_attr( $settings['smtp_username'] ); ?>" class="regular-text" placeholder="usuario@ejemplo.com o apikey" style="width:100%;" autocomplete="off" />
									<p class="description">Tu cuenta de correo o identificador de API.</p>
								</div>
								<div class="wpat-field-group" style="flex: 1; min-width: 220px;">
									<label for="wpat_smtp_password" style="font-weight: 600;">Contraseña SMTP o Token API</label>
									<input type="password" name="wpat_settings[smtp_password]" id="wpat_smtp_password" value="<?php echo esc_attr( $settings['smtp_password'] ); ?>" class="regular-text" placeholder="••••••••••••••••" style="width:100%;" autocomplete="new-password" />
									<p class="description">Contraseña o clave de aplicación generada.</p>
								</div>
							</div>
						</div>

						<hr style="border:none; border-top: 1px solid #e2e8f0; margin: 25px 0;" />

						<!-- 3. Encabezados de Remitente (From / Reply-To) -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b;">
							<span class="dashicons dashicons-email" style="color: var(--wpat-primary, #2563eb);"></span>
							Configuración del Remitente (From & Reply-To)
						</h4>

						<div style="display: flex; flex-wrap: wrap; gap: 20px;">
							<div class="wpat-field-group" style="flex: 1; min-width: 220px;">
								<label for="wpat_smtp_from_email" style="font-weight: 600;">Email del Remitente</label>
								<input type="email" name="wpat_settings[smtp_from_email]" id="wpat_smtp_from_email" value="<?php echo esc_attr( $settings['smtp_from_email'] ); ?>" class="regular-text" placeholder="webmaster@ejemplo.com" style="width:100%;" />
								<p class="description">Dirección visible de origen de los correos salientes.</p>
							</div>
							<div class="wpat-field-group" style="flex: 1; min-width: 220px;">
								<label for="wpat_smtp_from_name" style="font-weight: 600;">Nombre del Remitente</label>
								<input type="text" name="wpat_settings[smtp_from_name]" id="wpat_smtp_from_name" value="<?php echo esc_attr( $settings['smtp_from_name'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="width:100%;" />
								<p class="description">Nombre mostrado al receptor del mensaje.</p>
							</div>
							<div class="wpat-field-group" style="flex: 1; min-width: 220px;">
								<label for="wpat_smtp_reply_to" style="font-weight: 600;">Email de Respuesta (Reply-To Opcional)</label>
								<input type="email" name="wpat_settings[smtp_reply_to]" id="wpat_smtp_reply_to" value="<?php echo esc_attr( isset( $settings['smtp_reply_to'] ) ? $settings['smtp_reply_to'] : '' ); ?>" class="regular-text" placeholder="soporte@ejemplo.com" style="width:100%;" />
								<p class="description">A dónde irán dirigidas las respuestas de los usuarios.</p>
							</div>
						</div>

						<div class="wpat-field-group" style="margin-top: 15px;">
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[smtp_force_from]" value="1" <?php checked( isset( $settings['smtp_force_from'] ) ? $settings['smtp_force_from'] : '0', '1' ); ?>>
								<span><strong>Forzar remitente en todos los correos</strong> (Sobrescribe cualquier remitente enviado por otros plugins o formularios para evitar rechazos por SPF/DKIM).</span>
							</label>
						</div>

						<div class="wpat-field-group" style="margin-top: 10px;">
							<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[smtp_log_enabled]" value="1" <?php checked( ! isset( $settings['smtp_log_enabled'] ) || '1' === $settings['smtp_log_enabled'] ); ?>>
								<span><strong>Activar registro automático de envíos (Delivery Log)</strong> para monitorizar el estado de entrega en tiempo real.</span>
							</label>
						</div>

					</div>
				</div>

				<!-- 4. Caja de Diagnóstico y Envío de Prueba -->
				<div class="wpat-module-card" style="margin-top: 24px;">
					<div class="wpat-module-header" style="border-bottom: 1px solid var(--wpat-border);">
						<div class="wpat-module-info">
							<h3 style="color:#0f172a; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-testimonial" style="color: #2563eb;"></span>
								Diagnóstico: Enviar Correo de Prueba en Tiempo Real
							</h3>
							<p>Verifica al instante si tu conexión SMTP, autenticación y certificados funcionan sin errores antes de poner el sitio en producción.</p>
						</div>
					</div>
					<div class="wpat-module-body" style="background:#ffffff; padding: 20px;">
						<?php wp_nonce_field( 'wpat_smtp_test_nonce_action', 'wpat_smtp_test_nonce' ); ?>
						
						<div style="display:flex; gap:12px; align-items:flex-end; max-width: 600px; flex-wrap: wrap;">
							<div style="flex:1; min-width: 240px;">
								<label for="wpat_smtp_test_email" style="font-weight: 600; font-size:13px; margin-bottom:6px; display:block;">Email Destinatario de la Prueba</label>
								<input type="email" id="wpat_smtp_test_email" class="regular-text" placeholder="tu-email@dominio.com" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" style="width:100%;" />
							</div>
							<button type="button" class="button button-primary" id="wpat_smtp_send_test_btn" style="height:36px; padding: 0 18px; display: inline-flex; align-items: center; gap: 6px;">
								<span class="dashicons dashicons-email-alt" style="margin-top: 2px;"></span> Enviar Prueba
							</button>
						</div>
						<div id="wpat_smtp_test_result" style="display:none; margin-top:16px; padding:16px; border-radius:8px; font-size:13px; line-height:1.5;"></div>
					</div>
				</div>

				<!-- 5. Historial de Envíos Recientes (Email Delivery Log) -->
				<div class="wpat-module-card" style="margin-top: 24px;">
					<div class="wpat-module-header" style="border-bottom: 1px solid var(--wpat-border); display: flex; justify-content: space-between; align-items: center;">
						<div class="wpat-module-info">
							<h3 style="color:#0f172a; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-list-view" style="color: #10b981;"></span>
								Historial de Envíos Recientes (Últimos 30 correos)
							</h3>
							<p>Supervisa el estado de entrega de todos los emails transaccionales emitidos por WordPress, formularios y WooCommerce.</p>
						</div>
						<div>
							<button type="button" class="button button-secondary" id="wpat_smtp_clear_log_btn" style="display: inline-flex; align-items: center; gap: 5px;">
								<span class="dashicons dashicons-trash" style="font-size: 16px; line-height: 20px;"></span> Vaciar Historial
							</button>
						</div>
					</div>
					<div class="wpat-module-body" style="background:#ffffff; padding: 0;">
						<div style="overflow-x: auto;">
							<table class="wp-list-table widefat fixed striped" style="border: none;" id="wpat_smtp_log_table">
								<thead>
									<tr>
										<th style="width: 110px; padding: 12px 16px;">Estado</th>
										<th style="padding: 12px 16px;">Destinatario</th>
										<th style="padding: 12px 16px;">Asunto</th>
										<th style="width: 170px; padding: 12px 16px;">Fecha y Hora</th>
									</tr>
								</thead>
								<tbody id="wpat_smtp_log_tbody">
									<?php if ( empty( $smtp_logs ) ) : ?>
										<tr id="wpat_smtp_no_logs_row">
											<td colspan="4" style="text-align: center; padding: 30px; color: #64748b;">
												<span class="dashicons dashicons-info" style="font-size: 28px; width: 28px; height: 28px; display: block; margin: 0 auto 8px; color: #94a3b8;"></span>
												No hay envíos registrados todavía. Realiza un envío de prueba o espera a que tu sitio emita un correo.
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $smtp_logs as $log_entry ) : 
											$is_ok    = isset( $log_entry['status'] ) && 'success' === $log_entry['status'];
											$time_str = isset( $log_entry['time'] ) ? wp_date( 'd/m/Y H:i:s', $log_entry['time'] ) : '-';
											$to_str   = isset( $log_entry['to'] ) ? esc_html( $log_entry['to'] ) : '-';
											$sub_str  = isset( $log_entry['subject'] ) ? esc_html( $log_entry['subject'] ) : '-';
											$err_str  = isset( $log_entry['error'] ) && ! empty( $log_entry['error'] ) ? esc_html( $log_entry['error'] ) : '';
										?>
											<tr>
												<td style="padding: 12px 16px;">
													<?php if ( $is_ok ) : ?>
														<span style="display: inline-flex; align-items: center; gap: 4px; background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600;">
															<span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px;"></span> Enviado
														</span>
													<?php else : ?>
														<span style="display: inline-flex; align-items: center; gap: 4px; background: #fee2e2; color: #b91c1c; padding: 3px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600;" title="<?php echo esc_attr( $err_str ); ?>">
															<span class="dashicons dashicons-dismiss" style="font-size: 14px; width: 14px; height: 14px;"></span> Error
														</span>
													<?php endif; ?>
												</td>
												<td style="padding: 12px 16px; font-weight: 500; color: #1e293b;">
													<?php echo $to_str; ?>
												</td>
												<td style="padding: 12px 16px; color: #334155;">
													<strong><?php echo $sub_str; ?></strong>
													<?php if ( ! $is_ok && ! empty( $err_str ) ) : ?>
														<div style="font-size: 11px; color: #b91c1c; margin-top: 4px; font-family: monospace;">
															<?php echo $err_str; ?>
														</div>
													<?php endif; ?>
												</td>
												<td style="padding: 12px 16px; color: #64748b; font-size: 12px;">
													<?php echo esc_html( $time_str ); ?>
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
			case 'sitemap-xml':
				require_once WPAT_PATH . 'includes/modules/class-wpat-sitemap-xml.php';
				$sitemap_stats = WPAT_Sitemap_XML::get_sitemap_summary();
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Generador de Sitemap XML Profesional</h3>
							<p>Genera un índice XML ultraligero con soporte para imágenes, hoja de estilos visual XSL interactiva, exclusión de etiquetas <code>noindex</code> y enlace directo para Google Search Console.</p>
						</div>
						<?php $this->render_module_toggle( 'sitemap-xml', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">

						<!-- 1. Estado en Vivo y Enlaces directos a Motores de Búsqueda -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin-bottom: 22px;">
							<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
								<div>
									<h4 style="margin: 0; font-size: 15px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
										<span class="dashicons dashicons-networking" style="color: #10b981;"></span>
										<?php esc_html_e( 'URL del Sitemap XML Activo', 'wp-agency-toolkit' ); ?>
									</h4>
									<div style="margin-top: 4px;">
										<code style="font-size: 13px; font-weight: 600; background: #ffffff; padding: 4px 10px; border-radius: 4px; border: 1px solid #cbd5e1; color: #0284c7;">
											<?php echo esc_url( $sitemap_stats['sitemap_url'] ); ?>
										</code>
									</div>
								</div>
								<div style="display: flex; gap: 8px; flex-wrap: wrap;">
									<a href="<?php echo esc_url( $sitemap_stats['visual_url'] ); ?>" target="_blank" class="button button-primary" style="display: inline-flex; align-items: center; gap: 4px;">
										<span class="dashicons dashicons-visibility" style="font-size: 15px; line-height: 20px; width: 15px; height: 15px;"></span>
										<?php esc_html_e( 'Abrir Sitemap Visual', 'wp-agency-toolkit' ); ?>
									</a>
									<a href="<?php echo esc_url( $sitemap_stats['raw_url'] ); ?>" target="_blank" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
										<span class="dashicons dashicons-external" style="font-size: 15px; line-height: 20px; width: 15px; height: 15px;"></span>
										<?php esc_html_e( 'Ver XML Raw', 'wp-agency-toolkit' ); ?>
									</a>
									<a href="<?php echo esc_url( $sitemap_stats['dl_url'] ); ?>" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
										<span class="dashicons dashicons-download" style="font-size: 15px; line-height: 20px; width: 15px; height: 15px;"></span>
										<?php esc_html_e( 'Descargar XML', 'wp-agency-toolkit' ); ?>
									</a>
								</div>
							</div>

							<!-- Estadísticas de URLs -->
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-top: 14px;">
								<div style="background: #ffffff; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600;"><?php esc_html_e( 'Total Estimado', 'wp-agency-toolkit' ); ?></div>
									<div style="font-size: 16px; font-weight: bold; color: #1e293b;"><?php echo (int) $sitemap_stats['total_est']; ?> URLs</div>
								</div>
								<div style="background: #ffffff; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600;"><?php esc_html_e( 'Entradas Blog', 'wp-agency-toolkit' ); ?></div>
									<div style="font-size: 16px; font-weight: bold; color: #2563eb;"><?php echo (int) $sitemap_stats['posts']; ?></div>
								</div>
								<div style="background: #ffffff; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600;"><?php esc_html_e( 'Páginas', 'wp-agency-toolkit' ); ?></div>
									<div style="font-size: 16px; font-weight: bold; color: #059669;"><?php echo (int) $sitemap_stats['pages']; ?></div>
								</div>
								<?php if ( class_exists( 'WooCommerce' ) ) : ?>
									<div style="background: #ffffff; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
										<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600;"><?php esc_html_e( 'Productos', 'wp-agency-toolkit' ); ?></div>
										<div style="font-size: 16px; font-weight: bold; color: #7c3aed;"><?php echo (int) $sitemap_stats['products']; ?></div>
									</div>
								<?php endif; ?>
								<div style="background: #ffffff; padding: 10px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
									<div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600;"><?php esc_html_e( 'Taxonomías', 'wp-agency-toolkit' ); ?></div>
									<div style="font-size: 16px; font-weight: bold; color: #d97706;"><?php echo (int) $sitemap_stats['taxonomies']; ?></div>
								</div>
							</div>

							<!-- Accesos rápidos Search Console & Bing -->
							<div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #cbd5e1; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
								<span style="font-size: 12px; font-weight: 600; color: #475569;"><?php esc_html_e( 'Indexación Rápida:', 'wp-agency-toolkit' ); ?></span>
								<a href="https://search.google.com/search-console/sitemaps" target="_blank" style="font-size: 12px; color: #2563eb; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
									<span class="dashicons dashicons-google" style="font-size: 14px; line-height: 16px; width: 14px; height: 14px;"></span> Google Search Console &rarr;
								</a>
								<a href="https://www.bing.com/webmasters/sitemaps" target="_blank" style="font-size: 12px; color: #0284c7; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
									<span class="dashicons dashicons-search" style="font-size: 14px; line-height: 16px; width: 14px; height: 14px;"></span> Bing Webmaster Tools &rarr;
								</a>
							</div>
						</div>

						<!-- 2. Opciones de Contenido a Incluir -->
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-admin-settings" style="color: #6366f1;"></span>
							<?php esc_html_e( 'Contenidos a Incluir en el Sitemap', 'wp-agency-toolkit' ); ?>
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; margin-bottom: 22px;">
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[sitemap_include_posts]" value="1" <?php checked( ! isset( $settings['sitemap_include_posts'] ) || '1' === (string) $settings['sitemap_include_posts'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Incluir Entradas del Blog (Posts)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 2px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Prioridad 0.8 / Frecuencia semanal.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[sitemap_include_pages]" value="1" <?php checked( ! isset( $settings['sitemap_include_pages'] ) || '1' === (string) $settings['sitemap_include_pages'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Incluir Páginas Estáticas (Pages)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 2px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Prioridad 0.7 / Frecuencia mensual.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>

							<?php if ( class_exists( 'WooCommerce' ) ) : ?>
								<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
									<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
										<input type="checkbox" name="wpat_settings[sitemap_include_products]" value="1" <?php checked( ! isset( $settings['sitemap_include_products'] ) || '1' === (string) $settings['sitemap_include_products'] ); ?> style="margin-top: 2px;" />
										<div>
											<span><?php esc_html_e( 'Incluir Productos WooCommerce', 'wp-agency-toolkit' ); ?></span>
											<p class="description" style="margin: 2px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Prioridad 0.9 / Frecuencia semanal.', 'wp-agency-toolkit' ); ?></p>
										</div>
									</label>
								</div>
							<?php endif; ?>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[sitemap_include_taxonomies]" value="1" <?php checked( ! isset( $settings['sitemap_include_taxonomies'] ) || '1' === (string) $settings['sitemap_include_taxonomies'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Incluir Taxonomías (Categorías y Etiquetas)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 2px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Prioridad 0.5 / Frecuencia semanal.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>

							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px;">
								<label style="font-weight: 600; cursor: pointer; display: flex; align-items: flex-start; gap: 8px;">
									<input type="checkbox" name="wpat_settings[sitemap_include_images]" value="1" <?php checked( ! isset( $settings['sitemap_include_images'] ) || '1' === (string) $settings['sitemap_include_images'] ); ?> style="margin-top: 2px;" />
									<div>
										<span><?php esc_html_e( 'Google Image Sitemap (Imágenes Destacadas)', 'wp-agency-toolkit' ); ?></span>
										<p class="description" style="margin: 2px 0 0 0; font-weight: normal;"><?php esc_html_e( 'Inyecta etiquetas image:loc e image:title para posicionamiento en Google Imágenes.', 'wp-agency-toolkit' ); ?></p>
									</div>
								</label>
							</div>
						</div>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- 3. Exclusiones Manuales de URLs -->
						<div class="wpat-field-group">
							<label for="wpat_sitemap_exclude_urls" style="font-weight: 600; display: block; margin-bottom: 6px;">
								<?php esc_html_e( 'Exclusiones Manuales de URLs (Opcional)', 'wp-agency-toolkit' ); ?>
							</label>
							<textarea name="wpat_settings[sitemap_exclude_urls]" id="wpat_sitemap_exclude_urls" rows="4" class="large-text" placeholder="<?php echo esc_attr( home_url( '/carrito/' ) . "\n" . home_url( '/mi-cuenta/' ) . "\n/gracias/" ); ?>" style="font-family: monospace;"><?php echo esc_textarea( isset( $settings['sitemap_exclude_urls'] ) ? $settings['sitemap_exclude_urls'] : '' ); ?></textarea>
							<p class="description" style="margin-top: 6px;">
								<?php esc_html_e( 'Escribe una URL o ruta por línea que desees omitir del sitemap XML (por ejemplo: /checkout/ o /politica-privacidad/). Las páginas configuradas con directiva "noindex" en el módulo SEO se excluyen automáticamente sin necesidad de listarlas aquí.', 'wp-agency-toolkit' ); ?>
							</p>
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
						<?php $this->render_module_toggle( 'woo-sale-badges', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">
						<div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 25px; align-items: start;">
							<div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; display: block; margin-bottom: 12px;">Selecciona la Forma del Badge / Etiqueta:</label>
									<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px;" id="wpat-badge-shape-options">
										<label class="wpat-shape-card" style="border: 2px solid <?php echo 'soft' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 12px; border-radius: 8px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
											<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="soft" <?php checked( $shape, 'soft' ); ?>>
											<strong style="display: block; margin-top: 4px; font-size: 13px;">Bordes Suaves</strong>
											<span class="description" style="font-size: 11px; display: block; margin-top: 2px;">Redondeado 8px.</span>
										</label>
										<label class="wpat-shape-card" style="border: 2px solid <?php echo 'pill' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 12px; border-radius: 8px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
											<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="pill" <?php checked( $shape, 'pill' ); ?>>
											<strong style="display: block; margin-top: 4px; font-size: 13px;">Píldora (Pill)</strong>
											<span class="description" style="font-size: 11px; display: block; margin-top: 2px;">Cápsula 20px.</span>
										</label>
										<label class="wpat-shape-card" style="border: 2px solid <?php echo 'rect' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 12px; border-radius: 8px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
											<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="rect" <?php checked( $shape, 'rect' ); ?>>
											<strong style="display: block; margin-top: 4px; font-size: 13px;">Rectangular</strong>
											<span class="description" style="font-size: 11px; display: block; margin-top: 2px;">Bordes rectos 0px.</span>
										</label>
										<label class="wpat-shape-card" style="border: 2px solid <?php echo 'circle' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 12px; border-radius: 8px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
											<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="circle" <?php checked( $shape, 'circle' ); ?>>
											<strong style="display: block; margin-top: 4px; font-size: 13px;">Circular</strong>
											<span class="description" style="font-size: 11px; display: block; margin-top: 2px;">Compacto 50x50px.</span>
										</label>
										<label class="wpat-shape-card" style="border: 2px solid <?php echo 'corner-ribbon' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 12px; border-radius: 8px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
											<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="corner-ribbon" <?php checked( $shape, 'corner-ribbon' ); ?>>
											<strong style="display: block; margin-top: 4px; font-size: 13px;">Cinta Diagonal</strong>
											<span class="description" style="font-size: 11px; display: block; margin-top: 2px;">Esquina 45 grados.</span>
										</label>
										<label class="wpat-shape-card" style="border: 2px solid <?php echo 'price-tag' === $shape ? '#2563eb' : '#e2e8f0'; ?>; padding: 12px; border-radius: 8px; cursor: pointer; background: var(--wpat-card-bg, #fff);">
											<input type="radio" name="wpat_settings[woo_sale_badge_shape]" value="price-tag" <?php checked( $shape, 'price-tag' ); ?>>
											<strong style="display: block; margin-top: 4px; font-size: 13px;">Etiqueta Ticket</strong>
											<span class="description" style="font-size: 11px; display: block; margin-top: 2px;">Con muesca de precio.</span>
										</label>
									</div>
								</div>

								<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 20px 0;" />

								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
									<div class="wpat-field-group">
										<label style="font-weight: 600; display: block; margin-bottom: 6px;">Tipo de Texto a Mostrar:</label>
										<select name="wpat_settings[woo_sale_badge_text_type]" id="wpat_badge_text_type" class="regular-text" style="width: 100%;">
											<option value="custom" <?php selected( $text_type, 'custom' ); ?>>Texto Personalizado (Ej: ¡OFERTA!)</option>
											<option value="percentage" <?php selected( $text_type, 'percentage' ); ?>>Porcentaje de Descuento (Ej: -25% / Hasta -30%)</option>
											<option value="amount" <?php selected( $text_type, 'amount' ); ?>>Ahorro en Moneda (Ej: Ahorra 15,00 €)</option>
										</select>
									</div>
									<div class="wpat-field-group" id="wpat-badge-custom-text-wrap" style="<?php echo 'custom' !== $text_type ? 'opacity: 0.5;' : ''; ?>">
										<label style="font-weight: 600; display: block; margin-bottom: 6px;">Texto Personalizado:</label>
										<input type="text" name="wpat_settings[woo_sale_badge_custom_text]" id="wpat_badge_custom_text" value="<?php echo esc_attr( $custom_txt ); ?>" class="regular-text" style="width: 100%;" placeholder="¡OFERTA!">
									</div>
								</div>

								<div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-top: 15px;">
									<div class="wpat-field-group">
										<label style="font-weight: 600; display: block; margin-bottom: 6px;">Color Fondo:</label>
										<input type="text" name="wpat_settings[woo_sale_badge_bg_color]" id="wpat_badge_bg_color" value="<?php echo esc_attr( $bg_color ); ?>" class="wpat-color-picker" data-default-color="#ef4444">
									</div>
									<div class="wpat-field-group">
										<label style="font-weight: 600; display: block; margin-bottom: 6px;">Color Texto:</label>
										<input type="text" name="wpat_settings[woo_sale_badge_txt_color]" id="wpat_badge_txt_color" value="<?php echo esc_attr( $txt_color ); ?>" class="wpat-color-picker" data-default-color="#ffffff">
									</div>
									<div class="wpat-field-group">
										<label style="font-weight: 600; display: block; margin-bottom: 6px;">Fuente (px):</label>
										<input type="number" name="wpat_settings[woo_sale_badge_font_size]" id="wpat_badge_font_size" value="<?php echo esc_attr( $font_size ); ?>" min="8" max="32" class="small-text" style="width: 100%;">
									</div>
									<div class="wpat-field-group">
										<label style="font-weight: 600; display: block; margin-bottom: 6px;">Posición:</label>
										<select name="wpat_settings[woo_sale_badge_position]" id="wpat_badge_position" style="width: 100%;">
											<option value="top-left" <?php selected( $position, 'top-left' ); ?>>Superior Izq</option>
											<option value="top-right" <?php selected( $position, 'top-right' ); ?>>Superior Der</option>
										</select>
									</div>
								</div>
							</div>

							<!-- Live Preview Card -->
							<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; text-align: center;">
								<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; display: block; margin-bottom: 12px;">Vista Previa en Vivo</span>
								
								<div id="wpat-badge-preview-container" style="position: relative; width: 200px; height: 220px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between; padding: 15px;">
									
									<!-- Mock Sale Badge -->
									<span id="wpat-badge-preview" class="onsale wpat-sale-badge badge-<?php echo esc_attr( $shape ); ?> pos-<?php echo esc_attr( $position ); ?>" style="background-color: <?php echo esc_attr( $bg_color ); ?> !important; color: <?php echo esc_attr( $txt_color ); ?> !important; font-size: <?php echo esc_attr( $font_size ); ?>px !important; <?php echo 'top-right' === $position ? 'right: 12px !important; left: auto !important;' : 'left: 12px !important; right: auto !important;'; ?>">
										<?php
										if ( 'percentage' === $text_type ) {
											echo '-25%';
										} elseif ( 'amount' === $text_type ) {
											echo 'Ahorra 15,00 €';
										} else {
											echo esc_html( $custom_txt );
										}
										?>
									</span>

									<!-- Mock Product Image Placeholder -->
									<div style="height: 120px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
										<span class="dashicons dashicons-format-image" style="font-size: 36px; width: 36px; height: 36px;"></span>
									</div>

									<!-- Mock Product Meta -->
									<div style="text-align: left;">
										<div style="height: 10px; width: 70%; background: #e2e8f0; border-radius: 4px; margin-bottom: 6px;"></div>
										<div style="display: flex; gap: 8px; align-items: center;">
											<span style="font-size: 11px; text-decoration: line-through; color: #94a3b8;">60,00 €</span>
											<span style="font-size: 13px; font-weight: 700; color: #0f172a;">45,00 €</span>
										</div>
									</div>
								</div>
								<p class="description" style="font-size: 11px; margin-top: 10px; color: #64748b;">Visualización en directo de cómo lucirá sobre las imágenes de tu catálogo.</p>
							</div>
						</div>
					</div>
				</div>

				<script>
				(function($) {
					$(document).ready(function() {
						function updateBadgePreview() {
							var shape = $('input[name="wpat_settings[woo_sale_badge_shape]"]:checked').val() || 'soft';
							var textType = $('#wpat_badge_text_type').val();
							var customTxt = $('#wpat_badge_custom_text').val() || '¡OFERTA!';
							var bgColor = $('#wpat_badge_bg_color').val() || '#ef4444';
							var txtColor = $('#wpat_badge_txt_color').val() || '#ffffff';
							var fontSize = $('#wpat_badge_font_size').val() || 13;
							var position = $('#wpat_badge_position').val() || 'top-left';

							var $badge = $('#wpat-badge-preview');
							$badge.attr('class', 'onsale wpat-sale-badge badge-' + shape + ' pos-' + position);
							$badge.css({
								'background-color': bgColor,
								'color': txtColor,
								'font-size': fontSize + 'px'
							});

							if (position === 'top-right') {
								$badge.css({ 'right': '12px', 'left': 'auto' });
							} else {
								$badge.css({ 'left': '12px', 'right': 'auto' });
							}

							var displayText = customTxt;
							if (textType === 'percentage') {
								displayText = '-25%';
								$('#wpat-badge-custom-text-wrap').css('opacity', '0.5');
							} else if (textType === 'amount') {
								displayText = 'Ahorra 15,00 €';
								$('#wpat-badge-custom-text-wrap').css('opacity', '0.5');
							} else {
								$('#wpat-badge-custom-text-wrap').css('opacity', '1');
							}
							$badge.text(displayText);

							// Highlight selected shape card
							$('.wpat-shape-card').each(function() {
								var isChecked = $(this).find('input[type="radio"]').is(':checked');
								$(this).css('border-color', isChecked ? '#2563eb' : '#e2e8f0');
							});
						}

						$('input[name="wpat_settings[woo_sale_badge_shape]"]').on('change', updateBadgePreview);
						$('#wpat_badge_text_type, #wpat_badge_position').on('change', updateBadgePreview);
						$('#wpat_badge_custom_text, #wpat_badge_font_size').on('input change', updateBadgePreview);
						$('#wpat_badge_bg_color, #wpat_badge_txt_color').on('input change', function() {
							setTimeout(updateBadgePreview, 50);
						});
					});
				})(jQuery);
				</script>
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

				// Ajustes de Carrito
				$cart_designer_enabled     = ! isset( $settings['woo_cart_designer_enabled'] ) || '1' === $settings['woo_cart_designer_enabled'];
				$cart_layout               = isset( $settings['woo_cart_designer_layout'] ) ? $settings['woo_cart_designer_layout'] : 'wpat-cart-classic';
				$cart_free_shipping_bar    = ! isset( $settings['woo_cart_free_shipping_bar'] ) || '1' === $settings['woo_cart_free_shipping_bar'];
				$cart_free_shipping_min    = isset( $settings['woo_cart_free_shipping_min_amount'] ) ? floatval( $settings['woo_cart_free_shipping_min_amount'] ) : 50;
				$cart_drawer_auto_open     = ! isset( $settings['woo_cart_drawer_auto_open'] ) || '1' === $settings['woo_cart_drawer_auto_open'];
				$cart_show_shipping_calc   = isset( $settings['woo_cart_show_shipping_calculator'] ) && '1' === $settings['woo_cart_show_shipping_calculator'];

				$active_subtab = isset( $_REQUEST['subtab'] ) ? sanitize_key( $_REQUEST['subtab'] ) : ( isset( $_POST['wpat_active_subtab'] ) ? sanitize_key( $_POST['wpat_active_subtab'] ) : 'checkout-designer' );
				if ( ! in_array( $active_subtab, array( 'checkout-designer', 'cart-designer' ), true ) ) {
					$active_subtab = 'checkout-designer';
				}
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Diseñador de Carrito y Checkout</h3>
							<p>Personaliza y optimiza tanto el <strong>Carrito de Compras</strong> como la página de <strong>Checkout</strong> de WooCommerce para maximizar la conversión con plantillas modernas y responsivas.</p>
						</div>
						<?php $this->render_module_toggle( 'woo-checkout-designer', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">
						<div class="wpat-inner-subtabs" style="display: flex; gap: 8px; border-bottom: 2px solid var(--wpat-border, #e2e8f0); margin-bottom: 20px;">
							<input type="hidden" name="wpat_active_subtab" class="wpat-active-subtab-input" value="<?php echo esc_attr( $active_subtab ); ?>">
							<button type="button" class="wpat-subtab-nav-btn <?php echo 'checkout-designer' === $active_subtab ? 'active' : ''; ?>" data-subtab="checkout-designer" style="padding: 10px 18px; font-weight: 700; font-size: 13.5px; border: none; background: transparent; cursor: pointer; border-bottom: 3px solid <?php echo 'checkout-designer' === $active_subtab ? '#2563eb' : 'transparent'; ?>; margin-bottom: -2px; color: <?php echo 'checkout-designer' === $active_subtab ? '#2563eb' : '#64748b'; ?>;">💳 Diseñador de Checkout</button>
							<button type="button" class="wpat-subtab-nav-btn <?php echo 'cart-designer' === $active_subtab ? 'active' : ''; ?>" data-subtab="cart-designer" style="padding: 10px 18px; font-weight: 700; font-size: 13.5px; border: none; background: transparent; cursor: pointer; border-bottom: 3px solid <?php echo 'cart-designer' === $active_subtab ? '#2563eb' : 'transparent'; ?>; margin-bottom: -2px; color: <?php echo 'cart-designer' === $active_subtab ? '#2563eb' : '#64748b'; ?>;">🛒 Diseñador de Carrito</button>
						</div>

						<!-- Subpestaña 1: Checkout -->
						<div id="wpat-subtab-checkout-designer" class="wpat-subtab-content <?php echo 'checkout-designer' === $active_subtab ? 'active' : ''; ?>" style="display: <?php echo 'checkout-designer' === $active_subtab ? 'block' : 'none'; ?>;">
							<div class="wpat-field-group">
								<label style="font-weight: 700; display: block; margin-bottom: 12px;">Selecciona la Plantilla de Checkout:</label>
								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
									<label class="wpat-checkout-layout-card <?php echo 'wpat-classic' === $layout ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'wpat-classic' === $layout ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
										<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-classic" <?php checked( $layout, 'wpat-classic' ); ?>>
										<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Classic Checkout</strong>
										<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Multi-Paso (3 Pasos) con migas de pan y columna de resumen fija (<em>sticky</em>).</span>
									</label>
									<label class="wpat-checkout-layout-card <?php echo 'wpat-express' === $layout ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'wpat-express' === $layout ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
										<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-express" <?php checked( $layout, 'wpat-express' ); ?>>
										<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Express Checkout</strong>
										<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Vista rápida en 2 columnas agrupadas en tarjetas redondeadas limpias.</span>
									</label>
									<label class="wpat-checkout-layout-card <?php echo 'wpat-accordion' === $layout ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'wpat-accordion' === $layout ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
										<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-accordion" <?php checked( $layout, 'wpat-accordion' ); ?>>
										<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Accordion Checkout</strong>
										<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Pasos desplegables secuenciales que avanzan conforme se valida cada paso.</span>
									</label>
									<label class="wpat-checkout-layout-card <?php echo 'wpat-minimalist' === $layout ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'wpat-minimalist' === $layout ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
										<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-minimalist" <?php checked( $layout, 'wpat-minimalist' ); ?>>
										<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Minimalist Checkout</strong>
										<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">1 Columna ultra-limpia enfocado en máxima conversión y sellos de confianza.</span>
									</label>
									<label class="wpat-checkout-layout-card <?php echo 'wpat-shop-style' === $layout ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'wpat-shop-style' === $layout ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
										<input type="radio" name="wpat_settings[woo_checkout_designer_layout]" value="wpat-shop-style" <?php checked( $layout, 'wpat-shop-style' ); ?>>
										<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Shop-Style Checkout</strong>
										<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Diseño en 2 columnas agrupadas (Contacto, Entrega, Envío y Pago a la izquierda) y lateral sticky con productos y cupones.</span>
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

						<!-- Subpestaña 2: Carrito -->
						<div id="wpat-subtab-cart-designer" class="wpat-subtab-content <?php echo 'cart-designer' === $active_subtab ? 'active' : ''; ?>" style="display: <?php echo 'cart-designer' === $active_subtab ? 'block' : 'none'; ?>;">
							<div class="wpat-field-group" style="margin-bottom: 20px;">
								<label style="font-weight: 700; font-size: 14px;">
									<input type="checkbox" name="wpat_settings[woo_cart_designer_enabled]" value="1" <?php checked( $cart_designer_enabled ); ?>>
									Activar el Diseñador Personalizado de Carrito de Compras
								</label>
							</div>

							<div class="wpat-field-group">
								<label style="font-weight: 700; display: block; margin-bottom: 12px;">Selecciona la Plantilla de Carrito:</label>
								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
									<label class="wpat-cart-layout-card <?php echo 'wpat-cart-classic' === $cart_layout ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'wpat-cart-classic' === $cart_layout ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
										<input type="radio" name="wpat_settings[woo_cart_designer_layout]" value="wpat-cart-classic" <?php checked( $cart_layout, 'wpat-cart-classic' ); ?>>
										<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Classic Cart</strong>
										<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Tabla limpia de productos a 2 columnas con tarjetas redondeadas y resumen fija (*sticky*).</span>
									</label>
									<label class="wpat-cart-layout-card <?php echo 'wpat-cart-modern' === $cart_layout ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'wpat-cart-modern' === $cart_layout ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
										<input type="radio" name="wpat_settings[woo_cart_designer_layout]" value="wpat-cart-modern" <?php checked( $cart_layout, 'wpat-cart-modern' ); ?>>
										<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Modern Cards Cart</strong>
										<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Formato en tarjetas individuales con imágenes destacadas y botones táctiles <code>+</code> / <code>-</code> de cantidad.</span>
									</label>
									<label class="wpat-cart-layout-card <?php echo 'wpat-cart-drawer' === $cart_layout ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'wpat-cart-drawer' === $cart_layout ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
										<input type="radio" name="wpat_settings[woo_cart_designer_layout]" value="wpat-cart-drawer" <?php checked( $cart_layout, 'wpat-cart-drawer' ); ?>>
										<strong style="display: block; margin-top: 6px; font-size: 14px;">WPAT Slide-Out Drawer Cart</strong>
										<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Mini-carrito deslizable lateral desplegable automáticamente vía AJAX sin recargar la página.</span>
									</label>
								</div>
							</div>

							<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

							<div class="wpat-field-group">
								<label style="font-weight: 700; display: block; margin-bottom: 12px;">Barra de Progreso para Envío Gratuito:</label>
								<label style="font-weight: 600; display: block; margin-bottom: 10px;">
									<input type="checkbox" name="wpat_settings[woo_cart_free_shipping_bar]" value="1" <?php checked( $cart_free_shipping_bar ); ?>>
									Mostrar barra de incentivo animada (Ej: <em>"¡Te faltan X € para conseguir ENVÍO GRATIS!"</em>)
								</label>
								<div style="max-width: 300px; margin-top: 8px;">
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 4px;">Importe mínimo para Envío Gratis (€):</label>
									<input type="number" step="0.01" name="wpat_settings[woo_cart_free_shipping_min_amount]" value="<?php echo esc_attr( $cart_free_shipping_min ); ?>" class="regular-text" style="width: 100%;">
								</div>
							</div>

							<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

							<div class="wpat-field-group">
								<label style="font-weight: 600;">
									<input type="checkbox" name="wpat_settings[woo_cart_drawer_auto_open]" value="1" <?php checked( $cart_drawer_auto_open ); ?>>
									Abrir automáticamente el carrito deslizable (Drawer) al añadir un producto desde cualquier página de la tienda
								</label>
							</div>

							<div class="wpat-field-group" style="margin-top: 12px;">
								<label style="font-weight: 600;">
									<input type="checkbox" name="wpat_settings[woo_cart_show_shipping_calculator]" value="1" <?php checked( $cart_show_shipping_calc ); ?>>
									Mostrar el botón "Cambiar dirección" (Formulario de Ubicación) en la tarjeta de resumen del Carrito
								</label>
								<p class="description" style="margin-left: 24px; margin-top: 3px; font-size: 12px;">Los gastos y tarifas de envío siempre se muestran en el resumen del carrito. Activa esta casilla si deseas mostrar además el botón "Cambiar dirección" para permitir al cliente modificar su ubicación o CP manualmente antes del Checkout.</p>
							</div>

							<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

							<div class="wpat-field-group">
								<label style="font-weight: 700; display: block; margin-bottom: 12px;">Personalización de Colores de Botón de Finalizar Compra:</label>
								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
									<div class="wpat-field-group">
										<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Fondo de Botón Principal:</label>
										<input type="text" name="wpat_settings[woo_checkout_btn_bg_color]" value="<?php echo esc_attr( $btn_bg_color ); ?>" class="wpat-color-picker" data-default-color="#2563eb">
									</div>
									<div class="wpat-field-group">
										<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Fondo Al Pasar el Ratón (Hover):</label>
										<input type="text" name="wpat_settings[woo_checkout_btn_hover_bg_color]" value="<?php echo esc_attr( $btn_hover_bg_color ); ?>" class="wpat-color-picker" data-default-color="#1d4ed8">
									</div>
									<div class="wpat-field-group">
										<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Texto de Botón Principal:</label>
										<input type="text" name="wpat_settings[woo_checkout_btn_txt_color]" value="<?php echo esc_attr( $btn_txt_color ); ?>" class="wpat-color-picker" data-default-color="#ffffff">
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php
				break;
			case 'woo-email-designer':
				require_once WPAT_PATH . 'includes/modules/class-wpat-woo-email-designer.php';
				$email_style     = isset( $settings['woo_email_template_style'] ) ? $settings['woo_email_template_style'] : 'modern';
				$email_type_sel  = isset( $settings['woo_email_selected_type'] ) ? $settings['woo_email_selected_type'] : 'customer_processing_order';
				$email_logo      = isset( $settings['woo_email_logo_url'] ) ? $settings['woo_email_logo_url'] : '';
				$email_logo_w    = isset( $settings['woo_email_logo_width'] ) ? $settings['woo_email_logo_width'] : 150;
				$email_primary   = isset( $settings['woo_email_primary_color'] ) ? $settings['woo_email_primary_color'] : '#2563eb';
				$email_body_bg   = isset( $settings['woo_email_body_bg'] ) ? $settings['woo_email_body_bg'] : '#f8fafc';
				$email_card_bg   = isset( $settings['woo_email_card_bg'] ) ? $settings['woo_email_card_bg'] : '#ffffff';
				$email_text_col  = isset( $settings['woo_email_text_color'] ) ? $settings['woo_email_text_color'] : '#1e293b';
				$email_welcome   = isset( $settings['woo_email_welcome_msg'] ) ? $settings['woo_email_welcome_msg'] : '';
				$email_intro     = isset( $settings['woo_email_body_intro'] ) ? $settings['woo_email_body_intro'] : '';
				$email_promo     = isset( $settings['woo_email_promo_text'] ) ? $settings['woo_email_promo_text'] : '';
				$email_footer    = isset( $settings['woo_email_footer_text'] ) ? $settings['woo_email_footer_text'] : get_option( 'woocommerce_email_footer_text' );
				$social_fb       = isset( $settings['woo_email_social_fb'] ) ? $settings['woo_email_social_fb'] : '';
				$social_ig       = isset( $settings['woo_email_social_ig'] ) ? $settings['woo_email_social_ig'] : '';
				$social_tw       = isset( $settings['woo_email_social_tw'] ) ? $settings['woo_email_social_tw'] : '';
				$social_web      = isset( $settings['woo_email_social_web'] ) ? $settings['woo_email_social_web'] : '';
				$test_recipient  = isset( $settings['woo_email_test_recipient'] ) && ! empty( $settings['woo_email_test_recipient'] ) ? $settings['woo_email_test_recipient'] : get_option( 'admin_email' );

				$email_types = array(
					'customer_processing_order' => '📦 Procesando pedido (Cliente)',
					'customer_completed_order'  => '✅ Pedido completado (Cliente)',
					'new_order'                 => '🔔 Nuevo pedido (Administrador)',
					'customer_invoice'          => '📄 Factura / Detalles de pedido (Cliente)',
					'customer_on_hold'          => '⏳ Pedido en espera (Cliente)',
					'customer_reset_password'   => '🔑 Restablecer contraseña (Cliente)',
					'customer_new_account'      => '👤 Nueva cuenta creada (Cliente)',
				);

				$email_msgs_saved = isset( $settings['woo_email_messages'] ) && is_array( $settings['woo_email_messages'] ) ? $settings['woo_email_messages'] : array();

				// Valores actuales para la plantilla seleccionada
				$cur_welcome = WPAT_Woo_Email_Designer::get_message_for_type( $email_type_sel, 'welcome', $settings );
				$cur_intro   = WPAT_Woo_Email_Designer::get_message_for_type( $email_type_sel, 'intro', $settings );
				$cur_promo   = WPAT_Woo_Email_Designer::get_message_for_type( $email_type_sel, 'promo', $settings );
				$cur_footer  = WPAT_Woo_Email_Designer::get_message_for_type( $email_type_sel, 'footer', $settings );
				$email_defaults_map = WPAT_Woo_Email_Designer::get_default_messages_map();
				$cur_defaults       = isset( $email_defaults_map[ $email_type_sel ] ) ? $email_defaults_map[ $email_type_sel ] : array();
				?>
				<script id="wpat_email_defaults_data" type="application/json">
				<?php echo wp_json_encode( $email_defaults_map ); ?>
				</script>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Diseñador de Plantillas de Email para WooCommerce</h3>
							<p>Personaliza visualmente todas las notificaciones por correo electrónico de WooCommerce con un diseño moderno, logotipos y colores de marca.</p>
						</div>
						<?php $this->render_module_toggle( 'woo-email-designer', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">

						<div class="wpat-field-group">
							<label style="font-weight: 700; display: block; margin-bottom: 12px;">Selecciona el Estilo de Plantilla:</label>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
								<label class="wpat-email-layout-card <?php echo 'classic' === $email_style ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'classic' === $email_style ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
									<input type="radio" name="wpat_settings[woo_email_template_style]" value="classic" <?php checked( $email_style, 'classic' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Clásica Profesional</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Cabecera en color corporativo sólido, tabla limpia con bordes suaves y pie de página en tono oscuro.</span>
								</label>
								<label class="wpat-email-layout-card <?php echo 'modern' === $email_style ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'modern' === $email_style ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
									<input type="radio" name="wpat_settings[woo_email_template_style]" value="modern" <?php checked( $email_style, 'modern' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Moderna High-Tech</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Estructura flotante en tarjeta con esquinas redondeadas (12px), sombra sutil y tipografía refinada.</span>
								</label>
								<label class="wpat-email-layout-card <?php echo 'minimalist' === $email_style ? 'active' : ''; ?>" style="border: 2px solid <?php echo 'minimalist' === $email_style ? '#2563eb' : 'var(--wpat-border, #e2e8f0)'; ?>; padding: 15px; border-radius: 10px; cursor: pointer; background: var(--wpat-bg-card, #ffffff); transition: all 0.2s ease;">
									<input type="radio" name="wpat_settings[woo_email_template_style]" value="minimalist" <?php checked( $email_style, 'minimalist' ); ?>>
									<strong style="display: block; margin-top: 6px; font-size: 14px;">Minimalista / Elegante</strong>
									<span class="description" style="font-size: 12px; display: block; margin-top: 4px;">Diseño blanco ultra-limpio con bordes sutiles, logotipo centrado y líneas de división finas.</span>
								</label>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

						<!-- Personalización de Logotipo -->
						<div class="wpat-field-group">
							<label style="font-weight: 700; display: block; margin-bottom: 12px;">Identidad Visual y Logotipo:</label>
							<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">URL del Logotipo:</label>
									<div style="display: flex; gap: 8px;">
										<input type="text" id="woo_email_logo_url_input" name="wpat_settings[woo_email_logo_url]" value="<?php echo esc_attr( $email_logo ); ?>" class="regular-text" style="flex: 1;" placeholder="https://tudominio.com/logo.png">
										<button type="button" class="button wpat-upload-image-btn" data-target="#woo_email_logo_url_input">📷 Biblioteca / Subir Logo</button>
									</div>
								</div>
								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Ancho Máximo del Logo (px):</label>
									<input type="number" name="wpat_settings[woo_email_logo_width]" value="<?php echo esc_attr( $email_logo_w ); ?>" min="30" max="500" class="small-text" style="width: 100%;">
								</div>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

						<!-- Personalización de Colores -->
						<div class="wpat-field-group">
							<label style="font-weight: 700; display: block; margin-bottom: 12px;">Paleta de Colores de los Correos:</label>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color Primario / Acento:</label>
									<input type="text" name="wpat_settings[woo_email_primary_color]" value="<?php echo esc_attr( $email_primary ); ?>" class="wpat-color-picker" data-default-color="#2563eb">
								</div>
								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Fondo General del Email:</label>
									<input type="text" name="wpat_settings[woo_email_body_bg]" value="<?php echo esc_attr( $email_body_bg ); ?>" class="wpat-color-picker" data-default-color="#f8fafc">
								</div>
								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Fondo de la Tarjeta Interior:</label>
									<input type="text" name="wpat_settings[woo_email_card_bg]" value="<?php echo esc_attr( $email_card_bg ); ?>" class="wpat-color-picker" data-default-color="#ffffff">
								</div>
								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Color del Texto:</label>
									<input type="text" name="wpat_settings[woo_email_text_color]" value="<?php echo esc_attr( $email_text_col ); ?>" class="wpat-color-picker" data-default-color="#1e293b">
								</div>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

						<!-- Selector de Tipo de Email de WooCommerce (Reubicado encima de Mensajes) -->
						<div class="wpat-field-group" style="background: #f8fafc; padding: 16px 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 22px;">
							<label style="font-weight: 700; display: block; margin-bottom: 6px; font-size: 14px; color: #0f172a;">Plantilla de Email a Personalizar / Previsualizar:</label>
							<select id="wpat_email_type_selector" name="wpat_settings[woo_email_selected_type]" class="regular-text" style="width: 100%; max-width: 480px; font-weight: 600; padding: 6px 12px; border-radius: 6px;">
								<?php foreach ( $email_types as $t_key => $t_label ) : ?>
									<option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $email_type_sel, $t_key ); ?>><?php echo esc_html( $t_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Almacén de valores ocultos per-template para envío POST completo -->
						<div id="wpat_email_messages_store_container" style="display:none;">
							<?php foreach ( $email_types as $t_key => $t_label ) :
								$w_val = WPAT_Woo_Email_Designer::get_message_for_type( $t_key, 'welcome', $settings );
								$i_val = WPAT_Woo_Email_Designer::get_message_for_type( $t_key, 'intro', $settings );
								$p_val = WPAT_Woo_Email_Designer::get_message_for_type( $t_key, 'promo', $settings );
								$f_val = WPAT_Woo_Email_Designer::get_message_for_type( $t_key, 'footer', $settings );
								?>
								<input type="hidden" name="wpat_settings[woo_email_messages][<?php echo esc_attr( $t_key ); ?>][welcome]" id="wpat_store_welcome_<?php echo esc_attr( $t_key ); ?>" class="wpat-msg-store" data-type="<?php echo esc_attr( $t_key ); ?>" data-field="welcome" value="<?php echo esc_attr( $w_val ); ?>">
								<input type="hidden" name="wpat_settings[woo_email_messages][<?php echo esc_attr( $t_key ); ?>][intro]" id="wpat_store_intro_<?php echo esc_attr( $t_key ); ?>" class="wpat-msg-store" data-type="<?php echo esc_attr( $t_key ); ?>" data-field="intro" value="<?php echo esc_attr( $i_val ); ?>">
								<input type="hidden" name="wpat_settings[woo_email_messages][<?php echo esc_attr( $t_key ); ?>][promo]" id="wpat_store_promo_<?php echo esc_attr( $t_key ); ?>" class="wpat-msg-store" data-type="<?php echo esc_attr( $t_key ); ?>" data-field="promo" value="<?php echo esc_attr( $p_val ); ?>">
								<input type="hidden" name="wpat_settings[woo_email_messages][<?php echo esc_attr( $t_key ); ?>][footer]" id="wpat_store_footer_<?php echo esc_attr( $t_key ); ?>" class="wpat-msg-store" data-type="<?php echo esc_attr( $t_key ); ?>" data-field="footer" value="<?php echo esc_attr( $f_val ); ?>">
							<?php endforeach; ?>
						</div>

						<!-- Contenido Personalizado y Etiquetas Dinámicas -->
						<div class="wpat-field-group">
							<label style="font-weight: 700; display: block; margin-bottom: 6px;">Mensajes de Contenido Dinámico:</label>
							<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 18px; margin-bottom: 18px;">
								<span style="font-size: 13px; color: #1e40af; font-weight: 700; display: block; margin-bottom: 8px;">💡 Haz clic sobre cualquier etiqueta para insertarla automáticamente en tu campo de texto:</span>
								<div style="display: flex; flex-wrap: wrap; gap: 8px;" class="wpat-tag-pills-container">
									<button type="button" class="wpat-tag-pill-btn" data-tag="{customer_name}" style="background: #ffffff; color: #1d4ed8; padding: 5px 12px; border-radius: 6px; border: 1px solid #93c5fd; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: all 0.15s ease;">+ {customer_name}</button>
									<button type="button" class="wpat-tag-pill-btn" data-tag="{order_number}" style="background: #ffffff; color: #1d4ed8; padding: 5px 12px; border-radius: 6px; border: 1px solid #93c5fd; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: all 0.15s ease;">+ {order_number}</button>
									<button type="button" class="wpat-tag-pill-btn" data-tag="{order_date}" style="background: #ffffff; color: #1d4ed8; padding: 5px 12px; border-radius: 6px; border: 1px solid #93c5fd; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: all 0.15s ease;">+ {order_date}</button>
									<button type="button" class="wpat-tag-pill-btn" data-tag="{order_total}" style="background: #ffffff; color: #1d4ed8; padding: 5px 12px; border-radius: 6px; border: 1px solid #93c5fd; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: all 0.15s ease;">+ {order_total}</button>
									<button type="button" class="wpat-tag-pill-btn" data-tag="{site_title}" style="background: #ffffff; color: #1d4ed8; padding: 5px 12px; border-radius: 6px; border: 1px solid #93c5fd; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: all 0.15s ease;">+ {site_title}</button>
									<button type="button" class="wpat-tag-pill-btn" data-tag="{tracking_code}" style="background: #ffffff; color: #1d4ed8; padding: 5px 12px; border-radius: 6px; border: 1px solid #93c5fd; font-weight: 700; font-size: 12.5px; cursor: pointer; transition: all 0.15s ease;">+ {tracking_code}</button>
								</div>
							</div>

							<div style="display: flex; flex-direction: column; gap: 16px;">
								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 4px;">Mensaje de Bienvenida en Cabecera (Opcional):</label>
									<input type="text" id="wpat_email_field_welcome" name="wpat_settings[woo_email_welcome_msg]" value="<?php echo esc_attr( $cur_welcome ); ?>" class="regular-text wpat-email-input-field" style="width: 100%;" placeholder="<?php echo esc_attr( isset( $cur_defaults['welcome'] ) ? $cur_defaults['welcome'] : 'Ej: ¡Gracias por tu pedido en {site_title}!' ); ?>">
								</div>

								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 4px;">Mensaje / Introducción Principal del Cuerpo (Opcional):</label>
									<textarea id="wpat_email_field_intro" name="wpat_settings[woo_email_body_intro]" rows="2" class="large-text wpat-email-input-field" style="width: 100%;" placeholder="<?php echo esc_attr( isset( $cur_defaults['intro'] ) ? $cur_defaults['intro'] : 'Ej: Hola {customer_name}...' ); ?>"><?php echo esc_textarea( $cur_intro ); ?></textarea>
								</div>

								<div>
									<label style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 4px;">Bloque Promocional o Banner Destacado (Opcional):</label>
									<input type="text" id="wpat_email_field_promo" name="wpat_settings[woo_email_promo_text]" value="<?php echo esc_attr( $cur_promo ); ?>" class="regular-text wpat-email-input-field" style="width: 100%;" placeholder="<?php echo esc_attr( isset( $cur_defaults['promo'] ) ? $cur_defaults['promo'] : 'Ej: 🎁 ¡Usa el cupón GRACIAS10...' ); ?>">
								</div>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

						<!-- Pie de Página y Redes Sociales -->
						<div class="wpat-field-group">
							<label style="font-weight: 700; display: block; margin-bottom: 6px;">Texto de Pie de Página (Copyright / Aviso Legal):</label>
							<textarea id="wpat_email_field_footer" name="wpat_settings[woo_email_footer_text]" rows="2" class="large-text wpat-email-input-field" style="width: 100%;" placeholder="<?php echo esc_attr( isset( $cur_defaults['footer'] ) ? $cur_defaults['footer'] : '{site_title}' ); ?>"><?php echo esc_textarea( $cur_footer ); ?></textarea>
						</div>

						<div class="wpat-field-group" style="margin-top: 15px;">
							<label style="font-weight: 700; display: block; margin-bottom: 12px;">Enlaces a Redes Sociales (Opcional):</label>
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
								<div>
									<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Facebook:</label>
									<input type="text" name="wpat_settings[woo_email_social_fb]" value="<?php echo esc_attr( $social_fb ); ?>" class="regular-text" style="width: 100%;" placeholder="https://facebook.com/tupagina">
								</div>
								<div>
									<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Instagram:</label>
									<input type="text" name="wpat_settings[woo_email_social_ig]" value="<?php echo esc_attr( $social_ig ); ?>" class="regular-text" style="width: 100%;" placeholder="https://instagram.com/tuusuario">
								</div>
								<div>
									<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">X (Twitter):</label>
									<input type="text" name="wpat_settings[woo_email_social_tw]" value="<?php echo esc_attr( $social_tw ); ?>" class="regular-text" style="width: 100%;" placeholder="https://x.com/tuusuario">
								</div>
								<div>
									<label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Sitio Web:</label>
									<input type="text" name="wpat_settings[woo_email_social_web]" value="<?php echo esc_attr( $social_web ); ?>" class="regular-text" style="width: 100%;" placeholder="https://tudominio.com">
								</div>
							</div>
						</div>

						<hr style="border:none; border-top: 1px dashed var(--wpat-border); margin: 25px 0;" />

						<!-- Vista Previa en Vivo & Envío de Correo de Prueba -->
						<div class="wpat-field-group" style="background: #f8fafc; padding: 18px 22px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 14px;">
							<div>
								<strong style="font-size: 14.5px; color: #0f172a; display: block;">Comprobación de Diseño y Envíos de Prueba</strong>
								<span style="font-size: 12.5px; color: #64748b;">Visualiza en tiempo real tu plantilla o envía un correo electrónico de demostración a la dirección seleccionada.</span>
							</div>

							<div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
								<div style="flex: 1; min-width: 250px;">
									<input type="email" id="wpat_test_email_recipient" name="wpat_settings[woo_email_test_recipient]" value="<?php echo esc_attr( $test_recipient ); ?>" class="regular-text" style="width: 100%; padding: 7px 12px;" placeholder="correo@ejemplo.com">
								</div>
								<button type="button" class="button button-secondary" id="wpat_email_live_preview_btn" style="height: 38px; padding: 0 18px; font-weight: 700; background: #ffffff; border-color: #cbd5e1; color: #1e293b;">👁️ Vista Previa en Vivo</button>
								<button type="button" class="button button-primary" id="wpat_send_test_email_btn" style="height: 38px; padding: 0 20px; font-weight: 700;">📧 Enviar Email de Prueba</button>
							</div>
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
									<h3>Integraciones & Inyección de Scripts</h3>
									<p>Gestiona e inyecta códigos de herramientas externas, analítica, píxeles de conversión, metaetiquetas de verificación y scripts personalizados (Header/Body/Footer) de forma ultra-ligera, limpia y con cero bloat.</p>
								</div>
								<?php $this->render_module_toggle( 'integrations', $settings, true ); ?>
							</div>
							<div class="wpat-module-body" style="display: block; padding: 20px;">
								<!-- Opción de Exclusión de Administradores -->
								<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
									<label style="font-weight: 600; display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-size: 14px;">
										<input type="checkbox" name="wpat_settings[integrations_exclude_admins]" value="1" <?php checked( isset( $settings['integrations_exclude_admins'] ) && '1' === (string) $settings['integrations_exclude_admins'] ); ?> />
										Excluir a los Administradores del Rastreo / Seguimiento
									</label>
									<p class="description" style="margin: 5px 0 0 25px; color: #64748b;">
										Recomendado para agencias: Oculta e inhabilita automáticamente Google Analytics, Tag Manager, Píxeles de conversión y Clarity cuando navegue un usuario administrador para no distorsionar las métricas de tráfico y conversión del cliente.
									</p>
								</div>

								<!-- SECCIÓN 1: ANALÍTICA Y PÍXELES -->
								<h4 style="margin: 0 0 15px 0; font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-chart-bar" style="color: var(--wpat-primary);"></span> 1. Herramientas de Analítica & Píxeles de Conversión
								</h4>

								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; margin-bottom: 25px;">
									<!-- Google Analytics GA4 -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Google Analytics (GA4)</strong>
											<?php if ( ! empty( $settings['google_analytics_id'] ) && preg_match( '/^G-[A-Z0-9]+$/i', trim( $settings['google_analytics_id'] ) ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Conectado</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[google_analytics_id]" value="<?php echo esc_attr( isset( $settings['google_analytics_id'] ) ? $settings['google_analytics_id'] : '' ); ?>" class="large-text" placeholder="G-XXXXXXXXXX" style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">ID de flujo de datos GA4. <a href="https://analytics.google.com/analytics/web/#/admin" target="_blank" rel="noopener noreferrer">Obtener en Google Analytics &rarr;</a></p>
									</div>

									<!-- Google Tag Manager -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Google Tag Manager (GTM)</strong>
											<?php if ( ! empty( $settings['gtm_container_id'] ) && preg_match( '/^GTM-[A-Z0-9]+$/i', trim( $settings['gtm_container_id'] ) ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Conectado</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[gtm_container_id]" value="<?php echo esc_attr( isset( $settings['gtm_container_id'] ) ? $settings['gtm_container_id'] : '' ); ?>" class="large-text" placeholder="GTM-XXXXXXX" style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">Inyecta script en <code>&lt;head&gt;</code> y noscript en <code>&lt;body&gt;</code>. <a href="https://tagmanager.google.com/" target="_blank" rel="noopener noreferrer">Obtener en GTM &rarr;</a></p>
									</div>

									<!-- Meta / Facebook Pixel -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Meta / Facebook Pixel</strong>
											<?php if ( ! empty( $settings['facebook_pixel_id'] ) && preg_match( '/^[0-9]+$/', trim( $settings['facebook_pixel_id'] ) ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Conectado</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[facebook_pixel_id]" value="<?php echo esc_attr( isset( $settings['facebook_pixel_id'] ) ? $settings['facebook_pixel_id'] : '' ); ?>" class="large-text" placeholder="Ej: 123456789012345" style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">ID numérico de Pixel. <a href="https://eventsmanager.facebook.com/" target="_blank" rel="noopener noreferrer">Meta Events Manager &rarr;</a></p>
									</div>

									<!-- Microsoft Clarity -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Microsoft Clarity (Mapas de Calor)</strong>
											<?php if ( ! empty( $settings['clarity_project_id'] ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Conectado</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[clarity_project_id]" value="<?php echo esc_attr( isset( $settings['clarity_project_id'] ) ? $settings['clarity_project_id'] : '' ); ?>" class="large-text" placeholder="Ej: abc123def4" style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">ID de Proyecto Clarity (100% gratuito). <a href="https://clarity.microsoft.com/" target="_blank" rel="noopener noreferrer">Ir a Clarity &rarr;</a></p>
									</div>

									<!-- TikTok Pixel -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">TikTok Pixel</strong>
											<?php if ( ! empty( $settings['tiktok_pixel_id'] ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Conectado</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[tiktok_pixel_id]" value="<?php echo esc_attr( isset( $settings['tiktok_pixel_id'] ) ? $settings['tiktok_pixel_id'] : '' ); ?>" class="large-text" placeholder="Ej: C1234567890ABCDEF" style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">ID de Pixel TikTok Ads.</p>
									</div>

									<!-- Pinterest Tag -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Pinterest Tag</strong>
											<?php if ( ! empty( $settings['pinterest_tag_id'] ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Conectado</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[pinterest_tag_id]" value="<?php echo esc_attr( isset( $settings['pinterest_tag_id'] ) ? $settings['pinterest_tag_id'] : '' ); ?>" class="large-text" placeholder="Ej: 2612345678901" style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">ID de Tag de Pinterest.</p>
									</div>
								</div>

								<!-- SECCIÓN 2: VERIFICACIONES DE PROPIEDAD -->
								<h4 style="margin: 0 0 15px 0; font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-yes-alt" style="color: var(--wpat-primary);"></span> 2. Verificaciones de Propiedad & Motores de Búsqueda
								</h4>

								<?php
								// Comprobar archivo de verificación Google en el directorio raíz (ABSPATH)
								$google_files      = glob( ABSPATH . 'google*.html' );
								$google_file_found = false;
								$google_file_name  = '';
								if ( ! empty( $google_files ) ) {
									foreach ( $google_files as $file ) {
										$filename = basename( $file );
										if ( preg_match( '/^google[a-f0-9]+\.html$/i', $filename ) ) {
											$google_file_found = true;
											$google_file_name  = $filename;
											break;
										}
									}
								}
								$sc_connected = ! empty( $settings['google_search_console_code'] ) || $google_file_found;
								?>

								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; margin-bottom: 25px;">
									<!-- Google Search Console -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Google Search Console</strong>
											<?php if ( $sc_connected ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">
													<?php echo $google_file_found ? 'Conectado (Archivo: ' . esc_html( $google_file_name ) . ')' : 'Conectado (Meta)'; ?>
												</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[google_search_console_code]" value="<?php echo esc_attr( isset( $settings['google_search_console_code'] ) ? $settings['google_search_console_code'] : '' ); ?>" class="large-text" placeholder='Ej: xyz123... o &lt;meta name="google-site-verification"...' style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">Pega el código o la etiqueta meta completa. <a href="https://search.google.com/search-console/welcome" target="_blank" rel="noopener noreferrer">Search Console &rarr;</a></p>
									</div>

									<!-- Bing Webmaster -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Bing / Microsoft Webmaster</strong>
											<?php if ( ! empty( $settings['bing_verification_code'] ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Conectado</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[bing_verification_code]" value="<?php echo esc_attr( isset( $settings['bing_verification_code'] ) ? $settings['bing_verification_code'] : '' ); ?>" class="large-text" placeholder='Ej: A1B2C3D4... o &lt;meta name="msvalidate.01"...' style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">Código de autenticación <code>msvalidate.01</code> para Bing Webmaster Tools.</p>
									</div>

									<!-- Pinterest Verification -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Pinterest Domain Verification</strong>
											<?php if ( ! empty( $settings['pinterest_verification_code'] ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Conectado</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<input type="text" name="wpat_settings[pinterest_verification_code]" value="<?php echo esc_attr( isset( $settings['pinterest_verification_code'] ) ? $settings['pinterest_verification_code'] : '' ); ?>" class="large-text" placeholder='Ej: 1a2b3c... o &lt;meta name="p:domain_verify"...' style="width:100%; margin-bottom: 6px;" />
										<p class="description" style="font-size: 11px; margin: 0;">Código <code>p:domain_verify</code> para reclamar tu dominio en Pinterest.</p>
									</div>
								</div>

								<!-- SECCIÓN 3: INYECCIÓN DE SCRIPTS PERSONALIZADOS (HEADER, BODY, FOOTER) -->
								<h4 style="margin: 0 0 15px 0; font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-editor-code" style="color: var(--wpat-primary);"></span> 3. Inyección de Scripts Personalizados (Zero-Bloat Header & Footer)
								</h4>

								<div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;">
									<!-- Scripts en Cabecera (<head>) -->
									<div class="wpat-field-group" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;">
										<label for="wpat_header_custom_scripts" style="font-weight: 600; display: block; margin-bottom: 5px; color: #1e293b;">
											Scripts en Cabecera (Inyectados en <code>&lt;head&gt;</code>)
										</label>
										<p class="description" style="margin-bottom: 8px;">Ideal para metaetiquetas adicionales, estilos CSS personalizados con <code>&lt;style&gt;</code>, o scripts de seguimiento que deben cargar al inicio.</p>
										<textarea name="wpat_settings[header_custom_scripts]" id="wpat_header_custom_scripts" rows="4" class="large-text" placeholder="<!-- Códigos en <head> -->&#10;<script>...</script>&#10;<style>...</style>" style="font-family: monospace; font-size: 12px; width: 100%;"><?php echo esc_textarea( isset( $settings['header_custom_scripts'] ) ? $settings['header_custom_scripts'] : '' ); ?></textarea>
									</div>

									<!-- Scripts en Apertura de Body (<body>) -->
									<div class="wpat-field-group" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;">
										<label for="wpat_body_custom_scripts" style="font-weight: 600; display: block; margin-bottom: 5px; color: #1e293b;">
											Scripts en Apertura de Body (Inyectados justo tras abrir <code>&lt;body&gt;</code>)
										</label>
										<p class="description" style="margin-bottom: 8px;">Inyectado mediante el gancho nativo <code>wp_body_open</code>. Perfecto para etiquetas <code>&lt;noscript&gt;</code>, contenedores o avisos flotantes.</p>
										<textarea name="wpat_settings[body_custom_scripts]" id="wpat_body_custom_scripts" rows="3" class="large-text" placeholder="<!-- Códigos tras abrir <body> -->&#10;<noscript>...</noscript>" style="font-family: monospace; font-size: 12px; width: 100%;"><?php echo esc_textarea( isset( $settings['body_custom_scripts'] ) ? $settings['body_custom_scripts'] : '' ); ?></textarea>
									</div>

									<!-- Scripts en Pie de Página (</body>) -->
									<div class="wpat-field-group" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px;">
										<label for="wpat_footer_custom_scripts" style="font-weight: 600; display: block; margin-bottom: 5px; color: #1e293b;">
											Scripts en Pie de Página (Inyectados antes de cerrar <code>&lt;/body&gt;</code>)
										</label>
										<p class="description" style="margin-bottom: 8px;">Inyectado mediante <code>wp_footer</code>. Recomendado para scripts de chat, eventos de conversión de ecommerce o JavaScript de optimización diferida.</p>
										<textarea name="wpat_settings[footer_custom_scripts]" id="wpat_footer_custom_scripts" rows="4" class="large-text" placeholder="<!-- Códigos en </body> -->&#10;<script>...</script>" style="font-family: monospace; font-size: 12px; width: 100%;"><?php echo esc_textarea( isset( $settings['footer_custom_scripts'] ) ? $settings['footer_custom_scripts'] : '' ); ?></textarea>
									</div>
								</div>

								<!-- SECCIÓN 4: ALMACENAMIENTO EN LA NUBE (CLOUD STORAGE) -->
								<h4 style="margin: 0 0 15px 0; font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-cloud" style="color: var(--wpat-primary);"></span> 4. Sincronización con Almacenamiento en la Nube (Google Drive, Dropbox, OneDrive)
								</h4>
								<p class="description" style="margin-bottom: 15px;">Sincroniza y almacena copias de seguridad de los archivos y documentos cargados por los clientes en los campos extra de compra directamente en tu servicio de nube.</p>

								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 15px; margin-bottom: 25px;">
									<!-- Google Drive -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Google Drive Storage</strong>
											<?php if ( ! empty( $settings['google_drive_token'] ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Listo</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 3px;">Token OAuth2 Bearer</label>
										<input type="text" name="wpat_settings[google_drive_token]" value="<?php echo esc_attr( isset( $settings['google_drive_token'] ) ? $settings['google_drive_token'] : '' ); ?>" class="large-text" placeholder="ya29.a0..." style="width:100%; margin-bottom: 8px;" />
										<label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 3px;">ID Carpeta Destino (Opcional)</label>
										<input type="text" name="wpat_settings[google_drive_folder]" value="<?php echo esc_attr( isset( $settings['google_drive_folder'] ) ? $settings['google_drive_folder'] : '' ); ?>" class="large-text" placeholder="Ej: 1A2b3C4d5E6f7G8h9I0J" style="width:100%;" />
									</div>

									<!-- Dropbox -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Dropbox Storage</strong>
											<?php if ( ! empty( $settings['dropbox_token'] ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Listo</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 3px;">Token de Acceso Personal</label>
										<input type="text" name="wpat_settings[dropbox_token]" value="<?php echo esc_attr( isset( $settings['dropbox_token'] ) ? $settings['dropbox_token'] : '' ); ?>" class="large-text" placeholder="sl.B... / Generated Access Token" style="width:100%; margin-bottom: 8px;" />
										<p class="description" style="font-size: 11px; margin: 0;">Generable en Dropbox Developer Console.</p>
									</div>

									<!-- Microsoft OneDrive -->
									<div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #fff;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<strong style="font-size: 13px;">Microsoft OneDrive Storage</strong>
											<?php if ( ! empty( $settings['onedrive_token'] ) ) : ?>
												<span style="background: #e6f4ea; color: #137333; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;">Listo</span>
											<?php else : ?>
												<span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 10px; font-size: 11px;">Sin configurar</span>
											<?php endif; ?>
										</div>
										<label style="font-size: 11px; font-weight: 600; color: #475569; display: block; margin-bottom: 3px;">Token Microsoft Graph API</label>
										<input type="text" name="wpat_settings[onedrive_token]" value="<?php echo esc_attr( isset( $settings['onedrive_token'] ) ? $settings['onedrive_token'] : '' ); ?>" class="large-text" placeholder="EwB... / OAuth Access Token" style="width:100%; margin-bottom: 8px;" />
										<p class="description" style="font-size: 11px; margin: 0;">Token de autenticación Microsoft Graph.</p>
									</div>
								</div>

								<!-- SECCIÓN 5: ACCESO DIRECTO AUDITORÍA DE RENDIMIENTO -->
								<div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
									<div>
										<strong style="color: #0369a1; font-size: 13px; display: block; margin-bottom: 2px;">Google PageSpeed Insights</strong>
										<span style="color: #0c4a6e; font-size: 12px;">Audita el rendimiento, Core Web Vitals y velocidad real de la web en Google sin salir del navegador.</span>
									</div>
									<a href="<?php echo esc_url( 'https://pagespeed.web.dev/analysis?url=' . urlencode( home_url( '/' ) ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="height: 32px; display: inline-flex; align-items: center; gap: 5px;">
										<span class="dashicons dashicons-performance" style="font-size: 16px; width: 16px; height: 16px; margin: 0;"></span> Auditar Rendimiento
									</a>
								</div>

								<!-- BOTÓN DE GUARDADO -->
								<div style="margin-top: 25px; border-top: 1px dashed var(--wpat-border); padding-top: 20px;">
									<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes de Integraciones" style="height: 36px; padding: 0 20px;" />
								</div>
							</div>
						</div>
				<?php
				break;
			case 'whatsapp':
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Botón Flotante de WhatsApp (Ultra-ligero)</h3>
							<p>Muestra un botón flotante directo a WhatsApp en tu web sin librerías externas pesadas, con soporte para mensajes dinámicos con etiquetas, múltiples agentes, horario comercial y seguimiento de conversiones.</p>
						</div>
						<?php $this->render_module_toggle( 'whatsapp', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">
						
						<!-- SECCIÓN 1: CONFIGURACIÓN BÁSICA -->
						<h4 style="margin: 0 0 15px 0; font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-phone" style="color: #25D366;"></span> 1. Teléfono & Mensaje Predeterminado
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
							<div class="wpat-field-group">
								<label for="wpat_whatsapp_phone" style="font-weight: 600; display: block; margin-bottom: 5px;">Número de Teléfono Principal (con prefijo de país)</label>
								<input type="text" name="wpat_settings[whatsapp_phone]" id="wpat_whatsapp_phone" value="<?php echo esc_attr( isset( $settings['whatsapp_phone'] ) ? $settings['whatsapp_phone'] : '' ); ?>" class="large-text" placeholder="Ej: 34600000000" style="width: 100%;" />
								<p class="description" style="margin-top: 4px;">Introduce el número internacional sin espacios ni signos +. Ejemplo para España: <code>34600000000</code>.</p>
							</div>

							<div class="wpat-field-group">
								<label for="wpat_whatsapp_work_hours" style="font-weight: 600; display: block; margin-bottom: 5px;">Horario de Atención (Opcional)</label>
								<input type="text" name="wpat_settings[whatsapp_work_hours]" id="wpat_whatsapp_work_hours" value="<?php echo esc_attr( isset( $settings['whatsapp_work_hours'] ) ? $settings['whatsapp_work_hours'] : '' ); ?>" class="large-text" placeholder="Ej: Lun - Vie: 09:00 a 19:00" style="width: 100%;" />
								<p class="description" style="margin-top: 4px;">Se muestra como una pequeña insignia informativa en la cabecera del panel multi-agente.</p>
							</div>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 25px;">
							<label for="wpat_whatsapp_message" style="font-weight: 600; display: block; margin-bottom: 5px;">Plantilla de Mensaje Inicial</label>
							<input type="text" name="wpat_settings[whatsapp_message]" id="wpat_whatsapp_message" value="<?php echo esc_attr( isset( $settings['whatsapp_message'] ) ? $settings['whatsapp_message'] : '¡Hola! Quisiera más información sobre {title}.' ); ?>" class="large-text" style="width: 100%;" />
							<p class="description" style="margin-top: 5px;">
								Puedes usar etiquetas dinámicas: <code>{title}</code> (Título de la página o producto), <code>{url}</code> (URL actual), <code>{price}</code> (Precio del producto WooCommerce), <code>{sku}</code> (SKU del producto), <code>{site}</code> (Nombre de la web).
							</p>
						</div>

						<!-- SECCIÓN 2: MULTI-AGENTE -->
						<h4 style="margin: 0 0 15px 0; font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-groups" style="color: #25D366;"></span> 2. Múltiples Agentes / Departamentos (Pop-up Emergente)
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 15px;">
							<div class="wpat-field-group">
								<label for="wpat_whatsapp_popup_title" style="font-weight: 600; display: block; margin-bottom: 5px;">Título del Pop-up</label>
								<input type="text" name="wpat_settings[whatsapp_popup_title]" id="wpat_whatsapp_popup_title" value="<?php echo esc_attr( isset( $settings['whatsapp_popup_title'] ) ? $settings['whatsapp_popup_title'] : 'Contacta con nuestro equipo' ); ?>" class="large-text" style="width: 100%;" />
							</div>

							<div class="wpat-field-group">
								<label for="wpat_whatsapp_popup_subtitle" style="font-weight: 600; display: block; margin-bottom: 5px;">Subtítulo del Pop-up</label>
								<input type="text" name="wpat_settings[whatsapp_popup_subtitle]" id="wpat_whatsapp_popup_subtitle" value="<?php echo esc_attr( isset( $settings['whatsapp_popup_subtitle'] ) ? $settings['whatsapp_popup_subtitle'] : 'Selecciona un asesor para iniciar el chat' ); ?>" class="large-text" style="width: 100%;" />
							</div>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 25px;">
							<label for="wpat_whatsapp_agents" style="font-weight: 600; display: block; margin-bottom: 5px;">Lista de Agentes y Departamentos (Opcional)</label>
							<textarea name="wpat_settings[whatsapp_agents]" id="wpat_whatsapp_agents" rows="3" class="large-text" placeholder="Soporte Técnico | 34600000001 | Departamento Técnico&#10;Ventas y Presupuestos | 34600000002 | Asesor Comercial" style="font-family: monospace; font-size: 12px; width: 100%;"><?php echo esc_textarea( isset( $settings['whatsapp_agents'] ) ? $settings['whatsapp_agents'] : '' ); ?></textarea>
							<p class="description" style="margin-top: 4px;">Escribe un asesor por línea en formato: <code>Nombre | Teléfono | Cargo/Departamento</code>. Si se definen agentes, al hacer clic en el botón de WhatsApp se abrirá una elegante ventana modal con la lista de asesores.</p>
						</div>

						<!-- SECCIÓN 3: DISEÑO Y COMPORTAMIENTO -->
						<h4 style="margin: 0 0 15px 0; font-size: 15px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-admin-appearance" style="color: #25D366;"></span> 3. Posición, Diseño & Dispositivos
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px;">
							<div class="wpat-field-group">
								<label for="wpat_whatsapp_position" style="font-weight: 600; display: block; margin-bottom: 5px;">Posición en Pantalla</label>
								<select name="wpat_settings[whatsapp_position]" id="wpat_whatsapp_position" style="width: 100%;">
									<option value="bottom-right" <?php selected( isset( $settings['whatsapp_position'] ) ? $settings['whatsapp_position'] : 'bottom-right', 'bottom-right' ); ?>>Inferior Derecha</option>
									<option value="bottom-left" <?php selected( isset( $settings['whatsapp_position'] ) ? $settings['whatsapp_position'] : 'bottom-right', 'bottom-left' ); ?>>Inferior Izquierda</option>
								</select>
							</div>

							<div class="wpat-field-group">
								<label for="wpat_whatsapp_offset_x" style="font-weight: 600; display: block; margin-bottom: 5px;">Margen Lateral (px)</label>
								<input type="number" name="wpat_settings[whatsapp_offset_x]" id="wpat_whatsapp_offset_x" value="<?php echo esc_attr( isset( $settings['whatsapp_offset_x'] ) ? $settings['whatsapp_offset_x'] : '20' ); ?>" min="0" max="200" style="width: 100%;" />
							</div>

							<div class="wpat-field-group">
								<label for="wpat_whatsapp_offset_y" style="font-weight: 600; display: block; margin-bottom: 5px;">Margen Inferior (px)</label>
								<input type="number" name="wpat_settings[whatsapp_offset_y]" id="wpat_whatsapp_offset_y" value="<?php echo esc_attr( isset( $settings['whatsapp_offset_y'] ) ? $settings['whatsapp_offset_y'] : '20' ); ?>" min="0" max="200" style="width: 100%;" />
							</div>

							<div class="wpat-field-group">
								<label for="wpat_whatsapp_bg_color" style="font-weight: 600; display: block; margin-bottom: 5px;">Color de Fondo</label>
								<input type="text" name="wpat_settings[whatsapp_bg_color]" id="wpat_whatsapp_bg_color" value="<?php echo esc_attr( isset( $settings['whatsapp_bg_color'] ) ? $settings['whatsapp_bg_color'] : '#25D366' ); ?>" class="wpat-color-picker" data-default-color="#25D366" />
							</div>
						</div>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
							<div class="wpat-field-group">
								<label for="wpat_whatsapp_tooltip" style="font-weight: 600; display: block; margin-bottom: 5px;">Globo de Saludo / Tooltip (Opcional)</label>
								<input type="text" name="wpat_settings[whatsapp_tooltip]" id="wpat_whatsapp_tooltip" value="<?php echo esc_attr( isset( $settings['whatsapp_tooltip'] ) ? $settings['whatsapp_tooltip'] : '' ); ?>" class="large-text" placeholder="Ej: ¿Necesitas ayuda? ¡Escríbenos!" style="width: 100%;" />
							</div>

							<div class="wpat-field-group">
								<label for="wpat_whatsapp_devices" style="font-weight: 600; display: block; margin-bottom: 5px;">Mostrar en Dispositivos</label>
								<select name="wpat_settings[whatsapp_devices]" id="wpat_whatsapp_devices" style="width: 100%;">
									<option value="all" <?php selected( isset( $settings['whatsapp_devices'] ) ? $settings['whatsapp_devices'] : 'all', 'all' ); ?>>Todos los dispositivos (Móvil y Escritorio)</option>
									<option value="mobile" <?php selected( isset( $settings['whatsapp_devices'] ) ? $settings['whatsapp_devices'] : 'all', 'mobile' ); ?>>Sólo en Dispositivos Móviles</option>
									<option value="desktop" <?php selected( isset( $settings['whatsapp_devices'] ) ? $settings['whatsapp_devices'] : 'all', 'desktop' ); ?>>Sólo en Ordenadores de Escritorio</option>
								</select>
							</div>
						</div>

						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 25px; display: flex; flex-direction: column; gap: 10px;">
							<label style="font-weight: 600; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b;">
								<input type="checkbox" name="wpat_settings[whatsapp_pulse]" value="1" <?php checked( ! isset( $settings['whatsapp_pulse'] ) || '1' === (string) $settings['whatsapp_pulse'] ); ?> />
								Activar efecto de pulso / animación de llamada a la acción (Ripple)
							</label>

							<label style="font-weight: 600; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b;">
								<input type="checkbox" name="wpat_settings[whatsapp_track_events]" value="1" <?php checked( ! isset( $settings['whatsapp_track_events'] ) || '1' === (string) $settings['whatsapp_track_events'] ); ?> />
								Registrar automáticamente clics en Google Analytics (evento <code>whatsapp_click</code>) y Meta Pixel
							</label>

							<label style="font-weight: 600; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b;">
								<input type="checkbox" name="wpat_settings[whatsapp_hide_on_checkout]" value="1" <?php checked( isset( $settings['whatsapp_hide_on_checkout'] ) && '1' === (string) $settings['whatsapp_hide_on_checkout'] ); ?> />
								Ocultar botón en páginas de Finalizar Compra (Checkout) y Carrito para evitar distracciones
							</label>
						</div>

						<!-- SECCIÓN 4: SHORTCODES Y ATAJOS -->
						<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px;">
							<h4 style="margin: 0 0 8px 0; font-size: 13px; color: #166534; display: flex; align-items: center; gap: 6px;">
								<span class="dashicons dashicons-shortcode"></span> Shortcodes & Atajos Disponibles
							</h4>
							<ul style="margin: 0; padding-left: 18px; font-size: 12px; color: #14532d; line-height: 1.6;">
								<li><strong>Shortcode botón estándar:</strong> <code>[wpat_whatsapp text="Contactar por WhatsApp" message="Hola, tengo una consulta"]</code></li>
								<li><strong>Disparador por clase CSS:</strong> Añade la clase <code>.wpat-open-whatsapp</code> a cualquier enlace, menú o botón de tu constructor visual (Elementor, Divi, Gutenberg) para abrir directamente la ventana modal de WhatsApp.</li>
							</ul>
						</div>

						<!-- BOTÓN GUARDAR -->
						<div style="margin-top: 25px; border-top: 1px dashed var(--wpat-border); padding-top: 20px;">
							<input type="submit" name="wpat_save_settings" class="button button-primary" value="Guardar Ajustes de WhatsApp" style="height: 36px; padding: 0 20px;" />
						</div>

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

						<!-- 1. Limpieza de contenido y plugins basura -->
						<div class="wpat-field-group" style="margin-bottom: 20px;">
							<label style="font-weight: 600; display: block; margin-bottom: 8px; font-size: 14px; color: #1e293b;">
								<span class="dashicons dashicons-trash" style="color: #ef4444; vertical-align: middle;"></span> 1. Limpieza de Contenido & Plugins por Defecto
							</label>
							<div style="margin-left: 10px; display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 10px;">
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[delete_post]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Eliminar entrada "Hola mundo" y comentarios
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[delete_page]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Eliminar "Página de ejemplo" (Sample Page)
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[delete_hello_dolly]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Eliminar plugin "Hello Dolly"
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[delete_akismet]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Eliminar plugin "Akismet Anti-spam"
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[clean_tagline]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Limpiar descripción corta por defecto
								</label>
							</div>
						</div>

						<!-- 2. Creación de páginas y menú -->
						<div class="wpat-field-group" style="margin-bottom: 20px; border-top: 1px solid #e2e8f0; padding-top: 18px;">
							<label style="font-weight: 600; display: block; margin-bottom: 8px; font-size: 14px; color: #1e293b;">
								<span class="dashicons dashicons-admin-page" style="color: var(--wpat-primary); vertical-align: middle;"></span> 2. Crear Estructura de Páginas & Menú Principal
							</label>
							
							<div style="display: flex; justify-content: space-between; align-items: center; margin: 0 0 12px 10px; flex-wrap: wrap; gap: 10px;">
								<p class="description" style="margin: 0;">Selecciona las páginas que deseas crear automáticamente:</p>
								<label style="font-weight: 600; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; padding: 4px 10px; border-radius: 4px; border: 1px solid var(--wpat-border); color: #475569;">
									<input type="checkbox" name="wpat_init[create_pages]" id="wpat_init_create_pages" value="1" class="wpat-init-action-checkbox" style="margin: 0;" />
									Seleccionar todas las páginas
								</label>
							</div>
							<div style="margin-left: 10px; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid var(--wpat-border);">
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
									<input type="checkbox" name="wpat_init[pages_list][]" value="home" class="wpat-init-page-checkbox" />
									<strong>Inicio</strong> (y fijar como Portada)
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
									<input type="checkbox" name="wpat_init[pages_list][]" value="blog" class="wpat-init-page-checkbox" />
									<strong>Blog</strong> (y fijar como Entradas)
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
									<input type="checkbox" name="wpat_init[pages_list][]" value="portfolio" class="wpat-init-page-checkbox" />
									Proyectos / Portfolio
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center;">
									<input type="checkbox" name="wpat_init[pages_list][]" value="faq" class="wpat-init-page-checkbox" />
									Preguntas frecuentes
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

							<div style="margin-top: 12px; margin-left: 10px;">
								<label style="font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; color: #0f766e;">
									<input type="checkbox" name="wpat_init[create_main_menu]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Crear automáticamente el "Menú Principal" y asignarlo a la cabecera del tema
								</label>
							</div>

							<div style="margin-top: 15px; margin-left: 10px;">
								<label for="wpat_init_custom_pages" style="font-weight: 500; font-size: 13px; display: block; margin-bottom: 6px; color: #475569;">
									Crear páginas personalizadas adicionales (separadas por comas):
								</label>
								<input type="text" name="wpat_init[custom_pages]" id="wpat_init_custom_pages" class="large-text" placeholder="Ej: Equipo, Testimonios, Tienda, Presupuestos" style="width: 100%; max-width: 600px; margin: 0; height: 32px;" />
								<p class="description" style="margin-top: 4px;">Introduce nombres de páginas extra separándolos por comas.</p>
							</div>
						</div>

						<!-- 3. Temas y Plugins de Trabajo -->
						<div class="wpat-field-group" style="margin-bottom: 20px; border-top: 1px solid #e2e8f0; padding-top: 18px;">
							<label style="font-weight: 600; display: block; margin-bottom: 8px; font-size: 14px; color: #1e293b;">
								<span class="dashicons dashicons-admin-plugins" style="color: var(--wpat-primary); vertical-align: middle;"></span> 3. Temas & Plugins de Trabajo Esenciales
							</label>
							<div style="margin-left: 10px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px;">
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[clean_themes]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Eliminar todos los temas inactivos (Conserva solo el activo)
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[install_hello]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Instalar y activar tema oficial "Hello Elementor"
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[install_elementor]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Instalar y activar plugin "Elementor"
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[install_translatepress]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Instalar y activar plugin "TranslatePress"
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[install_woocommerce]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Instalar y activar plugin oficial "WooCommerce"
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[install_fluentforms]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Instalar y activar plugin "Fluent Forms" (Formularios)
								</label>
							</div>
						</div>

						<!-- 4. Ajustes generales del sistema -->
						<div class="wpat-field-group" style="margin-bottom: 20px; border-top: 1px solid #e2e8f0; padding-top: 18px;">
							<label style="font-weight: 600; display: block; margin-bottom: 8px; font-size: 14px; color: #1e293b;">
								<span class="dashicons dashicons-admin-settings" style="color: var(--wpat-primary); vertical-align: middle;"></span> 4. Optimización de Ajustes del Sistema
							</label>
							<div style="margin-left: 10px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px;">
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[media_sizes]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Optimizar tamaños de medios (300px, 800px, 1920px)
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[permalinks]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Cambiar Enlaces Permanentes a "Nombre de entrada" (/%postname%/)
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[disable_default_comments]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Desactivar comentarios y avatares por defecto
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[set_timezone_es]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Fijar zona horaria Europe/Madrid y formato d/m/Y
								</label>
								<label style="font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; background: #fff; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 6px;">
									<input type="checkbox" name="wpat_init[discourage_indexing]" value="1" class="wpat-init-action-checkbox" style="margin-right: 8px;" />
									Disuadir indexación a buscadores (Sitio en desarrollo)
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
							<h3>Ocultar Huella WPAT (Marca Blanca & Modo Silencioso)</h3>
							<p>Oculta o renombra la presencia del plugin WP Agency Toolkit en el panel de administración, permitiendo ofrecer un entorno 100% marca blanca para tus clientes.</p>
						</div>
						<?php $this->render_module_toggle( 'silent-skin', $settings, true ); ?>
					</div>
					<div class="wpat-module-body" style="display: block;">

						<!-- Modo de Visibilidad -->
						<div class="wpat-field-group" style="margin-bottom: 20px;">
							<label for="wpat_white_label_mode" style="font-weight: 600; display: block; margin-bottom: 6px;">Comportamiento en la Lista de Plugins (plugins.php)</label>
							<select name="wpat_settings[white_label_mode]" id="wpat_white_label_mode" class="regular-text" style="width: 100%; max-width: 450px;">
								<option value="rename" <?php selected( isset( $settings['white_label_mode'] ) ? $settings['white_label_mode'] : 'rename', 'rename' ); ?>>Renombrar Plugin y Aplicar Marca Blanca Personalizada</option>
								<option value="hide" <?php selected( isset( $settings['white_label_mode'] ) ? $settings['white_label_mode'] : 'rename', 'hide' ); ?>>Ocultar Totalmente de la Lista de Plugins (Modo Invisible)</option>
							</select>
							<p class="description">Elige si prefieres que el plugin aparezca con la marca de tu agencia o que no aparezca en absoluto en la lista de plugins instalados.</p>
						</div>

						<!-- Grid de Rebranding -->
						<div class="wpat-field-group-row" style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
							<div class="wpat-field-group" style="flex: 1; min-width: 250px;">
								<label for="wpat_white_label_plugin_name" style="font-weight: 600; display: block; margin-bottom: 6px;">Nombre del Plugin</label>
								<input type="text" name="wpat_settings[white_label_plugin_name]" id="wpat_white_label_plugin_name" value="<?php echo esc_attr( isset( $settings['white_label_plugin_name'] ) ? $settings['white_label_plugin_name'] : 'Herramientas del Sitio Web' ); ?>" class="regular-text" style="width: 100%;" />
								<p class="description">Nombre que se mostrará en plugins.php y en la cabecera del panel.</p>
							</div>
							<div class="wpat-field-group" style="flex: 1; min-width: 250px;">
								<label for="wpat_white_label_menu_title" style="font-weight: 600; display: block; margin-bottom: 6px;">Título en el Menú Lateral de WordPress</label>
								<input type="text" name="wpat_settings[white_label_menu_title]" id="wpat_white_label_menu_title" value="<?php echo esc_attr( isset( $settings['white_label_menu_title'] ) ? $settings['white_label_menu_title'] : 'Herramientas Web' ); ?>" class="regular-text" style="width: 100%;" />
								<p class="description">Texto del elemento en el menú de navegación lateral de administración.</p>
							</div>
						</div>

						<div class="wpat-field-group-row" style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
							<div class="wpat-field-group" style="flex: 1; min-width: 250px;">
								<label for="wpat_white_label_author" style="font-weight: 600; display: block; margin-bottom: 6px;">Nombre de la Agencia / Autor</label>
								<input type="text" name="wpat_settings[white_label_author]" id="wpat_white_label_author" value="<?php echo esc_attr( isset( $settings['white_label_author'] ) ? $settings['white_label_author'] : 'Equipo de Desarrollo Web' ); ?>" class="regular-text" style="width: 100%;" />
								<p class="description">Firma o nombre de agencia visible en los créditos del plugin.</p>
							</div>
							<div class="wpat-field-group" style="flex: 1; min-width: 250px;">
								<label for="wpat_white_label_author_url" style="font-weight: 600; display: block; margin-bottom: 6px;">URL de la Agencia / Soporte</label>
								<input type="url" name="wpat_settings[white_label_author_url]" id="wpat_white_label_author_url" value="<?php echo esc_attr( isset( $settings['white_label_author_url'] ) ? $settings['white_label_author_url'] : '' ); ?>" placeholder="https://tuagencia.com" class="regular-text" style="width: 100%;" />
								<p class="description">Enlace hacia la web de tu agencia o portal de clientes.</p>
							</div>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 15px;">
							<label for="wpat_white_label_plugin_desc" style="font-weight: 600; display: block; margin-bottom: 6px;">Descripción Personalizada del Plugin</label>
							<textarea name="wpat_settings[white_label_plugin_desc]" id="wpat_white_label_plugin_desc" rows="2" class="large-text" placeholder="Módulo de optimización, seguridad y utilidades para la administración de este sitio."><?php echo esc_textarea( isset( $settings['white_label_plugin_desc'] ) ? $settings['white_label_plugin_desc'] : 'Módulo de optimización, seguridad y utilidades para la administración de este sitio.' ); ?></textarea>
							<p class="description">Descripción que aparecerá bajo el plugin en la tabla de plugins instalados.</p>
						</div>

						<div class="wpat-field-group" style="margin-bottom: 20px;">
							<label for="wpat_white_label_menu_icon" style="font-weight: 600; display: block; margin-bottom: 6px;">Icono del Menú de Administración</label>
							<select name="wpat_settings[white_label_menu_icon]" id="wpat_white_label_menu_icon" class="regular-text" style="width: 100%; max-width: 320px;">
								<option value="dashicons-admin-generic" <?php selected( isset( $settings['white_label_menu_icon'] ) ? $settings['white_label_menu_icon'] : 'dashicons-admin-generic', 'dashicons-admin-generic' ); ?>>⚙️ Ajustes Generales (dashicons-admin-generic)</option>
								<option value="dashicons-shield" <?php selected( isset( $settings['white_label_menu_icon'] ) ? $settings['white_label_menu_icon'] : '', 'dashicons-shield' ); ?>>🛡️ Escudo de Seguridad (dashicons-shield)</option>
								<option value="dashicons-admin-tools" <?php selected( isset( $settings['white_label_menu_icon'] ) ? $settings['white_label_menu_icon'] : '', 'dashicons-admin-tools' ); ?>>🔧 Herramientas (dashicons-admin-tools)</option>
								<option value="dashicons-art" <?php selected( isset( $settings['white_label_menu_icon'] ) ? $settings['white_label_menu_icon'] : '', 'dashicons-art' ); ?>>🎨 Diseño & Marca (dashicons-art)</option>
								<option value="dashicons-star-filled" <?php selected( isset( $settings['white_label_menu_icon'] ) ? $settings['white_label_menu_icon'] : '', 'dashicons-star-filled' ); ?>>⭐ Estrella Premium (dashicons-star-filled)</option>
								<option value="dashicons-dashboard" <?php selected( isset( $settings['white_label_menu_icon'] ) ? $settings['white_label_menu_icon'] : '', 'dashicons-dashboard' ); ?>>📊 Dashboard (dashicons-dashboard)</option>
								<option value="dashicons-cloud" <?php selected( isset( $settings['white_label_menu_icon'] ) ? $settings['white_label_menu_icon'] : '', 'dashicons-cloud' ); ?>>☁️ Nube & Red (dashicons-cloud)</option>
								<option value="dashicons-superhero" <?php selected( isset( $settings['white_label_menu_icon'] ) ? $settings['white_label_menu_icon'] : '', 'dashicons-superhero' ); ?>>🦸 Pro Toolkit (dashicons-superhero)</option>
							</select>
							<p class="description">Icono Dashicon que lucirá en la barra lateral del panel de control de WordPress.</p>
						</div>

						<!-- Restricción de Acceso & Ocultación -->
						<div class="wpat-field-group" style="margin-top: 20px; border-top: 1px dotted var(--wpat-border); padding-top: 15px;">
							<label style="font-weight: 600; display: block; margin-bottom: 10px;">Seguridad y Restricción de Acceso al Panel</label>
							
							<div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 15px;">
								<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px;">
									<input type="checkbox" name="wpat_settings[white_label_hide_from_non_admins]" value="1" <?php checked( isset( $settings['white_label_hide_from_non_admins'] ) ? $settings['white_label_hide_from_non_admins'] : '1', '1' ); ?> />
									<strong>Restringir acceso al menú:</strong> Ocultar el menú de administración a todos los usuarios excepto al Administrador principal o usuarios autorizados.
								</label>

								<label style="font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px;">
									<input type="checkbox" name="wpat_settings[white_label_prevent_deactivation]" value="1" <?php checked( isset( $settings['white_label_prevent_deactivation'] ) ? $settings['white_label_prevent_deactivation'] : '0', '1' ); ?> />
									<strong>Prevenir desactivación accidental:</strong> Ocultar el botón "Desactivar" en la lista de plugins.
								</label>
							</div>

							<div class="wpat-field-group" style="margin-top: 10px;">
								<label for="wpat_white_label_allowed_users" style="font-weight: 600; display: block; margin-bottom: 4px;">Usuarios o Emails Autorizados</label>
								<input type="text" name="wpat_settings[white_label_allowed_users]" id="wpat_white_label_allowed_users" value="<?php echo esc_attr( isset( $settings['white_label_allowed_users'] ) ? $settings['white_label_allowed_users'] : '' ); ?>" placeholder="admin, mi_agencia@correo.com" class="regular-text" style="width: 100%; max-width: 450px;" />
								<p class="description">Nombres de usuario o correos electrónicos (separados por coma) que podrán ver y gestionar este panel. Si está vacío, solo tendrá acceso el usuario ID 1 o Superadministrador.</p>
							</div>
						</div>

						<!-- Caja de Ayuda / Clave de Desbloqueo -->
						<div style="margin-top: 20px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px 18px; font-size: 13px; color: #1e40af; line-height: 1.5;">
							<div style="display: flex; align-items: center; gap: 8px; font-weight: 700; margin-bottom: 4px;">
								<span>💡 Enlace de Desbloqueo Directo (Emergencia)</span>
							</div>
							<p style="margin: 0;">Si en algún momento el menú lateral queda oculto para tu usuario, siempre puedes acceder directamente a la configuración introduciendo esta URL en tu navegador:</p>
							<code style="display: block; margin-top: 6px; padding: 6px 10px; background: #fff; border: 1px solid #dbeafe; border-radius: 4px; color: #1d4ed8; word-break: break-all;">
								<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&wpat_unlock=1' ) ); ?>
							</code>
						</div>

					</div>
				</div>
				<?php
				break;

			case 'conflict-detector':
				require_once WPAT_PATH . 'includes/modules/class-wpat-conflict-detector.php';
				$conflicts  = WPAT_Conflict_Detector::get_active_conflicts();
				$js_errors  = WPAT_Conflict_Detector::get_logged_js_errors();
				$ajax_nonce = wp_create_nonce( 'wpat_js_error_nonce' );
				?>
				<div class="wpat-module-card">
					<div class="wpat-module-header">
						<div class="wpat-module-info">
							<h3>Detector de Incompatibilidades y Conflictos</h3>
							<p>Monitorea y detecta plugins duplicados o conflictivos que puedan causar interferencias con los módulos nativos de WP Agency Toolkit, y previene bloqueos fatales.</p>
						</div>
						<?php $this->render_module_toggle( 'conflict-detector', $settings, false ); ?>
					</div>
					<div class="wpat-module-body" style="display: block; padding: 20px;">
						<h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
							<span class="dashicons dashicons-admin-plugins" style="color: #6366f1;"></span>
							<?php esc_html_e( 'Plugins de Terceros Detectados', 'wp-agency-toolkit' ); ?>
						</h4>
						<?php if ( ! empty( $conflicts ) ) : ?>
							<div class="wpat-conflict-list" style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;">
								<?php foreach ( $conflicts as $plugin_file => $conflict ) : ?>
									<div class="wpat-conflict-item" style="background: #fff1f2; border: 1px solid #fecdd3; border-left: 4px solid #e11d48; padding: 15px; border-radius: 6px;">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
											<h4 style="margin: 0; font-size: 15px; color: #9f1239; font-weight: 600;"><?php echo esc_html( $conflict['name'] ); ?></h4>
											<span class="wpat-badge" style="background: <?php echo 'active' === $conflict['status'] ? '#e11d48' : '#f43f5e'; ?>; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase; font-weight: bold;">
												<?php echo 'active' === $conflict['status'] ? esc_html__( 'Plugin Activo', 'wp-agency-toolkit' ) : esc_html__( 'Instalado', 'wp-agency-toolkit' ); ?>
											</span>
										</div>
										<p style="margin: 0 0 10px 0; color: #881337; font-size: 13px; line-height: 1.5;"><?php echo esc_html( $conflict['reason'] ); ?></p>
										<a href="<?php echo esc_url( $conflict['action_link'] ); ?>" class="button button-small" style="background: #be123c; border-color: #9f1239; color: #fff; text-shadow: none;">
											<?php esc_html_e( 'Gestionar Plugins', 'wp-agency-toolkit' ); ?>
										</a>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a; padding: 15px; border-radius: 6px; display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
								<span class="dashicons dashicons-yes-alt" style="font-size: 24px; width: 24px; height: 24px; color: #16a34a;"></span>
								<div>
									<h4 style="margin: 0 0 4px 0; font-size: 14px; color: #14532d; font-weight: 600;"><?php esc_html_e( 'Sin Incompatibilidades de Plugins', 'wp-agency-toolkit' ); ?></h4>
									<p style="margin: 0; color: #166534; font-size: 13px;"><?php esc_html_e( 'No se han detectado plugins conflictivos o duplicados en esta instalación. Los módulos nativos de WP Agency Toolkit funcionan con máxima compatibilidad y rendimiento.', 'wp-agency-toolkit' ); ?></p>
								</div>
							</div>
						<?php endif; ?>

						<hr style="border: 0; border-top: 1px dashed #e2e8f0; margin: 25px 0;">

						<!-- Monitor de Errores JavaScript en Tiempo Real -->
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
							<h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-code-standards" style="color: #6366f1;"></span>
								<?php esc_html_e( 'Monitor de Errores JavaScript & Scripts en Tiempo Real', 'wp-agency-toolkit' ); ?>
							</h4>
							<?php if ( ! empty( $js_errors ) ) : ?>
								<button type="button" id="wpat-clear-js-errors-btn" class="button button-secondary button-small" style="display: inline-flex; align-items: center; gap: 4px;">
									<span class="dashicons dashicons-trash" style="font-size: 15px; line-height: 20px; width: 15px; height: 15px;"></span>
									<?php esc_html_e( 'Limpiar Registro', 'wp-agency-toolkit' ); ?>
								</button>
							<?php endif; ?>
						</div>
						<p style="font-size: 13px; color: #64748b; margin-top: 0; margin-bottom: 15px;">
							<?php esc_html_e( 'Captura automáticamente excepciones no controladas de JavaScript, scripts caídos y promesas rechazadas en el frontend y panel de administración para ayudarte a identificar qué plugin o script está fallando.', 'wp-agency-toolkit' ); ?>
						</p>

						<div id="wpat-js-errors-container">
							<?php if ( ! empty( $js_errors ) ) : ?>
								<div style="display: flex; flex-direction: column; gap: 10px;">
									<?php foreach ( $js_errors as $err ) : ?>
										<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #ef4444; border-radius: 6px; padding: 12px 16px;">
											<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 6px;">
												<strong style="color: #b91c1c; font-size: 13px; font-family: monospace; word-break: break-all;">
													<?php echo esc_html( $err['msg'] ); ?>
												</strong>
												<span style="font-size: 11px; color: #94a3b8; white-space: nowrap;"><?php echo esc_html( $err['time'] ); ?></span>
											</div>
											<div style="font-size: 12px; color: #475569; font-family: monospace;">
												<div><strong><?php esc_html_e( 'Archivo:', 'wp-agency-toolkit' ); ?></strong> <?php echo esc_html( $err['file'] ); ?><?php if ( ! empty( $err['line'] ) ) : ?>:<strong><?php echo (int) $err['line']; ?></strong><?php endif; ?></div>
												<?php if ( ! empty( $err['url'] ) ) : ?>
													<div style="color: #64748b; margin-top: 3px;"><strong><?php esc_html_e( 'Página:', 'wp-agency-toolkit' ); ?></strong> <?php echo esc_html( $err['url'] ); ?></div>
												<?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 6px; display: flex; align-items: center; gap: 10px;">
									<span class="dashicons dashicons-shield-alt" style="font-size: 20px; width: 20px; height: 20px; color: #3b82f6;"></span>
									<span style="color: #475569; font-size: 13px;">
										<?php esc_html_e( 'No se han registrado errores de JavaScript en la consola del navegador. Tus scripts se están ejecutando con normalidad.', 'wp-agency-toolkit' ); ?>
									</span>
								</div>
							<?php endif; ?>
						</div>

						<script type="text/javascript">
							document.addEventListener('DOMContentLoaded', function() {
								var btn = document.getElementById('wpat-clear-js-errors-btn');
								if (btn) {
									btn.addEventListener('click', function() {
										btn.disabled = true;
										btn.textContent = '<?php echo esc_js( __( 'Limpiando...', 'wp-agency-toolkit' ) ); ?>';
										var xhr = new XMLHttpRequest();
										xhr.open('POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', true);
										xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
										xhr.onload = function() {
											var container = document.getElementById('wpat-js-errors-container');
											if (container) {
												container.innerHTML = '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6; padding: 12px 16px; border-radius: 6px; display: flex; align-items: center; gap: 10px;"><span class="dashicons dashicons-shield-alt" style="font-size: 20px; width: 20px; height: 20px; color: #3b82f6;"></span><span style="color: #475569; font-size: 13px;"><?php echo esc_js( __( 'Registro de errores vaciado con éxito.', 'wp-agency-toolkit' ) ); ?></span></div>';
											}
											btn.style.display = 'none';
										};
										xhr.send('action=wpat_clear_js_errors&security=<?php echo esc_js( $ajax_nonce ); ?>');
									});
								}
							});
						</script>
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
			case 'error-log-viewer':
				$this->render_error_log_viewer_content( $settings );
				break;
			case 'role-manager':
				$this->render_role_manager_content( $settings );
				break;
			case 'cookie-consent':
				$this->render_cookie_consent_content( $settings );
				break;
			case 'quick-pay':
				$this->render_quick_pay_content( $settings );
				break;
			case 'qr-generator':
				$this->render_qr_generator_content( $settings );
				break;
			case 'admin-tables-ui':
				$this->render_admin_tables_ui_content( $settings );
				break;
			case 'tools':
				echo '<div id="wpat_health_content_wrapper">';
				$this->render_health_tab_content();
				echo '</div>';
				break;
			default:
				echo '<div class="notice notice-info"><p>Módulo de configuración en preparación.</p></div>';
				break;
		}
	}

	/**
	 * Renderiza la interfaz del Visor y Monitor de Logs de Error en tiempo real.
	 *
	 * @param array $settings Ajustes del plugin.
	 */
	public function render_error_log_viewer_content( $settings ) {
		if ( ! class_exists( 'WPAT_Error_Log_Viewer' ) ) {
			require_once WPAT_PATH . 'includes/modules/class-wpat-error-log-viewer.php';
		}

		$log_data   = WPAT_Error_Log_Viewer::read_last_log_lines( 250 );
		$debug_info = WPAT_Error_Log_Viewer::get_debug_status();
		$entries    = $log_data['entries'];

		$count_total      = count( $entries );
		$count_fatal      = 0;
		$count_warning    = 0;
		$count_notice     = 0;
		$count_deprecated = 0;

		foreach ( $entries as $entry ) {
			if ( 'wpat-fatal' === $entry['badge_class'] ) {
				$count_fatal++;
			} elseif ( 'wpat-warning' === $entry['badge_class'] ) {
				$count_warning++;
			} elseif ( 'wpat-deprecated' === $entry['badge_class'] ) {
				$count_deprecated++;
			} else {
				$count_notice++;
			}
		}

		$download_url = wp_nonce_url( admin_url( 'admin.php?page=wp-agency-toolkit&mod=error-log-viewer&wpat_action=download_error_log' ), 'wpat_download_log_nonce' );
		?>
		<div class="wpat-module-card wpat-error-log-wrapper">
			<div class="wpat-module-header">
				<div class="wpat-module-info">
					<h3>Visor y Monitor de Logs de Error (<code>debug.log</code>)</h3>
					<p>Supervisa en tiempo real los registros y errores de PHP/WordPress, clasifica por severidad, expande stack traces y vacía el archivo con un clic.</p>
				</div>
				<?php $this->render_module_toggle( 'error-log-viewer', $settings, true ); ?>
			</div>

			<div class="wpat-module-body" style="display: block; padding: 22px;">

				<!-- BARRA DE DIAGNÓSTICO DEL SISTEMA Y CONSTANTES WP_DEBUG -->
				<div class="wpat-log-diagnostics-bar" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
					<div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
						<div style="font-size: 13px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
							<span class="dashicons dashicons-admin-settings" style="color: #6366f1;"></span> Diagnóstico:
						</div>

						<div class="wpat-diag-badge <?php echo $debug_info['wp_debug'] ? 'active' : 'inactive'; ?>" title="<?php echo $debug_info['wp_debug'] ? 'WP_DEBUG está habilitado' : 'WP_DEBUG está deshabilitado'; ?>">
							<span class="dot"></span> WP_DEBUG: <strong><?php echo $debug_info['wp_debug'] ? 'ACTIVO' : 'INACTIVO'; ?></strong>
						</div>

						<div class="wpat-diag-badge <?php echo $debug_info['wp_debug_log'] ? 'active' : 'warning'; ?>" title="<?php echo $debug_info['wp_debug_log'] ? 'WP_DEBUG_LOG guardando errores en debug.log' : 'WP_DEBUG_LOG desactivado. No se generará debug.log'; ?>">
							<span class="dot"></span> WP_DEBUG_LOG: <strong><?php echo $debug_info['wp_debug_log'] ? 'ACTIVO' : 'DESACTIVADO'; ?></strong>
						</div>

						<div class="wpat-diag-badge <?php echo $debug_info['wp_debug_display'] ? 'warning' : 'active'; ?>" title="<?php echo $debug_info['wp_debug_display'] ? 'WP_DEBUG_DISPLAY activo (Muestra errores en pantalla frontend)' : 'WP_DEBUG_DISPLAY oculto (Recomendado en producción)'; ?>">
							<span class="dot"></span> WP_DEBUG_DISPLAY: <strong><?php echo $debug_info['wp_debug_display'] ? 'VISIBLE' : 'OCULTO'; ?></strong>
						</div>

						<div class="wpat-diag-badge neutral" title="Versión de PHP del servidor">
							PHP <strong><?php echo esc_html( $debug_info['php_version'] ); ?></strong>
						</div>

						<div class="wpat-diag-badge neutral" title="Límite de memoria PHP">
							Memoria: <strong><?php echo esc_html( $debug_info['memory_limit'] ); ?></strong>
						</div>
					</div>

					<div class="wpat-log-file-meta" style="font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 10px;">
						<span>Archivo: <code style="font-size: 11px; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a;" title="<?php echo esc_attr( $log_data['path'] ); ?>"><?php echo esc_html( basename( $log_data['path'] ) ); ?></code></span>
						<span>Tamaño: <strong id="wpat_log_filesize_badge" style="color: #0f172a;"><?php echo esc_html( $log_data['size_fmt'] ); ?></strong></span>
					</div>
				</div>

				<?php if ( ! $debug_info['wp_debug_log'] ) : ?>
					<!-- AVISO DE WP_DEBUG_LOG DESACTIVADO CON SNIPPET -->
					<div class="wpat-debug-log-notice" style="background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 14px 18px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
						<div>
							<h4 style="margin: 0 0 4px 0; color: #92400e; font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
								<span class="dashicons dashicons-info" style="color: #f59e0b;"></span> El registro de errores en archivo (<code>WP_DEBUG_LOG</code>) está desactivado
							</h4>
							<p style="margin: 0; color: #b45309; font-size: 12.5px;">
								Para que WordPress guarde los errores en <code>wp-content/debug.log</code>, añade estas líneas en tu archivo <code>wp-config.php</code> justo antes de <em>/* That's all, stop editing! */</em>:
							</p>
						</div>
						<div style="display: flex; align-items: center; gap: 8px;">
							<button type="button" class="button button-secondary" id="wpat_copy_wp_config_snippet" data-snippet="define( 'WP_DEBUG', true );&#10;define( 'WP_DEBUG_LOG', true );&#10;define( 'WP_DEBUG_DISPLAY', false );&#10;@ini_set( 'display_errors', 0 );" style="background: #fff; border-color: #f59e0b; color: #92400e; font-weight: 600; height: 32px; display: inline-flex; align-items: center; gap: 6px;">
								<span class="dashicons dashicons-clipboard" style="font-size: 16px; width: 16px; height: 16px;"></span> Copiar Código para wp-config.php
							</button>
						</div>
					</div>
				<?php endif; ?>

				<!-- TARJETAS DE CONTADORES DE SEVERIDAD -->
				<div class="wpat-log-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 22px;">
					<div class="wpat-log-stat-card wpat-card-total" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; text-align: center;">
						<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Total Errores</div>
						<div class="wpat-stat-num" id="wpat_stat_total" style="font-size: 22px; font-weight: 800; color: #0f172a; margin-top: 4px;"><?php echo esc_html( $count_total ); ?></div>
					</div>
					<div class="wpat-log-stat-card wpat-card-fatal" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 14px; text-align: center;">
						<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #b91c1c; letter-spacing: 0.5px;">Fatal Errors</div>
						<div class="wpat-stat-num" id="wpat_stat_fatal" style="font-size: 22px; font-weight: 800; color: #dc2626; margin-top: 4px;"><?php echo esc_html( $count_fatal ); ?></div>
					</div>
					<div class="wpat-log-stat-card wpat-card-warning" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; text-align: center;">
						<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #b45309; letter-spacing: 0.5px;">Warnings</div>
						<div class="wpat-stat-num" id="wpat_stat_warning" style="font-size: 22px; font-weight: 800; color: #d97706; margin-top: 4px;"><?php echo esc_html( $count_warning ); ?></div>
					</div>
					<div class="wpat-log-stat-card wpat-card-notice" style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 14px; text-align: center;">
						<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #1d4ed8; letter-spacing: 0.5px;">Notices</div>
						<div class="wpat-stat-num" id="wpat_stat_notice" style="font-size: 22px; font-weight: 800; color: #2563eb; margin-top: 4px;"><?php echo esc_html( $count_notice ); ?></div>
					</div>
					<div class="wpat-log-stat-card wpat-card-deprecated" style="background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 12px 14px; text-align: center;">
						<div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6d28d9; letter-spacing: 0.5px;">Deprecated</div>
						<div class="wpat-stat-num" id="wpat_stat_deprecated" style="font-size: 22px; font-weight: 800; color: #7c3aed; margin-top: 4px;"><?php echo esc_html( $count_deprecated ); ?></div>
					</div>
				</div>

				<!-- BARRA DE HERRAMIENTAS Y CONTROLES -->
				<div class="wpat-log-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
					<!-- Buscador y Filtro por Severidad -->
					<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1; min-width: 280px;">
						<div class="wpat-log-search-box" style="position: relative; flex: 1; max-width: 320px;">
							<span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 8px; color: #94a3b8; font-size: 18px; width: 18px; height: 18px;"></span>
							<input type="text" id="wpat_log_search" placeholder="Buscar por error, archivo o línea..." style="width: 100%; height: 36px; padding: 0 12px 0 34px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" />
						</div>

						<div class="wpat-log-severity-filters" style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
							<button type="button" class="wpat-log-filter-btn active" data-filter="all">Todos</button>
							<button type="button" class="wpat-log-filter-btn wpat-filter-fatal" data-filter="wpat-fatal">Fatal (<span class="f-count"><?php echo esc_html( $count_fatal ); ?></span>)</button>
							<button type="button" class="wpat-log-filter-btn wpat-filter-warning" data-filter="wpat-warning">Warning (<span class="w-count"><?php echo esc_html( $count_warning ); ?></span>)</button>
							<button type="button" class="wpat-log-filter-btn wpat-filter-notice" data-filter="wpat-notice">Notice (<span class="n-count"><?php echo esc_html( $count_notice ); ?></span>)</button>
							<button type="button" class="wpat-log-filter-btn wpat-filter-deprecated" data-filter="wpat-deprecated">Deprecated (<span class="d-count"><?php echo esc_html( $count_deprecated ); ?></span>)</button>
						</div>
					</div>

					<!-- Acciones: Auto-refresh, Refrescar, Copiar, Descargar, Vaciar -->
					<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
						<!-- Auto Refresco Switch -->
						<div class="wpat-log-live-toggle" style="display: flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 10px; border-radius: 6px;" title="Actualiza automáticamente los errores cada 5 segundos">
							<label class="wpat-switch" style="transform: scale(0.75); margin: 0;">
								<input type="checkbox" id="wpat_log_auto_refresh_toggle">
								<span class="wpat-slider"></span>
							</label>
							<span style="font-size: 12px; font-weight: 600; color: #475569;" id="wpat_log_live_status_label">Auto-refresco (5s)</span>
						</div>

						<button type="button" class="button button-secondary" id="wpat_refresh_logs_btn" style="height: 34px; line-height: 32px; display: inline-flex; align-items: center; gap: 5px; font-weight: 600; border-radius: 6px;" title="Comprobar registros ahora">
							<span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span> Actualizar
						</button>

						<button type="button" class="button button-secondary" id="wpat_copy_last_error_btn" style="height: 34px; line-height: 32px; display: inline-flex; align-items: center; gap: 5px; font-weight: 600; border-radius: 6px;" title="Copiar el error más reciente al portapapeles">
							<span class="dashicons dashicons-clipboard" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span> Copiar Último
						</button>

						<a href="<?php echo esc_url( $download_url ); ?>" class="button button-secondary" id="wpat_download_log_btn" style="height: 34px; line-height: 32px; display: inline-flex; align-items: center; gap: 5px; font-weight: 600; border-radius: 6px;" title="Descargar archivo debug.log completo">
							<span class="dashicons dashicons-download" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span> Descargar Log
						</a>

						<button type="button" class="button button-link-delete" id="wpat_clear_log_btn" style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; height: 34px; line-height: 32px; padding: 0 12px; display: inline-flex; align-items: center; gap: 5px; font-weight: 700; border-radius: 6px;" title="Vaciar completamente el archivo de log">
							<span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span> Vaciar Log
						</button>
					</div>
				</div>

				<!-- SUB-PESTAÑAS: VISTA ESTRUCTURADA VS TERMINAL BRUTO -->
				<div class="wpat-log-view-tabs" style="display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 18px;">
					<button type="button" class="wpat-log-tab-btn active" data-target="structured" style="background: none; border: none; padding: 10px 16px; font-weight: 700; font-size: 13.5px; color: #2563eb; border-bottom: 2px solid #2563eb; margin-bottom: -2px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
						<span class="dashicons dashicons-list-view"></span> Vista Estructurada
					</button>
					<button type="button" class="wpat-log-tab-btn" data-target="raw" style="background: none; border: none; padding: 10px 16px; font-weight: 600; font-size: 13.5px; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
						<span class="dashicons dashicons-editor-code"></span> Vista Terminal / Bruto
					</button>
				</div>

				<!-- TAB 1: VISTA ESTRUCTURADA (TABLA INTERACTIVA) -->
				<div class="wpat-log-tab-content active" id="wpat_log_view_structured">
					<div class="wpat-log-table-container" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
						<table class="wpat-log-table widefat" style="border: none; margin: 0;">
							<thead>
								<tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
									<th style="width: 100px; font-weight: 700; color: #475569; padding: 12px 14px;">Severidad</th>
									<th style="width: 160px; font-weight: 700; color: #475569; padding: 12px 14px;">Fecha / Hora</th>
									<th style="font-weight: 700; color: #475569; padding: 12px 14px;">Mensaje de Error</th>
									<th style="width: 250px; font-weight: 700; color: #475569; padding: 12px 14px;">Archivo y Línea</th>
									<th style="width: 110px; font-weight: 700; color: #475569; padding: 12px 14px; text-align: right;">Acciones</th>
								</tr>
							</thead>
							<tbody id="wpat_log_entries_tbody">
								<?php if ( empty( $entries ) ) : ?>
									<tr class="wpat-log-empty-row">
										<td colspan="5" style="text-align: center; padding: 40px 20px;">
											<div style="font-size: 38px; margin-bottom: 10px;">✨</div>
											<strong style="font-size: 15px; color: #0f172a; display: block;">¡Excelente! No hay errores registrados</strong>
											<p style="color: #64748b; font-size: 13px; margin: 4px 0 0 0;">El archivo <code>debug.log</code> está completamente limpio o no ha registrado incidentes.</p>
										</td>
									</tr>
								<?php else : ?>
									<?php foreach ( $entries as $idx => $entry ) : ?>
										<tr class="wpat-log-entry-row severity-<?php echo esc_attr( $entry['badge_class'] ); ?>" data-severity="<?php echo esc_attr( $entry['badge_class'] ); ?>" data-search="<?php echo esc_attr( strtolower( $entry['message'] . ' ' . $entry['file'] . ' ' . $entry['line'] . ' ' . $entry['severity'] ) ); ?>">
											<td style="padding: 12px 14px; vertical-align: top;">
												<span class="wpat-severity-badge <?php echo esc_attr( $entry['badge_class'] ); ?>">
													<?php echo esc_html( $entry['severity'] ); ?>
												</span>
											</td>
											<td style="padding: 12px 14px; vertical-align: top; white-space: nowrap;">
												<strong style="font-size: 12px; color: #1e293b; display: block;"><?php echo esc_html( $entry['time_human'] ); ?></strong>
												<span style="font-size: 11px; color: #94a3b8;" title="<?php echo esc_attr( $entry['timestamp'] ); ?>"><?php echo esc_html( $entry['timestamp'] ); ?></span>
											</td>
											<td style="padding: 12px 14px; vertical-align: top;">
												<div class="wpat-log-msg-text" style="font-size: 13px; color: #0f172a; font-weight: 500; word-break: break-word; line-height: 1.4;">
													<?php echo esc_html( $entry['message'] ); ?>
												</div>
												<?php if ( ! empty( $entry['stack_trace'] ) ) : ?>
													<div class="wpat-log-trace-box" id="wpat_trace_<?php echo esc_attr( $idx ); ?>" style="display: none; margin-top: 10px; background: #0f172a; color: #f8fafc; border-radius: 6px; padding: 12px; font-family: monospace; font-size: 11.5px; white-space: pre-wrap; line-height: 1.5; max-height: 250px; overflow-y: auto;">
														<?php echo esc_html( $entry['stack_trace'] ); ?>
													</div>
												<?php endif; ?>
											</td>
											<td style="padding: 12px 14px; vertical-align: top;">
												<?php if ( ! empty( $entry['file'] ) ) : ?>
													<div style="font-family: monospace; font-size: 11.5px; color: #475569; word-break: break-all;" title="<?php echo esc_attr( $entry['file'] ); ?>">
														<?php echo esc_html( $entry['file'] ); ?>
														<?php if ( ! empty( $entry['line'] ) ) : ?>
															<span class="wpat-log-line-num" style="background: #e2e8f0; color: #0f172a; padding: 1px 5px; border-radius: 4px; font-weight: 700; margin-left: 4px;">:<?php echo esc_html( $entry['line'] ); ?></span>
														<?php endif; ?>
													</div>
												<?php else : ?>
													<span style="color: #94a3b8; font-size: 12px;">—</span>
												<?php endif; ?>
											</td>
											<td style="padding: 12px 14px; vertical-align: top; text-align: right; white-space: nowrap;">
												<div style="display: flex; gap: 4px; justify-content: flex-end;">
													<?php if ( ! empty( $entry['stack_trace'] ) ) : ?>
														<button type="button" class="button button-small wpat-toggle-trace-btn" data-target="#wpat_trace_<?php echo esc_attr( $idx ); ?>" title="Ver Stack Trace de ejecución" style="padding: 0 6px; height: 26px; line-height: 24px;">
															<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span> Stack
														</button>
													<?php endif; ?>
													<button type="button" class="button button-small wpat-copy-single-error-btn" data-raw="<?php echo esc_attr( $entry['raw_line'] . ( ! empty( $entry['stack_trace'] ) ? "\n" . $entry['stack_trace'] : '' ) ); ?>" title="Copiar este error completo" style="padding: 0 6px; height: 26px; line-height: 24px;">
														<span class="dashicons dashicons-clipboard" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span>
													</button>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- TAB 2: VISTA TERMINAL / BRUTO (RAW) -->
				<div class="wpat-log-tab-content" id="wpat_log_view_raw" style="display: none;">
					<div class="wpat-raw-terminal-header" style="display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 8px 16px; border-radius: 8px 8px 0 0;">
						<span style="color: #94a3b8; font-size: 12px; font-family: monospace;">debug.log (Últimos 2MB)</span>
						<button type="button" class="button button-small" id="wpat_copy_raw_log_btn" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); color: #fff; font-size: 11px; height: 26px; display: inline-flex; align-items: center; gap: 4px;">
							<span class="dashicons dashicons-clipboard" style="font-size: 14px; width: 14px; height: 14px;"></span> Copiar Todo en Bruto
						</button>
					</div>
					<pre id="wpat_log_raw_pre" style="background: #0f172a; color: #38bdf8; padding: 16px; border-radius: 0 0 8px 8px; margin: 0; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 12px; line-height: 1.6; max-height: 550px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; border: 1px solid #1e293b; border-top: none;"><?php echo esc_html( $log_data['raw'] ); ?></pre>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza la interfaz del Gestor de Roles y Permisos (Role Manager).
	 *
	 * @param array $settings Ajustes del plugin.
	 */
	public function render_role_manager_content( $settings ) {
		if ( ! class_exists( 'WPAT_Role_Manager' ) ) {
			require_once WPAT_PATH . 'includes/modules/class-wpat-role-manager.php';
		}

		$roles          = WPAT_Role_Manager::get_all_roles_with_meta();
		$categories     = WPAT_Role_Manager::get_categorized_capabilities_map();
		$active_role_slug = isset( $_GET['role'] ) && isset( $roles[ sanitize_key( $_GET['role'] ) ] ) ? sanitize_key( $_GET['role'] ) : ( isset( $roles['administrator'] ) ? 'administrator' : key( $roles ) );
		$active_role    = isset( $roles[ $active_role_slug ] ) ? $roles[ $active_role_slug ] : reset( $roles );
		?>
		<div class="wpat-module-card wpat-role-manager-wrapper">
			<div class="wpat-module-header">
				<div class="wpat-module-info">
					<h3>Gestor de Roles y Permisos (<code>Capabilities</code>)</h3>
					<p>Crea, clona, personaliza y audita los permisos de usuarios y tiendas de forma visual, segura y sin dependencias externas.</p>
				</div>
				<?php $this->render_module_toggle( 'role-manager', $settings, true ); ?>
			</div>

			<div class="wpat-module-body" style="display: block; padding: 22px;">

				<!-- CONTENEDOR PRINCIPAL: SIDEBAR DE ROLES + PANEL DE CAPABILITIES -->
				<div class="wpat-roles-layout" style="display: flex; gap: 24px; align-items: flex-start;">

					<!-- COLUMNA IZQUIERDA: LISTA DE ROLES -->
					<aside class="wpat-roles-sidebar" style="width: 270px; flex-shrink: 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
							<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Roles Registrados</span>
							<button type="button" class="button button-primary button-small" id="wpat_open_create_role_modal_btn" style="background: #2563eb; border-color: #1d4ed8; font-size: 11px; height: 26px; line-height: 24px; border-radius: 5px; display: inline-flex; align-items: center; gap: 4px;">
								<span class="dashicons dashicons-plus-alt2" style="font-size: 13px; width: 13px; height: 13px; line-height: 1;"></span> Nuevo Rol
							</button>
						</div>

						<div class="wpat-roles-list" id="wpat_roles_list_container" style="display: flex; flex-direction: column; gap: 4px; max-height: 520px; overflow-y: auto;">
							<?php foreach ( $roles as $slug => $r ) : ?>
								<?php
								$is_active = ( $slug === $active_role_slug );
								$badge_color = ( 'core' === $r['type'] ) ? '#2563eb' : ( ( 'woocommerce' === $r['type'] ) ? '#7c3aed' : '#059669' );
								$badge_bg    = ( 'core' === $r['type'] ) ? '#eff6ff' : ( ( 'woocommerce' === $r['type'] ) ? '#f5f3ff' : '#ecfdf5' );
								?>
								<button type="button" class="wpat-role-item <?php echo $is_active ? 'active' : ''; ?>" data-role="<?php echo esc_attr( $slug ); ?>" style="text-align: left; background: <?php echo $is_active ? '#ffffff' : 'transparent'; ?>; border: 1px solid <?php echo $is_active ? '#2563eb' : 'transparent'; ?>; border-radius: 8px; padding: 10px 12px; cursor: pointer; transition: all 0.15s ease-in-out; display: flex; justify-content: space-between; align-items: center; width: 100%; box-shadow: <?php echo $is_active ? '0 2px 6px rgba(37,99,235,0.1)' : 'none'; ?>;">
									<div style="min-width: 0; flex: 1;">
										<div style="font-weight: 700; font-size: 13px; color: <?php echo $is_active ? '#1e40af' : '#1e293b'; ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
											<?php echo esc_html( $r['name'] ); ?>
										</div>
										<div style="font-size: 11px; color: #64748b; font-family: monospace; margin-top: 2px;">
											<code><?php echo esc_html( $slug ); ?></code>
										</div>
									</div>
									<div style="text-align: right; flex-shrink: 0; margin-left: 8px;">
										<span style="font-size: 10px; font-weight: 700; color: <?php echo esc_attr( $badge_color ); ?>; background: <?php echo esc_attr( $badge_bg ); ?>; padding: 2px 6px; border-radius: 4px; display: block; margin-bottom: 3px;">
											<?php echo esc_html( $r['type_label'] ); ?>
										</span>
										<span style="font-size: 10.5px; color: #94a3b8; font-weight: 600;">
											<?php echo esc_html( $r['user_count'] ); ?> <?php echo ( 1 === $r['user_count'] ) ? 'usuario' : 'usuarios'; ?>
										</span>
									</div>
								</button>
							<?php endforeach; ?>
						</div>

						<div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #e2e8f0; text-align: center;">
							<button type="button" class="button button-link-delete" id="wpat_reset_roles_btn" style="font-size: 11.5px; color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
								<span class="dashicons dashicons-undo" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span> Restaurar Roles Predeterminados
							</button>
						</div>
					</aside>

					<!-- COLUMNA DERECHA: MATRIZ DE CAPABILITIES DEL ROL ACTIVO -->
					<main class="wpat-role-main-panel" style="flex: 1; min-width: 0;">

						<!-- CABECERA DEL ROL ACTIVO -->
						<div class="wpat-role-active-header" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
							<div>
								<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
									<h3 id="wpat_active_role_title" style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;">
										<?php echo esc_html( $active_role['name'] ); ?>
									</h3>
									<code id="wpat_active_role_slug" style="font-size: 12px; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; color: #334155;"><?php echo esc_html( $active_role_slug ); ?></code>
									<span id="wpat_active_role_type_badge" class="wpat-role-type-badge" style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; background: #eff6ff; color: #1d4ed8;">
										<?php echo esc_html( $active_role['type_label'] ); ?>
									</span>
								</div>
								<p class="description" style="margin: 4px 0 0 0; color: #64748b; font-size: 12.5px;">
									<span id="wpat_active_role_user_count"><?php echo esc_html( $active_role['user_count'] ); ?></span> usuarios tienen asignado este rol actualmente.
								</p>
							</div>

							<div style="display: flex; align-items: center; gap: 8px;">
								<button type="button" class="button button-secondary" id="wpat_clone_active_role_btn" data-slug="<?php echo esc_attr( $active_role_slug ); ?>" data-name="<?php echo esc_attr( $active_role['name'] ); ?>" style="font-weight: 600; height: 32px; display: inline-flex; align-items: center; gap: 5px;">
									<span class="dashicons dashicons-admin-page" style="font-size: 15px; width: 15px; height: 15px; line-height: 1;"></span> Clonar Rol
								</button>
								<button type="button" class="button button-link-delete" id="wpat_delete_active_role_btn" data-slug="<?php echo esc_attr( $active_role_slug ); ?>" style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; font-weight: 600; height: 32px; padding: 0 10px; border-radius: 4px; display: <?php echo $active_role['is_core'] ? 'none' : 'inline-flex'; ?>; align-items: center; gap: 4px;">
									<span class="dashicons dashicons-trash" style="font-size: 15px; width: 15px; height: 15px; line-height: 1;"></span> Eliminar Rol
								</button>
							</div>
						</div>

						<!-- BARRA DE BÚSQUEDA Y FILTRADO DE PERMISOS -->
						<div class="wpat-cap-toolbar" style="display: flex; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 18px; flex-wrap: wrap; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px;">
							<div style="position: relative; flex: 1; max-width: 340px;">
								<span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 8px; color: #94a3b8; font-size: 18px; width: 18px; height: 18px;"></span>
								<input type="text" id="wpat_cap_search_input" placeholder="Filtrar permisos por nombre o clave..." style="width: 100%; height: 36px; padding: 0 12px 0 34px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" />
							</div>

							<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
								<span style="font-size: 12.5px; color: #475569; font-weight: 600; margin-right: 4px;">
									Permisos concedidos: <strong id="wpat_granted_caps_counter" style="color: #2563eb;">0</strong>
								</span>
								<button type="button" class="button button-secondary button-small" id="wpat_expand_all_cats_btn" style="height: 30px; font-weight: 600;" title="Desplegar todas las secciones">Desplegar Todo</button>
								<button type="button" class="button button-secondary button-small" id="wpat_collapse_all_cats_btn" style="height: 30px; font-weight: 600;" title="Plegar todas las secciones">Plegar Todo</button>
								<button type="button" class="button button-secondary button-small" id="wpat_check_all_caps_btn" style="height: 30px; font-weight: 600;">Conceder Todos</button>
								<button type="button" class="button button-secondary button-small" id="wpat_uncheck_all_caps_btn" style="height: 30px; font-weight: 600;">Revocar Todos</button>
							</div>
						</div>

						<!-- FORMULARIO DE CAPABILITIES POR CATEGORÍAS -->
						<div id="wpat_role_caps_form_container">
							<input type="hidden" id="wpat_current_editing_role" name="role_manager_active_role" value="<?php echo esc_attr( $active_role_slug ); ?>" />

							<div class="wpat-cap-categories-wrapper" style="display: flex; flex-direction: column; gap: 12px;">
								<?php foreach ( $categories as $cat_key => $cat ) : ?>
									<div class="wpat-cap-category-card" data-category="<?php echo esc_attr( $cat_key ); ?>" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
										<div class="wpat-cap-cat-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">
											<div style="display: flex; align-items: center; gap: 8px;">
												<span class="dashicons dashicons-arrow-down-alt2 wpat-cat-accordion-arrow" style="transition: transform 0.2s ease; transform: rotate(-90deg); color: #94a3b8; font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span>
												<span class="dashicons <?php echo esc_attr( $cat['icon'] ); ?>" style="color: #2563eb; font-size: 18px; width: 18px; height: 18px;"></span>
												<strong style="font-size: 13.5px; color: #1e293b;"><?php echo esc_html( $cat['title'] ); ?></strong>
												<span class="wpat-cat-count-badge" style="background: #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 10px;">
													<span class="granted-count">0</span> / <?php echo count( $cat['caps'] ); ?>
												</span>
											</div>
											<div style="display: flex; align-items: center; gap: 6px;">
												<button type="button" class="button button-small wpat-cat-check-all" data-cat="<?php echo esc_attr( $cat_key ); ?>" style="font-size: 11px; height: 24px; line-height: 22px;">Marcar bloque</button>
												<button type="button" class="button button-small wpat-cat-uncheck-all" data-cat="<?php echo esc_attr( $cat_key ); ?>" style="font-size: 11px; height: 24px; line-height: 22px;">Desmarcar</button>
											</div>
										</div>

										<div class="wpat-cap-cat-body" style="padding: 16px; display: none; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px;">
											<?php foreach ( $cat['caps'] as $cap_slug => $cap_label ) : ?>
												<?php
												$has_cap = ! empty( $active_role['capabilities'][ $cap_slug ] );
												$is_admin_crit = ( 'administrator' === $active_role_slug && in_array( $cap_slug, array( 'manage_options', 'edit_users', 'promote_users', 'activate_plugins', 'edit_plugins', 'edit_theme_options', 'read' ), true ) );
												?>
												<label class="wpat-cap-item <?php echo $has_cap ? 'active' : ''; ?>" data-cap="<?php echo esc_attr( $cap_slug ); ?>" data-search="<?php echo esc_attr( strtolower( $cap_label . ' ' . $cap_slug ) ); ?>" style="display: flex; align-items: flex-start; gap: 10px; background: <?php echo $has_cap ? '#f0fdf4' : '#ffffff'; ?>; border: 1px solid <?php echo $has_cap ? '#bbf7d0' : '#e2e8f0'; ?>; border-radius: 8px; padding: 10px 12px; cursor: <?php echo $is_admin_crit ? 'not-allowed' : 'pointer'; ?>; transition: all 0.15s ease;">
													<input type="checkbox" class="wpat-cap-checkbox" name="capabilities[<?php echo esc_attr( $cap_slug ); ?>]" value="1" <?php checked( $has_cap ); ?> <?php disabled( $is_admin_crit ); ?> style="margin-top: 2px;" />
													<div style="min-width: 0; flex: 1;">
														<div style="font-size: 12.5px; font-weight: 600; color: #1e293b; line-height: 1.3;">
															<?php echo esc_html( $cap_label ); ?>
															<?php if ( $is_admin_crit ) : ?>
																<span class="dashicons dashicons-lock" style="font-size: 13px; width: 13px; height: 13px; color: #94a3b8; vertical-align: middle;" title="Permiso protegido para Administrador"></span>
															<?php endif; ?>
														</div>
														<code style="font-size: 11px; color: #64748b; margin-top: 3px; display: inline-block; background: rgba(0,0,0,0.04); padding: 1px 4px; border-radius: 3px;"><?php echo esc_html( $cap_slug ); ?></code>
													</div>
												</label>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>

							<!-- BOTÓN GUARDAR STICKY INFERIOR -->
							<div class="wpat-role-save-bar" style="position: sticky; bottom: 20px; margin-top: 24px; background: #1e293b; color: #fff; padding: 14px 20px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; gap: 15px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); z-index: 10;">
								<div>
									<strong style="font-size: 13.5px; display: block;">Modificando permisos de: <span id="wpat_save_bar_role_name" style="color: #60a5fa;"><?php echo esc_html( $active_role['name'] ); ?></span></strong>
									<span style="font-size: 11.5px; color: #94a3b8;">Los cambios se aplicarán inmediatamente a todos los usuarios con este rol.</span>
								</div>
								<button type="button" class="button button-primary" id="wpat_save_role_caps_btn" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 36px; line-height: 34px; padding: 0 22px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-saved" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span> Guardar Permisos
								</button>
							</div>
						</div>
					</main>
				</div>
			</div>
		</div>

		<!-- MODAL: CREAR / CLONAR ROL -->
		<div id="wpat_create_role_modal" class="wpat-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 999999; align-items: center; justify-content: center;">
			<div class="wpat-modal-content" style="background: #fff; border-radius: 12px; width: 100%; max-width: 480px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); position: relative;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
					<h3 id="wpat_role_modal_title" style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;">Crear Nuevo Rol</h3>
					<button type="button" class="wpat-close-modal-btn" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8;">&times;</button>
				</div>

				<div id="wpat_create_role_form_container">
					<div class="wpat-field-group" style="margin-bottom: 14px;">
						<label for="wpat_new_role_name" style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 5px;">Nombre Visible del Rol *</label>
						<input type="text" id="wpat_new_role_name" name="role_name" placeholder="Ej: Gestor de Clientes" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" required />
					</div>

					<div class="wpat-field-group" style="margin-bottom: 14px;">
						<label for="wpat_new_role_slug" style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 5px;">Identificador del Rol (Slug / Opcional)</label>
						<input type="text" id="wpat_new_role_slug" name="role_slug" placeholder="Ej: gestor_clientes (auto-generado si vacío)" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
						<p class="description" style="margin-top: 4px; font-size: 11.5px;">Solo letras minúsculas, números y guiones bajos.</p>
					</div>

					<div class="wpat-field-group" style="margin-bottom: 22px;">
						<label for="wpat_new_role_clone_from" style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 5px;">Heredar / Clonar Permisos de:</label>
						<select id="wpat_new_role_clone_from" name="clone_from" style="width: 100%; height: 38px; border-radius: 6px; font-size: 13px;">
							<option value="">Desde cero (Solo permiso de lectura básico)</option>
							<?php foreach ( $roles as $slug => $r ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>">Clonar permisos de: <?php echo esc_html( $r['name'] ); ?> (<?php echo esc_html( $slug ); ?>)</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
						<button type="button" class="button button-secondary wpat-close-modal-btn" style="height: 36px; font-weight: 600;">Cancelar</button>
						<button type="button" class="button button-primary" id="wpat_submit_create_role_btn" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 36px; padding: 0 18px;">Crear Rol</button>
					</div>
				</div>
			</div>
		</div>
		<?php
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

			// 4. Rellenar Frase Clave si está vacía
			$keyword = get_post_meta( $id, '_wpat_seo_keyword', true );
			if ( empty( $keyword ) ) {
				$post_title = get_the_title( $id );
				if ( ! empty( $post_title ) ) {
					$stopwords = array( 'de', 'la', 'el', 'en', 'y', 'a', 'los', 'del', 'las', 'un', 'por', 'con', 'no', 'una', 'su', 'para', 'es', 'al', 'lo', 'como', 'más', 'o', 'pero', 'sus', 'le', 'ha', 'me', 'si', 'sin', 'sobre', 'este', 'ya', 'entre', 'cuando', 'todo', 'esta', 'ser', 'son', 'dos', 'también', 'fue', 'había', 'era', 'muy', 'hasta', 'desde', 'está', 'mi', 'porque', 'qué', 'solo', 'han', 'yo', 'hay', 'vez', 'puede', 'todos', 'así', 'nos', 'ni', 'parte', 'tiene', 'él', 'uno', 'donde', 'bien', 'guía', 'completa', 'cómo', 'paso' );
					$clean_pt  = mb_strtolower( preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $post_title ) );
					$words     = preg_split( '/\s+/', $clean_pt, -1, PREG_SPLIT_NO_EMPTY );
					$filtered  = array();
					foreach ( $words as $w ) {
						if ( mb_strlen( $w ) > 2 && ! in_array( $w, $stopwords, true ) ) {
							$filtered[] = $w;
						}
					}
					if ( ! empty( $filtered ) ) {
						$suggested_kw = implode( ' ', array_slice( $filtered, 0, 3 ) );
						update_post_meta( $id, '_wpat_seo_keyword', sanitize_text_field( $suggested_kw ) );
					}
				}
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
		$github_url  = 'https://github.com/19webs/wp-agency-toolkit';
		
		if ( class_exists( 'WPAT_Updater' ) ) {
			$updater = WPAT_Updater::get_instance();
			// Forzar llamada real en tiempo real a GitHub
			$release = $updater->get_latest_github_release( true );
			if ( $release && ! empty( $release['version'] ) ) {
				$new_version = $release['version'];
				$github_url  = isset( $release['html_url'] ) ? $release['html_url'] : $github_url;
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

	/**
	 * Renderiza la interfaz de administración del módulo de Banner de Cookies y RGPD.
	 *
	 * @param array $settings Ajustes del plugin.
	 */
	public function render_cookie_consent_content( $settings ) {
		$is_active          = isset( $settings['cookie-consent'] ) && '1' === (string) $settings['cookie-consent'];
		$gcm_enabled        = ! isset( $settings['cookie_consent_gcm'] ) || '1' === (string) $settings['cookie_consent_gcm'];
		$layout             = isset( $settings['cookie_consent_layout'] ) ? $settings['cookie_consent_layout'] : 'layout-bar';
		$revoke_badge       = ! isset( $settings['cookie_consent_revoke_badge'] ) || '1' === (string) $settings['cookie_consent_revoke_badge'];
		$badge_pos          = isset( $settings['cookie_consent_revoke_badge_pos'] ) && 'bottom-right' === $settings['cookie_consent_revoke_badge_pos'] ? 'bottom-right' : 'bottom-left';
		$policy_version     = isset( $settings['cookie_consent_version'] ) && ! empty( $settings['cookie_consent_version'] ) ? $settings['cookie_consent_version'] : '1.0';

		$cookie_policy_url  = isset( $settings['cookie_consent_cookie_policy_url'] ) && ! empty( $settings['cookie_consent_cookie_policy_url'] ) ? $settings['cookie_consent_cookie_policy_url'] : ( isset( $settings['cookie_consent_policy_url'] ) ? $settings['cookie_consent_policy_url'] : '' );
		$privacy_policy_url = isset( $settings['cookie_consent_privacy_policy_url'] ) && ! empty( $settings['cookie_consent_privacy_policy_url'] ) ? $settings['cookie_consent_privacy_policy_url'] : get_privacy_policy_url();
		$legal_notice_url   = isset( $settings['cookie_consent_legal_notice_url'] ) ? $settings['cookie_consent_legal_notice_url'] : '';

		$title          = isset( $settings['cookie_consent_title'] ) && ! empty( $settings['cookie_consent_title'] ) ? $settings['cookie_consent_title'] : 'Gestionar Consentimiento de Cookies';
		$text           = isset( $settings['cookie_consent_text'] ) && ! empty( $settings['cookie_consent_text'] ) ? $settings['cookie_consent_text'] : 'Utilizamos cookies propias y de terceros para fines analíticos y para mostrarle publicidad personalizada según su navegación. Puede aceptar todas las cookies, rechazarlas o configurar sus preferencias.';
		$btn_accept     = isset( $settings['cookie_consent_btn_accept'] ) && ! empty( $settings['cookie_consent_btn_accept'] ) ? $settings['cookie_consent_btn_accept'] : 'Aceptar Todas';
		$btn_reject     = isset( $settings['cookie_consent_btn_reject'] ) && ! empty( $settings['cookie_consent_btn_reject'] ) ? $settings['cookie_consent_btn_reject'] : 'Rechazar Todas';
		$btn_settings   = isset( $settings['cookie_consent_btn_settings'] ) && ! empty( $settings['cookie_consent_btn_settings'] ) ? $settings['cookie_consent_btn_settings'] : 'Configurar Preferencias';
		$bg_color       = isset( $settings['cookie_consent_bg_color'] ) && ! empty( $settings['cookie_consent_bg_color'] ) ? $settings['cookie_consent_bg_color'] : '#1e293b';
		$text_color     = isset( $settings['cookie_consent_text_color'] ) && ! empty( $settings['cookie_consent_text_color'] ) ? $settings['cookie_consent_text_color'] : '#f8fafc';
		$btn_accept_bg  = isset( $settings['cookie_consent_btn_accept_bg'] ) && ! empty( $settings['cookie_consent_btn_accept_bg'] ) ? $settings['cookie_consent_btn_accept_bg'] : '#2563eb';
		$btn_accept_txt = isset( $settings['cookie_consent_btn_accept_text'] ) && ! empty( $settings['cookie_consent_btn_accept_text'] ) ? $settings['cookie_consent_btn_accept_text'] : '#ffffff';
		$btn_reject_bg  = isset( $settings['cookie_consent_btn_reject_bg'] ) && ! empty( $settings['cookie_consent_btn_reject_bg'] ) ? $settings['cookie_consent_btn_reject_bg'] : '#475569';
		$btn_reject_txt = isset( $settings['cookie_consent_btn_reject_text'] ) && ! empty( $settings['cookie_consent_btn_reject_text'] ) ? $settings['cookie_consent_btn_reject_text'] : '#ffffff';

		if ( ! class_exists( 'WPAT_Cookie_Consent' ) ) {
			require_once WPAT_PATH . 'includes/modules/class-wpat-cookie-consent.php';
		}
		$known_cookies = WPAT_Cookie_Consent::get_known_cookies_database();

		// Obtener lista de páginas para datalist de selección rápida
		$wp_pages = get_pages( array( 'post_status' => 'publish' ) );
		$active_subtab = isset( $_GET['subtab'] ) && in_array( $_GET['subtab'], array( 'general', 'texts', 'design', 'scanner' ), true ) ? sanitize_key( $_GET['subtab'] ) : 'general';
		?>
		<div class="wpat-module-card wpat-cookie-consent-admin-wrap" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
			<input type="hidden" name="wpat_active_subtab" id="wpat_cookie_active_subtab" value="<?php echo esc_attr( $active_subtab ); ?>" />

			<!-- CABECERA DEL MÓDULO -->
			<div class="wpat-module-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 22px;">
				<div class="wpat-module-info">
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
						<span style="font-size: 26px; line-height: 1;">🍪</span>
						<h3 style="margin: 0; font-size: 19px; font-weight: 700; color: #1e293b;">Banner de Cookies, RGPD & Google Consent Mode v2</h3>
						<span class="wpat-badge wpat-badge-new" style="background: #10b981; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; text-transform: uppercase;">Nuevo</span>
					</div>
					<p style="margin: 0; font-size: 13.5px; color: #64748b; line-height: 1.5;">
						Cumplimiento estricto del RGPD y de la AEPD europea con bloqueo previo de scripts, soporte para <strong>Google Consent Mode v2</strong>, modal de categorías, enlaces legales y escáner automático de cookies.
					</p>
				</div>
				<div>
					<?php $this->render_module_toggle( 'cookie-consent', $settings, true ); ?>
				</div>
			</div>

			<!-- SUB-PESTAÑAS -->
			<div class="wpat-cookie-subtabs" style="display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 22px; flex-wrap: wrap;">
				<button type="button" class="wpat-cookie-tab-btn <?php echo 'general' === $active_subtab ? 'active' : ''; ?>" data-tab="general" style="background: none; border: none; padding: 10px 18px; font-weight: <?php echo 'general' === $active_subtab ? '700' : '600'; ?>; font-size: 13.5px; color: <?php echo 'general' === $active_subtab ? '#2563eb' : '#64748b'; ?>; border-bottom: 2px solid <?php echo 'general' === $active_subtab ? '#2563eb' : 'transparent'; ?>; margin-bottom: -2px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
					<span class="dashicons dashicons-admin-generic"></span> General & GCM v2
				</button>
				<button type="button" class="wpat-cookie-tab-btn <?php echo 'texts' === $active_subtab ? 'active' : ''; ?>" data-tab="texts" style="background: none; border: none; padding: 10px 18px; font-weight: <?php echo 'texts' === $active_subtab ? '700' : '600'; ?>; font-size: 13.5px; color: <?php echo 'texts' === $active_subtab ? '#2563eb' : '#64748b'; ?>; border-bottom: 2px solid <?php echo 'texts' === $active_subtab ? '#2563eb' : 'transparent'; ?>; margin-bottom: -2px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
					<span class="dashicons dashicons-editor-textcolor"></span> Textos y Botones
				</button>
				<button type="button" class="wpat-cookie-tab-btn <?php echo 'design' === $active_subtab ? 'active' : ''; ?>" data-tab="design" style="background: none; border: none; padding: 10px 18px; font-weight: <?php echo 'design' === $active_subtab ? '700' : '600'; ?>; font-size: 13.5px; color: <?php echo 'design' === $active_subtab ? '#2563eb' : '#64748b'; ?>; border-bottom: 2px solid <?php echo 'design' === $active_subtab ? '#2563eb' : 'transparent'; ?>; margin-bottom: -2px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
					<span class="dashicons dashicons-art"></span> Diseño y Colores
				</button>
				<button type="button" class="wpat-cookie-tab-btn <?php echo 'scanner' === $active_subtab ? 'active' : ''; ?>" data-tab="scanner" style="background: none; border: none; padding: 10px 18px; font-weight: <?php echo 'scanner' === $active_subtab ? '700' : '600'; ?>; font-size: 13.5px; color: <?php echo 'scanner' === $active_subtab ? '#2563eb' : '#64748b'; ?>; border-bottom: 2px solid <?php echo 'scanner' === $active_subtab ? '#2563eb' : 'transparent'; ?>; margin-bottom: -2px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
					<span class="dashicons dashicons-search"></span> Escáner & Shortcode
				</button>
			</div>

			<!-- DATALIST DE PÁGINAS DE WORDPRESS -->
			<datalist id="wpat_pages_datalist">
				<?php if ( ! empty( $wp_pages ) ) : ?>
					<?php foreach ( $wp_pages as $p ) : ?>
						<option value="<?php echo esc_url( get_permalink( $p->ID ) ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</datalist>

			<!-- CONTENIDO SUB-PESTAÑAS -->
			<!-- TAB 1: GENERAL & GCM V2 & ENLACES LEGALES -->
			<div class="wpat-cookie-tab-panel <?php echo 'general' === $active_subtab ? 'active' : ''; ?>" id="wpat_cookie_tab_general" style="<?php echo 'general' === $active_subtab ? 'display:block;' : 'display:none;'; ?>">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
					<!-- Configuración Google Consent Mode v2 -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
						<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
							<div style="display: flex; align-items: center; gap: 8px;">
								<span style="font-size: 20px;">🛡️</span>
								<h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Google Consent Mode v2</h4>
							</div>
							<label class="wpat-switch" style="margin: 0;">
								<input type="hidden" name="wpat_settings[cookie_consent_gcm]" value="0" />
								<input type="checkbox" name="wpat_settings[cookie_consent_gcm]" value="1" <?php checked( $gcm_enabled ); ?> />
								<span class="wpat-slider"></span>
							</label>
						</div>
						<p style="font-size: 12.5px; color: #64748b; line-height: 1.5; margin: 0 0 12px 0;">
							Inyecta la inicialización predeterminada de <code>gtag('consent', 'default', {...})</code> denegando almacenamiento de analítica y publicidad hasta que el visitante otorgue su consentimiento explícito. Obligatorio por Google para medir conversiones en la UE.
						</p>
						<div style="background: #eff6ff; border-left: 3px solid #3b82f6; padding: 10px 12px; border-radius: 4px; font-size: 12px; color: #1e40af;">
							<strong>Compatible con:</strong> Google Analytics 4 (GA4), Google Tag Manager (GTM) y Google Ads.
						</div>
					</div>

					<!-- Disposición y Formato del Banner -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
						<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
							<span style="font-size: 20px;">📐</span>
							<h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Disposición Visual</h4>
						</div>
						<div class="wpat-field-group" style="margin-bottom: 16px;">
							<label for="wpat_cookie_consent_layout" style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Posición y Estilo del Banner</label>
							<select name="wpat_settings[cookie_consent_layout]" id="wpat_cookie_consent_layout" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600;">
								<option value="layout-bar" <?php selected( $layout, 'layout-bar' ); ?>>Barra Inferior Fija (Ancho Completo)</option>
								<option value="layout-floating" <?php selected( $layout, 'layout-floating' ); ?>>Ventana Flotante en la Esquina Inferior</option>
								<option value="layout-modal" <?php selected( $layout, 'layout-modal' ); ?>>Modal Centrado con Fondo Bloqueante (Backdrop)</option>
							</select>
						</div>

						<div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #e2e8f0; padding-top: 12px; margin-bottom: 12px;">
							<div>
								<div style="font-size: 13px; font-weight: 600; color: #1e293b;">Icono Flotante de Revocación</div>
								<div style="font-size: 12px; color: #64748b;">Botón permanente para reabrir preferencias (Exigido por AEPD)</div>
							</div>
							<label class="wpat-switch" style="margin: 0;">
								<input type="hidden" name="wpat_settings[cookie_consent_revoke_badge]" value="0" />
								<input type="checkbox" name="wpat_settings[cookie_consent_revoke_badge]" value="1" <?php checked( $revoke_badge ); ?> />
								<span class="wpat-slider"></span>
							</label>
						</div>

						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_revoke_badge_pos" style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">Posición del Icono de Revocación</label>
							<select name="wpat_settings[cookie_consent_revoke_badge_pos]" id="wpat_cookie_consent_revoke_badge_pos" style="width: 100%; height: 34px; border-radius: 6px; border: 1px solid #cbd5e1;">
								<option value="bottom-left" <?php selected( $badge_pos, 'bottom-left' ); ?>>Esquina Inferior Izquierda (Recomendado)</option>
								<option value="bottom-right" <?php selected( $badge_pos, 'bottom-right' ); ?>>Esquina Inferior Derecha</option>
							</select>
						</div>
					</div>
				</div>

				<!-- ENLACES LEGALES (COOKIES, PRIVACIDAD Y AVISO LEGAL) -->
				<div style="margin-top: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
					<h4 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 700; color: #1e293b;">⚖️ Enlaces Legales en el Banner y Modal</h4>
					<p style="margin: 0 0 16px 0; font-size: 12.5px; color: #64748b;">
						Configura los enlaces a tus textos legales obligatorios. Se mostrarán de forma clara en el banner y en el modal de preferencias de cookies:
					</p>

					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
						<!-- 1. Política de Cookies -->
						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_cookie_policy_url" style="font-size: 12.5px; font-weight: 700; color: #1e293b; display: block; margin-bottom: 4px;">
								1. Política de Cookies <span style="color: #ef4444;">*</span>
							</label>
							<input type="url" name="wpat_settings[cookie_consent_cookie_policy_url]" id="wpat_cookie_consent_cookie_policy_url" list="wpat_pages_datalist" value="<?php echo esc_url( $cookie_policy_url ); ?>" placeholder="https://tudominio.com/politica-de-cookies/" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1;" />
							<p class="description" style="font-size: 11px; color: #64748b; margin-top: 4px;">Imprescindible en el aviso de cookies según la AEPD.</p>
						</div>

						<!-- 2. Política de Privacidad -->
						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_privacy_policy_url" style="font-size: 12.5px; font-weight: 700; color: #1e293b; display: block; margin-bottom: 4px;">
								2. Política de Privacidad
							</label>
							<div style="display: flex; gap: 6px;">
								<input type="url" name="wpat_settings[cookie_consent_privacy_policy_url]" id="wpat_cookie_consent_privacy_policy_url" list="wpat_pages_datalist" value="<?php echo esc_url( $privacy_policy_url ); ?>" placeholder="https://tudominio.com/politica-de-privacidad/" class="regular-text" style="flex: 1; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1;" />
								<?php if ( function_exists( 'get_privacy_policy_url' ) && get_privacy_policy_url() ) : ?>
									<button type="button" class="button button-secondary" id="wpat_set_wp_privacy_url_btn" data-url="<?php echo esc_url( get_privacy_policy_url() ); ?>" style="height: 36px; font-size: 11px; padding: 0 8px;" title="Cargar página de privacidad de WordPress">
										WP
									</button>
								<?php endif; ?>
							</div>
							<p class="description" style="font-size: 11px; color: #64748b; margin-top: 4px;">Requerida para el tratamiento de datos personales (RGPD).</p>
						</div>

						<!-- 3. Aviso Legal -->
						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_legal_notice_url" style="font-size: 12.5px; font-weight: 700; color: #1e293b; display: block; margin-bottom: 4px;">
								3. Aviso Legal
							</label>
							<input type="url" name="wpat_settings[cookie_consent_legal_notice_url]" id="wpat_cookie_consent_legal_notice_url" list="wpat_pages_datalist" value="<?php echo esc_url( $legal_notice_url ); ?>" placeholder="https://tudominio.com/aviso-legal/" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1;" />
							<p class="description" style="font-size: 11px; color: #64748b; margin-top: 4px;">Obligatorio en España por la LSSI para webs profesionales o comerciales.</p>
						</div>
					</div>
				</div>

				<!-- VERSIÓN DE POLÍTICA Y AUDITORÍA RGPD -->
				<div style="margin-top: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
					<div>
						<div style="font-size: 13.5px; font-weight: 700; color: #1e293b; margin-bottom: 2px;">
							🔄 Versión de la Política de Cookies
						</div>
						<div style="font-size: 12px; color: #64748b;">
							Si realizas cambios legales sustanciales o añades nuevos píxeles, incrementa la versión para solicitar de nuevo el consentimiento a todos los visitantes.
						</div>
					</div>
					<div style="display: flex; align-items: center; gap: 10px;">
						<input type="text" name="wpat_settings[cookie_consent_version]" id="wpat_cookie_consent_version" value="<?php echo esc_attr( $policy_version ); ?>" style="width: 70px; height: 34px; text-align: center; font-weight: 700; border-radius: 6px; border: 1px solid #cbd5e1;" />
						<button type="button" class="button button-secondary" id="wpat_bump_consent_version_btn" style="height: 34px; font-weight: 600;">
							+ Forzar Re-consentimiento
						</button>
					</div>
				</div>
			</div>

			<!-- TAB 2: TEXTOS Y BOTONES -->
			<div class="wpat-cookie-tab-panel <?php echo 'texts' === $active_subtab ? 'active' : ''; ?>" id="wpat_cookie_tab_texts" style="<?php echo 'texts' === $active_subtab ? 'display:block;' : 'display:none;'; ?>">
				<div style="display: grid; grid-template-columns: 1fr; gap: 18px;">
					<div class="wpat-field-group">
						<label for="wpat_cookie_consent_title" style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Título del Banner</label>
						<input type="text" name="wpat_settings[cookie_consent_title]" id="wpat_cookie_consent_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600;" />
					</div>

					<div class="wpat-field-group">
						<label for="wpat_cookie_consent_text" style="font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Texto Explicativo del Consentimiento</label>
						<textarea name="wpat_settings[cookie_consent_text]" id="wpat_cookie_consent_text" rows="4" style="width: 100%; border-radius: 6px; border: 1px solid #cbd5e1; padding: 10px; font-size: 13px;"><?php echo esc_textarea( $text ); ?></textarea>
						<p class="description" style="font-size: 12px; color: #64748b; margin-top: 4px;">Explica de forma clara y accesible el uso de cookies según las directrices de la AEPD y el RGPD.</p>
					</div>

					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_btn_accept" style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Botón Aceptar Todas</label>
							<input type="text" name="wpat_settings[cookie_consent_btn_accept]" id="wpat_cookie_consent_btn_accept" value="<?php echo esc_attr( $btn_accept ); ?>" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1;" />
						</div>

						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_btn_reject" style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Botón Rechazar Todas</label>
							<input type="text" name="wpat_settings[cookie_consent_btn_reject]" id="wpat_cookie_consent_btn_reject" value="<?php echo esc_attr( $btn_reject ); ?>" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1;" />
						</div>

						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_btn_settings" style="font-size: 12.5px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px;">Botón Configurar Preferencias</label>
							<input type="text" name="wpat_settings[cookie_consent_btn_settings]" id="wpat_cookie_consent_btn_settings" value="<?php echo esc_attr( $btn_settings ); ?>" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px; border: 1px solid #cbd5e1;" />
						</div>
					</div>
				</div>
			</div>

			<!-- TAB 3: DISEÑO Y COLORES -->
			<div class="wpat-cookie-tab-panel <?php echo 'design' === $active_subtab ? 'active' : ''; ?>" id="wpat_cookie_tab_design" style="<?php echo 'design' === $active_subtab ? 'display:block;' : 'display:none;'; ?>">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
					<!-- Colores de Fondo y Texto -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 700; color: #1e293b;">🎨 Banner & Modal</h4>
						<div class="wpat-field-group" style="margin-bottom: 14px;">
							<label for="wpat_cookie_consent_bg_color" style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 6px;">Color de Fondo del Banner</label>
							<input type="text" name="wpat_settings[cookie_consent_bg_color]" id="wpat_cookie_consent_bg_color" value="<?php echo esc_attr( $bg_color ); ?>" class="wpat-color-picker" />
						</div>
						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_text_color" style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 6px;">Color de Texto y Enlaces</label>
							<input type="text" name="wpat_settings[cookie_consent_text_color]" id="wpat_cookie_consent_text_color" value="<?php echo esc_attr( $text_color ); ?>" class="wpat-color-picker" />
						</div>
					</div>

					<!-- Botón Aceptar -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 700; color: #1e293b;">✅ Botón Aceptar</h4>
						<div class="wpat-field-group" style="margin-bottom: 14px;">
							<label for="wpat_cookie_consent_btn_accept_bg" style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 6px;">Color de Fondo</label>
							<input type="text" name="wpat_settings[cookie_consent_btn_accept_bg]" id="wpat_cookie_consent_btn_accept_bg" value="<?php echo esc_attr( $btn_accept_bg ); ?>" class="wpat-color-picker" />
						</div>
						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_btn_accept_text" style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 6px;">Color de Texto</label>
							<input type="text" name="wpat_settings[cookie_consent_btn_accept_text]" id="wpat_cookie_consent_btn_accept_text" value="<?php echo esc_attr( $btn_accept_txt ); ?>" class="wpat-color-picker" />
						</div>
					</div>

					<!-- Botón Rechazar -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 700; color: #1e293b;">⛔ Botón Rechazar</h4>
						<div class="wpat-field-group" style="margin-bottom: 14px;">
							<label for="wpat_cookie_consent_btn_reject_bg" style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 6px;">Color de Fondo</label>
							<input type="text" name="wpat_settings[cookie_consent_btn_reject_bg]" id="wpat_cookie_consent_btn_reject_bg" value="<?php echo esc_attr( $btn_reject_bg ); ?>" class="wpat-color-picker" />
						</div>
						<div class="wpat-field-group">
							<label for="wpat_cookie_consent_btn_reject_text" style="font-size: 12.5px; font-weight: 600; display: block; margin-bottom: 6px;">Color de Texto</label>
							<input type="text" name="wpat_settings[cookie_consent_btn_reject_text]" id="wpat_cookie_consent_btn_reject_text" value="<?php echo esc_attr( $btn_reject_txt ); ?>" class="wpat-color-picker" />
						</div>
					</div>
				</div>
			</div>

			<!-- TAB 4: ESCÁNER INTELIGENTE & SHORTCODE -->
			<div class="wpat-cookie-tab-panel <?php echo 'scanner' === $active_subtab ? 'active' : ''; ?>" id="wpat_cookie_tab_scanner" style="<?php echo 'scanner' === $active_subtab ? 'display:block;' : 'display:none;'; ?>">
				<!-- Tarjeta del Escáner -->
				<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 22px;">
					<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
						<div>
							<h4 style="margin: 0 0 4px 0; font-size: 16px; font-weight: 700; color: #1e293b;">⚡ Escáner Inteligente de Cookies y Scripts</h4>
							<p style="margin: 0; font-size: 13px; color: #64748b;">
								Analiza en tiempo real los plugins activos, pasarelas de pago, píxeles de seguimiento y cabeceras HTTP para listar las cookies utilizadas en tu web.
							</p>
						</div>
						<div>
							<button type="button" class="button button-primary" id="wpat_scan_cookies_btn" style="height: 38px; line-height: 36px; padding: 0 18px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; background: #2563eb; border-color: #1d4ed8;">
								<span class="dashicons dashicons-search" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span> Escanear Cookies Ahora
							</button>
						</div>
					</div>

					<div id="wpat_scanner_status_area" style="display: none; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 15px;"></div>

					<!-- Contenedor de Resultados del Escaneo -->
					<div id="wpat_scan_results_container">
						<div class="wpat-cookie-table-wrapper" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
							<table class="wpat-cookie-table widefat" style="border: none; margin: 0;">
								<thead>
									<tr style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
										<th style="font-weight: 700; color: #475569; padding: 10px 14px;">Cookie / Script</th>
										<th style="font-weight: 700; color: #475569; padding: 10px 14px;">Proveedor</th>
										<th style="font-weight: 700; color: #475569; padding: 10px 14px;">Categoría</th>
										<th style="font-weight: 700; color: #475569; padding: 10px 14px;">Finalidad Legal</th>
										<th style="font-weight: 700; color: #475569; padding: 10px 14px;">Caducidad</th>
									</tr>
								</thead>
								<tbody id="wpat_scan_tbody">
									<?php
									$preview_cookies = array_slice( $known_cookies, 0, 8, true );
									foreach ( $preview_cookies as $c ) :
										$badge_bg = '#e2e8f0';
										$badge_tx = '#475569';
										if ( 'necessary' === $c['category'] ) {
											$badge_bg = '#eff6ff';
											$badge_tx = '#1d4ed8';
										} elseif ( 'analytics' === $c['category'] ) {
											$badge_bg = '#ecfdf5';
											$badge_tx = '#047857';
										} elseif ( 'marketing' === $c['category'] ) {
											$badge_bg = '#fef2f2';
											$badge_tx = '#b91c1c';
										}
										?>
										<tr style="border-bottom: 1px solid #f1f5f9;">
											<td style="padding: 10px 14px; font-weight: 700; color: #1e293b;"><code><?php echo esc_html( $c['name'] ); ?></code></td>
											<td style="padding: 10px 14px; color: #475569;"><?php echo esc_html( $c['provider'] ); ?></td>
											<td style="padding: 10px 14px;">
												<span style="background: <?php echo esc_attr( $badge_bg ); ?>; color: <?php echo esc_attr( $badge_tx ); ?>; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
													<?php echo esc_html( $c['cat_label'] ); ?>
												</span>
											</td>
											<td style="padding: 10px 14px; color: #64748b; font-size: 12.5px;"><?php echo esc_html( $c['purpose'] ); ?></td>
											<td style="padding: 10px 14px; color: #475569; font-weight: 600; font-size: 12px;"><?php echo esc_html( $c['expiry'] ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Shortcode para la Política de Cookies -->
				<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
					<div>
						<div style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 14px; color: #1e40af; margin-bottom: 4px;">
							<span class="dashicons dashicons-shortcode"></span> Shortcode para la Página de Política de Cookies
						</div>
						<div style="font-size: 12.5px; color: #3b82f6;">
							Inserta este shortcode en tu página legal para mostrar una tabla siempre actualizada con las cookies activas en tu sitio.
						</div>
					</div>
					<div style="display: flex; align-items: center; gap: 8px;">
						<code style="background: #fff; border: 1px solid #93c5fd; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 700; color: #1e40af;">[wpat_cookie_table]</code>
						<button type="button" class="button button-secondary" id="wpat_copy_shortcode_btn" style="height: 34px; font-weight: 600;">
							Copiar Shortcode
						</button>
					</div>
				</div>

				<!-- Guía de Bloqueo Previo de Scripts para Desarrolladores -->
				<div style="margin-top: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px;">
					<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700; color: #1e293b;">🛠️ Guía: Cómo bloquear scripts de terceros antes de consentimiento</h4>
					<p style="font-size: 12.5px; color: #64748b; line-height: 1.5; margin: 0 0 10px 0;">
						Para bloquear la ejecución automática de scripts de terceros (ej: Hotjar, Criteo, scripts de afiliados) hasta que el usuario acepte la categoría correspondiente, añade el atributo <code>data-wpat-cookie-category</code> y cambia el tipo a <code>text/plain</code>:
					</p>
					<pre style="background: #0f172a; color: #38bdf8; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; margin: 0;"><code>&lt;!-- Script analítico que esperará a que se acepten cookies analíticas --&gt;
&lt;script type="text/plain" data-wpat-cookie-category="analytics"&gt;
    // Código de seguimiento que sólo se ejecutará tras el consentimiento
&lt;/script&gt;

&lt;!-- Script publicitario / marketing --&gt;
&lt;script type="text/plain" data-wpat-cookie-category="marketing"&gt;
    // Píxel publicitario
&lt;/script&gt;</code></pre>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Sincroniza el importe mínimo configurado en WPAT con las zonas de envío de WooCommerce.
	 */
	public static function sync_wpat_min_amount_to_woocommerce( $min_amount ) {
		$min_amount = max( 0, floatval( $min_amount ) );
		if ( $min_amount <= 0 ) {
			return;
		}

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return;
		}

		$zones     = WC_Shipping_Zones::get_zones();
		$rest_zone = new WC_Shipping_Zone( 0 );
		$zones[]   = array( 'shipping_methods' => $rest_zone->get_shipping_methods() );

		foreach ( $zones as $zone_data ) {
			$methods = isset( $zone_data['shipping_methods'] ) ? $zone_data['shipping_methods'] : array();
			foreach ( $methods as $method ) {
				if ( isset( $method->id ) && 'free_shipping' === $method->id && isset( $method->instance_id ) ) {
					$option_key = 'woocommerce_free_shipping_' . $method->instance_id . '_settings';
					$opts       = get_option( $option_key, array() );
					if ( is_array( $opts ) ) {
						$opts['min_amount'] = $min_amount;
						if ( empty( $opts['requires'] ) || 'coupon' === $opts['requires'] ) {
							$opts['requires'] = 'min_amount';
						}
						update_option( $option_key, $opts );
					}
				}
			}
		}
	}

	/**
	 * Renderiza la interfaz y panel de configuración de Venta Directa & Pagos Rápidos (Quick Pay).
	 *
	 * @param array $settings Ajustes actuales.
	 */
	public function render_quick_pay_content( $settings ) {
		if ( ! class_exists( 'WPAT_Quick_Pay' ) ) {
			require_once WPAT_PATH . 'includes/modules/class-wpat-quick-pay.php';
		}

		$products = WPAT_Quick_Pay::get_products();
		$coupons  = WPAT_Quick_Pay::get_coupons();
		$orders   = WPAT_Quick_Pay::get_all_orders( 150 );

		$active_subtab = isset( $_GET['subtab'] ) ? sanitize_key( $_GET['subtab'] ) : 'products';
		if ( ! in_array( $active_subtab, array( 'products', 'gateways', 'coupons', 'orders', 'settings' ), true ) ) {
			$active_subtab = 'products';
		}

		$total_sales = 0.0;
		$count_completed = 0;
		$count_pending = 0;
		foreach ( $orders as $o ) {
			if ( 'completed' === $o->status ) {
				$total_sales += floatval( $o->amount );
				$count_completed++;
			} elseif ( 'pending' === $o->status ) {
				$count_pending++;
			}
		}

		$curr_sym = isset( $settings['qp_currency_symbol'] ) ? $settings['qp_currency_symbol'] : '€';
		$admin_nonce = wp_create_nonce( 'wpat_quick_pay_admin_nonce' );
		?>
		<input type="hidden" id="wpat_qp_active_subtab" name="wpat_active_subtab" value="<?php echo esc_attr( $active_subtab ); ?>" />
		<input type="hidden" id="wpat_qp_admin_nonce_field" value="<?php echo esc_attr( $admin_nonce ); ?>" />

		<div class="wpat-module-card wpat-quick-pay-wrapper">
			<div class="wpat-module-header">
				<div class="wpat-module-info">
					<h3>Venta Directa & Pagos Rápidos <span class="wpat-badge" style="background:#059669; color:#fff;">Sin WooCommerce</span></h3>
					<p>Vende servicios, productos digitales, físicos y suscripciones en 1 clic con Stripe, Redsys TPV, Bizum, PayPal y facturación PDF automática.</p>
				</div>
				<?php $this->render_module_toggle( 'quick-pay', $settings, true ); ?>
			</div>

			<div class="wpat-module-body" style="display: block; padding: 22px;">

				<!-- SUB-PESTAÑAS DE NAVEGACIÓN -->
				<div class="wpat-cookie-tabs-nav" style="display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; flex-wrap: wrap;">
					<button type="button" class="wpat-qp-tab-btn <?php echo ( 'products' === $active_subtab ) ? 'active' : ''; ?>" data-tab="products" style="background: none; border: none; border-bottom: 3px solid <?php echo ( 'products' === $active_subtab ) ? '#2563eb' : 'transparent'; ?>; color: <?php echo ( 'products' === $active_subtab ) ? '#2563eb' : '#64748b'; ?>; padding: 10px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
						📦 Productos & Enlaces
					</button>
					<button type="button" class="wpat-qp-tab-btn <?php echo ( 'gateways' === $active_subtab ) ? 'active' : ''; ?>" data-tab="gateways" style="background: none; border: none; border-bottom: 3px solid <?php echo ( 'gateways' === $active_subtab ) ? '#2563eb' : 'transparent'; ?>; color: <?php echo ( 'gateways' === $active_subtab ) ? '#2563eb' : '#64748b'; ?>; padding: 10px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
						💳 Pasarelas de Pago
					</button>
					<button type="button" class="wpat-qp-tab-btn <?php echo ( 'coupons' === $active_subtab ) ? 'active' : ''; ?>" data-tab="coupons" style="background: none; border: none; border-bottom: 3px solid <?php echo ( 'coupons' === $active_subtab ) ? '#2563eb' : 'transparent'; ?>; color: <?php echo ( 'coupons' === $active_subtab ) ? '#2563eb' : '#64748b'; ?>; padding: 10px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
						🎟️ Cupones de Descuento
					</button>
					<button type="button" class="wpat-qp-tab-btn <?php echo ( 'orders' === $active_subtab ) ? 'active' : ''; ?>" data-tab="orders" style="background: none; border: none; border-bottom: 3px solid <?php echo ( 'orders' === $active_subtab ) ? '#2563eb' : 'transparent'; ?>; color: <?php echo ( 'orders' === $active_subtab ) ? '#2563eb' : '#64748b'; ?>; padding: 10px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
						📋 Ventas & Pedidos (<?php echo count( $orders ); ?>)
					</button>
					<button type="button" class="wpat-qp-tab-btn <?php echo ( 'settings' === $active_subtab ) ? 'active' : ''; ?>" data-tab="settings" style="background: none; border: none; border-bottom: 3px solid <?php echo ( 'settings' === $active_subtab ) ? '#2563eb' : 'transparent'; ?>; color: <?php echo ( 'settings' === $active_subtab ) ? '#2563eb' : '#64748b'; ?>; padding: 10px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
						🧾 Facturación PDF & Ajustes
					</button>
				</div>

				<!-- ========================================== -->
				<!-- PESTAÑA 1: PRODUCTOS & ENLACES            -->
				<!-- ========================================== -->
				<div id="wpat_qp_tab_products" class="wpat-qp-tab-panel" style="<?php echo ( 'products' === $active_subtab ) ? 'display:block;' : 'display:none;'; ?>">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
						<div>
							<h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">Catálogo de Productos y Servicios</h4>
							<p style="margin: 3px 0 0 0; color: #64748b; font-size: 12.5px;">Crea productos para vender con shortcode o botón modal en cualquier página o entrada.</p>
						</div>
						<button type="button" class="button button-primary" id="wpat_qp_open_add_product_btn" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 34px; display: inline-flex; align-items: center; gap: 6px;">
							<span class="dashicons dashicons-plus-alt2" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span> Añadir Nuevo Producto
						</button>
					</div>

					<div class="wpat-qp-products-table-wrap" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
						<table class="wp-list-table widefat fixed striped" style="border: none;">
							<thead>
								<tr>
									<th style="width: 28%; padding: 12px 14px; font-weight: 700;">Producto / Servicio</th>
									<th style="width: 14%; padding: 12px 14px; font-weight: 700;">Tipo</th>
									<th style="width: 12%; padding: 12px 14px; font-weight: 700;">Precio</th>
									<th style="width: 10%; padding: 12px 14px; font-weight: 700;">IVA</th>
									<th style="width: 22%; padding: 12px 14px; font-weight: 700;">Shortcode de Compra</th>
									<th style="width: 14%; text-align: right; padding: 12px 14px; font-weight: 700;">Acciones</th>
								</tr>
							</thead>
							<tbody id="wpat_qp_products_tbody">
								<?php if ( ! empty( $products ) ) : ?>
									<?php foreach ( $products as $p_id => $p ) : ?>
										<?php
										$type_label = ( 'digital' === $p['type'] ) ? '📥 Descarga' : ( ( 'physical' === $p['type'] ) ? '📦 Físico' : '🎓 Servicio' );
										$type_color = ( 'digital' === $p['type'] ) ? '#059669' : ( ( 'physical' === $p['type'] ) ? '#d97706' : '#2563eb' );
										$type_bg    = ( 'digital' === $p['type'] ) ? '#ecfdf5' : ( ( 'physical' === $p['type'] ) ? '#fffbeb' : '#eff6ff' );
										?>
										<tr id="wpat_qp_row_prod_<?php echo esc_attr( $p_id ); ?>">
											<td style="padding: 12px 14px;">
												<strong style="font-size: 13.5px; color: #0f172a; display: block;"><?php echo esc_html( $p['name'] ); ?></strong>
												<code style="font-size: 11px; color: #64748b;"><?php echo esc_html( $p_id ); ?></code>
												<?php if ( ! empty( $p['is_recurring'] ) ) : ?>
													<span style="font-size: 10.5px; font-weight: 700; color: #7c3aed; background: #f5f3ff; padding: 1px 6px; border-radius: 4px; margin-left: 4px;">Suscripción (<?php echo esc_html( $p['billing_interval'] ); ?>)</span>
												<?php endif; ?>
											</td>
											<td style="padding: 12px 14px;">
												<span style="background: <?php echo esc_attr( $type_bg ); ?>; color: <?php echo esc_attr( $type_color ); ?>; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px;">
													<?php echo esc_html( $type_label ); ?>
												</span>
											</td>
											<td style="padding: 12px 14px; font-weight: 700; font-size: 14px; color: #0f172a;">
												<?php echo number_format( $p['price'], 2, ',', '.' ) . ' ' . esc_html( $curr_sym ); ?>
											</td>
											<td style="padding: 12px 14px; color: #475569; font-size: 12.5px;">
												<?php echo esc_html( isset( $p['tax_rate'] ) ? $p['tax_rate'] : '21' ); ?>%
											</td>
											<td style="padding: 12px 14px;">
												<div style="display: flex; align-items: center; gap: 6px;">
													<code style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 11.5px; color: #334155; font-weight: 600;">[wpat_pay id="<?php echo esc_attr( $p_id ); ?>"]</code>
													<button type="button" class="button button-small wpat-qp-copy-shortcode-btn" data-code="[wpat_pay id=&quot;<?php echo esc_attr( $p_id ); ?>&quot;]" title="Copiar Shortcode" style="height: 24px; padding: 0 6px;">📋</button>
												</div>
											</td>
											<td style="text-align: right; padding: 12px 14px;">
												<button type="button" class="button button-small wpat-qp-edit-prod-btn" data-prod='<?php echo esc_attr( wp_json_encode( $p ) ); ?>' style="margin-right: 4px;">Editar</button>
												<button type="button" class="button button-small button-link-delete wpat-qp-delete-prod-btn" data-id="<?php echo esc_attr( $p_id ); ?>">Eliminar</button>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">
											No tienes productos creados todavía. Pulsa en "Añadir Nuevo Producto".
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- ========================================== -->
				<!-- PESTAÑA 2: PASARELAS DE PAGO              -->
				<!-- ========================================== -->
				<div id="wpat_qp_tab_gateways" class="wpat-qp-tab-panel" style="<?php echo ( 'gateways' === $active_subtab ) ? 'display:block;' : 'display:none;'; ?>">
					<div style="display: flex; flex-direction: column; gap: 20px;">

						<!-- 1. STRIPE -->
						<div class="wpat-qp-gateway-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 22px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
								<div style="display: flex; align-items: center; gap: 10px;">
									<span style="font-size: 24px;">💳</span>
									<div>
										<strong style="font-size: 15px; color: #0f172a;">Stripe (Tarjetas, Apple Pay, Google Pay)</strong>
										<span style="display: block; font-size: 12px; color: #64748b;">Cobros instantáneos con tarjeta y soporte para suscripciones periódicas.</span>
									</div>
								</div>
								<label class="wpat-switch">
									<input type="checkbox" name="wpat_settings[qp_stripe_enabled]" value="1" <?php checked( isset( $settings['qp_stripe_enabled'] ) ? $settings['qp_stripe_enabled'] : '1', '1' ); ?> />
									<span class="wpat-slider round"></span>
								</label>
							</div>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Modo de Funcionamiento</label>
									<select name="wpat_settings[qp_stripe_mode]" style="width: 100%; height: 36px; border-radius: 6px;">
										<option value="test" <?php selected( isset( $settings['qp_stripe_mode'] ) ? $settings['qp_stripe_mode'] : 'test', 'test' ); ?>>🧪 Modo de Pruebas (Test / Sandbox)</option>
										<option value="live" <?php selected( isset( $settings['qp_stripe_mode'] ) ? $settings['qp_stripe_mode'] : 'test', 'live' ); ?>>🚀 Modo Producción (Real / Live)</option>
									</select>
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Clave Pública de Pruebas (Test Publishable Key)</label>
									<input type="text" name="wpat_settings[qp_stripe_test_pub_key]" value="<?php echo esc_attr( isset( $settings['qp_stripe_test_pub_key'] ) ? $settings['qp_stripe_test_pub_key'] : '' ); ?>" placeholder="pk_test_..." class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Clave Secreta de Pruebas (Test Secret Key)</label>
									<input type="password" name="wpat_settings[qp_stripe_test_sec_key]" value="<?php echo esc_attr( isset( $settings['qp_stripe_test_sec_key'] ) ? $settings['qp_stripe_test_sec_key'] : '' ); ?>" placeholder="sk_test_..." class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Clave Pública Real (Live Publishable Key)</label>
									<input type="text" name="wpat_settings[qp_stripe_live_pub_key]" value="<?php echo esc_attr( isset( $settings['qp_stripe_live_pub_key'] ) ? $settings['qp_stripe_live_pub_key'] : '' ); ?>" placeholder="pk_live_..." class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Clave Secreta Real (Live Secret Key)</label>
									<input type="password" name="wpat_settings[qp_stripe_live_sec_key]" value="<?php echo esc_attr( isset( $settings['qp_stripe_live_sec_key'] ) ? $settings['qp_stripe_live_sec_key'] : '' ); ?>" placeholder="sk_live_..." class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
							</div>
						</div>

						<!-- 2. REDSYS TPV & BIZUM BANCO -->
						<div class="wpat-qp-gateway-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 22px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
								<div style="display: flex; align-items: center; gap: 10px;">
									<span style="font-size: 24px;">🏦</span>
									<div>
										<strong style="font-size: 15px; color: #0f172a;">Redsys TPV Virtual & Bizum Bancario</strong>
										<span style="display: block; font-size: 12px; color: #64748b;">Pasarela directa con firma HMAC SHA-256 sin plugins adicionales.</span>
									</div>
								</div>
								<label class="wpat-switch">
									<input type="checkbox" name="wpat_settings[qp_redsys_enabled]" value="1" <?php checked( isset( $settings['qp_redsys_enabled'] ) ? $settings['qp_redsys_enabled'] : '0', '1' ); ?> />
									<span class="wpat-slider round"></span>
								</label>
							</div>

							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px;">
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Entorno Redsys</label>
									<select name="wpat_settings[qp_redsys_mode]" style="width: 100%; height: 36px; border-radius: 6px;">
										<option value="test" <?php selected( isset( $settings['qp_redsys_mode'] ) ? $settings['qp_redsys_mode'] : 'test', 'test' ); ?>>🧪 Pruebas (sis-t.redsys.es)</option>
										<option value="live" <?php selected( isset( $settings['qp_redsys_mode'] ) ? $settings['qp_redsys_mode'] : 'test', 'live' ); ?>>🚀 Real / Producción (sis.redsys.es)</option>
									</select>
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Número de Comercio (FUC)</label>
									<input type="text" name="wpat_settings[qp_redsys_fuc]" value="<?php echo esc_attr( isset( $settings['qp_redsys_fuc'] ) ? $settings['qp_redsys_fuc'] : '' ); ?>" placeholder="Ej: 999008881" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Número de Terminal</label>
									<input type="text" name="wpat_settings[qp_redsys_terminal]" value="<?php echo esc_attr( isset( $settings['qp_redsys_terminal'] ) ? $settings['qp_redsys_terminal'] : '1' ); ?>" placeholder="Ej: 1" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Clave Secreta SHA-256</label>
									<input type="password" name="wpat_settings[qp_redsys_key]" value="<?php echo esc_attr( isset( $settings['qp_redsys_key'] ) ? $settings['qp_redsys_key'] : '' ); ?>" placeholder="Clave generada en el panel de Redsys" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
							</div>
						</div>

						<!-- 3. BIZUM DIRECTO MANUAL -->
						<div class="wpat-qp-gateway-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 22px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
								<div style="display: flex; align-items: center; gap: 10px;">
									<span style="font-size: 24px;">📱</span>
									<div>
										<strong style="font-size: 15px; color: #0f172a;">Bizum Directo (Manual / Freelancers)</strong>
										<span style="display: block; font-size: 12px; color: #64748b;">Muestra tu número de Bizum en el checkout y registra el pedido pendiente.</span>
									</div>
								</div>
								<label class="wpat-switch">
									<input type="checkbox" name="wpat_settings[qp_bizum_enabled]" value="1" <?php checked( isset( $settings['qp_bizum_enabled'] ) ? $settings['qp_bizum_enabled'] : '1', '1' ); ?> />
									<span class="wpat-slider round"></span>
								</label>
							</div>

							<div class="wpat-field-group" style="max-width: 320px;">
								<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Teléfono para recibir Bizum *</label>
								<input type="text" name="wpat_settings[qp_bizum_phone]" value="<?php echo esc_attr( isset( $settings['qp_bizum_phone'] ) ? $settings['qp_bizum_phone'] : '' ); ?>" placeholder="Ej: 600 000 000" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
							</div>
						</div>

						<!-- 4. PAYPAL & TRANSFERENCIA -->
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
							<div class="wpat-qp-gateway-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 22px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
									<strong style="font-size: 14px; color: #0f172a;">🅿️ PayPal</strong>
									<label class="wpat-switch">
										<input type="checkbox" name="wpat_settings[qp_paypal_enabled]" value="1" <?php checked( isset( $settings['qp_paypal_enabled'] ) ? $settings['qp_paypal_enabled'] : '0', '1' ); ?> />
										<span class="wpat-slider round"></span>
									</label>
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12px; color: #334155; margin-bottom: 4px; display: block;">Email de tu Cuenta PayPal</label>
									<input type="email" name="wpat_settings[qp_paypal_email]" value="<?php echo esc_attr( isset( $settings['qp_paypal_email'] ) ? $settings['qp_paypal_email'] : '' ); ?>" placeholder="pagos@tunegocio.com" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
							</div>

							<div class="wpat-qp-gateway-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 22px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
									<strong style="font-size: 14px; color: #0f172a;">🏛️ Transferencia Bancaria</strong>
									<label class="wpat-switch">
										<input type="checkbox" name="wpat_settings[qp_bank_enabled]" value="1" <?php checked( isset( $settings['qp_bank_enabled'] ) ? $settings['qp_bank_enabled'] : '0', '1' ); ?> />
										<span class="wpat-slider round"></span>
									</label>
								</div>
								<div class="wpat-field-group" style="margin-bottom: 8px;">
									<label style="font-weight: 700; font-size: 12px; color: #334155; margin-bottom: 4px; display: block;">IBAN</label>
									<input type="text" name="wpat_settings[qp_bank_iban]" value="<?php echo esc_attr( isset( $settings['qp_bank_iban'] ) ? $settings['qp_bank_iban'] : '' ); ?>" placeholder="ES00 0000 0000 0000 0000 0000" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12px; color: #334155; margin-bottom: 4px; display: block;">Titular de la Cuenta</label>
									<input type="text" name="wpat_settings[qp_bank_holder]" value="<?php echo esc_attr( isset( $settings['qp_bank_holder'] ) ? $settings['qp_bank_holder'] : '' ); ?>" placeholder="Tu Empresa / Nombre" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ========================================== -->
				<!-- PESTAÑA 3: CUPONES DE DESCUENTO           -->
				<!-- ========================================== -->
				<div id="wpat_qp_tab_coupons" class="wpat-qp-tab-panel" style="<?php echo ( 'coupons' === $active_subtab ) ? 'display:block;' : 'display:none;'; ?>">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 12px;">
						<div>
							<h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">Cupones de Descuento</h4>
							<p style="margin: 3px 0 0 0; color: #64748b; font-size: 12.5px;">Crea códigos promocionales para aplicar descuentos en porcentaje o importe fijo en el checkout.</p>
						</div>
						<button type="button" class="button button-primary" id="wpat_qp_open_add_coupon_btn" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 34px; display: inline-flex; align-items: center; gap: 6px;">
							<span class="dashicons dashicons-plus-alt2" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span> Añadir Cupón
						</button>
					</div>

					<div class="wpat-qp-coupons-table-wrap" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
						<table class="wp-list-table widefat fixed striped" style="border: none;">
							<thead>
								<tr>
									<th style="width: 25%; padding: 12px 14px; font-weight: 700;">Código de Cupón</th>
									<th style="width: 20%; padding: 12px 14px; font-weight: 700;">Descuento</th>
									<th style="width: 20%; padding: 12px 14px; font-weight: 700;">Caducidad</th>
									<th style="width: 15%; padding: 12px 14px; font-weight: 700;">Usos</th>
									<th style="width: 20%; text-align: right; padding: 12px 14px; font-weight: 700;">Acciones</th>
								</tr>
							</thead>
							<tbody id="wpat_qp_coupons_tbody">
								<?php if ( ! empty( $coupons ) ) : ?>
									<?php foreach ( $coupons as $code => $c ) : ?>
										<tr id="wpat_qp_row_coupon_<?php echo esc_attr( $code ); ?>">
											<td style="padding: 12px 14px;">
												<strong style="font-size: 13.5px; color: #2563eb; font-family: monospace;"><?php echo esc_html( $code ); ?></strong>
											</td>
											<td style="padding: 12px 14px; font-weight: 700; color: #059669;">
												<?php echo ( 'percent' === $c['type'] ) ? esc_html( $c['amount'] ) . '%' : number_format( $c['amount'], 2, ',', '.' ) . ' ' . esc_html( $curr_sym ); ?>
											</td>
											<td style="padding: 12px 14px; color: #64748b; font-size: 12.5px;">
												<?php echo ! empty( $c['expiry'] ) ? esc_html( $c['expiry'] ) : 'Sin límite'; ?>
											</td>
											<td style="padding: 12px 14px; font-size: 12.5px; color: #334155;">
												<strong><?php echo intval( isset( $c['usage_count'] ) ? $c['usage_count'] : 0 ); ?></strong> / <?php echo ( ! empty( $c['usage_limit'] ) ) ? intval( $c['usage_limit'] ) : '∞'; ?>
											</td>
											<td style="text-align: right; padding: 12px 14px;">
												<button type="button" class="button button-small button-link-delete wpat-qp-delete-coupon-btn" data-code="<?php echo esc_attr( $code ); ?>">Eliminar</button>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">
											No tienes cupones creados. Pulsa en "Añadir Cupón".
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- ========================================== -->
				<!-- PESTAÑA 4: VENTAS & PEDIDOS               -->
				<!-- ========================================== -->
				<div id="wpat_qp_tab_orders" class="wpat-qp-tab-panel" style="<?php echo ( 'orders' === $active_subtab ) ? 'display:block;' : 'display:none;'; ?>">
					<!-- MÉTRICAS DE VENTAS -->
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px;">
						<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; border-left: 4px solid #2563eb;">
							<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b;">Total Facturado</span>
							<div style="font-size: 22px; font-weight: 900; color: #0f172a; margin-top: 4px;"><?php echo number_format( $total_sales, 2, ',', '.' ) . ' ' . esc_html( $curr_sym ); ?></div>
						</div>
						<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; border-left: 4px solid #059669;">
							<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b;">Ventas Completadas</span>
							<div style="font-size: 22px; font-weight: 900; color: #059669; margin-top: 4px;"><?php echo intval( $count_completed ); ?></div>
						</div>
						<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; border-left: 4px solid #d97706;">
							<span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b;">Pendientes de Pago</span>
							<div style="font-size: 22px; font-weight: 900; color: #d97706; margin-top: 4px;"><?php echo intval( $count_pending ); ?></div>
						</div>
					</div>

					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
						<h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #0f172a;">Historial de Pedidos Registrados</h4>
						<a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=wpat_qp_admin_export_csv' ), 'wpat_quick_pay_admin_nonce', 'security' ); ?>" class="button button-secondary" style="font-weight: 600; height: 32px; display: inline-flex; align-items: center; gap: 5px;">
							<span class="dashicons dashicons-download" style="font-size: 14px; width: 14px; height: 14px; line-height: 1;"></span> Exportar Ventas a CSV
						</a>
					</div>

					<div class="wpat-qp-orders-table-wrap" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
						<table class="wp-list-table widefat fixed striped" style="border: none;">
							<thead>
								<tr>
									<th style="width: 12%; padding: 12px 14px; font-weight: 700;">Pedido</th>
									<th style="width: 14%; padding: 12px 14px; font-weight: 700;">Fecha</th>
									<th style="width: 22%; padding: 12px 14px; font-weight: 700;">Cliente</th>
									<th style="width: 20%; padding: 12px 14px; font-weight: 700;">Producto</th>
									<th style="width: 10%; padding: 12px 14px; font-weight: 700;">Total</th>
									<th style="width: 10%; padding: 12px 14px; font-weight: 700;">Estado</th>
									<th style="width: 12%; text-align: right; padding: 12px 14px; font-weight: 700;">Acciones</th>
								</tr>
							</thead>
							<tbody id="wpat_qp_orders_tbody">
								<?php if ( ! empty( $orders ) ) : ?>
									<?php foreach ( $orders as $o ) : ?>
										<?php
										$is_comp = ( 'completed' === $o->status );
										$st_bg   = $is_comp ? '#ecfdf5' : '#fffbeb';
										$st_tx   = $is_comp ? '#059669' : '#d97706';
										$st_lbl  = $is_comp ? 'Completado' : 'Pendiente';
										?>
										<tr id="wpat_qp_row_order_<?php echo esc_attr( $o->id ); ?>">
											<td style="padding: 12px 14px;">
												<strong style="color: #2563eb;">#WPAT-<?php echo esc_html( $o->id ); ?></strong>
												<div style="font-size: 11px; color: #64748b;"><?php echo esc_html( $o->invoice_number ); ?></div>
											</td>
											<td style="padding: 12px 14px; font-size: 12px; color: #475569;">
												<?php echo esc_html( date( 'd/m/Y H:i', strtotime( $o->created_at ) ) ); ?>
											</td>
											<td style="padding: 12px 14px;">
												<strong style="font-size: 13px; color: #0f172a;"><?php echo esc_html( $o->customer_name ); ?></strong>
												<div style="font-size: 11.5px; color: #64748b;"><?php echo esc_html( $o->customer_email ); ?></div>
												<?php if ( ! empty( $o->customer_dni ) ) : ?>
													<div style="font-size: 10.5px; color: #94a3b8;">DNI: <?php echo esc_html( $o->customer_dni ); ?></div>
												<?php endif; ?>
											</td>
											<td style="padding: 12px 14px;">
												<div style="font-size: 13px; font-weight: 600; color: #1e293b;"><?php echo esc_html( $o->product_name ); ?></div>
												<div style="font-size: 11px; color: #64748b;">Pasarela: <?php echo esc_html( strtoupper( $o->gateway ) ); ?></div>
											</td>
											<td style="padding: 12px 14px; font-weight: 800; font-size: 14px; color: #0f172a;">
												<?php echo number_format( $o->amount, 2, ',', '.' ) . ' ' . esc_html( $o->currency ); ?>
											</td>
											<td style="padding: 12px 14px;">
												<select class="wpat-qp-change-order-status" data-id="<?php echo esc_attr( $o->id ); ?>" style="font-size: 11px; height: 26px; border-radius: 4px; background: <?php echo esc_attr( $st_bg ); ?>; color: <?php echo esc_attr( $st_tx ); ?>; font-weight: 700; border-color: #cbd5e1;">
													<option value="completed" <?php selected( $o->status, 'completed' ); ?>>✓ Completado</option>
													<option value="pending" <?php selected( $o->status, 'pending' ); ?>>⏳ Pendiente</option>
													<option value="cancelled" <?php selected( $o->status, 'cancelled' ); ?>>✕ Cancelado</option>
												</select>
											</td>
											<td style="text-align: right; padding: 12px 14px;">
												<a href="<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=wpat_qp_admin_download_pdf&order_id=' . $o->id ), 'wpat_quick_pay_admin_nonce', 'security' ); ?>" target="_blank" class="button button-small" title="Factura PDF" style="margin-right: 4px;">PDF</a>
												<button type="button" class="button button-small button-link-delete wpat-qp-delete-order-btn" data-id="<?php echo esc_attr( $o->id ); ?>">🗑️</button>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="7" style="text-align: center; padding: 35px; color: #94a3b8;">
											Aún no se han registrado compras. Los pedidos realizados aparecerán aquí automáticamente.
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- ========================================== -->
				<!-- PESTAÑA 5: FACTURAS PDF, IMPUESTOS & AJUSTES -->
				<!-- ========================================== -->
				<div id="wpat_qp_tab_settings" class="wpat-qp-tab-panel" style="<?php echo ( 'settings' === $active_subtab ) ? 'display:block;' : 'display:none;'; ?>">
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

						<!-- DATOS FISCALES PARA FACTURA -->
						<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
							<h4 style="margin: 0 0 14px 0; font-size: 15px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
								🏢 Datos Fiscales de Facturación
							</h4>

							<div class="wpat-field-group" style="margin-bottom: 12px;">
								<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Nombre Comercial / Razón Social *</label>
								<input type="text" name="wpat_settings[qp_company_name]" value="<?php echo esc_attr( isset( $settings['qp_company_name'] ) ? $settings['qp_company_name'] : get_bloginfo( 'name' ) ); ?>" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
							</div>

							<div class="wpat-field-group" style="margin-bottom: 12px;">
								<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">NIF / CIF de la Empresa *</label>
								<input type="text" name="wpat_settings[qp_company_cif]" value="<?php echo esc_attr( isset( $settings['qp_company_cif'] ) ? $settings['qp_company_cif'] : '' ); ?>" placeholder="Ej: B12345678" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
							</div>

							<div class="wpat-field-group" style="margin-bottom: 12px;">
								<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Dirección Fiscal Completa</label>
								<textarea name="wpat_settings[qp_company_address]" rows="3" class="large-text" style="width: 100%; border-radius: 6px; font-size: 12.5px;" placeholder="Calle Mayor 10, 1º A&#10;28001 Madrid, España"><?php echo esc_textarea( isset( $settings['qp_company_address'] ) ? $settings['qp_company_address'] : '' ); ?></textarea>
							</div>

							<div class="wpat-field-group">
								<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Prefijo de Factura Correlativa</label>
								<input type="text" name="wpat_settings[qp_invoice_prefix]" value="<?php echo esc_attr( isset( $settings['qp_invoice_prefix'] ) ? $settings['qp_invoice_prefix'] : 'FAC-' . date( 'Y' ) . '-' ); ?>" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
							</div>
						</div>

						<!-- MONEDA, IVA Y DISEÑO -->
						<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
							<h4 style="margin: 0 0 14px 0; font-size: 15px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
								⚙️ Moneda, Impuestos y Diseño
							</h4>

							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Símbolo Moneda</label>
									<input type="text" name="wpat_settings[qp_currency_symbol]" value="<?php echo esc_attr( isset( $settings['qp_currency_symbol'] ) ? $settings['qp_currency_symbol'] : '€' ); ?>" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								</div>
								<div class="wpat-field-group">
									<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Posición Símbolo</label>
									<select name="wpat_settings[qp_currency_pos]" style="width: 100%; height: 36px; border-radius: 6px;">
										<option value="right" <?php selected( isset( $settings['qp_currency_pos'] ) ? $settings['qp_currency_pos'] : 'right', 'right' ); ?>>Derecha (99 €)</option>
										<option value="left" <?php selected( isset( $settings['qp_currency_pos'] ) ? $settings['qp_currency_pos'] : 'right', 'left' ); ?>>Izquierda (€ 99)</option>
									</select>
								</div>
							</div>

							<div class="wpat-field-group" style="margin-bottom: 14px;">
								<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Tipo de IVA por Defecto (%)</label>
								<input type="number" step="0.1" name="wpat_settings[qp_default_tax_rate]" value="<?php echo esc_attr( isset( $settings['qp_default_tax_rate'] ) ? $settings['qp_default_tax_rate'] : '21' ); ?>" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
								<p class="description" style="font-size: 11.5px; margin-top: 3px;">Se usará para desglosar la base imponible y cuota de IVA en la factura PDF.</p>
							</div>

							<div class="wpat-field-group">
								<label style="font-weight: 700; font-size: 12.5px; color: #334155; margin-bottom: 4px; display: block;">Color Principal del Botón y Checkout</label>
								<div style="display: flex; align-items: center; gap: 8px;">
									<input type="color" name="wpat_settings[qp_primary_color]" value="<?php echo esc_attr( isset( $settings['qp_primary_color'] ) ? $settings['qp_primary_color'] : '#2563eb' ); ?>" style="width: 44px; height: 36px; padding: 2px; border-radius: 6px; border: 1px solid #cbd5e1; cursor: pointer;" />
									<input type="text" value="<?php echo esc_attr( isset( $settings['qp_primary_color'] ) ? $settings['qp_primary_color'] : '#2563eb' ); ?>" class="regular-text" style="width: 120px; height: 36px; border-radius: 6px; font-family: monospace;" readonly />
								</div>
							</div>
						</div>
					</div>
				</div>

			</div>
		</div>

		<!-- MODAL: AÑADIR / EDITAR PRODUCTO -->
		<div id="wpat_qp_product_modal" class="wpat-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 999999; align-items: center; justify-content: center;">
			<div class="wpat-modal-content" style="background: #fff; border-radius: 12px; width: 100%; max-width: 540px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); position: relative; max-height: 90vh; overflow-y: auto;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
					<h3 id="wpat_qp_modal_prod_title" style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;">Añadir Nuevo Producto</h3>
					<button type="button" class="wpat-qp-close-admin-modal" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8;">&times;</button>
				</div>

				<div id="wpat_qp_product_form_fields">
					<input type="hidden" id="wpat_qp_prod_id" value="" />

					<div class="wpat-field-group" style="margin-bottom: 12px;">
						<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Nombre del Producto o Servicio *</label>
						<input type="text" id="wpat_qp_prod_name" placeholder="Ej: Consultoría 1h / eBook SEO Avanzado" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" required />
					</div>

					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
						<div class="wpat-field-group">
							<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Precio (€ / $) *</label>
							<input type="number" step="0.01" id="wpat_qp_prod_price" placeholder="49.00" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" required />
						</div>
						<div class="wpat-field-group">
							<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Tipo de Producto *</label>
							<select id="wpat_qp_prod_type" style="width: 100%; height: 38px; border-radius: 6px;">
								<option value="service">🎓 Servicio / Consultoría / Curso</option>
								<option value="digital">📥 Descarga Digital (Archivo / PDF)</option>
								<option value="physical">📦 Producto Físico (Con Envío)</option>
							</select>
						</div>
					</div>

					<div id="wpat_qp_prod_digital_group" style="display: none; margin-bottom: 12px; background: #ecfdf5; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0;">
						<label style="display: block; font-weight: 700; font-size: 12.5px; color: #065f46; margin-bottom: 4px;">URL o Archivo para Descarga Digital *</label>
						<input type="text" id="wpat_qp_prod_download_file" placeholder="https://tusitio.com/archivo.pdf" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
					</div>

					<div id="wpat_qp_prod_physical_group" style="display: none; margin-bottom: 12px; background: #fffbeb; padding: 12px; border-radius: 8px; border: 1px solid #fde68a;">
						<label style="display: block; font-weight: 700; font-size: 12.5px; color: #92400e; margin-bottom: 4px;">Coste de Envío Fijo (€ / $)</label>
						<input type="number" step="0.01" id="wpat_qp_prod_shipping_cost" placeholder="4.95" class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
					</div>

					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
						<div class="wpat-field-group">
							<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Tipo de IVA (%)</label>
							<input type="number" step="0.1" id="wpat_qp_prod_tax_rate" placeholder="21" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
						</div>
						<div class="wpat-field-group">
							<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Texto del Botón</label>
							<input type="text" id="wpat_qp_prod_btn_text" placeholder="Ej: Comprar Ahora" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
						</div>
					</div>

					<div class="wpat-field-group" style="margin-bottom: 12px;">
						<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Descripción Corta (Opcional)</label>
						<textarea id="wpat_qp_prod_desc" rows="2" class="large-text" style="width: 100%; border-radius: 6px; font-size: 12.5px;" placeholder="Breve resumen visible en tarjeta de producto..."></textarea>
					</div>

					<div class="wpat-field-group" style="margin-bottom: 18px;">
						<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Imagen Destacada URL (Opcional)</label>
						<input type="text" id="wpat_qp_prod_image_url" placeholder="https://..." class="regular-text" style="width: 100%; height: 36px; border-radius: 6px;" />
					</div>

					<div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
						<button type="button" class="button button-secondary wpat-qp-close-admin-modal" style="height: 36px; font-weight: 600;">Cancelar</button>
						<button type="button" class="button button-primary" id="wpat_qp_save_prod_btn" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 36px; padding: 0 18px;">Guardar Producto</button>
					</div>
				</div>
			</div>
		</div>

		<!-- MODAL: AÑADIR CUPÓN -->
		<div id="wpat_qp_coupon_modal" class="wpat-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); z-index: 999999; align-items: center; justify-content: center;">
			<div class="wpat-modal-content" style="background: #fff; border-radius: 12px; width: 100%; max-width: 440px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); position: relative;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
					<h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;">Añadir Cupón de Descuento</h3>
					<button type="button" class="wpat-qp-close-admin-modal" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8;">&times;</button>
				</div>

				<div class="wpat-field-group" style="margin-bottom: 12px;">
					<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Código del Cupón *</label>
					<input type="text" id="wpat_qp_c_code" placeholder="Ej: PROMO10 / BLACKFRIDAY" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px; text-transform: uppercase; font-family: monospace;" required />
				</div>

				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
					<div class="wpat-field-group">
						<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Tipo Descuento *</label>
						<select id="wpat_qp_c_type" style="width: 100%; height: 38px; border-radius: 6px;">
							<option value="percent">Porcentaje (%)</option>
							<option value="fixed">Importe Fijo (€ / $)</option>
						</select>
					</div>
					<div class="wpat-field-group">
						<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Importe Descuento *</label>
						<input type="number" step="0.01" id="wpat_qp_c_amount" placeholder="10" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" required />
					</div>
				</div>

				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px;">
					<div class="wpat-field-group">
						<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Fecha Caducidad (Opcional)</label>
						<input type="date" id="wpat_qp_c_expiry" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
					</div>
					<div class="wpat-field-group">
						<label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">Límite de Usos (0 = ∞)</label>
						<input type="number" id="wpat_qp_c_limit" placeholder="0" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
					</div>
				</div>

				<div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
					<button type="button" class="button button-secondary wpat-qp-close-admin-modal" style="height: 36px; font-weight: 600;">Cancelar</button>
					<button type="button" class="button button-primary" id="wpat_qp_save_coupon_btn" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 36px; padding: 0 18px;">Guardar Cupón</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza la interfaz del Generador de Códigos QR & Enlaces Directos.
	 *
	 * @param array $settings Ajustes del plugin.
	 */
	public function render_qr_generator_content( $settings ) {
		if ( ! class_exists( 'WPAT_QR_Generator' ) ) {
			require_once WPAT_PATH . 'includes/modules/class-wpat-qr-generator.php';
		}

		$qr_module = WPAT_QR_Generator::get_instance();
		$saved_qrs = $qr_module->get_saved_qrs();
		$site_icon = get_site_icon_url( 128 );
		?>
		<div class="wpat-module-card wpat-qr-generator-wrapper">
			<div class="wpat-module-header">
				<div class="wpat-module-info">
					<h3>Generador de Códigos QR & Enlaces Directos <span class="wpat-badge" style="background:#2563eb; color:#fff;">Vectorial & PNG</span></h3>
					<p>Genera al instante códigos QR dinámicos para WhatsApp con mensaje, enlaces de venta, redes Wi-Fi, tarjetas vCard, emails y Bizum con previsualización en vivo.</p>
				</div>
				<?php $this->render_module_toggle( 'qr-generator', $settings, true ); ?>
			</div>

			<div class="wpat-module-body" style="display: block; padding: 22px;">

				<!-- GENERADOR EN 2 COLUMNAS (FORMULARIO + PREVIEW) -->
				<div style="display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; margin-bottom: 30px;">
					
					<!-- COLUMNA IZQUIERDA: FORMULARIO Y OPCIONES -->
					<div class="wpat-qr-builder-form" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
						<input type="hidden" id="wpat_qr_editing_id" value="" />

						<!-- Nombre identificativo -->
						<div class="wpat-field-group" style="margin-bottom: 16px;">
							<label for="wpat_qr_name" style="display: block; font-weight: 700; font-size: 13.5px; color: #1e293b; margin-bottom: 6px;">
								🏷️ Nombre del Código QR (Para tu biblioteca)
							</label>
							<input type="text" id="wpat_qr_name" placeholder="Ej: WhatsApp Pedidos / Carta Menú / Wi-Fi Oficina" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
						</div>

						<!-- Selector de Tipo de QR (Pestañas visuales) -->
						<div class="wpat-field-group" style="margin-bottom: 18px;">
							<label style="display: block; font-weight: 700; font-size: 13.5px; color: #1e293b; margin-bottom: 8px;">
								🎯 Tipo de Contenido / Acción del QR
							</label>
							<div class="wpat-qr-type-selector" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px;">
								<button type="button" class="wpat-qr-type-btn active" data-type="whatsapp" style="background: #2563eb; color: #fff; border: 1px solid #2563eb; padding: 8px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all 0.15s ease;">
									<span style="font-size: 16px;">💬</span> WhatsApp
								</button>
								<button type="button" class="wpat-qr-type-btn" data-type="url" style="background: #fff; color: #475569; border: 1px solid #cbd5e1; padding: 8px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all 0.15s ease;">
									<span style="font-size: 16px;">🔗</span> Enlace / Web
								</button>
								<button type="button" class="wpat-qr-type-btn" data-type="wifi" style="background: #fff; color: #475569; border: 1px solid #cbd5e1; padding: 8px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all 0.15s ease;">
									<span style="font-size: 16px;">📶</span> Red Wi-Fi
								</button>
								<button type="button" class="wpat-qr-type-btn" data-type="vcard" style="background: #fff; color: #475569; border: 1px solid #cbd5e1; padding: 8px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all 0.15s ease;">
									<span style="font-size: 16px;">📇</span> vCard
								</button>
								<button type="button" class="wpat-qr-type-btn" data-type="email" style="background: #fff; color: #475569; border: 1px solid #cbd5e1; padding: 8px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all 0.15s ease;">
									<span style="font-size: 16px;">✉️</span> Email
								</button>
								<button type="button" class="wpat-qr-type-btn" data-type="phone" style="background: #fff; color: #475569; border: 1px solid #cbd5e1; padding: 8px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all 0.15s ease;">
									<span style="font-size: 16px;">📞</span> Teléfono
								</button>
								<button type="button" class="wpat-qr-type-btn" data-type="bizum" style="background: #fff; color: #475569; border: 1px solid #cbd5e1; padding: 8px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all 0.15s ease;">
									<span style="font-size: 16px;">💳</span> Bizum / Texto
								</button>
							</div>
							<input type="hidden" id="wpat_qr_selected_type" value="whatsapp" />
						</div>

						<!-- BLOQUES ESPECÍFICOS SEGÚN TIPO -->
						<div class="wpat-qr-fields-container" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
							
							<!-- 1. WHATSAPP -->
							<div class="wpat-qr-type-section" id="wpat_qr_sec_whatsapp">
								<div class="wpat-field-group" style="margin-bottom: 12px;">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Número de Teléfono WhatsApp (Con prefijo internacional sin '+')</label>
									<input type="text" id="wpat_qr_wa_phone" placeholder="Ej: 34600123456" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									<p class="description" style="margin-top: 4px;">Introduce el prefijo del país seguido del teléfono (ej: 34 para España, 52 para México, 54 para Argentina).</p>
								</div>
								<div class="wpat-field-group">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Mensaje Predefinido (Opcional)</label>
									<textarea id="wpat_qr_wa_msg" rows="3" placeholder="Hola, he escaneado el código QR y me gustaría recibir más información." style="width: 100%; border-radius: 6px;"></textarea>
								</div>
							</div>

							<!-- 2. URL / WEB -->
							<div class="wpat-qr-type-section" id="wpat_qr_sec_url" style="display: none;">
								<div class="wpat-field-group">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">URL / Enlace Destino *</label>
									<input type="url" id="wpat_qr_url_field" placeholder="https://tusitio.com/oferta-o-carta" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									<p class="description" style="margin-top: 4px;">Puedes usar cualquier enlace web, ficha de producto WooCommerce, enlace de pago directo o menú digital.</p>
								</div>
							</div>

							<!-- 3. WI-FI -->
							<div class="wpat-qr-type-section" id="wpat_qr_sec_wifi" style="display: none;">
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Nombre de la Red (SSID) *</label>
										<input type="text" id="wpat_qr_wifi_ssid" placeholder="MiWifiClientes" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Tipo de Seguridad</label>
										<select id="wpat_qr_wifi_type" style="width: 100%; height: 38px; border-radius: 6px;">
											<option value="WPA">WPA / WPA2 / WPA3 (Estándar)</option>
											<option value="WEP">WEP</option>
											<option value="nopass">Sin Contraseña (Abierta)</option>
										</select>
									</div>
								</div>
								<div class="wpat-field-group" style="margin-bottom: 8px;">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Contraseña Wi-Fi</label>
									<input type="text" id="wpat_qr_wifi_pass" placeholder="Clave de acceso" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
								</div>
								<label style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #475569; margin-top: 6px; cursor: pointer;">
									<input type="checkbox" id="wpat_qr_wifi_hidden" value="1" /> Red Wi-Fi Oculta (Hidden SSID)
								</label>
							</div>

							<!-- 4. VCARD / CONTACTO -->
							<div class="wpat-qr-type-section" id="wpat_qr_sec_vcard" style="display: none;">
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Nombre y Apellidos *</label>
										<input type="text" id="wpat_qr_vc_name" placeholder="Juan Pérez" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Empresa / Organización</label>
										<input type="text" id="wpat_qr_vc_org" placeholder="Agencia Creativa SL" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
								</div>
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Cargo / Puesto</label>
										<input type="text" id="wpat_qr_vc_title" placeholder="Director Comercial" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Teléfono Directo</label>
										<input type="text" id="wpat_qr_vc_phone" placeholder="+34 600 000 000" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
								</div>
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Correo Electrónico</label>
										<input type="email" id="wpat_qr_vc_email" placeholder="contacto@agencia.com" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Sitio Web</label>
										<input type="url" id="wpat_qr_vc_url" placeholder="https://agencia.com" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
								</div>
							</div>

							<!-- 5. EMAIL -->
							<div class="wpat-qr-type-section" id="wpat_qr_sec_email" style="display: none;">
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Correo Destinatario *</label>
										<input type="email" id="wpat_qr_em_to" placeholder="info@tusitio.com" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Asunto del Mensaje</label>
										<input type="text" id="wpat_qr_em_sub" placeholder="Consulta desde Código QR" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
								</div>
								<div class="wpat-field-group">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Cuerpo del Mensaje</label>
									<textarea id="wpat_qr_em_body" rows="3" placeholder="Hola, me pongo en contacto para..." style="width: 100%; border-radius: 6px;"></textarea>
								</div>
							</div>

							<!-- 6. TELÉFONO / SMS -->
							<div class="wpat-qr-type-section" id="wpat_qr_sec_phone" style="display: none;">
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Modo de Acción</label>
										<select id="wpat_qr_ph_mode" style="width: 100%; height: 38px; border-radius: 6px;">
											<option value="tel">Llamada Telefónica Directa</option>
											<option value="sms">Enviar Mensaje SMS</option>
										</select>
									</div>
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Número de Teléfono *</label>
										<input type="text" id="wpat_qr_ph_num" placeholder="+34 600 000 000" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
								</div>
								<div class="wpat-field-group" id="wpat_qr_ph_sms_group" style="display: none;">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Texto del SMS</label>
									<textarea id="wpat_qr_ph_sms_msg" rows="2" placeholder="Mensaje para enviar por SMS..." style="width: 100%; border-radius: 6px;"></textarea>
								</div>
							</div>

							<!-- 7. BIZUM / TEXTO -->
							<div class="wpat-qr-type-section" id="wpat_qr_sec_bizum" style="display: none;">
								<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Número para Bizum / Contacto</label>
										<input type="text" id="wpat_qr_bz_phone" placeholder="600000000" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
									<div class="wpat-field-group">
										<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Concepto / Importe</label>
										<input type="text" id="wpat_qr_bz_concept" placeholder="Reserva mesa / Pedido #102" class="regular-text" style="width: 100%; height: 38px; border-radius: 6px;" />
									</div>
								</div>
								<div class="wpat-field-group">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">O Texto Libre Personalizado</label>
									<textarea id="wpat_qr_bz_raw" rows="2" placeholder="Introduce cualquier texto o código plano..." style="width: 100%; border-radius: 6px;"></textarea>
								</div>
							</div>

						</div>

						<!-- PERSONALIZACIÓN VISUAL & LOGOTIPO -->
						<h4 style="margin: 0 0 12px 0; font-size: 13.5px; font-weight: 800; color: #0f172a; border-top: 1px solid #e2e8f0; padding-top: 16px;">
							🎨 Personalización Visual, Colores & Logotipo
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 16px;">
							<!-- Color del QR -->
							<div class="wpat-field-group">
								<label style="display: block; font-weight: 700; font-size: 12px; color: #1e293b; margin-bottom: 4px;">Color de los Puntos (QR)</label>
								<input type="text" id="wpat_qr_fg_color" value="#000000" class="wpat-qr-color-input" />
							</div>

							<!-- Color de Fondo -->
							<div class="wpat-field-group">
								<label style="display: block; font-weight: 700; font-size: 12px; color: #1e293b; margin-bottom: 4px;">Color de Fondo</label>
								<input type="text" id="wpat_qr_bg_color" value="#ffffff" class="wpat-qr-color-input" />
							</div>

							<!-- Nivel de Corrección -->
							<div class="wpat-field-group">
								<label style="display: block; font-weight: 700; font-size: 12px; color: #1e293b; margin-bottom: 4px;">Corrección de Errores</label>
								<select id="wpat_qr_ec_level" style="width: 100%; height: 34px; border-radius: 6px; font-size: 12.5px;">
									<option value="L">L (7% - Puntos más grandes)</option>
									<option value="M" selected>M (15% - Equilibrado / Recomendado)</option>
									<option value="Q">Q (25% - Alta fiabilidad)</option>
									<option value="H">H (30% - Máxima protección / Ideal con Logo)</option>
								</select>
							</div>

							<!-- Tamaño de Descarga -->
							<div class="wpat-field-group">
								<label style="display: block; font-weight: 700; font-size: 12px; color: #1e293b; margin-bottom: 4px;">Tamaño Descarga (px)</label>
								<select id="wpat_qr_size" style="width: 100%; height: 34px; border-radius: 6px; font-size: 12.5px;">
									<option value="256">256 x 256 px (Web & Móvil)</option>
									<option value="512" selected>512 x 512 px (Alta Resolución)</option>
									<option value="1024">1024 x 1024 px (Ultra HD / Imprenta)</option>
									<option value="2048">2048 x 2048 px (Gran Formato Cartelería)</option>
								</select>
							</div>
						</div>

						<!-- Logotipo Central -->
						<div style="background: #f1f5f9; border-radius: 8px; padding: 14px; margin-top: 10px;">
							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: center;">
								<div class="wpat-field-group">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Logotipo Central del QR</label>
									<select id="wpat_qr_logo_type" style="width: 100%; height: 36px; border-radius: 6px;">
										<option value="none">Sin Logotipo Central</option>
										<option value="whatsapp">💬 Icono Oficial de WhatsApp</option>
										<?php if ( ! empty( $site_icon ) ) : ?>
											<option value="site_logo">🌐 Logotipo / Favicon del Sitio</option>
										<?php endif; ?>
										<option value="custom">🖼️ Imagen Personalizada (Medios)</option>
									</select>
								</div>
								<div class="wpat-field-group" id="wpat_qr_custom_logo_box" style="display: none;">
									<label style="display: block; font-weight: 700; font-size: 12.5px; color: #1e293b; margin-bottom: 4px;">Imagen de Medios</label>
									<div style="display: flex; gap: 6px;">
										<input type="text" id="wpat_qr_custom_logo_url" placeholder="https://..." class="regular-text" style="height: 36px; border-radius: 6px; flex: 1;" />
										<button type="button" class="button" id="wpat_qr_upload_logo_btn" style="height: 36px;">Seleccionar</button>
									</div>
								</div>
							</div>
							<p class="description" style="margin-top: 6px; font-size: 11.5px; color: #64748b;">
								💡 Al insertar un logotipo central, el generador eleva automáticamente la corrección de errores al nivel <strong>H (30%)</strong> para garantizar una lectura 100% fiable con cualquier escáner o smartphone.
							</p>
						</div>

					</div>

					<!-- COLUMNA DERECHA: PREVISUALIZACIÓN EN VIVO & DESCARGAS -->
					<div class="wpat-qr-preview-sidebar" style="position: sticky; top: 40px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); text-align: center;">
						<h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px;">
							👁️ Vista Previa en Vivo
						</h4>

						<!-- Caja de renderizado del QR -->
						<div id="wpat_qr_live_box_container" style="background: #ffffff; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 16px; margin: 0 auto 16px auto; display: inline-flex; align-items: center; justify-content: center; min-width: 220px; min-height: 220px; box-sizing: border-box; position: relative;">
							<div id="wpat_qr_canvas_holder" style="width: 200px; height: 200px; position: relative; display: flex; align-items: center; justify-content: center;">
								<!-- Canvas generado por QRCode.js -->
							</div>
						</div>

						<!-- Detalle del contenido escaneable -->
						<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin-bottom: 16px; font-size: 11.5px; color: #475569; text-align: left; word-break: break-all; max-height: 50px; overflow-y: auto;">
							<strong style="color: #0f172a;">Destino:</strong> <span id="wpat_qr_preview_text_label">-</span>
						</div>

						<!-- Botones de Descarga -->
						<div style="display: flex; gap: 8px; margin-bottom: 12px;">
							<button type="button" class="button button-primary" id="wpat_qr_download_png_btn" style="background: #059669; border-color: #047857; font-weight: 700; height: 38px; flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
								📥 Descargar PNG
							</button>
							<button type="button" class="button button-secondary" id="wpat_qr_download_svg_btn" style="font-weight: 700; height: 38px; flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
								📐 Descargar SVG
							</button>
						</div>

						<!-- Botón Guardar en Biblioteca -->
						<button type="button" class="button button-primary" id="wpat_qr_save_to_library_btn" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 40px; width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13.5px; margin-bottom: 8px;">
							💾 Guardar en Biblioteca de QRs
						</button>

						<button type="button" class="button button-link" id="wpat_qr_reset_builder_btn" style="color: #64748b; font-size: 12px; text-decoration: none;">
							✨ Limpiar formulario / Nuevo QR
						</button>
					</div>

				</div>

				<!-- TABLA DE CÓDIGOS QR GUARDADOS (BIBLIOTECA) -->
				<div class="wpat-saved-qrs-section" style="margin-top: 30px; border-top: 2px solid #e2e8f0; padding-top: 24px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
						<div>
							<h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #0f172a;">
								🗃️ Biblioteca de Códigos QR Guardados
							</h3>
							<p style="margin: 2px 0 0 0; font-size: 12.5px; color: #64748b;">
								Gestiona tus códigos QR, cópialos en tu web mediante Shortcode <code>[wpat_qr id="..."]</code> o descárgalos cuando los necesites.
							</p>
						</div>
					</div>

					<div style="background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
						<table class="wp-list-table widefat fixed striped" style="border: none; margin: 0;">
							<thead>
								<tr>
									<th style="width: 50px; text-align: center; font-weight: 700;">QR</th>
									<th style="font-weight: 700;">Nombre & Contenido</th>
									<th style="width: 140px; font-weight: 700;">Tipo</th>
									<th style="width: 220px; font-weight: 700;">Shortcode Embebible</th>
									<th style="width: 130px; font-weight: 700;">Actualizado</th>
									<th style="width: 140px; text-align: right; font-weight: 700;">Acciones</th>
								</tr>
							</thead>
							<tbody id="wpat_saved_qrs_tbody">
								<?php $qr_module->render_saved_qrs_table( $saved_qrs ); ?>
							</tbody>
						</table>
					</div>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Renderiza la interfaz de configuración del Diseño Moderno SaaS para Listados & CPTs.
	 *
	 * @param array $settings Ajustes del plugin.
	 */
	public function render_admin_tables_ui_content( $settings ) {
		if ( ! class_exists( 'WPAT_Admin_Tables_UI' ) ) {
			require_once WPAT_PATH . 'includes/modules/class-wpat-admin-tables-ui.php';
		}

		$all_post_types = WPAT_Admin_Tables_UI::get_supported_post_types();
		$enabled_pts    = isset( $settings['tables_ui_post_types'] ) && is_array( $settings['tables_ui_post_types'] )
			? $settings['tables_ui_post_types']
			: array( 'post', 'page', 'product' );

		$density        = isset( $settings['tables_ui_density'] ) ? $settings['tables_ui_density'] : 'comfortable';
		$hover_effect   = isset( $settings['tables_ui_hover'] ) ? $settings['tables_ui_hover'] : 'subtle';
		$actions_style  = isset( $settings['tables_ui_actions_style'] ) ? $settings['tables_ui_actions_style'] : 'modern';
		$pills_enabled  = ! isset( $settings['tables_ui_pills'] ) || '1' === (string) $settings['tables_ui_pills'];
		$thumbs_enabled = isset( $settings['tables_ui_show_thumbs'] ) && '1' === (string) $settings['tables_ui_show_thumbs'];
		$zoom_enabled   = ! isset( $settings['tables_ui_thumb_zoom'] ) || '1' === (string) $settings['tables_ui_thumb_zoom'];
		$sticky_enabled = isset( $settings['tables_ui_sticky_header'] ) && '1' === (string) $settings['tables_ui_sticky_header'];
		?>
		<div class="wpat-module-card wpat-tables-ui-wrapper">
			<div class="wpat-module-header">
				<div class="wpat-module-info">
					<h3>Diseño Moderno SaaS para Listados & CPTs <span class="wpat-badge" style="background:#2563eb; color:#fff;">UX / UI SaaS</span></h3>
					<p>Transforma los toscos listados clásicos de WordPress (edit.php) en una interfaz moderna, limpia y ergonómica estilo Stripe/Linear, con total compatibilidad con plugins de terceros.</p>
				</div>
				<?php $this->render_module_toggle( 'admin-tables-ui', $settings, true ); ?>
			</div>

			<div class="wpat-module-body" style="display: block; padding: 22px;">

				<form method="post" action="">
					<?php wp_nonce_field( 'wpat_save_settings_action', 'wpat_settings_nonce' ); ?>
					<input type="hidden" name="wpat_save_settings_btn" value="1" />
					<input type="hidden" name="wpat_saving_module" value="admin-tables-ui" />

					<!-- SECCIÓN 1: SELECCIÓN DE TIPOS DE CONTENIDO -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
						<h4 style="margin: 0 0 6px 0; font-size: 14px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
							🎯 Tipos de Contenido (Post Types) con Diseño SaaS
						</h4>
						<p style="margin: 0 0 16px 0; font-size: 12.5px; color: #64748b;">
							Selecciona en qué pantallas de administración deseas aplicar la interfaz SaaS moderna. Compatible al 100% con columnas de WooCommerce, Yoast, RankMath, ACF y JetEngine.
						</p>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
							<?php
							foreach ( $all_post_types as $pt_name => $pt_obj ) :
								$is_checked = in_array( $pt_name, $enabled_pts, true );
								$icon = '📝';
								if ( 'page' === $pt_name ) {
									$icon = '📄';
								} elseif ( 'product' === $pt_name ) {
									$icon = '🛍️';
								} elseif ( strpos( $pt_name, 'jet-' ) !== false ) {
									$icon = '⚡';
								} elseif ( strpos( $pt_name, 'elementor' ) !== false ) {
									$icon = '🎨';
								}
								?>
								<label style="display: flex; align-items: center; gap: 8px; background: #ffffff; border: 1px solid <?php echo $is_checked ? '#2563eb' : '#cbd5e1'; ?>; padding: 10px 14px; border-radius: 8px; cursor: pointer; transition: all 0.15s ease;">
									<input type="checkbox" name="wpat_settings[tables_ui_post_types][]" value="<?php echo esc_attr( $pt_name ); ?>" <?php checked( $is_checked ); ?> />
									<span style="font-weight: 700; font-size: 13px; color: #1e293b;">
										<?php echo esc_html( $icon . ' ' . $pt_obj->labels->name ); ?>
									</span>
									<span style="font-size: 11px; color: #94a3b8; margin-left: auto;">
										(<?php echo esc_html( $pt_name ); ?>)
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- SECCIÓN 2: OPCIONES VISUALES Y ERGONOMÍA -->
					<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px;">
						<h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 800; color: #0f172a;">
							🎨 Personalización Visual & Ergonomía de las Tablas
						</h4>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
							
							<!-- Densidad de Fila -->
							<div class="wpat-field-group">
								<label for="tables_ui_density" style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 6px;">
									📏 Densidad y Espaciado de Filas
								</label>
								<select name="wpat_settings[tables_ui_density]" id="tables_ui_density" style="width: 100%; height: 38px; border-radius: 6px;">
									<option value="comfortable" <?php selected( $density, 'comfortable' ); ?>>Holgado / Cómodo (Recomendado SaaS - 14px padding)</option>
									<option value="compact" <?php selected( $density, 'compact' ); ?>>Compacto (Mayor densidad de elementos en pantalla)</option>
								</select>
								<p class="description" style="margin-top: 4px;">El modo holgado ofrece una lectura mucho más limpia y descansada para la vista.</p>
							</div>

							<!-- Efecto Hover -->
							<div class="wpat-field-group">
								<label for="tables_ui_hover" style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 6px;">
									✨ Efecto al Pasar el Ratón (Hover)
								</label>
								<select name="wpat_settings[tables_ui_hover]" id="tables_ui_hover" style="width: 100%; height: 38px; border-radius: 6px;">
									<option value="subtle" <?php selected( $hover_effect, 'subtle' ); ?>>Sutil (Cambio suave de color de fondo)</option>
									<option value="bordered" <?php selected( $hover_effect, 'bordered' ); ?>>Borde de Acento (Línea lateral azul activa)</option>
									<option value="shadow" <?php selected( $hover_effect, 'shadow' ); ?>>Elevación con Sombra (Efecto tarjeta flotante)</option>
								</select>
								<p class="description" style="margin-top: 4px;">Resalta visualmente la fila sobre la que estás trabajando.</p>
							</div>

							<!-- Estilo de Acciones de Fila -->
							<div class="wpat-field-group">
								<label for="tables_ui_actions_style" style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 6px;">
									🔘 Acciones de Fila (Editar / Ver / Papelera)
								</label>
								<select name="wpat_settings[tables_ui_actions_style]" id="tables_ui_actions_style" style="width: 100%; height: 38px; border-radius: 6px;">
									<option value="modern" <?php selected( $actions_style, 'modern' ); ?>>Botones Modernos con Colores Semánticos</option>
									<option value="native" <?php selected( $actions_style, 'native' ); ?>>Enlaces Nativos Estilizados</option>
								</select>
								<p class="description" style="margin-top: 4px;">Elimina las barras separadoras anticuadas "|" y aplica botones ergonómicos.</p>
							</div>

						</div>

						<div style="border-top: 1px solid #f1f5f9; margin-top: 20px; padding-top: 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
							
							<!-- Badges de Estado -->
							<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[tables_ui_pills]" value="1" <?php checked( $pills_enabled ); ?> style="margin-top: 3px;" />
								<div>
									<strong style="display: block; font-size: 13px; color: #1e293b;">Badges de Estado Modernos (Pills)</strong>
									<span style="font-size: 12px; color: #64748b;">
										Transforma "— Borrador", "— Privada" y "— Programada" en elegantes etiquetas redondeadas de color semántico (Verde, Ámbar, Azul, Violeta).
									</span>
								</div>
							</label>

							<!-- Columna de Miniaturas -->
							<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[tables_ui_show_thumbs]" value="1" <?php checked( $thumbs_enabled ); ?> style="margin-top: 3px;" />
								<div>
									<strong style="display: block; font-size: 13px; color: #1e293b;">Columna de Imagen Destacada</strong>
									<span style="font-size: 12px; color: #64748b;">
										Añade automáticamente una columna visual con la miniatura de la entrada o página en los post types que no dispongan de ella.
									</span>
								</div>
							</label>

							<!-- Zoom de Miniaturas -->
							<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[tables_ui_thumb_zoom]" value="1" <?php checked( $zoom_enabled ); ?> style="margin-top: 3px;" />
								<div>
									<strong style="display: block; font-size: 13px; color: #1e293b;">Previsualización Ampliada de Imágenes (Hover Zoom)</strong>
									<span style="font-size: 12px; color: #64748b;">
										Muestra una ventana emergente en alta resolución de la imagen al pasar el cursor sobre la miniatura en la tabla.
									</span>
								</div>
							</label>

							<!-- Cabecera Fija -->
							<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
								<input type="checkbox" name="wpat_settings[tables_ui_sticky_header]" value="1" <?php checked( $sticky_enabled ); ?> style="margin-top: 3px;" />
								<div>
									<strong style="display: block; font-size: 13px; color: #1e293b;">Cabecera Fija al Hacer Scroll (Sticky Header)</strong>
									<span style="font-size: 12px; color: #64748b;">
										Mantiene visible la fila de títulos de columna en la parte superior cuando te desplazas por listados largos de entradas o productos.
									</span>
								</div>
							</label>

						</div>
					</div>

					<!-- BOTÓN GUARDAR AJUSTES -->
					<div style="display: flex; justify-content: flex-end; gap: 10px;">
						<button type="submit" class="button button-primary" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 38px; padding: 0 20px; font-size: 13.5px;">
							Guardar Configuración de Listados SaaS
						</button>
					</div>

				</form>

			</div>
		</div>
		<?php
	}
}



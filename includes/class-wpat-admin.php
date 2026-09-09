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
	}

	/**
	 * Añade el menú del plugin a la administración de WordPress.
	 */
	public function add_admin_menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" fill="currentColor"><path d="M512.1 191l-8.2 14.3c-3 5.3-9.4 7.5-15.1 5.4-11.8-4.4-22.6-10.7-32.1-18.6-4.6-3.8-5.8-10.5-2.8-15.7l8.2-14.3c-6.9-8-12.3-17.3-15.9-27.4h-16.5c-6 0-11.2-4.3-12.2-10.3-2-12-2.1-24.6 0-37.1 1-6 6.2-10.4 12.2-10.4h16.5c3.6-10.1 9-19.4 15.9-27.4l-8.2-14.3c-3-5.2-1.9-11.9 2.8-15.7-9.5-7.9-20.4-14.2-32.1-18.6-5.7-2.1 12.1.1 15.1 5.4l8.2 14.3c10.5-1.9 21.2-1.9 31.7 0L552 6.3c3-5.3 9.4-7.5 15.1-5.4 11.8 4.4 22.6 10.7 32.1 18.6 4.6 3.8 5.8 10.5 2.8 15.7l-8.2 14.3c-6.9 8-12.3 17.3-15.9 27.4h16.5c6 0 11.2 4.3 12.2 10.3 2 12 2.1 24.6 0 37.1-1 6-6.2 10.4-12.2 10.4h-16.5c-3.6 10.1-9 19.4-15.9 27.4l8.2 14.3c3 5.2 1.9 11.9-2.8 15.7-9.5 7.9-20.4 14.2-32.1 18.6-5.7 2.1-12.1-.1-15.1-5.4l-8.2-14.3c-10.4 1.9-21.2 1.9-31.7 0zm-10.5-58.8c38.5 29.6 82.4-14.3 52.8-52.8-38.5-29.7-82.4 14.3-52.8 52.8zM386.3 286.1l33.7 16.8c10.1 5.8 14.5 18.1 10.5 29.1-8.9 24.2-26.4 46.4-42.6 65.8-7.4 8.9-20.2 11.1-30.3 5.3l-29.1-16.8c-16 13.7-34.6 24.6-54.9 31.7v33.6c0 11.6-8.3 21.6-19.7 23.6-24.6 4.2-50.4 4.4-75.9 0-11.5-2-20-11.9-20-23.6V418c-20.3-7.2-38.9-18-54.9-31.7L74 403c-10 5.8-22.9 3.6-30.3-5.3-16.2-19.4-33.3-41.6-42.2-65.7-4-10.9.4-23.2 10.5-29.1l33.3-16.8c-3.9-20.9-3.9-42.4 0-63.4L12 205.8c-10.1-5.8-14.6-18.1-10.5-29 8.9-24.2 26-46.4 42.2-65.8 7.4-8.9 20.2-11.1 30.3-5.3l29.1 16.8c16-13.7 34.6-24.6 54.9-31.7V57.1c0-11.5 8.2-21.5 19.6-23.5 24.6-4.2 50.5-4.4 76-.1 11.5 2 20 11.9 20 23.6v33.6c20.3 7.2 38.9 18 54.9 31.7l29.1-16.8c10-5.8 22.9-3.6 30.3 5.3 16.2 19.4 33.2 41.6 42.1 65.8 4 10.9.1 23.2-10 29.1l-33.7 16.8c3.9 21 3.9 42.5 0 63.5zm-117.6 21.1c59.2-77-28.7-164.9-105.7-105.7-59.2 77 28.7 164.9 105.7 105.7zm243.4 182.7l-8.2 14.3c-3 5.3-9.4 7.5-15.1 5.4-11.8-4.4-22.6-10.7-32.1-18.6-4.6-3.8-5.8-10.5-2.8-15.7l8.2-14.3c-6.9-8-12.3-17.3-15.9-27.4h-16.5c-6 0-11.2-4.3-12.2-10.3-2-12-2.1-24.6 0-37.1 1-6 6.2-10.4 12.2-10.4h16.5c3.6-10.1 9-19.4 15.9-27.4l-8.2-14.3c-3-5.2-1.9-11.9 2.8-15.7 9.5-7.9 20.4-14.2 32.1-18.6 5.7-2.1 12.1.1 15.1 5.4l8.2 14.3c10.5-1.9 21.2-1.9 31.7 0l8.2-14.3c3-5.3 9.4-7.5 15.1-5.4 11.8 4.4 22.6 10.7 32.1 18.6 4.6 3.8 5.8 10.5 2.8 15.7l-8.2 14.3c-6.9 8-12.3-17.3-15.9-27.4h16.5c6 0 11.2 4.3 12.2 10.3 2 12 2.1 24.6 0 37.1-1 6-6.2 10.4-12.2 10.4h-16.5c-3.6 10.1-9 19.4-15.9 27.4l8.2 14.3c3.6 5.2 1.9 11.9-2.8 15.7-9.5 7.9-20.4 14.2-32.1 18.6-5.7 2.1-12.1-.1-15.1-5.4l-8.2-14.3c-10.4 1.9-21.2 1.9-31.7 0zM501.6 431c38.5 29.6 82.4-14.3 52.8-52.8-38.5-29.6-82.4 14.3-52.8 52.8z" /></svg>' );

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

		$settings = WPAT_Main::get_instance()->get_settings();

		if ( isset( $settings['woo-extra-options'] ) && '1' === $settings['woo-extra-options'] ) {
			add_submenu_page(
				'wp-agency-toolkit',
				'Campos Extras Woo',
				'Campos Extras Woo',
				'manage_options',
				'wpat-woo-extra-options',
				array( $this, 'render_admin_page' )
			);
		}

		if ( isset( $settings['snippets'] ) && '1' === $settings['snippets'] ) {
			add_submenu_page(
				'wp-agency-toolkit',
				'Snippets de Código',
				'Snippets de Código',
				'manage_options',
				'wpat-snippets',
				array( $this, 'render_admin_page' )
			);
		}

		if ( isset( $settings['woo-pdf-invoices'] ) && '1' === $settings['woo-pdf-invoices'] ) {
			add_submenu_page(
				'wp-agency-toolkit',
				'Facturación PDF',
				'Facturación PDF',
				'manage_options',
				'wpat-woo-pdf-invoices',
				array( $this, 'render_admin_page' )
			);
		}

		if ( isset( $settings['login-customizer'] ) && '1' === $settings['login-customizer'] ) {
			add_submenu_page(
				'wp-agency-toolkit',
				'Personalizador Login',
				'Personalizador Login',
				'manage_options',
				'wpat-login-customizer',
				array( $this, 'render_admin_page' )
			);
		}

		if ( isset( $settings['seo'] ) && '1' === $settings['seo'] ) {
			add_submenu_page(
				'wp-agency-toolkit',
				'Optimización SEO',
				'Optimización SEO',
				'manage_options',
				'wpat-seo',
				array( $this, 'render_admin_page' )
			);
		}

		add_submenu_page(
			'wp-agency-toolkit',
			'Salud & Herramientas',
			'Salud & Herramientas',
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
			'whatsapp',
			'reading-progress',
			'conflict-detector',
			'accessibility',
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
		$new_settings['google_drive_token']           = isset( $input_settings['google_drive_token'] ) ? sanitize_text_field( $input_settings['google_drive_token'] ) : '';
		$new_settings['google_drive_folder']          = isset( $input_settings['google_drive_folder'] ) ? sanitize_text_field( $input_settings['google_drive_folder'] ) : '';
		$new_settings['dropbox_token']                = isset( $input_settings['dropbox_token'] ) ? sanitize_text_field( $input_settings['dropbox_token'] ) : '';
		$new_settings['onedrive_token']               = isset( $input_settings['onedrive_token'] ) ? sanitize_text_field( $input_settings['onedrive_token'] ) : '';

		// 9. Sanitizar WhatsApp
		$new_settings['whatsapp']          = isset( $input_settings['whatsapp'] ) && '1' === $input_settings['whatsapp'] ? '1' : '0';
		$new_settings['whatsapp_enabled']  = isset( $input_settings['whatsapp_enabled'] ) && '1' === $input_settings['whatsapp_enabled'] ? '1' : '0';
		$new_settings['whatsapp_phone']    = isset( $input_settings['whatsapp_phone'] ) ? sanitize_text_field( $input_settings['whatsapp_phone'] ) : '';
		$new_settings['whatsapp_message']  = isset( $input_settings['whatsapp_message'] ) ? sanitize_text_field( $input_settings['whatsapp_message'] ) : '¡Hola! Quisiera más información.';
		$new_settings['whatsapp_position'] = isset( $input_settings['whatsapp_position'] ) && in_array( $input_settings['whatsapp_position'], array( 'bottom-right', 'bottom-left' ), true ) ? $input_settings['whatsapp_position'] : 'bottom-right';
		$new_settings['whatsapp_tooltip']  = isset( $input_settings['whatsapp_tooltip'] ) ? sanitize_text_field( $input_settings['whatsapp_tooltip'] ) : '';
		$new_settings['whatsapp_agents']   = isset( $input_settings['whatsapp_agents'] ) ? sanitize_textarea_field( $input_settings['whatsapp_agents'] ) : '';

		// 10. Sanitizar Barra y Tiempo de Lectura
		$new_settings['reading-progress']     = isset( $input_settings['reading-progress'] ) && '1' === $input_settings['reading-progress'] ? '1' : '0';
		$new_settings['reading_bar_enabled']  = isset( $input_settings['reading_bar_enabled'] ) && '1' === $input_settings['reading_bar_enabled'] ? '1' : '0';
		$new_settings['reading_bar_color']    = isset( $input_settings['reading_bar_color'] ) && preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $input_settings['reading_bar_color'] ) ? $input_settings['reading_bar_color'] : '#2563eb';
		$new_settings['reading_bar_height']   = isset( $input_settings['reading_bar_height'] ) ? max( 1, min( 30, absint( $input_settings['reading_bar_height'] ) ) ) : 4;
		$new_settings['reading_time_enabled'] = isset( $input_settings['reading_time_enabled'] ) && '1' === $input_settings['reading_time_enabled'] ? '1' : '0';

		// 11. Sanitizar Detector de Incompatibilidades
		$new_settings['conflict-detector'] = isset( $input_settings['conflict-detector'] ) && '1' === $input_settings['conflict-detector'] ? '1' : '0';

		// 12. Sanitizar Herramientas de Accesibilidad
		$new_settings['accessibility']                   = isset( $input_settings['accessibility'] ) && '1' === $input_settings['accessibility'] ? '1' : '0';
		$new_settings['accessibility_enabled']           = isset( $input_settings['accessibility_enabled'] ) && '1' === $input_settings['accessibility_enabled'] ? '1' : '0';
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

		// 13. Sanitizar Editor de Campos de Checkout (WooCommerce)
		$new_settings['woo-checkout-editor']      = isset( $input_settings['woo-checkout-editor'] ) && '1' === $input_settings['woo-checkout-editor'] ? '1' : '0';
		$new_settings['checkout_editor_enabled']  = isset( $input_settings['checkout_editor_enabled'] ) && '1' === $input_settings['checkout_editor_enabled'] ? '1' : '0';
		$new_settings['checkout_nif_enabled']     = isset( $input_settings['checkout_nif_enabled'] ) && '1' === $input_settings['checkout_nif_enabled'] ? '1' : '0';
		$new_settings['checkout_nif_required']    = isset( $input_settings['checkout_nif_required'] ) && '1' === $input_settings['checkout_nif_required'] ? '1' : '0';
		$new_settings['checkout_nif_position']    = isset( $input_settings['checkout_nif_position'] ) && in_array( $input_settings['checkout_nif_position'], array( 'after_names', 'after_company', 'at_end' ), true ) ? $input_settings['checkout_nif_position'] : 'after_names';

		$new_settings['checkout_disabled_fields'] = isset( $input_settings['checkout_disabled_fields'] ) && is_array( $input_settings['checkout_disabled_fields'] ) ? array_map( 'sanitize_key', $input_settings['checkout_disabled_fields'] ) : array();

		$custom_fields_clean = array();
		if ( isset( $input_settings['checkout_custom_fields'] ) && is_array( $input_settings['checkout_custom_fields'] ) ) {
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
		}
		$new_settings['checkout_custom_fields'] = $custom_fields_clean;

		// 14. Sanitizar Opciones Extra y Swatches de Producto (WooCommerce)
		$new_settings['woo-extra-options']     = isset( $input_settings['woo-extra-options'] ) && '1' === $input_settings['woo-extra-options'] ? '1' : '0';
		$new_settings['extra_options_enabled'] = isset( $input_settings['extra_options_enabled'] ) && '1' === $input_settings['extra_options_enabled'] ? '1' : '0';

		$rules_clean = array();
		if ( isset( $input_settings['extra_options_rules'] ) && is_array( $input_settings['extra_options_rules'] ) ) {
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
		}
		$new_settings['extra_options_rules'] = $rules_clean;

		// 15. Sanitizar Swatches de Variación de Producto (WooCommerce)
		$new_settings['woo-variation-swatches']    = isset( $input_settings['woo-variation-swatches'] ) && '1' === $input_settings['woo-variation-swatches'] ? '1' : '0';
		$new_settings['variation_swatches_enabled'] = isset( $input_settings['variation_swatches_enabled'] ) && '1' === $input_settings['variation_swatches_enabled'] ? '1' : '0';
		$new_settings['variation_swatches_shape']   = isset( $input_settings['variation_swatches_shape'] ) && in_array( $input_settings['variation_swatches_shape'], array( 'round', 'square' ), true ) ? $input_settings['variation_swatches_shape'] : 'round';
		$new_settings['variation_swatches_colors']  = isset( $input_settings['variation_swatches_colors'] ) ? sanitize_textarea_field( $input_settings['variation_swatches_colors'] ) : '';

		// 15. Sanitizar Facturas PDF y Albaranes Automáticos (WooCommerce)
		$new_settings['woo-pdf-invoices']     = isset( $input_settings['woo-pdf-invoices'] ) && '1' === $input_settings['woo-pdf-invoices'] ? '1' : '0';
		$new_settings['pdf_invoices_enabled'] = isset( $input_settings['pdf_invoices_enabled'] ) && '1' === $input_settings['pdf_invoices_enabled'] ? '1' : '0';
		$new_settings['pdf_company_name']     = isset( $input_settings['pdf_company_name'] ) ? sanitize_text_field( $input_settings['pdf_company_name'] ) : get_bloginfo( 'name' );
		$new_settings['pdf_company_nif']      = isset( $input_settings['pdf_company_nif'] ) ? sanitize_text_field( $input_settings['pdf_company_nif'] ) : '';
		$new_settings['pdf_company_address']  = isset( $input_settings['pdf_company_address'] ) ? sanitize_textarea_field( $input_settings['pdf_company_address'] ) : '';
		$new_settings['pdf_company_footer']   = isset( $input_settings['pdf_company_footer'] ) ? sanitize_textarea_field( $input_settings['pdf_company_footer'] ) : '';
		$new_settings['pdf_invoice_prefix']   = isset( $input_settings['pdf_invoice_prefix'] ) ? sanitize_text_field( $input_settings['pdf_invoice_prefix'] ) : 'FACT-' . date( 'Y' ) . '-';
		$new_settings['pdf_invoice_next_num'] = isset( $input_settings['pdf_invoice_next_num'] ) ? max( 1, absint( $input_settings['pdf_invoice_next_num'] ) ) : 1;

		// 16. Sanitizar Buscador AJAX en Vivo de WooCommerce (Frontend)
		$new_settings['woo-live-search']          = isset( $input_settings['woo-live-search'] ) && '1' === $input_settings['woo-live-search'] ? '1' : '0';
		$new_settings['live_search_enabled']      = isset( $input_settings['live_search_enabled'] ) && '1' === $input_settings['live_search_enabled'] ? '1' : '0';
		$new_settings['live_search_max_results']  = isset( $input_settings['live_search_max_results'] ) ? min( 8, max( 1, absint( $input_settings['live_search_max_results'] ) ) ) : 5;
		$new_settings['live_search_show_thumb']   = isset( $input_settings['live_search_show_thumb'] ) && '1' === $input_settings['live_search_show_thumb'] ? '1' : '0';
		$new_settings['live_search_show_price']   = isset( $input_settings['live_search_show_price'] ) && '1' === $input_settings['live_search_show_price'] ? '1' : '0';
		$new_settings['live_search_show_stock']   = isset( $input_settings['live_search_show_stock'] ) && '1' === $input_settings['live_search_show_stock'] ? '1' : '0';
		$new_settings['live_search_show_meta']    = isset( $input_settings['live_search_show_meta'] ) && in_array( $input_settings['live_search_show_meta'], array( 'sku', 'cat', 'both', 'none' ), true ) ? $input_settings['live_search_show_meta'] : 'sku';
		$new_settings['live_search_auto_replace'] = isset( $input_settings['live_search_auto_replace'] ) && '1' === $input_settings['live_search_auto_replace'] ? '1' : '0';
		$new_settings['live_search_placeholder']  = isset( $input_settings['live_search_placeholder'] ) ? sanitize_text_field( $input_settings['live_search_placeholder'] ) : 'Buscar productos por nombre, SKU o categoría...';

		// 17. Sanitizar Filtro por Facetas AJAX de WooCommerce (Estilo FacetWP)
		$new_settings['woo-facets']     = isset( $input_settings['woo-facets'] ) && '1' === $input_settings['woo-facets'] ? '1' : '0';
		$new_settings['facets_enabled'] = isset( $input_settings['facets_enabled'] ) && '1' === $input_settings['facets_enabled'] ? '1' : '0';
		$new_settings['facets_config']  = isset( $input_settings['facets_config'] ) && is_array( $input_settings['facets_config'] ) ? array_map( 'sanitize_key', $input_settings['facets_config'] ) : array( 'sort', 'price', 'category', 'stock', 'rating' );

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

		// Determinar si se ha solicitado ver la pantalla de un módulo individual (Standalone View)
		$mod_id = '';
		if ( isset( $_GET['mod'] ) && ! empty( $_GET['mod'] ) ) {
			$mod_id = sanitize_key( $_GET['mod'] );
		} elseif ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'wpat-' ) === 0 ) {
			$page_slug = sanitize_key( $_GET['page'] );
			$map = array(
				'wpat-woo-extra-options' => 'woo-extra-options',
				'wpat-snippets'          => 'snippets',
				'wpat-woo-pdf-invoices'  => 'woo-pdf-invoices',
				'wpat-login-customizer'  => 'login-customizer',
				'wpat-seo'               => 'seo',
				'wpat-tools'             => 'tools',
			);
			if ( isset( $map[ $page_slug ] ) ) {
				$mod_id = $map[ $page_slug ];
			}
		}

		$is_single_module_view = ! empty( $mod_id );
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
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit' ) ); ?>" class="button button-secondary" style="background: #f6f7f7; border-color: #cbd5e1; color: #1e293b; font-weight: 700; border-radius: 6px; height: 34px; line-height: 32px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
							<span class="dashicons dashicons-arrow-left-alt" style="font-size: 16px; width: 16px; height: 16px; margin: 0; line-height: 1;"></span> Volver al Centro de Módulos
						</a>
						<button type="submit" class="button button-primary" style="background: #2271b1; border-color: #135e96; font-weight: 700; height: 34px; line-height: 32px; padding: 0 20px; border-radius: 6px;">
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
								<div class="wpat-dashboard-toolbar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
									<div>
										<h2 style="margin:0 0 4px 0; font-size:20px; font-weight:700;">Centro de Módulos & Herramientas</h2>
										<p class="section-desc" style="margin:0; color:#646970;">Activa o desactiva utilidades de forma independiente para mantener tu sitio rápido y ligero.</p>
									</div>
									<div class="wpat-search-box" style="position: relative; min-width: 260px;">
										<span class="dashicons dashicons-search" style="position: absolute; left: 10px; top: 9px; color: #94a3b8; font-size: 16px;"></span>
										<input type="text" id="wpat_modules_search_input" placeholder="Buscar módulo..." style="padding-left: 32px; width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; height: 34px; font-size: 13px;" />
									</div>
								</div>

								<!-- FILTROS POR CATEGORÍAS (10 CATEGORÍAS COMPLETAS) -->
								<div class="wpat-cat-filters" style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; overflow-x: auto; white-space: nowrap;">
									<button type="button" class="wpat-cat-pill active" data-cat="all">Todos (35)</button>
									<button type="button" class="wpat-cat-pill" data-cat="security">Seguridad & Acceso (7)</button>
									<button type="button" class="wpat-cat-pill" data-cat="woocommerce">WooCommerce (10)</button>
									<button type="button" class="wpat-cat-pill" data-cat="performance">Rendimiento & Código (4)</button>
									<button type="button" class="wpat-cat-pill" data-cat="media">Optimización de Medios (2)</button>
									<button type="button" class="wpat-cat-pill" data-cat="kits">Importador de Kits (1)</button>
									<button type="button" class="wpat-cat-pill" data-cat="smtp">Configuración SMTP (1)</button>
									<button type="button" class="wpat-cat-pill" data-cat="seo">Optimización SEO (1)</button>
									<button type="button" class="wpat-cat-pill" data-cat="integrations">Integraciones (2)</button>
									<button type="button" class="wpat-cat-pill" data-cat="initial">Configuración Inicial (3)</button>
									<button type="button" class="wpat-cat-pill" data-cat="tools">Salud & Herramientas (4)</button>
								</div>

								<div class="wpat-modules-grid-container" id="wpat_modules_grid">
									<?php $this->render_all_modules_grid_cards( $settings ); ?>
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
			// WOOCOMMERCE (10)
			array(
				'id'          => 'woo-extra-options',
				'title'       => 'Campos Extras & Swatches',
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
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Inyecta un campo obligatorio de DNI/CIF en los datos de facturación de WooCommerce.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🆔',
				'icon_bg'     => 'woo',
				'keywords'    => 'dni cif nif woocommerce checkout'
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
				'keywords'    => 'modo catalogo ocultar precios carrito'
			),
			array(
				'id'          => 'woo-zoom',
				'title'       => 'Zoom en Galería',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Desactiva de forma independiente funciones de la galería como Zoom, Lightbox o Slider.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🔎',
				'icon_bg'     => 'woo',
				'keywords'    => 'zoom galeria lightbox slider productos'
			),
			array(
				'id'          => 'woo-variation-swatches',
				'title'       => 'Swatches de Variación',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Convierte los menús desplegables de atributos de variación en botones visuales de color o texto.',
				'cat_class'   => 'cat-woocommerce cat-woo',
				'icon'        => '🎨',
				'icon_bg'     => 'woo',
				'keywords'    => 'swatches variacion colores botones producto'
			),

			// SEGURIDAD & ACCESO (7)
			array(
				'id'          => 'login-customizer',
				'title'       => 'Personalizador del Login',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Personaliza la pantalla de inicio de sesión de WordPress con logo corporativo, colores y estilos.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🔐',
				'icon_bg'     => 'sec',
				'keywords'    => 'personalizador login wp-login logo inicio sesion'
			),
			array(
				'id'          => 'hide-login',
				'title'       => 'Ocultar URL de Login',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Cambia la ruta predeterminada wp-login.php por un slug personalizado para evitar ataques.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🚪',
				'icon_bg'     => 'sec',
				'keywords'    => 'ocultar login slug wp-login acceso'
			),
			array(
				'id'          => 'security-hardening',
				'title'       => 'Fortalecimiento de Seguridad',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Aplica directivas de seguridad para desactivar XML-RPC, ocultar versión WP y restringir archivos.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🛡️',
				'icon_bg'     => 'sec',
				'keywords'    => 'seguridad hardening xmlrpc proteccion'
			),
			array(
				'id'          => 'bot-blocker',
				'title'       => 'Bloqueador de Bots por 404',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Detecta y bloquea IPs sospechosas que generan múltiples errores 404 buscando vulnerabilidades.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🤖',
				'icon_bg'     => 'sec',
				'keywords'    => 'bloqueador bots 404 ips vulnerabilidades'
			),
			array(
				'id'          => 'anti-spam',
				'title'       => 'Protección Anti-Spam',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Bloquea spam en formularios (Elementor, MetForm, Comentarios) con Honeypot sin Captchas molestos.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🚫',
				'icon_bg'     => 'sec',
				'keywords'    => 'anti-spam antispam honeypot formularios comentarios'
			),
			array(
				'id'          => 'ssl-fixer',
				'title'       => 'Forzar SSL & HTTPS',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Fuerza la redirección 301 a HTTPS y corrige automáticamente advertencias de contenido mixto.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🔒',
				'icon_bg'     => 'sec',
				'keywords'    => 'ssl https contenido mixto redireccion 301'
			),
			array(
				'id'          => 'hide_admin_bar',
				'title'       => 'Restringir Barra & Acceso Admin',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Oculta la barra superior negra de WordPress y bloquea el acceso a wp-admin a clientes/suscriptores.',
				'cat_class'   => 'cat-security cat-sec',
				'icon'        => '🚫',
				'icon_bg'     => 'sec',
				'keywords'    => 'restringir barra admin wp-admin acceso clientes'
			),

			// RENDIMIENTO & CÓDIGO (4)
			array(
				'id'          => 'snippets',
				'title'       => 'Snippets de Código',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Gestor ligero de fragmentos PHP, CSS y JS sin editar functions.php con ejecución segura.',
				'cat_class'   => 'cat-performance cat-perf cat-admin',
				'icon'        => '💻',
				'icon_bg'     => 'perf',
				'keywords'    => 'snippets codigo php css js fragmentos'
			),
			array(
				'id'          => 'performance',
				'title'       => 'Optimización de Rendimiento',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Limpieza de cabeceras WP, control de Heartbeat API y desactivación de emojis y embeds innecesarios.',
				'cat_class'   => 'cat-performance cat-perf',
				'icon'        => '⚡',
				'icon_bg'     => 'perf',
				'keywords'    => 'rendimiento performance heartbeat emojis cabeceras'
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
				'id'          => 'duplicator',
				'title'       => 'Duplicador Entradas/Páginas',
				'badge'       => 'Automático',
				'badge_class' => 'tweak',
				'desc'        => 'Duplica entradas, páginas o CPTs con 1-clic conservando la estructura y campos personalizados.',
				'cat_class'   => 'cat-performance cat-perf',
				'icon'        => '📋',
				'icon_bg'     => 'perf',
				'keywords'    => 'duplicar clonar entradas paginas cpts'
			),

			// OPTIMIZACIÓN DE MEDIOS (2)
			array(
				'id'          => 'image-optimizer',
				'title'       => 'Optimización Medios & WebP',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Compresión en lote y conversión automática a formato WebP de nueva generación al subir imágenes.',
				'cat_class'   => 'cat-media cat-perf',
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
				'cat_class'   => 'cat-media',
				'icon'        => '📐',
				'icon_bg'     => 'perf',
				'keywords'    => 'svg vectorial biblioteca medios soporte'
			),

			// IMPORTADOR DE KITS (1)
			array(
				'id'          => 'envato-importer',
				'title'       => 'Importador Kits Template',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Importador masivo de kits de maquetación de Envato Elements y plantillas listas para Elementor.',
				'cat_class'   => 'cat-kits cat-admin',
				'icon'        => '📥',
				'icon_bg'     => 'admin',
				'keywords'    => 'importador kits plantillas envato elementor'
			),

			// CONFIGURACIÓN SMTP (1)
			array(
				'id'          => 'smtp',
				'title'       => 'Servidor SMTP',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Servicio seguro de envío de correo SMTP con soporte TLS/SSL y comprobación de envío.',
				'cat_class'   => 'cat-smtp cat-admin',
				'icon'        => '📧',
				'icon_bg'     => 'admin',
				'keywords'    => 'smtp correo envio email servidor tls ssl'
			),

			// OPTIMIZACIÓN SEO (1)
			array(
				'id'          => 'seo',
				'title'       => 'Optimización SEO Integrada',
				'badge'       => 'Subpágina',
				'badge_class' => 'subpage',
				'desc'        => 'Auditoría SEO on-page, generador de meta etiquetas y solución automática de imágenes sin ALT.',
				'cat_class'   => 'cat-seo cat-perf',
				'icon'        => '🚀',
				'icon_bg'     => 'perf',
				'keywords'    => 'seo optimizacion meta titulos auditoria alt'
			),

			// INTEGRACIONES (2)
			array(
				'id'          => 'integrations',
				'title'       => 'Integraciones & Scripts',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Inyecta código de Google Analytics, Tag Manager, Facebook Pixel y scripts en Head/Body sin plugins.',
				'cat_class'   => 'cat-integrations cat-admin',
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
				'cat_class'   => 'cat-integrations',
				'icon'        => '💬',
				'icon_bg'     => 'woo',
				'keywords'    => 'whatsapp boton flotante contacto chat'
			),

			// CONFIGURACIÓN INICIAL (3)
			array(
				'id'          => 'initial-setup',
				'title'       => 'Configuración Inicial Sitio',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Configura rápidamente zona horaria, enlaces permanentes, página de inicio y limpieza inicial.',
				'cat_class'   => 'cat-initial cat-admin',
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
				'cat_class'   => 'cat-initial cat-admin',
				'icon'        => '📊',
				'icon_bg'     => 'admin',
				'keywords'    => 'escritorio personalizado limpiador widgets soporte'
			),
			array(
				'id'          => 'silent-skin',
				'title'       => 'Ocultar Huella WPAT',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Modo Marca Blanca para agencias: oculta las menciones de WP Agency Toolkit a los clientes final.',
				'cat_class'   => 'cat-initial cat-admin',
				'icon'        => '🎭',
				'icon_bg'     => 'admin',
				'keywords'    => 'marca blanca marca agencia huella silent skin'
			),

			// SALUD & HERRAMIENTAS (4)
			array(
				'id'          => 'post-csv-importer',
				'title'       => 'Exportar / Importar CSV & JSON',
				'badge'       => 'Herramienta',
				'badge_class' => 'tweak',
				'desc'        => 'Respalda o migra contenidos completos en JSON o edita páginas/entradas en masa vía CSV.',
				'cat_class'   => 'cat-tools cat-admin',
				'icon'        => '📊',
				'icon_bg'     => 'admin',
				'keywords'    => 'exportador importador csv json respaldo migracion'
			),
			array(
				'id'          => 'conflict-detector',
				'title'       => 'Detector de Conflictos',
				'badge'       => 'Herramienta',
				'badge_class' => 'tweak',
				'desc'        => 'Diagnostica incompatibilidades entre plugins, errores PHP fatales y conflictos de JS.',
				'cat_class'   => 'cat-tools',
				'icon'        => '🩺',
				'icon_bg'     => 'admin',
				'keywords'    => 'detector conflictos plugins errores php diagnostico'
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
				'id'          => 'reading-progress',
				'title'       => 'Progreso de Lectura',
				'badge'       => 'Configuración',
				'badge_class' => 'tweak',
				'desc'        => 'Muestra una barra de progreso superior animada al hacer scroll en artículos del blog.',
				'cat_class'   => 'cat-tools',
				'icon'        => '📏',
				'icon_bg'     => 'perf',
				'keywords'    => 'barra progreso lectura scroll blog'
			),
			array(
				'id'          => 'tools',
				'title'       => 'Salud & Limpieza BD',
				'badge'       => 'Herramienta',
				'badge_class' => 'subpage',
				'desc'        => 'Limpieza profunda de la base de datos, escaneo de imágenes huérfanas e informes del sistema.',
				'cat_class'   => 'cat-tools cat-admin',
				'icon'        => '🛠️',
				'icon_bg'     => 'admin',
				'keywords'    => 'salud herramientas base de datos imagenes no usadas'
			),
		);

		foreach ( $modules_data as $mod ) {
			$is_active = ( isset( $settings[ $mod['id'] ] ) && '1' === $settings[ $mod['id'] ] );
			?>
			<div class="wpat-module-grid-card <?php echo esc_attr( $mod['cat_class'] ); ?>" data-name="<?php echo esc_attr( $mod['keywords'] ); ?>">
				<div class="wpat-card-top">
					<div class="wpat-card-icon-box <?php echo esc_attr( $mod['icon_bg'] ); ?>"><?php echo $mod['icon']; ?></div>
					<label class="wpat-toggle-switch">
						<input type="checkbox" class="wpat-ajax-toggle-module" data-module="<?php echo esc_attr( $mod['id'] ); ?>" <?php checked( $is_active ); ?>>
						<span class="wpat-toggle-slider"></span>
					</label>
				</div>
				<div class="wpat-card-content">
					<h3><?php echo esc_html( $mod['title'] ); ?> <span class="wpat-badge-<?php echo esc_attr( $mod['badge_class'] ); ?>"><?php echo esc_html( $mod['badge'] ); ?></span></h3>
					<p><?php echo esc_html( $mod['desc'] ); ?></p>
				</div>
				<div class="wpat-card-bottom">
					<span class="wpat-module-status-indicator <?php echo $is_active ? 'active' : ''; ?>">
						<span class="dot"></span> <span class="text"><?php echo $is_active ? 'Activo' : 'Inactivo'; ?></span>
					</span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&mod=' . $mod['id'] ) ); ?>" class="wpat-card-action-btn primary <?php echo $is_active ? '' : 'disabled'; ?>">
						Ajustes ⚙️
					</a>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Renderiza únicamente la vista aislada / standalone de un módulo individual.
	 */
	public function render_single_module_standalone_view( $mod_id, $settings ) {
		echo '<style>.wpat-module-body { display: block !important; }</style>';

		$modules_mapping = array(
			'login-customizer'       => 'Personalizador del Login',
			'hide-login'             => 'Ocultar URL de Login',
			'security-hardening'     => 'Fortalecimiento de Seguridad (Hardening)',
			'bot-blocker'            => 'Bloqueador de Bots por 404',
			'anti-spam'              => 'Protección Anti-Spam Zero-Bloat',
			'ssl-fixer'              => 'Forzar SSL & Contenido Mixto',
			'hide_admin_bar'         => 'Restringir Barra y Acceso de Admin',
			'woo-extra-options'      => 'Campos extras en productos (Woocommerce)',
			'woo-pdf-invoices'       => 'Facturación y Albaranes PDF',
			'woo-live-search'        => 'Buscador Live AJAX WooCommerce',
			'woo-facets'             => 'Filtros por Facetas',
			'woo-checkout-editor'    => 'Editor de Campos de Checkout (WooCommerce)',
			'woo-dni'                => 'Campo DNI/CIF en el Checkout',
			'woo-catalog'            => 'Modo Catálogo',
			'woo-zoom'               => 'Controles de la Galería de Productos',
			'woo-variation-swatches' => 'Swatches de Variación',
			'performance'            => 'Optimización de Rendimiento',
			'disable-comments'       => 'Deshabilitar Comentarios',
			'duplicator'             => 'Duplicador de Entradas y Páginas',
			'image-optimizer'        => 'Optimización de Medios & WebP',
			'svg-support'            => 'Soporte para Archivos SVG',
			'smtp'                   => 'Configuración Servidor SMTP',
			'integrations'           => 'Integraciones & Scripts',
			'whatsapp'               => 'Botón Flotante de WhatsApp',
			'initial-setup'          => 'Configuración Inicial de Sitio',
			'dashboard_cleaner'      => 'Escritorio Personalizado & Limpiador',
			'silent-skin'            => 'Ocultar Huella WP Agency Toolkit',
			'accessibility'          => 'Accesibilidad Web',
			'reading-progress'       => 'Barra de Progreso de Lectura',
			'conflict-detector'      => 'Detector de Conflictos de Plugins',
		);

		if ( 'snippets' === $mod_id ) {
			$this->render_tab_snippets_content( $settings );
		} elseif ( 'seo' === $mod_id ) {
			$this->render_tab_seo_content( $settings );
		} elseif ( 'tools' === $mod_id ) {
			$this->render_health_tab_content();
		} elseif ( 'envato-importer' === $mod_id || 'kits' === $mod_id ) {
			$this->render_tab_kits_content( $settings );
		} elseif ( 'post-csv-importer' === $mod_id ) {
			$this->render_tab_tools_content( $settings );
		} else {
			$this->render_generic_card_by_id( $mod_id, $settings, $modules_mapping );
		}
	}

	/**
	 * Helper para renderizar los bloques específicos de pestañas/módulos grandes.
	 */
	private function render_tab_snippets_content( $settings ) {
		?>
		<div class="wpat-standalone-module-box" style="background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
				<div>
					<h2 style="margin:0 0 5px 0; font-size:20px; font-weight:700;">Gestor de Snippets de Código</h2>
					<p style="margin:0; color:#64748b; font-size:13px;">Ejecuta fragmentos PHP, CSS y JS sin tocar funciones del tema.</p>
				</div>
				<?php $this->render_module_toggle( 'snippets', $settings, false ); ?>
			</div>
			<?php
			$snippets = get_option( 'wpat_snippets', array() );
			?>
			<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
				<span style="font-weight: 600; font-size: 13px;">Total Snippets: <strong><?php echo esc_html( count( $snippets ) ); ?></strong></span>
				<div style="display: flex; gap: 10px;">
					<button type="button" class="button button-primary" id="wpat_open_add_snippet_modal">+ Crear Snippet</button>
					<button type="submit" name="wpat_export_snippets_only_btn" class="button button-secondary">Exportar Todos (JSON)</button>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_tab_seo_content( $settings ) {
		?>
		<div class="wpat-standalone-module-box" style="background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
				<div>
					<h2 style="margin:0 0 5px 0; font-size:20px; font-weight:700;">Optimización SEO Integrada</h2>
					<p style="margin:0; color:#64748b; font-size:13px;">Auditoría SEO On-Page y autocompletado de meta títulos y descripciones.</p>
				</div>
				<?php $this->render_module_toggle( 'seo', $settings, false ); ?>
			</div>
		</div>
		<?php
	}

	private function render_tab_kits_content( $settings ) {
		?>
		<div class="wpat-standalone-module-box" style="background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
				<div>
					<h2 style="margin:0 0 5px 0; font-size:20px; font-weight:700;">Importador de Kits de Plantillas (Envato)</h2>
					<p style="margin:0; color:#64748b; font-size:13px;">Sube tus kits comprimidos en ZIP e impóortalos directamente en Elementor.</p>
				</div>
				<?php $this->render_module_toggle( 'envato-importer', $settings, false ); ?>
			</div>
		</div>
		<?php
	}

	private function render_tab_tools_content( $settings ) {
		?>
		<div class="wpat-standalone-module-box" style="background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
			<h2 style="margin:0 0 5px 0; font-size:20px; font-weight:700;">Exportación e Importación de Contenidos</h2>
			<p style="margin:0 0 20px 0; color:#64748b; font-size:13px;">Herramientas para respaldar o migrar tu sitio web en formato JSON o CSV.</p>
		</div>
		<?php
	}

	private function render_generic_card_by_id( $mod_id, $settings, $modules_mapping ) {
		$title = isset( $modules_mapping[ $mod_id ] ) ? $modules_mapping[ $mod_id ] : ucfirst( str_replace( array( '-', '_' ), ' ', $mod_id ) );
		?>
		<div class="wpat-standalone-module-box" style="background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:15px;">
				<h2 style="margin:0; font-size:20px; font-weight:700;"><?php echo esc_html( $title ); ?></h2>
				<?php $this->render_module_toggle( $mod_id, $settings, true ); ?>
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

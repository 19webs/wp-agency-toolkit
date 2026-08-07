<?php
/**
 * Módulo: Configuración Inicial - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Initial_Setup {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Initial_Setup
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Initial_Setup
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
	private function __construct() {}

	/**
	 * Ejecuta las tareas seleccionadas de la configuración inicial.
	 *
	 * @param array $actions Acciones a realizar.
	 * @return array Mensajes del resultado de cada acción.
	 */
	public function run( $actions ) {
		$results = array();

		// 1. Eliminar entrada "Hola mundo"
		if ( ! empty( $actions['delete_post'] ) ) {
			$post_id = 1;
			if ( get_post( $post_id ) ) {
				wp_delete_post( $post_id, true ); // Forzar borrado completo sin papelera
				$results[] = 'Entrada por defecto "Hola mundo" eliminada permanentemente.';
			} else {
				// Buscar por título o slug por si acaso
				$posts = get_posts( array(
					'post_type'   => 'post',
					'name'        => 'hola-mundo',
					'numberposts' => 1,
				) );
				if ( ! empty( $posts ) ) {
					wp_delete_post( $posts[0]->ID, true );
					$results[] = 'Entrada por defecto "Hola mundo" eliminada.';
				} else {
					$results[] = 'No se encontró la entrada "Hola mundo" (ya eliminada).';
				}
			}
		}

		// 2. Eliminar página de ejemplo
		if ( ! empty( $actions['delete_page'] ) ) {
			// Buscar la página de ejemplo de WordPress por defecto (slug sample-page o pagina-de-ejemplo)
			$pages = get_posts( array(
				'post_type'   => 'page',
				'post_status' => 'any',
				'name'        => 'sample-page',
				'numberposts' => 1,
			) );
			if ( empty( $pages ) ) {
				$pages = get_posts( array(
					'post_type'   => 'page',
					'post_status' => 'any',
					'name'        => 'pagina-de-ejemplo',
					'numberposts' => 1,
				) );
			}
			if ( ! empty( $pages ) ) {
				wp_delete_post( $pages[0]->ID, true );
				$results[] = 'Página de ejemplo eliminada permanentemente.';
			} else {
				$results[] = 'No se encontró la "Página de ejemplo" (ya eliminada).';
			}
		}

		// 3. Crear estructura de páginas básicas y personalizadas
		$has_standard_pages = ! empty( $actions['create_pages'] ) && ! empty( $actions['pages_list'] );
		$has_custom_pages   = ! empty( $actions['custom_pages'] );

		if ( $has_standard_pages || $has_custom_pages ) {
			$pages_map = array(
				'home'     => array( 'title' => 'Inicio', 'content' => "<!-- wp:paragraph -->\n<p>Bienvenido a la página de inicio.</p>\n<!-- /wp:paragraph -->" ),
				'about'    => array( 'title' => 'Quiénes somos', 'content' => "<!-- wp:paragraph -->\n<p>Descubre quiénes somos.</p>\n<!-- /wp:paragraph -->" ),
				'services' => array( 'title' => 'Servicios', 'content' => "<!-- wp:paragraph -->\n<p>Nuestros servicios profesionales.</p>\n<!-- /wp:paragraph -->" ),
				'contact'  => array( 'title' => 'Contacto', 'content' => "<!-- wp:paragraph -->\n<p>Ponte en contacto con nosotros.</p>\n<!-- /wp:paragraph -->" ),
				'legal'    => array( 'title' => 'Aviso legal', 'content' => "<!-- wp:paragraph -->\n<p>Información de Aviso Legal.</p>\n<!-- /wp:paragraph -->" ),
				'privacy'  => array( 'title' => 'Política de privacidad', 'content' => "<!-- wp:paragraph -->\n<p>Información de Política de Privacidad.</p>\n<!-- /wp:paragraph -->" ),
				'cookies'  => array( 'title' => 'Política de cookies', 'content' => "<!-- wp:paragraph -->\n<p>Información de Política de Cookies.</p>\n<!-- /wp:paragraph -->" ),
			);

			$created_names = array();
			$existing_names = array();
			$home_page_id = 0;

			// A. Crear páginas estándar seleccionadas
			if ( $has_standard_pages ) {
				$pages_to_create = $actions['pages_list']; // Array de claves
				foreach ( $pages_to_create as $key ) {
					if ( isset( $pages_map[ $key ] ) ) {
						$page_data = $pages_map[ $key ];
						$page_check = get_page_by_title( $page_data['title'] );
						if ( ! $page_check ) {
							$new_page_id = wp_insert_post( array(
								'post_title'   => $page_data['title'],
								'post_content' => $page_data['content'],
								'post_status'  => 'publish',
								'post_type'    => 'page',
							) );
							if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
								$created_names[] = $page_data['title'];
								if ( 'home' === $key ) {
									$home_page_id = $new_page_id;
								}
							}
						} else {
							$existing_names[] = $page_data['title'];
							if ( 'home' === $key ) {
								$home_page_id = $page_check->ID;
							}
						}
					}
				}
			}

			// B. Crear páginas personalizadas
			if ( $has_custom_pages ) {
				$custom_input = sanitize_text_field( $actions['custom_pages'] );
				$custom_titles = array_filter( array_map( 'trim', explode( ',', $custom_input ) ) );
				
				foreach ( $custom_titles as $title ) {
					if ( empty( $title ) ) {
						continue;
					}
					$page_check = get_page_by_title( $title );
					if ( ! $page_check ) {
						$new_page_id = wp_insert_post( array(
							'post_title'   => $title,
							'post_content' => "<!-- wp:paragraph -->\n<p>Contenido de " . esc_html( $title ) . ".</p>\n<!-- /wp:paragraph -->",
							'post_status'  => 'publish',
							'post_type'    => 'page',
						) );
						if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
							$created_names[] = $title;
						}
					} else {
						$existing_names[] = $title;
					}
				}
			}

			if ( ! empty( $created_names ) ) {
				$results[] = 'Páginas creadas: ' . implode( ', ', $created_names ) . '.';
			}
			if ( ! empty( $existing_names ) ) {
				$results[] = 'Páginas omitidas (ya existían en el sitio): ' . implode( ', ', $existing_names ) . '.';
			}

			// Establecer página de Inicio como portada de WordPress (solo si se seleccionó en la lista estándar)
			if ( $has_standard_pages && in_array( 'home', $pages_to_create, true ) && $home_page_id > 0 ) {
				$current_front = get_option( 'page_on_front' );
				if ( 'page' === get_option( 'show_on_front' ) && intval( $current_front ) === intval( $home_page_id ) ) {
					$results[] = 'La página "Inicio" ya estaba configurada como portada principal de la web.';
				} else {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', $home_page_id );
					$results[] = 'La página "Inicio" ha sido configurada como portada principal de la web.';
				}
			}
		}

		// 4. Limpiar temas inactivos
		if ( ! empty( $actions['clean_themes'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			$all_themes = wp_get_themes();
			$active_theme_slug = get_stylesheet();
			$deleted_themes = array();

			foreach ( $all_themes as $slug => $theme ) {
				// No borrar el tema activo, ni su tema padre (en caso de tema hijo)
				if ( $slug !== $active_theme_slug && $slug !== $theme->get( 'Template' ) ) {
					$theme_dir = $theme->get_stylesheet_directory();
					if ( is_dir( $theme_dir ) ) {
						// Borrar directorio de forma recursiva
						$this->delete_directory( $theme_dir );
						$deleted_themes[] = $theme->get( 'Name' );
					}
				}
			}

			if ( ! empty( $deleted_themes ) ) {
				$results[] = 'Temas inactivos eliminados: ' . implode( ', ', $deleted_themes ) . '.';
			} else {
				$results[] = 'No se encontraron temas inactivos para eliminar (limpieza omitida).';
			}
		}

		// 5. Instalar y activar Hello Elementor
		if ( ! empty( $actions['install_hello'] ) ) {
			$theme_slug = 'hello-elementor';
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			
			$active_theme = wp_get_theme();
			$is_already_active = ( $active_theme->get_stylesheet() === $theme_slug );
			$theme_exists = wp_get_theme( $theme_slug )->exists();

			if ( $is_already_active ) {
				$results[] = 'El tema "Hello Elementor" ya está instalado y activo.';
			} else {
				$was_installed = $theme_exists;
				if ( ! $theme_exists ) {
					include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
					include_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();

					// Obtener la URL del paquete desde la API oficial de WordPress.org
					$api_url = 'https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]=' . $theme_slug;
					$response = wp_remote_get( $api_url );
					
					if ( ! is_wp_error( $response ) ) {
						$data = json_decode( wp_remote_retrieve_body( $response ), true );
						if ( ! empty( $data['download_link'] ) ) {
							$installer = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
							$installed = $installer->install( $data['download_link'] );
							if ( $installed && ! is_wp_error( $installed ) ) {
								$theme_exists = true;
							}
						}
					}
				}

				if ( $theme_exists ) {
					switch_theme( $theme_slug );
					if ( $was_installed ) {
						$results[] = 'El tema "Hello Elementor" ya estaba instalado y ha sido activado.';
					} else {
						$results[] = 'Tema "Hello Elementor" instalado y activado correctamente.';
					}
				} else {
					$results[] = 'Error al instalar el tema "Hello Elementor" desde WordPress.org.';
				}
			}
		}

		// 6. Instalar y activar el plugin Elementor
		if ( ! empty( $actions['install_elementor'] ) ) {
			$plugin_slug = 'elementor';
			$plugin_file = 'elementor/elementor.php';
			
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugin_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin_file );
			$plugin_active = is_plugin_active( $plugin_file );

			if ( $plugin_installed && $plugin_active ) {
				$results[] = 'El plugin "Elementor" ya está instalado y activo.';
			} else {
				$was_installed = $plugin_installed;
				if ( ! $plugin_installed ) {
					include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
					include_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();

					$api_url = 'https://api.wordpress.org/plugins/info/1.0/' . $plugin_slug . '.json';
					$response = wp_remote_get( $api_url );

					if ( ! is_wp_error( $response ) ) {
						$data = json_decode( wp_remote_retrieve_body( $response ), true );
						if ( ! empty( $data['download_link'] ) ) {
							$installer = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
							$installed = $installer->install( $data['download_link'] );
							if ( $installed && ! is_wp_error( $installed ) ) {
								$plugin_installed = true;
							}
						}
					}
				}

				if ( $plugin_installed ) {
					$activated = activate_plugin( $plugin_file );
					if ( ! is_wp_error( $activated ) ) {
						if ( $was_installed ) {
							$results[] = 'El plugin "Elementor" ya estaba instalado y ha sido activado.';
						} else {
							$results[] = 'Plugin "Elementor" instalado y activado correctamente.';
						}
					} else {
						$results[] = 'El plugin "Elementor" está instalado pero falló su activación.';
					}
				} else {
					$results[] = 'Error al instalar el plugin "Elementor" desde WordPress.org.';
				}
			}
		}

		// 7. Ajustes de medios
		if ( ! empty( $actions['media_sizes'] ) ) {
			update_option( 'thumbnail_size_w', 300 );
			update_option( 'thumbnail_size_h', 300 );
			update_option( 'thumbnail_crop', 1 );
			update_option( 'medium_size_w', 800 );
			update_option( 'medium_size_h', 800 );
			update_option( 'large_size_w', 1920 );
			update_option( 'large_size_h', 1080 );
			$results[] = 'Ajustes de dimensiones de medios optimizados (300px, 800px, 1920px).';
		}

		// 8. Enlaces permanentes
		if ( ! empty( $actions['permalinks'] ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
			// Regenerar las reglas del archivo .htaccess
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			flush_rewrite_rules();
			$results[] = 'Enlaces permanentes cambiados a "Nombre de la entrada" (postname).';
		}

		// 9. Disuadir indexación para buscadores
		if ( ! empty( $actions['discourage_indexing'] ) ) {
			update_option( 'blog_public', '0' );
			$results[] = 'Indexación de motores de búsqueda bloqueada temporalmente.';
		}

		return $results;
	}

	/**
	 * Borra recursivamente un directorio.
	 */
	private function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}
}

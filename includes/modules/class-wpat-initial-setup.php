<?php
/**
 * Módulo: Configuración Inicial de Sitio - WP Agency Toolkit
 *
 * Asistente integral de puesta a punto y limpieza para nuevas instalaciones de WordPress.
 * Permite eliminar contenido y plugins basura por defecto, crear páginas y menús esenciales,
 * instalar temas/plugins de trabajo y optimizar los ajustes clave del sistema en un solo clic.
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Initial_Setup {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Initial_Setup|null
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
	 * Constructor privado.
	 */
	private function __construct() {}

	/**
	 * Ejecuta las tareas seleccionadas de la configuración inicial.
	 *
	 * @param array $actions Acciones a realizar.
	 * @return array Mensajes de resultado.
	 */
	public function run( $actions ) {
		$results = array();

		// Desactivar redireccionamientos molestos de bienvenida de plugins
		add_filter( 'elementor/admin/after_install_redirect', '__return_false', 9999 );
		add_filter( 'woocommerce_enable_setup_wizard', '__return_false', 9999 );
		add_filter( 'woocommerce_prevent_automatic_wizard_redirect', '__return_true', 9999 );

		// ==========================================
		// 1. LIMPIEZA DE CONTENIDO POR DEFECTO
		// ==========================================

		// A. Eliminar entrada "Hola mundo"
		if ( ! empty( $actions['delete_post'] ) ) {
			$post_id = 1;
			if ( get_post( $post_id ) ) {
				wp_delete_post( $post_id, true );
				$results[] = 'Entrada por defecto "Hola mundo" eliminada permanentemente.';
			} else {
				$posts = get_posts( array(
					'post_type'   => 'post',
					'name'        => 'hola-mundo',
					'numberposts' => 1,
				) );
				if ( ! empty( $posts ) ) {
					wp_delete_post( $posts[0]->ID, true );
					$results[] = 'Entrada por defecto "Hola mundo" eliminada.';
				} else {
					$results[] = 'Entrada "Hola mundo" no encontrada (ya eliminada).';
				}
			}

			// Eliminar comentarios por defecto
			$default_comments = get_comments( array( 'number' => 5 ) );
			foreach ( $default_comments as $c ) {
				wp_delete_comment( $c->comment_ID, true );
			}
		}

		// B. Eliminar página de ejemplo
		if ( ! empty( $actions['delete_page'] ) ) {
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
				$results[] = 'Página de ejemplo de WordPress eliminada permanentemente.';
			} else {
				$results[] = 'Página de ejemplo no encontrada (ya eliminada).';
			}
		}

		// C. Eliminar plugin Hello Dolly
		if ( ! empty( $actions['delete_hello_dolly'] ) ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			deactivate_plugins( 'hello.php' );

			if ( file_exists( WP_PLUGIN_DIR . '/hello.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				$deleted = delete_plugins( array( 'hello.php' ) );
				if ( $deleted && ! is_wp_error( $deleted ) ) {
					$results[] = 'Plugin por defecto "Hello Dolly" eliminado correctamente.';
				} else {
					$results[] = 'Error al eliminar el plugin "Hello Dolly".';
				}
			} else {
				$results[] = 'Plugin "Hello Dolly" ya no está presente.';
			}
		}

		// D. Eliminar plugin Akismet
		if ( ! empty( $actions['delete_akismet'] ) ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			deactivate_plugins( 'akismet/akismet.php' );

			if ( is_dir( WP_PLUGIN_DIR . '/akismet' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				$deleted = delete_plugins( array( 'akismet/akismet.php' ) );
				if ( $deleted && ! is_wp_error( $deleted ) ) {
					$results[] = 'Plugin por defecto "Akismet Anti-spam" eliminado correctamente.';
				} else {
					$results[] = 'Error al eliminar el plugin "Akismet".';
				}
			} else {
				$results[] = 'Plugin "Akismet" ya no está presente.';
			}
		}

		// E. Limpiar descripción corta por defecto ("Otro sitio más de WordPress")
		if ( ! empty( $actions['clean_tagline'] ) ) {
			$current_desc = get_option( 'blogdescription' );
			$default_descs = array(
				'Otro sitio realizado con WordPress',
				'Otro sitio más de WordPress',
				'Just another WordPress site',
				'Un sitio más de WordPress',
			);
			if ( in_array( trim( $current_desc ), $default_descs, true ) ) {
				update_option( 'blogdescription', '' );
				$results[] = 'Descripción corta por defecto ("' . esc_html( $current_desc ) . '") limpiada.';
			}
		}

		// ==========================================
		// 2. CREACIÓN DE PÁGINAS Y MENÚ
		// ==========================================

		$has_standard_pages = ! empty( $actions['create_pages'] ) && ! empty( $actions['pages_list'] );
		$has_custom_pages   = ! empty( $actions['custom_pages'] );

		$created_page_ids = array();

		if ( $has_standard_pages || $has_custom_pages ) {
			$pages_map = array(
				'home'      => array( 'title' => 'Inicio', 'content' => "<!-- wp:paragraph -->\n<p>Bienvenido a la página principal.</p>\n<!-- /wp:paragraph -->" ),
				'blog'      => array( 'title' => 'Blog', 'content' => "<!-- wp:paragraph -->\n<p>Últimas noticias y artículos.</p>\n<!-- /wp:paragraph -->" ),
				'about'     => array( 'title' => 'Quiénes somos', 'content' => "<!-- wp:paragraph -->\n<p>Descubre nuestra historia y equipo.</p>\n<!-- /wp:paragraph -->" ),
				'services'  => array( 'title' => 'Servicios', 'content' => "<!-- wp:paragraph -->\n<p>Nuestros servicios profesionales.</p>\n<!-- /wp:paragraph -->" ),
				'contact'   => array( 'title' => 'Contacto', 'content' => "<!-- wp:paragraph -->\n<p>Ponte en contacto con nuestro equipo.</p>\n<!-- /wp:paragraph -->" ),
				'portfolio' => array( 'title' => 'Proyectos', 'content' => "<!-- wp:paragraph -->\n<p>Galería de proyectos destacados.</p>\n<!-- /wp:paragraph -->" ),
				'faq'       => array( 'title' => 'Preguntas frecuentes', 'content' => "<!-- wp:paragraph -->\n<p>Respuestas a preguntas habituales.</p>\n<!-- /wp:paragraph -->" ),
				'legal'     => array( 'title' => 'Aviso legal', 'content' => "<!-- wp:paragraph -->\n<p>Información de Aviso Legal y datos identificativos.</p>\n<!-- /wp:paragraph -->" ),
				'privacy'   => array( 'title' => 'Política de privacidad', 'content' => "<!-- wp:paragraph -->\n<p>Información sobre el tratamiento de datos y privacidad.</p>\n<!-- /wp:paragraph -->" ),
				'cookies'   => array( 'title' => 'Política de cookies', 'content' => "<!-- wp:paragraph -->\n<p>Información sobre el uso de cookies en este sitio.</p>\n<!-- /wp:paragraph -->" ),
			);

			$created_names  = array();
			$existing_names = array();
			$home_page_id   = 0;
			$blog_page_id   = 0;
			$privacy_id     = 0;

			// A. Crear páginas estándar seleccionadas
			if ( $has_standard_pages ) {
				$pages_to_create = (array) $actions['pages_list'];
				foreach ( $pages_to_create as $key ) {
					if ( isset( $pages_map[ $key ] ) ) {
						$page_data  = $pages_map[ $key ];
						$page_check = get_page_by_title( $page_data['title'] );
						if ( ! $page_check ) {
							$new_page_id = wp_insert_post( array(
								'post_title'   => $page_data['title'],
								'post_content' => $page_data['content'],
								'post_status'  => 'publish',
								'post_type'    => 'page',
							) );
							if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
								$created_names[]      = $page_data['title'];
								$created_page_ids[]   = $new_page_id;
								if ( 'home' === $key ) {
									$home_page_id = $new_page_id;
								}
								if ( 'blog' === $key ) {
									$blog_page_id = $new_page_id;
								}
								if ( 'privacy' === $key ) {
									$privacy_id = $new_page_id;
								}
							}
						} else {
							$existing_names[]   = $page_data['title'];
							$created_page_ids[] = $page_check->ID;
							if ( 'home' === $key ) {
								$home_page_id = $page_check->ID;
							}
							if ( 'blog' === $key ) {
								$blog_page_id = $page_check->ID;
							}
							if ( 'privacy' === $key ) {
								$privacy_id = $page_check->ID;
							}
						}
					}
				}
			}

			// B. Crear páginas personalizadas
			if ( $has_custom_pages ) {
				$custom_input  = sanitize_text_field( $actions['custom_pages'] );
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
							$created_names[]    = $title;
							$created_page_ids[] = $new_page_id;
						}
					} else {
						$existing_names[]   = $title;
						$created_page_ids[] = $page_check->ID;
					}
				}
			}

			if ( ! empty( $created_names ) ) {
				$results[] = 'Páginas creadas: ' . implode( ', ', $created_names ) . '.';
			}
			if ( ! empty( $existing_names ) ) {
				$results[] = 'Páginas omitidas (ya existían en el sitio): ' . implode( ', ', $existing_names ) . '.';
			}

			// Establecer página de Inicio como portada de WordPress
			if ( $home_page_id > 0 ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $home_page_id );
				$results[] = 'Página "Inicio" establecida como portada estática principal.';
			}

			// Establecer página de Blog para entradas
			if ( $blog_page_id > 0 ) {
				update_option( 'page_for_posts', $blog_page_id );
				$results[] = 'Página "Blog" establecida como archivo principal de entradas.';
			}

			// Asignar página de privacidad nativa de WP
			if ( $privacy_id > 0 ) {
				update_option( 'wp_page_for_privacy_policy', $privacy_id );
			}

			// C. Crear Menú Principal de Navegación si se solicitó
			if ( ! empty( $actions['create_main_menu'] ) && ! empty( $created_page_ids ) ) {
				$menu_name = 'Menú Principal';
				$menu_exists = wp_get_nav_menu_object( $menu_name );

				if ( ! $menu_exists ) {
					$menu_id = wp_create_nav_menu( $menu_name );
					if ( ! is_wp_error( $menu_id ) ) {
						// Añadir páginas creadas excluyendo las páginas puramente legales
						$legal_titles = array( 'Aviso legal', 'Política de privacidad', 'Política de cookies' );
						$menu_order = 1;

						foreach ( $created_page_ids as $pid ) {
							$page_title = get_the_title( $pid );
							if ( in_array( $page_title, $legal_titles, true ) ) {
								continue;
							}
							wp_update_nav_menu_item( $menu_id, 0, array(
								'menu-item-title'     => $page_title,
								'menu-item-object'    => 'page',
								'menu-item-object-id' => $pid,
								'menu-item-type'      => 'post_type',
								'menu-item-status'    => 'publish',
								'menu-item-position'  => $menu_order++,
							) );
						}

						// Asignar menú a ubicaciones primarias de temas comunes
						$locations = get_theme_mod( 'nav_menu_locations', array() );
						$possible_locations = array( 'primary', 'main', 'main-menu', 'header-menu', 'top', 'header' );
						foreach ( $possible_locations as $loc ) {
							$locations[ $loc ] = $menu_id;
						}
						set_theme_mod( 'nav_menu_locations', $locations );

						$results[] = 'Menú de navegación "Menú Principal" creado y asignado a la cabecera.';
					}
				} else {
					$results[] = 'El "Menú Principal" ya existía (omitiendo creación duplicada).';
				}
			}
		}

		// ==========================================
		// 3. TEMAS Y PLUGINS DE TRABAJO
		// ==========================================

		// A. Limpiar temas inactivos
		if ( ! empty( $actions['clean_themes'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			$all_themes        = wp_get_themes();
			$active_theme_slug = get_stylesheet();
			$deleted_themes    = array();

			foreach ( $all_themes as $slug => $theme ) {
				if ( $slug !== $active_theme_slug && $slug !== $theme->get( 'Template' ) ) {
					$theme_dir = $theme->get_stylesheet_directory();
					if ( is_dir( $theme_dir ) ) {
						$this->delete_directory( $theme_dir );
						$deleted_themes[] = $theme->get( 'Name' );
					}
				}
			}

			if ( ! empty( $deleted_themes ) ) {
				$results[] = 'Temas inactivos eliminados: ' . implode( ', ', $deleted_themes ) . '.';
			} else {
				$results[] = 'No se encontraron temas inactivos que eliminar.';
			}
		}

		// B. Instalar y activar Hello Elementor
		if ( ! empty( $actions['install_hello'] ) ) {
			$res = $this->install_and_activate_theme( 'hello-elementor', 'Hello Elementor' );
			if ( $res ) {
				$results[] = $res;
			}
		}

		// C. Instalar y activar Elementor
		if ( ! empty( $actions['install_elementor'] ) ) {
			$res = $this->install_and_activate_plugin( 'elementor', 'elementor/elementor.php', 'Elementor' );
			if ( $res ) {
				$results[] = $res;
			}
		}

		// D. Instalar y activar TranslatePress
		if ( ! empty( $actions['install_translatepress'] ) ) {
			$res = $this->install_and_activate_plugin( 'translatepress-multilingual', 'translatepress-multilingual/index.php', 'TranslatePress' );
			if ( ! $res ) {
				$res = $this->install_and_activate_plugin( 'translatepress-multilingual', 'translatepress-multilingual/translatepress-multilingual.php', 'TranslatePress' );
			}
			if ( $res ) {
				$results[] = $res;
			}
		}

		// E. Instalar y activar WooCommerce
		if ( ! empty( $actions['install_woocommerce'] ) ) {
			$res = $this->install_and_activate_plugin( 'woocommerce', 'woocommerce/woocommerce.php', 'WooCommerce' );
			if ( $res ) {
				$results[] = $res;
			}
		}

		// F. Instalar y activar Fluent Forms
		if ( ! empty( $actions['install_fluentforms'] ) ) {
			$res = $this->install_and_activate_plugin( 'fluentform', 'fluentform/fluentform.php', 'Fluent Forms' );
			if ( $res ) {
				$results[] = $res;
			}
		}

		// ==========================================
		// 4. OPTIMIZACIÓN DE AJUSTES DEL SISTEMA
		// ==========================================

		// A. Ajustes de medios
		if ( ! empty( $actions['media_sizes'] ) ) {
			update_option( 'thumbnail_size_w', 300 );
			update_option( 'thumbnail_size_h', 300 );
			update_option( 'thumbnail_crop', 1 );
			update_option( 'medium_size_w', 800 );
			update_option( 'medium_size_h', 800 );
			update_option( 'large_size_w', 1920 );
			update_option( 'large_size_h', 1080 );
			$results[] = 'Dimensiones de medios optimizadas (Miniatura 300px, Medio 800px, Grande 1920px).';
		}

		// B. Enlaces permanentes
		if ( ! empty( $actions['permalinks'] ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			flush_rewrite_rules();
			$results[] = 'Estructura de enlaces permanentes cambiada a "/%postname%/".';
		}

		// C. Desactivar comentarios por defecto
		if ( ! empty( $actions['disable_default_comments'] ) ) {
			update_option( 'default_comment_status', 'closed' );
			update_option( 'default_ping_status', 'closed' );
			update_option( 'show_avatars', 0 );
			$results[] = 'Comentarios por defecto y pingbacks deshabilitados en nuevas publicaciones.';
		}

		// D. Configurar Zona Horaria e Idioma Español
		if ( ! empty( $actions['set_timezone_es'] ) ) {
			update_option( 'timezone_string', 'Europe/Madrid' );
			update_option( 'date_format', 'd/m/Y' );
			update_option( 'time_format', 'H:i' );
			update_option( 'start_of_week', 1 );
			$results[] = 'Zona horaria configurada a "Europe/Madrid" y formato de fecha a "d/m/Y".';
		}

		// E. Disuadir indexación para buscadores
		if ( ! empty( $actions['discourage_indexing'] ) ) {
			update_option( 'blog_public', '0' );
			$results[] = 'Indexación de motores de búsqueda bloqueada temporalmente (Sitio en desarrollo).';
		}

		// Limpieza de transients de activación de terceros
		delete_transient( 'elementor_activation_redirect' );
		delete_transient( '_wc_activation_redirect' );

		return $results;
	}

	/**
	 * Instala y activa un tema desde WordPress.org.
	 */
	private function install_and_activate_theme( $theme_slug, $theme_name ) {
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$active_theme = wp_get_theme();

		if ( $active_theme->get_stylesheet() === $theme_slug ) {
			return 'El tema "' . esc_html( $theme_name ) . '" ya está instalado y activo.';
		}

		$theme_exists = wp_get_theme( $theme_slug )->exists();
		$was_installed = $theme_exists;

		if ( ! $theme_exists ) {
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();

			$api_url  = 'https://api.wordpress.org/themes/info/1.1/?action=theme_information&request[slug]=' . $theme_slug;
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
			return $was_installed ? 'El tema "' . esc_html( $theme_name ) . '" ya estaba instalado y ha sido activado.' : 'Tema "' . esc_html( $theme_name ) . '" instalado y activado correctamente.';
		}

		return 'Error al instalar el tema "' . esc_html( $theme_name ) . '" desde WordPress.org.';
	}

	/**
	 * Instala y activa un plugin desde WordPress.org.
	 */
	private function install_and_activate_plugin( $slug, $file, $name ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$installed = file_exists( WP_PLUGIN_DIR . '/' . $file );
		$active    = is_plugin_active( $file );

		if ( $installed && $active ) {
			return 'El plugin "' . esc_html( $name ) . '" ya está instalado y activo.';
		}

		$was_installed = $installed;

		if ( ! $installed ) {
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();

			$api_url  = 'https://api.wordpress.org/plugins/info/1.0/' . $slug . '.json';
			$response = wp_remote_get( $api_url );

			if ( ! is_wp_error( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $data['download_link'] ) ) {
					$installer = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
					$install_res = $installer->install( $data['download_link'] );
					if ( $install_res && ! is_wp_error( $install_res ) ) {
						$installed = true;
					}
				}
			}
		}

		if ( $installed ) {
			$activated = activate_plugin( $file );
			if ( ! is_wp_error( $activated ) ) {
				return $was_installed ? 'El plugin "' . esc_html( $name ) . '" ya estaba instalado y ha sido activado.' : 'Plugin "' . esc_html( $name ) . '" instalado y activado correctamente.';
			}
			return 'El plugin "' . esc_html( $name ) . '" está instalado pero falló su activación.';
		}

		return 'Error al instalar el plugin "' . esc_html( $name ) . '" desde WordPress.org.';
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

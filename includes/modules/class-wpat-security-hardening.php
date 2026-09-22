<?php
/**
 * Módulo: Fortalecimiento de Seguridad (Hardening) - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Security_Hardening {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Security_Hardening|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Security_Hardening
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

		// 1. Desactivar editores de archivos incorporados
		if ( isset( $settings['sec_disable_file_edit'] ) && '1' === (string) $settings['sec_disable_file_edit'] ) {
			add_filter( 'user_has_cap', array( $this, 'disable_file_editors' ) );
		}

		// 2 y 5. Sincronización de .htaccess (Uploads e Indexes) exclusivamente en el panel de control
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'sync_uploads_htaccess' ) );
			add_action( 'admin_init', array( $this, 'sync_indexes_htaccess' ) );
		}

		// 3. Ocultar versión de WordPress
		if ( isset( $settings['sec_hide_wp_version'] ) && '1' === (string) $settings['sec_hide_wp_version'] ) {
			add_action( 'init', array( $this, 'hide_wp_version' ) );
		}

		// 4. Evitar respuestas detalladas en los errores de acceso
		if ( isset( $settings['sec_generic_login_errors'] ) && '1' === (string) $settings['sec_generic_login_errors'] ) {
			add_filter( 'login_errors', array( $this, 'generic_login_errors' ) );
		}

		// 6. Desactivar enumeración de usuarios
		if ( isset( $settings['sec_disable_user_enum'] ) && '1' === (string) $settings['sec_disable_user_enum'] ) {
			add_action( 'template_redirect', array( $this, 'block_user_enumeration' ) );
			add_filter( 'rest_endpoints', array( $this, 'block_rest_user_enum' ) );
			add_filter( 'wp_sitemaps_add_provider', array( $this, 'remove_users_sitemap_provider' ), 10, 2 );
		}

		// 7. Desactivar XML-RPC
		if ( isset( $settings['sec_disable_xmlrpc'] ) && '1' === (string) $settings['sec_disable_xmlrpc'] ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
			add_filter( 'wp_headers', array( $this, 'remove_xmlrpc_headers' ) );
			add_action( 'init', array( $this, 'block_xmlrpc_requests' ) );
		}

		// 8. Bloquear el usuario 'admin'
		if ( isset( $settings['sec_block_admin_user'] ) && '1' === (string) $settings['sec_block_admin_user'] ) {
			add_filter( 'authenticate', array( $this, 'block_admin_auth' ), 25, 3 );
			add_filter( 'illegal_user_logins', array( $this, 'add_admin_to_illegal_logins' ) );
			add_filter( 'validate_username', array( $this, 'validate_not_admin_username' ), 10, 2 );
		}

		// 9. Cabeceras de seguridad HTTP
		if ( isset( $settings['sec_security_headers'] ) && '1' === (string) $settings['sec_security_headers'] ) {
			add_filter( 'wp_headers', array( $this, 'add_security_headers' ) );
			add_action( 'send_headers', array( $this, 'send_security_headers' ) );
		}
	}

	/**
	 * Sincroniza el archivo .htaccess en uploads sólo en el entorno de administración.
	 */
	public function sync_uploads_htaccess() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$enable   = isset( $settings['sec_block_uploads_php'] ) && '1' === (string) $settings['sec_block_uploads_php'];
		$this->manage_uploads_htaccess( $enable );
	}

	/**
	 * Sincroniza la directiva Options -Indexes en el .htaccess raíz sólo en el entorno de administración.
	 */
	public function sync_indexes_htaccess() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$enable   = isset( $settings['sec_disable_indexes'] ) && '1' === (string) $settings['sec_disable_indexes'];
		$this->manage_indexes_htaccess( $enable );
	}

	/**
	 * Desactiva el editor de temas y plugins modificando las capacidades de usuario.
	 *
	 * @param array $allcaps Capacidades actuales del usuario.
	 * @return array
	 */
	public function disable_file_editors( $allcaps ) {
		$allcaps['edit_themes']  = false;
		$allcaps['edit_plugins'] = false;
		$allcaps['edit_files']   = false;
		return $allcaps;
	}

	/**
	 * Crea o elimina el archivo .htaccess en la carpeta uploads para evitar la ejecución de archivos PHP/scripts (Apache 2.2 y 2.4+).
	 *
	 * @param bool $enable Activar o desactivar protección.
	 */
	private function manage_uploads_htaccess( $enable ) {
		$upload_dir    = wp_upload_dir();
		$htaccess_file = $upload_dir['basedir'] . '/.htaccess';

		if ( $enable ) {
			if ( ! file_exists( $htaccess_file ) ) {
				$rules  = "# BEGIN WP Agency Toolkit - Bloqueo de ejecucion en Uploads\n";
				$rules .= "<FilesMatch \"(?i)\\.(php|phtml|php3|php4|php5|php7|php8|phps|pht|phar|inc)$\">\n";
				$rules .= "    <IfModule mod_authz_core.c>\n";
				$rules .= "        Require all denied\n";
				$rules .= "    </IfModule>\n";
				$rules .= "    <IfModule !mod_authz_core.c>\n";
				$rules .= "        Order allow,deny\n";
				$rules .= "        Deny from all\n";
				$rules .= "    </IfModule>\n";
				$rules .= "</FilesMatch>\n";
				$rules .= "# END WP Agency Toolkit - Bloqueo de ejecucion en Uploads\n";

				if ( is_writable( $upload_dir['basedir'] ) ) {
					@file_put_contents( $htaccess_file, $rules );
				}
			}
		} else {
			if ( file_exists( $htaccess_file ) ) {
				$content = @file_get_contents( $htaccess_file );
				if ( false !== strpos( $content, 'WP Agency Toolkit' ) ) {
					@unlink( $htaccess_file );
				}
			}
		}
	}

	/**
	 * Elimina la versión de WP de cabeceras, scripts, estilos y feeds.
	 */
	public function hide_wp_version() {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'get_the_generator_atom', '__return_empty_string' );
		add_filter( 'get_the_generator_rss2', '__return_empty_string' );
		add_filter( 'get_the_generator_rdf', '__return_empty_string' );
		add_filter( 'get_the_generator_comment', '__return_empty_string' );
		add_filter( 'get_the_generator_export', '__return_empty_string' );

		add_filter( 'script_loader_src', array( $this, 'remove_version_query_arg' ), 15 );
		add_filter( 'style_loader_src', array( $this, 'remove_version_query_arg' ), 15 );
	}

	/**
	 * Remueve el argumento de consulta ?ver=X.X coincidente con la versión de WordPress.
	 *
	 * @param string $src URL del asset.
	 * @return string
	 */
	public function remove_version_query_arg( $src ) {
		global $wp_version;
		$ver = ! empty( $wp_version ) ? $wp_version : get_bloginfo( 'version' );
		if ( false !== strpos( $src, 'ver=' . $ver ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Devuelve un mensaje genérico al fallar el inicio de sesión.
	 *
	 * @return string
	 */
	public function generic_login_errors() {
		return __( '<strong>ERROR:</strong> Las credenciales introducidas no son correctas.', 'wp-agency-toolkit' );
	}

	/**
	 * Inyecta o remueve Options -Indexes en el archivo .htaccess raíz para evitar la búsqueda de directorios.
	 *
	 * @param bool $enable Activar o desactivar directiva.
	 */
	private function manage_indexes_htaccess( $enable ) {
		$htaccess_file = ABSPATH . '.htaccess';
		if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
			return;
		}

		$content = @file_get_contents( $htaccess_file );
		if ( false === $content ) {
			return;
		}

		$marker_start = "# BEGIN WP Agency Toolkit - Desactivar Indexes\n";
		$marker_block = "# BEGIN WP Agency Toolkit - Desactivar Indexes\nOptions -Indexes\n# END WP Agency Toolkit - Desactivar Indexes\n";

		if ( $enable ) {
			if ( false === strpos( $content, 'Options -Indexes' ) ) {
				$new_content = $marker_block . "\n" . $content;
				@file_put_contents( $htaccess_file, $new_content );
			}
		} else {
			if ( false !== strpos( $content, $marker_start ) ) {
				$new_content = str_replace( array( $marker_block . "\n", $marker_block ), '', $content );
				@file_put_contents( $htaccess_file, $new_content );
			}
		}
	}

	/**
	 * Bloquea la enumeración de autores en frontend respondiendo con un 404 nativo.
	 */
	public function block_user_enumeration() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}

		$has_author_query = isset( $_GET['author'] ) || ( function_exists( 'is_author' ) && is_author() );

		if ( $has_author_query ) {
			global $wp_query;
			if ( $wp_query ) {
				$wp_query->set_404();
			}
			status_header( 404 );
			nocache_headers();

			$template = get_query_template( '404' );
			if ( $template && file_exists( $template ) ) {
				include $template;
			} else {
				wp_safe_redirect( home_url(), 404 );
			}
			exit;
		}
	}

	/**
	 * Elimina los endpoints REST API que listan usuarios públicos para visitantes anónimos.
	 *
	 * @param array $endpoints Endpoints registrados de la REST API.
	 * @return array
	 */
	public function block_rest_user_enum( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			if ( isset( $endpoints['/wp/v2/users'] ) ) {
				unset( $endpoints['/wp/v2/users'] );
			}
			if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
				unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
			}
		}
		return $endpoints;
	}

	/**
	 * Desactiva el proveedor de sitemaps de usuarios para evitar exposición de nombres de autor en sitemaps XML.
	 *
	 * @param WP_Sitemaps_Provider $provider Instancia del proveedor.
	 * @param string               $name     Nombre del proveedor.
	 * @return WP_Sitemaps_Provider|false
	 */
	public function remove_users_sitemap_provider( $provider, $name ) {
		if ( 'users' === $name ) {
			return false;
		}
		return $provider;
	}

	/**
	 * Remueve las cabeceras HTTP de XML-RPC / Pingback.
	 *
	 * @param array $headers Cabeceras HTTP salientes.
	 * @return array
	 */
	public function remove_xmlrpc_headers( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Bloquea cualquier petición directa hacia xmlrpc.php con error 403 Forbidden.
	 */
	public function block_xmlrpc_requests() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false !== strpos( $uri, 'xmlrpc.php' ) ) {
			wp_die(
				esc_html__( 'El protocolo XML-RPC se encuentra deshabilitado en este sitio por motivos de seguridad.', 'wp-agency-toolkit' ),
				esc_html__( 'Acceso Denegado (403)', 'wp-agency-toolkit' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Impide iniciar sesión usando el nombre de usuario 'admin'.
	 *
	 * @param WP_User|WP_Error|null $user     Usuario autenticado o error.
	 * @param string                $username Nombre de usuario introducido.
	 * @param string                $password Contraseña introducida.
	 * @return WP_User|WP_Error
	 */
	public function block_admin_auth( $user, $username, $password ) {
		if ( 'admin' === strtolower( (string) $username ) ) {
			return new WP_Error(
				'invalid_username',
				__( '<strong>ERROR:</strong> El acceso para el usuario "admin" está desactivado por seguridad.', 'wp-agency-toolkit' )
			);
		}
		return $user;
	}

	/**
	 * Añade 'admin' a la lista negra de nombres de usuario no permitidos en WordPress.
	 *
	 * @param array $usernames Lista de nombres ilegales.
	 * @return array
	 */
	public function add_admin_to_illegal_logins( $usernames ) {
		$usernames[] = 'admin';
		$usernames[] = 'administrator';
		return array_unique( $usernames );
	}

	/**
	 * Invalida el registro si el nombre de usuario es 'admin'.
	 *
	 * @param bool   $valid    Si el nombre es válido.
	 * @param string $username Nombre de usuario.
	 * @return bool
	 */
	public function validate_not_admin_username( $valid, $username ) {
		if ( in_array( strtolower( (string) $username ), array( 'admin', 'administrator' ), true ) ) {
			return false;
		}
		return $valid;
	}

	/**
	 * Añade cabeceras de seguridad HTTP en el filtro wp_headers.
	 *
	 * @param array $headers Cabeceras HTTP.
	 * @return array
	 */
	public function add_security_headers( $headers ) {
		$headers['X-Content-Type-Options'] = 'nosniff';
		$headers['X-Frame-Options']        = 'SAMEORIGIN';
		$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';
		$headers['X-XSS-Protection']       = '1; mode=block';
		return $headers;
	}

	/**
	 * Emite cabeceras de seguridad HTTP mediante el hook send_headers.
	 */
	public function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'X-XSS-Protection: 1; mode=block' );
	}
}


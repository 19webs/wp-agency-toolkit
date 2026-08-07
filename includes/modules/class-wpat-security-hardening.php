<?php
/**
 * Módulo: Fortalecimiento de Seguridad (Hardening) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Security_Hardening {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Security_Hardening
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
		if ( isset( $settings['sec_disable_file_edit'] ) && '1' === $settings['sec_disable_file_edit'] ) {
			add_filter( 'user_has_cap', array( $this, 'disable_file_editors' ) );
		}

		// 2. Evitar ejecución de código en la carpeta pública 'Uploads'
		if ( isset( $settings['sec_block_uploads_php'] ) && '1' === $settings['sec_block_uploads_php'] ) {
			$this->manage_uploads_htaccess( true );
		} else {
			$this->manage_uploads_htaccess( false );
		}

		// 3. Ocultar versión de WordPress
		if ( isset( $settings['sec_hide_wp_version'] ) && '1' === $settings['sec_hide_wp_version'] ) {
			add_action( 'init', array( $this, 'hide_wp_version' ) );
		}

		// 4. Evitar respuestas detalladas en los errores de acceso
		if ( isset( $settings['sec_generic_login_errors'] ) && '1' === $settings['sec_generic_login_errors'] ) {
			add_filter( 'login_errors', array( $this, 'generic_login_errors' ) );
		}

		// 5. Desactivar la búsqueda de directorios (Indexes)
		if ( isset( $settings['sec_disable_indexes'] ) && '1' === $settings['sec_disable_indexes'] ) {
			$this->manage_indexes_htaccess( true );
		}

		// 6. Desactivar enumeración de usuarios
		if ( isset( $settings['sec_disable_user_enum'] ) && '1' === $settings['sec_disable_user_enum'] ) {
			add_action( 'wp_loaded', array( $this, 'block_user_enumeration' ) );
			add_filter( 'rest_endpoints', array( $this, 'block_rest_user_enum' ) );
		}

		// 7. Desactivar XML-RPC
		if ( isset( $settings['sec_disable_xmlrpc'] ) && '1' === $settings['sec_disable_xmlrpc'] ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_xmlrpc_headers' ) );
		}

		// 8. Bloquear el usuario 'admin'
		if ( isset( $settings['sec_block_admin_user'] ) && '1' === $settings['sec_block_admin_user'] ) {
			add_filter( 'authenticate', array( $this, 'block_admin_auth' ), 25, 3 );
		}
	}

	/**
	 * Desactiva el editor de temas y plugins modificando las capacidades de usuario.
	 */
	public function disable_file_editors( $allcaps ) {
		$allcaps['edit_themes']  = false;
		$allcaps['edit_plugins'] = false;
		return $allcaps;
	}

	/**
	 * Crea o elimina el archivo .htaccess en la carpeta uploads para evitar la ejecución de archivos PHP.
	 */
	private function manage_uploads_htaccess( $enable ) {
		$upload_dir = wp_upload_dir();
		$htaccess_file = $upload_dir['basedir'] . '/.htaccess';

		if ( $enable ) {
			if ( ! file_exists( $htaccess_file ) ) {
				$rules = "# Evitar ejecucion de PHP - WP Agency Toolkit\n";
				$rules .= "<Files *.php>\n";
				$rules .= "deny from all\n";
				$rules .= "</Files>\n";
				
				if ( is_writable( $upload_dir['basedir'] ) ) {
					@file_put_contents( $htaccess_file, $rules );
				}
			}
		} else {
			if ( file_exists( $htaccess_file ) ) {
				$content = @file_get_contents( $htaccess_file );
				if ( strpos( $content, '# Evitar ejecucion de PHP - WP Agency Toolkit' ) !== false ) {
					@unlink( $htaccess_file );
				}
			}
		}
	}

	/**
	 * Elimina la versión de WP de cabeceras, scripts y feeds.
	 */
	public function hide_wp_version() {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		
		add_filter( 'script_loader_src', array( $this, 'remove_version_query_arg' ) );
		add_filter( 'style_loader_src', array( $this, 'remove_version_query_arg' ) );
	}

	public function remove_version_query_arg( $src ) {
		if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Devuelve un mensaje genérico al fallar el inicio de sesión.
	 */
	public function generic_login_errors() {
		return '<strong>ERROR:</strong> Las credenciales introducidas no son correctas.';
	}

	/**
	 * Inyecta Options -Indexes en el archivo .htaccess raíz para evitar la búsqueda de directorios.
	 */
	private function manage_indexes_htaccess( $enable ) {
		$htaccess_file = ABSPATH . '.htaccess';
		if ( $enable && file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
			$content = @file_get_contents( $htaccess_file );
			if ( strpos( $content, 'Options -Indexes' ) === false ) {
				$new_content = "# Evitar listado de directorios - WP Agency Toolkit\nOptions -Indexes\n\n" . $content;
				@file_put_contents( $htaccess_file, $new_content );
			}
		}
	}

	/**
	 * Bloquea la búsqueda de autores /?author=N.
	 */
	public function block_user_enumeration() {
		if ( ! is_admin() && isset( $_GET['author'] ) ) {
			wp_safe_redirect( home_url( '/404' ) );
			exit;
		}
	}

	/**
	 * Elimina los endpoints REST API que listan usuarios públicos.
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
	 * Remueve las cabeceras HTTP de XML-RPC / Pingback.
	 */
	public function remove_xmlrpc_headers( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Impide iniciar sesión usando el nombre de usuario 'admin'.
	 */
	public function block_admin_auth( $user, $username, $password ) {
		if ( 'admin' === strtolower( $username ) ) {
			return new WP_Error(
				'invalid_username',
				'<strong>ERROR:</strong> El acceso para el usuario "admin" está desactivado por seguridad.'
			);
		}
		return $user;
	}
}

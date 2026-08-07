<?php
/**
 * Módulo: SSL & Mixed Content - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_SSL_Fixer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_SSL_Fixer
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_SSL_Fixer
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

		// Redirección forzada de HTTP a HTTPS si está en modo PHP
		$method = isset( $settings['ssl_redirect_method'] ) ? $settings['ssl_redirect_method'] : 'php';
		if ( 'php' === $method ) {
			add_action( 'init', array( $this, 'force_ssl_redirection' ), 1 );
		}
		
		// Corrección de contenido mixto mediante buffer de salida
		add_action( 'template_redirect', array( $this, 'start_output_buffer' ) );

		// Inyección de cabeceras de seguridad HTTP
		add_filter( 'wp_headers', array( $this, 'inject_security_headers' ) );
	}

	/**
	 * Fuerza la redirección 301 a HTTPS si se accede vía HTTP.
	 */
	public function force_ssl_redirection() {
		if ( ! is_ssl() && ! $this->is_cli() ) {
			if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
				$redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				wp_safe_redirect( $redirect_url, 301 );
				exit;
			}
		}
	}

	/**
	 * Inicia el almacenamiento en búfer de salida para el frontend.
	 */
	public function start_output_buffer() {
		if ( ! is_admin() && ! $this->is_xmlrpc() ) {
			ob_start( array( $this, 'sanitize_mixed_content' ) );
		}
	}

	/**
	 * Callback para corregir enlaces HTTP al dominio local y forzar HTTPS.
	 *
	 * @param string $buffer Contenido de la página.
	 * @return string
	 */
	public function sanitize_mixed_content( $buffer ) {
		$host = parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host ) {
			return $buffer;
		}

		// Escapar el host para usarlo en expresiones regulares
		$escaped_host = preg_quote( $host, '/' );

		// 1. Reemplazo directo de la URL home_url() en HTTP a HTTPS
		$http_home = str_replace( 'https://', 'http://', home_url() );
		$buffer    = str_replace( $http_home, home_url(), $buffer );

		// 2. Reemplazar dinámicamente cualquier http://dominio/ o http://subdominio.dominio/ por su equivalente https://
		$pattern = '/http:\/\/([a-zA-Z0-9\-\.]*' . $escaped_host . ')/i';
		$buffer  = preg_replace( $pattern, 'https://$1', $buffer );

		return $buffer;
	}

	/**
	 * Inyecta las cabeceras de seguridad HTTP recomendadas.
	 *
	 * @param array $headers Cabeceras de respuesta por defecto.
	 * @return array
	 */
	public function inject_security_headers( $headers ) {
		$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
		$headers['X-Content-Type-Options']    = 'nosniff';
		$headers['X-Frame-Options']           = 'SAMEORIGIN';
		$headers['Referrer-Policy']           = 'strict-origin-when-cross-origin';
		
		return $headers;
	}

	/**
	 * Comprueba si la petición se realiza por línea de comandos (WP-CLI).
	 *
	 * @return bool
	 */
	private function is_cli() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Comprueba si la petición es para XML-RPC.
	 *
	 * @return bool
	 */
	private function is_xmlrpc() {
		return defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	}

	/**
	 * Escribe o elimina las reglas en .htaccess según la configuración.
	 */
	public static function update_htaccess_rules( $enable ) {
		$htaccess_file = ABSPATH . '.htaccess';
		if ( ! file_exists( $htaccess_file ) || ! is_writable( $htaccess_file ) ) {
			return false;
		}

		$content = @file_get_contents( $htaccess_file );
		if ( false === $content ) {
			return false;
		}

		// Remover reglas existentes si están
		$start_marker = '# BEGIN SSL Redirect - WP Agency Toolkit';
		$end_marker   = '# END SSL Redirect - WP Agency Toolkit';
		
		if ( strpos( $content, $start_marker ) !== false ) {
			$pattern = '/' . preg_quote( $start_marker, '/' ) . '.*?' . preg_quote( $end_marker, '/' ) . '/s';
			$content = preg_replace( $pattern, '', $content );
			$content = preg_replace( "/\n+/s", "\n", $content );
		}

		if ( $enable ) {
			$rules = "\n" . $start_marker . "\n";
			$rules .= "<IfModule mod_rewrite.c>\n";
			$rules .= "RewriteEngine On\n";
			$rules .= "RewriteCond %{HTTPS} !=on\n";
			$rules .= "RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n";
			$rules .= "</IfModule>\n";
			$rules .= $end_marker . "\n";

			// Insertar al principio del archivo .htaccess
			$content = $rules . trim( $content ) . "\n";
		} else {
			$content = trim( $content ) . "\n";
		}

		return @file_put_contents( $htaccess_file, $content ) !== false;
	}
}

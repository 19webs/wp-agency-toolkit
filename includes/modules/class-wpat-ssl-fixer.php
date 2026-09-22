<?php
/**
 * Módulo: Forzar SSL & Contenido Mixto - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_SSL_Fixer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_SSL_Fixer|null
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

		// 1. Detección temprana y normalización de Proxies Inversos / Cloudflare / Load Balancers
		$proxy_fix = ! isset( $settings['ssl_proxy_fix'] ) || '1' === (string) $settings['ssl_proxy_fix'];
		if ( $proxy_fix ) {
			$this->normalize_proxy_https();
		}

		// 2. Redirección forzada de HTTP a HTTPS si está en modo PHP
		$method = isset( $settings['ssl_redirect_method'] ) ? $settings['ssl_redirect_method'] : 'php';
		if ( 'php' === $method ) {
			add_action( 'init', array( $this, 'force_ssl_redirection' ), 1 );
		}

		// 3. Corrección dinámica de contenido mixto mediante buffer de salida en frontend
		$fix_mixed = ! isset( $settings['ssl_fix_mixed_content'] ) || '1' === (string) $settings['ssl_fix_mixed_content'];
		if ( $fix_mixed ) {
			add_action( 'template_redirect', array( $this, 'start_output_buffer' ), 1 );
		}

		// 4. Inyección de cabeceras de seguridad HTTP y directiva CSP
		add_filter( 'wp_headers', array( $this, 'inject_security_headers' ), 99 );
		add_action( 'send_headers', array( $this, 'send_security_headers_direct' ), 99 );

		// 5. AJAX Endpoints para diagnóstico y reparación de URLs
		add_action( 'wp_ajax_wpat_fix_wp_urls_https', array( $this, 'ajax_fix_wp_urls_https' ) );
		add_action( 'wp_ajax_wpat_scan_mixed_content', array( $this, 'ajax_scan_mixed_content' ) );
	}

	/**
	 * Normaliza variables de servidor ante proxies inversos (Cloudflare, AWS, Nginx, LiteSpeed, Varnish).
	 * Previene bucles infinitos de redirección cuando el proxy termina la conexión SSL.
	 */
	public function normalize_proxy_https() {
		if ( isset( $_SERVER['HTTP_CF_VISITOR'] ) ) {
			$visitor = json_decode( wp_unslash( $_SERVER['HTTP_CF_VISITOR'] ), true );
			if ( isset( $visitor['scheme'] ) && 'https' === strtolower( $visitor['scheme'] ) ) {
				$_SERVER['HTTPS'] = 'on';
			}
		}

		if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) ) {
			$_SERVER['HTTPS'] = 'on';
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) && 'on' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ) ) ) {
			$_SERVER['HTTPS'] = 'on';
		} elseif ( isset( $_SERVER['HTTP_FRONT_END_HTTPS'] ) && 'on' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_FRONT_END_HTTPS'] ) ) ) ) {
			$_SERVER['HTTPS'] = 'on';
		} elseif ( isset( $_SERVER['HTTP_X_URL_SCHEME'] ) && 'https' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_URL_SCHEME'] ) ) ) ) {
			$_SERVER['HTTPS'] = 'on';
		} elseif ( isset( $_SERVER['SERVER_PORT'] ) && 443 === (int) $_SERVER['SERVER_PORT'] ) {
			$_SERVER['HTTPS'] = 'on';
		}
	}

	/**
	 * Comprueba de forma robusta si la petición actual se está sirviendo por HTTPS.
	 *
	 * @return bool
	 */
	public static function is_https_request() {
		if ( is_ssl() ) {
			return true;
		}

		if ( isset( $_SERVER['HTTPS'] ) && ( 'on' === strtolower( $_SERVER['HTTPS'] ) || '1' === (string) $_SERVER['HTTPS'] ) ) {
			return true;
		}

		if ( isset( $_SERVER['SERVER_PORT'] ) && 443 === (int) $_SERVER['SERVER_PORT'] ) {
			return true;
		}

		if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
			return true;
		}

		if ( isset( $_SERVER['HTTP_CF_VISITOR'] ) ) {
			$visitor = json_decode( $_SERVER['HTTP_CF_VISITOR'], true );
			if ( isset( $visitor['scheme'] ) && 'https' === strtolower( $visitor['scheme'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fuerza la redirección 301 a HTTPS si se accede vía HTTP.
	 */
	public function force_ssl_redirection() {
		if ( self::is_https_request() || $this->is_cli() || $this->is_cron() ) {
			return;
		}

		if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
			$uri  = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			$redirect_url = 'https://' . $host . $uri;

			if ( ! headers_sent() ) {
				wp_safe_redirect( $redirect_url, 301 );
				exit;
			}
		}
	}

	/**
	 * Inicia el almacenamiento en búfer de salida para el frontend.
	 */
	public function start_output_buffer() {
		if ( ! is_admin() && ! $this->is_xmlrpc() && ! $this->is_cli() ) {
			ob_start( array( $this, 'sanitize_mixed_content' ) );
		}
	}

	/**
	 * Callback para corregir enlaces HTTP al dominio local y recursos CDN comunes, forzando HTTPS.
	 *
	 * @param string $buffer Contenido de la página.
	 * @return string
	 */
	public function sanitize_mixed_content( $buffer ) {
		if ( empty( $buffer ) || ! is_string( $buffer ) ) {
			return $buffer;
		}

		$host = parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host ) {
			return $buffer;
		}

		$escaped_host = preg_quote( $host, '/' );

		// 1. Reemplazo directo de URLs del dominio local (y subdominios)
		$pattern_domain = '/http:\/\/([a-zA-Z0-9\-\.]*' . $escaped_host . ')/i';
		$buffer         = preg_replace( $pattern_domain, 'https://$1', $buffer );

		// 2. Reemplazo de CDN y servicios habituales de fuentes, scripts y medios que soportan HTTPS nativo
		$common_cdns = array(
			'http://fonts.googleapis.com'   => 'https://fonts.googleapis.com',
			'http://fonts.gstatic.com'      => 'https://fonts.gstatic.com',
			'http://ajax.googleapis.com'    => 'https://ajax.googleapis.com',
			'http://cdnjs.cloudflare.com'   => 'https://cdnjs.cloudflare.com',
			'http://secure.gravatar.com'    => 'https://secure.gravatar.com',
			'http://0.gravatar.com'         => 'https://secure.gravatar.com',
			'http://1.gravatar.com'         => 'https://secure.gravatar.com',
			'http://2.gravatar.com'         => 'https://secure.gravatar.com',
			'http://platform.twitter.com'   => 'https://platform.twitter.com',
			'http://connect.facebook.net'   => 'https://connect.facebook.net',
			'http://use.fontawesome.com'    => 'https://use.fontawesome.com',
			'http://cdn.jsdelivr.net'       => 'https://cdn.jsdelivr.net',
			'http://code.jquery.com'        => 'https://code.jquery.com',
			'http://unpkg.com'              => 'https://unpkg.com',
		);

		$buffer = str_replace( array_keys( $common_cdns ), array_values( $common_cdns ), $buffer );

		// 3. Corrección de atributos src, href y srcset con http:// en medios e incrustaciones locales
		$pattern_attrs = '/(src|href|srcset|data-src|data-srcset)=["\']http:\/\/([^\s"\']+' . $escaped_host . '[^\s"\']*)["\']/i';
		$buffer        = preg_replace_callback(
			$pattern_attrs,
			function( $matches ) {
				return $matches[1] . '="https://' . $matches[2] . '"';
			},
			$buffer
		);

		return $buffer;
	}

	/**
	 * Inyecta las cabeceras de seguridad HTTP recomendadas mediante el filtro wp_headers.
	 *
	 * @param array $headers Cabeceras de respuesta por defecto.
	 * @return array
	 */
	public function inject_security_headers( $headers ) {
		$settings = WPAT_Main::get_instance()->get_settings();

		// Directiva CSP: Upgrade-Insecure-Requests (fuerza al navegador a pedir todos los recursos HTTP vía HTTPS)
		$enable_csp = ! isset( $settings['ssl_enable_csp'] ) || '1' === (string) $settings['ssl_enable_csp'];
		if ( $enable_csp ) {
			if ( ! isset( $headers['Content-Security-Policy'] ) ) {
				$headers['Content-Security-Policy'] = 'upgrade-insecure-requests;';
			} elseif ( false === strpos( $headers['Content-Security-Policy'], 'upgrade-insecure-requests' ) ) {
				$headers['Content-Security-Policy'] = rtrim( $headers['Content-Security-Policy'], ';' ) . '; upgrade-insecure-requests;';
			}
		}

		// HSTS (HTTP Strict Transport Security) - Solo debe enviarse si la conexión actual es HTTPS
		$enable_hsts = ! isset( $settings['ssl_enable_hsts'] ) || '1' === (string) $settings['ssl_enable_hsts'];
		if ( $enable_hsts && self::is_https_request() ) {
			$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
		}

		$headers['X-Content-Type-Options'] = 'nosniff';
		$headers['X-Frame-Options']        = 'SAMEORIGIN';
		$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';

		return $headers;
	}

	/**
	 * Envía cabeceras directas en la acción send_headers como respaldo.
	 */
	public function send_security_headers_direct() {
		if ( headers_sent() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		$enable_csp = ! isset( $settings['ssl_enable_csp'] ) || '1' === (string) $settings['ssl_enable_csp'];
		if ( $enable_csp ) {
			header( 'Content-Security-Policy: upgrade-insecure-requests;', false );
		}

		$enable_hsts = ! isset( $settings['ssl_enable_hsts'] ) || '1' === (string) $settings['ssl_enable_hsts'];
		if ( $enable_hsts && self::is_https_request() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains; preload', false );
		}

		header( 'X-Content-Type-Options: nosniff', false );
		header( 'X-Frame-Options: SAMEORIGIN', false );
		header( 'Referrer-Policy: strict-origin-when-cross-origin', false );
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
	 * Comprueba si la petición es un proceso cron en ejecución.
	 *
	 * @return bool
	 */
	private function is_cron() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
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
	 * Escribe o elimina las reglas en .htaccess de forma compatible con servidores Apache y proxies inversos.
	 *
	 * @param bool $enable Activar o desactivar reglas de redirección.
	 * @return bool
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

		$start_marker = '# BEGIN SSL Redirect - WP Agency Toolkit';
		$end_marker   = '# END SSL Redirect - WP Agency Toolkit';

		if ( strpos( $content, $start_marker ) !== false ) {
			$pattern = '/' . preg_quote( $start_marker, '/' ) . '.*?' . preg_quote( $end_marker, '/' ) . '/s';
			$content = preg_replace( $pattern, '', $content );
			$content = preg_replace( "/\n+/s", "\n", $content );
		}

		if ( $enable ) {
			$rules  = "\n" . $start_marker . "\n";
			$rules .= "<IfModule mod_rewrite.c>\n";
			$rules .= "RewriteEngine On\n";
			$rules .= "RewriteCond %{HTTP:X-Forwarded-Proto} !https\n";
			$rules .= "RewriteCond %{HTTPS} !=on\n";
			$rules .= "RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n";
			$rules .= "</IfModule>\n";
			$rules .= $end_marker . "\n";

			$content = $rules . trim( $content ) . "\n";
		} else {
			$content = trim( $content ) . "\n";
		}

		return @file_put_contents( $htaccess_file, $content ) !== false;
	}

	/**
	 * Obtiene un diagnóstico completo del estado de SSL en el servidor y la instalación.
	 *
	 * @return array
	 */
	public static function get_ssl_status() {
		$site_url = get_option( 'siteurl' );
		$home_url = get_option( 'home' );

		$site_url_https = 0 === strpos( $site_url, 'https://' );
		$home_url_https = 0 === strpos( $home_url, 'https://' );
		$is_https_live  = self::is_https_request();

		$proxy = 'Directo (Sin proxy detectado)';
		if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) || isset( $_SERVER['HTTP_CF_VISITOR'] ) ) {
			$proxy = 'Cloudflare Edge CDN';
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
			$proxy = 'Proxy Inverso (X-Forwarded-Proto)';
		} elseif ( isset( $_SERVER['HTTP_FRONT_END_HTTPS'] ) ) {
			$proxy = 'Front-End HTTPS';
		}

		return array(
			'is_https_live'    => $is_https_live,
			'site_url'         => $site_url,
			'home_url'         => $home_url,
			'site_url_https'   => $site_url_https,
			'home_url_https'   => $home_url_https,
			'is_fully_https'   => ( $site_url_https && $home_url_https ),
			'proxy_type'       => $proxy,
			'hsts_supported'   => $is_https_live,
		);
	}

	/**
	 * AJAX: Actualiza siteurl y home a https:// en la base de datos de WordPress.
	 */
	public function ajax_fix_wp_urls_https() {
		check_ajax_referer( 'wpat_ssl_fixer_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) ) );
		}

		$site_url = get_option( 'siteurl' );
		$home_url = get_option( 'home' );

		$new_site_url = str_replace( 'http://', 'https://', $site_url );
		$new_home_url = str_replace( 'http://', 'https://', $home_url );

		update_option( 'siteurl', $new_site_url );
		update_option( 'home', $new_home_url );

		wp_send_json_success(
			array(
				'message'  => __( 'Las URLs de WordPress se han actualizado a HTTPS correctamente.', 'wp-agency-toolkit' ),
				'site_url' => $new_site_url,
				'home_url' => $new_home_url,
			)
		);
	}

	/**
	 * AJAX: Escanea entradas y páginas para detectar enlaces o imágenes HTTP al dominio local.
	 */
	public function ajax_scan_mixed_content() {
		check_ajax_referer( 'wpat_ssl_fixer_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) ) );
		}

		global $wpdb;

		$host = parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo determinar el dominio principal.', 'wp-agency-toolkit' ) ) );
		}

		$search_pattern = '%http://' . $wpdb->esc_like( $host ) . '%';

		// Buscar en posts
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_type, post_status FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status IN ('publish', 'draft', 'private') LIMIT 25",
				$search_pattern
			)
		);

		$count = count( $results );
		$items = array();

		foreach ( $results as $row ) {
			$items[] = array(
				'id'        => $row->ID,
				'title'     => ! empty( $row->post_title ) ? $row->post_title : sprintf( __( '(Entrada #%d sin título)', 'wp-agency-toolkit' ), $row->ID ),
				'type'      => $row->post_type,
				'edit_link' => get_edit_post_link( $row->ID, 'raw' ),
			);
		}

		wp_send_json_success(
			array(
				'count' => $count,
				'items' => $items,
			)
		);
	}
}

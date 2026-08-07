<?php
/**
 * Módulo: Bloqueador de Bots por 404 - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Bot_Blocker {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Bot_Blocker
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Bot_Blocker
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
		$this->check_and_create_tables();

		// Carga temprana del bloqueo de IP
		add_action( 'plugins_loaded', array( $this, 'check_visitor_block' ), 5 );

		// Interceptar redirección para detectar 404
		add_action( 'template_redirect', array( $this, 'detect_404_request' ) );

		// Endpoints AJAX para desbloquear e ingresar a lista blanca
		add_action( 'wp_ajax_wpat_unblock_ip', array( $this, 'ajax_unblock_ip' ) );
		add_action( 'wp_ajax_wpat_whitelist_ip', array( $this, 'ajax_whitelist_ip' ) );
	}

	/**
	 * Crea las tablas de base de datos si no existen.
	 */
	private function check_and_create_tables() {
		if ( get_option( 'wpat_bot_blocker_db_version' ) !== '1.0' ) {
			global $wpdb;
			$table_logs = $wpdb->prefix . 'wpat_404_logs';
			$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';
			$charset_collate = $wpdb->get_charset_collate();

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$sql_logs = "CREATE TABLE $table_logs (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				ip varchar(45) NOT NULL,
				user_agent varchar(255) DEFAULT '',
				requested_url text NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY ip_created (ip, created_at)
			) $charset_collate;";

			dbDelta( $sql_logs );

			$sql_blocked = "CREATE TABLE $table_blocked (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				ip varchar(45) NOT NULL,
				reason varchar(255) DEFAULT '',
				blocked_at datetime DEFAULT CURRENT_TIMESTAMP,
				expires_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY ip (ip),
				KEY ip_expires (ip, expires_at)
			) $charset_collate;";

			dbDelta( $sql_blocked );

			update_option( 'wpat_bot_blocker_db_version', '1.0' );
		}
	}

	/**
	 * Obtiene la dirección IP real del visitante actual.
	 *
	 * @return string
	 */
	public function get_visitor_ip() {
		$keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ips = explode( ',', $_SERVER[ $key ] );
				$ip  = trim( $ips[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	/**
	 * Verifica si la IP del visitante actual está en la lista de bloqueo activo.
	 */
	public function check_visitor_block() {
		$ip = $this->get_visitor_ip();
		if ( empty( $ip ) ) {
			return;
		}

		// No bloquear jamás a un administrador de WordPress
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		
		// Comprobar lista blanca de configuración
		$whitelist_raw = isset( $settings['bot_blocker_whitelist'] ) ? $settings['bot_blocker_whitelist'] : '';
		$whitelist = array_filter( array_map( 'trim', explode( ',', $whitelist_raw ) ) );
		if ( in_array( $ip, $whitelist, true ) ) {
			return;
		}

		global $wpdb;
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';
		
		$is_blocked = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_blocked WHERE ip = %s AND expires_at > NOW()",
			$ip
		) );

		if ( $is_blocked ) {
			status_header( 403 );
			nocache_headers();
			
			$expires = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $is_blocked->expires_at ) );
			
			$message = '
			<!DOCTYPE html>
			<html lang="es">
			<head>
				<meta charset="utf-8">
				<title>Acceso Denegado - Seguridad</title>
				<style>
					body { background: #f1f5f9; color: #334155; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; text-align: center; padding: 50px 20px; }
					.container { background: #fff; max-width: 500px; margin: 0 auto; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
					h1 { color: #ef4444; font-size: 24px; margin-bottom: 20px; }
					p { font-size: 15px; line-height: 1.6; color: #475569; }
					.info { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 13px; text-align: left; border-left: 4px solid #ef4444; }
					.footer { font-size: 12px; color: #94a3b8; margin-top: 30px; }
				</style>
			</head>
			<body>
				<div class="container">
					<h1>Acceso Temporalmente Restringido</h1>
					<p>Tu dirección IP ha sido bloqueada temporalmente por nuestro sistema de seguridad tras generar demasiadas solicitudes de páginas inexistentes (errores 404).</p>
					<div class="info">
						<strong>IP Detectada:</strong> ' . esc_html( $ip ) . '<br>
						<strong>Razón:</strong> Escaneo automatizado sospechoso<br>
						<strong>Expira el:</strong> ' . esc_html( $expires ) . '
					</div>
					<p>Si consideras que esto es un error y eres un usuario legítimo, por favor ponte en contacto con el soporte técnico para desbloquear tu acceso.</p>
					<div class="footer">Sistema de Seguridad - WP Agency Toolkit</div>
				</div>
			</body>
			</html>';
			
			echo $message;
			exit;
		}
	}

	/**
	 * Detecta solicitudes 404 sospechosas y calcula si se debe bloquear la IP.
	 */
	public function detect_404_request() {
		if ( ! is_404() ) {
			return;
		}

		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$ip = $this->get_visitor_ip();
		if ( empty( $ip ) ) {
			return;
		}

		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$whitelist_raw = isset( $settings['bot_blocker_whitelist'] ) ? $settings['bot_blocker_whitelist'] : '';
		$whitelist = array_filter( array_map( 'trim', explode( ',', $whitelist_raw ) ) );
		if ( in_array( $ip, $whitelist, true ) ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		// Excluir buscadores legítimos (evitar falsos positivos SEO)
		$good_bots = array( 'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider', 'yandexbot', 'facebot', 'facebookexternalhit', 'twitterbot' );
		$ua_lower = strtolower( $user_agent );
		foreach ( $good_bots as $bot ) {
			if ( strpos( $ua_lower, $bot ) !== false ) {
				return;
			}
		}

		// Excluir recursos estáticos rotos (fotos, fuentes, etc.)
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '';
		$path = parse_url( $request_uri, PHP_URL_PATH );
		if ( ! empty( $path ) ) {
			$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			$excluded_exts = array( 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'css', 'js', 'woff', 'woff2', 'ttf', 'eot', 'ico', 'map', 'pdf', 'mp4', 'mp3', 'ogg', 'webm' );
			if ( in_array( $ext, $excluded_exts, true ) ) {
				return;
			}
		}

		global $wpdb;
		$table_logs = $wpdb->prefix . 'wpat_404_logs';
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';

		// Registrar en BD el 404
		$wpdb->insert(
			$table_logs,
			array(
				'ip'            => $ip,
				'user_agent'    => substr( $user_agent, 0, 255 ),
				'requested_url' => substr( $request_uri, 0, 500 ),
			),
			array( '%s', '%s', '%s' )
		);

		// Calcular si sobrepasa los límites establecidos
		$limit = isset( $settings['bot_blocker_limit'] ) ? intval( $settings['bot_blocker_limit'] ) : 15;
		$timeframe = isset( $settings['bot_blocker_timeframe'] ) ? intval( $settings['bot_blocker_timeframe'] ) : 300;
		$duration = isset( $settings['bot_blocker_duration'] ) ? intval( $settings['bot_blocker_duration'] ) : 24;

		$time_limit = date( 'Y-m-d H:i:s', time() - $timeframe );

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table_logs WHERE ip = %s AND created_at > %s",
			$ip,
			$time_limit
		) );

		if ( $count >= $limit ) {
			$expires_at = date( 'Y-m-d H:i:s', time() + ( $duration * HOUR_IN_SECONDS ) );
			
			$wpdb->replace(
				$table_blocked,
				array(
					'ip'         => $ip,
					'reason'     => sprintf( 'Superado límite de %d errores 404 en %d segundos.', $limit, $timeframe ),
					'expires_at' => $expires_at,
				),
				array( '%s', '%s', '%s' )
			);
		}
	}

	public function ajax_unblock_ip() {
		check_ajax_referer( 'wpat_bot_blocker_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( $_POST['ip'] ) : '';
		if ( empty( $ip ) ) {
			wp_send_json_error( array( 'message' => 'IP inválida.' ) );
		}

		global $wpdb;
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';
		$table_logs = $wpdb->prefix . 'wpat_404_logs';

		$wpdb->delete( $table_blocked, array( 'ip' => $ip ), array( '%s' ) );
		$wpdb->delete( $table_logs, array( 'ip' => $ip ), array( '%s' ) );

		wp_send_json_success( array( 'message' => 'La IP ha sido desbloqueada e historial reseteado.' ) );
	}

	/**
	 * AJAX endpoint para meter una IP en la lista blanca.
	 */
	public function ajax_whitelist_ip() {
		check_ajax_referer( 'wpat_bot_blocker_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( $_POST['ip'] ) : '';
		if ( empty( $ip ) ) {
			wp_send_json_error( array( 'message' => 'IP inválida.' ) );
		}

		global $wpdb;
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';
		$table_logs = $wpdb->prefix . 'wpat_404_logs';

		// Borrar de bloqueados
		$wpdb->delete( $table_blocked, array( 'ip' => $ip ), array( '%s' ) );
		$wpdb->delete( $table_logs, array( 'ip' => $ip ), array( '%s' ) );

		// Añadir a lista blanca en los ajustes del plugin
		$main = WPAT_Main::get_instance();
		$settings = $main->get_settings();
		$whitelist_raw = isset( $settings['bot_blocker_whitelist'] ) ? $settings['bot_blocker_whitelist'] : '';
		
		$whitelist = array_filter( array_map( 'trim', explode( ',', $whitelist_raw ) ) );
		if ( ! in_array( $ip, $whitelist, true ) ) {
			$whitelist[] = $ip;
			$settings['bot_blocker_whitelist'] = implode( ', ', $whitelist );
			update_option( 'wpat_settings', $settings );
		}

		wp_send_json_success( array( 'message' => 'La IP ha sido añadida a la lista blanca.', 'whitelist' => $settings['bot_blocker_whitelist'] ) );
	}
}

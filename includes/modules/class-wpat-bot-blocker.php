<?php
/**
 * Módulo: Bloqueador de Bots por 404 & Anti-DDoS - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Bot_Blocker {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Bot_Blocker|null
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

		// Cron diario para purga automática de logs y registros antiguos
		if ( ! wp_next_scheduled( 'wpat_bot_blocker_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wpat_bot_blocker_daily_cleanup' );
		}
		add_action( 'wpat_bot_blocker_daily_cleanup', array( $this, 'cleanup_old_logs_and_blocks' ) );

		// Endpoints AJAX para desbloquear e ingresar a lista blanca
		add_action( 'wp_ajax_wpat_unblock_ip', array( $this, 'ajax_unblock_ip' ) );
		add_action( 'wp_ajax_wpat_whitelist_ip', array( $this, 'ajax_whitelist_ip' ) );
	}

	/**
	 * Crea las tablas de base de datos si no existen.
	 */
	private function check_and_create_tables() {
		if ( get_option( 'wpat_bot_blocker_db_version' ) !== '1.1' ) {
			global $wpdb;
			$table_logs      = $wpdb->prefix . 'wpat_404_logs';
			$table_blocked   = $wpdb->prefix . 'wpat_blocked_ips';
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

			update_option( 'wpat_bot_blocker_db_version', '1.1' );
		}
	}

	/**
	 * Obtiene la dirección IP real del visitante actual con soporte para Cloudflare y proxies seguros.
	 *
	 * @return string
	 */
	public function get_visitor_ip() {
		$keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_TRUE_CLIENT_IP',
			'HTTP_FASTLY_CLIENT_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$raw = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ips = explode( ',', $raw );
				foreach ( $ips as $candidate ) {
					$candidate = trim( $candidate );
					if ( filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
						return $candidate;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Comprueba si una dirección IP está en la lista blanca (incluyendo subredes CIDR).
	 *
	 * @param string $ip            Dirección IP a comprobar.
	 * @param string $whitelist_raw Cadena de IPs o rangos separados por coma.
	 * @return bool
	 */
	public static function is_ip_whitelisted( $ip, $whitelist_raw = '' ) {
		if ( empty( $ip ) ) {
			return false;
		}

		// Localhost y la IP del propio servidor nunca se bloquean
		if ( in_array( $ip, array( '127.0.0.1', '::1', 'localhost' ), true ) ) {
			return true;
		}
		if ( ! empty( $_SERVER['SERVER_ADDR'] ) && $ip === $_SERVER['SERVER_ADDR'] ) {
			return true;
		}

		if ( empty( $whitelist_raw ) ) {
			return false;
		}

		$entries = array_filter( array_map( 'trim', explode( ',', $whitelist_raw ) ) );
		foreach ( $entries as $entry ) {
			if ( $ip === $entry ) {
				return true;
			}

			// Soporte para notación de subred CIDR (ej. 192.168.1.0/24)
			if ( false !== strpos( $entry, '/' ) ) {
				if ( self::ip_in_range( $ip, $entry ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Comprueba si una IP pertenece a un rango CIDR.
	 *
	 * @param string $ip    Dirección IP.
	 * @param string $range Rango CIDR (ej: 10.0.0.0/8).
	 * @return bool
	 */
	public static function ip_in_range( $ip, $range ) {
		if ( false === strpos( $range, '/' ) ) {
			return $ip === $range;
		}

		list( $subnet, $bits ) = explode( '/', $range, 2 );
		$bits = (int) $bits;

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			if ( $bits < 0 || $bits > 32 ) {
				return false;
			}
			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );
			$mask        = -1 << ( 32 - $bits );
			$subnet_long &= $mask;
			return ( $ip_long & $mask ) === $subnet_long;
		}

		return false;
	}

	/**
	 * Purga registros de logs antiguos y bloqueos expirados para mantener la base de datos ligera.
	 */
	public function cleanup_old_logs_and_blocks() {
		global $wpdb;
		$table_logs    = $wpdb->prefix . 'wpat_404_logs';
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';

		// Purgar logs de 404 con más de 7 días
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_logs'" ) === $table_logs ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM $table_logs WHERE created_at < NOW() - INTERVAL 7 DAY" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Purgar bloqueos expirados hace más de 7 días
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_blocked'" ) === $table_blocked ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DELETE FROM $table_blocked WHERE expires_at < NOW() - INTERVAL 7 DAY" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Verifica si la IP del visitante actual está en la lista de bloqueo activo.
	 */
	public function check_visitor_block() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		$ip = $this->get_visitor_ip();
		if ( empty( $ip ) ) {
			return;
		}

		// No bloquear jamás a un administrador de WordPress
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings      = WPAT_Main::get_instance()->get_settings();
		$whitelist_raw = isset( $settings['bot_blocker_whitelist'] ) ? $settings['bot_blocker_whitelist'] : '';

		if ( self::is_ip_whitelisted( $ip, $whitelist_raw ) ) {
			return;
		}

		global $wpdb;
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';

		$is_blocked = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_blocked WHERE ip = %s AND expires_at > NOW()", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ip
			)
		);

		if ( $is_blocked ) {
			$expires_ts = strtotime( $is_blocked->expires_at );
			$remaining  = max( 60, $expires_ts - time() );

			status_header( 429 );
			nocache_headers();
			header( 'Retry-After: ' . $remaining );

			$expires = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_ts );

			?>
			<!DOCTYPE html>
			<html lang="es">
			<head>
				<meta charset="utf-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title><?php esc_html_e( 'Acceso Temporalmente Restringido - Seguridad', 'wp-agency-toolkit' ); ?></title>
				<style>
					body { background: #f8fafc; color: #334155; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,Cantarell,sans-serif; text-align: center; padding: 50px 20px; margin: 0; box-sizing: border-box; }
					.container { background: #ffffff; max-width: 520px; margin: 0 auto; padding: 36px 28px; border-radius: 14px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01); border: 1px solid #e2e8f0; }
					.icon-badge { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background: #fee2e2; color: #ef4444; border-radius: 50%; font-size: 26px; margin-bottom: 16px; }
					h1 { color: #0f172a; font-size: 22px; font-weight: 700; margin: 0 0 14px 0; }
					p { font-size: 14.5px; line-height: 1.6; color: #64748b; margin: 0 0 16px 0; }
					.info { background: #f1f5f9; padding: 14px 16px; border-radius: 8px; margin: 20px 0; font-size: 13px; text-align: left; border-left: 4px solid #ef4444; color: #334155; line-height: 1.6; }
					.footer { font-size: 12px; color: #94a3b8; margin-top: 24px; }
				</style>
			</head>
			<body>
				<div class="container">
					<div class="icon-badge">🛡️</div>
					<h1><?php esc_html_e( 'Acceso Temporalmente Restringido', 'wp-agency-toolkit' ); ?></h1>
					<p><?php esc_html_e( 'Tu dirección IP ha sido bloqueada temporalmente por nuestro sistema de protección anti-DDoS tras registrar un volumen anómalo de solicitudes o errores 404.', 'wp-agency-toolkit' ); ?></p>
					<div class="info">
						<strong><?php esc_html_e( 'IP Registrada:', 'wp-agency-toolkit' ); ?></strong> <?php echo esc_html( $ip ); ?><br>
						<strong><?php esc_html_e( 'Motivo:', 'wp-agency-toolkit' ); ?></strong> <?php echo esc_html( $is_blocked->reason ); ?><br>
						<strong><?php esc_html_e( 'Desbloqueo automático el:', 'wp-agency-toolkit' ); ?></strong> <?php echo esc_html( $expires ); ?>
					</div>
					<p style="font-size: 13px;"><?php esc_html_e( 'Si eres un visitante legítimo y consideras que esto es un error, por favor contacta al administrador del sitio para restablecer tu acceso.', 'wp-agency-toolkit' ); ?></p>
					<div class="footer"><?php esc_html_e( 'Sistema de Protección Anti-DDoS — WP Agency Toolkit', 'wp-agency-toolkit' ); ?></div>
				</div>
			</body>
			</html>
			<?php
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

		$settings      = WPAT_Main::get_instance()->get_settings();
		$whitelist_raw = isset( $settings['bot_blocker_whitelist'] ) ? $settings['bot_blocker_whitelist'] : '';

		if ( self::is_ip_whitelisted( $ip, $whitelist_raw ) ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Excluir buscadores y rastreadores legítimos (evitar falsos positivos SEO)
		$good_bots = array(
			'googlebot',
			'bingbot',
			'slurp',
			'duckduckbot',
			'baiduspider',
			'yandexbot',
			'facebot',
			'facebookexternalhit',
			'twitterbot',
			'linkedinbot',
			'pinterestbot',
			'applebot',
			'petalbot',
			'whatsapp',
			'telegrambot',
			'semrushbot',
			'ahrefsbot',
			'uptimerobot',
		);

		$ua_lower = strtolower( $user_agent );
		foreach ( $good_bots as $bot ) {
			if ( false !== strpos( $ua_lower, $bot ) ) {
				return;
			}
		}

		// Excluir recursos estáticos rotos (fotos, fuentes, multimedia, etc.)
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = (string) parse_url( $request_uri, PHP_URL_PATH );

		if ( ! empty( $path ) ) {
			$ext           = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
			$excluded_exts = array(
				'jpg',
				'jpeg',
				'png',
				'gif',
				'svg',
				'webp',
				'avif',
				'css',
				'js',
				'woff',
				'woff2',
				'ttf',
				'eot',
				'ico',
				'map',
				'pdf',
				'mp4',
				'mp3',
				'ogg',
				'webm',
				'm4a',
				'flac',
				'json',
				'xml',
				'txt',
			);
			if ( in_array( $ext, $excluded_exts, true ) ) {
				return;
			}
		}

		global $wpdb;
		$table_logs    = $wpdb->prefix . 'wpat_404_logs';
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
		$limit     = isset( $settings['bot_blocker_limit'] ) ? intval( $settings['bot_blocker_limit'] ) : 15;
		$timeframe = isset( $settings['bot_blocker_timeframe'] ) ? intval( $settings['bot_blocker_timeframe'] ) : 300;
		$duration  = isset( $settings['bot_blocker_duration'] ) ? intval( $settings['bot_blocker_duration'] ) : 24;

		$time_limit = date( 'Y-m-d H:i:s', time() - $timeframe );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_logs WHERE ip = %s AND created_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ip,
				$time_limit
			)
		);

		if ( $count >= $limit ) {
			$expires_at = date( 'Y-m-d H:i:s', time() + ( $duration * HOUR_IN_SECONDS ) );

			$wpdb->replace(
				$table_blocked,
				array(
					'ip'         => $ip,
					'reason'     => sprintf( __( 'Superado límite de %1$d errores 404 en %2$d segundos.', 'wp-agency-toolkit' ), $limit, $timeframe ),
					'expires_at' => $expires_at,
				),
				array( '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Registra eventos sospechosos procedentes de otros módulos (ej. Anti-Spam o Fuerza Bruta).
	 *
	 * @param string|null $ip     Dirección IP sospechosa (opcional).
	 * @param string      $reason Motivo o descripción del evento.
	 * @param int         $weight Peso o número de infracciones a contabilizar.
	 */
	public static function record_suspicious_ip( $ip = null, $reason = '', $weight = 1 ) {
		$instance = self::get_instance();
		if ( empty( $ip ) ) {
			$ip = $instance->get_visitor_ip();
		}
		if ( empty( $ip ) ) {
			return;
		}

		$settings      = WPAT_Main::get_instance()->get_settings();
		$whitelist_raw = isset( $settings['bot_blocker_whitelist'] ) ? $settings['bot_blocker_whitelist'] : '';

		if ( self::is_ip_whitelisted( $ip, $whitelist_raw ) ) {
			return;
		}

		global $wpdb;
		$table_logs    = $wpdb->prefix . 'wpat_404_logs';
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';

		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'Security Module';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ( ! empty( $reason ) ? $reason : 'Suspicious Activity' );

		for ( $i = 0; $i < max( 1, (int) $weight ); $i++ ) {
			$wpdb->insert(
				$table_logs,
				array(
					'ip'            => $ip,
					'user_agent'    => substr( $user_agent, 0, 255 ),
					'requested_url' => substr( $request_uri, 0, 500 ),
				),
				array( '%s', '%s', '%s' )
			);
		}

		$limit      = isset( $settings['bot_blocker_limit'] ) ? intval( $settings['bot_blocker_limit'] ) : 15;
		$timeframe  = isset( $settings['bot_blocker_timeframe'] ) ? intval( $settings['bot_blocker_timeframe'] ) : 300;
		$duration   = isset( $settings['bot_blocker_duration'] ) ? intval( $settings['bot_blocker_duration'] ) : 24;
		$time_limit = date( 'Y-m-d H:i:s', time() - $timeframe );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_logs WHERE ip = %s AND created_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ip,
				$time_limit
			)
		);

		if ( $count >= $limit ) {
			$expires_at = date( 'Y-m-d H:i:s', time() + ( $duration * HOUR_IN_SECONDS ) );

			$wpdb->replace(
				$table_blocked,
				array(
					'ip'         => $ip,
					'reason'     => ! empty( $reason ) ? $reason : sprintf( __( 'Superado límite de actividad sospechosa (%1$d eventos en %2$d seg).', 'wp-agency-toolkit' ), $limit, $timeframe ),
					'expires_at' => $expires_at,
				),
				array( '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * AJAX endpoint para desbloquear manualmente una IP y resetear su historial.
	 */
	public function ajax_unblock_ip() {
		check_ajax_referer( 'wpat_bot_blocker_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) ) );
		}

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		if ( empty( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Dirección IP inválida.', 'wp-agency-toolkit' ) ) );
		}

		global $wpdb;
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';
		$table_logs    = $wpdb->prefix . 'wpat_404_logs';

		$wpdb->delete( $table_blocked, array( 'ip' => $ip ), array( '%s' ) );
		$wpdb->delete( $table_logs, array( 'ip' => $ip ), array( '%s' ) );

		wp_send_json_success( array( 'message' => __( 'La IP ha sido desbloqueada e historial reseteado con éxito.', 'wp-agency-toolkit' ) ) );
	}

	/**
	 * AJAX endpoint para añadir una IP a la lista blanca de seguridad.
	 */
	public function ajax_whitelist_ip() {
		check_ajax_referer( 'wpat_bot_blocker_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos suficientes.', 'wp-agency-toolkit' ) ) );
		}

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		if ( empty( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Dirección IP inválida.', 'wp-agency-toolkit' ) ) );
		}

		global $wpdb;
		$table_blocked = $wpdb->prefix . 'wpat_blocked_ips';
		$table_logs    = $wpdb->prefix . 'wpat_404_logs';

		// Borrar de tablas de bloqueo
		$wpdb->delete( $table_blocked, array( 'ip' => $ip ), array( '%s' ) );
		$wpdb->delete( $table_logs, array( 'ip' => $ip ), array( '%s' ) );

		// Añadir a lista blanca en los ajustes del plugin
		$main          = WPAT_Main::get_instance();
		$settings      = $main->get_settings();
		$whitelist_raw = isset( $settings['bot_blocker_whitelist'] ) ? $settings['bot_blocker_whitelist'] : '';

		$whitelist = array_filter( array_map( 'trim', explode( ',', $whitelist_raw ) ) );
		if ( ! in_array( $ip, $whitelist, true ) ) {
			$whitelist[]                       = $ip;
			$settings['bot_blocker_whitelist'] = implode( ', ', $whitelist );
			update_option( 'wpat_settings', $settings );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'La IP ha sido añadida a la lista blanca.', 'wp-agency-toolkit' ),
				'whitelist' => $settings['bot_blocker_whitelist'],
			)
		);
	}
}


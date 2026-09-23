<?php
/**
 * Módulo: Visor y Monitor de Logs de Error - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Error_Log_Viewer {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Error_Log_Viewer|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Error_Log_Viewer
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
		// Endpoints AJAX para el panel de administración
		add_action( 'wp_ajax_wpat_get_error_logs', array( $this, 'ajax_get_error_logs' ) );
		add_action( 'wp_ajax_wpat_clear_error_log', array( $this, 'ajax_clear_error_log' ) );
		add_action( 'wp_ajax_wpat_download_error_log', array( $this, 'ajax_download_error_log' ) );
		
		// Interceptar descarga directa si se solicita por GET
		add_action( 'admin_init', array( $this, 'maybe_handle_direct_download' ) );
	}

	/**
	 * Detecta la ruta del archivo debug.log o error_log del servidor.
	 *
	 * @return string
	 */
	public static function get_log_file_path() {
		// 1. debug.log estándar de WordPress en wp-content
		$wp_debug_log = WP_CONTENT_DIR . '/debug.log';
		if ( file_exists( $wp_debug_log ) ) {
			return $wp_debug_log;
		}

		// 2. Ruta configurada en ini_get('error_log')
		$ini_log = ini_get( 'error_log' );
		if ( ! empty( $ini_log ) && file_exists( $ini_log ) && is_readable( $ini_log ) ) {
			return $ini_log;
		}

		// 3. Ruta por defecto aunque no exista todavía para creación
		return $wp_debug_log;
	}

	/**
	 * Lee las últimas líneas del archivo de log de forma ultra-eficiente sin desbordar memoria.
	 *
	 * @param int $max_lines Cantidad de líneas a recuperar.
	 * @param int $max_bytes Límite en bytes para leer (por defecto 2MB).
	 * @return array
	 */
	public static function read_last_log_lines( $max_lines = 300, $max_bytes = 2097152 ) {
		$file_path = self::get_log_file_path();

		if ( ! file_exists( $file_path ) ) {
			return array(
				'exists'   => false,
				'readable' => false,
				'size'     => 0,
				'size_fmt' => '0 B',
				'path'     => $file_path,
				'entries'  => array(),
				'raw'      => '',
			);
		}

		$file_size = (int) filesize( $file_path );
		$size_fmt  = size_format( $file_size );

		if ( $file_size === 0 ) {
			return array(
				'exists'   => true,
				'readable' => true,
				'size'     => 0,
				'size_fmt' => '0 B',
				'path'     => $file_path,
				'entries'  => array(),
				'raw'      => '',
			);
		}

		$fp = @fopen( $file_path, 'rb' );
		if ( ! $fp ) {
			return array(
				'exists'   => true,
				'readable' => false,
				'size'     => $file_size,
				'size_fmt' => $size_fmt,
				'path'     => $file_path,
				'entries'  => array(),
				'raw'      => '',
			);
		}

		// Leer los últimos N bytes con fseek
		$read_size = min( $file_size, $max_bytes );
		fseek( $fp, -$read_size, SEEK_END );
		$content = fread( $fp, $read_size );
		fclose( $fp );

		$lines   = explode( "\n", str_replace( "\r", '', $content ) );
		$entries = array();
		$current_entry = null;

		// Procesar y agrupar stack traces
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( empty( $trimmed ) ) {
				continue;
			}

			// Comprobar si la línea inicia con un timestamp [DD-Mon-YYYY HH:MM:SS UTC]
			if ( preg_match( '/^\[([0-9]{2}-[A-Za-z]{3}-[0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2}(?: [A-Za-z\/]+)?)\]\s*(.*)$/i', $trimmed, $matches ) ) {
				if ( null !== $current_entry ) {
					$entries[] = $current_entry;
				}

				$timestamp = $matches[1];
				$rest_msg  = $matches[2];
				$parsed    = self::parse_log_message( $rest_msg );

				$current_entry = array(
					'timestamp'   => $timestamp,
					'time_human'  => self::format_timestamp( $timestamp ),
					'severity'    => $parsed['severity'],
					'badge_class' => $parsed['badge_class'],
					'message'     => $parsed['message'],
					'file'        => $parsed['file'],
					'line'        => $parsed['line'],
					'stack_trace' => '',
					'raw_line'    => $trimmed,
				);
			} else {
				// Es parte de un stack trace de la entrada anterior
				if ( null !== $current_entry ) {
					$current_entry['stack_trace'] .= ( empty( $current_entry['stack_trace'] ) ? '' : "\n" ) . $trimmed;
				}
			}
		}

		if ( null !== $current_entry ) {
			$entries[] = $current_entry;
		}

		// Invertir para que los errores más recientes aparezcan al principio
		$entries = array_reverse( $entries );
		if ( count( $entries ) > $max_lines ) {
			$entries = array_slice( $entries, 0, $max_lines );
		}

		return array(
			'exists'   => true,
			'readable' => true,
			'size'     => $file_size,
			'size_fmt' => $size_fmt,
			'path'     => $file_path,
			'entries'  => $entries,
			'raw'      => $content,
		);
	}

	/**
	 * Parsea el texto del mensaje para extraer severidad, mensaje, archivo y número de línea.
	 *
	 * @param string $message Mensaje bruto.
	 * @return array
	 */
	public static function parse_log_message( $message ) {
		$severity    = 'Notice';
		$badge_class = 'wpat-notice';
		$clean_msg   = $message;
		$file        = '';
		$line        = '';

		// Extraer severidad
		if ( stripos( $message, 'PHP Fatal error' ) !== false || stripos( $message, 'Fatal error' ) !== false || stripos( $message, 'Uncaught Error' ) !== false ) {
			$severity    = 'Fatal Error';
			$badge_class = 'wpat-fatal';
		} elseif ( stripos( $message, 'PHP Parse error' ) !== false || stripos( $message, 'Parse error' ) !== false || stripos( $message, 'syntax error' ) !== false ) {
			$severity    = 'Parse Error';
			$badge_class = 'wpat-fatal';
		} elseif ( stripos( $message, 'PHP Warning' ) !== false || stripos( $message, 'Warning:' ) !== false ) {
			$severity    = 'Warning';
			$badge_class = 'wpat-warning';
		} elseif ( stripos( $message, 'PHP Deprecated' ) !== false || stripos( $message, 'Deprecated:' ) !== false ) {
			$severity    = 'Deprecated';
			$badge_class = 'wpat-deprecated';
		} elseif ( stripos( $message, 'PHP Notice' ) !== false || stripos( $message, 'Notice:' ) !== false ) {
			$severity    = 'Notice';
			$badge_class = 'wpat-notice';
		} elseif ( stripos( $message, 'WordPress database error' ) !== false ) {
			$severity    = 'DB Error';
			$badge_class = 'wpat-fatal';
		}

		// Extraer archivo y línea si coincide con " in /path/to/file.php on line 123"
		if ( preg_match( '/\s+in\s+([^\s]+\.php)(?:\s+on\s+line\s+([0-9]+))?/i', $clean_msg, $file_matches ) ) {
			$file = isset( $file_matches[1] ) ? $file_matches[1] : '';
			$line = isset( $file_matches[2] ) ? $file_matches[2] : '';
			$clean_msg = preg_replace( '/\s+in\s+[^\s]+\.php(?:\s+on\s+line\s+[0-9]+)?/i', '', $clean_msg );
		}

		// Limpiar prefijos redundantes
		$clean_msg = preg_replace( '/^PHP\s+(?:Fatal error|Parse error|Warning|Notice|Deprecated):\s*/i', '', $clean_msg );

		return array(
			'severity'    => $severity,
			'badge_class' => $badge_class,
			'message'     => trim( $clean_msg ),
			'file'        => $file,
			'line'        => $line,
		);
	}

	/**
	 * Formatea timestamps de log a formato legible humano.
	 *
	 * @param string $timestamp Timestamp del log.
	 * @return string
	 */
	public static function format_timestamp( $timestamp ) {
		$time = strtotime( $timestamp );
		if ( ! $time ) {
			return $timestamp;
		}

		$diff = time() - $time;
		if ( $diff < 60 ) {
			return 'Hace unos segundos';
		} elseif ( $diff < 3600 ) {
			$mins = max( 1, round( $diff / 60 ) );
			return 'Hace ' . $mins . ' min';
		} elseif ( $diff < 86400 ) {
			$hours = round( $diff / 3600 );
			return 'Hace ' . $hours . ' h';
		} else {
			return gmdate( 'd/m/Y H:i:s', $time );
		}
	}

	/**
	 * Obtiene el estado actual de las constantes de depuración de WordPress.
	 *
	 * @return array
	 */
	public static function get_debug_status() {
		return array(
			'wp_debug'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'     => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'wp_debug_display' => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'script_debug'     => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'php_version'      => PHP_VERSION,
			'memory_limit'     => ini_get( 'memory_limit' ),
		);
	}

	/**
	 * Endpoint AJAX para obtener registros de log en tiempo real (Live Polling).
	 */
	public function ajax_get_error_logs() {
		check_ajax_referer( 'wpat_error_log_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$log_data = self::read_last_log_lines( 250 );
		$stats    = array(
			'total'      => count( $log_data['entries'] ),
			'fatal'      => 0,
			'warning'    => 0,
			'notice'     => 0,
			'deprecated' => 0,
		);

		foreach ( $log_data['entries'] as $entry ) {
			if ( 'wpat-fatal' === $entry['badge_class'] ) {
				$stats['fatal']++;
			} elseif ( 'wpat-warning' === $entry['badge_class'] ) {
				$stats['warning']++;
			} elseif ( 'wpat-deprecated' === $entry['badge_class'] ) {
				$stats['deprecated']++;
			} else {
				$stats['notice']++;
			}
		}

		wp_send_json_success(
			array(
				'exists'   => $log_data['exists'],
				'readable' => $log_data['readable'],
				'size_fmt' => $log_data['size_fmt'],
				'path'     => $log_data['path'],
				'stats'    => $stats,
				'entries'  => $log_data['entries'],
				'raw'      => esc_html( $log_data['raw'] ),
			)
		);
	}

	/**
	 * Endpoint AJAX para vaciar el archivo debug.log.
	 */
	public function ajax_clear_error_log() {
		check_ajax_referer( 'wpat_error_log_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$file_path = self::get_log_file_path();

		if ( ! file_exists( $file_path ) ) {
			wp_send_json_success( array( 'message' => 'El archivo de log ya estaba vacío o no existe.' ) );
		}

		if ( ! is_writable( $file_path ) ) {
			wp_send_json_error( array( 'message' => 'El archivo de log no tiene permisos de escritura en el servidor.' ) );
		}

		$fp = @fopen( $file_path, 'w' );
		if ( false !== $fp ) {
			fclose( $fp );
			wp_send_json_success( array( 'message' => 'El registro de errores se ha vaciado correctamente.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'No se pudo abrir el archivo para truncarlo.' ) );
		}
	}

	/**
	 * Descarga directa del archivo debug.log si se solicita por URL.
	 */
	public function maybe_handle_direct_download() {
		if ( isset( $_GET['wpat_action'] ) && 'download_error_log' === $_GET['wpat_action'] ) {
			if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpat_download_log_nonce' ) ) {
				wp_die( 'Enlace de descarga caducado o permisos insuficientes.' );
			}

			$file_path = self::get_log_file_path();
			if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				wp_die( 'El archivo de log no existe o no tiene permisos de lectura.' );
			}

			while ( ob_get_level() ) {
				ob_end_clean();
			}

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="debug-' . date( 'Y-m-d-His' ) . '.log"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize( $file_path ) );
			readfile( $file_path );
			exit;
		}
	}
}

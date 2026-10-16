<?php
/**
 * Módulo: Explorador de Archivos Web con Seguridad 2FA - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_File_Manager {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_File_Manager|null
	 */
	private static $instance = null;

	/**
	 * Directorio base de copias de seguridad de archivos editados.
	 */
	const BACKUP_DIR_NAME = 'wpat-backups';

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_File_Manager
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
		// Endpoints AJAX para 2FA y Sesión
		add_action( 'wp_ajax_wpat_fm_setup_2fa', array( $this, 'ajax_setup_2fa' ) );
		add_action( 'wp_ajax_wpat_fm_verify_2fa', array( $this, 'ajax_verify_2fa' ) );
		add_action( 'wp_ajax_wpat_fm_lock_session', array( $this, 'ajax_lock_session' ) );
		add_action( 'wp_ajax_wpat_fm_reset_2fa', array( $this, 'ajax_reset_2fa' ) );

		// Endpoints AJAX para Operaciones de Archivos
		add_action( 'wp_ajax_wpat_fm_list_dir', array( $this, 'ajax_list_dir' ) );
		add_action( 'wp_ajax_wpat_fm_get_file', array( $this, 'ajax_get_file' ) );
		add_action( 'wp_ajax_wpat_fm_save_file', array( $this, 'ajax_save_file' ) );
		add_action( 'wp_ajax_wpat_fm_create_item', array( $this, 'ajax_create_item' ) );
		add_action( 'wp_ajax_wpat_fm_rename_item', array( $this, 'ajax_rename_item' ) );
		add_action( 'wp_ajax_wpat_fm_delete_item', array( $this, 'ajax_delete_item' ) );
		add_action( 'wp_ajax_wpat_fm_upload_file', array( $this, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_wpat_fm_download_item', array( $this, 'ajax_download_item' ) );

		// Manejar descarga directa binaria si se solicita por GET
		add_action( 'admin_init', array( $this, 'maybe_handle_direct_download' ) );
	}

	/* =========================================================================
	 * SEGURIDAD Y GESTIÓN DE SESIÓN 2FA (TOTP RFC 6238)
	 * ========================================================================= */

	/**
	 * Comprueba si el usuario actual tiene permisos de Administrador Supremo.
	 *
	 * @return bool
	 */
	public static function current_user_can_access() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Comprueba si un usuario tiene configurado el 2FA en su perfil.
	 *
	 * @param int|null $user_id ID del usuario.
	 * @return bool
	 */
	public static function is_user_setup( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		$secret = get_user_meta( $user_id, 'wpat_fm_totp_secret', true );
		return ! empty( $secret );
	}

	/**
	 * Comprueba si la sesión 2FA está actualmente desbloqueada y vigente.
	 *
	 * @param int|null $user_id ID del usuario.
	 * @return bool
	 */
	public static function is_session_unlocked( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		$session_token = get_transient( 'wpat_fm_session_' . $user_id );
		if ( empty( $session_token ) ) {
			return false;
		}

		$stored_hash = get_user_meta( $user_id, 'wpat_fm_session_hash', true );
		if ( empty( $stored_hash ) ) {
			return false;
		}

		return hash_equals( $stored_hash, wp_hash( $session_token . '|' . $user_id ) );
	}

	/**
	 * Desbloquea la sesión de acceso por 20 minutos tras superar el 2FA.
	 *
	 * @param int $user_id ID del usuario.
	 * @param int $timeout_minutes Duración en minutos (por defecto 20).
	 * @return string Token de sesión generado.
	 */
	public static function unlock_session( $user_id, $timeout_minutes = 20 ) {
		$token = wp_generate_password( 32, false );
		$hash  = wp_hash( $token . '|' . $user_id );
		$exp   = $timeout_minutes * MINUTE_IN_SECONDS;

		set_transient( 'wpat_fm_session_' . $user_id, $token, $exp );
		update_user_meta( $user_id, 'wpat_fm_session_hash', $hash );
		delete_transient( 'wpat_fm_failed_' . $user_id ); // Reset de intentos fallidos

		return $token;
	}

	/**
	 * Bloquea inmediatamente la sesión activa.
	 *
	 * @param int|null $user_id ID del usuario.
	 */
	public static function lock_session( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id ) {
			delete_transient( 'wpat_fm_session_' . $user_id );
			delete_user_meta( $user_id, 'wpat_fm_session_hash' );
		}
	}

	/**
	 * Verifica que la petición AJAX esté completamente autorizada y con 2FA activo.
	 */
	public function verify_ajax_access() {
		if ( ! self::current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes. Solo administradores pueden acceder.', 'wp-agency-toolkit' ) ), 403 );
		}

		check_ajax_referer( 'wpat_file_manager_nonce', 'nonce' );

		if ( ! self::is_session_unlocked() ) {
			wp_send_json_error( array(
				'locked'  => true,
				'message' => __( 'Sesión bloqueada por seguridad. Introduce tu código 2FA para continuar.', 'wp-agency-toolkit' ),
			), 403 );
		}
	}

	/* =========================================================================
	 * MOTOR CRIPTOGRÁFICO TOTP NATIVO (RFC 6238 - SIN DEPENDENCIAS EXTERNAS)
	 * ========================================================================= */

	/**
	 * Alfabeto Base32 estándar (RFC 4648).
	 */
	private static $base32_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/**
	 * Genera un secreto Base32 seguro de 16 caracteres para TOTP.
	 *
	 * @return string
	 */
	public static function generate_totp_secret() {
		$secret = '';
		$length = 16;
		for ( $i = 0; $i < $length; $i++ ) {
			$secret .= self::$base32_chars[ wp_rand( 0, 31 ) ];
		}
		return $secret;
	}

	/**
	 * Decodifica una cadena Base32 a binario.
	 *
	 * @param string $secret Secreto en Base32.
	 * @return string|false
	 */
	private static function base32_decode( $secret ) {
		$secret = strtoupper( trim( $secret ) );
		$buffer = 0;
		$bits_left = 0;
		$binary = '';

		for ( $i = 0; $i < strlen( $secret ); $i++ ) {
			$val = strpos( self::$base32_chars, $secret[ $i ] );
			if ( false === $val ) {
				continue;
			}
			$buffer = ( $buffer << 5 ) | $val;
			$bits_left += 5;
			if ( $bits_left >= 8 ) {
				$bits_left -= 8;
				$binary .= chr( ( $buffer >> $bits_left ) & 0xFF );
			}
		}

		return $binary;
	}

	/**
	 * Calcula el código TOTP de 6 dígitos para un instante temporal dado.
	 *
	 * @param string   $secret Secreto Base32.
	 * @param int|null $time_slice Intervalo de tiempo de 30 segundos.
	 * @return string Código de 6 dígitos con ceros a la izquierda.
	 */
	public static function calculate_totp_code( $secret, $time_slice = null ) {
		if ( null === $time_slice ) {
			$time_slice = floor( time() / 30 );
		}

		$binary_key = self::base32_decode( $secret );
		if ( empty( $binary_key ) ) {
			return '';
		}

		// Empaquetar el time_slice en formato entero de 64-bit big-endian
		$packed_time = pack( 'N*', 0 ) . pack( 'N*', $time_slice );
		$hash        = hash_hmac( 'sha1', $packed_time, $binary_key, true );

		$offset = ord( $hash[ strlen( $hash ) - 1 ] ) & 0x0F;
		$truncated_hash = (
			( ( ord( $hash[ $offset + 0 ] ) & 0x7F ) << 24 ) |
			( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 ) |
			( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 ) |
			( ord( $hash[ $offset + 3 ] ) & 0xFF )
		);

		$code = $truncated_hash % 1000000;
		return str_pad( (string) $code, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Valida un código introducido con tolerancia de ±1 paso temporal (30s) y anti-repetición.
	 *
	 * @param string $secret Secreto Base32.
	 * @param string $code Código de 6 dígitos introducido.
	 * @return bool
	 */
	public static function verify_totp_code( $secret, $code ) {
		$code = preg_replace( '/\s+/', '', (string) $code );
		if ( 6 !== strlen( $code ) || ! ctype_digit( $code ) ) {
			return false;
		}

		$current_slice = floor( time() / 30 );

		// Comprobar ventana de tiempo [-1, 0, +1] para mitigar desvíos de reloj de hasta 30 seg
		for ( $offset = -1; $offset <= 1; $offset++ ) {
			$slice = $current_slice + $offset;
			$expected_code = self::calculate_totp_code( $secret, $slice );
			if ( hash_equals( $expected_code, $code ) ) {
				// Anti-replay: asegurar que este código no se reutilice en los siguientes 60 segundos
				$replay_key = 'wpat_fm_replay_' . md5( $secret . '_' . $code . '_' . $slice );
				if ( get_transient( $replay_key ) ) {
					return false;
				}
				set_transient( $replay_key, 1, 60 );
				return true;
			}
		}

		return false;
	}

	/**
	 * Genera un conjunto de 3 códigos de recuperación de un solo uso.
	 *
	 * @param int $user_id ID del usuario.
	 * @return array Lista de códigos en texto plano para mostrar al usuario.
	 */
	public static function generate_recovery_codes( $user_id ) {
		$plain_codes  = array();
		$hashed_codes = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$code = wp_generate_password( 10, false, false );
			$plain_codes[]  = $code;
			$hashed_codes[] = wp_hash_password( $code );
		}

		update_user_meta( $user_id, 'wpat_fm_recovery_codes', $hashed_codes );
		return $plain_codes;
	}

	/**
	 * Valida y consume un código de recuperación de un solo uso.
	 *
	 * @param int    $user_id ID del usuario.
	 * @param string $code Código de rescate introducido.
	 * @return bool
	 */
	public static function verify_and_consume_recovery_code( $user_id, $code ) {
		$code = trim( $code );
		$hashed_codes = get_user_meta( $user_id, 'wpat_fm_recovery_codes', true );
		if ( ! is_array( $hashed_codes ) || empty( $hashed_codes ) ) {
			return false;
		}

		foreach ( $hashed_codes as $idx => $hashed ) {
			if ( wp_check_password( $code, $hashed ) ) {
				unset( $hashed_codes[ $idx ] );
				update_user_meta( $user_id, 'wpat_fm_recovery_codes', array_values( $hashed_codes ) );
				return true;
			}
		}

		return false;
	}

	/* =========================================================================
	 * ENDPOINTS AJAX DE AUTENTICACIÓN 2FA
	 * ========================================================================= */

	/**
	 * AJAX: Inicia la configuración de 2FA generando el secreto y la URL de Google Authenticator.
	 */
	public function ajax_setup_2fa() {
		if ( ! self::current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Acceso denegado.', 'wp-agency-toolkit' ) ), 403 );
		}

		check_ajax_referer( 'wpat_file_manager_nonce', 'nonce' );

		$user_id = get_current_user_id();
		$secret  = self::generate_totp_secret();

		// Guardar temporalmente el secreto pendiente de confirmación
		set_transient( 'wpat_fm_pending_secret_' . $user_id, $secret, 15 * MINUTE_IN_SECONDS );

		$blog_name = get_bloginfo( 'name' );
		$user      = wp_get_current_user();
		$account   = rawurlencode( $user->user_login );
		$issuer    = rawurlencode( 'WP Agency Toolkit (' . $blog_name . ')' );
		$otp_uri   = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";

		wp_send_json_success( array(
			'secret'    => $secret,
			'otp_uri'   => $otp_uri,
			'blog_name' => $blog_name,
		) );
	}

	/**
	 * AJAX: Verifica el código 2FA o código de rescate para desbloquear la sesión.
	 */
	public function ajax_verify_2fa() {
		if ( ! self::current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Acceso denegado.', 'wp-agency-toolkit' ) ), 403 );
		}

		check_ajax_referer( 'wpat_file_manager_nonce', 'nonce' );

		$user_id = get_current_user_id();

		// Control de intentos fallidos / Fuerza bruta (Máximo 4 intentos, congelado 15 minutos)
		$failed_attempts = (int) get_transient( 'wpat_fm_failed_' . $user_id );
		if ( $failed_attempts >= 4 ) {
			wp_send_json_error( array(
				'message' => __( 'Demasiados intentos fallidos. Por seguridad, el acceso al explorador está bloqueado temporalmente durante 15 minutos.', 'wp-agency-toolkit' ),
				'lockout' => true,
			), 429 );
		}

		$code     = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$is_setup = isset( $_POST['is_setup'] ) && '1' === $_POST['is_setup'];

		// Determinar el secreto activo
		if ( $is_setup ) {
			$secret = get_transient( 'wpat_fm_pending_secret_' . $user_id );
			if ( empty( $secret ) ) {
				wp_send_json_error( array( 'message' => __( 'La sesión de configuración ha caducado. Vuelve a escanear el código QR.', 'wp-agency-toolkit' ) ) );
			}
		} else {
			$secret = get_user_meta( $user_id, 'wpat_fm_totp_secret', true );
		}

		$is_valid = false;
		$used_recovery = false;

		// 1. Probar validación TOTP de 6 dígitos
		if ( ! empty( $secret ) && self::verify_totp_code( $secret, $code ) ) {
			$is_valid = true;
		}

		// 2. Si no es válido y no estamos en setup, probar como código de rescate
		if ( ! $is_valid && ! $is_setup && strlen( $code ) >= 8 ) {
			if ( self::verify_and_consume_recovery_code( $user_id, $code ) ) {
				$is_valid = true;
				$used_recovery = true;
			}
		}

		if ( ! $is_valid ) {
			$failed_attempts++;
			set_transient( 'wpat_fm_failed_' . $user_id, $failed_attempts, 15 * MINUTE_IN_SECONDS );
			$remaining = 4 - $failed_attempts;
			wp_send_json_error( array(
				'message'   => sprintf( __( 'Código incorrecto. Te quedan %d intento(s) antes del bloqueo temporal.', 'wp-agency-toolkit' ), max( 0, $remaining ) ),
				'remaining' => $remaining,
			) );
		}

		// Si venimos de setup, consolidar el secreto y generar códigos de rescate
		$recovery_codes = array();
		if ( $is_setup ) {
			update_user_meta( $user_id, 'wpat_fm_totp_secret', $secret );
			delete_transient( 'wpat_fm_pending_secret_' . $user_id );
			$recovery_codes = self::generate_recovery_codes( $user_id );
		}

		// Desbloquear sesión por 20 minutos
		$session_token = self::unlock_session( $user_id, 20 );

		wp_send_json_success( array(
			'message'        => $used_recovery ? __( 'Desbloqueado con código de recuperación.', 'wp-agency-toolkit' ) : __( 'Autenticación 2FA correcta. Sesión desbloqueada.', 'wp-agency-toolkit' ),
			'session_token'  => $session_token,
			'recovery_codes' => $recovery_codes,
			'timeout_secs'   => 20 * 60,
		) );
	}

	/**
	 * AJAX: Cierra la sesión activa del explorador inmediatamente.
	 */
	public function ajax_lock_session() {
		if ( ! self::current_user_can_access() ) {
			wp_send_json_error( array( 'message' => __( 'Acceso denegado.', 'wp-agency-toolkit' ) ), 403 );
		}

		check_ajax_referer( 'wpat_file_manager_nonce', 'nonce' );
		self::lock_session();

		wp_send_json_success( array( 'message' => __( 'Sesión bloqueada correctamente.', 'wp-agency-toolkit' ) ) );
	}

	/**
	 * AJAX: Restablece el 2FA si el usuario solicita reconfigurar su app authenticator.
	 */
	public function ajax_reset_2fa() {
		$this->verify_ajax_access();

		$user_id = get_current_user_id();
		delete_user_meta( $user_id, 'wpat_fm_totp_secret' );
		delete_user_meta( $user_id, 'wpat_fm_recovery_codes' );
		self::lock_session( $user_id );

		wp_send_json_success( array( 'message' => __( '2FA restablecido. Configura tu nuevo autenticador.', 'wp-agency-toolkit' ) ) );
	}

	/* =========================================================================
	 * JAIL Y GESTIÓN DE RUTAS (CONFINAMIENTO INFRANQUEABLE EN ABSPATH)
	 * ========================================================================= */

	/**
	 * Normaliza y valida que una ruta relativa permanezca estrictamente dentro de ABSPATH.
	 *
	 * @param string $relative_path Ruta relativa proporcionada por el usuario.
	 * @param bool   $must_exist Si true, verifica que el archivo o carpeta exista actualmente.
	 * @return string|false Ruta absoluta canónica, o false si intenta escapar de ABSPATH.
	 */
	public static function get_safe_path( $relative_path = '', $must_exist = true ) {
		$base_dir = wp_normalize_path( untrailingslashit( ABSPATH ) );

		// Sanitizar ruta relativa limpiando caracteres de control y nulos
		$clean_rel = str_replace( array( "\0", "\r", "\n" ), '', (string) $relative_path );
		$clean_rel = ltrim( wp_normalize_path( $clean_rel ), '/' );

		// Construir ruta candidata
		$candidate = empty( $clean_rel ) ? $base_dir : $base_dir . '/' . $clean_rel;
		$candidate = wp_normalize_path( $candidate );

		if ( $must_exist ) {
			$real = realpath( $candidate );
			if ( false === $real ) {
				return false;
			}
			$real = wp_normalize_path( $real );
			// Asegurar que la ruta comience con ABSPATH
			if ( 0 !== strpos( $real, $base_dir ) ) {
				return false;
			}
			return $real;
		}

		// Para archivos a crear que aún no existen, validar el directorio padre
		$parent = dirname( $candidate );
		$real_parent = realpath( $parent );
		if ( false === $real_parent ) {
			return false;
		}
		$real_parent = wp_normalize_path( $real_parent );
		if ( 0 !== strpos( $real_parent, $base_dir ) ) {
			return false;
		}

		return $candidate;
	}

	/**
	 * Convierte una ruta absoluta interna a ruta relativa para mostrar en interfaz.
	 *
	 * @param string $absolute_path Ruta absoluta validada.
	 * @return string Ruta relativa (ej: "wp-content/themes").
	 */
	public static function get_relative_path( $absolute_path ) {
		$base_dir = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$norm_abs = wp_normalize_path( $absolute_path );

		if ( $norm_abs === $base_dir ) {
			return '';
		}

		if ( 0 === strpos( $norm_abs, $base_dir . '/' ) ) {
			return substr( $norm_abs, strlen( $base_dir ) + 1 );
		}

		return '';
	}

	/**
	 * Asegura que el directorio de copias de seguridad exista y esté blindado contra descargas web directas.
	 *
	 * @return string Ruta absoluta de la carpeta de backups.
	 */
	public static function get_backup_directory() {
		$upload_dir = wp_upload_dir();
		$backup_dir = wp_normalize_path( $upload_dir['basedir'] . '/' . self::BACKUP_DIR_NAME );

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		// Proteger con .htaccess
		$htaccess_file = $backup_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			@file_put_contents( $htaccess_file, "Order deny,allow\nDeny from all\n" );
		}

		// Proteger con index.php silencioso
		$index_file = $backup_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, "<?php\n// Silencio de seguridad.\nexit;\n" );
		}

		return $backup_dir;
	}

	/* =========================================================================
	 * ENDPOINTS AJAX DE OPERACIONES DE ARCHIVOS
	 * ========================================================================= */

	/**
	 * AJAX: Lista los archivos y subcarpetas de un directorio.
	 */
	public function ajax_list_dir() {
		$this->verify_ajax_access();

		$rel_path  = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$safe_path = self::get_safe_path( $rel_path, true );

		if ( ! $safe_path || ! is_dir( $safe_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Directorio no encontrado o fuera del ámbito de seguridad.', 'wp-agency-toolkit' ) ), 404 );
		}

		$items = scandir( $safe_path );
		if ( false === $items ) {
			wp_send_json_error( array( 'message' => __( 'No se tienen permisos del servidor para leer este directorio.', 'wp-agency-toolkit' ) ) );
		}

		$directories = array();
		$files       = array();

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$full_path = $safe_path . '/' . $item;
			$is_dir    = is_dir( $full_path );
			$item_rel  = self::get_relative_path( $full_path );
			$stat      = @stat( $full_path );
			$perms     = substr( sprintf( '%o', @fileperms( $full_path ) ), -4 );
			$mtime     = $stat ? date( 'Y-m-d H:i', $stat['mtime'] ) : '—';

			$entry = array(
				'name'       => $item,
				'rel_path'   => $item_rel,
				'is_dir'     => $is_dir,
				'size'       => $is_dir ? 0 : ( $stat ? $stat['size'] : 0 ),
				'size_fmt'   => $is_dir ? '—' : ( $stat ? size_format( $stat['size'] ) : '0 B' ),
				'perms'      => $perms,
				'mtime'      => $mtime,
				'extension'  => $is_dir ? '' : strtolower( pathinfo( $item, PATHINFO_EXTENSION ) ),
				'is_editable'=> ! $is_dir && self::is_file_editable( $item ),
			);

			if ( $is_dir ) {
				$directories[] = $entry;
			} else {
				$files[] = $entry;
			}
		}

		// Ordenar alfabéticamente: carpetas primero, luego archivos
		usort( $directories, function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );
		usort( $files, function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		// Construir migas de pan (Breadcrumbs)
		$breadcrumbs = array( array( 'name' => 'root (ABSPATH)', 'path' => '' ) );
		if ( ! empty( $rel_path ) ) {
			$segments = explode( '/', trim( $rel_path, '/' ) );
			$accum = '';
			foreach ( $segments as $seg ) {
				$accum .= ( empty( $accum ) ? '' : '/' ) . $seg;
				$breadcrumbs[] = array(
					'name' => $seg,
					'path' => $accum,
				);
			}
		}

		wp_send_json_success( array(
			'current_path' => $rel_path,
			'breadcrumbs'  => $breadcrumbs,
			'directories'  => $directories,
			'files'        => $files,
			'total_items'  => count( $directories ) + count( $files ),
			'is_writable'  => is_writable( $safe_path ),
		) );
	}

	/**
	 * Determina si la extensión de un archivo admite edición de código.
	 *
	 * @param string $filename Nombre del archivo.
	 * @return bool
	 */
	public static function is_file_editable( $filename ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$editable_extensions = array(
			'php', 'js', 'css', 'html', 'htm', 'json', 'txt', 'md',
			'xml', 'svg', 'htaccess', 'user.ini', 'env', 'sql', 'log',
			'yml', 'yaml', 'scss', 'less', 'ini', 'conf'
		);
		return in_array( $ext, $editable_extensions, true ) || '.htaccess' === $filename || 'robots.txt' === $filename;
	}

	/**
	 * AJAX: Obtiene el contenido de un archivo para editarlo.
	 */
	public function ajax_get_file() {
		$this->verify_ajax_access();

		$rel_path  = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$safe_path = self::get_safe_path( $rel_path, true );

		if ( ! $safe_path || ! is_file( $safe_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Archivo no encontrado.', 'wp-agency-toolkit' ) ), 404 );
		}

		$file_size = (int) filesize( $safe_path );
		if ( $file_size > 5 * 1024 * 1024 ) { // Límite de 5 MB para editor en navegador
			wp_send_json_error( array( 'message' => __( 'El archivo supera el tamaño máximo de 5MB para editarse en el navegador.', 'wp-agency-toolkit' ) ) );
		}

		$content = file_get_contents( $safe_path );
		if ( false === $content ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo leer el archivo. Comprueba los permisos de lectura.', 'wp-agency-toolkit' ) ) );
		}

		$filename = basename( $safe_path );
		$is_critical = ( 'wp-config.php' === $filename || '.htaccess' === $filename );

		wp_send_json_success( array(
			'path'        => $rel_path,
			'filename'    => $filename,
			'size'        => $file_size,
			'size_fmt'    => size_format( $file_size ),
			'is_writable' => is_writable( $safe_path ),
			'is_critical' => $is_critical,
			'content'     => $content,
			'mtime'       => date( 'Y-m-d H:i:s', filemtime( $safe_path ) ),
		) );
	}

	/**
	 * AJAX: Guarda los cambios de un archivo creando copia de respaldo y validando sintaxis PHP.
	 */
	public function ajax_save_file() {
		$this->verify_ajax_access();

		$rel_path  = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$safe_path = self::get_safe_path( $rel_path, true );

		if ( ! $safe_path || ! is_file( $safe_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Archivo no encontrado para guardar.', 'wp-agency-toolkit' ) ), 404 );
		}

		if ( ! is_writable( $safe_path ) ) {
			wp_send_json_error( array( 'message' => __( 'El archivo no tiene permisos de escritura en el servidor.', 'wp-agency-toolkit' ) ), 403 );
		}

		// Obtener contenido crudo enviado
		$new_content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';

		// 1. VALIDACIÓN PREVENTIVA DE SINTAXIS PHP (ANTI-WSOD)
		$extension = strtolower( pathinfo( $safe_path, PATHINFO_EXTENSION ) );
		if ( 'php' === $extension ) {
			$syntax_check = self::validate_php_syntax( $new_content );
			if ( false === $syntax_check['valid'] ) {
				wp_send_json_error( array(
					'message'       => sprintf( __( 'Error de sintaxis PHP detectado en línea %d: %s. Se ha bloqueado el guardado para evitar una caída de la web.', 'wp-agency-toolkit' ), $syntax_check['line'], $syntax_check['error'] ),
					'syntax_error'  => true,
					'error_line'    => $syntax_check['line'],
					'error_message' => $syntax_check['error'],
				) );
			}
		}

		// 2. CREACIÓN DE COPIA DE SEGURIDAD AUTOMÁTICA INSTANTÁNEA
		$backup_dir = self::get_backup_directory();
		$filename   = basename( $safe_path );
		$timestamp  = date( 'Ymd_His' );
		$backup_file = $backup_dir . '/bak_' . $timestamp . '_' . sanitize_file_name( $filename );
		@copy( $safe_path, $backup_file );

		// 3. GUARDAR EL ARCHIVO
		$bytes_written = @file_put_contents( $safe_path, $new_content );
		if ( false === $bytes_written ) {
			wp_send_json_error( array( 'message' => __( 'Error al escribir el archivo en disco.', 'wp-agency-toolkit' ) ) );
		}

		wp_send_json_success( array(
			'message'     => __( 'Archivo guardado correctamente.', 'wp-agency-toolkit' ),
			'size'        => $bytes_written,
			'size_fmt'    => size_format( $bytes_written ),
			'backup_made' => basename( $backup_file ),
			'mtime'       => date( 'Y-m-d H:i:s', filemtime( $safe_path ) ),
		) );
	}

	/**
	 * Valida la sintaxis de un bloque de código PHP mediante token_get_all().
	 *
	 * @param string $php_code Código PHP a verificar.
	 * @return array Array con ['valid' => bool, 'error' => string, 'line' => int].
	 */
	public static function validate_php_syntax( $php_code ) {
		// Comprobar con token_get_all() para detectar fallos léxicos obvios
		try {
			if ( function_exists( 'token_get_all' ) ) {
				$tokens = @token_get_all( $php_code, TOKEN_PARSE );
				if ( false === $tokens ) {
					return array(
						'valid' => false,
						'error' => __( 'Fallo en análisis sintáctico de tokens.', 'wp-agency-toolkit' ),
						'line'  => 0,
					);
				}
			}
		} catch ( ParseError $pe ) {
			return array(
				'valid' => false,
				'error' => $pe->getMessage(),
				'line'  => $pe->getLine(),
			);
		} catch ( Throwable $t ) {
			return array(
				'valid' => false,
				'error' => $t->getMessage(),
				'line'  => 0,
			);
		}

		return array( 'valid' => true, 'error' => '', 'line' => 0 );
	}

	/**
	 * AJAX: Crea un nuevo archivo o carpeta.
	 */
	public function ajax_create_item() {
		$this->verify_ajax_access();

		$parent_rel = isset( $_POST['parent_path'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_path'] ) ) : '';
		$item_name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$type       = isset( $_POST['type'] ) && 'dir' === $_POST['type'] ? 'dir' : 'file';

		$parent_safe = self::get_safe_path( $parent_rel, true );
		if ( ! $parent_safe || ! is_dir( $parent_safe ) ) {
			wp_send_json_error( array( 'message' => __( 'Directorio contenedor inválido.', 'wp-agency-toolkit' ) ) );
		}

		if ( empty( $item_name ) || false !== strpos( $item_name, '/' ) || false !== strpos( $item_name, '\\' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nombre de archivo o carpeta no válido.', 'wp-agency-toolkit' ) ) );
		}

		$target_path = $parent_safe . '/' . $item_name;
		if ( file_exists( $target_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Ya existe un elemento con ese nombre.', 'wp-agency-toolkit' ) ) );
		}

		if ( 'dir' === $type ) {
			$created = @mkdir( $target_path, 0755 );
		} else {
			$created = @touch( $target_path );
		}

		if ( ! $created ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo crear el elemento. Comprueba los permisos de la carpeta.', 'wp-agency-toolkit' ) ) );
		}

		wp_send_json_success( array(
			'message' => 'dir' === $type ? __( 'Carpeta creada correctamente.', 'wp-agency-toolkit' ) : __( 'Archivo creado correctamente.', 'wp-agency-toolkit' ),
		) );
	}

	/**
	 * AJAX: Renombra un archivo o carpeta.
	 */
	public function ajax_rename_item() {
		$this->verify_ajax_access();

		$old_rel = isset( $_POST['old_path'] ) ? sanitize_text_field( wp_unslash( $_POST['old_path'] ) ) : '';
		$new_name = isset( $_POST['new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_name'] ) ) : '';

		$old_safe = self::get_safe_path( $old_rel, true );
		if ( ! $old_safe || ! file_exists( $old_safe ) ) {
			wp_send_json_error( array( 'message' => __( 'Elemento a renombrar no encontrado.', 'wp-agency-toolkit' ) ) );
		}

		if ( empty( $new_name ) || false !== strpos( $new_name, '/' ) || false !== strpos( $new_name, '\\' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nuevo nombre no válido.', 'wp-agency-toolkit' ) ) );
		}

		$target_path = dirname( $old_safe ) . '/' . $new_name;
		if ( file_exists( $target_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Ya existe otro elemento con ese nombre.', 'wp-agency-toolkit' ) ) );
		}

		if ( ! @rename( $old_safe, $target_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Error al renombrar el elemento.', 'wp-agency-toolkit' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Elemento renombrado correctamente.', 'wp-agency-toolkit' ) ) );
	}

	/**
	 * AJAX: Elimina un archivo o carpeta.
	 */
	public function ajax_delete_item() {
		$this->verify_ajax_access();

		$rel_path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$safe_path = self::get_safe_path( $rel_path, true );

		if ( ! $safe_path || ! file_exists( $safe_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Elemento a eliminar no encontrado.', 'wp-agency-toolkit' ) ) );
		}

		// Protección: no permitir borrar la raíz ABSPATH
		if ( untrailingslashit( wp_normalize_path( ABSPATH ) ) === $safe_path ) {
			wp_send_json_error( array( 'message' => __( 'Acción denegada: no se puede eliminar la raíz de WordPress.', 'wp-agency-toolkit' ) ), 403 );
		}

		// Copia preventiva al directorio de backups si es archivo
		if ( is_file( $safe_path ) ) {
			$backup_dir = self::get_backup_directory();
			@copy( $safe_path, $backup_dir . '/deleted_' . date( 'Ymd_His' ) . '_' . basename( $safe_path ) );
			$deleted = @unlink( $safe_path );
		} else {
			$deleted = self::delete_directory_recursive( $safe_path );
		}

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo eliminar el elemento. Verifica los permisos del servidor.', 'wp-agency-toolkit' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Elemento eliminado correctamente.', 'wp-agency-toolkit' ) ) );
	}

	/**
	 * Elimina un directorio recursivamente.
	 *
	 * @param string $dir Ruta absoluta del directorio.
	 * @return bool
	 */
	private static function delete_directory_recursive( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::delete_directory_recursive( $path );
			} else {
				@unlink( $path );
			}
		}

		return @rmdir( $dir );
	}

	/**
	 * AJAX: Sube un archivo a un directorio validado.
	 */
	public function ajax_upload_file() {
		$this->verify_ajax_access();

		$target_rel = isset( $_POST['target_path'] ) ? sanitize_text_field( wp_unslash( $_POST['target_path'] ) ) : '';
		$safe_target = self::get_safe_path( $target_rel, true );

		if ( ! $safe_target || ! is_dir( $safe_target ) ) {
			wp_send_json_error( array( 'message' => __( 'Directorio de destino no válido.', 'wp-agency-toolkit' ) ) );
		}

		if ( ! is_writable( $safe_target ) ) {
			wp_send_json_error( array( 'message' => __( 'El directorio de destino no tiene permisos de escritura.', 'wp-agency-toolkit' ) ), 403 );
		}

		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No se ha recibido ningún archivo.', 'wp-agency-toolkit' ) ) );
		}

		$uploaded = $_FILES['file'];
		if ( UPLOAD_ERR_OK !== $uploaded['error'] ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Error al subir el archivo (Código %d).', 'wp-agency-toolkit' ), $uploaded['error'] ) ) );
		}

		$clean_name = sanitize_file_name( $uploaded['name'] );
		if ( empty( $clean_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Nombre de archivo no válido.', 'wp-agency-toolkit' ) ) );
		}

		$destination = $safe_target . '/' . $clean_name;

		if ( ! @move_uploaded_file( $uploaded['tmp_name'], $destination ) ) {
			wp_send_json_error( array( 'message' => __( 'Error al mover el archivo subido al directorio de destino.', 'wp-agency-toolkit' ) ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'Archivo subido con éxito.', 'wp-agency-toolkit' ),
			'filename' => $clean_name,
		) );
	}

	/**
	 * AJAX: Prepara y descarga un archivo o empaqueta una carpeta en ZIP.
	 */
	public function ajax_download_item() {
		$this->verify_ajax_access();

		$rel_path  = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$safe_path = self::get_safe_path( $rel_path, true );

		if ( ! $safe_path || ! file_exists( $safe_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Elemento no encontrado.', 'wp-agency-toolkit' ) ) );
		}

		$nonce = wp_create_nonce( 'wpat_fm_download_' . get_current_user_id() );
		$download_url = add_query_arg( array(
			'action'   => 'wpat_fm_direct_download',
			'path'     => rawurlencode( $rel_path ),
			'download_nonce' => $nonce,
		), admin_url( 'admin.php' ) );

		wp_send_json_success( array( 'download_url' => $download_url ) );
	}

	/**
	 * Maneja la descarga binaria directa mediante GET validando nonce y sesión 2FA.
	 */
	public function maybe_handle_direct_download() {
		if ( ! isset( $_GET['action'] ) || 'wpat_fm_direct_download' !== $_GET['action'] ) {
			return;
		}

		if ( ! self::current_user_can_access() ) {
			wp_die( 'Acceso denegado.' );
		}

		$nonce = isset( $_GET['download_nonce'] ) ? sanitize_text_field( $_GET['download_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpat_fm_download_' . get_current_user_id() ) ) {
			wp_die( 'Nonce de descarga inválido o caducado.' );
		}

		if ( ! self::is_session_unlocked() ) {
			wp_die( 'Sesión bloqueada. Autentícate con 2FA en el panel primero.' );
		}

		$rel_path  = isset( $_GET['path'] ) ? sanitize_text_field( rawurldecode( $_GET['path'] ) ) : '';
		$safe_path = self::get_safe_path( $rel_path, true );

		if ( ! $safe_path || ! file_exists( $safe_path ) ) {
			wp_die( 'Elemento no encontrado.' );
		}

		if ( is_file( $safe_path ) ) {
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . basename( $safe_path ) . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize( $safe_path ) );
			readfile( $safe_path );
			exit;
		}

		// Descarga de directorio en ZIP
		if ( is_dir( $safe_path ) && class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			$zip_file = wp_tempnam( 'wpat_zip_' );
			if ( true === $zip->open( $zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				$files = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $safe_path, RecursiveDirectoryIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::LEAVES_ONLY
				);

				foreach ( $files as $file ) {
					if ( ! $file->isDir() ) {
						$file_path = $file->getRealPath();
						$rel_zip   = substr( $file_path, strlen( $safe_path ) + 1 );
						$zip->addFile( $file_path, $rel_zip );
					}
				}
				$zip->close();

				$zip_name = ( empty( $rel_path ) ? 'wordpress_root' : basename( $safe_path ) ) . '.zip';

				header( 'Content-Type: application/zip' );
				header( 'Content-Disposition: attachment; filename="' . $zip_name . '"' );
				header( 'Content-Length: ' . filesize( $zip_file ) );
				readfile( $zip_file );
				@unlink( $zip_file );
				exit;
			}
		}

		wp_die( 'No se pudo generar la descarga.' );
	}
}

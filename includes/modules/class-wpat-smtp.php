<?php
/**
 * Módulo SMTP - Configuración, enrutamiento seguro y registro de correos
 *
 * @package WP_Agency_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAT_SMTP {

	/**
	 * Instancia única del módulo (Singleton).
	 *
	 * @var WPAT_SMTP|null
	 */
	private static $instance = null;

	/**
	 * Almacena el último error registrado por PHPMailer en caso de fallo.
	 *
	 * @var string
	 */
	private $last_mail_error = '';

	/**
	 * Indica si se está enviando el correo de prueba para habilitar depuración.
	 *
	 * @var bool
	 */
	private $is_test_email = false;

	/**
	 * Log de depuración detallado de la conexión SMTP.
	 *
	 * @var string
	 */
	private $smtp_debug_log = '';

	/**
	 * Almacena temporalmente los datos del correo en curso antes de enviar.
	 *
	 * @var array|null
	 */
	private $current_mail_data = null;

	/**
	 * Clave de almacenamiento de logs de envío en la base de datos.
	 */
	const LOG_OPTION_KEY = 'wpat_smtp_delivery_log';

	/**
	 * Máximo número de registros en el historial de envíos.
	 */
	const MAX_LOG_ENTRIES = 30;

	/**
	 * Obtiene la instancia Singleton del módulo.
	 *
	 * @return WPAT_SMTP
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

		// Enrutar correos por SMTP si está activo
		if ( isset( $settings['smtp'] ) && '1' === $settings['smtp'] ) {
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ), 999 );
		}

		// Registro de envíos si el log está habilitado (por defecto activado)
		$log_enabled = ! isset( $settings['smtp_log_enabled'] ) || '1' === $settings['smtp_log_enabled'];
		if ( $log_enabled ) {
			add_filter( 'wp_mail', array( $this, 'capture_mail_pre_send' ), 999 );
			add_action( 'wp_mail_succeeded', array( $this, 'log_mail_success' ), 999 );
			add_action( 'wp_mail_failed', array( $this, 'log_mail_failed' ), 999 );
		}

		// Registrar endpoints de AJAX
		add_action( 'wp_ajax_wpat_smtp_send_test', array( $this, 'handle_send_test_email' ) );
		add_action( 'wp_ajax_wpat_smtp_clear_log', array( $this, 'handle_clear_log' ) );
	}

	/**
	 * Intercepta e inicializa los parámetros de PHPMailer con las credenciales SMTP configuradas.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer|PHPMailer $phpmailer Objeto PHPMailer pasado por referencia.
	 */
	public function configure_smtp( $phpmailer ) {
		$settings = WPAT_Main::get_instance()->get_settings();

		if ( empty( $settings['smtp_host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = sanitize_text_field( $settings['smtp_host'] );
		$phpmailer->SMTPAuth   = ( isset( $settings['smtp_auth'] ) && '1' === $settings['smtp_auth'] );
		$phpmailer->Port       = ! empty( $settings['smtp_port'] ) ? intval( $settings['smtp_port'] ) : 587;
		$phpmailer->Username   = isset( $settings['smtp_username'] ) ? $settings['smtp_username'] : '';
		$phpmailer->Password   = isset( $settings['smtp_password'] ) ? $settings['smtp_password'] : '';
		$phpmailer->Timeout    = 15; // Evita bloqueos eternos si el puerto está cerrado por el hosting

		$secure = isset( $settings['smtp_secure'] ) ? $settings['smtp_secure'] : 'tls';
		if ( 'none' === $secure || empty( $secure ) ) {
			$phpmailer->SMTPSecure = '';
			$phpmailer->AutoTLS    = false; // Desactivar STARTTLS oportunista si se eligió sin cifrado
		} else {
			$phpmailer->SMTPSecure = $secure;
		}

		// Desactivar validación de certificados SSL si está configurado
		if ( isset( $settings['smtp_insecure'] ) && '1' === $settings['smtp_insecure'] ) {
			$phpmailer->SMTPOptions = array(
				'ssl' => array(
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'allow_self_signed' => true,
				),
			);
		}

		// Activar diagnóstico de depuración detallado en los envíos de prueba
		if ( $this->is_test_email ) {
			$phpmailer->SMTPDebug   = 3; // Log de conexiones y comandos
			$phpmailer->Debugoutput = array( $this, 'capture_smtp_debug' );
		}

		// Remitente (From) y Nombre (FromName)
		$force_from = isset( $settings['smtp_force_from'] ) && '1' === $settings['smtp_force_from'];
		$from_email = ! empty( $settings['smtp_from_email'] ) ? sanitize_email( $settings['smtp_from_email'] ) : ( ! empty( $settings['smtp_username'] ) && is_email( $settings['smtp_username'] ) ? sanitize_email( $settings['smtp_username'] ) : '' );
		$from_name  = ! empty( $settings['smtp_from_name'] ) ? sanitize_text_field( $settings['smtp_from_name'] ) : '';

		if ( ! empty( $from_email ) ) {
			if ( $force_from || empty( $phpmailer->From ) || 'wordpress@' . ( isset( $_SERVER['SERVER_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) : '' ) === $phpmailer->From ) {
				$phpmailer->From = $from_email;
			}
		}

		if ( ! empty( $from_name ) ) {
			if ( $force_from || empty( $phpmailer->FromName ) || 'WordPress' === $phpmailer->FromName ) {
				$phpmailer->FromName = $from_name;
			}
		}

		// Reply-To personalizado si está definido
		if ( ! empty( $settings['smtp_reply_to'] ) && is_email( $settings['smtp_reply_to'] ) ) {
			$phpmailer->addReplyTo( sanitize_email( $settings['smtp_reply_to'] ) );
		}
	}

	/**
	 * Captura y formatea los logs de depuración del protocolo SMTP de PHPMailer.
	 *
	 * @param string $str   Mensaje de depuración.
	 * @param string $level Nivel de depuración.
	 */
	public function capture_smtp_debug( $str, $level ) {
		$this->smtp_debug_log .= esc_html( trim( $str ) ) . "\n";
	}

	/**
	 * Captura el error arrojado al fallar el envío del correo de prueba.
	 *
	 * @param WP_Error $wp_error Objeto de error de WordPress.
	 */
	public function capture_mail_error( $wp_error ) {
		if ( is_wp_error( $wp_error ) ) {
			$this->last_mail_error = $wp_error->get_error_message();
		}
	}

	/**
	 * Intercepta los datos del correo justo antes de enviar para tener contexto en el registro.
	 *
	 * @param array $mail_args Argumentos del correo.
	 * @return array
	 */
	public function capture_mail_pre_send( $mail_args ) {
		$this->current_mail_data = $mail_args;
		return $mail_args;
	}

	/**
	 * Registra un envío exitoso en el historial.
	 *
	 * @param array $mail_data Datos del correo de wp_mail_succeeded.
	 */
	public function log_mail_success( $mail_data ) {
		$to      = isset( $mail_data['to'] ) ? ( is_array( $mail_data['to'] ) ? implode( ', ', $mail_data['to'] ) : (string) $mail_data['to'] ) : '';
		$subject = isset( $mail_data['subject'] ) ? (string) $mail_data['subject'] : '(Sin asunto)';

		self::add_log_entry( array(
			'to'      => $to,
			'subject' => $subject,
			'status'  => 'success',
			'error'   => '',
			'time'    => current_time( 'timestamp' ),
		) );
	}

	/**
	 * Registra un fallo de envío en el historial.
	 *
	 * @param WP_Error $wp_error Error devuelto por wp_mail_failed.
	 */
	public function log_mail_failed( $wp_error ) {
		$to      = '';
		$subject = '(Sin asunto)';

		if ( is_wp_error( $wp_error ) ) {
			$this->last_mail_error = $wp_error->get_error_message();
			$error_data            = $wp_error->get_error_data();
			if ( is_array( $error_data ) ) {
				if ( isset( $error_data['to'] ) ) {
					$to = is_array( $error_data['to'] ) ? implode( ', ', $error_data['to'] ) : (string) $error_data['to'];
				}
				if ( isset( $error_data['subject'] ) ) {
					$subject = (string) $error_data['subject'];
				}
			}
		}

		if ( empty( $to ) && ! empty( $this->current_mail_data['to'] ) ) {
			$to = is_array( $this->current_mail_data['to'] ) ? implode( ', ', $this->current_mail_data['to'] ) : (string) $this->current_mail_data['to'];
		}
		if ( '(Sin asunto)' === $subject && ! empty( $this->current_mail_data['subject'] ) ) {
			$subject = (string) $this->current_mail_data['subject'];
		}

		self::add_log_entry( array(
			'to'      => $to,
			'subject' => $subject,
			'status'  => 'error',
			'error'   => ! empty( $this->last_mail_error ) ? $this->last_mail_error : 'Error desconocido de envío SMTP.',
			'time'    => current_time( 'timestamp' ),
		) );
	}

	/**
	 * Añade una entrada al historial de envíos (máximo 30 entradas).
	 *
	 * @param array $entry Datos de la entrada de log.
	 */
	public static function add_log_entry( $entry ) {
		$log = get_option( self::LOG_OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Insertar al inicio
		array_unshift( $log, $entry );

		// Limitar a MAX_LOG_ENTRIES
		if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );
		}

		update_option( self::LOG_OPTION_KEY, $log, false );
	}

	/**
	 * Obtiene el historial de envíos.
	 *
	 * @return array
	 */
	public static function get_delivery_log() {
		$log = get_option( self::LOG_OPTION_KEY, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Limpia el historial de envíos.
	 *
	 * @return bool
	 */
	public static function clear_delivery_log() {
		return delete_option( self::LOG_OPTION_KEY );
	}

	/**
	 * AJAX: Limpiar el registro de envíos SMTP.
	 */
	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		check_ajax_referer( 'wpat_smtp_test_nonce_action', 'security' );

		self::clear_delivery_log();

		wp_send_json_success( array( 'message' => 'El historial de envíos se ha vaciado correctamente.' ) );
	}

	/**
	 * AJAX: Envia un correo electrónico de prueba utilizando la configuración SMTP actual.
	 */
	public function handle_send_test_email() {
		// Verificar permisos y nonce
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		check_ajax_referer( 'wpat_smtp_test_nonce_action', 'security' );

		$test_email = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';

		if ( empty( $test_email ) || ! is_email( $test_email ) ) {
			wp_send_json_error( array( 'message' => 'Por favor, introduce una dirección de correo válida.' ) );
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Si el SMTP no está encendido globalmente, lo inyectamos temporalmente solo para este envío de prueba
		$smtp_already_active = ( isset( $settings['smtp'] ) && '1' === $settings['smtp'] );
		if ( ! $smtp_already_active ) {
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ), 999 );
		}

		// Capturar posibles errores del envío
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		$this->smtp_debug_log  = '';
		$this->last_mail_error = '';
		$this->is_test_email   = true;

		$subject = 'WP Agency Toolkit - Correo de prueba SMTP';
		$message = "¡Hola!\n\nEste es un correo electrónico enviado para comprobar la configuración de SMTP en WP Agency Toolkit.\n\nSi has recibido este mensaje, significa que tu servidor SMTP está correctamente configurado y listo para enrutar los correos de tu sitio web de forma segura y fiable.\n\nDetalles de la prueba:\n- Fecha y hora: " . wp_date( 'd/m/Y H:i:s' ) . "\n- Host SMTP: " . ( ! empty( $settings['smtp_host'] ) ? $settings['smtp_host'] : 'No definido' ) . "\n- Puerto: " . ( ! empty( $settings['smtp_port'] ) ? $settings['smtp_port'] : '25' ) . "\n- Cifrado: " . strtoupper( ! empty( $settings['smtp_secure'] ) ? $settings['smtp_secure'] : 'none' ) . "\n\nUn saludo,\nEquipo de Desarrollo - WP Agency Toolkit";

		$sent = wp_mail( $test_email, $subject, $message );

		$this->is_test_email = false;

		// Remover el hook temporal si no estaba activo
		if ( ! $smtp_already_active ) {
			remove_action( 'phpmailer_init', array( $this, 'configure_smtp' ), 999 );
		}
		remove_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		if ( $sent ) {
			wp_send_json_success( array(
				'message' => '¡Correo de prueba enviado con éxito! Comprueba la bandeja de entrada del email destinatario (' . $test_email . ').',
				'time'    => wp_date( 'd/m/Y H:i:s' ),
			) );
		} else {
			$error_details = ! empty( $this->last_mail_error ) ? $this->last_mail_error : 'No se pudo conectar con el servidor SMTP o las credenciales son incorrectas.';
			wp_send_json_error( array(
				'message' => 'Fallo al enviar el correo de prueba: ' . $error_details,
				'debug'   => trim( $this->smtp_debug_log ),
			) );
		}
	}
}

// Inicializar el módulo
WPAT_SMTP::get_instance();


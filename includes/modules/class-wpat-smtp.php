<?php
/**
 * Módulo SMTP - Configuración y enrutamiento seguro de correos
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
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}

		// Registrar endpoints de AJAX para el correo de prueba
		add_action( 'wp_ajax_wpat_smtp_send_test', array( $this, 'handle_send_test_email' ) );
	}

	/**
	 * Intercepta e inicializa los parámetros de PHPMailer con las credenciales SMTP configuradas.
	 *
	 * @param PHPMailer $phpmailer Objeto PHPMailer pasado por referencia.
	 */
	public function configure_smtp( $phpmailer ) {
		$settings = WPAT_Main::get_instance()->get_settings();

		if ( empty( $settings['smtp_host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['smtp_host'];
		$phpmailer->SMTPAuth   = ( isset( $settings['smtp_auth'] ) && '1' === $settings['smtp_auth'] );
		$phpmailer->Port       = intval( $settings['smtp_port'] );
		$phpmailer->Username   = $settings['smtp_username'];
		$phpmailer->Password   = $settings['smtp_password'];
		$phpmailer->SMTPSecure = ( isset( $settings['smtp_secure'] ) && 'none' !== $settings['smtp_secure'] ) ? $settings['smtp_secure'] : '';

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

		// Forzar remitente y nombre si están configurados (o fallback automático al usuario SMTP)
		if ( ! empty( $settings['smtp_from_email'] ) ) {
			$phpmailer->From = sanitize_email( $settings['smtp_from_email'] );
		} elseif ( ! empty( $settings['smtp_username'] ) ) {
			$phpmailer->From = sanitize_email( $settings['smtp_username'] );
		}
		if ( ! empty( $settings['smtp_from_name'] ) ) {
			$phpmailer->FromName = sanitize_text_field( $settings['smtp_from_name'] );
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
	 * AJAX: Envia un correo electrónico de prueba utilizando la configuración SMTP actual.
	 */
	public function handle_send_test_email() {
		// Verificar permisos y nonce
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		check_ajax_referer( 'wpat_smtp_test_nonce_action', 'security' );

		$test_email = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : '';

		if ( empty( $test_email ) || ! is_email( $test_email ) ) {
			wp_send_json_error( array( 'message' => 'Por favor, introduce una dirección de correo válida.' ) );
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Si el SMTP no está encendido globalmente, lo inyectamos temporalmente solo para este envío de prueba
		$smtp_already_active = ( isset( $settings['smtp'] ) && '1' === $settings['smtp'] );
		if ( ! $smtp_already_active ) {
			add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}

		// Capturar posibles errores del envío
		add_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		$this->smtp_debug_log = '';
		$this->is_test_email  = true;

		$subject = 'WP Agency Toolkit - Correo de prueba SMTP';
		$message = "¡Hola!\n\nEste es un correo electrónico enviado para comprobar la configuración de SMTP en WP Agency Toolkit.\n\nSi has recibido este mensaje, significa que tu servidor SMTP está correctamente configurado y listo para enrutar los correos de tu sitio web.\n\nUn saludo,\nEl equipo de desarrollo de tu Agencia.";

		$sent = wp_mail( $test_email, $subject, $message );

		$this->is_test_email = false;

		// Remover el hook temporal si no estaba activo
		if ( ! $smtp_already_active ) {
			remove_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		}
		remove_action( 'wp_mail_failed', array( $this, 'capture_mail_error' ) );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => 'El correo de prueba ha sido enviado con éxito. Comprueba la bandeja de entrada del email destinatario (' . $test_email . ').' ) );
		} else {
			$error_details = ! empty( $this->last_mail_error ) ? $this->last_mail_error : 'No se pudo conectar con el servidor SMTP.';
			wp_send_json_error( array( 
				'message' => 'Fallo al enviar el correo de prueba: ' . $error_details,
				'debug'   => trim( $this->smtp_debug_log )
			) );
		}
	}
}

// Inicializar el módulo
WPAT_SMTP::get_instance();

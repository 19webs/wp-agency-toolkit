<?php
/**
 * Módulo: Ocultar URL de Login & Seguridad Avanzada - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Hide_Login {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Hide_Login
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Hide_Login
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
		// Control de enrutamiento
		add_action( 'wp_loaded', array( $this, 'handle_login_routing' ) );
		
		// Filtrar las URLs para reemplazar wp-login.php por el slug personalizado
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 2 );
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( $this, 'filter_site_url' ), 10, 4 );

		// Control de intentos fallidos
		add_action( 'wp_login_failed', array( $this, 'track_failed_login_attempts' ) );
		add_filter( 'authenticate', array( $this, 'check_ip_lockout' ), 1, 3 );

		// Captcha matemático en formulario
		add_action( 'login_form', array( $this, 'render_captcha_field' ) );
		add_filter( 'authenticate', array( $this, 'validate_captcha' ), 20, 3 );
	}

	/**
	 * Obtiene el slug de login personalizado de los ajustes.
	 *
	 * @return string
	 */
	private function get_slug() {
		$settings = WPAT_Main::get_instance()->get_settings();
		return ! empty( $settings['hide_login_slug'] ) ? sanitize_title( $settings['hide_login_slug'] ) : 'acceso';
	}

	/**
	 * Obtiene un token único de autenticación basado en la clave secreta de WordPress.
	 *
	 * @return string
	 */
	private function get_auth_token() {
		$salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'wpat_default_salt';
		return md5( $salt . get_option( 'admin_email' ) );
	}

	/**
	 * Obtiene la dirección IP real del visitante de forma segura.
	 *
	 * @return string
	 */
	private function get_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip  = trim( $ips[0] );
		} else {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
		}
		return sanitize_text_field( $ip );
	}

	/**
	 * Intercepta las peticiones de inicio de sesión y gestiona el acceso.
	 */
	public function handle_login_routing() {
		global $pagenow;

		$slug = $this->get_slug();

		// Obtener la ruta de la petición actual
		$request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$home_path    = parse_url( home_url(), PHP_URL_PATH );
		$relative_path = $request_path;

		// Si WP está en una subcarpeta, limpiar la ruta
		if ( ! empty( $home_path ) && '/' !== $home_path ) {
			if ( 0 === strpos( $request_path, $home_path ) ) {
				$relative_path = substr( $request_path, strlen( $home_path ) );
			}
		}
		$relative_path = trim( $relative_path, '/' );

		// Caso 1: Acceden a través del slug secreto (ej. /warehouse)
		if ( $relative_path === $slug ) {
			// Decirle a WordPress que no es un 404
			status_header( 200 );
			
			// Preparar variables de servidor para simular ejecución de wp-login.php físico
			$_SERVER['SCRIPT_NAME'] = str_replace( $slug, 'wp-login.php', $_SERVER['SCRIPT_NAME'] );
			
			// Cargar el archivo wp-login.php directamente en memoria
			require_once ABSPATH . 'wp-login.php';
			exit;
		}

		// Caso 2: Intento de acceso directo a wp-login.php físico
		if ( 'wp-login.php' === $pagenow && $relative_path !== $slug ) {
			$allowed = false;

			// Acciones autorizadas que no bloqueamos
			$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
			$allowed_actions = array( 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp' );

			if ( in_array( $action, $allowed_actions, true ) ) {
				$allowed = true;
			}

			// Permitir si es un envío POST o si el usuario ya está conectado
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] || is_user_logged_in() ) {
				$allowed = true;
			}

			// Si no está permitido el acceso directo a wp-login.php, aplicar la regla de bloqueo
			if ( ! $allowed ) {
				$settings = WPAT_Main::get_instance()->get_settings();
				$redirect_type = isset( $settings['hide_login_redirect'] ) ? $settings['hide_login_redirect'] : 'home';

				if ( '404' === $redirect_type ) {
					// Redirigir a una ruta inexistente para que WordPress pinte de forma natural el 404 del tema/Elementor
					wp_safe_redirect( home_url( '/404' ) );
					exit;
				} else {
					// Redirigir a portada
					wp_safe_redirect( home_url( '/' ) );
					exit;
				}
			}
		}

		// Caso 3: Intento de acceso directo al panel /wp-admin/ sin estar logueado
		$is_admin_post = ( basename( $_SERVER['SCRIPT_FILENAME'] ) === 'admin-post.php' );
		
		if ( is_admin() && ! is_user_logged_in() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) && ! $is_admin_post ) {
			$settings = WPAT_Main::get_instance()->get_settings();
			$redirect_type = isset( $settings['hide_login_redirect'] ) ? $settings['hide_login_redirect'] : 'home';

			if ( '404' === $redirect_type ) {
				// Redirigir a una ruta inexistente para que WordPress pinte de forma natural el 404 del tema/Elementor
				wp_safe_redirect( home_url( '/404' ) );
				exit;
			} else {
				// Redirigir a portada
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}
		}
	}

	/**
	 * Modifica la URL de login generada por WordPress.
	 */
	public function filter_login_url( $login_url, $redirect ) {
		$slug = $this->get_slug();
		$new_url = home_url( '/' . $slug );
		if ( ! empty( $redirect ) ) {
			$new_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $new_url );
		}
		return $new_url;
	}

	/**
	 * Filtra site_url para que las llamadas a wp-login.php se traduzcan en el slug personalizado.
	 */
	public function filter_site_url( $url, $path, $scheme, $blog_id = null ) {
		if ( strpos( $path, 'wp-login.php' ) !== false ) {
			$slug = $this->get_slug();
			
			// Preservar argumentos de consulta
			$query = '';
			$parsed = parse_url( $path );
			if ( isset( $parsed['query'] ) ) {
				$query = '?' . $parsed['query'];
			}
			
			return home_url( '/' . $slug . $query );
		}
		return $url;
	}

	/**
	 * Registra un intento de acceso fallido asociado a la IP del usuario.
	 *
	 * @param string $username Nombre de usuario introducido.
	 */
	public function track_failed_login_attempts( $username ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['hide_login_limit_attempts'] ) || '1' !== $settings['hide_login_limit_attempts'] ) {
			return;
		}

		$ip = $this->get_user_ip();
		$transient_attempts = 'wpat_att_' . md5( $ip );
		$transient_lockout  = 'wpat_lock_' . md5( $ip );

		// Si ya está bloqueada la IP, no hacer nada más
		if ( get_transient( $transient_lockout ) ) {
			return;
		}

		$attempts = (int) get_transient( $transient_attempts );
		$attempts++;

		$max_attempts = isset( $settings['hide_login_max_attempts'] ) ? (int) $settings['hide_login_max_attempts'] : 3;
		$lockout      = isset( $settings['hide_login_lockout'] ) ? (int) $settings['hide_login_lockout'] : 120;

		if ( $attempts >= $max_attempts ) {
			// Bloquear IP
			set_transient( $transient_lockout, time() + $lockout, $lockout );
			delete_transient( $transient_attempts );
		} else {
			// Guardar intentos con expiración de 1 hora
			set_transient( $transient_attempts, $attempts, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Comprueba si la dirección IP del usuario está bloqueada temporalmente antes de procesar la contraseña.
	 */
	public function check_ip_lockout( $user, $username, $password ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['hide_login_limit_attempts'] ) || '1' !== $settings['hide_login_limit_attempts'] ) {
			return $user;
		}

		$ip = $this->get_user_ip();
		$transient_lockout = 'wpat_lock_' . md5( $ip );
		$lock_time         = get_transient( $transient_lockout );

		if ( $lock_time ) {
			$remaining = $lock_time - time();
			if ( $remaining > 0 ) {
				return new WP_Error(
					'wpat_ip_locked',
					sprintf( '<strong>ERROR:</strong> Demasiados intentos fallidos. Tu IP está bloqueada. Por favor, vuelve a intentarlo en %d segundos.', $remaining )
				);
			} else {
				delete_transient( $transient_lockout );
			}
		}
		return $user;
	}

	/**
	 * Renderiza el campo del captcha matemático dentro del formulario de Login.
	 */
	public function render_captcha_field() {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['hide_login_captcha'] ) || '1' !== $settings['hide_login_captcha'] ) {
			return;
		}

		// Generar números aleatorios y operación
		$num1   = rand( 1, 9 );
		$num2   = rand( 1, 9 );
		$is_add = rand( 0, 1 );

		$result    = $is_add ? ( $num1 + $num2 ) : ( $num1 * $num2 );
		$op_symbol = $is_add ? '+' : '×';

		// Cifrar el resultado combinándolo con las claves secretas de WP
		$hash = wp_hash( $result . '_wpat_captcha' );

		?>
		<p class="wpat-captcha-row" style="margin-bottom: 20px;">
			<label for="wpat_captcha_ans">Seguridad: ¿Cuánto es <?php echo esc_html( "$num1 $op_symbol $num2" ); ?>?<br />
				<input type="number" name="wpat_captcha_ans" id="wpat_captcha_ans" class="input" value="" size="20" required style="width: 100%;" />
				<input type="hidden" name="wpat_captcha_challenge" value="<?php echo esc_attr( $hash ); ?>" />
			</label>
		</p>
		<?php
	}

	/**
	 * Valida la respuesta del captcha al intentar iniciar sesión.
	 */
	public function validate_captcha( $user, $username, $password ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// Solo validar si es un intento real de POST en el login
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $username ) ) {
			return $user;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['hide_login_captcha'] ) || '1' !== $settings['hide_login_captcha'] ) {
			return $user;
		}

		$user_ans       = isset( $_POST['wpat_captcha_ans'] ) ? (int) $_POST['wpat_captcha_ans'] : -1;
		$challenge_hash = isset( $_POST['wpat_captcha_challenge'] ) ? sanitize_text_field( $_POST['wpat_captcha_challenge'] ) : '';

		if ( empty( $challenge_hash ) || wp_hash( $user_ans . '_wpat_captcha' ) !== $challenge_hash ) {
			return new WP_Error(
				'wpat_captcha_failed',
				'<strong>ERROR:</strong> La respuesta al Captcha de seguridad es incorrecta o ha caducado.'
			);
		}
		
		return $user;
	}
}

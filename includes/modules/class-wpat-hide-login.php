<?php
/**
 * Módulo: Ocultar URL de Login & Seguridad Avanzada - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Hide_Login {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Hide_Login|null
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
		add_action( 'wp_login', array( $this, 'clear_failed_attempts_on_success' ), 10, 2 );
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
	 * Obtiene la dirección IP real del visitante de forma segura.
	 *
	 * @return string
	 */
	private function get_user_ip() {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip  = trim( $ips[0] );
		} else {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		}
		return $ip;
	}

	/**
	 * Emite una respuesta 404 nativa o redirige a la portada según la configuración.
	 */
	private function render_404_or_redirect() {
		$settings      = WPAT_Main::get_instance()->get_settings();
		$redirect_type = isset( $settings['hide_login_redirect'] ) ? $settings['hide_login_redirect'] : 'home';

		if ( '404' === $redirect_type ) {
			global $wp_query;
			if ( $wp_query ) {
				$wp_query->set_404();
			}
			status_header( 404 );
			nocache_headers();

			$template = get_query_template( '404' );
			if ( $template && file_exists( $template ) ) {
				include $template;
			} else {
				wp_safe_redirect( home_url(), 404 );
			}
			exit;
		} else {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}

	/**
	 * Intercepta las peticiones de inicio de sesión y gestiona el acceso.
	 */
	public function handle_login_routing() {
		global $pagenow;

		$slug = $this->get_slug();

		// Obtener la ruta de la petición actual
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path = (string) parse_url( $request_uri, PHP_URL_PATH );
		$home_path    = (string) parse_url( home_url(), PHP_URL_PATH );
		$relative_path = $request_path;

		// Si WP está en una subcarpeta, limpiar la ruta relativa
		if ( ! empty( $home_path ) && '/' !== $home_path ) {
			if ( 0 === strpos( $request_path, $home_path ) ) {
				$relative_path = substr( $request_path, strlen( $home_path ) );
			}
		}
		$relative_path = trim( $relative_path, '/' );

		// Caso 1: Acceden a través del slug secreto (ej. /acceso)
		if ( $relative_path === $slug ) {
			status_header( 200 );
			if ( isset( $_SERVER['SCRIPT_NAME'] ) ) {
				$_SERVER['SCRIPT_NAME'] = str_replace( $slug, 'wp-login.php', sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) );
			}
			require_once ABSPATH . 'wp-login.php';
			exit;
		}

		// Caso 2: Intento de acceso directo a wp-login.php físico
		if ( 'wp-login.php' === $pagenow && $relative_path !== $slug ) {
			$allowed = false;

			// Acciones autorizadas de WordPress que no deben bloquearse
			$action          = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
			$allowed_actions = array(
				'postpass',
				'logout',
				'lostpassword',
				'retrievepassword',
				'resetpass',
				'rp',
				'register',
				'entered_recovery_mode',
				'confirmaction',
			);

			if ( in_array( $action, $allowed_actions, true ) ) {
				$allowed = true;
			}

			// Permitir peticiones POST (procesamiento de login) o usuarios ya autenticados
			$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
			if ( 'POST' === $method || is_user_logged_in() ) {
				$allowed = true;
			}

			if ( ! $allowed ) {
				$this->render_404_or_redirect();
			}
		}

		// Caso 3: Intento de acceso directo a /wp-admin/ sin estar logueado
		$script_filename = isset( $_SERVER['SCRIPT_FILENAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) ) ) : '';
		$is_admin_post   = ( 'admin-post.php' === $script_filename );

		if ( is_admin() && ! is_user_logged_in() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) && ! $is_admin_post ) {
			$this->render_404_or_redirect();
		}
	}

	/**
	 * Modifica la URL de login generada por WordPress.
	 *
	 * @param string $login_url URL de login predeterminada.
	 * @param string $redirect  URL de redirección post-login.
	 * @return string
	 */
	public function filter_login_url( $login_url, $redirect ) {
		$slug    = $this->get_slug();
		$new_url = home_url( '/' . $slug );
		if ( ! empty( $redirect ) ) {
			$new_url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $new_url );
		}
		return $new_url;
	}

	/**
	 * Filtra site_url para que las llamadas a wp-login.php se traduzcan en el slug personalizado.
	 *
	 * @param string      $url     URL completa.
	 * @param string      $path    Ruta solicitada.
	 * @param string|null $scheme  Esquema.
	 * @param int|null    $blog_id ID del blog.
	 * @return string
	 */
	public function filter_site_url( $url, $path, $scheme = null, $blog_id = null ) {
		if ( is_string( $path ) && false !== strpos( $path, 'wp-login.php' ) ) {
			$slug   = $this->get_slug();
			$query  = '';
			$parsed = parse_url( $path );
			if ( isset( $parsed['query'] ) && ! empty( $parsed['query'] ) ) {
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
		if ( empty( $settings['hide_login_limit_attempts'] ) || '1' !== (string) $settings['hide_login_limit_attempts'] ) {
			return;
		}

		$ip                 = $this->get_user_ip();
		$transient_attempts = 'wpat_att_' . md5( $ip );
		$transient_lockout  = 'wpat_lock_' . md5( $ip );

		if ( get_transient( $transient_lockout ) ) {
			return;
		}

		$attempts = (int) get_transient( $transient_attempts );
		$attempts++;

		$max_attempts = isset( $settings['hide_login_max_attempts'] ) ? max( 1, (int) $settings['hide_login_max_attempts'] ) : 3;
		$lockout      = isset( $settings['hide_login_lockout'] ) ? max( 30, (int) $settings['hide_login_lockout'] ) : 120;

		if ( $attempts >= $max_attempts ) {
			set_transient( $transient_lockout, time() + $lockout, $lockout );
			delete_transient( $transient_attempts );
		} else {
			set_transient( $transient_attempts, $attempts, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Limpia el contador de intentos fallidos cuando el usuario inicia sesión correctamente.
	 *
	 * @param string  $user_login Nombre de usuario logueado.
	 * @param WP_User $user       Objeto de usuario.
	 */
	public function clear_failed_attempts_on_success( $user_login, $user ) {
		$ip = $this->get_user_ip();
		delete_transient( 'wpat_att_' . md5( $ip ) );
	}

	/**
	 * Comprueba si la dirección IP del usuario está bloqueada temporalmente antes de procesar la contraseña.
	 *
	 * @param WP_User|WP_Error|null $user     Usuario o error.
	 * @param string                $username Nombre de usuario.
	 * @param string                $password Contraseña.
	 * @return WP_User|WP_Error
	 */
	public function check_ip_lockout( $user, $username, $password ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['hide_login_limit_attempts'] ) || '1' !== (string) $settings['hide_login_limit_attempts'] ) {
			return $user;
		}

		$ip                = $this->get_user_ip();
		$transient_lockout = 'wpat_lock_' . md5( $ip );
		$lock_time         = get_transient( $transient_lockout );

		if ( $lock_time ) {
			$remaining = (int) $lock_time - time();
			if ( $remaining > 0 ) {
				return new WP_Error(
					'wpat_ip_locked',
					sprintf(
						/* translators: %d: remaining lockout seconds */
						__( '<strong>ERROR:</strong> Demasiados intentos fallidos. Tu IP está bloqueada. Por favor, vuelve a intentarlo en %d segundos.', 'wp-agency-toolkit' ),
						$remaining
					)
				);
			} else {
				delete_transient( $transient_lockout );
			}
		}
		return $user;
	}

	/**
	 * Renderiza el campo del captcha matemático dentro del formulario de Login nativo.
	 */
	public function render_captcha_field() {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['hide_login_captcha'] ) || '1' !== (string) $settings['hide_login_captcha'] ) {
			return;
		}

		$num1   = wp_rand( 1, 9 );
		$num2   = wp_rand( 1, 9 );
		$is_add = ( wp_rand( 0, 1 ) === 1 );

		$result    = $is_add ? ( $num1 + $num2 ) : ( $num1 * $num2 );
		$op_symbol = $is_add ? '+' : '×';

		// Generar desafío criptográfico con expiración por timestamp (protección anti-replay)
		$timestamp = time();
		$salt      = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpat_captcha_salt';
		$hash      = hash_hmac( 'sha256', $result . '|' . $timestamp, $salt );
		$payload   = base64_encode( $timestamp . '|' . $hash );

		?>
		<p class="wpat-captcha-row" style="margin-bottom: 20px;">
			<label for="wpat_captcha_ans"><?php echo esc_html( sprintf( __( 'Seguridad: ¿Cuánto es %1$d %2$s %3$d?', 'wp-agency-toolkit' ), $num1, $op_symbol, $num2 ) ); ?><br />
				<input type="number" name="wpat_captcha_ans" id="wpat_captcha_ans" class="input" value="" size="20" required style="width: 100%;" autocomplete="off" />
				<input type="hidden" name="wpat_captcha_challenge" value="<?php echo esc_attr( $payload ); ?>" />
			</label>
		</p>
		<?php
	}

	/**
	 * Valida la respuesta del captcha al intentar iniciar sesión.
	 * Totalmente compatible con WooCommerce, APIs y formularios de terceros.
	 *
	 * @param WP_User|WP_Error|null $user     Usuario o error.
	 * @param string                $username Nombre de usuario.
	 * @param string                $password Contraseña.
	 * @return WP_User|WP_Error
	 */
	public function validate_captcha( $user, $username, $password ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'POST' !== $method || empty( $username ) ) {
			return $user;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['hide_login_captcha'] ) || '1' !== (string) $settings['hide_login_captcha'] ) {
			return $user;
		}

		// Si el formulario que envía la petición no incluye el desafío (ej. WooCommerce Mi Cuenta / Checkout / REST), no bloquear
		if ( ! isset( $_POST['wpat_captcha_challenge'] ) ) {
			return $user;
		}

		$raw_payload = sanitize_text_field( wp_unslash( $_POST['wpat_captcha_challenge'] ) );
		$decoded     = base64_decode( $raw_payload, true );

		if ( false === $decoded || false === strpos( $decoded, '|' ) ) {
			return new WP_Error(
				'wpat_captcha_failed',
				__( '<strong>ERROR:</strong> Desafío de seguridad inválido o manipulado.', 'wp-agency-toolkit' )
			);
		}

		list( $timestamp, $expected_hash ) = explode( '|', $decoded, 2 );
		$timestamp = (int) $timestamp;

		// Comprobar que el captcha no tenga más de 10 minutos de antigüedad (anti-replay)
		if ( ( time() - $timestamp ) > 600 || $timestamp > ( time() + 60 ) ) {
			return new WP_Error(
				'wpat_captcha_expired',
				__( '<strong>ERROR:</strong> El captcha de seguridad ha caducado. Por favor, recarga la página e inténtalo de nuevo.', 'wp-agency-toolkit' )
			);
		}

		$user_ans  = isset( $_POST['wpat_captcha_ans'] ) ? (int) $_POST['wpat_captcha_ans'] : -999;
		$salt      = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpat_captcha_salt';
		$calc_hash = hash_hmac( 'sha256', $user_ans . '|' . $timestamp, $salt );

		if ( ! hash_equals( $expected_hash, $calc_hash ) ) {
			return new WP_Error(
				'wpat_captcha_failed',
				__( '<strong>ERROR:</strong> La respuesta al Captcha de seguridad es incorrecta.', 'wp-agency-toolkit' )
			);
		}

		return $user;
	}
}


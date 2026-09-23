<?php
/**
 * Módulo: Snippets de Código Personalizados - WP Agency Toolkit
 *
 * Ejecutor seguro y blindado de fragmentos de código (PHP, CSS, JS)
 * con auto-desactivación ante errores fatales, modo seguro de emergencia
 * y compatibilidad total tras desactivar Code Snippets.
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Snippets {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Snippets
	 */
	private static $instance = null;

	/**
	 * Índice del snippet que se está ejecutando actualmente.
	 *
	 * @var int|string|null
	 */
	private $current_executing_key = null;

	/**
	 * Nombre del snippet que se está ejecutando actualmente.
	 *
	 * @var string
	 */
	private $current_executing_name = '';

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Snippets
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
		// Registrar manejador de apagado de emergencia (shutdown handler)
		// para capturar errores fatales de PHP y desactivar el snippet causante al instante
		register_shutdown_function( array( $this, 'handle_fatal_error_shutdown' ) );

		// Compatibilidad con stubs si Code Snippets ha sido desactivado
		$this->register_code_snippets_compat_stubs();

		// Inyección de CSS y JS en Frontend
		add_action( 'wp_head', array( $this, 'inject_css' ), 100 );
		add_action( 'wp_footer', array( $this, 'inject_js' ), 100 );

		// Mostrar avisos informativos sobre snippets auto-desactivados por error
		add_action( 'admin_notices', array( $this, 'display_crash_notices' ) );

		// Comprobar si el Modo Seguro está activo (vía URL o constante)
		if ( $this->is_safe_mode_active() ) {
			add_action( 'admin_notices', array( $this, 'display_safe_mode_notice' ) );
			return;
		}

		// Ejecutar snippets PHP en 'init' prioridad 1 para que todo WordPress (pluggable, plugins, WooCommerce, ACF) esté disponible
		add_action( 'init', array( $this, 'execute_php' ), 1 );
	}

	/**
	 * Comprueba si el Modo Seguro de Snippets está activado.
	 *
	 * @return bool
	 */
	private function is_safe_mode_active() {
		if ( defined( 'WPAT_SNIPPETS_SAFE_MODE' ) && WPAT_SNIPPETS_SAFE_MODE ) {
			return true;
		}

		if ( isset( $_GET['wpat_safe_mode'] ) || isset( $_GET['wpat_snippets_safe'] ) || isset( $_GET['snippets_safe_mode'] ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Muestra aviso cuando el Modo Seguro está activo.
	 */
	public function display_safe_mode_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo '<strong>[WP Agency Toolkit] Modo Seguro de Snippets Activado:</strong> La ejecución de fragmentos PHP está temporalmente pausada para permitir el acceso al panel y la resolución de problemas.';
		echo '</p></div>';
	}

	/**
	 * Define funciones de compatibilidad en caso de que Code Snippets esté desactivado
	 * pero algún fragmento haga referencia a sus funciones auxiliares.
	 */
	private function register_code_snippets_compat_stubs() {
		if ( ! function_exists( 'code_snippets' ) ) {
			function code_snippets() {
				return null;
			}
		}
		if ( ! function_exists( 'code_snippets_get_snippets' ) ) {
			function code_snippets_get_snippets() {
				return array();
			}
		}
	}

	/**
	 * Inyecta los CSS personalizados activos en la cabecera del frontend.
	 */
	public function inject_css() {
		if ( is_admin() ) {
			return;
		}

		$snippets = get_option( 'wpat_snippets', array() );
		if ( empty( $snippets ) || ! is_array( $snippets ) ) {
			return;
		}

		foreach ( $snippets as $snippet ) {
			$is_active = isset( $snippet['active'] ) && ( '1' === (string) $snippet['active'] || true === $snippet['active'] );
			$type      = isset( $snippet['type'] ) ? $snippet['type'] : 'php';

			if ( $is_active && 'css' === $type && ! empty( $snippet['code'] ) ) {
				$css = trim( $snippet['code'] );
				if ( ! empty( $css ) ) {
					$name = isset( $snippet['name'] ) ? $snippet['name'] : 'CSS';
					echo "\n<!-- WPAT Custom CSS Snippet: " . esc_html( $name ) . " -->\n";
					echo "<style type=\"text/css\">\n";
					echo wp_strip_all_tags( $css ) . "\n";
					echo "</style>\n";
				}
			}
		}
	}

	/**
	 * Inyecta los JS personalizados activos en el pie de página del frontend.
	 */
	public function inject_js() {
		if ( is_admin() ) {
			return;
		}

		$snippets = get_option( 'wpat_snippets', array() );
		if ( empty( $snippets ) || ! is_array( $snippets ) ) {
			return;
		}

		foreach ( $snippets as $snippet ) {
			$is_active = isset( $snippet['active'] ) && ( '1' === (string) $snippet['active'] || true === $snippet['active'] );
			$type      = isset( $snippet['type'] ) ? $snippet['type'] : 'php';

			if ( $is_active && 'js' === $type && ! empty( $snippet['code'] ) ) {
				$js = trim( $snippet['code'] );
				if ( ! empty( $js ) ) {
					$name = isset( $snippet['name'] ) ? $snippet['name'] : 'JS';
					echo "\n<!-- WPAT Custom JS Snippet: " . esc_html( $name ) . " -->\n";
					echo "<script type=\"text/javascript\">\n";
					echo $js . "\n";
					echo "</script>\n";
				}
			}
		}
	}

	/**
	 * Ejecuta de forma segura los fragmentos PHP activos.
	 */
	public function execute_php() {
		$snippets = get_option( 'wpat_snippets', array() );
		if ( empty( $snippets ) || ! is_array( $snippets ) ) {
			return;
		}

		$is_admin_area = is_admin();

		foreach ( $snippets as $key => $snippet ) {
			$is_active = isset( $snippet['active'] ) && ( '1' === (string) $snippet['active'] || true === $snippet['active'] );
			$type      = isset( $snippet['type'] ) ? $snippet['type'] : 'php';
			$location  = isset( $snippet['location'] ) ? $snippet['location'] : ( isset( $snippet['scope'] ) ? $snippet['scope'] : 'everywhere' );

			// Si no es un snippet PHP o no está activo, omitir
			if ( ! $is_active || 'php' !== $type ) {
				continue;
			}

			// Filtrar por ámbito/ubicación de ejecución
			if ( ( 'admin' === $location || '1' === (string) $location ) && ! $is_admin_area ) {
				continue;
			}
			if ( ( 'frontend' === $location || 'site' === $location || '2' === (string) $location ) && $is_admin_area ) {
				continue;
			}

			$php_code = isset( $snippet['code'] ) ? trim( $snippet['code'] ) : '';
			if ( empty( $php_code ) ) {
				continue;
			}

			// Limpiar etiquetas de apertura/cierre de PHP
			$php_code = preg_replace( '/^\s*<\?(php)?/i', '', $php_code );
			$php_code = preg_replace( '/\?>\s*$/', '', $php_code );
			$php_code = trim( $php_code );

			if ( empty( $php_code ) ) {
				continue;
			}

			$snippet_name = ! empty( $snippet['name'] ) ? $snippet['name'] : "Snippet #{$key}";

			$this->execute_single_php( $php_code, $snippet_name, $key );
		}
	}

	/**
	 * Ejecuta un único fragmento PHP con protección sandbox y registro de estado.
	 *
	 * @param string     $php_code Código a evaluar.
	 * @param string     $name     Nombre descriptivo del snippet.
	 * @param int|string $key      Clave del snippet en la base de datos.
	 */
	private function execute_single_php( $php_code, $name, $key ) {
		// Validar sintaxis básica antes de evaluar
		if ( ! $this->validate_php_syntax( $php_code ) ) {
			$this->auto_deactivate_faulty_snippet( $key, $name, 'Error de sintaxis detectado antes de la ejecución.' );
			return;
		}

		// Marcar snippet en ejecución para el shutdown handler
		$this->current_executing_key  = $key;
		$this->current_executing_name = $name;

		try {
			eval( $php_code );
		} catch ( \Throwable $t ) {
			// Auto-desactivar el snippet para que la web no se rompa en la siguiente petición
			$this->auto_deactivate_faulty_snippet(
				$key,
				$name,
				sprintf( '%s en la línea %d', $t->getMessage(), $t->getLine() )
			);
		} finally {
			// Limpiar el estado de ejecución al finalizar con éxito el snippet
			$this->current_executing_key  = null;
			$this->current_executing_name = '';
		}
	}

	/**
	 * Valida que el código PHP no tenga tokens rotos o errores evidentes.
	 *
	 * @param string $code Código a verificar.
	 * @return bool
	 */
	private function validate_php_syntax( $code ) {
		// Si la función token_get_all está disponible, hacer verificación léxica
		if ( function_exists( 'token_get_all' ) ) {
			try {
				$tokens = @token_get_all( "<?php " . $code );
				if ( empty( $tokens ) ) {
					return false;
				}
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Auto-desactiva de inmediato un snippet que ha provocado un error
	 * y registra una notificación para el administrador.
	 *
	 * @param int|string $key   Clave del snippet.
	 * @param string     $name  Nombre del snippet.
	 * @param string     $error Detalle del error.
	 */
	private function auto_deactivate_faulty_snippet( $key, $name, $error ) {
		$snippets = get_option( 'wpat_snippets', array() );
		if ( isset( $snippets[ $key ] ) ) {
			$snippets[ $key ]['active'] = '0';
			update_option( 'wpat_snippets', $snippets );
		}

		$log_msg = sprintf(
			'[WPAT Snippets] El fragmento "%s" fue DESACTIVADO automáticamente para proteger el sitio web. Causa: %s',
			$name,
			$error
		);
		error_log( $log_msg );

		// Guardar aviso para mostrar en el panel de administración
		$crashes = get_transient( 'wpat_snippets_crashes' );
		if ( ! is_array( $crashes ) ) {
			$crashes = array();
		}

		$crashes[] = array(
			'name'  => $name,
			'error' => $error,
			'time'  => current_time( 'mysql' ),
		);

		set_transient( 'wpat_snippets_crashes', $crashes, DAY_IN_SECONDS );
	}

	/**
	 * Manejador de apagado (Shutdown Handler) para atrapar Fatal Errors (E_ERROR, E_PARSE, E_COMPILE_ERROR).
	 */
	public function handle_fatal_error_shutdown() {
		if ( null === $this->current_executing_key ) {
			return;
		}

		$error = error_get_last();
		if ( ! $error ) {
			return;
		}

		$fatal_error_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		if ( in_array( $error['type'], $fatal_error_types, true ) ) {
			// Desactivar inmediatamente el snippet en la base de datos
			$snippets = get_option( 'wpat_snippets', array() );
			if ( isset( $snippets[ $this->current_executing_key ] ) ) {
				$snippets[ $this->current_executing_key ]['active'] = '0';
				update_option( 'wpat_snippets', $snippets );
			}

			error_log( sprintf(
				'[WPAT Snippets FATAL ERROR] El snippet "%s" provocó un error crítico fatal (%s en %s:%d) y ha sido auto-desactivado de inmediato.',
				$this->current_executing_name,
				$error['message'],
				$error['file'],
				$error['line']
			) );

			$crashes = get_transient( 'wpat_snippets_crashes' );
			if ( ! is_array( $crashes ) ) {
				$crashes = array();
			}

			$crashes[] = array(
				'name'  => $this->current_executing_name,
				'error' => $error['message'] . ' en la línea ' . $error['line'],
				'time'  => current_time( 'mysql' ),
			);

			set_transient( 'wpat_snippets_crashes', $crashes, DAY_IN_SECONDS );
		}
	}

	/**
	 * Muestra avisos en el panel de control si se desactivó algún snippet defectuoso.
	 */
	public function display_crash_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$crashes = get_transient( 'wpat_snippets_crashes' );
		if ( empty( $crashes ) || ! is_array( $crashes ) ) {
			return;
		}

		foreach ( $crashes as $crash ) {
			echo '<div class="notice notice-error is-dismissible" style="border-left-color: #ef4444;"><p>';
			echo '<strong>⚠️ [WP Agency Toolkit - Protección de Snippets]:</strong> El fragmento de código <code>' . esc_html( $crash['name'] ) . '</code> fue <strong>desactivado automáticamente</strong> para evitar un error crítico en tu sitio web.<br>';
			echo '<small style="color: #64748b;">Detalle: ' . esc_html( $crash['error'] ) . '</small>';
			echo '</p></div>';
		}

		// Limpiar aviso tras ser visualizado
		delete_transient( 'wpat_snippets_crashes' );
	}
}


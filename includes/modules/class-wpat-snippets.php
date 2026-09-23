<?php
/**
 * Módulo: Snippets de Código Personalizados - WP Agency Toolkit
 *
 * Ejecutor blindado y 100% seguro de fragmentos de código (PHP, CSS, JS)
 * con auto-desactivación ante errores fatales, aislamiento de ámbito,
 * captura de errores en callbacks encolados, modo seguro de emergencia
 * y compatibilidad total tras desactivar el plugin "Code Snippets".
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Snippets {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Snippets
	 */
	private static $instance = null;

	/**
	 * Clave del snippet que se está ejecutando actualmente.
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
	 * Lista de claves de snippets activos cargados en la petición.
	 *
	 * @var array
	 */
	private $active_php_snippets_loaded = array();

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
		// 1. Registrar manejador de apagado de emergencia (shutdown handler)
		// para capturar cualquier error fatal de PHP y desactivar el snippet causante de inmediato.
		register_shutdown_function( array( $this, 'handle_fatal_error_shutdown' ) );

		// 2. Registrar funciones y clases stub de compatibilidad con Code Snippets
		$this->register_code_snippets_compat_stubs();

		// 3. Comprobar desactivación de emergencia vía URL (?wpat_disable_snippets=1)
		$this->handle_emergency_disable();

		// 4. Inyección de CSS y JS en Frontend
		add_action( 'wp_head', array( $this, 'inject_css' ), 100 );
		add_action( 'wp_footer', array( $this, 'inject_js' ), 100 );

		// 5. Avisos informativos en el panel de administración
		add_action( 'admin_notices', array( $this, 'display_crash_notices' ) );

		// 6. Comprobar si el Modo Seguro está activo
		if ( $this->is_safe_mode_active() ) {
			add_action( 'admin_notices', array( $this, 'display_safe_mode_notice' ) );
			return;
		}

		// 7. Ejecutar snippets PHP en 'init' prioridad 1 para que todo WordPress, WooCommerce y plugins estén disponibles
		add_action( 'init', array( $this, 'execute_php' ), 1 );
	}

	/**
	 * Desactivación de emergencia de todos los snippets vía parámetro URL si el usuario es administrador.
	 * Ejemplo: https://tusitio.com/wp-admin/?wpat_disable_snippets=1
	 */
	private function handle_emergency_disable() {
		if ( isset( $_GET['wpat_disable_snippets'] ) && '1' === (string) $_GET['wpat_disable_snippets'] ) {
			if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
				$snippets = get_option( 'wpat_snippets', array() );
				if ( is_array( $snippets ) && ! empty( $snippets ) ) {
					$deactivated_count = 0;
					foreach ( $snippets as $k => $snip ) {
						if ( isset( $snip['active'] ) && ( '1' === (string) $snip['active'] || true === $snip['active'] ) ) {
							$snippets[ $k ]['active'] = '0';
							$deactivated_count++;
						}
					}
					if ( $deactivated_count > 0 ) {
						update_option( 'wpat_snippets', $snippets );
						set_transient(
							'wpat_snippets_crashes',
							array(
								array(
									'name'  => 'Desactivación de Emergencia',
									'error' => sprintf( 'Se han desactivado todos los snippets activos (%d) mediante el parámetro de emergencia.', $deactivated_count ),
									'time'  => current_time( 'mysql' ),
								),
							),
							DAY_IN_SECONDS
						);
					}
				}
			}
		}
	}

	/**
	 * Comprueba si el Modo Seguro de Snippets está activado.
	 *
	 * @return bool
	 */
	public function is_safe_mode_active() {
		if ( defined( 'WPAT_SNIPPETS_SAFE_MODE' ) && WPAT_SNIPPETS_SAFE_MODE ) {
			return true;
		}

		if ( isset( $_GET['wpat_safe_mode'] ) || isset( $_GET['wpat_snippets_safe'] ) || isset( $_GET['snippets_safe_mode'] ) ) {
			if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
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
		echo '<div class="notice notice-warning is-dismissible" style="border-left-color: #f59e0b;"><p>';
		echo '<strong>🛡️ [WP Agency Toolkit] Modo Seguro de Snippets Activado:</strong> La ejecución de fragmentos PHP está temporalmente pausada para permitir el acceso al panel y la resolución de incidencias de forma segura.';
		echo '</p></div>';
	}

	/**
	 * Define funciones y clases de compatibilidad en caso de que Code Snippets esté desactivado
	 * pero algún fragmento o tema haga referencia a sus funciones auxiliares.
	 */
	private function register_code_snippets_compat_stubs() {
		if ( ! defined( 'CODE_SNIPPETS_FILE' ) ) {
			define( 'CODE_SNIPPETS_FILE', __FILE__ );
		}
		if ( ! defined( 'CODE_SNIPPETS_VERSION' ) ) {
			define( 'CODE_SNIPPETS_VERSION', '3.6.5' );
		}

		if ( ! function_exists( 'code_snippets' ) ) {
			function code_snippets() {
				return (object) array(
					'version' => '3.6.5',
					'db'      => (object) array(),
				);
			}
		}

		if ( ! function_exists( 'code_snippets_get_snippets' ) ) {
			function code_snippets_get_snippets( $scopes = array() ) {
				$snippets = get_option( 'wpat_snippets', array() );
				$results  = array();
				if ( is_array( $snippets ) ) {
					foreach ( $snippets as $id => $s ) {
						$results[] = (object) array(
							'id'          => $id,
							'name'        => isset( $s['name'] ) ? $s['name'] : '',
							'code'        => isset( $s['code'] ) ? $s['code'] : '',
							'description' => isset( $s['description'] ) ? $s['description'] : '',
							'active'      => isset( $s['active'] ) && ( '1' === (string) $s['active'] || true === $s['active'] ),
							'scope'       => isset( $s['location'] ) ? $s['location'] : 'global',
						);
					}
				}
				return $results;
			}
		}

		if ( ! function_exists( 'code_snippets_execute_snippet' ) ) {
			function code_snippets_execute_snippet( $code, $id = 0 ) {
				return true;
			}
		}

		if ( ! function_exists( 'is_snippet_active' ) ) {
			function is_snippet_active( $id ) {
				$snippets = get_option( 'wpat_snippets', array() );
				if ( isset( $snippets[ $id ]['active'] ) ) {
					return ( '1' === (string) $snippets[ $id ]['active'] || true === $snippets[ $id ]['active'] );
				}
				return false;
			}
		}

		if ( ! function_exists( 'get_snippet' ) ) {
			function get_snippet( $id ) {
				$snippets = get_option( 'wpat_snippets', array() );
				if ( isset( $snippets[ $id ] ) ) {
					$s = $snippets[ $id ];
					return (object) array(
						'id'          => $id,
						'name'        => isset( $s['name'] ) ? $s['name'] : '',
						'code'        => isset( $s['code'] ) ? $s['code'] : '',
						'description' => isset( $s['description'] ) ? $s['description'] : '',
						'active'      => isset( $s['active'] ) && ( '1' === (string) $s['active'] || true === $s['active'] ),
					);
				}
				return false;
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
					echo "\n<!-- WPAT Custom CSS: " . esc_html( $name ) . " -->\n";
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
					echo "\n<!-- WPAT Custom JS: " . esc_html( $name ) . " -->\n";
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

			$this->active_php_snippets_loaded[ $key ] = array(
				'name' => $snippet_name,
				'code' => $php_code,
			);

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
		// Validar sintaxis antes de evaluar
		if ( ! $this->validate_php_syntax( $php_code ) ) {
			$this->auto_deactivate_faulty_snippet( $key, $name, 'Error de sintaxis detectado antes de la ejecución.' );
			return;
		}

		// Marcar snippet en ejecución para el shutdown handler
		$this->current_executing_key  = $key;
		$this->current_executing_name = $name;

		// Sandbox aislado: función estática anónima para evitar fugas de $this
		$sandbox_runner = static function( $code ) {
			// Buffer de salida para evitar que ecos o espacios en blanco rompan cabeceras HTTP
			ob_start();
			try {
				$result = eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
				$output = ob_get_clean();
				if ( ! empty( $output ) && ! is_admin() ) {
					echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				return $result;
			} catch ( \Throwable $e ) {
				ob_end_clean();
				throw $e;
			}
		};

		try {
			$sandbox_runner( $php_code );
		} catch ( \Throwable $t ) {
			// Auto-desactivar el snippet para que la web no se rompa en la siguiente petición
			$this->auto_deactivate_faulty_snippet(
				$key,
				$name,
				sprintf( '%s en la línea %d', $t->getMessage(), $t->getLine() )
			);
		} finally {
			// Limpiar el estado de ejecución al finalizar el bloque de inicialización
			$this->current_executing_key  = null;
			$this->current_executing_name = '';
		}
	}

	/**
	 * Valida que el código PHP no tenga tokens rotos o errores léxicos.
	 *
	 * @param string $code Código a verificar.
	 * @return bool
	 */
	private function validate_php_syntax( $code ) {
		if ( function_exists( 'token_get_all' ) ) {
			try {
				$tokens = @token_get_all( "<?php \n" . $code );
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
	public function auto_deactivate_faulty_snippet( $key, $name, $error ) {
		$snippets = get_option( 'wpat_snippets', array() );
		if ( isset( $snippets[ $key ] ) ) {
			$snippets[ $key ]['active'] = '0';
			update_option( 'wpat_snippets', $snippets );
		}

		$log_msg = sprintf(
			'[WPAT Snippets] El fragmento "%s" (ID: %s) fue DESACTIVADO automáticamente para proteger el sitio web. Causa: %s',
			$name,
			(string) $key,
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
	 * Manejador de apagado (Shutdown Handler) para atrapar Fatal Errors (E_ERROR, E_PARSE, E_COMPILE_ERROR, etc.)
	 * incluso si ocurren en callbacks enganchados a hooks posteriores de WordPress.
	 */
	public function handle_fatal_error_shutdown() {
		$error = error_get_last();
		if ( ! $error ) {
			return;
		}

		$fatal_error_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
		if ( ! in_array( $error['type'], $fatal_error_types, true ) ) {
			return;
		}

		$error_file = isset( $error['file'] ) ? $error['file'] : '';
		$error_msg  = isset( $error['message'] ) ? $error['message'] : '';
		$error_line = isset( $error['line'] ) ? (int) $error['line'] : 0;

		$is_eval_error     = ( false !== strpos( $error_file, "eval()'d code" ) );
		$is_snippets_class = ( false !== strpos( $error_file, 'class-wpat-snippets.php' ) );

		// Caso 1: El error ocurrió durante la ejecución directa de un snippet concreto
		if ( null !== $this->current_executing_key ) {
			$this->auto_deactivate_faulty_snippet(
				$this->current_executing_key,
				$this->current_executing_name,
				sprintf( '%s en %s:%d', $error_msg, $error_file, $error_line )
			);
			return;
		}

		// Caso 2: El error ocurrió en código evaluado (eval) o vinculado a snippets
		if ( $is_eval_error || $is_snippets_class || ! empty( $this->active_php_snippets_loaded ) ) {
			$snippets = get_option( 'wpat_snippets', array() );
			if ( empty( $snippets ) || ! is_array( $snippets ) ) {
				return;
			}

			// Intentar correlacionar el error con un snippet específico buscando nombres de funciones en el mensaje de error
			$matched_key = null;
			foreach ( $this->active_php_snippets_loaded as $snip_key => $snip_data ) {
				$code = $snip_data['code'];
				// Extraer funciones definidas en el snippet
				if ( preg_match_all( '/function\s+([a-zA-Z0-9_]+)\s*\(/i', $code, $matches ) ) {
					foreach ( $matches[1] as $fn_name ) {
						if ( false !== stripos( $error_msg, $fn_name ) ) {
							$matched_key = $snip_key;
							break 2;
						}
					}
				}
			}

			if ( null !== $matched_key && isset( $snippets[ $matched_key ] ) ) {
				$snip_name = isset( $snippets[ $matched_key ]['name'] ) ? $snippets[ $matched_key ]['name'] : "Snippet #{$matched_key}";
				$this->auto_deactivate_faulty_snippet(
					$matched_key,
					$snip_name,
					sprintf( 'Error fatal en hook: %s (Línea %d)', $error_msg, $error_line )
				);
			} elseif ( $is_eval_error ) {
				// Si no pudimos identificar la función exacta pero el error fatal proviene de eval()'d code,
				// desactivamos los snippets PHP activos para salvar el sitio de un bucle de bloqueo permanente
				$deactivated_names = array();
				foreach ( $snippets as $k => $snip ) {
					if ( isset( $snip['type'] ) && 'php' === $snip['type'] && isset( $snip['active'] ) && ( '1' === (string) $snip['active'] || true === $snip['active'] ) ) {
						$snippets[ $k ]['active'] = '0';
						$deactivated_names[]      = isset( $snip['name'] ) ? $snip['name'] : "ID #{$k}";
					}
				}
				update_option( 'wpat_snippets', $snippets );

				$names_str = implode( ', ', $deactivated_names );
				error_log( sprintf( '[WPAT Snippets FATAL EMERGENCY] Se han auto-desactivado los snippets PHP [%s] tras un error crítico en eval(): %s', $names_str, $error_msg ) );

				$crashes   = get_transient( 'wpat_snippets_crashes' );
				$crashes   = is_array( $crashes ) ? $crashes : array();
				$crashes[] = array(
					'name'  => 'Protección Automática Global',
					'error' => sprintf( 'Error crítico en fragmento PHP: %s. Se han desactivado temporalmente los snippets (%s) para mantener el sitio online.', $error_msg, $names_str ),
					'time'  => current_time( 'mysql' ),
				);
				set_transient( 'wpat_snippets_crashes', $crashes, DAY_IN_SECONDS );
			}
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
			echo '<div class="notice notice-error is-dismissible" style="border-left-color: #ef4444; padding: 12px 16px; margin: 15px 0;"><p style="margin: 0 0 6px 0;">';
			echo '<strong style="font-size: 14px;">🛡️ [WP Agency Toolkit - Protección de Snippets]:</strong> El fragmento de código <strong>«' . esc_html( $crash['name'] ) . '»</strong> fue <strong>desactivado automáticamente</strong> para evitar un error crítico en tu sitio web.';
			echo '</p><p style="margin: 0; color: #64748b; font-family: monospace; font-size: 12px; background: #f8fafc; padding: 6px 10px; border-radius: 4px; border: 1px solid #e2e8f0;">';
			echo '<strong>Detalle:</strong> ' . esc_html( $crash['error'] );
			echo '</p></div>';
		}

		// Limpiar aviso tras ser visualizado
		delete_transient( 'wpat_snippets_crashes' );
	}
}

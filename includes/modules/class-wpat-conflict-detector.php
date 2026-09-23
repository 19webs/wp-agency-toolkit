<?php
/**
 * Módulo: Detector de Incompatibilidades y Conflictos JS/CSS - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Conflict_Detector {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Conflict_Detector|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Conflict_Detector
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
		// Mostrar avisos informativos en el panel si hay plugins conflictivos activos
		add_action( 'admin_notices', array( $this, 'display_conflict_notices' ) );

		// Bloquear activación de plugins de terceros si el módulo nativo correspondiente ya está activo
		add_action( 'activate_plugin', array( $this, 'prevent_conflicting_plugin_activation' ), 10, 2 );

		// Monitor de errores JS y diagnóstico en tiempo real (exclusivo administradores)
		add_action( 'wp_footer', array( $this, 'inject_js_error_listener' ) );
		add_action( 'admin_footer', array( $this, 'inject_js_error_listener' ) );

		// AJAX endpoints para reporte y limpieza de errores JS
		add_action( 'wp_ajax_wpat_log_js_error', array( $this, 'ajax_log_js_error' ) );
		add_action( 'wp_ajax_wpat_clear_js_errors', array( $this, 'ajax_clear_js_errors' ) );
	}

	/**
	 * Retorna el catálogo completo de reglas de conflicto y plugins equivalentes conocidos.
	 *
	 * @return array
	 */
	public static function get_conflict_rules() {
		return array(
			// 1. Snippets y Código
			'code-snippets/code-snippets.php' => array(
				'name'             => 'Code Snippets',
				'wpat_module'      => 'snippets',
				'module_name'      => __( 'Fragmentos de Código (Snippets)', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Code Snippets está activo. WP Agency Toolkit incluye su propio ejecutor nativo de Snippets. Mantener ambos activos causa llamadas duplicadas y errores críticos de funciones redeclaradas.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Code Snippets está instalado. Se recomienda desinstalarlo si ya utilizas los Snippets nativos de la suite.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'insert-headers-and-footers/ihaf.php' => array(
				'name'             => 'WPCode (Insert Headers and Footers)',
				'wpat_module'      => 'integrations',
				'module_name'      => __( 'Integraciones & Scripts', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'WPCode está activo. WP Agency Toolkit integra inyección nativa de scripts en cabecera y pie de página en el panel de Integraciones.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'WPCode está instalado. Desinstálalo si ya utilizas las herramientas de inyección de scripts de WP Agency Toolkit.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'header-footer-code-manager/hfcm.php' => array(
				'name'             => 'Header Footer Code Manager (HFCM)',
				'wpat_module'      => 'integrations',
				'module_name'      => __( 'Integraciones & Scripts', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'HFCM está activo y duplica la funcionalidad nativa de inyección de scripts de WP Agency Toolkit.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'HFCM está instalado. Puedes desinstalarlo de forma segura.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),

			// 2. Duplicadores de Contenido
			'duplicate-post/duplicate-post.php' => array(
				'name'             => 'Yoast Duplicate Post',
				'wpat_module'      => 'duplicate-post',
				'module_name'      => __( 'Duplicador de Entradas y Páginas', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Yoast Duplicate Post está activo. WP Agency Toolkit incluye un duplicador nativo ultraligero de entradas y páginas.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Yoast Duplicate Post está instalado en el servidor. Puedes desinstalarlo de forma segura para ahorrar recursos.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'duplicate-page/duplicatepage.php' => array(
				'name'             => 'Duplicate Page',
				'wpat_module'      => 'duplicate-post',
				'module_name'      => __( 'Duplicador de Entradas y Páginas', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Duplicate Page está activo y colisiona con el duplicador nativo de la suite.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Duplicate Page está instalado en el servidor. Se recomienda desinstalarlo.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'post-duplicator/post-duplicator.php' => array(
				'name'             => 'Post Duplicator',
				'wpat_module'      => 'duplicate-post',
				'module_name'      => __( 'Duplicador de Entradas y Páginas', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Post Duplicator está activo y duplica el módulo nativo de duplicación de entradas.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Post Duplicator está instalado en el servidor.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),

			// 3. Envío de Correo SMTP
			'wp-mail-smtp/wp_mail_smtp.php' => array(
				'name'             => 'WP Mail SMTP',
				'wpat_module'      => 'smtp',
				'module_name'      => __( 'Configuración SMTP', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'WP Mail SMTP está activo. Mantener ambos activos causa conflictos en phpmailer_init y duplicidad de envíos.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'WP Mail SMTP está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'easy-wp-smtp/easy-wp-smtp.php' => array(
				'name'             => 'Easy WP SMTP',
				'wpat_module'      => 'smtp',
				'module_name'      => __( 'Configuración SMTP', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Easy WP SMTP está activo y colisiona con el módulo SMTP nativo de WP Agency Toolkit.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Easy WP SMTP está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'fluent-smtp/fluent-smtp.php' => array(
				'name'             => 'FluentSMTP',
				'wpat_module'      => 'smtp',
				'module_name'      => __( 'Configuración SMTP', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'FluentSMTP está activo y colisiona con el módulo SMTP nativo.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'FluentSMTP está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'post-smtp/postman-smtp.php' => array(
				'name'             => 'Post SMTP Mailer',
				'wpat_module'      => 'smtp',
				'module_name'      => __( 'Configuración SMTP', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Post SMTP está activo y colisiona con el módulo SMTP nativo.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Post SMTP está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),

			// 4. Seguridad y Ocultación de Login
			'wps-hide-login/wps-hide-login.php' => array(
				'name'             => 'WPS Hide Login',
				'wpat_module'      => 'hide-login',
				'module_name'      => __( 'Ocultar URL de Login', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'WPS Hide Login está activo. Tener ambos módulos de cambio de URL de login activos genera bucles de redirección infinita y bloqueo total del panel.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'WPS Hide Login está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'disable-xml-rpc/disable-xml-rpc.php' => array(
				'name'             => 'Disable XML-RPC',
				'wpat_module'      => 'security-hardening',
				'module_name'      => __( 'Fortalecimiento de Seguridad', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Disable XML-RPC está activo. Esta protección ya está incluida en Fortalecimiento de Seguridad.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Disable XML-RPC está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => array(
				'name'             => 'Limit Login Attempts Reloaded',
				'wpat_module'      => 'hide-login',
				'module_name'      => __( 'Ocultar Login & Límite de Intentos', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Limit Login Attempts está activo y se pisa con el bloqueador de fuerza bruta de WP Agency Toolkit.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Limit Login Attempts está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),

			// 5. Desactivar Comentarios
			'disable-comments/disable-comments.php' => array(
				'name'             => 'Disable Comments',
				'wpat_module'      => 'disable-comments',
				'module_name'      => __( 'Desactivar Comentarios Globales', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Disable Comments está activo. WP Agency Toolkit incluye control nativo granular de comentarios en entradas, páginas y medios.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Disable Comments está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),

			// 6. Modo Mantenimiento
			'under-construction-page/under-construction.php' => array(
				'name'             => 'Under Construction',
				'wpat_module'      => 'maintenance-mode',
				'module_name'      => __( 'Modo Mantenimiento y Coming Soon', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Under Construction está activo y entra en conflicto con el Modo Mantenimiento de WP Agency Toolkit.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Under Construction está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'coming-soon/coming-soon.php' => array(
				'name'             => 'Coming Soon Page by SeedProd',
				'wpat_module'      => 'maintenance-mode',
				'module_name'      => __( 'Modo Mantenimiento y Coming Soon', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'SeedProd Coming Soon está activo y genera redirecciones duplicadas con el Modo Mantenimiento nativo.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'SeedProd Coming Soon está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'cmp-coming-soon-maintenance/cmp-coming-soon-maintenance.php' => array(
				'name'             => 'CMP - Coming Soon & Maintenance',
				'wpat_module'      => 'maintenance-mode',
				'module_name'      => __( 'Modo Mantenimiento y Coming Soon', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'CMP está activo y entra en conflicto con el Modo Mantenimiento de la suite.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'CMP está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),

			// 7. Chat de WhatsApp
			'joinchat/joinchat.php' => array(
				'name'             => 'Joinchat',
				'wpat_module'      => 'whatsapp',
				'module_name'      => __( 'Botón Flotante de WhatsApp', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Joinchat está activo y genera botones flotantes duplicados con el módulo nativo de WhatsApp de WP Agency Toolkit.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Joinchat está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'click-to-chat-for-whatsapp/click-to-chat.php' => array(
				'name'             => 'Click to Chat',
				'wpat_module'      => 'whatsapp',
				'module_name'      => __( 'Botón Flotante de WhatsApp', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Click to Chat está activo y duplica el módulo de WhatsApp.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Click to Chat está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),

			// 8. Accesibilidad
			'one-click-accessibility/one-click-accessibility.php' => array(
				'name'             => 'One Click Accessibility',
				'wpat_module'      => 'accessibility',
				'module_name'      => __( 'Herramientas de Accesibilidad', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'One Click Accessibility está activo y colisiona con la barra de accesibilidad de WP Agency Toolkit.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'One Click Accessibility está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),

			// 9. WooCommerce PDF Invoices y Swatches
			'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php' => array(
				'name'             => 'WooCommerce PDF Invoices & Packing Slips',
				'wpat_module'      => 'woo-pdf-invoices',
				'module_name'      => __( 'Facturas PDF y Albaranes Automáticos', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'PDF Invoices está activo y puede duplicar la generación de facturas automáticas de WooCommerce.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'PDF Invoices está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'woo-variation-swatches/woo-variation-swatches.php' => array(
				'name'             => 'Variation Swatches for WooCommerce',
				'wpat_module'      => 'woo-variation-swatches',
				'module_name'      => __( 'Swatches de Variación de Producto', 'wp-agency-toolkit' ),
				'reason_active'    => __( 'Variation Swatches está activo y genera selectores de variación duplicados en la ficha de producto.', 'wp-agency-toolkit' ),
				'reason_installed' => __( 'Variation Swatches está instalado.', 'wp-agency-toolkit' ),
				'action_link'      => admin_url( 'plugins.php' ),
			),
		);
	}

	/**
	 * Obtiene los conflictos activos e instalados en la instalación.
	 *
	 * @return array
	 */
	public static function get_active_conflicts() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_sitewide = (array) get_site_option( 'active_sitewide_plugins', array() );
			$active_plugins  = array_merge( $active_plugins, array_keys( $active_sitewide ) );
		}

		$rules     = self::get_conflict_rules();
		$conflicts = array();

		foreach ( $rules as $plugin_file => $data ) {
			$is_active    = in_array( $plugin_file, $active_plugins, true );
			$is_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin_file );

			if ( $is_active || $is_installed ) {
				$data['status'] = $is_active ? 'active' : 'installed';
				$data['reason'] = $is_active ? $data['reason_active'] : $data['reason_installed'];
				$conflicts[ $plugin_file ] = $data;
			}
		}

		return $conflicts;
	}

	/**
	 * Intercepta y bloquea la activación de un plugin externo si ya tenemos activo el módulo nativo equivalente.
	 *
	 * @param string $plugin       Ruta del plugin a activar.
	 * @param bool   $network_wide Si la activación es multisitio.
	 */
	public function prevent_conflicting_plugin_activation( $plugin, $network_wide = false ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		$rules    = self::get_conflict_rules();

		if ( isset( $rules[ $plugin ] ) ) {
			$rule          = $rules[ $plugin ];
			$wpat_mod      = isset( $rule['wpat_module'] ) ? $rule['wpat_module'] : '';
			$is_mod_active = ! empty( $wpat_mod ) && isset( $settings[ $wpat_mod ] ) && '1' === (string) $settings[ $wpat_mod ];

			if ( $is_mod_active ) {
				$msg = sprintf(
					'<div style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; max-width:600px; margin:20px auto; padding:20px; border-left:4px solid #ef4444; background:#fff; box-shadow:0 4px 6px rgba(0,0,0,0.05);">'
					. '<h2 style="color:#ef4444; margin-top:0;">⚠️ %s</h2>'
					. '<p style="font-size:14px; line-height:1.6; color:#334155;">%s</p>'
					. '<p style="font-size:13px; color:#64748b;">%s</p>'
					. '<p style="font-size:13px; color:#334155;"><strong>%s</strong><br>%s</p>'
					. '</div>',
					esc_html__( 'Activación Bloqueada por Seguridad (WP Agency Toolkit)', 'wp-agency-toolkit' ),
					sprintf(
						/* translators: 1: Plugin Name, 2: Module Name */
						esc_html__( 'No se puede activar el plugin "%1$s" porque ya tienes habilitado el módulo equivalente "%2$s" en WP Agency Toolkit.', 'wp-agency-toolkit' ),
						'<strong>' . esc_html( $rule['name'] ) . '</strong>',
						'<strong>' . esc_html( $rule['module_name'] ) . '</strong>'
					),
					esc_html__( 'Mantener ambos plugins activos simultáneamente provocaría colisiones críticas (redirecciones infinitas, ejecuciones duplicadas o errores fatales en WordPress).', 'wp-agency-toolkit' ),
					esc_html__( '¿Qué debes hacer?', 'wp-agency-toolkit' ),
					esc_html__( 'Desactiva primero el módulo en WP Agency Toolkit si prefieres usar este plugin externo, o continúa usando la solución nativa optimizada.', 'wp-agency-toolkit' )
				);

				wp_die( $msg, esc_html__( 'Conflicto de Plugin Detectado - WP Agency Toolkit', 'wp-agency-toolkit' ), array( 'back_link' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Muestra avisos globales en el panel de administración únicamente cuando hay plugins conflictivos ACTIVOS.
	 */
	public function display_conflict_notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$settings  = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : get_option( 'wpat_settings', array() );
		$conflicts = self::get_active_conflicts();
		if ( empty( $conflicts ) ) {
			return;
		}

		foreach ( $conflicts as $plugin_file => $info ) {
			// Solo actuar si el plugin externo está ACTIVO en WordPress
			if ( 'active' !== $info['status'] ) {
				continue;
			}

			// Solo mostrar aviso si el módulo equivalente de WP Agency Toolkit también está ACTIVO.
			// Si nuestro módulo está desactivado, no se ejecutan sus funciones ni hay colisión alguna.
			$wpat_mod      = isset( $info['wpat_module'] ) ? $info['wpat_module'] : '';
			$is_mod_active = ! empty( $wpat_mod ) && isset( $settings[ $wpat_mod ] ) && '1' === (string) $settings[ $wpat_mod ];

			if ( ! $is_mod_active ) {
				continue;
			}

			$dismiss_key = 'wpat_dismiss_conflict_' . md5( $plugin_file );
			if ( get_transient( $dismiss_key ) ) {
				continue;
			}
			?>
			<div class="notice notice-warning is-dismissible wpat-conflict-notice" style="border-left-color: #f59e0b; padding: 12px 16px;">
				<p style="margin: 0 0 6px 0; font-size: 14px; font-weight: 600; color: #b45309;">
					⚠️ <strong><?php esc_html_e( 'WP Agency Toolkit: Conflicto o Duplicidad detectada', 'wp-agency-toolkit' ); ?></strong>
				</p>
				<p style="margin: 0 0 8px 0; font-size: 13px; color: #374151;">
					<?php
					echo sprintf(
						/* translators: 1: Plugin Name, 2: Reason */
						esc_html__( 'El plugin "%1$s" está actualmente activo. %2$s', 'wp-agency-toolkit' ),
						'<strong>' . esc_html( $info['name'] ) . '</strong>',
						esc_html( $info['reason'] )
					);
					?>
				</p>
				<p style="margin: 0; font-size: 12px;">
					<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-secondary" style="height: 28px; line-height: 26px; font-size: 12px;">
						<?php esc_html_e( 'Gestionar Plugins', 'wp-agency-toolkit' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Inyecta el listener de captura de errores JS en tiempo de ejecución solo para usuarios con permisos de administración.
	 */
	public function inject_js_error_listener() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<script type="text/javascript">
			(function() {
				var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
				var nonce = '<?php echo esc_js( wp_create_nonce( 'wpat_js_error_nonce' ) ); ?>';
				var reported = {};

				function reportError(msg, file, line, col) {
					var key = msg + ':' + file + ':' + line;
					if (reported[key]) return;
					reported[key] = true;

					if (!file || file.indexOf('chrome-extension') !== -1 || file.indexOf('moz-extension') !== -1) return;

					try {
						var xhr = new XMLHttpRequest();
						xhr.open('POST', ajaxUrl, true);
						xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
						xhr.send('action=wpat_log_js_error&security=' + encodeURIComponent(nonce) +
							'&msg=' + encodeURIComponent(msg || 'Unknown error') +
							'&file=' + encodeURIComponent(file || window.location.href) +
							'&line=' + encodeURIComponent(line || 0) +
							'&url=' + encodeURIComponent(window.location.href));
					} catch(e) {}
				}

				window.addEventListener('error', function(e) {
					if (e.error || e.message) {
						reportError(e.message || (e.error ? e.error.message : ''), e.filename, e.lineno, e.colno);
					}
				});

				window.addEventListener('unhandledrejection', function(e) {
					var reason = e.reason;
					var msg = typeof reason === 'string' ? reason : (reason && reason.message ? reason.message : 'Unhandled Promise Rejection');
					reportError('Promise Rejection: ' + msg, window.location.href, 0, 0);
				});
			})();
		</script>
		<?php
	}

	/**
	 * Registra el error JS capturado en un transitorio ligero de diagnóstico.
	 */
	public function ajax_log_js_error() {
		check_ajax_referer( 'wpat_js_error_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$msg  = isset( $_POST['msg'] ) ? sanitize_text_field( wp_unslash( $_POST['msg'] ) ) : '';
		$file = isset( $_POST['file'] ) ? esc_url_raw( wp_unslash( $_POST['file'] ) ) : '';
		$line = isset( $_POST['line'] ) ? absint( $_POST['line'] ) : 0;
		$url  = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( empty( $msg ) ) {
			wp_send_json_error();
		}

		$errors = (array) get_transient( 'wpat_js_console_errors' );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}

		// Limitar a los últimos 15 errores únicos
		$hash = md5( $msg . $file . $line );
		$errors[ $hash ] = array(
			'msg'  => $msg,
			'file' => $file,
			'line' => $line,
			'url'  => $url,
			'time' => current_time( 'mysql' ),
		);

		if ( count( $errors ) > 15 ) {
			$errors = array_slice( $errors, -15, 15, true );
		}

		set_transient( 'wpat_js_console_errors', $errors, 3 * DAY_IN_SECONDS );
		wp_send_json_success();
	}

	/**
	 * Limpia el historial de errores JS registrados.
	 */
	public function ajax_clear_js_errors() {
		check_ajax_referer( 'wpat_js_error_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		delete_transient( 'wpat_js_console_errors' );
		wp_send_json_success();
	}

	/**
	 * Retorna los errores JS registrados en el transitorio de diagnóstico.
	 *
	 * @return array
	 */
	public static function get_logged_js_errors() {
		$errors = get_transient( 'wpat_js_console_errors' );
		return is_array( $errors ) ? array_reverse( $errors, true ) : array();
	}
}



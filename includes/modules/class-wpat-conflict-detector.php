<?php
/**
 * Módulo: Detector de Incompatibilidades y Conflictos - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Conflict_Detector {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Conflict_Detector
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
		add_action( 'admin_notices', array( $this, 'display_conflict_notices' ) );
	}

	/**
	 * Retorna la lista de plugins conflictivos conocidos y su razón.
	 *
	 * @return array
	 */
	public static function get_conflict_rules() {
		return array(
			'code-snippets/code-snippets.php' => array(
				'name'             => 'Code Snippets',
				'reason_active'    => 'Code Snippets está actualmente activo. WP Agency Toolkit incluye su propio ejecutor nativo de Snippets. Mantener ambos activos puede provocar llamadas duplicadas y errores de redeterminación de funciones.',
				'reason_installed' => 'Code Snippets está instalado en el servidor (aunque desactivado). Si algún snippet o código personalizado referencia funciones propias de Code Snippets (como code_snippets()), WordPress lanzará un error crítico al no encontrarlas. Se recomienda eliminar el plugin si usas la suite nativa.',
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'insert-headers-and-footers/ihaf.php' => array(
				'name'             => 'WPCode (Insert Headers and Footers)',
				'reason_active'    => 'WPCode está activo. WP Agency Toolkit integra inyección nativa de scripts en cabecera y pie de página en el panel de Integraciones.',
				'reason_installed' => 'WPCode está instalado. Desinstálalo si ya utilizas las herramientas de inyección de scripts de WP Agency Toolkit para mantener el sitio limpio.',
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'duplicate-post/duplicate-post.php' => array(
				'name'             => 'Yoast Duplicate Post',
				'reason_active'    => 'Yoast Duplicate Post está activo. WP Agency Toolkit incluye un duplicador nativo ultraligero de entradas y páginas.',
				'reason_installed' => 'Yoast Duplicate Post está instalado en el servidor. Puedes desinstalarlo de forma segura ya que dispones del duplicador nativo de la suite.',
				'action_link'      => admin_url( 'plugins.php' ),
			),
			'duplicate-page/duplicatepage.php' => array(
				'name'             => 'Duplicate Page',
				'reason_active'    => 'Duplicate Page está activo. WP Agency Toolkit incluye un duplicador nativo ultraligero de entradas y páginas.',
				'reason_installed' => 'Duplicate Page está instalado en el servidor. Se recomienda desinstalarlo para liberar espacio y reducir sobrecarga.',
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
	 * Muestra avisos globales en el panel de administración únicamente cuando hay plugins conflictivos ACTIVOS.
	 */
	public function display_conflict_notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$conflicts = self::get_active_conflicts();
		if ( empty( $conflicts ) ) {
			return;
		}

		foreach ( $conflicts as $plugin_file => $info ) {
			// Notificación superior emergente únicamente si el plugin conflictivo está ACTIVO
			if ( 'active' !== $info['status'] ) {
				continue;
			}

			$dismiss_key = 'wpat_dismiss_conflict_' . md5( $plugin_file );
			if ( get_transient( $dismiss_key ) ) {
				continue;
			}
			?>
			<div class="notice notice-warning is-dismissible wpat-conflict-notice" style="border-left-color: #f59e0b; padding: 12px 16px;">
				<p style="margin: 0 0 6px 0; font-size: 14px; font-weight: 600; color: #b45309;">
					⚠️ <strong>WP Agency Toolkit: Conflicto o Duplicidad detectada</strong>
				</p>
				<p style="margin: 0 0 8px 0; font-size: 13px; color: #374151;">
					El plugin <strong><?php echo esc_html( $info['name'] ); ?></strong> está actualmente activo. <?php echo esc_html( $info['reason'] ); ?>
				</p>
				<p style="margin: 0; font-size: 12px;">
					<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-secondary" style="height: 28px; line-height: 26px; font-size: 12px;">
						Gestionar Plugins
					</a>
				</p>
			</div>
			<?php
		}
	}
}


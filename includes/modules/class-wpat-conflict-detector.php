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
				'name'        => 'Code Snippets',
				'reason'      => 'WP Agency Toolkit incluye un módulo nativo de Snippets PHP de alto rendimiento. Mantener ambos plugins activos puede provocar colisiones de código y ralentización.',
				'action_link' => admin_url( 'plugins.php' ),
			),
			'insert-headers-and-footers/ihaf.php' => array(
				'name'        => 'WPCode (Insert Headers and Footers)',
				'reason'      => 'WP Agency Toolkit integra nativamente la inyección de scripts en cabecera y pie de página en el panel de Integraciones.',
				'action_link' => admin_url( 'plugins.php' ),
			),
			'duplicate-post/duplicate-post.php' => array(
				'name'        => 'Yoast Duplicate Post',
				'reason'      => 'WP Agency Toolkit cuenta con un duplicador nativo de entradas y páginas sin sobrecarga.',
				'action_link' => admin_url( 'plugins.php' ),
			),
			'duplicate-page/duplicatepage.php' => array(
				'name'        => 'Duplicate Page',
				'reason'      => 'WP Agency Toolkit cuenta con un duplicador nativo de entradas y páginas sin sobrecarga.',
				'action_link' => admin_url( 'plugins.php' ),
			),
		);
	}

	/**
	 * Obtiene los conflictos activos actualmente en la instalación.
	 *
	 * @return array
	 */
	public static function get_active_conflicts() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$rules     = self::get_conflict_rules();
		$conflicts = array();

		foreach ( $rules as $plugin_file => $data ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$conflicts[ $plugin_file ] = $data;
			}
		}

		return $conflicts;
	}

	/**
	 * Muestra avisos globales en el panel de administración cuando se detectan plugins conflictivos.
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
			// Permite descartar el aviso usando transientes o sesiones simples de WP si se desea
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

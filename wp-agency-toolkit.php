<?php
/**
 * Plugin Name: WP Agency Toolkit
 * Description: Un plugin modular, ligero y de alto rendimiento que unifica utilidades esenciales de administraciÃƒÆ’Ã‚Â³n, seguridad, WooCommerce, rendimiento y optimizaciÃƒÆ’Ã‚Â³n de medios.
 * Version:     3.2.8
 * Author:      19webs
 * License:     GPLv2 or later
 * Text Domain: wp-agency-toolkit
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes del plugin
define( 'WPAT_VERSION', '3.2.8' );
define( 'WPAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPAT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Clase principal WPAT_Main.
 */
class WPAT_Main {

	/**
	 * Instancia ÃƒÆ’Ã‚Âºnica de la clase.
	 *
	 * @var WPAT_Main
	 */
	private static $instance = null;

	/**
	 * Listado de mÃƒÆ’Ã‚Â³dulos y sus archivos/clases.
	 *
	 * @var array
	 */
	private $modules = array(
		'login-customizer' => array(
			'file'  => 'includes/modules/class-wpat-login-customizer.php',
			'class' => 'WPAT_Login_Customizer',
		),
		'hide-login'       => array(
			'file'  => 'includes/modules/class-wpat-hide-login.php',
			'class' => 'WPAT_Hide_Login',
		),
		'ssl-fixer'        => array(
			'file'  => 'includes/modules/class-wpat-ssl-fixer.php',
			'class' => 'WPAT_SSL_Fixer',
		),
		'woo-dni'          => array(
			'file'  => 'includes/modules/class-wpat-woo-dni.php',
			'class' => 'WPAT_Woo_Dni',
		),
		'woo-catalog'      => array(
			'file'  => 'includes/modules/class-wpat-woo-catalog.php',
			'class' => 'WPAT_Woo_Catalog',
		),
		'woo-zoom'         => array(
			'file'  => 'includes/modules/class-wpat-woo-zoom.php',
			'class' => 'WPAT_Woo_Zoom',
		),
		'duplicator'       => array(
			'file'  => 'includes/modules/class-wpat-duplicator.php',
			'class' => 'WPAT_Duplicator',
		),
		'snippets'         => array(
			'file'  => 'includes/modules/class-wpat-snippets.php',
			'class' => 'WPAT_Snippets',
		),
		'performance'      => array(
			'file'  => 'includes/modules/class-wpat-performance.php',
			'class' => 'WPAT_Performance',
		),
		'svg-support'      => array(
			'file'  => 'includes/modules/class-wpat-svg-support.php',
			'class' => 'WPAT_SVG_Support',
		),
		'image-optimizer'  => array(
			'file'  => 'includes/modules/class-wpat-image-optimizer.php',
			'class' => 'WPAT_Image_Optimizer',
		),
		'seo'              => array(
			'file'  => 'includes/modules/class-wpat-seo.php',
			'class' => 'WPAT_SEO',
		),
		'disable-comments' => array(
			'file'  => 'includes/modules/class-wpat-disable-comments.php',
			'class' => 'WPAT_Disable_Comments',
		),
		'security-hardening' => array(
			'file'  => 'includes/modules/class-wpat-security-hardening.php',
			'class' => 'WPAT_Security_Hardening',
		),
		'smtp' => array(
			'file'  => 'includes/modules/class-wpat-smtp.php',
			'class' => 'WPAT_SMTP',
		),
		'hide_admin_bar' => array(
			'file'  => 'includes/modules/class-wpat-admin-bar-restriction.php',
			'class' => 'WPAT_Admin_Bar_Restriction',
		),
		'dashboard_cleaner' => array(
			'file'  => 'includes/modules/class-wpat-dashboard-cleaner.php',
			'class' => 'WPAT_Dashboard_Cleaner',
		),
		'bot-blocker' => array(
			'file'  => 'includes/modules/class-wpat-bot-blocker.php',
			'class' => 'WPAT_Bot_Blocker',
		),
		'integrations' => array(
			'file'  => 'includes/modules/class-wpat-integrations.php',
			'class' => 'WPAT_Integrations',
		),
		'initial-setup' => array(
			'file'  => 'includes/modules/class-wpat-initial-setup.php',
			'class' => 'WPAT_Initial_Setup',
		),
	);

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Main
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado para evitar instanciaciÃƒÆ’Ã‚Â³n externa.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Inicializa el plugin, cargando mÃƒÆ’Ã‚Â³dulos y el panel de administraciÃƒÆ’Ã‚Â³n.
	 */
	public function init() {
		// Cargar configuraciÃƒÆ’Ã‚Â³n de forma centralizada
		$settings = $this->get_settings();

		// Cargar condicionalmente cada mÃƒÆ’Ã‚Â³dulo si estÃƒÆ’Ã‚Â¡ activo (ON)
		foreach ( $this->modules as $id => $module ) {
			if ( isset( $settings[ $id ] ) && '1' === $settings[ $id ] ) {
				$file_path = WPAT_PATH . $module['file'];
				if ( file_exists( $file_path ) ) {
					require_once $file_path;
					if ( class_exists( $module['class'] ) ) {
						if ( method_exists( $module['class'], 'get_instance' ) ) {
							call_user_func( array( $module['class'], 'get_instance' ) );
						}
					}
				}
			}
		}

		// Cargar Envato Importer de forma incondicional (siempre activo)
		$envato_importer_path = WPAT_PATH . 'includes/modules/class-wpat-envato-importer.php';
		if ( file_exists( $envato_importer_path ) ) {
			require_once $envato_importer_path;
		}

		// Cargar actualizador automÃƒÆ’Ã‚Â¡tico conectado con GitHub
		$updater_path = WPAT_PATH . 'includes/class-wpat-updater.php';
		if ( file_exists( $updater_path ) ) {
			require_once $updater_path;
		}

		// Cargar panel de administraciÃƒÆ’Ã‚Â³n en el back-office
		if ( is_admin() ) {
			require_once WPAT_PATH . 'includes/class-wpat-admin.php';
			WPAT_Admin::get_instance();
		}
	}

	/**
	 * Obtiene los ajustes centralizados del plugin, con valores por defecto.
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			// MÃƒÆ’Ã‚Â³dulos (0 = desactivado, 1 = activo)
			'login-customizer'          => '0',
			'hide-login'                => '0',
			'ssl-fixer'                 => '0',
			'woo-dni'                   => '0',
			'woo-catalog'               => '0',
			'woo_catalog_hide_price'    => '0',
			'woo_catalog_price_text'    => '',
			'woo_catalog_hide_cart'     => '0',
			'woo_catalog_wa_enable'     => '0',
			'woo_catalog_wa_phone'      => '',
			'woo_catalog_wa_message'    => 'Estoy interesado en el producto {product_title} ({product_url}). Ãƒâ€šÃ‚Â¿CÃƒÆ’Ã‚Â³mo podrÃƒÆ’Ã‚Â­a comprarlo?',
			'woo_catalog_form_enable'   => '0',
			'woo_catalog_form_email'    => '',
			'woo-zoom'                  => '0',
			'duplicator'                => '0',
			'snippets'                  => '0',
			'performance'               => '0',
			'svg-support'               => '0',
			'image-optimizer'           => '0',
			'seo'                       => '0',
			'disable-comments'          => '0',
			'ssl_redirect_method'       => 'php',
			'envato-importer'           => '0',
			'security-hardening'        => '0',
			'sec_disable_file_edit'     => '0',
			'sec_block_uploads_php'     => '0',
			'sec_hide_wp_version'       => '0',
			'sec_generic_login_errors'  => '0',
			'sec_disable_indexes'       => '0',
			'sec_disable_user_enum'     => '0',
			'sec_disable_xmlrpc'        => '0',
			'sec_block_admin_user'      => '0',

			// Opciones de SMTP
			'smtp'                      => '0',
			'smtp_host'                 => '',
			'smtp_port'                 => '25',
			'smtp_secure'               => 'none',
			'smtp_insecure'             => '0',
			'smtp_auth'                 => '0',
			'smtp_username'             => '',
			'smtp_password'             => '',
			'smtp_from_email'           => '',
			'smtp_from_name'            => '',

			// Opciones de Login Customizer
			'login_style'               => 'default',
			'login_logo'                => '',
			'login_bg_image'            => '',
			'login_bg_type'             => 'image',
			'login_bg_color'            => '#f0f0f0',
			'login_accent_color'        => '#2563eb',
			'login_hide_languages'      => '0',
			'login_footer_text'         => '',
			'admin_footer_text'         => '',
			'hide_admin_bar'            => '0',
			'dashboard_cleaner'         => '0',
			'dashboard_welcome_title'   => 'Soporte y GestiÃƒÆ’Ã‚Â³n',
			'dashboard_welcome_text'    => 'Bienvenido al panel de administraciÃƒÆ’Ã‚Â³n de tu sitio web.',

			// Opciones del Bloqueador de Bots
			'bot_blocker'               => '0',
			'bot_blocker_limit'         => '15',
			'bot_blocker_timeframe'     => '300',
			'bot_blocker_duration'      => '24',
			'bot_blocker_whitelist'     => '',

			// Opciones de Integraciones
			'integrations'                => '1',
			'google_search_console_code'  => '',
			'google_analytics_id'         => '',

			// Opciones de ConfiguraciÃƒÆ’Ã‚Â³n Inicial
			'initial-setup'               => '1',

			// Opciones de Hide Login
			'hide_login_slug'           => 'acceso',
			'hide_login_redirect'       => 'home', // 'home' o '404'
			'hide_login_limit_attempts' => '0',
			'hide_login_max_attempts'   => '3',
			'hide_login_lockout'        => '120', // en segundos
			'hide_login_captcha'        => '0',


			// Opciones de WooCommerce Gallery Zoom
			'woo_zoom_disable_zoom'     => '0',
			'woo_zoom_disable_lightbox' => '0',
			'woo_zoom_disable_slider'   => '0',

			// Opciones de Deshabilitar Comentarios
			'disable_comments_global'   => '1',
			'disable_comments_posts'    => '0',
			'disable_comments_pages'    => '0',
			'disable_comments_media'    => '0',

			// Opciones de visibilidad de las tarjetas del escritorio
			'db_card_seo'               => '1',
			'db_card_pages'             => '1',
			'db_card_posts'             => '1',
			'db_card_plugins'           => '1',
			'db_card_themes'            => '1',
			'db_card_users'             => '1',
			'db_card_db'                => '1',
			'db_card_tools'             => '1',
			'db_card_smtp'              => '1',
			'db_card_jet'               => '1',
			'db_card_woo'               => '1',
			'db_card_media'             => '1',
		);

		$saved = get_option( 'wpat_settings', array() );

		return wp_parse_args( $saved, $defaults );
	}
}

// Arrancar plugin
WPAT_Main::get_instance();

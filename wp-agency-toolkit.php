<?php
/**
 * Plugin Name: WP Agency Toolkit
 * Description: Un plugin modular, ligero y de alto rendimiento que unifica utilidades esenciales de administración, seguridad, WooCommerce, rendimiento y optimización de medios.
 * Version:     4.3.79
 * Author:      19webs
 * License:     GPLv2 or later
 * Text Domain: wp-agency-toolkit
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes del plugin
define( 'WPAT_VERSION', '4.3.79' );
define( 'WPAT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPAT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Clase principal WPAT_Main.
 */
class WPAT_Main {

	/**
	 * Instancia ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºnica de la clase.
	 *
	 * @var WPAT_Main
	 */
	private static $instance = null;

	/**
	 * Listado de mÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³dulos y sus archivos/clases.
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
		'sitemap-xml'      => array(
			'file'  => 'includes/modules/class-wpat-sitemap-xml.php',
			'class' => 'WPAT_Sitemap_XML',
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
		'post-csv-importer' => array(
			'file'  => 'includes/modules/class-wpat-post-csv-importer.php',
			'class' => 'WPAT_Post_CSV_Importer',
		),
		'anti-spam' => array(
			'file'  => 'includes/modules/class-wpat-anti-spam.php',
			'class' => 'WPAT_Anti_Spam',
		),
		'whatsapp' => array(
			'file'  => 'includes/modules/class-wpat-whatsapp.php',
			'class' => 'WPAT_WhatsApp',
		),
		'reading-progress' => array(
			'file'  => 'includes/modules/class-wpat-reading-progress.php',
			'class' => 'WPAT_Reading_Progress',
		),
		'conflict-detector' => array(
			'file'  => 'includes/modules/class-wpat-conflict-detector.php',
			'class' => 'WPAT_Conflict_Detector',
		),
		'accessibility' => array(
			'file'  => 'includes/modules/class-wpat-accessibility.php',
			'class' => 'WPAT_Accessibility',
		),
		'silent-skin' => array(
			'file'  => 'includes/modules/class-wpat-silent-skin.php',
			'class' => 'WPAT_Silent_Skin',
		),
		'woo-checkout-editor' => array(
			'file'  => 'includes/modules/class-wpat-woo-checkout-editor.php',
			'class' => 'WPAT_Woo_Checkout_Editor',
		),
		'woo-extra-options' => array(
			'file'  => 'includes/modules/class-wpat-woo-extra-options.php',
			'class' => 'WPAT_Woo_Extra_Options',
		),
		'woo-variation-swatches' => array(
			'file'  => 'includes/modules/class-wpat-woo-variation-swatches.php',
			'class' => 'WPAT_Woo_Variation_Swatches',
		),
		'woo-pdf-invoices' => array(
			'file'  => 'includes/modules/class-wpat-woo-pdf-invoices.php',
			'class' => 'WPAT_Woo_PDF_Invoices',
		),
		'woo-live-search' => array(
			'file'  => 'includes/modules/class-wpat-woo-live-search.php',
			'class' => 'WPAT_Woo_Live_Search',
		),
		'woo-facets' => array(
			'file'  => 'includes/modules/class-wpat-woo-facets.php',
			'class' => 'WPAT_Woo_Facets',
		),
		'woo-checkout-designer' => array(
			'file'  => 'includes/modules/class-wpat-woo-checkout-designer.php',
			'class' => 'WPAT_Woo_Checkout_Designer',
		),
		'woo-sale-badges' => array(
			'file'  => 'includes/modules/class-wpat-woo-sale-badges.php',
			'class' => 'WPAT_Woo_Sale_Badges',
		),
		'woo-address-autofill' => array(
			'file'  => 'includes/modules/class-wpat-woo-address-autofill.php',
			'class' => 'WPAT_Woo_Address_Autofill',
		),
		'woo-email-designer' => array(
			'file'  => 'includes/modules/class-wpat-woo-email-designer.php',
			'class' => 'WPAT_Woo_Email_Designer',
		),
		'woo-promotions' => array(
			'file'  => 'includes/modules/class-wpat-woo-promotions.php',
			'class' => 'WPAT_Woo_Promotions',
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
	 * Constructor privado para evitar instanciaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n externa.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Inicializa el plugin, cargando mÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³dulos y el panel de administraciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n.
	 */
	public function init() {
		// Cargar configuraciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n de forma centralizada
		$settings = $this->get_settings();

		// Cargar condicionalmente cada módulo si está activo (ON) o si tiene reglas activas
		foreach ( $this->modules as $id => $module ) {
			$is_module_on = ( isset( $settings[ $id ] ) && '1' === (string) $settings[ $id ] );
			if ( ! $is_module_on && ( 'woo-promotions' === $id || 'woo_promotions' === $id ) && ! empty( $settings['woo_promotions_rules'] ) ) {
				$is_module_on = true;
			}
			if ( $is_module_on ) {
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

		// Cargar actualizador automÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡tico conectado con GitHub
		$updater_path = WPAT_PATH . 'includes/class-wpat-updater.php';
		if ( file_exists( $updater_path ) ) {
			require_once $updater_path;
		}

		// Cargar panel de administraciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n en el back-office
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
			// MÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³dulos (0 = desactivado, 1 = activo)
			'login-customizer'          => '0',
			'hide-login'                => '0',
			'ssl-fixer'                 => '0',
			'woo-dni'                   => '0',
			'woo-catalog'               => '0',
			'woo-address-autofill'      => '0',
			'woo_address_autofill_city' => '1',
			'woo_catalog_hide_price'    => '0',
			'woo_catalog_price_text'    => '',
			'woo_catalog_hide_cart'     => '0',
			'woo_catalog_wa_enable'     => '0',
			'woo_catalog_wa_phone'      => '',
			'woo_catalog_wa_message'    => 'Estoy interesado en el producto {product_title} ({product_url}). ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿CÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³mo podrÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­a comprarlo?',
			'woo_catalog_form_enable'   => '0',
			'woo_catalog_form_email'    => '',
			'woo-zoom'                  => '0',
			'duplicator'                => '0',
			'duplicator_title_suffix'   => ' (Copia)',
			'duplicator_post_status'    => 'draft',
			'duplicator_redirect_to'    => 'edit',
			'duplicator_post_types'     => array( 'post', 'page', 'product' ),
			'duplicator_show_admin_bar' => '1',
			'duplicator_copy_taxonomies'=> '1',
			'duplicator_copy_meta'      => '1',
			'duplicator_copy_author'    => 'current',
			'duplicator_copy_date'      => 'current',
			'snippets'                  => '0',
			'performance'               => '0',
			'perf_disable_emojis'       => '1',
			'perf_cleanup_head'         => '1',
			'perf_heartbeat_control'    => 'slow',
			'perf_disable_jquery_migrate' => '1',
			'perf_disable_wc_cart_fragments' => '1',
			'perf_limit_revisions'      => '5',
			'perf_autosave_interval'    => '180',
			'perf_disable_dashicons'    => '1',
			'perf_disable_embeds'       => '1',
			'svg-support'               => '0',
			'svg_admin_only'            => '1',
			'svg_sanitize_strict'       => '1',
			'svg_generate_dimensions'   => '1',
			'image-optimizer'               => '0',
			'image_optimizer_quality'       => '82',
			'image_optimizer_max_width'     => '1920',
			'image_optimizer_max_height'    => '1920',
			'image_optimizer_auto_convert'   => '1',
			'image_optimizer_keep_original'  => '0',
			'seo'                       => '0',
			'sitemap-xml'                => '0',
			'sitemap_include_posts'      => '1',
			'sitemap_include_pages'      => '1',
			'sitemap_include_products'   => '1',
			'sitemap_include_taxonomies' => '1',
			'sitemap_include_images'     => '1',
			'sitemap_exclude_urls'       => '',
			'disable-comments'          => '0',
			'disable_comments_global'   => '1',
			'disable_comments_posts'    => '1',
			'disable_comments_pages'    => '1',
			'disable_comments_media'    => '1',
			'disable_comments_cpts'     => array(),
			'disable_comments_keep_reviews' => '1',
			'ssl_redirect_method'       => 'php',
			'ssl_fix_mixed_content'     => '1',
			'ssl_enable_hsts'           => '1',
			'ssl_enable_csp'            => '1',
			'ssl_proxy_fix'             => '1',
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
			'sec_security_headers'      => '0',

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
			'smtp_force_from'           => '0',
			'smtp_reply_to'             => '',
			'smtp_log_enabled'          => '1',

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
			'dashboard_welcome_title'   => 'Soporte y Gestión',
			'dashboard_welcome_text'    => 'Bienvenido al panel de administración de tu sitio web.',

			// Opciones del Bloqueador de Bots
			'bot_blocker'               => '0',
			'bot_blocker_limit'         => '15',
			'bot_blocker_timeframe'     => '300',
			'bot_blocker_duration'      => '24',
			'bot_blocker_whitelist'     => '',

			// Opciones de Anti-Spam
			'anti-spam'                 => '1',
			'antispam_honeypot'          => '1',
			'antispam_time_check'       => '1',
			'antispam_max_links'        => '2',
			'antispam_block_cyrillic'   => '1',
			'antispam_keywords'         => '',

			// Opciones de Importador CSV
			'post-csv-importer'         => '1',

			// Opciones de Integraciones & Scripts
			'integrations'                => '1',
			'integrations_exclude_admins' => '0',
			'google_search_console_code'  => '',
			'bing_verification_code'      => '',
			'pinterest_verification_code' => '',
			'google_analytics_id'         => '',
			'gtm_container_id'            => '',
			'facebook_pixel_id'           => '',
			'clarity_project_id'          => '',
			'tiktok_pixel_id'             => '',
			'pinterest_tag_id'            => '',
			'header_custom_scripts'       => '',
			'body_custom_scripts'         => '',
			'footer_custom_scripts'       => '',
			'google_drive_token'          => '',
			'google_drive_folder'         => '',
			'dropbox_token'               => '',
			'onedrive_token'              => '',

			// Opciones de WhatsApp
			'whatsapp'                    => '0',
			'whatsapp_enabled'            => '0',
			'whatsapp_phone'              => '',
			'whatsapp_message'            => '¡Hola! Quisiera más información sobre {title}.',
			'whatsapp_position'           => 'bottom-right',
			'whatsapp_offset_x'           => '20',
			'whatsapp_offset_y'           => '20',
			'whatsapp_bg_color'           => '#25D366',
			'whatsapp_tooltip'            => '',
			'whatsapp_agents'             => '',
			'whatsapp_devices'            => 'all',
			'whatsapp_pulse'              => '1',
			'whatsapp_track_events'       => '1',
			'whatsapp_popup_title'        => 'Contacta con nuestro equipo',
			'whatsapp_popup_subtitle'     => 'Selecciona un asesor para iniciar el chat',
			'whatsapp_work_hours'         => '',
			'whatsapp_hide_on_checkout'   => '0',

			// Opciones de Barra y Tiempo de Lectura
			'reading-progress'            => '0',
			'reading_bar_enabled'         => '1',
			'reading_bar_color'           => '#2563eb',
			'reading_bar_color_end'       => '',
			'reading_bar_height'          => '4',
			'reading_bar_position'        => 'top',
			'reading_bar_scope'           => 'article',
			'reading_bar_post_types'      => array( 'post' ),
			'reading_time_enabled'        => '1',
			'reading_time_wpm'            => '200',
			'reading_time_style'          => 'pill',
			'reading_time_label'          => 'Tiempo estimado de lectura: {time} min',

			// Opciones de Herramientas de Accesibilidad
			'accessibility'                   => '0',
			'accessibility_enabled'           => '0',
			'accessibility_position'          => 'bottom-left',
			'accessibility_offset_y'          => '25',
			'accessibility_bg_color'          => '#2563eb',
			'accessibility_text_zoom'         => '1',
			'accessibility_reading_guide'     => '1',
			'accessibility_big_cursor'        => '1',
			'accessibility_stop_animations'   => '1',
			'accessibility_text_spacing'      => '1',
			'accessibility_dyslexic_font'     => '1',
			'accessibility_grayscale'         => '1',
			'accessibility_high_contrast'     => '1',
			'accessibility_negative_contrast' => '1',
			'accessibility_light_bg'          => '1',
			'accessibility_underline_links'   => '1',
			'accessibility_readable_font'     => '1',

			// Opciones de Editor de Campos de Checkout (WooCommerce)
			'woo-checkout-designer'              => '0',
			'woo_checkout_designer_layout'         => 'wpat-classic',
			'woo_checkout_designer_mobile_summary' => '1',
			'woo_checkout_designer_trust_badges'   => '1',
			'woo_checkout_designer_trust_text'     => 'Garantía de Devolución • Pago 100% Seguro • Envío Gratis',
			'woo_checkout_designer_email_fix'      => '1',
			'woo_checkout_designer_product_thumbs' => '1',
			'woo-checkout-editor'             => '0',
			'checkout_editor_enabled'         => '0',
			'checkout_nif_enabled'            => '1',
			'checkout_nif_required'           => '1',
			'checkout_nif_position'           => 'after_names',
			'checkout_disabled_fields'        => array(),
			'checkout_custom_fields'          => array(),

			// Opciones de Opciones Extra y Swatches de Producto (WooCommerce)
			'woo-extra-options'               => '0',
			'extra_options_enabled'           => '0',
			'extra_options_rules'             => array(),

			// Opciones de Swatches de Variación de Producto (WooCommerce)
			'woo-variation-swatches'          => '0',
			'variation_swatches_enabled'      => '0',
			'variation_swatches_shape'        => 'round',
			'variation_swatches_colors'       => '',

			// Opciones de Facturas PDF y Albaranes Automáticos (WooCommerce)
			'woo-pdf-invoices'                => '0',
			'pdf_invoices_enabled'            => '0',
			'pdf_company_name'                => get_bloginfo( 'name' ),
			'pdf_company_nif'                 => '',
			'pdf_company_address'             => '',
			'pdf_company_footer'              => 'Gracias por su compra.',
			'pdf_invoice_prefix'              => 'FACT-' . date( 'Y' ) . '-',
			'pdf_invoice_next_num'            => '1',

			// Opciones de Buscador AJAX en Vivo (WooCommerce)
			'woo-live-search'                 => '0',
			'live_search_enabled'             => '0',
			'live_search_max_results'         => '5',
			'live_search_show_thumb'          => '1',
			'live_search_show_price'          => '1',
			'live_search_show_stock'          => '1',
			'live_search_show_meta'           => 'sku',
			'live_search_auto_replace'        => '0',
			'live_search_placeholder'         => 'Buscar productos por nombre, SKU o categoría...',

			// Opciones de Promociones Dinámicas y Descuentos (WooCommerce)
			'woo-promotions'                  => '0',
			'woo_promotions'                  => '0',
			'woo_promotions_rules'            => array(),

			// Opciones de Filtro por Facetas AJAX (WooCommerce)
			'woo-facets'                      => '0',
			'facets_enabled'                  => '0',
			'facets_config'                   => array( 'sort', 'price', 'category', 'stock', 'rating' ),

			// Opciones de Badges y Etiquetas de Oferta High-Impact (WooCommerce)
			'woo-sale-badges'                 => '0',
			'woo_sale_badge_shape'            => 'soft',
			'woo_sale_badge_text_type'        => 'custom',
			'woo_sale_badge_custom_text'      => '¡OFERTA!',
			'woo_sale_badge_bg_color'         => '#ef4444',
			'woo_sale_badge_txt_color'        => '#ffffff',
			'woo_sale_badge_font_size'        => '13',
			'woo_sale_badge_position'         => 'top-left',

			// Opciones de Detector de Incompatibilidades
			'conflict-detector'           => '1',

			// Opciones de ConfiguraciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n Inicial
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
			'db_card_support'           => '1',
			'dashboard_support_email'   => '',

			// Opciones de Ocultar Huella WPAT / Marca Blanca
			'silent-skin'                      => '0',
			'white_label_mode'                 => 'rename',
			'white_label_plugin_name'          => 'Herramientas del Sitio Web',
			'white_label_plugin_desc'          => 'Módulo de optimización, seguridad y utilidades para la administración de este sitio.',
			'white_label_author'               => 'Equipo de Desarrollo Web',
			'white_label_author_url'           => '',
			'white_label_menu_title'           => 'Herramientas Web',
			'white_label_menu_icon'            => 'dashicons-admin-generic',
			'white_label_hide_from_non_admins' => '1',
			'white_label_allowed_users'        => '',
			'white_label_prevent_deactivation' => '0',

			// Opciones de Restringir Barra & Acceso Admin
			'hide_admin_bar'                     => '0',
			'admin_bar_hide_mode'                => 'all_except_admin',
			'admin_bar_hidden_roles'             => array( 'subscriber', 'customer' ),
			'admin_access_restrict_enabled'      => '1',
			'admin_access_restricted_roles'      => array( 'subscriber', 'customer' ),
			'admin_access_redirect_to'           => 'home',
			'admin_access_custom_redirect_url'   => '',
			'admin_access_excluded_users'        => '',
			'admin_bar_remove_wp_logo'           => '1',
			'admin_bar_remove_comments'          => '0',
			'admin_bar_remove_new_content'       => '0',
			'admin_bar_remove_updates'           => '0',
			'admin_bar_remove_customize'         => '0',
		);

		$saved = get_option( 'wpat_settings', array() );

		return wp_parse_args( $saved, $defaults );
	}
}

// Arrancar plugin
WPAT_Main::get_instance();

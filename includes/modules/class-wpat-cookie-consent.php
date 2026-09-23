<?php
/**
 * Módulo: Banner de Cookies, RGPD y Google Consent Mode v2 - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Cookie_Consent {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Cookie_Consent|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Cookie_Consent
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
		// Inyección de Google Consent Mode v2 antes de cualquier tag en wp_head
		add_action( 'wp_head', array( $this, 'inject_google_consent_mode_default' ), 1 );

		// Encolar assets frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Inyectar HTML del banner y modal en el footer
		add_action( 'wp_footer', array( $this, 'render_cookie_banner_html' ) );

		// Registrar shortcode de tabla de cookies para la política legal
		add_shortcode( 'wpat_cookie_table', array( $this, 'render_cookie_table_shortcode' ) );

		// Endpoint AJAX para el escáner inteligente de cookies
		add_action( 'wp_ajax_wpat_scan_cookies', array( $this, 'ajax_scan_cookies' ) );
	}

	/**
	 * Diccionario maestro de firmas de cookies conocidas.
	 *
	 * @return array
	 */
	public static function get_known_cookies_database() {
		return array(
			// 1. Necesarias / Técnicas
			'wpat_cookie_consent' => array(
				'name'        => 'wpat_cookie_consent',
				'provider'    => 'WP Agency Toolkit',
				'purpose'     => 'Almacena el estado de consentimiento de cookies del usuario.',
				'expiry'      => '6 meses',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'wordpress_logged_in_*' => array(
				'name'        => 'wordpress_logged_in_*',
				'provider'    => 'WordPress',
				'purpose'     => 'Mantiene la sesión de usuario autenticado en el sitio.',
				'expiry'      => 'Sesión / 15 días',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'wordpress_sec_*' => array(
				'name'        => 'wordpress_sec_*',
				'provider'    => 'WordPress',
				'purpose'     => 'Protección de seguridad para usuarios registrados.',
				'expiry'      => 'Sesión / 15 días',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'wordpress_test_cookie' => array(
				'name'        => 'wordpress_test_cookie',
				'provider'    => 'WordPress',
				'purpose'     => 'Comprueba si el navegador tiene habilitado el soporte de cookies.',
				'expiry'      => 'Sesión',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'woocommerce_cart_hash' => array(
				'name'        => 'woocommerce_cart_hash',
				'provider'    => 'WooCommerce',
				'purpose'     => 'Identifica los cambios y productos en el carrito de compra.',
				'expiry'      => 'Sesión',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'woocommerce_items_in_cart' => array(
				'name'        => 'woocommerce_items_in_cart',
				'provider'    => 'WooCommerce',
				'purpose'     => 'Ayuda a WooCommerce a determinar si hay artículos en la cesta.',
				'expiry'      => 'Sesión',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'wp_woocommerce_session_*' => array(
				'name'        => 'wp_woocommerce_session_*',
				'provider'    => 'WooCommerce',
				'purpose'     => 'Contiene un código único para cada cliente para almacenar los datos del carrito en la base de datos.',
				'expiry'      => '2 días',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'__stripe_mid' => array(
				'name'        => '__stripe_mid',
				'provider'    => 'Stripe',
				'purpose'     => 'Prevención de fraude en procesamiento seguro de pagos.',
				'expiry'      => '1 año',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'__stripe_sid' => array(
				'name'        => '__stripe_sid',
				'provider'    => 'Stripe',
				'purpose'     => 'Identificador de sesión para procesamiento seguro de pagos.',
				'expiry'      => '30 minutos',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),
			'__cf_bm' => array(
				'name'        => '__cf_bm',
				'provider'    => 'Cloudflare',
				'purpose'     => 'Gestión de bots y protección contra tráfico abusivo.',
				'expiry'      => '30 minutos',
				'category'    => 'necessary',
				'cat_label'   => 'Necesaria',
			),

			// 2. Analíticas / Estadísticas
			'_ga' => array(
				'name'        => '_ga',
				'provider'    => 'Google Analytics (GA4)',
				'purpose'     => 'Distingue usuarios únicos asignando un identificador aleatorio para medir estadísticas de visitas.',
				'expiry'      => '2 años',
				'category'    => 'analytics',
				'cat_label'   => 'Analítica',
			),
			'_ga_*' => array(
				'name'        => '_ga_*',
				'provider'    => 'Google Analytics (GA4)',
				'purpose'     => 'Mantiene el estado de la sesión y recopila métricas de tráfico en GA4.',
				'expiry'      => '2 años',
				'category'    => 'analytics',
				'cat_label'   => 'Analítica',
			),
			'_gid' => array(
				'name'        => '_gid',
				'provider'    => 'Google Analytics',
				'purpose'     => 'Almacena y cuenta las páginas vistas para generar informes estadísticos.',
				'expiry'      => '24 horas',
				'category'    => 'analytics',
				'cat_label'   => 'Analítica',
			),
			'_gat' => array(
				'name'        => '_gat',
				'provider'    => 'Google Analytics',
				'purpose'     => 'Limita el porcentaje de solicitudes para optimizar el rendimiento del servicio.',
				'expiry'      => '1 minuto',
				'category'    => 'analytics',
				'cat_label'   => 'Analítica',
			),
			'_clck' => array(
				'name'        => '_clck',
				'provider'    => 'Microsoft Clarity',
				'purpose'     => 'Almacena un identificador de usuario único para mapas de calor y grabaciones.',
				'expiry'      => '1 año',
				'category'    => 'analytics',
				'cat_label'   => 'Analítica',
			),
			'_clsk' => array(
				'name'        => '_clsk',
				'provider'    => 'Microsoft Clarity',
				'purpose'     => 'Conecta múltiples vistas de página en una sola sesión de grabación.',
				'expiry'      => '1 día',
				'category'    => 'analytics',
				'cat_label'   => 'Analítica',
			),
			'_hjSessionUser_*' => array(
				'name'        => '_hjSessionUser_*',
				'provider'    => 'Hotjar',
				'purpose'     => 'Almacena un ID de usuario único para mapas de calor.',
				'expiry'      => '1 año',
				'category'    => 'analytics',
				'cat_label'   => 'Analítica',
			),

			// 3. Marketing / Publicidad
			'_fbp' => array(
				'name'        => '_fbp',
				'provider'    => 'Meta / Facebook Pixel',
				'purpose'     => 'Rastrea conversiones publicitarias y mide la efectividad de los anuncios en Facebook e Instagram.',
				'expiry'      => '3 meses',
				'category'    => 'marketing',
				'cat_label'   => 'Marketing',
			),
			'_fbc' => array(
				'name'        => '_fbc',
				'provider'    => 'Meta / Facebook Pixel',
				'purpose'     => 'Registra el último clic del usuario desde un anuncio en Meta.',
				'expiry'      => '2 años',
				'category'    => 'marketing',
				'cat_label'   => 'Marketing',
			),
			'_gcl_au' => array(
				'name'        => '_gcl_au',
				'provider'    => 'Google Ads / AdSense',
				'purpose'     => 'Almacena y rastrea conversiones de campañas de Google Ads.',
				'expiry'      => '3 meses',
				'category'    => 'marketing',
				'cat_label'   => 'Marketing',
			),
			'IDE' => array(
				'name'        => 'IDE',
				'provider'    => 'Google DoubleClick',
				'purpose'     => 'Registra e informa sobre las acciones del usuario después de ver anuncios.',
				'expiry'      => '1 año',
				'category'    => 'marketing',
				'cat_label'   => 'Marketing',
			),
			'_ttp' => array(
				'name'        => '_ttp',
				'provider'    => 'TikTok Pixel',
				'purpose'     => 'Mide el rendimiento de las campañas publicitarias en TikTok.',
				'expiry'      => '13 meses',
				'category'    => 'marketing',
				'cat_label'   => 'Marketing',
			),

			// 4. Preferencias / Funcionales
			'wpat_theme_mode' => array(
				'name'        => 'wpat_theme_mode',
				'provider'    => 'WP Agency Toolkit',
				'purpose'     => 'Guarda la preferencia visual de Modo Oscuro o Claro.',
				'expiry'      => '1 año',
				'category'    => 'preferences',
				'cat_label'   => 'Preferencia',
			),
			'wp-settings-*' => array(
				'name'        => 'wp-settings-*',
				'provider'    => 'WordPress',
				'purpose'     => 'Personaliza la interfaz y configuración visual del usuario.',
				'expiry'      => '1 año',
				'category'    => 'preferences',
				'cat_label'   => 'Preferencia',
			),
		);
	}

	/**
	 * Inyecta el script de Google Consent Mode v2 al inicio de wp_head.
	 */
	public function inject_google_consent_mode_default() {
		if ( is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$is_enabled = ( isset( $settings['cookie-consent'] ) && '1' === (string) $settings['cookie-consent'] );
		$enable_gcm = ( ! isset( $settings['cookie_consent_gcm'] ) || '1' === (string) $settings['cookie_consent_gcm'] );

		if ( ! $is_enabled || ! $enable_gcm ) {
			return;
		}

		?>
<!-- WP Agency Toolkit: Google Consent Mode v2 Default -->
<script data-wpat-cookie-consent="true">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
	'analytics_storage': 'denied',
	'ad_storage': 'denied',
	'ad_user_data': 'denied',
	'ad_personalization': 'denied',
	'functionality_storage': 'denied',
	'personalization_storage': 'denied',
	'security_storage': 'granted',
	'wait_for_update': 500
});
</script>
		<?php
	}

	/**
	 * Encola los scripts y estilos necesarios en el frontend.
	 */
	public function enqueue_frontend_assets() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$is_enabled = ( isset( $settings['cookie-consent'] ) && '1' === (string) $settings['cookie-consent'] );

		if ( ! $is_enabled ) {
			return;
		}

		wp_enqueue_style( 'wpat-cookie-consent-css', WPAT_URL . 'assets/css/wpat-cookie-consent.css', array(), WPAT_VERSION );
		wp_enqueue_script( 'wpat-cookie-consent-js', WPAT_URL . 'assets/js/wpat-cookie-consent.js', array(), WPAT_VERSION, false );

		$config = array(
			'consentMode'   => ( ! isset( $settings['cookie_consent_gcm'] ) || '1' === (string) $settings['cookie_consent_gcm'] ),
			'revokeBadge'   => ( ! isset( $settings['cookie_consent_revoke_badge'] ) || '1' === (string) $settings['cookie_consent_revoke_badge'] ),
			'policyVersion' => isset( $settings['cookie_consent_version'] ) && ! empty( $settings['cookie_consent_version'] ) ? (string) $settings['cookie_consent_version'] : '1.0',
		);

		wp_localize_script( 'wpat-cookie-consent-js', 'wpatCookieConfig', $config );
	}

	/**
	 * Renderiza el HTML del banner y el modal de preferencias en el footer.
	 */
	public function render_cookie_banner_html() {
		if ( is_admin() ) {
			return;
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$is_enabled = ( isset( $settings['cookie-consent'] ) && '1' === (string) $settings['cookie-consent'] );

		if ( ! $is_enabled ) {
			return;
		}

		// Configuración de Textos y Estilos
		$layout        = isset( $settings['cookie_consent_layout'] ) ? $settings['cookie_consent_layout'] : 'layout-bar';
		$title         = isset( $settings['cookie_consent_title'] ) && ! empty( $settings['cookie_consent_title'] ) ? $settings['cookie_consent_title'] : 'Gestionar Consentimiento de Cookies';
		$text          = isset( $settings['cookie_consent_text'] ) && ! empty( $settings['cookie_consent_text'] ) ? $settings['cookie_consent_text'] : 'Utilizamos cookies propias y de terceros para fines analíticos y para mostrarle publicidad personalizada según su navegación. Puede aceptar todas las cookies, rechazarlas o configurar sus preferencias.';
		$btn_accept    = isset( $settings['cookie_consent_btn_accept'] ) && ! empty( $settings['cookie_consent_btn_accept'] ) ? $settings['cookie_consent_btn_accept'] : 'Aceptar Todas';
		$btn_reject    = isset( $settings['cookie_consent_btn_reject'] ) && ! empty( $settings['cookie_consent_btn_reject'] ) ? $settings['cookie_consent_btn_reject'] : 'Rechazar Todas';
		$btn_settings  = isset( $settings['cookie_consent_btn_settings'] ) && ! empty( $settings['cookie_consent_btn_settings'] ) ? $settings['cookie_consent_btn_settings'] : 'Configurar Preferencias';
		
		// Enlaces Legales (Cookies, Privacidad, Aviso Legal)
		$cookie_policy_url  = isset( $settings['cookie_consent_cookie_policy_url'] ) && ! empty( $settings['cookie_consent_cookie_policy_url'] ) ? $settings['cookie_consent_cookie_policy_url'] : ( isset( $settings['cookie_consent_policy_url'] ) ? $settings['cookie_consent_policy_url'] : '' );
		$privacy_policy_url = isset( $settings['cookie_consent_privacy_policy_url'] ) && ! empty( $settings['cookie_consent_privacy_policy_url'] ) ? $settings['cookie_consent_privacy_policy_url'] : get_privacy_policy_url();
		$legal_notice_url   = isset( $settings['cookie_consent_legal_notice_url'] ) ? $settings['cookie_consent_legal_notice_url'] : '';

		$legal_links = array();
		if ( ! empty( $cookie_policy_url ) ) {
			$legal_links[] = '<a href="' . esc_url( $cookie_policy_url ) . '" target="_blank" rel="noopener noreferrer" class="wpat-cookie-legal-link">Política de Cookies</a>';
		}
		if ( ! empty( $privacy_policy_url ) ) {
			$legal_links[] = '<a href="' . esc_url( $privacy_policy_url ) . '" target="_blank" rel="noopener noreferrer" class="wpat-cookie-legal-link">Política de Privacidad</a>';
		}
		if ( ! empty( $legal_notice_url ) ) {
			$legal_links[] = '<a href="' . esc_url( $legal_notice_url ) . '" target="_blank" rel="noopener noreferrer" class="wpat-cookie-legal-link">Aviso Legal</a>';
		}

		$revoke_badge  = ( ! isset( $settings['cookie_consent_revoke_badge'] ) || '1' === (string) $settings['cookie_consent_revoke_badge'] );
		$badge_pos     = isset( $settings['cookie_consent_revoke_badge_pos'] ) && 'bottom-right' === $settings['cookie_consent_revoke_badge_pos'] ? 'bottom-right' : 'bottom-left';

		// Colores personalizados
		$bg_color          = isset( $settings['cookie_consent_bg_color'] ) && ! empty( $settings['cookie_consent_bg_color'] ) ? $settings['cookie_consent_bg_color'] : '#1e293b';
		$text_color        = isset( $settings['cookie_consent_text_color'] ) && ! empty( $settings['cookie_consent_text_color'] ) ? $settings['cookie_consent_text_color'] : '#f8fafc';
		$btn_accept_bg     = isset( $settings['cookie_consent_btn_accept_bg'] ) && ! empty( $settings['cookie_consent_btn_accept_bg'] ) ? $settings['cookie_consent_btn_accept_bg'] : '#2563eb';
		$btn_accept_text   = isset( $settings['cookie_consent_btn_accept_text'] ) && ! empty( $settings['cookie_consent_btn_accept_text'] ) ? $settings['cookie_consent_btn_accept_text'] : '#ffffff';
		$btn_reject_bg     = isset( $settings['cookie_consent_btn_reject_bg'] ) && ! empty( $settings['cookie_consent_btn_reject_bg'] ) ? $settings['cookie_consent_btn_reject_bg'] : '#475569';
		$btn_reject_text   = isset( $settings['cookie_consent_btn_reject_text'] ) && ! empty( $settings['cookie_consent_btn_reject_text'] ) ? $settings['cookie_consent_btn_reject_text'] : '#ffffff';
		?>
		<style>
		:root {
			--wpat-cookie-bg: <?php echo esc_attr( $bg_color ); ?>;
			--wpat-cookie-text: <?php echo esc_attr( $text_color ); ?>;
			--wpat-cookie-btn-accept-bg: <?php echo esc_attr( $btn_accept_bg ); ?>;
			--wpat-cookie-btn-accept-text: <?php echo esc_attr( $btn_accept_text ); ?>;
			--wpat-cookie-btn-reject-bg: <?php echo esc_attr( $btn_reject_bg ); ?>;
			--wpat-cookie-btn-reject-text: <?php echo esc_attr( $btn_reject_text ); ?>;
		}
		</style>

		<!-- BANNER PRINCIPAL DE COOKIES -->
		<div id="wpat_cookie_banner" class="<?php echo esc_attr( $layout ); ?>" role="dialog" aria-labelledby="wpat_cookie_title" aria-describedby="wpat_cookie_desc">
			<div class="wpat-cookie-inner">
				<div class="wpat-cookie-content">
					<div id="wpat_cookie_title" class="wpat-cookie-title"><?php echo esc_html( $title ); ?></div>
					<div id="wpat_cookie_desc" class="wpat-cookie-text">
						<?php echo wp_kses_post( $text ); ?>
						<?php if ( ! empty( $legal_links ) ) : ?>
							<div class="wpat-cookie-legal-links-wrap" style="margin-top: 8px;">
								<?php echo implode( '<span class="wpat-cookie-links-sep"> • </span>', $legal_links ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="wpat-cookie-actions">
					<button type="button" class="wpat-cookie-btn wpat-cookie-btn-settings"><?php echo esc_html( $btn_settings ); ?></button>
					<button type="button" class="wpat-cookie-btn wpat-cookie-btn-reject"><?php echo esc_html( $btn_reject ); ?></button>
					<button type="button" class="wpat-cookie-btn wpat-cookie-btn-accept"><?php echo esc_html( $btn_accept ); ?></button>
				</div>
			</div>
		</div>

		<?php if ( 'layout-modal' === $layout ) : ?>
			<div id="wpat_cookie_backdrop"></div>
		<?php endif; ?>

		<!-- MODAL DE PREFERENCIAS Y CATEGORÍAS -->
		<div id="wpat_cookie_modal" role="dialog" aria-modal="true" aria-labelledby="wpat_modal_title">
			<div class="wpat-cookie-modal-dialog">
				<div class="wpat-cookie-modal-header">
					<h3 id="wpat_modal_title">Preferencias de Consentimiento</h3>
					<button type="button" class="wpat-cookie-modal-close" aria-label="Cerrar">&times;</button>
				</div>

				<div class="wpat-cookie-modal-body">
					<!-- Categoría 1: Necesarias -->
					<div class="wpat-cookie-cat-item">
						<div class="wpat-cookie-cat-header">
							<div class="wpat-cookie-cat-title">
								<span>🔒 Cookies Técnicas / Necesarias</span>
								<span style="font-size: 11px; font-weight: 700; color: #16a34a; background: #dcfce7; padding: 2px 8px; border-radius: 10px;">Siempre Activas</span>
							</div>
							<label class="wpat-cookie-switch">
								<input type="checkbox" checked disabled>
								<span class="wpat-cookie-slider"></span>
							</label>
						</div>
						<p class="wpat-cookie-cat-desc">Son imprescindibles para el funcionamiento técnico de la web (inicio de sesión, cesta de compra, prevención de fraude y seguridad). No almacenan información personal identificable.</p>
					</div>

					<!-- Categoría 2: Analíticas -->
					<div class="wpat-cookie-cat-item">
						<div class="wpat-cookie-cat-header">
							<div class="wpat-cookie-cat-title">
								<span>📊 Cookies Analíticas / Estadísticas</span>
							</div>
							<label class="wpat-cookie-switch">
								<input type="checkbox" id="wpat_cookie_cat_analytics">
								<span class="wpat-cookie-slider"></span>
							</label>
						</div>
						<p class="wpat-cookie-cat-desc">Permiten cuantificar el número de usuarios y realizar la medición y análisis estadístico de la utilización del sitio para mejorar la oferta de productos y servicios.</p>
					</div>

					<!-- Categoría 3: Marketing -->
					<div class="wpat-cookie-cat-item">
						<div class="wpat-cookie-cat-header">
							<div class="wpat-cookie-cat-title">
								<span>🎯 Cookies de Marketing y Publicidad</span>
							</div>
							<label class="wpat-cookie-switch">
								<input type="checkbox" id="wpat_cookie_cat_marketing">
								<span class="wpat-cookie-slider"></span>
							</label>
						</div>
						<p class="wpat-cookie-cat-desc">Almacenan información del comportamiento de los usuarios obtenida a través de la observación continuada de sus hábitos de navegación para mostrar publicidad personalizada.</p>
					</div>

					<!-- Categoría 4: Preferencias -->
					<div class="wpat-cookie-cat-item">
						<div class="wpat-cookie-cat-header">
							<div class="wpat-cookie-cat-title">
								<span>⚙️ Cookies de Personalización / Preferencias</span>
							</div>
							<label class="wpat-cookie-switch">
								<input type="checkbox" id="wpat_cookie_cat_preferences">
								<span class="wpat-cookie-slider"></span>
							</label>
						</div>
						<p class="wpat-cookie-cat-desc">Permiten recordar información para que el usuario acceda al servicio con determinadas características personalizadas (idioma, aspecto visual o región).</p>
					</div>

					<?php if ( ! empty( $legal_links ) ) : ?>
						<div class="wpat-cookie-modal-links" style="margin-top: 15px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 12px; text-align: center; color: #64748b;">
							Consulta más detalles en nuestra <?php echo implode( ', ', $legal_links ); ?>.
						</div>
					<?php endif; ?>
				</div>

				<div class="wpat-cookie-modal-footer">
					<button type="button" class="wpat-cookie-btn wpat-cookie-btn-reject">Rechazar Todas</button>
					<button type="button" class="wpat-cookie-btn wpat-cookie-btn-settings" id="wpat_cookie_save_selection_btn">Guardar Preferencias</button>
					<button type="button" class="wpat-cookie-btn wpat-cookie-btn-accept">Aceptar Todas</button>
				</div>
			</div>
		</div>

		<?php if ( $revoke_badge ) : ?>
			<!-- BOTÓN FLOTANTE PARA REVOCAR / REABRIR CONSENTIMIENTO -->
			<button type="button" id="wpat_cookie_revoke_badge" class="<?php echo esc_attr( $badge_pos ); ?>" style="display: none;" title="Gestionar Preferencias de Cookies" aria-label="Gestionar Preferencias de Cookies">
				<span>🍪</span> <span class="wpat-cookie-badge-label">Cookies</span>
			</button>
		<?php endif; ?>
		<?php
	}

	/**
	 * Shortcode [wpat_cookie_table] para mostrar la tabla de cookies en la Política de Cookies.
	 */
	public function render_cookie_table_shortcode( $atts ) {
		$cookies_db = self::get_detected_cookies_list();

		ob_start();
		?>
		<div class="wpat-cookie-table-wrapper">
			<table class="wpat-cookie-table">
				<thead>
					<tr>
						<th>Cookie</th>
						<th>Proveedor</th>
						<th>Finalidad</th>
						<th>Caducidad</th>
						<th>Tipo</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cookies_db as $c ) : ?>
						<tr>
							<td><code><?php echo esc_html( $c['name'] ); ?></code></td>
							<td><strong><?php echo esc_html( $c['provider'] ); ?></strong></td>
							<td><?php echo esc_html( $c['purpose'] ); ?></td>
							<td><?php echo esc_html( $c['expiry'] ); ?></td>
							<td><span class="wpat-cookie-badge-cat <?php echo esc_attr( $c['category'] ); ?>"><?php echo esc_html( $c['cat_label'] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Obtiene la lista de cookies detectadas en la web.
	 *
	 * @return array
	 */
	public static function get_detected_cookies_list() {
		$saved_scan = get_option( 'wpat_detected_cookies', array() );
		if ( ! empty( $saved_scan ) && is_array( $saved_scan ) ) {
			return $saved_scan;
		}

		// Si no hay escaneo previo, devolver las cookies base esenciales
		$all_db   = self::get_known_cookies_database();
		$defaults = array();
		$essential_keys = array( 'wpat_cookie_consent', 'wordpress_logged_in_*', 'wordpress_sec_*', 'wordpress_test_cookie', 'wpat_theme_mode' );

		if ( class_exists( 'WooCommerce' ) ) {
			$essential_keys[] = 'woocommerce_cart_hash';
			$essential_keys[] = 'woocommerce_items_in_cart';
			$essential_keys[] = 'wp_woocommerce_session_*';
		}

		foreach ( $essential_keys as $k ) {
			if ( isset( $all_db[ $k ] ) ) {
				$defaults[] = $all_db[ $k ];
			}
		}

		return $defaults;
	}

	/**
	 * Endpoint AJAX para ejecutar el escáner inteligente de cookies del sitio.
	 */
	public function ajax_scan_cookies() {
		check_ajax_referer( 'wpat_cookie_consent_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$all_db = self::get_known_cookies_database();
		$detected = array();

		// 1. Cookies base siempre presentes en WordPress
		$detected['wpat_cookie_consent']  = $all_db['wpat_cookie_consent'];
		$detected['wordpress_logged_in_*'] = $all_db['wordpress_logged_in_*'];
		$detected['wordpress_sec_*']       = $all_db['wordpress_sec_*'];
		$detected['wordpress_test_cookie'] = $all_db['wordpress_test_cookie'];
		$detected['wpat_theme_mode']      = $all_db['wpat_theme_mode'];

		// 2. Comprobar WooCommerce
		if ( class_exists( 'WooCommerce' ) || post_type_exists( 'product' ) ) {
			$detected['woocommerce_cart_hash']     = $all_db['woocommerce_cart_hash'];
			$detected['woocommerce_items_in_cart'] = $all_db['woocommerce_items_in_cart'];
			$detected['wp_woocommerce_session_*']  = $all_db['wp_woocommerce_session_*'];
		}

		// 3. Comprobar pasarelas de pago (Stripe)
		if ( class_exists( 'WC_Gateway_Stripe' ) || defined( 'STRIPE_VERSION' ) ) {
			$detected['__stripe_mid'] = $all_db['__stripe_mid'];
			$detected['__stripe_sid'] = $all_db['__stripe_sid'];
		}

		// 4. Comprobar integraciones activas en WP Agency Toolkit
		$settings = WPAT_Main::get_instance()->get_settings();
		$integrations_head = isset( $settings['integrations_header_code'] ) ? $settings['integrations_header_code'] : '';
		$integrations_body = isset( $settings['integrations_body_code'] ) ? $settings['integrations_body_code'] : '';
		$integrations_all  = $integrations_head . ' ' . $integrations_body;

		// 5. Escanear el HTML de la página de inicio
		$home_url  = home_url( '/' );
		$response  = wp_remote_get( $home_url, array( 'timeout' => 8, 'sslverify' => false ) );
		$home_html = '';
		if ( ! is_wp_error( $response ) ) {
			$home_html = wp_remote_retrieve_body( $response );
		}

		$combined_source = $integrations_all . ' ' . $home_html;

		// Detección por firmas
		if ( stripos( $combined_source, 'googletagmanager.com' ) !== false || stripos( $combined_source, 'google-analytics.com' ) !== false || stripos( $combined_source, 'gtag(' ) !== false || stripos( $combined_source, 'G-' ) !== false ) {
			$detected['_ga']   = $all_db['_ga'];
			$detected['_ga_*'] = $all_db['_ga_*'];
			$detected['_gid']  = $all_db['_gid'];
		}

		if ( stripos( $combined_source, 'googleadservices.com' ) !== false || stripos( $combined_source, 'AW-' ) !== false ) {
			$detected['_gcl_au'] = $all_db['_gcl_au'];
			$detected['IDE']     = $all_db['IDE'];
		}

		if ( stripos( $combined_source, 'connect.facebook.net' ) !== false || stripos( $combined_source, 'fbq(' ) !== false ) {
			$detected['_fbp'] = $all_db['_fbp'];
			$detected['_fbc'] = $all_db['_fbc'];
		}

		if ( stripos( $combined_source, 'clarity.ms' ) !== false ) {
			$detected['_clck'] = $all_db['_clck'];
			$detected['_clsk'] = $all_db['_clsk'];
		}

		if ( stripos( $combined_source, 'hotjar.com' ) !== false || stripos( $combined_source, '_hjSettings' ) !== false ) {
			$detected['_hjSessionUser_*'] = $all_db['_hjSessionUser_*'];
		}

		if ( stripos( $combined_source, 'analytics.tiktok.com' ) !== false || stripos( $combined_source, 'ttq.load' ) !== false ) {
			$detected['_ttp'] = $all_db['_ttp'];
		}

		$detected_list = array_values( $detected );
		update_option( 'wpat_detected_cookies', $detected_list );

		wp_send_json_success(
			array(
				'message' => 'Escaneo completado. Se han detectado ' . count( $detected_list ) . ' cookies en el sitio.',
				'cookies' => $detected_list,
			)
		);
	}
}

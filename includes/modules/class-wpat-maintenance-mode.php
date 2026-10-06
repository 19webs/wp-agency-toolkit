<?php
/**
 * Módulo: Modo Mantenimiento & Próximamente (Coming Soon) - WP Agency Toolkit
 *
 * Permite poner el sitio web en modo mantenimiento o próximamente con control
 * de acceso por IP, por Rol de usuario, URL secreta de bypass con cookie, y
 * cabeceras HTTP 503 Service Unavailable / 200 OK para SEO.
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Maintenance_Mode {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_Maintenance_Mode|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia única.
	 *
	 * @return WPAT_Maintenance_Mode
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
		// Interceptar el renderizado frontend
		add_action( 'template_redirect', array( $this, 'intercept_request' ), 1 );

		// Añadir aviso en la barra superior de administración de WordPress
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_indicator' ), 100 );
	}

	/**
	 * Obtiene la IP real del visitante actual.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$ip = '';
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$raw_ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Si contiene varias IPs separadas por coma (proxies)
				if ( strpos( $raw_ip, ',' ) !== false ) {
					$parts = explode( ',', $raw_ip );
					$raw_ip = trim( $parts[0] );
				}
				if ( filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
					$ip = $raw_ip;
					break;
				}
			}
		}

		return ! empty( $ip ) ? $ip : '127.0.0.1';
	}

	/**
	 * Comprueba si la petición actual debe saltarse el modo mantenimiento.
	 *
	 * @param array $settings Ajustes del plugin.
	 * @return bool
	 */
	public function should_bypass( $settings ) {
		// 1. Siempre permitir accesos de sistema/admin/cron/ajax/REST
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return true;
		}

		// Permitir acceso a wp-login.php y páginas de autenticación
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false !== strpos( $request_uri, 'wp-login.php' ) || false !== strpos( $request_uri, 'wp-register.php' ) ) {
			return true;
		}

		// 2. Comprobar si el usuario logueado tiene un rol permitido
		if ( is_user_logged_in() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}
			$allowed_roles = isset( $settings['wpat_maintenance_roles_allowed'] ) && is_array( $settings['wpat_maintenance_roles_allowed'] )
				? $settings['wpat_maintenance_roles_allowed']
				: array( 'administrator' );

			$current_user = wp_get_current_user();
			if ( ! empty( $current_user->roles ) ) {
				foreach ( $current_user->roles as $role ) {
					if ( in_array( $role, $allowed_roles, true ) ) {
						return true;
					}
				}
			}
		}

		// 3. Comprobar Token secreto en Query String (ej: ?wpat_bypass_maintenance=TOKEN)
		$bypass_token = isset( $settings['wpat_maintenance_bypass_token'] ) ? trim( $settings['wpat_maintenance_bypass_token'] ) : '';
		if ( ! empty( $bypass_token ) ) {
			if ( isset( $_GET['wpat_bypass_maintenance'] ) && sanitize_text_field( wp_unslash( $_GET['wpat_bypass_maintenance'] ) ) === $bypass_token ) {
				// Guardar cookie para navegación continua (7 días)
				setcookie( 'wpat_maintenance_bypass', md5( $bypass_token . NONCE_SALT ), time() + ( 7 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
				return true;
			}

			// Comprobar Cookie existente
			if ( isset( $_COOKIE['wpat_maintenance_bypass'] ) && sanitize_text_field( wp_unslash( $_COOKIE['wpat_maintenance_bypass'] ) ) === md5( $bypass_token . NONCE_SALT ) ) {
				return true;
			}
		}

		// 4. Comprobar lista blanca de IPs
		$whitelist_raw = isset( $settings['wpat_maintenance_ip_whitelist'] ) ? $settings['wpat_maintenance_ip_whitelist'] : '';
		if ( ! empty( $whitelist_raw ) ) {
			$allowed_ips = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $whitelist_raw ) ) );
			$client_ip   = self::get_client_ip();

			foreach ( $allowed_ips as $allowed_ip ) {
				if ( $client_ip === $allowed_ip ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Intercepta la petición y muestra la plantilla de mantenimiento si está activo.
	 */
	public function intercept_request() {
		$settings = get_option( 'wpat_settings', array() );
		$is_active = isset( $settings['maintenance-mode'] ) && '1' === (string) $settings['maintenance-mode'];

		if ( ! $is_active ) {
			return;
		}

		if ( $this->should_bypass( $settings ) ) {
			return;
		}

		// Configurar cabeceras HTTP (SEO friendly)
		$status_code = isset( $settings['wpat_maintenance_status_code'] ) ? (string) $settings['wpat_maintenance_status_code'] : '503';
		if ( '503' === $status_code ) {
			status_header( 503 );
			nocache_headers();
			header( 'Retry-After: 3600' ); // Indicar a bots que reintenten en 1 hora
		} else {
			status_header( 200 );
			nocache_headers();
		}

		// Renderizar página seleccionada o plantilla nativa
		$page_source = isset( $settings['wpat_maintenance_page_source'] ) ? $settings['wpat_maintenance_page_source'] : 'default';
		$page_id     = isset( $settings['wpat_maintenance_page_id'] ) ? intval( $settings['wpat_maintenance_page_id'] ) : 0;

		if ( 'page' === $page_source && $page_id > 0 ) {
			$page_post = get_post( $page_id );
			if ( $page_post && 'publish' === $page_post->post_status ) {
				// Renderizar la página de WordPress completa seleccionada
				global $wp_query, $post;
				$post = $page_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $post );
				$wp_query->posts = array( $page_post );
				$wp_query->post_count = 1;
				$wp_query->is_page = true;
				$wp_query->is_single = false;
				$wp_query->is_home = false;
				$wp_query->is_front_page = false;

				$template = get_page_template();
				if ( $template && file_exists( $template ) ) {
					include $template;
					exit;
				}
			}
		}

		// Si no se usa página o falla, renderizar la plantilla nativa elegante
		$this->render_default_maintenance_template( $settings );
		exit;
	}

	/**
	 * Renderiza la plantilla HTML de mantenimiento nativa de alta gama.
	 *
	 * @param array $settings Ajustes del plugin.
	 */
	public function render_default_maintenance_template( $settings ) {
		$site_title = get_bloginfo( 'name' );
		$mode_type  = isset( $settings['wpat_maintenance_mode_type'] ) ? $settings['wpat_maintenance_mode_type'] : 'maintenance';
		$title      = ! empty( $settings['wpat_maintenance_title'] ) ? $settings['wpat_maintenance_title'] : ( 'coming_soon' === $mode_type ? 'Próximamente' : 'Sitio Web en Mantenimiento' );
		$message    = ! empty( $settings['wpat_maintenance_message'] ) ? $settings['wpat_maintenance_message'] : 'Estamos realizando tareas de mejora y optimización. Volveremos a estar disponibles muy pronto. Gracias por tu paciencia.';
		$logo_url   = ! empty( $settings['wpat_maintenance_logo'] ) ? $settings['wpat_maintenance_logo'] : '';
		$bg_color   = ! empty( $settings['wpat_maintenance_bg_color'] ) ? $settings['wpat_maintenance_bg_color'] : '#0f172a';
		$contact_email = ! empty( $settings['wpat_maintenance_contact_email'] ) ? $settings['wpat_maintenance_contact_email'] : '';
		$social_links = array(
			'instagram' => ! empty( $settings['wpat_maintenance_social_ig'] ) ? $settings['wpat_maintenance_social_ig'] : '',
			'facebook'  => ! empty( $settings['wpat_maintenance_social_fb'] ) ? $settings['wpat_maintenance_social_fb'] : '',
			'whatsapp'  => ! empty( $settings['wpat_maintenance_social_wa'] ) ? $settings['wpat_maintenance_social_wa'] : '',
		);
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title . ' — ' . $site_title ); ?></title>
			<meta name="robots" content="noindex, nofollow">
			<link rel="preconnect" href="https://fonts.googleapis.com">
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
			<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
			<style>
				*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
				body {
					font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
					background-color: <?php echo esc_attr( $bg_color ); ?>;
					background-image: radial-gradient(circle at 50% 20%, rgba(59, 130, 246, 0.15) 0%, rgba(15, 23, 42, 0) 60%),
					                  radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1) 0%, rgba(15, 23, 42, 0) 50%);
					color: #f8fafc;
					min-height: 100vh;
					display: flex;
					align-items: center;
					justify-content: center;
					padding: 24px;
					text-align: center;
					position: relative;
					overflow-x: hidden;
				}
				.maintenance-container {
					max-width: 620px;
					width: 100%;
					background: rgba(30, 41, 59, 0.75);
					backdrop-filter: blur(16px);
					-webkit-backdrop-filter: blur(16px);
					border: 1px solid rgba(255, 255, 255, 0.1);
					border-radius: 24px;
					padding: 48px 36px;
					box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
					animation: fadeIn 0.8s ease-out;
				}
				@keyframes fadeIn {
					from { opacity: 0; transform: translateY(20px); }
					to { opacity: 1; transform: translateY(0); }
				}
				.badge-pill {
					display: inline-flex;
					align-items: center;
					gap: 8px;
					background: rgba(59, 130, 246, 0.15);
					border: 1px solid rgba(59, 130, 246, 0.3);
					color: #60a5fa;
					font-size: 12px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: 1px;
					padding: 6px 14px;
					border-radius: 9999px;
					margin-bottom: 24px;
				}
				.badge-dot {
					width: 8px;
					height: 8px;
					background-color: #3b82f6;
					border-radius: 50%;
					box-shadow: 0 0 10px #3b82f6;
					animation: pulse 2s infinite;
				}
				@keyframes pulse {
					0%, 100% { opacity: 1; transform: scale(1); }
					50% { opacity: 0.4; transform: scale(1.2); }
				}
				.logo-wrap {
					margin-bottom: 24px;
				}
				.logo-img {
					max-width: 180px;
					max-height: 80px;
					object-fit: contain;
				}
				h1 {
					font-size: 32px;
					font-weight: 800;
					line-height: 1.25;
					color: #ffffff;
					margin-bottom: 16px;
					letter-spacing: -0.5px;
				}
				p.message {
					font-size: 16px;
					color: #94a3b8;
					line-height: 1.65;
					margin-bottom: 32px;
					font-weight: 400;
				}
				.contact-box {
					border-top: 1px solid rgba(255, 255, 255, 0.08);
					padding-top: 24px;
					margin-top: 20px;
					display: flex;
					flex-direction: column;
					align-items: center;
					gap: 12px;
				}
				.contact-link {
					display: inline-flex;
					align-items: center;
					gap: 8px;
					color: #38bdf8;
					text-decoration: none;
					font-weight: 600;
					font-size: 14.5px;
					transition: color 0.2s ease;
				}
				.contact-link:hover {
					color: #7dd3fc;
					text-decoration: underline;
				}
				.social-list {
					display: flex;
					justify-content: center;
					gap: 12px;
					margin-top: 12px;
				}
				.social-btn {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					width: 38px;
					height: 38px;
					background: rgba(255, 255, 255, 0.06);
					border: 1px solid rgba(255, 255, 255, 0.1);
					border-radius: 50%;
					color: #cbd5e1;
					text-decoration: none;
					font-size: 14px;
					transition: all 0.2s ease;
				}
				.social-btn:hover {
					background: #3b82f6;
					color: #ffffff;
					transform: translateY(-2px);
				}
				.footer-copy {
					margin-top: 24px;
					font-size: 12px;
					color: #64748b;
				}
			</style>
		</head>
		<body>
			<div class="maintenance-container">
				<?php if ( ! empty( $logo_url ) ) : ?>
					<div class="logo-wrap">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_title ); ?>" class="logo-img" />
					</div>
				<?php endif; ?>

				<div class="badge-pill">
					<span class="badge-dot"></span>
					<?php echo 'coming_soon' === $mode_type ? 'Próximamente' : 'En Mantenimiento'; ?>
				</div>

				<h1><?php echo esc_html( $title ); ?></h1>
				<p class="message"><?php echo nl2br( esc_html( $message ) ); ?></p>

				<?php if ( ! empty( $contact_email ) || ! empty( array_filter( $social_links ) ) ) : ?>
					<div class="contact-box">
						<?php if ( ! empty( $contact_email ) ) : ?>
							<div>
								<span style="color:#64748b; font-size:13px;">¿Necesitas soporte urgente?</span><br>
								<a href="mailto:<?php echo esc_attr( $contact_email ); ?>" class="contact-link">
									✉️ <?php echo esc_html( $contact_email ); ?>
								</a>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( array_filter( $social_links ) ) ) : ?>
							<div class="social-list">
								<?php if ( ! empty( $social_links['instagram'] ) ) : ?>
									<a href="<?php echo esc_url( $social_links['instagram'] ); ?>" target="_blank" rel="noopener" class="social-btn" title="Instagram">IG</a>
								<?php endif; ?>
								<?php if ( ! empty( $social_links['facebook'] ) ) : ?>
									<a href="<?php echo esc_url( $social_links['facebook'] ); ?>" target="_blank" rel="noopener" class="social-btn" title="Facebook">FB</a>
								<?php endif; ?>
								<?php if ( ! empty( $social_links['whatsapp'] ) ) : ?>
									<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $social_links['whatsapp'] ) ); ?>" target="_blank" rel="noopener" class="social-btn" title="WhatsApp">WA</a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="footer-copy">
					&copy; <?php echo esc_html( date( 'Y' ) . ' ' . $site_title ); ?> &bull; Todos los derechos reservados.
				</div>
			</div>
		</body>
		</html>
		<?php
	}

	/**
	 * Añade un badge indicador en la barra de administración de WordPress si el modo mantenimiento está activo.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Instancia de la barra de administración.
	 */
	public function add_admin_bar_indicator( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( 'wpat_settings', array() );
		$is_active = isset( $settings['maintenance-mode'] ) && '1' === (string) $settings['maintenance-mode'];
		$show_bar  = ! isset( $settings['wpat_maintenance_show_admin_bar'] ) || '1' === (string) $settings['wpat_maintenance_show_admin_bar'];

		if ( ! $is_active || ! $show_bar ) {
			return;
		}

		$mode_type = isset( $settings['wpat_maintenance_mode_type'] ) ? $settings['wpat_maintenance_mode_type'] : 'maintenance';
		$label = 'coming_soon' === $mode_type ? 'PRÓXIMAMENTE ACTIVO' : 'MODO MANTENIMIENTO ACTIVO';

		$wp_admin_bar->add_node( array(
			'id'    => 'wpat-maintenance-mode-indicator',
			'title' => '<span class="ab-icon dashicons dashicons-shield-alt" style="color:#f59e0b; font-size:16px; margin-top:2px;"></span> <span class="ab-label" style="background:#ef4444; color:#ffffff; padding:2px 8px; border-radius:10px; font-weight:700; font-size:11px; letter-spacing:0.3px;">⚠️ ' . esc_html( $label ) . '</span>',
			'href'  => admin_url( 'admin.php?page=wp-agency-toolkit&mod=maintenance-mode' ),
			'meta'  => array(
				'title' => __( 'El sitio web no es visible para el público general. Haz clic para configurar o desactivar.', 'wp-agency-toolkit' ),
			),
		) );
	}
}

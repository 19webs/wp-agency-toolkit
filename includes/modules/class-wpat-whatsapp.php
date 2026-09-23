<?php
/**
 * Módulo: Botón Flotante de WhatsApp - WP Agency Toolkit
 *
 * Botón ultra-ligero y optimizado para WhatsApp con soporte multi-agente,
 * variables dinámicas de contexto ({title}, {url}, {price}, {sku}),
 * control de visibilidad por dispositivo, atajos shortcode y seguimiento de clics.
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_WhatsApp {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_WhatsApp|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_WhatsApp
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
		add_action( 'wp_footer', array( $this, 'render_whatsapp_button' ), 99 );
		add_shortcode( 'wpat_whatsapp_button', array( $this, 'render_shortcode_button' ) );
		add_shortcode( 'wpat_whatsapp', array( $this, 'render_shortcode_button' ) );
	}

	/**
	 * Construye el mensaje dinámico reemplazando variables de contexto.
	 *
	 * @param string $template Plantilla del mensaje.
	 * @return string Mensaje procesado.
	 */
	private function parse_dynamic_message( $template ) {
		if ( empty( $template ) ) {
			$template = '¡Hola! Quisiera más información.';
		}

		$title = is_singular() ? get_the_title() : ( is_front_page() ? get_bloginfo( 'name' ) : wp_title( '', false ) );
		$url   = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
		$price = '';
		$sku   = '';

		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			if ( ! is_object( $product ) ) {
				$product = wc_get_product( get_the_ID() );
			}
			if ( $product ) {
				$price = html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ) );
				$sku   = $product->get_sku();
			}
		}

		$replacements = array(
			'{title}' => trim( wp_strip_all_tags( (string) $title ) ),
			'{url}'   => esc_url_raw( $url ),
			'{price}' => $price,
			'{sku}'   => $sku,
			'{site}'  => get_bloginfo( 'name' ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Renderiza el botón flotante de WhatsApp en el frontend.
	 */
	public function render_whatsapp_button() {
		if ( is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Verificar si el módulo está activo
		if ( empty( $settings['whatsapp'] ) && empty( $settings['whatsapp_enabled'] ) ) {
			return;
		}

		// Ocultar en páginas de finalizar compra / carrito si está configurado
		if ( ! empty( $settings['whatsapp_hide_on_checkout'] ) && '1' === (string) $settings['whatsapp_hide_on_checkout'] ) {
			if ( function_exists( 'is_checkout' ) && is_checkout() ) {
				return;
			}
			if ( function_exists( 'is_cart' ) && is_cart() ) {
				return;
			}
		}

		$phone = ! empty( $settings['whatsapp_phone'] ) ? trim( $settings['whatsapp_phone'] ) : '';
		if ( empty( $phone ) ) {
			return;
		}

		$clean_phone     = preg_replace( '/[^0-9]/', '', $phone );
		$default_msg_raw = ! empty( $settings['whatsapp_message'] ) ? $settings['whatsapp_message'] : '¡Hola! Quisiera más información sobre {title}.';
		$final_msg       = $this->parse_dynamic_message( $default_msg_raw );

		$position     = ! empty( $settings['whatsapp_position'] ) ? $settings['whatsapp_position'] : 'bottom-right';
		$offset_x     = isset( $settings['whatsapp_offset_x'] ) ? absint( $settings['whatsapp_offset_x'] ) : 20;
		$offset_y     = isset( $settings['whatsapp_offset_y'] ) ? absint( $settings['whatsapp_offset_y'] ) : 20;
		$bg_color     = ! empty( $settings['whatsapp_bg_color'] ) ? sanitize_hex_color( $settings['whatsapp_bg_color'] ) : '#25D366';
		$tooltip      = ! empty( $settings['whatsapp_tooltip'] ) ? trim( $settings['whatsapp_tooltip'] ) : '';
		$devices      = ! empty( $settings['whatsapp_devices'] ) ? $settings['whatsapp_devices'] : 'all';
		$has_pulse    = ! isset( $settings['whatsapp_pulse'] ) || '1' === (string) $settings['whatsapp_pulse'];
		$track_events = ! isset( $settings['whatsapp_track_events'] ) || '1' === (string) $settings['whatsapp_track_events'];
		$popup_title  = ! empty( $settings['whatsapp_popup_title'] ) ? $settings['whatsapp_popup_title'] : 'Contacta con nuestro equipo';
		$popup_sub    = ! empty( $settings['whatsapp_popup_subtitle'] ) ? $settings['whatsapp_popup_subtitle'] : 'Selecciona un asesor para iniciar el chat';
		$work_hours   = ! empty( $settings['whatsapp_work_hours'] ) ? trim( $settings['whatsapp_work_hours'] ) : '';
		$agents_raw   = ! empty( $settings['whatsapp_agents'] ) ? trim( $settings['whatsapp_agents'] ) : '';

		// Parsear agentes múltiples si existen
		$agents = array();
		if ( ! empty( $agents_raw ) ) {
			$lines = explode( "\n", str_replace( "\r", '', $agents_raw ) );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}
				$parts = explode( '|', $line );
				if ( count( $parts ) >= 2 ) {
					$a_name  = trim( $parts[0] );
					$a_phone = preg_replace( '/[^0-9]/', '', trim( $parts[1] ) );
					$a_role  = isset( $parts[2] ) ? trim( $parts[2] ) : '';
					if ( ! empty( $a_phone ) ) {
						$agents[] = array(
							'name'  => $a_name,
							'phone' => $a_phone,
							'role'  => $a_role,
						);
					}
				}
			}
		}

		$wa_url   = 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode( $final_msg );
		$is_right = ( 'bottom-left' !== $position );
		$pos_css  = $is_right ? 'right: ' . $offset_x . 'px;' : 'left: ' . $offset_x . 'px;';

		// Control de dispositivos
		$device_css = '';
		if ( 'mobile' === $devices ) {
			$device_css = '@media (min-width: 769px) { .wpat-wa-wrapper { display: none !important; } }';
		} elseif ( 'desktop' === $devices ) {
			$device_css = '@media (max-width: 768px) { .wpat-wa-wrapper { display: none !important; } }';
		}
		?>
		<!-- WP Agency Toolkit - WhatsApp Floating Button -->
		<style>
			<?php echo $device_css; ?>
			.wpat-wa-wrapper {
				position: fixed;
				bottom: <?php echo esc_attr( $offset_y ); ?>px;
				<?php echo $pos_css; ?>
				z-index: 999990;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
			}
			.wpat-wa-btn {
				width: 58px;
				height: 58px;
				background-color: <?php echo esc_attr( $bg_color ); ?>;
				color: #FFF;
				border-radius: 50%;
				text-align: center;
				box-shadow: 0 4px 16px rgba(0,0,0,0.22);
				display: flex;
				align-items: center;
				justify-content: center;
				text-decoration: none;
				transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.25s ease;
				cursor: pointer;
				position: relative;
				border: none;
				outline: none;
			}
			.wpat-wa-btn:hover {
				transform: scale(1.08);
				box-shadow: 0 6px 20px rgba(0,0,0,0.32);
			}
			.wpat-wa-btn svg {
				width: 32px;
				height: 32px;
				fill: #FFFFFF;
			}
			<?php if ( $has_pulse ) : ?>
			.wpat-wa-btn::before {
				content: '';
				position: absolute;
				top: -4px;
				left: -4px;
				right: -4px;
				bottom: -4px;
				border-radius: 50%;
				background: <?php echo esc_attr( $bg_color ); ?>;
				opacity: 0.4;
				z-index: -1;
				animation: wpatWaRipple 2.2s infinite;
			}
			@keyframes wpatWaRipple {
				0% { transform: scale(0.95); opacity: 0.6; }
				70% { transform: scale(1.3); opacity: 0; }
				100% { transform: scale(1.3); opacity: 0; }
			}
			<?php endif; ?>
			.wpat-wa-tooltip {
				position: absolute;
				bottom: 12px;
				<?php echo $is_right ? 'right: 68px;' : 'left: 68px;'; ?>
				background: #FFFFFF;
				color: #1e293b;
				padding: 8px 14px;
				border-radius: 20px;
				font-size: 13px;
				font-weight: 500;
				white-space: nowrap;
				box-shadow: 0 4px 14px rgba(0,0,0,0.12);
				display: flex;
				align-items: center;
				gap: 6px;
				border: 1px solid #f1f5f9;
				pointer-events: auto;
				cursor: pointer;
				transition: opacity 0.2s ease;
			}
			.wpat-wa-popup {
				display: none;
				position: absolute;
				bottom: 70px;
				<?php echo $is_right ? 'right: 0;' : 'left: 0;'; ?>
				width: 320px;
				max-width: 90vw;
				background: #FFFFFF;
				border-radius: 16px;
				box-shadow: 0 12px 35px rgba(0,0,0,0.22);
				overflow: hidden;
				border: 1px solid #e2e8f0;
				z-index: 999999;
				animation: wpatWaPopupIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
			}
			@keyframes wpatWaPopupIn {
				from { opacity: 0; transform: translateY(12px) scale(0.96); }
				to { opacity: 1; transform: translateY(0) scale(1); }
			}
			.wpat-wa-popup.wpat-active { display: block; }
			.wpat-wa-popup-header {
				background: #075e54;
				color: #FFFFFF;
				padding: 16px 18px;
				position: relative;
			}
			.wpat-wa-popup-header h4 {
				margin: 0 0 4px 0;
				font-size: 15px;
				font-weight: 600;
				color: #FFF;
			}
			.wpat-wa-popup-header p {
				margin: 0;
				font-size: 12px;
				opacity: 0.9;
				line-height: 1.4;
			}
			.wpat-wa-popup-close {
				position: absolute;
				top: 14px;
				right: 14px;
				background: rgba(255,255,255,0.2);
				border: none;
				color: #FFF;
				width: 24px;
				height: 24px;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				font-size: 16px;
				line-height: 1;
				transition: background 0.2s;
			}
			.wpat-wa-popup-close:hover { background: rgba(255,255,255,0.35); }
			.wpat-wa-hours-badge {
				display: inline-block;
				margin-top: 6px;
				background: rgba(255,255,255,0.18);
				padding: 2px 8px;
				border-radius: 10px;
				font-size: 10.5px;
				font-weight: 500;
			}
			.wpat-wa-agent-list {
				padding: 12px;
				max-height: 270px;
				overflow-y: auto;
			}
			.wpat-wa-agent-item {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 10px 12px;
				border-radius: 10px;
				text-decoration: none;
				color: #1e293b;
				transition: all 0.2s ease;
				margin-bottom: 6px;
				background: #f8fafc;
				border: 1px solid #f1f5f9;
			}
			.wpat-wa-agent-item:hover {
				background-color: #f0fdf4;
				border-color: #bbf7d0;
				transform: translateX(2px);
			}
			.wpat-wa-agent-info strong {
				display: block;
				font-size: 13px;
				color: #0f172a;
			}
			.wpat-wa-agent-info span {
				font-size: 11px;
				color: #64748b;
			}
			.wpat-wa-agent-icon {
				width: 30px;
				height: 30px;
				background: <?php echo esc_attr( $bg_color ); ?>;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
			}
			.wpat-wa-agent-icon svg {
				width: 16px;
				height: 16px;
				fill: #FFF;
			}
		</style>

		<div class="wpat-wa-wrapper" id="wpatWaWrapper">
			<?php if ( ! empty( $tooltip ) && empty( $agents ) ) : ?>
				<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpat-wa-tooltip wpat-wa-trigger-click" aria-label="<?php echo esc_attr( $tooltip ); ?>">
					<span><?php echo esc_html( $tooltip ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( ! empty( $agents ) ) : ?>
				<!-- Pop-up Multi-agente -->
				<div class="wpat-wa-popup" id="wpatWaPopup" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $popup_title ); ?>">
					<div class="wpat-wa-popup-header">
						<button type="button" class="wpat-wa-popup-close" id="wpatWaCloseBtn" aria-label="Cerrar">&times;</button>
						<h4><?php echo esc_html( $popup_title ); ?></h4>
						<p><?php echo esc_html( $popup_sub ); ?></p>
						<?php if ( ! empty( $work_hours ) ) : ?>
							<span class="wpat-wa-hours-badge"><?php echo esc_html( $work_hours ); ?></span>
						<?php endif; ?>
					</div>
					<div class="wpat-wa-agent-list">
						<!-- Agente principal -->
						<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpat-wa-agent-item wpat-wa-trigger-click" data-agent="Atención Principal">
							<div class="wpat-wa-agent-info">
								<strong>Atención General</strong>
								<span><?php echo esc_html( $phone ); ?></span>
							</div>
							<div class="wpat-wa-agent-icon">
								<svg viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.69 21.25L3 27.5l6.45-1.66A13 13 0 1 0 16 2zm0 23.8a10.74 10.74 0 0 1-5.48-1.5l-.39-.23-4.07 1.05 1.09-3.95-.25-.41A10.78 10.78 0 1 1 16 25.8zM21.9 19.33c-.32-.16-1.92-.95-2.22-1.06s-.52-.16-.73.16-.84 1.06-1.03 1.27-.38.24-.7.08a8.86 8.86 0 0 1-2.61-1.61 9.77 9.77 0 0 1-1.81-2.25c-.19-.32 0-.5.14-.65s.32-.37.48-.56a2.14 2.14 0 0 0 .32-.53.59.59 0 0 0 0-.56c-.08-.16-.73-1.75-1-2.4-.26-.63-.53-.54-.73-.55h-.62a1.2 1.2 0 0 0-.87.41 3.66 3.66 0 0 0-1.14 2.72 6.37 6.37 0 0 0 1.34 3.37 14.54 14.54 0 0 0 5.56 4.92c2.47 1.07 2.97.86 3.51.81a3 3 0 0 0 2-1.4 2.45 2.45 0 0 0 .17-1.4c-.09-.16-.33-.24-.65-.4z"/></svg>
							</div>
						</a>
						<?php foreach ( $agents as $ag ) :
							$ag_msg = $this->parse_dynamic_message( $default_msg_raw );
							$ag_url = 'https://wa.me/' . $ag['phone'] . '?text=' . rawurlencode( $ag_msg );
						?>
							<a href="<?php echo esc_url( $ag_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpat-wa-agent-item wpat-wa-trigger-click" data-agent="<?php echo esc_attr( $ag['name'] ); ?>">
								<div class="wpat-wa-agent-info">
									<strong><?php echo esc_html( $ag['name'] ); ?></strong>
									<?php if ( ! empty( $ag['role'] ) ) : ?>
										<span><?php echo esc_html( $ag['role'] ); ?></span>
									<?php endif; ?>
								</div>
								<div class="wpat-wa-agent-icon">
									<svg viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.69 21.25L3 27.5l6.45-1.66A13 13 0 1 0 16 2zm0 23.8a10.74 10.74 0 0 1-5.48-1.5l-.39-.23-4.07 1.05 1.09-3.95-.25-.41A10.78 10.78 0 1 1 16 25.8zM21.9 19.33c-.32-.16-1.92-.95-2.22-1.06s-.52-.16-.73.16-.84 1.06-1.03 1.27-.38.24-.7.08a8.86 8.86 0 0 1-2.61-1.61 9.77 9.77 0 0 1-1.81-2.25c-.19-.32 0-.5.14-.65s.32-.37.48-.56a2.14 2.14 0 0 0 .32-.53.59.59 0 0 0 0-.56c-.08-.16-.73-1.75-1-2.4-.26-.63-.53-.54-.73-.55h-.62a1.2 1.2 0 0 0-.87.41 3.66 3.66 0 0 0-1.14 2.72 6.37 6.37 0 0 0 1.34 3.37 14.54 14.54 0 0 0 5.56 4.92c2.47 1.07 2.97.86 3.51.81a3 3 0 0 0 2-1.4 2.45 2.45 0 0 0 .17-1.4c-.09-.16-.33-.24-.65-.4z"/></svg>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
				<button type="button" class="wpat-wa-btn" id="wpatWaBtn" aria-label="Abrir panel de WhatsApp" aria-haspopup="true">
					<svg viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.69 21.25L3 27.5l6.45-1.66A13 13 0 1 0 16 2zm0 23.8a10.74 10.74 0 0 1-5.48-1.5l-.39-.23-4.07 1.05 1.09-3.95-.25-.41A10.78 10.78 0 1 1 16 25.8zM21.9 19.33c-.32-.16-1.92-.95-2.22-1.06s-.52-.16-.73.16-.84 1.06-1.03 1.27-.38.24-.7.08a8.86 8.86 0 0 1-2.61-1.61 9.77 9.77 0 0 1-1.81-2.25c-.19-.32 0-.5.14-.65s.32-.37.48-.56a2.14 2.14 0 0 0 .32-.53.59.59 0 0 0 0-.56c-.08-.16-.73-1.75-1-2.4-.26-.63-.53-.54-.73-.55h-.62a1.2 1.2 0 0 0-.87.41 3.66 3.66 0 0 0-1.14 2.72 6.37 6.37 0 0 0 1.34 3.37 14.54 14.54 0 0 0 5.56 4.92c2.47 1.07 2.97.86 3.51.81a3 3 0 0 0 2-1.4 2.45 2.45 0 0 0 .17-1.4c-.09-.16-.33-.24-.65-.4z"/></svg>
				</button>
			<?php else : ?>
				<!-- Enlace directo simple -->
				<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpat-wa-btn wpat-wa-trigger-click" id="wpatWaBtn" aria-label="Chat por WhatsApp">
					<svg viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.69 21.25L3 27.5l6.45-1.66A13 13 0 1 0 16 2zm0 23.8a10.74 10.74 0 0 1-5.48-1.5l-.39-.23-4.07 1.05 1.09-3.95-.25-.41A10.78 10.78 0 1 1 16 25.8zM21.9 19.33c-.32-.16-1.92-.95-2.22-1.06s-.52-.16-.73.16-.84 1.06-1.03 1.27-.38.24-.7.08a8.86 8.86 0 0 1-2.61-1.61 9.77 9.77 0 0 1-1.81-2.25c-.19-.32 0-.5.14-.65s.32-.37.48-.56a2.14 2.14 0 0 0 .32-.53.59.59 0 0 0 0-.56c-.08-.16-.73-1.75-1-2.4-.26-.63-.53-.54-.73-.55h-.62a1.2 1.2 0 0 0-.87.41 3.66 3.66 0 0 0-1.14 2.72 6.37 6.37 0 0 0 1.34 3.37 14.54 14.54 0 0 0 5.56 4.92c2.47 1.07 2.97.86 3.51.81a3 3 0 0 0 2-1.4 2.45 2.45 0 0 0 .17-1.4c-.09-.16-.33-.24-.65-.4z"/></svg>
				</a>
			<?php endif; ?>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var btn = document.getElementById('wpatWaBtn');
				var popup = document.getElementById('wpatWaPopup');
				var closeBtn = document.getElementById('wpatWaCloseBtn');

				if (btn && popup) {
					btn.addEventListener('click', function(e) {
						e.preventDefault();
						popup.classList.toggle('wpat-active');
					});
					if (closeBtn) {
						closeBtn.addEventListener('click', function(e) {
							e.stopPropagation();
							popup.classList.remove('wpat-active');
						});
					}
					document.addEventListener('click', function(e) {
						if (!btn.contains(e.target) && !popup.contains(e.target)) {
							popup.classList.remove('wpat-active');
						}
					});
				}

				// Atajo global .wpat-open-whatsapp
				document.querySelectorAll('.wpat-open-whatsapp').forEach(function(elem) {
					elem.addEventListener('click', function(e) {
						e.preventDefault();
						if (popup) {
							popup.classList.add('wpat-active');
						} else if (btn) {
							btn.click();
						}
					});
				});

				<?php if ( $track_events ) : ?>
				// Event Tracking para Google Analytics / Meta Pixel
				document.querySelectorAll('.wpat-wa-trigger-click').forEach(function(item) {
					item.addEventListener('click', function() {
						var agentName = item.getAttribute('data-agent') || 'General';
						if (typeof gtag === 'function') {
							gtag('event', 'whatsapp_click', {
								'event_category': 'Contact',
								'event_label': agentName
							});
						}
						if (typeof fbq === 'function') {
							fbq('trackCustom', 'WhatsAppClick', { agent: agentName });
						}
					});
				});
				<?php endif; ?>
			});
		</script>
		<?php
	}

	/**
	 * Shortcode [wpat_whatsapp] para colocar botones o enlaces directos en cualquier parte.
	 *
	 * @param array $atts
	 * @return string HTML
	 */
	public function render_shortcode_button( $atts = array() ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		$default_phone = ! empty( $settings['whatsapp_phone'] ) ? $settings['whatsapp_phone'] : '';

		$atts = shortcode_atts( array(
			'phone'   => $default_phone,
			'message' => '¡Hola! Quisiera más información sobre {title}.',
			'text'    => 'Chatear por WhatsApp',
			'class'   => '',
		), $atts, 'wpat_whatsapp' );

		$phone_clean = preg_replace( '/[^0-9]/', '', $atts['phone'] );
		if ( empty( $phone_clean ) ) {
			return '';
		}

		$msg = $this->parse_dynamic_message( $atts['message'] );
		$url = 'https://wa.me/' . $phone_clean . '?text=' . rawurlencode( $msg );

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="wpat-wa-shortcode-btn %s" style="display:inline-flex;align-items:center;gap:8px;background-color:#25D366;color:#FFF;padding:10px 18px;border-radius:24px;text-decoration:none;font-weight:600;"><svg style="width:20px;height:20px;fill:#FFF;" viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.69 21.25L3 27.5l6.45-1.66A13 13 0 1 0 16 2zm0 23.8a10.74 10.74 0 0 1-5.48-1.5l-.39-.23-4.07 1.05 1.09-3.95-.25-.41A10.78 10.78 0 1 1 16 25.8zM21.9 19.33c-.32-.16-1.92-.95-2.22-1.06s-.52-.16-.73.16-.84 1.06-1.03 1.27-.38.24-.7.08a8.86 8.86 0 0 1-2.61-1.61 9.77 9.77 0 0 1-1.81-2.25c-.19-.32 0-.5.14-.65s.32-.37.48-.56a2.14 2.14 0 0 0 .32-.53.59.59 0 0 0 0-.56c-.08-.16-.73-1.75-1-2.4-.26-.63-.53-.54-.73-.55h-.62a1.2 1.2 0 0 0-.87.41 3.66 3.66 0 0 0-1.14 2.72 6.37 6.37 0 0 0 1.34 3.37 14.54 14.54 0 0 0 5.56 4.92c2.47 1.07 2.97.86 3.51.81a3 3 0 0 0 2-1.4 2.45 2.45 0 0 0 .17-1.4c-.09-.16-.33-.24-.65-.4z"/></svg> %s</a>',
			esc_url( $url ),
			esc_attr( $atts['class'] ),
			esc_html( $atts['text'] )
		);
	}
}

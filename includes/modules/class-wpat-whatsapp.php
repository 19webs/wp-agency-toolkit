<?php
/**
 * Módulo: Botón Flotante de WhatsApp - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_WhatsApp {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_WhatsApp
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
	}

	/**
	 * Renderiza el botón flotante de WhatsApp en el frontend.
	 */
	public function render_whatsapp_button() {
		if ( is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Verificar si el botón está activado
		if ( empty( $settings['whatsapp'] ) && empty( $settings['whatsapp_enabled'] ) ) {
			return;
		}

		$phone = ! empty( $settings['whatsapp_phone'] ) ? trim( $settings['whatsapp_phone'] ) : '';
		if ( empty( $phone ) ) {
			return;
		}

		// Limpiar número (quitar espacios, guiones, signos + para la URL)
		$clean_phone = preg_replace( '/[^0-9]/', '', $phone );
		$default_msg = ! empty( $settings['whatsapp_message'] ) ? $settings['whatsapp_message'] : '¡Hola! Quisiera más información.';
		$position    = ! empty( $settings['whatsapp_position'] ) ? $settings['whatsapp_position'] : 'bottom-right';
		$tooltip     = ! empty( $settings['whatsapp_tooltip'] ) ? trim( $settings['whatsapp_tooltip'] ) : '';
		$agents_raw  = ! empty( $settings['whatsapp_agents'] ) ? trim( $settings['whatsapp_agents'] ) : '';

		// Parsear agentes múltiples si existen
		$agents = array();
		if ( ! empty( $agents_raw ) ) {
			$lines = explode( "\n", str_replace( "\r", "", $agents_raw ) );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( empty( $line ) ) continue;
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

		$wa_url = 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode( $default_msg );
		$is_right = ( 'bottom-left' !== $position );
		$pos_css = $is_right ? 'right: 20px;' : 'left: 20px;';
		$tooltip_pos_css = $is_right ? 'right: 70px;' : 'left: 70px;';
		?>
		<!-- WP Agency Toolkit - WhatsApp Floating Button -->
		<style>
			.wpat-wa-wrapper {
				position: fixed;
				bottom: 20px;
				<?php echo $pos_css; ?>
				z-index: 999990;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
			}
			.wpat-wa-btn {
				width: 58px;
				height: 58px;
				background-color: #25D366;
				color: #FFF;
				border-radius: 50px;
				text-align: center;
				box-shadow: 2px 2px 12px rgba(0,0,0,0.25);
				display: flex;
				align-items: center;
				justify-content: center;
				text-decoration: none;
				transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
				cursor: pointer;
				position: relative;
				border: none;
			}
			.wpat-wa-btn:hover {
				transform: scale(1.08);
				background-color: #20BA5A;
				box-shadow: 2px 4px 16px rgba(0,0,0,0.35);
			}
			.wpat-wa-btn svg {
				width: 32px;
				height: 32px;
				fill: #FFFFFF;
			}
			.wpat-wa-tooltip {
				position: absolute;
				bottom: 10px;
				<?php echo $tooltip_pos_css; ?>
				background: #FFFFFF;
				color: #111827;
				padding: 8px 14px;
				border-radius: 20px;
				font-size: 13px;
				font-weight: 500;
				white-space: nowrap;
				box-shadow: 0 4px 14px rgba(0,0,0,0.15);
				display: flex;
				align-items: center;
				gap: 6px;
				animation: wpatWaPulse 3s infinite;
			}
			@keyframes wpatWaPulse {
				0%, 100% { transform: translateY(0); }
				50% { transform: translateY(-4px); }
			}
			.wpat-wa-popup {
				display: none;
				position: absolute;
				bottom: 70px;
				<?php echo $is_right ? 'right: 0;' : 'left: 0;'; ?>
				width: 300px;
				background: #FFFFFF;
				border-radius: 16px;
				box-shadow: 0 10px 30px rgba(0,0,0,0.2);
				overflow: hidden;
				border: 1px solid #E5E7EB;
				z-index: 999999;
			}
			.wpat-wa-popup.wpat-active { display: block; }
			.wpat-wa-popup-header {
				background: #075E54;
				color: #FFFFFF;
				padding: 16px;
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
				opacity: 0.85;
			}
			.wpat-wa-agent-list {
				padding: 12px;
				max-height: 260px;
				overflow-y: auto;
			}
			.wpat-wa-agent-item {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 10px 12px;
				border-radius: 10px;
				text-decoration: none;
				color: #1F2937;
				transition: background-color 0.2s ease;
				margin-bottom: 6px;
				background: #F9FAFB;
			}
			.wpat-wa-agent-item:hover {
				background-color: #F0FDF4;
			}
			.wpat-wa-agent-info strong {
				display: block;
				font-size: 13px;
				color: #111827;
			}
			.wpat-wa-agent-info span {
				font-size: 11px;
				color: #6B7280;
			}
			.wpat-wa-agent-icon {
				width: 28px;
				height: 28px;
				background: #25D366;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			.wpat-wa-agent-icon svg {
				width: 16px;
				height: 16px;
				fill: #FFF;
			}
		</style>

		<div class="wpat-wa-wrapper">
			<?php if ( ! empty( $tooltip ) && empty( $agents ) ) : ?>
				<div class="wpat-wa-tooltip">
					<span><?php echo esc_html( $tooltip ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $agents ) ) : ?>
				<!-- Pop-up Multi-agente -->
				<div class="wpat-wa-popup" id="wpatWaPopup">
					<div class="wpat-wa-popup-header">
						<h4><?php echo esc_html( ! empty( $tooltip ) ? $tooltip : 'Contacta con nuestro equipo' ); ?></h4>
						<p>Selecciona un agente para iniciar el chat</p>
					</div>
					<div class="wpat-wa-agent-list">
						<!-- Agente principal -->
						<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpat-wa-agent-item">
							<div class="wpat-wa-agent-info">
								<strong>Atención Principal</strong>
								<span><?php echo esc_html( $phone ); ?></span>
							</div>
							<div class="wpat-wa-agent-icon">
								<svg viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.69 21.25L3 27.5l6.45-1.66A13 13 0 1 0 16 2zm0 23.8a10.74 10.74 0 0 1-5.48-1.5l-.39-.23-4.07 1.05 1.09-3.95-.25-.41A10.78 10.78 0 1 1 16 25.8zM21.9 19.33c-.32-.16-1.92-.95-2.22-1.06s-.52-.16-.73.16-.84 1.06-1.03 1.27-.38.24-.7.08a8.86 8.86 0 0 1-2.61-1.61 9.77 9.77 0 0 1-1.81-2.25c-.19-.32 0-.5.14-.65s.32-.37.48-.56a2.14 2.14 0 0 0 .32-.53.59.59 0 0 0 0-.56c-.08-.16-.73-1.75-1-2.4-.26-.63-.53-.54-.73-.55h-.62a1.2 1.2 0 0 0-.87.41 3.66 3.66 0 0 0-1.14 2.72 6.37 6.37 0 0 0 1.34 3.37 14.54 14.54 0 0 0 5.56 4.92c2.47 1.07 2.97.86 3.51.81a3 3 0 0 0 2-1.4 2.45 2.45 0 0 0 .17-1.4c-.09-.16-.33-.24-.65-.4z"/></svg>
							</div>
						</a>
						<?php foreach ( $agents as $ag ) :
							$ag_url = 'https://wa.me/' . $ag['phone'] . '?text=' . rawurlencode( $default_msg );
						?>
							<a href="<?php echo esc_url( $ag_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpat-wa-agent-item">
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
				<button type="button" class="wpat-wa-btn" id="wpatWaBtn" aria-label="WhatsApp">
					<svg viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.69 21.25L3 27.5l6.45-1.66A13 13 0 1 0 16 2zm0 23.8a10.74 10.74 0 0 1-5.48-1.5l-.39-.23-4.07 1.05 1.09-3.95-.25-.41A10.78 10.78 0 1 1 16 25.8zM21.9 19.33c-.32-.16-1.92-.95-2.22-1.06s-.52-.16-.73.16-.84 1.06-1.03 1.27-.38.24-.7.08a8.86 8.86 0 0 1-2.61-1.61 9.77 9.77 0 0 1-1.81-2.25c-.19-.32 0-.5.14-.65s.32-.37.48-.56a2.14 2.14 0 0 0 .32-.53.59.59 0 0 0 0-.56c-.08-.16-.73-1.75-1-2.4-.26-.63-.53-.54-.73-.55h-.62a1.2 1.2 0 0 0-.87.41 3.66 3.66 0 0 0-1.14 2.72 6.37 6.37 0 0 0 1.34 3.37 14.54 14.54 0 0 0 5.56 4.92c2.47 1.07 2.97.86 3.51.81a3 3 0 0 0 2-1.4 2.45 2.45 0 0 0 .17-1.4c-.09-.16-.33-.24-.65-.4z"/></svg>
				</button>
				<script>
					document.addEventListener('DOMContentLoaded', function() {
						var btn = document.getElementById('wpatWaBtn');
						var popup = document.getElementById('wpatWaPopup');
						if (btn && popup) {
							btn.addEventListener('click', function(e) {
								e.preventDefault();
								popup.classList.toggle('wpat-active');
							});
							document.addEventListener('click', function(e) {
								if (!btn.contains(e.target) && !popup.contains(e.target)) {
									popup.classList.remove('wpat-active');
								}
							});
						}
					});
				</script>
			<?php else : ?>
				<!-- Enlace directo simple -->
				<a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpat-wa-btn" aria-label="Chat por WhatsApp">
					<svg viewBox="0 0 32 32"><path d="M16 2A13 13 0 0 0 4.69 21.25L3 27.5l6.45-1.66A13 13 0 1 0 16 2zm0 23.8a10.74 10.74 0 0 1-5.48-1.5l-.39-.23-4.07 1.05 1.09-3.95-.25-.41A10.78 10.78 0 1 1 16 25.8zM21.9 19.33c-.32-.16-1.92-.95-2.22-1.06s-.52-.16-.73.16-.84 1.06-1.03 1.27-.38.24-.7.08a8.86 8.86 0 0 1-2.61-1.61 9.77 9.77 0 0 1-1.81-2.25c-.19-.32 0-.5.14-.65s.32-.37.48-.56a2.14 2.14 0 0 0 .32-.53.59.59 0 0 0 0-.56c-.08-.16-.73-1.75-1-2.4-.26-.63-.53-.54-.73-.55h-.62a1.2 1.2 0 0 0-.87.41 3.66 3.66 0 0 0-1.14 2.72 6.37 6.37 0 0 0 1.34 3.37 14.54 14.54 0 0 0 5.56 4.92c2.47 1.07 2.97.86 3.51.81a3 3 0 0 0 2-1.4 2.45 2.45 0 0 0 .17-1.4c-.09-.16-.33-.24-.65-.4z"/></svg>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}
}

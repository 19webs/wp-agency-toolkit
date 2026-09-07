<?php
/**
 * Módulo: Herramientas de Accesibilidad Web (Zero-Bloat) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Accessibility {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Accessibility
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Accessibility
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
		add_action( 'wp_footer', array( $this, 'render_accessibility_widget' ), 99 );
	}

	/**
	 * Renderiza el widget flotante y el menú emergente de accesibilidad en el frontend.
	 */
	public function render_accessibility_widget() {
		if ( is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Verificar que el módulo y la opción flotante estén activados
		if ( empty( $settings['accessibility'] ) || empty( $settings['accessibility_enabled'] ) ) {
			return;
		}

		$position = ! empty( $settings['accessibility_position'] ) ? $settings['accessibility_position'] : 'bottom-left';
		$offset_y = isset( $settings['accessibility_offset_y'] ) ? max( 0, min( 500, absint( $settings['accessibility_offset_y'] ) ) ) : 25;
		$bg_color = ! empty( $settings['accessibility_bg_color'] ) ? sanitize_hex_color( $settings['accessibility_bg_color'] ) : '#2563eb';
		if ( ! $bg_color ) {
			$bg_color = '#2563eb';
		}

		$menu_offset = $offset_y + 58;

		// Posicionamiento CSS
		$pos_css = '';
		$menu_pos_css = '';
		switch ( $position ) {
			case 'bottom-right':
				$pos_css = "bottom: {$offset_y}px; right: 25px;";
				$menu_pos_css = "bottom: {$menu_offset}px; right: 25px;";
				break;
			case 'top-left':
				$pos_css = "top: {$offset_y}px; left: 25px;";
				$menu_pos_css = "top: {$menu_offset}px; left: 25px;";
				break;
			case 'top-right':
				$pos_css = "top: {$offset_y}px; right: 25px;";
				$menu_pos_css = "top: {$menu_offset}px; right: 25px;";
				break;
			case 'bottom-left':
			default:
				$pos_css = "bottom: {$offset_y}px; left: 25px;";
				$menu_pos_css = "bottom: {$menu_offset}px; left: 25px;";
				break;
		}

		// Opciones habilitadas
		$show_zoom           = isset( $settings['accessibility_text_zoom'] ) ? '1' === $settings['accessibility_text_zoom'] : true;
		$show_grayscale      = isset( $settings['accessibility_grayscale'] ) ? '1' === $settings['accessibility_grayscale'] : true;
		$show_high_contrast  = isset( $settings['accessibility_high_contrast'] ) ? '1' === $settings['accessibility_high_contrast'] : true;
		$show_neg_contrast   = isset( $settings['accessibility_negative_contrast'] ) ? '1' === $settings['accessibility_negative_contrast'] : true;
		$show_light_bg       = isset( $settings['accessibility_light_bg'] ) ? '1' === $settings['accessibility_light_bg'] : true;
		$show_links          = isset( $settings['accessibility_underline_links'] ) ? '1' === $settings['accessibility_underline_links'] : true;
		$show_font           = isset( $settings['accessibility_readable_font'] ) ? '1' === $settings['accessibility_readable_font'] : true;
		?>
		<!-- WP Agency Toolkit - Web Accessibility Toolbar -->
		<style>
			#wpat-a11y-btn {
				position: fixed !important;
				<?php echo $pos_css; ?>
				width: 48px !important;
				height: 48px !important;
				background-color: <?php echo esc_attr( $bg_color ); ?> !important;
				border-radius: 50% !important;
				box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25) !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
				cursor: pointer !important;
				z-index: 999998 !important;
				transition: transform 0.2s ease, box-shadow 0.2s ease !important;
				border: none !important;
				outline: none !important;
				padding: 0 !important;
				margin: 0 !important;
			}
			#wpat-a11y-btn:hover {
				transform: scale(1.08) !important;
				box-shadow: 0 6px 18px rgba(0, 0, 0, 0.3) !important;
			}
			#wpat-a11y-btn svg {
				width: 26px !important;
				height: 26px !important;
				fill: #ffffff !important;
			}
			#wpat-a11y-menu {
				position: fixed !important;
				<?php echo $menu_pos_css; ?>
				width: 290px !important;
				background: #ffffff !important;
				border-radius: 8px !important;
				box-shadow: 0 10px 30px rgba(0,0,0,0.14) !important;
				border: 1px solid #e2e8f0 !important;
				z-index: 999999 !important;
				display: none;
				overflow: hidden !important;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif !important;
				direction: ltr !important;
				box-sizing: border-box !important;
			}
			#wpat-a11y-menu.wpat-open {
				display: block !important;
				animation: wpatA11yFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
			}
			@keyframes wpatA11yFadeIn {
				from { opacity: 0; transform: translateY(8px); }
				to { opacity: 1; transform: translateY(0); }
			}
			.wpat-a11y-header {
				background: #ffffff !important;
				padding: 14px 16px !important;
				border-bottom: 1px solid #f1f5f9 !important;
				display: flex !important;
				justify-content: space-between !important;
				align-items: center !important;
			}
			.wpat-a11y-header h4 {
				margin: 0 !important;
				font-size: 14.5px !important;
				font-weight: 700 !important;
				color: #0f172a !important;
				border: none !important;
				padding: 0 !important;
				line-height: 1.2 !important;
			}
			.wpat-a11y-close {
				background: transparent !important;
				border: none !important;
				font-size: 20px !important;
				line-height: 1 !important;
				color: #64748b !important;
				cursor: pointer !important;
				padding: 2px 6px !important;
				border-radius: 4px !important;
				box-shadow: none !important;
				margin: 0 !important;
			}
			.wpat-a11y-close:hover {
				background: #f1f5f9 !important;
				color: #0f172a !important;
			}
			.wpat-a11y-body {
				padding: 4px 0 !important;
				max-height: 400px !important;
				overflow-y: auto !important;
				background: #ffffff !important;
			}
			.wpat-a11y-item {
				display: flex !important;
				align-items: center !important;
				gap: 12px !important;
				width: 100% !important;
				padding: 10px 16px !important;
				background: #ffffff !important;
				border: none !important;
				border-radius: 0 !important;
				box-shadow: none !important;
				text-align: left !important;
				font-size: 13.5px !important;
				font-weight: 500 !important;
				color: #334155 !important;
				cursor: pointer !important;
				transition: background 0.15s ease, color 0.15s ease, opacity 0.15s ease !important;
				margin: 0 !important;
				outline: none !important;
				box-sizing: border-box !important;
				border-left: 3px solid transparent !important;
			}
			.wpat-a11y-item:hover {
				background: #f8fafc !important;
				color: #0f172a !important;
			}
			.wpat-a11y-item.wpat-active {
				background: #f0f9ff !important;
				color: #0284c7 !important;
				font-weight: 600 !important;
				border-left: 3px solid #0284c7 !important;
			}
			.wpat-a11y-icon {
				width: 20px !important;
				height: 20px !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
				flex-shrink: 0 !important;
			}
			.wpat-a11y-icon svg {
				width: 18px !important;
				height: 18px !important;
				fill: currentColor !important;
			}
			.wpat-a11y-divider {
				height: 1px !important;
				background: #f1f5f9 !important;
				margin: 4px 0 !important;
			}

			/* Reglas CSS para Zoom de Texto (+4px por nivel hasta 10 niveles) */
			html[data-wpat-zoom] h1,
			html[data-wpat-zoom] h2,
			html[data-wpat-zoom] h3,
			html[data-wpat-zoom] h4,
			html[data-wpat-zoom] h5,
			html[data-wpat-zoom] h6,
			html[data-wpat-zoom] p,
			html[data-wpat-zoom] li,
			html[data-wpat-zoom] blockquote,
			html[data-wpat-zoom] label,
			html[data-wpat-zoom] td,
			html[data-wpat-zoom] th,
			html[data-wpat-zoom] input,
			html[data-wpat-zoom] button,
			html[data-wpat-zoom] textarea {
				font-size: calc(1em + var(--wpat-zoom-add, 0px)) !important;
			}
			html[data-wpat-zoom] p *,
			html[data-wpat-zoom] h1 *,
			html[data-wpat-zoom] h2 *,
			html[data-wpat-zoom] h3 *,
			html[data-wpat-zoom] h4 *,
			html[data-wpat-zoom] h5 *,
			html[data-wpat-zoom] h6 *,
			html[data-wpat-zoom] li *,
			html[data-wpat-zoom] td *,
			html[data-wpat-zoom] th * {
				font-size: inherit !important;
			}

			/* Evitar que el zoom afecte a la barra de accesibilidad */
			#wpat-a11y-btn,
			#wpat-a11y-btn *,
			#wpat-a11y-menu,
			#wpat-a11y-menu * {
				font-size: initial;
			}

			/* Reglas CSS dinámicas aplicables al documento */
			html.wpat-a11y-grayscale {
				filter: grayscale(100%) !important;
			}
			html.wpat-a11y-high-contrast {
				background-color: #000000 !important;
				color: #ffffff !important;
			}
			html.wpat-a11y-high-contrast * {
				background-color: #000000 !important;
				color: #ffffff !important;
				border-color: #555555 !important;
			}
			html.wpat-a11y-high-contrast a {
				color: #ffff00 !important;
				text-decoration: underline !important;
			}
			html.wpat-a11y-negative-contrast {
				filter: invert(100%) hue-rotate(180deg) !important;
			}
			html.wpat-a11y-negative-contrast img,
			html.wpat-a11y-negative-contrast video,
			html.wpat-a11y-negative-contrast iframe,
			html.wpat-a11y-negative-contrast canvas {
				filter: invert(100%) hue-rotate(180deg) !important;
			}
			html.wpat-a11y-light-bg,
			html.wpat-a11y-light-bg body {
				background-color: #ffffff !important;
				color: #111827 !important;
			}
			html.wpat-a11y-underline-links a {
				text-decoration: underline !important;
				font-weight: 700 !important;
			}
			html.wpat-a11y-readable-font,
			html.wpat-a11y-readable-font * {
				font-family: Arial, Helvetica, sans-serif !important;
				letter-spacing: 0.02em !important;
			}
		</style>

		<!-- Botón flotante -->
		<button id="wpat-a11y-btn" aria-label="Herramientas de accesibilidad" aria-expanded="false" title="Accesibilidad">
			<svg viewBox="0 0 24 24">
				<circle cx="12" cy="4" r="2"/>
				<path d="M19 13v-2c-1.54 0-3.07-.49-4.33-1.42L13 8.35c-.39-.39-1.02-.39-1.41 0L9.9 9.93C8.42 11.23 6.46 12 4 12v2c2.97 0 5.42-.98 7.31-2.58l.69 3.58L9.5 21h2.2l1.6-6 1.6 6h2.2l-2.5-6 .8-4.2c.98.53 2.15.8 3.8.8z"/>
			</svg>
		</button>

		<!-- Menú Emergente de Herramientas -->
		<div id="wpat-a11y-menu" role="dialog" aria-label="Herramientas de accesibilidad">
			<div class="wpat-a11y-header">
				<h4>Herramientas de accesibilidad</h4>
				<button type="button" class="wpat-a11y-close" id="wpat-a11y-close-btn" aria-label="Cerrar">&times;</button>
			</div>
			<div class="wpat-a11y-body">
				<?php if ( $show_zoom ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="zoom-in">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/><path d="M12 10h-2v2H9v-2H7V9h2V7h1v2h2v1z"/></svg>
						</span>
						<span class="wpat-a11y-label">Aumentar texto</span>
					</button>
					<button type="button" class="wpat-a11y-item" data-action="zoom-out" style="opacity: 0.4; cursor: default;">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14zM7 9h5v1H7V9z"/></svg>
						</span>
						<span class="wpat-a11y-label">Disminuir texto</span>
					</button>
				<?php endif; ?>

				<?php if ( $show_grayscale ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-grayscale">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14v12c3.31 0 6-2.69 6-6s-2.69-6-6-6z"/></svg>
						</span>
						<span class="wpat-a11y-label">Escala de grises</span>
					</button>
				<?php endif; ?>

				<?php if ( $show_high_contrast ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-high-contrast">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18V4c4.41 0 8 3.59 8 8s-3.59 8-8 8z"/></svg>
						</span>
						<span class="wpat-a11y-label">Alto contraste</span>
					</button>
				<?php endif; ?>

				<?php if ( $show_neg_contrast ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-negative-contrast">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
						</span>
						<span class="wpat-a11y-label">Contraste negativo</span>
					</button>
				<?php endif; ?>

				<?php if ( $show_light_bg ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-light-bg">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-3.3l-.85-.6C7.8 11.16 7 9.88 7 8.5 7 5.74 9.24 3.5 12 3.5s5 2.24 5 5c0 1.38-.8 2.66-2.15 3.6z"/></svg>
						</span>
						<span class="wpat-a11y-label">Fondo claro</span>
					</button>
				<?php endif; ?>

				<?php if ( $show_links ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-underline-links">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
						</span>
						<span class="wpat-a11y-label">Subrayar enlaces</span>
					</button>
				<?php endif; ?>

				<?php if ( $show_font ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-readable-font">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M9.93 13.5h4.14L12 7.98zM20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-4.05 16.5l-1.14-3H9.17l-1.12 3H5.96l5.11-13h1.86l5.11 13h-2.09z"/></svg>
						</span>
						<span class="wpat-a11y-label">Fuente legible</span>
					</button>
				<?php endif; ?>

				<div class="wpat-a11y-divider"></div>

				<button type="button" class="wpat-a11y-item" data-action="reset" style="color: #ef4444 !important;">
					<span class="wpat-a11y-icon">
						<svg viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
					</span>
					<span class="wpat-a11y-label">Restablecer</span>
				</button>
			</div>
		</div>

		<!-- Script de control ultra-ligero (Vanilla JS) -->
		<script>
		(function() {
			var btn = document.getElementById('wpat-a11y-btn');
			var menu = document.getElementById('wpat-a11y-menu');
			var closeBtn = document.getElementById('wpat-a11y-close-btn');
			if (!btn || !menu) return;

			var zoomLevel = 0; // 0 (normal), 1 (+4px), ..., 10 (+40px)
			var maxZoomLevel = 10;

			function applyZoom() {
				var zoomInBtn = menu.querySelector('[data-action="zoom-in"]');
				var zoomOutBtn = menu.querySelector('[data-action="zoom-out"]');
				var zoomInLabel = zoomInBtn ? zoomInBtn.querySelector('.wpat-a11y-label') : null;
				var zoomOutLabel = zoomOutBtn ? zoomOutBtn.querySelector('.wpat-a11y-label') : null;

				if (zoomLevel > 0) {
					document.documentElement.setAttribute('data-wpat-zoom', zoomLevel);
					document.documentElement.style.setProperty('--wpat-zoom-add', (zoomLevel * 4) + 'px');
					
					if (zoomInBtn) zoomInBtn.classList.add('wpat-active');
					if (zoomOutBtn) {
						zoomOutBtn.style.opacity = '1';
						zoomOutBtn.style.cursor = 'pointer';
					}

					if (zoomInLabel) {
						if (zoomLevel >= maxZoomLevel) {
							zoomInLabel.textContent = 'Aumentar texto (+40px Máx)';
							if (zoomInBtn) {
								zoomInBtn.style.opacity = '0.6';
								zoomInBtn.style.cursor = 'not-allowed';
							}
						} else {
							zoomInLabel.textContent = 'Aumentar texto (+' + (zoomLevel * 4) + 'px)';
							if (zoomInBtn) {
								zoomInBtn.style.opacity = '1';
								zoomInBtn.style.cursor = 'pointer';
							}
						}
					}
					if (zoomOutLabel) {
						zoomOutLabel.textContent = 'Disminuir texto';
					}
				} else {
					document.documentElement.removeAttribute('data-wpat-zoom');
					document.documentElement.style.removeProperty('--wpat-zoom-add');
					
					if (zoomInBtn) {
						zoomInBtn.classList.remove('wpat-active');
						zoomInBtn.style.opacity = '1';
						zoomInBtn.style.cursor = 'pointer';
					}
					if (zoomOutBtn) {
						zoomOutBtn.classList.remove('wpat-active');
						zoomOutBtn.style.opacity = '0.4';
						zoomOutBtn.style.cursor = 'default';
					}
					if (zoomInLabel) zoomInLabel.textContent = 'Aumentar texto';
					if (zoomOutLabel) zoomOutLabel.textContent = 'Disminuir texto';
				}
			}

			// Cargar estado guardado en sessionStorage
			try {
				var savedState = JSON.parse(sessionStorage.getItem('wpat_a11y_state') || '{}');
				if (typeof savedState.zoomLevel === 'number') {
					zoomLevel = savedState.zoomLevel;
					applyZoom();
				}
				if (savedState.classes && Array.isArray(savedState.classes)) {
					savedState.classes.forEach(function(cls) {
						document.documentElement.classList.add(cls);
						var item = menu.querySelector('[data-target="' + cls + '"]');
						if (item) item.classList.add('wpat-active');
					});
				}
			} catch(e) {}

			function saveState() {
				var activeClasses = [];
				var items = menu.querySelectorAll('[data-target]');
				items.forEach(function(item) {
					var cls = item.getAttribute('data-target');
					if (document.documentElement.classList.contains(cls)) {
						activeClasses.push(cls);
					}
				});
				try {
					sessionStorage.setItem('wpat_a11y_state', JSON.stringify({
						zoomLevel: zoomLevel,
						classes: activeClasses
					}));
				} catch(e) {}
			}

			function toggleMenu() {
				var isOpen = menu.classList.contains('wpat-open');
				if (isOpen) {
					menu.classList.remove('wpat-open');
					btn.setAttribute('aria-expanded', 'false');
				} else {
					menu.classList.add('wpat-open');
					btn.setAttribute('aria-expanded', 'true');
				}
			}

			btn.addEventListener('click', toggleMenu);
			if (closeBtn) closeBtn.addEventListener('click', toggleMenu);

			// Cerrar al pulsar Escape
			window.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && menu.classList.contains('wpat-open')) {
					toggleMenu();
				}
			});

			// Acciones del menú
			menu.addEventListener('click', function(e) {
				var targetBtn = e.target.closest('[data-action]');
				if (!targetBtn) return;

				var action = targetBtn.getAttribute('data-action');

				if (action === 'zoom-in') {
					if (zoomLevel < maxZoomLevel) {
						zoomLevel++;
						applyZoom();
						saveState();
					}
				} else if (action === 'zoom-out') {
					if (zoomLevel > 0) {
						zoomLevel--;
						applyZoom();
						saveState();
					}
				} else if (action === 'toggle-class') {
					var cls = targetBtn.getAttribute('data-target');
					if (cls) {
						var isAdd = document.documentElement.classList.toggle(cls);
						if (isAdd) {
							targetBtn.classList.add('wpat-active');
						} else {
							targetBtn.classList.remove('wpat-active');
						}
						saveState();
					}
				} else if (action === 'reset') {
					zoomLevel = 0;
					applyZoom();
					var items = menu.querySelectorAll('[data-target]');
					items.forEach(function(item) {
						var cls = item.getAttribute('data-target');
						document.documentElement.classList.remove(cls);
						item.classList.remove('wpat-active');
					});
					try { sessionStorage.removeItem('wpat_a11y_state'); } catch(e) {}
				}
			});
		})();
		</script>
		<?php
	}
}

<?php
/**
 * Módulo: Herramientas de Accesibilidad Web (Zero-Bloat & WCAG) - WP Agency Toolkit
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
		add_shortcode( 'wpat_accessibility', array( $this, 'render_accessibility_shortcode' ) );
		add_shortcode( 'wpat_accessibility_button', array( $this, 'render_accessibility_shortcode' ) );
	}

	/**
	 * Renderiza un botón mediante shortcode para abrir el panel de accesibilidad.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_accessibility_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'text'  => __( 'Herramientas de Accesibilidad', 'wp-agency-toolkit' ),
			'class' => 'wpat-a11y-custom-trigger',
		), $atts, 'wpat_accessibility' );

		return sprintf(
			'<button type="button" class="wpat-open-a11y %s" aria-label="%s" style="cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
				<span class="dashicons dashicons-universal-access" style="font-size:18px; width:18px; height:18px;"></span>
				<span>%s</span>
			</button>',
			esc_attr( $atts['class'] ),
			esc_attr( $atts['text'] ),
			esc_html( $atts['text'] )
		);
	}

	/**
	 * Renderiza el widget flotante y el menú emergente de accesibilidad en el frontend.
	 */
	public function render_accessibility_widget() {
		if ( is_admin() ) {
			return;
		}

		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : get_option( 'wpat_settings', array() );

		// Verificar que el módulo esté activado
		if ( empty( $settings['accessibility'] ) && empty( $settings['accessibility_enabled'] ) ) {
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
		$pos_css      = '';
		$menu_pos_css = '';
		switch ( $position ) {
			case 'bottom-right':
				$pos_css      = "bottom: {$offset_y}px; right: 25px;";
				$menu_pos_css = "bottom: {$menu_offset}px; right: 25px;";
				break;
			case 'top-left':
				$pos_css      = "top: {$offset_y}px; left: 25px;";
				$menu_pos_css = "top: {$menu_offset}px; left: 25px;";
				break;
			case 'top-right':
				$pos_css      = "top: {$offset_y}px; right: 25px;";
				$menu_pos_css = "top: {$menu_offset}px; right: 25px;";
				break;
			case 'bottom-left':
			default:
				$pos_css      = "bottom: {$offset_y}px; left: 25px;";
				$menu_pos_css = "bottom: {$menu_offset}px; left: 25px;";
				break;
		}

		// Opciones habilitadas
		$show_zoom            = ! isset( $settings['accessibility_text_zoom'] ) || '1' === $settings['accessibility_text_zoom'];
		$show_guide           = ! isset( $settings['accessibility_reading_guide'] ) || '1' === $settings['accessibility_reading_guide'];
		$show_cursor          = ! isset( $settings['accessibility_big_cursor'] ) || '1' === $settings['accessibility_big_cursor'];
		$show_animations      = ! isset( $settings['accessibility_stop_animations'] ) || '1' === $settings['accessibility_stop_animations'];
		$show_spacing         = ! isset( $settings['accessibility_text_spacing'] ) || '1' === $settings['accessibility_text_spacing'];
		$show_dyslexic        = ! isset( $settings['accessibility_dyslexic_font'] ) || '1' === $settings['accessibility_dyslexic_font'];
		$show_grayscale       = ! isset( $settings['accessibility_grayscale'] ) || '1' === $settings['accessibility_grayscale'];
		$show_high_contrast   = ! isset( $settings['accessibility_high_contrast'] ) || '1' === $settings['accessibility_high_contrast'];
		$show_neg_contrast    = ! isset( $settings['accessibility_negative_contrast'] ) || '1' === $settings['accessibility_negative_contrast'];
		$show_light_bg        = ! isset( $settings['accessibility_light_bg'] ) || '1' === $settings['accessibility_light_bg'];
		$show_links           = ! isset( $settings['accessibility_underline_links'] ) || '1' === $settings['accessibility_underline_links'];
		$show_font            = ! isset( $settings['accessibility_readable_font'] ) || '1' === $settings['accessibility_readable_font'];
		?>
		<!-- WP Agency Toolkit - Web Accessibility Suite (WCAG Compliant) -->
		<style>
			#wpat-a11y-btn {
				position: fixed !important;
				<?php echo $pos_css; ?>
				width: 50px !important;
				height: 50px !important;
				background-color: <?php echo esc_attr( $bg_color ); ?> !important;
				border-radius: 50% !important;
				box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25) !important;
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
				cursor: pointer !important;
				z-index: 999998 !important;
				transition: transform 0.2s ease, box-shadow 0.2s ease !important;
				border: 2px solid #ffffff !important;
				outline: none !important;
				padding: 0 !important;
				margin: 0 !important;
			}
			#wpat-a11y-btn:hover, #wpat-a11y-btn:focus-visible {
				transform: scale(1.08) !important;
				box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35) !important;
				outline: 2px solid <?php echo esc_attr( $bg_color ); ?> !important;
				outline-offset: 3px !important;
			}
			#wpat-a11y-btn svg {
				width: 28px !important;
				height: 28px !important;
				fill: #ffffff !important;
			}
			#wpat-a11y-menu {
				position: fixed !important;
				<?php echo $menu_pos_css; ?>
				width: 310px !important;
				max-width: calc(100vw - 30px) !important;
				background: #ffffff !important;
				border-radius: 10px !important;
				box-shadow: 0 12px 35px rgba(0,0,0,0.18) !important;
				border: 1px solid #cbd5e1 !important;
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
				background: #f8fafc !important;
				padding: 12px 16px !important;
				border-bottom: 1px solid #e2e8f0 !important;
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
				font-size: 22px !important;
				line-height: 1 !important;
				color: #64748b !important;
				cursor: pointer !important;
				padding: 2px 6px !important;
				border-radius: 4px !important;
				box-shadow: none !important;
				margin: 0 !important;
			}
			.wpat-a11y-close:hover, .wpat-a11y-close:focus {
				background: #e2e8f0 !important;
				color: #0f172a !important;
			}
			.wpat-a11y-body {
				padding: 4px 0 !important;
				max-height: 420px !important;
				overflow-y: auto !important;
				background: #ffffff !important;
			}
			.wpat-a11y-item {
				display: flex !important;
				align-items: center !important;
				gap: 12px !important;
				width: 100% !important;
				padding: 9px 16px !important;
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
			.wpat-a11y-item:hover, .wpat-a11y-item:focus {
				background: #f1f5f9 !important;
				color: #0f172a !important;
			}
			.wpat-a11y-item.wpat-active {
				background: #eff6ff !important;
				color: #1d4ed8 !important;
				font-weight: 600 !important;
				border-left: 3px solid #2563eb !important;
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

			/* 1. Zoom de Texto (+4px por nivel hasta 10 niveles) */
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

			/* Proteger el widget de los estilos aplicados al documento */
			#wpat-a11y-btn, #wpat-a11y-btn *,
			#wpat-a11y-menu, #wpat-a11y-menu * {
				font-size: initial;
			}

			/* 2. Guía de Lectura */
			#wpat-a11y-reading-guide {
				position: fixed !important;
				left: 0 !important;
				right: 0 !important;
				height: 16px !important;
				background: rgba(254, 240, 138, 0.45) !important;
				border-top: 2px solid #eab308 !important;
				border-bottom: 2px solid #eab308 !important;
				pointer-events: none !important;
				z-index: 9999990 !important;
				display: none;
				transform: translateY(-50%) !important;
			}
			html.wpat-a11y-guide #wpat-a11y-reading-guide {
				display: block !important;
			}

			/* 3. Cursor Grande */
			html.wpat-a11y-big-cursor, html.wpat-a11y-big-cursor * {
				cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='36' height='36' viewBox='0 0 24 24'%3E%3Cpath fill='%23000000' stroke='%23ffffff' stroke-width='1.5' d='M3 3l7 18 3-7 7-3L3 3z'/%3E%3C/svg%3E"), auto !important;
			}

			/* 4. Detener Animaciones (WCAG 2.3.3) */
			html.wpat-a11y-stop-animations *,
			html.wpat-a11y-stop-animations *::before,
			html.wpat-a11y-stop-animations *::after {
				animation: none !important;
				transition: none !important;
				scroll-behavior: auto !important;
			}

			/* 5. Espaciado de Texto (WCAG 1.4.12) */
			html.wpat-a11y-spacing,
			html.wpat-a11y-spacing p,
			html.wpat-a11y-spacing h1,
			html.wpat-a11y-spacing h2,
			html.wpat-a11y-spacing h3,
			html.wpat-a11y-spacing h4,
			html.wpat-a11y-spacing h5,
			html.wpat-a11y-spacing h6,
			html.wpat-a11y-spacing li,
			html.wpat-a11y-spacing a,
			html.wpat-a11y-spacing span {
				line-height: 1.85 !important;
				letter-spacing: 0.12em !important;
				word-spacing: 0.16em !important;
			}

			/* 6. Fuente para Dislexia */
			html.wpat-a11y-dyslexic,
			html.wpat-a11y-dyslexic * {
				font-family: 'OpenDyslexic', 'Comic Sans MS', cursive, sans-serif !important;
				letter-spacing: 0.04em !important;
			}

			/* 7. Escala de grises */
			html.wpat-a11y-grayscale {
				filter: grayscale(100%) !important;
			}

			/* 8. Alto contraste */
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

			/* 9. Contraste negativo / Invertir */
			html.wpat-a11y-negative-contrast {
				filter: invert(100%) hue-rotate(180deg) !important;
			}
			html.wpat-a11y-negative-contrast img,
			html.wpat-a11y-negative-contrast video,
			html.wpat-a11y-negative-contrast iframe,
			html.wpat-a11y-negative-contrast canvas {
				filter: invert(100%) hue-rotate(180deg) !important;
			}

			/* 10. Fondo claro */
			html.wpat-a11y-light-bg,
			html.wpat-a11y-light-bg body {
				background-color: #ffffff !important;
				color: #111827 !important;
			}

			/* 11. Subrayar enlaces */
			html.wpat-a11y-underline-links a {
				text-decoration: underline !important;
				font-weight: 700 !important;
			}

			/* 12. Fuente legible Sans-Serif */
			html.wpat-a11y-readable-font,
			html.wpat-a11y-readable-font * {
				font-family: Arial, Helvetica, sans-serif !important;
				letter-spacing: 0.02em !important;
			}
		</style>

		<!-- Guía de Lectura flotante -->
		<div id="wpat-a11y-reading-guide" aria-hidden="true"></div>

		<!-- Botón flotante -->
		<button id="wpat-a11y-btn" aria-label="<?php esc_attr_e( 'Herramientas de accesibilidad', 'wp-agency-toolkit' ); ?>" aria-expanded="false" title="<?php esc_attr_e( 'Accesibilidad', 'wp-agency-toolkit' ); ?>">
			<svg viewBox="0 0 24 24">
				<circle cx="12" cy="4" r="2"/>
				<path d="M19 13v-2c-1.54 0-3.07-.49-4.33-1.42L13 8.35c-.39-.39-1.02-.39-1.41 0L9.9 9.93C8.42 11.23 6.46 12 4 12v2c2.97 0 5.42-.98 7.31-2.58l.69 3.58L9.5 21h2.2l1.6-6 1.6 6h2.2l-2.5-6 .8-4.2c.98.53 2.15.8 3.8.8z"/>
			</svg>
		</button>

		<!-- Menú Emergente de Herramientas -->
		<div id="wpat-a11y-menu" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Herramientas de accesibilidad web', 'wp-agency-toolkit' ); ?>">
			<div class="wpat-a11y-header">
				<h4><?php esc_html_e( 'Herramientas de Accesibilidad', 'wp-agency-toolkit' ); ?></h4>
				<button type="button" class="wpat-a11y-close" id="wpat-a11y-close-btn" aria-label="<?php esc_attr_e( 'Cerrar panel de accesibilidad', 'wp-agency-toolkit' ); ?>">&times;</button>
			</div>
			<div class="wpat-a11y-body">
				
				<?php if ( $show_zoom ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="zoom-in">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/><path d="M12 10h-2v2H9v-2H7V9h2V7h1v2h2v1z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Aumentar texto', 'wp-agency-toolkit' ); ?></span>
					</button>
					<button type="button" class="wpat-a11y-item" data-action="zoom-out" style="opacity: 0.4; cursor: default;">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14zM7 9h5v1H7V9z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Disminuir texto', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_guide ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-guide">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M3 13h18v-2H3v2zm0-7h18V4H3v2zm0 14h18v-2H3v2z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Guía de lectura (Dislexia)', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_cursor ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-big-cursor">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M13.64 21.97l-3.27-7.46-4.52 4.52V2l15.13 15.13h-6.28l3.18 7.23-4.24 1.61z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Cursor grande', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_animations ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-stop-animations">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Detener animaciones', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_spacing ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-spacing">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M4 19h16v2H4zM20 3H4v2h16zM9.5 8.5L7 11h10l-2.5-2.5L13 7l5 5-5 5 1.5-1.5L17 13H7l2.5 2.5L8 17l-5-5 5-5z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Espaciado de texto (WCAG)', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_dyslexic ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-dyslexic">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9 0 2.12.74 4.07 1.97 5.61L4.35 19.4c-.39.39-.39 1.02 0 1.41.39.39 1.02.39 1.41 0l1.9-1.9C9.28 19.58 10.59 20 12 20c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5h2v6h-2V8zm1 10.25c-.69 0-1.25-.56-1.25-1.25s.56-1.25 1.25-1.25 1.25.56 1.25 1.25-.56 1.25-1.25 1.25z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Fuente para dislexia', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_grayscale ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-grayscale">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14v12c3.31 0 6-2.69 6-6s-2.69-6-6-6z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Escala de grises', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_high_contrast ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-high-contrast">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18V4c4.41 0 8 3.59 8 8s-3.59 8-8 8z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Alto contraste', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_neg_contrast ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-negative-contrast">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Contraste negativo', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_light_bg ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-light-bg">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-3.3l-.85-.6C7.8 11.16 7 9.88 7 8.5 7 5.74 9.24 3.5 12 3.5s5 2.24 5 5c0 1.38-.8 2.66-2.15 3.6z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Fondo claro', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_links ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-underline-links">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Subrayar enlaces', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<?php if ( $show_font ) : ?>
					<button type="button" class="wpat-a11y-item" data-action="toggle-class" data-target="wpat-a11y-readable-font">
						<span class="wpat-a11y-icon">
							<svg viewBox="0 0 24 24"><path d="M9.93 13.5h4.14L12 7.98zM20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-4.05 16.5l-1.14-3H9.17l-1.12 3H5.96l5.11-13h1.86l5.11 13h-2.09z"/></svg>
						</span>
						<span class="wpat-a11y-label"><?php esc_html_e( 'Fuente legible (Sans-Serif)', 'wp-agency-toolkit' ); ?></span>
					</button>
				<?php endif; ?>

				<div class="wpat-a11y-divider"></div>

				<button type="button" class="wpat-a11y-item" data-action="reset" style="color: #ef4444 !important;">
					<span class="wpat-a11y-icon">
						<svg viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
					</span>
					<span class="wpat-a11y-label" style="font-weight: 600;"><?php esc_html_e( 'Restablecer Ajustes', 'wp-agency-toolkit' ); ?></span>
				</button>
			</div>
		</div>

		<!-- Script de control de accesibilidad Zero-Bloat (Vanilla JS) -->
		<script>
		(function() {
			var btn = document.getElementById('wpat-a11y-btn');
			var menu = document.getElementById('wpat-a11y-menu');
			var closeBtn = document.getElementById('wpat-a11y-close-btn');
			var readingGuide = document.getElementById('wpat-a11y-reading-guide');
			if (!btn || !menu) return;

			var zoomLevel = 0; // 0 a 10
			var maxZoomLevel = 10;

			// Mover guía de lectura con el cursor
			if (readingGuide) {
				window.addEventListener('mousemove', function(e) {
					if (document.documentElement.classList.contains('wpat-a11y-guide')) {
						readingGuide.style.top = e.clientY + 'px';
					}
				}, { passive: true });
			}

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

			// Cargar estado persistente (localStorage con fallback a sessionStorage)
			try {
				var storage = window.localStorage || window.sessionStorage;
				var savedState = JSON.parse(storage.getItem('wpat_a11y_state') || '{}');
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
					var storage = window.localStorage || window.sessionStorage;
					storage.setItem('wpat_a11y_state', JSON.stringify({
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
					btn.focus();
				} else {
					menu.classList.add('wpat-open');
					btn.setAttribute('aria-expanded', 'true');
					var firstItem = menu.querySelector('.wpat-a11y-item');
					if (firstItem) firstItem.focus();
				}
			}

			btn.addEventListener('click', toggleMenu);
			if (closeBtn) closeBtn.addEventListener('click', toggleMenu);

			// Disparadores externos (.wpat-open-a11y y #wpat-a11y-trigger)
			document.addEventListener('click', function(e) {
				var trigger = e.target.closest('.wpat-open-a11y, #wpat-a11y-trigger');
				if (trigger) {
					e.preventDefault();
					toggleMenu();
				}
			});

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
					try {
						var storage = window.localStorage || window.sessionStorage;
						storage.removeItem('wpat_a11y_state');
					} catch(e) {}
				}
			});
		})();
		</script>
		<?php
	}
}

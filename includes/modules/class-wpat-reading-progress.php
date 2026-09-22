<?php
/**
 * Módulo: Barra de Progreso y Tiempo de Lectura - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Reading_Progress {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Reading_Progress|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Reading_Progress
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'the_content', array( $this, 'prepend_reading_time' ), 5 );
		add_shortcode( 'tiempo_lectura', array( $this, 'reading_time_shortcode' ) );
		add_shortcode( 'wpat_reading_time', array( $this, 'reading_time_shortcode' ) );
	}

	/**
	 * Comprueba si la vista actual corresponde a uno de los tipos de contenido configurados.
	 *
	 * @return bool
	 */
	public static function is_target_screen() {
		if ( ! is_singular() || is_admin() ) {
			return false;
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$post_types = isset( $settings['reading_bar_post_types'] ) && is_array( $settings['reading_bar_post_types'] ) ? $settings['reading_bar_post_types'] : array( 'post' );

		return is_singular( $post_types );
	}

	/**
	 * Carga el CSS/JS ultra-ligero para la barra de progreso de lectura.
	 */
	public function enqueue_frontend_assets() {
		if ( ! self::is_target_screen() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		$bar_enabled  = ! isset( $settings['reading_bar_enabled'] ) || '1' === (string) $settings['reading_bar_enabled'];
		$time_enabled = isset( $settings['reading_time_enabled'] ) && '1' === (string) $settings['reading_time_enabled'];

		if ( ! $bar_enabled && ! $time_enabled ) {
			return;
		}

		if ( $bar_enabled ) {
			$bar_color     = ! empty( $settings['reading_bar_color'] ) ? sanitize_hex_color( $settings['reading_bar_color'] ) : '#2563eb';
			$bar_color_end = ! empty( $settings['reading_bar_color_end'] ) ? sanitize_hex_color( $settings['reading_bar_color_end'] ) : '';
			$bar_height    = ! empty( $settings['reading_bar_height'] ) ? max( 1, min( 30, absint( $settings['reading_bar_height'] ) ) ) : 4;
			$position      = isset( $settings['reading_bar_position'] ) && 'bottom' === $settings['reading_bar_position'] ? 'bottom' : 'top';
			$scope         = isset( $settings['reading_bar_scope'] ) && 'window' === $settings['reading_bar_scope'] ? 'window' : 'article';

			if ( ! $bar_color ) {
				$bar_color = '#2563eb';
			}

			// Fondo plano o degradado
			if ( ! empty( $bar_color_end ) ) {
				$background_css = 'background: linear-gradient(90deg, ' . esc_attr( $bar_color ) . ' 0%, ' . esc_attr( $bar_color_end ) . ' 100%);';
			} else {
				$background_css = 'background-color: ' . esc_attr( $bar_color ) . ';';
			}

			add_action(
				'wp_footer',
				function() use ( $background_css, $bar_height, $position, $scope ) {
					?>
					<!-- WP Agency Toolkit - Reading Progress Bar -->
					<style>
						#wpat-reading-progress-bar {
							position: fixed;
							<?php if ( 'bottom' === $position ) : ?>
								bottom: 0;
								top: auto;
							<?php else : ?>
								top: 0;
								bottom: auto;
							<?php endif; ?>
							left: 0;
							width: 0%;
							height: <?php echo (int) $bar_height; ?>px;
							<?php echo $background_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							z-index: 999999;
							transition: width 0.08s ease-out;
							pointer-events: none;
							box-shadow: 0 1px 3px rgba(0,0,0,0.12);
						}
						<?php if ( 'top' === $position ) : ?>
							.admin-bar #wpat-reading-progress-bar {
								top: 32px;
							}
							@media screen and (max-width: 782px) {
								.admin-bar #wpat-reading-progress-bar {
									top: 46px;
								}
							}
						<?php endif; ?>
					</style>
					<div id="wpat-reading-progress-bar"></div>
					<script type="text/javascript">
						(function() {
							var progressBar = document.getElementById('wpat-reading-progress-bar');
							if (!progressBar) return;

							var scope = '<?php echo esc_js( $scope ); ?>';
							var ticking = false;

							function getTargetMetrics() {
								if (scope === 'article') {
									var target = document.querySelector('article, .entry-content, .post-content, #content, main');
									if (target) {
										var rect = target.getBoundingClientRect();
										var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
										var targetTop = rect.top + scrollTop;
										var targetHeight = target.offsetHeight;
										var winHeight = window.innerHeight;

										var start = targetTop;
										var end = targetTop + targetHeight - winHeight;

										if (end <= start) {
											end = start + targetHeight;
										}

										return { start: start, end: end };
									}
								}

								var docHeight = document.documentElement.scrollHeight - window.innerHeight;
								return { start: 0, end: docHeight > 0 ? docHeight : 1 };
							}

							function updateProgress() {
								var metrics = getTargetMetrics();
								var currentScroll = window.pageYOffset || document.documentElement.scrollTop;

								var progress = 0;
								if (currentScroll <= metrics.start) {
									progress = 0;
								} else if (currentScroll >= metrics.end) {
									progress = 100;
								} else {
									progress = ((currentScroll - metrics.start) / (metrics.end - metrics.start)) * 100;
								}

								progressBar.style.width = Math.min(100, Math.max(0, progress)).toFixed(2) + '%';
								ticking = false;
							}

							window.addEventListener('scroll', function() {
								if (!ticking) {
									window.requestAnimationFrame(updateProgress);
									ticking = true;
								}
							}, { passive: true });

							window.addEventListener('resize', updateProgress);
							document.addEventListener('DOMContentLoaded', updateProgress);
						})();
					</script>
					<?php
				},
				99
			);
		}
	}

	/**
	 * Calcula el tiempo estimado de lectura en minutos.
	 *
	 * @param string $content Contenido de texto.
	 * @param int    $wpm     Palabras por minuto promedio (por defecto 200).
	 * @return int Minutos estimados
	 */
	public static function calculate_reading_time( $content, $wpm = 200 ) {
		$clean_text = wp_strip_all_tags( strip_shortcodes( $content ) );
		$word_count = count( preg_split( '/\s+/', trim( $clean_text ) ) );
		$wpm        = max( 100, (int) $wpm );
		$minutes    = (int) ceil( $word_count / $wpm );
		return max( 1, $minutes );
	}

	/**
	 * Inserta el distintivo de tiempo de lectura al inicio de la entrada si está activado.
	 *
	 * @param string $content Contenido del post.
	 * @return string
	 */
	public function prepend_reading_time( $content ) {
		if ( ! self::is_target_screen() || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $content;
		}

		if ( ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['reading_time_enabled'] ) ) {
			return $content;
		}

		static $processed_posts = array();
		$post_id = get_the_ID();
		if ( isset( $processed_posts[ $post_id ] ) ) {
			return $content;
		}
		$processed_posts[ $post_id ] = true;

		$wpm     = isset( $settings['reading_time_wpm'] ) ? (int) $settings['reading_time_wpm'] : 200;
		$minutes = self::calculate_reading_time( $content, $wpm );
		$style   = isset( $settings['reading_time_style'] ) ? $settings['reading_time_style'] : 'pill';
		$label   = isset( $settings['reading_time_label'] ) && ! empty( $settings['reading_time_label'] ) ? $settings['reading_time_label'] : __( 'Tiempo estimado de lectura: {time} min', 'wp-agency-toolkit' );

		$text = str_replace( '{time}', (string) $minutes, $label );

		$badge_html = self::render_badge_html( $minutes, $text, $style );

		return $badge_html . $content;
	}

	/**
	 * Renderiza el HTML del distintivo de tiempo de lectura según el estilo configurado.
	 *
	 * @param int    $minutes Minutos estimados.
	 * @param string $text    Texto formateado.
	 * @param string $style   Estilo visual (pill, minimal, bordered).
	 * @return string
	 */
	public static function render_badge_html( $minutes, $text, $style = 'pill' ) {
		$clock_svg = '<svg style="width:15px; height:15px; fill:none; stroke:#2563eb; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';

		if ( 'minimal' === $style ) {
			return sprintf(
				'<div class="wpat-reading-time-badge wpat-style-minimal" style="display:inline-flex; align-items:center; gap:6px; color:#64748b; font-size:13px; font-weight:500; margin-bottom:16px;">%s<span>%s</span></div>',
				$clock_svg,
				esc_html( $text )
			);
		}

		if ( 'bordered' === $style ) {
			return sprintf(
				'<div class="wpat-reading-time-badge wpat-style-bordered" style="display:inline-flex; align-items:center; gap:8px; border-left:3px solid #2563eb; padding:6px 12px; background:#f8fafc; color:#334155; font-size:13px; font-weight:600; margin-bottom:18px;">%s<span>%s</span></div>',
				$clock_svg,
				esc_html( $text )
			);
		}

		// Por defecto: 'pill'
		return sprintf(
			'<div class="wpat-reading-time-badge wpat-style-pill" style="display:inline-flex; align-items:center; gap:8px; background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; padding:6px 14px; border-radius:20px; font-size:13px; font-weight:600; margin-bottom:18px; line-height:1.2;">%s<span>%s</span></div>',
			$clock_svg,
			esc_html( $text )
		);
	}

	/**
	 * Shortcode [tiempo_lectura] o [wpat_reading_time] para renderizar el distintivo en cualquier lugar de la plantilla o post.
	 *
	 * @param array $atts Atributos del shortcode.
	 * @return string
	 */
	public function reading_time_shortcode( $atts = array() ) {
		global $post;
		if ( ! $post ) {
			return '';
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$wpm      = isset( $settings['reading_time_wpm'] ) ? (int) $settings['reading_time_wpm'] : 200;
		$minutes  = self::calculate_reading_time( $post->post_content, $wpm );

		return sprintf(
			'<span class="wpat-reading-time-shortcode" style="display:inline-flex; align-items:center; gap:5px; font-size:13px; font-weight:500; color:#475569;">
				<svg style="width:14px;height:14px;fill:none;stroke:#2563eb;stroke-width:2;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
				%d min de lectura
			</span>',
			$minutes
		);
	}
}

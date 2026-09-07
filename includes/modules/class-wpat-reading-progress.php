<?php
/**
 * Módulo: Barra de Progreso y Tiempo de Lectura - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Reading_Progress {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Reading_Progress
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
		add_filter( 'the_content', array( $this, 'prepend_reading_time' ), 10 );
		add_shortcode( 'tiempo_lectura', array( $this, 'reading_time_shortcode' ) );
	}

	/**
	 * Carga el CSS/JS ultra-ligero para la barra de lectura en entradas.
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_single() || is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Si no está activada la barra ni el tiempo de lectura, salir
		if ( empty( $settings['reading_bar_enabled'] ) && empty( $settings['reading_time_enabled'] ) ) {
			return;
		}

		if ( ! empty( $settings['reading_bar_enabled'] ) ) {
			$bar_color = ! empty( $settings['reading_bar_color'] ) ? sanitize_hex_color( $settings['reading_bar_color'] ) : '#2563eb';
			if ( ! $bar_color ) {
				$bar_color = '#2563eb';
			}

			add_action( 'wp_footer', function() use ( $bar_color ) {
				?>
				<!-- WP Agency Toolkit - Reading Progress Bar -->
				<style>
					#wpat-reading-progress-bar {
						position: fixed;
						top: 0;
						left: 0;
						width: 0%;
						height: 4px;
						background-color: <?php echo esc_attr( $bar_color ); ?>;
						z-index: 999999;
						transition: width 0.1s ease-out;
						pointer-events: none;
					}
				</style>
				<div id="wpat-reading-progress-bar"></div>
				<script>
					(function() {
						var progressBar = document.getElementById('wpat-reading-progress-bar');
						if (!progressBar) return;
						var ticking = false;

						function updateProgress() {
							var totalHeight = document.documentElement.scrollHeight - window.innerHeight;
							if (totalHeight <= 0) {
								progressBar.style.width = '0%';
								return;
							}
							var currentScroll = window.scrollY || window.pageYOffset;
							var progress = (currentScroll / totalHeight) * 100;
							progressBar.style.width = Math.min(100, Math.max(0, progress)) + '%';
							ticking = false;
						}

						window.addEventListener('scroll', function() {
							if (!ticking) {
								window.requestAnimationFrame(updateProgress);
								ticking = true;
							}
						});
					})();
				</script>
				<?php
			}, 99 );
		}
	}

	/**
	 * Calcula el tiempo estimado de lectura en minutos.
	 *
	 * @param string $content
	 * @return int Minutos estimados
	 */
	public static function calculate_reading_time( $content ) {
		$clean_text = wp_strip_all_tags( strip_shortcodes( $content ) );
		$word_count = count( preg_split( '/\s+/', trim( $clean_text ) ) );
		// Promedio de lectura: 200 palabras por minuto
		$minutes = (int) ceil( $word_count / 200 );
		return max( 1, $minutes );
	}

	/**
	 * Inserta el distintivo de tiempo de lectura al inicio de la entrada si está activado.
	 *
	 * @param string $content
	 * @return string
	 */
	public function prepend_reading_time( $content ) {
		if ( ! is_single() || ! in_the_loop() || ! is_main_query() || is_admin() ) {
			return $content;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['reading_time_enabled'] ) ) {
			return $content;
		}

		$minutes = self::calculate_reading_time( $content );
		$badge_html = sprintf(
			'<div class="wpat-reading-time-badge" style="display:inline-flex; align-items:center; gap:6px; background:#F3F4F6; color:#374151; padding:6px 12px; border-radius:20px; font-size:13px; font-weight:500; margin-bottom:16px;">
				<svg style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
				<span>Tiempo de lectura: %d min</span>
			</div>',
			$minutes
		);

		return $badge_html . $content;
	}

	/**
	 * Shortcode [tiempo_lectura] para renderizar el distintivo en cualquier lugar de la plantilla o post.
	 */
	public function reading_time_shortcode() {
		global $post;
		if ( ! $post ) {
			return '';
		}
		$minutes = self::calculate_reading_time( $post->post_content );
		return sprintf(
			'<span class="wpat-reading-time-shortcode" style="display:inline-flex; align-items:center; gap:4px; font-size:13px; color:#6B7280;">
				<svg style="width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
				%d min de lectura
			</span>',
			$minutes
		);
	}
}

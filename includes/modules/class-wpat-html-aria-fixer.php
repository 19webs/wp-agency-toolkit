<?php
/**
 * Módulo: Corrector Técnico de Código HTML y ARIA (Silencioso & WCAG 2.1) - WP Agency Toolkit
 *
 * Sanea y repara automáticamente en segundo plano inconsistencias de roles ARIA,
 * trampas de foco en teclado, nombres accesibles en botones/enlaces de solo icono
 * e inputs sin etiqueta, garantizando cumplimiento W3C y puntuaciones de 95-100 en Google Lighthouse.
 *
 * 100% silencioso e invisible: Cero botones flotantes ni alteraciones visuales.
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_HTML_ARIA_Fixer {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_HTML_ARIA_Fixer|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia única.
	 *
	 * @return WPAT_HTML_ARIA_Fixer
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
		// Encolar script ultra-ligero de saneamiento en el frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 99 );

		// Filtrado en servidor del buffer HTML para Google PageSpeed / Lighthouse y SEO
		add_action( 'template_redirect', array( $this, 'start_output_buffer' ), 1 );
	}

	/**
	 * Inicia el búfer de salida para sanear el HTML en servidor antes de enviarlo al cliente.
	 */
	public function start_output_buffer() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : get_option( 'wpat_settings', array() );
		$is_active = isset( $settings['html-aria-fixer'] ) && '1' === (string) $settings['html-aria-fixer'];
		if ( ! $is_active ) {
			return;
		}

		ob_start( array( $this, 'filter_page_html' ) );
	}

	/**
	 * Sanea atributos ARIA y roles incompatibles en el HTML renderizado por el servidor.
	 *
	 * @param string $html Contenido HTML de la página.
	 * @return string HTML saneado.
	 */
	public function filter_page_html( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		$settings  = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : get_option( 'wpat_settings', array() );
		$fix_roles = ! isset( $settings['aria_fix_roles'] ) || '1' === (string) $settings['aria_fix_roles'];
		$fix_decor = ! isset( $settings['aria_fix_decorative_imgs'] ) || '1' === (string) $settings['aria_fix_decorative_imgs'];

		if ( $fix_roles ) {
			// 1. Eliminar role="listitem" y roles incompatibles en <article> (Fallo crítico en Elementor)
			$html = preg_replace_callback( '/<article\b([^>]*?)>/i', function( $matches ) {
				$attrs = preg_replace( '/\srole=["\'](?:listitem|button|link|navigation|banner)["\']/i', '', $matches[1] );
				return '<article' . $attrs . '>';
			}, $html );

			// 2. Eliminar role="list" en contenedores de posts de Elementor para evitar conflicto aria-required-children
			$html = preg_replace_callback( '/<([a-z0-9_-]+)\b([^>]*?\bclass=["\'][^"\']*(?:elementor-posts-container|elementor-posts|elementor-grid)[^"\']*["\'][^>]*?)>/i', function( $matches ) {
				$tag   = $matches[1];
				$attrs = preg_replace( '/\srole=["\']list["\']/i', '', $matches[2] );
				return '<' . $tag . $attrs . '>';
			}, $html );

			// 3. Eliminar role="listitem" en <section>, <header>, <nav>, <footer>, <main>, <aside>
			$html = preg_replace_callback( '/<(section|header|nav|footer|main|aside)\b([^>]*?)>/i', function( $matches ) {
				$tag   = $matches[1];
				$attrs = preg_replace( '/\srole=["\'](?:listitem)["\']/i', '', $matches[2] );
				$tag_l = strtolower( $tag );
				if ( 'header' === $tag_l || 'footer' === $tag_l ) {
					$attrs = preg_replace( '/\srole=["\'](?:navigation|button)["\']/i', '', $attrs );
				} elseif ( 'nav' === $tag_l ) {
					$attrs = preg_replace( '/\srole=["\'](?:banner|button)["\']/i', '', $attrs );
				}
				return '<' . $tag . $attrs . '>';
			}, $html );

			// 4. Enlaces de miniaturas con tabindex="-1": ocultar con aria-hidden="true" para que no dupliquen lectura del título
			$html = preg_replace_callback( '/<a\b([^>]*?\bclass=["\'][^"\']*elementor-post__thumbnail__link[^"\']*["\'][^>]*?)>/i', function( $matches ) {
				$attrs = $matches[1];
				$attrs = preg_replace( '/\saria-label=["\'][^"\']*["\']/i', '', $attrs );
				if ( stripos( $attrs, 'aria-hidden' ) === false ) {
					$attrs .= ' aria-hidden="true"';
				}
				return '<a' . $attrs . '>';
			}, $html );

			// 5. Eliminar cualquier etiqueta genérica "Enlace de navegación"
			$html = preg_replace( '/\saria-label=["\']Enlace de navegación["\']/i', '', $html );

			// 6. Eliminar role="presentation" o role="none" en <img> si tiene atributo alt no vacío
			$html = preg_replace_callback( '/<img\b([^>]*?)>/i', function( $matches ) {
				$attrs = $matches[1];
				if ( preg_match( '/\balt=["\']([^\'"]+)["\']/i', $attrs, $alt_m ) && trim( $alt_m[1] ) !== '' ) {
					$attrs = preg_replace( '/\srole=["\'](?:presentation|none)["\']/i', '', $attrs );
				}
				return '<img' . $attrs . '>';
			}, $html );

			// 7. Eliminar role="text" (en desuso y penalizado por W3C)
			$html = preg_replace( '/\srole=["\']text["\']/i', '', $html );

			// 8. Eliminar role="listitem" en <span> (ej. iconos sociales de Elementor que carecen de contenedor role="list")
			$html = preg_replace_callback( '/<span\b([^>]*?)>/i', function( $matches ) {
				$attrs = preg_replace( '/\srole=["\']listitem["\']/i', '', $matches[1] );
				return '<span' . $attrs . '>';
			}, $html );
		}

		if ( $fix_decor ) {
			// Asignar alt="" en servidor a <img> que no tengan atributo alt
			$html = preg_replace_callback( '/<img\b((?:(?!alt=)[^>])*)>/i', function( $matches ) {
				$attrs = rtrim( $matches[1] );
				return '<img' . $attrs . ' alt="">';
			}, $html );
		}

		return $html;
	}

	/**
	 * Encola el script de saneamiento ARIA en el frontend con la configuración activa.
	 */
	public function enqueue_frontend_scripts() {
		if ( is_admin() ) {
			return;
		}

		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : get_option( 'wpat_settings', array() );

		// Comprobar si el módulo está activo
		$is_active = isset( $settings['html-aria-fixer'] ) && '1' === (string) $settings['html-aria-fixer'];
		if ( ! $is_active ) {
			return;
		}

		$config = array(
			'fix_roles'           => ! isset( $settings['aria_fix_roles'] ) || '1' === (string) $settings['aria_fix_roles'],
			'fix_hidden'          => ! isset( $settings['aria_fix_hidden'] ) || '1' === (string) $settings['aria_fix_hidden'],
			'fix_empty_buttons'   => ! isset( $settings['aria_fix_empty_buttons'] ) || '1' === (string) $settings['aria_fix_empty_buttons'],
			'fix_empty_links'     => ! isset( $settings['aria_fix_empty_links'] ) || '1' === (string) $settings['aria_fix_empty_links'],
			'fix_inputs'          => ! isset( $settings['aria_fix_inputs'] ) || '1' === (string) $settings['aria_fix_inputs'],
			'fix_decorative_imgs' => ! isset( $settings['aria_fix_decorative_imgs'] ) || '1' === (string) $settings['aria_fix_decorative_imgs'],
		);

		wp_enqueue_script(
			'wpat-html-aria-fixer-js',
			WPAT_URL . 'assets/js/wpat-html-aria-fixer.js',
			array(),
			defined( 'WPAT_VERSION' ) ? WPAT_VERSION : '1.0.0',
			true
		);

		wp_localize_script(
			'wpat-html-aria-fixer-js',
			'wpatAriaConfig',
			$config
		);
	}
}

// Inicializar el módulo
WPAT_HTML_ARIA_Fixer::get_instance();

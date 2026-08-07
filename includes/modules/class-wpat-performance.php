<?php
/**
 * Módulo: Optimización de Rendimiento - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Performance {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Performance
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Performance
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
		// 1. Desactivar Emojis
		add_action( 'init', array( $this, 'disable_emojis' ) );

		// 2. Limpieza de cabecera <head>
		add_action( 'init', array( $this, 'cleanup_head' ) );

		// 3. Limitar Revisiones de Post
		add_filter( 'wp_revisions_to_keep', array( $this, 'limit_post_revisions' ), 10, 2 );
	}

	/**
	 * Deshabilita los emojis nativos de WordPress.
	 */
	public function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		
		// Desactivar en el editor TinyMCE
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_tinymce' ) );
		
		// Remover DNS prefetch de emojis
		add_filter( 'wp_resource_hints', array( $this, 'disable_emojis_dns_prefetch' ), 10, 2 );
	}

	/**
	 * Remueve el plugin de emojis de TinyMCE.
	 *
	 * @param array $plugins Lista de plugins de TinyMCE.
	 * @return array
	 */
	public function disable_emojis_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		}
		return array();
	}

	/**
	 * Elimina la precarga de DNS para las imágenes de emojis de s.w.org.
	 *
	 * @param array  $urls Lista de URLs de prefetch.
	 * @param string $relation_type Tipo de relación.
	 * @return array
	 */
	public function disable_emojis_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' );
			$urls = array_diff( $urls, array( $emoji_svg_url ) );
		}
		return $urls;
	}

	/**
	 * Remueve tags y enlaces sobrantes del <head> de WordPress.
	 */
	public function cleanup_head() {
		// RSD Link (Really Simple Discovery) - usado por clientes XML-RPC externos
		remove_action( 'wp_head', 'rsd_link' );
		
		// Windows Live Writer manifest
		remove_action( 'wp_head', 'wlwmanifest_link' );
		
		// Meta etiqueta WordPress Generator (Muestra la versión de WP activa, riesgo de seguridad)
		remove_action( 'wp_head', 'wp_generator' );
		
		// Enlace corto del post actual
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		
		// Enlaces de descubrimiento de oEmbed (Evita inyección de scripts externos para incrustar)
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		
		// Cabecera de enlace de API REST de WordPress
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	}

	/**
	 * Limita el número de revisiones a guardar por entrada a un máximo de 5.
	 *
	 * @param int     $num Revisiones a mantener.
	 * @param WP_Post $post Objeto del post.
	 * @return int
	 */
	public function limit_post_revisions( $num, $post ) {
		return 5;
	}
}

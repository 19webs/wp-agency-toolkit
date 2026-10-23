<?php
/**
 * Módulo: Optimización de Rendimiento - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Performance {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Performance|null
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
		$settings = WPAT_Main::get_instance()->get_settings();

		// 1. Desactivar Emojis
		$disable_emojis = ! isset( $settings['perf_disable_emojis'] ) || '1' === (string) $settings['perf_disable_emojis'];
		if ( $disable_emojis ) {
			add_action( 'init', array( $this, 'disable_emojis' ) );
		}

		// 2. Limpieza de cabecera <head>
		$cleanup_head = ! isset( $settings['perf_cleanup_head'] ) || '1' === (string) $settings['perf_cleanup_head'];
		if ( $cleanup_head ) {
			add_action( 'init', array( $this, 'cleanup_head' ) );
		}

		// 3. Control de Heartbeat API
		$heartbeat = isset( $settings['perf_heartbeat_control'] ) ? $settings['perf_heartbeat_control'] : 'slow';
		if ( 'default' !== $heartbeat ) {
			add_action( 'init', array( $this, 'control_heartbeat_api' ), 1 );
		}

		// 4. Desactivar jQuery Migrate en Frontend
		$disable_jq_migrate = ! isset( $settings['perf_disable_jquery_migrate'] ) || '1' === (string) $settings['perf_disable_jquery_migrate'];
		if ( $disable_jq_migrate ) {
			add_action( 'wp_default_scripts', array( $this, 'dequeue_jquery_migrate' ) );
		}

		// 5. Desactivar Cart Fragments de WooCommerce en páginas no comerciales
		$disable_cart_frag = ! isset( $settings['perf_disable_wc_cart_fragments'] ) || '1' === (string) $settings['perf_disable_wc_cart_fragments'];
		if ( $disable_cart_frag ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'optimize_wc_cart_fragments' ), 99 );
		}

		// 6. Limitar o desactivar Revisiones de Entradas
		add_filter( 'wp_revisions_to_keep', array( $this, 'limit_post_revisions' ), 10, 2 );

		// 7. Modificar intervalo de autoguardado si es superior a 60s
		$autosave_sec = isset( $settings['perf_autosave_interval'] ) ? (int) $settings['perf_autosave_interval'] : 180;
		if ( $autosave_sec > 60 && ! defined( 'AUTOSAVE_INTERVAL' ) ) {
			define( 'AUTOSAVE_INTERVAL', $autosave_sec );
		}

		// 8. Desactivar Dashicons en frontend para visitantes no logueados
		$disable_dashicons = ! isset( $settings['perf_disable_dashicons'] ) || '1' === (string) $settings['perf_disable_dashicons'];
		if ( $disable_dashicons ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_dashicons_frontend' ), 100 );
		}

		// 9. Desactivar Embeds e incrustaciones interactivas
		$disable_embeds = ! isset( $settings['perf_disable_embeds'] ) || '1' === (string) $settings['perf_disable_embeds'];
		if ( $disable_embeds ) {
			add_action( 'init', array( $this, 'disable_embeds_scripts' ), 9999 );
		}

		// 10. Acelerador Core Web Vitals: Estabilizador Tipografías Google Fonts (display=swap y preconnect)
		$font_swap = ! isset( $settings['perf_font_swap'] ) || '1' === (string) $settings['perf_font_swap'];
		if ( $font_swap ) {
			add_filter( 'style_loader_src', array( $this, 'add_font_display_swap' ), 20 );
			add_action( 'wp_head', array( $this, 'preconnect_google_fonts' ), 1 );
			add_filter( 'wp_resource_hints', array( $this, 'add_font_resource_hints' ), 10, 2 );
		}

		// 11. Acelerador Core Web Vitals: Preload de Hero Image (LCP)
		$lcp_priority = ! isset( $settings['perf_lcp_priority'] ) || '1' === (string) $settings['perf_lcp_priority'];
		if ( $lcp_priority ) {
			add_action( 'wp_head', array( $this, 'preload_hero_image' ), 2 );
		}

		// 12. Acelerador Core Web Vitals: Anti-CLS y LCP mediante Búfer y Filtros de Contenido
		$anti_cls = ! isset( $settings['perf_anti_cls'] ) || '1' === (string) $settings['perf_anti_cls'];
		if ( $anti_cls || $lcp_priority ) {
			add_action( 'template_redirect', array( $this, 'start_cwv_buffer' ), 1 );

			if ( $anti_cls ) {
				add_filter( 'the_content', array( $this, 'filter_content_images_dimensions' ), 999 );
				add_filter( 'post_thumbnail_html', array( $this, 'filter_content_images_dimensions' ), 999 );
			}
		}

		// Endpoints AJAX para mantenimiento y limpieza de base de datos
		add_action( 'wp_ajax_wpat_perf_get_db_stats', array( $this, 'ajax_get_db_stats' ) );
		add_action( 'wp_ajax_wpat_perf_clean_revisions', array( $this, 'ajax_clean_revisions' ) );
		add_action( 'wp_ajax_wpat_perf_clean_transients', array( $this, 'ajax_clean_transients' ) );
		add_action( 'wp_ajax_wpat_perf_clean_trash', array( $this, 'ajax_clean_trash' ) );
		add_action( 'wp_ajax_wpat_perf_optimize_tables', array( $this, 'ajax_optimize_tables' ) );
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
	 * @param array  $urls          Lista de URLs de prefetch.
	 * @param string $relation_type Tipo de relación.
	 * @return array
	 */
	public function disable_emojis_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/' );
			$urls          = array_diff( $urls, array( $emoji_svg_url ) );
		}
		return $urls;
	}

	/**
	 * Remueve tags y enlaces sobrantes del <head> de WordPress.
	 */
	public function cleanup_head() {
		// RSD Link (Really Simple Discovery)
		remove_action( 'wp_head', 'rsd_link' );

		// Windows Live Writer manifest
		remove_action( 'wp_head', 'wlwmanifest_link' );

		// Meta etiqueta WordPress Generator
		remove_action( 'wp_head', 'wp_generator' );

		// Enlace corto del post actual
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );

		// Enlaces de descubrimiento de oEmbed
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

		// Cabecera de enlace de API REST de WordPress en frontend
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}

	/**
	 * Controla y optimiza el consumo de recursos de la API Heartbeat de WordPress.
	 */
	public function control_heartbeat_api() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$mode     = isset( $settings['perf_heartbeat_control'] ) ? $settings['perf_heartbeat_control'] : 'slow';

		if ( 'disable_frontend' === $mode ) {
			add_action(
				'wp_enqueue_scripts',
				function() {
					wp_deregister_script( 'heartbeat' );
				},
				1
			);
		} elseif ( 'disable_all' === $mode ) {
			add_action(
				'init',
				function() {
					global $pagenow;
					if ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
						wp_deregister_script( 'heartbeat' );
					}
				},
				1
			);
		} elseif ( 'slow' === $mode ) {
			// Ralentizar la frecuencia a 60s en edición y 120s en admin
			add_filter(
				'heartbeat_settings',
				function( $settings ) {
					$settings['interval'] = 60;
					return $settings;
				}
			);
		}
	}

	/**
	 * Desencola jQuery Migrate en el frontend para evitar peticiones bloqueantes innecesarias.
	 *
	 * @param WP_Scripts $scripts Objeto de scripts de WordPress.
	 */
	public function dequeue_jquery_migrate( $scripts ) {
		if ( ! is_admin() && ! empty( $scripts->registered['jquery'] ) ) {
			$jquery = $scripts->registered['jquery'];
			if ( ! empty( $jquery->deps ) ) {
				$jquery->deps = array_diff( $jquery->deps, array( 'jquery-migrate' ) );
			}
		}
	}

	/**
	 * Desactiva el script Cart Fragments de WooCommerce en páginas donde no hay tienda ni carrito.
	 */
	public function optimize_wc_cart_fragments() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Si estamos en el carrito, finalizar compra o página de producto de WooCommerce, mantenerlo
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}

		// En páginas estándar (inicio, blog, contacto, etc.), si el carrito está vacío, no ejecutar AJAX fragments
		if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->is_empty() ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}
	}

	/**
	 * Limita el número de revisiones a guardar por entrada según la configuración.
	 *
	 * @param int     $num  Revisiones a mantener.
	 * @param WP_Post $post Objeto del post.
	 * @return int
	 */
	public function limit_post_revisions( $num, $post ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		$limit    = isset( $settings['perf_limit_revisions'] ) ? $settings['perf_limit_revisions'] : '5';

		if ( 'unlimited' === $limit ) {
			return -1;
		}

		return (int) $limit;
	}

	/**
	 * Desencola Dashicons en el frontend para visitantes que no hayan iniciado sesión.
	 */
	public function dequeue_dashicons_frontend() {
		if ( ! is_user_logged_in() && ! is_admin_bar_showing() ) {
			wp_deregister_style( 'dashicons' );
		}
	}

	/**
	 * Desactiva la carga de scripts de incrustación (oEmbed wp-embed.min.js).
	 */
	public function disable_embeds_scripts() {
		// Desactivar script de frontend
		wp_deregister_script( 'wp-embed' );

		// Remover filtros de oEmbed
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
	}

	/**
	 * AJAX: Obtiene estadísticas en vivo de la base de datos para la tarjeta de mantenimiento.
	 */
	public function ajax_get_db_stats() {
		check_ajax_referer( 'wpat_perf_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		global $wpdb;

		// 1. Conteo de revisiones
		$revisions_count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

		// 2. Conteo de transients caducados
		$time               = time();
		$expired_transients = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(option_id) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				'_transient_timeout_%',
				$time
			)
		);

		// 3. Conteo de entradas y comentarios en la papelera
		$trashed_posts    = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
		$trashed_comments = (int) $wpdb->get_var( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

		// 4. Espacio recuperable (overhead) en tablas
		$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
		$overhead_bytes = 0;
		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$overhead_bytes += isset( $table->Data_free ) ? (int) $table->Data_free : 0;
			}
		}

		$overhead_formatted = size_format( $overhead_bytes, 2 );

		wp_send_json_success(
			array(
				'revisions'          => $revisions_count,
				'expired_transients' => $expired_transients,
				'trashed_total'      => ( $trashed_posts + $trashed_comments ),
				'overhead'           => $overhead_formatted,
			)
		);
	}

	/**
	 * AJAX: Purga todas las revisiones antiguas de la base de datos.
	 */
	public function ajax_clean_revisions() {
		check_ajax_referer( 'wpat_perf_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		global $wpdb;

		// Eliminar postmeta de revisiones
		$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_type = 'revision'" );

		// Eliminar revisiones
		$deleted = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );

		wp_send_json_success( array( 'deleted' => (int) $deleted ) );
	}

	/**
	 * AJAX: Purga los transients caducados y huérfanos.
	 */
	public function ajax_clean_transients() {
		check_ajax_referer( 'wpat_perf_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		global $wpdb;

		$time = time();

		// Obtener nombres de transients caducados
		$expired = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				'_transient_timeout_%',
				$time
			)
		);

		$count = 0;
		if ( ! empty( $expired ) ) {
			foreach ( $expired as $transient_timeout ) {
				$transient_name = str_replace( '_transient_timeout_', '', $transient_timeout );
				delete_transient( $transient_name );
				$count++;
			}
		}

		wp_send_json_success( array( 'deleted' => $count ) );
	}

	/**
	 * AJAX: Vacía entradas y comentarios en la papelera.
	 */
	public function ajax_clean_trash() {
		check_ajax_referer( 'wpat_perf_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		global $wpdb;

		// Eliminar posts en papelera y sus metadatos
		$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_status = 'trash'" );
		$deleted_posts = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );

		// Eliminar comentarios en papelera
		$deleted_comments = $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

		wp_send_json_success( array( 'deleted' => ( (int) $deleted_posts + (int) $deleted_comments ) ) );
	}

	/**
	 * AJAX: Optimiza las tablas de la base de datos de WordPress.
	 */
	public function ajax_optimize_tables() {
		check_ajax_referer( 'wpat_perf_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		global $wpdb;

		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );

		if ( ! empty( $tables ) ) {
			foreach ( $tables as $table ) {
				$wpdb->query( "OPTIMIZE TABLE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		wp_send_json_success( array( 'optimized_count' => count( $tables ) ) );
	}

	/**
	 * Fuerza el parámetro display=swap en las hojas de estilo de Google Fonts.
	 *
	 * @param string $src URL del recurso de estilo.
	 * @return string
	 */
	public function add_font_display_swap( $src ) {
		if ( false !== strpos( $src, 'fonts.googleapis.com' ) ) {
			if ( false === strpos( $src, 'display=' ) ) {
				$src = add_query_arg( 'display', 'swap', $src );
			}
		}
		return $src;
	}

	/**
	 * Inyecta pre-conexiones a Google Fonts en el <head> para reducir la latencia de DNS/TLS.
	 */
	public function preconnect_google_fonts() {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	}

	/**
	 * Añade sugerencias de recursos nativas de WordPress para preconnect a Google Fonts.
	 *
	 * @param array  $urls          URLs a procesar.
	 * @param string $relation_type Tipo de relación ('preconnect', 'dns-prefetch', etc.).
	 * @return array
	 */
	public function add_font_resource_hints( $urls, $relation_type ) {
		if ( 'preconnect' === $relation_type ) {
			$urls[] = array(
				'href' => 'https://fonts.googleapis.com',
			);
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}
		return $urls;
	}

	/**
	 * Pre-carga la imagen principal visible (Hero / Destacada) en el <head> para optimizar el LCP.
	 */
	public function preload_hero_image() {
		if ( is_admin() || is_feed() || wp_doing_ajax() ) {
			return;
		}

		$image_url = '';

		if ( is_singular() && has_post_thumbnail() ) {
			$image_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
		} elseif ( is_front_page() ) {
			$custom_logo_id = get_theme_mod( 'custom_logo' );
			if ( $custom_logo_id ) {
				$image_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			}
		}

		if ( ! empty( $image_url ) ) {
			echo '<link rel="preload" as="image" href="' . esc_url( $image_url ) . '" fetchpriority="high">' . "\n";
		}
	}

	/**
	 * Inicia el búfer de salida para procesar optimizaciones de Core Web Vitals en el frontend.
	 */
	public function start_cwv_buffer() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		// Evitar interferir en modo edición o previsualización de maquetadores
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['et_fb'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['preview'] ) ) {
			return;
		}

		ob_start( array( $this, 'filter_cwv_buffer' ) );
	}

	/**
	 * Búfer de salida para Core Web Vitals (Anti-CLS y LCP Priority).
	 *
	 * @param string $html Contenido HTML generado.
	 * @return string
	 */
	public function filter_cwv_buffer( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		// Solo procesar si contiene documento HTML válido
		if ( false === stripos( $html, '<html' ) && false === stripos( $html, '<body' ) ) {
			return $html;
		}

		$settings     = WPAT_Main::get_instance()->get_settings();
		$anti_cls     = ! isset( $settings['perf_anti_cls'] ) || '1' === (string) $settings['perf_anti_cls'];
		$lcp_priority = ! isset( $settings['perf_lcp_priority'] ) || '1' === (string) $settings['perf_lcp_priority'];

		if ( $lcp_priority ) {
			$html = $this->optimize_lcp_first_image( $html );
		}

		if ( $anti_cls ) {
			$html = $this->add_missing_image_dimensions( $html );
		}

		return $html;
	}

	/**
	 * Optimiza la primera imagen de contenido visible (Above the Fold) para acelerar el LCP.
	 * Remueve loading="lazy" e inyecta fetchpriority="high".
	 *
	 * @param string $html
	 * @return string
	 */
	public function optimize_lcp_first_image( $html ) {
		$body_pos = stripos( $html, '<body' );
		if ( false === $body_pos ) {
			return $html;
		}

		$head_part = substr( $html, 0, $body_pos );
		$body_part = substr( $html, $body_pos );

		if ( preg_match_all( '/<img\s+[^>]+>/i', $body_part, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				$img_tag = $match[0];
				$offset  = $match[1];

				// Ignorar píxeles de rastreo (1x1), elementos ocultos o avatares diminutos
				if ( preg_match( '/(?:width|height)=["\']?(?:0|1|2|3|4|5|10|16|20)["\']?/i', $img_tag ) ) {
					continue;
				}
				if ( false !== stripos( $img_tag, 'display:none' ) || false !== stripos( $img_tag, 'visibility:hidden' ) ) {
					continue;
				}
				if ( false !== stripos( $img_tag, 'avatar' ) ) {
					continue;
				}

				// Encontramos la primera imagen candidata LCP
				$new_img_tag = $img_tag;

				// 1. Quitar loading="lazy" o cambiarlo a "eager"
				if ( preg_match( '/\s+loading=["\']?lazy["\']?/i', $new_img_tag ) ) {
					$new_img_tag = preg_replace( '/\s+loading=["\']?lazy["\']?/i', ' loading="eager"', $new_img_tag );
				} elseif ( false === stripos( $new_img_tag, 'loading=' ) ) {
					$new_img_tag = preg_replace( '/<img\s+/i', '<img loading="eager" ', $new_img_tag, 1 );
				}

				// 2. Inyectar fetchpriority="high"
				if ( false === stripos( $new_img_tag, 'fetchpriority=' ) ) {
					$new_img_tag = preg_replace( '/<img\s+/i', '<img fetchpriority="high" ', $new_img_tag, 1 );
				} else {
					$new_img_tag = preg_replace( '/\s+fetchpriority=["\']?[^"\'>\s]+["\']?/i', ' fetchpriority="high"', $new_img_tag );
				}

				// 3. Reemplazar decoding="async" a "sync" para la primera imagen visible
				$new_img_tag = preg_replace( '/\s+decoding=["\']?async["\']?/i', ' decoding="sync"', $new_img_tag );

				$body_part = substr_replace( $body_part, $new_img_tag, $offset, strlen( $img_tag ) );
				break;
			}
		}

		return $head_part . $body_part;
	}

	/**
	 * Caché en memoria de dimensiones calculadas para optimizar rendimiento.
	 *
	 * @var array
	 */
	private static $dimensions_cache = array();

	/**
	 * Añade atributos width y height a imágenes que carecen de ellos para prevenir CLS.
	 *
	 * @param string $html
	 * @return string
	 */
	public function add_missing_image_dimensions( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		if ( ! preg_match_all( '/<img\s+[^>]+>/i', $html, $matches ) ) {
			return $html;
		}

		$upload_dir = wp_upload_dir();
		$base_url   = isset( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : '';
		$base_dir   = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';

		$replaced = array();

		foreach ( $matches[0] as $img_tag ) {
			// Si ya tiene width Y height válidos, omitir
			$has_width  = preg_match( '/\bwidth=["\']?[0-9]+%?["\']?/i', $img_tag );
			$has_height = preg_match( '/\bheight=["\']?[0-9]+%?["\']?/i', $img_tag );

			if ( $has_width && $has_height ) {
				continue;
			}

			if ( isset( $replaced[ $img_tag ] ) ) {
				continue;
			}

			// Extraer atributo src
			if ( ! preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $img_tag, $src_match ) ) {
				continue;
			}

			$src = $src_match[1];

			// Omitir SVG, base64 data URIs o urls vacías
			if ( empty( $src ) || false !== stripos( $src, '.svg' ) || 0 === stripos( $src, 'data:image/' ) ) {
				continue;
			}

			$width  = null;
			$height = null;

			// Verificar caché en memoria
			if ( isset( self::$dimensions_cache[ $src ] ) ) {
				$width  = self::$dimensions_cache[ $src ]['w'];
				$height = self::$dimensions_cache[ $src ]['h'];
			}

			// 1. Intentar resolver mediante clase wp-image-{id}
			if ( ! $width && preg_match( '/\bwp-image-([0-9]+)\b/i', $img_tag, $class_match ) ) {
				$attachment_id = (int) $class_match[1];
				if ( $attachment_id > 0 ) {
					$meta = wp_get_attachment_metadata( $attachment_id );
					if ( is_array( $meta ) && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
						$width  = (int) $meta['width'];
						$height = (int) $meta['height'];

						if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
							$file_basename = wp_basename( $src );
							foreach ( $meta['sizes'] as $size_data ) {
								if ( isset( $size_data['file'] ) && $size_data['file'] === $file_basename ) {
									$width  = (int) $size_data['width'];
									$height = (int) $size_data['height'];
									break;
								}
							}
						}
					}
				}
			}

			// 2. Intentar deducir dimensiones del patrón del nombre del archivo: ejemplo-800x600.jpg
			if ( ! $width && preg_match( '/-([0-9]{2,4})x([0-9]{2,4})\.(jpe?g|png|webp|avif|gif)(?:\?.*)?$/i', $src, $dim_match ) ) {
				$width  = (int) $dim_match[1];
				$height = (int) $dim_match[2];
			}

			// 3. Si es una imagen local en uploads pero no tiene wp-image-ID, obtener tamaño local
			if ( ! $width && ! empty( $base_url ) && 0 === strpos( $src, $base_url ) ) {
				$local_path = str_replace( $base_url, $base_dir, $src );
				$local_path = preg_replace( '/\?.*$/', '', $local_path );

				if ( file_exists( $local_path ) ) {
					$img_info = @getimagesize( $local_path );
					if ( is_array( $img_info ) && ! empty( $img_info[0] ) && ! empty( $img_info[1] ) ) {
						$width  = (int) $img_info[0];
						$height = (int) $img_info[1];
					}
				}
			}

			// Si obtuvimos dimensiones, inyectarlas
			if ( $width && $height ) {
				self::$dimensions_cache[ $src ] = array(
					'w' => $width,
					'h' => $height,
				);

				$new_tag = $img_tag;
				if ( ! $has_width ) {
					$new_tag = preg_replace( '/<img\s+/i', '<img width="' . $width . '" ', $new_tag, 1 );
				}
				if ( ! $has_height ) {
					$new_tag = preg_replace( '/<img\s+/i', '<img height="' . $height . '" ', $new_tag, 1 );
				}

				if ( $new_tag !== $img_tag ) {
					$html = str_replace( $img_tag, $new_tag, $html );
					$replaced[ $img_tag ] = true;
				}
			}
		}

		return $html;
	}

	/**
	 * Filtro directo para the_content y post_thumbnail_html.
	 *
	 * @param string $content
	 * @return string
	 */
	public function filter_content_images_dimensions( $content ) {
		return $this->add_missing_image_dimensions( $content );
	}

	/**
	 * Diagnóstico y salud del servidor para la tarjeta de Rendimiento.
	 *
	 * @return array
	 */
	public static function get_server_performance_diagnostics() {
		// 1. Versión de PHP
		$php_version = PHP_VERSION;
		$php_status  = version_compare( $php_version, '8.1', '>=' ) ? 'good' : ( version_compare( $php_version, '7.4', '<=' ) ? 'bad' : 'warning' );

		// 2. OPcache
		$opcache_enabled  = false;
		$opcache_hit_rate = 0;
		if ( function_exists( 'opcache_get_status' ) && ini_get( 'opcache.enable' ) ) {
			$op_status = @opcache_get_status( false );
			if ( is_array( $op_status ) && ! empty( $op_status['opcache_enabled'] ) ) {
				$opcache_enabled = true;
				if ( isset( $op_status['opcache_statistics']['opcache_hit_rate'] ) ) {
					$opcache_hit_rate = round( (float) $op_status['opcache_statistics']['opcache_hit_rate'], 1 );
				}
			}
		}

		// 3. Memoria PHP
		$memory_limit       = ini_get( 'memory_limit' );
		$memory_limit_bytes = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( $memory_limit ) : 0;
		$wp_memory_limit    = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
		$memory_status      = $memory_limit_bytes >= ( 256 * 1024 * 1024 ) ? 'good' : ( $memory_limit_bytes >= ( 128 * 1024 * 1024 ) ? 'warning' : 'bad' );

		// 4. Compresión HTTP (Gzip / Brotli)
		$has_gzip   = extension_loaded( 'zlib' ) || (bool) ini_get( 'zlib.output_compression' ) || in_array( 'ob_gzhandler', ob_list_handlers(), true );
		$has_brotli = extension_loaded( 'brotli' ) || function_exists( 'brotli_compress' );

		// 5. Soporte WebP / AVIF
		$has_imagick    = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
		$webp_supported = function_exists( 'imagewebp' ) || ( $has_imagick && in_array( 'WEBP', Imagick::queryFormats(), true ) );
		$avif_supported = function_exists( 'imageavif' ) || ( $has_imagick && in_array( 'AVIF', Imagick::queryFormats(), true ) );

		// 6. Micro-benchmark Base de Datos
		global $wpdb;
		$db_latency_ms     = 0.0;
		$db_latency_status = 'good';
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			$db_start = microtime( true );
			$wpdb->get_var( 'SELECT 1' );
			$db_latency_ms = round( ( microtime( true ) - $db_start ) * 1000, 2 );
			if ( $db_latency_ms > 10.0 ) {
				$db_latency_status = 'bad';
			} elseif ( $db_latency_ms > 3.0 ) {
				$db_latency_status = 'warning';
			} else {
				$db_latency_status = 'good';
			}
		}

		return array(
			'php_version'       => $php_version,
			'php_status'        => $php_status,
			'opcache_enabled'   => $opcache_enabled,
			'opcache_hit_rate'  => $opcache_hit_rate,
			'memory_limit'      => $memory_limit,
			'wp_memory_limit'   => $wp_memory_limit,
			'memory_status'     => $memory_status,
			'has_gzip'          => $has_gzip,
			'has_brotli'        => $has_brotli,
			'webp_supported'    => $webp_supported,
			'avif_supported'    => $avif_supported,
			'db_latency_ms'     => $db_latency_ms,
			'db_latency_status' => $db_latency_status,
		);
	}
}

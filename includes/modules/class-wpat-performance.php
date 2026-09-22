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
}

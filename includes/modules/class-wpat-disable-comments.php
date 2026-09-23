<?php
/**
 * Módulo: Deshabilitar Comentarios Globales y Anti-Spam - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Disable_Comments {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Disable_Comments|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Disable_Comments
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
		// 1. Acciones tempranas de inicialización y bloqueo de POST directo
		add_action( 'init', array( $this, 'disable_comments_on_init' ), 99 );
		add_action( 'admin_init', array( $this, 'disable_comments_on_admin_init' ) );

		// 2. Modificaciones visuales en el panel de administración
		add_action( 'admin_menu', array( $this, 'remove_comments_admin_menu' ) );
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_comments_admin_bar' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'remove_comments_dashboard_widget' ) );
		add_filter( 'admin_comment_types_dropdown', '__return_empty_array' );

		// 3. Remover columna de comentarios en listas de entradas y páginas
		add_filter( 'manage_posts_columns', array( $this, 'filter_manage_posts_columns' ), 10, 2 );
		add_filter( 'manage_pages_columns', array( $this, 'filter_manage_posts_columns' ), 10, 2 );

		// 4. Filtros de frontend para cerrar discusiones, pingbacks y ocultar comentarios existentes
		add_filter( 'comments_open', array( $this, 'filter_comments_open' ), 20, 2 );
		add_filter( 'pings_open', array( $this, 'filter_comments_open' ), 20, 2 );
		add_filter( 'comments_array', array( $this, 'filter_comments_array' ), 20, 2 );
		add_filter( 'comments_template', array( $this, 'filter_comments_template' ), 20 );

		// 5. Desregistrar widgets de comentarios
		add_action( 'widgets_init', array( $this, 'disable_recent_comments_widget' ) );

		// 6. Bloquear feeds RSS de comentarios
		add_action( 'template_redirect', array( $this, 'filter_comment_feeds' ), 9 );
		add_filter( 'feed_links_show_comments_feed', '__return_false' );

		// 7. Bloquear endpoints de comentarios en la API REST de WordPress
		add_filter( 'rest_endpoints', array( $this, 'filter_rest_endpoints' ) );

		// 8. Bloquear métodos Pingback en XML-RPC
		add_filter( 'xmlrpc_methods', array( $this, 'filter_xmlrpc_methods' ) );

		// 9. Endpoints AJAX para conteo y purga de comentarios en base de datos
		add_action( 'wp_ajax_wpat_comments_get_stats', array( $this, 'ajax_get_comment_stats' ) );
		add_action( 'wp_ajax_wpat_comments_delete_all', array( $this, 'ajax_delete_all_comments' ) );
	}

	/**
	 * Comprueba si la desactivación global está activa.
	 *
	 * @return bool
	 */
	public static function is_global_disabled() {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( isset( $settings['disable_comments_mode'] ) ) {
			return 'global' === $settings['disable_comments_mode'];
		}
		return ! isset( $settings['disable_comments_global'] ) || '1' === (string) $settings['disable_comments_global'];
	}

	/**
	 * Comprueba si los comentarios deben estar deshabilitados para un tipo de post dado.
	 *
	 * @param string $post_type Tipo de contenido.
	 * @return bool
	 */
	public static function is_post_type_disabled( $post_type ) {
		$settings     = WPAT_Main::get_instance()->get_settings();
		$is_global    = self::is_global_disabled();
		$keep_reviews = isset( $settings['disable_comments_keep_reviews'] ) && '1' === (string) $settings['disable_comments_keep_reviews'];

		// Si WooCommerce está activo y el usuario desea preservar reseñas de productos
		if ( 'product' === $post_type && $keep_reviews ) {
			return false;
		}

		if ( $is_global ) {
			return true;
		}

		if ( 'post' === $post_type && isset( $settings['disable_comments_posts'] ) && '1' === (string) $settings['disable_comments_posts'] ) {
			return true;
		}
		if ( 'page' === $post_type && isset( $settings['disable_comments_pages'] ) && '1' === (string) $settings['disable_comments_pages'] ) {
			return true;
		}
		if ( 'attachment' === $post_type && isset( $settings['disable_comments_media'] ) && '1' === (string) $settings['disable_comments_media'] ) {
			return true;
		}

		// Custom Post Types adicionales seleccionados
		$cpts = isset( $settings['disable_comments_cpts'] ) && is_array( $settings['disable_comments_cpts'] ) ? $settings['disable_comments_cpts'] : array();
		if ( in_array( $post_type, $cpts, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Remueve el soporte nativo de comentarios y trackbacks y bloquea envíos directos a wp-comments-post.php.
	 */
	public function disable_comments_on_init() {
		// Bloqueo directo de peticiones POST a wp-comments-post.php
		global $pagenow;
		if ( 'wp-comments-post.php' === $pagenow ) {
			$post_id   = isset( $_POST['comment_post_ID'] ) ? absint( $_POST['comment_post_ID'] ) : 0;
			$post_type = $post_id ? get_post_type( $post_id ) : '';

			if ( ! $post_type || self::is_post_type_disabled( $post_type ) ) {
				wp_die(
					esc_html__( 'Los comentarios están deshabilitados en este sitio web.', 'wp-agency-toolkit' ),
					esc_html__( 'Comentarios Deshabilitados', 'wp-agency-toolkit' ),
					array( 'response' => 403 )
				);
			}
		}

		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $post_type ) {
			if ( self::is_post_type_disabled( $post_type ) ) {
				if ( post_type_supports( $post_type, 'comments' ) ) {
					remove_post_type_support( $post_type, 'comments' );
				}
				if ( post_type_supports( $post_type, 'trackbacks' ) ) {
					remove_post_type_support( $post_type, 'trackbacks' );
				}
			}
		}
	}

	/**
	 * Redirige accesos directos al panel de comentarios y oculta metaboxes de discusión en el editor.
	 */
	public function disable_comments_on_admin_init() {
		global $pagenow;

		if ( self::is_global_disabled() ) {
			if ( 'edit-comments.php' === $pagenow || 'comment.php' === $pagenow || 'options-discussion.php' === $pagenow ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}

		// Remover metaboxes de comentarios y trackbacks del editor clásico si aplica
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $post_type ) {
			if ( self::is_post_type_disabled( $post_type ) ) {
				remove_meta_box( 'commentstatusdiv', $post_type, 'normal' );
				remove_meta_box( 'commentsdiv', $post_type, 'normal' );
				remove_meta_box( 'trackbacksdiv', $post_type, 'normal' );
			}
		}
	}

	/**
	 * Remueve el menú "Comentarios" y "Ajustes de Discusión" de la barra lateral de administración.
	 */
	public function remove_comments_admin_menu() {
		if ( self::is_global_disabled() ) {
			remove_menu_page( 'edit-comments.php' );
			remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		}
	}

	/**
	 * Remueve el nodo de comentarios del menú de la barra superior de administración.
	 */
	public function remove_comments_admin_bar() {
		if ( self::is_global_disabled() ) {
			global $wp_admin_bar;
			if ( $wp_admin_bar ) {
				$wp_admin_bar->remove_menu( 'comments' );
			}
		}
	}

	/**
	 * Remueve el widget "Actividad / Comentarios recientes" del panel de control de WordPress.
	 */
	public function remove_comments_dashboard_widget() {
		if ( self::is_global_disabled() ) {
			remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
		}
	}

	/**
	 * Remueve la columna de comentarios en los listados de entradas, páginas y CPTs.
	 *
	 * @param array  $columns Columnas de la tabla.
	 * @param string $post_type Tipo de entrada.
	 * @return array
	 */
	public function filter_manage_posts_columns( $columns, $post_type = 'post' ) {
		if ( self::is_post_type_disabled( $post_type ) && isset( $columns['comments'] ) ) {
			unset( $columns['comments'] );
		}
		return $columns;
	}

	/**
	 * Fuerza el estado cerrado de los comentarios en el frontend según el tipo de post.
	 *
	 * @param bool $open    Estado de apertura.
	 * @param int  $post_id ID del post.
	 * @return bool
	 */
	public function filter_comments_open( $open, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $open;
		}

		if ( self::is_post_type_disabled( $post->post_type ) ) {
			return false;
		}

		return $open;
	}

	/**
	 * Oculta los comentarios ya existentes de la base de datos si el tipo de post los tiene desactivados.
	 *
	 * @param array $comments Array de comentarios.
	 * @param int   $post_id  ID del post.
	 * @return array
	 */
	public function filter_comments_array( $comments, $post_id ) {
		$post = get_post( $post_id );
		if ( $post && self::is_post_type_disabled( $post->post_type ) ) {
			return array();
		}
		return $comments;
	}

	/**
	 * Devuelve una plantilla de comentarios vacía para evitar que los temas pinten el formulario o encabezados.
	 *
	 * @param string $file Ruta de la plantilla original.
	 * @return string
	 */
	public function filter_comments_template( $file ) {
		global $post;
		if ( $post && self::is_post_type_disabled( $post->post_type ) ) {
			return WPAT_PATH . 'templates/empty-comments.php';
		}
		return $file;
	}

	/**
	 * Desregistra el widget nativo de WordPress de comentarios recientes.
	 */
	public function disable_recent_comments_widget() {
		if ( self::is_global_disabled() ) {
			unregister_widget( 'WP_Widget_Recent_Comments' );
		}
	}

	/**
	 * Redirige o responde 404 ante peticiones a feeds de comentarios.
	 */
	public function filter_comment_feeds() {
		if ( is_comment_feed() ) {
			wp_safe_redirect( home_url(), 301 );
			exit;
		}
	}

	/**
	 * Desactiva las rutas /wp/v2/comments de la API REST para evitar inyecciones directas de spambots.
	 *
	 * @param array $endpoints Lista de endpoints de la API REST.
	 * @return array
	 */
	public function filter_rest_endpoints( $endpoints ) {
		if ( self::is_global_disabled() ) {
			if ( isset( $endpoints['/wp/v2/comments'] ) ) {
				unset( $endpoints['/wp/v2/comments'] );
			}
			if ( isset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] ) ) {
				unset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );
			}
		}
		return $endpoints;
	}

	/**
	 * Bloquea los métodos Pingback de XML-RPC para mitigar ataques DDoS y comentarios no deseados.
	 *
	 * @param array $methods Métodos XML-RPC registrados.
	 * @return array
	 */
	public function filter_xmlrpc_methods( $methods ) {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * AJAX: Obtiene las estadísticas de comentarios en base de datos.
	 */
	public function ajax_get_comment_stats() {
		check_ajax_referer( 'wpat_comments_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		global $wpdb;

		$total    = (int) $wpdb->get_var( "SELECT COUNT(comment_ID) FROM {$wpdb->comments}" );
		$approved = (int) $wpdb->get_var( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = '1'" );
		$spam     = (int) $wpdb->get_var( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
		$trash    = (int) $wpdb->get_var( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

		wp_send_json_success(
			array(
				'total'    => $total,
				'approved' => $approved,
				'spam'     => $spam,
				'trash'    => $trash,
			)
		);
	}

	/**
	 * AJAX: Elimina todos los comentarios y metadatos asociados de la base de datos.
	 */
	public function ajax_delete_all_comments() {
		check_ajax_referer( 'wpat_comments_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'wp-agency-toolkit' ) ) );
		}

		global $wpdb;

		// Eliminar metadatos de comentarios
		$wpdb->query( "TRUNCATE TABLE {$wpdb->commentmeta}" );

		// Eliminar todos los comentarios
		$deleted = $wpdb->query( "TRUNCATE TABLE {$wpdb->comments}" );

		// Resetear conteo de comentarios en wp_posts
		$wpdb->query( "UPDATE {$wpdb->posts} SET comment_count = 0" );

		wp_send_json_success(
			array(
				'message' => __( 'Todos los comentarios y metadatos se han eliminado permanentemente de la base de datos.', 'wp-agency-toolkit' ),
			)
		);
	}
}

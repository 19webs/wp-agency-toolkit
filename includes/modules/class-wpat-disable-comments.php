<?php
/**
 * Módulo: Deshabilitar Comentarios - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Disable_Comments {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Disable_Comments
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
		// Acciones de inicialización
		add_action( 'init', array( $this, 'disable_comments_on_init' ) );
		add_action( 'admin_init', array( $this, 'disable_comments_on_admin_init' ) );
		
		// Modificaciones visuales de menús de admin
		add_action( 'admin_menu', array( $this, 'remove_comments_admin_menu' ) );
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_comments_admin_bar' ) );
		
		// Filtros del frontend para deshabilitar discusiones y ocultar los existentes
		add_filter( 'comments_open', array( $this, 'filter_comments_open' ), 10, 2 );
		add_filter( 'pings_open', array( $this, 'filter_comments_open' ), 10, 2 );
		add_filter( 'comments_array', array( $this, 'filter_comments_array' ), 10, 2 );

		// Desregistrar widgets de comentarios
		add_action( 'widgets_init', array( $this, 'disable_recent_comments_widget' ) );
	}

	/**
	 * Remueve el soporte nativo de comentarios y trackbacks para los post types seleccionados.
	 */
	public function disable_comments_on_init() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$is_global = isset( $settings['disable_comments_global'] ) && '1' === $settings['disable_comments_global'];

		$post_types = array();

		if ( $is_global ) {
			// Si es global, obtener todos los tipos de post públicos
			$post_types = get_post_types( array( 'public' => true ) );
		} else {
			// Si es selectivo, añadir los marcados
			if ( isset( $settings['disable_comments_posts'] ) && '1' === $settings['disable_comments_posts'] ) {
				$post_types[] = 'post';
			}
			if ( isset( $settings['disable_comments_pages'] ) && '1' === $settings['disable_comments_pages'] ) {
				$post_types[] = 'page';
			}
			if ( isset( $settings['disable_comments_media'] ) && '1' === $settings['disable_comments_media'] ) {
				$post_types[] = 'attachment';
			}
		}

		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
			}
			if ( post_type_supports( $post_type, 'trackbacks' ) ) {
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * Redirige el acceso directo de administración de comentarios al escritorio.
	 */
	public function disable_comments_on_admin_init() {
		global $pagenow;

		$settings = WPAT_Main::get_instance()->get_settings();
		$is_global = isset( $settings['disable_comments_global'] ) && '1' === $settings['disable_comments_global'];

		if ( $is_global && ( 'edit-comments.php' === $pagenow || 'comment.php' === $pagenow ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Remueve el menú "Comentarios" de la barra lateral de administración.
	 */
	public function remove_comments_admin_menu() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$is_global = isset( $settings['disable_comments_global'] ) && '1' === $settings['disable_comments_global'];

		if ( $is_global ) {
			remove_menu_page( 'edit-comments.php' );
		}
	}

	/**
	 * Remueve el globo de comentarios del menú de la barra superior.
	 */
	public function remove_comments_admin_bar() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$is_global = isset( $settings['disable_comments_global'] ) && '1' === $settings['disable_comments_global'];

		if ( $is_global ) {
			global $wp_admin_bar;
			if ( $wp_admin_bar ) {
				$wp_admin_bar->remove_menu( 'comments' );
			}
		}
	}

	/**
	 * Filtro para forzar el estado cerrado de los comentarios en el frontend según el tipo de post.
	 */
	public function filter_comments_open( $open, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $open;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$is_global = isset( $settings['disable_comments_global'] ) && '1' === $settings['disable_comments_global'];

		if ( $is_global ) {
			return false;
		}

		// Filtro selectivo por tipo de post
		if ( 'post' === $post->post_type && isset( $settings['disable_comments_posts'] ) && '1' === $settings['disable_comments_posts'] ) {
			return false;
		}
		if ( 'page' === $post->post_type && isset( $settings['disable_comments_pages'] ) && '1' === $settings['disable_comments_pages'] ) {
			return false;
		}
		if ( 'attachment' === $post->post_type && isset( $settings['disable_comments_media'] ) && '1' === $settings['disable_comments_media'] ) {
			return false;
		}

		return $open;
	}

	/**
	 * Oculta los comentarios ya existentes de la base de datos para no pintarlos si el tipo de post los tiene desactivados.
	 */
	public function filter_comments_array( $comments, $post_id ) {
		// Si para este post el comentario debe estar cerrado por nuestra regla, vaciamos el array
		if ( ! $this->filter_comments_open( true, $post_id ) ) {
			return array();
		}
		return $comments;
	}

	/**
	 * Desregistra el widget nativo de WordPress de comentarios recientes.
	 */
	public function disable_recent_comments_widget() {
		$settings = WPAT_Main::get_instance()->get_settings();
		$is_global = isset( $settings['disable_comments_global'] ) && '1' === $settings['disable_comments_global'];

		if ( $is_global ) {
			unregister_widget( 'WP_Widget_Recent_Comments' );
		}
	}
}

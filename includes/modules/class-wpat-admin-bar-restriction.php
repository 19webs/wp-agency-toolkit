<?php
/**
 * Módulo: Restricción de Barra de Admin y Backend - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Admin_Bar_Restriction {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Admin_Bar_Restriction
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Admin_Bar_Restriction
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
		add_action( 'init', array( $this, 'restrict_admin_bar_and_backend' ) );
	}

	/**
	 * Oculta la barra de administración y bloquea el acceso a /wp-admin para usuarios no editores.
	 */
	public function restrict_admin_bar_and_backend() {
		if ( is_user_logged_in() ) {
			// Si no puede editar posts (ej: suscriptor, cliente), ocultamos la barra
			if ( ! current_user_can( 'edit_posts' ) ) {
				add_filter( 'show_admin_bar', '__return_false' );
			}

			// Si intenta acceder a la administración y no puede editar posts (y no es una llamada AJAX)
			if ( is_admin() && ! current_user_can( 'edit_posts' ) && ! wp_doing_ajax() ) {
				wp_safe_redirect( home_url() );
				exit;
			}
		}
	}
}

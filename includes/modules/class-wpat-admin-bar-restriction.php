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
		add_action( 'wp_before_admin_bar_render', array( $this, 'clean_admin_bar_nodes' ), 999 );
	}

	/**
	 * Comprueba si el usuario actual está explícitamente excluido de restricciones.
	 *
	 * @param array $settings Ajustes del plugin.
	 * @return bool
	 */
	private function is_user_excluded( $settings ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$current_user = wp_get_current_user();

		// Superadministrador o usuario ID 1 nunca se bloquean
		if ( 1 === (int) $current_user->ID || is_super_admin() ) {
			return true;
		}

		// Administrador principal no se bloquea si el modo es all_except_admin
		if ( current_user_can( 'manage_options' ) ) {
			$hide_mode = isset( $settings['admin_bar_hide_mode'] ) ? $settings['admin_bar_hide_mode'] : 'all_except_admin';
			if ( 'all_except_admin' === $hide_mode ) {
				return true;
			}
		}

		$excluded_raw = ! empty( $settings['admin_access_excluded_users'] ) ? $settings['admin_access_excluded_users'] : '';
		if ( ! empty( $excluded_raw ) ) {
			$excluded_list = array_filter( array_map( 'trim', explode( ',', strtolower( $excluded_raw ) ) ) );
			$user_login    = strtolower( $current_user->user_login );
			$user_email    = strtolower( $current_user->user_email );
			$user_id       = (string) $current_user->ID;

			if ( in_array( $user_login, $excluded_list, true ) || in_array( $user_email, $excluded_list, true ) || in_array( $user_id, $excluded_list, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Oculta la barra de administración y bloquea el acceso a /wp-admin para roles seleccionados.
	 */
	public function restrict_admin_bar_and_backend() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Verificar exclusión de usuario
		if ( $this->is_user_excluded( $settings ) ) {
			return;
		}

		$current_user = wp_get_current_user();
		$user_roles   = (array) $current_user->roles;

		// 1. GESTIÓN DE OCULTACIÓN DE LA BARRA DE ADMIN
		$hide_mode = isset( $settings['admin_bar_hide_mode'] ) ? $settings['admin_bar_hide_mode'] : 'all_except_admin';

		if ( 'all' === $hide_mode ) {
			add_filter( 'show_admin_bar', '__return_false' );
		} elseif ( 'all_except_admin' === $hide_mode ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				add_filter( 'show_admin_bar', '__return_false' );
			}
		} elseif ( 'roles' === $hide_mode ) {
			$hidden_roles = isset( $settings['admin_bar_hidden_roles'] ) && is_array( $settings['admin_bar_hidden_roles'] ) ? $settings['admin_bar_hidden_roles'] : array( 'subscriber', 'customer' );
			$match = array_intersect( $user_roles, $hidden_roles );
			if ( ! empty( $match ) ) {
				add_filter( 'show_admin_bar', '__return_false' );
			}
		}

		// 2. GESTIÓN DE BLOQUEO DE ACCESO A /WP-ADMIN
		$restrict_backend = isset( $settings['admin_access_restrict_enabled'] ) && '1' === $settings['admin_access_restrict_enabled'];

		if ( $restrict_backend && is_admin() ) {
			// Ignorar llamadas seguras de backend (AJAX, REST, Cron)
			if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
				return;
			}

			// Roles restringidos de entrar a wp-admin
			$restricted_roles = isset( $settings['admin_access_restricted_roles'] ) && is_array( $settings['admin_access_restricted_roles'] ) ? $settings['admin_access_restricted_roles'] : array( 'subscriber', 'customer' );
			$is_restricted = ! empty( array_intersect( $user_roles, $restricted_roles ) );

			// Si el modo es 'all_except_admin' y no es administrador
			if ( 'all_except_admin' === $hide_mode && ! current_user_can( 'manage_options' ) ) {
				$is_restricted = true;
			}

			if ( $is_restricted ) {
				$redirect_type = isset( $settings['admin_access_redirect_to'] ) ? $settings['admin_access_redirect_to'] : 'home';
				$redirect_url  = home_url( '/' );

				if ( 'woocommerce_myaccount' === $redirect_type && function_exists( 'wc_get_page_permalink' ) ) {
					$my_account_url = wc_get_page_permalink( 'myaccount' );
					if ( ! empty( $my_account_url ) ) {
						$redirect_url = $my_account_url;
					}
				} elseif ( 'custom' === $redirect_type && ! empty( $settings['admin_access_custom_redirect_url'] ) ) {
					$redirect_url = esc_url_raw( $settings['admin_access_custom_redirect_url'] );
				}

				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Limpia nodos específicos de la barra de administración de WordPress.
	 */
	public function clean_admin_bar_nodes() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		global $wp_admin_bar;

		if ( ! is_object( $wp_admin_bar ) ) {
			return;
		}

		// Ocultar Logo de WordPress
		if ( isset( $settings['admin_bar_remove_wp_logo'] ) && '1' === $settings['admin_bar_remove_wp_logo'] ) {
			$wp_admin_bar->remove_node( 'wp-logo' );
		}

		// Ocultar Comentarios
		if ( isset( $settings['admin_bar_remove_comments'] ) && '1' === $settings['admin_bar_remove_comments'] ) {
			$wp_admin_bar->remove_node( 'comments' );
		}

		// Ocultar + Nuevo
		if ( isset( $settings['admin_bar_remove_new_content'] ) && '1' === $settings['admin_bar_remove_new_content'] ) {
			$wp_admin_bar->remove_node( 'new-content' );
		}

		// Ocultar Actualizaciones
		if ( isset( $settings['admin_bar_remove_updates'] ) && '1' === $settings['admin_bar_remove_updates'] ) {
			$wp_admin_bar->remove_node( 'updates' );
		}

		// Ocultar Personalizar
		if ( isset( $settings['admin_bar_remove_customize'] ) && '1' === $settings['admin_bar_remove_customize'] ) {
			$wp_admin_bar->remove_node( 'customize' );
		}
	}
}

<?php
/**
 * Módulo: Ocultar Huella WPAT & Marca Blanca (Silent Skin) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Silent_Skin {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Silent_Skin
	 */
	private static $instance = null;

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename = 'wp-agency-toolkit/wp-agency-toolkit.php';

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Silent_Skin
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
		$this->plugin_basename = plugin_basename( WPAT_FILE );

		// 1. Filtrar lista de plugins instalados (ocultar o renombrar)
		add_filter( 'all_plugins', array( $this, 'filter_plugins_list' ), 999 );

		// 2. Filtrar enlaces de acción del plugin (evitar desactivación accidental)
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'filter_action_links' ), 999 );

		// 3. Filtrar metadatos en la fila del plugin
		add_filter( 'plugin_row_meta', array( $this, 'filter_plugin_row_meta' ), 999, 4 );

		// 4. Limpiar barra de administración de WordPress
		add_action( 'wp_before_admin_bar_render', array( $this, 'clean_admin_bar' ), 999 );

		// 5. Ocultar huellas en el código HTML y cabeceras
		add_action( 'template_redirect', array( $this, 'clean_frontend_headers' ), 1 );
	}

	/**
	 * Determina si el usuario actual tiene permiso para ver el plugin original.
	 *
	 * @return bool
	 */
	public function is_current_user_allowed() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Si se pasa el parámetro secreto de desbloqueo en la URL
		if ( isset( $_GET['wpat_unlock'] ) && '1' === $_GET['wpat_unlock'] ) {
			return true;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$hide_from_non_admins = isset( $settings['white_label_hide_from_non_admins'] ) && '1' === $settings['white_label_hide_from_non_admins'];

		if ( ! $hide_from_non_admins ) {
			return false;
		}

		$current_user = wp_get_current_user();
		$allowed_raw  = isset( $settings['white_label_allowed_users'] ) ? $settings['white_label_allowed_users'] : '';

		if ( empty( $allowed_raw ) ) {
			// Por defecto solo el Superadmin de Multisite o el ID 1
			return ( 1 === (int) $current_user->ID || is_super_admin() );
		}

		$allowed_list = array_filter( array_map( 'trim', explode( ',', strtolower( $allowed_raw ) ) ) );
		$user_login   = strtolower( $current_user->user_login );
		$user_email   = strtolower( $current_user->user_email );

		return ( in_array( $user_login, $allowed_list, true ) || in_array( $user_email, $allowed_list, true ) || 1 === (int) $current_user->ID );
	}

	/**
	 * Filtra la lista de plugins para ocultar o renombrar WP Agency Toolkit.
	 *
	 * @param array $plugins Lista de plugins instalados.
	 * @return array
	 */
	public function filter_plugins_list( $plugins ) {
		// Si el usuario actual está en la lista de administradores autorizados, mostrar sin alterar
		if ( $this->is_current_user_allowed() ) {
			return $plugins;
		}

		if ( ! isset( $plugins[ $this->plugin_basename ] ) ) {
			return $plugins;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$mode     = isset( $settings['white_label_mode'] ) ? $settings['white_label_mode'] : 'rename';

		if ( 'hide' === $mode ) {
			// Ocultar completamente de la tabla de plugins
			unset( $plugins[ $this->plugin_basename ] );
		} else {
			// Renombrar y aplicar Marca Blanca
			$custom_name   = ! empty( $settings['white_label_plugin_name'] ) ? $settings['white_label_plugin_name'] : 'Herramientas del Sitio Web';
			$custom_desc   = ! empty( $settings['white_label_plugin_desc'] ) ? $settings['white_label_plugin_desc'] : 'Módulo de optimización, seguridad y utilidades para la administración de este sitio.';
			$custom_author = ! empty( $settings['white_label_author'] ) ? $settings['white_label_author'] : 'Equipo de Desarrollo Web';
			$custom_url    = ! empty( $settings['white_label_author_url'] ) ? $settings['white_label_author_url'] : '';

			$plugins[ $this->plugin_basename ]['Name']        = $custom_name;
			$plugins[ $this->plugin_basename ]['Title']       = $custom_name;
			$plugins[ $this->plugin_basename ]['Description'] = $custom_desc;
			$plugins[ $this->plugin_basename ]['Author']      = $custom_author;
			$plugins[ $this->plugin_basename ]['AuthorName']  = $custom_author;
			$plugins[ $this->plugin_basename ]['AuthorURI']   = $custom_url;
			$plugins[ $this->plugin_basename ]['PluginURI']   = $custom_url;
		}

		return $plugins;
	}

	/**
	 * Filtra los enlaces de acción del plugin en la tabla plugins.php.
	 *
	 * @param array $actions Enlaces de acción (Desactivar, Ajustes, etc.).
	 * @return array
	 */
	public function filter_action_links( $actions ) {
		if ( $this->is_current_user_allowed() ) {
			return $actions;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Si se ha configurado prevenir desactivación
		if ( isset( $settings['white_label_prevent_deactivation'] ) && '1' === $settings['white_label_prevent_deactivation'] ) {
			if ( isset( $actions['deactivate'] ) ) {
				unset( $actions['deactivate'] );
			}
			if ( isset( $actions['delete'] ) ) {
				unset( $actions['delete'] );
			}
		}

		return $actions;
	}

	/**
	 * Filtra los metadatos de la fila del plugin (Visitar sitio web, etc.).
	 *
	 * @param array  $plugin_meta Metadatos.
	 * @param string $plugin_file Archivo del plugin.
	 * @param array  $plugin_data Datos del plugin.
	 * @param string $status Estado del plugin.
	 * @return array
	 */
	public function filter_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		if ( $plugin_file === $this->plugin_basename && ! $this->is_current_user_allowed() ) {
			// Vaciar metadatos para no filtrar enlaces del repositorio o GitHub
			return array();
		}
		return $plugin_meta;
	}

	/**
	 * Limpia nodos de la barra de administración superior.
	 */
	public function clean_admin_bar() {
		if ( $this->is_current_user_allowed() ) {
			return;
		}

		global $wp_admin_bar;
		if ( is_object( $wp_admin_bar ) ) {
			$wp_admin_bar->remove_node( 'wp-agency-toolkit' );
			$wp_admin_bar->remove_node( 'wpat-admin-bar' );
		}
	}

	/**
	 * Elimina cabeceras o huellas en el front-end.
	 */
	public function clean_frontend_headers() {
		if ( ! is_admin() && ! headers_sent() ) {
			@header_remove( 'X-WPAT-Version' );
		}
	}
}

/**
 * Skin silencioso para actualizaciones automáticas de plugins y temas de Envato.
 */
if ( class_exists( 'WP_Upgrader_Skin' ) && ! class_exists( 'WPAT_Silent_Upgrader_Skin' ) ) {
	class WPAT_Silent_Upgrader_Skin extends WP_Upgrader_Skin {
		public function header() {}
		public function footer() {}
		public function error( $errors ) {}
		public function feedback( $string, ...$args ) {}
	}
}

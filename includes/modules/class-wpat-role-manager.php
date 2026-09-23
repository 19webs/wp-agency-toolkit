<?php
/**
 * Módulo: Gestor de Roles y Permisos - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Role_Manager {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Role_Manager|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Role_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Roles nativos protegidos de WordPress que no pueden eliminarse.
	 *
	 * @var array
	 */
	private static $core_roles = array(
		'administrator',
		'editor',
		'author',
		'contributor',
		'subscriber',
	);

	/**
	 * Permisos críticos del Administrador protegidos contra revocación accidental.
	 *
	 * @var array
	 */
	private static $critical_admin_caps = array(
		'manage_options',
		'edit_users',
		'promote_users',
		'activate_plugins',
		'edit_plugins',
		'edit_theme_options',
		'read',
	);

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Endpoints AJAX para la gestión de roles y permisos
		add_action( 'wp_ajax_wpat_get_role_caps', array( $this, 'ajax_get_role_caps' ) );
		add_action( 'wp_ajax_wpat_save_role_caps', array( $this, 'ajax_save_role_caps' ) );
		add_action( 'wp_ajax_wpat_create_custom_role', array( $this, 'ajax_create_custom_role' ) );
		add_action( 'wp_ajax_wpat_delete_custom_role', array( $this, 'ajax_delete_custom_role' ) );
		add_action( 'wp_ajax_wpat_reset_default_roles', array( $this, 'ajax_reset_default_roles' ) );
	}

	/**
	 * Comprueba si un rol es del core de WordPress.
	 *
	 * @param string $role_slug Slug del rol.
	 * @return bool
	 */
	public static function is_core_role( $role_slug ) {
		return in_array( $role_slug, self::$core_roles, true );
	}

	/**
	 * Obtiene todos los roles registrados junto con su recuento de usuarios y tipo.
	 *
	 * @return array
	 */
	public static function get_all_roles_with_meta() {
		$wp_roles   = wp_roles();
		$roles_data = array();

		if ( ! $wp_roles || empty( $wp_roles->roles ) ) {
			return $roles_data;
		}

		// Obtener recuento de usuarios por rol
		$user_counts = count_users();
		$avail_counts = isset( $user_counts['avail_roles'] ) ? $user_counts['avail_roles'] : array();

		foreach ( $wp_roles->roles as $slug => $role_info ) {
			$name = isset( $wp_roles->role_names[ $slug ] ) ? translate_user_role( $wp_roles->role_names[ $slug ] ) : $role_info['name'];
			$user_count = isset( $avail_counts[ $slug ] ) ? (int) $avail_counts[ $slug ] : 0;

			$type = 'custom';
			$type_label = 'Personalizado';

			if ( in_array( $slug, self::$core_roles, true ) ) {
				$type = 'core';
				$type_label = 'Nativo WP';
			} elseif ( in_array( $slug, array( 'customer', 'shop_manager' ), true ) ) {
				$type = 'woocommerce';
				$type_label = 'WooCommerce';
			}

			$roles_data[ $slug ] = array(
				'slug'         => $slug,
				'name'         => $name,
				'raw_name'     => $role_info['name'],
				'capabilities' => isset( $role_info['capabilities'] ) ? $role_info['capabilities'] : array(),
				'user_count'   => $user_count,
				'type'         => $type,
				'type_label'   => $type_label,
				'is_core'      => ( 'core' === $type ),
			);
		}

		return $roles_data;
	}

	/**
	 * Obtiene el mapa categorizado de todas las capabilities conocidas de WordPress, WooCommerce y CPTs.
	 *
	 * @return array
	 */
	public static function get_categorized_capabilities_map() {
		$categories = array(
			'posts_pages' => array(
				'title' => 'Entradas y Páginas',
				'icon'  => 'dashicons-admin-post',
				'caps'  => array(
					'edit_posts'              => 'Editar entradas propias',
					'edit_others_posts'       => 'Editar entradas de otros autores',
					'publish_posts'           => 'Publicar entradas directamente',
					'read_private_posts'      => 'Leer entradas privadas',
					'delete_posts'            => 'Eliminar entradas propias',
					'delete_others_posts'     => 'Eliminar entradas de otros autores',
					'delete_published_posts'  => 'Eliminar entradas publicadas',
					'delete_private_posts'    => 'Eliminar entradas privadas',
					'edit_published_posts'    => 'Editar entradas ya publicadas',
					'edit_private_posts'      => 'Editar entradas privadas',
					'edit_pages'              => 'Editar páginas propias',
					'edit_others_pages'       => 'Editar páginas de otros autores',
					'publish_pages'           => 'Publicar páginas directamente',
					'read_private_pages'      => 'Leer páginas privadas',
					'delete_pages'            => 'Eliminar páginas propias',
					'delete_others_pages'     => 'Eliminar páginas de otros autores',
					'delete_published_pages'  => 'Eliminar páginas publicadas',
					'delete_private_pages'    => 'Eliminar páginas privadas',
					'edit_published_pages'    => 'Editar páginas ya publicadas',
					'edit_private_pages'      => 'Editar páginas privadas',
					'manage_categories'       => 'Gestionar categorías y etiquetas',
				),
			),
			'media' => array(
				'title' => 'Biblioteca y Medios',
				'icon'  => 'dashicons-admin-media',
				'caps'  => array(
					'upload_files'      => 'Subir archivos multimedia',
					'unfiltered_upload' => 'Subir cualquier formato de archivo (sin filtrar)',
				),
			),
			'comments' => array(
				'title' => 'Comentarios',
				'icon'  => 'dashicons-admin-comments',
				'caps'  => array(
					'moderate_comments' => 'Moderar y gestionar comentarios',
					'edit_comment'      => 'Editar comentarios',
				),
			),
			'appearance' => array(
				'title' => 'Apariencia y Temas',
				'icon'  => 'dashicons-admin-appearance',
				'caps'  => array(
					'switch_themes'      => 'Cambiar de tema activo',
					'edit_theme_options' => 'Gestionar menús, widgets y opciones de tema',
					'customize'          => 'Acceder al Personalizador de WordPress (Customizer)',
					'install_themes'     => 'Instalar nuevos temas',
					'update_themes'      => 'Actualizar temas',
					'delete_themes'      => 'Eliminar temas',
					'edit_themes'        => 'Editar código PHP/CSS de temas',
				),
			),
			'plugins' => array(
				'title' => 'Plugins y Extensiones',
				'icon'  => 'dashicons-admin-plugins',
				'caps'  => array(
					'activate_plugins' => 'Activar y desactivar plugins',
					'install_plugins'  => 'Instalar nuevos plugins',
					'update_plugins'   => 'Actualizar plugins',
					'delete_plugins'   => 'Eliminar plugins',
					'edit_plugins'     => 'Editar código de plugins',
				),
			),
			'users' => array(
				'title' => 'Gestión de Usuarios',
				'icon'  => 'dashicons-admin-users',
				'caps'  => array(
					'list_users'    => 'Ver listado de usuarios registrados',
					'edit_users'    => 'Editar perfiles de otros usuarios',
					'create_users'  => 'Crear nuevos usuarios manualmente',
					'delete_users'  => 'Eliminar cuentas de usuarios',
					'promote_users' => 'Cambiar roles y permisos de usuarios',
					'remove_users'  => 'Remover usuarios del sitio',
				),
			),
			'system' => array(
				'title' => 'Sistema y Configuración General',
				'icon'  => 'dashicons-admin-generic',
				'caps'  => array(
					'manage_options'  => 'Acceder a ajustes generales del sitio y plugins',
					'read'            => 'Acceder al panel de administración / Leer contenidos',
					'unfiltered_html' => 'Publicar HTML / Scripts sin sanitizar',
					'export'          => 'Exportar contenidos y bases de datos',
					'import'          => 'Importar archivos y contenidos',
					'update_core'     => 'Actualizar el núcleo de WordPress',
				),
			),
		);

		// Si WooCommerce está activo o instalado, añadir su bloque de permisos
		if ( class_exists( 'WooCommerce' ) || post_type_exists( 'product' ) ) {
			$categories['woocommerce'] = array(
				'title' => 'WooCommerce y Tienda',
				'icon'  => 'dashicons-cart',
				'caps'  => array(
					'manage_woocommerce'          => 'Gestionar ajustes de WooCommerce y pedidos',
					'view_woocommerce_reports'    => 'Ver informes y estadísticas de ventas',
					'edit_products'               => 'Editar productos propios',
					'edit_others_products'        => 'Editar productos de otros usuarios',
					'publish_products'            => 'Publicar productos directamente',
					'read_private_products'       => 'Ver productos privados',
					'delete_products'             => 'Eliminar productos propios',
					'delete_others_products'      => 'Eliminar productos de otros usuarios',
					'delete_published_products'   => 'Eliminar productos publicados',
					'delete_private_products'     => 'Eliminar productos privados',
					'edit_published_products'     => 'Editar productos publicados',
					'edit_private_products'       => 'Editar productos privados',
					'manage_product_terms'        => 'Gestionar categorías y atributos de producto',
					'edit_product_terms'          => 'Editar términos de producto',
					'delete_product_terms'        => 'Eliminar términos de producto',
					'assign_product_terms'        => 'Asignar términos a productos',
					'edit_shop_orders'            => 'Editar pedidos de la tienda',
					'edit_others_shop_orders'     => 'Editar pedidos de otros clientes',
					'publish_shop_orders'         => 'Crear y publicar nuevos pedidos',
					'delete_shop_orders'          => 'Eliminar pedidos de la tienda',
					'delete_others_shop_orders'   => 'Eliminar pedidos de otros clientes',
					'manage_shop_order_terms'     => 'Gestionar estados de pedidos',
					'edit_shop_coupons'           => 'Editar cupones de descuento',
					'edit_others_shop_coupons'    => 'Editar cupones de otros usuarios',
					'publish_shop_coupons'        => 'Publicar cupones de descuento',
					'delete_shop_coupons'         => 'Eliminar cupones de descuento',
				),
			);
		}

		// Recopilar capacidades no clasificadas de plugins y CPTs registrados
		$all_registered_caps = self::get_all_unique_capabilities();
		$categorized_caps    = array();

		foreach ( $categories as $cat ) {
			$categorized_caps = array_merge( $categorized_caps, array_keys( $cat['caps'] ) );
		}

		$custom_caps = array_diff( $all_registered_caps, $categorized_caps );

		if ( ! empty( $custom_caps ) ) {
			$custom_caps_map = array();
			foreach ( $custom_caps as $cap ) {
				$custom_caps_map[ $cap ] = ucwords( str_replace( array( '_', '-' ), ' ', $cap ) );
			}
			$categories['custom_cpts'] = array(
				'title' => 'Permisos Adicionales / CPTs',
				'icon'  => 'dashicons-admin-generic',
				'caps'  => $custom_caps_map,
			);
		}

		return $categories;
	}

	/**
	 * Obtiene todas las capacidades únicas presentes en el sistema.
	 *
	 * @return array
	 */
	public static function get_all_unique_capabilities() {
		$wp_roles = wp_roles();
		$caps     = array();

		if ( ! $wp_roles || empty( $wp_roles->roles ) ) {
			return $caps;
		}

		foreach ( $wp_roles->roles as $role ) {
			if ( ! empty( $role['capabilities'] ) && is_array( $role['capabilities'] ) ) {
				foreach ( $role['capabilities'] as $cap => $granted ) {
					$caps[ $cap ] = true;
				}
			}
		}

		return array_keys( $caps );
	}

	/**
	 * Endpoint AJAX para obtener las capabilities de un rol específico.
	 */
	public function ajax_get_role_caps() {
		check_ajax_referer( 'wpat_role_manager_nonce_action', 'security' );

		if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$role_slug = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
		$wp_roles  = wp_roles();

		if ( empty( $role_slug ) || ! isset( $wp_roles->roles[ $role_slug ] ) ) {
			wp_send_json_error( array( 'message' => 'Rol no encontrado.' ) );
		}

		$role_obj = $wp_roles->get_role( $role_slug );

		wp_send_json_success(
			array(
				'role'         => $role_slug,
				'name'         => translate_user_role( $wp_roles->role_names[ $role_slug ] ),
				'capabilities' => $role_obj ? $role_obj->capabilities : array(),
				'is_core'      => self::is_core_role( $role_slug ),
				'is_admin'     => ( 'administrator' === $role_slug ),
			)
		);
	}

	/**
	 * Endpoint AJAX para guardar las capabilities de un rol.
	 */
	public function ajax_save_role_caps() {
		check_ajax_referer( 'wpat_role_manager_nonce_action', 'security' );

		if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$role_slug = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';
		$caps_raw  = isset( $_POST['capabilities'] ) ? (array) $_POST['capabilities'] : array();

		$wp_roles = wp_roles();

		if ( empty( $role_slug ) || ! isset( $wp_roles->roles[ $role_slug ] ) ) {
			wp_send_json_error( array( 'message' => 'Rol no válido.' ) );
		}

		$role_obj = $wp_roles->get_role( $role_slug );
		if ( ! $role_obj ) {
			wp_send_json_error( array( 'message' => 'No se pudo cargar el rol solicitado.' ) );
		}

		// Protección Anti-Lockout para Administrador
		if ( 'administrator' === $role_slug ) {
			foreach ( self::$critical_admin_caps as $crit_cap ) {
				$caps_raw[ $crit_cap ] = 1;
			}
		}

		// Obtener todas las capacidades del mapa para comprobar cuáles revocar
		$all_known_caps = self::get_all_unique_capabilities();

		// Actualizar capabilities en el objeto WP_Role
		foreach ( $all_known_caps as $cap ) {
			if ( ! empty( $caps_raw[ $cap ] ) && ( '1' === (string) $caps_raw[ $cap ] || true === $caps_raw[ $cap ] ) ) {
				$role_obj->add_cap( $cap, true );
			} else {
				$role_obj->remove_cap( $cap );
			}
		}

		wp_send_json_success(
			array(
				'message' => 'Permisos del rol "' . translate_user_role( $wp_roles->role_names[ $role_slug ] ) . '" actualizados correctamente.',
				'role'    => $role_slug,
			)
		);
	}

	/**
	 * Endpoint AJAX para crear un nuevo rol personalizado o clonar uno existente.
	 */
	public function ajax_create_custom_role() {
		check_ajax_referer( 'wpat_role_manager_nonce_action', 'security' );

		if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$role_name  = isset( $_POST['role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['role_name'] ) ) : '';
		$role_slug  = isset( $_POST['role_slug'] ) ? sanitize_key( $_POST['role_slug'] ) : '';
		$clone_from = isset( $_POST['clone_from'] ) ? sanitize_key( $_POST['clone_from'] ) : '';

		if ( empty( $role_name ) ) {
			wp_send_json_error( array( 'message' => 'El nombre del rol es obligatorio.' ) );
		}

		if ( empty( $role_slug ) ) {
			$role_slug = sanitize_key( $role_name );
		}

		$wp_roles = wp_roles();

		if ( isset( $wp_roles->roles[ $role_slug ] ) ) {
			wp_send_json_error( array( 'message' => 'Ya existe un rol con el identificador "' . esc_html( $role_slug ) . '". Elige otro nombre.' ) );
		}

		$initial_caps = array( 'read' => true );

		// Si se solicitó clonar de un rol existente
		if ( ! empty( $clone_from ) && isset( $wp_roles->roles[ $clone_from ] ) ) {
			$clone_obj = $wp_roles->get_role( $clone_from );
			if ( $clone_obj && ! empty( $clone_obj->capabilities ) ) {
				$initial_caps = $clone_obj->capabilities;
			}
		}

		// Crear el nuevo rol
		$result = add_role( $role_slug, $role_name, $initial_caps );

		if ( null !== $result ) {
			wp_send_json_success(
				array(
					'message'   => 'Rol "' . esc_html( $role_name ) . '" creado con éxito.',
					'role_slug' => $role_slug,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'No se pudo crear el rol en la base de datos.' ) );
		}
	}

	/**
	 * Endpoint AJAX para eliminar un rol personalizado.
	 */
	public function ajax_delete_custom_role() {
		check_ajax_referer( 'wpat_role_manager_nonce_action', 'security' );

		if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		$role_slug = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : '';

		if ( empty( $role_slug ) ) {
			wp_send_json_error( array( 'message' => 'Identificador de rol no válido.' ) );
		}

		if ( self::is_core_role( $role_slug ) ) {
			wp_send_json_error( array( 'message' => 'No es posible eliminar roles nativos protegidos de WordPress.' ) );
		}

		$wp_roles = wp_roles();

		if ( ! isset( $wp_roles->roles[ $role_slug ] ) ) {
			wp_send_json_error( array( 'message' => 'El rol que intentas eliminar no existe.' ) );
		}

		// Comprobar si hay usuarios con este rol asignado
		$users = get_users( array( 'role' => $role_slug, 'number' => 1 ) );
		if ( ! empty( $users ) ) {
			// Reasignar usuarios a 'subscriber' antes de eliminar el rol
			$all_role_users = get_users( array( 'role' => $role_slug ) );
			foreach ( $all_role_users as $user ) {
				$user->remove_role( $role_slug );
				$user->add_role( 'subscriber' );
			}
		}

		// Eliminar el rol
		remove_role( $role_slug );

		wp_send_json_success(
			array(
				'message' => 'El rol personalizado ha sido eliminado correctamente.',
			)
		);
	}

	/**
	 * Endpoint AJAX para restaurar los roles nativos a la configuración de fábrica de WordPress.
	 */
	public function ajax_reset_default_roles() {
		check_ajax_referer( 'wpat_role_manager_nonce_action', 'security' );

		if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ) );
		}

		// Incluir archivo de esquema de roles de WordPress si es necesario
		require_once ABSPATH . 'wp-admin/includes/schema.php';

		// Eliminar primero los roles nativos para que populate_roles los reconstruya desde cero con sus permisos de fábrica
		$core_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		foreach ( $core_roles as $core_role ) {
			remove_role( $core_role );
		}

		// Ejecutar reseteo estándar de roles de WordPress
		populate_roles();

		// Si WooCommerce está instalado, restaurar también sus roles predeterminados
		if ( class_exists( 'WC_Install' ) && method_exists( 'WC_Install', 'create_roles' ) ) {
			WC_Install::create_roles();
		}

		wp_send_json_success(
			array(
				'message' => 'Los roles nativos de WordPress han sido restaurados a sus valores predeterminados de fábrica.',
			)
		);
	}
}

<?php
/**
 * Módulo: Diseño SaaS para Entradas, Productos & CPTs (Client Studio)
 *
 * Transforma de forma nativa e integrada los listados (edit.php) y las pantallas
 * de creación y edición (post-new.php / post.php) de WordPress en una experiencia
 * limpia, moderna y visual estilo SaaS (Notion/Ghost/Stripe).
 *
 * Utiliza la arquitectura de la plantilla maestra interactiva en templates/client-studio/app.php
 * Cuando está activo, se aplica directamente al hacer clic en el menú estándar de WP.
 * Cuando está desactivado, todo vuelve al 100% a la interfaz nativa estándar.
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Client_Studio {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_Client_Studio
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Client_Studio
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
		// 1. Registro de la página de administración de Client Studio
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );

		// 2. Interceptación y enrutado transparente desde el menú de WordPress
		add_action( 'admin_init', array( $this, 'intercept_native_wp_screens' ) );

		// 3. Destacar el elemento de menú correspondiente en la barra lateral de WP
		add_filter( 'parent_file', array( $this, 'fix_parent_menu_active' ) );
		add_filter( 'submenu_file', array( $this, 'fix_submenu_menu_active' ) );

		// 4. Encolar assets de la aplicación
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_app_assets' ) );

		// 5. Endpoints AJAX para guardado y papelera desde el Studio
		add_action( 'wp_ajax_wpat_studio_save_post', array( $this, 'ajax_save_post' ) );
		add_action( 'wp_ajax_wpat_studio_trash_post', array( $this, 'ajax_trash_post' ) );
	}

	/**
	 * Obtiene la lista de todos los post types públicos disponibles.
	 *
	 * @return array
	 */
	public static function get_supported_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		if ( isset( $types['attachment'] ) ) {
			unset( $types['attachment'] );
		}
		return $types;
	}

	/**
	 * Comprueba si el módulo está habilitado para el usuario y post type actual.
	 *
	 * @param string $post_type Post type a evaluar.
	 * @return bool
	 */
	public function is_active_for_context( $post_type = '' ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! isset( $settings['client-studio'] ) || '1' !== (string) $settings['client-studio'] ) {
			return false;
		}

		if ( empty( $post_type ) ) {
			global $typenow;
			$post_type = ! empty( $typenow ) ? $typenow : ( isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post' );
		}

		// Comprobar si el post type está habilitado
		$enabled_pts = isset( $settings['studio_post_types'] ) && is_array( $settings['studio_post_types'] )
			? $settings['studio_post_types']
			: array( 'post', 'page', 'product' );

		if ( ! in_array( $post_type, $enabled_pts, true ) ) {
			return false;
		}

		// Comprobar restricción por rol
		$target_roles = isset( $settings['studio_roles'] ) && is_array( $settings['studio_roles'] )
			? $settings['studio_roles']
			: array( 'all' );

		if ( ! in_array( 'all', $target_roles, true ) ) {
			$user = wp_get_current_user();
			$user_roles = (array) $user->roles;
			$has_role = false;
			foreach ( $user_roles as $r ) {
				if ( in_array( $r, $target_roles, true ) ) {
					$has_role = true;
					break;
				}
			}
			if ( ! $has_role ) {
				return false;
			}
		}

		// Si el usuario añadió parámetro explícito ?wpat_classic=1, permitir acceso clásico
		if ( isset( $_GET['wpat_classic'] ) && '1' === $_GET['wpat_classic'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Registra la página oculta del Client Studio.
	 */
	public function register_admin_page() {
		add_submenu_page(
			null, // Oculto del menú raíz para integrarse en las secciones nativas
			'Client Studio',
			'Client Studio',
			'edit_posts',
			'wpat-client-studio',
			array( $this, 'render_client_studio_page' )
		);
	}

	/**
	 * Intercepta las visitas a edit.php, post.php y post-new.php y las redirige limpiamente al Client Studio.
	 */
	public function intercept_native_wp_screens() {
		global $pagenow;

		// Si estamos en una petición AJAX o REST, no redirigir
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// Solo intervenir en pantallas de listado o edición
		if ( ! in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// Determinar post type actual
		$post_type = 'post';
		$post_id   = 0;

		if ( 'edit.php' === $pagenow || 'post-new.php' === $pagenow ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
		} elseif ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
			$post = get_post( $post_id );
			if ( $post ) {
				$post_type = $post->post_type;
			}
		}

		if ( ! $this->is_active_for_context( $post_type ) ) {
			return;
		}

		// Redirigir al Client Studio según la pantalla
		if ( 'edit.php' === $pagenow ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpat-client-studio&post_type=' . $post_type . '&view=list' ) );
			exit;
		}

		if ( 'post-new.php' === $pagenow ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpat-client-studio&post_type=' . $post_type . '&view=editor&post_id=0' ) );
			exit;
		}

		if ( 'post.php' === $pagenow && $post_id > 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpat-client-studio&post_type=' . $post_type . '&view=editor&post_id=' . $post_id ) );
			exit;
		}
	}

	/**
	 * Mantiene iluminado el menú padre correcto en el panel lateral de WP.
	 *
	 * @param string $parent_file
	 * @return string
	 */
	public function fix_parent_menu_active( $parent_file ) {
		global $pagenow;
		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'wpat-client-studio' === $_GET['page'] ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
			if ( 'post' === $post_type ) {
				return 'edit.php';
			} elseif ( 'page' === $post_type ) {
				return 'edit.php?post_type=page';
			} else {
				return 'edit.php?post_type=' . $post_type;
			}
		}
		return $parent_file;
	}

	/**
	 * Mantiene iluminado el submenú correcto en el panel lateral de WP.
	 *
	 * @param string $submenu_file
	 * @return string
	 */
	public function fix_submenu_menu_active( $submenu_file ) {
		global $pagenow;
		if ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'wpat-client-studio' === $_GET['page'] ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
			$view      = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'list';

			if ( 'editor' === $view && empty( $_GET['post_id'] ) ) {
				return ( 'post' === $post_type ) ? 'post-new.php' : 'post-new.php?post_type=' . $post_type;
			}
			return ( 'post' === $post_type ) ? 'edit.php' : 'edit.php?post_type=' . $post_type;
		}
		return $submenu_file;
	}

	/**
	 * Encola los estilos y scripts de Client Studio en la página del Studio.
	 *
	 * @param string $hook
	 */
	public function enqueue_app_assets( $hook ) {
		if ( 'admin_page_wpat-client-studio' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'wpat-client-studio-app-css',
			WPAT_URL . 'assets/css/wpat-client-studio-app.css',
			array(),
			time()
		);

		wp_enqueue_script(
			'wpat-client-studio-app-js',
			WPAT_URL . 'assets/js/wpat-client-studio-app.js',
			array( 'jquery' ),
			time(),
			true
		);

		wp_localize_script(
			'wpat-client-studio-app-js',
			'wpatStudioConfig',
			array(
				'nonce' => wp_create_nonce( 'wpat_studio_nonce' ),
			)
		);
	}

	/**
	 * Renderiza la aplicación completa de Client Studio.
	 */
	public function render_client_studio_page() {
		$template_path = WPAT_DIR . 'templates/client-studio/app.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<div class="notice notice-error"><p>No se encontró la plantilla de Client Studio.</p></div>';
		}
	}

	/**
	 * Guarda o actualiza una publicación vía AJAX desde el Studio.
	 */
	public function ajax_save_post() {
		check_ajax_referer( 'wpat_studio_nonce', 'security' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos para editar publicaciones.' ) );
		}

		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$content     = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$excerpt     = isset( $_POST['excerpt'] ) ? sanitize_textarea_field( $_POST['excerpt'] ) : '';
		$status      = isset( $_POST['post_status'] ) && in_array( $_POST['post_status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ? $_POST['post_status'] : 'draft';
		$thumb_id    = isset( $_POST['thumbnail_id'] ) ? absint( $_POST['thumbnail_id'] ) : 0;
		$categories  = isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ? array_map( 'absint', $_POST['categories'] ) : array();

		// Campos SEO
		$seo_title   = isset( $_POST['seo_title'] ) ? sanitize_text_field( $_POST['seo_title'] ) : '';
		$seo_desc    = isset( $_POST['seo_desc'] ) ? sanitize_textarea_field( $_POST['seo_desc'] ) : '';
		$seo_keyword = isset( $_POST['seo_keyword'] ) ? sanitize_text_field( $_POST['seo_keyword'] ) : '';

		// Campos CPT / ACF dinámicos
		$cpt_meta    = isset( $_POST['cpt_meta'] ) && is_array( $_POST['cpt_meta'] ) ? map_deep( $_POST['cpt_meta'], 'sanitize_text_field' ) : array();

		if ( empty( $title ) ) {
			$title = 'Borrador sin título (' . date_i18n( 'd/m/Y H:i' ) . ')';
		}

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => $status,
			'post_type'    => $post_type,
		);

		if ( $post_id > 0 ) {
			$post_data['ID'] = $post_id;
			$saved_id = wp_update_post( $post_data );
		} else {
			$saved_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $saved_id ) || 0 === $saved_id ) {
			wp_send_json_error( array( 'message' => 'Error al guardar la publicación.' ) );
		}

		// Asignar Imagen Destacada
		if ( $thumb_id > 0 ) {
			set_post_thumbnail( $saved_id, $thumb_id );
		} elseif ( isset( $_POST['remove_thumbnail'] ) && '1' === $_POST['remove_thumbnail'] ) {
			delete_post_thumbnail( $saved_id );
		}

		// Asignar Categorías si el post type lo soporta
		if ( ! empty( $categories ) && is_object_in_taxonomy( $post_type, 'category' ) ) {
			wp_set_post_categories( $saved_id, $categories );
		}

		// Guardar Metadatos SEO (compatibilidad con WPAT SEO, Yoast y Rank Math)
		if ( ! empty( $seo_title ) ) {
			update_post_meta( $saved_id, '_wpat_seo_title', $seo_title );
			update_post_meta( $saved_id, '_yoast_wpseo_title', $seo_title );
			update_post_meta( $saved_id, 'rank_math_title', $seo_title );
		}
		if ( ! empty( $seo_desc ) ) {
			update_post_meta( $saved_id, '_wpat_seo_desc', $seo_desc );
			update_post_meta( $saved_id, '_yoast_wpseo_metadesc', $seo_desc );
			update_post_meta( $saved_id, 'rank_math_description', $seo_desc );
		}
		if ( ! empty( $seo_keyword ) ) {
			update_post_meta( $saved_id, '_wpat_seo_keyword', $seo_keyword );
			update_post_meta( $saved_id, '_yoast_wpseo_focuskw', $seo_keyword );
			update_post_meta( $saved_id, 'rank_math_focus_keyword', $seo_keyword );
		}

		// Guardar Campos CPT / ACF dinámicos
		if ( ! empty( $cpt_meta ) ) {
			foreach ( $cpt_meta as $key => $val ) {
				update_post_meta( $saved_id, sanitize_key( $key ), $val );
			}
		}

		wp_send_json_success( array(
			'message'   => 'Publicación guardada correctamente.',
			'post_id'   => $saved_id,
			'permalink' => get_permalink( $saved_id ),
		) );
	}

	/**
	 * Mueve una publicación a la papelera vía AJAX.
	 */
	public function ajax_trash_post() {
		check_ajax_referer( 'wpat_studio_nonce', 'security' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id > 0 ) {
			wp_trash_post( $post_id );
			wp_send_json_success( array( 'message' => 'Publicación enviada a la papelera.' ) );
		}

		wp_send_json_error( array( 'message' => 'ID de publicación no válido.' ) );
	}
}

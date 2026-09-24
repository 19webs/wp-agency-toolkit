<?php
/**
 * Módulo: Client Studio - Entorno de Redacción y Publicación SaaS para Clientes
 *
 * Ofrece un espacio de trabajo limpio, amigable y enfocado estilo Notion/Ghost
 * para clientes y redactores, con editor WYSIWYG, soporte para CPTs/ACF,
 * vista previa de Google SEO (compatible con Yoast/RankMath) y modo día/noche.
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
		// Endpoints AJAX para gestión de publicaciones
		add_action( 'wp_ajax_wpat_studio_save_post', array( $this, 'ajax_save_post' ) );
		add_action( 'wp_ajax_wpat_studio_trash_post', array( $this, 'ajax_trash_post' ) );

		// Añadir acceso directo en el menú de administración si el módulo está activo
		add_action( 'admin_menu', array( $this, 'add_studio_menu' ), 85 );
	}

	/**
	 * Registra el acceso a Client Studio en el menú de WordPress.
	 */
	public function add_studio_menu() {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! isset( $settings['client-studio'] ) || '1' !== (string) $settings['client-studio'] ) {
			return;
		}

		add_submenu_page(
			'wp-agency-toolkit',
			'Client Studio',
			'✍️ Client Studio',
			'edit_posts',
			'wpat-client-studio',
			array( WPAT_Admin::get_instance(), 'render_admin_page' )
		);
	}

	/**
	 * Obtiene los Post Types públicos compatibles para el selector del Studio.
	 *
	 * @return array
	 */
	public static function get_compatible_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		if ( isset( $types['attachment'] ) ) {
			unset( $types['attachment'] );
		}
		return $types;
	}

	/**
	 * Guarda o actualiza una publicación vía AJAX desde el Studio.
	 */
	public function ajax_save_post() {
		check_ajax_referer( 'wpat_client_studio_nonce', 'security' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos para editar publicaciones.' ) );
		}

		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post_type   = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'post';
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$content     = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$excerpt     = isset( $_POST['excerpt'] ) ? sanitize_textarea_field( $_POST['excerpt'] ) : '';
		$status      = isset( $_POST['post_status'] ) && in_array( $_POST['post_status'], array( 'publish', 'draft', 'pending' ), true ) ? $_POST['post_status'] : 'draft';
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

		// Guardar Metadatos SEO (compatibilidad con WPAT SEO y Yoast)
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
		check_ajax_referer( 'wpat_client_studio_nonce', 'security' );

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

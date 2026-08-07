<?php
/**
 * Módulo: Clonador de Entradas, Páginas y CPTs - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Duplicator {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Duplicator
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Duplicator
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
		// Añadir enlace "Duplicar" en las tablas de posts
		add_filter( 'post_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );

		// Procesar la acción de duplicación
		add_action( 'admin_action_wpat_duplicate_post', array( $this, 'process_post_duplication' ) );

		// Mostrar aviso de éxito
		add_action( 'admin_notices', array( $this, 'display_duplication_notice' ) );
	}

	/**
	 * Añade el enlace de acción "Duplicar" a los posts de la tabla.
	 *
	 * @param array   $actions Enlaces de acción actuales.
	 * @param WP_Post $post Objeto del post.
	 * @return array
	 */
	public function add_duplicate_link( $actions, $post ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=wpat_duplicate_post&post=' . $post->ID ),
			'wpat_duplicate_' . $post->ID
		);

		$actions['wpat_duplicate'] = sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr__( 'Duplicar esta entrada', 'wp-agency-toolkit' ),
			esc_html__( 'Duplicar', 'wp-agency-toolkit' )
		);

		return $actions;
	}

	/**
	 * Procesa la acción de duplicado de un post.
	 */
	public function process_post_duplication() {
		if ( ! isset( $_GET['post'] ) ) {
			wp_die( esc_html__( 'No se ha especificado ningún post para duplicar.', 'wp-agency-toolkit' ) );
		}

		$post_id = absint( $_GET['post'] );

		// Validar Nonce de seguridad
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wpat_duplicate_' . $post_id ) ) {
			wp_die( esc_html__( 'Fallo en la validación de seguridad.', 'wp-agency-toolkit' ) );
		}

		// Validar Permisos
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permisos para duplicar entradas.', 'wp-agency-toolkit' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_die( esc_html__( 'El post original no existe.', 'wp-agency-toolkit' ) );
		}

		$current_user = wp_get_current_user();

		// Crear argumentos para el nuevo post duplicado
		$new_post_args = array(
			'post_title'     => $post->post_title . ' (Copia)',
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => 'draft', // Siempre se crea como borrador
			'post_type'      => $post->post_type,
			'post_author'    => $current_user->ID,
			'post_parent'    => $post->post_parent,
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
		);

		$new_post_id = wp_insert_post( $new_post_args );

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html( $new_post_id->get_error_message() ) );
		}

		// 1. Clonar Taxonomías
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) {
				wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
			}
		}

		// 2. Clonar Metadatos del Post (ACF, JetEngine y otros)
		$post_meta = get_post_meta( $post_id );
		if ( ! empty( $post_meta ) ) {
			foreach ( $post_meta as $key => $values ) {
				foreach ( $values as $value ) {
					// Usar maybe_unserialize para evitar la doble serialización
					add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
				}
			}
		}

		// Redirigir de vuelta al listado de edición
		$redirect_url = admin_url( 'edit.php' );
		if ( 'post' !== $post->post_type ) {
			$redirect_url = add_query_arg( 'post_type', $post->post_type, $redirect_url );
		}
		$redirect_url = add_query_arg( 'wpat_duplicated', '1', $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Muestra una notificación de éxito al duplicar la entrada.
	 */
	public function display_duplication_notice() {
		if ( isset( $_GET['wpat_duplicated'] ) && '1' === $_GET['wpat_duplicated'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Entrada duplicada correctamente como Borrador.', 'wp-agency-toolkit' ); ?></p>
			</div>
			<?php
		}
	}
}

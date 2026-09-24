<?php
/**
 * Módulo: Diseño SaaS para Entradas, Productos & CPTs (Client Studio)
 *
 * Transforma de forma nativa e integrada los listados (edit.php) y las pantallas
 * de creación y edición (post-new.php / post.php) de WordPress en una experiencia
 * limpia, moderna y visual estilo SaaS (Notion/Ghost/Stripe).
 *
 * Utiliza plantillas modulares personalizadas en templates/client-studio/
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
		// 1. Assets y Clases de Body (edit.php, post.php, post-new.php)
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_studio_assets' ) );
		add_action( 'admin_init', array( $this, 'register_thumbnail_columns' ) );
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_classes' ) );

		// 2. Integración en Pantallas de Edición y Creación (post.php & post-new.php)
		add_action( 'load-post.php', array( $this, 'intercept_native_editor_screen' ) );
		add_action( 'load-post-new.php', array( $this, 'intercept_native_editor_screen' ) );

		// 3. Endpoints AJAX para guardado y papelera desde el Studio
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
	 * Renderiza una plantilla personalizada desde templates/client-studio/
	 *
	 * @param string $template_name Nombre del archivo sin .php
	 * @param array  $args          Argumentos pasados a la vista
	 */
	public static function render_template( $template_name, $args = array() ) {
		$file = WPAT_DIR . 'templates/client-studio/' . sanitize_file_name( $template_name ) . '.php';
		if ( file_exists( $file ) ) {
			include $file;
		}
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
			$post_type = ! empty( $typenow ) ? $typenow : 'post';
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
	 * Inyecta clases en el body para activar el diseño SaaS en tablas y editor.
	 *
	 * @param string $classes
	 * @return string
	 */
	public function add_admin_body_classes( $classes ) {
		global $pagenow;

		if ( 'edit.php' === $pagenow && $this->is_active_for_context() ) {
			$settings = WPAT_Main::get_instance()->get_settings();
			$density  = isset( $settings['studio_density'] ) ? $settings['studio_density'] : 'comfortable';
			$hover    = isset( $settings['studio_hover'] ) ? $settings['studio_hover'] : 'subtle';

			$classes .= ' wpat-saas-tables-active';
			$classes .= ' wpat-tables-density-' . sanitize_html_class( $density );
			$classes .= ' wpat-tables-hover-' . sanitize_html_class( $hover );

			if ( isset( $settings['studio_sticky_header'] ) && '1' === (string) $settings['studio_sticky_header'] ) {
				$classes .= ' wpat-tables-sticky-header';
			}
		}

		if ( ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) && $this->is_active_for_context() ) {
			$classes .= ' wpat-studio-active';
		}

		return $classes;
	}

	/**
	 * Encola estilos y scripts de Client Studio según la pantalla.
	 *
	 * @param string $hook
	 */
	public function enqueue_studio_assets( $hook ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! isset( $settings['client-studio'] ) || '1' !== (string) $settings['client-studio'] ) {
			return;
		}

		// 1. Pantalla de Listados (edit.php)
		if ( 'edit.php' === $hook && $this->is_active_for_context() ) {
			wp_enqueue_style(
				'wpat-admin-tables-ui-css',
				WPAT_URL . 'assets/css/wpat-admin-tables-ui.css',
				array(),
				time()
			);

			wp_enqueue_script(
				'wpat-admin-tables-ui-js',
				WPAT_URL . 'assets/js/wpat-admin-tables-ui.js',
				array( 'jquery' ),
				time(),
				true
			);

			wp_localize_script(
				'wpat-admin-tables-ui-js',
				'wpatTablesConfig',
				array(
					'enablePills'       => ! isset( $settings['studio_pills'] ) || '1' === (string) $settings['studio_pills'],
					'enableRowActions'  => true,
					'enableSticky'      => isset( $settings['studio_sticky_header'] ) && '1' === (string) $settings['studio_sticky_header'],
					'enableThumbZoom'   => ! isset( $settings['studio_thumb_zoom'] ) || '1' === (string) $settings['studio_thumb_zoom'],
				)
			);
		}

		// 2. Pantalla de Edición / Creación (post.php, post-new.php)
		if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && $this->is_active_for_context() ) {
			wp_enqueue_media();

			wp_enqueue_style(
				'wpat-client-studio-css',
				WPAT_URL . 'assets/css/wpat-client-studio.css',
				array(),
				time()
			);

			wp_enqueue_script(
				'wpat-client-studio-js',
				WPAT_URL . 'assets/js/wpat-client-studio.js',
				array( 'jquery' ),
				time(),
				true
			);

			wp_localize_script(
				'wpat-client-studio-js',
				'wpatStudioConfig',
				array(
					'nonce' => wp_create_nonce( 'wpat_studio_nonce' ),
				)
			);
		}
	}

	/**
	 * Registra columna de miniatura en edit.php para post types que no la tengan.
	 */
	public function register_thumbnail_columns() {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! isset( $settings['client-studio'] ) || '1' !== (string) $settings['client-studio'] ) {
			return;
		}

		if ( ! isset( $settings['studio_show_thumbs'] ) || '1' === (string) $settings['studio_show_thumbs'] ) {
			$post_types = self::get_supported_post_types();
			foreach ( $post_types as $pt => $obj ) {
				if ( 'product' === $pt ) {
					continue;
				}
				add_filter( "manage_{$pt}_posts_columns", array( $this, 'inject_thumb_column_header' ), 5 );
				add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_thumb_column_content' ), 10, 2 );
			}
		}
	}

	public function inject_thumb_column_header( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'title' === $key ) {
				$new_columns['wpat_thumb'] = '<span class="dashicons dashicons-format-image" title="' . esc_attr__( 'Imagen Destacada', 'wp-agency-toolkit' ) . '" style="font-size:16px; color:#64748b;"></span>';
			}
			$new_columns[ $key ] = $title;
		}
		if ( ! isset( $new_columns['wpat_thumb'] ) ) {
			$new_columns = array( 'wpat_thumb' => 'Imagen' ) + $columns;
		}
		return $new_columns;
	}

	public function render_thumb_column_content( $column_name, $post_id ) {
		if ( 'wpat_thumb' === $column_name ) {
			if ( has_post_thumbnail( $post_id ) ) {
				$thumb_id  = get_post_thumbnail_id( $post_id );
				$small_url = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
				$large_url = wp_get_attachment_image_url( $thumb_id, 'medium' );
				$edit_link = get_edit_post_link( $post_id );
				?>
				<div class="wpat-table-thumb-wrap" data-large-img="<?php echo esc_url( $large_url ); ?>">
					<a href="<?php echo esc_url( $edit_link ); ?>">
						<img src="<?php echo esc_url( $small_url ); ?>" alt="" class="wpat-table-thumb-img" />
					</a>
				</div>
				<?php
			} else {
				?>
				<div class="wpat-table-thumb-placeholder">
					<span class="dashicons dashicons-format-image"></span>
				</div>
				<?php
			}
		}
	}

	/**
	 * Intercepta la pantalla nativa de edición para renderizar el Studio SaaS.
	 */
	public function intercept_native_editor_screen() {
		global $typenow;
		$post_type = ! empty( $typenow ) ? $typenow : ( isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post' );

		if ( isset( $_GET['post'] ) ) {
			$post = get_post( absint( $_GET['post'] ) );
			if ( $post ) {
				$post_type = $post->post_type;
			}
		}

		if ( ! $this->is_active_for_context( $post_type ) ) {
			return;
		}

		// Deshabilitar el editor de bloques Gutenberg y cargar la plantilla SaaS
		add_filter( 'use_block_editor_for_post_type', '__return_false', 999 );
		add_action( 'edit_form_top', array( $this, 'render_studio_editor_override' ), 1 );
	}

	/**
	 * Renderiza el espacio de trabajo de Client Studio invocando la plantilla personalizada.
	 *
	 * @param WP_Post|null $post
	 */
	public function render_studio_editor_override( $post = null ) {
		if ( ! $post ) {
			global $post;
		}

		$post_id   = $post ? $post->ID : 0;
		$post_type = $post ? $post->post_type : ( isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post' );
		$pt_obj    = get_post_type_object( $post_type );
		$pt_label  = $pt_obj ? $pt_obj->labels->singular_name : 'Entrada';

		$title     = $post ? $post->post_title : '';
		$content   = $post ? $post->post_content : '';
		$excerpt   = $post ? $post->post_excerpt : '';
		$status    = $post ? $post->post_status : 'draft';
		$thumb_id  = $post ? get_post_thumbnail_id( $post->ID ) : 0;
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
		$post_name = $post ? $post->post_name : '';

		// Metadatos SEO
		$seo_title   = $post ? get_post_meta( $post->ID, '_wpat_seo_title', true ) : '';
		if ( empty( $seo_title ) && $post ) {
			$seo_title = get_post_meta( $post->ID, '_yoast_wpseo_title', true );
		}
		$seo_desc    = $post ? get_post_meta( $post->ID, '_wpat_seo_desc', true ) : '';
		if ( empty( $seo_desc ) && $post ) {
			$seo_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		}
		$seo_keyword = $post ? get_post_meta( $post->ID, '_wpat_seo_keyword', true ) : '';
		$permalink   = $post ? get_permalink( $post->ID ) : '';
		$back_url    = admin_url( 'edit.php' . ( 'post' !== $post_type ? '?post_type=' . $post_type : '' ) );

		self::render_template(
			'editor',
			array(
				'post'        => $post,
				'post_id'     => $post_id,
				'post_type'   => $post_type,
				'pt_label'    => $pt_label,
				'title'       => $title,
				'content'     => $content,
				'excerpt'     => $excerpt,
				'status'      => $status,
				'thumb_id'    => $thumb_id,
				'thumb_url'   => $thumb_url,
				'post_name'   => $post_name,
				'permalink'   => $permalink,
				'back_url'    => $back_url,
				'seo_title'   => $seo_title,
				'seo_desc'    => $seo_desc,
				'seo_keyword' => $seo_keyword,
			)
		);
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

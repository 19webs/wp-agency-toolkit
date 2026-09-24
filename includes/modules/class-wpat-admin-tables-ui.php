<?php
/**
 * Módulo: Diseño Moderno SaaS para Listados (Entradas, Páginas, Productos & CPTs)
 *
 * Transforma la interfaz clásica de las tablas de administración (edit.php)
 * en un diseño limpio, moderno y ergonómico estilo SaaS (Stripe/Linear/Shopify),
 * manteniendo total compatibilidad con plugins de terceros (Yoast, WooCommerce, ACF, etc.).
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Admin_Tables_UI {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_Admin_Tables_UI
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Admin_Tables_UI
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
		// Encolar estilos y scripts en las pantallas edit.php
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_table_assets' ) );

		// Añadir columna de imagen destacada para post types seleccionados
		add_action( 'admin_init', array( $this, 'register_thumbnail_columns' ) );

		// Clases en el body del panel para activar los estilos SaaS
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_classes' ) );
	}

	/**
	 * Obtiene la lista de todos los post types públicos disponibles.
	 *
	 * @return array
	 */
	public static function get_supported_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		
		// Excluir attachments
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		return $post_types;
	}

	/**
	 * Comprueba si la pantalla actual debe tener activo el diseño SaaS.
	 *
	 * @param string $hook Hook de la pantalla.
	 * @return bool
	 */
	public function is_active_for_current_screen( $hook = '' ) {
		if ( empty( $hook ) ) {
			global $pagenow;
			$hook = $pagenow;
		}

		if ( 'edit.php' !== $hook ) {
			return false;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! isset( $settings['admin-tables-ui'] ) || '1' !== (string) $settings['admin-tables-ui'] ) {
			return false;
		}

		// Determinar post type actual
		global $typenow;
		$current_pt = ! empty( $typenow ) ? $typenow : 'post';

		// Comprobar si este post type está habilitado
		$enabled_pts = isset( $settings['tables_ui_post_types'] ) && is_array( $settings['tables_ui_post_types'] )
			? $settings['tables_ui_post_types']
			: array( 'post', 'page', 'product' );

		return in_array( $current_pt, $enabled_pts, true );
	}

	/**
	 * Inyecta clases en el body de la administración para activar el motor CSS.
	 *
	 * @param string $classes Clases existentes.
	 * @return string
	 */
	public function add_admin_body_classes( $classes ) {
		if ( $this->is_active_for_current_screen() ) {
			$settings = WPAT_Main::get_instance()->get_settings();
			$density  = isset( $settings['tables_ui_density'] ) ? $settings['tables_ui_density'] : 'comfortable';
			$hover    = isset( $settings['tables_ui_hover'] ) ? $settings['tables_ui_hover'] : 'subtle';

			$classes .= ' wpat-saas-tables-active';
			$classes .= ' wpat-tables-density-' . sanitize_html_class( $density );
			$classes .= ' wpat-tables-hover-' . sanitize_html_class( $hover );

			if ( isset( $settings['tables_ui_sticky_header'] ) && '1' === (string) $settings['tables_ui_sticky_header'] ) {
				$classes .= ' wpat-tables-sticky-header';
			}

			if ( isset( $settings['tables_ui_pills'] ) && '0' === (string) $settings['tables_ui_pills'] ) {
				$classes .= ' wpat-tables-no-pills';
			}
		}
		return $classes;
	}

	/**
	 * Encola los estilos y scripts modernos en edit.php de forma no destructiva.
	 *
	 * @param string $hook
	 */
	public function enqueue_table_assets( $hook ) {
		if ( ! $this->is_active_for_current_screen( $hook ) ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

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
				'enablePills'       => ! isset( $settings['tables_ui_pills'] ) || '1' === (string) $settings['tables_ui_pills'],
				'enableRowActions'  => ! isset( $settings['tables_ui_actions_style'] ) || 'modern' === $settings['tables_ui_actions_style'],
				'enableSticky'      => isset( $settings['tables_ui_sticky_header'] ) && '1' === (string) $settings['tables_ui_sticky_header'],
				'enableThumbZoom'   => ! isset( $settings['tables_ui_thumb_zoom'] ) || '1' === (string) $settings['tables_ui_thumb_zoom'],
				'i18n'              => array(
					'published' => __( 'Publicado', 'wp-agency-toolkit' ),
					'draft'     => __( 'Borrador', 'wp-agency-toolkit' ),
					'pending'   => __( 'Pendiente', 'wp-agency-toolkit' ),
					'private'   => __( 'Privado', 'wp-agency-toolkit' ),
					'scheduled' => __( 'Programado', 'wp-agency-toolkit' ),
					'trash'     => __( 'Papelera', 'wp-agency-toolkit' ),
				),
			)
		);
	}

	/**
	 * Registra dinámicamente columnas de imagen destacada si está activado en ajustes.
	 */
	public function register_thumbnail_columns() {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( ! isset( $settings['admin-tables-ui'] ) || '1' !== (string) $settings['admin-tables-ui'] ) {
			return;
		}

		if ( isset( $settings['tables_ui_show_thumbs'] ) && '1' === (string) $settings['tables_ui_show_thumbs'] ) {
			$post_types = self::get_supported_post_types();
			foreach ( $post_types as $pt => $obj ) {
				// No duplicar en WooCommerce product si ya tiene columna 'thumb' o 'image'
				if ( 'product' === $pt ) {
					continue;
				}
				add_filter( "manage_{$pt}_posts_columns", array( $this, 'inject_thumb_column_header' ), 5 );
				add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_thumb_column_content' ), 10, 2 );
			}
		}
	}

	/**
	 * Añade la cabecera de la columna de miniatura destacada.
	 *
	 * @param array $columns
	 * @return array
	 */
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

	/**
	 * Renderiza el contenido de la columna de miniatura destacada.
	 *
	 * @param string $column_name
	 * @param int    $post_id
	 */
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
}

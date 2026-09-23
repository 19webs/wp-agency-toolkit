<?php
/**
 * Módulo: Clonador / Duplicador de Entradas, Páginas y CPTs - WP Agency Toolkit
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
	 * Ajustes cacheados del módulo.
	 *
	 * @var array
	 */
	private $settings = array();

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
		$this->settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : get_option( 'wpat_settings', array() );

		// 1. Añadir enlaces "Duplicar" en las tablas de listados
		add_filter( 'post_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_duplicate_link' ), 10, 2 );

		// 2. Registrar acciones dinámicas en Custom Post Types y WooCommerce
		add_action( 'admin_init', array( $this, 'register_cpt_hooks' ) );

		// 3. Procesar la acción individual de duplicación
		add_action( 'admin_action_wpat_duplicate_post', array( $this, 'process_post_duplication' ) );

		// 4. Mostrar avisos de éxito
		add_action( 'admin_notices', array( $this, 'display_duplication_notice' ) );

		// 5. Botón en la Barra Superior de WordPress (Admin Bar)
		if ( $this->get_setting( 'duplicator_show_admin_bar', '1' ) === '1' ) {
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_duplicate_link' ), 100 );
		}

		// 6. Botón de duplicar dentro del editor (Gutenberg / Clásico)
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_submitbox_duplicate_button' ) );
		add_action( 'post_submitbox_start', array( $this, 'add_submitbox_duplicate_button' ) );
	}

	/**
	 * Obtiene un ajuste específico con valor fallback.
	 *
	 * @param string $key Clave del ajuste.
	 * @param mixed  $default Valor por defecto.
	 * @return mixed
	 */
	private function get_setting( $key, $default = '' ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Obtiene la lista de Post Types permitidos para duplicar.
	 *
	 * @return array
	 */
	public function get_allowed_post_types() {
		$allowed = $this->get_setting( 'duplicator_post_types', array( 'post', 'page', 'product' ) );
		if ( ! is_array( $allowed ) ) {
			$allowed = array( 'post', 'page', 'product' );
		}
		return $allowed;
	}

	/**
	 * Registra hooks para CPTs y acciones en lote (Bulk Actions).
	 */
	public function register_cpt_hooks() {
		$allowed_types = $this->get_allowed_post_types();

		foreach ( $allowed_types as $post_type ) {
			// Enlace en filas de CPT
			if ( 'post' !== $post_type && 'page' !== $post_type ) {
				add_filter( "{$post_type}_row_actions", array( $this, 'add_duplicate_link' ), 10, 2 );
			}

			// Acciones en lote (Bulk Actions)
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'register_bulk_action' ) );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_action' ), 10, 3 );
		}
	}

	/**
	 * Añade el enlace de acción "Duplicar" a los posts de la tabla.
	 *
	 * @param array   $actions Enlaces de acción actuales.
	 * @param WP_Post $post    Objeto del post.
	 * @return array
	 */
	public function add_duplicate_link( $actions, $post ) {
		if ( ! $post || ! is_object( $post ) ) {
			return $actions;
		}

		$allowed_types = $this->get_allowed_post_types();
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return $actions;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=wpat_duplicate_post&post=' . $post->ID ),
			'wpat_duplicate_' . $post->ID
		);

		$actions['wpat_duplicate'] = sprintf(
			'<a href="%s" title="%s" style="color: #4f46e5; font-weight: 600;">%s</a>',
			esc_url( $url ),
			esc_attr__( 'Clonar este contenido como borrador', 'wp-agency-toolkit' ),
			esc_html__( 'Duplicar', 'wp-agency-toolkit' )
		);

		return $actions;
	}

	/**
	 * Registra la acción "Duplicar" en el desplegable de Acciones en Lote.
	 *
	 * @param array $bulk_actions
	 * @return array
	 */
	public function register_bulk_action( $bulk_actions ) {
		$bulk_actions['wpat_bulk_duplicate'] = __( 'Duplicar seleccionados', 'wp-agency-toolkit' );
		return $bulk_actions;
	}

	/**
	 * Procesa la acción en lote de duplicación.
	 *
	 * @param string $redirect_to URL de redirección.
	 * @param string $doaction    Acción ejecutada.
	 * @param array  $post_ids    Array de IDs seleccionados.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_to, $doaction, $post_ids ) {
		if ( 'wpat_bulk_duplicate' !== $doaction || empty( $post_ids ) ) {
			return $redirect_to;
		}

		$duplicated_count = 0;
		foreach ( $post_ids as $post_id ) {
			$new_id = $this->duplicate_post( (int) $post_id, false );
			if ( $new_id && ! is_wp_error( $new_id ) ) {
				$duplicated_count++;
			}
		}

		$redirect_to = add_query_arg( array(
			'wpat_bulk_duplicated' => $duplicated_count,
		), $redirect_to );

		return $redirect_to;
	}

	/**
	 * Añade el botón de duplicar a la Barra de Administración superior de WordPress.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function add_admin_bar_duplicate_link( $wp_admin_bar ) {
		if ( ! is_admin() && ! is_singular() ) {
			return;
		}

		$post_id = 0;
		if ( is_admin() ) {
			global $post;
			if ( $post && isset( $post->ID ) ) {
				$post_id = $post->ID;
			}
		} elseif ( is_singular() ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$allowed_types = $this->get_allowed_post_types();
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=wpat_duplicate_post&post=' . $post_id ),
			'wpat_duplicate_' . $post_id
		);

		$wp_admin_bar->add_node( array(
			'id'    => 'wpat-duplicate-post',
			'title' => '<span class="ab-icon dashicons dashicons-admin-page" style="margin-top:2px;"></span> ' . __( 'Duplicar', 'wp-agency-toolkit' ),
			'href'  => $url,
			'meta'  => array(
				'title' => __( 'Duplicar esta entrada o página con WP Agency Toolkit', 'wp-agency-toolkit' ),
			),
		) );
	}

	/**
	 * Añade un botón rápido de duplicar en el panel lateral de publicación del editor clásico.
	 */
	public function add_submitbox_duplicate_button() {
		global $post;
		static $rendered = false;
		if ( $rendered || ! $post || ! isset( $post->ID ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		$allowed_types = $this->get_allowed_post_types();
		if ( ! in_array( $post->post_type, $allowed_types, true ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$rendered = true;
		$url = wp_nonce_url(
			admin_url( 'admin.php?action=wpat_duplicate_post&post=' . $post->ID ),
			'wpat_duplicate_' . $post->ID
		);
		?>
		<div class="misc-pub-section wpat-submitbox-duplicate" style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #f1f5f9; padding: 10px 0;">
			<span class="dashicons dashicons-admin-page" style="color: #64748b; font-size: 16px; width: 16px; height: 16px;"></span>
			<a href="<?php echo esc_url( $url ); ?>" class="button button-small" style="font-size: 12px; height: 26px; line-height: 24px; color: #4338ca; border-color: #c7d2fe; background: #eef2ff;">
				<?php esc_html_e( 'Duplicar como nuevo borrador', 'wp-agency-toolkit' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Procesa la acción de duplicado individual de un post.
	 */
	public function process_post_duplication() {
		if ( ! isset( $_GET['post'] ) ) {
			wp_die( esc_html__( 'No se ha especificado ningún post para duplicar.', 'wp-agency-toolkit' ), '', array( 'back_link' => true ) );
		}

		$post_id = absint( $_GET['post'] );

		// Validar Nonce de seguridad
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'wpat_duplicate_' . $post_id ) ) {
			wp_die( esc_html__( 'Fallo en la validación de seguridad.', 'wp-agency-toolkit' ), '', array( 'back_link' => true ) );
		}

		// Validar Permisos
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'No tienes permisos para duplicar esta entrada.', 'wp-agency-toolkit' ), '', array( 'back_link' => true ) );
		}

		$new_post_id = $this->duplicate_post( $post_id, true );

		if ( is_wp_error( $new_post_id ) ) {
			wp_die( esc_html( $new_post_id->get_error_message() ), '', array( 'back_link' => true ) );
		}
	}

	/**
	 * Realiza la clonación completa del post (contenido, taxonomías, metadatos, ACF, Elementor, WooCommerce).
	 *
	 * @param int  $post_id  ID del post a duplicar.
	 * @param bool $redirect Si debe ejecutar la redirección final.
	 * @return int|WP_Error  ID del nuevo post o WP_Error.
	 */
	public function duplicate_post( $post_id, $redirect = false ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', __( 'El post original no existe.', 'wp-agency-toolkit' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'insufficient_permissions', __( 'No tienes permisos para duplicar esta entrada.', 'wp-agency-toolkit' ) );
		}

		$current_user = wp_get_current_user();

		// 1. Configurar Título y Sufijo
		$suffix_template = $this->get_setting( 'duplicator_title_suffix', ' (Copia)' );
		if ( false !== strpos( $suffix_template, '{title}' ) ) {
			$new_title = str_replace( '{title}', $post->post_title, $suffix_template );
		} else {
			$new_title = $post->post_title . $suffix_template;
		}

		// 2. Configurar Estado del Post Cloned
		$target_status = $this->get_setting( 'duplicator_post_status', 'draft' );
		$valid_statuses = array( 'draft', 'pending', 'publish', 'private' );
		if ( ! in_array( $target_status, $valid_statuses, true ) ) {
			$target_status = 'draft';
		}

		// 3. Configurar Autor
		$copy_author_opt = $this->get_setting( 'duplicator_copy_author', 'current' );
		$target_author   = ( 'original' === $copy_author_opt && ! empty( $post->post_author ) ) ? $post->post_author : $current_user->ID;

		// 4. Configurar Fecha
		$copy_date_opt = $this->get_setting( 'duplicator_copy_date', 'current' );
		$target_date   = ( 'original' === $copy_date_opt ) ? $post->post_date : current_time( 'mysql' );
		$target_date_gmt = ( 'original' === $copy_date_opt ) ? $post->post_date_gmt : get_gmt_from_date( $target_date );

		// Crear argumentos para el nuevo post duplicado
		$new_post_args = array(
			'post_title'     => $new_title,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => $target_status,
			'post_type'      => $post->post_type,
			'post_author'    => (int) $target_author,
			'post_parent'    => (int) $post->post_parent,
			'menu_order'     => (int) $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_password'  => $post->post_password,
			'post_date'      => $target_date,
			'post_date_gmt'  => $target_date_gmt,
		);

		// Insertar nuevo post
		$new_post_id = wp_insert_post( $new_post_args );

		if ( is_wp_error( $new_post_id ) || empty( $new_post_id ) ) {
			return is_wp_error( $new_post_id ) ? $new_post_id : new WP_Error( 'insert_failed', __( 'No se pudo crear la entrada clonada.', 'wp-agency-toolkit' ) );
		}

		// 5. Clonar Taxonomías (si está habilitado)
		if ( $this->get_setting( 'duplicator_copy_taxonomies', '1' ) === '1' ) {
			$taxonomies = get_object_taxonomies( $post->post_type );
			foreach ( $taxonomies as $taxonomy ) {
				$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
				if ( ! is_wp_error( $post_terms ) && ! empty( $post_terms ) ) {
					wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
				}
			}
		}

		// 6. Clonar Metadatos del Post (ACF, JetEngine, Elementor, Divi, WooCommerce)
		if ( $this->get_setting( 'duplicator_copy_meta', '1' ) === '1' ) {
			$post_meta = get_post_meta( $post_id );
			if ( ! empty( $post_meta ) && is_array( $post_meta ) ) {
				// Claves de sistema que no deben clonarse
				$excluded_meta = array(
					'_edit_lock',
					'_edit_last',
					'_wp_old_slug',
					'_wp_old_date',
				);

				foreach ( $post_meta as $meta_key => $meta_values ) {
					if ( in_array( $meta_key, $excluded_meta, true ) ) {
						continue;
					}

					foreach ( $meta_values as $meta_value ) {
						// Soporte especial para Elementor JSON data (evita romper caracteres escapados)
						if ( '_elementor_data' === $meta_key ) {
							$elementor_raw = get_post_meta( $post_id, '_elementor_data', true );
							if ( is_string( $elementor_raw ) ) {
								$elementor_data = json_decode( $elementor_raw, true );
								if ( $elementor_data ) {
									update_post_meta( $new_post_id, '_elementor_data', wp_slash( $elementor_raw ) );
									continue;
								}
							}
						}

						// Soporte especial WooCommerce: limpiar SKU para evitar duplicados si es producto
						if ( '_sku' === $meta_key && 'product' === $post->post_type && ! empty( $meta_value ) ) {
							$meta_value = $meta_value . '-copia';
						}

						// Deserializar si es necesario para evitar doble serialización
						$unserialized = maybe_unserialize( $meta_value );
						add_post_meta( $new_post_id, $meta_key, $unserialized );
					}
				}
			}
		}

		// 7. Disparar hook de acción para plugins y extensiones de terceros
		do_action( 'wpat_after_duplicate_post', $new_post_id, $post_id, $new_post_args );

		// 8. Redirección si se solicita
		if ( $redirect ) {
			$redirect_opt = $this->get_setting( 'duplicator_redirect_to', 'edit' );

			if ( 'edit' === $redirect_opt ) {
				// Abrir directamente la pantalla de edición del nuevo clon
				$redirect_url = admin_url( 'post.php?action=edit&post=' . $new_post_id . '&wpat_duplicated=1' );
			} else {
				// Volver a la lista de administración
				$redirect_url = admin_url( 'edit.php' );
				if ( 'post' !== $post->post_type ) {
					$redirect_url = add_query_arg( 'post_type', $post->post_type, $redirect_url );
				}
				$redirect_url = add_query_arg( array(
					'wpat_duplicated' => '1',
					'wpat_cloned_id'  => $new_post_id,
				), $redirect_url );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		return $new_post_id;
	}

	/**
	 * Muestra una notificación visual informativa tras la duplicación individual o en lote.
	 */
	public function display_duplication_notice() {
		if ( isset( $_GET['wpat_duplicated'] ) && '1' === $_GET['wpat_duplicated'] ) {
			$cloned_id = isset( $_GET['wpat_cloned_id'] ) ? absint( $_GET['wpat_cloned_id'] ) : 0;
			$edit_link = $cloned_id ? get_edit_post_link( $cloned_id ) : '';
			?>
			<div class="notice notice-success is-dismissible" style="border-left-color: #10b981; padding: 10px 15px;">
				<p style="font-size: 13.5px; margin: 0; display: flex; align-items: center; gap: 8px;">
					<span style="color: #10b981; font-weight: bold; font-size: 16px;">✓</span>
					<strong><?php esc_html_e( 'Contenido duplicado correctamente.', 'wp-agency-toolkit' ); ?></strong>
					<?php if ( $edit_link ) : ?>
						<a href="<?php echo esc_url( $edit_link ); ?>" class="button button-small" style="margin-left: 8px;">
							<?php esc_html_e( 'Editar nuevo clon ahora', 'wp-agency-toolkit' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['wpat_bulk_duplicated'] ) ) {
			$count = absint( $_GET['wpat_bulk_duplicated'] );
			?>
			<div class="notice notice-success is-dismissible" style="border-left-color: #10b981; padding: 10px 15px;">
				<p style="font-size: 13.5px; margin: 0;">
					<span style="color: #10b981; font-weight: bold; font-size: 16px;">✓</span>
					<?php
					echo sprintf(
						/* translators: %d: Número de elementos duplicados */
						esc_html( _n( 'Se ha duplicado %d elemento correctamente.', 'Se han duplicado %d elementos correctamente.', $count, 'wp-agency-toolkit' ) ),
						(int) $count
					);
					?>
				</p>
			</div>
			<?php
		}
	}
}

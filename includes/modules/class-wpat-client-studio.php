<?php
/**
 * Módulo: Diseño SaaS para Entradas, Productos & CPTs (Client Studio)
 *
 * Transforma de forma nativa e integrada los listados (edit.php) y las pantallas
 * de creación y edición (post-new.php / post.php) de WordPress en una experiencia
 * limpia, moderna y visual estilo SaaS (Notion/Ghost/Stripe).
 *
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
		// 1. Integración en Listados de WordPress (edit.php)
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_table_assets' ) );
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
	 * =========================================================================
	 * 1. INTEGRACIÓN NATIVA EN LISTADOS (edit.php)
	 * =========================================================================
	 */

	/**
	 * Inyecta clases en el body de edit.php para activar el diseño SaaS.
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
		return $classes;
	}

	/**
	 * Encola estilos y scripts en edit.php cuando el módulo está activo.
	 *
	 * @param string $hook
	 */
	public function enqueue_list_table_assets( $hook ) {
		if ( 'edit.php' !== $hook || ! $this->is_active_for_context() ) {
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
				'enablePills'       => ! isset( $settings['studio_pills'] ) || '1' === (string) $settings['studio_pills'],
				'enableRowActions'  => true,
				'enableSticky'      => isset( $settings['studio_sticky_header'] ) && '1' === (string) $settings['studio_sticky_header'],
				'enableThumbZoom'   => ! isset( $settings['studio_thumb_zoom'] ) || '1' === (string) $settings['studio_thumb_zoom'],
			)
		);
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
	 * =========================================================================
	 * 2. INTEGRACIÓN NATIVA EN EDICIÓN Y CREACIÓN (post.php & post-new.php)
	 * =========================================================================
	 */

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

		// Deshabilitar el editor de bloques Gutenberg / pantalla estándar y cargar nuestra vista limpia
		add_filter( 'use_block_editor_for_post_type', '__return_false', 999 );
		add_action( 'edit_form_top', array( $this, 'render_studio_editor_override' ), 1 );
	}

	/**
	 * Renderiza el espacio de trabajo de Client Studio directamente dentro de post.php / post-new.php.
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
		$post_cats = $post ? wp_get_post_categories( $post->ID ) : array();

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

		$back_url = admin_url( 'edit.php' . ( 'post' !== $post_type ? '?post_type=' . $post_type : '' ) );
		?>
		<!-- Ocultar metaboxes desordenadas nativas de WordPress vía CSS -->
		<style>
			#poststuff #post-body.columns-2 { margin-right: 0 !important; }
			#poststuff #postbox-container-1, #poststuff #postbox-container-2, #post-body-content, #titlediv, #postdivrich, #postexcerpt, #authordiv, #commentstatusdiv { display: none !important; }
			#wpbody-content .wrap > h1, #wpbody-content .wrap > .page-title-action { display: none !important; }
		</style>

		<input type="hidden" id="wpat_studio_post_id" value="<?php echo esc_attr( $post_id ); ?>" />
		<input type="hidden" id="wpat_studio_post_type" value="<?php echo esc_attr( $post_type ); ?>" />
		<input type="hidden" id="wpat_studio_thumbnail_id" value="<?php echo esc_attr( $thumb_id ); ?>" />
		<input type="hidden" id="wpat_studio_remove_thumb_flag" value="0" />

		<div class="wpat-studio-wrap" style="margin-top: 10px; margin-bottom: 40px;">

			<!-- BARRA SUPERIOR DE ACCIONES STICKY -->
			<div class="wpat-studio-card" style="padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 24px; position: sticky; top: 32px; z-index: 100;">
				<div style="display: flex; align-items: center; gap: 12px;">
					<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary" style="height: 36px; line-height: 34px; font-weight: 700; border-radius: 6px;">
						← Volver al Listado
					</a>
					<span id="wpat_studio_autosave_status" style="font-size: 12px; color: #10b981; font-weight: 600;">
						<?php echo $post_id ? '🟢 Editando ' . esc_html( $pt_label ) : '✨ Nueva ' . esc_html( $pt_label ); ?>
					</span>
				</div>

				<div style="display: flex; align-items: center; gap: 10px;">
					<?php if ( $permalink ) : ?>
						<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" id="wpat_studio_preview_link" class="button button-secondary" style="height: 36px; line-height: 34px; font-weight: 600; border-radius: 6px;">
							👁️ Ver en la Web
						</a>
					<?php endif; ?>
					<button type="button" class="button button-secondary wpat-studio-save-action" data-status="draft" style="height: 36px; line-height: 34px; font-weight: 700; border-radius: 6px;">
						💾 Guardar Borrador
					</button>
					<button type="button" class="button button-primary wpat-studio-save-action" data-status="publish" style="background: #2563eb; border-color: #1d4ed8; font-weight: 700; height: 36px; line-height: 34px; padding: 0 20px; border-radius: 6px; box-shadow: 0 2px 4px rgba(37,99,235,0.2);">
						🚀 Publicar en la Web
					</button>
				</div>
			</div>

			<!-- WORKSPACE DE 2 COLUMNAS (CONTENIDO + BARRA LATERAL) -->
			<div style="display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start;">
				
				<!-- COLUMNA IZQUIERDA: TÍTULO, WYSIWYG & CAMPOS -->
				<div style="display: flex; flex-direction: column; gap: 24px;">

					<!-- TARJETA PRINCIPAL: TÍTULO Y EDITOR WYSIWYG -->
					<div class="wpat-studio-card" style="padding: 24px;">
						<div style="margin-bottom: 20px;">
							<label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
								Título de <?php echo esc_html( $pt_label ); ?>
							</label>
							<input type="text" id="wpat_studio_post_title" value="<?php echo esc_attr( $title ); ?>" placeholder="Escribe un título claro y atractivo..." style="width: 100%; font-size: 24px; font-weight: 800; color: inherit; background: transparent; border: none; border-bottom: 2px solid #e2e8f0; padding: 6px 0; outline: none;" />
						</div>

						<!-- Editor WYSIWYG Nativo Simplificado -->
						<div style="margin-bottom: 16px;">
							<label style="display: block; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
								Contenido Principal (WYSIWYG)
							</label>
							<?php
							wp_editor(
								$content,
								'wpat_studio_content_editor',
								array(
									'textarea_rows' => 16,
									'media_buttons' => true,
									'teeny'         => false,
									'tinymce'       => array(
										'toolbar1' => 'formatselect,bold,italic,underline,blockquote,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,wp_more,fullscreen',
										'toolbar2' => '',
									),
									'quicktags'     => true,
								)
							);
							?>
						</div>

						<!-- Métricas en Vivo -->
						<div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 14px; font-size: 12px; color: #64748b;">
							<div style="display: flex; gap: 16px;">
								<span>📊 Palabras: <strong id="wpat_studio_word_count">0</strong></span>
								<span>⏱️ Lectura: <strong id="wpat_studio_read_time">1 min</strong></span>
							</div>
							<span class="wpat-studio-badge wpat-studio-badge-publish">🟢 Vista Limpia</span>
						</div>
					</div>

					<!-- TARJETA: CAMPOS PERSONALIZADOS CPT / ACF DINÁMICOS -->
					<?php if ( 'post' !== $post_type && 'page' !== $post_type ) : ?>
						<div class="wpat-studio-card" style="padding: 24px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
								<h4 style="margin: 0; font-size: 14px; font-weight: 800;">
									⚡ Campos Personalizados de <?php echo esc_html( $pt_label ); ?> (ACF / CPT)
								</h4>
								<span style="font-size: 11px; background: #eff6ff; color: #2563eb; font-weight: 700; padding: 2px 8px; border-radius: 12px;">Metadatos</span>
							</div>

							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
								<div>
									<label style="display: block; font-weight: 700; font-size: 12px; margin-bottom: 4px;">Cliente / Empresa Asociada</label>
									<input type="text" class="wpat-studio-input wpat-studio-cpt-field" data-meta-key="client_company" value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, 'client_company', true ) : '' ); ?>" placeholder="Ej: Empresa SL" />
								</div>
								<div>
									<label style="display: block; font-weight: 700; font-size: 12px; margin-bottom: 4px;">URL / Enlace del Proyecto</label>
									<input type="url" class="wpat-studio-input wpat-studio-cpt-field" data-meta-key="project_url" value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, 'project_url', true ) : '' ); ?>" placeholder="https://..." />
								</div>
							</div>
						</div>
					<?php endif; ?>

					<!-- TARJETA: GOOGLE SERP & OPTIMIZACIÓN SEO -->
					<div class="wpat-studio-card" style="padding: 24px;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
							<h4 style="margin: 0; font-size: 14px; font-weight: 800;">
								🔍 Vista Previa en Google & SEO <span style="font-size: 11px; background: #ecfdf5; color: #047857; font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-left: 6px;">Yoast / WPAT SEO</span>
							</h4>
						</div>

						<!-- Snippet Google en Vivo -->
						<div class="wpat-studio-serp-box" style="margin-bottom: 18px;">
							<span class="wpat-studio-serp-url"><?php echo esc_html( home_url( '/' . ( $post ? $post->post_name : 'tu-articulo' ) ) ); ?></span>
							<span class="wpat-studio-serp-title" id="wpat_studio_google_title_preview"><?php echo esc_html( ! empty( $seo_title ) ? $seo_title : ( $title ? $title : 'Título de la publicación' ) ); ?></span>
							<p class="wpat-studio-serp-desc" id="wpat_studio_google_desc_preview"><?php echo esc_html( ! empty( $seo_desc ) ? $seo_desc : ( $excerpt ? $excerpt : 'Descripción previa que verán los usuarios en los resultados de Google...' ) ); ?></p>
						</div>

						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
							<div>
								<label style="display: block; font-weight: 700; font-size: 12px; margin-bottom: 4px;">Palabra Clave Objetivo</label>
								<input type="text" id="wpat_studio_seo_keyword" value="<?php echo esc_attr( $seo_keyword ); ?>" class="wpat-studio-input" placeholder="Ej: diseño web valencia" />
							</div>
							<div>
								<label style="display: block; font-weight: 700; font-size: 12px; margin-bottom: 4px;">Título SEO Personalizado</label>
								<input type="text" id="wpat_studio_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" class="wpat-studio-input" placeholder="Dejar vacío para usar el título principal" />
							</div>
						</div>
						<div style="margin-top: 12px;">
							<label style="display: block; font-weight: 700; font-size: 12px; margin-bottom: 4px;">Meta Descripción para Google</label>
							<textarea id="wpat_studio_seo_desc" rows="2" class="wpat-studio-textarea" placeholder="Resumen atractivo para Google..."><?php echo esc_textarea( $seo_desc ); ?></textarea>
						</div>
					</div>

				</div>

				<!-- COLUMNA DERECHA: BARRA LATERAL DEL CLIENTE -->
				<div style="display: flex; flex-direction: column; gap: 20px;">
					
					<!-- TARJETA: IMAGEN DESTACADA -->
					<div class="wpat-studio-card" style="padding: 18px;">
						<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
							<h4 style="margin: 0; font-size: 13.5px; font-weight: 800;">🖼️ Imagen Destacada</h4>
							<button type="button" id="wpat_studio_remove_thumb_btn" class="button-link-delete" style="font-size: 11px; cursor: pointer; <?php echo empty( $thumb_url ) ? 'display:none;' : ''; ?>">Quitar</button>
						</div>

						<div class="wpat-studio-featured-img-holder">
							<div id="wpat_studio_thumb_preview" style="<?php echo empty( $thumb_url ) ? 'display:none;' : ''; ?> width:100%; height:100%;">
								<?php if ( ! empty( $thumb_url ) ) : ?>
									<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
								<?php endif; ?>
							</div>
							<div id="wpat_studio_thumb_placeholder" style="<?php echo ! empty( $thumb_url ) ? 'display:none;' : ''; ?> text-align:center; padding:15px; color:#94a3b8;">
								<div style="font-size: 26px; margin-bottom: 4px;">📷</div>
								<span style="font-size: 12px; font-weight: 600; color: #64748b;">Clic para elegir imagen</span>
							</div>
						</div>
					</div>

					<!-- TARJETA: CATEGORÍAS -->
					<?php if ( is_object_in_taxonomy( $post_type, 'category' ) ) : ?>
						<?php $all_categories = get_categories( array( 'hide_empty' => false ) ); ?>
						<div class="wpat-studio-card" style="padding: 18px;">
							<h4 style="margin: 0 0 12px 0; font-size: 13.5px; font-weight: 800;">🏷️ Categoría</h4>
							<div style="max-height: 160px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
								<?php foreach ( $all_categories as $cat ) : ?>
									<label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer;">
										<input type="checkbox" name="wpat_studio_categories[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( in_array( $cat->term_id, $post_cats, true ) ); ?> />
										<span><?php echo esc_html( $cat->name ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- TARJETA: EXTRACTO / RESUMEN CORTO -->
					<div class="wpat-studio-card" style="padding: 18px;">
						<h4 style="margin: 0 0 8px 0; font-size: 13.5px; font-weight: 800;">📝 Resumen Corto (Extracto)</h4>
						<textarea id="wpat_studio_post_excerpt" rows="3" class="wpat-studio-textarea" placeholder="Breve introducción para tarjetas del blog..."><?php echo esc_textarea( $excerpt ); ?></textarea>
					</div>

					<!-- TARJETA: ESTADO & PUBLICACIÓN -->
					<div class="wpat-studio-card" style="padding: 18px;">
						<h4 style="margin: 0 0 12px 0; font-size: 13.5px; font-weight: 800;">⚙️ Estado & Publicación</h4>
						<div style="display: flex; flex-direction: column; gap: 8px; font-size: 12px;">
							<div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
								<span style="color: #64748b;">Estado:</span>
								<strong><?php echo 'publish' === $status ? '🟢 Publicado' : '🟡 Borrador'; ?></strong>
							</div>
							<div style="display: flex; justify-content: space-between;">
								<span style="color: #64748b;">Tipo:</span>
								<strong><?php echo esc_html( $pt_label ); ?></strong>
							</div>
						</div>
					</div>

				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * =========================================================================
	 * 3. AJUSTES Y CONFIGURACIÓN AJAX
	 * =========================================================================
	 */

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

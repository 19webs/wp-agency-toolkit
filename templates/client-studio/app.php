<?php
/**
 * Plantilla Maestra SaaS: Client Studio App
 * Vista interactiva completa (Listado + Editor WYSIWYG) con datos reales de WordPress.
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

// Parámetros de la vista actual
$current_post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
$current_view      = isset( $_GET['view'] ) && in_array( $_GET['view'], array( 'list', 'editor' ), true ) ? sanitize_key( $_GET['view'] ) : 'list';
$current_post_id   = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

// Obtener datos del post si estamos editando
$editing_post = $current_post_id ? get_post( $current_post_id ) : null;
if ( $editing_post ) {
	$current_post_type = $editing_post->post_type;
}

$pt_obj   = get_post_type_object( $current_post_type );
$pt_label = $pt_obj ? $pt_obj->labels->singular_name : 'Entrada';
$pt_plural = $pt_obj ? $pt_obj->labels->name : 'Entradas';

// Lista de tipos de contenido públicos soportados
$supported_types = WPAT_Client_Studio::get_supported_post_types();

// Obtener posts para el listado
$posts_query = new WP_Query( array(
	'post_type'      => $current_post_type,
	'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
	'posts_per_page' => 50,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

$total_count     = $posts_query->found_posts;
$published_count = count( get_posts( array( 'post_type' => $current_post_type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
$drafts_count    = count( get_posts( array( 'post_type' => $current_post_type, 'post_status' => 'draft', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );

// Datos del post para el editor
$post_title   = $editing_post ? $editing_post->post_title : '';
$post_content = $editing_post ? $editing_post->post_content : '';
$post_excerpt = $editing_post ? $editing_post->post_excerpt : '';
$post_status  = $editing_post ? $editing_post->post_status : 'draft';
$post_name    = $editing_post ? $editing_post->post_name : '';
$permalink    = $editing_post ? get_permalink( $editing_post->ID ) : '';

$thumb_id  = $editing_post ? get_post_thumbnail_id( $editing_post->ID ) : 0;
$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';

// SEO Meta
$seo_title   = $editing_post ? get_post_meta( $editing_post->ID, '_wpat_seo_title', true ) : '';
if ( empty( $seo_title ) && $editing_post ) {
	$seo_title = get_post_meta( $editing_post->ID, '_yoast_wpseo_title', true );
}
$seo_desc    = $editing_post ? get_post_meta( $editing_post->ID, '_wpat_seo_desc', true ) : '';
if ( empty( $seo_desc ) && $editing_post ) {
	$seo_desc = get_post_meta( $editing_post->ID, '_yoast_wpseo_metadesc', true );
}
$seo_keyword = $editing_post ? get_post_meta( $editing_post->ID, '_wpat_seo_keyword', true ) : '';

// CPT Meta
$cpt_client  = $editing_post ? get_post_meta( $editing_post->ID, 'client_company', true ) : '';
$cpt_budget  = $editing_post ? get_post_meta( $editing_post->ID, 'project_budget', true ) : '';
$cpt_date    = $editing_post ? get_post_meta( $editing_post->ID, 'delivery_date', true ) : '';
$cpt_url     = $editing_post ? get_post_meta( $editing_post->ID, 'project_url', true ) : '';

$current_user = wp_get_current_user();
?>

<div id="wpat-studio-app" class="wpat-saas-root" data-current-post-type="<?php echo esc_attr( $current_post_type ); ?>" data-current-view="<?php echo esc_attr( $current_view ); ?>">

	<!-- ======================================================================= -->
	<!-- 1. BARRA SUPERIOR DE CONTROL SAAS                                      -->
	<!-- ======================================================================= -->
	<header class="wpat-top-header" id="top-header">
		<div class="wpat-brand-wrap">
			<div class="wpat-brand-icon">✨</div>
			<div>
				<h1 class="wpat-brand-title">
					WP Agency Toolkit <span class="wpat-badge-pill">Client Studio v2</span>
				</h1>
				<p class="wpat-brand-sub">Workspace Editorial & CPTs para Clientes y Redactores</p>
			</div>
		</div>

		<div class="wpat-header-actions">
			<!-- Selector de Vistas -->
			<div class="wpat-view-switch-box" id="view-switch-box">
				<button type="button" id="btn-view-list" class="wpat-view-tab <?php echo 'list' === $current_view ? 'active' : ''; ?>">
					📋 Listado
				</button>
				<button type="button" id="btn-view-editor" class="wpat-view-tab <?php echo 'editor' === $current_view ? 'active' : ''; ?>">
					✍️ Editor WYSIWYG
				</button>
			</div>

			<!-- Selector de Tipo de Contenido -->
			<select id="wpat-post-type-selector" class="wpat-post-type-select">
				<?php foreach ( $supported_types as $pt_slug => $pt_data ) : ?>
					<option value="<?php echo esc_attr( $pt_slug ); ?>" <?php selected( $current_post_type, $pt_slug ); ?>>
						<?php
						if ( 'post' === $pt_slug ) {
							echo '📝 Entradas del Blog';
						} elseif ( 'page' === $pt_slug ) {
							echo '📄 Páginas';
						} elseif ( 'product' === $pt_slug ) {
							echo '🛍️ Productos WooCommerce';
						} else {
							echo '🧩 ' . esc_html( $pt_data->labels->name );
						}
						?>
					</option>
				<?php endforeach; ?>
			</select>

			<!-- Botón Modo Oscuro / Claro -->
			<button type="button" id="theme-toggle-btn" class="wpat-btn-theme">
				<span id="theme-icon">🌙</span> <span id="theme-text">Modo Oscuro</span>
			</button>

			<!-- Enlace Modo Clásico -->
			<a href="<?php echo esc_url( admin_url( 'edit.php' . ( 'post' !== $current_post_type ? '?post_type=' . $current_post_type : '' ) . ( strpos( admin_url(), '?' ) ? '&' : '?' ) . 'wpat_classic=1' ) ); ?>" class="wpat-btn-classic" title="Abrir interfaz estándar de WordPress">
				⚙️ Modo Clásico
			</a>
		</div>
	</header>

	<!-- ======================================================================= -->
	<!-- 2. VISTA 1: LISTADO SAAS DE CONTENIDOS                                  -->
	<!-- ======================================================================= -->
	<div id="view-list" class="wpat-view-container <?php echo 'list' === $current_view ? '' : 'wpat-hidden'; ?>">
		
		<!-- HERO BIENVENIDA -->
		<div class="wpat-hero-card" id="list-hero-card">
			<div>
				<span class="wpat-hero-eyebrow">● Entorno Editorial del Cliente</span>
				<h2 class="wpat-hero-title" id="list-welcome-title">
					<?php
					if ( 'post' === $current_post_type ) {
						echo 'Centro de Publicaciones del Blog';
					} elseif ( 'page' === $current_post_type ) {
						echo 'Gestión de Páginas del Sitio';
					} elseif ( 'product' === $current_post_type ) {
						echo 'Catálogo de Productos de la Tienda';
					} else {
						echo 'Gestión de ' . esc_html( $pt_plural );
					}
					?>
				</h2>
				<p class="wpat-hero-sub">Gestiona y publica tus contenidos de forma visual, rápida y sin saturación técnica.</p>
			</div>
			<button type="button" id="btn-create-new-post" class="wpat-btn-primary-hero">
				<span>+</span> <span>Añadir <?php echo esc_html( $pt_label ); ?></span>
			</button>
		</div>

		<!-- FILTROS Y BUSCADOR -->
		<div class="wpat-filter-card" id="list-filter-card">
			<div class="wpat-status-tabs" id="list-status-tabs">
				<button type="button" class="wpat-filter-tab active" data-status="all">Todos (<?php echo esc_html( $total_count ); ?>)</button>
				<button type="button" class="wpat-filter-tab" data-status="publish">Publicados (<?php echo esc_html( $published_count ); ?>)</button>
				<button type="button" class="wpat-filter-tab" data-status="draft">Borradores (<?php echo esc_html( $drafts_count ); ?>)</button>
			</div>
			<div class="wpat-search-wrap">
				<input type="text" id="wpat-list-search-input" placeholder="Buscar por título..." class="wpat-search-input" />
			</div>
		</div>

		<!-- LISTA DE ENTRADAS SAAS -->
		<div class="wpat-items-card" id="list-table-card">
			<?php if ( $posts_query->have_posts() ) : ?>
				<div class="wpat-items-list" id="wpat-items-list-container">
					<?php
					while ( $posts_query->have_posts() ) :
						$posts_query->the_post();
						$p_id       = get_the_ID();
						$p_title    = get_the_title();
						$p_status   = get_post_status();
						$p_date     = get_the_date( 'd/m/Y' );
						$p_thumb_id = get_post_thumbnail_id( $p_id );
						$p_thumb    = $p_thumb_id ? wp_get_attachment_image_url( $p_thumb_id, 'thumbnail' ) : '';

						// Categoría principal
						$cats = get_the_category( $p_id );
						$cat_name = ! empty( $cats ) ? $cats[0]->name : $pt_label;
						?>
						<div class="wpat-item-row" data-post-id="<?php echo esc_attr( $p_id ); ?>" data-status="<?php echo esc_attr( $p_status ); ?>" data-title="<?php echo esc_attr( strtolower( $p_title ) ); ?>">
							<div class="wpat-item-left">
								<?php if ( $p_thumb ) : ?>
									<img src="<?php echo esc_url( $p_thumb ); ?>" class="wpat-item-thumb" alt="" />
								<?php else : ?>
									<div class="wpat-item-thumb-placeholder">
										<span>📷</span>
									</div>
								<?php endif; ?>

								<div class="wpat-item-details">
									<div class="wpat-item-meta">
										<?php if ( 'publish' === $p_status ) : ?>
											<span class="wpat-pill-status wpat-pill-publish">🟢 Publicado</span>
										<?php elseif ( 'draft' === $p_status ) : ?>
											<span class="wpat-pill-status wpat-pill-draft">🟡 Borrador</span>
										<?php else : ?>
											<span class="wpat-pill-status wpat-pill-pending">⏳ <?php echo esc_html( ucfirst( $p_status ) ); ?></span>
										<?php endif; ?>

										<span class="wpat-item-cat"><?php echo esc_html( $cat_name ); ?></span>
										<span class="wpat-item-dot">•</span>
										<span class="wpat-item-date"><?php echo esc_html( $p_date ); ?></span>
									</div>
									<h3 class="wpat-item-title"><?php echo esc_html( $p_title ? $p_title : '(Sin título)' ); ?></h3>
								</div>
							</div>

							<div class="wpat-item-actions">
								<?php if ( 'publish' === $p_status ) : ?>
									<a href="<?php echo esc_url( get_permalink( $p_id ) ); ?>" target="_blank" class="wpat-btn-action wpat-btn-view" title="Ver en la web">
										👁️
									</a>
								<?php endif; ?>
								<button type="button" class="wpat-btn-action wpat-btn-edit wpat-trigger-edit-post" data-id="<?php echo esc_attr( $p_id ); ?>">
									✏️ Editar
								</button>
								<button type="button" class="wpat-btn-action wpat-btn-delete wpat-trigger-trash-post" data-id="<?php echo esc_attr( $p_id ); ?>" title="Mover a la papelera">
									🗑️
								</button>
							</div>
						</div>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<div class="wpat-empty-state">
					<div class="wpat-empty-icon">📂</div>
					<h4 class="wpat-empty-title">No hay publicaciones encontradas</h4>
					<p class="wpat-empty-desc">Comienza creando tu primera <?php echo esc_html( strtolower( $pt_label ) ); ?> con el botón superior.</p>
				</div>
			<?php endif; ?>
		</div>

	</div>

	<!-- ======================================================================= -->
	<!-- 3. VISTA 2: EDITOR WYSIWYG & ESTUDIO EDITORIAL                          -->
	<!-- ======================================================================= -->
	<div id="view-editor" class="wpat-view-container <?php echo 'editor' === $current_view ? '' : 'wpat-hidden'; ?>">
		
		<!-- INPUTS OCULTOS -->
		<input type="hidden" id="wpat_post_id" value="<?php echo esc_attr( $current_post_id ); ?>" />
		<input type="hidden" id="wpat_post_type" value="<?php echo esc_attr( $current_post_type ); ?>" />
		<input type="hidden" id="wpat_thumbnail_id" value="<?php echo esc_attr( $thumb_id ); ?>" />
		<input type="hidden" id="wpat_remove_thumbnail" value="0" />

		<!-- BARRA SUPERIOR DE ACCIONES -->
		<div class="wpat-editor-topbar" id="editor-topbar">
			<div class="wpat-editor-top-left">
				<button type="button" id="btn-back-to-list" class="wpat-btn-back">
					<span>←</span> Volver a la Lista
				</button>
				<div class="wpat-sep-v"></div>
				<span class="wpat-autosave-status" id="wpat-autosave-status">
					<span class="wpat-pulse-circle"></span> <span id="wpat-status-label"><?php echo $current_post_id ? 'Edición en curso' : 'Nuevo borrador'; ?></span>
				</span>
			</div>

			<div class="wpat-editor-top-right">
				<?php if ( $permalink ) : ?>
					<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" id="wpat-btn-preview" class="wpat-btn-outline">
						👁️ Vista Previa
					</a>
				<?php endif; ?>
				<button type="button" class="wpat-btn-secondary wpat-save-post-action" data-status="draft" id="wpat-btn-save-draft">
					💾 Guardar Borrador
				</button>
				<button type="button" class="wpat-btn-primary wpat-save-post-action" data-status="publish" id="wpat-btn-publish">
					<span class="wpat-btn-spinner"></span>
					<span class="wpat-btn-text">🚀 <?php echo ( $editing_post && 'publish' === $post_status ) ? 'Actualizar' : 'Publicar en la Web'; ?></span>
				</button>
			</div>
		</div>

		<!-- WORKSPACE 2 COLUMNAS (CONTENIDO + BARRA LATERAL) -->
		<div class="wpat-editor-workspace">
			
			<!-- COLUMNA IZQUIERDA: ÁREA WYSIWYG & CAMPOS -->
			<div class="wpat-editor-main-col">
				
				<!-- TARJETA DEL EDITOR WYSIWYG -->
				<div class="wpat-editor-card" id="editor-main-card">
					<!-- TÍTULO -->
					<div class="wpat-title-wrap">
						<label class="wpat-field-label">Título de la Publicación</label>
						<input 
							type="text" 
							id="wpat-post-title-input" 
							value="<?php echo esc_attr( $post_title ); ?>" 
							placeholder="Escribe un título atractivo..." 
							class="wpat-title-input" 
							autocomplete="off"
						/>
					</div>

					<!-- BARRA DE HERRAMIENTAS WYSIWYG -->
					<div class="wpat-wysiwyg-toolbar" id="wysiwyg-toolbar">
						<button type="button" onclick="wpatFormatDoc('formatBlock', '<h2>')" class="wpat-tool-btn" title="Título H2">H2</button>
						<button type="button" onclick="wpatFormatDoc('formatBlock', '<h3>')" class="wpat-tool-btn" title="Título H3">H3</button>
						<button type="button" onclick="wpatFormatDoc('formatBlock', '<p>')" class="wpat-tool-btn" title="Párrafo normal">Párrafo</button>
						<div class="wpat-tool-sep"></div>
						<button type="button" onclick="wpatFormatDoc('bold')" class="wpat-tool-btn font-bold" title="Negrita">B</button>
						<button type="button" onclick="wpatFormatDoc('italic')" class="wpat-tool-btn italic" title="Cursiva">I</button>
						<button type="button" onclick="wpatFormatDoc('underline')" class="wpat-tool-btn underline" title="Subrayado">U</button>
						<div class="wpat-tool-sep"></div>
						<button type="button" onclick="wpatFormatDoc('justifyLeft')" class="wpat-tool-btn" title="Alinear a la izquierda">⬅️</button>
						<button type="button" onclick="wpatFormatDoc('justifyCenter')" class="wpat-tool-btn" title="Centrar">↔️</button>
						<button type="button" onclick="wpatFormatDoc('insertUnorderedList')" class="wpat-tool-btn" title="Lista con viñetas">• Lista</button>
						<button type="button" onclick="wpatFormatDoc('formatBlock', '<blockquote>')" class="wpat-tool-btn" title="Cita destacada">“ Cita</button>
						<button type="button" onclick="wpatAddLink()" class="wpat-tool-btn" title="Añadir enlace">🔗 Enlace</button>
						<div class="wpat-tool-sep"></div>
						<button type="button" onclick="wpatInsertMediaImage()" class="wpat-tool-btn wpat-tool-media" title="Insertar imagen de WordPress">
							📷 Añadir Imagen
						</button>
					</div>

					<!-- CUERPO WYSIWYG EDITABLE EN VIVO -->
					<div id="wpat-wysiwyg-editor-area" contenteditable="true" class="wpat-wysiwyg-content" placeholder="Haz clic y escribe aquí el contenido...">
						<?php echo ! empty( $post_content ) ? wp_kses_post( $post_content ) : '<p>Escribe tu contenido aquí de forma cómoda y limpia...</p>'; ?>
					</div>

					<!-- ESTADÍSTICAS EN TIEMPO REAL -->
					<div class="wpat-editor-stats">
						<div class="wpat-stats-left">
							<span>📊 <strong id="wpat-word-count">0</strong> palabras</span>
							<span>⏱️ <strong id="wpat-read-time">1 min</strong> tiempo de lectura</span>
						</div>
						<span class="wpat-pill-readability">
							🟢 Legibilidad: Fácil de leer
						</span>
					</div>
				</div>

				<!-- TARJETA: CAMPOS PERSONALIZADOS CPT (SI ES CPT) -->
				<div id="cpt-custom-fields-card" class="wpat-editor-card <?php echo in_array( $current_post_type, array( 'post', 'page' ), true ) ? 'wpat-hidden' : ''; ?>">
					<div class="wpat-card-header">
						<h4 class="wpat-card-header-title">
							⚡ Campos Personalizados de <?php echo esc_html( $pt_label ); ?> (ACF / CPT)
						</h4>
						<span class="wpat-pill-cpt">Metadatos Dinámicos</span>
					</div>

					<div class="wpat-cpt-grid">
						<div class="wpat-form-group">
							<label class="wpat-field-label">Nombre del Cliente / Empresa</label>
							<input type="text" id="wpat-cpt-client" value="<?php echo esc_attr( $cpt_client ); ?>" placeholder="Ej: Innovaciones Digitales S.L." class="wpat-input-field" />
						</div>
						<div class="wpat-form-group">
							<label class="wpat-field-label">Presupuesto / Importe (€)</label>
							<input type="text" id="wpat-cpt-budget" value="<?php echo esc_attr( $cpt_budget ); ?>" placeholder="Ej: 12.500 €" class="wpat-input-field" />
						</div>
						<div class="wpat-form-group">
							<label class="wpat-field-label">Fecha de Entrega</label>
							<input type="date" id="wpat-cpt-date" value="<?php echo esc_attr( $cpt_date ); ?>" class="wpat-input-field" />
						</div>
						<div class="wpat-form-group">
							<label class="wpat-field-label">URL del Proyecto en Vivo</label>
							<input type="url" id="wpat-cpt-url" value="<?php echo esc_attr( $cpt_url ); ?>" placeholder="https://ejemplo.com" class="wpat-input-field" />
						</div>
					</div>
				</div>

				<!-- TARJETA: OPTIMIZACIÓN SEO & GOOGLE PREVIEW -->
				<div class="wpat-editor-card" id="seo-preview-card">
					<div class="wpat-card-header">
						<h4 class="wpat-card-header-title">
							🔍 Optimización SEO & Google Preview <span class="wpat-pill-seo">Yoast / WPAT SEO</span>
						</h4>
						<span class="wpat-seo-score" id="wpat-seo-score-badge">Puntuación: 95/100 🟢</span>
					</div>

					<!-- Google Snippet Simulator -->
					<div class="wpat-google-serp-box">
						<span class="wpat-serp-url" id="wpat-serp-url-preview"><?php echo esc_html( home_url( '/' . ( $post_name ? $post_name : 'tu-articulo' ) ) ); ?></span>
						<a href="#" class="wpat-serp-title" id="wpat-google-title-preview"><?php echo esc_html( ! empty( $seo_title ) ? $seo_title : ( $post_title ? $post_title : 'Título de la publicación' ) ); ?></a>
						<p class="wpat-serp-desc" id="wpat-google-desc-preview"><?php echo esc_html( ! empty( $seo_desc ) ? $seo_desc : ( $post_excerpt ? $post_excerpt : 'Descripción previa que verán los usuarios en los resultados de Google...' ) ); ?></p>
					</div>

					<div class="wpat-seo-grid">
						<div class="wpat-form-group">
							<label class="wpat-field-label">Palabra Clave Objetivo</label>
							<input type="text" id="wpat-seo-keyword" value="<?php echo esc_attr( $seo_keyword ); ?>" placeholder="Ej: diseño web valencia" class="wpat-input-field" />
						</div>
						<div class="wpat-form-group">
							<label class="wpat-field-label">Título SEO Personalizado</label>
							<input type="text" id="wpat-seo-title" value="<?php echo esc_attr( $seo_title ); ?>" placeholder="Dejar en blanco para usar el título principal" class="wpat-input-field" />
						</div>
					</div>
				</div>

			</div>

			<!-- COLUMNA DERECHA: BARRA LATERAL DEL CLIENTE -->
			<div class="wpat-editor-sidebar-col">
				
				<!-- TARJETA: IMAGEN DESTACADA -->
				<div class="wpat-editor-card" id="sidebar-img-card">
					<div class="wpat-card-header">
						<h4 class="wpat-card-header-title">🖼️ Imagen Destacada</h4>
						<button type="button" class="wpat-btn-link-action" id="wpat-change-image-btn">Cambiar</button>
					</div>
					<div class="wpat-dropzone-holder <?php echo $thumb_url ? 'has-img' : ''; ?>" id="wpat-dropzone-holder">
						<?php if ( $thumb_url ) : ?>
							<img src="<?php echo esc_url( $thumb_url ); ?>" id="wpat-preview-img-tag" alt="" class="wpat-dropzone-img" />
						<?php else : ?>
							<div class="wpat-dropzone-empty" id="wpat-dropzone-empty-wrap">
								<span class="wpat-dropzone-icon">📷</span>
								<span class="wpat-dropzone-text">Clic para seleccionar de Medios</span>
							</div>
							<img src="" id="wpat-preview-img-tag" alt="" class="wpat-dropzone-img wpat-hidden" />
						<?php endif; ?>
						<div class="wpat-dropzone-overlay">
							<span>🔄 Clic para cambiar imagen</span>
						</div>
					</div>
					<?php if ( $thumb_url ) : ?>
						<button type="button" id="wpat-remove-image-btn" class="wpat-btn-remove-thumb">✕ Eliminar imagen destacada</button>
					<?php else : ?>
						<button type="button" id="wpat-remove-image-btn" class="wpat-btn-remove-thumb wpat-hidden">✕ Eliminar imagen destacada</button>
					<?php endif; ?>
				</div>

				<!-- TARJETA: CATEGORÍAS & TAXONOMÍAS -->
				<?php
				$taxonomies = get_object_taxonomies( $current_post_type, 'objects' );
				if ( ! empty( $taxonomies ) ) :
					foreach ( $taxonomies as $tax_name => $tax_obj ) :
						if ( ! $tax_obj->show_ui || in_array( $tax_name, array( 'post_format' ), true ) ) {
							continue;
						}
						$terms = get_terms( array( 'taxonomy' => $tax_name, 'hide_empty' => false ) );
						$selected_terms = $editing_post ? wp_get_object_terms( $editing_post->ID, $tax_name, array( 'fields' => 'ids' ) ) : array();
						?>
						<div class="wpat-editor-card" id="sidebar-cat-card">
							<h4 class="wpat-card-header-title">🏷️ <?php echo esc_html( $tax_obj->labels->name ); ?></h4>
							<div class="wpat-tax-checklist">
								<?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
									<?php foreach ( $terms as $term ) : ?>
										<label class="wpat-tax-item">
											<input 
												type="checkbox" 
												class="wpat-tax-checkbox" 
												data-taxonomy="<?php echo esc_attr( $tax_name ); ?>" 
												value="<?php echo esc_attr( $term->term_id ); ?>" 
												<?php checked( in_array( $term->term_id, $selected_terms, true ) ); ?> 
											/>
											<span class="wpat-tax-name"><?php echo esc_html( $term->name ); ?></span>
										</label>
									<?php endforeach; ?>
								<?php else : ?>
									<p class="wpat-tax-empty">Sin categorías creadas aún.</p>
								<?php endif; ?>
							</div>
						</div>
						<?php
					endforeach;
				endif;
				?>

				<!-- TARJETA: EXTRACTO / RESUMEN -->
				<div class="wpat-editor-card" id="sidebar-excerpt-card">
					<h4 class="wpat-card-header-title">📝 Resumen Corto (Extracto)</h4>
					<textarea 
						id="wpat-post-excerpt-input" 
						rows="3" 
						placeholder="Breve resumen que se mostrará en los listados del blog..." 
						class="wpat-textarea-field"
					><?php echo esc_textarea( $post_excerpt ); ?></textarea>
				</div>

				<!-- TARJETA: ESTADO & AUTOR -->
				<div class="wpat-editor-card" id="sidebar-publish-card">
					<h4 class="wpat-card-header-title">⚙️ Estado & Autor</h4>
					<div class="wpat-meta-details-list">
						<div class="wpat-meta-item">
							<span class="wpat-meta-label">Estado:</span>
							<select id="wpat-status-select" class="wpat-status-dropdown">
								<option value="draft" <?php selected( $post_status, 'draft' ); ?>>📝 Borrador</option>
								<option value="publish" <?php selected( $post_status, 'publish' ); ?>>🚀 Publicado</option>
								<option value="pending" <?php selected( $post_status, 'pending' ); ?>>⏳ Pendiente</option>
								<option value="private" <?php selected( $post_status, 'private' ); ?>>🔒 Privado</option>
							</select>
						</div>
						<div class="wpat-meta-item">
							<span class="wpat-meta-label">Fecha:</span>
							<span class="wpat-meta-val"><?php echo ( $editing_post && 'publish' === $post_status ) ? esc_html( get_the_date( 'd/m/Y H:i', $editing_post ) ) : 'Inmediata'; ?></span>
						</div>
						<div class="wpat-meta-item">
							<span class="wpat-meta-label">Autor:</span>
							<span class="wpat-meta-val"><?php echo esc_html( $current_user->display_name ); ?></span>
						</div>
					</div>

					<?php if ( $current_post_id ) : ?>
						<div class="wpat-danger-zone">
							<button type="button" class="wpat-btn-trash-current wpat-trigger-trash-post" data-id="<?php echo esc_attr( $current_post_id ); ?>">
								🗑️ Mover a la papelera
							</button>
						</div>
					<?php endif; ?>
				</div>

			</div>

		</div>

	</div>

</div>

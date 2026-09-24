<?php
/**
 * Plantilla SaaS: Client Studio - Editor Principal
 *
 * @package WP_Agency_Toolkit
 * @var array $args Datos pasados por WPAT_Client_Studio::render_studio_editor_override
 */

defined( 'ABSPATH' ) || exit;

$post         = $args['post'];
$post_id      = $args['post_id'];
$post_type    = $args['post_type'];
$pt_label     = $args['pt_label'];
$title        = $args['title'];
$content      = $args['content'];
$excerpt      = $args['excerpt'];
$status       = $args['status'];
$thumb_id     = $args['thumb_id'];
$thumb_url    = $args['thumb_url'];
$permalink    = $args['permalink'];
$post_name    = $args['post_name'];
$back_url     = $args['back_url'];
$seo_title    = $args['seo_title'];
$seo_desc     = $args['seo_desc'];
$seo_keyword  = $args['seo_keyword'];
?>

<div class="wpat-studio-app" id="wpat-studio-app">

	<!-- INPUTS OCULTOS DE ESTADO -->
	<input type="hidden" id="wpat_studio_post_id" value="<?php echo esc_attr( $post_id ); ?>" />
	<input type="hidden" id="wpat_studio_post_type" value="<?php echo esc_attr( $post_type ); ?>" />
	<input type="hidden" id="wpat_studio_thumbnail_id" value="<?php echo esc_attr( $thumb_id ); ?>" />
	<input type="hidden" id="wpat_studio_remove_thumb_flag" value="0" />

	<!-- =======================================================================
	     BARRA DE HERRAMIENTAS SUPERIOR (NAVBAR FLOTANTE SAAS)
	     ======================================================================= -->
	<header class="wpat-studio-navbar">
		<div class="wpat-studio-nav-left">
			<a href="<?php echo esc_url( $back_url ); ?>" class="wpat-studio-btn wpat-studio-btn-ghost wpat-studio-back-link" title="Volver al listado">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M19 12H5M12 19l-7-7 7-7"/>
				</svg>
				<span>Volver a <?php echo esc_html( $pt_label ); ?>s</span>
			</a>

			<div class="wpat-studio-nav-divider"></div>

			<div class="wpat-studio-breadcrumbs">
				<span class="wpat-studio-crumb-type"><?php echo esc_html( $pt_label ); ?></span>
				<span class="wpat-studio-crumb-sep">/</span>
				<span class="wpat-studio-crumb-title" id="wpat_studio_nav_title_preview">
					<?php echo ! empty( $title ) ? esc_html( wp_trim_words( $title, 6 ) ) : 'Nuevo borrador'; ?>
				</span>
			</div>

			<div class="wpat-studio-status-indicator" id="wpat_studio_autosave_indicator">
				<span class="wpat-studio-status-dot <?php echo 'publish' === $status ? 'published' : 'draft'; ?>"></span>
				<span class="wpat-studio-status-text" id="wpat_studio_autosave_status">
					<?php echo $post_id ? ( 'publish' === $status ? 'Publicado' : 'Borrador guardado' ) : 'Sin guardar'; ?>
				</span>
			</div>
		</div>

		<div class="wpat-studio-nav-right">
			<!-- Botón Modo Oscuro / Claro -->
			<button type="button" class="wpat-studio-btn-icon" id="wpat_studio_theme_toggle" title="Cambiar Tema Claro / Oscuro">
				<svg class="wpat-icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
				</svg>
				<svg class="wpat-icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
				</svg>
			</button>

			<!-- Enlace de Vista Previa -->
			<?php if ( $permalink ) : ?>
				<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" id="wpat_studio_preview_link" class="wpat-studio-btn wpat-studio-btn-outline" title="Ver en una pestaña nueva">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
					</svg>
					<span>Vista Previa</span>
				</a>
			<?php endif; ?>

			<!-- Guardar Borrador -->
			<button type="button" class="wpat-studio-btn wpat-studio-btn-secondary wpat-studio-save-action" data-status="draft" id="wpat_studio_btn_draft">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
				</svg>
				<span>Guardar Borrador</span>
			</button>

			<!-- Publicar / Actualizar -->
			<button type="button" class="wpat-studio-btn wpat-studio-btn-primary wpat-studio-save-action" data-status="publish" id="wpat_studio_btn_publish">
				<svg class="wpat-btn-spinner" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/>
				</svg>
				<svg class="wpat-btn-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<polyline points="20 6 9 17 4 12"/>
				</svg>
				<span class="wpat-btn-label"><?php echo ( 'publish' === $status ) ? 'Actualizar' : 'Publicar Ahora'; ?></span>
			</button>
		</div>
	</header>

	<!-- =======================================================================
	     CONTENEDOR PRINCIPAL (2 COLUMNAS: EDITOR + SIDEBAR)
	     ======================================================================= -->
	<main class="wpat-studio-layout">

		<!-- COLUMNA PRINCIPAL (CANVAS DE CONTENIDO) -->
		<div class="wpat-studio-canvas">

			<!-- TARJETA DEL EDITOR -->
			<div class="wpat-studio-card wpat-studio-main-card">
				
				<!-- CAMPO DE TÍTULO PRINCIPAL -->
				<div class="wpat-studio-title-box">
					<input 
						type="text" 
						id="wpat_studio_post_title" 
						name="post_title"
						value="<?php echo esc_attr( $title ); ?>" 
						placeholder="Escribe el título de tu <?php echo esc_attr( strtolower( $pt_label ) ); ?>..." 
						class="wpat-studio-title-input" 
						autocomplete="off" 
						spellcheck="true"
					/>
				</div>

				<!-- SLUG / ENLACE PERMANENTE MODERNO -->
				<?php if ( $post_id ) : ?>
					<div class="wpat-studio-permalink-bar">
						<span class="wpat-studio-permalink-label">Enlace:</span>
						<span class="wpat-studio-permalink-url">
							<?php echo esc_html( home_url( '/' ) ); ?><span class="wpat-studio-slug-text"><?php echo esc_html( $post_name ? $post_name : sanitize_title( $title ) ); ?></span>
						</span>
						<button type="button" class="wpat-studio-btn-copy-slug" id="wpat_copy_permalink_btn" data-url="<?php echo esc_url( $permalink ); ?>" title="Copiar enlace">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
							</svg>
							<span>Copiar</span>
						</button>
					</div>
				<?php endif; ?>

				<!-- CONTENEDOR WYSIWYG ELEGANTE -->
				<div class="wpat-studio-editor-container">
					<?php
					wp_editor(
						$content,
						'wpat_studio_content_editor',
						array(
							'textarea_rows' => 18,
							'media_buttons' => true,
							'teeny'         => false,
							'tinymce'       => array(
								'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,fullscreen',
								'toolbar2' => '',
							),
							'quicktags'     => true,
						)
					);
					?>
				</div>

				<!-- MÉTRICAS EN VIVO & TIEMPO DE LECTURA -->
				<div class="wpat-studio-metrics-footer">
					<div class="wpat-studio-metrics-group">
						<div class="wpat-studio-metric-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
							</svg>
							<span>Palabras: <strong id="wpat_studio_word_count">0</strong></span>
						</div>
						<div class="wpat-studio-metric-item">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
							</svg>
							<span>Tiempo de lectura: <strong id="wpat_studio_read_time">1 min</strong></span>
						</div>
					</div>

					<div class="wpat-studio-format-pill">
						<span class="wpat-studio-pill-tag">✨ SaaS Studio Mode</span>
					</div>
				</div>
			</div>

			<!-- COMPONENTE SEO & GOOGLE PREVIEW -->
			<?php
			WPAT_Client_Studio::render_template(
				'seo-card',
				array(
					'post'        => $post,
					'post_name'   => $post_name,
					'title'       => $title,
					'excerpt'     => $excerpt,
					'seo_title'   => $seo_title,
					'seo_desc'    => $seo_desc,
					'seo_keyword' => $seo_keyword,
				)
			);
			?>

			<!-- COMPONENTE CAMPOS PERSONALIZADOS CPT / ACF -->
			<?php
			WPAT_Client_Studio::render_template(
				'cpt-fields',
				array(
					'post_id'   => $post_id,
					'post_type' => $post_type,
					'pt_label'  => $pt_label,
				)
			);
			?>

		</div>

		<!-- COLUMNA LATERAL (INSPECTOR DE PUBLICACIÓN & METADATOS) -->
		<aside class="wpat-studio-sidebar">
			<?php
			WPAT_Client_Studio::render_template(
				'editor-sidebar',
				array(
					'post'      => $post,
					'post_id'   => $post_id,
					'post_type' => $post_type,
					'status'    => $status,
					'thumb_id'  => $thumb_id,
					'thumb_url' => $thumb_url,
					'excerpt'   => $excerpt,
				)
			);
			?>
		</aside>

	</main>

</div>

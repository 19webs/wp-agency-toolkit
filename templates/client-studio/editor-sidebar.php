<?php
/**
 * Plantilla SaaS: Client Studio - Barra Lateral (Inspector)
 *
 * @package WP_Agency_Toolkit
 * @var array $args Datos del post y estado
 */

defined( 'ABSPATH' ) || exit;

$post      = $args['post'];
$post_id   = $args['post_id'];
$post_type = $args['post_type'];
$status    = $args['status'];
$thumb_id  = $args['thumb_id'];
$thumb_url = $args['thumb_url'];
$excerpt   = $args['excerpt'];

$pt_obj    = get_post_type_object( $post_type );
$is_page   = ( 'page' === $post_type );
?>

<div class="wpat-studio-sidebar-stack">

	<!-- TARJETA 1: ESTADO Y PUBLICACIÓN -->
	<div class="wpat-studio-card wpat-sidebar-card">
		<div class="wpat-sidebar-card-header">
			<div class="wpat-sidebar-card-title">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
				</svg>
				<span>Publicación & Estado</span>
			</div>
		</div>

		<div class="wpat-sidebar-card-body">
			<!-- Selector de Estado -->
			<div class="wpat-form-group">
				<label class="wpat-form-label" for="wpat_studio_status_select">Estado</label>
				<select id="wpat_studio_status_select" class="wpat-studio-select">
					<option value="draft" <?php selected( $status, 'draft' ); ?>>📝 Borrador</option>
					<option value="publish" <?php selected( $status, 'publish' ); ?>>🚀 Publicado</option>
					<option value="pending" <?php selected( $status, 'pending' ); ?>>⏳ Pendiente de revisión</option>
					<option value="private" <?php selected( $status, 'private' ); ?>>🔒 Privado</option>
				</select>
			</div>

			<!-- Fecha de Publicación -->
			<div class="wpat-form-group">
				<label class="wpat-form-label">Fecha</label>
				<div class="wpat-form-static-val">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
					</svg>
					<span><?php echo ( $post && 'publish' === $status ) ? esc_html( get_the_date( 'd/m/Y H:i', $post ) ) : 'Inmediatamente'; ?></span>
				</div>
			</div>

			<!-- Autor -->
			<div class="wpat-form-group">
				<label class="wpat-form-label">Autor</label>
				<div class="wpat-form-static-val">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
					</svg>
					<span><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
				</div>
			</div>

			<?php if ( $post_id ) : ?>
				<div class="wpat-sidebar-danger-zone">
					<button type="button" class="wpat-studio-btn-danger-link" id="wpat_studio_trash_btn">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
						</svg>
						<span>Mover a la papelera</span>
					</button>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- TARJETA 2: IMAGEN DESTACADA SAAS -->
	<div class="wpat-studio-card wpat-sidebar-card">
		<div class="wpat-sidebar-card-header">
			<div class="wpat-sidebar-card-title">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
				</svg>
				<span>Imagen Destacada</span>
			</div>
		</div>

		<div class="wpat-sidebar-card-body">
			<div class="wpat-studio-dropzone <?php echo $thumb_url ? 'has-image' : ''; ?>" id="wpat_studio_image_dropzone">
				
				<!-- Vista previa cuando hay imagen -->
				<div class="wpat-dropzone-preview" id="wpat_studio_thumb_preview_wrap" style="<?php echo $thumb_url ? 'display: block;' : 'display: none;'; ?>">
					<img src="<?php echo esc_url( $thumb_url ); ?>" id="wpat_studio_thumb_img" alt="Imagen Destacada" />
					<div class="wpat-dropzone-overlay">
						<button type="button" class="wpat-studio-btn wpat-studio-btn-sm wpat-studio-btn-outline" id="wpat_studio_change_thumb_btn">Cambiar</button>
						<button type="button" class="wpat-studio-btn wpat-studio-btn-sm wpat-studio-btn-danger" id="wpat_studio_remove_thumb_btn">Eliminar</button>
					</div>
				</div>

				<!-- Placeholder cuando NO hay imagen -->
				<div class="wpat-dropzone-empty" id="wpat_studio_thumb_placeholder" style="<?php echo $thumb_url ? 'display: none;' : 'display: flex;'; ?>">
					<div class="wpat-dropzone-icon">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
							<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
						</svg>
					</div>
					<span class="wpat-dropzone-primary-text">Seleccionar o arrastrar imagen</span>
					<span class="wpat-dropzone-sub-text">PNG, JPG, WEBP hasta 10MB</span>
				</div>

			</div>
		</div>
	</div>

	<!-- TARJETA 3: CATEGORÍAS & TAXONOMÍAS (Si aplica) -->
	<?php
	$taxonomies = get_object_taxonomies( $post_type, 'objects' );
	if ( ! empty( $taxonomies ) ) :
		foreach ( $taxonomies as $tax_name => $tax_obj ) :
			if ( ! $tax_obj->show_ui || in_array( $tax_name, array( 'post_format' ), true ) ) {
				continue;
			}
			$terms = get_terms( array( 'taxonomy' => $tax_name, 'hide_empty' => false ) );
			$selected_terms = $post_id ? wp_get_object_terms( $post_id, $tax_name, array( 'fields' => 'ids' ) ) : array();
			?>
			<div class="wpat-studio-card wpat-sidebar-card">
				<div class="wpat-sidebar-card-header">
					<div class="wpat-sidebar-card-title">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
						</svg>
						<span><?php echo esc_html( $tax_obj->labels->name ); ?></span>
					</div>
				</div>

				<div class="wpat-sidebar-card-body">
					<?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
						<div class="wpat-taxonomy-checklist">
							<?php foreach ( $terms as $term ) : ?>
								<label class="wpat-checkbox-label">
									<input 
										type="checkbox" 
										class="wpat-studio-tax-check" 
										data-taxonomy="<?php echo esc_attr( $tax_name ); ?>" 
										value="<?php echo esc_attr( $term->term_id ); ?>" 
										<?php checked( in_array( $term->term_id, $selected_terms, true ) ); ?>
									/>
									<span class="wpat-checkbox-custom"></span>
									<span class="wpat-checkbox-text"><?php echo esc_html( $term->name ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="wpat-muted-tip">No hay <?php echo esc_html( strtolower( $tax_obj->labels->name ) ); ?> creadas todavía.</p>
					<?php endif; ?>
				</div>
			</div>
			<?php
		endforeach;
	endif;
	?>

	<!-- TARJETA 4: EXTRACTO / RESUMEN CORTO -->
	<div class="wpat-studio-card wpat-sidebar-card">
		<div class="wpat-sidebar-card-header">
			<div class="wpat-sidebar-card-title">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/>
				</svg>
				<span>Resumen Corto (Extracto)</span>
			</div>
		</div>

		<div class="wpat-sidebar-card-body">
			<textarea 
				id="wpat_studio_post_excerpt" 
				class="wpat-studio-textarea" 
				rows="3" 
				placeholder="Breve resumen que se mostrará en los listados del blog o tarjetas..."
			><?php echo esc_textarea( $excerpt ); ?></textarea>
			<span class="wpat-form-hint">Recomendado entre 120 y 160 caracteres.</span>
		</div>
	</div>

</div>

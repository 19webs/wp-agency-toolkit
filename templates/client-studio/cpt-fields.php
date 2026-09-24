<?php
/**
 * Plantilla SaaS: Client Studio - Campos Personalizados CPT / ACF & Plugins
 *
 * @package WP_Agency_Toolkit
 * @var array $args Datos del post y tipo de contenido
 */

defined( 'ABSPATH' ) || exit;

$post_id   = $args['post_id'];
$post_type = $args['post_type'];
$pt_label  = $args['pt_label'];
?>

<?php if ( 'post' !== $post_type && 'page' !== $post_type ) : ?>
	<div class="wpat-studio-card wpat-cpt-card">
		<div class="wpat-card-header">
			<div class="wpat-card-header-left">
				<div class="wpat-card-title">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
					</svg>
					<span>Campos Personalizados de <?php echo esc_html( $pt_label ); ?></span>
				</div>
				<span class="wpat-pill-badge wpat-pill-blue">Metadatos CPT</span>
			</div>
		</div>

		<div class="wpat-card-body">
			<div class="wpat-cpt-fields-grid">
				<div class="wpat-form-group">
					<label class="wpat-form-label">Cliente / Empresa Asociada</label>
					<input 
						type="text" 
						class="wpat-studio-input wpat-studio-cpt-field" 
						data-meta-key="client_company" 
						value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, 'client_company', true ) : '' ); ?>" 
						placeholder="Ej: Innova Tech SL"
					/>
				</div>
				<div class="wpat-form-group">
					<label class="wpat-form-label">Enlace / URL del Proyecto</label>
					<input 
						type="url" 
						class="wpat-studio-input wpat-studio-cpt-field" 
						data-meta-key="project_url" 
						value="<?php echo esc_attr( $post_id ? get_post_meta( $post_id, 'project_url', true ) : '' ); ?>" 
						placeholder="https://..."
					/>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<!-- ACORDEÓN PARA CAMPOS AVANZADOS Y PLUGINS EXTERNOS (ACF, JETENGINE, ETC.) -->
<div class="wpat-studio-card wpat-advanced-plugins-card">
	<details class="wpat-accordion-details">
		<summary class="wpat-accordion-summary">
			<div class="wpat-summary-left">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/>
				</svg>
				<span>Campos Avanzados & Plugins Externos (ACF, Meta Boxes)</span>
			</div>
			<span class="wpat-accordion-toggle-icon">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<polyline points="6 9 12 15 18 9"/>
				</svg>
			</span>
		</summary>
		<div class="wpat-accordion-content" id="wpat_external_metaboxes_container">
			<p class="wpat-muted-tip" style="margin-bottom: 12px;">
				Los metaboxes de terceros se integran automáticamente. Si prefieres editar en la vista clásica sin restricciones de plantilla, puedes alternar abajo:
			</p>
			<a href="<?php echo esc_url( add_query_arg( 'wpat_classic', '1' ) ); ?>" class="wpat-studio-btn wpat-studio-btn-outline wpat-studio-btn-sm">
				Cambiar a Vista Clásica de WordPress
			</a>
		</div>
	</details>
</div>

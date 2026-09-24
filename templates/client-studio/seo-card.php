<?php
/**
 * Plantilla SaaS: Client Studio - Vista Previa Google & Tarjeta SEO
 *
 * @package WP_Agency_Toolkit
 * @var array $args Metadatos SEO y del post
 */

defined( 'ABSPATH' ) || exit;

$post        = $args['post'];
$post_name   = $args['post_name'];
$title       = $args['title'];
$excerpt     = $args['excerpt'];
$seo_title   = $args['seo_title'];
$seo_desc    = $args['seo_desc'];
$seo_keyword = $args['seo_keyword'];

$display_title = ! empty( $seo_title ) ? $seo_title : ( ! empty( $title ) ? $title : 'Título de la publicación' );
$display_desc  = ! empty( $seo_desc ) ? $seo_desc : ( ! empty( $excerpt ) ? $excerpt : 'Escribe una descripción atractiva para que los usuarios hagan clic en Google...' );
$display_slug  = ! empty( $post_name ) ? $post_name : ( ! empty( $title ) ? sanitize_title( $title ) : 'ejemplo-de-enlace' );
?>

<div class="wpat-studio-card wpat-seo-card">
	<div class="wpat-card-header">
		<div class="wpat-card-header-left">
			<div class="wpat-card-title">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
				</svg>
				<span>Vista Previa en Google & Optimización SEO</span>
			</div>
			<span class="wpat-pill-badge wpat-pill-emerald">Compatible Yoast / RankMath / WPAT</span>
		</div>
	</div>

	<div class="wpat-card-body">
		
		<!-- SIMULADOR GOOGLE SERP PIXEL-PERFECT -->
		<div class="wpat-google-serp-box">
			<div class="wpat-google-serp-header">
				<div class="wpat-google-favicon">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
					</svg>
				</div>
				<div class="wpat-google-url-wrap">
					<span class="wpat-google-site-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
					<span class="wpat-google-url"><?php echo esc_html( home_url( '/' . $display_slug ) ); ?></span>
				</div>
			</div>
			
			<div class="wpat-google-serp-title" id="wpat_studio_google_title_preview">
				<?php echo esc_html( $display_title ); ?>
			</div>
			
			<div class="wpat-google-serp-desc" id="wpat_studio_google_desc_preview">
				<?php echo esc_html( $display_desc ); ?>
			</div>
		</div>

		<!-- CONTROLES DE EDICIÓN SEO -->
		<div class="wpat-seo-controls-grid">
			
			<!-- Palabra Clave Objetivo -->
			<div class="wpat-form-group wpat-fullwidth">
				<label class="wpat-form-label" for="wpat_studio_seo_keyword">
					Palabra Clave Objetivo
				</label>
				<input 
					type="text" 
					id="wpat_studio_seo_keyword" 
					class="wpat-studio-input" 
					value="<?php echo esc_attr( $seo_keyword ); ?>" 
					placeholder="Ej: diseño web valencia, zapatillas deportivas..."
				/>
			</div>

			<!-- Título SEO Personalizado -->
			<div class="wpat-form-group">
				<div class="wpat-label-with-counter">
					<label class="wpat-form-label" for="wpat_studio_seo_title">Título SEO en Google</label>
					<span class="wpat-char-counter" id="wpat_seo_title_count">0 / 60</span>
				</div>
				<input 
					type="text" 
					id="wpat_studio_seo_title" 
					class="wpat-studio-input" 
					value="<?php echo esc_attr( $seo_title ); ?>" 
					placeholder="Dejar en blanco para usar el título general"
					maxlength="70"
				/>
				<div class="wpat-char-progress-bar"><div class="wpat-char-progress-fill" id="wpat_seo_title_bar"></div></div>
			</div>

			<!-- Meta Descripción SEO -->
			<div class="wpat-form-group">
				<div class="wpat-label-with-counter">
					<label class="wpat-form-label" for="wpat_studio_seo_desc">Meta Descripción</label>
					<span class="wpat-char-counter" id="wpat_seo_desc_count">0 / 160</span>
				</div>
				<textarea 
					id="wpat_studio_seo_desc" 
					class="wpat-studio-textarea" 
					rows="3" 
					placeholder="Texto persuasivo para captar clics en Google..."
					maxlength="175"
				><?php echo esc_textarea( $seo_desc ); ?></textarea>
				<div class="wpat-char-progress-bar"><div class="wpat-char-progress-fill" id="wpat_seo_desc_bar"></div></div>
			</div>

		</div>

	</div>
</div>

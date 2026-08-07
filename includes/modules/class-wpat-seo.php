<?php
/**
 * Módulo: Optimización SEO Ultra-Ligero - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_SEO {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_SEO
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_SEO
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
		// Acciones en la administración (Editor de entradas/páginas)
		add_action( 'add_meta_boxes', array( $this, 'add_seo_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_seo_meta_box_data' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'admin_init', array( $this, 'register_seo_list_columns' ) );

		// Acciones en el frontend (Inyección en cabecera)
		add_filter( 'pre_get_document_title', array( $this, 'filter_frontend_title' ), 999 );
		add_action( 'wp_head', array( $this, 'inject_seo_meta_tags' ), 5 );

		// Sitemap XML integrado
		add_action( 'init', array( $this, 'intercept_sitemap_request' ) );
	}

	/**
	 * Carga assets específicos para el editor de WordPress.
	 */
	public function enqueue_editor_assets( $hook ) {
		global $pagenow;
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wpat-admin-css', WPAT_URL . 'assets/css/wpat-admin.css', array(), WPAT_VERSION );
			wp_enqueue_script( 'wpat-admin-js', WPAT_URL . 'assets/js/wpat-admin.js', array( 'jquery' ), WPAT_VERSION, true );
		}
	}

	/**
	 * Añade la caja Meta Box en todos los Post Types públicos.
	 */
	public function add_seo_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'wpat_seo_metabox',
				'Optimización SEO - WP Agency Toolkit',
				array( $this, 'render_seo_metabox' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Renderiza el HTML del metabox SEO con vista previa responsive.
	 *
	 * @param WP_Post $post
	 */
	public function render_seo_metabox( $post ) {
		// Crear campo nonce de seguridad
		wp_nonce_field( 'wpat_seo_metabox_save_action', 'wpat_seo_nonce' );

		// Obtener valores guardados
		$keyword             = get_post_meta( $post->ID, '_wpat_seo_keyword', true );
		$title               = get_post_meta( $post->ID, '_wpat_seo_title', true );
		$desc                = get_post_meta( $post->ID, '_wpat_seo_desc', true );
		$noindex             = get_post_meta( $post->ID, '_wpat_seo_noindex', true );
		$og_img              = get_post_meta( $post->ID, '_wpat_seo_og_image', true );
		$schema_page_type    = get_post_meta( $post->ID, '_wpat_seo_schema_page_type', true );
		$schema_article_type = get_post_meta( $post->ID, '_wpat_seo_schema_article_type', true );
		
		// Nuevos Campos Avanzados
		$canonical           = get_post_meta( $post->ID, '_wpat_seo_canonical', true );
		$og_title            = get_post_meta( $post->ID, '_wpat_seo_og_title', true );
		$og_desc             = get_post_meta( $post->ID, '_wpat_seo_og_desc', true );
		$cornerstone         = get_post_meta( $post->ID, '_wpat_seo_cornerstone', true );

		// Valores por defecto
		if ( empty( $schema_page_type ) ) {
			$schema_page_type = 'WebPage';
		}
		if ( empty( $schema_article_type ) ) {
			$schema_article_type = 'Article';
		}

		// Fallbacks de previsualización
		$site_name   = get_bloginfo( 'name' );
		$preview_url = get_permalink( $post->ID );
		$display_url = str_replace( array( 'http://', 'https://' ), '', $preview_url );
		
		$default_title = get_the_title( $post->ID );
		if ( empty( $default_title ) ) {
			$default_title = 'Título de la entrada';
		}
		$default_title_formatted = $default_title . ' - ' . $site_name;

		$default_desc = 'Por favor, escribe una meta descripción atractiva para que este contenido capte visitas en los resultados de búsqueda de Google.';
		?>
		<div class="wpat-seo-metabox-container" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; color: #1e293b; margin: -6px;">
			
			<!-- Pestañas Superiores Estilo Yoast -->
			<div class="wpat-seo-tabs-header" style="display:flex; border-bottom:1px solid #cbd5e1; background:#f8fafc; margin:-6px -6px 15px -6px; padding:10px 12px 0 12px; gap:6px;">
				<button type="button" class="wpat-seo-nav-tab active" data-wpat-tab="wpat-seo-tab-seo" style="background:#fff; border:1px solid #cbd5e1; border-bottom:none; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 16px; font-weight:600; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:6px; color:#1e293b; position:relative; top:1px; transition:all 0.15s ease;">
					<span class="wpat-seo-bullet" id="wpat_seo_bullet_seo" style="width:10px; height:10px; border-radius:50%; background:#94a3b8; display:inline-block;"></span>
					SEO
				</button>
				<button type="button" class="wpat-seo-nav-tab" data-wpat-tab="wpat-seo-tab-readability" style="background:none; border:1px solid transparent; border-bottom:none; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 16px; font-weight:600; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:6px; color:#64748b; position:relative; top:1px; transition:all 0.15s ease;">
					<span class="wpat-seo-bullet" id="wpat_seo_bullet_readability" style="width:10px; height:10px; border-radius:50%; background:#94a3b8; display:inline-block;"></span>
					Legibilidad
				</button>
				<button type="button" class="wpat-seo-nav-tab" data-wpat-tab="wpat-seo-tab-schema" style="background:none; border:1px solid transparent; border-bottom:none; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 16px; font-weight:600; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:6px; color:#64748b; position:relative; top:1px; transition:all 0.15s ease;">
					<span class="dashicons dashicons-media-text" style="font-size:15px; width:15px; height:15px; color:#64748b; margin:0;"></span>
					Schema
				</button>
				<button type="button" class="wpat-seo-nav-tab" data-wpat-tab="wpat-seo-tab-social" style="background:none; border:1px solid transparent; border-bottom:none; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 16px; font-weight:600; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:6px; color:#64748b; position:relative; top:1px; transition:all 0.15s ease;">
					<span class="dashicons dashicons-share" style="font-size:15px; width:15px; height:15px; color:#64748b; margin:0;"></span>
					Social
				</button>
			</div>

			<!-- CONTENIDO TABS -->
			<div style="padding: 20px 24px; max-width: 850px; margin: 0 auto; background: #fff;">
				
				<!-- TAB 1: SEO -->
				<div class="wpat-seo-tab-content active" id="wpat-seo-tab-seo">
					
					<!-- Frase clave objetivo y Cornerstone Switch -->
					<div style="margin-bottom:20px; display:flex; gap:20px; flex-wrap:wrap; align-items:center;">
						<div style="flex:2; min-width:250px;">
							<label for="wpat_seo_keyword_input" style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:4px;">Frase clave objetivo</label>
							<input type="text" id="wpat_seo_keyword_input" name="wpat_seo_keyword" value="<?php echo esc_attr( $keyword ); ?>" placeholder="Ej: diseño web, desarrollo wordpress" style="width:100%; height:36px; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; box-sizing:border-box;" />
							<p class="description" style="margin-top:4px; margin-bottom:0;">Puedes escribir varias frases clave separadas por comas.</p>
						</div>
						<div style="flex:1; min-width:200px; margin-top: 15px;">
							<label style="display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#475569; cursor:pointer;">
								<input type="checkbox" id="wpat_seo_cornerstone_input" name="wpat_seo_cornerstone" value="1" <?php checked( $cornerstone, '1' ); ?> style="border-radius:4px;" />
								<span>¿Es contenido esencial?</span>
							</label>
							<p class="description" style="margin-top:4px; margin-bottom:0; line-height:1.3;">Los contenidos esenciales (cornerstone) requieren un análisis de lectura más exhaustivo (mínimo 900 palabras).</p>
						</div>
					</div>

					<!-- Apariencia en el buscador -->
					<div style="border-top:1px solid #e2e8f0; padding-top:20px; margin-top:20px;">
						<h3 style="margin-top:0; font-size:14.5px; font-weight:700; color:#1e293b; margin-bottom:15px;">Apariencia en el buscador</h3>
						
						<!-- Vista previa Google centrada y premium -->
						<div style="display:flex; justify-content:center; flex-direction:column; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:20px; margin-bottom:20px;">
							<div style="margin-bottom:15px; display:flex; gap:20px; align-items:center; font-size:12.5px; font-weight:600; color:#475569;">
								<span style="color:#64748b;">Previsualizar como:</span>
								<label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
									<input type="radio" name="wpat_seo_preview_mode" value="mobile" checked style="margin:0;" /> Resultado móvil
								</label>
								<label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
									<input type="radio" name="wpat_seo_preview_mode" value="desktop" style="margin:0;" /> Resultado en escritorio
								</label>
							</div>

							<!-- Caja de Previsualización Móvil -->
							<div id="wpat-seo-google-preview-mobile" class="wpat-seo-google-preview-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.04); max-width: 440px; width:100%; box-sizing:border-box; text-align: left;">
								<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:12px; color:#202124;">
									<div style="background:#f1f3f4; border-radius:50%; width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; font-weight:bold; font-size:10px;">
										<?php echo esc_html( strtoupper( substr( $site_name, 0, 1 ) ) ); ?>
									</div>
									<div style="display:flex; flex-direction:column; line-height:1.2;">
										<span style="font-size:12px; font-weight:bold;"><?php echo esc_html( $site_name ); ?></span>
										<span style="font-size:10px; color:#5f6368;"><?php echo esc_html( $display_url ); ?></span>
									</div>
								</div>
								<h3 class="wpat-seo-preview-title" style="color:#1a0dab; font-family: Roboto, sans-serif; font-size:18px; line-height:1.3; margin:0 0 4px 0; font-weight:normal; word-wrap: break-word;">
									<?php echo esc_html( ! empty( $title ) ? $title : $default_title_formatted ); ?>
								</h3>
								<p class="wpat-seo-preview-desc" style="color:#4d5156; font-size:13px; line-height:1.4; margin:0; word-wrap: break-word;">
									<?php echo esc_html( ! empty( $desc ) ? $desc : $default_desc ); ?>
								</p>
							</div>

							<!-- Caja de Previsualización Escritorio -->
							<div id="wpat-seo-google-preview-desktop" class="wpat-seo-google-preview-box" style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px; box-shadow:0 2px 8px rgba(0,0,0,0.04); width:100%; max-width:650px; display:none; box-sizing:border-box; text-align: left;">
								<div style="font-size:12px; color:#202124; margin-bottom:4px; line-height:1.3;">
									<span style="font-size:12px; color:#202124;"><?php echo esc_html( $display_url ); ?></span>
								</div>
								<h3 class="wpat-seo-preview-title" style="color:#1a0dab; font-family: Arial, sans-serif; font-size:20px; line-height:1.3; margin:0 0 4px 0; font-weight:normal; display:inline-block; word-wrap: break-word;">
									<?php echo esc_html( ! empty( $title ) ? $title : $default_title_formatted ); ?>
								</h3>
								<p class="wpat-seo-preview-desc" style="color:#4d5156; font-family: Arial, sans-serif; font-size:14px; line-height:1.48; margin:0; word-wrap: break-word;">
									<?php echo esc_html( ! empty( $desc ) ? $desc : $default_desc ); ?>
								</p>
							</div>
						</div>

						<div style="display:flex; flex-direction:column; gap:16px;">
							<!-- Título SEO -->
							<div>
								<div style="display:flex; justify-content:space-between; margin-bottom:4px; align-items:center;">
									<label for="wpat_seo_title_input" style="font-weight:700; font-size:12.5px; color:#475569;">Título SEO</label>
									<span id="wpat_seo_title_counter" style="font-size:11.5px; font-weight:600; color:#64748b;">
										<strong id="wpat_seo_title_len"><?php echo mb_strlen( ! empty( $title ) ? $title : $default_title_formatted ); ?></strong> / 60 caracteres
									</span>
								</div>
								<input type="text" id="wpat_seo_title_input" name="wpat_seo_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( $default_title_formatted ); ?>" style="width:100%; height:36px; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; box-sizing:border-box;" />
								<div style="width:100%; height:6px; background:#e2e8f0; border-radius:3px; margin-top:6px; overflow:hidden;">
									<div id="wpat_seo_title_progress_bar" style="width:0%; height:100%; background:#ef4444; transition:width 0.2s ease; border-radius:3px;"></div>
								</div>
							</div>

							<!-- Slug personalizado -->
							<div>
								<label for="wpat_seo_slug_input" style="font-weight:700; font-size:12.5px; color:#475569; display:block; margin-bottom:4px;">Slug de URL</label>
								<input type="text" id="wpat_seo_slug_input" name="wpat_seo_slug" value="<?php echo esc_attr( $post->post_name ); ?>" placeholder="slug-de-la-pagina" style="width:100%; height:36px; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; box-sizing:border-box;" />
							</div>

							<!-- Meta Descripción -->
							<div>
								<div style="display:flex; justify-content:space-between; margin-bottom:4px; align-items:center;">
									<label for="wpat_seo_desc_input" style="font-weight:700; font-size:12.5px; color:#475569;">Meta Descripción</label>
									<span id="wpat_seo_desc_counter" style="font-size:11.5px; font-weight:600; color:#64748b;">
										<strong id="wpat_seo_desc_len"><?php echo mb_strlen( ! empty( $desc ) ? $desc : '' ); ?></strong> / 160 caracteres
									</span>
								</div>
								<textarea id="wpat_seo_desc_input" name="wpat_seo_desc" rows="3" placeholder="Escribe la meta descripción que captará a tus visitas en buscadores..." style="width:100%; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1; resize:vertical; font-size:13px; box-sizing:border-box;"><?php echo esc_textarea( $desc ); ?></textarea>
								<div style="width:100%; height:6px; background:#e2e8f0; border-radius:3px; margin-top:6px; overflow:hidden;">
									<div id="wpat_seo_desc_progress_bar" style="width:0%; height:100%; background:#ef4444; transition:width 0.2s ease; border-radius:3px;"></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Análisis SEO en tiempo real -->
					<div style="border-top:1px solid #e2e8f0; padding-top:20px; margin-top:25px;">
						<h3 style="margin-top:0; font-size:15px; font-weight:700; color:#1e293b; margin-bottom:15px; display:flex; align-items:center; gap:6px;">
							<span class="dashicons dashicons-chart-bar" style="color:#64748b; font-size:18px; width:18px; height:18px; margin:0;"></span>
							Resultados del análisis SEO
						</h3>
						<div id="wpat_seo_analysis_bullets" style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
							<!-- Rellenado por JS en vivo -->
						</div>
					</div>

				</div>

				<!-- TAB 2: LEGIBILIDAD -->
				<div class="wpat-seo-tab-content" id="wpat-seo-tab-readability" style="display:none;">
					<h3 style="margin-top:0; font-size:15px; font-weight:700; color:#1e293b; margin-bottom:15px; display:flex; align-items:center; gap:6px;">
						<span class="dashicons dashicons-welcome-write-blog" style="color:#64748b; font-size:18px; width:18px; height:18px; margin:0;"></span>
						Resultados del análisis de Legibilidad
					</h3>
					<div id="wpat_readability_analysis_bullets" style="display:flex; flex-direction:column; gap:10px; font-size:13px;">
						<!-- Rellenado por JS en vivo -->
					</div>
				</div>

				<!-- TAB 3: SCHEMA -->
				<div class="wpat-seo-tab-content" id="wpat-seo-tab-schema" style="display:none;">
					<h3 style="margin-top:0; font-size:15px; font-weight:700; color:#1e293b; margin-bottom:5px;">Datos Estructurados (Schema.org)</h3>
					<p class="description" style="margin-bottom:20px;">Configura la información semántica para mejorar la indexación estructurada en motores de búsqueda.</p>
					
					<div style="display:flex; flex-direction:column; gap:15px; max-width:500px;">
						<div>
							<label for="wpat_seo_schema_page_type" style="font-weight:600; font-size:12.5px; color:#475569; display:block; margin-bottom:4px;">Tipo de Página</label>
							<select id="wpat_seo_schema_page_type" name="wpat_seo_schema_page_type" style="width:100%; height:36px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px;">
								<option value="WebPage" <?php selected( $schema_page_type, 'WebPage' ); ?>>Página Web estándar (Default)</option>
								<option value="AboutPage" <?php selected( $schema_page_type, 'AboutPage' ); ?>>Página "Sobre Mí / Quiénes Somos"</option>
								<option value="ContactPage" <?php selected( $schema_page_type, 'ContactPage' ); ?>>Página de Contacto</option>
								<option value="FAQPage" <?php selected( $schema_page_type, 'FAQPage' ); ?>>Página de Preguntas Frecuentes (FAQ)</option>
								<option value="ItemPage" <?php selected( $schema_page_type, 'ItemPage' ); ?>>Página de Artículo o Producto</option>
							</select>
						</div>

						<div>
							<label for="wpat_seo_schema_article_type" style="font-weight:600; font-size:12.5px; color:#475569; display:block; margin-bottom:4px;">Tipo de Artículo</label>
							<select id="wpat_seo_schema_article_type" name="wpat_seo_schema_article_type" style="width:100%; height:36px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px;">
								<option value="Article" <?php selected( $schema_article_type, 'Article' ); ?>>Artículo general (Default)</option>
								<option value="BlogPosting" <?php selected( $schema_article_type, 'BlogPosting' ); ?>>Entrada de Blog</option>
								<option value="NewsArticle" <?php selected( $schema_article_type, 'NewsArticle' ); ?>>Artículo de Noticias</option>
								<option value="TechArticle" <?php selected( $schema_article_type, 'TechArticle' ); ?>>Artículo Técnico</option>
							</select>
						</div>
					</div>
				</div>

				<!-- TAB 4: SOCIAL / INDEXABLE -->
				<div class="wpat-seo-tab-content" id="wpat-seo-tab-social" style="display:none;">
					<h3 style="margin-top:0; font-size:15px; font-weight:700; color:#1e293b; margin-bottom:15px;">Ajustes de Compartir e Indexación</h3>
					
					<div style="display:flex; flex-direction:column; gap:20px;">
						
						<!-- Título y Descripción Social -->
						<div style="display:flex; gap:20px; flex-wrap:wrap; border-bottom:1px solid #e2e8f0; padding-bottom:16px;">
							<div style="flex:1; min-width:250px;">
								<label for="wpat_seo_og_title_input" style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:4px;">Título Social Personalizado</label>
								<input type="text" id="wpat_seo_og_title_input" name="wpat_seo_og_title" value="<?php echo esc_attr( $og_title ); ?>" placeholder="Si se deja vacío se usará el título SEO" style="width:100%; height:36px; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; box-sizing:border-box;" />
							</div>
							<div style="flex:1; min-width:250px;">
								<label for="wpat_seo_og_desc_input" style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:4px;">Descripción Social Personalizada</label>
								<textarea id="wpat_seo_og_desc_input" name="wpat_seo_og_desc" rows="2" placeholder="Si se deja vacío se usará la meta descripción" style="width:100%; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; box-sizing:border-box; resize:vertical;"><?php echo esc_textarea( $og_desc ); ?></textarea>
							</div>
						</div>

						<!-- Imagen de portada redes -->
						<div style="border-bottom:1px solid #e2e8f0; padding-bottom:16px;">
							<label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Imagen de Portada Social (Open Graph Image)</label>
							<div style="display:flex; gap:10px; align-items:center; max-width:600px;">
								<input type="text" id="wpat_seo_og_image_input" name="wpat_seo_og_image" value="<?php echo esc_url( $og_img ); ?>" placeholder="https://..." style="flex:1; height:36px; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; box-sizing:border-box;" />
								<button type="button" id="wpat_seo_og_image_btn" class="button button-secondary" style="height:36px; line-height:34px;">Subir Imagen</button>
							</div>
							<p class="description" style="margin-top:4px;">Imagen que se mostrará al compartir en WhatsApp, Facebook, etc. Si está vacío, se usará la imagen destacada del post.</p>
						</div>

						<!-- Indexación y URL Canónica -->
						<div style="display:flex; gap:20px; flex-wrap:wrap; align-items:start;">
							<div style="flex:1; min-width:250px;">
								<label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Indexabilidad (Buscadores)</label>
								<label style="display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:500; cursor:pointer;">
									<input type="checkbox" name="wpat_seo_noindex" value="1" <?php checked( $noindex, '1' ); ?> style="border-radius:4px;" />
									<span>Marcar esta página como <strong>noindex</strong> (ocultar de Google)</span>
								</label>
								<p class="description" style="margin-top:4px;">Al activarlo, Google no mostrará esta página en sus resultados de búsqueda y será excluida del sitemap XML automáticamente.</p>
							</div>

							<div style="flex:1; min-width:250px;">
								<label for="wpat_seo_canonical_input" style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:4px;">URL Canónica Personalizada</label>
								<input type="url" id="wpat_seo_canonical_input" name="wpat_seo_canonical" value="<?php echo esc_url( $canonical ); ?>" placeholder="<?php echo esc_url( $preview_url ); ?>" style="width:100%; height:36px; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:13px; box-sizing:border-box;" />
								<p class="description" style="margin-top:4px;">Define la dirección web principal si este contenido está duplicado o publicado originalmente en otra URL.</p>
							</div>
						</div>
					</div>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Guarda la información del metabox SEO al salvar la página.
	 *
	 * @param int $post_id
	 */
	public function save_seo_meta_box_data( $post_id ) {
		// Validar seguridad
		if ( ! isset( $_POST['wpat_seo_nonce'] ) || ! wp_verify_nonce( $_POST['wpat_seo_nonce'], 'wpat_seo_metabox_save_action' ) ) {
			return;
		}

		// Evitar guardados automáticos
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Validar permisos
		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		// 1. Guardar Frase Clave
		if ( isset( $_POST['wpat_seo_keyword'] ) ) {
			$keyword = sanitize_text_field( $_POST['wpat_seo_keyword'] );
			update_post_meta( $post_id, '_wpat_seo_keyword', $keyword );
		}

		// 2. Guardar Título SEO
		if ( isset( $_POST['wpat_seo_title'] ) ) {
			$title = sanitize_text_field( $_POST['wpat_seo_title'] );
			update_post_meta( $post_id, '_wpat_seo_title', $title );
		}

		// 3. Guardar Meta Descripción
		if ( isset( $_POST['wpat_seo_desc'] ) ) {
			$desc = sanitize_textarea_field( $_POST['wpat_seo_desc'] );
			update_post_meta( $post_id, '_wpat_seo_desc', $desc );
		}

		// 4. Guardar noindex
		$noindex = isset( $_POST['wpat_seo_noindex'] ) ? '1' : '0';
		update_post_meta( $post_id, '_wpat_seo_noindex', $noindex );

		// 5. Guardar Open Graph Image
		if ( isset( $_POST['wpat_seo_og_image'] ) ) {
			$og_img = esc_url_raw( $_POST['wpat_seo_og_image'] );
			update_post_meta( $post_id, '_wpat_seo_og_image', $og_img );
		}

		// 6. Guardar Schema Page Type
		if ( isset( $_POST['wpat_seo_schema_page_type'] ) ) {
			update_post_meta( $post_id, '_wpat_seo_schema_page_type', sanitize_text_field( $_POST['wpat_seo_schema_page_type'] ) );
		}

		// 7. Guardar Schema Article Type
		if ( isset( $_POST['wpat_seo_schema_article_type'] ) ) {
			update_post_meta( $post_id, '_wpat_seo_schema_article_type', sanitize_text_field( $_POST['wpat_seo_schema_article_type'] ) );
		}

		// 8. Guardar URL Canónica
		if ( isset( $_POST['wpat_seo_canonical'] ) ) {
			$canonical = esc_url_raw( $_POST['wpat_seo_canonical'] );
			update_post_meta( $post_id, '_wpat_seo_canonical', $canonical );
		}

		// 9. Guardar Título Social
		if ( isset( $_POST['wpat_seo_og_title'] ) ) {
			$og_title = sanitize_text_field( $_POST['wpat_seo_og_title'] );
			update_post_meta( $post_id, '_wpat_seo_og_title', $og_title );
		}

		// 10. Guardar Descripción Social
		if ( isset( $_POST['wpat_seo_og_desc'] ) ) {
			$og_desc = sanitize_textarea_field( $_POST['wpat_seo_og_desc'] );
			update_post_meta( $post_id, '_wpat_seo_og_desc', $og_desc );
		}

		// 11. Guardar Contenido Esencial (Cornerstone)
		$cornerstone = isset( $_POST['wpat_seo_cornerstone'] ) ? '1' : '0';
		update_post_meta( $post_id, '_wpat_seo_cornerstone', $cornerstone );

		// 12. Guardar Slug Personalizado
		if ( isset( $_POST['wpat_seo_slug'] ) && ! empty( $_POST['wpat_seo_slug'] ) ) {
			$new_slug = sanitize_title( $_POST['wpat_seo_slug'] );
			$post = get_post( $post_id );
			if ( $post && $post->post_name !== $new_slug ) {
				// Evitar bucles infinitos en save_post
				remove_action( 'save_post', array( $this, 'save_seo_meta_box_data' ) );
				wp_update_post( array(
					'ID'        => $post_id,
					'post_name' => $new_slug,
				) );
				add_action( 'save_post', array( $this, 'save_seo_meta_box_data' ) );
			}
		}
	}

	/**
	 * Filtra el título en el frontend de la web con el título SEO personalizado.
	 *
	 * @param string $title
	 * @return string
	 */
	public function filter_frontend_title( $title ) {
		if ( is_singular() ) {
			$seo_title = get_post_meta( get_the_ID(), '_wpat_seo_title', true );
			if ( ! empty( $seo_title ) ) {
				return $seo_title;
			}
		}
		return $title;
	}

	/**
	 * Inyecta las meta etiquetas en el frontend de la web.
	 */
	public function inject_seo_meta_tags() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();

		// 1. URL Canónica
		$canonical = get_post_meta( $post_id, '_wpat_seo_canonical', true );
		if ( ! empty( $canonical ) ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}

		// 2. Meta descripción
		$seo_desc = get_post_meta( $post_id, '_wpat_seo_desc', true );
		if ( empty( $seo_desc ) ) {
			// Fallback: usar extracto o los primeros 155 caracteres del contenido limpio
			$post_obj = get_post( $post_id );
			if ( $post_obj ) {
				$raw_content = $post_obj->post_content;
				$clean_text  = trim( strip_tags( strip_shortcodes( $raw_content ) ) );
				$clean_text  = preg_replace( '/\s+/', ' ', $clean_text );
				if ( ! empty( $clean_text ) ) {
					$seo_desc = $clean_text;
					if ( mb_strlen( $seo_desc ) > 155 ) {
						$seo_desc = mb_substr( $seo_desc, 0, 152 ) . '...';
					}
				}
			}
		}
		if ( ! empty( $seo_desc ) ) {
			echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '" />' . "\n";
		}

		// 3. Robots noindex
		$noindex = get_post_meta( $post_id, '_wpat_seo_noindex', true );
		if ( '1' === $noindex ) {
			echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
		}

		// 4. Open Graph (Redes Sociales)
		$site_name = get_bloginfo( 'name' );
		$seo_title = get_post_meta( $post_id, '_wpat_seo_title', true );
		$title     = ! empty( $seo_title ) ? $seo_title : get_the_title( $post_id ) . ' - ' . $site_name;
		$desc      = ! empty( $seo_desc ) ? $seo_desc : wp_strip_all_tags( get_the_excerpt( $post_id ) );
		
		// Conseguir títulos/descripciones sociales personalizadas
		$og_title = get_post_meta( $post_id, '_wpat_seo_og_title', true );
		$og_desc  = get_post_meta( $post_id, '_wpat_seo_og_desc', true );

		$social_title = ! empty( $og_title ) ? $og_title : $title;
		$social_desc  = ! empty( $og_desc ) ? $og_desc : $desc;

		// Conseguir imagen destacada
		$og_img = get_post_meta( $post_id, '_wpat_seo_og_image', true );
		if ( empty( $og_img ) ) {
			$thumb_id = get_post_thumbnail_id( $post_id );
			if ( $thumb_id ) {
				$og_img = wp_get_attachment_url( $thumb_id );
			}
		}

		echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $social_title ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( get_permalink( $post_id ) ) . '" />' . "\n";
		if ( ! empty( $social_desc ) ) {
			echo '<meta property="og:description" content="' . esc_attr( $social_desc ) . '" />' . "\n";
		}
		if ( ! empty( $og_img ) ) {
			echo '<meta property="og:image" content="' . esc_url( $og_img ) . '" />' . "\n";
		}

		// Twitter Cards
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $social_title ) . '" />' . "\n";
		if ( ! empty( $social_desc ) ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $social_desc ) . '" />' . "\n";
		}
		if ( ! empty( $og_img ) ) {
			echo '<meta name="twitter:image" content="' . esc_url( $og_img ) . '" />' . "\n";
		}
	}

	/**
	 * Intercepta la petición de sitemap.xml en el init y la procesa.
	 */
	public function intercept_sitemap_request() {
		$request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		$home_path    = parse_url( home_url(), PHP_URL_PATH );
		$relative_path = $request_path;

		// Si WordPress está instalado en una subcarpeta
		if ( ! empty( $home_path ) && '/' !== $home_path ) {
			if ( 0 === strpos( $request_path, $home_path ) ) {
				$relative_path = substr( $request_path, strlen( $home_path ) );
			}
		}
		$relative_path = trim( $relative_path, '/' );

		// Si se pide exactamente sitemap.xml (insensible a mayúsculas)
		if ( 'sitemap.xml' === strtolower( $relative_path ) ) {
			$this->generate_xml_sitemap();
			exit;
		}
	}

	/**
	 * Genera el documento XML de Sitemap excluyendo los elementos configurados como noindex.
	 */
	private function generate_xml_sitemap() {
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: index, follow' );
		
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		// 1. Página de Portada (Home)
		$home_url = home_url( '/' );
		$latest_post = get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );
		$home_modified = ! empty( $latest_post ) ? get_the_modified_date( 'c', $latest_post[0]->ID ) : date( 'c' );

		// Comprobar si la Home está excluida por noindex
		$front_page_id = get_option( 'page_on_front' );
		$home_noindex = $front_page_id ? get_post_meta( $front_page_id, '_wpat_seo_noindex', true ) : '0';

		if ( '1' !== $home_noindex ) {
			echo "  <url>\n";
			echo "    <loc>" . esc_url( $home_url ) . "</loc>\n";
			echo "    <lastmod>" . esc_html( $home_modified ) . "</lastmod>\n";
			echo "    <changefreq>daily</changefreq>\n";
			echo "    <priority>1.0</priority>\n";
			echo "  </url>\n";
		}

		// 2. Entradas, Páginas, Productos WooCommerce y CPTs Públicos
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		$posts = get_posts( array(
			'post_type'      => array_values( $post_types ),
			'post_status'    => 'publish',
			'posts_per_page' => 1500, // Límite amplio para un sitemap plano
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				// Comprobar noindex
				$noindex = get_post_meta( $post->ID, '_wpat_seo_noindex', true );
				if ( '1' === $noindex ) {
					continue; // Exclusión dinámica inteligente
				}

				// Evitar duplicar la home si se define una página estática
				$permalink = get_permalink( $post->ID );
				if ( $permalink === $home_url ) {
					continue;
				}

				// Prioridades
				$priority = '0.8';
				if ( 'page' === $post->post_type ) {
					$priority = '0.7';
				} elseif ( 'product' === $post->post_type ) {
					$priority = '0.9';
				}

				$last_mod = get_the_modified_date( 'c', $post->ID );

				echo "  <url>\n";
				echo "    <loc>" . esc_url( $permalink ) . "</loc>\n";
				echo "    <lastmod>" . esc_html( $last_mod ) . "</lastmod>\n";
				echo "    <changefreq>weekly</changefreq>\n";
				echo "    <priority>" . esc_html( $priority ) . "</priority>\n";
				echo "  </url>\n";
			}
		}

		// 3. Taxonomías Públicas
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		$exclude_taxonomies = array( 'post_format', 'nav_menu', 'link_category' );
		$taxonomies = array_diff( $taxonomies, $exclude_taxonomies );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			) );

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_url = get_term_link( $term );
					if ( ! is_wp_error( $term_url ) ) {
						echo "  <url>\n";
						echo "    <loc>" . esc_url( $term_url ) . "</loc>\n";
						echo "    <changefreq>weekly</changefreq>\n";
						echo "    <priority>0.5</priority>\n";
						echo "  </url>\n";
					}
				}
			}
		}

		echo '</urlset>' . "\n";
	}

	/**
	 * Registra los filtros y acciones para las columnas personalizadas de SEO en todos los post types públicos.
	 */
	public function register_seo_list_columns() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		foreach ( $post_types as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_seo_columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_seo_columns_content' ), 10, 2 );
		}
	}

	/**
	 * Añade las cabeceras de columnas a la tabla de listados.
	 */
	public function add_seo_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $val ) {
			$new_columns[ $key ] = $val;
			if ( 'title' === $key ) {
				$new_columns['wpat_seo_score']       = '<span class="dashicons dashicons-admin-generic" title="Puntuación SEO"></span>';
				$new_columns['wpat_seo_readability'] = '<span class="dashicons dashicons-editor-textcolor" title="Puntuación de Legibilidad"></span>';
			}
		}

		if ( isset( $new_columns['date'] ) ) {
			$date_val = $new_columns['date'];
			unset( $new_columns['date'] );
			$new_columns['wpat_seo_title']   = 'Título SEO';
			$new_columns['wpat_seo_desc']    = 'Meta Desc.';
			$new_columns['wpat_seo_keyword'] = 'Frase clave';
			$new_columns['date']             = $date_val;
		} else {
			$new_columns['wpat_seo_title']   = 'Título SEO';
			$new_columns['wpat_seo_desc']    = 'Meta Desc.';
			$new_columns['wpat_seo_keyword'] = 'Frase clave';
		}

		return $new_columns;
	}

	/**
	 * Renderiza el contenido de cada columna para cada fila.
	 */
	public function render_seo_columns_content( $column, $post_id ) {
		switch ( $column ) {
			case 'wpat_seo_score':
				$score = $this->calculate_php_seo_score( $post_id );
				$this->render_score_bullet( $score, 'seo' );
				break;

			case 'wpat_seo_readability':
				$score = $this->calculate_php_readability_score( $post_id );
				$this->render_score_bullet( $score, 'readability' );
				break;

			case 'wpat_seo_title':
				$title = get_post_meta( $post_id, '_wpat_seo_title', true );
				if ( ! empty( $title ) ) {
					echo esc_html( $title );
				} else {
					echo '<span style="color:#cbd5e1;">—</span>';
				}
				break;

			case 'wpat_seo_desc':
				$desc = get_post_meta( $post_id, '_wpat_seo_desc', true );
				if ( ! empty( $desc ) ) {
					echo esc_html( $desc );
				} else {
					echo '<span style="color:#cbd5e1;">—</span>';
				}
				break;

			case 'wpat_seo_keyword':
				$keyword = get_post_meta( $post_id, '_wpat_seo_keyword', true );
				if ( ! empty( $keyword ) ) {
					echo esc_html( $keyword );
				} else {
					echo '<span style="color:#cbd5e1;">—</span>';
				}
				break;
		}
	}

	/**
	 * Renderiza un indicador visual (círculo de color) para las puntuaciones SEO o legibilidad.
	 */
	private function render_score_bullet( $status, $type ) {
		$bg_color = '#94a3b8'; 
		$title = 'Sin analizar';

		if ( 'no-content' === $status || 'no-keyword' === $status ) {
			$bg_color = '#94a3b8';
			$title = 'seo' === $type ? 'Sin frase clave objetivo' : 'Sin contenido';
		} elseif ( 'good' === $status ) {
			$bg_color = '#10b981'; 
			$title = 'seo' === $type ? 'SEO: Bueno' : 'Legibilidad: Buena';
		} elseif ( 'ok' === $status ) {
			$bg_color = '#f59e0b'; 
			$title = 'seo' === $type ? 'SEO: Aceptable' : 'Legibilidad: Aceptable';
		} elseif ( 'bad' === $status ) {
			$bg_color = '#ef4444'; 
			$title = 'seo' === $type ? 'SEO: Con problemas' : 'Legibilidad: Con problemas';
		}

		echo '<span style="display:inline-block; width:12px; height:12px; border-radius:50%; background-color:' . esc_attr( $bg_color ) . '; vertical-align: middle; margin-left: 2px;" title="' . esc_attr( $title ) . '"></span>';
	}

	/**
	 * Helper para eliminar acentos.
	 */
	private function remove_accents_php( $str ) {
		$unwanted_array = array(    
			'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C',
			'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
			'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a',
			'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i',
			'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u',
			'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
		);
		return strtr( $str, $unwanted_array );
	}

	/**
	 * Calcula de forma ligera la puntuación SEO en el backend.
	 */
	private function calculate_php_seo_score( $post_id ) {
		$keyword = get_post_meta( $post_id, '_wpat_seo_keyword', true );
		$title   = get_post_meta( $post_id, '_wpat_seo_title', true );
		if ( empty( $title ) ) {
			$title = get_the_title( $post_id ) . ' - ' . get_bloginfo( 'name' );
		}

		$desc = get_post_meta( $post_id, '_wpat_seo_desc', true );
		if ( empty( $desc ) ) {
			$post_obj = get_post( $post_id );
			if ( $post_obj ) {
				$raw_content = $post_obj->post_content;
				$clean_text  = trim( strip_tags( strip_shortcodes( $raw_content ) ) );
				$clean_text  = preg_replace( '/\s+/', ' ', $clean_text );
				if ( ! empty( $clean_text ) ) {
					$desc = $clean_text;
					if ( mb_strlen( $desc ) > 155 ) {
						$desc = mb_substr( $desc, 0, 152 ) . '...';
					}
				}
			}
		}

		$t_len = mb_strlen( $title );
		$d_len = mb_strlen( $desc );

		// Rangos recomendados
		$title_ok = ( $t_len >= 40 && $t_len <= 70 );
		$desc_ok  = ( $d_len >= 100 && $d_len <= 165 );

		$score = 0;
		if ( $title_ok ) {
			$score += 35;
		}
		if ( $desc_ok ) {
			$score += 35;
		}

		if ( ! empty( $keyword ) ) {
			$score += 10;
			$norm_keyword = strtolower( $this->remove_accents_php( $keyword ) );
			$norm_title   = strtolower( $this->remove_accents_php( $title ) );
			$norm_desc    = strtolower( $this->remove_accents_php( $desc ) );

			if ( ! empty( $title ) && false !== strpos( $norm_title, $norm_keyword ) ) {
				$score += 10;
			}
			if ( ! empty( $desc ) && false !== strpos( $norm_desc, $norm_keyword ) ) {
				$score += 10;
			}
		}

		if ( $score >= 70 ) {
			return 'good';
		} elseif ( $score >= 35 ) {
			return 'ok';
		} else {
			return 'bad';
		}
	}

	/**
	 * Calcula de forma ligera la legibilidad en el backend.
	 */
	private function calculate_php_readability_score( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 'no-content';
		}

		$content = $post->post_content;
		$clean_text = trim( strip_tags( $content ) );
		if ( empty( $clean_text ) ) {
			return 'no-content';
		}

		$word_count = count( preg_split( '/\s+/', $clean_text ) );
		$cornerstone = get_post_meta( $post_id, '_wpat_seo_cornerstone', true );
		$min_words = ( '1' === $cornerstone ) ? 900 : 300;

		$score = 0;
		if ( $word_count >= $min_words ) {
			$score += 50;
		}

		$sentences = preg_split( '/[.!?]+/', $clean_text, -1, PREG_SPLIT_NO_EMPTY );
		$sentence_count = count( $sentences );

		if ( $sentence_count > 0 ) {
			$avg_sentence_len = $word_count / $sentence_count;
			if ( $avg_sentence_len <= 20 ) {
				$score += 50;
			} else {
				$score += 25;
			}
		}

		if ( $score >= 80 ) {
			return 'good';
		} elseif ( $score >= 50 ) {
			return 'ok';
		} else {
			return 'bad';
		}
	}
}

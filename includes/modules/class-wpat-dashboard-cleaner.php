<?php
/**
 * Módulo: Escritorio Personalizado & Limpiador - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Dashboard_Cleaner {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Dashboard_Cleaner
	 */
	private static $instance = null;

	/**
	 * Buffer donde se acumularán las notificaciones capturadas de otros plugins.
	 *
	 * @var string
	 */
	private $captured_notices = '';

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Dashboard_Cleaner
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
		add_action( 'admin_init', array( $this, 'init_dashboard_cleaner' ) );
		add_action( 'wp_ajax_wpat_send_dashboard_support_email', array( $this, 'ajax_send_support_email' ) );
	}

	/**
	 * Inicializa el limpiador de escritorio si estamos en la pantalla index.php de administración.
	 */
	public function init_dashboard_cleaner() {
		global $pagenow;

		// Solo ejecutar si estamos en el Escritorio principal
		if ( is_admin() && 'index.php' === $pagenow && ! isset( $_GET['page'] ) ) {
			// 1. Quitar todos los widgets por defecto y de terceros
			add_action( 'wp_dashboard_setup', array( $this, 'clean_all_dashboard_widgets' ), 999 );

			// 2. Forzar la visibilidad del Panel de Bienvenida nativo para renderizar nuestro contenido
			add_filter( 'get_user_metadata', array( $this, 'force_welcome_panel_visibility' ), 10, 4 );

			// 3. Desactivar el panel de bienvenida de WordPress original y acoplar el nuestro
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
			add_action( 'welcome_panel', array( $this, 'render_custom_dashboard' ) );

			// 4. Capturar alertas de plugins en búfer por cada hook individualmente con prioridad extrema
			add_action( 'all_admin_notices', array( $this, 'capture_admin_notices_start' ), PHP_INT_MIN );
			add_action( 'all_admin_notices', array( $this, 'capture_admin_notices_end' ), PHP_INT_MAX );

			add_action( 'admin_notices', array( $this, 'capture_admin_notices_start' ), PHP_INT_MIN );
			add_action( 'admin_notices', array( $this, 'capture_admin_notices_end' ), PHP_INT_MAX );

			add_action( 'user_admin_notices', array( $this, 'capture_admin_notices_start' ), PHP_INT_MIN );
			add_action( 'user_admin_notices', array( $this, 'capture_admin_notices_end' ), PHP_INT_MAX );

			// 5. Inyectar los estilos CSS necesarios en la cabecera
			add_action( 'admin_head', array( $this, 'inject_dashboard_styles' ) );

			// 6. Inyectar el panel lateral deslizante y sus scripts en el footer para tener el buffer lleno
			add_action( 'admin_footer', array( $this, 'render_notifications_panel' ) );
		}
	}

	/**
	 * Fuerza el retorno de '1' para 'show_welcome_panel' para asegurar que se pinte siempre.
	 */
	public function force_welcome_panel_visibility( $val, $object_id, $meta_key, $single ) {
		if ( 'show_welcome_panel' === $meta_key ) {
			return 1;
		}
		return $val;
	}

	/**
	 * Inicia la captura del buffer de notificaciones.
	 */
	public function capture_admin_notices_start() {
		ob_start();
	}

	/**
	 * Cierra y guarda la captura de notificaciones.
	 */
	public function capture_admin_notices_end() {
		$notices = ob_get_clean();
		if ( ! empty( $notices ) ) {
			$this->captured_notices .= $notices;
		}
	}

	/**
	 * Limpia por completo la pantalla de widgets nativos.
	 */
	public function clean_all_dashboard_widgets() {
		global $wp_meta_boxes;

		$wp_meta_boxes['dashboard'] = array(
			'normal'   => array( 'core' => array() ),
			'side'     => array( 'core' => array() ),
			'advanced' => array( 'core' => array() ),
		);
	}

	/**
	 * Inyecta los estilos CSS premium para la opción de diseño C-1 y el panel lateral.
	 */
	public function inject_dashboard_styles() {
		?>
		<style>
			/* Ocultar el panel de widgets estándar */
			.index-php #dashboard-widgets {
				display: none !important;
			}

			/* Resetear el contenedor de bienvenida nativo */
			.index-php #welcome-panel.welcome-panel {
				background: transparent !important;
				border: none !important;
				box-shadow: none !important;
				margin: 0 !important;
				padding: 0 !important;
			}
			.index-php .welcome-panel-close {
				display: none !important;
			}

			/* Ocultar notificaciones y avisos del flujo principal del Escritorio (Hijos directos) */
			.index-php #wpbody-content > .notice,
			.index-php #wpbody-content > .update-nag,
			.index-php #wpbody-content > div.error,
			.index-php #wpbody-content > div.updated,
			.index-php #wpbody-content > #message,
			.index-php .wrap > .notice,
			.index-php .wrap > .update-nag,
			.index-php .wrap > div.error,
			.index-php .wrap > div.updated,
			.index-php .wrap > #message {
				display: none !important;
			}

			/* Inyectar degradado corporativo fluido en el fondo derecho */
			.index-php #wpbody-content {
				background: linear-gradient(135deg, #f1f4f9 0%, #e8ecf5 50%, #e0e5f0 100%) !important;
				min-height: calc(100vh - 32px);
				padding-bottom: 40px !important;
			}

			/* Cabecera del escritorio */
			.wpat-db-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-top: 15px;
				margin-bottom: 25px;
				padding: 10px 0;
				border-bottom: 1px solid rgba(0, 0, 0, 0.05);
				flex-wrap: wrap;
				gap: 15px;
			}
			.wpat-db-welcome h2 {
				font-size: 24px;
				font-weight: 700;
				color: #1e293b;
				margin: 0 0 4px 0;
				line-height: 1.2;
			}
			.wpat-db-welcome p {
				font-size: 13px;
				color: #64748b;
				margin: 0;
			}
			.wpat-db-time-card {
				background: rgba(255, 255, 255, 0.6);
				backdrop-filter: blur(8px);
				border: 1px solid rgba(255, 255, 255, 0.4);
				padding: 8px 16px;
				border-radius: 6px;
				display: flex;
				align-items: center;
				gap: 10px;
				font-size: 13px;
				color: #475569;
				font-weight: 500;
				box-shadow: 0 2px 8px rgba(0,0,0,0.02);
			}

			/* Rejilla del Escritorio (3 Columnas en resoluciones de pantalla) */
			.wpat-db-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
				gap: 20px;
				margin-bottom: 25px;
			}
			@media (min-width: 1200px) {
				.wpat-db-grid {
					grid-template-columns: repeat(3, 1fr);
				}
			}

			/* Tarjetas Premium (Glassmorphism) */
			.wpat-db-card {
				background: rgba(255, 255, 255, 0.85);
				backdrop-filter: blur(10px);
				border: 1px solid rgba(255, 255, 255, 0.4);
				border-radius: 8px;
				padding: 20px;
				box-shadow: 0 4px 15px rgba(0,0,0,0.04);
				display: flex;
				flex-direction: column;
				justify-content: space-between;
				transition: transform 0.2s ease, box-shadow 0.2s ease;
			}
			.wpat-db-card:hover {
				transform: translateY(-2px);
				box-shadow: 0 6px 20px rgba(0,0,0,0.06);
			}
			.wpat-db-card-title {
				font-size: 15px;
				font-weight: 600;
				color: #334155;
				margin: 0 0 12px 0;
				display: flex;
				align-items: center;
				gap: 8px;
			}
			.wpat-db-card-title svg {
				width: 18px;
				height: 18px;
				color: #3b82f6;
			}
			.wpat-db-card-value {
				font-size: 24px;
				font-weight: 700;
				color: #1e293b;
				margin-bottom: 6px;
			}
			.wpat-db-card-desc {
				font-size: 13px;
				color: #64748b;
				margin: 0 0 20px 0;
				line-height: 1.4;
			}

			/* Botoneras */
			.wpat-db-actions {
				display: flex;
				gap: 10px;
				flex-wrap: wrap;
			}
			.wpat-db-btn {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				padding: 7px 14px;
				border-radius: 5px;
				font-weight: 600;
				font-size: 12px;
				text-decoration: none;
				transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
				cursor: pointer;
				border: 1px solid transparent;
				height: 30px;
				box-sizing: border-box;
			}
			.wpat-db-btn-primary {
				background: #3b82f6;
				color: #fff !important;
			}
			.wpat-db-btn-primary:hover {
				background: #2563eb;
			}
			.wpat-db-btn-secondary {
				background: #f8fafc;
				color: #475569 !important;
				border: 1px solid #cbd5e1;
			}
			.wpat-db-btn-secondary:hover {
				background: #f1f5f9;
				border-color: #94a3b8;
			}
			.wpat-db-btn-success {
				background: #10b981;
				color: #fff !important;
			}
			.wpat-db-btn-success:hover {
				background: #059669;
			}
			.wpat-db-btn-warning {
				background: #ea580c;
				color: #fff !important;
			}
			.wpat-db-btn-warning:hover {
				background: #c2410c;
			}

			/* Tarjeta de soporte */
			.wpat-db-support-card {
				background: rgba(255, 255, 255, 0.9);
				backdrop-filter: blur(10px);
				border: 1px solid rgba(255, 255, 255, 0.4);
				border-radius: 8px;
				padding: 24px;
				box-shadow: 0 4px 15px rgba(0,0,0,0.04);
				margin-top: 5px;
			}
			.wpat-db-support-card h3 {
				font-size: 16px;
				font-weight: 700;
				color: #1e293b;
				margin: 0 0 10px 0;
				display: flex;
				align-items: center;
				gap: 8px;
			}
			.wpat-db-support-card h3 svg {
				width: 20px;
				height: 20px;
				color: #10b981;
			}

			/* Forzar visualización de alertas dentro del panel lateral deslizante */
			.wpat-captured-notices-container .notice,
			.wpat-captured-notices-container .update-nag,
			.wpat-captured-notices-container div.error,
			.wpat-captured-notices-container div.updated {
				margin: 0 0 15px 0 !important;
				position: relative !important;
				display: block !important;
				width: auto !important;
				float: none !important;
				box-shadow: 0 2px 8px rgba(0,0,0,0.05) !important;
				border-radius: 4px !important;
				padding: 10px 15px !important;
				opacity: 1 !important;
				visibility: visible !important;
			}
			.wpat-captured-notices-container .notice-dismiss {
				display: none !important;
			}
		</style>
		<?php
	}

	/**
	 * Renderiza el Escritorio Personalizado.
	 */
	public function render_custom_dashboard() {
		// Obtener configuración
		$settings = WPAT_Main::get_instance()->get_settings();

		// Calcular estadísticas de SEO para la tarjeta del Escritorio
		$seo_enabled = isset( $settings['seo'] ) && '1' === $settings['seo'];
		$health_score = 100;
		$total_audited = 0;
		$optimized_count = 0;
		$issues_count = 0;
		$no_keyword_count = 0;

		if ( $seo_enabled ) {
			$post_types = get_post_types( array( 'public' => true ), 'names' );
			if ( isset( $post_types['attachment'] ) ) {
				unset( $post_types['attachment'] );
			}
			$scan_post_types = array_values( $post_types );

			$all_posts = get_posts( array(
				'post_type'      => $scan_post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			) );

			$total_audited = count( $all_posts );
			if ( $total_audited > 0 ) {
				// Calentar la caché de metadatos de forma masiva en una única consulta de BD
				update_postmeta_cache( $all_posts );

				$site_name = get_bloginfo( 'name' );

				foreach ( $all_posts as $post_id ) {
					$keyword = get_post_meta( $post_id, '_wpat_seo_keyword', true );
					$title   = get_post_meta( $post_id, '_wpat_seo_title', true );
					if ( empty( $title ) ) {
						$title = get_the_title( $post_id ) . ' - ' . $site_name;
					}

					$desc  = get_post_meta( $post_id, '_wpat_seo_desc', true );
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

					// Rangos óptimos recomendados para el análisis global
					$title_ok = ( $t_len >= 40 && $t_len <= 70 );
					$desc_ok  = ( $d_len >= 100 && $d_len <= 165 );

					if ( empty( $keyword ) ) {
						$no_keyword_count++;
					}

					if ( ! $title_ok || ! $desc_ok || empty( $title ) || empty( $desc ) ) {
						$issues_count++;
					}

					if ( $title_ok && $desc_ok ) {
						$optimized_count++;
					}
				}
				$health_score = round( ( $optimized_count / $total_audited ) * 100 );
			}
		}

		// Datos del usuario y de localización
		$current_user = wp_get_current_user();
		$user_name = ! empty( $current_user->display_name ) ? $current_user->display_name : $current_user->user_login;
		$current_date = ucfirst( date_i18n( 'l, j \d\e F \d\e Y' ) );

		// FORZAR ACTUALIZACIÓN SILENCIOSA Y TRICK DE PAGENAME PARA ACTUALIZADORES PROS (Elementor Pro, ACF Pro, etc.)
		$update_plugins = get_site_transient( 'update_plugins' );
		$update_themes  = get_site_transient( 'update_themes' );
		$current_time   = time();
		$refresh_needed = false;

		// Si el transient no existe o tiene más de 2 horas de antigüedad, realizamos la llamada síncrona (con spoofing para que los pros carguen)
		if ( ! $update_plugins || ! isset( $update_plugins->last_checked ) || ( $current_time - $update_plugins->last_checked ) > 7200 ) {
			global $pagenow;
			$original_pagenow = $pagenow;
			$pagenow = 'update-core.php'; // Truco de spoofing para que el updater pro responda

			require_once ABSPATH . 'wp-admin/includes/update.php';
			wp_update_plugins();

			$pagenow = $original_pagenow;
			$refresh_needed = true;
		}

		if ( ! $update_themes || ! isset( $update_themes->last_checked ) || ( $current_time - $update_themes->last_checked ) > 7200 ) {
			global $pagenow;
			$original_pagenow = $pagenow;
			$pagenow = 'update-core.php';

			require_once ABSPATH . 'wp-admin/includes/update.php';
			wp_update_themes();

			$pagenow = $original_pagenow;
			$refresh_needed = true;
		}

		if ( $refresh_needed ) {
			$update_plugins = get_site_transient( 'update_plugins' );
			$update_themes  = get_site_transient( 'update_themes' );
		}

		// Ejecución forzada de filtros con spoofing de página para inyección inmediata de plugins y temas de pago (premium)
		global $pagenow;
		$original_pagenow = $pagenow;
		$pagenow = 'update-core.php';

		if ( $update_plugins ) {
			$update_plugins = apply_filters( 'site_transient_update_plugins', $update_plugins );
			$update_plugins = apply_filters( 'pre_set_site_transient_update_plugins', $update_plugins );
		}
		if ( $update_themes ) {
			$update_themes = apply_filters( 'site_transient_update_themes', $update_themes );
			$update_themes = apply_filters( 'pre_set_site_transient_update_themes', $update_themes );
		}

		$pagenow = $original_pagenow;

		// 1. Conteo de Páginas
		$pages_count = wp_count_posts( 'page' );
		$total_pages = intval( $pages_count->publish ) + intval( $pages_count->draft );

		// 2. Conteo de Entradas
		$posts_count = wp_count_posts( 'post' );
		$total_posts = intval( $posts_count->publish ) + intval( $posts_count->draft );

		// 3. Conteo de Plugins e Identificación de Actualizaciones
		$plugins = get_plugins();
		$total_plugins = count( $plugins );
		$active_plugins = 0;
		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$active_plugins++;
			}
		}
		$pending_updates = ! empty( $update_plugins->response ) ? count( $update_plugins->response ) : 0;
		$plugins_to_update = array();
		if ( $pending_updates > 0 ) {
			foreach ( $update_plugins->response as $plugin_file => $plugin_data ) {
				$plugins_to_update[] = array(
					'file' => $plugin_file,
					'slug' => ! empty( $plugin_data->slug ) ? $plugin_data->slug : dirname( $plugin_file ),
				);
			}
		}

		// 4. Conteo de Temas e Identificación de Actualizaciones
		$themes = wp_get_themes();
		$total_themes = count( $themes );
		$active_theme = wp_get_theme()->get( 'Name' );
		$pending_theme_updates = ! empty( $update_themes->response ) ? count( $update_themes->response ) : 0;
		$themes_to_update = array();
		if ( $pending_theme_updates > 0 ) {
			foreach ( $update_themes->response as $theme_slug => $theme_data ) {
				$themes_to_update[] = array(
					'file' => $theme_slug,
					'slug' => $theme_slug,
				);
			}
		}

		// 5. Conteo de Usuarios
		$user_counts = count_users();
		$total_users = $user_counts['total_users'];
		$roles_list = array();
		foreach ( $user_counts['avail_roles'] as $role => $count ) {
			if ( $count > 0 ) {
				$role_name = translate_user_role( wp_roles()->role_names[ $role ] );
				$roles_list[] = esc_html( $role_name ) . ': <strong>' . intval( $count ) . '</strong>';
			}
		}
		$roles_html = implode( ' &nbsp;•&nbsp; ', $roles_list );

		// 6. Consultar estadísticas de JetEngine si está activo
		$jet_engine_active = class_exists( 'Jet_Engine' );
		$total_cpts = 0;
		$total_taxonomies = 0;
		$total_listings = 0;

		if ( $jet_engine_active ) {
			global $wpdb;
			$cpts_table = $wpdb->prefix . 'jet_post_types';
			$tax_table  = $wpdb->prefix . 'jet_taxonomies';

			if ( $wpdb->get_var( "SHOW TABLES LIKE '$cpts_table'" ) === $cpts_table ) {
				$total_cpts = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $cpts_table" ) );
			}

			if ( $wpdb->get_var( "SHOW TABLES LIKE '$tax_table'" ) === $tax_table ) {
				$total_taxonomies = intval( $wpdb->get_var( "SELECT COUNT(*) FROM $tax_table" ) );
			}

			$listings_count = wp_count_posts( 'jet-engine' );
			$total_listings = isset( $listings_count->publish ) ? intval( $listings_count->publish ) : 0;
		}

		// 7. Calcular peso de la web por separado (con iteradores rápidos y caché de 12 horas)
		$weight_data = get_transient( 'wpat_site_weight_breakdown' );
		if ( false === $weight_data ) {
			$db_bytes = $this->wpat_get_db_size();
			$files_bytes = $this->wpat_get_folder_size( WP_CONTENT_DIR );
			$total_bytes = $db_bytes + $files_bytes;

			$weight_data = array(
				'db'        => $this->wpat_format_bytes( $db_bytes ),
				'files'     => $this->wpat_format_bytes( $files_bytes ),
				'total'     => $this->wpat_format_bytes( $total_bytes ),
				'timestamp' => time(),
			);
			set_transient( 'wpat_site_weight_breakdown', $weight_data, 43200 ); // Cache por 12 horas
		}

		// 7.1. Obtener estadísticas de WooCommerce (solo si está activo)
		$total_products      = 0;
		$outofstock_products = 0;
		$woo_active          = class_exists( 'WooCommerce' );
		if ( $woo_active ) {
			$products_count      = wp_count_posts( 'product' );
			$total_products      = ( isset( $products_count->publish ) ? intval( $products_count->publish ) : 0 ) + ( isset( $products_count->draft ) ? intval( $products_count->draft ) : 0 );
			
			global $wpdb;
			$outofstock_products = intval( $wpdb->get_var( "
				SELECT COUNT(post_id) 
				FROM {$wpdb->postmeta} 
				WHERE meta_key = '_stock_status' AND meta_value = 'outofstock'
			" ) );
		}

		// 7.2. Obtener estadísticas de Biblioteca de Medios
		$media_count = wp_count_attachments();
		$total_media = 0;
		if ( is_object( $media_count ) ) {
			foreach ( get_object_vars( $media_count ) as $count ) {
				$total_media += intval( $count );
			}
		}

		// Nonce de seguridad para actualizaciones AJAX nativas
		$ajax_updates_nonce = wp_create_nonce( 'updates' );

		// 8. Configuración de soporte de la Agencia
		$support_title = 'Soporte y Gestión';
		$support_text  = 'Bienvenido al panel de administración de tu sitio web. Si necesitas asistencia, puedes ponerte en contacto con nosotros a través del formulario de soporte.';
		?>
		<div class="wpat-custom-dashboard-wrapper">
			
			<!-- CABECERA -->
			<div class="wpat-db-header">
				<div class="wpat-db-welcome">
					<h2>¡Hola, <?php echo esc_html( $user_name ); ?>!</h2>
					<p>Gestiona los contenidos y configuraciones del sitio web de forma rápida y sencilla.</p>
				</div>
				
				<div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
					<!-- Contenedor para indicadores de notificaciones (Pintado encima del reloj) -->
					<div id="wpat-header-notices-container" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end;"></div>

					<!-- Reloj -->
					<div class="wpat-db-time-card" style="margin: 0;">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:16px; height:16px; color:#64748b;">
							<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.5-13a.5.5 0 00-1 0v5a.5.5 0 00.25.433l3.5 2a.5.5 0 00.5-.866L10.5 9.75V5z" clip-rule="evenodd"/>
						</svg>
						<span><?php echo esc_html( $current_date ); ?></span>
						<span>•</span>
						<strong id="wpat-live-clock">00:00:00</strong>
					</div>
				</div>
			</div>

			<!-- ALERTA ACTUALIZACIÓN WORDPRESS -->
			<?php
			$update_core = get_preferred_from_update_core();
			if ( isset( $update_core->response ) && 'upgrade' === $update_core->response && current_user_can( 'update_core' ) ) :
				$wp_update_version = $update_core->current;
			?>
				<div style="background: #ffedd5; border: 1px solid #fed7aa; border-radius: 6px; padding: 12px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #c2410c; box-shadow:0 2px 10px rgba(234,88,12,0.05);">
					<div style="display: flex; align-items: center; gap: 8px; font-weight: 600;">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 20px; height: 20px; color: #ea580c; flex-shrink: 0;">
							<path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 110 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
						</svg>
						<span>Nueva versión de WordPress disponible (v<?php echo esc_html( $wp_update_version ); ?>). Se recomienda actualizar el núcleo del sistema.</span>
					</div>
					<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-warning" style="height: 28px; line-height: 28px; padding: 0 12px;">Actualizar WordPress</a>
				</div>
			<?php endif; ?>

			<!-- REJILLA DE TARJETAS -->
			<div class="wpat-db-grid">

				<!-- TARJETA SEO (Solo si el módulo está activo) -->
				<?php if ( $seo_enabled && ( ! isset( $settings['db_card_seo'] ) || '1' === $settings['db_card_seo'] ) ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L10 10.586 13.586 7H12z" clip-rule="evenodd" />
								</svg>
								Salud SEO del Sitio
							</h4>
							<div class="wpat-db-card-value" style="display: flex; align-items: baseline; gap: 8px;">
								<?php echo intval( $health_score ); ?>%
								<span style="font-size: 13px; font-weight: 700; color: <?php echo $health_score >= 80 ? '#10b981' : ( $health_score >= 50 ? '#d97706' : '#ef4444' ); ?>;">
									(<?php echo $health_score >= 80 ? 'Excelente' : ( $health_score >= 50 ? 'Ajustable' : 'Crítico' ); ?>)
								</span>
							</div>
							<p class="wpat-db-card-desc" style="margin-bottom: 12px;">
								Analizadas <strong><?php echo intval( $total_audited ); ?></strong> páginas en total.
							</p>
							<div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">
								<span style="color:#047857; background:#d1fae5; padding:2px 6px; border-radius:4px; font-weight:700; font-size:10.5px;">🟢 <?php echo intval( $optimized_count ); ?> OK</span>
								<span style="color:#b45309; background:#fef3c7; padding:2px 6px; border-radius:4px; font-weight:700; font-size:10.5px;">🟡 <?php echo intval( $issues_count ); ?> Alertas</span>
								<span style="color:#b91c1c; background:#fee2e2; padding:2px 6px; border-radius:4px; font-weight:700; font-size:10.5px;">🔴 <?php echo intval( $no_keyword_count ); ?> Sin palabra</span>
							</div>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&tab=tab-seo' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">Auditar SEO</a>
							<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" download="sitemap.xml" class="wpat-db-btn wpat-db-btn-secondary">Descargar Sitemap</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- 1. PÁGINAS -->
				<?php if ( ! isset( $settings['db_card_pages'] ) || '1' === $settings['db_card_pages'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
								</svg>
								Estructura de Páginas
							</h4>
							<div class="wpat-db-card-value"><?php echo intval( $total_pages ); ?></div>
							<p class="wpat-db-card-desc"><?php echo intval( $pages_count->publish ); ?> publicadas y <?php echo intval( $pages_count->draft ); ?> borradores. Las páginas estructuran la navegación de tu sitio.</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">+ Añadir Página</a>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Ver Todas</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- 2. ENTRADAS (BLOG) -->
				<?php if ( ! isset( $settings['db_card_posts'] ) || '1' === $settings['db_card_posts'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
								</svg>
								Artículos de Blog
							</h4>
							<div class="wpat-db-card-value"><?php echo intval( $total_posts ); ?></div>
							<p class="wpat-db-card-desc"><?php echo intval( $posts_count->publish ); ?> publicados y <?php echo intval( $posts_count->draft ); ?> borradores. Las entradas alimentan las novedades del blog.</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">+ Añadir Entrada</a>
							<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Ver Todas</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- 3. PLUGINS -->
				<?php if ( ! isset( $settings['db_card_plugins'] ) || '1' === $settings['db_card_plugins'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd" />
								</svg>
								Plugins Instalados
							</h4>
							<div class="wpat-db-card-value">
								<?php echo intval( $total_plugins ); ?>
								<span style="font-size: 14px; font-weight: normal; color: #64748b;">(<?php echo intval( $active_plugins ); ?> activos)</span>
							</div>
							<p class="wpat-db-card-desc">
								<?php if ( $pending_updates > 0 ) : ?>
									Hay <strong style="color:#ea580c;"><?php echo intval( $pending_updates ); ?> actualizaciones pendientes</strong> de realizar en el sistema.
								<?php else : ?>
									¡Excelente! Todos los plugins están actualizados a su última versión.
								<?php endif; ?>
							</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'plugin-install.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">+ Añadir Plugin</a>
							<?php if ( $pending_updates > 0 ) : ?>
								<button class="wpat-db-btn wpat-db-btn-warning wpat-ajax-update-btn" data-type="plugin" data-nonce="<?php echo esc_attr( $ajax_updates_nonce ); ?>" data-list="<?php echo esc_attr( wp_json_encode( $plugins_to_update ) ); ?>">
									Actualizar Todos (<?php echo intval( $pending_updates ); ?>)
								</button>
							<?php else : ?>
								<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Gestionar Plugins</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
 
 				<!-- 4. TEMAS -->
				<?php if ( ! isset( $settings['db_card_themes'] ) || '1' === $settings['db_card_themes'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd" />
								</svg>
								Temas del Sitio
							</h4>
							<div class="wpat-db-card-value">
								<?php echo intval( $total_themes ); ?>
								<span style="font-size: 14px; font-weight: normal; color: #64748b;">(Activo: <?php echo esc_html( $active_theme ); ?>)</span>
							</div>
							<p class="wpat-db-card-desc">
								<?php if ( $pending_theme_updates > 0 ) : ?>
									Hay <strong style="color:#ea580c;"><?php echo intval( $pending_theme_updates ); ?> temas con actualizaciones</strong> pendientes de realizar en la web.
								<?php else : ?>
									¡Excelente! Todos tus temas instalados están al día.
								<?php endif; ?>
							</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'theme-install.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">+ Añadir Tema</a>
							<?php if ( $pending_theme_updates > 0 ) : ?>
								<button class="wpat-db-btn wpat-db-btn-warning wpat-ajax-update-btn" data-type="theme" data-nonce="<?php echo esc_attr( $ajax_updates_nonce ); ?>" data-list="<?php echo esc_attr( wp_json_encode( $themes_to_update ) ); ?>">
									Actualizar Todos (<?php echo intval( $pending_theme_updates ); ?>)
								</button>
							<?php else : ?>
								<a href="<?php echo esc_url( admin_url( 'themes.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Ver Temas</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
 
 				<!-- 5. USUARIOS -->
				<?php if ( ! isset( $settings['db_card_users'] ) || '1' === $settings['db_card_users'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.07-.32.07-.65 0-.98a5.002 5.002 0 00-7.86 0c.07.33.07.66 0 .98A8.97 8.97 0 013 18v-1a5 5 0 0110 0v1a8.97 8.97 0 01-1.07-1zM21 17v-1a5 5 0 00-9-2.93 5.002 5.002 0 011.07 3.93 8.97 8.97 0 011.07 1v-1z" />
								</svg>
								Cuentas y Accesos
							</h4>
							<div class="wpat-db-card-value"><?php echo intval( $total_users ); ?></div>
							<p class="wpat-db-card-desc"><?php echo $roles_html; ?>. Gestiona quién tiene acceso de edición o administración al sitio.</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">+ Añadir Usuario</a>
							<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Ver Todos</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- 6. PESO Y BASE DE DATOS -->
				<?php if ( ! isset( $settings['db_card_db'] ) || '1' === $settings['db_card_db'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm14 1a1 1 0 100-2 1 1 0 000 2zm-14 7a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2zm14 1a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
								</svg>
								Peso y Base de Datos
							</h4>
							<div class="wpat-db-card-value">
								<?php echo esc_html( $weight_data['total'] ); ?>
							</div>
							<p class="wpat-db-card-desc">
								Base de datos: <strong><?php echo esc_html( $weight_data['db'] ); ?></strong>. Carpeta Content: <strong><?php echo esc_html( $weight_data['files'] ); ?></strong>. Limpia transitorios y optimiza el almacenamiento.
							</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&tab=tab-health' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">Optimizar BD</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&tab=tab-health' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Límites Servidor</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- 7. IMPORTADOR Y EXPORTADOR (HERRAMIENTAS JSON) -->
				<?php if ( ! isset( $settings['db_card_tools'] ) || '1' === $settings['db_card_tools'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
								</svg>
								Copias y Contenidos
							</h4>
							<div class="wpat-db-card-value">JSON Activo</div>
							<p class="wpat-db-card-desc">
								Exporta e importa contenidos completos y metas de SEO en archivos JSON para duplicar estructuras rápidamente.
							</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&tab=tab-tools' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">Ir a Importar</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&tab=tab-tools' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Exportar JSON</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- 8. SERVIDOR SMTP (CORREO) -->
				<?php if ( ! isset( $settings['db_card_smtp'] ) || '1' === $settings['db_card_smtp'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
									<path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
								</svg>
								Servidor de Correo SMTP
							</h4>
							<div class="wpat-db-card-value">
								<?php echo isset( $settings['smtp'] ) && '1' === $settings['smtp'] ? '🟢 Activo' : '⚪ Inactivo'; ?>
							</div>
							<p class="wpat-db-card-desc">
								Garantiza que todos los correos del sistema y WooCommerce lleguen a la bandeja de entrada evitando el spam.
							</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&tab=tab-smtp' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">Configurar SMTP</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&tab=tab-smtp' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Probar Envío</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- 6. JETENGINE (Solo si está activo) -->
				<?php if ( $jet_engine_active && ( ! isset( $settings['db_card_jet'] ) || '1' === $settings['db_card_jet'] ) ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="color: #3b82f6;">
									<path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
								</svg>
								Estructura JetEngine
							</h4>
							<div class="wpat-db-card-value">
								<?php echo intval( $total_cpts ); ?> <span style="font-size: 13px; font-weight: normal; color: #64748b;">CPTs</span>
								&nbsp;•&nbsp;
								<?php echo intval( $total_listings ); ?> <span style="font-size: 13px; font-weight: normal; color: #64748b;">Listings</span>
							</div>
							<p class="wpat-db-card-desc">
								Se han configurado <strong><?php echo intval( $total_taxonomies ); ?> taxonomías</strong> en JetEngine. Controla la estructura dinámica del sitio.
							</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=jet-engine-cpt' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">Post Types</a>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=jet-engine' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Listings</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- TARJETA WOOCOMMERCE (Solo si WooCommerce está activo) -->
				<?php if ( $woo_active && ( ! isset( $settings['db_card_woo'] ) || '1' === $settings['db_card_woo'] ) ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title" style="color: #7f54b3;">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="color: #7f54b3;">
									<path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 100-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" />
								</svg>
								Tienda WooCommerce
							</h4>
							<div class="wpat-db-card-value">
								<?php echo intval( $total_products ); ?>
								<span style="font-size: 14px; font-weight: normal; color: #64748b;">productos</span>
							</div>
							<p class="wpat-db-card-desc">
								<?php if ( $outofstock_products > 0 ) : ?>
									Hay <strong style="color: #ef4444; font-weight: 700;"><?php echo intval( $outofstock_products ); ?> productos agotados</strong> sin existencias actualmente.
								<?php else : ?>
									¡Catálogo al día! Todos los productos de la tienda tienen existencias disponibles.
								<?php endif; ?>
							</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">+ Añadir Producto</a>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary" style="border-color: #7f54b3; color: #7f54b3;">Ver Catálogo</a>
						</div>
					</div>
				<?php endif; ?>

				<!-- TARJETA BIBLIOTECA DE MEDIOS -->
				<?php if ( ! isset( $settings['db_card_media'] ) || '1' === $settings['db_card_media'] ) : ?>
					<div class="wpat-db-card">
						<div>
							<h4 class="wpat-db-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
									<path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
								</svg>
								Biblioteca de Medios
							</h4>
							<div class="wpat-db-card-value">
								<?php echo intval( $total_media ); ?>
								<span style="font-size: 14px; font-weight: normal; color: #64748b;">archivos</span>
							</div>
							<p class="wpat-db-card-desc">
								<?php if ( isset( $settings['image-optimizer'] ) && '1' === $settings['image-optimizer'] ) : ?>
									Optimización activa. Las imágenes se convierten a WebP para acelerar la velocidad de carga.
								<?php else : ?>
									Optimización inactiva. Habilita la conversión a WebP en la pestaña Medios para ahorrar espacio.
								<?php endif; ?>
							</p>
						</div>
						<div class="wpat-db-actions">
							<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="wpat-db-btn wpat-db-btn-primary">Biblioteca</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit&tab=tab-media' ) ); ?>" class="wpat-db-btn wpat-db-btn-secondary">Ajustes Medios</a>
						</div>
					</div>
				<?php endif; ?>

			</div>

			<!-- 5. SOPORTE DE LA AGENCIA (Formulario de Contacto & Info del Sistema) -->
			<div class="wpat-db-support-card">
				<h3>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
						<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.555-1.555A7.962 7.962 0 0116 10zm-9.965 5.085a7.977 7.977 0 01-2.22-2.22l1.555-1.554a3.997 3.997 0 002.22 2.22l-1.555 1.554zm9.18-9.18a7.978 7.978 0 012.22 2.22l-1.555 1.554a3.997 3.997 0 00-2.22-2.22l1.555-1.554zM2 10c0-.993.24-1.93.668-2.755l1.524 1.525a3.997 3.997 0 00-.078 2.183L2.56 12.508A7.962 7.962 0 012 10zm7 3a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
					</svg>
					Soporte y Gestión
				</h3>
				<div style="display: flex; gap: 24px; flex-wrap: wrap; margin-top: 15px;">
					<!-- Formulario de Consulta -->
					<div style="flex: 1.2; min-width: 280px;">
						<p style="font-size: 13.5px; line-height: 1.5; color: #475569; margin: 0 0 15px 0;"><?php echo wp_kses_post( nl2br( $support_text ) ); ?></p>
						
						<form id="wpat-support-contact-form" style="background: rgba(255, 255, 255, 0.5); padding: 15px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.05);">
							<?php wp_nonce_field( 'wpat_dashboard_support_nonce', 'wpat_support_security' ); ?>
							
							<div style="margin-bottom: 10px;">
								<label for="wpat_support_client_email" style="display:block; font-size:11.5px; font-weight:600; color:#475569; margin-bottom:4px;">Tu Correo Electrónico de Contacto</label>
								<input type="email" id="wpat_support_client_email" name="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px; height: 32px; box-sizing: border-box;" />
							</div>
							
							<div style="margin-bottom: 10px;">
								<label for="wpat_support_subject" style="display:block; font-size:11.5px; font-weight:600; color:#475569; margin-bottom:4px;">Asunto de la Consulta</label>
								<input type="text" id="wpat_support_subject" name="subject" required placeholder="Ej: Error al intentar editar textos..." style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px; height: 32px; box-sizing: border-box;" />
							</div>
							
							<div style="margin-bottom: 12px;">
								<label for="wpat_support_message" style="display:block; font-size:11.5px; font-weight:600; color:#475569; margin-bottom:4px;">Descripción de la Consulta</label>
								<textarea id="wpat_support_message" name="message" required rows="4" placeholder="Indícanos en qué podemos ayudarte..." style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px; box-sizing: border-box; resize: vertical;"></textarea>
							</div>
							
							<div style="display:flex; justify-content:space-between; align-items:center;">
								<button type="submit" id="wpat-submit-support" class="wpat-db-btn wpat-db-btn-success" style="height: 32px; padding: 0 20px;">Enviar Mensaje</button>
								<span id="wpat-support-status" style="font-size:12.5px; font-weight:600;"></span>
							</div>
						</form>
					</div>
					
					<!-- Información del Sistema a la derecha -->
					<div style="flex: 0.8; min-width: 250px; background: rgba(255, 255, 255, 0.5); padding: 15px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: space-between;">
						<div>
							<h4 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #334155; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 6px;">Información del Entorno</h4>
							<table style="width: 100%; font-size: 12.5px; border-collapse: collapse;">
								<tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
									<td style="padding: 6px 0; color: #64748b; font-weight:500;">Versión de PHP</td>
									<td style="padding: 6px 0; text-align: right; font-weight: 600; color: <?php echo version_compare( PHP_VERSION, '8.0', '<' ) ? '#ea580c' : '#1e293b'; ?>;">
										<?php echo esc_html( PHP_VERSION ); ?>
										<?php if ( version_compare( PHP_VERSION, '8.0', '<' ) ) : ?>
											⚠️
										<?php endif; ?>
									</td>
								</tr>
								<tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
									<td style="padding: 6px 0; color: #64748b; font-weight:500;">Versión de WordPress</td>
									<td style="padding: 6px 0; text-align: right; font-weight: 600; color: #1e293b;">
										<?php echo esc_html( get_bloginfo( 'version' ) ); ?>
									</td>
								</tr>
								<tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
									<td style="padding: 6px 0; color: #64748b; font-weight:500;">Tema Activo</td>
									<td style="padding: 6px 0; text-align: right; font-weight: 600; color: #1e293b; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
										<?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?>
									</td>
								</tr>
								<tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
									<td style="padding: 6px 0; color: #64748b; font-weight:500;">Límite de Memoria</td>
									<td style="padding: 6px 0; text-align: right; font-weight: 600; color: #1e293b;">
										<?php echo esc_html( WP_MEMORY_LIMIT ); ?>
									</td>
								</tr>
								<tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
									<td style="padding: 6px 0; color: #64748b; font-weight:500;">Peso Archivos (wp-content)</td>
									<td style="padding: 6px 0; text-align: right; font-weight: 600; color: #1e293b;">
										<?php echo esc_html( $weight_data['files'] ); ?>
									</td>
								</tr>
								<tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
									<td style="padding: 6px 0; color: #64748b; font-weight:500;">Peso Base de Datos</td>
									<td style="padding: 6px 0; text-align: right; font-weight: 600; color: #1e293b;">
										<?php echo esc_html( $weight_data['db'] ); ?>
									</td>
								</tr>
								<tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
									<td style="padding: 6px 0; color: #64748b; font-weight:500;">Peso Total del Sitio</td>
									<td style="padding: 6px 0; text-align: right; font-weight: 700; color: #3b82f6;">
										<?php echo esc_html( $weight_data['total'] ); ?>
									</td>
								</tr>
							</table>
							<div style="margin-top: 8px; font-size: 11px; color: #94a3b8; text-align: right; font-style: italic;">
								Último cálculo de peso: <?php echo esc_html( date_i18n( 'H:i', $weight_data['timestamp'] ) ); ?> hs (Caché de 12h)
							</div>
						</div>
						
						<!-- Enlace a la configuración del plugin (Solo Administradores) -->
						<?php if ( current_user_can( 'manage_options' ) ) : ?>
							<div style="margin-top: 15px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 12px; text-align: center;">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-agency-toolkit' ) ); ?>" style="font-size: 11.5px; font-weight: 700; color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
									⚙️ Configurar WP Agency Toolkit
								</a>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Renderiza el panel lateral deslizante para notificaciones de plugins (desde el footer).
	 */
	public function render_notifications_panel() {
		// Contar notificaciones capturadas de otros plugins
		$notice_count = 0;
		if ( ! empty( $this->captured_notices ) ) {
			$notice_count += preg_match_all( '/class="[^"]*(notice|update-nag|error|warning|updated)[^"]*"/', $this->captured_notices );
		}

		$current_user = wp_get_current_user();
		?>
		<!-- PANEL LATERAL DE NOTIFICACIONES CAPTURADAS -->
		<div id="wpat-notices-panel" style="position: fixed; top: 0; right: -420px; width: 400px; height: 100vh; background: #fff; box-shadow: -5px 0 25px rgba(0,0,0,0.15); z-index: 999999; transition: right 0.3s ease; display: flex; flex-direction: column;">
			<div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
				<h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 18px; height: 18px; color: #ea580c;">
						<path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
					</svg>
					Alertas de Plugins (<?php echo intval( $notice_count ); ?>)
				</h3>
				<button id="wpat-close-notices" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; padding: 0; line-height: 1; transition: color 0.15s ease;">&times;</button>
			</div>
			<div style="padding: 20px; overflow-y: auto; flex: 1; background: #f8fafc;" class="wpat-captured-notices-container">
				<?php if ( $notice_count > 0 ) : ?>
					<?php echo $this->captured_notices; ?>
				<?php else : ?>
					<div style="text-align: center; padding: 40px 20px; color: #64748b;">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 48px; height: 48px; color: #10b981; margin: 0 auto 15px auto; display: block;">
							<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
						</svg>
						<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 700; color: #334155;">¡Panel optimizado y limpio!</h4>
						<p style="margin: 0; font-size: 12px; line-height: 1.5;">No hay notificaciones de otros plugins que requieran tu atención.</p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<!-- Capa de fondo para cerrar el panel -->
		<div id="wpat-notices-overlay" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.35); z-index: 999998; display: none; backdrop-filter: blur(1px);"></div>

		<!-- SCRIPTS PRINCIPALES (Reloj, Inyección de campana, Notificaciones, Formulario AJAX y Actualizaciones AJAX) -->
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				// 1. Reloj Digital
				function updateWpatClock() {
					var now = new Date();
					var hours = String(now.getHours()).padStart(2, '0');
					var minutes = String(now.getMinutes()).padStart(2, '0');
					var seconds = String(now.getSeconds()).padStart(2, '0');
					var clockElement = document.getElementById('wpat-live-clock');
					if (clockElement) {
						clockElement.textContent = hours + ':' + minutes + ':' + seconds;
					}
				}
				setInterval(updateWpatClock, 1000);
				updateWpatClock();

				// 2. Inyección dinámica del botón/estado de notificaciones en la cabecera (Puesto a la izquierda de la hora)
				var noticesContainer = document.getElementById('wpat-header-notices-container');
				var panelTitle = document.querySelector('#wpat-notices-panel h3');
				var container = document.querySelector('.wpat-captured-notices-container');

				var realNoticeCount = 0;
				if (container) {
					// Filtrar notificaciones fantasma (vacías, de cierre, scripts o de carga de plugins)
					var items = container.querySelectorAll('.notice, .update-nag, .error, .warning, .updated');
					items.forEach(function(item) {
						if (item.classList.contains('notice-dismiss')) {
							return;
						}
						// Eliminar si está oculta
						if (item.classList.contains('hidden') || item.classList.contains('inline') || item.style.display === 'none') {
							item.remove();
							return;
						}
						
						// Clonar para limpiar elementos invisibles que inflan falsamente el textContent (scripts, styles, inputs hidden)
						var clone = item.cloneNode(true);
						var invisibleTags = clone.querySelectorAll('script, style, input[type="hidden"]');
						invisibleTags.forEach(function(tag) {
							tag.remove();
						});
						
						var text = clone.textContent.trim();
						if (text === '') {
							item.remove();
							return;
						}
						// Es un aviso visible real!
						realNoticeCount++;
					});
				}

				if (noticesContainer) {
					if (realNoticeCount > 0) {
						noticesContainer.innerHTML = `
							<button id="wpat-toggle-notices" class="wpat-db-btn wpat-db-btn-warning" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; height: 32px; border-radius: 6px; font-weight:600; font-size:12.5px; margin: 0;">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px;">
									<path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
								</svg>
								Notificaciones (${realNoticeCount})
							</button>
						`;
					} else {
						noticesContainer.innerHTML = `
							<button id="wpat-toggle-notices" class="wpat-db-btn wpat-db-btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; height: 32px; border-radius: 6px; font-weight:600; font-size:12.5px; margin: 0; background: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.4); color: #475569;">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; color: #64748b;">
									<path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
								</svg>
								Notificaciones (0)
							</button>
							<div class="wpat-db-time-card" style="background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.2); color: #047857; margin-left: 10px; height:32px; box-sizing:border-box;">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; color: #10b981;">
									<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
								</svg>
								<span>Panel limpio y optimizado</span>
							</div>
						`;

						if (container) {
							container.innerHTML = `
								<div style="text-align: center; padding: 40px 20px; color: #64748b;">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 48px; height: 48px; color: #10b981; margin: 0 auto 15px auto; display: block;">
										<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
									</svg>
									<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 700; color: #334155;">¡Panel optimizado y limpio!</h4>
									<p style="margin: 0; font-size: 12px; line-height: 1.5;">No hay notificaciones de otros plugins que requieran tu atención.</p>
								</div>
							`;
						}
					}
				}

				if (panelTitle) {
					panelTitle.innerHTML = `
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 18px; height: 18px; color: #ea580c;">
							<path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
						</svg>
						Alertas de Plugins (${realNoticeCount})
					`;
				}

				// 3. Control del panel deslizante de notificaciones
				var toggleBtn = document.getElementById('wpat-toggle-notices');
				var closeBtn = document.getElementById('wpat-close-notices');
				var panel = document.getElementById('wpat-notices-panel');
				var overlay = document.getElementById('wpat-notices-overlay');

				if (toggleBtn && panel && overlay) {
					toggleBtn.addEventListener('click', function() {
						panel.style.right = '0';
						overlay.style.display = 'block';
					});
				}

				// Event delegation para clics dinámicos en la campana
				document.addEventListener('click', function(e) {
					var target = e.target;
					var button = target.closest('#wpat-toggle-notices');
					if (button && panel && overlay) {
						panel.style.right = '0';
						overlay.style.display = 'block';
					}
				});

				function closeNoticesPanel() {
					if (panel && overlay) {
						panel.style.right = '-420px';
						overlay.style.display = 'none';
					}
				}

				if (closeBtn) {
					closeBtn.addEventListener('click', closeNoticesPanel);
				}
				if (overlay) {
					overlay.addEventListener('click', closeNoticesPanel);
				}

				// 4. Envío del Formulario de Soporte vía AJAX (Vanilla JS)
				var supportForm = document.getElementById('wpat-support-contact-form');
				var supportStatus = document.getElementById('wpat-support-status');
				var supportSubmit = document.getElementById('wpat-submit-support');

				if (supportForm) {
					supportForm.addEventListener('submit', function(e) {
						e.preventDefault();
						
						supportStatus.textContent = 'Enviando consulta...';
						supportStatus.style.color = '#64748b';
						supportSubmit.disabled = true;

						var formData = new FormData(supportForm);
						formData.append('action', 'wpat_send_dashboard_support_email');

						// Convertir FormData a URLSearchParams para post estándar urlencoded
						var params = new URLSearchParams();
						for (var pair of formData.entries()) {
							params.append(pair[0], pair[1]);
						}

						var xhr = new XMLHttpRequest();
						xhr.open('POST', ajaxurl, true);
						xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
						xhr.onload = function() {
							if (xhr.status === 200) {
								try {
									var res = JSON.parse(xhr.responseText);
									if (res.success) {
										supportStatus.textContent = res.data.message;
										supportStatus.style.color = '#10b981';
										supportForm.reset();
										// Conservar el email del cliente
										var emailInput = document.getElementById('wpat_support_client_email');
										if (emailInput) {
											emailInput.value = '<?php echo esc_js( $current_user->user_email ); ?>';
										}
									} else {
										supportStatus.textContent = res.data.message;
										supportStatus.style.color = '#ef4444';
									}
								} catch(err) {
									supportStatus.textContent = 'Error al procesar la respuesta del servidor.';
									supportStatus.style.color = '#ef4444';
								}
							} else {
								supportStatus.textContent = 'Error en la conexión con el servidor.';
								supportStatus.style.color = '#ef4444';
							}
							supportSubmit.disabled = false;
						};
						xhr.send(params.toString());
					});
				}

				// 5. Actualización AJAX en lote de Plugins y Temas (Con modal de confirmación premium)
				var updateButtons = document.querySelectorAll('.wpat-ajax-update-btn');
				updateButtons.forEach(function(btn) {
					btn.addEventListener('click', function() {
						var type = btn.getAttribute('data-type');
						var nonce = btn.getAttribute('data-nonce');
						var list = JSON.parse(btn.getAttribute('data-list'));
						
						if (!list || list.length === 0) return;
						
						var actionsContainer = btn.parentElement;
						
						// Mostrar modal de confirmación premium
						var modalOverlay = document.createElement('div');
						modalOverlay.id = 'wpat-confirm-modal-overlay';
						modalOverlay.style = 'position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:9999999; display:flex; align-items:center; justify-content:center;';
						
						var itemsListHTML = '<ul style="margin: 0; padding: 0 0 0 20px; text-align: left; font-size: 13px; color: #475569; line-height: 1.6;">';
						list.forEach(function(item) {
							var humanName = item.slug.replace('-', ' ').replace(/\b\w/g, function(l){ return l.toUpperCase() });
							itemsListHTML += `<li style="margin-bottom:6px; font-weight: 600; color: #1e293b;">${humanName}</li>`;
						});
						itemsListHTML += '</ul>';
						
						var modalHTML = `
							<div style="background:#fff; border-radius:10px; width:450px; max-width:90%; padding:24px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); border:1px solid rgba(0,0,0,0.05); text-align:center; font-family:sans-serif; animation: wpat-scale-in 0.2s ease-out;">
								<div style="display:inline-flex; align-items:center; justify-content:center; width:48px; height:48px; border-radius:50%; background:#fef3c7; color:#d97706; margin-bottom:16px;">
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:24px; height:24px;">
										<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
									</svg>
								</div>
								<h3 style="margin:0 0 8px 0; font-size:16px; font-weight:700; color:#1e293b;">Confirmar Actualización en Lote</h3>
								<p style="margin:0 0 20px 0; font-size:13px; color:#64748b; line-height:1.5;">Estás a punto de actualizar los siguientes ${type === 'plugin' ? 'plugins' : 'temas'}:</p>
								
								<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:16px; max-height:150px; overflow-y:auto; margin-bottom:24px; box-sizing:border-box;">
									${itemsListHTML}
								</div>
								
								<div style="display:flex; gap:12px; justify-content:flex-end;">
									<button id="wpat-modal-cancel" class="wpat-db-btn wpat-db-btn-secondary" style="height:34px; padding:0 16px; margin: 0;">Cancelar</button>
									<button id="wpat-modal-confirm" class="wpat-db-btn wpat-db-btn-warning" style="height:34px; padding:0 20px; font-weight:700; margin: 0;">Actualizar Ahora</button>
								</div>
							</div>
							<style>
								@keyframes wpat-scale-in {
									from { transform: scale(0.95); opacity: 0; }
									to { transform: scale(1); opacity: 1; }
								}
							</style>
						`;
						
						modalOverlay.innerHTML = modalHTML;
						document.body.appendChild(modalOverlay);
						
						modalOverlay.querySelector('#wpat-modal-cancel').addEventListener('click', function() {
							modalOverlay.remove();
						});
						
						modalOverlay.querySelector('#wpat-modal-confirm').addEventListener('click', function() {
							modalOverlay.remove();
							runUpdateProcess();
						});
						
						function runUpdateProcess() {
							actionsContainer.innerHTML = `
								<div class="wpat-progress-wrapper" style="width: 100%; margin-top: 10px; font-family: sans-serif;">
									<div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #475569; margin-bottom: 6px; font-weight:600;">
										<span class="wpat-progress-label">Iniciando actualización...</span>
										<span class="wpat-progress-percent">0%</span>
									</div>
									<div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; border: 1px solid rgba(0,0,0,0.03);">
										<div class="wpat-progress-bar" style="width: 0%; height: 100%; background: #3b82f6; transition: width 0.3s ease; border-radius: 3px;"></div>
									</div>
									<div class="wpat-progress-status" style="font-size: 11px; color: #64748b; margin-top: 6px; font-style: italic; display: flex; align-items: center; gap: 4px;">
										<span class="wpat-spinner" style="display: inline-block; width: 10px; height: 10px; border: 2px solid #94a3b8; border-top-color: transparent; border-radius: 50%; animation: wpat-spin 0.6s linear infinite; margin-right:4px;"></span>
										<span>Preparando descargas...</span>
									</div>
								</div>
								<style>
									@keyframes wpat-spin {
										to { transform: rotate(360deg); }
									}
								</style>
							`;
							
							var progressLabel = actionsContainer.querySelector('.wpat-progress-label');
							var progressPercent = actionsContainer.querySelector('.wpat-progress-percent');
							var progressBar = actionsContainer.querySelector('.wpat-progress-bar');
							var progressStatus = actionsContainer.querySelector('.wpat-progress-status');
							
							var currentIndex = 0;
							var totalItems = list.length;
							
							function updateNextItem() {
								if (currentIndex >= totalItems) {
									progressLabel.textContent = type === 'plugin' ? '¡Todos los plugins actualizados!' : '¡Todos los temas actualizados!';
									progressPercent.textContent = '100%';
									progressBar.style.width = '100%';
									progressBar.style.background = '#10b981';
									progressStatus.style.color = '#10b981';
									progressStatus.innerHTML = `
										<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px; color: #10b981; display:inline-block; vertical-align:middle;">
											<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
										</svg>
										<span style="vertical-align:middle; margin-left:4px; font-weight:700;">Recargando el escritorio...</span>
									`;
									
									setTimeout(function() {
										window.location.reload();
									}, 2200);
									return;
								}
								
								var currentItem = list[currentIndex];
								var humanName = currentItem.slug.replace('-', ' ').replace(/\b\w/g, function(l){ return l.toUpperCase() });
								
								progressLabel.textContent = `Actualizando ${humanName} (${currentIndex + 1} de ${totalItems})`;
								var percent = Math.round((currentIndex / totalItems) * 100);
								progressPercent.textContent = percent + '%';
								progressBar.style.width = percent + '%';
								progressStatus.innerHTML = `
									<span class="wpat-spinner" style="display: inline-block; width: 10px; height: 10px; border: 2px solid #3b82f6; border-top-color: transparent; border-radius: 50%; animation: wpat-spin 0.6s linear infinite; margin-right:4px;"></span>
									<span>Descargando y descomprimiendo paquete...</span>
								`;
								
								var xhr = new XMLHttpRequest();
								xhr.open('POST', ajaxurl, true);
								xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
								
								var params = 'action=' + (type === 'plugin' ? 'update-plugin' : 'update-theme') +
								             '&_ajax_nonce=' + encodeURIComponent(nonce) +
								             '&' + (type === 'plugin' ? 'plugin' : 'theme') + '=' + encodeURIComponent(currentItem.file) +
								             '&slug=' + encodeURIComponent(currentItem.slug);
								
								xhr.onload = function() {
									if (xhr.status === 200) {
										try {
											var res = JSON.parse(xhr.responseText);
											if (res.success) {
												currentIndex++;
												updateNextItem();
											} else {
												var errMsg = res.data && res.data.errorMessage ? res.data.errorMessage : 'Error desconocido al instalar.';
												showItemError(humanName, errMsg);
											}
										} catch(e) {
											showItemError(humanName, 'Error de formato en la respuesta.');
										}
									} else {
										showItemError(humanName, 'Error de conexión con el servidor.');
									}
								};
								xhr.send(params);
							}
							
							function showItemError(name, msg) {
								progressLabel.textContent = `Error al actualizar ${name}`;
								progressLabel.style.color = '#ef4444';
								progressBar.style.background = '#ef4444';
								progressStatus.style.color = '#ef4444';
								progressStatus.innerHTML = `
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width: 14px; height: 14px; color: #ef4444; display:inline-block; vertical-align:middle;">
										<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
									</svg>
									<span style="vertical-align:middle; margin-left:4px; font-weight:600;">${msg}</span>
								`;
								
								setTimeout(function() {
									currentIndex++;
									updateNextItem();
								}, 4000);
							}
							
							updateNextItem();
						}
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Obtiene el peso de la base de datos (tablas con prefijo actual).
	 *
	 * @return float
	 */
	private function wpat_get_db_size() {
		global $wpdb;
		$db_size = 0;
		$rows = $wpdb->get_results( "SHOW TABLE STATUS LIKE '" . $wpdb->prefix . "%'", ARRAY_A );
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$db_size += (float) $row['Data_length'] + (float) $row['Index_length'];
			}
		}
		return $db_size;
	}

	/**
	 * Calcula el tamaño de una carpeta recursivamente de forma segura.
	 *
	 * @param string $path
	 * @return float
	 */
	private function wpat_get_folder_size( $path ) {
		$total_size = 0;
		if ( ! is_dir( $path ) ) {
			return 0;
		}
		
		// Usar iterador del sistema para mejor rendimiento
		try {
			$dir = new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS );
			$files = new RecursiveIteratorIterator( $dir, RecursiveIteratorIterator::LEAVES_ONLY );
			foreach ( $files as $file ) {
				$total_size += $file->getSize();
			}
		} catch ( Exception $e ) {
			$total_size = 0;
		}
		
		return $total_size;
	}

	/**
	 * Formatea los bytes a una cadena legible (MB o GB).
	 *
	 * @param float $bytes
	 * @return string
	 */
	private function wpat_format_bytes( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return number_format( $bytes / 1073741824, 2 ) . ' GB';
		}
		return number_format( $bytes / 1048576, 2 ) . ' MB';
	}

	/**
	 * Handler de AJAX para enviar consultas de soporte técnico.
	 */
	public function ajax_send_support_email() {
		// Validar token nonce de seguridad
		check_ajax_referer( 'wpat_dashboard_support_nonce', 'security' );

		$client_email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$subject      = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
		$message      = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

		if ( empty( $client_email ) || empty( $subject ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Por favor, rellena todos los campos del formulario.' ) );
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$to = ! empty( $settings['dashboard_support_email'] ) ? $settings['dashboard_support_email'] : get_option( 'admin_email' );

		$site_name = get_bloginfo( 'name' );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'Reply-To: ' . $client_email,
		);

		$email_subject = '[' . $site_name . ' - Soporte] ' . $subject;
		$email_body = '<h2>Nueva consulta de soporte técnico</h2>';
		$email_body .= '<p><strong>De:</strong> ' . esc_html( $client_email ) . '</p>';
		$email_body .= '<p><strong>Asunto:</strong> ' . esc_html( $subject ) . '</p>';
		$email_body .= '<p><strong>Mensaje:</strong></p>';
		$email_body .= '<div style="background:#f8fafc; padding:15px; border-radius:6px; border:1px solid #cbd5e1; color:#334155; font-family:sans-serif; font-size:14px; line-height:1.5;">' . nl2br( esc_html( $message ) ) . '</div>';

		$sent = wp_mail( $to, $email_subject, $email_body, $headers );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => 'Consulta enviada correctamente.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Error al enviar el correo mediante php mail.' ) );
		}
	}
}

<?php
/**
 * Módulo: Integraciones & Scripts Globales - WP Agency Toolkit
 *
 * Inyección ultra-ligera y optimizada de herramientas de analítica, píxeles de conversión,
 * códigos de verificación de motores de búsqueda, scripts personalizados (Header/Body/Footer)
 * y sincronización con almacenamiento en la nube (Google Drive, Dropbox, OneDrive).
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Integrations {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Integrations|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Integrations
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
		// Inyección en la cabecera (<head>) con prioridad 1 (alta)
		add_action( 'wp_head', array( $this, 'inject_header_codes' ), 1 );

		// Inyección en la apertura del <body> (WordPress 5.2+)
		add_action( 'wp_body_open', array( $this, 'inject_body_open_codes' ), 1 );

		// Inyección antes del cierre del </body>
		add_action( 'wp_footer', array( $this, 'inject_footer_codes' ), 99 );
	}

	/**
	 * Comprueba si el rastreo debe omitirse (por ejemplo, si se excluye a los administradores).
	 *
	 * @param array $settings
	 * @return bool
	 */
	private function should_exclude_tracking( $settings ) {
		if ( is_admin() ) {
			return true;
		}

		if ( ! empty( $settings['integrations_exclude_admins'] ) && '1' === (string) $settings['integrations_exclude_admins'] ) {
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Inyecta las etiquetas de verificación, analítica, píxeles y scripts personalizados en <head>.
	 */
	public function inject_header_codes() {
		if ( is_admin() ) {
			return;
		}

		$settings         = WPAT_Main::get_instance()->get_settings();
		$exclude_tracking = $this->should_exclude_tracking( $settings );

		echo "\n<!-- WP Agency Toolkit: Integraciones & Scripts - Head -->\n";

		// ==========================================
		// 1. VERIFICACIONES DE SITIO / BUSCADORES
		// ==========================================

		// A. Google Search Console Verification Meta Tag
		if ( ! empty( $settings['google_search_console_code'] ) ) {
			$code = $settings['google_search_console_code'];
			if ( preg_match( '/content=["\']([^"\']+)["\']/i', $code, $matches ) ) {
				$code = $matches[1];
			}
			echo '<meta name="google-site-verification" content="' . esc_attr( trim( $code ) ) . '" />' . "\n";
		}

		// B. Bing / Microsoft Webmaster Verification
		if ( ! empty( $settings['bing_verification_code'] ) ) {
			$bing_code = $settings['bing_verification_code'];
			if ( preg_match( '/content=["\']([^"\']+)["\']/i', $bing_code, $matches ) ) {
				$bing_code = $matches[1];
			}
			echo '<meta name="msvalidate.01" content="' . esc_attr( trim( $bing_code ) ) . '" />' . "\n";
		}

		// C. Pinterest Domain Verification
		if ( ! empty( $settings['pinterest_verification_code'] ) ) {
			$pin_code = $settings['pinterest_verification_code'];
			if ( preg_match( '/content=["\']([^"\']+)["\']/i', $pin_code, $matches ) ) {
				$pin_code = $matches[1];
			}
			echo '<meta name="p:domain_verify" content="' . esc_attr( trim( $pin_code ) ) . '" />' . "\n";
		}

		// ==========================================
		// 2. HERRAMIENTAS DE ANALÍTICA & PÍXELES
		// ==========================================

		if ( ! $exclude_tracking ) {

			// A. Google Analytics (GA4) Tracking Script
			if ( ! empty( $settings['google_analytics_id'] ) ) {
				$ga_id = trim( sanitize_text_field( $settings['google_analytics_id'] ) );
				if ( preg_match( '/^G-[A-Z0-9]+$/i', $ga_id ) ) {
					?>
					<!-- Google Analytics (gtag.js) - WP Agency Toolkit -->
					<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga_id ); ?>"></script>
					<script>
						window.dataLayer = window.dataLayer || [];
						function gtag(){dataLayer.push(arguments);}
						gtag('js', new Date());
						gtag('config', '<?php echo esc_js( $ga_id ); ?>');
					</script>
					<?php
				}
			}

			// B. Google Tag Manager (GTM) - Head Container
			if ( ! empty( $settings['gtm_container_id'] ) ) {
				$gtm_id = trim( sanitize_text_field( $settings['gtm_container_id'] ) );
				if ( preg_match( '/^GTM-[A-Z0-9]+$/i', $gtm_id ) ) {
					?>
					<!-- Google Tag Manager - WP Agency Toolkit -->
					<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
					new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
					j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
					'https://www.googletagmanager.com/gtag.js?id='+i+dl;f.parentNode.insertBefore(j,f);
					})(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
					<!-- End Google Tag Manager -->
					<?php
				}
			}

			// C. Meta / Facebook Pixel
			if ( ! empty( $settings['facebook_pixel_id'] ) ) {
				$fb_id = trim( sanitize_text_field( $settings['facebook_pixel_id'] ) );
				if ( preg_match( '/^[0-9]+$/', $fb_id ) ) {
					?>
					<!-- Meta Pixel Code - WP Agency Toolkit -->
					<script>
					!function(f,b,e,v,n,t,s)
					{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
					n.callMethod.apply(n,arguments):n.queue.push(arguments)};
					if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
					n.queue=[];t=b.createElement(e);t.async=!0;
					t.src=v;s=b.getElementsByTagName(e)[0];
					s.parentNode.insertBefore(t,s)}(window, document,'script',
					'https://connect.facebook.net/en_US/fbevents.js');
					fbq('init', '<?php echo esc_js( $fb_id ); ?>');
					fbq('track', 'PageView');
					</script>
					<!-- End Meta Pixel Code -->
					<?php
				}
			}

			// D. Microsoft Clarity
			if ( ! empty( $settings['clarity_project_id'] ) ) {
				$clarity_id = trim( sanitize_text_field( $settings['clarity_project_id'] ) );
				if ( preg_match( '/^[a-z0-9]+$/i', $clarity_id ) ) {
					?>
					<!-- Microsoft Clarity - WP Agency Toolkit -->
					<script type="text/javascript">
					(function(c,l,a,r,i,t,y){
						c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
						t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
						y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
					})(window, document, "clarity", "script", "<?php echo esc_js( $clarity_id ); ?>");
					</script>
					<!-- End Microsoft Clarity -->
					<?php
				}
			}

			// E. TikTok Pixel
			if ( ! empty( $settings['tiktok_pixel_id'] ) ) {
				$tiktok_id = trim( sanitize_text_field( $settings['tiktok_pixel_id'] ) );
				if ( preg_match( '/^[A-Z0-9]+$/i', $tiktok_id ) ) {
					?>
					<!-- TikTok Pixel - WP Agency Toolkit -->
					<script>
					!function (w, d, t) {
						w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
						ttq.load('<?php echo esc_js( $tiktok_id ); ?>');
						ttq.page();
					}(window, document, 'ttq');
					</script>
					<!-- End TikTok Pixel -->
					<?php
				}
			}

			// F. Pinterest Tag
			if ( ! empty( $settings['pinterest_tag_id'] ) ) {
				$pinterest_id = trim( sanitize_text_field( $settings['pinterest_tag_id'] ) );
				if ( preg_match( '/^[0-9]+$/', $pinterest_id ) ) {
					?>
					<!-- Pinterest Tag - WP Agency Toolkit -->
					<script>
					!function(e){if(!window.pintrk){window.pintrk = function () {
					window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var
					  n=window.pintrk;n.queue=[],n.version="3.0";var
					  t=document.createElement("script");t.async=!0,t.src=e;var
					  r=document.getElementsByTagName("script")[0];
					  r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");
					pintrk('load', '<?php echo esc_js( $pinterest_id ); ?>');
					pintrk('page');
					</script>
					<noscript>
					<img height="1" width="1" style="display:none;" alt="" src="https://ct.pinterest.com/v3/?event=init&tid=<?php echo esc_attr( $pinterest_id ); ?>&noscript=1" />
					</noscript>
					<!-- End Pinterest Tag -->
					<?php
				}
			}
		}

		// ==========================================
		// 3. SCRIPTS PERSONALIZADOS EN CABECERA (<HEAD>)
		// ==========================================
		if ( ! empty( $settings['header_custom_scripts'] ) ) {
			echo "\n<!-- Custom Header Scripts - WP Agency Toolkit -->\n";
			echo $settings['header_custom_scripts'] . "\n";
		}

		echo "<!-- /WP Agency Toolkit: Integraciones & Scripts - Head -->\n\n";
	}

	/**
	 * Inyecta los códigos en la apertura de <body> (vía wp_body_open).
	 */
	public function inject_body_open_codes() {
		if ( is_admin() ) {
			return;
		}

		$settings         = WPAT_Main::get_instance()->get_settings();
		$exclude_tracking = $this->should_exclude_tracking( $settings );

		echo "\n<!-- WP Agency Toolkit: Integraciones & Scripts - Body Open -->\n";

		if ( ! $exclude_tracking ) {
			// A. Google Tag Manager (GTM) - Fallback NoScript
			if ( ! empty( $settings['gtm_container_id'] ) ) {
				$gtm_id = trim( sanitize_text_field( $settings['gtm_container_id'] ) );
				if ( preg_match( '/^GTM-[A-Z0-9]+$/i', $gtm_id ) ) {
					?>
					<!-- Google Tag Manager (noscript) - WP Agency Toolkit -->
					<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
					height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
					<!-- End Google Tag Manager (noscript) -->
					<?php
				}
			}

			// B. Meta Pixel NoScript
			if ( ! empty( $settings['facebook_pixel_id'] ) ) {
				$fb_id = trim( sanitize_text_field( $settings['facebook_pixel_id'] ) );
				if ( preg_match( '/^[0-9]+$/', $fb_id ) ) {
					?>
					<noscript><img height="1" width="1" style="display:none"
					src="https://www.facebook.com/tr?id=<?php echo esc_attr( $fb_id ); ?>&ev=PageView&noscript=1"
					/></noscript>
					<?php
				}
			}
		}

		// C. Scripts Personalizados en Apertura de Body
		if ( ! empty( $settings['body_custom_scripts'] ) ) {
			echo "\n<!-- Custom Body Open Scripts - WP Agency Toolkit -->\n";
			echo $settings['body_custom_scripts'] . "\n";
		}

		echo "<!-- /WP Agency Toolkit: Integraciones & Scripts - Body Open -->\n\n";
	}

	/**
	 * Inyecta los códigos antes del cierre del </body> (vía wp_footer).
	 */
	public function inject_footer_codes() {
		if ( is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// Scripts Personalizados en Pie de Página
		if ( ! empty( $settings['footer_custom_scripts'] ) ) {
			echo "\n<!-- WP Agency Toolkit: Custom Footer Scripts -->\n";
			echo $settings['footer_custom_scripts'] . "\n";
			echo "<!-- /WP Agency Toolkit: Custom Footer Scripts -->\n\n";
		}
	}

	/**
	 * Sincroniza y sube un archivo adjunto a las nubes configuradas.
	 *
	 * @param string $file_path Ruta absoluta local del archivo.
	 * @param string $file_name Nombre del archivo.
	 * @return array Resultados de cada servicio de nube.
	 */
	public function sync_file_to_cloud( $file_path, $file_name = '' ) {
		if ( ! file_exists( $file_path ) ) {
			return array();
		}

		if ( empty( $file_name ) ) {
			$file_name = basename( $file_path );
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$results  = array();

		// 1. Google Drive
		if ( ! empty( $settings['google_drive_token'] ) ) {
			$results['google_drive'] = $this->upload_to_google_drive( $file_path, $file_name, $settings['google_drive_token'], isset( $settings['google_drive_folder'] ) ? $settings['google_drive_folder'] : '' );
		}

		// 2. Dropbox
		if ( ! empty( $settings['dropbox_token'] ) ) {
			$results['dropbox'] = $this->upload_to_dropbox( $file_path, $file_name, $settings['dropbox_token'] );
		}

		// 3. OneDrive
		if ( ! empty( $settings['onedrive_token'] ) ) {
			$results['onedrive'] = $this->upload_to_onedrive( $file_path, $file_name, $settings['onedrive_token'] );
		}

		return $results;
	}

	/**
	 * Sube un archivo a Google Drive mediante API REST v3.
	 */
	private function upload_to_google_drive( $file_path, $file_name, $token, $folder_id = '' ) {
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			return false;
		}

		$mime_type = function_exists( 'mime_content_type' ) ? mime_content_type( $file_path ) : 'application/octet-stream';
		if ( ! $mime_type ) {
			$mime_type = 'application/octet-stream';
		}
		$boundary = '---------------------------' . microtime( true );

		$metadata = array(
			'name' => $file_name,
		);
		if ( ! empty( $folder_id ) ) {
			$metadata['parents'] = array( $folder_id );
		}

		$body  = "--{$boundary}\r\n";
		$body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
		$body .= wp_json_encode( $metadata ) . "\r\n";
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Type: {$mime_type}\r\n\r\n";
		$body .= $file_content . "\r\n";
		$body .= "--{$boundary}--\r\n";

		$response = wp_remote_post( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . trim( $token ),
				'Content-Type'  => 'multipart/related; boundary=' . $boundary,
			),
			'body'    => $body,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Sube un archivo a Dropbox mediante API v2.
	 */
	private function upload_to_dropbox( $file_path, $file_name, $token ) {
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			return false;
		}

		$api_args = array(
			'path'            => '/' . ltrim( $file_name, '/' ),
			'mode'            => 'add',
			'autorename'      => true,
			'mute'            => false,
			'strict_conflict' => false,
		);

		$response = wp_remote_post( 'https://content.dropboxapi.com/2/files/upload', array(
			'headers' => array(
				'Authorization'   => 'Bearer ' . trim( $token ),
				'Dropbox-API-Arg' => wp_json_encode( $api_args ),
				'Content-Type'    => 'application/octet-stream',
			),
			'body'    => $file_content,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Sube un archivo a Microsoft OneDrive mediante API Microsoft Graph.
	 */
	private function upload_to_onedrive( $file_path, $file_name, $token ) {
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			return false;
		}

		$url       = 'https://graph.microsoft.com/v1.0/me/drive/root:/' . rawurlencode( $file_name ) . ':/content';
		$mime_type = function_exists( 'mime_content_type' ) ? mime_content_type( $file_path ) : 'application/octet-stream';
		if ( ! $mime_type ) {
			$mime_type = 'application/octet-stream';
		}

		$response = wp_remote_request( $url, array(
			'method'  => 'PUT',
			'headers' => array(
				'Authorization' => 'Bearer ' . trim( $token ),
				'Content-Type'  => $mime_type,
			),
			'body'    => $file_content,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}
}

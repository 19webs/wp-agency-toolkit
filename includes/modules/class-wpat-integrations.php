<?php
/**
 * Módulo: Integraciones de Terceros - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Integrations {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Integrations
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
		// Inyección de códigos en la cabecera de la web pública (prioridad alta 1)
		add_action( 'wp_head', array( $this, 'inject_integration_codes' ), 1 );
	}

	/**
	 * Inyecta las etiquetas de Search Console y los scripts de Google Analytics en el frontend.
	 */
	public function inject_integration_codes() {
		// Evitar inyección en paneles de administración
		if ( is_admin() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();

		// 1. Google Search Console Verification Meta Tag
		if ( ! empty( $settings['google_search_console_code'] ) ) {
			$code = $settings['google_search_console_code'];
			
			// Si el usuario pegó la etiqueta HTML completa, extraer solo el valor del atributo content
			if ( preg_match( '/content=["\']([^"\']+)["\']/i', $code, $matches ) ) {
				$code = $matches[1];
			}
			
			echo '<!-- Google Search Console Verification - WP Agency Toolkit -->' . "\n";
			echo '<meta name="google-site-verification" content="' . esc_attr( trim( $code ) ) . '" />' . "\n";
		}

		// 2. Google Analytics (GA4) Tracking Script
		if ( ! empty( $settings['google_analytics_id'] ) ) {
			$ga_id = trim( sanitize_text_field( $settings['google_analytics_id'] ) );
			
			// Validar formato estándar de GA4 (comienza con G- seguido de caracteres alfanuméricos)
			if ( preg_match( '/^G-[A-Z0-9]+$/i', $ga_id ) ) {
				?>
				<!-- Google Analytics (gtag.js) - WP Agency Toolkit -->
				<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga_id ); ?>"></script>
				<script>
					window.dataLayer = window.dataLayer || [];
					function gtag(){dataLayer.push(arguments);}
					gtag('js', new Date());
					gtag('config', '<?php echo esc_attr( $ga_id ); ?>');
				</script>
				<?php
			}
		}

		// 3. Google Tag Manager (GTM)
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

		// 4. Meta / Facebook Pixel
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
				<noscript><img height="1" width="1" style="display:none"
				src="https://www.facebook.com/tr?id=<?php echo esc_attr( $fb_id ); ?>&ev=PageView&noscript=1"
				/></noscript>
				<!-- End Meta Pixel Code -->
				<?php
			}
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
	 * Sube un archivo a Google Drive mediante API.
	 */
	private function upload_to_google_drive( $file_path, $file_name, $token, $folder_id = '' ) {
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			return false;
		}

		$mime_type = mime_content_type( $file_path ) ?: 'application/octet-stream';
		$boundary  = '---------------------------' . microtime( true );

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
				'Authorization' => 'Bearer ' . $token,
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
	 * Sube un archivo a Dropbox mediante API.
	 */
	private function upload_to_dropbox( $file_path, $file_name, $token ) {
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			return false;
		}

		$api_args = array(
			'path'            => '/' . $file_name,
			'mode'            => 'add',
			'autorename'      => true,
			'mute'            => false,
			'strict_conflict' => false,
		);

		$response = wp_remote_post( 'https://content.dropboxapi.com/2/files/upload', array(
			'headers' => array(
				'Authorization'   => 'Bearer ' . $token,
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
	 * Sube un archivo a Microsoft OneDrive mediante API.
	 */
	private function upload_to_onedrive( $file_path, $file_name, $token ) {
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			return false;
		}

		$url       = 'https://graph.microsoft.com/v1.0/me/drive/root:/' . rawurlencode( $file_name ) . ':/content';
		$mime_type = mime_content_type( $file_path ) ?: 'application/octet-stream';

		$response = wp_remote_request( $url, array(
			'method'  => 'PUT',
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
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

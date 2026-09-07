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

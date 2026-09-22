<?php
/**
 * Módulo: Soporte, Sanitización y Renderizado de SVG - WP Agency Toolkit
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_SVG_Support {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_SVG_Support|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_SVG_Support
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
		// 1. Permitir tipos MIME de SVG
		add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );

		// 2. Validar extensión correcta de archivo SVG
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'validate_svg_extension' ), 10, 4 );

		// 3. Sanitizar el SVG en la subida temporal (Seguridad Anti-XSS y Anti-XXE)
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg_upload' ) );

		// 4. Generar metadatos y dimensiones para la biblioteca de medios
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_svg_metadata' ), 10, 2 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_svg_for_js' ), 10, 3 );

		// 5. Inyectar estilos CSS para previsualización nítida en el panel de medios
		add_action( 'admin_head', array( $this, 'inject_svg_admin_styles' ) );
	}

	/**
	 * Agrega la extensión SVG a la lista de tipos mime permitidos si el usuario cumple los permisos configurados.
	 *
	 * @param array $mimes Mimes existentes.
	 * @return array
	 */
	public function allow_svg_upload( $mimes ) {
		$settings   = WPAT_Main::get_instance()->get_settings();
		$admin_only = ! isset( $settings['svg_admin_only'] ) || '1' === (string) $settings['svg_admin_only'];

		if ( $admin_only && ! current_user_can( 'administrator' ) && ! current_user_can( 'manage_options' ) ) {
			return $mimes;
		}

		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		return $mimes;
	}

	/**
	 * Corrige las comprobaciones de extensión de archivo de WordPress para archivos SVG.
	 *
	 * @param array       $data     Datos del tipo de archivo comprobado.
	 * @param string      $file     Ruta temporal.
	 * @param string      $filename Nombre del archivo.
	 * @param array|null  $mimes    Mimes permitidos.
	 * @return array
	 */
	public function validate_svg_extension( $data, $file, $filename, $mimes ) {
		if ( empty( $data['ext'] ) || empty( $data['type'] ) ) {
			$filetype = wp_check_filetype( $filename, $mimes );
			$ext      = $filetype['ext'];
			$type     = $filetype['type'];

			if ( 'svg' === $ext || 'svgz' === $ext ) {
				$data['ext']  = $ext;
				$data['type'] = 'image/svg+xml';
			}
		}
		return $data;
	}

	/**
	 * Intercepta la subida de SVG y ejecuta la sanitización estricta en el archivo temporal.
	 *
	 * @param array $file Parámetros del archivo subido ($_FILES item).
	 * @return array
	 */
	public function sanitize_svg_upload( $file ) {
		$filename = isset( $file['name'] ) ? $file['name'] : '';
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'svg' !== $ext && 'svgz' !== $ext ) {
			return $file;
		}

		$settings   = WPAT_Main::get_instance()->get_settings();
		$admin_only = ! isset( $settings['svg_admin_only'] ) || '1' === (string) $settings['svg_admin_only'];

		// Verificar permisos de rol si está restringido a administradores
		if ( $admin_only && ! current_user_can( 'administrator' ) && ! current_user_can( 'manage_options' ) ) {
			$file['error'] = __( 'Por seguridad, la subida de archivos SVG está restringida exclusivamente a administradores.', 'wp-agency-toolkit' );
			return $file;
		}

		$file_path = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $file;
		}

		$is_gzipped = ( 'svgz' === $ext );
		$contents   = $is_gzipped ? @gzdecode( file_get_contents( $file_path ) ) : @file_get_contents( $file_path );

		if ( false === $contents || empty( $contents ) ) {
			$file['error'] = __( 'El archivo SVG subido está vacío o dañado.', 'wp-agency-toolkit' );
			return $file;
		}

		$strict_sanitize = ! isset( $settings['svg_sanitize_strict'] ) || '1' === (string) $settings['svg_sanitize_strict'];
		if ( $strict_sanitize ) {
			$sanitized = $this->sanitize_svg_content( $contents );

			if ( false === $sanitized ) {
				$file['error'] = __( 'El archivo SVG contiene scripts maliciosos o elementos no permitidos por seguridad.', 'wp-agency-toolkit' );
				return $file;
			}

			if ( $is_gzipped ) {
				file_put_contents( $file_path, gzencode( $sanitized ) );
			} else {
				file_put_contents( $file_path, $sanitized );
			}
		}

		return $file;
	}

	/**
	 * Limpia el contenido XML del SVG eliminando XSS, XXE y scripts maliciosos.
	 *
	 * @param string $content XML crudo del SVG.
	 * @return string|false
	 */
	public function sanitize_svg_content( $content ) {
		if ( empty( $content ) || ! is_string( $content ) ) {
			return false;
		}

		// 1. Bloqueo de XML External Entity (XXE) y Billion Laughs (Entity expansions)
		if ( preg_match( '/<!ENTITY/i', $content ) || preg_match( '/<!DOCTYPE[^>]*\[/i', $content ) ) {
			// Remover entidades y DTD maliciosos
			$content = preg_replace( '/<!ENTITY[^>]*>/is', '', $content );
			$content = preg_replace( '/<!DOCTYPE[^>]*>/is', '', $content );
		}

		// 2. Eliminar etiquetas ejecutables peligrosas y su contenido
		$dangerous_tags = array(
			'script',
			'foreignObject',
			'iframe',
			'object',
			'embed',
			'applet',
			'audio',
			'video',
		);

		foreach ( $dangerous_tags as $tag ) {
			$content = preg_replace( '/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', '', $content );
			$content = preg_replace( '/<' . $tag . '\b[^>]*\/>/is', '', $content );
		}

		// 3. Eliminar etiquetas <use> con referencias externas sospechosas
		$content = preg_replace( '/<use\b[^>]*(href|xlink:href)\s*=\s*["\'](http|https|\/\/)[^"\']*["\'][^>]*>/is', '', $content );

		// 4. Eliminar eventos de Javascript inline (ej. onload, onerror, onclick, onmouseover...)
		$content = preg_replace( '/\bon[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $content );

		// 5. Eliminar pseudoprotocolos ejecutables en atributos (javascript:, vbscript:, data:text/html)
		$content = preg_replace( '/(href|xlink:href|src|poster)\s*=\s*["\']\s*(javascript|vbscript|data\s*:\s*text\/html|data\s*:\s*text\/javascript)[^"\']*["\']/i', '', $content );

		// 6. Validar que conserve la estructura <svg ...>
		if ( false === stripos( $content, '<svg' ) || false === stripos( $content, '</svg>' ) ) {
			return false;
		}

		return trim( $content );
	}

	/**
	 * Extrae las dimensiones (ancho, alto o viewBox) de un archivo SVG.
	 *
	 * @param string $file_path Ruta del archivo SVG.
	 * @return array
	 */
	public static function get_svg_dimensions( $file_path ) {
		$dimensions = array(
			'width'  => 300,
			'height' => 300,
		);

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return $dimensions;
		}

		$content = @file_get_contents( $file_path );
		if ( empty( $content ) ) {
			return $dimensions;
		}

		// Buscar atributos width y height en la etiqueta <svg>
		if ( preg_match( '/<svg[^>]*\bwidth\s*=\s*["\']([^"\']+)["\']/i', $content, $m_width ) ) {
			$w = (float) preg_replace( '/[^0-9.]/', '', $m_width[1] );
			if ( $w > 0 ) {
				$dimensions['width'] = round( $w );
			}
		}

		if ( preg_match( '/<svg[^>]*\bheight\s*=\s*["\']([^"\']+)["\']/i', $content, $m_height ) ) {
			$h = (float) preg_replace( '/[^0-9.]/', '', $m_height[1] );
			if ( $h > 0 ) {
				$dimensions['height'] = round( $h );
			}
		}

		// Si no tiene width/height explícitos, extraerlos del viewBox="min-x min-y width height"
		if ( ( 300 === $dimensions['width'] && 300 === $dimensions['height'] ) || empty( $dimensions['width'] ) || empty( $dimensions['height'] ) ) {
			if ( preg_match( '/<svg[^>]*\bviewBox\s*=\s*["\']([^"\']+)["\']/i', $content, $m_viewbox ) ) {
				$parts = preg_split( '/[\s,]+/', trim( $m_viewbox[1] ) );
				if ( count( $parts ) >= 4 ) {
					$vb_w = (float) $parts[2];
					$vb_h = (float) $parts[3];
					if ( $vb_w > 0 && $vb_h > 0 ) {
						$dimensions['width']  = round( $vb_w );
						$dimensions['height'] = round( $vb_h );
					}
				}
			}
		}

		return $dimensions;
	}

	/**
	 * Genera metadatos reales de dimensiones para el adjunto SVG.
	 *
	 * @param array $metadata      Metadatos generados.
	 * @param int   $attachment_id ID del adjunto.
	 * @return array
	 */
	public function generate_svg_metadata( $metadata, $attachment_id ) {
		$mime = get_post_mime_type( $attachment_id );

		if ( 'image/svg+xml' === $mime ) {
			$file = get_attached_file( $attachment_id );
			if ( $file && file_exists( $file ) ) {
				$dimensions = self::get_svg_dimensions( $file );
				if ( ! is_array( $metadata ) ) {
					$metadata = array();
				}
				$metadata['width']  = $dimensions['width'];
				$metadata['height'] = $dimensions['height'];
				$metadata['sizes']  = array(
					'full' => array(
						'file'      => wp_basename( $file ),
						'width'     => $dimensions['width'],
						'height'    => $dimensions['height'],
						'mime-type' => 'image/svg+xml',
					),
				);
			}
		}

		return $metadata;
	}

	/**
	 * Prepara la respuesta JS para la Biblioteca de Medios (Gutenberg / Elementor / Modal).
	 *
	 * @param array   $response   Array de datos JS.
	 * @param WP_Post $attachment Post del adjunto.
	 * @param array   $meta       Metadatos.
	 * @return array
	 */
	public function prepare_svg_for_js( $response, $attachment, $meta ) {
		if ( isset( $response['mime'] ) && 'image/svg+xml' === $response['mime'] ) {
			$file = get_attached_file( $attachment->ID );
			$dim  = self::get_svg_dimensions( $file );

			$response['sizes'] = array(
				'full' => array(
					'url'         => $response['url'],
					'width'       => $dim['width'],
					'height'      => $dim['height'],
					'orientation' => $dim['width'] >= $dim['height'] ? 'landscape' : 'portrait',
				),
			);

			$response['icon']   = $response['url'];
			$response['width']  = $dim['width'];
			$response['height'] = $dim['height'];
		}

		return $response;
	}

	/**
	 * Inyecta reglas CSS en el panel de administración para renderizado y previsualización perfecta de SVGs.
	 */
	public function inject_svg_admin_styles() {
		?>
		<style type="text/css">
			.attachment-preview .thumbnail img[src$=".svg"],
			.attachment-preview .thumbnail img[src*=".svg?"],
			.media-icon img[src$=".svg"],
			.wp-list-table .media-icon img[src$=".svg"],
			.media-frame .attachment .thumbnail img[src$=".svg"],
			.elementor-control-media-preview-container img[src$=".svg"] {
				width: 100% !important;
				height: auto !important;
				max-height: 100% !important;
				object-fit: contain;
			}
			.attachment .thumbnail .centered img[src$=".svg"] {
				transform: translate(-50%, -50%);
			}
		</style>
		<?php
	}
}

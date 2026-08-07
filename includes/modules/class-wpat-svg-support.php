<?php
/**
 * Módulo: Soporte y Sanitización de SVG - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_SVG_Support {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_SVG_Support
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
		// Permitir tipos mime de SVG
		add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );

		// Validar extensión correcta de archivo SVG
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'validate_svg_extension' ), 10, 4 );

		// Sanitizar el SVG en la subida temporal
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg_upload' ) );
	}

	/**
	 * Agrega la extensión SVG a la lista de tipos mime permitidos.
	 *
	 * @param array $mimes Mimes existentes.
	 * @return array
	 */
	public function allow_svg_upload( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Corrige las comprobaciones de extensión de archivo de WordPress para archivos SVG.
	 */
	public function validate_svg_extension( $data, $file, $filename, $mimes ) {
		if ( ! $data['type'] ) {
			$filetype = wp_check_filetype( $filename, $mimes );
			$ext      = $filetype['ext'];
			$type     = $filetype['type'];
			
			if ( 'svg' === $ext || 'svgz' === $ext ) {
				$data['ext']  = $ext;
				$data['type'] = $type;
			}
		}
		return $data;
	}

	/**
	 * Intercepta la subida de SVG y ejecuta la sanitización en el archivo temporal.
	 *
	 * @param array $file Parámetros del archivo subido.
	 * @return array
	 */
	public function sanitize_svg_upload( $file ) {
		if ( isset( $file['type'] ) && 'image/svg+xml' === $file['type'] ) {
			$file_path = $file['tmp_name'];
			
			if ( file_exists( $file_path ) ) {
				$contents = file_get_contents( $file_path );
				if ( false !== $contents ) {
					$sanitized = $this->sanitize_svg_content( $contents );
					file_put_contents( $file_path, $sanitized );
				}
			}
		}
		return $file;
	}

	/**
	 * Limpia el contenido XML del SVG eliminando XSS y scripts maliciosos.
	 *
	 * @param string $content XML crudo del SVG.
	 * @return string
	 */
	private function sanitize_svg_content( $content ) {
		// 1. Eliminar etiquetas <script> y su contenido
		$content = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $content );

		// 2. Eliminar etiquetas <foreignObject> que pueden ocultar scripts/HTML intrusivo
		$content = preg_replace( '/<foreignObject\b[^>]*>(.*?)<\/foreignObject>/is', '', $content );

		// 3. Eliminar eventos de Javascript inline (ej. onload, onclick, onmouseover...)
		$content = preg_replace( '/\bon[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $content );

		// 4. Eliminar enlaces javascript: en etiquetas href o xlink:href
		$content = preg_replace( '/(href|xlink:href)\s*=\s*("[^"]*javascript:[^"]*"|\'[^\']*javascript:[^\']*\'|[^\s>]*javascript:[^\s>]*)/i', '', $content );

		return $content;
	}
}

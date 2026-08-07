<?php
/**
 * Módulo: Snippets de Código Personalizados - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Snippets {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Snippets
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Snippets
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
		add_action( 'wp_head', array( $this, 'inject_css' ), 100 );
		add_action( 'wp_footer', array( $this, 'inject_js' ), 100 );
		
		// Ejecutar PHP de inmediato para permitir que los fragmentos registren hooks tempranos
		$this->execute_php();
	}

	/**
	 * Inyecta los CSS personalizados activos en la cabecera del frontend.
	 */
	public function inject_css() {
		if ( is_admin() ) {
			return;
		}

		$snippets = get_option( 'wpat_snippets', array() );
		if ( empty( $snippets ) ) {
			return;
		}

		foreach ( $snippets as $snippet ) {
			if ( isset( $snippet['active'] ) && '1' === $snippet['active'] && 'css' === $snippet['type'] ) {
				$css = trim( $snippet['code'] );
				if ( ! empty( $css ) ) {
					echo "\n<!-- WPAT Custom CSS Snippet: " . esc_html( $snippet['name'] ) . " -->\n";
					echo "<style type=\"text/css\">\n";
					echo $css . "\n";
					echo "</style>\n";
				}
			}
		}
	}

	/**
	 * Inyecta los JS personalizados activos en el pie de página del frontend.
	 */
	public function inject_js() {
		if ( is_admin() ) {
			return;
		}

		$snippets = get_option( 'wpat_snippets', array() );
		if ( empty( $snippets ) ) {
			return;
		}

		foreach ( $snippets as $snippet ) {
			if ( isset( $snippet['active'] ) && '1' === $snippet['active'] && 'js' === $snippet['type'] ) {
				$js = trim( $snippet['code'] );
				if ( ! empty( $js ) ) {
					echo "\n<!-- WPAT Custom JS Snippet: " . esc_html( $snippet['name'] ) . " -->\n";
					echo "<script type=\"text/javascript\">\n";
					echo $js . "\n";
					echo "</script>\n";
				}
			}
		}
	}

	/**
	 * Ejecuta de forma segura los fragmentos PHP activos.
	 */
	public function execute_php() {
		$snippets = get_option( 'wpat_snippets', array() );
		if ( empty( $snippets ) ) {
			return;
		}

		foreach ( $snippets as $snippet ) {
			if ( isset( $snippet['active'] ) && '1' === $snippet['active'] && 'php' === $snippet['type'] ) {
				$php_code = trim( $snippet['code'] );
				if ( empty( $php_code ) ) {
					continue;
				}

				// Limpiar etiquetas de apertura/cierre de PHP si el usuario las colocó
				if ( 0 === strpos( $php_code, '<?php' ) ) {
					$php_code = substr( $php_code, 5 );
				}
				if ( '?>' === substr( $php_code, -2 ) ) {
					$php_code = substr( $php_code, 0, -2 );
				}

				$php_code = trim( $php_code );

				if ( empty( $php_code ) ) {
					continue;
				}

				$this->execute_single_php( $php_code, $snippet['name'] );
			}
		}
	}

	/**
	 * Ejecuta un único fragmento PHP bajo try-catch.
	 *
	 * @param string $php_code Código a ejecutar.
	 * @param string $name Nombre del fragmento.
	 */
	private function execute_single_php( $php_code, $name ) {
		try {
			eval( $php_code );
		} catch ( \Throwable $t ) {
			// Escribir el error en el log del sistema sin tirar abajo la web
			error_log( sprintf(
				'WPAT PHP Snippet (%s) Error: %s en la línea %d.',
				$name,
				$t->getMessage(),
				$t->getLine()
			) );

			// Mostrar error en la administración únicamente a administradores para depurar
			if ( is_admin() && current_user_can( 'manage_options' ) ) {
				add_action( 'admin_notices', function() use ( $t, $name ) {
					echo '<div class="notice notice-error"><p>';
					echo '<strong>WP Agency Toolkit (Error en ' . esc_html( $name ) . '):</strong> ' . esc_html( $t->getMessage() );
					echo ' en la línea ' . esc_html( $t->getLine() );
					echo '</p></div>';
				} );
			}
		}
	}
}

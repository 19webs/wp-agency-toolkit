<?php
/**
 * Clase WPAT_Updater.
 * Gestiona las actualizaciones automáticas desde el repositorio público de GitHub.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAT_Updater {

	/**
	 * Instancia única de la clase.
	 */
	private static $instance = null;

	/**
	 * Nombre del plugin (carpeta/archivo).
	 */
	private $plugin_slug = 'wp-agency-toolkit/wp-agency-toolkit.php';

	/**
	 * Nombre de la carpeta del plugin.
	 */
	private $plugin_dir = 'wp-agency-toolkit';

	/**
	 * Propietario del repositorio en GitHub.
	 */
	private $username = '19webs';

	/**
	 * Nombre del repositorio en GitHub.
	 */
	private $repository = 'wp-agency-toolkit';

	/**
	 * Datos de la última versión obtenidos de GitHub.
	 */
	private $github_response = null;

	/**
	 * Obtiene la instancia única.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registra los hooks.
	 */
	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'post_install' ), 10, 3 );
	}

	/**
	 * Consulta la última versión en GitHub.
	 */
	public function get_latest_github_release() {
		if ( null !== $this->github_response ) {
			return $this->github_response;
		}

		// Intentar obtener de la caché transitoria por 12 horas
		$cached = get_transient( 'wpat_github_update_check' );
		if ( false !== $cached ) {
			$this->github_response = $cached;
			return $cached;
		}

		$url = "https://api.github.com/repos/{$this->username}/{$this->repository}/tags";
		
		$args = array(
			'user-agent' => 'WP-Agency-Toolkit-Updater',
			'timeout'    => 10,
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data ) || ! isset( $data[0] ) ) {
			return false;
		}

		// El primer elemento de la lista de etiquetas de Git es la más reciente
		$latest_tag = $data[0];
		if ( ! is_array( $latest_tag ) || ! isset( $latest_tag['name'] ) || ! isset( $latest_tag['zipball_url'] ) ) {
			return false;
		}

		// Adaptamos al formato de release esperado por el resto de la clase
		$release = array(
			'tag_name'    => $latest_tag['name'],
			'zipball_url' => $latest_tag['zipball_url'],
			'html_url'    => "https://github.com/{$this->username}/{$this->repository}/releases/tag/" . $latest_tag['name'],
			'body'        => 'Mejoras y actualizaciones en la versión ' . $latest_tag['name'] . '.',
			'assets'      => array(),
		);

		$this->github_response = $release;

		// Guardar en caché por 12 horas
		set_transient( 'wpat_github_update_check', $release, 12 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Compara versiones y notifica a WordPress si hay una nueva actualización.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_github_release();
		if ( ! $release ) {
			return $transient;
		}

		// Limpiar la versión (quitar la 'v' si la lleva, ej: v3.2.0 -> 3.2.0)
		$new_version = ltrim( $release['tag_name'], 'v' );

		// Comparar con la versión local
		if ( version_compare( WPAT_VERSION, $new_version, '<' ) ) {
			$package = array(
				'slug'        => $this->plugin_dir,
				'plugin'      => $this->plugin_slug,
				'new_version' => $new_version,
				'url'         => $release['html_url'],
				'package'     => $release['zipball_url'], // URL directa de descarga de GitHub
				'icons'       => array(
					'1x' => WPAT_URL . 'assets/images/icon-256x256.jpg',
					'2x' => WPAT_URL . 'assets/images/icon-256x256.jpg',
				),
				'banners'     => array(
					'low'  => WPAT_URL . 'assets/images/banner-772x250.jpg',
					'high' => WPAT_URL . 'assets/images/banner-772x250.jpg',
				),
			);

			// Si el desarrollador subió un zip como asset (ej: wp-agency-toolkit.zip), preferimos ese
			if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
				foreach ( $release['assets'] as $asset ) {
					if ( isset( $asset['name'] ) && strpos( $asset['name'], '.zip' ) !== false ) {
						$package['package'] = $asset['browser_download_url'];
						break;
					}
				}
			}

			$transient->response[ $this->plugin_slug ] = (object) $package;
		}

		return $transient;
	}

	/**
	 * Muestra la información detallada del plugin en la ventana emergente de WordPress.
	 */
	public function plugin_popup_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_dir ) {
			return $result;
		}

		$release = $this->get_latest_github_release();
		if ( ! $release ) {
			return $result;
		}

		$new_version = ltrim( $release['tag_name'], 'v' );
		$changelog   = ! empty( $release['body'] ) ? nl2br( esc_html( $release['body'] ) ) : 'Actualizaciones y mejoras de rendimiento.';

		$res = new stdClass();
		$res->name           = 'WP Agency Toolkit';
		$res->slug           = $this->plugin_dir;
		$res->version        = $new_version;
		$res->author         = '<a href="https://19webs.es" target="_blank">19webs</a>';
		$res->homepage       = 'https://19webs.es';
		$res->download_link  = $release['zipball_url'];
		
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && strpos( $asset['name'], '.zip' ) !== false ) {
					$res->download_link = $asset['browser_download_url'];
					break;
				}
			}
		}
		$res->icons = array(
			'1x' => WPAT_URL . 'assets/images/icon-256x256.jpg',
			'2x' => WPAT_URL . 'assets/images/icon-256x256.jpg',
		);
		$res->banners = array(
			'low'  => WPAT_URL . 'assets/images/banner-772x250.jpg',
			'high' => WPAT_URL . 'assets/images/banner-772x250.jpg',
		);

		$res->sections = array(
			'description' => 'Un plugin modular, ligero y de alto rendimiento que unifica utilidades esenciales de administración, seguridad, WooCommerce, rendimiento y optimización de medios.',
			'changelog'   => $changelog,
		);

		return $res;
	}

	/**
	 * Corrige el nombre de la carpeta tras la instalación de la actualización de GitHub.
	 * GitHub suele nombrar la carpeta descargada como "nombre-repo-tag" o "nombre-repo-branch".
	 */
	public function post_install( $true, $hook_extra, $result ) {
		// Solo aplicar si la actualización corresponde a nuestro propio plugin
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
			return $result;
		}

		global $wp_filesystem;

		// Asegurar que el objeto filesystem existe
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Carpeta donde debería estar el plugin
		$proper_destination = WP_PLUGIN_DIR . '/' . $this->plugin_dir;

		// Si el resultado de la instalación tiene una carpeta destino diferente
		if ( isset( $result['destination'] ) && $result['destination'] !== $proper_destination ) {
			// Eliminar la carpeta destino si ya existe para evitar errores de movimiento
			if ( $wp_filesystem->exists( $proper_destination ) ) {
				$wp_filesystem->delete( $proper_destination, true );
			}

			$move = $wp_filesystem->move( $result['destination'], $proper_destination );
			if ( $move ) {
				$result['destination'] = $proper_destination;
			}
		}

		return $result;
	}
}

// Inicializar
WPAT_Updater::get_instance();

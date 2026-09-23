<?php
/**
 * Clase WPAT_Updater.
 * Gestiona las actualizaciones automáticas seguras y directas desde el repositorio de GitHub.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAT_Updater {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Updater|null
	 */
	private static $instance = null;

	/**
	 * Nombre del plugin (carpeta/archivo).
	 *
	 * @var string
	 */
	private $plugin_slug = 'wp-agency-toolkit/wp-agency-toolkit.php';

	/**
	 * Nombre de la carpeta del plugin.
	 *
	 * @var string
	 */
	private $plugin_dir = 'wp-agency-toolkit';

	/**
	 * Propietario del repositorio en GitHub.
	 *
	 * @var string
	 */
	private $username = '19webs';

	/**
	 * Nombre del repositorio en GitHub.
	 *
	 * @var string
	 */
	private $repository = 'wp-agency-toolkit';

	/**
	 * Datos de la última versión obtenidos de GitHub en memoria durante la petición.
	 *
	 * @var array|null
	 */
	private $github_response = null;

	/**
	 * Obtiene la instancia única.
	 *
	 * @return WPAT_Updater
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registra los hooks del actualizador de WordPress.
	 */
	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup_info' ), 20, 3 );
		add_filter( 'upgrader_package_options', array( $this, 'refresh_package_before_upgrade' ) );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_github_source' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade_complete' ), 10, 2 );
	}

	/**
	 * Consulta y determina la versión semántica más alta en GitHub.
	 *
	 * @param bool $force_refresh Si es true, ignora cualquier caché y consulta GitHub en tiempo real.
	 * @return array|false Datos de la versión más reciente o false en caso de fallo.
	 */
	public function get_latest_github_release( $force_refresh = false ) {
		if ( ! $force_refresh && null !== $this->github_response ) {
			return $this->github_response;
		}

		// Si no se fuerza refresco, intentar obtener de la caché transitoria
		if ( ! $force_refresh ) {
			$cached = get_transient( 'wpat_github_update_check' );
			if ( false !== $cached && is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
				$this->github_response = $cached;
				return $cached;
			}
		}

		$args = array(
			'user-agent' => 'WP-Agency-Toolkit-Updater/' . WPAT_VERSION . ' (WordPress/' . get_bloginfo( 'version' ) . ')',
			'timeout'    => 12,
			'headers'    => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		);

		// 1. Obtener todas las etiquetas de Git (hasta 100)
		$tags_url      = "https://api.github.com/repos/{$this->username}/{$this->repository}/tags?per_page=100";
		$tags_response = wp_remote_get( $tags_url, $args );

		if ( is_wp_error( $tags_response ) ) {
			return false;
		}

		$tags_body = wp_remote_retrieve_body( $tags_response );
		$tags_data = json_decode( $tags_body, true );

		if ( ! is_array( $tags_data ) || empty( $tags_data ) || isset( $tags_data['message'] ) ) {
			return false;
		}

		// 2. Recorrer TODAS las etiquetas y encontrar la versión semántica MÁS ALTA
		$highest_version = '0.0.0';
		$highest_tag     = null;

		foreach ( $tags_data as $tag ) {
			if ( ! is_array( $tag ) || empty( $tag['name'] ) ) {
				continue;
			}
			$clean_ver = ltrim( trim( $tag['name'] ), 'vV' );
			if ( version_compare( $highest_version, $clean_ver, '<' ) ) {
				$highest_version = $clean_ver;
				$highest_tag     = $tag;
			}
		}

		if ( ! $highest_tag || ! isset( $highest_tag['name'] ) ) {
			return false;
		}

		$tag_name    = $highest_tag['name'];
		$download_url = isset( $highest_tag['zipball_url'] ) ? $highest_tag['zipball_url'] : '';
		$html_url    = "https://github.com/{$this->username}/{$this->repository}/releases/tag/{$tag_name}";
		$body_notes  = "Mejoras, correcciones y nuevas funcionalidades en la versión {$highest_version}.";

		// 3. Comprobar si existe una Release formal asociada con assets empaquetados (.zip)
		$releases_url      = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases?per_page=10";
		$releases_response = wp_remote_get( $releases_url, $args );

		if ( ! is_wp_error( $releases_response ) ) {
			$releases_body = wp_remote_retrieve_body( $releases_response );
			$releases_data = json_decode( $releases_body, true );

			if ( is_array( $releases_data ) && ! empty( $releases_data ) && ! isset( $releases_data['message'] ) ) {
				foreach ( $releases_data as $rel ) {
					if ( isset( $rel['tag_name'] ) && $rel['tag_name'] === $tag_name ) {
						if ( ! empty( $rel['body'] ) ) {
							$body_notes = $rel['body'];
						}
						if ( ! empty( $rel['html_url'] ) ) {
							$html_url = $rel['html_url'];
						}
						// Si hay un .zip adjunto como asset, preferir esa URL directa
						if ( ! empty( $rel['assets'] ) && is_array( $rel['assets'] ) ) {
							foreach ( $rel['assets'] as $asset ) {
								if ( isset( $asset['name'] ) && strpos( $asset['name'], '.zip' ) !== false && ! empty( $asset['browser_download_url'] ) ) {
									$download_url = $asset['browser_download_url'];
									break;
								}
							}
						}
						break;
					}
				}
			}
		}

		$release = array(
			'tag_name'     => $tag_name,
			'version'      => $highest_version,
			'zipball_url'  => isset( $highest_tag['zipball_url'] ) ? $highest_tag['zipball_url'] : '',
			'download_url' => $download_url,
			'html_url'     => $html_url,
			'body'         => $body_notes,
		);

		$this->github_response = $release;

		// Guardar en caché transitoria por 2 horas (tiempo óptimo para evitar rate-limits y responder rápido a nuevas releases)
		set_transient( 'wpat_github_update_check', $release, 2 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Compara versiones y notifica a WordPress si hay una nueva versión disponible.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function check_for_update( $transient ) {
		// Si se está forzando la comprobación nativa de WordPress en wp-admin, limpiar caché
		if ( isset( $_GET['force-check'] ) || ( isset( $_GET['action'] ) && 'upgrade-plugin' === $_GET['action'] ) ) {
			delete_transient( 'wpat_github_update_check' );
			$this->github_response = null;
		}

		if ( empty( $transient ) || ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$release = $this->get_latest_github_release();
		if ( ! $release || empty( $release['version'] ) ) {
			return $transient;
		}

		$new_version = $release['version'];

		// Comparar con la versión local instalada
		if ( version_compare( WPAT_VERSION, $new_version, '<' ) ) {
			$package = array(
				'slug'        => $this->plugin_dir,
				'plugin'      => $this->plugin_slug,
				'new_version' => $new_version,
				'url'         => $release['html_url'],
				'package'     => ! empty( $release['download_url'] ) ? $release['download_url'] : $release['zipball_url'],
				'icons'       => array(
					'1x' => WPAT_URL . 'assets/images/icon-256x256.jpg',
					'2x' => WPAT_URL . 'assets/images/icon-256x256.jpg',
				),
				'banners'     => array(
					'low'  => WPAT_URL . 'assets/images/banner-772x250.jpg',
					'high' => WPAT_URL . 'assets/images/banner-772x250.jpg',
				),
			);

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}

			$transient->response[ $this->plugin_slug ] = (object) $package;
		} else {
			// Si no hay actualización, limpiar cualquier respuesta previa del transient
			if ( isset( $transient->response[ $this->plugin_slug ] ) ) {
				unset( $transient->response[ $this->plugin_slug ] );
			}
		}

		return $transient;
	}

	/**
	 * Hook que se dispara justo antes de descargar el paquete de actualización.
	 * Fuerza una comprobación en tiempo real para garantizar que WordPress descargue siempre la versión más reciente absoluta.
	 *
	 * @param array $options Opciones del actualizador de WordPress.
	 * @return array
	 */
	public function refresh_package_before_upgrade( $options ) {
		if ( ! isset( $options['hook_extra']['plugin'] ) || $options['hook_extra']['plugin'] !== $this->plugin_slug ) {
			return $options;
		}

		// Forzar consulta en tiempo real a GitHub para saltarse cualquier caché obsoleta
		$release = $this->get_latest_github_release( true );
		if ( $release && ! empty( $release['version'] ) ) {
			$download_url = ! empty( $release['download_url'] ) ? $release['download_url'] : $release['zipball_url'];
			if ( ! empty( $download_url ) ) {
				$options['package'] = $download_url;
			}
			$options['hook_extra']['new_version'] = $release['version'];
		}

		return $options;
	}

	/**
	 * Muestra la información detallada del plugin en la ventana emergente de WordPress.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return object
	 */
	public function plugin_popup_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || ( $args->slug !== $this->plugin_dir && $args->slug !== $this->plugin_slug ) ) {
			return $result;
		}

		$release = $this->get_latest_github_release();
		if ( ! $release ) {
			return $result;
		}

		$new_version = $release['version'];
		$changelog   = ! empty( $release['body'] ) ? nl2br( esc_html( $release['body'] ) ) : 'Actualizaciones y mejoras de rendimiento.';

		$res                = new stdClass();
		$res->name          = 'WP Agency Toolkit';
		$res->slug          = $this->plugin_dir;
		$res->version       = $new_version;
		$res->author        = '<a href="https://19webs.es" target="_blank">19webs</a>';
		$res->homepage      = 'https://19webs.es';
		$res->download_link = ! empty( $release['download_url'] ) ? $release['download_url'] : $release['zipball_url'];
		
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
	 * Corrige de forma segura el nombre de la carpeta en el directorio temporal de actualizaciones.
	 * Evita borrar la carpeta de origen si ya tiene el nombre correcto (causa del fallo crítico previo).
	 *
	 * @param string      $source        Ruta temporal de la carpeta descomprimida.
	 * @param string      $remote_source Ruta remota de origen.
	 * @param WP_Upgrader $upgrader      Instancia del actualizador.
	 * @param array       $hook_extra    Datos adicionales del hook.
	 * @return string Ruta de la carpeta lista para ser instalada.
	 */
	public function rename_github_source( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$source_clean = untrailingslashit( $source );

		// 1. Comprobar si el archivo principal está en la raíz de $source
		$has_main_file = file_exists( $source_clean . '/wp-agency-toolkit.php' );
		
		// Si no está en la raíz, comprobar si está dentro de una subcarpeta (ej: 19webs-wp-agency-toolkit-xxx/wp-agency-toolkit/)
		if ( ! $has_main_file ) {
			$files = glob( $source_clean . '/*/wp-agency-toolkit.php' );
			if ( ! empty( $files ) ) {
				$source_clean  = dirname( $files[0] );
				$has_main_file = true;
			}
		}

		// Si no es nuestro plugin, no intervenir
		if ( ! $has_main_file ) {
			return $source;
		}

		// Carpeta destino correcta en la ruta temporal (ej: wp-content/upgrade/wp-agency-toolkit)
		$parent_dir          = dirname( $source_clean );
		$correct_destination = trailingslashit( $parent_dir ) . $this->plugin_dir;

		// CRÍTICO: Si la carpeta ya se llama exactamente 'wp-agency-toolkit', NO hacer nada ni borrarla
		if ( untrailingslashit( $correct_destination ) === $source_clean ) {
			return trailingslashit( $correct_destination );
		}

		// Si ya existe otra carpeta previa residual con el nombre destino, borrarla para evitar colisión
		if ( $wp_filesystem->exists( $correct_destination ) ) {
			$wp_filesystem->delete( $correct_destination, true );
		}

		// Renombrar la carpeta origen a wp-agency-toolkit
		$move = $wp_filesystem->move( $source_clean, $correct_destination, true );
		if ( $move ) {
			return trailingslashit( $correct_destination );
		}

		return $source;
	}

	/**
	 * Limpia la caché transitoria tras completarse la actualización.
	 *
	 * @param WP_Upgrader $upgrader
	 * @param array       $options
	 */
	public function on_upgrade_complete( $upgrader, $options ) {
		if ( isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			if ( isset( $options['plugins'] ) && is_array( $options['plugins'] ) && in_array( $this->plugin_slug, $options['plugins'], true ) ) {
				delete_transient( 'wpat_github_update_check' );
				delete_site_transient( 'update_plugins' );
				$this->github_response = null;
			}
		}
	}
}

// Inicializar
WPAT_Updater::get_instance();

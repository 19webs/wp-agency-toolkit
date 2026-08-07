<?php
/**
 * Módulo: Importador de Template Kits de Envato - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Envato_Importer {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Envato_Importer
	 */
	private static $instance = null;

	/**
	 * Directorio base donde se extraen los kits.
	 *
	 * @var string
	 */
	private $upload_base_dir;

	/**
	 * URL base de los kits extraídos.
	 *
	 * @var string
	 */
	private $upload_base_url;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Envato_Importer
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
		$upload_dir = wp_upload_dir();
		$this->upload_base_dir = $upload_dir['basedir'] . '/wpat-template-kits';
		$this->upload_base_url = $upload_dir['baseurl'] . '/wpat-template-kits';

		// Registrar endpoints AJAX
		add_action( 'wp_ajax_wpat_upload_envato_kit', array( $this, 'ajax_upload_envato_kit' ) );
		add_action( 'wp_ajax_wpat_import_envato_template', array( $this, 'ajax_import_envato_template' ) );
		add_action( 'wp_ajax_wpat_delete_envato_kit', array( $this, 'ajax_delete_envato_kit' ) );
		add_action( 'wp_ajax_wpat_import_and_get_template_id', array( $this, 'ajax_import_and_get_template_id' ) );
		add_action( 'wp_ajax_wpat_enable_elementor_setting', array( $this, 'ajax_enable_elementor_setting' ) );
		add_action( 'wp_ajax_wpat_install_required_plugin', array( $this, 'ajax_install_required_plugin' ) );
		add_action( 'wp_ajax_wpat_activate_required_plugin', array( $this, 'ajax_activate_required_plugin' ) );

		// Encolar scripts en el editor de Elementor y en su iframe de previsualización
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_preview_assets' ) );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_preview_assets' ) );
	}

	/**
	 * AJAX: Sube un archivo ZIP de kit de plantillas de Envato y lo procesa.
	 */
	public function ajax_upload_envato_kit() {
		check_ajax_referer( 'wpat_envato_importer_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		if ( empty( $_FILES['kit_zip'] ) ) {
			wp_send_json_error( array( 'message' => 'No se ha subido ningún archivo.' ) );
		}

		$file = $_FILES['kit_zip'];
		$ext  = pathinfo( $file['name'], PATHINFO_EXTENSION );

		if ( 'zip' !== strtolower( $ext ) ) {
			wp_send_json_error( array( 'message' => 'Solo se admiten archivos ZIP de kits de plantilla.' ) );
		}

		// Crear directorio de destino si no existe
		if ( ! file_exists( $this->upload_base_dir ) ) {
			wp_mkdir_p( $this->upload_base_dir );
		}

		$slug = sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) );
		$dest_dir = $this->upload_base_dir . '/' . $slug;

		// Si ya existe la carpeta del kit, la eliminamos para sobreescribir
		if ( file_exists( $dest_dir ) ) {
			$this->recursive_rmdir( $dest_dir );
		}

		wp_mkdir_p( $dest_dir );

		// Descomprimir ZIP
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		$unzipfile = unzip_file( $file['tmp_name'], $dest_dir );

		if ( is_wp_error( $unzipfile ) ) {
			wp_send_json_error( array( 'message' => 'Error al descomprimir archivo: ' . $unzipfile->get_error_message() ) );
		}

		// Buscar manifest.json recursivamente
		$manifest_path = $this->find_manifest_file( $dest_dir );

		if ( ! $manifest_path ) {
			$this->recursive_rmdir( $dest_dir );
			wp_send_json_error( array( 'message' => 'No se encontró un archivo manifest.json válido dentro del kit.' ) );
		}

		// Leer manifest.json
		$manifest_content = file_get_contents( $manifest_path );
		$manifest_data    = json_decode( $manifest_content, true );

		if ( ! $manifest_data || empty( $manifest_data['title'] ) ) {
			$this->recursive_rmdir( $dest_dir );
			wp_send_json_error( array( 'message' => 'El formato del manifest.json no es válido.' ) );
		}

		// Obtener la carpeta donde está el manifest para resolver las rutas relativas
		$manifest_dir = dirname( $manifest_path );
		$relative_manifest_dir = str_replace( $this->upload_base_dir, '', $manifest_dir );
		$relative_manifest_dir = ltrim( str_replace( '\\', '/', $relative_manifest_dir ), '/' );

		// Obtener miniatura/portada global del kit
		$kit_thumbnail = '';
		if ( ! empty( $manifest_data['thumbnail'] ) ) {
			$kit_thumbnail = $manifest_data['thumbnail'];
		} elseif ( ! empty( $manifest_data['preview'] ) ) {
			$kit_thumbnail = $manifest_data['preview'];
		} elseif ( ! empty( $manifest_data['image'] ) ) {
			$kit_thumbnail = $manifest_data['image'];
		}

		if ( ! empty( $kit_thumbnail ) && ! filter_var( $kit_thumbnail, FILTER_VALIDATE_URL ) ) {
			// Es una ruta relativa, resolvemos su URL completa
			$kit_thumbnail = $this->upload_base_url . '/' . ( $relative_manifest_dir ? $relative_manifest_dir . '/' : '' ) . ltrim( $kit_thumbnail, '/' );
		}

		// Extraer plugins requeridos del manifest de forma súper robusta
		$required_plugins = array();
		if ( ! empty( $manifest_data['required_plugins'] ) ) {
			$raw_plugins = $manifest_data['required_plugins'];
			
			if ( is_array( $raw_plugins ) ) {
				// Comprobar si es un array asociativo (Format B)
				$is_assoc = ( array_keys( $raw_plugins ) !== range( 0, count( $raw_plugins ) - 1 ) );
				
				if ( $is_assoc ) {
					foreach ( $raw_plugins as $slug => $data ) {
						$plugin_slug = sanitize_key( $slug );
						$plugin_name = '';
						$version     = '';
						
						if ( is_array( $data ) ) {
							$plugin_name = ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : ucfirst( $slug );
							$version     = ! empty( $data['version'] ) ? sanitize_text_field( $data['version'] ) : '';
						} elseif ( is_string( $data ) ) {
							$plugin_name = sanitize_text_field( $data );
						} else {
							$plugin_name = ucfirst( $slug );
						}
						
						$required_plugins[] = array(
							'slug'    => $plugin_slug,
							'name'    => $plugin_name,
							'version' => $version,
						);
					}
				} else {
					// Array indexado (Format A o C)
					foreach ( $raw_plugins as $req ) {
						if ( is_array( $req ) ) {
							// Format A: [ {"slug": "...", "name": "..."} ]
							$plugin_slug = ! empty( $req['slug'] ) ? sanitize_key( $req['slug'] ) : '';
							$plugin_name = ! empty( $req['name'] ) ? sanitize_text_field( $req['name'] ) : ( ! empty( $plugin_slug ) ? ucfirst( $plugin_slug ) : '' );
							$version     = ! empty( $req['version'] ) ? sanitize_text_field( $req['version'] ) : '';
							
							if ( ! empty( $plugin_slug ) ) {
								$required_plugins[] = array(
									'slug'    => $plugin_slug,
									'name'    => $plugin_name,
									'version' => $version,
								);
							}
						} elseif ( is_string( $req ) ) {
							// Format C: [ "elementor", "woocommerce" ]
							$plugin_slug = sanitize_key( $req );
							$required_plugins[] = array(
								'slug'    => $plugin_slug,
								'name'    => ucfirst( $plugin_slug ),
								'version' => '',
							);
						}
					}
				}
			}
		}

		// Estructurar el kit
		$kits = get_option( 'wpat_envato_kits', array() );

		$new_kit = array(
			'title'            => sanitize_text_field( $manifest_data['title'] ),
			'slug'             => $slug,
			'folder'           => $relative_manifest_dir,
			'thumbnail'        => $kit_thumbnail,
			'required_plugins' => $required_plugins,
			'templates'        => array(),
		);

		if ( ! empty( $manifest_data['templates'] ) && is_array( $manifest_data['templates'] ) ) {
			foreach ( $manifest_data['templates'] as $tpl ) {
				// Soporte flexible para 'title' o 'name'
				$tpl_title = '';
				if ( ! empty( $tpl['title'] ) ) {
					$tpl_title = $tpl['title'];
				} elseif ( ! empty( $tpl['name'] ) ) {
					$tpl_title = $tpl['name'];
				}

				// Soporte flexible para 'file', 'filename' o 'source'
				$tpl_file = '';
				if ( ! empty( $tpl['file'] ) ) {
					$tpl_file = $tpl['file'];
				} elseif ( ! empty( $tpl['filename'] ) ) {
					$tpl_file = $tpl['filename'];
				} elseif ( ! empty( $tpl['source'] ) ) {
					$tpl_file = $tpl['source'];
				}

				if ( empty( $tpl_title ) || empty( $tpl_file ) ) {
					continue;
				}

				// Buscar miniatura/imagen representativa si la tiene
				$thumbnail = '';
				if ( ! empty( $tpl['thumbnail_url'] ) ) {
					$thumbnail = esc_url_raw( $tpl['thumbnail_url'] );
				} else {
					$thumbnail_file = '';
					if ( ! empty( $tpl['thumbnail'] ) ) {
						$thumbnail_file = $tpl['thumbnail'];
					} elseif ( ! empty( $tpl['screenshot'] ) ) {
						$thumbnail_file = $tpl['screenshot'];
					}

					if ( ! empty( $thumbnail_file ) ) {
						if ( ! filter_var( $thumbnail_file, FILTER_VALIDATE_URL ) ) {
							$thumbnail = $this->upload_base_url . '/' . ( $relative_manifest_dir ? $relative_manifest_dir . '/' : '' ) . ltrim( $thumbnail_file, '/' );
						} else {
							$thumbnail = esc_url_raw( $thumbnail_file );
						}
					}
				}

				// Buscar url de vista previa si existe
				$preview_url = '';
				$raw_preview = '';
				if ( ! empty( $tpl['preview_url'] ) ) {
					$raw_preview = $tpl['preview_url'];
				} elseif ( ! empty( $tpl['preview'] ) ) {
					$raw_preview = $tpl['preview'];
				} elseif ( ! empty( $tpl['url'] ) ) {
					$raw_preview = $tpl['url'];
				}

				if ( ! empty( $raw_preview ) ) {
					if ( ! filter_var( $raw_preview, FILTER_VALIDATE_URL ) ) {
						$preview_url = $this->upload_base_url . '/' . ( $relative_manifest_dir ? $relative_manifest_dir . '/' : '' ) . ltrim( $raw_preview, '/' );
					} else {
						$preview_url = esc_url_raw( $raw_preview );
					}
				}

				$new_kit['templates'][] = array(
					'id'          => sanitize_title( $tpl_title ),
					'title'       => sanitize_text_field( $tpl_title ),
					'type'        => ! empty( $tpl['type'] ) ? sanitize_text_field( $tpl['type'] ) : 'page',
					'file'        => sanitize_text_field( $tpl_file ),
					'thumbnail'   => $thumbnail,
					'preview_url' => $preview_url,
				);
			}
		}

		if ( empty( $new_kit['templates'] ) ) {
			// Si no se han parseado plantillas, borramos el directorio y damos error detallado
			$this->recursive_rmdir( $dest_dir );
			$keys = is_array( $manifest_data ) ? array_keys( $manifest_data ) : array();
			wp_send_json_error( array( 
				'message' => 'El kit se subió pero tiene 0 plantillas detectadas. Claves del manifest: ' . implode( ', ', $keys ) . '. Contenido completo: ' . wp_json_encode( $manifest_data )
			) );
		}

		if ( empty( $new_kit['thumbnail'] ) && ! empty( $new_kit['templates'] ) ) {
			// Usar la primera plantilla como portada del kit
			$new_kit['thumbnail'] = $new_kit['templates'][0]['thumbnail'];
		}

		$kits[ $slug ] = $new_kit;
		update_option( 'wpat_envato_kits', $kits );

		// Obtener los kits con los estados de los plugins enriquecidos para el JS de administración
		$enriched_kits = $this->get_kits_with_plugin_status();
		$enriched_kit = isset( $enriched_kits[ $slug ] ) ? $enriched_kits[ $slug ] : $new_kit;

		wp_send_json_success( array( 
			'message' => 'Kit de plantilla subido e indexado correctamente.', 
			'kit'     => $enriched_kit 
		) );
	}

	/**
	 * AJAX: Importa una plantilla específica en la biblioteca local de Elementor.
	 */
	public function ajax_import_envato_template() {
		check_ajax_referer( 'wpat_envato_importer_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$kit_slug    = isset( $_POST['kit_slug'] ) ? sanitize_title( $_POST['kit_slug'] ) : '';
		$template_id = isset( $_POST['template_id'] ) ? sanitize_title( $_POST['template_id'] ) : '';

		$kits = get_option( 'wpat_envato_kits', array() );
		if ( empty( $kits[ $kit_slug ] ) ) {
			wp_send_json_error( array( 'message' => 'El kit especificado no existe.' ) );
		}

		$kit = $kits[ $kit_slug ];
		$target_template = null;

		foreach ( $kit['templates'] as $tpl ) {
			if ( $tpl['id'] === $template_id ) {
				$target_template = $tpl;
				break;
			}
		}

		if ( ! $target_template ) {
			wp_send_json_error( array( 'message' => 'La plantilla especificada no se encuentra en el kit.' ) );
		}

		$file_path = $this->upload_base_dir . '/' . $kit['folder'] . '/' . $target_template['file'];

		if ( ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => 'No se encuentra el archivo JSON de la plantilla en el servidor.' ) );
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			wp_send_json_error( array( 'message' => 'Elementor no está activo en el sitio.' ) );
		}

		// Si es una plantilla de estilos globales, la aplicamos directamente al kit activo de Elementor
		$is_global_style = (
			false !== strpos( strtolower( $target_template['title'] ), 'global' ) ||
			'global-styles' === $target_template['type'] ||
			'kit-settings' === $target_template['type'] ||
			'global.json' === basename( $target_template['file'] )
		);

		if ( $is_global_style ) {
			$global_import = $this->import_global_styles( $file_path );
			if ( is_wp_error( $global_import ) ) {
				wp_send_json_error( array( 'message' => 'Error al aplicar estilos globales: ' . $global_import->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => 'Los estilos globales (colores, tipografía y configuraciones) del kit han sido aplicados con éxito en Elementor.' ) );
		}

		// Evitar duplicaciones buscando si ya existe una plantilla en la librería con el mismo título
		$existing = get_posts( array(
			'post_type'      => 'elementor_library',
			'title'          => $target_template['title'],
			'posts_per_page' => 1,
			'post_status'    => 'any',
		) );

		if ( ! empty( $existing ) ) {
			wp_send_json_success( array( 'message' => 'Plantilla importada con éxito en tu biblioteca de Elementor.' ) );
		}

		// Importar usando la librería local de Elementor sanitizando el tipo de plantilla si es necesario
		$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );
		$import = $this->import_template_sanitize_type( $source, $file_path );

		if ( is_wp_error( $import ) ) {
			wp_send_json_error( array( 'message' => $import->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'Plantilla importada con éxito en tu biblioteca de Elementor.' ) );
	}

	/**
	 * AJAX: Elimina un kit de plantillas completo.
	 */
	public function ajax_delete_envato_kit() {
		check_ajax_referer( 'wpat_envato_importer_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$slug = isset( $_POST['kit_slug'] ) ? sanitize_title( $_POST['kit_slug'] ) : '';

		$kits = get_option( 'wpat_envato_kits', array() );
		if ( empty( $kits[ $slug ] ) ) {
			wp_send_json_error( array( 'message' => 'El kit especificado no existe.' ) );
		}

		// Ruta física de la carpeta del kit (obtenemos el primer fragmento de la carpeta)
		$kit_folder = explode( '/', $kits[ $slug ]['folder'] )[0];
		$dir_path   = $this->upload_base_dir . '/' . $kit_folder;

		if ( file_exists( $dir_path ) ) {
			$this->recursive_rmdir( $dir_path );
		}

		unset( $kits[ $slug ] );
		update_option( 'wpat_envato_kits', $kits );

		wp_send_json_success( array( 'message' => 'Kit eliminado correctamente del servidor.' ) );
	}

	/**
	 * AJAX: Importa una plantilla a la biblioteca de Elementor y devuelve su ID de post.
	 */
	public function ajax_import_and_get_template_id() {
		check_ajax_referer( 'wpat_envato_editor_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$kit_slug    = isset( $_POST['kit_slug'] ) ? sanitize_title( $_POST['kit_slug'] ) : '';
		$template_id = isset( $_POST['template_id'] ) ? sanitize_title( $_POST['template_id'] ) : '';

		$kits = get_option( 'wpat_envato_kits', array() );
		if ( empty( $kits[ $kit_slug ] ) ) {
			wp_send_json_error( array( 'message' => 'El kit especificado no existe.' ) );
		}

		$kit = $kits[ $kit_slug ];
		$target_template = null;

		foreach ( $kit['templates'] as $tpl ) {
			if ( $tpl['id'] === $template_id ) {
				$target_template = $tpl;
				break;
			}
		}

		if ( ! $target_template ) {
			wp_send_json_error( array( 'message' => 'La plantilla especificada no se encuentra en el kit.' ) );
		}

		$file_path = $this->upload_base_dir . '/' . $kit['folder'] . '/' . $target_template['file'];

		if ( ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => 'No se encuentra el archivo JSON de la plantilla en el servidor.' ) );
		}

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			wp_send_json_error( array( 'message' => 'Elementor no está activo en el sitio.' ) );
		}

		// Si es una plantilla de estilos globales, la aplicamos directamente
		$is_global_style = (
			false !== strpos( strtolower( $target_template['title'] ), 'global' ) ||
			'global-styles' === $target_template['type'] ||
			'kit-settings' === $target_template['type'] ||
			'global.json' === basename( $target_template['file'] )
		);

		if ( $is_global_style ) {
			$global_import = $this->import_global_styles( $file_path );
			if ( is_wp_error( $global_import ) ) {
				wp_send_json_error( array( 'message' => 'Error al aplicar estilos globales: ' . $global_import->get_error_message() ) );
			}
			wp_send_json_success( array(
				'type'    => 'global_styles',
				'message' => 'Los estilos globales han sido aplicados con éxito.'
			) );
		}

		// Importar a la librería local si no existe para evitar duplicaciones
		$existing = get_posts( array(
			'post_type'      => 'elementor_library',
			'title'          => $target_template['title'],
			'posts_per_page' => 1,
			'post_status'    => 'any',
		) );

		if ( ! empty( $existing ) ) {
			$template_post_id = $existing[0]->ID;
		} else {
			$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );
			$import = $this->import_template_sanitize_type( $source, $file_path );
			if ( is_wp_error( $import ) ) {
				wp_send_json_error( array( 'message' => $import->get_error_message() ) );
			}
			$template_post_id = ( is_array( $import ) && ! empty( $import[0]['template_id'] ) ) ? $import[0]['template_id'] : 0;
		}

		if ( ! $template_post_id ) {
			wp_send_json_error( array( 'message' => 'No se pudo crear la plantilla en la biblioteca de Elementor.' ) );
		}

		wp_send_json_success( array(
			'type'        => 'library_template',
			'template_id' => $template_post_id,
			'message'     => 'Plantilla lista para inserción.'
		) );
	}

	/**
	 * Importa una plantilla de Elementor sanitizando su tipo si no es compatible con la versión gratuita.
	 */
	private function import_template_sanitize_type( $source, $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', 'No se encuentra el archivo de la plantilla.' );
		}

		$json_content = file_get_contents( $file_path );
		$data = json_decode( $json_content, true );

		$modified = false;
		if ( is_array( $data ) ) {
			$current_type = isset( $data['type'] ) ? strtolower( $data['type'] ) : '';

			if ( 'page' !== $current_type && 'section' !== $current_type ) {
				// Forzar a 'section' como tipo seguro de Elementor gratuito
				$data['type'] = 'section';
				$modified = true;
			}
		}

		$temp_file_path = $file_path;
		if ( $modified ) {
			$temp_dir = $this->upload_base_dir . '/temp';
			if ( ! file_exists( $temp_dir ) ) {
				wp_mkdir_p( $temp_dir );
			}
			$temp_file_path = $temp_dir . '/' . uniqid( 'tpl_' ) . '.json';
			file_put_contents( $temp_file_path, wp_json_encode( $data ) );
		}

		$import = $source->import_template( basename( $file_path ), $temp_file_path );

		if ( $modified && file_exists( $temp_file_path ) ) {
			@unlink( $temp_file_path );
		}

		return $import;
	}

	/**
	 * Carga scripts y estilos de nuestro modal e icono dentro del editor e iframe de previsualización de Elementor.
	 */
	public function enqueue_preview_assets() {
		$is_editor  = isset( $_GET['action'] ) && 'elementor' === $_GET['action'];
		$is_preview = isset( $_GET['elementor-preview'] ) || ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() );

		if ( ! $is_editor && ! $is_preview ) {
			return;
		}

		// JS editor
		wp_enqueue_script(
			'wpat-envato-editor-js',
			WPAT_URL . 'assets/js/wpat-envato-editor.js',
			array( 'jquery' ),
			time(),
			true
		);

		// Pasar listado de kits y endpoints al JS del editor
		$kits = get_option( 'wpat_envato_kits', array() );
		wp_localize_script( 'wpat-envato-editor-js', 'wpatEnvatoEditor', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'security'=> wp_create_nonce( 'wpat_envato_editor_nonce' ),
			'kits'    => $kits,
		) );

		// CSS editor
		wp_enqueue_style(
			'wpat-envato-editor-css',
			WPAT_URL . 'assets/css/wpat-envato-editor.css',
			array(),
			time()
		);
	}

	/**
	 * Busca recursivamente el archivo manifest.json dentro de una ruta.
	 */
	private function find_manifest_file( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$files = scandir( $dir );
		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$manifest = $this->find_manifest_file( $path );
				if ( $manifest ) {
					return $manifest;
				}
			} elseif ( 'manifest.json' === strtolower( $file ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * Elimina una carpeta de forma recursiva en el servidor.
	 */
	private function recursive_rmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			( is_dir( $dir . '/' . $file ) ) ? $this->recursive_rmdir( $dir . '/' . $file ) : unlink( $dir . '/' . $file );
		}
		return rmdir( $dir );
	}

	/**
	 * Importa y aplica las configuraciones globales (colores, tipografías, layouts) en Elementor.
	 *
	 * @param string $file_path Ruta del archivo JSON del kit.
	 * @return bool|WP_Error
	 */
	private function import_global_styles( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_missing', 'El archivo de estilos globales no existe.' );
		}

		$content = file_get_contents( $file_path );
		$data    = json_decode( $content, true );

		if ( ! $data ) {
			return new WP_Error( 'json_invalid', 'El JSON del estilo global no es válido.' );
		}

		// En los kits de Envato, las configuraciones globales suelen estar en 'page_settings' o 'settings'
		$page_settings = isset( $data['page_settings'] ) ? $data['page_settings'] : ( isset( $data['settings'] ) ? $data['settings'] : array() );

		if ( empty( $page_settings ) ) {
			return new WP_Error( 'empty_settings', 'No se encontraron configuraciones globales en el archivo.' );
		}

		// Obtener el ID del kit activo de Elementor
		$active_kit_id = get_option( 'elementor_active_kit' );
		if ( ! $active_kit_id ) {
			return new WP_Error( 'no_active_kit', 'No se encontró el kit de configuración activo de Elementor.' );
		}

		// Obtener las configuraciones actuales
		$current_settings = get_post_meta( $active_kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $current_settings ) ) {
			$current_settings = array();
		}

		// Combinar las configuraciones del kit con las actuales (dando prioridad al kit)
		$merged_settings = array_merge( $current_settings, $page_settings );

		// Actualizar las configuraciones del kit activo en Elementor
		update_post_meta( $active_kit_id, '_elementor_page_settings', $merged_settings );

		// Limpiar la caché de Elementor
		if ( class_exists( '\Elementor\Plugin' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}

		return true;
	}

	/**
	 * AJAX: Instala y activa un plugin requerido desde el repositorio oficial de WordPress.
	 */
	public function ajax_install_required_plugin() {
		check_ajax_referer( 'wpat_envato_importer_nonce', 'security' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos para instalar plugins.' ) );
		}

		$slug = isset( $_POST['plugin_slug'] ) ? sanitize_key( $_POST['plugin_slug'] ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Slug de plugin no válido.' ) );
		}

		// Mapeo de slugs del manifest a slugs oficiales de WordPress.org
		$slug_mappings = array(
			'rtmkit-addons-for-elementor' => 'rometheme-for-elementor',
		);
		if ( isset( $slug_mappings[ $slug ] ) ) {
			$slug = $slug_mappings[ $slug ];
		}

		try {
			// Incluir archivos necesarios de WordPress
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			// Cargar la skin silenciosa de forma segura ahora que WP_Upgrader_Skin existe
			if ( ! class_exists( 'WPAT_Silent_Upgrader_Skin' ) ) {
				require_once WPAT_PATH . 'includes/modules/class-wpat-silent-skin.php';
			}

			// Obtener información del plugin desde el repositorio oficial
			$api = plugins_api( 'plugin_information', array(
				'slug'   => $slug,
				'fields' => array(
					'sections' => false,
				),
			) );

			if ( is_wp_error( $api ) ) {
				wp_send_json_error( array( 'message' => 'No se pudo obtener información del plugin: ' . $api->get_error_message() ) );
			}

			// Forzar método directo de sistema de archivos para evitar solicitudes de FTP/credenciales
			add_filter( 'filesystem_method', function() { return 'direct'; } );
			
			// Inicializar el sistema de archivos de WordPress
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();

			// Instalar el plugin de forma silenciosa capturando salidas que corrompan el JSON
			ob_start();
			$upgrader = new Plugin_Upgrader( new WPAT_Silent_Upgrader_Skin() );
			$install  = $upgrader->install( $api->download_link );
			ob_end_clean();

			if ( is_wp_error( $install ) || ! $install ) {
				$error_msg = is_wp_error( $install ) ? $install->get_error_message() : 'Error durante la instalación.';
				wp_send_json_error( array( 'message' => 'Fallo al instalar: ' . $error_msg ) );
			}

			// Activar el plugin instalado
			$plugin_path = $this->get_plugin_main_file_path( $slug );
			if ( $plugin_path ) {
				// Evitar redirecciones inmediatas durante la activación en la petición AJAX
				add_filter( 'wp_redirect', '__return_false', 9999 );
				add_filter( 'wp_safe_redirect', '__return_false', 9999 );

				$activate = activate_plugin( $plugin_path );

				remove_filter( 'wp_redirect', '__return_false', 9999 );
				remove_filter( 'wp_safe_redirect', '__return_false', 9999 );

				// Limpiar banderas en BD para prevenir redirecciones al recargar
				$this->clear_welcome_redirect_flags( $slug );

				if ( is_wp_error( $activate ) ) {
					wp_send_json_error( array( 'message' => 'Plugin instalado pero falló la activación: ' . $activate->get_error_message() ) );
				}
				wp_send_json_success( array( 'message' => 'Plugin instalado y activado correctamente.' ) );
			}

			wp_send_json_error( array( 'message' => 'Plugin instalado pero no se encontró su archivo principal para activación.' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Excepción capturada: ' . $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Activa un plugin ya instalado.
	 */
	public function ajax_activate_required_plugin() {
		check_ajax_referer( 'wpat_envato_importer_nonce', 'security' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos para activar plugins.' ) );
		}

		$slug = isset( $_POST['plugin_slug'] ) ? sanitize_key( $_POST['plugin_slug'] ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Slug de plugin no válido.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_path = $this->get_plugin_main_file_path( $slug );
		if ( $plugin_path ) {
			// Evitar redirecciones inmediatas durante la activación en la petición AJAX
			add_filter( 'wp_redirect', '__return_false', 9999 );
			add_filter( 'wp_safe_redirect', '__return_false', 9999 );

			$activate = activate_plugin( $plugin_path );

			remove_filter( 'wp_redirect', '__return_false', 9999 );
			remove_filter( 'wp_safe_redirect', '__return_false', 9999 );

			// Limpiar banderas en BD para prevenir redirecciones al recargar
			$this->clear_welcome_redirect_flags( $slug );

			if ( is_wp_error( $activate ) ) {
				wp_send_json_error( array( 'message' => 'Error al activar: ' . $activate->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => 'Plugin activado correctamente.' ) );
		}

		wp_send_json_error( array( 'message' => 'No se encontró el archivo del plugin.' ) );
	}

	/**
	 * Busca el archivo de inicio de un plugin instalado a partir de su slug.
	 */
	public function get_plugin_main_file_path( $slug ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();

		foreach ( array_keys( $plugins ) as $plugin_file ) {
			if ( dirname( $plugin_file ) === $slug || $plugin_file === $slug . '.php' ) {
				return $plugin_file;
			}
		}
		return false;
	}

	/**
	 * Limpia los transients y opciones que los plugins crean para redirigir a pantallas de bienvenida.
	 */
	private function clear_welcome_redirect_flags( $slug ) {
		// Opciones/Transients genéricos y específicos de plugins conocidos
		$redirect_keys = array(
			'elementskit_do_activation_redirect',
			'elementskit-lite_do_activation_redirect',
			'ekit_do_activation_redirect',
			'jeg-elementor-kit_do_activation_redirect',
			'jeg_elementor_kit_do_activation_redirect',
			'jeg_elementor_kit_welcome_redirect',
			'metform_do_activation_redirect',
			'rometheme-for-elementor_do_activation_redirect',
			'rtm_do_activation_redirect',
			'elementor_do_activation_redirect',
			'hfe_do_activation_redirect',
			'header-footer-elementor_do_activation_redirect',
		);

		// Añadir variantes basadas en el slug
		$redirect_keys[] = $slug . '_do_activation_redirect';
		$redirect_keys[] = str_replace( '-', '_', $slug ) . '_do_activation_redirect';
		$redirect_keys[] = $slug . '_welcome_redirect';
		$redirect_keys[] = str_replace( '-', '_', $slug ) . '_welcome_redirect';

		foreach ( $redirect_keys as $key ) {
			delete_option( $key );
			delete_transient( $key );
		}
	}

	/**
	 * Extrae la lista de plugins requeridos del manifest buscando múltiples sinónimos y formatos.
	 */
	public function extract_required_plugins( $manifest_data ) {
		$required_plugins = array();
		if ( empty( $manifest_data ) || ! is_array( $manifest_data ) ) {
			return $required_plugins;
		}

		// Sinónimos de claves que pueden contener dependencias de plugins
		$keys_to_check = array( 'required_plugins', 'plugins', 'dependencies', 'extensions', 'required_addons', 'addons' );
		$raw_plugins   = null;

		foreach ( $keys_to_check as $key ) {
			if ( ! empty( $manifest_data[ $key ] ) ) {
				$raw_plugins = $manifest_data[ $key ];
				break;
			}
		}

		if ( ! empty( $raw_plugins ) && is_array( $raw_plugins ) ) {
			// Comprobar si es un array asociativo (Format B)
			$is_assoc = ( array_keys( $raw_plugins ) !== range( 0, count( $raw_plugins ) - 1 ) );
			
			if ( $is_assoc ) {
				foreach ( $raw_plugins as $slug => $data ) {
					$plugin_slug = sanitize_key( $slug );
					$plugin_name = '';
					$version     = '';
					
					if ( is_array( $data ) ) {
						$plugin_name = ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : ucfirst( $slug );
						$version     = ! empty( $data['version'] ) ? sanitize_text_field( $data['version'] ) : '';
					} elseif ( is_string( $data ) ) {
						$plugin_name = sanitize_text_field( $data );
					} else {
						$plugin_name = ucfirst( $slug );
					}
					
					$required_plugins[] = array(
						'slug'    => $plugin_slug,
						'name'    => $plugin_name,
						'version' => $version,
					);
				}
			} else {
				// Array indexado (Format A o C)
				foreach ( $raw_plugins as $req ) {
					if ( is_array( $req ) ) {
						// Format A: [ {"slug": "...", "name": "...", "file": "..."} ]
						$plugin_slug = ! empty( $req['slug'] ) ? sanitize_key( $req['slug'] ) : '';
						
						// Si no tiene slug, lo extraemos del directorio de la clave "file"
						if ( empty( $plugin_slug ) && ! empty( $req['file'] ) ) {
							$plugin_slug = sanitize_key( dirname( $req['file'] ) );
						}
						
						$plugin_name = ! empty( $req['name'] ) ? sanitize_text_field( $req['name'] ) : ( ! empty( $plugin_slug ) ? ucfirst( $plugin_slug ) : '' );
						$version     = ! empty( $req['version'] ) ? sanitize_text_field( $req['version'] ) : '';
						
						if ( ! empty( $plugin_slug ) ) {
							$required_plugins[] = array(
								'slug'    => $plugin_slug,
								'name'    => $plugin_name,
								'version' => $version,
							);
						}
					} elseif ( is_string( $req ) ) {
						// Format C: [ "elementor", "woocommerce" ]
						$plugin_slug = sanitize_key( $req );
						$required_plugins[] = array(
							'slug'    => $plugin_slug,
							'name'    => ucfirst( $plugin_slug ),
							'version' => '',
						);
					}
				}
			}
		}

		return $required_plugins;
	}

	/**
	 * Obtiene el listado de kits instalados enriquecido con el estado de sus plugins requeridos.
	 */
	public function get_kits_with_plugin_status() {
		$kits = get_option( 'wpat_envato_kits', array() );
		if ( empty( $kits ) ) {
			return $kits;
		}

		$changed = false;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		foreach ( $kits as $slug => $kit ) {
			$folder = isset( $kit['folder'] ) ? $kit['folder'] : '';
			$manifest_keys = array();

			// Auto-corregir preview_url y thumbnail si son rutas relativas y no absolutas
			if ( ! empty( $kit['templates'] ) ) {
				foreach ( $kit['templates'] as $idx => $tpl ) {
					// Corregir thumbnail
					if ( ! empty( $tpl['thumbnail'] ) && ! filter_var( $tpl['thumbnail'], FILTER_VALIDATE_URL ) ) {
						$kits[ $slug ]['templates'][ $idx ]['thumbnail'] = $this->upload_base_url . '/' . ( $folder ? $folder . '/' : '' ) . ltrim( $tpl['thumbnail'], '/' );
						$changed = true;
					}
					// Corregir preview_url
					if ( ! empty( $tpl['preview_url'] ) && ! filter_var( $tpl['preview_url'], FILTER_VALIDATE_URL ) ) {
						$kits[ $slug ]['templates'][ $idx ]['preview_url'] = $this->upload_base_url . '/' . ( $folder ? $folder . '/' : '' ) . ltrim( $tpl['preview_url'], '/' );
						$changed = true;
					}
				}
				$kit = $kits[ $slug ];
			}

			// Autolimpiar cualquier residuo de ajustes de Elementor que se haya guardado previamente en la BD
			if ( ! empty( $kit['required_plugins'] ) ) {
				$filtered_plugins = array();
				foreach ( $kit['required_plugins'] as $req ) {
					if ( isset( $req['slug'] ) && 0 === strpos( $req['slug'], 'elementor_setting_' ) ) {
						continue; // omitir
					}
					$filtered_plugins[] = $req;
				}
				$kit['required_plugins'] = $filtered_plugins;
				$kits[ $slug ]['required_plugins'] = $filtered_plugins;
			}

			if ( ! empty( $folder ) ) {
				$manifest_path = $this->upload_base_dir . '/' . $folder . '/manifest.json';
				
				if ( ! file_exists( $manifest_path ) ) {
					$found = $this->find_manifest_file( $this->upload_base_dir . '/' . $folder );
					if ( $found ) {
						$manifest_path = $found;
					}
				}

				if ( file_exists( $manifest_path ) ) {
					$manifest_content = file_get_contents( $manifest_path );
					$manifest_data    = json_decode( $manifest_content, true );
					
					if ( is_array( $manifest_data ) ) {
						$manifest_keys = array_keys( $manifest_data );
						
						// Si la lista de plugins en la BD está vacía, intentamos extraer de nuevo buscando sinónimos o auto-detectando
						if ( empty( $kit['required_plugins'] ) ) {
							$required_plugins = $this->extract_required_plugins( $manifest_data );
							
							// Si sigue vacía tras buscar sinónimos, ejecutamos el auto-detector escaneando los archivos JSON
							if ( empty( $required_plugins ) && ! empty( $kit['templates'] ) ) {
								$required_plugins = $this->auto_detect_kit_required_plugins( $folder, $kit['templates'] );
							}

							if ( ! empty( $required_plugins ) ) {
								$kits[ $slug ]['required_plugins'] = $required_plugins;
								$kit['required_plugins'] = $required_plugins;
								$changed = true;
							}
						}
					}
				}
			}

			// Inyectar claves crudas del manifest para depurar en consola JS
			$kits[ $slug ]['manifest_keys'] = $manifest_keys;

			// Enriquecer estados de instalación y activación
			if ( ! empty( $kit['required_plugins'] ) ) {
				foreach ( $kit['required_plugins'] as $idx => $req ) {
					$plugin_path = $this->get_plugin_main_file_path( $req['slug'] );
					$installed   = ( false !== $plugin_path );
					$active      = $installed ? is_plugin_active( $plugin_path ) : false;
					
					$kits[ $slug ]['required_plugins'][ $idx ]['installed'] = $installed;
					$kits[ $slug ]['required_plugins'][ $idx ]['active']    = $active;
				}
			}
		}

		if ( $changed ) {
			update_option( 'wpat_envato_kits', $kits );
		}

		// Añadir configuraciones recomendadas de Elementor al listado de requerimientos EN MEMORIA (no se guardará en BD)
		foreach ( $kits as $slug => $kit ) {
			$disable_colors = get_option( 'elementor_disable_color_schemes' );
			$disable_fonts  = get_option( 'elementor_disable_typography_schemes' );
			
			$kits[ $slug ]['required_plugins'][] = array(
				'slug'      => 'elementor_setting_colors',
				'name'      => 'Ajuste: Desactivar esquemas de colores por defecto de Elementor (Recomendado)',
				'is_plugin' => false,
				'installed' => true,
				'active'    => ( 'yes' === $disable_colors ),
			);
			
			$kits[ $slug ]['required_plugins'][] = array(
				'slug'      => 'elementor_setting_fonts',
				'name'      => 'Ajuste: Desactivar esquemas de tipografías por defecto de Elementor (Recomendado)',
				'is_plugin' => false,
				'installed' => true,
				'active'    => ( 'yes' === $disable_fonts ),
			);
		}

		return $kits;
	}

	/**
	 * Busca de forma recursiva todos los widgetType dentro de un array de Elementor.
	 */
	private function find_widget_types_recursive( $elements, &$widget_types ) {
		if ( ! is_array( $elements ) ) {
			return;
		}
		foreach ( $elements as $el ) {
			if ( isset( $el['elType'] ) && 'widget' === $el['elType'] && ! empty( $el['widgetType'] ) ) {
				$widget_types[] = $el['widgetType'];
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$this->find_widget_types_recursive( $el['elements'], $widget_types );
			}
		}
	}

	/**
	 * Escanea los archivos JSON de las plantillas de un kit para auto-detectar plugins requeridos.
	 */
	private function auto_detect_kit_required_plugins( $kit_folder, $templates ) {
		$detected_slugs = array();
		$required_plugins = array();
		$widget_types = array();

		if ( empty( $templates ) || ! is_array( $templates ) ) {
			return $required_plugins;
		}

		foreach ( $templates as $tpl ) {
			$file_path = $this->upload_base_dir . '/' . $kit_folder . '/' . $tpl['file'];
			if ( file_exists( $file_path ) ) {
				$content = file_get_contents( $file_path );
				$data    = json_decode( $content, true );
				if ( is_array( $data ) ) {
					$elements = isset( $data['content'] ) ? $data['content'] : $data;
					$this->find_widget_types_recursive( $elements, $widget_types );
				}
			}
		}

		$widget_types = array_unique( $widget_types );

		// Mapeo de prefijos de widget a plugin
		$mappings = array(
			'elementskit-' => array( 'slug' => 'elementskit-lite', 'name' => 'ElementsKit Elementor Addons' ),
			'ekit-'        => array( 'slug' => 'elementskit-lite', 'name' => 'ElementsKit Elementor Addons' ),
			'hfe-'         => array( 'slug' => 'header-footer-elementor', 'name' => 'Elementor Header Footer Builder' ),
			'eael-'        => array( 'slug' => 'essential-addons-for-elementor-lite', 'name' => 'Essential Addons for Elementor' ),
			'premium-'     => array( 'slug' => 'premium-addons-for-elementor', 'name' => 'Premium Addons for Elementor' ),
			'metform-'     => array( 'slug' => 'metform', 'name' => 'MetForm Elementor Contact Form Builder' ),
		);

		// Lista de widgets exclusivos de Elementor Pro
		$pro_widgets = array(
			'slides', 'portfolio', 'posts', 'nav-menu', 'form', 'login', 'search-form', 
			'blockquote', 'media-carousel', 'testimonial-carousel', 'reviews', 'price-list', 
			'price-table', 'gallery', 'flip-box', 'call-to-action', 'countdown', 'share-buttons', 
			'author-box', 'post-navigation', 'post-info', 'post-title', 'post-excerpt', 
			'post-content', 'archive-posts', 'archive-title', 'sitemap', 'table-of-contents', 'lottie'
		);

		$requires_woocommerce = false;
		$requires_pro = false;

		foreach ( $widget_types as $wt ) {
			// Comprobar prefijos
			foreach ( $mappings as $prefix => $plugin ) {
				if ( 0 === strpos( $wt, $prefix ) ) {
					if ( ! in_array( $plugin['slug'], $detected_slugs ) ) {
						$detected_slugs[] = $plugin['slug'];
						$required_plugins[] = array(
							'slug'    => $plugin['slug'],
							'name'    => $plugin['name'],
							'version' => '',
						);
					}
				}
			}

			// Comprobar WooCommerce
			if ( 0 === strpos( $wt, 'wc-' ) || 0 === strpos( $wt, 'product-' ) || 0 === strpos( $wt, 'woocommerce-' ) ) {
				$requires_woocommerce = true;
			}

			// Comprobar Elementor Pro
			if ( in_array( $wt, $pro_widgets ) ) {
				$requires_pro = true;
			}
		}

		if ( $requires_woocommerce && ! in_array( 'woocommerce', $detected_slugs ) ) {
			$detected_slugs[] = 'woocommerce';
			$required_plugins[] = array(
				'slug'    => 'woocommerce',
				'name'    => 'WooCommerce',
				'version' => '',
			);
		}

		if ( $requires_pro && ! in_array( 'elementor-pro', $detected_slugs ) ) {
			$detected_slugs[] = 'elementor-pro';
			$required_plugins[] = array(
				'slug'    => 'elementor-pro',
				'name'    => 'Elementor Pro',
				'version' => '',
			);
		}

		return $required_plugins;
	}

	/**
	 * AJAX: Activa una configuración recomendada de Elementor.
	 */
	public function ajax_enable_elementor_setting() {
		check_ajax_referer( 'wpat_envato_importer_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$setting = isset( $_POST['setting_slug'] ) ? sanitize_key( $_POST['setting_slug'] ) : '';

		if ( 'elementor_setting_colors' === $setting ) {
			update_option( 'elementor_disable_color_schemes', 'yes' );
			wp_send_json_success( array( 'message' => 'Esquema de colores por defecto desactivado.' ) );
		} elseif ( 'elementor_setting_fonts' === $setting ) {
			update_option( 'elementor_disable_typography_schemes', 'yes' );
			wp_send_json_success( array( 'message' => 'Esquema de tipografías por defecto desactivado.' ) );
		}

		wp_send_json_error( array( 'message' => 'Configuración no válida.' ) );
	}
}

// Inicializar el módulo
WPAT_Envato_Importer::get_instance();

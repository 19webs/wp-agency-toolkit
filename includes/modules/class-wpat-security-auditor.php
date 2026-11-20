<?php
/**
 * Módulo: Auditor de Seguridad e Integridad de Archivos - WP Agency Toolkit
 *
 * Escaneo ligero bajo demanda para detección de archivos extraños, scripts en uploads,
 * verificación de integridad del Core de WordPress y checklist de blindaje de la suite.
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Security_Auditor {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Security_Auditor|null
	 */
	private static $instance = null;

	/**
	 * Clave para almacenar el último resultado en opciones de WordPress.
	 */
	const OPTION_LAST_SCAN = 'wpat_security_auditor_last_scan';

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Security_Auditor
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
		// Endpoints AJAX para el escaneo y para aplicar correcciones rápidas
		add_action( 'wp_ajax_wpat_security_auditor_scan', array( $this, 'ajax_run_scan' ) );
		add_action( 'wp_ajax_wpat_security_auditor_fix', array( $this, 'ajax_apply_fix' ) );
	}

	/**
	 * AJAX: Ejecuta la auditoría completa bajo demanda.
	 */
	public function ajax_run_scan() {
		check_ajax_referer( 'wpat_security_auditor_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes para realizar esta acción.', 'wp-agency-toolkit' ) ) );
		}

		$results = $this->run_full_audit();

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Aplica una recomendación de seguridad de la suite en un clic.
	 */
	public function ajax_apply_fix() {
		check_ajax_referer( 'wpat_security_auditor_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'wp-agency-toolkit' ) ) );
		}

		$rule_key = isset( $_POST['rule_key'] ) ? sanitize_key( $_POST['rule_key'] ) : '';
		if ( empty( $rule_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Regla no especificada.', 'wp-agency-toolkit' ) ) );
		}

		$main_instance    = WPAT_Main::get_instance();
		$current_settings = $main_instance->get_settings();
		$applied_label    = '';

		switch ( $rule_key ) {
			case 'sec_disable_xmlrpc':
				$current_settings['sec_disable_xmlrpc'] = '1';
				$applied_label = __( 'Bloqueo de XML-RPC activado correctamente.', 'wp-agency-toolkit' );
				break;

			case 'sec_block_uploads_php':
				$current_settings['sec_block_uploads_php'] = '1';
				// Sincronizar regla en .htaccess de uploads
				if ( class_exists( 'WPAT_Security_Hardening' ) ) {
					WPAT_Security_Hardening::get_instance()->sync_uploads_htaccess();
				}
				$applied_label = __( 'Bloqueo de ejecución PHP en uploads activado correctamente.', 'wp-agency-toolkit' );
				break;

			case 'sec_disable_user_enum':
				$current_settings['sec_disable_user_enum'] = '1';
				$applied_label = __( 'Bloqueo de enumeración de usuarios activado correctamente.', 'wp-agency-toolkit' );
				break;

			case 'sec_disable_file_edit':
				$current_settings['sec_disable_file_edit'] = '1';
				$applied_label = __( 'Desactivación de editores de archivos en el panel activada.', 'wp-agency-toolkit' );
				break;

			case 'sec_hide_wp_version':
				$current_settings['sec_hide_wp_version'] = '1';
				$applied_label = __( 'Ocultación de la versión de WordPress activada.', 'wp-agency-toolkit' );
				break;

			case 'sec_block_admin_user':
				$current_settings['sec_block_admin_user'] = '1';
				$applied_label = __( 'Bloqueo de usuario "admin" activado.', 'wp-agency-toolkit' );
				break;

			case 'sec_security_headers':
				$current_settings['sec_security_headers'] = '1';
				$applied_label = __( 'Cabeceras HTTP de seguridad activadas.', 'wp-agency-toolkit' );
				break;

			case 'disable-comments':
				$current_settings['disable-comments']        = '1';
				$current_settings['disable_comments_mode']   = 'global';
				$current_settings['disable_comments_global'] = '1';
				$applied_label = __( 'Módulo Deshabilitar Comentarios activado en modo Global.', 'wp-agency-toolkit' );
				break;

			case 'bot-blocker':
				$current_settings['bot-blocker'] = '1';
				$applied_label = __( 'Bloqueador de bots maliciosos activado.', 'wp-agency-toolkit' );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Acción desconocida.', 'wp-agency-toolkit' ) ) );
				break;
		}

		update_option( 'wpat_settings', $current_settings );

		// Re-ejecutar auditoría para devolver los datos actualizados
		$updated_audit = $this->run_full_audit();

		wp_send_json_success( array(
			'message' => $applied_label,
			'audit'   => $updated_audit,
		) );
	}

	/**
	 * Ejecuta el análisis completo del sistema.
	 *
	 * @return array
	 */
	public function run_full_audit() {
		$time_start = microtime( true );

		$root_scan     = $this->scan_root_directory();
		$uploads_scan  = $this->scan_uploads_directory();
		$core_scan     = $this->verify_core_integrity();
		$config_scan   = $this->check_system_and_config();
		$hardening     = $this->check_hardening_checklist();

		$elapsed = round( microtime( true ) - $time_start, 2 );

		// Calcular métricas
		$suspicious_count = count( $root_scan['suspicious_files'] ) + count( $uploads_scan['suspicious_files'] ) + count( $core_scan['issues'] );
		$warnings_count   = count( $config_scan['warnings'] );
		$hardening_active = 0;
		$hardening_total  = count( $hardening['items'] );

		foreach ( $hardening['items'] as $item ) {
			if ( ! empty( $item['is_active'] ) ) {
				$hardening_active++;
			}
		}

		// Calcular puntuación de seguridad (0 a 100)
		$score = 100;
		if ( count( $uploads_scan['suspicious_files'] ) > 0 ) {
			$score -= 40;
		}
		if ( count( $root_scan['suspicious_files'] ) > 0 ) {
			$score -= 25;
		}
		if ( count( $core_scan['issues'] ) > 0 ) {
			$score -= 25;
		}
		if ( $warnings_count > 0 ) {
			$score -= ( $warnings_count * 5 );
		}
		$hardening_penalty = ( $hardening_total - $hardening_active ) * 3;
		$score = max( 0, min( 100, $score - $hardening_penalty ) );

		$results = array(
			'timestamp'         => time(),
			'date_formatted'    => current_time( 'd/m/Y H:i:s' ),
			'elapsed_seconds'   => $elapsed,
			'score'             => $score,
			'suspicious_count'  => $suspicious_count,
			'warnings_count'    => $warnings_count,
			'hardening_active'  => $hardening_active,
			'hardening_total'   => $hardening_total,
			'root_scan'         => $root_scan,
			'uploads_scan'      => $uploads_scan,
			'core_scan'         => $core_scan,
			'config_scan'       => $config_scan,
			'hardening'         => $hardening,
		);

		update_option( self::OPTION_LAST_SCAN, $results, false );

		return $results;
	}

	/**
	 * Obtiene el resultado del último escaneo almacenado.
	 *
	 * @return array|null
	 */
	public static function get_last_scan_data() {
		$data = get_option( self::OPTION_LAST_SCAN, null );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * 1. Escaneo rápido de la carpeta raíz de WordPress (ABSPATH).
	 *
	 * @return array
	 */
	public function scan_root_directory() {
		$root_path = ABSPATH;
		$official_files = array(
			'index.php',
			'wp-config.php',
			'wp-config-sample.php',
			'wp-settings.php',
			'wp-load.php',
			'wp-login.php',
			'wp-cron.php',
			'wp-blog-header.php',
			'wp-comments-post.php',
			'wp-links-opml.php',
			'wp-mail.php',
			'wp-signup.php',
			'wp-trackback.php',
			'wp-activate.php',
			'xmlrpc.php',
			'readme.html',
			'license.txt',
			'.htaccess',
			'robots.txt',
			'favicon.ico',
			'sitemap.xml',
			'sitemap.xml.gz',
			'wp-sitemap.xml',
			'wp-sitemap-index.xml',
			'web.config',
			'nginx.conf',
			'.user.ini',
			'php.ini',
			'error_log',
		);

		$official_dirs = array(
			'wp-admin',
			'wp-includes',
			'wp-content',
			'.well-known',
		);

		$suspicious = array();
		$total_files = 0;

		if ( is_dir( $root_path ) && is_readable( $root_path ) ) {
			$items = scandir( $root_path );
			if ( is_array( $items ) ) {
				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}

					$full_path = $root_path . $item;
					$is_dir    = is_dir( $full_path );

					if ( $is_dir ) {
						if ( ! in_array( $item, $official_dirs, true ) && 0 !== strpos( $item, '.' ) ) {
							// Directorio no estándar en la raíz
							$suspicious[] = array(
								'name'        => $item,
								'path'        => $item . '/',
								'type'        => 'directory',
								'size'        => '-',
								'modified'    => date_i18n( 'd/m/Y H:i', filemtime( $full_path ) ),
								'severity'    => 'medium',
								'explanation' => __( 'Carpeta no estándar en la raíz de WordPress. Revisa si es legítima o añadida por software externo.', 'wp-agency-toolkit' ),
							);
						}
						continue;
					}

					$total_files++;

					// Si es un archivo conocido oficial, ignorar
					if ( in_array( strtolower( $item ), $official_files, true ) ) {
						continue;
					}

					$ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
					$size_str = size_format( filesize( $full_path ), 1 );
					$mod_str  = date_i18n( 'd/m/Y H:i', filemtime( $full_path ) );

					// Archivos ejecutables sospechosos (.php, .phtml, etc.)
					if ( in_array( $ext, array( 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'inc' ), true ) ) {
						$suspicious[] = array(
							'name'        => $item,
							'path'        => '/' . $item,
							'type'        => 'executable',
							'size'        => $size_str,
							'modified'    => $mod_str,
							'severity'    => 'high',
							'explanation' => __( 'Archivo PHP desconocido en la raíz. Puede tratarse de un backdoor, instalador huérfano o script malicioso.', 'wp-agency-toolkit' ),
						);
					} elseif ( in_array( $ext, array( 'sql', 'zip', 'tar', 'gz', 'bak', 'old', 'swp' ), true ) ) {
						// Archivos de respaldo o volcados expuestos
						$suspicious[] = array(
							'name'        => $item,
							'path'        => '/' . $item,
							'type'        => 'backup_dump',
							'size'        => $size_str,
							'modified'    => $mod_str,
							'severity'    => 'high',
							'explanation' => __( 'Archivo de respaldo o volcado accesible públicamente. Riesgo crítico de filtración de datos de clientes o contraseñas.', 'wp-agency-toolkit' ),
						);
					}
				}
			}
		}

		return array(
			'total_scanned'    => $total_files,
			'suspicious_files' => $suspicious,
			'status'           => empty( $suspicious ) ? 'clean' : 'warning',
		);
	}

	/**
	 * 2. Escaneo de scripts ejecutables en la carpeta de subidas (uploads).
	 *
	 * @return array
	 */
	public function scan_uploads_directory() {
		$upload_dir = wp_upload_dir();
		$basedir    = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';
		$suspicious = array();
		$total_scanned = 0;
		$htaccess_protects = false;

		if ( ! empty( $basedir ) && is_dir( $basedir ) && is_readable( $basedir ) ) {
			// Comprobar si existe el archivo .htaccess en uploads
			$htaccess_path = rtrim( $basedir, '/\\' ) . '/.htaccess';
			if ( file_exists( $htaccess_path ) && is_readable( $htaccess_path ) ) {
				$content = file_get_contents( $htaccess_path );
				if ( false !== strpos( $content, 'php' ) && ( false !== strpos( $content, 'Deny' ) || false !== strpos( $content, 'deny' ) ) ) {
					$htaccess_protects = true;
				}
			}

			// Búsqueda recursiva optimizada exclusivamente de extensiones ejecutables
			$forbidden_exts = array( 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'inc', 'cgi', 'pl', 'sh' );

			try {
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $basedir, RecursiveDirectoryIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::SELF_FIRST
				);

				$count = 0;
				foreach ( $iterator as $file ) {
					$count++;
					// Límite de seguridad de archivos analizados para evitar sobrecarga en bibliotecas gigantes
					if ( $count > 50000 ) {
						break;
					}

					if ( $file->isFile() ) {
						$ext = strtolower( $file->getExtension() );
						if ( in_array( $ext, $forbidden_exts, true ) ) {
							$filepath = $file->getPathname();
							$relative = str_replace( ABSPATH, '', $filepath );

							$suspicious[] = array(
								'name'        => $file->getFilename(),
								'path'        => $relative,
								'type'        => 'malware_script',
								'size'        => size_format( $file->getSize(), 1 ),
								'modified'    => date_i18n( 'd/m/Y H:i', $file->getMTime() ),
								'severity'    => 'critical',
								'explanation' => __( '¡Script ejecutable dentro de la carpeta de medios! Es el método más común de inyección de backdoors y webshells.', 'wp-agency-toolkit' ),
							);
						}
					}
				}
				$total_scanned = $count;
			} catch ( Exception $e ) {
				// Manejo silencioso en caso de permisos restringidos en alguna subcarpeta
			}
		}

		return array(
			'total_scanned'     => $total_scanned,
			'suspicious_files'  => $suspicious,
			'htaccess_protects' => $htaccess_protects,
			'status'            => empty( $suspicious ) ? 'clean' : 'critical',
		);
	}

	/**
	 * 3. Verificación de integridad con Checksums oficiales de WordPress.org.
	 *
	 * @return array
	 */
	public function verify_core_integrity() {
		global $wp_version, $wp_local_package;

		$locale = get_locale();
		$transient_key = 'wpat_core_checksums_' . sanitize_key( $wp_version ) . '_' . sanitize_key( $locale );
		$checksums = get_transient( $transient_key );

		if ( false === $checksums || ! is_array( $checksums ) ) {
			$api_url = "https://api.wordpress.org/core/checksums/1.0/?version={$wp_version}&locale={$locale}";
			$response = wp_remote_get( $api_url, array( 'timeout' => 5, 'sslverify' => true ) );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				// Fallback a inglés estadounidense si el paquete local no tiene checksums específicos
				$api_url = "https://api.wordpress.org/core/checksums/1.0/?version={$wp_version}&locale=en_US";
				$response = wp_remote_get( $api_url, array( 'timeout' => 5, 'sslverify' => true ) );
			}

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( isset( $body['checksums'] ) && is_array( $body['checksums'] ) ) {
					$checksums = $body['checksums'];
					set_transient( $transient_key, $checksums, 12 * HOUR_IN_SECONDS );
				}
			}
		}

		$issues = array();
		$checked_count = 0;

		if ( is_array( $checksums ) && ! empty( $checksums ) ) {
			// Comprobar archivos clave y carpetas críticas del Core (wp-admin, wp-includes y archivos raíz)
			foreach ( $checksums as $file => $expected_hash ) {
				// No comprobar wp-content (es específico del sitio del usuario)
				if ( 0 === strpos( $file, 'wp-content/' ) ) {
					continue;
				}

				$local_path = ABSPATH . $file;
				$checked_count++;

				if ( ! file_exists( $local_path ) ) {
					$issues[] = array(
						'file'        => $file,
						'status'      => 'missing',
						'severity'    => 'medium',
						'explanation' => __( 'Archivo original del Core de WordPress ausente.', 'wp-agency-toolkit' ),
					);
					continue;
				}

				$local_hash = md5_file( $local_path );
				if ( $local_hash !== $expected_hash ) {
					$issues[] = array(
						'file'        => $file,
						'status'      => 'modified',
						'severity'    => 'critical',
						'explanation' => __( 'El archivo oficial del Core ha sido modificado respecto a la versión oficial de WordPress.org. Alto indicio de inyección de código o malware.', 'wp-agency-toolkit' ),
					);
				}
			}
		}

		return array(
			'checked_count' => $checked_count,
			'issues'        => $issues,
			'status'        => empty( $issues ) ? 'clean' : 'critical',
			'wp_version'    => $wp_version,
		);
	}

	/**
	 * 4. Comprobaciones de configuración de WordPress y permisos del sistema.
	 *
	 * @return array
	 */
	public function check_system_and_config() {
		$warnings = array();

		// A. Registro público de usuarios
		$users_can_register = (bool) get_option( 'users_can_register' );
		$default_role       = get_option( 'default_role' );
		if ( $users_can_register ) {
			$warnings[] = array(
				'type'        => 'registration_open',
				'severity'    => ( 'administrator' === $default_role ) ? 'critical' : 'medium',
				'title'       => __( 'El registro de usuarios está abierto a cualquiera', 'wp-agency-toolkit' ),
				'description' => sprintf(
					__( 'En Ajustes > Generales la opción "Cualquiera puede registrarse" está marcada. El rol asignado por defecto es: "%s". Si no tienes una tienda o membresía, desactívala para frenar la creación masiva de cuentas de spam.', 'wp-agency-toolkit' ),
					esc_html( $default_role )
				),
				'action_url'  => admin_url( 'options-general.php' ),
			);
		}

		// B. Detección de usuario 'admin'
		if ( username_exists( 'admin' ) ) {
			$admin_user = get_user_by( 'login', 'admin' );
			$is_admin   = $admin_user && in_array( 'administrator', (array) $admin_user->roles, true );
			if ( $is_admin ) {
				$warnings[] = array(
					'type'        => 'admin_user_exists',
					'severity'    => 'high',
					'title'       => __( 'Existe un usuario administrador con el nombre "admin"', 'wp-agency-toolkit' ),
					'description' => __( 'El nombre "admin" es el objetivo número uno en ataques automatizados de fuerza bruta y diccionario. Te recomendamos crear otro usuario administrador y eliminar el usuario "admin".', 'wp-agency-toolkit' ),
					'action_url'  => admin_url( 'users.php' ),
				);
			}
		}

		// C. Permisos de wp-config.php
		$wp_config_path = file_exists( ABSPATH . 'wp-config.php' ) ? ABSPATH . 'wp-config.php' : ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ? dirname( ABSPATH ) . '/wp-config.php' : '' );
		if ( ! empty( $wp_config_path ) ) {
			$perms = fileperms( $wp_config_path );
			// Comprobar si es escribible por cualquiera (otros)
			if ( ( $perms & 0x0002 ) || is_writable( $wp_config_path ) ) {
				$warnings[] = array(
					'type'        => 'wp_config_writable',
					'severity'    => 'medium',
					'title'       => __( 'El archivo wp-config.php tiene permisos de escritura permisivos', 'wp-agency-toolkit' ),
					'description' => __( 'Se recomienda ajustar los permisos de wp-config.php a 0600 o 0640 mediante tu panel de hosting o FTP para restringir la lectura y edición no autorizada.', 'wp-agency-toolkit' ),
					'action_url'  => '',
				);
			}
		}

		return array(
			'warnings' => $warnings,
			'status'   => empty( $warnings ) ? 'clean' : 'warning',
		);
	}

	/**
	 * 5. Checklist de blindaje con los módulos de WP Agency Toolkit.
	 *
	 * @return array
	 */
	public function check_hardening_checklist() {
		$settings = WPAT_Main::get_instance()->get_settings();

		$items = array(
			array(
				'key'         => 'sec_disable_xmlrpc',
				'title'       => __( 'Desactivar Protocolo XML-RPC', 'wp-agency-toolkit' ),
				'description' => __( 'Cierra el archivo xmlrpc.php, bloqueando ataques de fuerza bruta masiva multicall e inyección de entradas/spam.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['sec_disable_xmlrpc'] ) && '1' === (string) $settings['sec_disable_xmlrpc'],
				'risk_level'  => 'high',
				'can_auto_fix'=> true,
				'target_mod'  => 'security-hardening',
			),
			array(
				'key'         => 'sec_block_uploads_php',
				'title'       => __( 'Bloquear Ejecución de PHP en Uploads', 'wp-agency-toolkit' ),
				'description' => __( 'Impide mediante .htaccess que el servidor web ejecute cualquier script PHP subido a la biblioteca de medios.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['sec_block_uploads_php'] ) && '1' === (string) $settings['sec_block_uploads_php'],
				'risk_level'  => 'critical',
				'can_auto_fix'=> true,
				'target_mod'  => 'security-hardening',
			),
			array(
				'key'         => 'sec_disable_user_enum',
				'title'       => __( 'Desactivar Enumeración de Usuarios', 'wp-agency-toolkit' ),
				'description' => __( 'Impide que atacantes extraigan los nombres reales de los usuarios escaneando ?author=1 o mediante la API REST.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['sec_disable_user_enum'] ) && '1' === (string) $settings['sec_disable_user_enum'],
				'risk_level'  => 'high',
				'can_auto_fix'=> true,
				'target_mod'  => 'security-hardening',
			),
			array(
				'key'         => 'hide-login',
				'title'       => __( 'Ocultar Acceso de Login (/wp-login.php)', 'wp-agency-toolkit' ),
				'description' => __( 'Cambia la URL por defecto del login a un slug secreto, neutralizando el 99% de ataques automatizados a wp-login.php.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['hide-login'] ) && '1' === (string) $settings['hide-login'],
				'risk_level'  => 'high',
				'can_auto_fix'=> false,
				'target_mod'  => 'hide-login',
			),
			array(
				'key'         => 'disable-comments',
				'title'       => __( 'Deshabilitar Comentarios Globales & Anti-Spam', 'wp-agency-toolkit' ),
				'description' => __( 'Cierra la inyección de comentarios por REST API y wp-comments-post.php y bloquea pingbacks de spambots.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['disable-comments'] ) && '1' === (string) $settings['disable-comments'],
				'risk_level'  => 'medium',
				'can_auto_fix'=> true,
				'target_mod'  => 'disable-comments',
			),
			array(
				'key'         => 'sec_disable_file_edit',
				'title'       => __( 'Desactivar Editores de Código en el Panel', 'wp-agency-toolkit' ),
				'description' => __( 'Desactiva los editores de plugins y temas de WordPress para evitar inyecciones si una cuenta se ve comprometida.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['sec_disable_file_edit'] ) && '1' === (string) $settings['sec_disable_file_edit'],
				'risk_level'  => 'medium',
				'can_auto_fix'=> true,
				'target_mod'  => 'security-hardening',
			),
			array(
				'key'         => 'sec_hide_wp_version',
				'title'       => __( 'Ocultar la Versión de WordPress', 'wp-agency-toolkit' ),
				'description' => __( 'Elimina las etiquetas con la versión del core en el código HTML, feeds y cabeceras para dificultar escaneos automáticos.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['sec_hide_wp_version'] ) && '1' === (string) $settings['sec_hide_wp_version'],
				'risk_level'  => 'low',
				'can_auto_fix'=> true,
				'target_mod'  => 'security-hardening',
			),
			array(
				'key'         => 'sec_block_admin_user',
				'title'       => __( 'Bloquear Intentos con Usuario "admin"', 'wp-agency-toolkit' ),
				'description' => __( 'Deniega cualquier intento de login o registro con el usuario "admin" a nivel de backend.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['sec_block_admin_user'] ) && '1' === (string) $settings['sec_block_admin_user'],
				'risk_level'  => 'medium',
				'can_auto_fix'=> true,
				'target_mod'  => 'security-hardening',
			),
			array(
				'key'         => 'sec_security_headers',
				'title'       => __( 'Cabeceras HTTP de Seguridad', 'wp-agency-toolkit' ),
				'description' => __( 'Añade X-Frame-Options, X-Content-Type-Options y Referrer-Policy contra Clickjacking y MIME sniffing.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['sec_security_headers'] ) && '1' === (string) $settings['sec_security_headers'],
				'risk_level'  => 'medium',
				'can_auto_fix'=> true,
				'target_mod'  => 'security-hardening',
			),
			array(
				'key'         => 'bot-blocker',
				'title'       => __( 'Bloqueador de Bots Anti-DDoS', 'wp-agency-toolkit' ),
				'description' => __( 'Limita peticiones abusivas por segundo para proteger el consumo de CPU y memoria ante ataques.', 'wp-agency-toolkit' ),
				'is_active'   => isset( $settings['bot-blocker'] ) && '1' === (string) $settings['bot-blocker'],
				'risk_level'  => 'medium',
				'can_auto_fix'=> true,
				'target_mod'  => 'bot-blocker',
			),
		);

		return array(
			'items' => $items,
		);
	}
}

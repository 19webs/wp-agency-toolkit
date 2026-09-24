<?php
/**
 * Módulo: Generador de Códigos QR & Enlaces Directos - WP Agency Toolkit
 *
 * Genera y gestiona Códigos QR de alta resolución (SVG y PNG) para WhatsApp,
 * Enlaces de venta, redes Wi-Fi, contactos vCard, emails, llamadas y Bizum.
 * Incluye personalización de colores, logos centrales y shortcodes embebibles.
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_QR_Generator {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_QR_Generator
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_QR_Generator
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
		// Registrar Shortcode [wpat_qr]
		add_shortcode( 'wpat_qr', array( $this, 'render_qr_shortcode' ) );

		// Acciones AJAX para gestión de QRs en el panel
		add_action( 'wp_ajax_wpat_save_qr_code', array( $this, 'ajax_save_qr_code' ) );
		add_action( 'wp_ajax_wpat_delete_qr_code', array( $this, 'ajax_delete_qr_code' ) );
		add_action( 'wp_ajax_wpat_get_qr_code', array( $this, 'ajax_get_qr_code' ) );
	}

	/**
	 * Obtiene todos los QRs guardados.
	 *
	 * @return array
	 */
	public function get_saved_qrs() {
		$qrs = get_option( 'wpat_saved_qr_codes', array() );
		return is_array( $qrs ) ? $qrs : array();
	}

	/**
	 * Guarda o actualiza un QR vía AJAX.
	 */
	public function ajax_save_qr_code() {
		check_ajax_referer( 'wpat_qr_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ) );
		}

		$id        = isset( $_POST['qr_id'] ) ? sanitize_title( $_POST['qr_id'] ) : '';
		$name      = isset( $_POST['qr_name'] ) ? sanitize_text_field( $_POST['qr_name'] ) : 'Código QR';
		$type      = isset( $_POST['qr_type'] ) ? sanitize_key( $_POST['qr_type'] ) : 'url';
		$content   = isset( $_POST['qr_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['qr_content'] ) ) : '';
		$fg_color  = isset( $_POST['qr_fg_color'] ) ? sanitize_hex_color( $_POST['qr_fg_color'] ) : '#000000';
		$bg_color  = isset( $_POST['qr_bg_color'] ) ? sanitize_hex_color( $_POST['qr_bg_color'] ) : '#ffffff';
		$size      = isset( $_POST['qr_size'] ) ? absint( $_POST['qr_size'] ) : 256;
		$logo_type = isset( $_POST['qr_logo_type'] ) ? sanitize_key( $_POST['qr_logo_type'] ) : 'none';
		$logo_url  = isset( $_POST['qr_logo_url'] ) ? esc_url_raw( $_POST['qr_logo_url'] ) : '';
		$ec_level  = isset( $_POST['qr_ec_level'] ) && in_array( $_POST['qr_ec_level'], array( 'L', 'M', 'Q', 'H' ), true ) ? $_POST['qr_ec_level'] : 'M';
		
		// Datos estructurados según el tipo
		$extra_data = isset( $_POST['qr_extra'] ) && is_array( $_POST['qr_extra'] ) ? array_map( 'sanitize_text_field', $_POST['qr_extra'] ) : array();

		if ( empty( $id ) ) {
			$id = 'qr_' . uniqid();
		}

		$saved_qrs = $this->get_saved_qrs();
		
		$saved_qrs[ $id ] = array(
			'id'         => $id,
			'name'       => $name,
			'type'       => $type,
			'content'    => $content,
			'fg_color'   => $fg_color ? $fg_color : '#000000',
			'bg_color'   => $bg_color ? $bg_color : '#ffffff',
			'size'       => $size ? $size : 256,
			'logo_type'  => $logo_type,
			'logo_url'   => $logo_url,
			'ec_level'   => $ec_level,
			'extra'      => $extra_data,
			'updated_at' => current_time( 'mysql' ),
		);

		update_option( 'wpat_saved_qr_codes', $saved_qrs );

		ob_start();
		$this->render_saved_qrs_table( $saved_qrs );
		$html = ob_get_clean();

		wp_send_json_success( array(
			'message' => 'Código QR guardado correctamente.',
			'html'    => $html,
			'qr_id'   => $id,
		) );
	}

	/**
	 * Elimina un QR guardado vía AJAX.
	 */
	public function ajax_delete_qr_code() {
		check_ajax_referer( 'wpat_qr_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$id = isset( $_POST['qr_id'] ) ? sanitize_title( $_POST['qr_id'] ) : '';
		if ( ! empty( $id ) ) {
			$saved_qrs = $this->get_saved_qrs();
			if ( isset( $saved_qrs[ $id ] ) ) {
				unset( $saved_qrs[ $id ] );
				update_option( 'wpat_saved_qr_codes', $saved_qrs );
			}
		}

		ob_start();
		$this->render_saved_qrs_table( $this->get_saved_qrs() );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Obtiene los datos de un QR guardado para cargarlo en el editor vía AJAX.
	 */
	public function ajax_get_qr_code() {
		check_ajax_referer( 'wpat_qr_nonce_action', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'No tienes permisos.' ) );
		}

		$id = isset( $_POST['qr_id'] ) ? sanitize_title( $_POST['qr_id'] ) : '';
		$saved_qrs = $this->get_saved_qrs();

		if ( ! empty( $id ) && isset( $saved_qrs[ $id ] ) ) {
			wp_send_json_success( $saved_qrs[ $id ] );
		}

		wp_send_json_error( array( 'message' => 'Código QR no encontrado.' ) );
	}

	/**
	 * Renderiza las filas de la tabla de QRs guardados.
	 *
	 * @param array $qrs Lista de QRs.
	 */
	public function render_saved_qrs_table( $qrs = null ) {
		if ( null === $qrs ) {
			$qrs = $this->get_saved_qrs();
		}

		if ( empty( $qrs ) ) {
			?>
			<tr>
				<td colspan="6" style="text-align: center; padding: 30px 15px; color: #64748b; background: #f8fafc;">
					<div style="font-size: 28px; margin-bottom: 8px;">🔲</div>
					<strong>No hay códigos QR guardados todavía.</strong><br>
					<span style="font-size: 12px;">Crea y personaliza tu primer código QR con el generador interactivo superior.</span>
				</td>
			</tr>
			<?php
			return;
		}

		$type_labels = array(
			'whatsapp' => '💬 WhatsApp',
			'url'      => '🔗 Enlace / Web',
			'wifi'     => '📶 Red Wi-Fi',
			'vcard'    => '📇 Contacto vCard',
			'email'    => '✉️ Correo Email',
			'phone'    => '📞 Teléfono / SMS',
			'bizum'    => '💳 Bizum / Pago',
		);

		foreach ( $qrs as $id => $qr ) {
			$type_label = isset( $type_labels[ $qr['type'] ] ) ? $type_labels[ $qr['type'] ] : ucfirst( $qr['type'] );
			$shortcode  = '[wpat_qr id="' . esc_attr( $id ) . '"]';
			?>
			<tr data-qr-id="<?php echo esc_attr( $id ); ?>">
				<td style="width: 50px; text-align: center; padding: 10px;">
					<div class="wpat-table-qr-thumb" data-content="<?php echo esc_attr( $qr['content'] ); ?>" data-fg="<?php echo esc_attr( $qr['fg_color'] ); ?>" data-bg="<?php echo esc_attr( $qr['bg_color'] ); ?>" data-logo="<?php echo esc_attr( $qr['logo_type'] ); ?>" data-logo-url="<?php echo esc_attr( $qr['logo_url'] ); ?>" style="width: 44px; height: 44px; margin: 0 auto; background: #fff; border-radius: 4px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden;">
						<!-- Renderizado por JS -->
					</div>
				</td>
				<td style="padding: 10px; vertical-align: middle;">
					<strong><a href="#" class="wpat-edit-saved-qr" data-id="<?php echo esc_attr( $id ); ?>" style="color: var(--wpat-text); text-decoration: none; font-size: 13.5px;"><?php echo esc_html( $qr['name'] ); ?></a></strong>
					<div style="font-size: 11px; color: #64748b; margin-top: 2px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr( $qr['content'] ); ?>">
						<?php echo esc_html( $qr['content'] ); ?>
					</div>
				</td>
				<td style="padding: 10px; vertical-align: middle;">
					<span style="font-size: 11px; font-weight: 700; background: #e2e8f0; color: #334155; padding: 3px 7px; border-radius: 4px; display: inline-block;">
						<?php echo esc_html( $type_label ); ?>
					</span>
				</td>
				<td style="padding: 10px; vertical-align: middle;">
					<code style="font-size: 11px; background: #f1f5f9; padding: 3px 6px; border-radius: 4px; cursor: pointer;" class="wpat-copy-shortcode-btn" data-code="<?php echo esc_attr( $shortcode ); ?>" title="Clic para copiar">
						<?php echo esc_html( $shortcode ); ?>
					</code>
				</td>
				<td style="padding: 10px; vertical-align: middle; color: #64748b; font-size: 11.5px;">
					<?php echo isset( $qr['updated_at'] ) ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $qr['updated_at'] ) ) ) : '-'; ?>
				</td>
				<td style="padding: 10px; vertical-align: middle; text-align: right; white-space: nowrap;">
					<button type="button" class="button button-small wpat-download-qr-btn" data-id="<?php echo esc_attr( $id ); ?>" data-name="<?php echo esc_attr( $qr['name'] ); ?>" title="Descargar PNG/SVG" style="padding: 3px 6px;">
						<span class="dashicons dashicons-download" style="font-size: 14px; width:14px; height:14px; vertical-align: middle; margin-top:-2px;"></span> Descargar
					</button>
					<button type="button" class="button button-small wpat-edit-saved-qr" data-id="<?php echo esc_attr( $id ); ?>" title="Editar QR" style="padding: 3px 6px; margin-left: 2px;">
						<span class="dashicons dashicons-edit" style="font-size: 14px; width:14px; height:14px; vertical-align: middle; margin-top:-2px;"></span>
					</button>
					<button type="button" class="button button-small button-link-delete wpat-delete-saved-qr" data-id="<?php echo esc_attr( $id ); ?>" title="Eliminar QR" style="padding: 3px 6px; margin-left: 2px;">
						<span class="dashicons dashicons-trash" style="font-size: 14px; width:14px; height:14px; vertical-align: middle; margin-top:-2px; color:#ef4444;"></span>
					</button>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Renderiza el Shortcode [wpat_qr].
	 *
	 * Atributos soportados:
	 * - id: ID del QR guardado (ej. id="qr_123")
	 * - type: whatsapp, url, wifi, phone, email
	 * - phone: Teléfono para WhatsApp o llamada
	 * - message: Mensaje inicial de WhatsApp
	 * - url: URL directa
	 * - size: Tamaño en px (default: 200)
	 * - fg: Color principal (default: #000000)
	 * - bg: Color de fondo (default: #ffffff)
	 * - logo: 'whatsapp', 'site_logo' o URL de imagen
	 *
	 * @param array $atts Atributos del shortcode.
	 * @return string
	 */
	public function render_qr_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'      => '',
				'type'    => 'url',
				'content' => '',
				'url'     => '',
				'phone'   => '',
				'message' => '',
				'size'    => 200,
				'fg'      => '#000000',
				'bg'      => '#ffffff',
				'logo'    => '',
				'class'   => '',
			),
			$atts,
			'wpat_qr'
		);

		// Si se pasa un ID guardado, cargar sus propiedades
		if ( ! empty( $atts['id'] ) ) {
			$saved_qrs = $this->get_saved_qrs();
			if ( isset( $saved_qrs[ $atts['id'] ] ) ) {
				$item            = $saved_qrs[ $atts['id'] ];
				$atts['content'] = $item['content'];
				$atts['fg']      = ! empty( $item['fg_color'] ) ? $item['fg_color'] : $atts['fg'];
				$atts['bg']      = ! empty( $item['bg_color'] ) ? $item['bg_color'] : $atts['bg'];
				$atts['size']    = ! empty( $item['size'] ) ? $item['size'] : $atts['size'];
				$atts['logo']    = ( 'none' !== $item['logo_type'] ) ? ( 'custom' === $item['logo_type'] ? $item['logo_url'] : $item['logo_type'] ) : '';
			}
		}

		// Si no hay contenido explícito, construirlo según los atributos
		if ( empty( $atts['content'] ) ) {
			if ( 'whatsapp' === $atts['type'] || ! empty( $atts['phone'] ) ) {
				$clean_phone     = preg_replace( '/[^0-9]/', '', $atts['phone'] );
				$encoded_msg     = rawurlencode( $atts['message'] );
				$atts['content'] = 'https://wa.me/' . $clean_phone . ( ! empty( $encoded_msg ) ? '?text=' . $encoded_msg : '' );
				if ( empty( $atts['logo'] ) ) {
					$atts['logo'] = 'whatsapp';
				}
			} elseif ( ! empty( $atts['url'] ) ) {
				$atts['content'] = esc_url_raw( $atts['url'] );
			} else {
				$atts['content'] = home_url();
			}
		}

		$uid = 'wpat_qr_' . uniqid();

		// Cargar script frontend si no está cargado
		wp_enqueue_script( 'wpat-qr-lib', WPAT_URL . 'assets/js/qrcode.min.js', array( 'jquery' ), WPAT_VERSION, true );

		ob_start();
		?>
		<div class="wpat-qr-embed-container <?php echo esc_attr( $atts['class'] ); ?>" style="display: inline-flex; flex-direction: column; align-items: center; justify-content: center; padding: 12px; background: <?php echo esc_attr( $atts['bg'] ); ?>; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); max-width: fit-content;">
			<div id="<?php echo esc_attr( $uid ); ?>" class="wpat-qr-canvas-holder" style="width: <?php echo intval( $atts['size'] ); ?>px; height: <?php echo intval( $atts['size'] ); ?>px; position: relative;"></div>
			<script type="text/javascript">
			(function() {
				function initQR() {
					if (typeof QRCode !== 'undefined') {
						new QRCode(document.getElementById('<?php echo esc_js( $uid ); ?>'), {
							text: <?php echo wp_json_encode( $atts['content'] ); ?>,
							width: <?php echo intval( $atts['size'] ); ?>,
							height: <?php echo intval( $atts['size'] ); ?>,
							colorDark: <?php echo wp_json_encode( $atts['fg'] ); ?>,
							colorLight: <?php echo wp_json_encode( $atts['bg'] ); ?>,
							correctLevel: QRCode.CorrectLevel.H
						});
					} else {
						setTimeout(initQR, 50);
					}
				}
				if (document.readyState === 'complete' || document.readyState === 'interactive') {
					initQR();
				} else {
					document.addEventListener('DOMContentLoaded', initQR);
				}
			})();
			</script>
		</div>
		<?php
		return ob_get_clean();
	}
}

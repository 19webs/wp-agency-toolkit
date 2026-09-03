<?php
/**
 * Clase WPAT_Anti_Spam.
 * Módulo de Protección Anti-Spam Inteligente Zero-Bloat para Comentarios, Elementor Forms, MetForm y ElementsKit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAT_Anti_Spam {

	/**
	 * Instancia única de la clase.
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registra ganchos y filtros.
	 */
	private function __construct() {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( isset( $settings['anti-spam'] ) && '1' === $settings['anti-spam'] ) {
			$this->init_hooks();
		}
	}

	/**
	 * Inicializa los hooks de protección para los distintos tipos de formulario.
	 */
	private function init_hooks() {
		// 1. Inyectar Honeypot y Timestamp en comentarios nativos de WordPress y reseñas de WooCommerce
		add_action( 'comment_form_after_fields', array( $this, 'inject_comment_honeypot' ) );
		add_action( 'comment_form_logged_in_after', array( $this, 'inject_comment_honeypot' ) );
		add_filter( 'preprocess_comment', array( $this, 'check_comment_spam' ) );

		// 2. Integración con Elementor Forms (Pro y nuevo Form Builder)
		add_action( 'elementor_pro/forms/new_record', array( $this, 'check_elementor_form_spam' ), 10, 2 );
		add_action( 'elementor/forms/new_record', array( $this, 'check_elementor_form_spam' ), 10, 2 );

		// 3. Integración con WPMet MetForm
		add_action( 'metform_after_store_form_data', array( $this, 'check_metform_spam' ), 10, 3 );

		// 4. Integración con WPMet ElementsKit Forms
		add_action( 'elementskit_form_submit', array( $this, 'check_elementskit_spam' ), 10, 2 );

		// 5. Inyectar script Honeypot en el footer del frontend para formularios de Elementor/MetForm/ElementsKit
		add_action( 'wp_footer', array( $this, 'inject_frontend_honeypot_script' ) );
	}

	/**
	 * Inyecta los campos trampas (Honeypot y Timestamp) en el formulario de comentarios.
	 */
	public function inject_comment_honeypot() {
		$time = time();
		echo '<p style="display:none !important; position:absolute !important; left:-9999px !important; opacity:0 !important; pointer-events:none !important;" aria-hidden="true">';
		echo '<label for="wpat_hp_email">No rellenar este campo si eres humano</label>';
		echo '<input type="text" name="wpat_hp_email" id="wpat_hp_email" value="" tabindex="-1" autocomplete="off" />';
		echo '<input type="hidden" name="wpat_hp_time" value="' . esc_attr( $time ) . '" />';
		echo '</p>';
	}

	/**
	 * Inyecta por JavaScript los campos Honeypot y Timestamp en formularios de Elementor, MetForm y ElementsKit en caliente.
	 */
	public function inject_frontend_honeypot_script() {
		if ( is_admin() ) {
			return;
		}
		$time = time();
		?>
		<script type="text/javascript">
			(function() {
				document.addEventListener('DOMContentLoaded', function() {
					var forms = document.querySelectorAll('form.elementor-form, form.metform-form_body, form.ekit-form-wrapper, form.ekit-form');
					if (!forms || !forms.length) return;

					var currentTime = <?php echo (int) $time; ?>;
					forms.forEach(function(form) {
						if (!form.querySelector('input[name="wpat_hp_email"]')) {
							var hpContainer = document.createElement('div');
							hpContainer.style.cssText = 'display:none !important; position:absolute !important; left:-9999px !important; opacity:0 !important; pointer-events:none !important;';
							hpContainer.setAttribute('aria-hidden', 'true');

							var hpInput = document.createElement('input');
							hpInput.type = 'text';
							hpInput.name = 'wpat_hp_email';
							hpInput.tabIndex = -1;
							hpInput.autocomplete = 'off';

							var timeInput = document.createElement('input');
							timeInput.type = 'hidden';
							timeInput.name = 'wpat_hp_time';
							timeInput.value = currentTime;

							hpContainer.appendChild(hpInput);
							hpContainer.appendChild(timeInput);
							form.appendChild(hpContainer);
						}
					});
				});
			})();
		</script>
		<?php
	}

	/**
	 * Evalúa una solicitud contra las reglas de Anti-Spam activas.
	 *
	 * @param string $content Texto o campos del formulario.
	 * @param array $extra_fields Campos adicionales enviadados.
	 * @return true|string Retorna true si es seguro, o mensaje de error si es spam.
	 */
	public function validate_anti_spam( $content, $extra_fields = array() ) {
		$settings = WPAT_Main::get_instance()->get_settings();

		// 1. Honeypot Check (Campo trampa)
		if ( isset( $extra_fields['wpat_hp_email'] ) && ! empty( $extra_fields['wpat_hp_email'] ) ) {
			$this->log_spam_attempt( 'Honeypot activado (campo trampa rellenado por bot)' );
			return 'Spam detectado (Honeypot). Envío bloqueado.';
		}

		// 2. Timestamp Challenge Check (Tiempo mínimo de envío < 2.5 segundos)
		if ( isset( $settings['antispam_time_check'] ) && '1' === $settings['antispam_time_check'] ) {
			if ( isset( $extra_fields['wpat_hp_time'] ) && ! empty( $extra_fields['wpat_hp_time'] ) ) {
				$time_submitted = intval( $extra_fields['wpat_hp_time'] );
				$elapsed = time() - $time_submitted;
				if ( $elapsed >= 0 && $elapsed < 2 ) {
					$this->log_spam_attempt( 'Tiempo de envío sospechoso (' . $elapsed . 's)' );
					return 'Envío demasiado rápido. Por favor, tómate un segundo para revisar tu mensaje.';
				}
			}
		}

		// 3. Límite máximo de enlaces/URLs
		if ( isset( $settings['antispam_max_links'] ) && is_numeric( $settings['antispam_max_links'] ) && intval( $settings['antispam_max_links'] ) >= 0 ) {
			$max_links = intval( $settings['antispam_max_links'] );
			$link_count = preg_match_all( '/https?:\/\//i', $content, $matches );
			if ( $link_count > $max_links ) {
				$this->log_spam_attempt( 'Exceso de enlaces (' . $link_count . ' detectados, máx ' . $max_links . ')' );
				return 'Tu mensaje contiene demasiados enlaces (' . $link_count . '). Máximo permitido: ' . $max_links . '.';
			}
		}

		// 4. Bloqueo de caracteres cirílicos (rusos) u otros alfabetos si está activo
		if ( isset( $settings['antispam_block_cyrillic'] ) && '1' === $settings['antispam_block_cyrillic'] ) {
			if ( preg_match( '/\p{Cyrillic}/u', $content ) ) {
				$this->log_spam_attempt( 'Bloqueo por caracteres cirílicos (rusos)' );
				return 'El mensaje contiene caracteres no permitidos.';
			}
		}

		// 5. Lista negra de palabras clave
		if ( ! empty( $settings['antispam_keywords'] ) ) {
			$keywords = explode( "\n", $settings['antispam_keywords'] );
			foreach ( $keywords as $word ) {
				$word = trim( $word );
				if ( empty( $word ) ) {
					continue;
				}
				if ( false !== mb_strpos( mb_strtolower( $content ), mb_strtolower( $word ) ) ) {
					$this->log_spam_attempt( 'Palabra clave de spam detectada: "' . $word . '"' );
					return 'El mensaje contiene términos no permitidos.';
				}
			}
		}

		return true;
	}

	/**
	 * Filtro para comentarios nativos y reseñas WooCommerce.
	 */
	public function check_comment_spam( $commentdata ) {
		// Si el usuario es administrador o moderador, omitir
		if ( current_user_can( 'moderate_comments' ) ) {
			return $commentdata;
		}

		$content = $commentdata['comment_content'];
		$extra   = $_POST;

		$check = $this->validate_anti_spam( $content, $extra );
		if ( true !== $check ) {
			wp_die( esc_html( $check ), 'Spam Bloqueado', array( 'response' => 403 ) );
		}

		return $commentdata;
	}

	/**
	 * Filtro para Elementor Forms (Pro y nuevo Form Builder).
	 */
	public function check_elementor_form_spam( $record, $ajax_handler ) {
		$raw_fields = $record->get( 'fields' );
		$all_text = '';
		foreach ( $raw_fields as $field ) {
			if ( ! empty( $field['value'] ) ) {
				$all_text .= ' ' . ( is_array( $field['value'] ) ? implode( ' ', $field['value'] ) : $field['value'] );
			}
		}

		$extra = $_POST;
		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			$ajax_handler->add_error_message( $check );
			$ajax_handler->is_success = false;
		}
	}

	/**
	 * Filtro para MetForm (WPMet).
	 */
	public function check_metform_spam( $form_id, $form_data, $settings ) {
		$all_text = is_array( $form_data ) ? implode( ' ', array_values( $form_data ) ) : '';
		$extra = $_POST;

		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			wp_send_json_error( array(
				'status'  => 0,
				'message' => $check,
			) );
			exit;
		}
	}

	/**
	 * Filtro para ElementsKit Forms (WPMet).
	 */
	public function check_elementskit_spam( $form_data, $form_id ) {
		$all_text = is_array( $form_data ) ? implode( ' ', array_values( $form_data ) ) : '';
		$extra = $_POST;

		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			wp_send_json_error( array(
				'status'  => 0,
				'message' => $check,
			) );
			exit;
		}
	}

	/**
	 * Registra el intento de spam y notifica opcionalmente al módulo Bot-Blocker.
	 */
	private function log_spam_attempt( $reason ) {
		$count = get_option( 'wpat_antispam_blocked_count', 0 );
		update_option( 'wpat_antispam_blocked_count', $count + 1 );

		// Si el módulo bot-blocker está activo, registrar el intento de la IP
		if ( class_exists( 'WPAT_Bot_Blocker' ) ) {
			$user_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '0.0.0.0';
			$key     = 'wpat_bot_spam_' . md5( $user_ip );
			$attempts = get_transient( $key );
			$attempts = $attempts ? intval( $attempts ) + 1 : 1;
			set_transient( $key, $attempts, 3600 );
		}
	}
}

// Inicializar
WPAT_Anti_Spam::get_instance();

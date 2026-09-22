<?php
/**
 * Clase WPAT_Anti_Spam.
 * Módulo de Protección Anti-Spam Inteligente Zero-Bloat para Comentarios, Elementor Forms, MetForm, ElementsKit, Contact Form 7, WPForms y Fluent Forms.
 *
 * @package WPAgencyToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAT_Anti_Spam {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Anti_Spam|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Anti_Spam
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
		if ( isset( $settings['anti-spam'] ) && '1' === (string) $settings['anti-spam'] ) {
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
		add_action( 'metform_before_store_form_data', array( $this, 'check_metform_spam' ), 10, 3 );
		add_action( 'metform_after_store_form_data', array( $this, 'check_metform_spam' ), 10, 3 );

		// 4. Integración con WPMet ElementsKit Forms
		add_action( 'elementskit_form_submit', array( $this, 'check_elementskit_spam' ), 10, 2 );

		// 5. Integración con Contact Form 7 (CF7)
		add_filter( 'wpcf7_validate', array( $this, 'check_cf7_spam' ), 10, 2 );

		// 6. Integración con WPForms
		add_action( 'wpforms_process_initial_errors', array( $this, 'check_wpforms_spam' ), 10, 2 );

		// 7. Integración con Fluent Forms
		add_filter( 'fluentform/before_insert_submission', array( $this, 'check_fluentform_spam' ), 10, 3 );

		// 8. Integración con Gravity Forms
		add_filter( 'gform_validation', array( $this, 'check_gravity_forms_spam' ) );

		// 9. Integración con Formidable Forms
		add_filter( 'frm_validate_entry', array( $this, 'check_formidable_spam' ), 20, 2 );

		// 10. Inyectar script Honeypot en el frontend para formularios dinámicos y maquetadores
		add_action( 'wp_footer', array( $this, 'inject_frontend_honeypot_script' ) );
	}

	/**
	 * Inyecta los campos trampas (Honeypot y Timestamp) en el formulario de comentarios.
	 */
	public function inject_comment_honeypot() {
		$time = time();
		echo '<p style="display:none !important; position:absolute !important; left:-9999px !important; opacity:0 !important; pointer-events:none !important;" aria-hidden="true">';
		echo '<label for="wpat_hp_email">' . esc_html__( 'No rellenar este campo si eres humano', 'wp-agency-toolkit' ) . '</label>';
		echo '<input type="text" name="wpat_hp_email" id="wpat_hp_email" value="" tabindex="-1" autocomplete="off" />';
		echo '<input type="hidden" name="wpat_hp_time" value="' . esc_attr( $time ) . '" />';
		echo '</p>';
	}

	/**
	 * Inyecta por JavaScript los campos Honeypot y Timestamp en formularios con soporte de MutationObserver para popups y modales.
	 */
	public function inject_frontend_honeypot_script() {
		if ( is_admin() ) {
			return;
		}
		$time = time();
		?>
		<script type="text/javascript">
			(function() {
				var currentTime = <?php echo (int) $time; ?>;
				var selector = 'form.elementor-form, form.metform-form_body, form.ekit-form-wrapper, form.ekit-form, form.wpcf7-form, form.wpforms-form, form.frm-fluent-form, form.gform_wrapper form, form.frm-show-form';

				function injectHoneypots() {
					var forms = document.querySelectorAll(selector);
					if (!forms || !forms.length) return;

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
				}

				if (document.readyState === 'loading') {
					document.addEventListener('DOMContentLoaded', injectHoneypots);
				} else {
					injectHoneypots();
				}

				window.addEventListener('load', injectHoneypots);
				document.addEventListener('elementor/popup/show', injectHoneypots);

				if (window.MutationObserver) {
					var observer = new MutationObserver(function(mutations) {
						for (var i = 0; i < mutations.length; i++) {
							if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
								injectHoneypots();
								break;
							}
						}
					});
					observer.observe(document.body, { childList: true, subtree: true });
				}
			})();
		</script>
		<?php
	}

	/**
	 * Evalúa una solicitud contra las reglas de Anti-Spam activas.
	 *
	 * @param string $content Texto o campos del formulario.
	 * @param array  $extra_fields Campos adicionales enviados.
	 * @return true|string Retorna true si es seguro, o mensaje de error si es spam.
	 */
	public function validate_anti_spam( $content, $extra_fields = array() ) {
		$settings = WPAT_Main::get_instance()->get_settings();

		// 1. Honeypot Check (Campo trampa)
		$honeypot_on = ! isset( $settings['antispam_honeypot'] ) || '1' === (string) $settings['antispam_honeypot'];
		if ( $honeypot_on && isset( $extra_fields['wpat_hp_email'] ) && ! empty( $extra_fields['wpat_hp_email'] ) ) {
			$this->log_spam_attempt( 'Honeypot activado (campo trampa rellenado por bot)' );
			return __( 'Spam detectado (Honeypot). Envío bloqueado.', 'wp-agency-toolkit' );
		}

		// 2. Timestamp Challenge Check (Tiempo mínimo de envío < 2 segundos o tiempo futuro)
		if ( isset( $settings['antispam_time_check'] ) && '1' === (string) $settings['antispam_time_check'] ) {
			if ( isset( $extra_fields['wpat_hp_time'] ) ) {
				$time_submitted = (int) $extra_fields['wpat_hp_time'];
				$now            = time();
				$elapsed        = $now - $time_submitted;

				if ( $time_submitted <= 0 || $elapsed < 2 || $time_submitted > ( $now + 60 ) ) {
					$this->log_spam_attempt( 'Tiempo de envío sospechoso (' . $elapsed . 's)' );
					return __( 'Envío demasiado rápido. Por favor, tómate un segundo para revisar tu mensaje antes de enviar.', 'wp-agency-toolkit' );
				}
			}
		}

		// 3. Límite máximo de enlaces/URLs
		if ( isset( $settings['antispam_max_links'] ) && is_numeric( $settings['antispam_max_links'] ) && (int) $settings['antispam_max_links'] >= 0 ) {
			$max_links = (int) $settings['antispam_max_links'];
			if ( $max_links < 99 ) {
				$link_count = preg_match_all( '/https?:\/\/|www\./i', (string) $content, $matches );
				if ( $link_count > $max_links ) {
					$this->log_spam_attempt( 'Exceso de enlaces (' . $link_count . ' detectados, máx ' . $max_links . ')' );
					return sprintf( __( 'Tu mensaje contiene demasiados enlaces (%1$d detectados). Máximo permitido: %2$d.', 'wp-agency-toolkit' ), $link_count, $max_links );
				}
			}
		}

		// 4. Bloqueo de caracteres cirílicos (rusos) u otros alfabetos si está activo
		if ( isset( $settings['antispam_block_cyrillic'] ) && '1' === (string) $settings['antispam_block_cyrillic'] ) {
			if ( preg_match( '/\p{Cyrillic}/u', (string) $content ) ) {
				$this->log_spam_attempt( 'Bloqueo por caracteres cirílicos (rusos)' );
				return __( 'El mensaje contiene caracteres no permitidos.', 'wp-agency-toolkit' );
			}
		}

		// 5. Lista negra de palabras clave
		if ( ! empty( $settings['antispam_keywords'] ) ) {
			$keywords = explode( "\n", (string) $settings['antispam_keywords'] );
			$haystack = mb_strtolower( (string) $content, 'UTF-8' );

			foreach ( $keywords as $word ) {
				$word = trim( $word );
				if ( empty( $word ) ) {
					continue;
				}
				if ( false !== mb_strpos( $haystack, mb_strtolower( $word, 'UTF-8' ) ) ) {
					$this->log_spam_attempt( 'Palabra clave de spam detectada: "' . $word . '"' );
					return __( 'El mensaje contiene términos no permitidos por nuestras directivas de seguridad.', 'wp-agency-toolkit' );
				}
			}
		}

		return true;
	}

	/**
	 * Filtro para comentarios nativos y reseñas WooCommerce.
	 *
	 * @param array $commentdata Datos del comentario.
	 * @return array
	 */
	public function check_comment_spam( $commentdata ) {
		if ( current_user_can( 'moderate_comments' ) ) {
			return $commentdata;
		}

		$content = isset( $commentdata['comment_content'] ) ? $commentdata['comment_content'] : '';
		$extra   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$check = $this->validate_anti_spam( $content, $extra );
		if ( true !== $check ) {
			wp_die( esc_html( $check ), esc_html__( 'Spam Bloqueado', 'wp-agency-toolkit' ), array( 'response' => 403 ) );
		}

		return $commentdata;
	}

	/**
	 * Filtro para Elementor Forms (Pro y nuevo Form Builder).
	 *
	 * @param object $record       Objeto del registro de Elementor.
	 * @param object $ajax_handler Manejador AJAX de Elementor.
	 */
	public function check_elementor_form_spam( $record, $ajax_handler ) {
		$raw_fields = method_exists( $record, 'get' ) ? $record->get( 'fields' ) : array();
		$all_text   = '';

		if ( is_array( $raw_fields ) ) {
			foreach ( $raw_fields as $field ) {
				if ( ! empty( $field['value'] ) ) {
					$all_text .= ' ' . ( is_array( $field['value'] ) ? implode( ' ', $field['value'] ) : $field['value'] );
				}
			}
		}

		$extra = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			if ( method_exists( $ajax_handler, 'add_error_message' ) ) {
				$ajax_handler->add_error_message( $check );
			}
			$ajax_handler->is_success = false;
		}
	}

	/**
	 * Filtro para MetForm (WPMet).
	 *
	 * @param int   $form_id   ID del formulario.
	 * @param array $form_data Datos del formulario.
	 * @param array $settings  Ajustes.
	 */
	public function check_metform_spam( $form_id, $form_data = array(), $settings = array() ) {
		$all_text = is_array( $form_data ) ? implode( ' ', array_values( $form_data ) ) : '';
		$extra    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			wp_send_json_error(
				array(
					'status'  => 0,
					'message' => $check,
				)
			);
			exit;
		}
	}

	/**
	 * Filtro para ElementsKit Forms (WPMet).
	 *
	 * @param array $form_data Datos del formulario.
	 * @param int   $form_id   ID del formulario.
	 */
	public function check_elementskit_spam( $form_data, $form_id = 0 ) {
		$all_text = is_array( $form_data ) ? implode( ' ', array_values( $form_data ) ) : '';
		$extra    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			wp_send_json_error(
				array(
					'status'  => 0,
					'message' => $check,
				)
			);
			exit;
		}
	}

	/**
	 * Filtro para Contact Form 7 (CF7).
	 *
	 * @param WPCF7_Validation $result Objeto de validación.
	 * @param array            $tags   Etiquetas del formulario.
	 * @return WPCF7_Validation
	 */
	public function check_cf7_spam( $result, $tags = array() ) {
		$submission = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;
		$data       = $submission ? $submission->get_posted_data() : $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$all_text   = is_array( $data ) ? implode( ' ', array_map( function( $v ) {
			return is_array( $v ) ? implode( ' ', $v ) : (string) $v;
		}, $data ) ) : '';

		$extra = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check && is_object( $result ) && method_exists( $result, 'invalidate' ) ) {
			$result->invalidate( array( 'type' => 'wpat_antispam', 'name' => 'wpat_antispam' ), $check );
		}

		return $result;
	}

	/**
	 * Filtro para WPForms.
	 *
	 * @param array $fields   Campos del formulario.
	 * @param array $entry    Entrada del formulario.
	 */
	public function check_wpforms_spam( $fields, $entry ) {
		$all_text = '';
		if ( isset( $entry['fields'] ) && is_array( $entry['fields'] ) ) {
			$all_text = implode( ' ', array_values( $entry['fields'] ) );
		}
		$extra = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check && function_exists( 'wpforms' ) ) {
			$form_id = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;
			wpforms()->process->errors[ $form_id ]['header'] = $check;
		}
	}

	/**
	 * Filtro para Fluent Forms.
	 *
	 * @param array $insert_data Datos a insertar.
	 * @param array $form_data   Datos del formulario.
	 * @param int   $form_id     ID del formulario.
	 * @return array
	 */
	public function check_fluentform_spam( $insert_data, $form_data, $form_id ) {
		$all_text = is_array( $form_data ) ? implode( ' ', array_map( function( $v ) {
			return is_array( $v ) ? implode( ' ', $v ) : (string) $v;
		}, $form_data ) ) : '';

		$extra = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			wp_send_json_error(
				array(
					'errors' => array( 'general' => array( $check ) ),
				),
				422
			);
			exit;
		}

		return $insert_data;
	}

	/**
	 * Filtro para Gravity Forms.
	 *
	 * @param array $validation_result Resultado de validación.
	 * @return array
	 */
	public function check_gravity_forms_spam( $validation_result ) {
		$form     = isset( $validation_result['form'] ) ? $validation_result['form'] : array();
		$extra    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$all_text = is_array( $extra ) ? implode( ' ', array_map( function( $v ) {
			return is_array( $v ) ? implode( ' ', $v ) : (string) $v;
		}, $extra ) ) : '';

		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			$validation_result['is_valid'] = false;
			if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
				foreach ( $form['fields'] as &$field ) {
					$field->failed_validation  = true;
					$field->validation_message = $check;
					break;
				}
			}
		}

		return $validation_result;
	}

	/**
	 * Filtro para Formidable Forms.
	 *
	 * @param array $errors Errores de validación.
	 * @param array $values Valores enviados.
	 * @return array
	 */
	public function check_formidable_spam( $errors, $values ) {
		$extra    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$all_text = is_array( $values ) ? implode( ' ', array_map( function( $v ) {
			return is_array( $v ) ? implode( ' ', $v ) : (string) $v;
		}, $values ) ) : '';

		$check = $this->validate_anti_spam( $all_text, $extra );

		if ( true !== $check ) {
			$errors['wpat_antispam'] = $check;
		}

		return $errors;
	}

	/**
	 * Registra el intento de spam y notifica opcionalmente al módulo Bot-Blocker.
	 *
	 * @param string $reason Motivo del bloqueo.
	 */
	private function log_spam_attempt( $reason ) {
		$count = get_option( 'wpat_antispam_blocked_count', 0 );
		update_option( 'wpat_antispam_blocked_count', $count + 1 );

		// Si el módulo bot-blocker está activo, registrar el intento de la IP
		if ( class_exists( 'WPAT_Bot_Blocker' ) && method_exists( 'WPAT_Bot_Blocker', 'record_suspicious_ip' ) ) {
			WPAT_Bot_Blocker::record_suspicious_ip( null, 'Intento de envío de spam en formulario (' . sanitize_text_field( $reason ) . ')', 1 );
		}
	}
}

// Inicializar
WPAT_Anti_Spam::get_instance();

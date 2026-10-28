<?php
/**
 * Módulo: Formularios de Registro Personalizados para WooCommerce - WP Agency Toolkit
 *
 * Permite crear formularios de registro avanzados con campos personalizados y nativos de WooCommerce,
 * asignación de roles de usuario, flujo de aprobación manual o automática, y sincronización
 * directa con los datos de facturación de WooCommerce.
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Registration_Forms {

	/**
	 * Instancia única de la clase (Singleton).
	 *
	 * @var WPAT_Woo_Registration_Forms|null
	 */
	private static $instance = null;

	/**
	 * Clave en wp_options para la lista de formularios.
	 *
	 * @var string
	 */
	const OPTION_FORMS = 'wpat_woo_registration_forms';

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Woo_Registration_Forms
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
		// Shortcode de frontend para incrustar cualquier formulario
		add_shortcode( 'wpat_registration_form', array( $this, 'render_shortcode' ) );

		// Encolado de scripts y estilos frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Procesar envío de formulario
		add_action( 'init', array( $this, 'process_registration_form' ) );

		// Interceptar autenticación de usuarios para control de cuentas pendientes / rechazadas
		add_filter( 'authenticate', array( $this, 'check_user_account_status_on_login' ), 30, 3 );

		// Integración con lista de usuarios de WordPress
		add_filter( 'manage_users_columns', array( $this, 'add_user_columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_user_columns' ), 10, 3 );

		// Mostrar campos registrados en el perfil del usuario (WP Admin)
		add_action( 'show_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );

		// Endpoint AJAX para aprobar o rechazar usuarios
		add_action( 'wp_ajax_wpat_update_user_reg_status', array( $this, 'ajax_update_user_reg_status' ) );

		// Endpoint AJAX para vista previa interactiva del formulario
		add_action( 'wp_ajax_wpat_preview_registration_form', array( $this, 'ajax_preview_registration_form' ) );

		// Encolar assets en admin si estamos en páginas relevantes
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Integración opcional en el formulario de Mi Cuenta de WooCommerce
		add_action( 'woocommerce_register_form', array( $this, 'render_wc_account_extra_fields' ) );
		add_action( 'woocommerce_created_customer', array( $this, 'handle_wc_account_created_customer' ) );
	}

	/**
	 * Obtiene todos los formularios configurados o devuelve el formulario por defecto.
	 *
	 * @return array
	 */
	public static function get_forms() {
		$forms = get_option( self::OPTION_FORMS, array() );

		if ( empty( $forms ) || ! is_array( $forms ) ) {
			// Formulario inicial preconfigurado por defecto (B2B / Mayoristas)
			$default_form = array(
				'id'                   => 'b2b-registration',
				'title'                => 'Registro Mayoristas / B2B',
				'desc'                 => 'Rellena este formulario para solicitar tu cuenta de cliente profesional o distribuidor.',
				'role_assignment'      => 'fixed', // 'fixed' o 'dropdown'
				'default_role'         => 'customer',
				'allowed_roles'        => array( 'customer' ),
				'approval_required'    => '1', // '1' = Manual (pendiente), '0' = Automático
				'redirect_type'        => 'default', // 'default', 'custom', 'myaccount'
				'redirect_url'         => '',
				'success_msg'          => '¡Gracias por registrarte! Tu solicitud de cuenta ha sido enviada y será revisada por nuestro equipo comercial.',
				'attach_to_my_account' => '0',
				'fields'               => array(
					array(
						'id'          => 'billing_first_name',
						'type'        => 'text',
						'label'       => 'Nombre',
						'placeholder' => 'Tu nombre',
						'required'    => '1',
						'width'       => 'half',
						'is_native'   => '1',
					),
					array(
						'id'          => 'billing_last_name',
						'type'        => 'text',
						'label'       => 'Apellidos',
						'placeholder' => 'Tus apellidos',
						'required'    => '1',
						'width'       => 'half',
						'is_native'   => '1',
					),
					array(
						'id'          => 'billing_company',
						'type'        => 'text',
						'label'       => 'Nombre de Empresa / Razón Social',
						'placeholder' => 'Tu empresa o negocio',
						'required'    => '1',
						'width'       => 'half',
						'is_native'   => '1',
					),
					array(
						'id'          => 'billing_vat',
						'type'        => 'text',
						'label'       => 'NIF / CIF / DNI',
						'placeholder' => 'B12345678',
						'required'    => '1',
						'width'       => 'half',
						'is_native'   => '1',
					),
					array(
						'id'          => 'billing_phone',
						'type'        => 'tel',
						'label'       => 'Teléfono de Contacto',
						'placeholder' => '+34 600 000 000',
						'required'    => '1',
						'width'       => 'half',
						'is_native'   => '1',
					),
					array(
						'id'          => 'billing_city',
						'type'        => 'text',
						'label'       => 'Población / Ciudad',
						'placeholder' => 'Ej: Madrid, Barcelona...',
						'required'    => '0',
						'width'       => 'half',
						'is_native'   => '1',
					),
					array(
						'id'          => 'tax_certificate',
						'type'        => 'file',
						'label'       => 'Modelo 036 / Certificado de Empresa (PDF/JPG)',
						'placeholder' => '',
						'required'    => '0',
						'width'       => 'full',
						'is_native'   => '0',
					),
					array(
						'id'          => 'terms_privacy',
						'type'        => 'checkbox',
						'label'       => 'He leído y acepto la política de privacidad y las condiciones comerciales',
						'placeholder' => '',
						'required'    => '1',
						'width'       => 'full',
						'is_native'   => '0',
					),
				),
			);

			$forms = array( 'b2b-registration' => $default_form );
			update_option( self::OPTION_FORMS, $forms );
		}

		return $forms;
	}

	/**
	 * Obtiene un formulario por su identificador.
	 *
	 * @param string $form_id ID del formulario.
	 * @return array|null
	 */
	public static function get_form( $form_id ) {
		$forms = self::get_forms();
		if ( isset( $forms[ $form_id ] ) ) {
			return $forms[ $form_id ];
		}
		// Fallback: buscar por el primer formulario
		if ( ! empty( $forms ) ) {
			return reset( $forms );
		}
		return null;
	}

	/**
	 * Encola estilos y scripts para frontend.
	 */
	public function enqueue_frontend_assets() {
		wp_register_style(
			'wpat-woo-registration-forms-css',
			WPAT_URL . 'assets/css/wpat-woo-registration-forms.css',
			array(),
			WPAT_VERSION
		);

		wp_register_script(
			'wpat-woo-registration-forms-js',
			WPAT_URL . 'assets/js/wpat-woo-registration-forms.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			WPAT_VERSION,
			true
		);

		wp_localize_script(
			'wpat-woo-registration-forms-js',
			'wpatRegForms',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpat_reg_forms_nonce' ),
			)
		);
	}

	/**
	 * Encola assets en administración cuando sea necesario.
	 *
	 * @param string $hook Pestaña actual de WP Admin.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'users.php' === $hook || false !== strpos( $hook, 'wp-agency-toolkit' ) ) {
			wp_enqueue_style( 'wpat-woo-registration-forms-css', WPAT_URL . 'assets/css/wpat-woo-registration-forms.css', array(), WPAT_VERSION );
			wp_enqueue_script( 'wpat-woo-registration-forms-js', WPAT_URL . 'assets/js/wpat-woo-registration-forms.js', array( 'jquery', 'jquery-ui-sortable' ), WPAT_VERSION, true );

			$available_roles = array();
			if ( function_exists( 'wp_roles' ) ) {
				foreach ( wp_roles()->roles as $r_slug => $r_val ) {
					$available_roles[ $r_slug ] = isset( $r_val['name'] ) ? translate_user_role( $r_val['name'] ) : ucfirst( $r_slug );
				}
			}

			wp_localize_script(
				'wpat-woo-registration-forms-js',
				'wpatRegForms',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpat_reg_forms_nonce' ),
					'roles'   => $available_roles,
				)
			);
		}
	}

	/**
	 * Renderiza el shortcode [wpat_registration_form id="b2b-registration"].
	 *
	 * @param array $atts Atributos del shortcode.
	 * @return string HTML del formulario.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => '',
			),
			$atts,
			'wpat_registration_form'
		);

		wp_enqueue_style( 'wpat-woo-registration-forms-css' );
		wp_enqueue_script( 'wpat-woo-registration-forms-js' );

		// Si el usuario ya está conectado, mostrar aviso con enlace a Mi Cuenta
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$my_account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/mi-cuenta/' );

			return sprintf(
				'<div class="wpat-reg-notice wpat-reg-notice-success">' .
					'<span class="dashicons dashicons-admin-users" style="font-size:24px; width:24px; height:24px;"></span>' .
					'<div><strong>%s</strong> %s <a href="%s" style="font-weight:600; text-decoration:underline;">%s</a>.</div>' .
				'</div>',
				esc_html__( 'Ya has iniciado sesión', 'wp-agency-toolkit' ),
				sprintf( esc_html__( 'como %s. Puedes gestionar tus pedidos y datos en tu área de cliente:', 'wp-agency-toolkit' ), '<strong>' . esc_html( $current_user->display_name ) . '</strong>' ),
				esc_url( $my_account_url ),
				esc_html__( 'Ir a Mi Cuenta', 'wp-agency-toolkit' )
			);
		}

		$form = self::get_form( $atts['id'] );
		if ( ! $form ) {
			return '<div class="wpat-reg-notice wpat-reg-notice-error">' . esc_html__( 'Formulario de registro no encontrado.', 'wp-agency-toolkit' ) . '</div>';
		}

		return $this->generate_form_markup( $form );
	}

	/**
	 * Genera el marcado HTML para el formulario dado.
	 *
	 * @param array $form Configuración del formulario.
	 * @param bool  $is_preview Si se está renderizando en modo vista previa interactiva.
	 * @return string
	 */
	public function generate_form_markup( $form, $is_preview = false ) {
		ob_start();

		$form_id = esc_attr( $form['id'] );

		// Mensajes de estado en URL (solo en frontend real)
		if ( ! $is_preview ) {
			if ( isset( $_GET['wpat_reg_success'] ) && $form_id === sanitize_text_field( $_GET['wpat_form_id'] ) ) {
				$is_pending = isset( $_GET['wpat_pending'] ) && '1' === $_GET['wpat_pending'];
				if ( $is_pending ) {
					?>
					<div class="wpat-reg-notice wpat-reg-notice-pending">
						<span class="dashicons dashicons-clock" style="font-size:24px; width:24px; height:24px; color:#f59e0b;"></span>
						<div>
							<strong><?php esc_html_e( 'Solicitud de registro enviada con éxito.', 'wp-agency-toolkit' ); ?></strong><br>
							<?php echo esc_html( ! empty( $form['success_msg'] ) ? $form['success_msg'] : __( 'Tu cuenta está pendiente de aprobación por un administrador. Te notificaremos por correo electrónico una vez validada.', 'wp-agency-toolkit' ) ); ?>
						</div>
					</div>
					<?php
				} else {
					?>
					<div class="wpat-reg-notice wpat-reg-notice-success">
						<span class="dashicons dashicons-yes-alt" style="font-size:24px; width:24px; height:24px; color:#16a34a;"></span>
						<div>
							<strong><?php esc_html_e( '¡Registro completado!', 'wp-agency-toolkit' ); ?></strong><br>
							<?php esc_html_e( 'Tu cuenta ha sido creada y ya has iniciado sesión.', 'wp-agency-toolkit' ); ?>
						</div>
					</div>
					<?php
				}
				return ob_get_clean();
			}

			if ( isset( $_GET['wpat_reg_error'] ) && ! empty( $_GET['wpat_reg_error'] ) ) {
				?>
				<div class="wpat-reg-notice wpat-reg-notice-error">
					<span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; color:#ef4444;"></span>
					<div>
						<strong><?php esc_html_e( 'Error en el registro:', 'wp-agency-toolkit' ); ?></strong><br>
						<?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['wpat_reg_error'] ) ) ); ?>
					</div>
				</div>
				<?php
			}
		}
		?>
		<div class="wpat-reg-form-wrap <?php echo $is_preview ? 'wpat-reg-preview-mode' : ''; ?>" id="wpat-form-wrap-<?php echo esc_attr( $form_id ); ?>">
			<?php if ( $is_preview ) : ?>
				<div class="wpat-reg-preview-notice">
					<span class="dashicons dashicons-visibility" style="font-size: 18px; width: 18px; height: 18px; color: #2563eb;"></span>
					<div>
						<strong><?php esc_html_e( 'Vista Previa en Vivo', 'wp-agency-toolkit' ); ?></strong>
						<span style="display: block; font-size: 12px; color: #475569; margin-top: 2px;">
							<?php esc_html_e( 'Así es como verán este formulario los usuarios en tu web. Los envíos de prueba están desactivados.', 'wp-agency-toolkit' ); ?>
						</span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $form['title'] ) ) : ?>
				<h3 class="wpat-reg-form-title"><?php echo esc_html( $form['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( ! empty( $form['desc'] ) ) : ?>
				<p class="wpat-reg-form-desc"><?php echo esc_html( $form['desc'] ); ?></p>
			<?php endif; ?>

			<?php if ( $is_preview ) : ?>
				<div class="wpat-reg-form wpat-reg-form-preview-inner" id="wpat-form-<?php echo esc_attr( $form_id ); ?>">
			<?php else : ?>
				<form method="post" action="" enctype="multipart/form-data" class="wpat-reg-form" id="wpat-form-<?php echo esc_attr( $form_id ); ?>">
					<?php wp_nonce_field( 'wpat_submit_registration_action', 'wpat_reg_nonce' ); ?>
					<input type="hidden" name="wpat_reg_action" value="submit_registration" />
					<input type="hidden" name="wpat_form_id" value="<?php echo esc_attr( $form_id ); ?>" />

					<!-- Campo Honeypot invisible para atrapar bots sin molestar usuarios -->
					<div class="wpat-reg-hp-field">
						<label for="wpat_reg_hp_<?php echo esc_attr( $form_id ); ?>">Dejar en blanco:</label>
						<input type="text" name="wpat_reg_hp" id="wpat_reg_hp_<?php echo esc_attr( $form_id ); ?>" value="" tabindex="-1" autocomplete="off" />
					</div>
			<?php endif; ?>

					<div class="wpat-reg-grid">
						<!-- Campos estándar obligatorios de WordPress / WooCommerce: Email y Contraseña -->
						<div class="wpat-reg-field-group col-half">
							<label class="wpat-reg-label" for="wpat_user_email_<?php echo esc_attr( $form_id ); ?>">
								<?php esc_html_e( 'Correo electrónico', 'wp-agency-toolkit' ); ?> <span class="wpat-reg-required">*</span>
							</label>
							<input type="email" name="user_email" id="wpat_user_email_<?php echo esc_attr( $form_id ); ?>" class="wpat-reg-input" required autocomplete="email" placeholder="ejemplo@empresa.com" />
						</div>

						<div class="wpat-reg-field-group col-half">
							<label class="wpat-reg-label" for="wpat_user_pass_<?php echo esc_attr( $form_id ); ?>">
								<?php esc_html_e( 'Contraseña de acceso', 'wp-agency-toolkit' ); ?> <span class="wpat-reg-required">*</span>
							</label>
							<input type="password" name="user_pass" id="wpat_user_pass_<?php echo esc_attr( $form_id ); ?>" class="wpat-reg-input" required autocomplete="new-password" placeholder="Mínimo 8 caracteres" />
						</div>

						<!-- Si está configurado selección de rol en desplegable -->
						<?php if ( isset( $form['role_assignment'] ) && 'dropdown' === $form['role_assignment'] && ! empty( $form['allowed_roles'] ) ) : ?>
							<div class="wpat-reg-field-group col-full">
								<label class="wpat-reg-label" for="wpat_user_role_<?php echo esc_attr( $form_id ); ?>">
									<?php esc_html_e( 'Tipo de Cuenta / Perfil', 'wp-agency-toolkit' ); ?> <span class="wpat-reg-required">*</span>
								</label>
								<select name="user_role" id="wpat_user_role_<?php echo esc_attr( $form_id ); ?>" class="wpat-reg-select" required>
									<?php
									$wp_roles = wp_roles()->roles;
									foreach ( $form['allowed_roles'] as $r_slug ) {
										$role_name = isset( $wp_roles[ $r_slug ]['name'] ) ? translate_user_role( $wp_roles[ $r_slug ]['name'] ) : ucfirst( $r_slug );
										echo '<option value="' . esc_attr( $r_slug ) . '">' . esc_html( $role_name ) . '</option>';
									}
									?>
								</select>
							</div>
						<?php endif; ?>

						<!-- Campos dinámicos del formulario -->
						<?php
						if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
							foreach ( $form['fields'] as $field ) {
								$this->render_field_input( $field, $form_id );
							}
						}
						?>
					</div>

					<div class="wpat-reg-submit-wrap">
						<button type="<?php echo $is_preview ? 'button' : 'submit'; ?>" class="wpat-reg-submit-btn" <?php echo $is_preview ? 'onclick="alert(\'' . esc_js( __( 'Esta es una vista previa de demostración. Los envíos de formulario están deshabilitados.', 'wp-agency-toolkit' ) ) . '\');"' : ''; ?>>
							<span class="dashicons dashicons-admin-users" style="font-size:18px; width:18px; height:18px; line-height:1;"></span>
							<?php echo esc_html( ! empty( $form['button_text'] ) ? $form['button_text'] : __( 'Completar Registro', 'wp-agency-toolkit' ) ); ?>
						</button>
					</div>

			<?php if ( $is_preview ) : ?>
				</div>
			<?php else : ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renderiza un campo individual en el formulario frontend.
	 *
	 * @param array  $field   Ajustes del campo.
	 * @param string $form_id ID del formulario contenedor.
	 */
	private function render_field_input( $field, $form_id ) {
		$f_id       = esc_attr( $field['id'] );
		$f_type     = isset( $field['type'] ) ? $field['type'] : 'text';
		$f_label    = isset( $field['label'] ) ? $field['label'] : '';
		$f_plh      = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
		$f_req      = ! empty( $field['required'] ) && '1' === (string) $field['required'];
		$f_width    = isset( $field['width'] ) && 'half' === $field['width'] ? 'col-half' : 'col-full';
		$input_name = 'wpat_fields[' . $f_id . ']';
		$html_id    = 'wpat_f_' . $form_id . '_' . $f_id;

		// Separador de sección / Encabezado
		if ( 'heading' === $f_type ) {
			echo '<div class="wpat-reg-section-header col-full">';
			echo '<h3 class="wpat-reg-section-title">' . esc_html( $f_label ) . '</h3>';
			if ( ! empty( $field['subtitle'] ) ) {
				echo '<p class="wpat-reg-section-subtitle">' . esc_html( $field['subtitle'] ) . '</p>';
			}
			echo '</div>';
			return;
		}

		// Selector de Opciones con Radio Buttons
		if ( 'radio' === $f_type ) {
			$options = array();
			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				$options = $field['options'];
			} elseif ( ! empty( $field['options_str'] ) ) {
				$parts = preg_split( '/[\r\n,]+/', $field['options_str'] );
				foreach ( $parts as $p ) {
					$p = trim( $p );
					if ( '' === $p ) {
						continue;
					}
					if ( strpos( $p, ':' ) !== false ) {
						list( $k, $v ) = explode( ':', $p, 2 );
						$options[ trim( $k ) ] = trim( $v );
					} else {
						$options[ $p ] = $p;
					}
				}
			}
			if ( empty( $options ) ) {
				$options = array( 'Sí' => __( 'Sí', 'wp-agency-toolkit' ), 'No' => __( 'No', 'wp-agency-toolkit' ) );
			}

			echo '<div class="wpat-reg-field-group ' . esc_attr( $f_width ) . ' wpat-reg-field-radio-wrap">';
			echo '<span class="wpat-reg-label">' . esc_html( $f_label ) . ( $f_req ? ' <span class="wpat-reg-required">*</span>' : '' ) . '</span>';
			echo '<div class="wpat-reg-radio-group">';
			$r_idx = 0;
			foreach ( $options as $opt_val => $opt_lbl ) {
				$r_id = $html_id . '_' . $r_idx;
				echo '<label class="wpat-reg-radio-label" for="' . esc_attr( $r_id ) . '">';
				echo '<input type="radio" name="' . esc_attr( $input_name ) . '" id="' . esc_attr( $r_id ) . '" value="' . esc_attr( $opt_val ) . '" ' . ( $f_req && 0 === $r_idx ? 'required' : '' ) . ' /> ';
				echo '<span>' . esc_html( $opt_lbl ) . '</span>';
				echo '</label>';
				$r_idx++;
			}
			echo '</div>';
			echo '</div>';
			return;
		}

		echo '<div class="wpat-reg-field-group ' . esc_attr( $f_width ) . '">';

		if ( 'checkbox' === $f_type ) {
			echo '<label class="wpat-reg-checkbox-label" for="' . esc_attr( $html_id ) . '">';
			echo '<input type="checkbox" name="' . esc_attr( $input_name ) . '" id="' . esc_attr( $html_id ) . '" value="1" ' . ( $f_req ? 'required' : '' ) . ' /> ';
			echo '<span>' . wp_kses_post( $f_label ) . ( $f_req ? ' <span class="wpat-reg-required">*</span>' : '' ) . '</span>';
			echo '</label>';
		} else {
			echo '<label class="wpat-reg-label" for="' . esc_attr( $html_id ) . '">';
			echo esc_html( $f_label ) . ( $f_req ? ' <span class="wpat-reg-required">*</span>' : '' );
			echo '</label>';

			switch ( $f_type ) {
				case 'textarea':
					echo '<textarea name="' . esc_attr( $input_name ) . '" id="' . esc_attr( $html_id ) . '" class="wpat-reg-textarea" rows="3" placeholder="' . esc_attr( $f_plh ) . '" ' . ( $f_req ? 'required' : '' ) . '></textarea>';
					break;

				case 'select':
					echo '<select name="' . esc_attr( $input_name ) . '" id="' . esc_attr( $html_id ) . '" class="wpat-reg-select" ' . ( $f_req ? 'required' : '' ) . '>';
					if ( ! empty( $f_plh ) ) {
						echo '<option value="">' . esc_html( $f_plh ) . '</option>';
					}
					$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
					foreach ( $options as $opt_val => $opt_label ) {
						echo '<option value="' . esc_attr( $opt_val ) . '">' . esc_html( $opt_label ) . '</option>';
					}
					echo '</select>';
					break;

				case 'file':
					echo '<input type="file" name="wpat_file_' . esc_attr( $f_id ) . '" id="' . esc_attr( $html_id ) . '" class="wpat-reg-file-input" accept=".pdf,.jpg,.jpeg,.png" ' . ( $f_req ? 'required' : '' ) . ' />';
					echo '<span class="wpat-reg-hint">' . esc_html__( 'Formatos permitidos: PDF, JPG, PNG (Máx 5MB)', 'wp-agency-toolkit' ) . '</span>';
					break;

				default: // text, tel, number, date
					echo '<input type="' . esc_attr( $f_type ) . '" name="' . esc_attr( $input_name ) . '" id="' . esc_attr( $html_id ) . '" class="wpat-reg-input" placeholder="' . esc_attr( $f_plh ) . '" ' . ( $f_req ? 'required' : '' ) . ' />';
					break;
			}
		}

		echo '</div>';
	}

	/**
	 * Procesa el envío seguro del formulario de registro.
	 */
	public function process_registration_form() {
		if ( ! isset( $_POST['wpat_reg_action'] ) || 'submit_registration' !== $_POST['wpat_reg_action'] ) {
			return;
		}

		// 1. Verificación de seguridad Nonce
		if ( ! isset( $_POST['wpat_reg_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['wpat_reg_nonce'] ), 'wpat_submit_registration_action' ) ) {
			$this->redirect_with_error( __( 'Sesión caducada. Por favor, recarga la página e inténtalo de nuevo.', 'wp-agency-toolkit' ) );
			return;
		}

		// 2. Comprobación de Honeypot Anti-Spam
		if ( ! empty( $_POST['wpat_reg_hp'] ) ) {
			// Es un bot automatizado. Simular éxito para descartarlo silenciosamente.
			wp_safe_redirect( home_url() );
			exit;
		}

		$form_id = isset( $_POST['wpat_form_id'] ) ? sanitize_text_field( $_POST['wpat_form_id'] ) : '';
		$form    = self::get_form( $form_id );
		if ( ! $form ) {
			$this->redirect_with_error( __( 'Formulario de registro no válido.', 'wp-agency-toolkit' ) );
			return;
		}

		$email    = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$password = isset( $_POST['user_pass'] ) ? $_POST['user_pass'] : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->redirect_with_error( __( 'Debes introducir una dirección de correo electrónico válida.', 'wp-agency-toolkit' ) );
			return;
		}

		if ( email_exists( $email ) ) {
			$this->redirect_with_error( __( 'Ya existe una cuenta registrada con este correo electrónico. Por favor, inicia sesión.', 'wp-agency-toolkit' ) );
			return;
		}

		if ( empty( $password ) || strlen( $password ) < 6 ) {
			$this->redirect_with_error( __( 'La contraseña debe contener al menos 6 caracteres.', 'wp-agency-toolkit' ) );
			return;
		}

		// Validar campos obligatorios
		$submitted_fields = isset( $_POST['wpat_fields'] ) && is_array( $_POST['wpat_fields'] ) ? $_POST['wpat_fields'] : array();
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $f ) {
				if ( 'heading' === $f['type'] ) {
					continue;
				}

				$fid = $f['id'];
				$req = ! empty( $f['required'] ) && '1' === (string) $f['required'];

				if ( 'file' === $f['type'] ) {
					$file_key = 'wpat_file_' . $fid;
					if ( $req && ( empty( $_FILES[ $file_key ] ) || empty( $_FILES[ $file_key ]['name'] ) ) ) {
						$this->redirect_with_error( sprintf( __( 'El archivo "%s" es obligatorio.', 'wp-agency-toolkit' ), $f['label'] ) );
						return;
					}
				} elseif ( $req && ( ! isset( $submitted_fields[ $fid ] ) || '' === trim( (string) $submitted_fields[ $fid ] ) ) ) {
					$this->redirect_with_error( sprintf( __( 'El campo "%s" es obligatorio.', 'wp-agency-toolkit' ), $f['label'] ) );
					return;
				}
			}
		}

		// Asignación de rol
		$assigned_role = isset( $form['default_role'] ) ? $form['default_role'] : 'customer';
		if ( isset( $form['role_assignment'] ) && 'dropdown' === $form['role_assignment'] && ! empty( $_POST['user_role'] ) ) {
			$chosen_role = sanitize_key( $_POST['user_role'] );
			if ( ! empty( $form['allowed_roles'] ) && in_array( $chosen_role, $form['allowed_roles'], true ) ) {
				$assigned_role = $chosen_role;
			}
		}

		// Asignación condicional de rol por campo (ej. Recargo de Equivalencia = Sí)
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $cf_field ) {
				if ( ! empty( $cf_field['conditional_role_enabled'] ) && ! empty( $cf_field['conditional_role'] ) && ! empty( $cf_field['conditional_value'] ) ) {
					$cf_id      = $cf_field['id'];
					$cf_val     = isset( $submitted_fields[ $cf_id ] ) ? trim( (string) $submitted_fields[ $cf_id ] ) : '';
					$target_val = trim( (string) $cf_field['conditional_value'] );

					if ( '' !== $cf_val && strcasecmp( $cf_val, $target_val ) === 0 ) {
						$assigned_role = $cf_field['conditional_role'];
						break;
					}
				}
			}
		}

		// Crear usuario en WordPress / WooCommerce
		$username = sanitize_user( current( explode( '@', $email ) ) );
		if ( username_exists( $username ) ) {
			$username .= '_' . wp_rand( 100, 999 );
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			$this->redirect_with_error( $user_id->get_error_message() );
			return;
		}

		// Asignar rol
		$user = new WP_User( $user_id );
		$user->set_role( $assigned_role );

		// Procesar y guardar campos de formulario
		$first_name = '';
		$last_name  = '';

		foreach ( $submitted_fields as $f_key => $f_val ) {
			$clean_val = is_array( $f_val ) ? array_map( 'sanitize_text_field', $f_val ) : sanitize_text_field( wp_unslash( $f_val ) );

			// Sincronización directa con WooCommerce usermeta si es campo de facturación
			if ( strpos( $f_key, 'billing_' ) === 0 || strpos( $f_key, 'shipping_' ) === 0 ) {
				update_user_meta( $user_id, $f_key, $clean_val );

				if ( 'billing_first_name' === $f_key ) {
					$first_name = $clean_val;
					update_user_meta( $user_id, 'first_name', $clean_val );
				} elseif ( 'billing_last_name' === $f_key ) {
					$last_name = $clean_val;
					update_user_meta( $user_id, 'last_name', $clean_val );
				}
			} else {
				// Metadato personalizado del formulario
				update_user_meta( $user_id, 'wpat_reg_' . $f_key, $clean_val );
			}
		}

		// Sincronizar campos con meta_key personalizada explícita (ej. billing_recargo_equivalencia)
		if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $cf_field ) {
				if ( 'heading' === $cf_field['type'] ) {
					continue;
				}
				$cf_id = $cf_field['id'];
				if ( isset( $submitted_fields[ $cf_id ] ) && ! empty( $cf_field['meta_key'] ) ) {
					$val         = sanitize_text_field( wp_unslash( $submitted_fields[ $cf_id ] ) );
					$custom_meta = sanitize_key( $cf_field['meta_key'] );
					update_user_meta( $user_id, $custom_meta, $val );
					// Si es Recargo de Equivalencia y el valor es afirmativo, también guardar flag 'yes' estándar
					if ( strcasecmp( $val, 'sí' ) === 0 || strcasecmp( $val, 'si' ) === 0 || strcasecmp( $val, 'yes' ) === 0 || '1' === $val ) {
						update_user_meta( $user_id, $custom_meta, 'yes' );
					}
				}
			}
		}

		if ( ! empty( $first_name ) || ! empty( $last_name ) ) {
			wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => trim( $first_name . ' ' . $last_name ),
			) );
		}

		// Procesar subida de ficheros adjuntos de forma segura
		if ( ! empty( $_FILES ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			foreach ( $_FILES as $f_input_key => $file_data ) {
				if ( strpos( $f_input_key, 'wpat_file_' ) === 0 && ! empty( $file_data['name'] ) ) {
					$field_id = str_replace( 'wpat_file_', '', $f_input_key );

					// Validación de tipos de archivo permitidos
					$allowed_mimes = array(
						'pdf'  => 'application/pdf',
						'jpg'  => 'image/jpeg',
						'jpeg' => 'image/jpeg',
						'png'  => 'image/png',
					);

					$file_type = wp_check_filetype( $file_data['name'], $allowed_mimes );
					if ( ! in_array( $file_type['type'], $allowed_mimes, true ) ) {
						continue; // Formato no permitido, omitir
					}

					// Subir a través de la API segura de WordPress
					$upload_overrides = array( 'test_form' => false, 'mimes' => $allowed_mimes );
					$uploaded_file    = wp_handle_upload( $file_data, $upload_overrides );

					if ( isset( $uploaded_file['url'] ) ) {
						update_user_meta( $user_id, 'wpat_reg_' . $field_id, esc_url_raw( $uploaded_file['url'] ) );
						update_user_meta( $user_id, 'wpat_reg_' . $field_id . '_file_path', sanitize_text_field( $uploaded_file['file'] ) );
					}
				}
			}
		}

		// Guardar ID de formulario utilizado y fecha de registro
		update_user_meta( $user_id, 'wpat_registered_via_form', $form_id );
		update_user_meta( $user_id, 'wpat_registered_date', current_time( 'mysql' ) );

		// Comprobar si requiere Aprobación Manual o Automática
		$requires_approval = isset( $form['approval_required'] ) && '1' === (string) $form['approval_required'];

		if ( $requires_approval ) {
			// Estado: Pendiente de aprobación
			update_user_meta( $user_id, 'wpat_account_status', 'pending' );

			// Notificar al Administrador por email
			$this->send_admin_pending_notification( $user_id, $form );

			// Notificar al Usuario por email que su solicitud está en proceso
			$this->send_user_pending_notification( $user_id, $form );

			// Redirigir mostrando mensaje de espera
			$redirect_url = add_query_arg(
				array(
					'wpat_reg_success' => '1',
					'wpat_pending'     => '1',
					'wpat_form_id'     => $form_id,
				),
				wp_get_referer() ? wp_get_referer() : home_url()
			);

			wp_safe_redirect( $redirect_url );
			exit;
		} else {
			// Aprobación Automática: Estado Activo
			update_user_meta( $user_id, 'wpat_account_status', 'active' );

			// Iniciar sesión automáticamente
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id );

			// Redirigir según ajustes
			$target_url = '';
			if ( ! empty( $form['redirect_type'] ) && 'custom' === $form['redirect_type'] && ! empty( $form['redirect_url'] ) ) {
				$target_url = esc_url_raw( $form['redirect_url'] );
			} elseif ( ! empty( $form['redirect_type'] ) && 'myaccount' === $form['redirect_type'] && function_exists( 'wc_get_page_permalink' ) ) {
				$target_url = wc_get_page_permalink( 'myaccount' );
			} else {
				$target_url = add_query_arg(
					array(
						'wpat_reg_success' => '1',
						'wpat_pending'     => '0',
						'wpat_form_id'     => $form_id,
					),
					wp_get_referer() ? wp_get_referer() : home_url()
				);
			}

			wp_safe_redirect( $target_url );
			exit;
		}
	}

	/**
	 * Redirige de vuelta con un mensaje de error legible.
	 *
	 * @param string $error_msg Mensaje de error.
	 */
	private function redirect_with_error( $error_msg ) {
		$referer = wp_get_referer() ? wp_get_referer() : home_url();
		$url     = add_query_arg(
			array(
				'wpat_reg_error' => rawurlencode( $error_msg ),
			),
			$referer
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Intercepta la autenticación para impedir el acceso a cuentas pendientes o rechazadas.
	 *
	 * @param WP_User|WP_Error|null $user     Objeto usuario autenticado o error.
	 * @param string                $username Nombre de usuario o email.
	 * @param string                $password Contraseña.
	 * @return WP_User|WP_Error
	 */
	public function check_user_account_status_on_login( $user, $username, $password ) {
		if ( ! is_a( $user, 'WP_User' ) ) {
			return $user;
		}

		// Administradores nunca se bloquean
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return $user;
		}

		$status = get_user_meta( $user->ID, 'wpat_account_status', true );

		if ( 'pending' === $status ) {
			return new WP_Error(
				'wpat_pending_approval',
				'<strong>' . __( 'Cuenta en revisión:', 'wp-agency-toolkit' ) . '</strong> ' .
				__( 'Tu cuenta ha sido registrada pero aún está pendiente de aprobación por un administrador. Te avisaremos por email en cuanto sea validada.', 'wp-agency-toolkit' )
			);
		}

		if ( 'rejected' === $status ) {
			return new WP_Error(
				'wpat_account_rejected',
				'<strong>' . __( 'Acceso denegado:', 'wp-agency-toolkit' ) . '</strong> ' .
				__( 'Tu solicitud de registro ha sido revisada y desestimada. Si crees que se trata de un error, por favor contacta con nosotros.', 'wp-agency-toolkit' )
			);
		}

		return $user;
	}

	/**
	 * Añade columna de estado de aprobación en la lista de usuarios de WordPress.
	 *
	 * @param array $columns Columnas existentes.
	 * @return array
	 */
	public function add_user_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'role' === $key ) {
				$new_columns['wpat_reg_status'] = __( 'Estado Registro', 'wp-agency-toolkit' );
			}
		}
		if ( ! isset( $new_columns['wpat_reg_status'] ) ) {
			$new_columns['wpat_reg_status'] = __( 'Estado Registro', 'wp-agency-toolkit' );
		}
		return $new_columns;
	}

	/**
	 * Renderiza el contenido de la columna de estado de registro en users.php.
	 *
	 * @param string $output      Salida HTML actual.
	 * @param string $column_name Nombre de la columna.
	 * @param int    $user_id     ID del usuario.
	 * @return string
	 */
	public function render_user_columns( $output, $column_name, $user_id ) {
		if ( 'wpat_reg_status' !== $column_name ) {
			return $output;
		}

		$status = get_user_meta( $user_id, 'wpat_account_status', true );
		if ( empty( $status ) ) {
			$status = 'active'; // Cuentas nativas previas o sin moderación
		}

		$badge = '';
		$actions = '';
		$nonce = wp_create_nonce( 'wpat_reg_forms_nonce' );

		if ( 'pending' === $status ) {
			$badge   = '<span class="wpat-reg-badge pending">' . esc_html__( 'Pendiente', 'wp-agency-toolkit' ) . '</span>';
			$actions = '<div class="wpat-user-actions-cell" style="margin-top:6px; display:flex; gap:6px;">' .
				'<button type="button" class="button button-small button-primary wpat-user-status-btn" data-user-id="' . (int) $user_id . '" data-status="active" data-nonce="' . esc_attr( $nonce ) . '" title="Aprobar">' . esc_html__( 'Aprobar', 'wp-agency-toolkit' ) . '</button>' .
				'<button type="button" class="button button-small wpat-user-status-btn" data-user-id="' . (int) $user_id . '" data-status="rejected" data-nonce="' . esc_attr( $nonce ) . '" title="Rechazar" style="color:#b91c1c;">' . esc_html__( 'Rechazar', 'wp-agency-toolkit' ) . '</button>' .
			'</div>';
		} elseif ( 'rejected' === $status ) {
			$badge   = '<span class="wpat-reg-badge rejected">' . esc_html__( 'Rechazado', 'wp-agency-toolkit' ) . '</span>';
			$actions = '<div class="wpat-user-actions-cell" style="margin-top:6px;">' .
				'<button type="button" class="button button-small wpat-user-status-btn" data-user-id="' . (int) $user_id . '" data-status="active" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Reactivar', 'wp-agency-toolkit' ) . '</button>' .
			'</div>';
		} else {
			$badge = '<span class="wpat-reg-badge active">' . esc_html__( 'Activo', 'wp-agency-toolkit' ) . '</span>';
		}

		return $badge . $actions;
	}

	/**
	 * Endpoint AJAX para actualizar el estado del usuario (active, pending, rejected).
	 */
	public function ajax_update_user_reg_status() {
		check_ajax_referer( 'wpat_reg_forms_nonce', 'security' );

		if ( ! current_user_can( 'promote_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'wp-agency-toolkit' ) ) );
		}

		$user_id    = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$new_status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';

		if ( ! $user_id || ! in_array( $new_status, array( 'active', 'pending', 'rejected' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Parámetros no válidos.', 'wp-agency-toolkit' ) ) );
		}

		$old_status = get_user_meta( $user_id, 'wpat_account_status', true );
		update_user_meta( $user_id, 'wpat_account_status', $new_status );

		// Si se aprueba y antes estaba pendiente, enviar email de bienvenida/aprobación
		if ( 'active' === $new_status && 'pending' === $old_status ) {
			$this->send_user_approved_notification( $user_id );
		} elseif ( 'rejected' === $new_status ) {
			$this->send_user_rejected_notification( $user_id );
		}

		wp_send_json_success( array(
			'user_id'    => $user_id,
			'status'     => $new_status,
			'status_lbl' => ucfirst( $new_status ),
		) );
	}

	/**
	 * Endpoint AJAX para generar la vista previa interactiva de un formulario.
	 */
	public function ajax_preview_registration_form() {
		check_ajax_referer( 'wpat_reg_forms_nonce', 'security' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'wp-agency-toolkit' ) ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? sanitize_key( $_POST['form_id'] ) : '';
		$forms   = self::get_forms();

		// Si se envían datos en vivo desde el editor
		if ( isset( $_POST['live_data'] ) && is_array( $_POST['live_data'] ) ) {
			$live = wp_unslash( $_POST['live_data'] );
			$form = array(
				'id'                => ! empty( $form_id ) ? $form_id : 'form-preview',
				'title'             => ! empty( $live['title'] ) ? sanitize_text_field( $live['title'] ) : __( 'Formulario de Registro', 'wp-agency-toolkit' ),
				'desc'              => isset( $live['desc'] ) ? sanitize_textarea_field( $live['desc'] ) : '',
				'button_text'       => ! empty( $live['button_text'] ) ? sanitize_text_field( $live['button_text'] ) : __( 'Completar Registro', 'wp-agency-toolkit' ),
				'role_assignment'   => isset( $live['role_assignment'] ) ? sanitize_key( $live['role_assignment'] ) : 'fixed',
				'default_role'      => isset( $live['default_role'] ) ? sanitize_key( $live['default_role'] ) : 'customer',
				'allowed_roles'     => isset( $live['allowed_roles'] ) && is_array( $live['allowed_roles'] ) ? array_map( 'sanitize_key', $live['allowed_roles'] ) : array( 'customer' ),
				'approval_required' => isset( $live['approval_required'] ) ? sanitize_key( $live['approval_required'] ) : '0',
				'fields'            => array(),
			);

			if ( isset( $live['fields'] ) && is_array( $live['fields'] ) ) {
				foreach ( $live['fields'] as $f ) {
					if ( ! empty( $f['id'] ) ) {
						$f_type          = sanitize_key( $f['type'] );
						$raw_options_str = isset( $f['options_str'] ) ? sanitize_text_field( $f['options_str'] ) : '';
						$parsed_options  = array();
						if ( ! empty( $raw_options_str ) ) {
							$parts = preg_split( '/[\r\n,]+/', $raw_options_str );
							foreach ( $parts as $p ) {
								$p = trim( $p );
								if ( '' === $p ) {
									continue;
								}
								if ( strpos( $p, ':' ) !== false ) {
									list( $k, $v ) = explode( ':', $p, 2 );
									$parsed_options[ sanitize_text_field( trim( $k ) ) ] = sanitize_text_field( trim( $v ) );
								} else {
									$parsed_options[ sanitize_text_field( $p ) ] = sanitize_text_field( $p );
								}
							}
						}

						$form['fields'][] = array(
							'id'          => sanitize_key( $f['id'] ),
							'type'        => $f_type,
							'label'       => sanitize_text_field( $f['label'] ),
							'placeholder' => isset( $f['placeholder'] ) ? sanitize_text_field( $f['placeholder'] ) : '',
							'subtitle'    => isset( $f['subtitle'] ) ? sanitize_text_field( $f['subtitle'] ) : '',
							'options_str' => $raw_options_str,
							'options'     => $parsed_options,
							'required'    => ( 'heading' !== $f_type && ! empty( $f['required'] ) ) ? '1' : '0',
							'width'       => ( 'heading' === $f_type || ! isset( $f['width'] ) || 'full' === $f['width'] ) ? 'full' : 'half',
							'is_native'   => ! empty( $f['is_native'] ) ? '1' : '0',
						);
					}
				}
			}
		} elseif ( ! empty( $form_id ) && isset( $forms[ $form_id ] ) ) {
			$form = $forms[ $form_id ];
		} else {
			wp_send_json_error( array( 'message' => __( 'Formulario no encontrado.', 'wp-agency-toolkit' ) ) );
		}

		$html = $this->generate_form_markup( $form, true );

		wp_send_json_success( array(
			'title'     => $form['title'],
			'shortcode' => '[wpat_registration_form id="' . esc_attr( $form['id'] ) . '"]',
			'html'      => $html,
		) );
	}

	/**
	 * Muestra los datos de registro personalizado en la pantalla de edición de usuario.
	 *
	 * @param WP_User $user Objeto de usuario de WP.
	 */
	public function render_user_profile_fields( $user ) {
		$form_id    = get_user_meta( $user->ID, 'wpat_registered_via_form', true );
		$reg_date   = get_user_meta( $user->ID, 'wpat_registered_date', true );
		$status     = get_user_meta( $user->ID, 'wpat_account_status', true );
		if ( empty( $status ) ) {
			$status = 'active';
		}
		?>
		<h2><?php esc_html_e( 'Información de Registro Personalizado (WP Agency Toolkit)', 'wp-agency-toolkit' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="wpat_account_status"><?php esc_html_e( 'Estado de la Cuenta', 'wp-agency-toolkit' ); ?></label></th>
				<td>
					<select name="wpat_account_status" id="wpat_account_status">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Activo (Acceso permitido)', 'wp-agency-toolkit' ); ?></option>
						<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pendiente de Aprobación', 'wp-agency-toolkit' ); ?></option>
						<option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rechazado (Acceso denegado)', 'wp-agency-toolkit' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Controla si el usuario puede iniciar sesión en la tienda o si su acceso está bloqueado.', 'wp-agency-toolkit' ); ?></p>
				</td>
			</tr>
			<?php if ( ! empty( $form_id ) ) : ?>
				<tr>
					<th><?php esc_html_e( 'Formulario de Origen', 'wp-agency-toolkit' ); ?></th>
					<td><code><?php echo esc_html( $form_id ); ?></code> <?php if ( ! empty( $reg_date ) ) : ?><span style="color:#64748b;">(<?php echo esc_html( $reg_date ); ?>)</span><?php endif; ?></td>
				</tr>
			<?php endif; ?>

			<!-- Inspección de metadatos personalizados wpat_reg_* -->
			<?php
			$all_meta = get_user_meta( $user->ID );
			foreach ( $all_meta as $key => $values ) {
				if ( strpos( $key, 'wpat_reg_' ) === 0 && strpos( $key, '_file_path' ) === false ) {
					$field_name = str_replace( 'wpat_reg_', '', $key );
					$val        = isset( $values[0] ) ? $values[0] : '';
					$is_url     = filter_var( $val, FILTER_VALIDATE_URL );
					?>
					<tr>
						<th><?php echo esc_html( ucwords( str_replace( '_', ' ', $field_name ) ) ); ?></th>
						<td>
							<?php if ( $is_url ) : ?>
								<a href="<?php echo esc_url( $val ); ?>" target="_blank" class="button button-small" style="display:inline-flex; align-items:center; gap:4px;">
									<span class="dashicons dashicons-media-document" style="font-size:16px; width:16px; height:16px;"></span>
									<?php esc_html_e( 'Ver Archivo Adjunto', 'wp-agency-toolkit' ); ?>
								</a>
								<span style="font-size:12px; color:#64748b; margin-left:8px;"><?php echo esc_html( basename( $val ) ); ?></span>
							<?php else : ?>
								<input type="text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" class="regular-text" />
							<?php endif; ?>
						</td>
					</tr>
					<?php
				}
			}
			?>
		</table>
		<?php
	}

	/**
	 * Guarda cambios en los campos personalizados desde la pantalla de edición de usuario.
	 *
	 * @param int $user_id ID del usuario.
	 */
	public function save_user_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( isset( $_POST['wpat_account_status'] ) ) {
			$new_status = sanitize_key( $_POST['wpat_account_status'] );
			if ( in_array( $new_status, array( 'active', 'pending', 'rejected' ), true ) ) {
				update_user_meta( $user_id, 'wpat_account_status', $new_status );
			}
		}

		foreach ( $_POST as $key => $val ) {
			if ( strpos( $key, 'wpat_reg_' ) === 0 ) {
				update_user_meta( $user_id, sanitize_key( $key ), sanitize_text_field( wp_unslash( $val ) ) );
			}
		}
	}

	/**
	 * Envía notificación al administrador cuando se crea una cuenta pendiente.
	 *
	 * @param int   $user_id ID del usuario.
	 * @param array $form    Datos del formulario.
	 */
	private function send_admin_pending_notification( $user_id, $form ) {
		$admin_email = get_option( 'admin_email' );
		$user        = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$company = get_user_meta( $user_id, 'billing_company', true );
		$cif     = get_user_meta( $user_id, 'billing_vat', true );

		$subject = sprintf( '[%s] Nueva solicitud de registro de cliente pendiente de aprobación', get_bloginfo( 'name' ) );
		$edit_link = admin_url( 'user-edit.php?user_id=' . $user_id );
		$users_list = admin_url( 'users.php' );

		$message = "Hola Administrador,\r\n\r\n";
		$message .= "Se ha registrado un nuevo usuario a través del formulario \"" . $form['title'] . "\" y requiere tu revisión y aprobación:\r\n\r\n";
		$message .= "• Usuario: " . $user->user_login . "\r\n";
		$message .= "• Email: " . $user->user_email . "\r\n";
		$message .= "• Nombre: " . $user->display_name . "\r\n";
		if ( ! empty( $company ) ) {
			$message .= "• Empresa: " . $company . "\r\n";
		}
		if ( ! empty( $cif ) ) {
			$message .= "• CIF / NIF: " . $cif . "\r\n";
		}
		$message .= "\r\nPuedes revisar y aprobar o rechazar la solicitud directamente desde:\r\n" . $users_list . "\r\n\r\n";
		$message .= "O ver su perfil completo:\r\n" . $edit_link . "\r\n";

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Envía notificación al usuario informando de que su cuenta está pendiente de validación.
	 *
	 * @param int   $user_id ID del usuario.
	 * @param array $form    Datos del formulario.
	 */
	private function send_user_pending_notification( $user_id, $form ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf( 'Hemos recibido tu solicitud de registro en %s', get_bloginfo( 'name' ) );
		$message = "Hola " . $user->display_name . ",\r\n\r\n";
		$message .= "Gracias por registrarte en " . get_bloginfo( 'name' ) . ".\r\n\r\n";
		$message .= "Tu solicitud de cuenta ha sido recibida correctamente y está en proceso de revisión por parte de nuestro equipo comercial.\r\n";
		$message .= "En cuanto tu cuenta sea aprobada, recibirás una notificación de confirmación para que puedas acceder a la tienda y comenzar a comprar.\r\n\r\n";
		$message .= "Un cordial saludo,\r\n";
		$message .= get_bloginfo( 'name' );

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Envía notificación al usuario cuando su cuenta ha sido aprobada.
	 *
	 * @param int $user_id ID del usuario.
	 */
	private function send_user_approved_notification( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$login_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		$subject   = sprintf( '¡Tu cuenta en %s ha sido aprobada!', get_bloginfo( 'name' ) );

		$message = "Hola " . $user->display_name . ",\r\n\r\n";
		$message .= "¡Buenas noticias! Tu cuenta de cliente en " . get_bloginfo( 'name' ) . " ha sido aprobada y ya se encuentra activa.\r\n\r\n";
		$message .= "Ya puedes iniciar sesión con tu correo electrónico (" . $user->user_email . ") y la contraseña que elegiste al registrarte:\r\n";
		$message .= $login_url . "\r\n\r\n";
		$message .= "Gracias por tu confianza,\r\n";
		$message .= get_bloginfo( 'name' );

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Envía notificación al usuario si su cuenta ha sido rechazada.
	 *
	 * @param int $user_id ID del usuario.
	 */
	private function send_user_rejected_notification( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf( 'Actualización sobre tu solicitud de cuenta en %s', get_bloginfo( 'name' ) );
		$message = "Hola " . $user->display_name . ",\r\n\r\n";
		$message .= "Lamentamos comunicarte que tu solicitud de cuenta en " . get_bloginfo( 'name' ) . " no ha podido ser aceptada en este momento.\r\n\r\n";
		$message .= "Si consideras que ha habido un error o deseas facilitarnos información adicional, por favor responde directamente a este correo.\r\n\r\n";
		$message .= "Atentamente,\r\n";
		$message .= get_bloginfo( 'name' );

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Campos extra opcionales en el formulario nativo de Mi Cuenta de WooCommerce.
	 */
	public function render_wc_account_extra_fields() {
		$forms = self::get_forms();
		$attached_form = null;
		foreach ( $forms as $f ) {
			if ( ! empty( $f['attach_to_my_account'] ) && '1' === (string) $f['attach_to_my_account'] ) {
				$attached_form = $f;
				break;
			}
		}

		if ( ! $attached_form || empty( $attached_form['fields'] ) ) {
			return;
		}

		echo '<div class="wpat-wc-account-extra-fields" style="margin-top:15px; border-top:1px dashed #cbd5e1; padding-top:15px;">';
		echo '<h4 style="margin:0 0 10px 0; font-size:14px; color:#1e293b;">' . esc_html( $attached_form['title'] ) . '</h4>';
		echo '<input type="hidden" name="wpat_wc_reg_form_id" value="' . esc_attr( $attached_form['id'] ) . '" />';

		foreach ( $attached_form['fields'] as $field ) {
			$f_id    = esc_attr( $field['id'] );
			$f_label = esc_html( $field['label'] );
			$f_req   = ! empty( $field['required'] ) && '1' === (string) $field['required'];
			$f_plh   = isset( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '';

			echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
			echo '<label for="wpat_wc_f_' . $f_id . '">' . $f_label . ( $f_req ? ' <span class="required">*</span>' : '' ) . '</label>';
			echo '<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="wpat_fields[' . $f_id . ']' . '" id="wpat_wc_f_' . $f_id . '" placeholder="' . $f_plh . '" ' . ( $f_req ? 'required' : '' ) . ' />';
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Guarda metadatos si el registro vino por el formulario estándar de WooCommerce.
	 *
	 * @param int $customer_id ID del cliente creado.
	 */
	public function handle_wc_account_created_customer( $customer_id ) {
		if ( empty( $_POST['wpat_wc_reg_form_id'] ) || empty( $_POST['wpat_fields'] ) || ! is_array( $_POST['wpat_fields'] ) ) {
			return;
		}

		$form_id = sanitize_text_field( $_POST['wpat_wc_reg_form_id'] );
		$form    = self::get_form( $form_id );

		foreach ( $_POST['wpat_fields'] as $key => $val ) {
			$clean = sanitize_text_field( wp_unslash( $val ) );
			if ( strpos( $key, 'billing_' ) === 0 ) {
				update_user_meta( $customer_id, $key, $clean );
			} else {
				update_user_meta( $customer_id, 'wpat_reg_' . $key, $clean );
			}
		}

		// Si ese formulario requiere aprobación manual
		if ( $form && isset( $form['approval_required'] ) && '1' === (string) $form['approval_required'] ) {
			update_user_meta( $customer_id, 'wpat_account_status', 'pending' );
			$this->send_admin_pending_notification( $customer_id, $form );
			$this->send_user_pending_notification( $customer_id, $form );
		}
	}
}

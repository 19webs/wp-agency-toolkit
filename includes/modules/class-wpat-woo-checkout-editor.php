<?php
/**
 * Módulo: Editor de Campos de Checkout (WooCommerce) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Checkout_Editor {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Checkout_Editor
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Checkout_Editor
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
		// Filtros del formulario de checkout en frontend
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), 9999 );

		// Validar campos obligatorios personalizados
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_custom_checkout_fields' ) );

		// Guardar metadatos en el pedido
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_checkout_fields' ), 10, 2 );

		// Mostrar campos en los correos electrónicos del pedido
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'display_fields_in_emails' ), 10, 3 );

		// Mostrar campos en el panel de administración del pedido (WP Admin)
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_fields_in_admin_billing' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_fields_in_admin_shipping' ) );

		// Exponer automáticamente en la API REST de WooCommerce para CRM/ERP
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'expose_fields_in_rest_api' ), 10, 3 );
	}

	/**
	 * Personaliza, añade u oculta campos en el Checkout de WooCommerce.
	 *
	 * @param array $fields Campos del checkout.
	 * @return array
	 */
	public function customize_checkout_fields( $fields ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $fields;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-checkout-editor'] ) || empty( $settings['checkout_editor_enabled'] ) ) {
			return $fields;
		}

		// 1. Ocultar o deshabilitar campos nativos según configuración
		$disabled_fields = isset( $settings['checkout_disabled_fields'] ) && is_array( $settings['checkout_disabled_fields'] ) ? $settings['checkout_disabled_fields'] : array();

		foreach ( $disabled_fields as $field_key ) {
			if ( strpos( $field_key, 'billing_' ) === 0 && isset( $fields['billing'][ $field_key ] ) ) {
				unset( $fields['billing'][ $field_key ] );
			} elseif ( strpos( $field_key, 'shipping_' ) === 0 && isset( $fields['shipping'][ $field_key ] ) ) {
				unset( $fields['shipping'][ $field_key ] );
			} elseif ( isset( $fields['order'][ $field_key ] ) ) {
				unset( $fields['order'][ $field_key ] );
			}
		}

		// 2. Ajustar campos requeridos nativos
		if ( ! empty( $settings['checkout_nif_enabled'] ) ) {
			$nif_required = isset( $settings['checkout_nif_required'] ) ? '1' === $settings['checkout_nif_required'] : true;
			$fields['billing']['billing_nif'] = array(
				'label'       => __( 'NIF / CIF / DNI', 'wp-agency-toolkit' ),
				'placeholder' => __( 'Ej. 12345678X', 'wp-agency-toolkit' ),
				'required'    => $nif_required,
				'class'       => array( 'form-row-wide' ),
				'clear'       => true,
				'priority'    => 25,
			);
		}

		// 3. Añadir campos personalizados dinámicos
		$custom_fields = isset( $settings['checkout_custom_fields'] ) && is_array( $settings['checkout_custom_fields'] ) ? $settings['checkout_custom_fields'] : array();

		foreach ( $custom_fields as $cf ) {
			if ( empty( $cf['key'] ) || empty( $cf['label'] ) ) {
				continue;
			}

			$section = ! empty( $cf['section'] ) ? $cf['section'] : 'billing';
			if ( ! isset( $fields[ $section ] ) ) {
				$fields[ $section ] = array();
			}

			$field_type = ! empty( $cf['type'] ) ? $cf['type'] : 'text';
			$field_data = array(
				'type'        => 'date' === $field_type ? 'date' : ( 'select' === $field_type ? 'select' : ( 'checkbox' === $field_type ? 'checkbox' : ( 'textarea' === $field_type ? 'textarea' : 'text' ) ) ),
				'label'       => sanitize_text_field( $cf['label'] ),
				'placeholder' => ! empty( $cf['placeholder'] ) ? sanitize_text_field( $cf['placeholder'] ) : '',
				'required'    => ! empty( $cf['required'] ) && '1' === $cf['required'],
				'class'       => array( 'form-row-wide' ),
				'priority'    => ! empty( $cf['priority'] ) ? intval( $cf['priority'] ) : 100,
			);

			if ( 'select' === $field_type && ! empty( $cf['options'] ) ) {
				$opts_arr = array( '' => __( '-- Seleccionar --', 'wp-agency-toolkit' ) );
				$lines = explode( "\n", $cf['options'] );
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( ! empty( $line ) ) {
						$opts_arr[ $line ] = $line;
					}
				}
				$field_data['options'] = $opts_arr;
			}

			$fields[ $section ][ sanitize_key( $cf['key'] ) ] = $field_data;
		}

		return $fields;
	}

	/**
	 * Valida que los campos requeridos estén correctamente rellenados.
	 */
	public function validate_custom_checkout_fields() {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-checkout-editor'] ) || empty( $settings['checkout_editor_enabled'] ) ) {
			return;
		}

		// Validar NIF
		if ( ! empty( $settings['checkout_nif_enabled'] ) && ! empty( $settings['checkout_nif_required'] ) ) {
			if ( empty( $_POST['billing_nif'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				wc_add_notice( __( 'Por favor, introduce tu <strong>NIF / CIF / DNI</strong> para completar el pedido.', 'wp-agency-toolkit' ), 'error' );
			}
		}

		// Validar campos personalizados obligatorios
		$custom_fields = isset( $settings['checkout_custom_fields'] ) && is_array( $settings['checkout_custom_fields'] ) ? $settings['checkout_custom_fields'] : array();
		foreach ( $custom_fields as $cf ) {
			if ( ! empty( $cf['key'] ) && ! empty( $cf['required'] ) && '1' === $cf['required'] ) {
				$key = sanitize_key( $cf['key'] );
				if ( empty( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					/* translators: %s: nombre del campo */
					wc_add_notice( sprintf( __( 'El campo <strong>%s</strong> es obligatorio.', 'wp-agency-toolkit' ), esc_html( $cf['label'] ) ), 'error' );
				}
			}
		}
	}

	/**
	 * Guarda los metadatos de los campos personalizados en el pedido.
	 *
	 * @param int   $order_id ID del pedido.
	 * @param array $posted   Datos enviados por POST.
	 */
	public function save_custom_checkout_fields( $order_id, $posted ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-checkout-editor'] ) || empty( $settings['checkout_editor_enabled'] ) ) {
			return;
		}

		// Guardar NIF
		if ( ! empty( $settings['checkout_nif_enabled'] ) && isset( $_POST['billing_nif'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$nif_val = sanitize_text_field( wp_unslash( $_POST['billing_nif'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $order_id, '_billing_nif', $nif_val );
		}

		// Guardar campos personalizados
		$custom_fields = isset( $settings['checkout_custom_fields'] ) && is_array( $settings['checkout_custom_fields'] ) ? $settings['checkout_custom_fields'] : array();
		foreach ( $custom_fields as $cf ) {
			if ( empty( $cf['key'] ) ) {
				continue;
			}
			$key = sanitize_key( $cf['key'] );
			if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				update_post_meta( $order_id, '_' . $key, $val );
				// También guardar sin guion bajo inicial para legibilidad si se requiere
				update_post_meta( $order_id, $key, $val );
			}
		}
	}

	/**
	 * Muestra los campos personalizados en los correos electrónicos del cliente y administración.
	 *
	 * @param array    $fields Campos existentes.
	 * @param bool     $sent_to_admin Si es correo de admin.
	 * @param WC_Order $order Objeto pedido.
	 * @return array
	 */
	public function display_fields_in_emails( $fields, $sent_to_admin, $order ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-checkout-editor'] ) || empty( $settings['checkout_editor_enabled'] ) ) {
			return $fields;
		}

		$order_id = $order->get_id();

		// NIF en email
		if ( ! empty( $settings['checkout_nif_enabled'] ) ) {
			$nif = get_post_meta( $order_id, '_billing_nif', true );
			if ( ! empty( $nif ) ) {
				$fields['billing_nif'] = array(
					'label' => __( 'NIF / CIF', 'wp-agency-toolkit' ),
					'value' => esc_html( $nif ),
				);
			}
		}

		// Campos personalizados en email
		$custom_fields = isset( $settings['checkout_custom_fields'] ) && is_array( $settings['checkout_custom_fields'] ) ? $settings['checkout_custom_fields'] : array();
		foreach ( $custom_fields as $cf ) {
			if ( empty( $cf['key'] ) ) {
				continue;
			}
			$key = sanitize_key( $cf['key'] );
			$val = get_post_meta( $order_id, '_' . $key, true );
			if ( ! empty( $val ) ) {
				$fields[ $key ] = array(
					'label' => esc_html( $cf['label'] ),
					'value' => esc_html( $val ),
				);
			}
		}

		return $fields;
	}

	/**
	 * Muestra el NIF y campos en la ficha del pedido en WP Admin (Dirección de Facturación).
	 *
	 * @param WC_Order $order Objeto del pedido.
	 */
	public function display_fields_in_admin_billing( $order ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-checkout-editor'] ) || empty( $settings['checkout_editor_enabled'] ) ) {
			return;
		}

		$order_id = $order->get_id();

		if ( ! empty( $settings['checkout_nif_enabled'] ) ) {
			$nif = get_post_meta( $order_id, '_billing_nif', true );
			if ( ! empty( $nif ) ) {
				echo '<p><strong>' . esc_html__( 'NIF / CIF:', 'wp-agency-toolkit' ) . '</strong> ' . esc_html( $nif ) . '</p>';
			}
		}

		$custom_fields = isset( $settings['checkout_custom_fields'] ) && is_array( $settings['checkout_custom_fields'] ) ? $settings['checkout_custom_fields'] : array();
		foreach ( $custom_fields as $cf ) {
			if ( empty( $cf['key'] ) || ( isset( $cf['section'] ) && 'billing' !== $cf['section'] ) ) {
				continue;
			}
			$key = sanitize_key( $cf['key'] );
			$val = get_post_meta( $order_id, '_' . $key, true );
			if ( ! empty( $val ) ) {
				echo '<p><strong>' . esc_html( $cf['label'] ) . ':</strong> ' . esc_html( $val ) . '</p>';
			}
		}
	}

	/**
	 * Muestra campos en la ficha del pedido en WP Admin (Dirección de Envío).
	 *
	 * @param WC_Order $order Objeto del pedido.
	 */
	public function display_fields_in_admin_shipping( $order ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-checkout-editor'] ) || empty( $settings['checkout_editor_enabled'] ) ) {
			return;
		}

		$order_id = $order->get_id();

		$custom_fields = isset( $settings['checkout_custom_fields'] ) && is_array( $settings['checkout_custom_fields'] ) ? $settings['checkout_custom_fields'] : array();
		foreach ( $custom_fields as $cf ) {
			if ( empty( $cf['key'] ) || empty( $cf['section'] ) || 'shipping' !== $cf['section'] ) {
				continue;
			}
			$key = sanitize_key( $cf['key'] );
			$val = get_post_meta( $order_id, '_' . $key, true );
			if ( ! empty( $val ) ) {
				echo '<p><strong>' . esc_html( $cf['label'] ) . ':</strong> ' . esc_html( $val ) . '</p>';
			}
		}
	}

	/**
	 * Expone los campos personalizados automáticamente en la API REST de WooCommerce para conectividad con CRM / ERP.
	 *
	 * @param WP_REST_Response $response Respuesta de la API REST.
	 * @param WC_Order         $order    Objeto del pedido.
	 * @param WP_REST_Request  $request  Petición REST.
	 * @return WP_REST_Response
	 */
	public function expose_fields_in_rest_api( $response, $order, $request ) {
		$settings = WPAT_Main::get_instance()->get_settings();
		if ( empty( $settings['woo-checkout-editor'] ) || empty( $settings['checkout_editor_enabled'] ) ) {
			return $response;
		}

		$data = $response->get_data();
		$order_id = $order->get_id();

		if ( ! isset( $data['meta_data'] ) || ! is_array( $data['meta_data'] ) ) {
			$data['meta_data'] = array();
		}

		// NIF en API REST
		if ( ! empty( $settings['checkout_nif_enabled'] ) ) {
			$nif = get_post_meta( $order_id, '_billing_nif', true );
			if ( ! empty( $nif ) ) {
				$data['billing']['nif'] = $nif;
				$data['meta_data'][] = array(
					'id'    => 0,
					'key'   => '_billing_nif',
					'value' => $nif,
				);
			}
		}

		// Campos personalizados en API REST
		$custom_fields = isset( $settings['checkout_custom_fields'] ) && is_array( $settings['checkout_custom_fields'] ) ? $settings['checkout_custom_fields'] : array();
		foreach ( $custom_fields as $cf ) {
			if ( empty( $cf['key'] ) ) {
				continue;
			}
			$key = sanitize_key( $cf['key'] );
			$val = get_post_meta( $order_id, '_' . $key, true );
			if ( ! empty( $val ) ) {
				$data['meta_data'][] = array(
					'id'    => 0,
					'key'   => '_' . $key,
					'value' => $val,
				);
			}
		}

		$response->set_data( $data );
		return $response;
	}
}

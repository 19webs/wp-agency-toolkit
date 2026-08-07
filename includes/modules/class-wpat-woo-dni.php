<?php
/**
 * Módulo: Campo DNI/CIF en el Checkout de WooCommerce - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Dni {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Dni
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Dni
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
		// Añadir campo al checkout
		add_filter( 'woocommerce_billing_fields', array( $this, 'add_dni_field' ) );
		
		// Hacer el campo editable en la administración del pedido
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'admin_billing_dni_field' ) );

		// Mostrar el DNI en la página del pedido del administrador
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_dni_in_admin_order' ) );

		// Añadir DNI a los correos de WooCommerce
		add_action( 'woocommerce_email_customer_details', array( $this, 'display_dni_in_emails' ), 15, 4 );

		// Mostrar y permitir editar el DNI en la ficha de usuario de WordPress (Perfil)
		add_filter( 'woocommerce_customer_meta_fields', array( $this, 'add_dni_to_customer_meta_fields' ) );
	}

	/**
	 * Añade el DNI/CIF a los campos del perfil de usuario en la administración de WordPress.
	 *
	 * @param array $fields Campos del perfil.
	 * @return array
	 */
	public function add_dni_to_customer_meta_fields( $fields ) {
		$fields['billing']['fields']['billing_dni'] = array(
			'label'       => 'DNI / CIF / NIE',
			'description' => 'Documento de identidad del cliente para facturación.',
		);
		return $fields;
	}

	/**
	 * Añade el campo DNI/CIF a los campos de facturación en el Checkout.
	 *
	 * @param array $fields Campos de facturación existentes.
	 * @return array
	 */
	public function add_dni_field( $fields ) {
		$fields['billing_dni'] = array(
			'label'       => 'DNI / CIF / NIE',
			'placeholder' => 'Introduce tu documento de identidad',
			'required'    => true,
			'class'       => array( 'form-row-wide' ),
			'clear'       => true,
			'priority'    => 35, // Se muestra debajo de la Empresa / Dirección
		);
		return $fields;
	}

	/**
	 * Añade el campo DNI/CIF a la lista de campos de facturación editables en la administración de pedidos.
	 *
	 * @param array $fields Campos del admin.
	 * @return array
	 */
	public function admin_billing_dni_field( $fields ) {
		$fields['dni'] = array(
			'label' => 'DNI / CIF / NIE',
			'show'  => true,
		);
		return $fields;
	}

	/**
	 * Pinta el valor del DNI en los detalles de facturación de la vista de pedido en el administrador.
	 *
	 * @param WC_Order $order Objeto de pedido WooCommerce.
	 */
	public function display_dni_in_admin_order( $order ) {
		$dni = $order->get_meta( '_billing_dni' );
		if ( ! empty( $dni ) ) {
			echo '<p><strong>DNI / CIF / NIE:</strong> ' . esc_html( $dni ) . '</p>';
		}
	}

	/**
	 * Inyecta el DNI en los correos electrónicos transaccionales de WooCommerce.
	 *
	 * @param WC_Order $order Objeto de pedido WooCommerce.
	 * @param bool     $sent_to_admin Si se envía al administrador.
	 * @param bool     $plain_text Formato de texto plano.
	 * @param string   $email Tipo de email.
	 */
	public function display_dni_in_emails( $order, $sent_to_admin, $plain_text, $email ) {
		$dni = $order->get_meta( '_billing_dni' );
		if ( ! empty( $dni ) ) {
			if ( $plain_text ) {
				echo "\nDNI / CIF / NIE: " . esc_html( $dni );
			} else {
				echo '<p style="margin: 0 0 16px;"><strong>DNI / CIF / NIE:</strong> ' . esc_html( $dni ) . '</p>';
			}
		}
	}
}

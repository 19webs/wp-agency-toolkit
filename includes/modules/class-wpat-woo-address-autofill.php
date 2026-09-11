<?php
/**
 * Módulo: Autocompletado de CP y Provincia (WooCommerce) - WP Agency Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Woo_Address_Autofill {

	/**
	 * Instancia única de la clase.
	 *
	 * @var WPAT_Woo_Address_Autofill
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton de la clase.
	 *
	 * @return WPAT_Woo_Address_Autofill
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_autofill_assets' ) );
	}

	/**
	 * Comprueba si la petición actual corresponde a la página de checkout.
	 *
	 * @return bool
	 */
	private function is_checkout_page() {
		if ( is_admin() ) {
			return false;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && ! is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$checkout_id = wc_get_page_id( 'checkout' );
			if ( $checkout_id && is_page( $checkout_id ) && ! is_order_received_page() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Encola los estilos y scripts del autocompletado de dirección.
	 */
	public function enqueue_autofill_assets() {
		if ( ! $this->is_checkout_page() ) {
			return;
		}

		$settings = WPAT_Main::get_instance()->get_settings();
		$active   = isset( $settings['woo-address-autofill'] ) && '1' === $settings['woo-address-autofill'];

		if ( ! $active ) {
			return;
		}

		wp_enqueue_script(
			'wpat-address-autofill-js',
			WPAT_URL . 'assets/js/wpat-address-autofill.js',
			array( 'jquery' ),
			WPAT_VERSION,
			true
		);

		$spain_provinces = array(
			'01' => 'VI', '02' => 'AB', '03' => 'A',  '04' => 'AL', '05' => 'AV',
			'06' => 'BA', '07' => 'PM', '08' => 'B',  '09' => 'BU', '10' => 'CC',
			'11' => 'CA', '12' => 'CS', '13' => 'CR', '14' => 'CO', '15' => 'C',
			'16' => 'CU', '17' => 'GI', '18' => 'GR', '19' => 'GU', '20' => 'SS',
			'21' => 'H',  '22' => 'HU', '23' => 'J',  '24' => 'LE', '25' => 'L',
			'26' => 'LO', '27' => 'LU', '28' => 'M',  '29' => 'MA', '30' => 'MU',
			'31' => 'NA', '32' => 'OR', '33' => 'O',  '34' => 'P',  '35' => 'GC',
			'36' => 'PO', '37' => 'SA', '38' => 'TF', '39' => 'S',  '40' => 'SG',
			'41' => 'SE', '42' => 'SO', '43' => 'T',  '44' => 'TE', '45' => 'TO',
			'46' => 'V',  '47' => 'VA', '48' => 'BI', '49' => 'ZA', '50' => 'Z',
			'51' => 'CE', '52' => 'ML',
		);

		$spain_cities = array(
			'01001' => 'Vitoria-Gasteiz', '02001' => 'Albacete', '03001' => 'Alicante/Alacant', '04001' => 'Almería',
			'05001' => 'Ávila', '06001' => 'Badajoz', '07001' => 'Palma de Mallorca', '08001' => 'Barcelona',
			'09001' => 'Burgos', '10001' => 'Cáceres', '11001' => 'Cádiz', '11201' => 'Algeciras', '11500' => 'El Puerto de Santa María', '11401' => 'Jerez de la Frontera',
			'12001' => 'Castellón de la Plana', '13001' => 'Ciudad Real', '14001' => 'Córdoba', '15001' => 'A Coruña', '15701' => 'Santiago de Compostela',
			'16001' => 'Cuenca', '17001' => 'Girona', '18001' => 'Granada', '19001' => 'Guadalajara', '20001' => 'Donostia / San Sebastián',
			'21001' => 'Huelva', '22001' => 'Huesca', '23001' => 'Jaén', '24001' => 'León', '24401' => 'Ponferrada',
			'25001' => 'Lleida', '26001' => 'Logroño', '27001' => 'Lugo', '28001' => 'Madrid', '28002' => 'Madrid', '28003' => 'Madrid', '28901' => 'Getafe', '28911' => 'Leganés', '28931' => 'Móstoles', '28801' => 'Alcalá de Henares',
			'29001' => 'Málaga', '29601' => 'Marbella', '29602' => 'Marbella', '29640' => 'Fuengirola', '29620' => 'Torremolinos', '29630' => 'Benalmádena', '29700' => 'Vélez-Málaga', '29740' => 'Torre del Mar', '29400' => 'Ronda', '29200' => 'Antequera',
			'30001' => 'Murcia', '30201' => 'Cartagena', '31001' => 'Pamplona / Iruña', '32001' => 'Ourense', '33001' => 'Oviedo', '33201' => 'Gijón',
			'34001' => 'Palencia', '35001' => 'Las Palmas de Gran Canaria', '35500' => 'Arrecife', '35600' => 'Puerto del Rosario',
			'36001' => 'Pontevedra', '36201' => 'Vigo', '37001' => 'Salamanca', '38001' => 'Santa Cruz de Tenerife', '38201' => 'San Cristóbal de La Laguna',
			'39001' => 'Santander', '40001' => 'Segovia', '41001' => 'Sevilla', '41500' => 'Alcalá de Guadaíra', '41701' => 'Dos Hermanas',
			'42001' => 'Soria', '43001' => 'Tarragona', '43201' => 'Reus', '44001' => 'Teruel', '45001' => 'Toledo', '45600' => 'Talavera de la Reina',
			'46001' => 'Valencia', '46700' => 'Gandia', '47001' => 'Valladolid', '48001' => 'Bilbao', '49001' => 'Zamora', '50001' => 'Zaragoza',
			'51001' => 'Ceuta', '52001' => 'Melilla',
		);

		wp_localize_script( 'wpat-address-autofill-js', 'wpatAutofillOptions', array(
			'spain_provinces' => $spain_provinces,
			'spain_cities'    => $spain_cities,
			'autofill_city'   => isset( $settings['woo_address_autofill_city'] ) ? $settings['woo_address_autofill_city'] : '1',
		) );
	}
}

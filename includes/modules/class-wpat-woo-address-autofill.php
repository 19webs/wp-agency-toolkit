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

		global $post;
		if ( is_a( $post, 'WP_Post' ) ) {
			if ( has_shortcode( $post->post_content, 'woocommerce_checkout' ) || has_block( 'woocommerce/checkout', $post ) ) {
				return true;
			}
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( $_SERVER['REQUEST_URI'] ), 'checkout' ) !== false && ! is_order_received_page() ) {
			return true;
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
			'01' => array( 'code' => 'VI', 'name' => 'Álava' ),
			'02' => array( 'code' => 'AB', 'name' => 'Albacete' ),
			'03' => array( 'code' => 'A',  'name' => 'Alicante' ),
			'04' => array( 'code' => 'AL', 'name' => 'Almería' ),
			'05' => array( 'code' => 'AV', 'name' => 'Ávila' ),
			'06' => array( 'code' => 'BA', 'name' => 'Badajoz' ),
			'07' => array( 'code' => 'PM', 'name' => 'Baleares' ),
			'08' => array( 'code' => 'B',  'name' => 'Barcelona' ),
			'09' => array( 'code' => 'BU', 'name' => 'Burgos' ),
			'10' => array( 'code' => 'CC', 'name' => 'Cáceres' ),
			'11' => array( 'code' => 'CA', 'name' => 'Cádiz' ),
			'12' => array( 'code' => 'CS', 'name' => 'Castellón' ),
			'13' => array( 'code' => 'CR', 'name' => 'Ciudad Real' ),
			'14' => array( 'code' => 'CO', 'name' => 'Córdoba' ),
			'15' => array( 'code' => 'C',  'name' => 'A Coruña' ),
			'16' => array( 'code' => 'CU', 'name' => 'Cuenca' ),
			'17' => array( 'code' => 'GI', 'name' => 'Girona' ),
			'18' => array( 'code' => 'GR', 'name' => 'Granada' ),
			'19' => array( 'code' => 'GU', 'name' => 'Guadalajara' ),
			'20' => array( 'code' => 'SS', 'name' => 'Gipuzkoa' ),
			'21' => array( 'code' => 'H',  'name' => 'Huelva' ),
			'22' => array( 'code' => 'HU', 'name' => 'Huesca' ),
			'23' => array( 'code' => 'J',  'name' => 'Jaén' ),
			'24' => array( 'code' => 'LE', 'name' => 'León' ),
			'25' => array( 'code' => 'L',  'name' => 'Lleida' ),
			'26' => array( 'code' => 'LO', 'name' => 'La Rioja' ),
			'27' => array( 'code' => 'LU', 'name' => 'Lugo' ),
			'28' => array( 'code' => 'M',  'name' => 'Madrid' ),
			'29' => array( 'code' => 'MA', 'name' => 'Málaga' ),
			'30' => array( 'code' => 'MU', 'name' => 'Murcia' ),
			'31' => array( 'code' => 'NA', 'name' => 'Navarra' ),
			'32' => array( 'code' => 'OR', 'name' => 'Ourense' ),
			'33' => array( 'code' => 'O',  'name' => 'Asturias' ),
			'34' => array( 'code' => 'P',  'name' => 'Palencia' ),
			'35' => array( 'code' => 'GC', 'name' => 'Las Palmas' ),
			'36' => array( 'code' => 'PO', 'name' => 'Pontevedra' ),
			'37' => array( 'code' => 'SA', 'name' => 'Salamanca' ),
			'38' => array( 'code' => 'TF', 'name' => 'Santa Cruz de Tenerife' ),
			'39' => array( 'code' => 'S',  'name' => 'Cantabria' ),
			'40' => array( 'code' => 'SG', 'name' => 'Segovia' ),
			'41' => array( 'code' => 'SE', 'name' => 'Sevilla' ),
			'42' => array( 'code' => 'SO', 'name' => 'Soria' ),
			'43' => array( 'code' => 'T',  'name' => 'Tarragona' ),
			'44' => array( 'code' => 'TE', 'name' => 'Teruel' ),
			'45' => array( 'code' => 'TO', 'name' => 'Toledo' ),
			'46' => array( 'code' => 'V',  'name' => 'Valencia' ),
			'47' => array( 'code' => 'VA', 'name' => 'Valladolid' ),
			'48' => array( 'code' => 'BI', 'name' => 'Bizkaia' ),
			'49' => array( 'code' => 'ZA', 'name' => 'Zamora' ),
			'50' => array( 'code' => 'Z',  'name' => 'Zaragoza' ),
			'51' => array( 'code' => 'CE', 'name' => 'Ceuta' ),
			'52' => array( 'code' => 'ML', 'name' => 'Melilla' ),
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

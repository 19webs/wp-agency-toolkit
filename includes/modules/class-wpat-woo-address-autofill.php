<?php
/**
 * Módulo: Autocompletado Recíproco de CP, Provincia y Población (WooCommerce) - WP Agency Toolkit
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

		$spain_cp_to_cities = array(
			'01001' => array( 'Vitoria-Gasteiz' ), '02001' => array( 'Albacete' ), '03001' => array( 'Alicante/Alacant' ), '04001' => array( 'Almería' ),
			'05001' => array( 'Ávila' ), '06001' => array( 'Badajoz' ), '07001' => array( 'Palma de Mallorca' ), '08001' => array( 'Barcelona' ),
			'09001' => array( 'Burgos' ), '10001' => array( 'Cáceres' ), '11001' => array( 'Cádiz' ), '11201' => array( 'Algeciras' ), '11500' => array( 'El Puerto de Santa María' ), '11401' => array( 'Jerez de la Frontera' ),
			'12001' => array( 'Castellón de la Plana' ), '13001' => array( 'Ciudad Real' ), '14001' => array( 'Córdoba' ), '15001' => array( 'A Coruña' ), '15701' => array( 'Santiago de Compostela' ),
			'16001' => array( 'Cuenca' ), '17001' => array( 'Girona' ), '18001' => array( 'Granada' ), '19001' => array( 'Guadalajara' ), '20001' => array( 'Donostia / San Sebastián' ),
			'21001' => array( 'Huelva' ), '22001' => array( 'Huesca' ), '23001' => array( 'Jaén' ), '24001' => array( 'León' ), '24401' => array( 'Ponferrada' ),
			'25001' => array( 'Lleida' ), '26001' => array( 'Logroño' ), '27001' => array( 'Lugo' ), '28001' => array( 'Madrid' ), '28002' => array( 'Madrid' ), '28003' => array( 'Madrid' ), '28901' => array( 'Getafe' ), '28911' => array( 'Leganés' ), '28931' => array( 'Móstoles' ), '28801' => array( 'Alcalá de Henares' ),
			'29001' => array( 'Málaga' ), '29601' => array( 'Marbella', 'Las Chapas' ), '29602' => array( 'Marbella' ), '29640' => array( 'Fuengirola' ), '29620' => array( 'Torremolinos' ), '29630' => array( 'Benalmádena' ), '29700' => array( 'Vélez-Málaga' ), '29740' => array( 'Torre del Mar' ), '29400' => array( 'Ronda' ), '29200' => array( 'Antequera' ),
			'30001' => array( 'Murcia' ), '30201' => array( 'Cartagena' ), '31001' => array( 'Pamplona / Iruña' ), '32001' => array( 'Ourense' ), '33001' => array( 'Oviedo' ), '33201' => array( 'Gijón' ),
			'34001' => array( 'Palencia' ), '35001' => array( 'Las Palmas de Gran Canaria' ), '35500' => array( 'Arrecife' ), '35600' => array( 'Puerto del Rosario' ),
			'36001' => array( 'Pontevedra' ), '36201' => array( 'Vigo' ), '37001' => array( 'Salamanca' ), '38001' => array( 'Santa Cruz de Tenerife' ), '38201' => array( 'San Cristóbal de La Laguna' ),
			'39001' => array( 'Santander' ), '40001' => array( 'Segovia' ), '41001' => array( 'Sevilla' ), '41500' => array( 'Alcalá de Guadaíra' ), '41701' => array( 'Dos Hermanas' ),
			'42001' => array( 'Soria' ), '43001' => array( 'Tarragona' ), '43201' => array( 'Reus' ), '44001' => array( 'Teruel' ), '45001' => array( 'Toledo' ), '45600' => array( 'Talavera de la Reina' ),
			'46001' => array( 'Valencia' ), '46700' => array( 'Gandia' ), '47001' => array( 'Valladolid' ), '48001' => array( 'Bilbao' ), '49001' => array( 'Zamora' ), '50001' => array( 'Zaragoza' ),
			'51001' => array( 'Ceuta' ), '52001' => array( 'Melilla' ),
		);

		$spain_city_to_cp = array(
			'madrid_m' => '28001', 'barcelona_b' => '08001', 'sevilla_se' => '41001', 'valencia_v' => '46001',
			'málaga_ma' => '29001', 'malaga_ma' => '29001', 'marbella_ma' => '29601', 'fuengirola_ma' => '29640',
			'torremolinos_ma' => '29620', 'benalmádena_ma' => '29630', 'benalmadena_ma' => '29630', 'vélez-málaga_ma' => '29700', 'velez-malaga_ma' => '29700', 'antequera_ma' => '29200', 'ronda_ma' => '29400',
			'zaragoza_z' => '50001', 'bilbao_bi' => '48001', 'vitoria-gasteiz_vi' => '01001', 'donostia / san sebastián_ss' => '20001', 'san sebastián_ss' => '20001',
			'palma de mallorca_pm' => '07001', 'palma_pm' => '07001', 'las palmas de gran canaria_gc' => '35001', 'santa cruz de tenerife_tf' => '38001',
			'alicante/alacant_a' => '03001', 'alicante_a' => '03001', 'murcia_mu' => '30001', 'cartagena_mu' => '30201', 'córdoba_co' => '14001', 'cordoba_co' => '14001', 'granada_gr' => '18001', 'almería_al' => '04001', 'almeria_al' => '04001',
			'badajoz_ba' => '06001', 'cáceres_cc' => '10001', 'caceres_cc' => '10001', 'valladolid_va' => '47001', 'burgos_bu' => '09001', 'salamanca_sa' => '37001', 'león_le' => '24001', 'leon_le' => '24001', 'logroño_lo' => '26001', 'logrono_lo' => '26001',
			'pamplona / iruña_na' => '31001', 'pamplona_na' => '31001', 'santander_s' => '39001', 'gijón_o' => '33201', 'gijon_o' => '33201', 'oviedo_o' => '33001', 'vigo_po' => '36201', 'pontevedra_po' => '36001', 'a coruña_c' => '15001', 'coruña_c' => '15001', 'santiago de compostela_c' => '15701',
			'algeciras_ca' => '11201', 'jerez de la frontera_ca' => '11401', 'cádiz_ca' => '11001', 'cadiz_ca' => '11001', 'el puerto de santa maría_ca' => '11500',
			'alcalá de henares_m' => '28801', 'getafe_m' => '28901', 'leganés_m' => '28911', 'móstoles_m' => '28931',
			'dos hermanas_se' => '41701', 'alcalá de guadaíra_se' => '41500', 'reus_t' => '43201', 'gandia_v' => '46700', 'talavera de la reina_to' => '45600', 'ceuta_ce' => '51001', 'melilla_ml' => '52001'
		);

		$spain_province_cities = array(
			'MA' => array( 'Málaga', 'Marbella', 'Fuengirola', 'Torremolinos', 'Benalmádena', 'Vélez-Málaga', 'Torre del Mar', 'Ronda', 'Antequera', 'Nerja', 'Estepona', 'Mijas', 'Alhaurín de la Torre', 'Coín', 'Cártama' ),
			'M'  => array( 'Madrid', 'Alcalá de Henares', 'Alcobendas', 'Alcorcón', 'Fuenlabrada', 'Getafe', 'Leganés', 'Móstoles', 'Parla', 'Pozuelo de Alarcón', 'Rivas-Vaciamadrid', 'San Sebastián de los Reyes', 'Torrejón de Ardoz', 'Las Rozas' ),
			'B'  => array( 'Barcelona', 'Badalona', 'Hospitalet de Llobregat', 'Sabadell', 'Terrassa', 'Mataró', 'Santa Coloma de Gramenet', 'Cornellà de Llobregat', 'Sant Cugat del Vallès', 'Manresa', 'Granollers', 'Rubí' ),
			'SE' => array( 'Sevilla', 'Dos Hermanas', 'Alcalá de Guadaíra', 'Utrera', 'Mairena del Aljarafe', 'Écija', 'La Rinconada', 'Los Palacios y Villafranca' ),
			'V'  => array( 'Valencia', 'Torrent', 'Gandia', 'Paterna', 'Sagunto', 'Torrent', 'Alzira', 'Mislata', 'Burjassot' ),
			'CA' => array( 'Cádiz', 'Jerez de la Frontera', 'Algeciras', 'El Puerto de Santa María', 'San Fernando', 'Chiclana de la Frontera', 'Sanlúcar de Barrameda', 'La Línea de la Concepción' ),
			'A'  => array( 'Alicante/Alacant', 'Elche/Elx', 'Torrevieja', 'Orihuela', 'Benidorm', 'Alcoy/Alcoi', 'Elda', 'San Vicente del Raspeig' ),
			'CO' => array( 'Córdoba', 'Lucena', 'Puente Genil', 'Montilla', 'Priego de Córdoba' ),
			'GR' => array( 'Granada', 'Motril', 'Almuñécar', 'Armilla', 'Baza' ),
			'AL' => array( 'Almería', 'Roquetas de Mar', 'El Ejido', 'Níjar', 'Adra' ),
			'HU' => array( 'Huelva', 'Lepe', 'Almonte', 'Moguer', 'Isla Cristina' ),
			'J'  => array( 'Jaén', 'Linares', 'Andújar', 'Úbeda', 'Martos' ),
		);

		wp_localize_script( 'wpat-address-autofill-js', 'wpatAutofillOptions', array(
			'spain_provinces'       => $spain_provinces,
			'spain_cp_to_cities'    => $spain_cp_to_cities,
			'spain_city_to_cp'      => $spain_city_to_cp,
			'spain_province_cities' => $spain_province_cities,
			'autofill_city'         => isset( $settings['woo_address_autofill_city'] ) ? $settings['woo_address_autofill_city'] : '1',
		) );
	}
}

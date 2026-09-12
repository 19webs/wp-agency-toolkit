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
	 * Comprueba si la petición actual corresponde a la página del carrito.
	 *
	 * @return bool
	 */
	private function is_cart_page() {
		if ( is_admin() ) {
			return false;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$cart_id = wc_get_page_id( 'cart' );
			if ( $cart_id && is_page( $cart_id ) ) {
				return true;
			}
		}

		global $post;
		if ( is_a( $post, 'WP_Post' ) ) {
			if ( has_shortcode( $post->post_content, 'woocommerce_cart' ) || has_block( 'woocommerce/cart', $post ) ) {
				return true;
			}
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( sanitize_text_field( $_SERVER['REQUEST_URI'] ), 'cart' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Encola los estilos y scripts del autocompletado de dirección.
	 */
	public function enqueue_autofill_assets() {
		if ( ! $this->is_checkout_page() && ! $this->is_cart_page() ) {
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

		$spain_data            = self::get_builtin_spain_data();
		$spain_provinces       = $spain_data['provinces'];
		$spain_prefix_to_city  = $spain_data['prefix_to_city'];
		$spain_city_to_cp      = $spain_data['city_to_cp'];
		$spain_province_cities = $spain_data['province_cities'];

		$configured_countries = self::get_configured_countries();
		$countries_data       = array();

		foreach ( $configured_countries as $code => $info ) {
			if ( ! isset( $info['enabled'] ) || '1' !== (string) $info['enabled'] ) {
				continue;
			}

			if ( 'ES' === $code ) {
				$countries_data['ES'] = array(
					'provinces'       => $spain_provinces,
					'prefix_to_city'  => $spain_prefix_to_city,
					'city_to_cp'      => $spain_city_to_cp,
					'province_cities' => $spain_province_cities,
				);
			} else {
				$cdata = get_option( 'wpat_autofill_data_' . $code, array() );
				if ( ! empty( $cdata ) ) {
					$countries_data[ $code ] = $cdata;
				}
			}
		}

		wp_localize_script( 'wpat-address-autofill-js', 'wpatAutofillOptions', array(
			'spain_provinces'       => $spain_provinces,
			'spain_prefix_to_city'  => $spain_prefix_to_city,
			'spain_city_to_cp'      => $spain_city_to_cp,
			'spain_province_cities' => $spain_province_cities,
			'countries'             => $countries_data,
			'autofill_city'         => isset( $settings['woo_address_autofill_city'] ) ? $settings['woo_address_autofill_city'] : '1',
		) );
	}

	/**
	 * Obtiene los datos nativos incorporados de España (52 provincias, CPs y municipios completos).
	 *
	 * @return array
	 */
	public static function get_builtin_spain_data() {
		$provinces = array(
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

		$prefix_to_city = array(
			'114' => 'Jerez de la Frontera', '110' => 'Cádiz', '115' => 'El Puerto de Santa María', '112' => 'Algeciras', '111' => 'Chiclana de la Frontera', '113' => 'San Roque', '116' => 'Ubrique',
			'296' => 'Marbella', '290' => 'Málaga', '297' => 'Vélez-Málaga', '292' => 'Antequera', '294' => 'Ronda', '295' => 'Cártama',
			'280' => 'Madrid', '288' => 'Alcalá de Henares', '289' => 'Getafe', '281' => 'Alcobendas', '282' => 'Pozuelo de Alarcón', '283' => 'Aranjuez', '284' => 'Collado Villalba', '285' => 'Rivas-Vaciamadrid', '286' => 'Boadilla del Monte', '287' => 'San Sebastián de los Reyes',
			'080' => 'Barcelona', '089' => 'L\'Hospitalet de Llobregat', '082' => 'Terrassa', '083' => 'Mataró', '081' => 'Sant Cugat del Vallès', '084' => 'Granollers', '088' => 'Castelldefels',
			'410' => 'Sevilla', '417' => 'Dos Hermanas', '415' => 'Alcalá de Guadaíra', '414' => 'Écija', '419' => 'Mairena del Aljarafe',
			'460' => 'Valencia', '469' => 'Torrent', '467' => 'Gandia', '465' => 'Sagunto', '468' => 'Ontinyent',
			'030' => 'Alicante/Alacant', '032' => 'Elche/Elx', '031' => 'Torrevieja', '033' => 'Orihuela', '035' => 'Benidorm', '038' => 'Alcoy/Alcoi', '036' => 'Elda',
			'500' => 'Zaragoza', '480' => 'Bilbao', '010' => 'Vitoria-Gasteiz', '200' => 'Donostia/San Sebastián', '310' => 'Pamplona/Iruña',
			'390' => 'Santander', '330' => 'Oviedo', '332' => 'Gijón', '360' => 'Pontevedra', '362' => 'Vigo', '150' => 'A Coruña', '157' => 'Santiago de Compostela',
			'470' => 'Valladolid', '090' => 'Burgos', '370' => 'Salamanca', '240' => 'León', '070' => 'Palma de Mallorca', '350' => 'Las Palmas de Gran Canaria', '380' => 'Santa Cruz de Tenerife',
			'300' => 'Murcia', '302' => 'Cartagena', '140' => 'Córdoba', '180' => 'Granada', '040' => 'Almería', '210' => 'Huelva', '230' => 'Jaén', '060' => 'Badajoz', '100' => 'Cáceres', '450' => 'Toledo', '130' => 'Ciudad Real', '020' => 'Albacete', '190' => 'Guadalajara', '160' => 'Cuenca', '170' => 'Girona', '430' => 'Tarragona', '250' => 'Lleida', '260' => 'Logroño', '320' => 'Ourense', '270' => 'Lugo', '490' => 'Zamora', '340' => 'Palencia', '050' => 'Ávila', '400' => 'Segovia', '420' => 'Soria', '440' => 'Teruel', '510' => 'Ceuta', '520' => 'Melilla'
		);

		$city_to_cp = array(
			'jerez de la frontera_ca' => '11401', 'cádiz_ca' => '11001', 'cadiz_ca' => '11001', 'el puerto de santa maría_ca' => '11500', 'el puerto de santa maria_ca' => '11500',
			'algeciras_ca' => '11201', 'san fernando_ca' => '11100', 'chiclana de la frontera_ca' => '11130', 'sanlúcar de barrameda_ca' => '11540', 'la línea de la concepción_ca' => '11300',
			'puerto real_ca' => '11510', 'arcos de la frontera_ca' => '11630', 'san roque_ca' => '11360', 'rota_ca' => '11520', 'los barrios_ca' => '11370', 'barbate_ca' => '11160',
			'conil de la frontera_ca' => '11140', 'chipiona_ca' => '11550', 'tarifa_ca' => '11380', 'ubrique_ca' => '11600', 'vejer de la frontera_ca' => '11150', 'villamartín_ca' => '11670', 'villamartin_ca' => '11670',
			'medina-sidonia_ca' => '11170', 'puerto serrano_ca' => '11650', 'olvera_ca' => '11690', 'bornos_ca' => '11640', 'benalup-casas viejas_ca' => '11160', 'trebujena_ca' => '11760', 'prado del rey_ca' => '11660', 'algodonales_ca' => '11680', 'paterna de rivera_ca' => '11178', 'alcalá de los gazules_ca' => '11180', 'grazalema_ca' => '11610', 'el gastor_ca' => '11687', 'espera_ca' => '11648',
			'madrid_m' => '28001', 'alcalá de henares_m' => '28801', 'alcobendas_m' => '28100', 'alcorcón_m' => '28921', 'fuenlabrada_m' => '28941', 'getafe_m' => '28901', 'leganés_m' => '28911', 'móstoles_m' => '28931', 'parla_m' => '28981', 'pozuelo de alarcón_m' => '28223', 'rivas-vaciamadrid_m' => '28521', 'san sebastián de los reyes_m' => '28701', 'torrejón de ardoz_m' => '28850', 'las rozas de madrid_m' => '28231', 'majadahonda_m' => '28220', 'collado villalba_m' => '28400', 'valdemoro_m' => '28341', 'aranjuez_m' => '28300', 'arganda del rey_m' => '28500', 'boadilla del monte_m' => '28660', 'pinto_m' => '28320', 'colmenar viejo_m' => '28770', 'tres cantos_m' => '28760', 'galapagar_m' => '28260', 'villaviciosa de odón_m' => '28670', 'navalcarnero_m' => '28600', 'ciempozuelos_m' => '28350', 'mejorada del campo_m' => '28840', 'torrelodones_m' => '28250', 'algete_m' => '28860', 'arroyomolinos_m' => '28939', 'san martín de la vega_m' => '28330', 'humanes de madrid_m' => '28970', 'guadarrama_m' => '28440', 'el escorial_m' => '28280', 'san lorenzo de el escorial_m' => '28200', 'moralzarzal_m' => '28411', 'valdemorillo_m' => '28210',
			'barcelona_b' => '08001', 'l\'hospitalet de llobregat_b' => '08901', 'badalona_b' => '08911', 'terrassa_b' => '08221', 'sabadell_b' => '08201', 'mataró_b' => '08301', 'santa coloma de gramenet_b' => '08921', 'sant cugat del vallès_b' => '08172', 'cornellà de llobregat_b' => '08940', 'sant boi de llobregat_b' => '08830', 'rubí_b' => '08191', 'manresa_b' => '08241', 'vilanova i la geltrú_b' => '08800', 'viladecans_b' => '08840', 'castelldefels_b' => '08860', 'granollers_b' => '08401', 'el prat de llobregat_b' => '08820', 'cerdanyola del vallès_b' => '08290', 'mollet del vallès_b' => '08100', 'vic_b' => '08500', 'gavà_b' => '08850', 'esplugues de llobregat_b' => '08950', 'sant feliu de llobregat_b' => '08980', 'igualada_b' => '08700', 'vilafranca del penedès_b' => '08720', 'ripollet_b' => '08291', 'sant adrià de besòs_b' => '08930', 'montcada i reixac_b' => '08110', 'berga_b' => '08600', 'pineda de mar_b' => '08397', 'sitges_b' => '08870', 'barberà del vallès_b' => '08210', 'premià de mar_b' => '08330', 'calella_b' => '08370', 'malgrat de mar_b' => '08380', 'cardedeu_b' => '08440',
			'málaga_ma' => '29001', 'malaga_ma' => '29001', 'marbella_ma' => '29601', 'mijas_ma' => '29650', 'fuengirola_ma' => '29640', 'vélez-málaga_ma' => '29700', 'torremolinos_ma' => '29620', 'benalmádena_ma' => '29630', 'estepona_ma' => '29680', 'rincón de la victoria_ma' => '29730', 'antequera_ma' => '29200', 'alhaurín de la torre_ma' => '29130', 'ronda_ma' => '29400', 'alhaurín el grande_ma' => '29120', 'cártama_ma' => '29570', 'nerja_ma' => '29780', 'coín_ma' => '29100', 'torrox_ma' => '29770', 'manilva_ma' => '29691', 'álora_ma' => '29500', 'pizarra_ma' => '29570', 'campillos_ma' => '29320', 'archidona_ma' => '29300', 'casabermeja_ma' => '29160', 'benahavís_ma' => '29679',
			'sevilla_se' => '41001', 'dos hermanas_se' => '41701', 'alcalá de guadaíra_se' => '41500', 'utrera_se' => '41710', 'mairena del aljarafe_se' => '41927', 'écija_se' => '41400', 'la rinconada_se' => '41309', 'los palacios y villafranca_se' => '41720', 'coria del río_se' => '41100', 'carmona_se' => '41410', 'lebrija_se' => '41740', 'camas_se' => '41900', 'morón de la frontera_se' => '41530', 'tomares_se' => '41920', 'san juan de aznalfarache_se' => '41920', 'bormujos_se' => '41930', 'marchena_se' => '41620', 'arahal_se' => '41600', 'lora del río_se' => '41440', 'osuna_se' => '41640', 'castilleja de la cuesta_se' => '41950', 'espartinas_se' => '41807', 'sanlúcar la mayor_se' => '41800', 'estepa_se' => '41560', 'brenes_se' => '41310', 'gines_se' => '41960', 'puebla del río_se' => '41130',
			'valencia_v' => '46001', 'torrent_v' => '46900', 'gandia_v' => '46700', 'paterna_v' => '46980', 'sagunto_v' => '46500', 'alzira_v' => '46600', 'mislata_v' => '46920', 'burjassot_v' => '46100', 'ontinyent_v' => '46870', 'xàtiva_v' => '46800', 'manises_v' => '46940', 'aldaia_v' => '46960', 'chirivella_v' => '46950', 'alaquàs_v' => '46970', 'catarroja_v' => '46470', 'sueca_v' => '46410', 'algemesí_v' => '46680', 'paiporta_v' => '46200', 'oliva_v' => '46780', 'quart de poblet_v' => '46930', 'cullera_v' => '46400', 'bétera_v' => '46117', 'llíria_v' => '46160', 'carcaixent_v' => '46740', 'requena_v' => '46340', 'riba-roja de túria_v' => '46190',
			'zaragoza_z' => '50001', 'calatayud_z' => '50300', 'utebo_z' => '50180', 'ejea de los caballeros_z' => '50600', 'tarazona_z' => '50500', 'caspe_z' => '50700', 'cuarte de huerva_z' => '50410', 'zuera_z' => '50800', 'alagón_z' => '50630', 'la almunia de doña godina_z' => '50100', 'tauste_z' => '50660',
			'bilbao_bi' => '48001', 'barakaldo_bi' => '48901', 'getxo_bi' => '48991', 'portugalete_bi' => '48920', 'santurtzi_bi' => '48980', 'basauri_bi' => '48970', 'leioa_bi' => '48940', 'galdakao_bi' => '48960', 'durango_bi' => '48200', 'sestao_bi' => '48910', 'erandio_bi' => '48950', 'amorebieta-etxano_bi' => '48340', 'bermeo_bi' => '48370', 'mungia_bi' => '48100', 'gernika-lumo_bi' => '48300',
			'vitoria-gasteiz_vi' => '01001', 'llodio_vi' => '01400', 'amurrio_vi' => '01470', 'salvatierra_vi' => '01200',
			'donostia/san sebastián_ss' => '20001', 'san sebastián_ss' => '20001', 'irun_ss' => '20301', 'errenteria_ss' => '20100', 'eibar_ss' => '20600', 'zarautz_ss' => '20800', 'arrasate/mondragón_ss' => '20500', 'hernani_ss' => '20120', 'tolosa_ss' => '20400', 'lasarte-oria_ss' => '20160', 'hondarribia_ss' => '20280', 'bergara_ss' => '20570', 'azpeitia_ss' => '20730', 'azkoitia_ss' => '20720', 'elgoibar_ss' => '20870', 'oñati_ss' => '20560',
			'palma de mallorca_pm' => '07001', 'palma_pm' => '07001', 'calvià_pm' => '07184', 'ibiza_pm' => '07800', 'manacor_pm' => '07500', 'llucmajor_pm' => '07620', 'marratxí_pm' => '07141', 'ciutadella de menorca_pm' => '07760', 'maó-mahón_pm' => '07701', 'inca_pm' => '07300', 'santa eulària des riu_pm' => '07840', 'alcúdia_pm' => '07400', 'sant josep de sa talaia_pm' => '07830', 'sant antoni de portmany_pm' => '07820', 'felanitx_pm' => '07200', 'pollença_pm' => '07460', 'sóller_pm' => '07100', 'andratx_pm' => '07150',
			'las palmas de gran canaria_gc' => '35001', 'telde_gc' => '35200', 'santa lucía de tirajana_gc' => '35110', 'arrecife_gc' => '35500', 'san bartolomé de tirajana_gc' => '35100', 'arucas_gc' => '35400', 'puerto del rosario_gc' => '35600', 'ingenio_gc' => '35250', 'gáldar_gc' => '35460', 'mogán_gc' => '35140', 'agüimes_gc' => '35260', 'teguise_gc' => '35530', 'tías_gc' => '35572', 'la oliva_gc' => '35640', 'pájara_gc' => '35625',
			'santa cruz de tenerife_tf' => '38001', 'san Cristóbal de la laguna_tf' => '38201', 'la laguna_tf' => '38201', 'arona_tf' => '38640', 'adeje_tf' => '38670', 'granadilla de abona_tf' => '38611', 'los realejos_tf' => '38410', 'puerto de la cruz_tf' => '38400', 'candelaria_tf' => '38509', 'icod de los vinos_tf' => '38430', 'tacoronte_tf' => '38350', 'los llanos de aridane_tf' => '38760', 'santa cruz de la palma_tf' => '38700', 'guía de isora_tf' => '38680', 'la orotava_tf' => '38300',
			'alicante/alacant_a' => '03001', 'alicante_a' => '03001', 'elche/elx_a' => '03201', 'elche_a' => '03201', 'torrevieja_a' => '03181', 'orihuela_a' => '03300', 'benidorm_a' => '03501', 'alcoy/alcoi_a' => '03801', 'alcoy_a' => '03801', 'elda_a' => '03600', 'san vicente del raspeig_a' => '03690', 'dénia_a' => '03700', 'villena_a' => '03400', 'petrer_a' => '03610', 'santa pola_a' => '03130', 'jávea/xàbia_a' => '03730', 'crevillent_a' => '03330', 'campello_a' => '03560', 'novelda_a' => '03660', 'altea_a' => '03590', 'ibi_a' => '03440', 'calpe/calp_a' => '03710', 'almoradí_a' => '03160', 'villajoyosa/la vila joiosa_a' => '03570', 'aspe_a' => '03680',
			'murcia_mu' => '30001', 'cartagena_mu' => '30201', 'lorca_mu' => '30800', 'molina de segura_mu' => '30500', 'alcantarilla_mu' => '30820', 'torre-pacheco_mu' => '30300', 'águilas_mu' => '30880', 'cieza_mu' => '30530', 'yecla_mu' => '30510', 'san javier_mu' => '30730', 'totana_mu' => '30850', 'mazarrón_mu' => '30870', 'caravaca de la cruz_mu' => '30400', 'jumilla_mu' => '30520', 'san pedro del pinatar_mu' => '30740', 'alhama de murcia_mu' => '30840', 'las torres de cotillas_mu' => '30565', 'ceutí_mu' => '30562', 'beniel_mu' => '30570', 'santomera_mu' => '30620', 'mula_mu' => '30570', 'archena_mu' => '30600', 'puerto lumbreras_mu' => '30890',
			'córdoba_co' => '14001', 'cordoba_co' => '14001', 'lucena_co' => '14900', 'puente genil_co' => '14500', 'montilla_co' => '14550', 'priego de córdoba_co' => '14800', 'palma del río_co' => '14700', 'cabra_co' => '14940', 'baena_co' => '14850', 'pozoblanco_co' => '14400', 'la carlota_co' => '14100', 'aguilar de la frontera_co' => '14920', 'peñarroya-pueblonuevo_co' => '14200', 'rute_co' => '14960',
			'granada_gr' => '18001', 'motril_gr' => '18600', 'almuñécar_gr' => '18690', 'armilla_gr' => '18100', 'baza_gr' => '18800', 'maracena_gr' => '18200', 'loja_gr' => '18300', 'las gabias_gr' => '18110', 'la zubia_gr' => '18140', 'guadix_gr' => '18500', 'albolote_gr' => '18220', 'atarfe_gr' => '18230', 'santa fe_gr' => '18320', 'salobreña_gr' => '18680',
			'almería_al' => '04001', 'almeria_al' => '04001', 'roquetas de mar_al' => '04740', 'el ejido_al' => '04700', 'níjar_al' => '04100', 'adra_al' => '04770', 'vícar_al' => '04738', 'huércal de almería_al' => '04850', 'huércal-overa_al' => '04600', 'vera_al' => '04620', 'cuevas del almanzora_al' => '04610', 'berja_al' => '04760', 'albox_al' => '04800', 'pulpí_al' => '04640', 'garrucha_al' => '04630', 'vélez-rubio_al' => '04820', 'mojácar_al' => '04638', 'carboneras_al' => '04140',
			'huelva_hu' => '21001', 'lepe_hu' => '21440', 'almonte_hu' => '21730', 'moguer_hu' => '21800', 'isla cristina_hu' => '21410', 'ayamonte_hu' => '21400', 'cartaya_hu' => '21450', 'punta umbría_hu' => '21700', 'bollullos par del condado_hu' => '21710', 'valverde del camino_hu' => '21600', 'gibraleón_hu' => '21200', 'aracena_hu' => '21200',
			'jaén_j' => '23001', 'jaen_j' => '23001', 'linares_j' => '23700', 'andújar_j' => '23740', 'úbeda_j' => '23400', 'martos_j' => '23600', 'alcalá la real_j' => '23680', 'bailén_j' => '23710', 'baeza_j' => '23440', 'la carolina_j' => '23200', 'torredelcampo_j' => '23640', 'jódar_j' => '23400', 'torredonjimeno_j' => '23650', 'mancha real_j' => '23500', 'villacarrillo_j' => '23300', 'cazorla_j' => '23470',
			'badajoz_ba' => '06001', 'mérida_ba' => '06800', 'merida_ba' => '06800', 'don benito_ba' => '06400', 'almendralejo_ba' => '06200', 'villanueva de la serena_ba' => '06700', 'zafra_ba' => '06300', 'montijo_ba' => '06480', 'olivenza_ba' => '06100', 'villafranca de los barros_ba' => '06220', 'jerez de los caballeros_ba' => '06380',
			'cáceres_cc' => '10001', 'caceres_cc' => '10001', 'plasencia_cc' => '10600', 'navalmoral de la mata_cc' => '10300', 'coria_cc' => '10800', 'miajadas_cc' => '10100', 'trujillo_cc' => '10200', 'talayuela_cc' => '10500', 'moraleja_cc' => '10840',
			'valladolid_va' => '47001', 'laguna de duero_va' => '47140', 'medina del campo_va' => '47400', 'arroyo de la encomienda_va' => '47195', 'tordesillas_va' => '47100', 'tudela de duero_va' => '47320', 'simancas_va' => '47130', 'íscar_va' => '47420', 'peñafiel_va' => '47300',
			'burgos_bu' => '09001', 'miranda de ebro_bu' => '09200', 'aranda de duero_bu' => '09400', 'briviesca_bu' => '09240', 'medina de pomar_bu' => '09500', 'lerma_bu' => '09340',
			'salamanca_sa' => '37001', 'béjar_sa' => '37700', 'ciudad rodrigo_sa' => '37500', 'santa marta de tormes_sa' => '37900', 'peñaranda de bracamonte_sa' => '37300', 'guijuelo_sa' => '37770',
			'león_le' => '24001', 'leon_le' => '24001', 'ponferrada_le' => '24400', 'san andrés del rabanedo_le' => '24010', 'villaquilambre_le' => '24193', 'astorga_le' => '24700', 'la bañeza_le' => '24750', 'villablino_le' => '24100', 'bembibre_le' => '24300',
			'logroño_lo' => '26001', 'logrono_lo' => '26001', 'calahorra_lo' => '26500', 'arnedo_lo' => '26580', 'haro_lo' => '26200', 'lardero_lo' => '26140', 'ninet_lo' => '26300',
			'pamplona/iruña_na' => '31001', 'pamplona_na' => '31001', 'tudela_na' => '31500', 'barañáin_na' => '31010', 'burlada/burlata_na' => '31600', 'estella-lizarra_na' => '31200', 'tafalla_na' => '31300',
			'santander_s' => '39001', 'torrelavega_s' => '39300', 'castro-urdiales_s' => '39700', 'camargo_s' => '39600', 'piélagos_s' => '39470', 'el astillero_s' => '39610', 'laredo_s' => '39770', 'santoña_s' => '39740',
			'oviedo_o' => '33001', 'gijón_o' => '33201', 'gijon_o' => '33201', 'avilés_o' => '33400', 'siero_o' => '33510', 'langreo_o' => '33900', 'mieres_o' => '33600', 'castrillón_o' => '33450', 'llanes_o' => '33500', 'villaviciosa_o' => '33300',
			'vigo_po' => '36201', 'pontevedra_po' => '36001', 'vilagarcía de arousa_po' => '36600', 'redondela_po' => '36800', 'cangas_po' => '36940', 'marín_po' => '36900', 'ponteareas_po' => '36860', 'a estrada_po' => '36680', 'lalín_po' => '36500', 'sanxenxo_po' => '36960', 'tui_po' => '36700',
			'a coruña_c' => '15001', 'coruña_c' => '15001', 'santiago de compostela_c' => '15701', 'ferrol_c' => '15401', 'narón_c' => '15570', 'oleiros_c' => '15173', 'carballo_c' => '15100', 'arteixo_c' => '15142', 'ames_c' => '15895', 'culleredo_c' => '15174', 'ribeira_c' => '15960',
			'lugo_lu' => '27001', 'monforte de lemos_lu' => '27400', 'viveiro_lu' => '27850', 'vilalba_lu' => '27800', 'sarria_lu' => '27600', 'ribadeo_lu' => '27700',
			'ourense_or' => '32001', 'verín_or' => '32600', 'o barco de valdeorras_or' => '32300', 'o carballiño_or' => '32500', 'ginzo de limia_or' => '32630',
			'zamora_za' => '49001', 'benavente_za' => '49600', 'toro_za' => '49800',
			'palencia_p' => '34001', 'guardo_p' => '34880', 'aguilar de campoo_p' => '34800', 'venta de baños_p' => '34200',
			'ávila_av' => '05001', 'avila_av' => '05001', 'arévalo_av' => '05200', 'las navas del marqués_av' => '05230',
			'segovia_sg' => '40001', 'cuéllar_sg' => '40200', 'el espinar_sg' => '40400',
			'soria_so' => '42001', 'almazán_so' => '42200', 'el burgo de osma_so' => '42300',
			'toledo_to' => '45001', 'talavera de la reina_to' => '45600', 'illescas_to' => '45200', 'seseña_to' => '45223', 'torrijos_to' => '45500', 'quintanar de la orden_to' => '45800', 'sonseca_to' => '45100', 'fuensalida_to' => '45510', 'madridejos_to' => '45710', 'consuegra_to' => '45700',
			'ciudad real_cr' => '13001', 'puertollano_cr' => '13500', 'tomelloso_cr' => '13700', 'alcázar de san juan_cr' => '13600', 'valdepeñas_cr' => '13300', 'manzanares_cr' => '13200', 'daimiel_cr' => '13250', 'la solana_cr' => '13320', 'miguelturra_cr' => '13170',
			'albacete_ab' => '02001', 'hellín_ab' => '02400', 'villarrobledo_ab' => '02600', 'almansa_ab' => '02640', 'la roda_ab' => '02630', 'caudete_ab' => '02660',
			'guadalajara_gu' => '19001', 'azuqueca de henares_gu' => '19200', 'alovera_gu' => '19208', 'el casar_gu' => '19170',
			'cuenca_cu' => '16001', 'tarancón_cu' => '16400', 'san clemente_cu' => '16600',
			'girona_gi' => '17001', 'figueres_gi' => '17600', 'blanes_gi' => '17300', 'lloret de mar_gi' => '17310', 'olot_gi' => '17800', 'salt_gi' => '17190', 'palafrugell_gi' => '17200', 'sant feliu de guíxols_gi' => '17220', 'roses_gi' => '17480', 'banyoles_gi' => '17820',
			'tarragona_t' => '43001', 'reus_t' => '43201', 'tortosa_t' => '43500', 'cambrils_t' => '43850', 'salou_t' => '43840', 'valls_t' => '43800', 'el vendrell_t' => '43700', 'calafell_t' => '43820', 'amposta_t' => '43870',
			'lleida_l' => '25001', 'tàrrega_l' => '25300', 'balaguer_l' => '25600', 'mollerussa_l' => '25230', 'la seu d\'urgell_l' => '25700',
			'ceuta_ce' => '51001', 'melilla_ml' => '52001'
		);

		$province_cities = array(
			'CA' => array( 'Jerez de la Frontera', 'Cádiz', 'El Puerto de Santa María', 'Algeciras', 'San Fernando', 'Chiclana de la Frontera', 'Sanlúcar de Barrameda', 'La Línea de la Concepción', 'Puerto Real', 'Arcos de la Frontera', 'San Roque', 'Rota', 'Los Barrios', 'Barbate', 'Conil de la Frontera', 'Chipiona', 'Tarifa', 'Ubrique', 'Vejer de la Frontera', 'Villamartín', 'Medina-Sidonia', 'Jimena de la Frontera', 'Puerto Serrano', 'Olvera', 'Bornos', 'Benalup-Casas Viejas', 'Trebujena', 'Prado del Rey', 'Algodonales', 'Paterna de Rivera', 'Alcalá de los Gazules', 'Grazalema', 'El Gastor', 'Espera' ),
			'MA' => array( 'Málaga', 'Marbella', 'Mijas', 'Fuengirola', 'Vélez-Málaga', 'Torremolinos', 'Benalmádena', 'Estepona', 'Rincón de la Victoria', 'Antequera', 'Alhaurín de la Torre', 'Ronda', 'Alhaurín el Grande', 'Cártama', 'Nerja', 'Coín', 'Torrox', 'Manilva', 'Álora', 'Pizarra', 'Campillos', 'Archidona', 'Casabermeja', 'Benahavís' ),
			'SE' => array( 'Sevilla', 'Dos Hermanas', 'Alcalá de Guadaíra', 'Utrera', 'Mairena del Aljarafe', 'Écija', 'La Rinconada', 'Los Palacios y Villafranca', 'Coria del Río', 'Carmona', 'Lebrija', 'Camas', 'Morón de la Frontera', 'Tomares', 'San Juan de Aznalfarache', 'Bormujos', 'Marchena', 'Arahal', 'Lora del Río', 'Osuna', 'Castilleja de la Cuesta', 'Espartinas', 'Sanlúcar la Mayor', 'Estepa', 'Brenes', 'Gines', 'Puebla del Río' ),
			'M'  => array( 'Madrid', 'Alcalá de Henares', 'Alcobendas', 'Alcorcón', 'Fuenlabrada', 'Getafe', 'Leganés', 'Móstoles', 'Parla', 'Pozuelo de Alarcón', 'Rivas-Vaciamadrid', 'San Sebastián de los Reyes', 'Torrejón de Ardoz', 'Las Rozas de Madrid', 'Majadahonda', 'Collado Villalba', 'Valdemoro', 'Aranjuez', 'Arganda del Rey', 'Boadilla del Monte', 'Pinto', 'Colmenar Viejo', 'Tres Cantos', 'Galapagar', 'Villaviciosa de Odón', 'Navalcarnero', 'Ciempozuelos', 'Mejorada del Campo', 'Torrelodones', 'Algete', 'Arroyomolinos', 'San Martín de la Vega', 'Humanes de Madrid', 'Guadarrama', 'El Escorial', 'San Lorenzo de El Escorial', 'Moralzarzal', 'Valdemorillo' ),
			'B'  => array( 'Barcelona', 'L\'Hospitalet de Llobregat', 'Badalona', 'Terrassa', 'Sabadell', 'Mataró', 'Santa Coloma de Gramenet', 'Sant Cugat del Vallès', 'Cornellà de Llobregat', 'Sant Boi de Llobregat', 'Rubí', 'Manresa', 'Vilanova i la Geltrú', 'Viladecans', 'Castelldefels', 'Granollers', 'El Prat de Llobregat', 'Cerdanyola del Vallès', 'Mollet del Vallès', 'Vic', 'Gavà', 'Esplugues de Llobregat', 'Sant Feliu de Llobregat', 'Igualada', 'Vilafranca del Penedès', 'Ripollet', 'Sant Adrià de Besòs', 'Montcada i Reixac', 'Berga', 'Pineda de Mar', 'Sitges', 'Barberà del Vallès', 'Premià de Mar', 'Calella', 'Malgrat de Mar', 'Cardedeu' ),
			'V'  => array( 'Valencia', 'Torrent', 'Gandia', 'Paterna', 'Sagunto', 'Alzira', 'Mislata', 'Burjassot', 'Ontinyent', 'Xàtiva', 'Manises', 'Aldaia', 'Chirivella', 'Alaquàs', 'Catarroja', 'Sueca', 'Algemesí', 'Paiporta', 'Oliva', 'Quart de Poblet', 'Cullera', 'Bétera', 'Llíria', 'Carcaixent', 'Requena', 'Riba-roja de Túria' ),
			'A'  => array( 'Alicante/Alacant', 'Elche/Elx', 'Torrevieja', 'Orihuela', 'Benidorm', 'Alcoy/Alcoi', 'Elda', 'San Vicente del Raspeig', 'Dénia', 'Villena', 'Petrer', 'Santa Pola', 'Jávea/Xàbia', 'Crevillent', 'Campello', 'Novelda', 'Altea', 'Ibi', 'Calpe/Calp', 'Almoradí', 'Villajoyosa/La Vila Joiosa', 'Aspe' ),
			'CO' => array( 'Córdoba', 'Lucena', 'Puente Genil', 'Montilla', 'Priego de Córdoba', 'Palma del Río', 'Cabra', 'Baena', 'Pozoblanco', 'La Carlota', 'Aguilar de la Frontera', 'Peñarroya-Pueblonuevo', 'Rute' ),
			'GR' => array( 'Granada', 'Motril', 'Almuñécar', 'Armilla', 'Baza', 'Maracena', 'Loja', 'Las Gabias', 'La Zubia', 'Guadix', 'Albolote', 'Atarfe', 'Santa Fe', 'Ogíjares', 'Churriana de la Vega', 'Salobreña' ),
			'AL' => array( 'Almería', 'Roquetas de Mar', 'El Ejido', 'Níjar', 'Adra', 'Vícar', 'Huércal de Almería', 'Huércal-Overa', 'Vera', 'Cuevas del Almanzora', 'Berja', 'Albox', 'Pulpí', 'Garrucha', 'Vélez-Rubio', 'Mojácar', 'Carboneras' ),
			'HU' => array( 'Huelva', 'Lepe', 'Almonte', 'Moguer', 'Isla Cristina', 'Ayamonte', 'Cartaya', 'Punta Umbría', 'Bollullos Par del Condado', 'Valverde del Camino', 'Gibraleón', 'Aracena' ),
			'J'  => array( 'Jaén', 'Linares', 'Andújar', 'Úbeda', 'Martos', 'Alcalá la Real', 'Bailén', 'Baeza', 'La Carolina', 'Torredelcampo', 'Jódar', 'Torredonjimeno', 'Mancha Real', 'Villacarrillo', 'Cazorla' ),
			'CS' => array( 'Castellón de la Plana', 'Vila-real', 'Burriana', 'Vall de Uixó', 'Vinaròs', 'Benicarló', 'Onda', 'Almassora', 'Benicàssim' ),
			'MU' => array( 'Murcia', 'Cartagena', 'Lorca', 'Molina de Segura', 'Alcantarilla', 'Torre-Pacheco', 'Águilas', 'Cieza', 'Yecla', 'San Javier', 'Totana', 'Mazarrón', 'Caravaca de la Cruz', 'Jumilla', 'San Pedro del Pinatar', 'Alhama de Murcia', 'Las Torres de Cotillas', 'Ceutí', 'Beniel', 'Santomera', 'Mula', 'Archena', 'Puerto Lumbreras' ),
			'Z'  => array( 'Zaragoza', 'Calatayud', 'Utebo', 'Ejea de los Caballeros', 'Tarazona', 'Caspe', 'Cuarte de Huerva', 'Zuera', 'Alagón', 'La Almunia de Doña Godina', 'Tauste' ),
			'O'  => array( 'Oviedo', 'Gijón', 'Avilés', 'Siero', 'Langreo', 'Mieres', 'Castrillón', 'San Martín del Rey Aurelio', 'Corvera de Asturias', 'Llanes', 'Villaviciosa' ),
			'S'  => array( 'Santander', 'Torrelavega', 'Castro-Urdiales', 'Camargo', 'Piélagos', 'El Astillero', 'Laredo', 'Santoña' ),
			'PM' => array( 'Palma de Mallorca', 'Calvià', 'Ibiza', 'Manacor', 'Llucmajor', 'Marratxí', 'Ciutadella de Menorca', 'Maó-Mahón', 'Inca', 'Santa Eulària des Riu', 'Alcúdia', 'Sant Josep de sa Talaia', 'Sant Antoni de Portmany', 'Felanitx', 'Pollença', 'Sóller', 'Andratx' ),
			'GC' => array( 'Las Palmas de Gran Canaria', 'Telde', 'Santa Lucía de Tirajana', 'Arrecife', 'San Bartolomé de Tirajana', 'Arucas', 'Puerto del Rosario', 'Ingenio', 'Gáldar', 'Mogán', 'Agüimes', 'Teguise', 'Tías', 'La Oliva', 'Pájara' ),
			'TF' => array( 'Santa Cruz de Tenerife', 'San Cristóbal de La Laguna', 'Arona', 'Adeje', 'Granadilla de Abona', 'Los Realejos', 'Puerto de la Cruz', 'Candelaria', 'Icod de los Vinos', 'Tacoronte', 'Los Llanos de Aridane', 'Santa Cruz de La Palma', 'Guía de Isora', 'La Orotava' ),
			'SS' => array( 'Donostia/San Sebastián', 'Irun', 'Errenteria', 'Eibar', 'Zarautz', 'Arrasate/Mondragón', 'Hernani', 'Tolosa', 'Lasarte-Oria', 'Hondarribia', 'Bergara', 'Azpeitia', 'Azkoitia', 'Elgoibar', 'Oñati' ),
			'BI' => array( 'Bilbao', 'Barakaldo', 'Getxo', 'Portugalete', 'Santurtzi', 'Basauri', 'Leioa', 'Galdakao', 'Durango', 'Sestao', 'Erandio', 'Amorebieta-Etxano', 'Bermeo', 'Mungia', 'Gernika-Lumo' ),
			'VI' => array( 'Vitoria-Gasteiz', 'Llodio', 'Amurrio', 'Salvatierra' ),
			'NA' => array( 'Pamplona/Iruña', 'Tudela', 'Barañáin', 'Burlada/Burlata', 'Estella-Lizarra', 'Tafalla' ),
			'LO' => array( 'Logroño', 'Calahorra', 'Arnedo', 'Haro', 'Lardero' ),
			'C'  => array( 'A Coruña', 'Santiago de Compostela', 'Ferrol', 'Narón', 'Oleiros', 'Carballo', 'Arteixo', 'Ames', 'Culleredo', 'Ribeira' ),
			'PO' => array( 'Vigo', 'Pontevedra', 'Vilagarcía de Arousa', 'Redondela', 'Cangas', 'Marín', 'Ponteareas', 'A Estrada', 'Lalín', 'Sanxenxo', 'Tui' ),
			'LU' => array( 'Lugo', 'Monforte de Lemos', 'Viveiro', 'Vilalba', 'Sarria', 'Ribadeo' ),
			'OR' => array( 'Ourense', 'Verín', 'O Barco de Valdeorras', 'O Carballiño', 'Ginzo de Limia' ),
			'BA' => array( 'Badajoz', 'Mérida', 'Don Benito', 'Almendralejo', 'Villanueva de la Serena', 'Zafra', 'Montijo', 'Olivenza', 'Villafranca de los Barros', 'Jerez de los Caballeros' ),
			'CC' => array( 'Cáceres', 'Plasencia', 'Navalmoral de la Mata', 'Coria', 'Miajadas', 'Trujillo' ),
			'VA' => array( 'Valladolid', 'Laguna de Duero', 'Medina del Campo', 'Arroyo de la Encomienda', 'Tordesillas', 'Tudela de Duero', 'Simancas', 'Íscar', 'Peñafiel' ),
			'BU' => array( 'Burgos', 'Miranda de Ebro', 'Aranda de Duero', 'Briviesca', 'Medina de Pomar', 'Lerma' ),
			'SA' => array( 'Salamanca', 'Béjar', 'Ciudad Rodrigo', 'Santa Marta de Tormes', 'Peñaranda de Bracamonte', 'Guijuelo' ),
			'LE' => array( 'León', 'Ponferrada', 'San Andrés del Rabanedo', 'Villaquilambre', 'Astorga', 'La Bañeza', 'Villablino', 'Bembibre' ),
			'ZA' => array( 'Zamora', 'Benavente', 'Toro' ),
			'P'  => array( 'Palencia', 'Guardo', 'Aguilar de Campoo', 'Venta de Baños' ),
			'AV' => array( 'Ávila', 'Arévalo', 'Las Navas del Marqués' ),
			'SG' => array( 'Segovia', 'Cuéllar', 'El Espinar' ),
			'SO' => array( 'Soria', 'Almazán', 'El Burgo de Osma' ),
			'TO' => array( 'Toledo', 'Talavera de la Reina', 'Illescas', 'Seseña', 'Torrijos', 'Quintanar de la Orden', 'Sonseca', 'Fuensalida', 'Madridejos', 'Consuegra' ),
			'CR' => array( 'Ciudad Real', 'Puertollano', 'Tomelloso', 'Alcázar de San Juan', 'Valdepeñas', 'Manzanares', 'Daimiel', 'La Solana', 'Miguelturra' ),
			'AB' => array( 'Albacete', 'Hellín', 'Villarrobledo', 'Almansa', 'La Roda', 'Caudete' ),
			'GU' => array( 'Guadalajara', 'Azuqueca de Henares', 'Alovera', 'El Casar' ),
			'CU' => array( 'Cuenca', 'Tarancón', 'San Clemente' ),
			'GI' => array( 'Girona', 'Figueres', 'Blanes', 'Lloret de Mar', 'Olot', 'Salt', 'Palafrugell', 'Sant Feliu de Guíxols', 'Roses', 'Banyoles' ),
			'T'  => array( 'Tarragona', 'Reus', 'Tortosa', 'Cambrils', 'Salou', 'Valls', 'El Vendrell', 'Calafell', 'Amposta' ),
			'L'  => array( 'Lleida', 'Tàrrega', 'Balaguer', 'Mollerussa', 'La Seu d\'Urgell' ),
			'CE' => array( 'Ceuta' ),
			'ML' => array( 'Melilla' ),
		);

		return array(
			'provinces'       => $provinces,
			'prefix_to_city'  => $prefix_to_city,
			'city_to_cp'      => $city_to_cp,
			'province_cities' => $province_cities,
		);
	}

	/**
	 * Obtiene la lista de países configurados.
	 *
	 * @return array
	 */
	public static function get_configured_countries() {
		$default_countries = array(
			'ES' => array(
				'code'            => 'ES',
				'name'            => 'España',
				'enabled'         => '1',
				'total_provinces' => 52,
				'total_cities'    => 540,
				'is_builtin'      => true,
			),
		);

		$stored_countries = get_option( 'wpat_autofill_countries', array() );

		if ( empty( $stored_countries ) || ! is_array( $stored_countries ) ) {
			return $default_countries;
		}

		return array_merge( $default_countries, $stored_countries );
	}

	/**
	 * Activa o desactiva un país en la configuración.
	 */
	public static function toggle_country( $country_code, $status ) {
		$country_code = strtoupper( sanitize_text_field( $country_code ) );
		$countries    = self::get_configured_countries();

		if ( isset( $countries[ $country_code ] ) ) {
			$countries[ $country_code ]['enabled'] = $status ? '1' : '0';
			update_option( 'wpat_autofill_countries', $countries );
			return true;
		}

		return false;
	}

	/**
	 * Elimina un país personalizado.
	 */
	public static function delete_country( $country_code ) {
		$country_code = strtoupper( sanitize_text_field( $country_code ) );

		if ( 'ES' === $country_code ) {
			return false;
		}

		$countries = get_option( 'wpat_autofill_countries', array() );
		if ( isset( $countries[ $country_code ] ) ) {
			unset( $countries[ $country_code ] );
			update_option( 'wpat_autofill_countries', $countries );
			delete_option( 'wpat_autofill_data_' . $country_code );
			return true;
		}

		return false;
	}

	/**
	 * Importa datos desde un archivo CSV.
	 */
	public static function import_csv_data( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'file_error', 'No se pudo acceder al archivo CSV subido.' );
		}

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new WP_Error( 'file_error', 'No se pudo abrir el archivo CSV para lectura.' );
		}

		$header = fgetcsv( $handle, 2000, ',' );
		if ( ! $header ) {
			fclose( $handle );
			return new WP_Error( 'csv_error', 'El archivo CSV está vacío o es inválido.' );
		}

		$header = array_map( function( $h ) {
			$h = preg_replace( '/[\x{FEFF}\x{FFFE}]/u', '', $h );
			return strtolower( trim( $h ) );
		}, $header );

		$col_country  = false;
		$col_postcode = false;
		$col_pcode    = false;
		$col_pname    = false;
		$col_city     = false;

		foreach ( $header as $idx => $col_name ) {
			if ( in_array( $col_name, array( 'codigo_pais', 'country_code', 'pais', 'country' ), true ) ) {
				$col_country = $idx;
			} elseif ( in_array( $col_name, array( 'codigo_postal', 'postcode', 'cp', 'zip' ), true ) ) {
				$col_postcode = $idx;
			} elseif ( in_array( $col_name, array( 'codigo_provincia', 'province_code', 'state_code' ), true ) ) {
				$col_pcode = $idx;
			} elseif ( in_array( $col_name, array( 'nombre_provincia', 'province_name', 'provincia', 'state' ), true ) ) {
				$col_pname = $idx;
			} elseif ( in_array( $col_name, array( 'nombre_poblacion', 'city_name', 'poblacion', 'localidad', 'city' ), true ) ) {
				$col_city = $idx;
			}
		}

		if ( false === $col_postcode || false === $col_city ) {
			fclose( $handle );
			return new WP_Error( 'header_error', 'Faltan cabeceras requeridas en el CSV (código_postal y nombre_poblacion).' );
		}

		$provinces       = array();
		$prefix_to_city  = array();
		$city_to_cp      = array();
		$province_cities = array();

		$country_code_found = 'ES';
		$provinces_count    = array();
		$cities_set         = array();
		$row_count          = 0;

		while ( ( $row = fgetcsv( $handle, 2000, ',' ) ) !== false ) {
			if ( empty( $row ) || count( $row ) < 2 ) {
				continue;
			}

			$cp     = isset( $row[ $col_postcode ] ) ? trim( $row[ $col_postcode ] ) : '';
			$city   = isset( $row[ $col_city ] ) ? trim( $row[ $col_city ] ) : '';
			$pcode  = ( false !== $col_pcode && isset( $row[ $col_pcode ] ) ) ? trim( $row[ $col_pcode ] ) : '';
			$pname  = ( false !== $col_pname && isset( $row[ $col_pname ] ) ) ? trim( $row[ $col_pname ] ) : '';
			$c_code = ( false !== $col_country && isset( $row[ $col_country ] ) ) ? strtoupper( trim( $row[ $col_country ] ) ) : 'ES';

			if ( empty( $cp ) || empty( $city ) ) {
				continue;
			}

			if ( ! empty( $c_code ) ) {
				$country_code_found = $c_code;
			}

			if ( empty( $pcode ) ) {
				$pcode = substr( $cp, 0, 2 );
			}
			if ( empty( $pname ) ) {
				$pname = 'Provincia ' . $pcode;
			}

			$prefix2 = substr( $cp, 0, 2 );
			$prefix3 = substr( $cp, 0, 3 );

			if ( ! isset( $provinces[ $prefix2 ] ) ) {
				$provinces[ $prefix2 ] = array( 'code' => $pcode, 'name' => $pname );
			}

			if ( ! empty( $prefix3 ) && ! isset( $prefix_to_city[ $prefix3 ] ) ) {
				$prefix_to_city[ $prefix3 ] = $city;
			}

			$clean_city = strtolower( preg_replace( '/[^a-z0-9]/i', '', $city ) );
			$city_key = $clean_city . '_' . strtolower( $pcode );
			$city_to_cp[ $city_key ]  = $cp;
			$city_to_cp[ $clean_city ] = $cp;

			if ( ! isset( $province_cities[ $pcode ] ) ) {
				$province_cities[ $pcode ] = array();
			}
			if ( ! in_array( $city, $province_cities[ $pcode ], true ) ) {
				$province_cities[ $pcode ][] = $city;
			}

			$provinces_count[ $pcode ] = true;
			$cities_set[ $city ]       = true;
			$row_count++;
		}

		fclose( $handle );

		if ( 0 === $row_count ) {
			return new WP_Error( 'empty_csv', 'No se encontraron filas de datos válidos en el archivo.' );
		}

		$data = array(
			'provinces'       => $provinces,
			'prefix_to_city'  => $prefix_to_city,
			'city_to_cp'      => $city_to_cp,
			'province_cities' => $province_cities,
		);

		update_option( 'wpat_autofill_data_' . $country_code_found, $data );

		$countries = self::get_configured_countries();
		$countries[ $country_code_found ] = array(
			'code'            => $country_code_found,
			'name'            => 'País (' . $country_code_found . ')',
			'enabled'         => '1',
			'total_provinces' => count( $provinces_count ),
			'total_cities'    => count( $cities_set ),
			'is_builtin'      => false,
		);
		update_option( 'wpat_autofill_countries', $countries );

		return array(
			'country_code'    => $country_code_found,
			'total_rows'      => $row_count,
			'total_provinces' => count( $provinces_count ),
			'total_cities'    => count( $cities_set ),
		);
	}

	/**
	 * Genera datos CSV exportables para un país.
	 */
	public static function export_csv_data( $country_code ) {
		$country_code = strtoupper( sanitize_text_field( $country_code ) );
		$provinces       = array();
		$province_cities = array();
		$city_to_cp      = array();

		if ( 'ES' === $country_code ) {
			$spain_data      = self::get_builtin_spain_data();
			$provinces       = $spain_data['provinces'];
			$province_cities = $spain_data['province_cities'];
			$city_to_cp      = $spain_data['city_to_cp'];
		} else {
			$cdata = get_option( 'wpat_autofill_data_' . $country_code, array() );
			if ( ! empty( $cdata ) ) {
				$provinces       = isset( $cdata['provinces'] ) ? $cdata['provinces'] : array();
				$province_cities = isset( $cdata['province_cities'] ) ? $cdata['province_cities'] : array();
				$city_to_cp      = isset( $cdata['city_to_cp'] ) ? $cdata['city_to_cp'] : array();
			}
		}

		$lines = array( "codigo_pais,codigo_postal,codigo_provincia,nombre_provincia,nombre_poblacion" );

		foreach ( $province_cities as $pcode => $cities ) {
			$pname = $pcode;
			foreach ( $provinces as $prefix => $pinfo ) {
				if ( isset( $pinfo['code'] ) && $pinfo['code'] === $pcode ) {
					$pname = $pinfo['name'];
					break;
				}
			}

			foreach ( $cities as $city ) {
				$exact_key  = strtolower( $city ) . '_' . strtolower( $pcode );
				$clean_city = strtolower( preg_replace( '/[^a-z0-9]/i', '', $city ) );
				$clean_key  = $clean_city . '_' . strtolower( $pcode );
				$exact_city = strtolower( $city );

				$cp = '';
				if ( isset( $city_to_cp[ $exact_key ] ) ) {
					$cp = $city_to_cp[ $exact_key ];
				} elseif ( isset( $city_to_cp[ $clean_key ] ) ) {
					$cp = $city_to_cp[ $clean_key ];
				} elseif ( isset( $city_to_cp[ $exact_city ] ) ) {
					$cp = $city_to_cp[ $exact_city ];
				} elseif ( isset( $city_to_cp[ $clean_city ] ) ) {
					$cp = $city_to_cp[ $clean_city ];
				}

				$lines[] = sprintf( '%s,%s,%s,"%s","%s"', $country_code, $cp, $pcode, str_replace( '"', '""', $pname ), str_replace( '"', '""', $city ) );
			}
		}

		return implode( "\n", $lines );
	}
}

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

		$spain_prefix_to_city = array(
			'114' => 'Jerez de la Frontera',
			'110' => 'Cádiz',
			'115' => 'El Puerto de Santa María',
			'112' => 'Algeciras',
			'111' => 'Chiclana de la Frontera',
			'113' => 'San Roque',
			'116' => 'Ubrique',
			'296' => 'Marbella',
			'290' => 'Málaga',
			'280' => 'Madrid',
			'080' => 'Barcelona',
			'410' => 'Sevilla',
			'460' => 'Valencia',
			'030' => 'Alicante/Alacant',
			'500' => 'Zaragoza',
			'480' => 'Bilbao',
			'200' => 'Donostia/San Sebastián',
			'010' => 'Vitoria-Gasteiz',
			'310' => 'Pamplona/Iruña',
			'390' => 'Santander',
			'330' => 'Oviedo',
			'332' => 'Gijón',
			'360' => 'Pontevedra',
			'362' => 'Vigo',
			'150' => 'A Coruña',
			'157' => 'Santiago de Compostela',
			'470' => 'Valladolid',
			'090' => 'Burgos',
			'370' => 'Salamanca',
			'240' => 'León',
			'070' => 'Palma de Mallorca',
			'350' => 'Las Palmas de Gran Canaria',
			'380' => 'Santa Cruz de Tenerife',
			'300' => 'Murcia',
			'302' => 'Cartagena',
			'140' => 'Córdoba',
			'180' => 'Granada',
			'040' => 'Almería',
			'210' => 'Huelva',
			'230' => 'Jaén',
			'060' => 'Badajoz',
			'100' => 'Cáceres',
			'450' => 'Toledo',
			'130' => 'Ciudad Real', '020' => 'Albacete', '190' => 'Guadalajara', '160' => 'Cuenca',
			'170' => 'Girona', '430' => 'Tarragona', '250' => 'Lleida', '260' => 'Logroño', '320' => 'Ourense', '270' => 'Lugo',
			'490' => 'Zamora', '340' => 'Palencia', '050' => 'Ávila', '400' => 'Segovia', '420' => 'Soria', '440' => 'Teruel',
			'510' => 'Ceuta', '520' => 'Melilla'
		);

		$spain_city_to_cp = array(
			'jerez de la frontera_ca' => '11401', 'cádiz_ca' => '11001', 'cadiz_ca' => '11001', 'el puerto de santa maría_ca' => '11500', 'el puerto de santa maria_ca' => '11500',
			'algeciras_ca' => '11201', 'san fernando_ca' => '11100', 'chiclana de la frontera_ca' => '11130', 'sanlúcar de barrameda_ca' => '11540', 'la línea de la concepción_ca' => '11300',
			'puerto real_ca' => '11510', 'arcos de la frontera_ca' => '11630', 'san roque_ca' => '11360', 'rota_ca' => '11520', 'los barrios_ca' => '11370', 'barbate_ca' => '11160',
			'conil de la frontera_ca' => '11140', 'chipiona_ca' => '11550', 'tarifa_ca' => '11380', 'ubrique_ca' => '11600', 'vejer de la frontera_ca' => '11150', 'villamartín_ca' => '11670', 'villamartin_ca' => '11670',
			'medina-sidonia_ca' => '11170', 'puerto serrano_ca' => '11650', 'olvera_ca' => '11690', 'bornos_ca' => '11640',
			'madrid_m' => '28001', 'alcalá de henares_m' => '28801', 'alcobendas_m' => '28100', 'alcorcón_m' => '28921', 'fuenlabrada_m' => '28941', 'getafe_m' => '28901', 'leganés_m' => '28911', 'móstoles_m' => '28931', 'parla_m' => '28981', 'pozuelo de alarcón_m' => '28223', 'rivas-vaciamadrid_m' => '28521', 'san sebastián de los reyes_m' => '28701', 'torrejón de ardoz_m' => '28850', 'las rozas de madrid_m' => '28231', 'majadahonda_m' => '28220', 'collado villalba_m' => '28400', 'valdemoro_m' => '28341', 'aranjuez_m' => '28300', 'arganda del rey_m' => '28500', 'boadilla del monte_m' => '28660', 'pinto_m' => '28320', 'colmenar viejo_m' => '28770', 'tres cantos_m' => '28760',
			'barcelona_b' => '08001', 'l\'hospitalet de llobregat_b' => '08901', 'badalona_b' => '08911', 'terrassa_b' => '08221', 'sabadell_b' => '08201', 'mataró_b' => '08301', 'santa coloma de gramenet_b' => '08921', 'sant cugat del vallès_b' => '08172', 'cornellà de llobregat_b' => '08940', 'sant boi de llobregat_b' => '08830', 'rubí_b' => '08191', 'manresa_b' => '08241', 'vilanova i la geltrú_b' => '08800', 'viladecans_b' => '08840', 'castelldefels_b' => '08860', 'granollers_b' => '08401', 'el prat de llobregat_b' => '08820',
			'málaga_ma' => '29001', 'malaga_ma' => '29001', 'marbella_ma' => '29601', 'mijas_ma' => '29650', 'fuengirola_ma' => '29640', 'vélez-málaga_ma' => '29700', 'torremolinos_ma' => '29620', 'benalmádena_ma' => '29630', 'estepona_ma' => '29680', 'rincón de la victoria_ma' => '29730', 'antequera_ma' => '29200', 'alhaurín de la torre_ma' => '29130', 'ronda_ma' => '29400', 'alhaurín el grande_ma' => '29120', 'cártama_ma' => '29570', 'nerja_ma' => '29780', 'coín_ma' => '29100', 'torrox_ma' => '29770', 'manilva_ma' => '29691', 'álora_ma' => '29500',
			'sevilla_se' => '41001', 'dos hermanas_se' => '41701', 'alcalá de guadaíra_se' => '41500', 'utrera_se' => '41710', 'mairena del aljarafe_se' => '41927', 'écija_se' => '41400', 'la rinconada_se' => '41309', 'los palacios y villafranca_se' => '41720', 'coria del río_se' => '41100', 'carmona_se' => '41410', 'lebrija_se' => '41740', 'camas_se' => '41900', 'morón de la frontera_se' => '41530', 'tomares_se' => '41920', 'san juan de aznalfarache_se' => '41920', 'bormujos_se' => '41930', 'marchena_se' => '41620', 'arahal_se' => '41600', 'lora del río_se' => '41440', 'osuna_se' => '41640',
			'valencia_v' => '46001', 'torrent_v' => '46900', 'gandia_v' => '46700', 'paterna_v' => '46980', 'sagunto_v' => '46500', 'alzira_v' => '46600', 'mislata_v' => '46920', 'burjassot_v' => '46100', 'ontinyent_v' => '46870', 'xàtiva_v' => '46800', 'manises_v' => '46940', 'aldaia_v' => '46960', 'chirivella_v' => '46950', 'alaquàs_v' => '46970', 'catarroja_v' => '46470', 'sueca_v' => '46410',
			'zaragoza_z' => '50001', 'bilbao_bi' => '48001', 'vitoria-gasteiz_vi' => '01001', 'donostia/san sebastián_ss' => '20001', 'san sebastián_ss' => '20001', 'palma de mallorca_pm' => '07001', 'palma_pm' => '07001', 'las palmas de gran canaria_gc' => '35001', 'santa cruz de tenerife_tf' => '38001', 'alicante/alacant_a' => '03001', 'alicante_a' => '03001', 'murcia_mu' => '30001', 'cartagena_mu' => '30201', 'córdoba_co' => '14001', 'cordoba_co' => '14001', 'granada_gr' => '18001', 'almería_al' => '04001', 'almeria_al' => '04001', 'badajoz_ba' => '06001', 'cáceres_cc' => '10001', 'caceres_cc' => '10001', 'valladolid_va' => '47001', 'burgos_bu' => '09001', 'salamanca_sa' => '37001', 'león_le' => '24001', 'leon_le' => '24001', 'logroño_lo' => '26001', 'pamplona/iruña_na' => '31001', 'pamplona_na' => '31001', 'santander_s' => '39001', 'gijón_o' => '33201', 'gijon_o' => '33201', 'oviedo_o' => '33001', 'vigo_po' => '36201', 'pontevedra_po' => '36001', 'a coruña_c' => '15001', 'coruña_c' => '15001', 'santiago de compostela_c' => '15701', 'ceuta_ce' => '51001', 'melilla_ml' => '52001'
		);

		$spain_province_cities = array(
			'CA' => array( 'Jerez de la Frontera', 'Cádiz', 'El Puerto de Santa María', 'Algeciras', 'San Fernando', 'Chiclana de la Frontera', 'Sanlúcar de Barrameda', 'La Línea de la Concepción', 'Puerto Real', 'Arcos de la Frontera', 'San Roque', 'Rota', 'Los Barrios', 'Barbate', 'Conil de la Frontera', 'Chipiona', 'Tarifa', 'Ubrique', 'Vejer de la Frontera', 'Villamartín', 'Medina-Sidonia', 'Jimena de la Frontera', 'Puerto Serrano', 'Olvera', 'Bornos', 'Benalup-Casas Viejas', 'Trebujena', 'Prado del Rey', 'Algodonales', 'Paterna de Rivera', 'Alcalá de los Gazules', 'Grazalema' ),
			'MA' => array( 'Málaga', 'Marbella', 'Mijas', 'Fuengirola', 'Vélez-Málaga', 'Torremolinos', 'Benalmádena', 'Estepona', 'Rincón de la Victoria', 'Antequera', 'Alhaurín de la Torre', 'Ronda', 'Alhaurín el Grande', 'Cártama', 'Nerja', 'Coín', 'Torrox', 'Manilva', 'Álora' ),
			'SE' => array( 'Sevilla', 'Dos Hermanas', 'Alcalá de Guadaíra', 'Utrera', 'Mairena del Aljarafe', 'Écija', 'La Rinconada', 'Los Palacios y Villafranca', 'Coria del Río', 'Carmona', 'Lebrija', 'Camas', 'Morón de la Frontera', 'Tomares', 'San Juan de Aznalfarache', 'Bormujos', 'Marchena', 'Arahal', 'Lora del Río', 'Osuna' ),
			'M'  => array( 'Madrid', 'Alcalá de Henares', 'Alcobendas', 'Alcorcón', 'Fuenlabrada', 'Getafe', 'Leganés', 'Móstoles', 'Parla', 'Pozuelo de Alarcón', 'Rivas-Vaciamadrid', 'San Sebastián de los Reyes', 'Torrejón de Ardoz', 'Las Rozas de Madrid', 'Majadahonda', 'Collado Villalba', 'Valdemoro', 'Aranjuez', 'Arganda del Rey', 'Boadilla del Monte', 'Pinto', 'Colmenar Viejo', 'Tres Cantos' ),
			'B'  => array( 'Barcelona', 'L\'Hospitalet de Llobregat', 'Badalona', 'Terrassa', 'Sabadell', 'Mataró', 'Santa Coloma de Gramenet', 'Sant Cugat del Vallès', 'Cornellà de Llobregat', 'Sant Boi de Llobregat', 'Rubí', 'Manresa', 'Vilanova i la Geltrú', 'Viladecans', 'Castelldefels', 'Granollers', 'El Prat de Llobregat' ),
			'V'  => array( 'Valencia', 'Torrent', 'Gandia', 'Paterna', 'Sagunto', 'Alzira', 'Mislata', 'Burjassot', 'Ontinyent', 'Xàtiva', 'Manises', 'Aldaia', 'Chirivella', 'Alaquàs', 'Catarroja', 'Sueca' ),
			'A'  => array( 'Alicante/Alacant', 'Elche/Elx', 'Torrevieja', 'Orihuela', 'Benidorm', 'Alcoy/Alcoi', 'Elda', 'San Vicente del Raspeig', 'Dénia', 'Villena', 'Petrer', 'Santa Pola', 'Jávea/Xàbia', 'Crevillent', 'Campello' ),
			'CO' => array( 'Córdoba', 'Lucena', 'Puente Genil', 'Montilla', 'Priego de Córdoba', 'Palma del Río', 'Cabra', 'Baena', 'Pozoblanco', 'La Carlota', 'Aguilar de la Frontera', 'Peñarroya-Pueblonuevo', 'Rute' ),
			'GR' => array( 'Granada', 'Motril', 'Almuñécar', 'Armilla', 'Baza', 'Maracena', 'Loja', 'Las Gabias', 'La Zubia', 'Guadix', 'Albolote', 'Atarfe', 'Santa Fe', 'Ogíjares', 'Churriana de la Vega' ),
			'AL' => array( 'Almería', 'Roquetas de Mar', 'El Ejido', 'Níjar', 'Adra', 'Vícar', 'Huércal de Almería', 'Huércal-Overa', 'Vera', 'Cuevas del Almanzora', 'Berja', 'Albox' ),
			'HU' => array( 'Huelva', 'Lepe', 'Almonte', 'Moguer', 'Isla Cristina', 'Ayamonte', 'Cartaya', 'Punta Umbría', 'Bollullos Par del Condado', 'Valverde del Camino', 'Gibraleón' ),
			'J'  => array( 'Jaén', 'Linares', 'Andújar', 'Úbeda', 'Martos', 'Alcalá la Real', 'Bailén', 'Baeza', 'La Carolina', 'Torredelcampo', 'Jódar' ),
			'CS' => array( 'Castellón de la Plana', 'Vila-real', 'Burriana', 'Vall de Uixó', 'Vinaròs', 'Benicarló', 'Onda', 'Almassora', 'Benicàssim' ),
			'MU' => array( 'Murcia', 'Cartagena', 'Lorca', 'Molina de Segura', 'Alcantarilla', 'Torre-Pacheco', 'Águilas', 'Cieza', 'Yecla', 'San Javier', 'Totana', 'Mazarrón', 'Caravaca de la Cruz', 'Jumilla' ),
			'Z'  => array( 'Zaragoza', 'Calatayud', 'Utebo', 'Ejea de los Caballeros', 'Tarazona', 'Caspe', 'Cuarte de Huerva', 'Zuera' ),
			'O'  => array( 'Oviedo', 'Gijón', 'Avilés', 'Siero', 'Langreo', 'Mieres', 'Castrillón', 'San Martín del Rey Aurelio', 'Corvera de Asturias', 'Llanes' ),
			'S'  => array( 'Santander', 'Torrelavega', 'Castro-Urdiales', 'Camargo', 'Piélagos', 'El Astillero', 'Laredo', 'Santoña' ),
			'PM' => array( 'Palma de Mallorca', 'Calvià', 'Ibiza', 'Manacor', 'Llucmajor', 'Marratxí', 'Ciutadella de Menorca', 'Maó-Mahón', 'Inca', 'Santa Eulària des Riu' ),
			'GC' => array( 'Las Palmas de Gran Canaria', 'Telde', 'Santa Lucía de Tirajana', 'Arrecife', 'San Bartolomé de Tirajana', 'Arucas', 'Puerto del Rosario', 'Ingenio', 'Gáldar' ),
			'TF' => array( 'Santa Cruz de Tenerife', 'San Cristóbal de La Laguna', 'Arona', 'Adeje', 'Granadilla de Abona', 'Los Realejos', 'Puerto de la Cruz', 'Candelaria', 'Icod de los Vinos' ),
			'SS' => array( 'Donostia/San Sebastián', 'Irun', 'Errenteria', 'Eibar', 'Zarautz', 'Arrasate/Mondragón', 'Hernani', 'Tolosa', 'Lasarte-Oria' ),
			'BI' => array( 'Bilbao', 'Barakaldo', 'Getxo', 'Portugalete', 'Santurtzi', 'Basauri', 'Leioa', 'Galdakao', 'Durango', 'Sestao', 'Erandio', 'Amorebieta-Etxano' ),
			'VI' => array( 'Vitoria-Gasteiz', 'Llodio', 'Amurrio', 'Salvatierra/Agurain' ),
			'NA' => array( 'Pamplona/Iruña', 'Tudela', 'Barañáin', 'Burlada/Burlata', 'Estella-Lizarra', 'Zizur Mayor', 'Tafalla' ),
			'LO' => array( 'Logroño', 'Calahorra', 'Arnedo', 'Haro', 'Lardero', 'Nájera' ),
			'C'  => array( 'A Coruña', 'Santiago de Compostela', 'Ferrol', 'Narón', 'Oleiros', 'Carballo', 'Arteixo', 'Ames', 'Culleredo', 'Ribeira' ),
			'PO' => array( 'Vigo', 'Pontevedra', 'Vilagarcía de Arousa', 'Redondela', 'Cangas', 'Marín', 'Ponteareas', 'A Estrada', 'Lalín' ),
			'LU' => array( 'Lugo', 'Monforte de Lemos', 'Viveiro', 'Vilalba', 'Sarria', 'Ribadeo' ),
			'OR' => array( 'Ourense', 'Verín', 'O Barco de Valdeorras', 'O Carballiño', 'Ginzo de Limia' ),
			'BA' => array( 'Badajoz', 'Mérida', 'Don Benito', 'Almendralejo', 'Villanueva de la Serena', 'Zafra', 'Montijo' ),
			'CC' => array( 'Cáceres', 'Plasencia', 'Navalmoral de la Mata', 'Coria', 'Miajadas', 'Trujillo' ),
			'VA' => array( 'Valladolid', 'Laguna de Duero', 'Medina del Campo', 'Arroyo de la Encomienda', 'Tordesillas' ),
			'BU' => array( 'Burgos', 'Miranda de Ebro', 'Aranda de Duero', 'Briviesca' ),
			'SA' => array( 'Salamanca', 'Béjar', 'Ciudad Rodrigo', 'Santa Marta de Tormes' ),
			'LE' => array( 'León', 'Ponferrada', 'San Andrés del Rabanedo', 'Villaquilambre', 'Astorga' ),
			'ZA' => array( 'Zamora', 'Benavente', 'Toro' ),
			'P'  => array( 'Palencia', 'Guardo', 'Aguilar de Campoo' ),
			'AV' => array( 'Ávila', 'Arévalo', 'Las Navas del Marqués' ),
			'SG' => array( 'Segovia', 'Cuéllar', 'El Espinar' ),
			'SO' => array( 'Soria', 'Almazán' ),
			'TO' => array( 'Toledo', 'Talavera de la Reina', 'Illescas', 'Seseña', 'Torrijos', 'Quintanar de la Orden' ),
			'CR' => array( 'Ciudad Real', 'Puertollano', 'Tomelloso', 'Alcázar de San Juan', 'Valdepeñas', 'Manzanares', 'Daimiel' ),
			'AB' => array( 'Albacete', 'Hellín', 'Villarrobledo', 'Almansa', 'La Roda' ),
			'GU' => array( 'Guadalajara', 'Azuqueca de Henares', 'Alovera', 'El Casar' ),
			'CU' => array( 'Cuenca', 'Tarancón', 'San Clemente' ),
			'GI' => array( 'Girona', 'Figueres', 'Blanes', 'Lloret de Mar', 'Olot', 'Salt', 'Palafrugell', 'Sant Feliu de Guíxols' ),
			'T'  => array( 'Tarragona', 'Reus', 'Tortosa', 'Cambrils', 'Salou', 'Valls', 'El Vendrell', 'Calafell' ),
			'L'  => array( 'Lleida', 'Tàrrega', 'Balaguer', 'Mollerussa', 'La Seu d\'Urgell' ),
			'CE' => array( 'Ceuta' ),
			'ML' => array( 'Melilla' ),
		);

		wp_localize_script( 'wpat-address-autofill-js', 'wpatAutofillOptions', array(
			'spain_provinces'       => $spain_provinces,
			'spain_prefix_to_city'  => $spain_prefix_to_city,
			'spain_city_to_cp'      => $spain_city_to_cp,
			'spain_province_cities' => $spain_province_cities,
			'autofill_city'         => isset( $settings['woo_address_autofill_city'] ) ? $settings['woo_address_autofill_city'] : '1',
		) );
	}
}

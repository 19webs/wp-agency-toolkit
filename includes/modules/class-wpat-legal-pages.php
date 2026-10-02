<?php
/**
 * Módulo: Generador y Gestor de Textos Legales RGPD / LSSI-CE - WP Agency Toolkit
 *
 * Centraliza la información fiscal y legal del cliente para generar y sincronizar dinámicamente
 * el Aviso Legal, la Política de Privacidad, la Política de Cookies, las Condiciones de Venta
 * y las Cláusulas informativas de 1ª capa para formularios.
 *
 * @package WPAgencyToolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Legal_Pages {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_Legal_Pages|null
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Legal_Pages
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
		// Registro de Shortcodes en Frontend
		add_shortcode( 'wpat_aviso_legal', array( $this, 'render_aviso_legal_shortcode' ) );
		add_shortcode( 'wpat_politica_privacidad', array( $this, 'render_privacidad_shortcode' ) );
		add_shortcode( 'wpat_privacidad', array( $this, 'render_privacidad_shortcode' ) ); // Alias
		add_shortcode( 'wpat_politica_cookies', array( $this, 'render_cookies_shortcode' ) );
		add_shortcode( 'wpat_cookies', array( $this, 'render_cookies_shortcode' ) ); // Alias
		add_shortcode( 'wpat_condiciones_venta', array( $this, 'render_condiciones_venta_shortcode' ) );
		add_shortcode( 'wpat_terminos_condiciones', array( $this, 'render_condiciones_venta_shortcode' ) ); // Alias
		add_shortcode( 'wpat_clausula', array( $this, 'render_clausula_shortcode' ) );
		add_shortcode( 'wpat_dato', array( $this, 'render_dato_shortcode' ) );
		add_shortcode( 'wpat_cookie_modal_button', array( $this, 'render_cookie_modal_button_shortcode' ) );
	}

	/**
	 * Obtiene el mapa de sustitución de etiquetas dinámicas (tokens).
	 *
	 * @return array
	 */
	public function get_tokens_map() {
		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : array();

		$site_url = ! empty( $settings['legal_site_url'] ) ? esc_url( $settings['legal_site_url'] ) : home_url();
		$site_name = ! empty( $settings['legal_site_name'] ) ? esc_html( $settings['legal_site_name'] ) : get_bloginfo( 'name' );
		$titular = ! empty( $settings['legal_titular'] ) ? esc_html( $settings['legal_titular'] ) : get_bloginfo( 'name' );
		$nombre_comercial = ! empty( $settings['legal_nombre_comercial'] ) ? esc_html( $settings['legal_nombre_comercial'] ) : '';
		$nif = ! empty( $settings['legal_nif'] ) ? esc_html( $settings['legal_nif'] ) : 'B-00000000';
		$direccion = ! empty( $settings['legal_direccion'] ) ? esc_html( $settings['legal_direccion'] ) : '';
		$cp = ! empty( $settings['legal_cp'] ) ? esc_html( $settings['legal_cp'] ) : '';
		$ciudad = ! empty( $settings['legal_ciudad'] ) ? esc_html( $settings['legal_ciudad'] ) : '';
		$provincia = ! empty( $settings['legal_provincia'] ) ? esc_html( $settings['legal_provincia'] ) : '';
		$pais = ! empty( $settings['legal_pais'] ) ? esc_html( $settings['legal_pais'] ) : 'España';

		$dir_parts = array_filter( array( $direccion, $cp, $ciudad, $provincia, $pais ) );
		$direccion_completa = ! empty( $dir_parts ) ? implode( ', ', $dir_parts ) : 'Domicilio Social no especificado';

		$email_rgpd = ! empty( $settings['legal_email_rgpd'] ) ? sanitize_email( $settings['legal_email_rgpd'] ) : get_option( 'admin_email' );
		$telefono = ! empty( $settings['legal_telefono'] ) ? esc_html( $settings['legal_telefono'] ) : '';
		$datos_registrales = ! empty( $settings['legal_registro_mercantil'] ) ? esc_html( $settings['legal_registro_mercantil'] ) : '';
		$dpd_contacto = ! empty( $settings['legal_dpd'] ) ? esc_html( $settings['legal_dpd'] ) : 'No aplica / ' . $email_rgpd;
		$hosting = ! empty( $settings['legal_hosting'] ) ? esc_html( $settings['legal_hosting'] ) : 'Proveedores con servidores ubicados en el Espacio Económico Europeo (EEE)';
		$actividad = ! empty( $settings['legal_actividad'] ) ? esc_html( $settings['legal_actividad'] ) : 'Prestación de servicios y comercio electrónico';

		// Enlaces de páginas legales si existen
		$privacy_page_id = (int) get_option( 'wp_page_for_privacy_policy' );
		$privacy_url = $privacy_page_id ? get_permalink( $privacy_page_id ) : home_url( '/politica-de-privacidad/' );
		$cookies_url = ! empty( $settings['legal_cookies_page_url'] ) ? esc_url( $settings['legal_cookies_page_url'] ) : home_url( '/politica-de-cookies/' );
		$aviso_url = ! empty( $settings['legal_aviso_page_url'] ) ? esc_url( $settings['legal_aviso_page_url'] ) : home_url( '/aviso-legal/' );
		$condiciones_url = ! empty( $settings['legal_condiciones_page_url'] ) ? esc_url( $settings['legal_condiciones_page_url'] ) : home_url( '/terminos-y-condiciones/' );

		$titular_completo = ! empty( $nombre_comercial ) ? $titular . ' (' . $nombre_comercial . ')' : $titular;

		return array(
			'{sitio_web}'                   => '<a href="' . esc_url( $site_url ) . '" target="_blank" rel="noopener">' . esc_html( $site_url ) . '</a>',
			'{sitio_web_url}'               => esc_url( $site_url ),
			'{nombre_sitio}'                => $site_name,
			'{titular}'                     => $titular,
			'{nombre_comercial}'            => $nombre_comercial,
			'{marca}'                       => $nombre_comercial,
			'{titular_completo}'            => $titular_completo,
			'{nombre_comercial_o_titular}'  => ! empty( $nombre_comercial ) ? $nombre_comercial : $titular,
			'{nif}'                         => $nif,
			'{cif}'                         => $nif,
			'{direccion}'                   => $direccion,
			'{cp}'                          => $cp,
			'{ciudad}'                      => $ciudad,
			'{provincia}'                   => $provincia,
			'{pais}'                        => $pais,
			'{direccion_completa}'          => $direccion_completa,
			'{email_rgpd}'                  => '<a href="mailto:' . esc_attr( $email_rgpd ) . '">' . esc_html( $email_rgpd ) . '</a>',
			'{email_rgpd_plano}'            => $email_rgpd,
			'{telefono}'                    => $telefono ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9\+]/', '', $telefono ) ) . '">' . esc_html( $telefono ) . '</a>' : '',
			'{telefono_plano}'              => $telefono,
			'{datos_registrales}'           => $datos_registrales,
			'{dpd_contacto}'                => $dpd_contacto,
			'{hosting_proveedor}'           => $hosting,
			'{actividad_comercial}'         => $actividad,
			'{url_privacidad}'              => esc_url( $privacy_url ),
			'{url_cookies}'                 => esc_url( $cookies_url ),
			'{url_aviso_legal}'             => esc_url( $aviso_url ),
			'{url_condiciones}'             => esc_url( $condiciones_url ),
		);
	}

	/**
	 * Reemplaza los tokens dinámicos en una plantilla de texto.
	 *
	 * @param string $content
	 * @return string
	 */
	public function parse_template( $content ) {
		if ( empty( $content ) ) {
			return '';
		}
		$tokens = $this->get_tokens_map();
		return strtr( $content, $tokens );
	}

	/**
	 * Renderiza el Shortcode de Aviso Legal [wpat_aviso_legal]
	 */
	public function render_aviso_legal_shortcode() {
		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : array();
		$template = ! empty( $settings['legal_template_aviso'] ) ? $settings['legal_template_aviso'] : self::get_default_aviso_legal();
		
		$html = $this->parse_template( $template );
		return '<div class="wpat-legal-document wpat-legal-aviso">' . wp_kses_post( wpautop( $html ) ) . '</div>';
	}

	/**
	 * Renderiza el Shortcode de Política de Privacidad [wpat_politica_privacidad]
	 */
	public function render_privacidad_shortcode() {
		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : array();
		$template = ! empty( $settings['legal_template_privacidad'] ) ? $settings['legal_template_privacidad'] : self::get_default_privacidad();

		$html = $this->parse_template( $template );
		return '<div class="wpat-legal-document wpat-legal-privacidad">' . wp_kses_post( wpautop( $html ) ) . '</div>';
	}

	/**
	 * Renderiza el Shortcode de Política de Cookies [wpat_politica_cookies]
	 */
	public function render_cookies_shortcode() {
		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : array();
		$template = ! empty( $settings['legal_template_cookies'] ) ? $settings['legal_template_cookies'] : self::get_default_cookies();

		$html = $this->parse_template( $template );
		$html = wpautop( $html );

		// Inyección de la tabla de cookies y botón de preferencias de forma limpia y segura
		$cookie_table_html = $this->get_cookie_table_output();
		$cookie_btn_html   = $this->render_cookie_modal_button_shortcode();

		$html = str_replace( array( '[wpat_cookie_table]', '<p>[wpat_cookie_table]</p>' ), $cookie_table_html, $html );
		$html = str_replace( array( '[wpat_cookie_modal_button]', '<p>[wpat_cookie_modal_button]</p>' ), $cookie_btn_html, $html );

		return '<div class="wpat-legal-document wpat-legal-cookies">' . wp_kses_post( $html ) . '</div>';
	}

	/**
	 * Renderiza el Shortcode de Condiciones Generales de Venta [wpat_condiciones_venta]
	 */
	public function render_condiciones_venta_shortcode() {
		$settings = class_exists( 'WPAT_Main' ) ? WPAT_Main::get_instance()->get_settings() : array();
		$template = ! empty( $settings['legal_template_condiciones'] ) ? $settings['legal_template_condiciones'] : self::get_default_condiciones_venta();

		$html = $this->parse_template( $template );
		return '<div class="wpat-legal-document wpat-legal-condiciones">' . wp_kses_post( wpautop( $html ) ) . '</div>';
	}

	/**
	 * Renderiza el botón interactivo para reabrir el modal de preferencias de cookies [wpat_cookie_modal_button]
	 * Convivencia total y limpia con el módulo de cookies.
	 */
	public function render_cookie_modal_button_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'texto' => 'Configurar o cambiar mis preferencias de cookies',
				'clase' => 'wpat-legal-cookie-btn',
			),
			$atts,
			'wpat_cookie_modal_button'
		);

		return '<div style="margin: 20px 0;"><button type="button" class="' . esc_attr( $atts['clase'] ) . '" data-wpat-action="open-cookie-modal" onclick="if(window.wpatOpenCookieModal){window.wpatOpenCookieModal();}else if(document.getElementById(\'wpat-cookie-modal\')){document.getElementById(\'wpat-cookie-modal\').style.display=\'flex\';}else{alert(\'El panel de configuración de cookies no está activo o todas las cookies técnicas están preconfiguradas.\');}" style="cursor:pointer; background:#1e293b; color:#ffffff; border:none; padding:10px 18px; border-radius:6px; font-weight:600; font-size:14px; display:inline-flex; align-items:center; gap:8px;">' . esc_html( $atts['texto'] ) . '</button></div>';
	}

	/**
	 * Genera la tabla de cookies de forma segura y sin conflictos con el módulo de cookies consent.
	 *
	 * @return string
	 */
	public function get_cookie_table_output() {
		$cookie_file = WPAT_PATH . 'includes/modules/class-wpat-cookie-consent.php';
		if ( ! class_exists( 'WPAT_Cookie_Consent' ) && file_exists( $cookie_file ) ) {
			require_once $cookie_file;
		}

		// 1. Si el módulo de cookies está cargado y disponible, usar su tabla
		if ( class_exists( 'WPAT_Cookie_Consent' ) ) {
			if ( method_exists( 'WPAT_Cookie_Consent', 'render_cookie_table_shortcode' ) ) {
				return WPAT_Cookie_Consent::render_cookie_table_shortcode();
			} elseif ( method_exists( 'WPAT_Cookie_Consent', 'get_instance' ) ) {
				return WPAT_Cookie_Consent::get_instance()->render_cookie_table_shortcode();
			}
		}

		// 2. Fallback elegante si el módulo no estuviera disponible
		return '<div class="wpat-cookie-table-wrapper"><table class="wpat-cookie-table" style="width:100%; border-collapse:collapse; margin:20px 0;"><thead><tr style="background:#f8fafc;"><th style="border:1px solid #e2e8f0; padding:10px; text-align:left;">Cookie</th><th style="border:1px solid #e2e8f0; padding:10px; text-align:left;">Proveedor</th><th style="border:1px solid #e2e8f0; padding:10px; text-align:left;">Finalidad</th><th style="border:1px solid #e2e8f0; padding:10px; text-align:left;">Caducidad</th><th style="border:1px solid #e2e8f0; padding:10px; text-align:left;">Tipo</th></tr></thead><tbody><tr><td style="border:1px solid #e2e8f0; padding:10px;"><code>wpat_cookie_consent</code></td><td style="border:1px solid #e2e8f0; padding:10px;">Este Sitio Web</td><td style="border:1px solid #e2e8f0; padding:10px;">Guarda las preferencias de consentimiento de cookies del usuario.</td><td style="border:1px solid #e2e8f0; padding:10px;">6 meses</td><td style="border:1px solid #e2e8f0; padding:10px;">Necesaria</td></tr></tbody></table></div>';
	}

	/**
	 * Renderiza Cláusulas informativas de 1ª capa para formularios [wpat_clausula tipo="contacto|newsletter|checkout|comentarios"]
	 */
	public function render_clausula_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'tipo'   => 'contacto',
				'estilo' => 'caja', // 'caja' o 'texto'
			),
			$atts,
			'wpat_clausula'
		);

		$tipo   = sanitize_key( $atts['tipo'] );
		$estilo = sanitize_key( $atts['estilo'] );
		$tokens = $this->get_tokens_map();

		$finalidad = '';
		$legitimacion = '';

		switch ( $tipo ) {
			case 'newsletter':
				$finalidad    = 'Envío de información comercial, promociones, novedades y ofertas de su interés.';
				$legitimacion = 'Consentimiento inequívoco del interesado (Art. 6.1.a RGPD).';
				break;
			case 'checkout':
			case 'pedido':
			case 'tienda':
				$finalidad    = 'Gestión, tramitación, facturación y envío del pedido realizado, así como el cumplimiento de obligaciones legales tributarias.';
				$legitimacion = 'Ejecución del contrato de compraventa y cumplimiento de obligaciones legales (Art. 6.1.b y 6.1.c RGPD).';
				break;
			case 'comentarios':
				$finalidad    = 'Gestión, moderación y publicación de su comentario o reseña en el sitio web.';
				$legitimacion = 'Consentimiento del interesado e interés legítimo del responsable.';
				break;
			case 'contacto':
			default:
				$finalidad    = 'Atender y dar respuesta a su consulta, solicitud o petición realizada a través del formulario.';
				$legitimacion = 'Consentimiento inequívoco del interesado al enviar el formulario (Art. 6.1.a RGPD).';
				break;
		}

		$destinatarios = 'No se cederán datos a terceros, salvo obligación legal expresa o proveedores de servicios técnicos necesarios ubicados en la UE.';
		$derechos      = 'Tiene derecho a acceder, rectificar, suprimir y limitar el tratamiento de sus datos, así como otros derechos, dirigiéndose a ' . $tokens['{email_rgpd}'];
		$mas_info      = '<a href="' . esc_url( $tokens['{url_privacidad}'] ) . '" target="_blank" rel="noopener" style="text-decoration:underline;">Política de Privacidad</a>';

		if ( 'texto' === $estilo ) {
			$output = '<div class="wpat-legal-clausula-text" style="font-size: 11px; line-height: 1.45; color: #64748b; margin-top: 10px;">' .
				'<strong>Información básica sobre protección de datos:</strong> ' .
				'<strong>Responsable:</strong> ' . $tokens['{titular}'] . ' (' . $tokens['{nif}'] . '). ' .
				'<strong>Finalidad:</strong> ' . $finalidad . ' ' .
				'<strong>Legitimación:</strong> ' . $legitimacion . ' ' .
				'<strong>Derechos:</strong> ' . $derechos . '. ' .
				'<strong>Información adicional:</strong> Consulte los detalles en nuestra ' . $mas_info . '.' .
				'</div>';
		} else {
			$output = '<div class="wpat-legal-clausula-box" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; font-size:11px; line-height:1.45; color:#475569; margin:14px 0;">' .
				'<div style="font-weight:700; color:#1e293b; margin-bottom:6px; font-size:12px;">🛡️ Información sobre Protección de Datos</div>' .
				'<table style="width:100%; border:none; margin:0; font-size:11px; line-height:1.4;">' .
				'<tr><td style="padding:2px 6px 2px 0; font-weight:600; width:95px; vertical-align:top; color:#334155;">Responsable:</td><td style="padding:2px 0; vertical-align:top;">' . $tokens['{titular}'] . ' (' . $tokens['{nif}'] . ')</td></tr>' .
				'<tr><td style="padding:2px 6px 2px 0; font-weight:600; vertical-align:top; color:#334155;">Finalidad:</td><td style="padding:2px 0; vertical-align:top;">' . $finalidad . '</td></tr>' .
				'<tr><td style="padding:2px 6px 2px 0; font-weight:600; vertical-align:top; color:#334155;">Legitimación:</td><td style="padding:2px 0; vertical-align:top;">' . $legitimacion . '</td></tr>' .
				'<tr><td style="padding:2px 6px 2px 0; font-weight:600; vertical-align:top; color:#334155;">Destinatarios:</td><td style="padding:2px 0; vertical-align:top;">' . $destinatarios . '</td></tr>' .
				'<tr><td style="padding:2px 6px 2px 0; font-weight:600; vertical-align:top; color:#334155;">Derechos:</td><td style="padding:2px 0; vertical-align:top;">' . $derechos . '</td></tr>' .
				'<tr><td style="padding:2px 6px 2px 0; font-weight:600; vertical-align:top; color:#334155;">Info adicional:</td><td style="padding:2px 0; vertical-align:top;">Más información en nuestra ' . $mas_info . '.</td></tr>' .
				'</table>' .
				'</div>';
		}

		return $output;
	}

	/**
	 * Renderiza un dato específico del negocio [wpat_dato campo="titular|nif|email|telefono|direccion|web|datos_registrales"]
	 */
	public function render_dato_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'campo' => 'titular',
			),
			$atts,
			'wpat_dato'
		);

		$tokens = $this->get_tokens_map();
		$campo  = sanitize_key( $atts['campo'] );

		switch ( $campo ) {
			case 'titular':
			case 'empresa':
			case 'razon_social':
				return $tokens['{titular}'];
			case 'nombre_comercial':
			case 'marca':
				return $tokens['{nombre_comercial}'];
			case 'titular_completo':
				return $tokens['{titular_completo}'];
			case 'nif':
			case 'cif':
			case 'dni':
				return $tokens['{nif}'];
			case 'email':
			case 'email_rgpd':
				return $tokens['{email_rgpd_plano}'];
			case 'telefono':
				return $tokens['{telefono_plano}'];
			case 'direccion':
			case 'direccion_completa':
			case 'domicilio':
				return $tokens['{direccion_completa}'];
			case 'registro':
			case 'datos_registrales':
			case 'registro_mercantil':
				return $tokens['{datos_registrales}'];
			case 'web':
			case 'sitio_web':
				return $tokens['{sitio_web_url}'];
			default:
				return '';
		}
	}

	/**
	 * Plantilla por defecto del Aviso Legal (LSSI-CE).
	 *
	 * @return string
	 */
	public static function get_default_aviso_legal() {
		return '<h2>1. Datos identificativos y de contacto (LSSI-CE)</h2>
<p>En cumplimiento de lo dispuesto en el artículo 10 de la Ley 34/2002, de 11 de julio, de Servicios de la Sociedad de la Información y de Comercio Electrónico (LSSI-CE), se informa a los usuarios de los datos identificativos del titular de este Sitio Web:</p>
<ul>
	<li><strong>Titular / Razón Social:</strong> {titular}</li>
	<li><strong>N.I.F. / C.I.F.:</strong> {nif}</li>
	<li><strong>Domicilio social:</strong> {direccion_completa}</li>
	<li><strong>Correo electrónico de contacto:</strong> {email_rgpd}</li>
	<li><strong>Teléfono de contacto:</strong> {telefono}</li>
	<li><strong>Sitio Web:</strong> {sitio_web}</li>
	<li><strong>Datos registrales:</strong> {datos_registrales}</li>
</ul>

<h2>2. Objeto y ámbito de aplicación</h2>
<p>El presente Aviso Legal regula el acceso, navegación y uso del sitio web {sitio_web} (en adelante, el "Sitio Web"), así como las responsabilidades derivadas de la utilización de sus contenidos (textos, gráficos, dibujos, diseños, códigos, software, fotografías, música, vídeos, sonidos, bases de datos, imágenes, expresiones e informaciones protegidas por las leyes nacionales y tratados internacionales sobre propiedad intelectual e industrial).</p>
<p>El acceso o la utilización del Sitio Web atribuyen la condición de Usuario e implican la adhesión plena y sin reservas a todas y cada una de las condiciones incluidas en este Aviso Legal publicado en el momento en que se acceda al mismo.</p>

<h2>3. Condiciones de acceso y uso del Sitio Web</h2>
<p>El acceso a este Sitio Web es libre y gratuito. El Usuario se compromete a hacer un uso adecuado, diligente y lícito del Sitio Web y de sus contenidos, de conformidad con la legislación aplicable, el presente Aviso Legal, las buenas costumbres y el orden público.</p>
<p>El Usuario se abstendrá expresamente de:</p>
<ul>
	<li>Hacer un uso no autorizado o fraudulento del Sitio Web y/o de los contenidos con fines o efectos ilícitos o lesivos para terceros.</li>
	<li>Provocar daños en los sistemas físicos o lógicos de {titular}, de sus proveedores o de terceras personas, o introducir virus informáticos y software malicioso.</li>
	<li>Intentar acceder, utilizar y/o manipular los datos de {titular}, terceros proveedores y otros usuarios.</li>
	<li>Reproducir, copiar, distribuir o transformar los contenidos sin la autorización expresa de {titular}.</li>
</ul>

<h2>4. Propiedad Intelectual e Industrial</h2>
<p>{titular}, por sí o como parte cesionaria o licenciataria, es titular de todos los derechos de propiedad intelectual e industrial del Sitio Web, así como de los elementos contenidos en el mismo (a título enunciativo: marcas, logotipos, combinaciones de colores, estructura y diseño, selección de materiales usados, programas de ordenador necesarios para su funcionamiento, etc.).</p>
<p>En virtud de lo dispuesto en los artículos 8 y 32.1, párrafo segundo, de la Ley de Propiedad Intelectual, quedan expresamente prohibidas la reproducción, la distribución y la comunicación pública, incluida su modalidad de puesta a disposición, de la totalidad o parte de los contenidos de esta página web con fines comerciales, en cualquier soporte y por cualquier medio técnico, sin la autorización previa por escrito de {titular}.</p>

<h2>5. Exclusión de garantías y responsabilidad</h2>
<p>{titular} declara que ha adoptado las medidas técnicas y organizativas necesarias, dentro de sus posibilidades y del estado de la tecnología, para garantizar el correcto funcionamiento del Sitio Web y evitar la presencia de componentes dañinos.</p>
<p>Sin embargo, {titular} no se hace responsable de:</p>
<ul>
	<li>La continuidad y disponibilidad de los contenidos y servicios.</li>
	<li>La ausencia de errores en dichos contenidos o la corrección de cualquier defecto que pudiera ocurrir.</li>
	<li>Los daños o perjuicios que cause cualquier persona que vulnere los sistemas de seguridad del Sitio Web.</li>
</ul>

<h2>6. Enlaces a terceros (Links)</h2>
<p>En el caso de que en el Sitio Web se dispusiesen enlaces o hipervínculos hacia otros sitios de Internet, {titular} no ejercerá ningún tipo de control sobre dichos sitios y contenidos. En ningún caso {titular} asumirá responsabilidad alguna por los contenidos de algún enlace perteneciente a un sitio web ajeno.</p>

<h2>7. Resolución de litigios en línea y legislación aplicable</h2>
<p>Conforme al Art. 14.1 del Reglamento (UE) 524/2013, la Comisión Europea facilita una plataforma de resolución de litigios en línea, la cual se encuentra disponible en el siguiente enlace: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a>.</p>
<p>Las relaciones establecidas entre {titular} y el Usuario se regirán por la normativa española vigente. Cualquier controversia se someterá a los Juzgados y Tribunales competentes de conformidad con la legislación de consumidores y usuarios aplicable.</p>';
	}

	/**
	 * Plantilla por defecto de la Política de Privacidad (RGPD / LOPDGDD).
	 *
	 * @return string
	 */
	public static function get_default_privacidad() {
		return '<h2>1. Información al Usuario y Responsable del Tratamiento</h2>
<p>{titular}, como Responsable del Tratamiento, le informa de que, en cumplimiento del Reglamento (UE) 2016/679 del Parlamento Europeo y del Consejo, de 27 de abril de 2016 (RGPD), y de la Ley Orgánica 3/2018, de 5 de diciembre, de Protección de Datos Personales y garantía de los derechos digitales (LOPDGDD), tratará sus datos personales conforme a los principios de licitud, lealtad, transparencia, limitación de la finalidad y minimización de datos.</p>

<ul>
	<li><strong>Responsable del tratamiento:</strong> {titular}</li>
	<li><strong>N.I.F. / C.I.F.:</strong> {nif}</li>
	<li><strong>Dirección postal:</strong> {direccion_completa}</li>
	<li><strong>Correo electrónico RGPD:</strong> {email_rgpd}</li>
	<li><strong>Teléfono:</strong> {telefono}</li>
	<li><strong>Delegado de Protección de Datos (DPD):</strong> {dpd_contacto}</li>
</ul>

<h2>2. Finalidades del tratamiento y bases jurídicas</h2>
<p>Tratamos la información que nos facilitan las personas interesadas con las siguientes finalidades y bases de legitimación:</p>

<table style="width:100%; border-collapse:collapse; margin:16px 0;">
	<thead>
		<tr style="background:#f8fafc;">
			<th style="border:1px solid #e2e8f0; padding:10px; text-align:left;">Tratamiento / Finalidad</th>
			<th style="border:1px solid #e2e8f0; padding:10px; text-align:left;">Base Jurídica (Legitimación)</th>
			<th style="border:1px solid #e2e8f0; padding:10px; text-align:left;">Plazo de Conservación</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td style="border:1px solid #e2e8f0; padding:10px;"><strong>Formularios de contacto y soporte:</strong> Atender solicitudes, dudas, presupuestos o mensajes recibidos a través de la web.</td>
			<td style="border:1px solid #e2e8f0; padding:10px;">Consentimiento inequívoco del interesado (Art. 6.1.a RGPD).</td>
			<td style="border:1px solid #e2e8f0; padding:10px;">Durante el tiempo necesario para resolver la consulta o hasta solicitar su supresión.</td>
		</tr>
		<tr>
			<td style="border:1px solid #e2e8f0; padding:10px;"><strong>Gestión de clientes y pedidos (E-Commerce):</strong> Tramitación de pedidos, facturación, cobro y entrega física o digital de productos.</td>
			<td style="border:1px solid #e2e8f0; padding:10px;">Ejecución de un contrato de compraventa (Art. 6.1.b RGPD) y cumplimiento de obligaciones legales tributarias (Art. 6.1.c RGPD).</td>
			<td style="border:1px solid #e2e8f0; padding:10px;">Durante la relación comercial y, posteriormente, durante los plazos legalmente exigidos (mínimo 5 años según Código de Comercio y normativa fiscal).</td>
		</tr>
		<tr>
			<td style="border:1px solid #e2e8f0; padding:10px;"><strong>Suscripción a Newsletter y comunicaciones comerciales:</strong> Envío de promociones, novedades, artículos y ofertas.</td>
			<td style="border:1px solid #e2e8f0; padding:10px;">Consentimiento expreso del interesado (Art. 6.1.a RGPD / Art. 21 LSSI-CE).</td>
			<td style="border:1px solid #e2e8f0; padding:10px;">Hasta que el usuario revoque su consentimiento mediante el enlace de baja incluido en cada email.</td>
		</tr>
	</tbody>
</table>

<h2>3. Destinatarios y Encargados del Tratamiento</h2>
<p>No se comunicarán ni cederán datos personales a terceros, salvo obligación legal expresa o cuando sea estrictamente necesario para la prestación del servicio. En tales casos, los destinatarios actúan bajo contrato de Encargo de Tratamiento conforme al Art. 28 del RGPD:</p>
<ul>
	<li><strong>Proveedores de alojamiento web y servidores:</strong> {hosting_proveedor}.</li>
	<li><strong>Pasarelas de pago y entidades bancarias:</strong> Redsys, Stripe o PayPal para procesar los pagos de forma cifrada y segura.</li>
	<li><strong>Empresas de mensajería y logística:</strong> Para la entrega material de los pedidos en el domicilio indicado por el cliente.</li>
	<li><strong>Plataformas de envío de correo electrónico transaccional y marketing:</strong> Con servidores dentro de la UE o amparadas por garantías adecuadas.</li>
</ul>

<h2>4. Transferencias internacionales de datos</h2>
<p>Con carácter general, los datos se almacenan en servidores ubicados dentro del Espacio Económico Europeo (EEE). Si algún proveedor auxiliar estuviera ubicado fuera del EEE, se garantiza que cuenta con cláusulas contractuales tipo (SCC) aprobadas por la Comisión Europea o se encuentra acogido al Marco de Privacidad de Datos UE-EE.UU. (Data Privacy Framework).</p>

<h2>5. Derechos de las personas interesadas (ARCO-POL)</h2>
<p>El Usuario puede ejercer en cualquier momento sus derechos reconocidos por el RGPD y la LOPDGDD:</p>
<ul>
	<li><strong>Derecho de Acceso:</strong> Conocer qué datos personales suyos están siendo tratados y con qué fin.</li>
	<li><strong>Derecho de Rectificación:</strong> Solicitar la corrección de datos inexactos o incompletos.</li>
	<li><strong>Derecho de Supresión (Derecho al olvido):</strong> Solicitar la eliminación de sus datos cuando ya no sean necesarios para los fines que fueron recogidos.</li>
	<li><strong>Derecho de Oposición:</strong> Oponerse al tratamiento de sus datos cuando la base sea el interés legítimo o para fines de marketing.</li>
	<li><strong>Derecho a la Limitación del tratamiento:</strong> Solicitar la limitación temporal del uso de sus datos en los supuestos previstos por la ley.</li>
	<li><strong>Derecho a la Portabilidad:</strong> Recibir sus datos en formato estructurado, de uso común y lectura mecánica para transmitirlos a otro responsable.</li>
	<li><strong>Derecho a no ser objeto de decisiones individuales automatizadas:</strong> Incluida la elaboración de perfiles.</li>
</ul>
<p>Para ejercitar cualquiera de estos derechos, el Usuario puede enviar una solicitud por escrito acreditando su identidad a la dirección postal {direccion_completa} o por correo electrónico a {email_rgpd}.</p>
<p>Asimismo, si considera que el tratamiento de sus datos personales vulnera la normativa, tiene derecho a presentar una reclamación ante la Autoridad de Control competente: en España, la <strong>Agencia Española de Protección de Datos (AEPD)</strong> a través de su sede electrónica en <a href="https://www.aepd.es" target="_blank" rel="noopener">www.aepd.es</a>.</p>

<h2>6. Seguridad y Secreto de los datos</h2>
<p>{titular} se compromete a respetar la confidencialidad de los datos personales y a utilizarlos de acuerdo con las finalidades declaradas, adoptando todas las medidas técnicas y organizativas necesarias (cifrado SSL/HTTPS, firewalls, copias de seguridad) para evitar su alteración, pérdida, tratamiento o acceso no autorizado.</p>';
	}

	/**
	 * Plantilla por defecto de la Política de Cookies (AEPD 2024).
	 *
	 * @return string
	 */
	public static function get_default_cookies() {
		return '<h2>1. ¿Qué son las cookies?</h2>
<p>Una cookie es un pequeño archivo de texto que un sitio web descarga en su navegador u ordenador cuando usted lo visita. Las cookies permiten a una página web, entre otras cosas, almacenar y recuperar información sobre los hábitos de navegación de un usuario o de su equipo y, dependiendo de la información que contengan y de la forma en que utilice su equipo, pueden utilizarse para reconocer al usuario.</p>

<h2>2. Tipos de cookies utilizadas en este Sitio Web</h2>
<p>En función de su finalidad, este Sitio Web puede utilizar las siguientes categorías de cookies:</p>
<ul>
	<li><strong>Cookies Técnicas y Estrictamente Necesarias:</strong> Son aquellas imprescindibles para permitir la navegación a través de la página web, garantizar la seguridad del sitio y gestionar la sesión, el carrito de compras o las preferencias de privacidad. No requieren consentimiento previo.</li>
	<li><strong>Cookies de Análisis o Medición:</strong> Permiten cuantificar el número de usuarios y realizar la medición y análisis estadístico de la utilización que hacen los usuarios del servicio ofertado con el fin de introducir mejoras.</li>
	<li><strong>Cookies de Personalización o Preferencias:</strong> Permiten recordar información para que el usuario acceda al servicio con determinadas características personalizadas (idioma, aspecto regional, etc.).</li>
	<li><strong>Cookies de Publicidad Comportamental y Marketing:</strong> Almacenan información del comportamiento de los usuarios obtenida a través de la observación continuada de sus hábitos de navegación, lo que permite desarrollar un perfil específico para mostrar publicidad relevante.</li>
</ul>

<h2>3. Listado detallado de Cookies activas en el Sitio Web</h2>
<p>A continuación se muestra el inventario actualizado de cookies detectadas y utilizadas en este Sitio Web:</p>

[wpat_cookie_table]

<h2>4. Panel de Configuración y Gestión del Consentimiento</h2>
<p>Usted puede aceptar todas las cookies, rechazarlas o configurar sus preferencias en cualquier momento a través del siguiente botón interactivo:</p>

[wpat_cookie_modal_button]

<h2>5. Cómo deshabilitar o eliminar cookies desde el navegador</h2>
<p>El Usuario puede permitir, bloquear o eliminar las cookies instaladas en su equipo mediante la configuración de las opciones del navegador web que utilice:</p>
<ul>
	<li><strong>Google Chrome:</strong> <a href="https://support.google.com/chrome/answer/95647" target="_blank" rel="noopener">Instrucciones para Chrome</a></li>
	<li><strong>Mozilla Firefox:</strong> <a href="https://support.mozilla.org/es/kb/habilitar-y-deshabilitar-cookies-sitios-web-rastrear-preferencias" target="_blank" rel="noopener">Instrucciones para Firefox</a></li>
	<li><strong>Microsoft Edge:</strong> <a href="https://support.microsoft.com/es-es/microsoft-edge/eliminar-las-cookies-en-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" rel="noopener">Instrucciones para Edge</a></li>
	<li><strong>Apple Safari:</strong> <a href="https://support.apple.com/es-es/guide/safari/sfri11471/mac" target="_blank" rel="noopener">Instrucciones para Safari</a></li>
</ul>

<h2>6. Actualización de la Política de Cookies</h2>
<p>Este Sitio Web puede modificar esta Política de Cookies en función de exigencias legislativas, reglamentarias, o con la finalidad de adaptar dicha política a las instrucciones dictadas por la Agencia Española de Protección de Datos (AEPD).</p>';
	}

	/**
	 * Plantilla por defecto de las Condiciones Generales de Venta / Contratación (WooCommerce & TRLGDCU).
	 *
	 * @return string
	 */
	public static function get_default_condiciones_venta() {
		return '<h2>1. Disposiciones Generales y Datos Identificativos</h2>
<p>Las presentes Condiciones Generales de Contratación regulan expresamente las relaciones comerciales surgidas entre {titular}, con N.I.F. {nif} y domicilio en {direccion_completa}, y los terceros (en adelante, "Clientes") que adquieran productos o contraten servicios a través del sitio web {sitio_web}.</p>
<p>La formalización de cualquier pedido a través de este sitio web implica la aceptación íntegra, expresa y sin reservas de las presentes Condiciones Generales.</p>

<h2>2. Proceso de Compra</h2>
<p>Para realizar compras en {sitio_web}, el Cliente deberá seleccionar los productos deseados, añadirlos al carrito de compra y completar el formulario de facturación y envío. Antes de finalizar el pedido, el Cliente podrá revisar el resumen de los productos, precios e impuestos aplicables, así como los costes de envío.</p>

<h2>3. Precios, Impuestos y Formas de Pago</h2>
<p>Todos los precios mostrados en el catálogo de productos incluyen el Impuesto sobre el Valor Añadido (IVA) legalmente aplicable en España, salvo que se indique expresamente lo contrario.</p>
<p>Las formas de pago admitidas en la tienda online son:</p>
<ul>
	<li><strong>Tarjeta de Crédito / Débito:</strong> A través de pasarela de pago segura con cifrado SSL (3D Secure).</li>
	<li><strong>Bizum:</strong> Pago instantáneo a través de pasarela bancaria.</li>
	<li><strong>Transferencia bancaria o PayPal:</strong> Según las opciones disponibles en el momento del pago.</li>
</ul>

<h2>4. Envíos, Plazos y Gastos de Entrega</h2>
<p>Los productos físicos se enviarán a la dirección indicada por el Cliente durante el proceso de compra. Los plazos habituales de entrega son de 24 a 72 horas laborables para envíos en la Península Ibérica, salvo incidencias extraordinarias o productos en reserva.</p>
<p>Los gastos de envío se calcularán automáticamente en la pantalla de pago en función del destino y del peso/volumen de los artículos.</p>

<h2>5. Derecho Legal de Desistimiento (14 días naturales)</h2>
<p>De conformidad con el Real Decreto Legislativo 1/2007, de 16 de noviembre, por el que se aprueba el texto refundido de la Ley General para la Defensa de los Consumidores y Usuarios (TRLGDCU), el Cliente tiene derecho a desistir del contrato en un plazo de <strong>14 días naturales</strong> desde la recepción del producto, sin necesidad de justificación.</p>
<p>Para ejercer el derecho de desistimiento, el Cliente deberá notificar su decisión mediante una declaración inequívoca enviada a {email_rgpd} indicando su nombre, número de pedido y los productos que desea devolver.</p>
<p>El producto deberá ser devuelto en su embalaje original, con todos sus accesorios y en perfecto estado de conservación. Salvo error atribuible a {titular}, los costes directos de la devolución correrán a cargo del Cliente.</p>

<h2>6. Garantía Legal de los Productos</h2>
<p>Conforme a la normativa vigente en materia de garantías (Real Decreto-ley 7/2021), los productos nuevos cuentan con una garantía legal de <strong>3 años</strong> frente a cualquier falta de conformidad existente en el momento de la entrega.</p>

<h2>7. Hojas de Reclamaciones y Resolución de Litigios</h2>
<p>Existen hojas de reclamaciones oficiales a disposición de los consumidores y usuarios, las cuales pueden ser solicitadas por correo electrónico a {email_rgpd}.</p>
<p>Asimismo, conforme al Art. 14.1 del Reglamento (UE) 524/2013, la Comisión Europea facilita una plataforma de resolución de litigios en línea disponible en: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener">https://ec.europa.eu/consumers/odr/</a>.</p>';
	}
}

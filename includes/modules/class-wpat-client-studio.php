<?php
/**
 * Módulo: Diseño SaaS para Entradas, Productos & CPTs (Client Studio)
 * Estado: En desarrollo (Próximamente).
 *
 * @package WP_Agency_Toolkit
 */

defined( 'ABSPATH' ) || exit;

class WPAT_Client_Studio {

	/**
	 * Instancia Singleton.
	 *
	 * @var WPAT_Client_Studio
	 */
	private static $instance = null;

	/**
	 * Obtiene la instancia Singleton.
	 *
	 * @return WPAT_Client_Studio
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor inerte.
	 */
	private function __construct() {
		// Módulo en fase 'Próximamente'. No ejecuta ganchos ni redirecciones en WordPress.
	}
}

<?php
/**
 * WP Agency Toolkit - Silent Upgrader Skin
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_Upgrader_Skin' ) ) {
	class WPAT_Silent_Upgrader_Skin extends WP_Upgrader_Skin {
		public function header() {}
		public function footer() {}
		public function error( $errors ) {}
		public function feedback( $string, ...$args ) {}
	}
}

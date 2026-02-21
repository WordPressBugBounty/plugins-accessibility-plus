<?php
/**
 * Class Fixes file.
 *
 * @package AccessibilityPlus
 */

namespace WebYes\AccessibilityPlus\Lite\Frontend\Modules\Fixes;

use WebYes\AccessibilityPlus\Lite\Includes\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Fixes extends Modules {

	/**
	 * Array to store loaded fix instances
	 *
	 * @var array
	 */
	private $fixes = array();

	public function init() {
		// Load fixes on plugins_loaded hook
		add_action( 'init', array( $this, 'load_fixes' ) );
	}

	/**
	 * Load all fix files from the fix folder
	 *
	 * @return void
	 */
	public function load_fixes() {
		$fix_dir = plugin_dir_path( __FILE__ ) . 'fix/';

		// Check if the fix directory exists
		if ( ! is_dir( $fix_dir ) ) {
			return;
		}

		// Get all PHP files from the fix directory
		$fix_files = glob( $fix_dir . '*.php' );

		if ( empty( $fix_files ) ) {
			return;
		}

		foreach ( $fix_files as $fix_file ) {
			$this->load_fix_file( $fix_file );
		}
	}

	/**
	 * Load a single fix file
	 *
	 * @param string $file_path Path to the fix file
	 * @return void
	 */
	private function load_fix_file( $file_path ) {
		// Get the class name from the file name
		$file_name  = basename( $file_path, '.php' );
		$class_name = $this->get_class_name_from_file( $file_name );

		// Build the full namespace class name
		$class_name = 'WebYes\AccessibilityPlus\Lite\Frontend\Modules\Fixes\Fix\\' . $class_name;

		if ( class_exists( $class_name ) ) {
			$fix_instance = new $class_name();
			if ( $fix_instance instanceof $class_name ) {
				$fix_instance->load();
			}
		}
	}

	/**
	 * Convert file name to class name
	 *
	 * @param string $file_name File name without extension
	 * @return string Class name
	 */
	private function get_class_name_from_file( $file_name ) {
		// Convert file name to class name format
		// e.g., 'class-color-contrast' becomes 'Color_Contrast'
		$class_name = str_replace( array( 'class-', '-' ), array( '', '_' ), $file_name );
		$class_name = str_replace( '_', ' ', $class_name );
		$class_name = ucwords( $class_name );
		$class_name = str_replace( ' ', '_', $class_name );

		return $class_name;
	}

	/**
	 * Get all loaded fixes
	 *
	 * @return array Array of fix instances
	 */
	public function get_fixes() {
		return $this->fixes;
	}
}

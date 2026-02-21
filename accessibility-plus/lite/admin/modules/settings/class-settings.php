<?php
/**
 * Class Settings file.
 *
 * @package AccessibilityPlus
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings;

use WebYes\AccessibilityPlus\Lite\Includes\Modules;
use WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Api\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles AccessibilityPlus Operation
 * @class       Settings
 * @version     3.0.0
 * @package     AccessibilityPlus
 */
class Settings extends Modules {

	/**
	 * Constructor.
	 */
	public function init() {
		$this->load_default();
		$this->load_apis();
	}

	/**
	 * Load API files
	 *
	 * @return void
	 */
	public function load_apis() {
		new Api();
	}


	/**
	 * Load default settings to the database.
	 *
	 * @return void
	 */
	public function load_default() {
		if ( false === wya11y_first_time_install() || false !== get_option( 'wya11y_settings', false ) ) {
			return;
		}
		$settings = new \WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Includes\Settings();
		$default  = $settings->get_defaults();
		$settings->update( $default );
	}
}

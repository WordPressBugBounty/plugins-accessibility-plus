<?php
/**
 * Class Settings file.
 *
 * @package AccessibilityPlus
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Statement;

use WebYes\AccessibilityPlus\Lite\Includes\Modules;
use WebYes\AccessibilityPlus\Lite\Admin\Modules\Statement\Api\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles AccessibilityPlus Operation
 * @class       Statement
 * @version     3.0.0
 * @package     AccessibilityPlus
 */
class Statement extends Modules {

	/**
	 * Constructor.
	 */
	public function init() {
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
}

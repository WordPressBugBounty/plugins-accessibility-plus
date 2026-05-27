<?php
/**
 * Auditable URLs module.
 *
 * Top-level orchestrator for the auditable URL listing exposed to the
 * "Accessibility Checker" tab. Wires up the REST controller and the cache
 * invalidation hooks for the underlying list.
 *
 * @package AccessibilityPlus
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Auditable_Urls;

use WebYes\AccessibilityPlus\Lite\Includes\Modules;
use WebYes\AccessibilityPlus\Lite\Admin\Modules\Auditable_Urls\Api\Api;
use WebYes\AccessibilityPlus\Lite\Admin\Modules\Auditable_Urls\Includes\Auditable_Urls as Auditable_Urls_List;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auditable URLs module.
 *
 * @class Auditable_Urls
 */
class Auditable_Urls extends Modules {

	/**
	 * Bootstrap the module.
	 *
	 * @return void
	 */
	public function init() {
		$this->load_apis();
		Auditable_Urls_List::get_instance()->register_cache_invalidation();
	}

	/**
	 * Instantiate the REST controller.
	 *
	 * @return void
	 */
	public function load_apis() {
		new Api();
	}
}

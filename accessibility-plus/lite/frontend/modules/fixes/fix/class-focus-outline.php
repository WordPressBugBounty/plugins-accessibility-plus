<?php
/**
 * Class Focus_Outline file.
 *
 * @package AccessibilityPlus
 *
 * @since 2.0.0
 */
namespace WebYes\AccessibilityPlus\Lite\Frontend\Modules\Fixes\Fix;
use WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Includes\Settings;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Focus_Outline class
 *
 * @since 2.0.0
 */
class Focus_Outline {

	/**
	 * Load the fix
	 *
	 * @return void
	 */
	public function load() {
		$settings          = Settings::get_instance();
		$fix_focus_outline = $settings->get( 'fixes', 'addFocusOutline' );

		if ( isset( $fix_focus_outline['status'] ) && $fix_focus_outline['status'] ) {
			add_filter( 'wp_head', array( $this, 'css' ) );
		}
	}

	/**
	 * Outputs the CSS for the focus outline fix.
	 *
	 * @return void
	 */
	public function css() {
		?>
		<style id="wya11y-fix-focus-outline">
			:focus {
				outline: revert !important;
				outline-offset: revert !important;
			}
		</style>
		<?php
	}
}
<?php

/**
 * Class New_Window_Warning file.
 *
 * @package AccessibilityPlus
 *
 * @since 2.0.0
 */

namespace WebYes\AccessibilityPlus\Lite\Frontend\Modules\Fixes\Fix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Includes\Settings;

/**
 * New_Window_Warning class
 *
 * @since 2.0.0
 */
class New_Window_Warning {

	/**
	 * Load the fix
	 *
	 * @return void
	 */
	public function load() {
		$settings               = Settings::get_instance();
		$fix_new_window_warning = $settings->get( 'fixes	', 'newWindowWarning' );
		if ( isset( $fix_new_window_warning['status'] ) && $fix_new_window_warning['status'] ) {
			// unregister the anww script if it's present, this fix supercedes it.
			if ( class_exists( '\ANWW' ) && defined( 'ANWW_VERSION' ) ) {
				add_action(
					'wp_enqueue_scripts',
					function () {
						wp_deregister_script( 'anww' );
						wp_deregister_style( 'anww' );
					},
					PHP_INT_MAX
				);
			}
		}
		add_action( 'wp_head', array( $this, 'add_styles' ) );
	}

	/**
	 * Add the styles for the new window warning.
	 */
	public function add_styles() {
		?>
		<style id="wyall-nww">
			
			.wya11y-nww-external-link-icon {
				font: normal normal normal 1em var(--font-base) !important;
				text-transform: none;
				-webkit-font-smoothing: antialiased;
				-moz-osx-font-smoothing: grayscale;
			}
		</style>
		<?php
	}
}
<?php

/**
 * Class Under_Line_Links file.
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
 * Under_Line_Links class
 *
 * @since 2.0.0
 */
class Under_Line_Links {

	/**
	 * Load the fix
	 *
	 * @return void
	 */
	public function load() {
		$settings                 = Settings::get_instance();
		$force_underline_on_links = $settings->get( 'fixes', 'forceUnderlineOnLinks' );
		if ( isset( $force_underline_on_links['status'] ) && $force_underline_on_links['status'] ) {
				add_filter( 'wya11y_config', array( $this, 'add_underline_config' ) );

		}
	}

	/**
	 * Add underline config
	 *
	 * @param array $config The config.
	 * @return array The config with underline config.
	 */
	public function add_underline_config( $config ) {
		$config['fixes']['forceUnderlineOnLinks']['target'] = 'a';
		return $config;
	}
}

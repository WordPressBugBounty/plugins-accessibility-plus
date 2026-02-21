<?php

/**
 * Class Lang_Dir file.
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
 * Class Lang_Dir file.
 *
 * @since 2.0.0
 */
class Lang_Dir {


	/**
	 * Load the fix
	 *
	 * @return void
	 */
	public function load() {
		$settings             = Settings::get_instance();
		$fix_add_lang_and_dir = $settings->get( 'fixes', 'langDir' );
		if ( isset( $fix_add_lang_and_dir['status'] ) && $fix_add_lang_and_dir['status'] ) {
			add_filter( 'language_attributes', array( $this, 'maybe_add_lang_and_dir' ) );
			add_filter( 'wya11y_config', array( $this, 'add_lang_dir_config' ) );
		}
	}

	/**
	 * Add lang and dir attributes to the config
	 *
	 * @param array $config The config.
	 * @return array The config with lang and dir attributes.
	 */
	public function add_lang_dir_config( $config ) {
		$status = $config['fixes']['langDir']['status'];
		if ( $status || true ) {
			$config['fixes']['langDir']['language']  = get_bloginfo( 'language' );
			$config['fixes']['langDir']['direction'] = is_rtl() ? 'rtl' : 'ltr';
		}
		return $config;
	}

	/**
	 * Add lang and dir attributes to the html tag
	 *
	 * @param string $output The html output.
	 * @return string The html output with lang and dir attributes.
	 */
	public function maybe_add_lang_and_dir( $output ) {
		$language  = get_bloginfo( 'language' );
		$direction = is_rtl() ? 'rtl' : 'ltr';

		$additional_atts = '';

		if ( strpos( $output, 'lang=' ) === false ) {
			$additional_atts = ' lang="' . esc_attr( $language ) . '"';
		}

		if ( strpos( $output, 'dir=' ) === false ) {
			$additional_atts .= ' dir="' . esc_attr( $direction ) . '"';
		}
		return $output . $additional_atts;
	}
}

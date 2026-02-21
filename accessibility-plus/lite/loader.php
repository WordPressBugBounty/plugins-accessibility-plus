<?php
/**
 * Initialize the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wya11y_define_constants' ) ) {
	/**
	 * Return parsed URL
	 *
	 * @return void
	 */
	function wya11y_define_constants() {
	}
}

wya11y_define_constants();

require_once WY_A11Y_PLUGIN_BASEPATH . 'class-autoloader.php';

$wya11y_autoloader = new \WebYes\AccessibilityPlus\Lite\Autoloader();
$wya11y_autoloader->register();

new \WebYes\AccessibilityPlus\Lite\Includes\Uninstall_Feedback();

// register_activation_hook( __FILE__, array( \WebYes\AccessibilityPlus\Lite\Includes\Activator::get_instance(), 'install' ) );

$wya11y_loader = new \WebYes\AccessibilityPlus\Lite\Includes\Base();
$wya11y_loader->run();

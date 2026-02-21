<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.webyes.com/
 * @since             1.6.6
 * @package           AccessibilityPlus
 *
 * @wordpress-plugin
 * Plugin Name:       Accessibility Tool Kit: WP Accessibility plugin for WCAG, Section 508, ADA, EAA Compliance
 * Plugin URI:        https://www.webyes.com/
 * Description:       A simple way to make your website more accessible.
 * Version:           2.0.9
 * Author:            WebYes
 * Author URI:        https://www.webyes.com/
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       accessibility-plus
 */

/*
	Copyright 2025  AccessibilityPlus

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WY_A11Y_VERSION', '2.0.9' );
define( 'WY_A11Y_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WY_A11Y_PLUGIN_BASEPATH', plugin_dir_path( __FILE__ ) );
define( 'WY_A11Y_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
// Previous version settings (deprecated from 0.9 onwards).
define( 'WY_A11Y_PLUGIN_FILENAME', __FILE__ );
define( 'WY_A11Y_DEFAULT_LANGUAGE', wya11y_set_default_language() );


function wya11y_set_default_language() {
	$default = get_option( 'WPLANG', 'en_US' );
	if ( empty( $default ) || strlen( $default ) <= 1 ) {
		$default = 'en';
	}
	return substr( $default, 0, 2 );
}

/**
 * Check if plugin is in legacy version.
 *
 * @return boolean
 */
function wya11y_is_legacy() {
	if ( empty( get_option( 'wya11y_settings', array() ) ) && ! empty( get_option( 'easywpstuff_assesplus_booster_options', '' ) ) ) {
		return true;
	} else {
		return false;
	}
}

if ( wya11y_is_legacy() ) {
	require_once WY_A11Y_PLUGIN_BASEPATH . 'legacy/loader.php';
} else {
	require_once WY_A11Y_PLUGIN_BASEPATH . 'lite/loader.php';
}

<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.webtoffee.com/
 * @since      3.0.0
 *
 * @package    WeYes
 * @subpackage WeYes/includes
 */

namespace WebYes\AccessibilityPlus\Lite\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      3.0.0
 * @package    WebYes\AccessibilityPlus
 * @subpackage WebYes\AccessibilityPlus\Lite\Includes
 * @author     WebToffee <info@webtoffee.com>
 */
class Activator {


	/**
	 * Instance of the current class
	 *
	 * @var object
	 */
	private static $instance;
	/**
	 * Update DB callbacks.
	 *
	 * @var array
	 */
	private static $db_updates = array();
	/**
	 * Return the current instance of the class
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Activate the plugin
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
	}
	/**
	 * Check the plugin version and run the updater is required.
	 *
	 * This check is done on all requests and runs if the versions do not match.
	 */
	public static function check_version() {
		if ( ! defined( 'IFRAME_REQUEST' ) && version_compare( get_option( 'wya11y_version', '1.2.4' ), WY_A11Y_VERSION, '<' ) ) {
			self::install();
		}
	}
	/**
	 * Install all the plugin
	 *
	 * @return void
	 */
	public static function install() {
		self::check_for_upgrade();
		if ( true === wya11y_first_time_install() ) {
			add_option( 'wya11y_first_time_activated_plugin', 'true' );
			// Store plugin activation timestamp for review banner (in milliseconds for JS compatibility)
			add_option( 'wya11y_plugin_activated_at', time() * 1000 );
		}
		self::maybe_update_db();
		update_option( 'wya11y_version', WY_A11Y_VERSION );
		do_action( 'wya11y_after_activate', WY_A11Y_VERSION );
		self::update_db_version();
	}

	/**
	 * Set a temporary flag during the first time installation.
	 *
	 * @return void
	 */
	public static function check_for_upgrade() {
		if ( false === get_option( 'wya11y_settings', false ) ) {
			if ( false === get_site_transient( '_wya11y_first_time_install' ) ) {
				set_site_transient( '_wya11y_first_time_install', true, 30 );
			}
		}
	}

	/**
	 * Update DB version to track changes to data structure.
	 *
	 * @param string $version Current version.
	 * @return void
	 */
	public static function update_db_version( $version = null ) {
		update_option( 'wya11y_version', is_null( $version ) ? WY_A11Y_VERSION : $version );
	}

	/**
	 * Check if any database changes is required on the latest release
	 *
	 * @return boolean
	 */
	private static function needs_db_update() {
		$current_version = get_option( 'wya11y_version', '3.0.0' ); // @since 3.0.7 introduced DB migrations
		$updates         = self::$db_updates;
		$update_versions = array_keys( $updates );
		usort( $update_versions, 'version_compare' );
		return ! is_null( $current_version ) && version_compare( $current_version, end( $update_versions ), '<' );
	}

	/**
	 * Update DB if required
	 *
	 * @return void
	 */
	public static function maybe_update_db() {
		if ( self::needs_db_update() ) {
			self::update();
		}
	}

	/**
	 * Run a update check during each release update.
	 *
	 * @return void
	 */
	private static function update() {
		$current_version = get_option( 'wya11y_version', '3.0.0' );
		foreach ( self::$db_updates as $version => $callbacks ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				foreach ( $callbacks as $callback ) {
					self::$callback();
				}
			}
		}
	}
}

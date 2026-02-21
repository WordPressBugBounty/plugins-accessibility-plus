<?php

/**
 * Class Banner file.
 *
 * @package AccessibilityPlus
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles Cookies Operation
 *
 * @class       Settings
 * @version     3.0.0
 * @package     AccessibilityPlus
 */
class Settings {


	/**
	 * Data array, with defaults.
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Instance of the current class
	 *
	 * @var object
	 */
	private static $instance;

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
	 * Constructor
	 */
	public function __construct() {
		$this->data = $this->get_defaults();
	}

	/**
	 * Get default plugin settings
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'fixes' => array(
				'langDir'                                 => array(
					'status' => false,
				),
				'removeTabIndex'                          => array(
					'status' => false,
				),
				'removeTitleIfAccessibleName'             => array(
					'status' => false,
				),
				'forceUnderlineOnLinks'                   => array(
					'status' => false,
				),
				'metaViewportScale'                       => array(
					'status' => false,
				),
				'removeTargetBlank'                       => array(
					'status' => false,
				),
				'addTargetBlankLabel'                     => array(
					'status' => false,
				),
				'enableSkipLink'                          => array(
					'status'  => false,
					'targets' => '',
				),
				'addMissingCommentFormLabels'             => array(
					'status' => false,
				),
				'addMissingSearchFormLabels'              => array(
					'status' => false,
				),
				'addFocusOutline'                         => array(
					'status' => false,
				),
				'addAccessibleNamesToInteractiveElements' => array(
					'status' => false,
				),
				'addMissingAltTextToImages'               => array(
					'status' => false,
				),
				'addMissingTitlesToFrames'                => array(
					'status' => false,
				),

			),

			// ADD THIS COMPLETE TOOLBAR SECTION:
			'toolbar' => array(
				'enabled'   => false,
				'features'  => array(
					'fontSize'            => true,
					'contrast'            => true,
					'grayscale'           => true,
					'hideOnSmallScreens'  => false,
				),
				'settings'  => array(
					'fontSize' => 14,
				),
				'placement' => array(
					'position'        => 'right',
					'verticalOffset'  => 50,
				),
			),

			// Checker configuration
			'checker' => array(
				'enabled'   => false,
				'placement' => array(
					'position'        => 'right',
				),
			),
		);
	}
	/**
	 * Get settings
	 *
	 * @param string $group Name of the group.
	 * @param string $key Name of the key.
	 * @return array
	 */
	public function get( $group = '', $key = '' ) {
		$settings = get_option( 'wya11y_settings', $this->data );
		$settings = self::sanitize( $settings, $this->data );
		if ( empty( $key ) && empty( $group ) ) {
			return $settings;
		} elseif ( ! empty( $key ) && ! empty( $group ) ) {
			$settings = isset( $settings[ $group ] ) ? $settings[ $group ] : array();
			return isset( $settings[ $key ] ) ? $settings[ $key ] : array();
		} else {
			return isset( $settings[ $group ] ) ? $settings[ $group ] : array();
		}
	}

	/**
	 * Excludes a key from sanitizing multiple times.
	 *
	 * @return array
	 */
	public static function get_excludes() {
		return array(
			'selected',
		);
	}
	/**
	 * Update settings to database.
	 *
	 * @param array $data Array of settings data.
	 * @return void
	 */
	public function update( $data, $clear = true ) {
		$settings = get_option( 'wya11y_settings', $this->data );
		if ( empty( $settings ) ) {
			$settings = $this->data;
		}
		// Sanitize new data using default structure
		$settings = self::sanitize( $data, $this->data );
		update_option( 'wya11y_settings', $settings );
		do_action( 'wya11y_after_update_settings', $clear );
	}

	/**
	 * Sanitize options
	 *
	 * @param array $settings Input settings array.
	 * @param array $defaults Default settings array.
	 * @return array
	 */
	public static function sanitize( $settings, $defaults ) {
		$result   = array();
		$excludes = self::get_excludes();
		foreach ( $defaults as $key => $data ) {
			if ( in_array( $key, $excludes, true ) ) {
				continue;
			}
			if ( ! isset( $settings[ $key ] ) ) {
				$result[ $key ] = $data;
				continue;
			}
			if ( is_array( $data ) ) {
				$result[ $key ] = self::sanitize_widget_data( $settings[ $key ], $data );
			} else {
				$result[ $key ] = self::sanitize_value( $settings[ $key ], $data );
			}
		}
		return $result;
	}

	private static function sanitize_widget_data( $input, $data ) {
		$result = array();
		foreach ( $data as $key => $value ) {
			if ( ! isset( $input[ $key ] ) ) {
				$result[ $key ] = $value;
				continue;
			}
			if ( is_array( $value ) ) {
				$result[ $key ] = self::sanitize_widget_data( $input[ $key ], $value );
			} else {
				$result[ $key ] = self::sanitize_value( $input[ $key ], $value );
			}
		}
		return $result;
	}

	private static function sanitize_value( $input, $data ) {
		$type = gettype( $data );
		switch ( $type ) {
			case 'string':
				return \sanitize_text_field( $input );
			case 'integer':
				return \absint( $input );
			case 'boolean':
				return (bool) $input;
			case 'double': // For float/decimal numbers
				return (float) $input;
			default:
				return $input;
		}
	}
}

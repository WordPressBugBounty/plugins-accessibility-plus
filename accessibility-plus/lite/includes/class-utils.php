<?php
/**
 * Utility functions class
 *
 * @link       https://www.webyes.com/
 * @since      3.0.0
 *
 * @author     WeYes <info@webyes.com>
 * @package    WebYes\AccessibilityPlus\Lite\Includes
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'wya11y_is_ajax_request' ) ) {
	/**
	 * Get localized date.
	 *
	 * @return boolean
	 */
	function wya11y_is_ajax_request() {
		if ( function_exists( 'wp_doing_ajax' ) ) {
			return wp_doing_ajax();
		} else {
			return ( defined( 'DOING_AJAX' ) && DOING_AJAX );
		}
	}
}

if ( ! function_exists( 'wya11y_is_rest_request' ) ) {

	/**
	 * Check if a request is a rest request
	 *
	 * @return boolean
	 */
	function wya11y_is_rest_request() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}
		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : false;
		if ( ! $request ) {
			return false;
		}
		$is_rest_api_request = ( false !== strpos( $request, $rest_prefix ) );

		return apply_filters( 'wya11y_is_rest_api_request', $is_rest_api_request );
	}
}

if ( ! function_exists( 'wya11y_first_time_install' ) ) {

	/**
	 * Check if the plugin is activated for the first time.
	 *
	 * @return boolean
	 */
	function wya11y_first_time_install() {
		return (bool) get_site_transient( 'wya11y_first_time_install' ) || (bool) get_option( 'wya11y_first_time_activated_plugin' );
	}
}

if ( ! function_exists( 'wya11y_is_admin_page' ) ) {

	/**
	 * Check if the plugin is activated for the first time.
	 *
	 * @return boolean
	 */
	function wya11y_is_admin_page() {
		if ( ! is_admin() ) {
			return false;
		}
		if ( function_exists( 'get_current_screen' ) && ! empty( get_current_screen() ) ) {
			$screen = get_current_screen();
			$page   = isset( $screen->id ) ? $screen->id : false;
			if ( false !== strpos( $page, 'accessibility-plus' ) ) {
				return true;
			}
			if ( ! empty( $screen->parent_base ) && false !== strpos( $screen->parent_base, 'accessibility-plus' ) ) {
				return true;
			}
		} else {
			$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return false !== strpos( $page, 'accessibility-plus' );
	}
}

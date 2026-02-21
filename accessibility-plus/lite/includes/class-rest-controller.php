<?php
/**
 * WordPress Rest controller class.
 *
 * @link       https://www.webyes.com/
 * @since      3.0.0
 *
 * @package    WebYes\AccessibilityPlus\Lite\Includes
 */

namespace WebYes\AccessibilityPlus\Lite\Includes;

use WP_REST_Controller;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Rest Controller Class
 *
 * @package WebYes\AccessibilityPlus\Lite\Includes
 * @extends  WP_REST_Controller
 * @version  3.0.0
 */
abstract class Rest_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wya11y/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Check if a given request has access to read items.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wya11y_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'accessibility-plus' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to create an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wya11y_rest_cannot_create', __( 'Sorry, you are not allowed to create resources.', 'accessibility-plus' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to read an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wya11y_rest_cannot_view', __( 'Sorry, you cannot view this resource.', 'accessibility-plus' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to update an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wya11y_rest_cannot_edit', __( 'Sorry, you are not allowed to edit this resource.', 'accessibility-plus' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check if a given request has access to delete an item.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wya11y_rest_cannot_delete', __( 'Sorry, you are not allowed to delete this resource.', 'accessibility-plus' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}
}

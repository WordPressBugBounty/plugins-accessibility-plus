<?php
/**
 * REST controller for the Auditable URLs module.
 *
 * @package AccessibilityPlus
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Auditable_Urls\Api;

use WP_REST_Server;
use WebYes\AccessibilityPlus\Lite\Includes\Rest_Controller;
use WebYes\AccessibilityPlus\Lite\Admin\Modules\Auditable_Urls\Includes\Auditable_Urls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auditable URLs REST controller.
 *
 * @class Api
 */
class Api extends Rest_Controller {

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
	protected $rest_base = 'auditable-urls';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'force' => array(
							'description'       => __( 'Bypass the cached list and rebuild from the database.', 'accessibility-plus' ),
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * GET /wya11y/v1/auditable-urls
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		$force = (bool) $request->get_param( 'force' );
		$data  = Auditable_Urls::get_instance()->get( $force );
		return rest_ensure_response( $data );
	}
}

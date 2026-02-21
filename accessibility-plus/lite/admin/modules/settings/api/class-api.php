<?php

/**
 * Class Api file.
 *
 * @package Settings
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Api;

use WP_REST_Server;
use WP_Error;
use stdClass;
use WebYes\AccessibilityPlus\Lite\Includes\Rest_Controller;
use WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Includes\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Cookies API
 *
 * @class       Api
 * @version     3.0.0
 * @package     AccessibilityPlus
 * @extends     Rest_Controller
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
	protected $rest_base = 'settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
	}
	/**
	 * Register the routes for cookies.
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
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Generic Banner endpoint - handles all banners with slug parameter
		register_rest_route(
			$this->namespace,
			'/(?P<slug>[a-zA-Z0-9-]+)-banner',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_banner_state' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_banner_state' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);
	}
	/**
	 * Get a collection of items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$object = new Settings();
		$data   = $object->get();
		return rest_ensure_response( $data );
	}
	/**
	 * Create a single cookie or cookie category.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		$data    = $this->prepare_item_for_database( $request );
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );
		return rest_ensure_response( $data );
	}


	/**
	 * Format data to provide output to API
	 *
	 * @param object $object Object of the corresponding item Cookie or Cookie_Categories.
	 * @param array  $request Request params.
	 * @return array
	 */
	public function prepare_item_for_response( $cookie_object, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $cookie_object, $request );
		$data    = $this->filter_response_by_context( $data, $context );
		return rest_ensure_response( $data );
	}

	/**
	 * Prepare a single item for create or update.
	 *
	 * @param  WP_REST_Request $request Request object.
	 * @return stdClass
	 */
	public function prepare_item_for_database( $request ) {
		$clear = $request->get_param( 'clear' );
		if ( is_null( $clear ) ) {
			$clear = true;
		} else {
			$clear = filter_var( $clear, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		}
		$object     = new Settings();
		$data       = $object->get();
		$schema     = $this->get_item_schema();
		$properties = isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
		
		if ( ! empty( $properties ) ) {
			$properties_keys = array_keys(
				array_filter(
					$properties,
					function ( $property ) {
						return isset( $property['readonly'] ) && true === $property['readonly'] ? false : true;
					}
				)
			);
			foreach ( $properties_keys as $key ) {
				// Use get_param() to properly retrieve JSON body parameters
				$value = $request->get_param( $key );
				if ( null !== $value ) {
					$data[ $key ] = $value;
				}
			}
		}
		
		$object->update( $data, $clear );
		
		return $object->get();
	}

	/**
	 * Get the query params for collections.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
			'paged'    => array(
				'description'       => __( 'Current page of the collection.', 'accessibility-plus' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
				'minimum'           => 1,
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'accessibility-plus' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'search'   => array(
				'description'       => __( 'Limit results to those matching a string.', 'accessibility-plus' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'force'    => array(
				'type'        => 'boolean',
				'description' => __( 'Force fetch data', 'accessibility-plus' ),
			),
		);
	}

	/**
	 * Get the Consent logs's schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'config',
			'type'       => 'object',
			'properties' => array(
				'fixes' => array(
					'description' => __( 'Configuration for various accessibility fixes.', 'accessibility-plus' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'langDir'                     => array(
							'type'        => 'object',
							'description' => __( 'Whether to add langDir.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array(
									'type' => 'boolean',
								),
							),
						),
						'removeTabIndex'              => array(
							'type'        => 'object',
							'description' => __( 'Remove tabindex attributes.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'removeTitleIfAccessibleName' => array(
							'type'        => 'object',
							'description' => __( 'Remove title when accessible name exists.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'forceUnderlineOnLinks'       => array(
							'type'        => 'object',
							'description' => __( 'Force underline on links.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'metaViewportScale'           => array(
							'type'        => 'object',
							'description' => __( 'Ensure viewport scale is set properly.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'removeTargetBlank'           => array(
							'type'        => 'object',
							'description' => __( 'Remove target="_blank" for security.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'addTargetBlankLabel'         => array(
							'type'        => 'object',
							'description' => __( 'Add target="_blank" label.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'enableSkipLink'              => array(
							'type'        => 'object',
							'description' => __( 'Enable skip link for accessibility.', 'accessibility-plus' ),
							'properties'  => array(
								'status'  => array( 'type' => 'boolean' ),
								'targets' => array( 'type' => 'string' ),
							),
						),
						'addMissingCommentFormLabels' => array(
							'type'        => 'object',
							'description' => __( 'Add missing labels to comment form.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'addMissingSearchFormLabels'  => array(
							'type'        => 'object',
							'description' => __( 'Add missing labels to search form.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'addFocusOutline'             => array(
							'type'        => 'object',
							'description' => __( 'Add focus outline to interactive elements.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'addAccessibleNamesToInteractiveElements' => array(
							'type'        => 'object',
							'description' => __( 'Add accessible names to interactive elements.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'addMissingAltTextToImages'   => array(
							'type'        => 'object',
							'description' => __( 'Add missing alt text to images.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
						'addMissingTitlesToFrames'    => array(
							'type'        => 'object',
							'description' => __( 'Add missing titles to iframes.', 'accessibility-plus' ),
							'properties'  => array(
								'status' => array( 'type' => 'boolean' ),
							),
						),
					),
				),

				// ADD THIS COMPLETE TOOLBAR SCHEMA:
				'toolbar' => array(
					'description' => __( 'Configuration for accessibility toolbar.', 'accessibility-plus' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'enabled' => array(
							'type'        => 'boolean',
							'description' => __( 'Enable or disable the accessibility toolbar.', 'accessibility-plus' ),
						),
						'features' => array(
							'type'        => 'object',
							'description' => __( 'Toolbar feature toggles.', 'accessibility-plus' ),
							'properties'  => array(
								'fontSize' => array(
									'type'        => 'boolean',
									'description' => __( 'Include font size button.', 'accessibility-plus' ),
								),
								'contrast' => array(
									'type'        => 'boolean',
									'description' => __( 'Include contrast button.', 'accessibility-plus' ),
								),
								'grayscale' => array(
									'type'        => 'boolean',
									'description' => __( 'Test with grayscale.', 'accessibility-plus' ),
								),
								'hideOnSmallScreens' => array(
									'type'        => 'boolean',
									'description' => __( 'Hide toolbar on small screens.', 'accessibility-plus' ),
								),
							),
						),
						'settings' => array(
							'type'        => 'object',
							'description' => __( 'Toolbar display settings.', 'accessibility-plus' ),
							'properties'  => array(
								'fontSize' => array(
									'type'        => 'integer',
									'description' => __( 'Toolbar font size.', 'accessibility-plus' ),
								),
							),
						),
						'placement' => array(
							'type'        => 'object',
							'description' => __( 'Toolbar placement settings.', 'accessibility-plus' ),
							'properties'  => array(
								'position' => array(
									'type'        => 'string',
									'description' => __( 'Toolbar position (left or right).', 'accessibility-plus' ),
								),
								'verticalOffset' => array(
									'type'        => 'integer',
									'description' => __( 'Vertical offset in pixels.', 'accessibility-plus' ),
								),
							),
						),
					),
				),

				// Checker configuration schema
				'checker' => array(
					'description' => __( 'Configuration for accessibility checker.', 'accessibility-plus' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'properties'  => array(
						'enabled' => array(
							'type'        => 'boolean',
							'description' => __( 'Enable or disable the accessibility checker.', 'accessibility-plus' ),
						),
						'placement' => array(
							'type'        => 'object',
							'description' => __( 'Checker placement settings.', 'accessibility-plus' ),
							'properties'  => array(
								'position' => array(
									'type'        => 'string',
									'description' => __( 'Checker position (left or right).', 'accessibility-plus' ),
								),
							),
						),
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get default state for a banner type.
	 *
	 * @param string $slug Banner slug (e.g., 'review', 'walkthrough', 'black-friday').
	 * @return array Default state for the banner.
	 */
	private function get_banner_default_state( $slug ) {
		$defaults = array(
			'review' => array(
				'dismissed'    => false,
				'dismissedAt'  => null,
				'remindLater'  => null,
				'ratedUs'      => false,
				'activatedAt'  => null,
			),
			'walkthrough' => array(
				'dismissed'    => false,
				'dismissedAt'  => null,
			),
			'black-friday' => array(
				'dismissed'    => false,
				'dismissedAt'  => null,
			),
		);

		return isset( $defaults[ $slug ] ) ? $defaults[ $slug ] : array(
			'dismissed'    => false,
			'dismissedAt'  => null,
		);
	}

	/**
	 * Get banner state from WordPress options (single option key for all banners).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_banner_state( $request ) {
		$slug = $request->get_param( 'slug' );

		if ( empty( $slug ) ) {
			return new WP_Error(
				'invalid_slug',
				__( 'Invalid banner slug.', 'accessibility-plus' ),
				array( 'status' => 400 )
			);
		}

		// Get all banner states from single option
		$all_banner_states = get_option( 'wya11y_banner_states', array() );

		// Get default state for this banner type
		$default_state = $this->get_banner_default_state( $slug );

		// Get state for this specific banner, or use default
		$state = isset( $all_banner_states[ $slug ] ) 
			? array_merge( $default_state, $all_banner_states[ $slug ] )
			: $default_state;

		// Special handling for review banner - get plugin activation timestamp
		if ( 'review' === $slug ) {
			$activated_at = get_option( 'wya11y_plugin_activated_at', null );
			
			// If activation time doesn't exist (existing installations), set it now
			if ( empty( $activated_at ) ) {
				$activated_at = time() * 1000; // Current time in milliseconds
				add_option( 'wya11y_plugin_activated_at', $activated_at );
			}
			
			// If activation time is not in state, add it from the option
			if ( empty( $state['activatedAt'] ) ) {
				$state['activatedAt'] = $activated_at;
				// Update the banner states
				$all_banner_states['review'] = $state;
				update_option( 'wya11y_banner_states', $all_banner_states );
			}
		}

		return rest_ensure_response( $state );
	}

	/**
	 * Update banner state in WordPress options (single option key for all banners).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_banner_state( $request ) {
		$slug = $request->get_param( 'slug' );

		if ( empty( $slug ) ) {
			return new WP_Error(
				'invalid_slug',
				__( 'Invalid banner slug.', 'accessibility-plus' ),
				array( 'status' => 400 )
			);
		}

		$params = $request->get_json_params();

		if ( empty( $params ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'Invalid banner state data.', 'accessibility-plus' ),
				array( 'status' => 400 )
			);
		}

		// Get all banner states from single option
		$all_banner_states = get_option( 'wya11y_banner_states', array() );

		// Get current state for this banner
		$current_state = isset( $all_banner_states[ $slug ] ) 
			? $all_banner_states[ $slug ] 
			: $this->get_banner_default_state( $slug );

		// Merge with new data
		$new_state = array_merge( $current_state, $params );

		// Update the specific banner state in the all banners array
		$all_banner_states[ $slug ] = $new_state;

		// Update the single option with all banner states
		update_option( 'wya11y_banner_states', $all_banner_states );

		return rest_ensure_response( $new_state );
	}
} // End the class.

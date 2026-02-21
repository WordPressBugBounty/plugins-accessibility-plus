<?php

/**
 * Class Api file.
 *
 * @package Settings
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Statement\Api;

use WP_REST_Server;
use WP_Error;
use stdClass;
use WebYes\AccessibilityPlus\Lite\Includes\Rest_Controller;
use WebYes\AccessibilityPlus\Lite\Admin\Modules\Statement\Includes\Statement;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Statement API
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
	protected $rest_base = 'statement';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10 );
	}
	/**
	 * Register the routes for statement.
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
	}
	/**
	 * Get a collection of items.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$object = new Statement();
		$data   = $object->get();
		return rest_ensure_response( $data );
	}
	/**
	 * Create a single statement.
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
	 * @param object $object Object of the corresponding item Statement.
	 * @param array  $request Request params.
	 * @return array
	 */
	public function prepare_item_for_response( $statement_object, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $statement_object, $request );
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

		$preview = $request->get_param( 'isPreview' );
		if ( is_null( $preview ) ) {
			$preview = false;
		} else {
			$preview = filter_var( $preview, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		}

		$object     = new Statement();
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
				$value        = isset( $request[ $key ] ) ? $request[ $key ] : '';
				$data[ $key ] = $value;
			}
		}
		$object->create( $data, $preview );
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
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'Accessibility Statement',
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'status'            => array(
					'type'    => 'string',
					'default' => 'draft',
				),
				'id'                => array(
					'type'    => 'string',
					'default' => '',
				),
				'url'               => array(
					'type'    => 'string',
					'format'  => 'uri',
					'default' => '',
				),
				'preview'           => array(
					'type'    => 'string',
					'format'  => 'uri',
					'default' => '',
				),
				'edit_url'          => array(
					'type'    => 'uri',
					'default' => '',
				),

				'basicInfo'         => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'companyName'     => array(
							'type'    => 'string',
							'default' => '',
						),
						'websiteUrl'      => array(
							'type'    => 'string',
							'format'  => 'uri',
							'default' => '',
						),
						'websiteName'     => array(
							'type'    => 'string',
							'default' => '',
						),
						'publicationDate' => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),

				'efforts'           => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'selectedEfforts'   => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'string' ),
							'default' => array(),
						),
						'additionalEfforts' => array(
							'type'    => 'array',
							'items'   => array( 'type' => 'string' ),
							'default' => array(),
						),
					),
				),

				'conformanceStatus' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'standard'                 => array(
							'type'    => 'string',
							'default' => '',
						),
						'customStandard'           => array(
							'type'    => 'string',
							'default' => '',
						),
						'conformanceLevel'         => array(
							'type'    => 'string',
							'default' => '',
						),
						'additionalConsiderations' => array(
							'type'    => 'string',
							'default' => '',
						),
						'knownIssues'              => array(
							'type'    => 'array',
							'default' => array(),
						),
						'unresolvedBarriers'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'contentOutsideScope'      => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),

				'technicalSpecs'    => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(

						'knownCompatibilities'   => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'browsers'              => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
								'operatingSystems'      => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
								'assistiveTechnologies' => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
							),
						),

						'knownIncompatibilities' => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'browsers'              => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
								'operatingSystems'      => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
								'assistiveTechnologies' => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
							),
						),

						'technologyReliance'     => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'selected' => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
								'other'    => array(
									'type'    => 'string',
									'default' => '',
								),
							),
						),

						'assessmentApproach'     => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'selected'     => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
								'otherMethods' => array(
									'type'    => 'array',
									'items'   => array( 'type' => 'string' ),
									'default' => array(),
								),
							),
						),

					),
				),

				'feedback'          => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'companyPhoneNumber'         => array(
							'type'    => 'string',
							'default' => '',
						),
						'companyEmailAddress'        => array(
							'type'    => 'string',
							'default' => '',
						),
						'companyVisitorAddress'      => array(
							'type'    => 'string',
							'default' => '',
						),
						'companyPostalAddress'       => array(
							'type'    => 'string',
							'default' => '',
						),
						'sameAsVisitorAddress'       => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'otherContactOptions'        => array(
							'type'    => 'string',
							'default' => '',
						),
						'typicalDurationForResponse' => array(
							'type'    => 'string',
							'default' => '',
						),
						'enforcementTeam'            => array(
							'type'    => 'array',
							'default' => array(),
						),
					),
				),

				'relatedEvidence'   => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'recentEvaluationReport' => array(
							'type'    => 'string',
							'default' => '',
						),
						'evaluationStatement'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'otherEvidence'          => array(
							'type'    => 'array',
							'default' => array(),
						),
					),
				),

			),
		);

		return $this->add_additional_fields_schema( $schema );
	}
} // End the class.

<?php

/**
 * Class Banner file.
 *
 * @package AccessibilityPlus
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Statement\Includes;

use WebYes\AccessibilityPlus\Lite\Admin\Modules\Statement\Includes\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles Cookies Operation
 *
 * @class       Statement
 * @version     3.0.0
 * @package     AccessibilityPlus
 */
class Statement {


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
			'status'            => 'draft',
			'id'                => '',
			'url'               => '',
			'preview'           => '',
			'edit_url'          => '',
			'basicInfo'         => array(
				'companyName'     => '',
				'websiteUrl'      => '',
				'websiteName'     => '',
				'publicationDate' => current_time( 'Y-m-d' ),
			),
			'efforts'           => array(
				'selectedEfforts'   => array(),
				'additionalEfforts' => array(),
			),
			'conformanceStatus' => array(
				'standard'                 => '',
				'customStandard'           => '',
				'conformanceLevel'         => '',
				'additionalConsiderations' => '',
				'knownIssues'              => array(
					array(
						'title'       => '',
						'description' => '',
					),
				),
				'unresolvedBarriers'       => '',
				'contentOutsideScope'      => '',
			),
			'technicalSpecs'    => array(
				'knownCompatibilities'   => array(
					'browsers'              => array(
						'',
					),
					'operatingSystems'      => array(
						'',
					),
					'assistiveTechnologies' => array(
						'',
					),
				),
				'knownIncompatibilities' => array(
					'browsers'              => array(
						'',
					),
					'operatingSystems'      => array(
						'',
					),
					'assistiveTechnologies' => array(
						'',
					),
				),
				'technologyReliance'     => array(
					'selected' => array(),
					'other'    => '',
				),
				'assessmentApproach'     => array(
					'selected'     => array(),
					'otherMethods' => array(),
				),
			),
			'feedback'          => array(
				'companyPhoneNumber'         => '',
				'companyEmailAddress'        => '',
				'companyVisitorAddress'      => '',
				'companyPostalAddress'       => '',
				'sameAsVisitorAddress'       => false,
				'otherContactOptions'        => '',
				'typicalDurationForResponse' => '',
				'enforcementTeam'            => array(
					array(
						'name'                       => '',
						'linkToEnforcementProcedure' => '',
						'contactDetails'             => '',
					),
				),
			),
			'relatedEvidence'   => array(
				'recentEvaluationReport' => '',
				'evaluationStatement'    => '',
				'otherEvidence'          => array(),
			),
		);
	}
	/**
	 * Get statement
	 *
	 * @param string $group Name of the group.
	 * @param string $key Name of the key.
	 * @return array
	 */
	public function get( $group = '', $key = '' ) {
		$statement = get_option( 'wya11y_statement', $this->get_defaults() );
		$statement = self::sanitize( $statement, $this->data );
		if ( empty( $key ) && empty( $group ) ) {
			return $statement;
		} elseif ( ! empty( $key ) && ! empty( $group ) ) {
			$statement = isset( $statement[ $group ] ) ? $statement[ $group ] : array();
			return isset( $statement[ $key ] ) ? $statement[ $key ] : array();
		} else {
			return isset( $statement[ $group ] ) ? $statement[ $group ] : array();
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
	 * Update statement to database.
	 *
	 * @param array $data Array of statement data.
	 * @return void
	 */
	public function create( $data, $preview = false ) {
		$statement = get_option( 'wya11y_statement', array() );
		$template  = new Generator( $data );
		$html      = $template->generate();

		// Create WordPress page with generated HTML content
		$page_id = $this->create_page( $html, $data );

		if ( empty( $statement ) ) {
			$statement = $this->data;
		}
		$statement             = self::sanitize( $data, $statement );
		$statement['url']      = get_permalink( $page_id );
		$statement['preview']  = get_preview_post_link( $page_id, false );
		$statement['edit_url'] = get_edit_post_link( $page_id, false );
		$statement['id']       = $page_id;
		// Only set status to 'published' if this is not a preview save
		if ( ! $preview ) {
			$statement['status'] = 'published';
		}

		update_option( 'wya11y_statement', $statement );
		do_action( 'wya11y_after_update_statement', true );
	}

	/**
	 * Create or update WordPress page with accessibility statement HTML content
	 *
	 * @param string $html Generated HTML content.
	 * @param array  $data Statement data.
	 * @return int|WP_Error Page ID on success, WP_Error on failure.
	 */
	private function create_page( $html, $data ) {
		$company_name = $data['basicInfo']['companyName'] ?? 'Organization';
		$page_title   = sprintf( 'Accessibility Statement for %s', $company_name );

		// Check if we have an existing statement page ID
		$existing_statement = get_option( 'wya11y_statement', array() );
		$existing_page_id   = isset( $existing_statement['id'] ) ? intval( $existing_statement['id'] ) : 0;

		// Prepare page content with proper WordPress formatting
		$page_content = wp_kses_post( $html );

		$page_data = array(
			'post_title'   => $page_title,
			'post_content' => $page_content,
			'post_status'  => 'draft',
			'post_type'    => 'page',
			'post_author'  => get_current_user_id(),
		);

		// Check if we have an existing statement page ID and if that page still exists
		if ( $existing_page_id > 0 ) {
			$existing_page = get_post( $existing_page_id );
			if ( $existing_page && 'page' === $existing_page->post_type ) {
				// Update existing statement page
				$page_data['ID'] = $existing_page_id;
				$page_id         = wp_update_post( $page_data );
			} else {
				// Existing page ID is invalid, create new page
				$page_id = wp_insert_post( $page_data );
			}
		} else {
			// No existing page ID, create new page
			$page_id = wp_insert_post( $page_data );
		}

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		return $page_id;
	}


	/**
	 * Sanitize options
	 *
	 * @param array $statement Input statement array.
	 * @param array $defaults Default statement array.
	 * @return array
	 */
	public static function sanitize( $statement, $defaults ) {
		$result   = array();
		$excludes = self::get_excludes();
		foreach ( $defaults as $key => $data ) {
			if ( in_array( $key, $excludes, true ) ) {
				continue;
			}
			if ( ! isset( $statement[ $key ] ) ) {
				$result[ $key ] = $data;
				continue;
			}
			if ( is_array( $data ) ) {
				$result[ $key ] = self::sanitize_widget_data( $statement[ $key ], $data );
			} else {
				$result[ $key ] = self::sanitize_value( $statement[ $key ], $data );
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

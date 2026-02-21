<?php
/**
 * Accessibility Statement Data Structure
 *
 * @package AccessibilityPlus
 * @since 2.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the default accessibility statement structure
 */
function wya11y_get_default_statement_structure() {
	return array(
		'basicInfo'         => array(
			'companyName'     => '',
			'websiteUrl'      => '',
			'websiteName'     => '',
			'publicationDate' => '',
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
			'knownIssues'              => array(),
			'unresolvedBarriers'       => '',
			'contentOutsideScope'      => '',
		),
		'technicalSpecs'    => array(
			'knownCompatibilities'   => array(
				'browsers'              => array(),
				'operatingSystems'      => array(),
				'assistiveTechnologies' => array(),
			),
			'knownIncompatibilities' => array(
				'browsers'              => array(),
				'operatingSystems'      => array(),
				'assistiveTechnologies' => array(),
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
			'enforcementTeam'            => array(),
		),
		'relatedEvidence'   => array(
			'recentEvaluationReport' => '',
			'evaluationStatement'    => '',
			'otherEvidence'          => array(),
		),
	);
}

/**
 * Validate accessibility statement data structure
 */
function wya11y_validate_statement_structure( $data ) {
	$default_structure = wya11y_get_default_statement_structure();

	// Ensure all required keys exist.
	foreach ( $default_structure as $key => $default_value ) {
		if ( ! isset( $data[ $key ] ) ) {
			$data[ $key ] = $default_value;
		}
	}

	return $data;
}

/**
 * Sanitize accessibility statement data
 */
function wya11y_sanitize_statement_data( $data ) {
	$sanitized = array();

	// Basic Info.
	$sanitized['basicInfo'] = array(
		'companyName'     => sanitize_text_field( $data['basicInfo']['companyName'] ?? '' ),
		'websiteUrl'      => esc_url_raw( $data['basicInfo']['websiteUrl'] ?? '' ),
		'websiteName'     => sanitize_text_field( $data['basicInfo']['websiteName'] ?? '' ),
		'publicationDate' => sanitize_text_field( $data['basicInfo']['publicationDate'] ?? '' ),
	);

	// Efforts.
	$sanitized['efforts'] = array(
		'selectedEfforts'   => array_map( 'sanitize_text_field', $data['efforts']['selectedEfforts'] ?? array() ),
		'additionalEfforts' => array_map( 'sanitize_text_field', $data['efforts']['additionalEfforts'] ?? array() ),
	);

	// Conformance Status.
	$sanitized['conformanceStatus'] = array(
		'standard'                 => sanitize_text_field( $data['conformanceStatus']['standard'] ?? '' ),
		'customStandard'           => sanitize_text_field( $data['conformanceStatus']['customStandard'] ?? '' ),
		'conformanceLevel'         => sanitize_text_field( $data['conformanceStatus']['conformanceLevel'] ?? '' ),
		'additionalConsiderations' => sanitize_textarea_field( $data['conformanceStatus']['additionalConsiderations'] ?? '' ),
		'knownIssues'              => array_map(
			function ( $issue ) {
				return array(
					'title'       => sanitize_text_field( $issue['title'] ?? '' ),
					'description' => sanitize_textarea_field( $issue['description'] ?? '' ),
				);
			},
			$data['conformanceStatus']['knownIssues'] ?? array()
		),
		'unresolvedBarriers'       => sanitize_textarea_field( $data['conformanceStatus']['unresolvedBarriers'] ?? '' ),
		'contentOutsideScope'      => sanitize_textarea_field( $data['conformanceStatus']['contentOutsideScope'] ?? '' ),
	);

	// Technical Specs.
	$sanitized['technicalSpecs'] = array(
		'knownCompatibilities'   => array(
			'browsers'              => array_map( 'sanitize_text_field', $data['technicalSpecs']['knownCompatibilities']['browsers'] ?? array() ),
			'operatingSystems'      => array_map( 'sanitize_text_field', $data['technicalSpecs']['knownCompatibilities']['operatingSystems'] ?? array() ),
			'assistiveTechnologies' => array_map( 'sanitize_text_field', $data['technicalSpecs']['knownCompatibilities']['assistiveTechnologies'] ?? array() ),
		),
		'knownIncompatibilities' => array(
			'browsers'              => array_map( 'sanitize_text_field', $data['technicalSpecs']['knownIncompatibilities']['browsers'] ?? array() ),
			'operatingSystems'      => array_map( 'sanitize_text_field', $data['technicalSpecs']['knownIncompatibilities']['operatingSystems'] ?? array() ),
			'assistiveTechnologies' => array_map( 'sanitize_text_field', $data['technicalSpecs']['knownIncompatibilities']['assistiveTechnologies'] ?? array() ),
		),
		'technologyReliance'     => array(
			'selected' => array_map( 'sanitize_text_field', $data['technicalSpecs']['technologyReliance']['selected'] ?? array() ),
			'other'    => sanitize_text_field( $data['technicalSpecs']['technologyReliance']['other'] ?? '' ),
		),
		'assessmentApproach'     => array(
			'selected'     => array_map( 'sanitize_text_field', $data['technicalSpecs']['assessmentApproach']['selected'] ?? array() ),
			'otherMethods' => array_map( 'sanitize_text_field', $data['technicalSpecs']['assessmentApproach']['otherMethods'] ?? array() ),
		),
	);

	// Feedback.
	$sanitized['feedback'] = array(
		'companyPhoneNumber'         => sanitize_text_field( $data['feedback']['companyPhoneNumber'] ?? '' ),
		'companyEmailAddress'        => sanitize_email( $data['feedback']['companyEmailAddress'] ?? '' ),
		'companyVisitorAddress'      => sanitize_textarea_field( $data['feedback']['companyVisitorAddress'] ?? '' ),
		'companyPostalAddress'       => sanitize_textarea_field( $data['feedback']['companyPostalAddress'] ?? '' ),
		'sameAsVisitorAddress'       => (bool) ( $data['feedback']['sameAsVisitorAddress'] ?? false ),
		'otherContactOptions'        => sanitize_textarea_field( $data['feedback']['otherContactOptions'] ?? '' ),
		'typicalDurationForResponse' => sanitize_text_field( $data['feedback']['typicalDurationForResponse'] ?? '' ),
		'enforcementTeam'            => array_map(
			function ( $team ) {
				return array(
					'name'                       => sanitize_text_field( $team['name'] ?? '' ),
					'linkToEnforcementProcedure' => esc_url_raw( $team['linkToEnforcementProcedure'] ?? '' ),
					'contactDetails'             => sanitize_textarea_field( $team['contactDetails'] ?? '' ),
				);
			},
			$data['feedback']['enforcementTeam'] ?? array()
		),
	);

	// Related Evidence.
	$sanitized['relatedEvidence'] = array(
		'recentEvaluationReport' => esc_url_raw( $data['relatedEvidence']['recentEvaluationReport'] ?? '' ),
		'evaluationStatement'    => sanitize_textarea_field( $data['relatedEvidence']['evaluationStatement'] ?? '' ),
		'otherEvidence'          => array_map( 'sanitize_text_field', $data['relatedEvidence']['otherEvidence'] ?? array() ),
	);

	return $sanitized;
}

/**
 * Get stored accessibility statement data
 */
function wya11y_get_stored_statement_data() {
	$option_name = 'wya11y_statement_data';
	$stored_data = get_option( $option_name, array() );

	if ( empty( $stored_data ) ) {
		$stored_data = wya11y_get_default_statement_structure();
		update_option( $option_name, $stored_data );
	}

	return wya11y_validate_statement_structure( $stored_data );
}

/**
 * Save accessibility statement data
 */
function wya11y_save_statement_data( $data ) {
	$option_name    = 'wya11y_statement_data';
	$sanitized_data = wya11y_sanitize_statement_data( $data );

	return update_option( $option_name, $sanitized_data );
}

/**
 * Generate HTML content from accessibility statement data
 */
function wya11y_generate_statement_html( $data ) {
	$data = wya11y_validate_statement_structure( $data );

	$html = '<div class="accessibility-statement">';

	// Header.
	$html .= '<h1>Accessibility Statement</h1>';
	$html .= '<p><strong>Last Updated:</strong> ' . esc_html( $data['basicInfo']['publicationDate'] ?: gmdate( 'F j, Y' ) ) . '</p>';

	// Basic Information.
	$html .= '<h2>Basic Information</h2>';
	$html .= '<p><strong>Company:</strong> ' . esc_html( $data['basicInfo']['companyName'] ?: 'N/A' ) . '</p>';
	$html .= '<p><strong>Website:</strong> ' . esc_html( $data['basicInfo']['websiteName'] ?: 'N/A' ) . '</p>';
	$html .= '<p><strong>URL:</strong> ' . esc_html( $data['basicInfo']['websiteUrl'] ?: 'N/A' ) . '</p>';

	// Accessibility Efforts.
	$html .= '<h2>Accessibility Efforts</h2>';
	if ( ! empty( $data['efforts']['additionalEfforts'] ) ) {
		$html .= '<ul>';
		foreach ( $data['efforts']['additionalEfforts'] as $effort ) {
			$html .= '<li>' . esc_html( $effort ) . '</li>';
		}
		$html .= '</ul>';
	} else {
		$html .= '<p>No additional efforts specified.</p>';
	}

	// Conformance Status.
	$html .= '<h2>Conformance Status</h2>';
	$html .= '<p><strong>Standard:</strong> ' . esc_html( $data['conformanceStatus']['standard'] ?: 'N/A' ) . '</p>';
	$html .= '<p><strong>Level:</strong> ' . esc_html( $data['conformanceStatus']['conformanceLevel'] ?: 'N/A' ) . '</p>';
	$html .= '<p><strong>Additional Considerations:</strong> ' . esc_html( $data['conformanceStatus']['additionalConsiderations'] ?: 'None specified.' ) . '</p>';

	// Known Issues.
	$html .= '<h2>Known Issues</h2>';
	if ( ! empty( $data['conformanceStatus']['knownIssues'] ) ) {
		$html .= '<ul>';
		foreach ( $data['conformanceStatus']['knownIssues'] as $issue ) {
			$html .= '<li><strong>' . esc_html( $issue['title'] ) . ':</strong> ' . esc_html( $issue['description'] ) . '</li>';
		}
		$html .= '</ul>';
	} else {
		$html .= '<p>No known issues specified.</p>';
	}

	// Technical Specifications.
	$html .= '<h2>Technical Specifications</h2>';
	$html .= '<p><strong>Assessment Approach:</strong> ' . esc_html( implode( ', ', $data['technicalSpecs']['assessmentApproach']['selected'] ) ?: 'N/A' ) . '</p>';

	// Feedback and Contact.
	$html .= '<h2>Feedback and Contact</h2>';
	$html .= '<p><strong>Phone:</strong> ' . esc_html( $data['feedback']['companyPhoneNumber'] ?: 'N/A' ) . '</p>';
	$html .= '<p><strong>Email:</strong> ' . esc_html( $data['feedback']['companyEmailAddress'] ?: 'N/A' ) . '</p>';
	$html .= '<p><strong>Address:</strong> ' . esc_html( $data['feedback']['companyVisitorAddress'] ?: 'N/A' ) . '</p>';
	$html .= '<p><strong>Response Time:</strong> ' . esc_html( $data['feedback']['typicalDurationForResponse'] ?: 'N/A' ) . '</p>';

	// Related Evidence.
	$html .= '<h2>Related Evidence</h2>';
	$html .= '<p>' . esc_html( $data['relatedEvidence']['evaluationStatement'] ?: 'No evaluation statement provided.' ) . '</p>';

	$html .= '</div>';

	return $html;
}

<?php

/**
 * Accessibility Statement Generator
 *
 * PHP template generator that produces the same HTML output as the React component
 * for generating accessibility statements.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Class name matches React component naming convention for consistency
class AccessibilityStatementGenerator {

	private $statement;

	public function __construct( $statement ) {
		$this->statement = $statement;
	}

	/**
	 * Generate the complete accessibility statement HTML
	 */
	public function generate() {
		$html  = $this->get_css();
		$html .= '<div class="accessibility-statement">';
		$html .= $this->generate_basic_info();
		$html .= $this->generate_efforts();
		$html .= $this->generate_conformance_status();
		$html .= $this->generate_additional_considerations();
		$html .= $this->generate_known_issues();
		$html .= $this->generate_unresolved_barriers();
		$html .= $this->generate_content_outside_scope();
		$html .= $this->generate_technical_specifications();
		$html .= $this->generate_known_incompatibilities();
		$html .= $this->generate_assessment_approach();
		$html .= $this->generate_related_evidence();
		$html .= $this->generate_feedback();
		$html .= $this->generate_footer();
		$html .= '</div>';

		return $html;
	}

	/**
	 * Format date string
	 */
	private function format_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return '[Month Day, Year]';
		}

		$date = new DateTime( $date_string );
		return $date->format( 'F j, Y' );
	}

	/**
	 * Get standard text
	 */
	private function get_standard_text() {
		$standard = $this->statement['conformanceStatus']['standard'] ?? '';

		if ( 'other' === $standard ) {
			return $this->statement['conformanceStatus']['customStandard'] ?? '_______';
		}

		$standardMap = array(
			'wcag22-aa' => 'WCAG 2.2 AA',
			'wcag21-aa' => 'WCAG 2.1 AA',
			'wcag20-aa' => 'WCAG 2.0 AA',
		);

		return $standardMap[ $standard ] ?? '[WCAG 2.2 AA/WCAG 2.1 AA/ WCAG 2.0 AA]';
	}

	/**
	 * Get conformance text
	 */
	private function get_conformance_text() {
		$level = $this->statement['conformanceStatus']['conformanceLevel'] ?? '';

		$levelMap = array(
			'fully-conformant'     => 'fully conformant',
			'partially-conformant' => 'partially conformant',
			'non-conformant'       => 'non conformant',
			'not-assessed'         => 'not assessed',
		);

		return $levelMap[ $level ] ?? '_______';
	}

	/**
	 * Render company name with optional link
	 */
	private function render_company_name( $link = true ) {
		$company_name = $this->statement['basicInfo']['companyName'] ?? '';
		$website_url  = $this->statement['basicInfo']['websiteUrl'] ?? '';

		if ( $link && $company_name && $website_url ) {
			return sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" style="color: #1976d2; text-decoration: underline;">%s</a>',
				htmlspecialchars( $website_url ),
				htmlspecialchars( $company_name )
			);
		}

		return htmlspecialchars( $company_name ? $company_name : '[Organization Name]' );
	}

	/**
	 * Generate basic information section
	 */
	private function generate_basic_info() {
		$company_name     = $this->render_company_name( false );
		$website_url      = $this->statement['basicInfo']['websiteUrl'] ?? '';
		$publication_date = $this->format_date( $this->statement['basicInfo']['publicationDate'] ?? '' );

		$html  = '<div id="preview-basic">';
		$html .= sprintf( '<h1 style="margin-bottom: 0;" tabindex="-1" id="main-heading">Accessibility Statement for %s</h1>', $company_name );
		$html .= '<hr style="margin: 16px 0;" aria-hidden="true" />';

		$html .= '<div>';
		$html .= '<p>';
		$html .= 'Website: ';

		if ( $website_url ) {
			$html .= sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" style="color: #1976d2; text-decoration: underline;" aria-label="Visit website: %s (opens in a new window)">%s</a>',
				htmlspecialchars( $website_url ),
				htmlspecialchars( $website_url ),
				htmlspecialchars( $website_url )
			);
		} else {
			$html .= '[website url]';
		}

		$html .= '<br />';
		$html .= sprintf( 'Last Revised: %s', $publication_date );
		$html .= '</p>';
		$html .= '</div>';

		$html .= '<p>';
		$html .= sprintf( 'At %s, we are committed to ensuring that our website and all digital services are accessible to everyone, including people with disabilities. This document describes our accessibility practices, the standards we follow, and how you can provide feedback.', $this->render_company_name() );
		$html .= '</p>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate efforts section
	 */
	private function generate_efforts() {
		$selected_efforts   = $this->statement['efforts']['selectedEfforts'] ?? array();
		$additional_efforts = $this->statement['efforts']['additionalEfforts'] ?? array();

		// Filter out empty additional efforts
		$additional_efforts = array_filter(
			$additional_efforts,
			function ( $effort ) {
				return ! empty( trim( $effort ) );
			}
		);

		if ( empty( $selected_efforts ) && empty( $additional_efforts ) ) {
			return '';
		}

		$html  = '<div id="preview-efforts" style="margin-bottom: 24px;">';
		$html .= sprintf( '<p>%s takes the following measures to ensure accessibility:</p>', $this->render_company_name() );

		if ( ! empty( $selected_efforts ) ) {
			$html .= '<ul style="list-style: disc; padding-left: 20px; margin: 0;">';
			foreach ( $selected_efforts as $effort ) {
				$html .= sprintf( '<li>%s</li>', htmlspecialchars( $effort ) );
			}
			$html .= '</ul>';
		}

		if ( ! empty( $additional_efforts ) ) {
			$html .= '<ul>';
			foreach ( $additional_efforts as $effort ) {
				$html .= sprintf( '<li>%s</li>', htmlspecialchars( $effort ) );
			}
			$html .= '</ul>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate conformance status section
	 */
	private function generate_conformance_status() {
		$standard          = $this->statement['conformanceStatus']['standard'] ?? '';
		$conformance_level = $this->statement['conformanceStatus']['conformanceLevel'] ?? '';
		$website_name      = $this->statement['basicInfo']['websiteName'] ?? '[Website Name]';

		if ( empty( $standard ) && empty( $conformance_level ) ) {
			return '';
		}

		$html  = '<div id="preview-conformance" style="margin-bottom: 24px;">';
		$html .= '<h2 id="conformance-status-heading">Conformance status</h2>';
		$html .= '<p style="margin-bottom: 12px;">';
		$html .= 'The Web Content Accessibility Guidelines (WCAG) is a set of standards that must be adhered to in order to ensure accessibility for people with disabilities. There are 3 defined levels of conformance: A, AA and AAA.';
		$html .= '</p>';

		$html .= '<p>';
		$html .= sprintf(
			'<span style="font-weight: 500;">%s</span> is <span style="font-weight: 500;">%s</span> with <span style="font-weight: 500;">%s</span>. This means that there are some parts of the content that do not conform to the accessibility standard.',
			htmlspecialchars( $website_name ),
			$this->get_conformance_text(),
			$this->get_standard_text()
		);
		$html .= '</p>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate additional considerations section
	 */
	private function generate_additional_considerations() {
		$considerations = $this->statement['conformanceStatus']['additionalConsiderations'] ?? '';

		if ( empty( $considerations ) ) {
			return '';
		}

		$html  = '<div style="margin-bottom: 24px;">';
		$html .= '<h3>Additional accessibility considerations</h3>';
		$html .= sprintf( '<p>%s</p>', htmlspecialchars( $considerations ) );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate known issues section
	 */
	private function generate_known_issues() {
		$known_issues = $this->statement['conformanceStatus']['knownIssues'] ?? array();

		// Filter out empty issues
		$known_issues = array_filter(
			$known_issues,
			function ( $issue ) {
				return ! empty( $issue['title'] ) || ! empty( $issue['description'] );
			}
		);

		if ( empty( $known_issues ) ) {
			return '';
		}

		$html  = '<div style="margin-bottom: 24px;">';
		$html .= '<h3>Known accessibility limitation</h3>';
		$html .= '<h6>Features or content that do not meet accessibility standards:</h6>';
		$html .= '<ol>';

		foreach ( $known_issues as $issue ) {
			$html .= '<li style="margin-bottom: 8px;">';
			$html .= sprintf(
				'<strong>%s:</strong><br /> %s',
				htmlspecialchars( $issue['title'] ? $issue['title'] : 'Issue' ),
				htmlspecialchars( $issue['description'] ? $issue['description'] : '[Description]' )
			);
			$html .= '</li>';
		}

		$html .= '</ol>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate unresolved barriers section
	 */
	private function generate_unresolved_barriers() {
		$barriers = $this->statement['conformanceStatus']['unresolvedBarriers'] ?? '';

		if ( empty( $barriers ) ) {
			return '';
		}

		$html  = '<div>';
		$html .= '<h6>Unresolved accessibility barriers due to budget or technical limitations (Disproportionate burden):</h6>';
		$html .= sprintf( '<p>%s</p>', htmlspecialchars( $barriers ) );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate content outside scope section
	 */
	private function generate_content_outside_scope() {
		$content = $this->statement['conformanceStatus']['contentOutsideScope'] ?? '';

		if ( empty( $content ) ) {
			return '';
		}

		$html  = '<div>';
		$html .= '<h6>Content outside the scope of accessibility legislation:</h6>';
		$html .= sprintf( '<p>%s</p>', htmlspecialchars( $content ) );
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate technical specifications section
	 */
	private function generate_technical_specifications() {
		$tech_specs   = $this->statement['technicalSpecs'] ?? array();
		$website_name = $this->statement['basicInfo']['websiteName'] ?? '[Website Name]';

		$has_compatibilities     = $this->has_any_compatibilities( $tech_specs['knownCompatibilities'] ?? array() );
		$has_technology_reliance = ! empty( $tech_specs['technologyReliance']['selected'] ?? array() );

		if ( ! $has_compatibilities && ! $has_technology_reliance ) {
			return '';
		}

		$html  = '<div id="preview-technical" style="margin-bottom: 24px;">';
		$html .= '<hr style="margin: 32px 0;" aria-hidden="true" />';
		$html .= '<div style="margin-bottom: 24px;">';
		$html .= '<h2>Technical specifications</h2>';

		$html .= sprintf(
			'<p style="margin-bottom: 12px;">Accessibility of %s depends on the following technologies:</p>',
			htmlspecialchars( $website_name )
		);

		$html .= '<ul style="list-style: disc; padding-left: 20px; margin-bottom: 16px;">';

		$selected_tech = $tech_specs['technologyReliance']['selected'] ?? array();
		if ( ! empty( $selected_tech ) ) {
			foreach ( $selected_tech as $tech ) {
				$display_tech = ( 'Other' === $tech ) ?
					( $tech_specs['technologyReliance']['other'] ?? 'Other' ) :
					$tech;
				$html        .= sprintf( '<li>%s</li>', htmlspecialchars( $display_tech ) );
			}
		} else {
			$html .= '<li>[technologies]</li>';
		}

		$html .= '</ul>';

		$html .= '<h3>Compatibility with user environment</h3>';

		// Browsers
		$browsers = $tech_specs['knownCompatibilities']['browsers'] ?? array();
		$browsers = array_filter(
			$browsers,
			function ( $browser ) {
				return ! empty( trim( $browser ) );
			}
		);

		if ( ! empty( $browsers ) ) {
			$html .= '<div style="margin-bottom: 12px;">';
			$html .= '<p style="margin-bottom: 6px;">This site is designed to be compatible with the following browsers:</p>';
			$html .= '<ul style="font-size: 15px; padding-left: 20px; margin: 0; list-style: disc;">';
			foreach ( $browsers as $browser ) {
				$html .= sprintf( '<li style="margin: 0;">%s</li>', htmlspecialchars( $browser ) );
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		// Operating Systems
		$operating_systems = $tech_specs['knownCompatibilities']['operatingSystems'] ?? array();
		$operating_systems = array_filter(
			$operating_systems,
			function ( $os ) {
				return ! empty( trim( $os ) );
			}
		);

		if ( ! empty( $operating_systems ) ) {
			$html .= '<div style="margin-bottom: 12px;">';
			$html .= '<p style="margin-bottom: 6px;">This site is designed to be compatible with the following operating systems:</p>';
			$html .= '<ul style="font-size: 15px; padding-left: 20px; margin: 0; list-style: disc;">';
			foreach ( $operating_systems as $os ) {
				$html .= sprintf( '<li style="margin: 0;">%s</li>', htmlspecialchars( $os ) );
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		// Assistive Technologies
		$assistive_tech = $tech_specs['knownCompatibilities']['assistiveTechnologies'] ?? array();
		$assistive_tech = array_filter(
			$assistive_tech,
			function ( $at ) {
				return ! empty( trim( $at ) );
			}
		);

		if ( ! empty( $assistive_tech ) ) {
			$html .= '<div style="margin-bottom: 12px;">';
			$html .= '<p style="margin-bottom: 6px;">This site is designed to be compatible with the following assistive technologies:</p>';
			$html .= '<ul style="font-size: 15px; padding-left: 20px; margin: 0; list-style: disc;">';
			foreach ( $assistive_tech as $at ) {
				$html .= sprintf( '<li style="margin: 0;">%s</li>', htmlspecialchars( $at ) );
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Check if any compatibilities exist
	 */
	private function has_any_compatibilities( $compatibilities ) {
		$browsers = array_filter(
			$compatibilities['browsers'] ?? array(),
			function ( $browser ) {
				return ! empty( trim( $browser ) );
			}
		);

		$operating_systems = array_filter(
			$compatibilities['operatingSystems'] ?? array(),
			function ( $os ) {
				return ! empty( trim( $os ) );
			}
		);

		$assistive_technologies = array_filter(
			$compatibilities['assistiveTechnologies'] ?? array(),
			function ( $at ) {
				return ! empty( trim( $at ) );
			}
		);

		return ! empty( $browsers ) || ! empty( $operating_systems ) || ! empty( $assistive_technologies );
	}

	/**
	 * Generate known incompatibilities section
	 */
	private function generate_known_incompatibilities() {
		$incompatibilities = $this->statement['technicalSpecs']['knownIncompatibilities'] ?? array();

		$has_incompatibilities = $this->has_any_compatibilities( $incompatibilities );

		if ( ! $has_incompatibilities ) {
			return '';
		}

		$html  = '<div style="margin-bottom: 24px;">';
		$html .= '<h3 style="margin-bottom: 12px;">Known Incompatibilities</h3>';

		// Browsers
		$browsers = array_filter(
			$incompatibilities['browsers'] ?? array(),
			function ( $browser ) {
				return ! empty( trim( $browser ) );
			}
		);

		if ( ! empty( $browsers ) ) {
			$html .= '<div style="margin-top: 8px;">';
			$html .= '<div style="margin-bottom: 4px;">This site is not compatible with the following browsers:</div>';
			$html .= '<ul style="font-size: 15px; padding-left: 20px; margin: 0; list-style: disc;">';
			foreach ( $browsers as $browser ) {
				$html .= sprintf( '<li style="margin-bottom: 8px;">%s</li>', htmlspecialchars( $browser ) );
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		// Operating Systems
		$operating_systems = array_filter(
			$incompatibilities['operatingSystems'] ?? array(),
			function ( $os ) {
				return ! empty( trim( $os ) );
			}
		);

		if ( ! empty( $operating_systems ) ) {
			$html .= '<div style="margin-top: 8px;">';
			$html .= '<div style="margin-bottom: 4px;">This site is not compatible with the following operating systems:</div>';
			$html .= '<ul style="font-size: 15px; padding-left: 20px; margin: 0; list-style: disc;">';
			foreach ( $operating_systems as $os ) {
				$html .= sprintf( '<li style="margin-bottom: 8px;">%s</li>', htmlspecialchars( $os ) );
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		// Assistive Technologies
		$assistive_tech = array_filter(
			$incompatibilities['assistiveTechnologies'] ?? array(),
			function ( $at ) {
				return ! empty( trim( $at ) );
			}
		);

		if ( ! empty( $assistive_tech ) ) {
			$html .= '<div style="margin-top: 8px;">';
			$html .= '<div style="margin-bottom: 4px;">This site is not compatible with the following assistive technologies:</div>';
			$html .= '<ul style="font-size: 15px; padding-left: 20px; margin: 0; list-style: disc;">';
			foreach ( $assistive_tech as $at ) {
				$html .= sprintf( '<li style="margin-bottom: 8px;">%s</li>', htmlspecialchars( $at ) );
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate assessment approach section
	 */
	private function generate_assessment_approach() {
		$assessment_approach = $this->statement['technicalSpecs']['assessmentApproach'] ?? array();
		$website_name        = $this->statement['basicInfo']['websiteName'] ?? '[Website Name]';

		$selected_methods = $assessment_approach['selected'] ?? array();
		$other_methods    = array_filter(
			$assessment_approach['otherMethods'] ?? array(),
			function ( $method ) {
				return ! empty( trim( $method ) );
			}
		);

		if ( empty( $selected_methods ) && empty( $other_methods ) ) {
			return '';
		}

		$html  = '<div style="margin-bottom: 24px;">';
		$html .= '<h3 style="margin-bottom: 12px;">Assessment methods</h3>';
		$html .= sprintf(
			'<p><b>%s</b> assessed the accessibility of this site using the following method(s):</p>',
			htmlspecialchars( $website_name )
		);

		$html .= '<ul style="list-style: disc; padding-left: 20px;">';

		foreach ( $selected_methods as $method ) {
			if ( 'Other methods' !== $method ) {
				$html .= '<li>';
				if ( 'Self-evaluation' === $method ) {
					$html .= '<strong>Self-evaluation:</strong> The content was evaluated by your own organization or the developer of the content';
				} elseif ( 'External evaluation' === $method ) {
					$html .= '<strong>External evaluation:</strong> The content was evaluated by an external entity not involved in the design and development process';
				}
				$html .= '</li>';
			}
		}

		foreach ( $other_methods as $method ) {
			$html .= sprintf( '<li>%s</li>', htmlspecialchars( $method ) );
		}

		$html .= '</ul>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate related evidence section
	 */
	private function generate_related_evidence() {
		$related_evidence = $this->statement['relatedEvidence'] ?? array();
		$html             = '';

		// Recent Evaluation Report
		$report_url = $related_evidence['recentEvaluationReport'] ?? '';
		if ( ! empty( $report_url ) ) {
			$html .= '<div id="preview-evidence" style="margin-bottom: 24px;">';
			$html .= '<h4 style="font-size: 20px; font-weight: 500; margin-bottom: 12px;">Accessibility evaluation report</h4>';
			$html .= '<p style="margin-bottom: 12px;">You can access our accessibility evaluation report at:</p>';
			$html .= '<p style="margin-bottom: 12px;">';
			$html .= sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				htmlspecialchars( $report_url ),
				htmlspecialchars( $report_url )
			);
			$html .= '</p>';
			$html .= '</div>';
		}

		// Evaluation Statement
		$statement_url = $related_evidence['evaluationStatement'] ?? '';
		if ( ! empty( $statement_url ) ) {
			$html .= '<div id="preview-evidence" style="margin-bottom: 24px;">';
			$html .= '<h4 style="font-size: 20px; font-weight: 500; margin-bottom: 12px;">Accessibility evaluation statement</h4>';
			$html .= '<p style="margin-bottom: 12px;">You can access our accessibility evaluation statement at:</p>';
			$html .= '<p style="margin-bottom: 12px;">';
			$html .= sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				htmlspecialchars( $statement_url ),
				htmlspecialchars( $statement_url )
			);
			$html .= '</p>';
			$html .= '</div>';
		}

		// Other Evidence
		$other_evidence = array_filter(
			$related_evidence['otherEvidence'] ?? array(),
			function ( $evidence ) {
				return ! empty( trim( $evidence ) );
			}
		);

		if ( ! empty( $other_evidence ) ) {
			$html .= '<div id="preview-evidence" style="margin-bottom: 24px;">';
			$html .= '<h4 style="font-size: 20px; font-weight: 500; margin-bottom: 12px;">Other evidence</h4>';
			$html .= '<p style="margin-bottom: 12px;">You can access our other evidence at:</p>';
			$html .= '<ul>';
			foreach ( $other_evidence as $evidence ) {
				$html .= sprintf(
					'<li><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></li>',
					htmlspecialchars( $evidence ),
					htmlspecialchars( $evidence )
				);
			}
			$html .= '</ul>';
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * Generate feedback section
	 */
	private function generate_feedback() {
		$feedback     = $this->statement['feedback'] ?? array();
		$website_name = $this->statement['basicInfo']['websiteName'] ?? '';

		$has_contact_info = ! empty( $feedback['companyPhoneNumber'] ) ||
						! empty( $feedback['companyEmailAddress'] ) ||
						! empty( $feedback['companyVisitorAddress'] ) ||
						! empty( $feedback['companyPostalAddress'] );

		$html = '<div id="preview-feedback">';

		if ( $has_contact_info ) {
			$html .= '<hr style="margin: 32px 0;" aria-hidden="true" />';
			$html .= '<h2 id="feedback-process-heading">Feedback process</h2>';
			$html .= sprintf(
				'<p>We welcome your feedback on the accessibility of %s Please contact us via one of the following methods:</p>',
				htmlspecialchars( $website_name )
			);

			$html .= '<p style="margin-bottom: 8px;">';

			// Phone
			if ( ! empty( $feedback['companyPhoneNumber'] ) ) {
				$html .= sprintf(
					'<strong>Phone:</strong> <a href="tel:%s" style="color: #1976d2;" aria-label="Call %s">%s</a><br />',
					htmlspecialchars( $feedback['companyPhoneNumber'] ),
					htmlspecialchars( $feedback['companyPhoneNumber'] ),
					htmlspecialchars( $feedback['companyPhoneNumber'] )
				);
			} else {
				$html .= '<strong>Phone:</strong> <span>[phone number]</span><br />';
			}

			// Email
			if ( ! empty( $feedback['companyEmailAddress'] ) ) {
				$html .= sprintf(
					'<strong>E-mail:</strong> <a href="mailto:%s" style="color: #1976d2;" aria-label="Email %s">%s</a><br />',
					htmlspecialchars( $feedback['companyEmailAddress'] ),
					htmlspecialchars( $feedback['companyEmailAddress'] ),
					htmlspecialchars( $feedback['companyEmailAddress'] )
				);
			}

			// Visitor Address
			if ( ! empty( $feedback['companyVisitorAddress'] ) ) {
				$html .= sprintf(
					'<strong>Visitor Address:</strong> %s<br />',
					htmlspecialchars( $feedback['companyVisitorAddress'] )
				);
			}

			// Postal Address
			if ( ! empty( $feedback['companyPostalAddress'] ) ) {
				$html .= sprintf(
					'<strong>Postal Address:</strong> %s<br />',
					htmlspecialchars( $feedback['companyPostalAddress'] )
				);
			}

			// Other Contact Options
			if ( ! empty( $feedback['otherContactOptions'] ) ) {
				$html .= sprintf(
					'<strong>Other contact options:</strong> %s<br />',
					htmlspecialchars( $feedback['otherContactOptions'] )
				);
			}

			$html .= '</p>';
		}

		// Response Duration
		$response_duration = $feedback['typicalDurationForResponse'] ?? '';
		if ( ! empty( $response_duration ) ) {
			$html .= sprintf(
				'<p>We aim to respond to feedback within <strong>%s</strong>.</p>',
				htmlspecialchars( $response_duration )
			);
		}

		// Enforcement Team
		$enforcement_team = $feedback['enforcementTeam'] ?? array();
		$enforcement_team = array_filter(
			$enforcement_team,
			function ( $team ) {
				return ! empty( $team['name'] ) || ! empty( $team['linkToEnforcementProcedure'] ) || ! empty( $team['contactDetails'] );
			}
		);

		if ( ! empty( $enforcement_team ) ) {
			$html .= '<h3 id="formal-complaints-heading">Formal complaints</h3>';
			$html .= '<p>If you haven\'t receive a timely or adequate response to the accessibility issue. Please raise a formal complaint to the given enforcement team:</p>';

			foreach ( $enforcement_team as $index => $team ) {
				$html .= sprintf(
					'<div style="margin-bottom: 24px;"><h5 style="margin-bottom: 15px; font-weight: 600;">Enforcement team %d</h5>',
					$index + 1
				);

				if ( ! empty( $team['name'] ) ) {
					$html .= sprintf(
						'<div><strong>Name:</strong> %s</div>',
						htmlspecialchars( $team['name'] )
					);
				}

				if ( ! empty( $team['linkToEnforcementProcedure'] ) ) {
					$html .= sprintf(
						'<div><strong>Link to the enforcement procedure or authority:</strong> <a href="%s" target="_blank" rel="noopener noreferrer" style="color: #1976d2;" aria-label="Enforcement procedure link (opens in a new window)">%s</a></div>',
						htmlspecialchars( $team['linkToEnforcementProcedure'] ),
						htmlspecialchars( $team['linkToEnforcementProcedure'] )
					);
				}

				if ( ! empty( $team['contactDetails'] ) ) {
					$html .= '<div><strong>Contact details:</strong>';
					$html .= sprintf(
						'<pre style="font-family: inherit; white-space: pre-wrap; margin: 0;">%s</pre>',
						htmlspecialchars( $team['contactDetails'] )
					);
					$html .= '</div>';
				}

				$html .= '</div>';
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate footer
	 */
	private function generate_footer() {
		$html  = '<div style="font-weight: 400; font-size: 16px; text-align: center; margin-top: 32px;">';
		$html .= '<span style="color: #1F2937; opacity: 0.5;">Generated using the </span>';
		$html .= '<a href="https://www.webyes.com/accessibility-statement-generator/" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;">WebYes Web Accessibility Statement Generator.</a>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Get CSS styles
	 */
	private function get_css() {
		return '<style>
            .accessibility-statement {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .accessibility-statement h1 {
                font-size: 2rem;
                font-weight: 600;
                color: #1a1a1a;
                margin-bottom: 1rem;
            }
            
            .accessibility-statement h2 {
                font-size: 1.5rem;
                font-weight: 600;
                color: #1a1a1a;
                margin-top: 2rem;
                margin-bottom: 1rem;
            }
            
            .accessibility-statement h3 {
                font-size: 1.25rem;
                font-weight: 600;
                color: #1a1a1a;
                margin-top: 1.5rem;
                margin-bottom: 0.75rem;
            }
            
            .accessibility-statement h4 {
                font-size: 1.125rem;
                font-weight: 500;
                color: #1a1a1a;
                margin-top: 1.25rem;
                margin-bottom: 0.5rem;
            }
            
            .accessibility-statement h5 {
                font-size: 1rem;
                font-weight: 600;
                color: #1a1a1a;
                margin-top: 1rem;
                margin-bottom: 0.5rem;
            }
            
            .accessibility-statement h6 {
                font-size: 0.875rem;
                font-weight: 600;
                color: #1a1a1a;
                margin-top: 0.75rem;
                margin-bottom: 0.5rem;
            }
            
            .accessibility-statement p {
                margin-bottom: 1rem;
            }
            
            .accessibility-statement ul, .accessibility-statement ol {
                margin-bottom: 1rem;
            }
            
            .accessibility-statement li {
                margin-bottom: 0.25rem;
            }
            
            .accessibility-statement a {
                color: #1976d2;
                text-decoration: underline;
            }
            
            .accessibility-statement a:hover {
                color: #1565c0;
            }
            
            .accessibility-statement hr {
                border: none;
                border-top: 1px solid #e5e7eb;
                margin: 2rem 0;
            }
            
            .accessibility-statement pre {
                background-color: #f9fafb;
                padding: 1rem;
                border-radius: 0.375rem;
                overflow-x: auto;
                font-size: 0.875rem;
            }
        </style>';
	}
}

// Usage example:
/*
$statement = [
	'basicInfo' => [
		'companyName' => 'Example Company',
		'websiteUrl' => 'https://example.com',
		'websiteName' => 'Example Website',
		'publicationDate' => '2024-01-15'
	],
	'efforts' => [
		'selectedEfforts' => ['Include accessibility as part of our mission statement'],
		'additionalEfforts' => ['Custom effort 1', 'Custom effort 2']
	],
	'conformanceStatus' => [
		'standard' => 'wcag21-aa',
		'conformanceLevel' => 'partially-conformant',
		'additionalConsiderations' => 'We also follow Section 508 guidelines',
		'knownIssues' => [
			[
				'title' => 'Image alt text',
				'description' => 'Some images lack descriptive alt text'
			]
		],
		'unresolvedBarriers' => 'Some legacy content may not be fully accessible',
		'contentOutsideScope' => 'Third-party content is outside our control'
	],
	'technicalSpecs' => [
		'knownCompatibilities' => [
			'browsers' => ['Chrome', 'Firefox', 'Safari'],
			'operatingSystems' => ['Windows', 'macOS', 'Linux'],
			'assistiveTechnologies' => ['NVDA', 'JAWS', 'VoiceOver']
		],
		'knownIncompatibilities' => [
			'browsers' => ['Internet Explorer'],
			'operatingSystems' => [],
			'assistiveTechnologies' => []
		],
		'technologyReliance' => [
			'selected' => ['HTML', 'CSS', 'JavaScript'],
			'other' => ''
		],
		'assessmentApproach' => [
			'selected' => ['Self-evaluation'],
			'otherMethods' => ['Manual testing with screen readers']
		]
	],
	'feedback' => [
		'companyPhoneNumber' => '+1-555-123-4567',
		'companyEmailAddress' => 'accessibility@example.com',
		'companyVisitorAddress' => '123 Main St, City, State 12345',
		'companyPostalAddress' => '123 Main St, City, State 12345',
		'sameAsVisitorAddress' => true,
		'otherContactOptions' => 'Contact form on website',
		'typicalDurationForResponse' => '2 business days',
		'enforcementTeam' => [
			[
				'name' => 'Department of Justice',
				'linkToEnforcementProcedure' => 'https://www.ada.gov/',
				'contactDetails' => 'Phone: 1-800-514-0301'
			]
		]
	],
	'relatedEvidence' => [
		'recentEvaluationReport' => 'https://example.com/accessibility-report.pdf',
		'evaluationStatement' => '',
		'otherEvidence' => ['https://example.com/accessibility-audit.pdf']
	]
];

$generator = new AccessibilityStatementGenerator($statement);
echo $generator->generate();
*/
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

<?php
/**
 * Class Comment_Search file.
 *
 * @package AccessibilityPlus
 *
 * @since 2.0.0
 */

namespace WebYes\AccessibilityPlus\Lite\Frontend\Modules\Fixes\Fix;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Includes\Settings;

/**
 * Comment_Search class
 *
 * @since 2.0.0
 */
class Comment_Search {


	/**
	 * Load the fix
	 *
	 * @return void
	 */
	public function load() {
		$settings          = Settings::get_instance();
		$fix_comment_label = $settings->get( 'fixes', 'addMissingCommentFormLabels' );
		$fix_search_label  = $settings->get( 'fixes', 'addMissingSearchFormLabels' );
		// Add the actual fixes if enabled in settings.
		if ( isset( $fix_comment_label['status'] ) && $fix_comment_label['status'] ) {
			add_filter( 'comment_form_defaults', array( $this, 'fix_comment_form_labels' ), PHP_INT_MAX );
		}

		if ( isset( $fix_search_label['status'] ) && $fix_search_label['status'] ) {
			add_filter( 'get_search_form', array( $this, 'fix_search_form_label' ), PHP_INT_MAX );
		}
	}

	/**
	 * Fixes labels in the comments form.
	 *
	 * @param array $defaults The default comment form arguments.
	 * @return array Modified comment form arguments.
	 */
	public function fix_comment_form_labels( $defaults ): array {

		// Check if the comment label is set correctly; if not, fix it.
		if ( empty( $defaults['comment_field'] ) || ! strpos( $defaults['comment_field'], '<label' ) ) {
			$defaults['comment_field'] = '<p class="comment-form-comment"><label for="comment" class="wya11y-generated-label">' . esc_html__( 'Comment', 'accessibility-plus' ) . '</label><textarea id="comment" name="comment" rows="4" required></textarea></p>';
		}

		// Check the author field label.
		if ( isset( $defaults['fields']['author'] ) && ! strpos( $defaults['fields']['author'], '<label' ) ) {
			$defaults['fields']['author'] = '<p class="comment-form-author"><label for="author" class="wya11y-generated-label">' . esc_html__( 'Name', 'accessibility-plus' ) . '</label><input id="author" name="author" type="text" value="" size="30" required /></p>';
		}

		// Check the email field label.
		if ( isset( $defaults['fields']['email'] ) && ! strpos( $defaults['fields']['email'], '<label' ) ) {
			$defaults['fields']['email'] = '<p class="comment-form-email"><label for="email" class="wya11y-generated-label">' . esc_html__( 'Email', 'accessibility-plus' ) . '</label><input id="email" name="email" type="email" value="" size="30" required /></p>';
		}

		// Check the website field label.
		if ( isset( $defaults['fields']['url'] ) && ! strpos( $defaults['fields']['url'], '<label' ) ) {
			$defaults['fields']['url'] = '<p class="comment-form-url"><label for="url" class="wya11y-generated-label">' . esc_html__( 'Website', 'accessibility-plus' ) . '</label><input id="url" name="url" type="url" value="" size="30" /></p>';
		}
		return $defaults;
	}
	/**
	 * Fixes the search form label.
	 *
	 * @param string $form The HTML of the search form.
	 * @return string Modified search form HTML.
	 */
	public function fix_search_form_label( $form ): string {

		// Check if the form already contains a visible <label> with a matching "for" attribute for the search input's id.
		if ( ! preg_match( '/<label[^>]*for=["\']([^"\']*)["\'][^>]*>.*<\/label>/', $form, $label_matches ) ||
			! preg_match( '/<input[^>]*id=["\']([^"\']*)["\'][^>]*name=["\']s["\'][^>]*>/', $form, $input_matches ) ||
			$label_matches[1] !== $input_matches[1] ) {

			// Extract the existing input field to preserve its attributes, or set a default if none found.
			if ( isset( $input_matches[0] ) ) {
				$input_field = $input_matches[0];
				$input_id    = $input_matches[1]; // Use the existing id of the input field.
			} else {
				$input_id    = 'search-form-' . uniqid(); // Generate a unique ID if the input field doesn't have one.
				$input_field = '<input type="search" id="' . esc_attr( $input_id ) . '" class="search-field wya11y-generated-label" placeholder="' . esc_attr__( 'Search …', 'accessibility-plus' ) . '" value="' . get_search_query() . '" name="s" />';
			}

			// Rebuild the form with a visible <label> and ensure the "for" attribute matches the input's id.
			$form = '<form role="search" method="get" class="search-form" action="' . esc_url( home_url( '/' ) ) . '">
			<label for="' . esc_attr( $input_id ) . '" class="wya11y-generated-label" >' . esc_html__( 'Search for:', 'accessibility-plus' ) . '</label>
			' . $input_field . '
			<button type="submit" class="search-submit">' . esc_attr__( 'Search', 'accessibility-plus' ) . '</button>
			</form>';
		}

		return $form;
	}
}

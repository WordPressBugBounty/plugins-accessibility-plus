<?php
/**
 * WordPress Rest controller class.
 *
 * @link       https://www.webyes.com/
 * @since      2.0.1
 *
 * @package    WebYes\AccessibilityPlus\Lite\Includes
 */

namespace WebYes\AccessibilityPlus\Lite\Includes;

use function add_action;
use function __;
use function esc_html_e;
use function esc_attr;
use function esc_attr_e;
use function esc_js;
use function esc_html;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function sanitize_email;
use function home_url;
use function get_bloginfo;
use function get_locale;
use function get_available_languages;
use function wp_get_theme;
use function is_multisite;
use function wp_remote_post;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_create_nonce;
use function wp_verify_nonce;
use function current_user_can;
use function wp_unslash;
use function wp_die;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

	/**
	 * Uninstall feedback modal and handler for AccessibilityPlus
	 */
class Uninstall_Feedback {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_footer', array( $this, 'deactivate_scripts' ) );
		add_action( 'wp_ajax_wya11y_submit_uninstall_reason', array( $this, 'send_uninstall_reason' ) );
	}

	/**
	 * Get uninstall reasons
	 *
	 * @return array
	 */
	private function get_uninstall_reasons() {
		$reasons = array(
			array(
				'id'          => 'used-it',
				'text'        => __( 'Used it successfully. Don\'t need anymore.', 'accessibility-plus' ),
				'type'        => 'reviewhtml',
				'placeholder' => __( 'Have used it successfully and don\'t need it anymore', 'accessibility-plus' ),
			),
			array(
				'id'          => 'could-not-understand',
				'text'        => __( 'I couldn\'t understand how to make it work', 'accessibility-plus' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Would you like us to assist you?', 'accessibility-plus' ),
			),
			array(
				'id'          => 'found-better-plugin',
				'text'        => __( 'I found a better plugin', 'accessibility-plus' ),
				'type'        => 'text',
				'placeholder' => __( 'Which plugin?', 'accessibility-plus' ),
			),
			array(
				'id'          => 'not-have-that-feature',
				'text'        => __( 'The plugin is great, but I need specific feature that you don\'t support', 'accessibility-plus' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Could you tell us more about that feature?', 'accessibility-plus' ),
			),
			array(
				'id'          => 'is-not-working',
				'text'        => __( 'The plugin didn\'t work as expected', 'accessibility-plus' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Could you tell us a bit more what\'s not working?', 'accessibility-plus' ),
			),
			array(
				'id'          => 'other',
				'text'        => __( 'Other', 'accessibility-plus' ),
				'type'        => 'textarea',
				'placeholder' => __( 'Could you tell us a bit more?', 'accessibility-plus' ),
			),
		);
		return $reasons;
	}

	/**
	 * Add deactivation scripts (modal + JS)
	 */
	public function deactivate_scripts() {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}
		$reasons = $this->get_uninstall_reasons();
		$reasons = ( isset( $reasons ) && is_array( $reasons ) ) ? $reasons : array();
		
		// Generate nonce for security
		$nonce = wp_create_nonce( 'wya11y_uninstall_feedback_nonce' );
		?>
			<div class="wya11y-modal" id="wya11y-uninstall-modal">
				<div class="wya11y-modal-wrap">
					<div class="wya11y-modal-header">
						<h3><?php esc_html_e( 'If you have a moment, please let us know why you are deactivating:', 'accessibility-plus' ); ?></h3>
					</div>
					<div class="wya11y-modal-body">
						<ul class="reasons">
						<?php foreach ( $reasons as $reason ) { ?>
								<li data-type="<?php echo esc_attr( $reason['type'] ); ?>" data-placeholder="<?php echo esc_attr( $reason['placeholder'] ); ?>">
									<label><input type="radio" name="selected-reason" value="<?php echo esc_attr( $reason['id'] ); ?>"> <?php echo esc_html( $reason['text'] ); ?></label>
								</li>
							<?php } ?>
						</ul>
						<label style="margin-bottom: 0;">
							<span class="wya11y-checkbox-top-border"></span>
							<input type="checkbox" id="wya11y_contact_me_checkbox" name="wya11y_contact_me_checkbox" value="1">
							<?php esc_html_e( "WebYes can contact me about this feedback.", 'accessibility-plus' ); ?>
						</label>
						<div id="wya11y_email_field_wrap" style="display:none; margin-top:10px;">
							<label for="wya11y_contact_email" style="font-weight:bold;"><?php esc_html_e( "Enter your email address.", 'accessibility-plus' ); ?></label>
							<br>
							<input type="email" id="wya11y_contact_email" name="wya11y_contact_email" class="input-text" style="width:75%; height: 40px; padding:2px; margin-top:10px; border-radius:5px; border:2px solid #2874ba; padding-left:15px;" placeholder="<?php esc_attr_e( "Enter email address", 'accessibility-plus' ); ?>">
							<div id="wya11y_email_error" style="color:red; display:none; font-size:12px; margin-top:5px;"></div>
						</div>
						<div class="wya11y_policy_infobox">
						<?php esc_html_e( "We do not collect any personal data when you submit this form. It's your feedback that we value.", 'accessibility-plus' ); ?>
							<a href="https://www.webyes.com/privacy-policy/" target="_blank"><?php esc_html_e( 'Privacy Policy', 'accessibility-plus' ); ?></a>
						</div>
					</div>
					<div class="wya11y-modal-footer">
						<a href="#" class="dont-bother-me"><?php esc_html_e( 'I rather wouldn\'t say', 'accessibility-plus' ); ?></a>
						<button class="button-primary wya11y-model-submit"><?php esc_html_e( 'Submit & Deactivate', 'accessibility-plus' ); ?></button>
						<button class="button-secondary wya11y-model-cancel"><?php esc_html_e( 'Cancel', 'accessibility-plus' ); ?></button>
					</div>
				</div>
			</div>

			<style type="text/css">
				.wya11y-modal { position: fixed; z-index: 99999; top: 0; right: 0; bottom: 0; left: 0; background: rgba(0,0,0,0.5); display: none; }
				.wya11y-modal.modal-active { display: block; }
				.wya11y-modal-wrap { width: 50%; position: relative; margin: 10% auto; background: #fff; }
				.wya11y-modal-header { border-bottom: 1px solid #eee; padding: 8px 20px; }
				.wya11y-modal-header h3 { line-height: 150%; margin: 0; }
				.wya11y-modal-body { padding: 5px 20px 20px 20px; }
				.wya11y-modal-body .input-text, .wya11y-modal-body textarea { width:75%; }
				.wya11y-modal-body .reason-input { margin-top: 5px; margin-left: 20px; }
				.wya11y-modal-body label:has(#wya11y_contact_me_checkbox) { padding-top: 8px; display: block; width: 100%; margin-bottom: 0; }
				.wya11y-modal-footer { border-top: 1px solid #eee; padding: 12px 20px; text-align: right; }
				.wya11y-checkbox-top-border { display: block; border-top: 1px solid #ddd; margin-top: 1px; margin-bottom: 15px; padding-top: 8px; width: 100%; }
				.wya11y_policy_infobox { font-style: italic; text-align: left; font-size: 12px; color: #aaa; line-height: 14px; margin-top: 20px; }
				.wya11y_policy_infobox a { font-size: 11px; color: #4b9cc3; text-decoration-color: #99c3d7; }
			</style>
			<script type="text/javascript">
			(function($){
				$(function(){
					var modal = $('#wya11y-uninstall-modal');
					var deactivateLink = '';
					var nonce = '<?php echo esc_js( $nonce ); ?>';

					// Target deactivation links for this plugin
					$('#the-list').on('click', 'a.wya11y-deactivate-link, a[href*="action=deactivate"][href*="accessibility-plus"]', function(e){
						e.preventDefault();
						modal.addClass('modal-active');
						deactivateLink = $(this).attr('href');
						modal.find('input[type="radio"]:checked').prop('checked', false);
						modal.find('a.dont-bother-me').attr('href', deactivateLink).css('float', 'left');
					});

					modal.on('click', 'button.wya11y-model-cancel', function(e){ e.preventDefault(); modal.removeClass('modal-active'); });
					modal.on('click', 'input[type="radio"]', function(){
						var parent = $(this).parents('li:first');
						modal.find('.reason-block').remove();
						var inputType = parent.data('type'), inputPlaceholder = parent.data('placeholder');
						var reasonInputHtml = '';
						if ('reviewhtml' === inputType) {
							reasonInputHtml = '<div class="reviewlink reason-block"><a href="#" target="_blank" class="review-and-deactivate"><?php echo esc_js( __( 'Deactivate and leave a review', 'accessibility-plus' ) ); ?> <span class="wya11y-rating-link"> &#9733;&#9733;&#9733;&#9733;&#9733; </span></a></div>';
						} else if ('supportlink' === inputType) {
							reasonInputHtml = '<div class="support_link reason-block"><a href="https://www.webyes.com/contact/" target="_blank" class="reach-via-support"><?php echo esc_js( __( 'Let our support team help you', 'accessibility-plus' ) ); ?></a></div>';
						} else {
							reasonInputHtml = '<div class="reason-input reason-block">' + (("text" === inputType) ? '<input type="text" class="input-text" size="40" />' : '<textarea rows="5" cols="45"></textarea>') + '</div>';
						}
						if (inputType !== '') { parent.append($(reasonInputHtml)); parent.find('input, textarea').attr('placeholder', inputPlaceholder).focus(); }
					});

					// Handle email checkbox toggle
					$('#wya11y_contact_me_checkbox').on('change', function() {
						if ($(this).is(':checked')) {
							$('#wya11y_email_field_wrap').slideDown();
						} else {
							$('#wya11y_email_field_wrap').slideUp();
							$('#wya11y_contact_email').val('');
							$('#wya11y_email_error').hide();
						}
					});

					modal.on('click', 'button.wya11y-model-submit', function(e){
						e.preventDefault();
						var button = $(this);
						if (button.hasClass('disabled')) { return; }
						
						// Email validation
						var emailCheckbox = $('#wya11y_contact_me_checkbox');
						var emailField = $('#wya11y_contact_email');
						var emailError = $('#wya11y_email_error');
						emailError.hide();
						
						if (emailCheckbox.is(':checked')) {
							var emailVal = emailField.val();
							var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
							if (!emailVal || !emailPattern.test(emailVal)) {
								emailError.text('<?php echo esc_js( __( 'Please enter a valid email address.', 'accessibility-plus' ) ); ?>').show();
								emailField.focus();
								return;
							}
						}
						
						var $radio = $('input[type="radio"]:checked', modal);
						var $selected = $radio.parents('li:first');
						var $input = $selected.find('textarea, input[type="text"]');
						var reason_info = $input.length ? $input.val().trim() : '';
						var reason_id = $radio.length ? $radio.val() : 'none';
						var user_email = emailCheckbox.is(':checked') ? emailField.val() : '';
						
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: { 
								action: 'wya11y_submit_uninstall_reason', 
								reason_id: reason_id, 
								reason_info: reason_info,
								user_email: user_email,
								nonce: nonce
							},
							beforeSend: function(){ button.addClass('disabled').text('Processing...'); },
							complete: function(){ window.location.href = deactivateLink; }
						});
					});
				});
			})(jQuery);
			</script>
			<?php
	}

	/**
	 * AJAX: Send uninstall reason to server
	 */
	public function send_uninstall_reason() {

		// Security check: Verify this is a valid AJAX request
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'accessibility-plus' ) ) );
		}

		// Security check: Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wya11y_uninstall_feedback_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'accessibility-plus' ) ) );
			wp_die();
		}

		// Security check: Verify user capability (only administrators can deactivate plugins)
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'accessibility-plus' ) ) );
			wp_die();
		}

		global $wpdb;
		if ( ! isset( $_POST['reason_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required data.', 'accessibility-plus' ) ) );
			wp_die();
		}
		$data = array(
			'reason_id'      => sanitize_text_field( wp_unslash( $_POST['reason_id'] ) ),
			'plugin'         => 'wyaccessibilityplus',
			'auth'           => 'wyaccessibilityplus_uninstall_1234#',
			'date'           => gmdate( 'M d, Y h:i:s A' ),
			'url'            => home_url(),
			'user_email'     => isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '',
			'reason_info'    => isset( $_POST['reason_info'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason_info'] ) ) : '',
			'software'       => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'php_version'    => phpversion(),
			'mysql_version'  => $wpdb->db_version(),
			'wp_version'     => get_bloginfo( 'version' ),
			'wc_version'     => ( function_exists( 'WC' ) ) ? WC()->version : '',
			'locale'         => get_locale(),
			'languages'      => implode( ',', get_available_languages() ),
			'theme'          => wp_get_theme()->get( 'Name' ),
			'wyaccessibilityplus_version' => defined( 'WY_A11Y_VERSION' ) ? WY_A11Y_VERSION : '',
			'multisite'      => is_multisite() ? 'Yes' : 'No',
		);

		// Send feedback to remote endpoint (non-blocking)
		wp_remote_post(
			'https://feedback.webtoffee.com/wp-json/wyaccessibilityplus/v1/uninstall',
			array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => false,
				'body'        => $data,
				'cookies'     => array(),
			)
		);

		wp_send_json_success();
	}
}

new Uninstall_Feedback();

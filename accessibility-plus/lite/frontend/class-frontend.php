<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.webyes.com/
 * @since      3.0.0
 *
 * @package    AccessibilityPlus
 * @subpackage AccessibilityPlus/Frontend
 */

namespace WebYes\AccessibilityPlus\Lite\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WebYes\AccessibilityPlus\Lite\Admin\Modules\Settings\Includes\Settings;

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    AccessibilityPlus
 * @subpackage WebYes\AccessibilityPlus\Lite\Frontend
 * @author     WebYes <info@webyes.com>
 */
class Frontend {



	/**
	 * The ID of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $plugin_name  The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Admin modules of the plugin
	 *
	 * @var array
	 */
	private static $modules;

	/**
	 * Currently active modules
	 *
	 * @var array
	 */
	private static $active_modules;

	/**
	 * Existing modules
	 *
	 * @var array
	 */
	public static $existing_modules;


	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    3.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		self::$modules     = $this->get_default_modules();
		$this->load_modules();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checker_assets' ), 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_toolbar_widget' ), 3 );
		add_action( 'wp_head', array( $this, 'inject_audit_preload_style' ), 0 );
	}

	/**
	 * Inject an early <style>/<script> pair that hides the page during an
	 * audit-triggered request so the user doesn't see the real page paint
	 * before the scanner dashboard mounts and the iframe re-renders it.
	 *
	 * Only fires when ?wya11y_audit=1 is on the URL and the visitor has the
	 * manage_options capability (same gate as enqueue_checker_assets()).
	 *
	 * @return void
	 */
	public function inject_audit_preload_style() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag, no state change.
		$is_audit_request = isset( $_GET['wya11y_audit'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wya11y_audit'] ) );
		if ( ! $is_audit_request ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<style id="wya11y-audit-preload">
			html.wya11y-auditing > body > *:not(#wya11y-checker-root) { visibility: hidden !important; }
			html.wya11y-auditing > body { background: #f3f4f6 !important; }
		</style>
		<script id="wya11y-audit-preload-mark">
			(function () {
				var root = document.documentElement;
				root.classList.add('wya11y-auditing');
				// Safety net: if the scanner bundle fails to load (404, CSP
				// block, parse error) the dashboard host never gets appended.
				// After 8s with no host in the DOM, clear the preload so the
				// admin isn't stranded on a blank gray page.
				setTimeout(function () {
					if (!document.getElementById('wya11y-checker-root')) {
						root.classList.remove('wya11y-auditing');
						var style = document.getElementById('wya11y-audit-preload');
						if (style && style.parentNode) {
							style.parentNode.removeChild(style);
						}
					}
				}, 8000);
			})();
		</script>
		<?php
	}

	/**
	 * Get the default modules array
	 *
	 * @since 3.0.0
	 * @return array
	 */
	public function get_default_modules() {
		$modules = array( 'fixes' );
		return $modules;
	}

	/**
	 * Load all the modules
	 *
	 * @return void
	 */
	public function load_modules() {

		foreach ( self::$modules as $module ) {
			$parts      = explode( '_', $module );
			$class      = implode( '_', $parts );
			$class_name = 'WebYes\AccessibilityPlus\Lite\\Frontend\\Modules\\' . ucfirst( $module ) . '\\' . ucfirst( $class );

			if ( class_exists( $class_name ) ) {
				$module_obj = new $class_name( $module );
				if ( $module_obj instanceof $class_name ) {
					if ( $module_obj->is_active() ) {
						self::$active_modules[ $module ] = true;
					}
				}
			}
		}
	}

	/**
	 * Enqueue front end scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/js/script' . $suffix . '.js', array(), $this->version, true );
		$config = Settings::get_instance()->get();
		$config = apply_filters( 'wya11y_config', $config );
		wp_localize_script( $this->plugin_name, '_wyA11yConfig', $config );
	}

	/**
	 * Enqueue checker assets (content script and dashboard bundle)
	 *
	 * @return void
	 */
	public function enqueue_checker_assets() {
		// Only load when an admin opened this URL via the backend "Scan page" trigger.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag, no state change.
		$is_audit_request = isset( $_GET['wya11y_audit'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wya11y_audit'] ) );
		if ( ! $is_audit_request ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Enqueue content script (vanilla JS, no build)
		wp_enqueue_script(
			'wya11y-content-script',
			plugin_dir_url( __FILE__ ) . 'assets/checker/js/content-script.js',
			array(),
			$this->version,
			true
		);

		// Check if built checker bundle exists
		$checker_js_path = dirname( __FILE__ ) . '/../admin/app/dist/assets/checker.js';
		$checker_css_path = dirname( __FILE__ ) . '/../admin/app/dist/assets/react.css';
		
		$checker_js = null;
		$checker_css = null;

		if ( file_exists( $checker_js_path ) ) {
			$checker_js = 'assets/checker.js';
			wp_enqueue_script(
				'wya11y-checker-dashboard',
				plugin_dir_url( dirname( __FILE__ ) ) . 'admin/app/dist/' . $checker_js,
				array(),
				$this->version,
				true
			);
			// Add filter to add type="module" attribute for this script
			add_filter( 'script_loader_tag', array( $this, 'add_module_type_to_checker' ), 10, 3 );
		}

		if ( file_exists( $checker_css_path ) ) {
			$checker_css = 'assets/react.css';
			wp_enqueue_style(
				'wya11y-checker-styles',
				plugin_dir_url( dirname( __FILE__ ) ) . 'admin/app/dist/' . $checker_css,
				array(),
				$this->version
			);
		}

		wp_localize_script(
			'wya11y-content-script',
			'wya11yChecker',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wya11y_checker' ),
				'assetsUrl'       => plugin_dir_url( __FILE__ ) . 'assets/checker/',
				'dashboardUrl'    => $checker_js ? plugin_dir_url( dirname( __FILE__ ) ) . 'admin/app/dist/' . $checker_js : '',
				'dashboardCssUrl' => $checker_css ? plugin_dir_url( dirname( __FILE__ ) ) . 'admin/app/dist/' . $checker_css : '',
				'version'         => $this->version,
			)
		);
	}

	/**
	 * Enqueue toolbar widget script with proper WordPress enqueuing and localization
	 *
	 * @return void
	 */
	public function enqueue_toolbar_widget() {
		$settings = Settings::get_instance();
		$toolbar_settings = $settings->get( 'toolbar' );

		// Check if toolbar is enabled
		if ( (empty( $toolbar_settings['enabled']) && ! $toolbar_settings['enabled']) || (! isset($toolbar_settings['features']) && ! is_array($toolbar_settings['features']) ) ){
			return;
		}

		// Prepare the configuration for the widget
		$hide_on_small_screen = isset( $toolbar_settings['features']['hideOnSmallScreens'] ) ? $toolbar_settings['features']['hideOnSmallScreens'] : false;

		$allowed_settings_options = array(
			'font-size' => 'fontSize',
			'high-contrast' => 'contrast',
			'grayscale' => 'grayscale',
		);
		$allowed_settings = array();
		$is_feature_enabled = false;
		foreach ( $allowed_settings_options as $key => $value ) {
			if( isset( $toolbar_settings['features'][ $value ] ) && ! empty( $toolbar_settings['features'][ $value ] ) && $toolbar_settings['features'][ $value ] === true ){
				$allowed_settings[ $key ] = true;
				$is_feature_enabled = true;
			}else{
				$allowed_settings[ $key ] = false;
			}
		}
		if(!$is_feature_enabled){
			return;
		}

		// Prepare configuration array
		$config = array(
			'iconId' => 'default',
			'toolbarSettings' => array(
				'hideFromSmallScreen' => isset( $toolbar_settings['features']['hideOnSmallScreens'] ) ? $toolbar_settings['features']['hideOnSmallScreens'] : false,
			),
			'position' => array(
				'mobile' => isset( $toolbar_settings['placement']['position'] ) ? $toolbar_settings['placement']['position'] : 'bottom-right',
				'desktop' => isset( $toolbar_settings['placement']['position'] ) ? $toolbar_settings['placement']['position'] : 'bottom-right',
				'vertical' => isset( $toolbar_settings['placement']['verticalOffset'] ) ? $toolbar_settings['placement']['verticalOffset'] : 20,
			),
			'allowedSettings' => $allowed_settings,
			'language' => array(
				'default' => get_locale(),
				'selected' => array(),
			),
			'translations' => array(
				'Accessibility toolbar' => __( 'Accessibility toolbar', 'accessibility-plus' ),
				'Adjust Font Sizing' => __( 'Adjust Font Sizing', 'accessibility-plus' ),
				'High contrast' => __( 'High contrast', 'accessibility-plus' ),
				'Grayscale' => __( 'Grayscale', 'accessibility-plus' ),
			),
		);

		// Enqueue the widget script with proper versioning
		wp_enqueue_script(
			'accessibility-plus-widget',
			plugin_dir_url( __FILE__ ) . 'assets/js/widget.min.js',
			array(), // No dependencies
			$this->version, // Use plugin version for cache busting
			true // Load in footer
		);
		// Localize script to pass configuration
		wp_localize_script(
			'accessibility-plus-widget',
			'_accessibilityPlusConfig',
			$config
		);
	}

	/**
	 * Add type="module" attribute to checker.js script tag
	 *
	 * @param string $tag    The script tag HTML.
	 * @param string $handle The script handle.
	 * @param string $src    The script source URL.
	 * @return string Modified script tag.
	 */
	public function add_module_type_to_checker( $tag, $handle, $src ) {
		// Only modify the checker dashboard script
		if ( 'wya11y-checker-dashboard' === $handle ) {
			// Add type="module" attribute
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}
}

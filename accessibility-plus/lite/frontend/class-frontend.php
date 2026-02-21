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
		add_action( 'wp_head', array( $this, 'inject_checker_icon' ), 999 );
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
		$settings = Settings::get_instance();
		$checker_settings = $settings->get( 'checker' );

		// Only enqueue if checker is enabled
		if ( empty( $checker_settings['enabled'] ) ) {
			return;
		}

		// Check user capability - only load assets for users who can edit posts (Editors and above)
		if ( ! current_user_can( 'edit_posts' ) ) {
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

		// Localize script with config
		$position = isset( $checker_settings['placement']['position'] ) ? $checker_settings['placement']['position'] : 'right';

		wp_localize_script(
			'wya11y-content-script',
			'wya11yChecker',
			array(
				'position' => $position,
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wya11y_checker' ),
				'assetsUrl' => plugin_dir_url( __FILE__ ) . 'assets/checker/',
				'dashboardUrl' => $checker_js ? plugin_dir_url( dirname( __FILE__ ) ) . 'admin/app/dist/' . $checker_js : '',
				'dashboardCssUrl' => $checker_css ? plugin_dir_url( dirname( __FILE__ ) ) . 'admin/app/dist/' . $checker_css : ''
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
	 * Inject accessibility checker icon in the footer
	 * 
	 * Only displayed to users with 'edit_posts' capability (Editors and Administrators)
	 * to ensure only users who can make changes to content can access the scanner.
	 *
	 * @return void
	 */
	public function inject_checker_icon() {
		$settings = Settings::get_instance();
		$checker_settings = $settings->get( 'checker' );

		// Check if checker is enabled
		if ( empty( $checker_settings['enabled'] ) ) {
			return;
		}

		// Check user capability - only show to users who can edit posts (Editors and above)
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Get position settings
		$position = isset( $checker_settings['placement']['position'] ) ? $checker_settings['placement']['position'] : 'right';

		// Output the checker icon HTML
		?>
		<div id="wya11y-checker-icon" 
			data-position="<?php echo esc_attr( $position ); ?>"
			aria-label="Click to scan this page"
			role="button"
			style="position: fixed; 
				<?php echo esc_attr( $position ); ?>: 10px; 
				bottom: 20px; 
				width: 60px; 
				height: 60px; 
				cursor: pointer; 
				z-index: 999999;
				display: flex;
				align-items: center;
				justify-content: center;
				transition: transform 0.2s ease, box-shadow 0.2s ease, outline 0.2s ease;
				user-select: none;
				outline: none;
				border-radius: 50%;
				background: transparent;">
			<svg width="60" height="60" viewBox="20 15 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
				<g>
					<path d="M51.3335 19.6667C66.2452 19.6667 78.3335 31.7551 78.3335 46.6667C78.3335 61.5784 66.2452 73.6667 51.3335 73.6667C36.4218 73.6667 24.3335 61.5784 24.3335 46.6667C24.3335 31.7551 36.4218 19.6667 51.3335 19.6667Z" fill="#0B66E4" stroke="#B3D0F7" stroke-width="2"/>
					<path fill-rule="evenodd" clip-rule="evenodd" d="M50.6753 49.6931C50.5789 49.9191 49.2847 54.9603 49.2847 54.9603C49.2547 55.0732 49.2033 55.179 49.133 55.2716C49.0628 55.3642 48.9753 55.442 48.8755 55.5003C48.6739 55.618 48.4344 55.6498 48.2097 55.5884C48.0986 55.5581 47.9943 55.5058 47.903 55.4344C47.8117 55.3632 47.7351 55.2744 47.6777 55.1732C47.5615 54.9686 47.5302 54.7256 47.5907 54.4977C47.5907 54.4977 49.1339 49.4671 49.1339 48.3941V45.7801L45.7004 44.8458C45.5875 44.8173 45.4813 44.7662 45.388 44.6956C45.2947 44.6249 45.2162 44.5362 45.1572 44.4345C45.098 44.3327 45.0595 44.2201 45.0437 44.1031C45.028 43.986 45.0353 43.867 45.0654 43.7529C45.0953 43.6389 45.1474 43.5319 45.2186 43.4385C45.2898 43.345 45.3786 43.267 45.4798 43.2086C45.581 43.1503 45.6927 43.1131 45.8082 43.0991C45.9237 43.0851 46.0409 43.0945 46.1528 43.1269C46.1528 43.1269 49.0533 44.1287 50.1861 44.1287H52.4815C53.6126 44.1287 56.5113 43.1269 56.5113 43.1269C56.7359 43.0655 56.9754 43.0972 57.177 43.215C57.3786 43.3328 57.5257 43.5271 57.5863 43.755C57.6467 43.983 57.6154 44.2259 57.4993 44.4305C57.3832 44.6351 57.1919 44.7844 56.9672 44.8458L53.5196 45.7836V48.3941C53.5196 49.4671 55.0628 54.4923 55.0628 54.4923C55.123 54.7202 55.0916 54.9631 54.9753 55.1676C54.859 55.3719 54.6675 55.5212 54.4429 55.5822C54.2183 55.6433 53.9789 55.6113 53.7775 55.4935C53.576 55.3755 53.429 55.1811 53.3688 54.9532C53.3688 54.9532 52.0694 49.9191 51.98 49.6931C51.8923 49.4671 51.596 49.4671 51.596 49.4671H51.0576C51.0576 49.4671 50.7542 49.4671 50.6753 49.6931ZM51.3329 42.7887C51.8561 42.7887 52.3579 42.5778 52.7279 42.2024C53.0979 41.827 53.3057 41.3178 53.3057 40.7868C53.3057 40.256 53.0979 39.7467 52.7279 39.3713C52.3579 38.9959 51.8561 38.7849 51.3329 38.7849C50.8097 38.7849 50.3079 38.9959 49.9379 39.3713C49.5679 39.7467 49.3601 40.256 49.3601 40.7868C49.3601 41.3178 49.5679 41.827 49.9379 42.2024C50.3079 42.5778 50.8097 42.7887 51.3329 42.7887Z" fill="white"/>
					<path fill-rule="evenodd" clip-rule="evenodd" d="M41.6091 42.2762C41.8592 42.0262 41.9997 41.687 41.9997 41.3334V38.5641C41.9997 37.9521 42.029 37.8027 42.109 37.6521C42.1553 37.5622 42.2285 37.489 42.3183 37.4427C42.4703 37.3627 42.6183 37.3334 43.2303 37.3334H45.9997C46.3533 37.3334 46.6924 37.1929 46.9425 36.9429C47.1925 36.6928 47.333 36.3537 47.333 36.0001C47.333 35.6465 47.1925 35.3073 46.9425 35.0573C46.6924 34.8072 46.3533 34.6667 45.9997 34.6667H43.2303C42.2157 34.6667 41.6503 34.7761 41.061 35.0921C40.5023 35.3894 40.0557 35.8361 39.7583 36.3947C39.4423 36.9841 39.333 37.5507 39.333 38.5641V41.3334C39.333 41.687 39.4735 42.0262 39.7235 42.2762C39.9736 42.5263 40.3127 42.6667 40.6663 42.6667C41.02 42.6667 41.3591 42.5263 41.6091 42.2762ZM46.9425 56.3906C46.6924 56.1406 46.3533 56.0001 45.9997 56.0001H43.2303C42.6183 56.0001 42.469 55.9707 42.3183 55.8907C42.225 55.8414 42.1597 55.7747 42.109 55.6814C42.029 55.5294 41.9997 55.3814 41.9997 54.7694V52.0001C41.9997 51.6465 41.8592 51.3073 41.6091 51.0573C41.3591 50.8072 41.02 50.6667 40.6663 50.6667C40.3127 50.6667 39.9736 50.8072 39.7235 51.0573C39.4735 51.3073 39.333 51.6465 39.333 52.0001V54.7694C39.333 55.7841 39.4423 56.3494 39.7583 56.9387C40.0531 57.4931 40.5067 57.9467 41.061 58.2414C41.6503 58.5574 42.217 58.6667 43.2303 58.6667H45.9997C46.3533 58.6667 46.6924 58.5263 46.9425 58.2762C47.1925 58.0262 47.333 57.687 47.333 57.3334C47.333 56.9798 47.1925 56.6407 46.9425 56.3906ZM55.7235 36.9429C55.4735 36.6928 55.333 36.3537 55.333 36.0001C55.333 35.6465 55.4735 35.3073 55.7235 35.0573C55.9736 34.8072 56.3127 34.6667 56.6663 34.6667H59.4357C60.4503 34.6667 61.0157 34.7761 61.605 35.0921C62.1593 35.3868 62.6129 35.8404 62.9077 36.3947C63.2237 36.9841 63.333 37.5507 63.333 38.5641V41.3334C63.333 41.687 63.1925 42.0262 62.9425 42.2762C62.6924 42.5263 62.3533 42.6667 61.9997 42.6667C61.646 42.6667 61.3069 42.5263 61.0569 42.2762C60.8068 42.0262 60.6663 41.687 60.6663 41.3334V38.5641C60.6663 37.9521 60.637 37.8027 60.557 37.6521C60.5077 37.5587 60.441 37.4934 60.3477 37.4427C60.1957 37.3627 60.0477 37.3334 59.4357 37.3334H56.6663C56.3127 37.3334 55.9736 37.1929 55.7235 36.9429Z" fill="white"/>
					<path d="M65.7772 60.0946L63.8199 58.1373M64.8752 55.5846C64.8752 57.5772 63.2599 59.1926 61.2672 59.1926C59.2745 59.1926 57.6592 57.5772 57.6592 55.5846C57.6592 53.5919 59.2745 51.9766 61.2672 51.9766C63.2599 51.9766 64.8752 53.5919 64.8752 55.5846Z" stroke="white" stroke-width="1.52213" stroke-linecap="round" stroke-linejoin="round"/>
				</g>
			</svg>
		</div>
		<style>
			#wya11y-checker-icon:hover {
				transform: scale(1.1);
			}
			#wya11y-checker-icon:active {
				transform: scale(0.95);
			}
			
			/* Keyboard focus styles - subtle background glow */
			#wya11y-checker-icon:focus {
				outline: none !important;
				box-shadow: 0 0 0 2px rgba(11, 102, 228, 0.15);
			}
			
			#wya11y-checker-icon:focus-visible {
				outline: none !important;
				box-shadow: 0 0 0 2px rgba(11, 102, 228, 0.15);
			}
			
			/* Tooltip styles */
			.wya11y-checker-tooltip {
				position: absolute;
				bottom: 95%;
				transform: translateX(-50%);
				background: #E5E5EA !important;
				color: #13151B !important;
				padding: 3px 16px;
				border-radius: 8px;
				font-size: 14px;
				font-weight: 500;
				white-space: nowrap;
				pointer-events: none;
				opacity: 0;
				transition: opacity 0.2s ease;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
				z-index: 10000;
			}
			.wya11y-checker-tooltip.show {
				opacity: 1;
			}
		</style>
		<script>
		(function() {
			// Wait for DOM to be ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initCheckerTooltip);
			} else {
				initCheckerTooltip();
			}
			
			function initCheckerTooltip() {
				const checkerIcon = document.getElementById('wya11y-checker-icon');
				if (!checkerIcon) return;
				
				// Get icon position (left or right)
				const position = checkerIcon.getAttribute('data-position') || 'right';
				
				// Create tooltip element
				const tooltip = document.createElement('div');
				tooltip.className = 'wya11y-checker-tooltip';
				tooltip.textContent = 'Click to scan this page  ';
				
				// Add arrow position class based on icon position
				if (position === 'left') {
					tooltip.style.left = '140%';
					tooltip.classList.add('arrow-left');
				} else {
					tooltip.style.left = '-30%';
					tooltip.classList.add('arrow-right');
				}
				
				checkerIcon.appendChild(tooltip);
								
				// Show tooltip on mouseenter
				checkerIcon.addEventListener('mouseenter', function() {
					tooltip.classList.add('show');
				});
				
				// Hide tooltip on mouseleave
				checkerIcon.addEventListener('mouseleave', function() {
					tooltip.classList.remove('show');
				});
				
				// Show tooltip on focus
				checkerIcon.addEventListener('focus', function() {
					tooltip.classList.add('show');
				});
				
				// Hide tooltip on blur
				checkerIcon.addEventListener('blur', function() {
					tooltip.classList.remove('show');
				});
				
				// Hide tooltip on click
				checkerIcon.addEventListener('click', function() {
					tooltip.classList.remove('show');
				});
				
				// Keyboard accessibility - Handle Enter and Space keys
				checkerIcon.addEventListener('keydown', function(event) {
					// Trigger click on Enter or Space key
					if (event.key === 'Enter' || event.key === ' ') {
						event.preventDefault();
						checkerIcon.click();
					}
					// Close on Escape key
					if (event.key === 'Escape') {
						checkerIcon.blur();
						tooltip.classList.remove('show');
					}
				});
			}
		})();
		</script>
		<?php
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

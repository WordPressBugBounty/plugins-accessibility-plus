<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.webyes.com/
 * @since      3.0.0
 *
 * @package    WebYes\AccessibilityPlus\Lite\Admin
 */

namespace WebYes\AccessibilityPlus\Lite\Admin;
use WebYes\AccessibilityPlus\Lite\Includes\Activator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    AccessibilityPlus
 * @author     AccessibilityPlus <info@webyes.com>
 */
class Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    3.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
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
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		self::$modules     = $this->get_default_modules();
		$this->load();
		$this->load_modules();
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_print_scripts', array( $this, 'hide_admin_notices' ) );

		// add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );
	}

	/**
	 * Load activator on each load.
	 *
	 * @return void
	 */
	public function load() {
		Activator::init();
		add_filter( 'plugin_action_links_' . WY_A11Y_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Get the default modules array
	 *
	 * @return array
	 */
	public function get_default_modules() {
		$modules = array(
			'settings',
			'statement',
		);
		return $modules;
	}

	/**
	 * Get the active admin modules
	 *
	 * @return void
	 */
	public function get_active_modules() {
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
			$class_name = 'WebYes\AccessibilityPlus\Lite\\Admin\\Modules\\' . ucfirst( $module ) . '\\' . ucfirst( $class );

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
	 * Register the stylesheets for the admin area.
	 *
	 * @since    3.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'app/dist/assets/react.css', array(), $this->version );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    3.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name . '-app', plugin_dir_url( __FILE__ ) . 'app/dist/assets/index.js', array(), $this->version, true );
		
		// Add type="module" attribute to the script tag
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 3 );
		
		wp_localize_script(
			$this->plugin_name . '-app',
			'wyA11yGlobals',
			array(
				'api'       => array(
					'endpoint' => rest_url( 'wya11y/v1/' ),
					'nonce'    => wp_create_nonce( 'wp_rest' ),
				),
				'statement' => array(
					'page' => get_option( 'wya11y_statement_page' ) ? get_option( 'wya11y_statement_page' ) : '',
				),
			)
		);
	}

	/**
	 * Add type="module" attribute to script tag for ES module support
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @param string $src    The script src.
	 * @return string Modified script tag.
	 */
	public function add_module_type_attribute( $tag, $handle, $src ) {
		// Only add type="module" to our app script
		if ( $this->plugin_name . '-app' === $handle ) {
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}

	/**
	 * Register main menu and sub menus
	 *
	 * @return void
	 */
	public function admin_menu() {
		$capability = 'manage_options';
		$slug       = 'accessibility-plus';

		$hook = add_menu_page(
			__( 'Accessibility Tool Kit', 'accessibility-plus' ),
			__( 'Accessibility Tool Kit', 'accessibility-plus' ),
			$capability,
			$slug,
			array( $this, 'menu_page_template' ),
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0yOS4yODk1IDI5LjI4OTVDMTAuNTM1OCA0OC4wNDMyIDAgNzMuNDc3OSAwIDEwMEMwIDEyNi41MjIgMTAuNTM1OCAxNTEuOTU3IDI5LjI4OTUgMTcwLjcxMUM0OC4wNDMyIDE4OS40NjQgNzMuNDc3OSAyMDAgMTAwIDIwMEMxMjYuNTIyIDIwMCAxNTEuOTU3IDE4OS40NjQgMTcwLjcxMSAxNzAuNzExQzE4OS40NjQgMTUxLjk1NyAyMDAgMTI2LjUyMiAyMDAgMTAwQzIwMCA3OS43OTQ0IDE5My44ODUgNjAuMjIgMTgyLjY4IDQzLjc1TDE3MS4zNTQgNTIuNjExOEMxODAuNjEyIDY2LjU1MjcgMTg1LjY1NiA4My4wMTYgMTg1LjY1NiAxMDBDMTg1LjY1NiAxMjIuNzE4IDE3Ni42MzIgMTQ0LjUwNCAxNjAuNTY4IDE2MC41NjhDMTQ0LjUwNCAxNzYuNjMyIDEyMi43MTggMTg1LjY1NiAxMDAgMTg1LjY1NkM3Ny4yODIxIDE4NS42NTYgNTUuNDk1OCAxNzYuNjMyIDM5LjQzMTYgMTYwLjU2OEMyMy4zNjg0IDE0NC41MDQgMTQuMzQ0MiAxMjIuNzE4IDE0LjM0NDIgMTAwQzE0LjM0NDIgNzcuMjgyMSAyMy4zNjg0IDU1LjQ5NTggMzkuNDMxNiAzOS40MzE2QzU1LjQ5NTggMjMuMzY4NCA3Ny4yODIxIDE0LjM0NDIgMTAwIDE0LjM0NDJDMTA1LjMxMiAxNC4zNDQyIDExMC41NzMgMTQuODM3NiAxMTUuNzIzIDE1Ljc5OTFMMTE4LjkzOSAxLjgwOTQ0QzExMi43NDIgMC42MTQwMTggMTA2LjQwMyAwIDEwMCAwQzczLjQ3NzkgMCA0OC4wNDMyIDEwLjUzNTggMjkuMjg5NSAyOS4yODk1WiIgZmlsbD0id2hpdGUiLz4KPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik05Ny41NTc3IDExOS4zNDhDOTYuODg2OCAxMjAuOTE0IDg3Ljg4NTcgMTU1Ljg1MiA4Ny44ODU3IDE1NS44NTJDODcuNjc3NCAxNTYuNjM0IDg3LjMxOTUgMTU3LjM2NyA4Ni44MzA1IDE1OC4wMDlDODYuMzQyNSAxNTguNjUxIDg1LjczMzcgMTU5LjE5IDg1LjAzOTggMTU5LjU5NEM4My42Mzc3IDE2MC40MSA4MS45NzIgMTYwLjYzIDgwLjQwOTIgMTYwLjIwNEM3OS42MzYzIDE1OS45OTUgNzguOTExIDE1OS42MzIgNzguMjc1OSAxNTkuMTM3Qzc3LjY0MDcgMTU4LjY0NCA3Ny4xMDg0IDE1OC4wMjggNzYuNzA4OCAxNTcuMzI3Qzc1LjkwMSAxNTUuOTA5IDc1LjY4MzQgMTU0LjIyNSA3Ni4xMDQyIDE1Mi42NDVDNzYuMTA0MiAxNTIuNjQ1IDg2LjgzNzQgMTE3Ljc4MSA4Ni44Mzc0IDExMC4zNDVWOTIuMjI5M0w2Mi45NTY0IDg1Ljc1NDRDNjIuMTcxNiA4NS41NTY3IDYxLjQzMjcgODUuMjAyNSA2MC43ODM5IDg0LjcxMzNDNjAuMTM1MSA4NC4yMjMyIDU5LjU4OTMgODMuNjA4NSA1OS4xNzg2IDgyLjkwMzVDNTguNzY3IDgyLjE5ODUgNTguNDk5MiA4MS40MTc4IDU4LjM4OTUgODAuNjA3QzU4LjI3OTggNzkuNzk1NCA1OC4zMzA4IDc4Ljk3MDkgNTguNTQgNzguMTc5OUM1OC43NDgzIDc3LjM4OTggNTkuMTEwNiA3Ni42NDg3IDU5LjYwNTQgNzYuMDAxM0M2MC4xMDExIDc1LjM1MzEgNjAuNzE4NCA3NC44MTIzIDYxLjQyMjUgNzQuNDA4MkM2Mi4xMjY1IDc0LjAwNDEgNjIuOTAyOCA3My43NDYyIDYzLjcwNjMgNzMuNjQ5QzY0LjUwOTggNzMuNTUxOSA2NS4zMjUyIDczLjYxNzIgNjYuMTAzMiA3My44NDE2QzY2LjEwMzIgNzMuODQxNiA4Ni4yNzYyIDgwLjc4NDEgOTQuMTU0OSA4MC43ODQxSDExMC4xMkMxMTcuOTg2IDgwLjc4NDEgMTM4LjE0NyA3My44NDE2IDEzOC4xNDcgNzMuODQxNkMxMzkuNzA5IDczLjQxNiAxNDEuMzc1IDczLjYzNjEgMTQyLjc3NyA3NC40NTJDMTQ0LjE3OSA3NS4yNjg4IDE0NS4yMDMgNzYuNjE1MiAxNDUuNjI0IDc4LjE5NDZDMTQ2LjA0NCA3OS43NzQ4IDE0NS44MjYgODEuNDU4MiAxNDUuMDE5IDgyLjg3NkMxNDQuMjEyIDg0LjI5MzcgMTQyLjg4MSA4NS4zMjg5IDE0MS4zMTggODUuNzU0NEwxMTcuMzQgOTIuMjUzM1YxMTAuMzQ1QzExNy4zNCAxMTcuNzgxIDEyOC4wNzMgMTUyLjYwOCAxMjguMDczIDE1Mi42MDhDMTI4LjQ5MiAxNTQuMTg4IDEyOC4yNzMgMTU1Ljg3MSAxMjcuNDY1IDE1Ny4yODhDMTI2LjY1NiAxNTguNzA0IDEyNS4zMjQgMTU5LjczOCAxMjMuNzYyIDE2MC4xNjFDMTIyLjIgMTYwLjU4NSAxMjAuNTM1IDE2MC4zNjQgMTE5LjEzNCAxNTkuNTQ3QzExNy43MzIgMTU4LjcyOSAxMTYuNzEgMTU3LjM4MiAxMTYuMjkxIDE1NS44MDNDMTE2LjI5MSAxNTUuODAzIDEwNy4yNTMgMTIwLjkxNCAxMDYuNjMyIDExOS4zNDhDMTA2LjAyMiAxMTcuNzgxIDEwMy45NjEgMTE3Ljc4MSAxMDMuOTYxIDExNy43ODFIMTAwLjIxNkMxMDAuMjE2IDExNy43ODEgOTguMTA2MSAxMTcuNzgxIDk3LjU1NzcgMTE5LjM0OFpNMTAyLjEzMSA3MS40OTc5QzEwNS43NzEgNzEuNDk3OSAxMDkuMjYgNzAuMDM2MyAxMTEuODM0IDY3LjQzNDdDMTE0LjQwNyA2NC44MzMgMTE1Ljg1MiA2MS4zMDM3IDExNS44NTIgNTcuNjI0QzExNS44NTIgNTMuOTQ1IDExNC40MDcgNTAuNDE1NyAxMTEuODM0IDQ3LjgxNDFDMTA5LjI2IDQ1LjIxMjUgMTA1Ljc3MSA0My43NSAxMDIuMTMxIDQzLjc1Qzk4LjQ5MjEgNDMuNzUgOTUuMDAyNiA0NS4yMTI1IDkyLjQyODggNDcuODE0MUM4OS44NTU4IDUwLjQxNTcgODguNDEwMyA1My45NDUgODguNDEwMyA1Ny42MjRDODguNDEwMyA2MS4zMDM3IDg5Ljg1NTggNjQuODMzIDkyLjQyODggNjcuNDM0N0M5NS4wMDI2IDcwLjAzNjMgOTguNDkyMSA3MS40OTc5IDEwMi4xMzEgNzEuNDk3OVoiIGZpbGw9IndoaXRlIi8+CjxwYXRoIGQ9Ik0xMzEuMjUgMTcuNVYyNi4yNUgxNDguNzVWNDMuNzVIMTU3LjVWMjYuMjVIMTc1VjE3LjVIMTU3LjVWMEgxNDguNzVWMTcuNUgxMzEuMjVaIiBmaWxsPSIjMDBFMzk1Ii8+Cjwvc3ZnPgo=',
			40
		);
	}

	/**
	 * Main menu template
	 *
	 * @return void
	 */
	public function menu_page_template() {
		echo '<div id="accessibility-plus-app" class="accessibility-plus-app"></div>';
	}

	/**
	 * Returns Jed-formatted localization data. Added for backwards-compatibility.
	 *
	 * @since 4.0.0
	 *
	 * @param  string $domain Translation domain.
	 * @return array          The information of the locale.
	 */
	public function get_jed_locale_data( $domain ) {
		$translations = get_translations_for_domain( $domain );
		$locale       = array(
			'' => array(
				'domain' => $domain,
				'lang'   => is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale(),
			),
		);

		if ( ! empty( $translations->headers['Plural-Forms'] ) ) {
			$locale['']['plural_forms'] = $translations->headers['Plural-Forms'];
		}

		foreach ( $translations->entries as $msgid => $entry ) {
			$locale[ $msgid ] = $entry->translations;
		}

		// If any of the translated strings incorrectly contains HTML line breaks, we need to return or else the admin is no longer accessible.
		$json = wp_json_encode( $locale );
		if ( preg_match( '/<br[\s\/\\\\]*>/', $json ) ) {
			return array();
		}

		return $locale;
	}

	/**
	 * Modify plugin action links on plugin listing page.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$links[] = '<a href="https://wordpress.org/support/plugin/accessibility-plus/#new-topic-0" target="_blank">' . esc_html__( 'Support', 'accessibility-plus' ) . '</a>';
		$links[] = '<a href="' . get_admin_url( null, 'admin.php?page=accessibility-plus' ) . '">' . esc_html__( 'Settings', 'accessibility-plus' ) . '</a>';
		return array_reverse( $links );
	}
	/**
	 * Hide all the unrelated notices from plugin page.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function hide_admin_notices() {
		// Bail if we're not on a WeYes screen.
		if ( empty( $_REQUEST['page'] ) || ! preg_match( '/accessibility-plus/', esc_html( wp_unslash( $_REQUEST['page'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}
		global $wp_filter;

		$notices_type = array(
			'user_admin_notices',
			'admin_notices',
			'all_admin_notices',
		);

		foreach ( $notices_type as $type ) {
			if ( empty( $wp_filter[ $type ]->callbacks ) || ! is_array( $wp_filter[ $type ]->callbacks ) ) {
				continue;
			}

			foreach ( $wp_filter[ $type ]->callbacks as $priority => $hooks ) {

				foreach ( $hooks as $name => $arr ) {
					if ( is_object( $arr['function'] ) && $arr['function'] instanceof \Closure ) {
						unset( $wp_filter[ $type ]->callbacks[ $priority ][ $name ] );
						continue;
					}
					$class = ! empty( $arr['function'][0] ) && is_object( $arr['function'][0] ) ? strtolower( get_class( $arr['function'][0] ) ) : '';

					if ( ! empty( $class ) && preg_match( '/^(?:wya11y)/', $class ) ) {
						continue;
					}
					if ( ! empty( $name ) && ! preg_match( '/^(?:wya11y)/', $name ) ) {
						unset( $wp_filter[ $type ]->callbacks[ $priority ][ $name ] );
					}
				}
			}
		}
	}
}

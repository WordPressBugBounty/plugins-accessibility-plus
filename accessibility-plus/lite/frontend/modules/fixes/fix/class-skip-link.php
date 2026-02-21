<?php

/**
 * Class Skip_Link file.
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
 * Skip_Link class
 *
 * @since 2.0.0
 */
class Skip_Link {
		/**
	 * Load the fix
	 *
	 * @return void
	 */
	public function load() {
		$settings      = Settings::get_instance();
		$fix_skip_link = $settings->get( 'fixes', 'enableSkipLink' );
		if ( isset( $fix_skip_link['status'] ) && $fix_skip_link['status'] ) {
			add_action( 'wp_body_open', array( $this, 'add_skip_link' ) );
		}
	}

	/**
	 * Adds the skip link code to the page.
	 *
	 * @return void
	 */
	public function add_skip_link() {

		$settings       = Settings::get_instance();
		$targets_string = isset( $settings->get( 'fixes', 'enableSkipLink' )['targets'] ) ? $settings->get( 'fixes', 'enableSkipLink' )['targets'] : '';
		if ( ! $targets_string ) {
			return;
		}
		?>
		<template id="wya11y-skip-link-template">
		
				<?php
				if ( $targets_string ) :
					?>
					<a class="wya11y-skip-link-content wya11y-bypass-block" href=""><?php esc_html_e( 'Skip to content', 'accessibility-plus' ); ?></a>
				<?php endif; ?>
			<?php $this->add_skip_link_styles(); ?>
		</template>
		<?php
	}

	/**
	 * Injects the style rules for the skip link.
	 *
	 * @return void
	 */
	public function add_skip_link_styles() {
		?>
		<style id="wya11y-fix-skip-link-styles">
			.wya11y-bypass-block {
				border: 0;
				clip: rect(1px, 1px, 1px, 1px);
				clip-path: inset(50%);
				height: 1px;
				margin: -1px;
				overflow: hidden;
				padding: 0;
				position: absolute !important;
				width: 1px;
				word-wrap: normal !important;
			}

			.wya11y-bypass-block:focus-within {
				background-color: #ececec;
				clip: auto !important;
				-webkit-clip-path: none;
				clip-path: none;
				display: block;
				font-size: 1rem;
				height: auto;
				left: 5px;
				line-height: normal;
				padding: 8px 22px 10px;
				top: 5px;
				width: auto;
				z-index: 100000;
			}

			.admin-bar .wya11y-bypass-block,
			.admin-bar .wya11y-bypass-block:focus-within {
				top: 37px;
			}

			@media screen and (max-width: 782px) {
				.admin-bar .wya11y-bypass-block,
				.admin-bar .wya11y-bypass-block:focus-within {
					top: 51px;
				}
			}

			a.wya11y-bypass-block {
				display: block;
				margin: 0.5rem 0;
				color: #444;
				text-decoration: underline;
			}

			a.wya11y-bypass-block:hover,
			a.wya11y-bypass-block:focus {
				text-decoration: none;
				color: #006595;
			}

			a.wya11y-bypass-block:focus {
				outline: 2px solid #000;
				outline-offset: 2px;
			}
		</style>
		<?php
	}
}

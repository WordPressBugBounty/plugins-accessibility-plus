<?php
/**
 * Auditable URLs listing service.
 *
 * Builds the grouped list of URLs that are reachable by a guest or
 * logged-in visitor (excluding admin-only and synthetic URLs). The result
 * is cached in a transient and invalidated whenever site content changes.
 *
 * @package AccessibilityPlus
 */

namespace WebYes\AccessibilityPlus\Lite\Admin\Modules\Auditable_Urls\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auditable URLs listing service.
 *
 * @class Auditable_Urls
 */
class Auditable_Urls {

	const CACHE_KEY = 'wya11y_auditable_urls_v1';

	/**
	 * Maximum entries listed per post type. Caps the cold-cache rebuild so
	 * stores with very large catalogues don't OOM the REST request or
	 * exceed the object-cache item-size limit. Filterable for sites that
	 * deliberately want everything.
	 */
	const MAX_PER_POST_TYPE = 500;

	/**
	 * Singleton instance.
	 *
	 * @var Auditable_Urls|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Auditable_Urls
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Return the grouped list of auditable URLs.
	 *
	 * @param bool $force_refresh Bypass the transient cache when true.
	 * @return array<string,array<int,array{id:int,url:string,label:string,access:string}>>
	 */
	public function get( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$grouped = array();

		$special = $this->build_special_pages();
		if ( ! empty( $special ) ) {
			$grouped['Special pages'] = $special;
		}

		foreach ( $this->build_post_type_groups() as $label => $items ) {
			$grouped[ $label ] = $items;
		}

		foreach ( $this->build_taxonomy_archive_groups() as $label => $items ) {
			$grouped[ $label ] = $items;
		}

		$wc = $this->build_woocommerce_group();
		if ( ! empty( $wc ) ) {
			$grouped['WooCommerce'] = $wc;
		}

		set_transient( self::CACHE_KEY, $grouped, HOUR_IN_SECONDS );

		return $grouped;
	}

	/**
	 * Register cache invalidation hooks.
	 *
	 * Called once during module bootstrap.
	 *
	 * @return void
	 */
	public function register_cache_invalidation() {
		// Content lifecycle.
		add_action( 'save_post', array( $this, 'maybe_flush_cache_on_save_post' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'flush_cache' ) );
		add_action( 'trashed_post', array( $this, 'flush_cache' ) );
		add_action( 'untrashed_post', array( $this, 'flush_cache' ) );

		// Taxonomy term lifecycle.
		add_action( 'created_term', array( $this, 'flush_cache' ) );
		add_action( 'edited_term', array( $this, 'flush_cache' ) );
		add_action( 'delete_term', array( $this, 'flush_cache' ) );

		// Settings that change which URLs are emitted or what permalinks look like.
		$option_hooks = array(
			'update_option_users_can_register',
			'update_option_page_for_posts',
			'update_option_show_on_front',
			'update_option_page_on_front',
			'update_option_home',
			'update_option_siteurl',
			'update_option_permalink_structure',
			'permalink_structure_changed',
			'switch_theme',
			// WooCommerce flow-page reassignments. WC stores each as its own option,
			// so each gets its own update_option_* trigger.
			'update_option_woocommerce_shop_page_id',
			'update_option_woocommerce_cart_page_id',
			'update_option_woocommerce_checkout_page_id',
			'update_option_woocommerce_myaccount_page_id',
		);
		foreach ( $option_hooks as $hook ) {
			add_action( $hook, array( $this, 'flush_cache' ) );
		}
	}

	/**
	 * save_post fires on revisions, autosaves, and nav_menu_item saves — none
	 * of which change the auditable URL list. Filter those out so the
	 * transient isn't thrashed during normal editing sessions.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function maybe_flush_cache_on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		// nav_menu_item changes never affect public URLs.
		if ( 'nav_menu_item' === $post->post_type ) {
			return;
		}
		$this->flush_cache();
	}

	/**
	 * Delete the transient.
	 *
	 * @return void
	 */
	public function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Build the "Special pages" group (Home, Search, Blog).
	 *
	 * wp-login.php (login/register URLs) is intentionally excluded: it does
	 * not fire wp_head / wp_enqueue_scripts (it uses login_head /
	 * login_enqueue_scripts), so the scanner can't bootstrap there even if
	 * the admin selects it.
	 *
	 * @return array
	 */
	private function build_special_pages() {
		$items = array();

		$items[] = array(
			'id'     => 0,
			'url'    => home_url( '/' ),
			'label'  => __( 'Homepage', 'accessibility-plus' ),
			'access' => 'public',
		);

		$items[] = array(
			'id'     => 0,
			'url'    => home_url( '/?s=accessibility' ),
			'label'  => __( 'Search results page', 'accessibility-plus' ),
			'access' => 'public',
		);

		$page_for_posts = (int) get_option( 'page_for_posts' );
		if ( $page_for_posts ) {
			$permalink = get_permalink( $page_for_posts );
			if ( $permalink ) {
				$items[] = array(
					'id'     => $page_for_posts,
					'url'    => $permalink,
					'label'  => __( 'Blog page', 'accessibility-plus' ),
					'access' => 'public',
				);
			}
		}

		return $items;
	}

	/**
	 * Build per-post-type groups (publish + private, excluding attachments
	 * and password-protected entries).
	 *
	 * @return array<string,array>
	 */
	private function build_post_type_groups() {
		$groups     = array();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		/**
		 * Filter the per-post-type cap on entries returned to the auditor.
		 *
		 * @param int $limit Default MAX_PER_POST_TYPE.
		 */
		$limit = (int) apply_filters( 'wya11y_auditable_urls_per_type_limit', self::MAX_PER_POST_TYPE );
		if ( $limit < 1 ) {
			$limit = self::MAX_PER_POST_TYPE;
		}

		foreach ( $post_types as $type_slug => $type_obj ) {
			$posts = get_posts(
				array(
					'post_type'        => $type_slug,
					'post_status'      => array( 'publish', 'private' ),
					'has_password'     => false,
					'posts_per_page'   => $limit,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'no_found_rows'    => true,
					'suppress_filters' => false,
				)
			);

			if ( empty( $posts ) ) {
				continue;
			}

			$group_label = isset( $type_obj->labels->name ) && $type_obj->labels->name
				? $type_obj->labels->name
				: ucfirst( $type_slug );

			$items = array();
			foreach ( $posts as $post ) {
				$permalink = get_permalink( $post->ID );
				if ( ! $permalink ) {
					continue;
				}
				$is_private = ( 'private' === $post->post_status );
				$items[]    = array(
					'id'     => $post->ID,
					'url'    => $permalink,
					'label'  => $post->post_title,
					'access' => $is_private ? 'members' : 'public',
				);
			}

			if ( ! empty( $items ) ) {
				$groups[ $group_label ] = $items;
			}
		}

		return $groups;
	}

	/**
	 * Build per-taxonomy archive groups (terms with content only).
	 *
	 * @return array<string,array>
	 */
	private function build_taxonomy_archive_groups() {
		$groups     = array();
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		foreach ( $taxonomies as $tax_slug => $tax_obj ) {
			if ( 'post_format' === $tax_slug ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $tax_slug,
					'hide_empty' => true,
					'orderby'    => 'name',
				)
			);

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			$tax_name = isset( $tax_obj->labels->name ) && $tax_obj->labels->name
				? $tax_obj->labels->name
				: $tax_slug;

			/* translators: %s: taxonomy label, e.g. "Categories". */
			$group_label = sprintf( __( 'Archives — %s', 'accessibility-plus' ), $tax_name );

			$items = array();
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$items[] = array(
					'id'     => (int) $term->term_id,
					'url'    => $link,
					'label'  => $term->name . ' (' . (int) $term->count . ')',
					'access' => 'public',
				);
			}

			if ( ! empty( $items ) ) {
				$groups[ $group_label ] = $items;
			}
		}

		return $groups;
	}

	/**
	 * Build the WooCommerce group (flow pages + my-account sub-endpoints).
	 *
	 * @return array
	 */
	private function build_woocommerce_group() {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return array();
		}

		$items = array();
		$home  = home_url( '/' );

		$main_pages = array(
			'shop'      => array( __( 'Shop', 'accessibility-plus' ), 'public' ),
			'cart'      => array( __( 'Cart', 'accessibility-plus' ), 'public' ),
			'checkout'  => array( __( 'Checkout', 'accessibility-plus' ), 'public' ),
			'myaccount' => array( __( 'My Account', 'accessibility-plus' ), 'members' ),
		);

		foreach ( $main_pages as $key => $info ) {
			list( $label, $access ) = $info;
			$url = wc_get_page_permalink( $key );
			if ( ! $url || $url === $home ) {
				continue;
			}
			$items[] = array(
				'id'     => function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $key ) : 0,
				'url'    => $url,
				'label'  => $label,
				'access' => $access,
			);
		}

		$myaccount_url = wc_get_page_permalink( 'myaccount' );
		if ( $myaccount_url && function_exists( 'wc_get_endpoint_url' ) ) {
			$endpoints = array(
				'orders'          => __( 'My Account → Orders', 'accessibility-plus' ),
				'downloads'       => __( 'My Account → Downloads', 'accessibility-plus' ),
				'edit-address'    => __( 'My Account → Addresses', 'accessibility-plus' ),
				'payment-methods' => __( 'My Account → Payment Methods', 'accessibility-plus' ),
				'edit-account'    => __( 'My Account → Account Details', 'accessibility-plus' ),
			);

			foreach ( $endpoints as $endpoint => $label ) {
				$endpoint_url = wc_get_endpoint_url( $endpoint, '', $myaccount_url );
				if ( ! $endpoint_url ) {
					continue;
				}
				$items[] = array(
					'id'     => 0,
					'url'    => $endpoint_url,
					'label'  => $label,
					'access' => 'members',
				);
			}
		}

		return $items;
	}
}

<?php
/**
 * The class that sets up
 * global plugin functionality.
 *
 * This class is initiated on every page
 * load and does not have to be instantiated.
 *
 * @class       WPCampus_Notifications_Global
 * @category    Class
 * @package     WPCampus Notifications
 */
final class WPCampus_Notifications_Global {

	/**
	 * We don't need to instantiate this class.
	 */
	protected function __construct() {}

	/**
	 * Registers all of our hooks and what not.
	 */
	public static function register() {
		$plugin = new self();

		// Runs on activation and deactivation.
		register_activation_hook( __FILE__, array( $plugin, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $plugin, 'deactivate' ) );

		// Load our text domain.
		add_action( 'plugins_loaded', array( $plugin, 'textdomain' ) );

		// Register our post types.
		add_action( 'init', array( $plugin, 'register_cpts_taxonomies' ) );

		// Register our social media feeds.
		add_action( 'init', array( $plugin, 'add_feeds' ) );

		// Modify the query for our tweets feed.
		add_filter( 'rest_notification_query', array( $plugin, 'modify_notifications_api_query' ), 10, 2 );
		add_action( 'pre_get_posts', array( $plugin, 'modify_notifications_query' ), 100 );

		// Filter the notification query.
		add_filter( 'post_limits', array( $plugin, 'filter_post_limits' ), 100, 2 );
		add_filter( 'posts_clauses', array( $plugin, 'filter_posts_clauses' ), 100, 2 );

		// Filter content to get specific notification message.
		add_filter( 'the_content', array( $plugin, 'filter_the_content' ) );

		// Filter the notification permalink.
		add_filter( 'post_type_link', array( $plugin, 'filter_notifications_permalink' ), 100, 2 );

		// Modify the notification post before being listed in the API.
		add_filter( 'rest_prepare_notification', array( $plugin, 'modify_notifications_rest_post' ), 100, 3 );

	}

	/**
	 * This method runs when the plugin is activated.
	 *
	 * Since we're adding a custom post type, flushing
	 * rewrite rules when the plugin is activated is helpful
	 * to make sure permalinks and rewrites work correctly.
	 *
	 * @access  public
	 * @return  void
	 */
	public function activate() {
		flush_rewrite_rules( true );
	}

	/**
	 * This method runs when the plugin is deactivated.
	 *
	 * Since we're adding a custom post type, flushing
	 * rewrite rules when the plugin is deactivated is helpful
	 * to make sure permalinks and rewrites work correctly.
	 *
	 * @access  public
	 * @return  void
	 */
	public function deactivate() {
		flush_rewrite_rules( true );
	}

	/**
	 * Loads the plugin's text domain.
	 *
	 * @access  public
	 * @return  void
	 */
	public function textdomain() {
		load_plugin_textdomain( 'wpc-notifications', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Add our RSS feeds.
	 *
	 * @access public
	 * @return void
	 */
	public function add_feeds() {
		foreach ( wpcampus_notifications()->get_notification_feeds() as $feed ) {
			add_feed( $feed, array( $this, 'print_notification_feed' ) );
		}
	}

	/**
	 * Print our notification feeds.
	 *
	 * @access public
	 * @return void
	 */
	public function print_notification_feed() {
		require_once wpcampus_notifications()->get_plugin_dir() . 'inc/feed-notifications.php';
	}

	/**
	 * Modify the query for our notification API feed.
	 *
	 * Most of the modifications are taken care of by
	 * modify_notifications_query(). This only handles
	 * API specific notifications queries.
	 *
	 * @param array           $args    Key value array of query var to query value.
	 * @param WP_REST_Request $request The request used.
	 * @return array - filtered query.
	 */
	public function modify_notifications_api_query( $args, $request ) {

		if ( empty( $args['post_type'] ) || 'notification' != $args['post_type'] ) {
			return $args;
		}

		// Don't overwrite URL parameters.
		if ( ! isset( $_GET['per_page'] ) ) {
			$args['posts_per_page'] = 50;
		}

		return $args;
	}

	/**
	 * Modify the query for our notification queries.
	 *
	 * This also modifies the API query. It treats it
	 * as a post type archive.
	 *
	 * @param  $query - WP_Query - the query object.
	 * @return void
	 */
	public function modify_notifications_query( $query ) {

		// Not in the admin.
		if ( is_admin() ) {
			return;
		}

		// Only need to filter for notification archives/API and feeds.
		$is_notification_archive = $query->is_post_type_archive( 'notification' );
		if ( ! ( $is_notification_archive || $query->is_feed( wpcampus_notifications()->get_notification_feeds() ) ) ) {
			return;
		}

		$notification_format = '';

		if ( $is_notification_archive ) {

			// This takes care of archives and the API.
			$notification_format = 'website';

			// Can't set -1 for the API.
			if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
				$query->set( 'posts_per_page', -1 );
			}
		} else {

			// Get all the posts for feeds.
			$query->set( 'posts_per_rss', -1 );
			$query->set( 'posts_per_page', -1 );
			$query->set( 'nopaging', true );

			$notification_format = wpcampus_notifications()->get_query_feed_format( $query );

		}

		if ( ! empty( $notification_format ) ) {
			$query->set( 'notification_format', $notification_format );
			$query->set( 'tax_query', array(
				array(
					'taxonomy' => 'notification_format',
					'field'    => 'slug',
					'terms'    => $notification_format,
				),
			));
		}

		// Set the post type.
		$query->set( 'post_type', 'notification' );

	}

	/**
	 * Filters the notification post data for a REST response.
	 *
	 * @access  public
	 * @param   $response - WP_REST_Response - The response object.
	 * @param   $post - WP_Post - The Post object.
	 * @param   $request - WP_REST_Request - The Request object.
	 * @return  WP_REST_Response - the filtered response
	 */
	public function modify_notifications_rest_post( $response, $post, $request ) {

		// Remove certain fields.
		$fields = array( 'guid', 'modified', 'modified_gmt', 'slug', 'status', 'template', 'type' );
		foreach ( $fields as $field ) {
			if ( isset( $response->data[ $field ] ) ) {
				unset( $response->data[ $field ] );
			}
		}

		// Get content.
		$content = wpcampus_notifications()->get_notification_message( $response->data['id'], 'website' );
		$response->data['content'] = array(
			'basic'     => strip_tags( $content, '<a>' ),
			'rendered'  => wpautop( $content ),
			'protected' => false,
		);

		return $response;
	}

	/**
	 * Remove the limits for notification feeds.
	 *
	 * @param  string   $limits The LIMIT clause of the query.
	 * @param  WP_Query $query The WP_Query instance (passed by reference).
	 * @return string - filtered limits.
	 */
	public function filter_post_limits( $limits, $query ) {

		// Remove limits for notification feeds.
		if ( $query->is_feed( wpcampus_notifications()->get_notification_feeds() ) ) {
			return '';
		}

		return $limits;
	}

	/**
	 * Filters all query clauses at once, for convenience.
	 *
	 * @access  public
	 * @param   $clauses - array - The list of clauses for the query.
	 * @param   $query - WP_Query - The WP_Query instance (passed by reference).
	 * @return  array - the filtered clauses.
	 */
	public function filter_posts_clauses( $clauses, $query ) {
		global $wpdb;

		// Only for notification post type.
		if ( 'notification' != $query->get( 'post_type' ) ) {
			return $clauses;
		}

		// Not in the admin.
		if ( is_admin() ) {
			return $clauses;
		}

		// Only get notifications that have a message.
		$notification_format = $query->get( 'notification_format' );
		if ( empty( $notification_format ) || ! in_array( $notification_format, wpcampus_notifications()->get_notification_formats() ) ) {
			$notification_format = 'website';
		}

		$clauses['join'] .= $wpdb->prepare( " INNER JOIN {$wpdb->postmeta} format ON format.post_id = {$wpdb->posts}.ID AND format.meta_key = %s AND format.meta_value != ''", "{$notification_format}_message" );

		// LEFT JOIN to get post meta.
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_deact ON wpc_nf_deact.post_id = {$wpdb->posts}.ID AND wpc_nf_deact.meta_key = 'wpc_notif_deactivate'";
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_sticky ON wpc_sticky.post_id = {$wpdb->posts}.ID AND wpc_sticky.meta_key = 'sticky'";
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_sdt ON wpc_nf_sdt.post_id = {$wpdb->posts}.ID AND wpc_nf_sdt.meta_key = 'wpc_notif_start_dt'";
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_edt ON wpc_nf_edt.post_id = {$wpdb->posts}.ID AND wpc_nf_edt.meta_key = 'wpc_notif_end_dt'";

		// Check the data in WHERE.
		$clauses['where'] .= ' AND IF ( wpc_nf_deact.meta_value IS NOT NULL AND wpc_nf_deact.meta_value != "", false, true )';
		$clauses['where'] .= ' AND IF ( wpc_nf_sdt.meta_value IS NOT NULL, CONVERT( wpc_nf_sdt.meta_value, DATETIME ) <= NOW(), true ) AND IF ( wpc_nf_edt.meta_value IS NOT NULL, CONVERT( wpc_nf_edt.meta_value, DATETIME ) > NOW(), true )';

		$clauses['orderby'] = "IF ( wpc_sticky.meta_value IS NOT NULL, wpc_sticky.meta_value, 0 ) DESC, IF ( wpc_nf_edt.meta_value IS NULL OR wpc_nf_edt.meta_value = '', 1, 0 ) DESC, IF ( wpc_nf_edt.meta_value IS NOT NULL, CONVERT( wpc_nf_edt.meta_value, DATETIME ), 0 ) DESC, IF ( wpc_nf_sdt.meta_value IS NOT NULL, CONVERT( wpc_nf_sdt.meta_value, DATETIME ), 0 ) DESC, {$wpdb->posts}.post_date DESC";

		return $clauses;
	}

	/**
	 * Filter the content to return
	 * format specific notification messages.
	 *
	 * @param  $content - string - the content.
	 * @return string - the filtered content.
	 */
	public function filter_the_content( $content ) {

		// Only for notification post type.
		if ( 'notification' != get_post_type() ) {
			return $content;
		}

		// Do we have a format?
		$format = get_query_var( 'notification_format' );
		if ( empty( $format ) || ! in_array( $format, wpcampus_notifications()->get_notification_formats() ) ) {
			$format = 'website';
		}

		return wpcampus_notifications()->get_notification_message( get_the_ID(), $format );
	}

	/**
	 * Filters the permalinks for the notifications
	 * post type to point them to the archive.
	 *
	 * @access  public
	 * @param   $post_link - string - The post's permalink.
	 * @param   $post - WP_Post - The post in question.
	 * @return  string - the filtered permalink.
	 */
	public function filter_notifications_permalink( $post_link, $post ) {
		if ( 'notification' == $post->post_type ) {
			return wpcampus_notifications()->get_notification_permalink( $post->ID );
		}
		return $post_link;
	}

	/**
	 * Register the custom post types.
	 *
	 * @access  public
	 * @return  void
	 */
	public function register_cpts_taxonomies() {

		// Define the notification post type labels.
		$notification_labels = array(
			'name'                  => _x( 'Notifications', 'Post Type General Name', 'wpc-notifications' ),
			'singular_name'         => _x( 'Notification', 'Post Type Singular Name', 'wpc-notifications' ),
			'menu_name'             => __( 'Notifications', 'wpc-notifications' ),
			'name_admin_bar'        => __( 'Notifications', 'wpc-notifications' ),
			'archives'              => __( 'Notification Archives', 'wpc-notifications' ),
			'attributes'            => __( 'Notification Attributes', 'wpc-notifications' ),
			'all_items'             => __( 'All Notifications', 'wpc-notifications' ),
			'add_new_item'          => __( 'Add New Notification', 'wpc-notifications' ),
			'new_item'              => __( 'New Notification', 'wpc-notifications' ),
			'edit_item'             => __( 'Edit Notification', 'wpc-notifications' ),
			'update_item'           => __( 'Update Notification', 'wpc-notifications' ),
			'view_item'             => __( 'View Notification', 'wpc-notifications' ),
			'view_items'            => __( 'View Notifications', 'wpc-notifications' ),
			'search_items'          => __( 'Search Notification', 'wpc-notifications' ),
			'insert_into_item'      => __( 'Insert into notification', 'wpc-notifications' ),
			'uploaded_to_this_item' => __( 'Uploaded to this notification', 'wpc-notifications' ),
			'items_list'            => __( 'Notifications list', 'wpc-notifications' ),
			'items_list_navigation' => __( 'Notifications list navigation', 'wpc-notifications' ),
			'filter_items_list'     => __( 'Filter notifications list', 'wpc-notifications' ),
		);

		// Define the notification post type arguments.
		$notification_args = array(
			'label'                 => __( 'Notifications', 'wpc-notifications' ),
			'labels'                => $notification_labels,
			'supports'              => array( 'title', 'thumbnail', 'revisions' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_icon'             => 'dashicons-info',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'notification',
			'map_meta_cap'          => true,
			'show_in_rest'          => true,
			'rest_base'             => 'notifications',
			'rewrite'               => array(
				'slug'              => 'notifications',
			),
		);

		// Register the notifications post type.
		register_post_type( 'notification', $notification_args );

		// Register the notification formats taxonomy.
		register_taxonomy( 'notification_format', array( 'notification' ), array(
			'label'             => __( 'Formats', 'wpc-notifications' ),
			'labels'            => array(
				'name'                          => _x( 'Formats', 'Taxonomy General Name', 'wpc-notifications' ),
				'singular_name'                 => _x( 'Format', 'Taxonomy Singular Name', 'wpc-notifications' ),
				'menu_name'                     => __( 'Formats', 'wpc-notifications' ),
				'all_items'                     => __( 'All Formats', 'wpc-notifications' ),
				'parent_item'                   => __( 'Parent Format', 'wpc-notifications' ),
				'parent_item_colon'             => __( 'Parent Format:', 'wpc-notifications' ),
				'new_item_name'                 => __( 'New Format Name', 'wpc-notifications' ),
				'add_new_item'                  => __( 'Add New Format', 'wpc-notifications' ),
				'edit_item'                     => __( 'Edit Format', 'wpc-notifications' ),
				'update_item'                   => __( 'Update Format', 'wpc-notifications' ),
				'view_item'                     => __( 'View Format', 'wpc-notifications' ),
				'separate_items_with_commas'    => __( 'Separate formats with commas', 'wpc-notifications' ),
				'add_or_remove_items'           => __( 'Add or remove formats', 'wpc-notifications' ),
				'popular_items'                 => __( 'Popular Formats', 'wpc-notifications' ),
				'search_items'                  => __( 'Search Formats', 'wpc-notifications' ),
				'no_terms'                      => __( 'No formats', 'wpc-notifications' ),
				'items_list'                    => __( 'Formats list', 'wpc-notifications' ),
				'items_list_navigation'         => __( 'Formats list', 'wpc-notifications' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_in_menu'      => 'edit.php?post_type=notification',
			'show_tagcloud'     => false,
			'show_in_rest'      => false,
		));
	}
}
WPCampus_Notifications_Global::register();

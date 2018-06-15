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
		add_action( 'init', array( $plugin, 'register_cpts' ) );

		// Filter the notification query.
		add_filter( 'posts_clauses', array( $plugin, 'filter_posts_clauses' ), 100, 2 );

		// Filter the notification permalink.
		add_filter( 'post_type_link', array( $plugin, 'filter_notifications_permalink' ), 100, 2 );

		// Modify the notification post before being listed in the API.
		add_filter( 'rest_prepare_notification', array( $plugin, 'modify_notifications_rest_post' ), 100, 3 );

	}

	/**
	 * This method runs when the plugin is activated.
	 *
	 * @access  public
	 * @return  void
	 */
	public function activate() {

		/*
		 * Since we're adding a custom post type, flushing
		 * rewrite rules when the plugin is activated is helpful
		 * to make sure permalinks and rewrites work correctly.
		 */
		flush_rewrite_rules( true );

	}

	/**
	 * This method runs when the plugin is deactivated.
	 *
	 * @access  public
	 * @return  void
	 */
	public function deactivate() {

		/*
		 * Since we're adding a custom post type, flushing
		 * rewrite rules when the plugin is deactivated is helpful
		 * to make sure permalinks and rewrites work correctly.
		 */
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
	 * Register the custom post types.
	 *
	 * @access  public
	 * @return  void
	 */
	public function register_cpts() {

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
			'supports'              => array(
				'title',
				'editor',
				'thumbnail',
				'revisions',
			),
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

		// LEFT JOIN to get post meta.
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_deact ON wpc_nf_deact.post_id = {$wpdb->posts}.ID AND wpc_nf_deact.meta_key = 'wpc_notif_deactivate'";
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_sdt ON wpc_nf_sdt.post_id = {$wpdb->posts}.ID AND wpc_nf_sdt.meta_key = 'wpc_notif_start_dt'";
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_edt ON wpc_nf_edt.post_id = {$wpdb->posts}.ID AND wpc_nf_edt.meta_key = 'wpc_notif_end_dt'";

		// Check the data in WHERE.
		$clauses['where'] .= ' AND IF ( wpc_nf_deact.meta_value IS NOT NULL AND wpc_nf_deact.meta_value != "", false, true )';
		$clauses['where'] .= ' AND IF ( wpc_nf_sdt.meta_value IS NOT NULL, CONVERT( wpc_nf_sdt.meta_value, DATETIME ) <= NOW(), true ) AND IF ( wpc_nf_edt.meta_value IS NOT NULL, CONVERT( wpc_nf_edt.meta_value, DATETIME ) > NOW(), true )';

		return $clauses;
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
			return get_post_type_archive_link( 'notification' );
		}
		return $post_link;
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

		// Adding a "basic" field to content stripped of specific HTML tags.
		if ( ! empty( $response->data['content']['rendered'] ) ) {
			$response->data['content']['basic'] = strip_tags( $response->data['content']['rendered'], '<a>' );
		} else {
			$response->data['content']['basic'] = '';
		}

		return $response;
	}
}
WPCampus_Notifications_Global::register();

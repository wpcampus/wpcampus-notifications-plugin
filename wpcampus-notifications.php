<?php
/**
 * Plugin Name:     WPCampus Notifications
 * Plugin URI:      https://github.com/wpcampus/wpcampus-notifications-plugin
 * Description:     Handles notification functionality for WPCampus websites.
 * Version:         1.0.0
 * Author:          WPCampus
 * Author URI:      https://wpcampus.org
 * Text Domain:     wpc-notifications
 * Domain Path:     /languages
 *
 * @package         WPCampus Notifications
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// We only need admin functionality in the admin.
if ( is_admin() ) {
	require_once wpcampus_notifications()->plugin_dir . 'inc/admin.php';
}

/**
 * PHP class that holds the main/administrative
 * functionality for the plugin.
 *
 * @since       1.0.0
 * @category    Class
 * @package     WPCampus Notifications
 */
class WPCampus_Notifications {

	/**
	 * Holds the absolute URL to
	 * the main plugin directory.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @var     string
	 */
	public $plugin_url;

	/**
	 * Holds the directory path
	 * to the main plugin directory.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @var     string
	 */
	public $plugin_dir;

	/**
	 * Holds the class instance.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     WPCampus_Notifications
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @return  WPCampus_Notifications
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			$class_name = __CLASS__;
			self::$instance = new $class_name;
		}
		return self::$instance;
	}

	/**
	 * Constructing the class object.
	 *
	 * The constructor is protected to prevent
	 * creating a new instance from outside of this class.
	 *
	 * @since   1.0.0
	 * @access  protected
	 */
	protected function __construct() {

		// Store the plugin URL and DIR.
		$this->plugin_url = plugin_dir_url( __FILE__ );
		$this->plugin_dir = plugin_dir_path( __FILE__ );

		// Runs on activation and deactivation.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Load our textdomain.
		add_action( 'plugins_loaded', array( $this, 'textdomain' ) );

		// Register our post types.
		add_action( 'init', array( $this, 'register_cpts' ) );

		// Filter the notification query.
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ), 100, 2 );

		// Filter the notification permalink.
		add_filter( 'post_type_link', array( $this, 'filter_notifications_permalink' ), 100, 2 );

		// Modify the notification post before being listed in the API.
		add_filter( 'rest_prepare_notification', array( $this, 'modify_notifications_rest_post' ), 100, 3 );

	}

	/**
	 * Having a private clone and wakeup
	 * method prevents cloning of the instance.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @return  void
	 */
	private function __clone() {}
	private function __wakeup() {}

	/**
	 * This method runs when the plugin is activated.
	 *
	 * @since   1.0.0
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
	 * @since   1.0.0
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
	 * @since   1.0.0
	 * @access  public
	 * @return  void
	 */
	public function textdomain() {
		load_plugin_textdomain( 'wpc-notifications', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Register the custom post types.
	 *
	 * @since   1.0.0
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
	 * @since   1.0.0
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

		// LEFT JOIN to get post meta.
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_sdt ON wpc_nf_sdt.post_id = {$wpdb->posts}.ID AND wpc_nf_sdt.meta_key = 'wpc_notif_start_dt'";
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_edt ON wpc_nf_edt.post_id = {$wpdb->posts}.ID AND wpc_nf_edt.meta_key = 'wpc_notif_end_dt'";

		// Check the data in WHERE.
		$clauses['where'] .= ' AND IF ( wpc_nf_sdt.meta_value IS NOT NULL, CONVERT( wpc_nf_sdt.meta_value, DATETIME ) <= NOW(), true ) AND IF ( wpc_nf_edt.meta_value IS NOT NULL, CONVERT( wpc_nf_edt.meta_value, DATETIME ) > NOW(), true )';

		return $clauses;
	}

	/**
	 * Filters the permalinks for the notifications
	 * post type to point them to the archive.
	 *
	 * @since   1.0.0
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
	 * @since   1.0.0
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

		return $response;
	}
}

/**
 * Returns the instance of our main WPCampus_Notifications class.
 *
 * Will come in handy when we need to access the
 * class to retrieve data throughout the plugin
 * and other plugins and themes.
 *
 * @return object - WPCampus_Notifications
 */
function wpcampus_notifications() {
	return WPCampus_Notifications::instance();
}

// Let's get this party started.
wpcampus_notifications();

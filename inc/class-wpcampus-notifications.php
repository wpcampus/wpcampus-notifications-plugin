<?php

/**
 * PHP class that holds the main/administrative
 * functionality for the plugin.
 *
 * @category    Class
 * @package     WPCampus: Notifications
 */
final class WPCampus_Notifications {

	/**
	 * Holds the absolute URL to
	 * the main plugin directory.
	 * Used for assets.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Holds the directory path
	 * to the main plugin directory.
	 * Used to require PHP files.
	 *
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * Holds the relative "path"
	 * to the main plugin file.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Holds the class instance.
	 *
	 * @var WPCampus_Notifications
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @return WPCampus_Notifications
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			$class_name     = __CLASS__;
			self::$instance = new $class_name;
		}
		return self::$instance;
	}

	/**
	 * Magic method to output a string if
	 * trying to use the object as a string.
	 *
	 * @return string
	 */
	public function __toString() {
		return __( 'WPCampus Notifications', 'wpc-notifications' );
	}

	/**
	 * Method to keep our instance
	 * from being cloned or unserialized
	 * and to prevent a fatal error when
	 * calling a method that doesn't exist.
	 *
	 * @return void
	 */
	public function __clone() {}
	public function __wakeup() {}
	public function __call( $method = '', $args = array() ) {}

	/**
	 * Start your engines.
	 *
	 * @return void
	 */
	protected function __construct() {}

	/**
	 * Returns the absolute URL to
	 * the main plugin directory.
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		if ( isset( $this->plugin_url ) ) {
			return $this->plugin_url;
		}
		$this->plugin_url = plugin_dir_url( dirname( __FILE__ ) );
		return $this->plugin_url;
	}

	/**
	 * Returns the directory path
	 * to the main plugin directory.
	 *
	 * @return string
	 */
	public function get_plugin_dir() {
		if ( isset( $this->plugin_dir ) ) {
			return $this->plugin_dir;
		}
		$this->plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
		return $this->plugin_dir;
	}

	/**
	 * Returns the relative "path"
	 * to the main plugin file.
	 *
	 * @return string
	 */
	public function get_plugin_basename() {
		if ( isset( $this->plugin_basename ) ) {
			return $this->plugin_basename;
		}
		$this->plugin_basename = 'wpcampus-notifications-plugin/wpcampus-notifications.php';
		return $this->plugin_basename;
	}

	/**
	 * Returns the current status of a notification.
	 *
	 * Options: active, deactivated, future, expired.
	 *
	 * @access  public
	 * @param   $post_id - int - the post ID.
	 * @return  string - the status
	 */
	public function get_notification_status( $post_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT
			IF ( 'publish' != posts.post_status, 'pending', IF ( wpc_nf_deact.meta_value IS NOT NULL AND wpc_nf_deact.meta_value != '', 'deactivated', IF ( wpc_nf_edt.meta_value IS NOT NULL AND CONVERT( wpc_nf_edt.meta_value, DATETIME ) <= NOW(), 'expired', IF ( wpc_nf_sdt.meta_value IS NOT NULL AND CONVERT( wpc_nf_sdt.meta_value, DATETIME ) > NOW(), 'future', 'active' ) ) ) )
			FROM {$wpdb->posts} posts
			LEFT JOIN {$wpdb->postmeta} wpc_nf_deact ON wpc_nf_deact.post_id = posts.ID AND wpc_nf_deact.meta_key = 'wpc_notif_deactivate'
			LEFT JOIN {$wpdb->postmeta} wpc_nf_sdt ON wpc_nf_sdt.post_id = posts.ID AND wpc_nf_sdt.meta_key = 'wpc_notif_start_dt'
			LEFT JOIN {$wpdb->postmeta} wpc_nf_edt ON wpc_nf_edt.post_id = posts.ID AND wpc_nf_edt.meta_key = 'wpc_notif_end_dt'
			WHERE %d = posts.ID AND 'notification' = posts.post_type", $post_id ) );
	}
}

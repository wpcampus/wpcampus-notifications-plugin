<?php
/**
 * Plugin Name:     WPCampus: Notifications
 * Plugin URI:      https://github.com/wpcampus/wpcampus-notifications-plugin
 * Description:     Manages notifications on main WPCampus website to share with network.
 * Version:         1.0.0
 * Author:          WPCampus
 * Author URI:      https://wpcampus.org
 * Text Domain:     wpc-notifications
 * Domain Path:     /languages
 *
 * @package         WPCampus: Notifications
 */

defined( 'ABSPATH' ) or die();

/*
 * Load plugin files.
 */
$plugin_dir = plugin_dir_path( __FILE__ );

// Load the main Event_Schedule class and global functionality.
require_once $plugin_dir . 'inc/class-wpcampus-notifications.php';
require_once $plugin_dir . 'inc/class-wpcampus-notifications-global.php';

// Load admin functionality in the admin.
if ( is_admin() ) {
	require_once $plugin_dir . 'inc/class-wpcampus-notifications-admin.php';
}

/**
 * Returns the instance of our main WPCampus_Notifications class.
 *
 * Use this function and class methods to retrieve plugin data.
 *
 * @return object - WPCampus_Notifications
 */
function wpcampus_notifications() {
	return WPCampus_Notifications::instance();
}

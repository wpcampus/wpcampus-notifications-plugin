<?php
/**
 * PHP class that holds the admin
 * functionality for the plugin.
 *
 * @category    Class
 * @package     WPCampus Notifications
 */
class WPCampus_Notifications_Admin {

	/**
	 * Holds the class instance.
	 *
	 * @access  private
	 * @var     WPCampus_Notifications_Admin
	 */
	private static $instance;

	/**
	 * Returns the instance of this class.
	 *
	 * @access  public
	 * @return  WPCampus_Notifications_Admin
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
	 * @access  protected
	 */
	protected function __construct() {

		// Add styles and scripts in the admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );

		// Filter the post class.
		add_filter( 'post_class', array( $this, 'filter_post_class' ), 10, 3 );

		// Filter the notification query.
		add_filter( 'posts_clauses', array( $this, 'filter_posts_clauses' ), 100, 2 );

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 1, 2 );

		// Save meta box data for notifications.
		add_action( 'save_post_notification', array( $this, 'save_meta_box_data' ), 10, 3 );

		// Add/manage notification columns.
		add_filter( 'manage_notification_posts_columns', array( $this, 'manage_notification_columns' ) );
		add_action( 'manage_notification_posts_custom_column', array( $this, 'manage_notification_column_values' ), 10, 2 );

	}

	/**
	 * Having a private clone and wakeup
	 * method prevents cloning of the instance.
	 *
	 * @access  private
	 * @return  void
	 */
	private function __clone() {}
	private function __wakeup() {}

	/**
	 * Returns the current status of a notification.
	 *
	 * Options: active, deactivated, future, expired.
	 *
	 * @since   1.0.0
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

	/**
	 * Add styles and scripts in the admin.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param	string - $hook_suffix - the ID of the current page
	 */
	public function enqueue_styles_scripts( $hook_suffix ) {
		global $post_type;

		// Only for notification screens.
		if ( 'notification' != $post_type ) {
			return;
		}

		// Only for the edit notification edit screens.
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) ) {

			// Register the timepicker script.
			wp_register_script( 'timepicker', '//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js', array( 'jquery' ), null, true );

			// Enqueue the notification script.
			wp_enqueue_script( 'wpc-notifications-admin', trailingslashit( wpcampus_notifications()->plugin_url ) . 'assets/js/admin-post.min.js', array( 'jquery', 'jquery-ui-datepicker', 'timepicker' ), null, true );

			// Enqueue the various style dependencies.
			wp_enqueue_style( 'jquery-ui', '//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css', array(), null );
			wp_enqueue_style( 'timepicker', '//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css', array(), null );

		}

		// Only need our styles on these pages.
		if ( in_array( $hook_suffix, array( 'edit.php', 'post.php', 'post-new.php' ) ) ) {
			wp_enqueue_style( 'wpc-notifications-admin', trailingslashit( wpcampus_notifications()->plugin_url ) . 'assets/css/admin.min.css', array(), null );
		}

	}

	/**
	 * Filters the list of CSS classes for the current post.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param   $classes - array - An array of post classes.
	 * @param   $class - array - An array of additional classes added to the post.
	 * @param   $post_id - int - The post ID.
	 * @return  array - the filtered post classes
	 */
	public function filter_post_class( $classes, $class, $post_id ) {

		// Only for notification post type.
		if ( 'notification' != get_post_type( $post_id ) ) {
			return $classes;
		}

		// Get/add the status.
		$status = $this->get_notification_status( $post_id );
		if ( $status ) {
			$classes[] = "wpc-notif-{$status}";
		}

		return $classes;
	}

	/**
	 * Filters all query clauses at once, for convenience.
	 *
	 * In the admin, we want to order the notifications by status.
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

		// Only for the main query.
		if ( ! $query->is_main_query() ) {
			return $clauses;
		}

		// LEFT JOIN to get post meta.
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_deact ON wpc_nf_deact.post_id = {$wpdb->posts}.ID AND wpc_nf_deact.meta_key = 'wpc_notif_deactivate'";
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_sdt ON wpc_nf_sdt.post_id = {$wpdb->posts}.ID AND wpc_nf_sdt.meta_key = 'wpc_notif_start_dt'";
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wpc_nf_edt ON wpc_nf_edt.post_id = {$wpdb->posts}.ID AND wpc_nf_edt.meta_key = 'wpc_notif_end_dt'";

		// ORDERBY status: active, future, expired, deactivated
		$clauses['orderby'] = "IF ( wpc_nf_deact.meta_value IS NOT NULL AND wpc_nf_deact.meta_value != '', 4, IF ( wpc_nf_edt.meta_value IS NOT NULL AND CONVERT( wpc_nf_edt.meta_value, DATETIME ) <= NOW(), 3, IF ( wpc_nf_sdt.meta_value IS NOT NULL AND CONVERT( wpc_nf_sdt.meta_value, DATETIME ) > NOW(), 2, 1 ) ) ) ASC";

		return $clauses;
	}

	/**
	 * Adds our admin meta boxes.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @return  void
	 */
	public function add_meta_boxes( $post_type, $post ) {

		// Add meta boxes for our notifications.
		if ( 'notification' == $post_type ) {

			// Notification Status.
			add_meta_box(
				'wpc-notifications-status',
				__( 'Notification Status', 'wpc-notifications' ),
				array( $this, 'print_meta_boxes' ),
				$post_type,
				'normal',
				'high'
			);

			// Notification Details.
			add_meta_box(
				'wpc-notifications-details',
				__( 'Notification Details', 'wpc-notifications' ),
				array( $this, 'print_meta_boxes' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Prints the content in our admin meta boxes.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @return  void
	 */
	public function print_meta_boxes( $post, $metabox ) {
		switch ( $metabox['id'] ) {

			case 'wpc-notifications-status':
				$this->print_notification_status_form( $post->ID );
				break;

			case 'wpc-notifications-details':
				$this->print_notification_details_form( $post->ID );
				break;
		}
	}

	/**
	 * When the post is saved, saves our custom meta box data.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @param	int - $post_id - the ID of the post being saved
	 * @param	WP_Post - $post - the post object
	 * @param	bool - $update - whether this is an existing post being updated or not
	 */
	function save_meta_box_data( $post_id, $post, $update ) {

		// Pointless if $_POST is empty (this happens on bulk edit).
		if ( empty( $_POST ) ) {
			return;
		}

		// Disregard on autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Don't save for revisions.
		if ( isset( $post->post_type ) && 'revision' == $post->post_type ) {
			return;
		}

		// Check our nonce for updating details.
		if ( isset( $_POST['wpc_notifications_save_details_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['wpc_notifications_save_details_nonce'], 'wpc_notifications_save_details' ) ) {

				/*
				 * Update the deactivate field.
				 */

				// The default deactivate value is null, or false.
				$deactivate_value = null;

				// Get the deactivate form field.
				if ( isset( $_POST['wpc_notifications']['deactivate'] ) ) {

					// Sanitize the value.
					$field_value = sanitize_text_field( $_POST['wpc_notifications']['deactivate'] );

					// If "truthy", set to deactivate.
					if ( $field_value ) {
						$deactivate_value = 1;
					}
				}

				// Update the deactivate meta.
				update_post_meta( $post_id, 'wpc_notif_deactivate', $deactivate_value );

				/*
				 * Update the date/time fields.
				 */

				// Get site timezone.
				$site_timezone = get_option( 'timezone_string' );
				if ( ! $site_timezone ) {
					$site_timezone = 'UTC';
				}

				// Will hold the date/time fields.
				$dt_fields = array();

				// Get/validate each field.
				foreach ( array( 'start_date', 'end_date', 'start_time', 'end_time' ) as $field_key ) {
					if ( isset( $_POST['wpc_notifications'][ $field_key ] ) ) {

						// Sanitize the value.
						$field_value = sanitize_text_field( $_POST['wpc_notifications'][ $field_key ] );

						// Make sure it's a valid string.
						if ( false == strtotime( $field_value ) ) {
							$field_value = null;
						}

						// Store the value.
						$dt_fields[ $field_key ] = $field_value;

					}
				}

				// Process dates.
				foreach ( array( 'start', 'end' ) as $field_key ) {

					// Get the date fields.
					$this_date = $dt_fields[ $field_key . '_date' ];
					$this_time = $dt_fields[ $field_key . '_time' ];

					// If no date, then clear the meta.
					if ( ! $this_date ) {
						update_post_meta( $post_id, "wpc_notif_{$field_key}_dt", null );
						continue;
					}

					// Make sure we have a time.
					if ( ! $this_time ) {
						$this_time = '00:00:00';
					} else {

						// Convert time format.
						$this_time = date( 'H:i:s', strtotime( $this_time ) );

					}

					// Create the proper date.
					$this_date = new DateTime( $this_date . ' ' . $this_time, new DateTimeZone( $site_timezone ) );

					// Convert the timezone TO UTC for storage.
					$this_date->setTimezone( new DateTimeZone( 'UTC' ) );

					// Update the post meta.
					update_post_meta( $post_id, "wpc_notif_{$field_key}_dt", $this_date->format( 'Y-m-d H:i:s' ) );

				}
			}
		}
	}

	/**
	 * Print the notification status form.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param   $post_id - int - the post ID.
	 * @return  void
	 */
	public function print_notification_status_form( $post_id ) {

		// Build wrapper classes
		$wrapper_class = array( 'wpc-notif-status-wrapper' );

		// Get/add the status.
		$status = $this->get_notification_status( $post_id );
		if ( $status ) {
			$wrapper_class[] = "wpc-notif-{$status}";
		}

		?>
		<div class="<?php echo implode( ' ', $wrapper_class ); ?>">
			<?php

			switch ( $status ) {

				case 'active':
					?><p><?php _e( 'This notification is <strong>active</strong>.', 'wpc-notifications' ); ?></p><?php
					break;

				case 'deactivated':
					?><p><?php _e( 'This notification has been <strong>deactivated</strong>.', 'wpc-notifications' ); ?></p><?php
					break;

				case 'expired':
					?><p><?php _e( 'This notification has <strong>expired</strong>.', 'wpc-notifications' ); ?></p><?php
					break;

				case 'future':
					?><p><?php _e( 'This notification is scheduled for the <strong>future</strong>.', 'wpc-notifications' ); ?></p><?php
					break;

				case 'pending':
				default:
					?><p><?php _e( 'This notification is <strong>pending</strong>.', 'wpc-notifications' ); ?></p><?php
					break;

			}

			?>
		</div>
		<?php
	}

	/**
	 * Print the notification details form.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param   $post_id - int - the post ID.
	 * @return  void
	 */
	public function print_notification_details_form( $post_id ) {

		// Add a nonce field so we can check for it when saving the data.
		wp_nonce_field( 'wpc_notifications_save_details', 'wpc_notifications_save_details_nonce' );

		// Get site timezone.
		$site_timezone_string = get_option( 'timezone_string' );
		if ( ! $site_timezone_string ) {
			$site_timezone_string = 'UTC';
		}

		// Convert to DateTimeZone.
		$site_timezone = new DateTimeZone( $site_timezone_string );

		// Set the date/time formats.
		$date_format = 'Y-m-d';
		$date_display_format = 'F j, Y';
		$time_display_format = 'g:i A';

		// Get saved details.
		$notif_deactivate = get_post_meta( $post_id, 'wpc_notif_deactivate', true );
		$notif_start_dt = get_post_meta( $post_id, 'wpc_notif_start_dt', true );
		$notif_end_dt = get_post_meta( $post_id, 'wpc_notif_end_dt', true );

		// Setup start and end date/time.
		foreach ( array( 'start', 'end' ) as $field_key ) {

			// Reference the variable.
			$date_value = &${"notif_{$field_key}_dt"};

			// Make sure its a valid date value.
			if ( ! $date_value || false === strtotime( $date_value ) ) {
				$date_value = null;
				continue;
			}

			// Convert to DateTime.
			$date_value = $date_value ? new DateTime( $date_value ) : null;

			// Convert the timezone.
			$date_value->setTimezone( $site_timezone );

		}

		// Prepare dates and times for display.
		$notif_start_date_display = $notif_start_dt ? $notif_start_dt->format( $date_display_format ) : null;
		$notif_start_date_alt = $notif_start_dt ? $notif_start_dt->format( $date_format ) : null;
		$notif_start_time_display = $notif_start_dt ? $notif_start_dt->format( $time_display_format ) : null;

		$notif_end_date_display = $notif_end_dt ? $notif_end_dt->format( $date_display_format ) : null;
		$notif_end_date_alt = $notif_end_dt ? $notif_end_dt->format( $date_format ) : null;
		$notif_end_time_display = $notif_end_dt ? $notif_end_dt->format( $time_display_format ) : null;

		?>
		<p><?php _e( 'The following start and end settings will limit when the notification will be displayed.', 'wpc-notifications' ); ?></p>
		<p class="description"><?php printf( __( "The date and time will align with this site's timezone: %s.", 'wpc-notifications' ), "<strong>{$site_timezone_string}</strong>" ); ?></p>
		<table class="form-table wpc-notifications-post">
			<tbody>
				<tr>
					<th scope="row"><?php _e( 'Deactivate', 'wpc-notifications' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php _e( 'Would you like to deactivate this notification?', 'wpc-notifications' ); ?></span></legend>
							<label for="wpc-notif-deactivate"><input id="wpc-notif-deactivate" name="wpc_notifications[deactivate]" type="checkbox" value="1"<?php checked( $notif_deactivate ); ?>> <?php _e( 'Deactivate this notification', 'wpc-notifications' ); ?></label>
							<p class="description"><?php _e( 'Deactivating a notification allows you to remove it from being displayed but save it for a later time.', 'wpc-notifications' ); ?></p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpc-notif-start-date"><?php _e( 'Start Date', 'wpc-notifications' ); ?></label></th>
					<td>
						<input type="text" id="wpc-notif-start-date" value="<?php echo esc_attr( $notif_start_date_display ); ?>" class="regular-text wpc-notif-date-field" data-time="wpc-notif-start-time" data-alt="wpc-notif-start-date-alt" />
						<input name="wpc_notifications[start_date]" type="hidden" id="wpc-notif-start-date-alt" value="<?php echo esc_attr( $notif_start_date_alt ); ?>" />
						<p class="description"><?php _e( 'Leave the start and end date blank to always display the notification.', 'wpc-notifications' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpc-notif-start-time"><?php _e( 'Start Time', 'wpc-notifications' ); ?></label></th>
					<td>
						<input id="wpc-notif-start-time" name="wpc_notifications[start_time]" type="text" value="<?php echo esc_attr( $notif_start_time_display ); ?>" class="regular-text wpc-notif-time-field" />
						<p class="description"><?php _e( 'A start date is required in order for the start time to take effect.', 'wpc-notifications' ); ?><br><strong><?php printf( __( 'Valid format: %s', 'wpc-notifications' ), '05:00 PM' ); ?></strong></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpc-notif-end-date"><?php _e( 'End Date', 'wpc-notifications' ); ?></label></th>
					<td>
						<input type="text" id="wpc-notif-end-date" value="<?php echo esc_attr( $notif_end_date_display ); ?>" class="regular-text wpc-notif-date-field" data-time="wpc-notif-end-time" data-alt="wpc-notif-end-date-alt" />
						<input name="wpc_notifications[end_date]" type="hidden" id="wpc-notif-end-date-alt" value="<?php echo esc_attr( $notif_end_date_alt ); ?>" />
						<p class="description"><?php _e( 'Leave the start and end date blank to always display the notification.', 'wpc-notifications' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpc-notif-end-time"><?php _e( 'End Time', 'wpc-notifications' ); ?></label></th>
					<td>
						<input id="wpc-notif-end-time" name="wpc_notifications[end_time]" type="text" value="<?php echo esc_attr( $notif_end_time_display ); ?>" class="regular-text wpc-notif-time-field" />
						<p class="description"><?php _e( 'An end date is required in order for the end time to take effect.', 'wpc-notifications' ); ?><br><strong><?php printf( __( 'Valid format: %s', 'wpc-notifications' ), '05:00 PM' ); ?></strong></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Filters the columns displayed in the notifications list table.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param   $columns - array - An array of column names.
	 * @return  array - filtered list of columns.
	 */
	public function manage_notification_columns( $columns ) {

		// Loop through each column and create new columns.
		$new_columns = array();
		foreach ( $columns as $key => $value ) {

			// Add each column to new columns.
			$new_columns[ $key ] = $value;

			// Add our columns after title.
			if ( 'title' == $key ) {
				$new_columns['wpc-notif-status'] = __( 'Status', 'wpc-notifications' );
				$new_columns['wpc-notif-starts'] = __( 'Starts', 'wpc-notifications' );
				$new_columns['wpc-notif-ends'] = __( 'Ends', 'wpc-notifications' );
			}
		}

		return $new_columns;
	}

	/**
	 * Fires for each custom column of a specific post type in the Posts list table.
	 *
	 * The dynamic portion of the hook name, `$post->post_type`, refers to the post type.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param   $column_name - string - The name of the column to display.
	 * @param   $post_id - int - The current post ID.
	 * @return  void
	 */
	public function manage_notification_column_values( $column_name, $post_id ) {

		switch ( $column_name ) {

			case 'wpc-notif-status':

				switch ( $this->get_notification_status( $post_id ) ) {

					case 'active':
						_e( 'Active', 'wpc-notifications' );
						break;

					case 'deactivated':
						_e( 'Deactivated', 'wpc-notifications' );
						break;

					case 'expired':
						_e( 'Expired', 'wpc-notifications' );
						break;

					case 'future':
						_e( 'Future', 'wpc-notifications' );
						break;

					case 'pending':
						_e( 'Pending', 'wpc-notifications' );
						break;

				}
				break;

			case 'wpc-notif-starts':
			case 'wpc-notif-ends':

				// Get/process the date/time.
				$date_time_key = 'wpc-notif-starts' == $column_name ? 'wpc_notif_start_dt' : 'wpc_notif_end_dt';
				$date_time = get_post_meta( $post_id, $date_time_key, true );
				if ( $date_time && false !== strtotime( $date_time ) ) {

					// Convert to Date/Time.
					$date_time = new DateTime( $date_time );

					// Get site timezone.
					$site_timezone = get_option( 'timezone_string' );
					if ( ! $site_timezone ) {
						$site_timezone = 'UTC';
					}

					// Convert the timezone.
					$date_time->setTimezone( new DateTimeZone( $site_timezone ) );

					// Print the date/time.
					echo $date_time->format( 'F j, Y g:i A' );

				}
				break;
		}
	}

}

/**
 * Returns the instance of our main WPCampus_Notifications_Admin class.
 *
 * @return object - WPCampus_Notifications_Admin
 */
function wpcampus_notifications_admin() {
	return WPCampus_Notifications_Admin::instance();
}

// Let's get this party started.
wpcampus_notifications_admin();

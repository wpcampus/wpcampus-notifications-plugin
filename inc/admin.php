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

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 1, 2 );

		// Save meta box data.
		add_action( 'save_post', array( $this, 'save_meta_box_data' ), 10, 3 );

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
	 * Add styles and scripts in the admin.
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param	string - $hook_suffix - the ID of the current page
	 */
	public function enqueue_styles_scripts( $hook_suffix ) {
		global $post_type;

		// Only for the edit notification admin screens.
		if ( ! ( 'notification' == $post_type && in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) ) ) {
			return;
		}

		// Register the timepicker script.
		wp_register_script( 'timepicker', '//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js', array( 'jquery' ), null, true );

		// Enqueue the notification script.
		wp_enqueue_script( 'wpc-notifications-admin', trailingslashit( wpcampus_notifications()->plugin_url ) . 'assets/js/admin-post.min.js', array( 'jquery', 'jquery-ui-datepicker', 'timepicker' ), null, true );

		// Enqueue the various style dependencies.
		wp_enqueue_style( 'jquery-ui', '//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css', array(), null );
		wp_enqueue_style( 'timepicker', '//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css', array(), null );

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

		// Print notification details form inside its meta box.
		if ( 'wpc-notifications-details' == $metabox['id'] ) {
			$this->print_notification_details_form( $post->ID );
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

		// Check if our nonce is set because the 'save_post' action can be triggered at other times.
		if ( isset( $_POST['wpc_notifications_save_details_nonce'] ) ) {

			// Verify the nonce.
			if ( wp_verify_nonce( $_POST['wpc_notifications_save_details_nonce'], 'wpc_notifications_save_details' ) ) {

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

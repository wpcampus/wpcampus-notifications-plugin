<?php
/**
 * Holds all the functionality needed
 * to register the plugin's ACF fields.
 *
 * @package WPCampus Notifications
 */

/**
 * Register the plugin's ACF fields.
 */
function wpcampus_notifications_add_fields() {
	if ( function_exists( 'acf_add_local_field_group' ) ) :

		acf_add_local_field_group(array(
			'key' => 'group_5b24308701e0d',
			'title' => __( 'Notification Details', 'wpc-notifications' ),
			'fields' => array(
				array(
					'key' => 'field_5b25fbc56890b',
					'label' => __( 'Sticky notification', 'wpc-notifications' ),
					'name' => 'sticky',
					'type' => 'true_false',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'message' => __( 'Place this notification at the top of the list.', 'wpc-notifications' ),
					'default_value' => 0,
					'ui' => 1,
					'ui_on_text' => '',
					'ui_off_text' => '',
				),
				array(
					'key' => 'field_5b243c03874da',
					'label' => __( 'Formats', 'wpc-notifications' ),
					'name' => 'formats',
					'type' => 'taxonomy',
					'instructions' => __( 'Where do you want to display the message? The selected formats must have a defined message.', 'wpc-notifications' ),
					'required' => 1,
					'conditional_logic' => 0,
					'taxonomy' => 'notification_format',
					'field_type' => 'checkbox',
					'allow_null' => 0,
					'add_term' => 0,
					'save_terms' => 1,
					'load_terms' => 0,
					'return_format' => 'id',
					'multiple' => 0,
				),
				array(
					'key' => 'field_5b253626a564a',
					'label' => __( 'Permalink', 'wpc-notifications' ),
					'name' => 'permalink',
					'type' => 'url',
					'instructions' => __( 'Where do you want users to go for more information?', 'wpc-notifications' ),
					'required' => 1,
					'conditional_logic' => 0,
					'default_value' => '',
					'placeholder' => '',
				),
				array(
					'key' => 'field_5b2466540abeb',
					'label' => __( 'Website message', 'wpc-notifications' ),
					'name' => 'website_message',
					'type' => 'wysiwyg',
					'instructions' => __( 'What message do you want to share on the website?', 'wpc-notifications' ),
					'required' => 0,
					'conditional_logic' => 0,
					'default_value' => '',
					'tabs' => 'text',
					'toolbar' => 'basic',
					'media_upload' => 0,
					'delay' => 1,
				),
			),
			'location' => array(
				array(
					array(
						'param' => 'post_type',
						'operator' => '==',
						'value' => 'notification',
					),
				),
			),
			'menu_order' => 0,
			'position' => 'normal',
			'style' => 'default',
			'label_placement' => 'left',
			'instruction_placement' => 'field',
			'hide_on_screen' => '',
			'active' => 1,
			'description' => '',
		));

	endif;
}
add_action( 'plugins_loaded', 'wpcampus_notifications_add_fields' );

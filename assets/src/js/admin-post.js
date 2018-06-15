(function( $ ) {
	'use strict';

	// When the document is ready...
	$(document).ready(function() {

		// Set up elements.
		var start_date_id = 'wpc-notif-start-date';
		var start_time_id = 'wpc-notif-start-time';
		var $end_date = $( '#wpc-notif-end-date' );
		var $end_time = $( '#wpc-notif-end-time' );

		// Setup our date pickers.
		$( '.wpc-notif-date-field' ).each(function(){

			// Set the date field.
			var $this_date_field = $( this );
			var this_date_id = $this_date_field.attr( 'id' );

			// Set the date alt ID.
			var date_alt_id = $this_date_field.data( 'alt' );

			// Assign the date picker.
			$this_date_field.datepicker({
				dateFormat: 'MM d, yy',
				altField: '#' + date_alt_id,
				altFormat: 'yy-mm-dd',
				showButtonPanel: true
			});

			// Check the date field's time.
			$this_date_field.wpc_notif_check_date_time();

			// When date is changed...
			$this_date_field.on( 'change', function() {

				// Be sure to clear the alt field.
				if ( '' == $this_date_field.val() ) {
					$( '#' + date_alt_id ).val( '' );
				}

				// If start date changing, change settings for end date.
				if ( start_date_id == this_date_id && $end_date.length > 0 ) {
					$end_date.datepicker( 'option', 'minDate', $( this ).datepicker( 'getDate' ) );
				}

				// Check the date field's time.
				$this_date_field.wpc_notif_check_date_time();

			});
		});

		// Setup our time pickers.
		$( '.wpc-notif-time-field' ).each(function(){

			// Set the time field.
			var $this_time_field = $( this );
			var this_time_id = $this_time_field.attr( 'id' );

			// Assign the time picker.
			$this_time_field.timepicker({
				interval: 15,
				timeFormat: 'h:mm p',
				dynamic: false,
				dropdown: true,
				scrollbar: true
			});

			// When time is changed...
			$this_time_field.timepicker( 'option', 'change', function ( time ) {

				// If start time changing, change settings for end time.
				if ( start_time_id == this_time_id && time && $end_time.length > 0 ) {
					$end_time.timepicker( 'option', 'minTime', time );
				}
			});
		});
	});

	/**
	 * Times are only needed if dates are set.
	 *
	 * This function check the times when dates
	 * are loaded and changed to disable if necessary.
	 *
	 * This function is invoked by the date field.
	 */
	$.fn.wpc_notif_check_date_time = function() {

		// Set the date field and time ID.
		var $this_date_field = $( this );
		var date_time_id = $this_date_field.data( 'time' );

		// If no date, then disable the time.
		if ( '' == $this_date_field.val() ) {
			$( '#' + date_time_id ).val( '' ).attr( 'disabled', true );
		} else {
			$( '#' + date_time_id ).attr( 'disabled', false );
		}
	}

})( jQuery );
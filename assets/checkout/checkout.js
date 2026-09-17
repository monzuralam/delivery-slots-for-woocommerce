/**
 * Classic (shortcode) checkout: two dependent fields — pick a delivery date,
 * then pick a delivery time from that date's slots — and push the customer's
 * selection to the server (for pricing) whenever the time changes, all
 * without a full page reload.
 *
 * Final capacity enforcement is server-side (see Checkout.php); this script
 * only drives the UI and keeps it reasonably fresh.
 */
( function ( $ ) {
	'use strict';

	if ( typeof DSW_Checkout === 'undefined' ) {
		return;
	}

	var $dateSelect = null;
	var $timeSelect = null;
	var $status     = null;
	var pollTimer   = null;
	var slotsData   = [];

	function init() {
		$dateSelect = $( '#dsw_delivery_date' );
		$timeSelect = $( '#dsw_delivery_slot_id' );
		$status     = $( '.dsw-slot-status' );

		if ( ! $dateSelect.length || ! $timeSelect.length ) {
			return;
		}

		fetchSlots();
		pollTimer = setInterval( fetchSlots, ( DSW_Checkout.pollSeconds || 20 ) * 1000 );

		$dateSelect.on( 'change', onDateChange );
		$timeSelect.on( 'change', onSlotChange );

		$( window ).on( 'beforeunload', function () {
			if ( pollTimer ) {
				clearInterval( pollTimer );
			}
		} );
	}

	function fetchSlots() {
		$.ajax( {
			url: DSW_Checkout.restUrl,
			method: 'GET',
			dataType: 'json'
		} ).done( function ( response ) {
			slotsData = ( response && response.slots ) ? response.slots : [];
			attachPlainPrice();
			renderDateOptions();
		} ).fail( function () {
			if ( $status ) {
				$status.text( DSW_Checkout.i18n.error );
			}
		} );
	}

	function renderDateOptions() {
		var previousDate = $dateSelect.val();
		var previousSlot = $timeSelect.val() || String( DSW_Checkout.selectedSlot || '' );

		// Nothing picked in this session yet, but a slot survived from a
		// previous page load (WC session) — recover its date so both
		// dropdowns can be restored to their prior state.
		if ( ! previousDate && previousSlot ) {
			var match = slotsData.filter( function ( s ) { return String( s.id ) === previousSlot; } )[ 0 ];
			if ( match ) {
				previousDate = match.date;
			}
		}

		var dates = [];
		slotsData.forEach( function ( slot ) {
			if ( dates.indexOf( slot.date ) === -1 ) {
				dates.push( slot.date );
			}
		} );
		dates.sort();

		var html = '<option value="">' + escapeHtml( DSW_Checkout.i18n.chooseDate ) + '</option>';
		dates.forEach( function ( date ) {
			html += '<option value="' + escapeHtml( date ) + '">' + escapeHtml( date ) + '</option>';
		} );
		$dateSelect.html( html );

		if ( previousDate && dates.indexOf( previousDate ) !== -1 ) {
			$dateSelect.val( previousDate );
			renderTimeOptions( previousDate, previousSlot );
		} else {
			renderTimeOptions( '', '' );
		}
	}

	function renderTimeOptions( date, preselectId ) {
		if ( ! date ) {
			$timeSelect.html( '<option value="">' + escapeHtml( DSW_Checkout.i18n.chooseDateFirst ) + '</option>' );
			$timeSelect.prop( 'disabled', true );
			return;
		}

		var slotsForDate = slotsData
			.filter( function ( s ) { return s.date === date; } )
			.sort( function ( a, b ) { return a.start_time < b.start_time ? -1 : 1; } );

		var html = '<option value="">' + escapeHtml( DSW_Checkout.i18n.choose ) + '</option>';
		slotsForDate.forEach( function ( slot ) {
			var label  = slot.start_time + ' - ' + slot.end_time + ' (' + slot.price_html_text + ')';
			var suffix = slot.available
				? ' - ' + slot.remaining + ' ' + escapeHtml( DSW_Checkout.i18n.left )
				: ' - ' + escapeHtml( DSW_Checkout.i18n.soldOut );
			html += '<option value="' + slot.id + '" ' + ( slot.available ? '' : 'disabled' ) + '>' +
				escapeHtml( label + suffix ) + '</option>';
		} );

		$timeSelect.html( html );
		$timeSelect.prop( 'disabled', false );

		if ( preselectId ) {
			$timeSelect.val( preselectId );
			if ( $timeSelect.val() !== String( preselectId ) ) {
				$timeSelect.val( '' );
				$status.text( DSW_Checkout.i18n.soldOut );
			}
		}
	}

	function onDateChange() {
		$status.text( '' );
		renderTimeOptions( $dateSelect.val(), '' );
		// The previously selected time (if any) no longer applies to the new
		// date — clear the server-side price/session immediately so the
		// total updates right away instead of staying stale until a new
		// time is picked.
		clearOrSelectSlot( '' );
	}

	function onSlotChange() {
		clearOrSelectSlot( $timeSelect.val() );
	}

	function clearOrSelectSlot( slotId ) {
		$status.text( '' );

		$.ajax( {
			url: DSW_Checkout.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'dsw_select_slot',
				slot_id: slotId,
				nonce: DSW_Checkout.nonce
			}
		} ).done( function ( response ) {
			if ( ! response.success ) {
				$status.text( ( response.data && response.data.message ) || DSW_Checkout.i18n.error );
				return;
			}
			if ( response.data.sold_out ) {
				$status.text( response.data.message );
			}
			// Trigger WooCommerce's native checkout refresh: recalculates
			// totals (our delivery fee) and refreshes checkout fragments.
			$( document.body ).trigger( 'update_checkout' );
		} ).fail( function () {
			$status.text( DSW_Checkout.i18n.error );
		} );
	}

	function escapeHtml( str ) {
		return String( str ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	// The REST controller returns `price_html` (with markup); build a
	// plain-text fallback for the <option> label since <option> can't render HTML.
	function attachPlainPrice() {
		slotsData.forEach( function ( slot ) {
			var tmp = document.createElement( 'div' );
			tmp.innerHTML = slot.price_html;
			slot.price_html_text = tmp.textContent || tmp.innerText || '';
		} );
	}

	$( init );
} )( jQuery );

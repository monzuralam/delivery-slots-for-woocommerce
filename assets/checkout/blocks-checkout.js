/**
 * Progressive-enhancement delivery-slot picker for the block-based Checkout:
 * two dependent fields — delivery date, then delivery time — using only
 * globals WooCommerce Blocks already loads on the checkout page — no build
 * step required.
 *
 * WooCommerce Blocks only exposes ExperimentalOrderMeta as a way to inject
 * an arbitrary custom component, and it always renders inside the Order
 * Summary sidebar (confirmed against the installed WooCommerce Blocks
 * bundle — there is no equivalent slot in the main Contact/Address/Payment
 * column). A required "pick your delivery slot" field belongs in the main
 * form, not the order-review sidebar, so this still registers through
 * ExperimentalOrderMeta (for its mount/unmount lifecycle) but renders its
 * actual content elsewhere via a React portal into an anchor placed right
 * before the Payment section.
 *
 * The chosen slot id is pushed via the Store API's extensionCartUpdate(),
 * which triggers a cart recalculation server-side (see
 * Checkout::register_cart_update_callback()) so the delivery fee updates
 * live, and is also persisted onto the order at place-order time (see
 * Checkout::save_slot_from_store_api_request()). The server is always the
 * final authority on whether the slot is actually still available.
 */
( function () {
	'use strict';

	if (
		typeof wp === 'undefined' || ! wp.plugins || ! wp.element ||
		typeof wc === 'undefined' || ! wc.blocksCheckout ||
		! wc.blocksCheckout.ExperimentalOrderMeta || ! wc.blocksCheckout.extensionCartUpdate
	) {
		return; // Older WooCommerce/Blocks version, or the checkout chunk hasn't finished loading yet — classic checkout path still fully works.
	}

	var el                    = wp.element.createElement;
	var useState              = wp.element.useState;
	var useEffect             = wp.element.useEffect;
	var useRef                = wp.element.useRef;
	var createPortal          = wp.element.createPortal;
	var registerPlugin        = wp.plugins.registerPlugin;
	var ExperimentalOrderMeta = wc.blocksCheckout.ExperimentalOrderMeta;
	var extensionCartUpdate   = wc.blocksCheckout.extensionCartUpdate;
	var __                    = ( wp.i18n && wp.i18n.__ ) ? wp.i18n.__ : function ( s ) { return s; };

	var settings  = window.DSW_BlocksCheckout || {};
	var ANCHOR_ID = 'dsw-blocks-slot-anchor';

	// Where in the main column to insert the anchor — first match wins,
	// covering a couple of WooCommerce Blocks versions' class names.
	var ANCHOR_BEFORE_SELECTORS = [
		'.wp-block-woocommerce-checkout-payment-block',
		'.wc-block-checkout__payment-method',
	];

	function getOrCreateAnchor() {
		var existing = document.getElementById( ANCHOR_ID );
		if ( existing ) {
			return existing;
		}

		var target = null;
		for ( var i = 0; i < ANCHOR_BEFORE_SELECTORS.length; i++ ) {
			target = document.querySelector( ANCHOR_BEFORE_SELECTORS[ i ] );
			if ( target ) {
				break;
			}
		}

		if ( ! target || ! target.parentNode ) {
			return null;
		}

		var anchor = document.createElement( 'div' );
		anchor.id = ANCHOR_ID;
		target.parentNode.insertBefore( anchor, target );

		return anchor;
	}

	function pushSlotSelection( slotId ) {
		return extensionCartUpdate( {
			namespace: settings.namespace || 'dsw',
			data: { slot_id: slotId ? parseInt( slotId, 10 ) : 0 }
		} );
	}

	function DeliverySlotField() {
		var anchorArr       = useState( null );
		var anchor          = anchorArr[ 0 ];
		var setAnchor       = anchorArr[ 1 ];

		var slotsArr        = useState( [] );
		var slots           = slotsArr[ 0 ];
		var setSlots        = slotsArr[ 1 ];

		var dateArr         = useState( '' );
		var selectedDate    = dateArr[ 0 ];
		var setSelectedDate = dateArr[ 1 ];

		var slotArr         = useState( '' );
		var selectedSlot    = slotArr[ 0 ];
		var setSelectedSlot = slotArr[ 1 ];

		var statusArr       = useState( '' );
		var status          = statusArr[ 0 ];
		var setStatus       = statusArr[ 1 ];

		// Whether we've already tried restoring a slot selection carried
		// over from the WC session (e.g. after a checkout page reload).
		// A ref (not state) because loadSlots() is captured once by
		// setInterval on mount — a state value read inside it would stay
		// stale forever and re-apply the restore on every poll.
		var restoredRef     = useRef( false );

		// The Payment block mounts asynchronously, so poll briefly for the
		// anchor point rather than assuming it exists on first render.
		useEffect( function () {
			var found = getOrCreateAnchor();
			if ( found ) {
				setAnchor( found );
				return;
			}

			var attempts = 0;
			var timer = setInterval( function () {
				attempts++;
				var anchorNode = getOrCreateAnchor();
				if ( anchorNode ) {
					setAnchor( anchorNode );
					clearInterval( timer );
				} else if ( attempts > 30 ) {
					clearInterval( timer ); // Give up after ~6s; falls back to the sidebar slot fill.
				}
			}, 200 );

			return function () { clearInterval( timer ); };
		}, [] );

		function loadSlots() {
			fetch( settings.restUrl )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					var freshSlots = ( data && data.slots ) || [];
					setSlots( freshSlots );

					// Restore a slot selection that survived from a previous
					// page load (WC session) so the fields don't appear
					// blank while the fee/reservation is still active.
					if ( ! restoredRef.current ) {
						restoredRef.current = true;

						if ( settings.selectedSlot ) {
							var match = freshSlots.filter( function ( s ) {
								return String( s.id ) === String( settings.selectedSlot );
							} )[ 0 ];

							if ( match ) {
								setSelectedDate( match.date );
								setSelectedSlot( String( match.id ) );
							}
						}
					}
				} )
				.catch( function () { setStatus( __( 'Could not load delivery slots.', 'delivery-slots-for-woocommerce' ) ); } );
		}

		useEffect( function () {
			loadSlots();
			var interval = setInterval( loadSlots, ( settings.pollSeconds || 20 ) * 1000 );
			return function () { clearInterval( interval ); };
		}, [] );

		function onDateChange( e ) {
			setSelectedDate( e.target.value );
			setSelectedSlot( '' );
			setStatus( '' );
			pushSlotSelection( 0 ).catch( function () {} );
		}

		function onSlotChange( e ) {
			var value = e.target.value;
			setSelectedSlot( value );
			setStatus( '' );

			pushSlotSelection( value ).catch( function () {
				setStatus( __( 'Could not update your delivery slot selection.', 'delivery-slots-for-woocommerce' ) );
			} );
		}

		var dates = [];
		slots.forEach( function ( slot ) {
			if ( dates.indexOf( slot.date ) === -1 ) {
				dates.push( slot.date );
			}
		} );
		dates.sort();

		var dateOptionEls = dates.map( function ( date ) {
			return el( 'option', { key: date, value: date }, date );
		} );

		var timeOptionEls = slots
			.filter( function ( slot ) { return slot.date === selectedDate; } )
			.sort( function ( a, b ) { return a.start_time < b.start_time ? -1 : 1; } )
			.map( function ( slot ) {
				var suffix = slot.available
					? ' - ' + slot.remaining + ' ' + __( 'left', 'delivery-slots-for-woocommerce' )
					: ' - ' + __( 'Sold out', 'delivery-slots-for-woocommerce' );
				return el(
					'option',
					{ key: slot.id, value: slot.id, disabled: ! slot.available },
					slot.start_time + ' - ' + slot.end_time + suffix
				);
			} );

		var content = el(
			'div',
			{ className: 'dsw-delivery-slot dsw-blocks-slot-field' },
			el( 'h3', null, __( 'Delivery date & time', 'delivery-slots-for-woocommerce' ) ),
			el(
				'div',
				{ className: 'dsw-blocks-slot-row' },
				el(
					'div',
					{ className: 'dsw-blocks-slot-col' },
					el( 'label', { htmlFor: 'dsw-blocks-date-select' }, __( 'Delivery date', 'delivery-slots-for-woocommerce' ) ),
					el(
						'select',
						{ id: 'dsw-blocks-date-select', value: selectedDate, onChange: onDateChange, required: true },
						el( 'option', { value: '' }, __( 'Select a delivery date…', 'delivery-slots-for-woocommerce' ) ),
						dateOptionEls
					)
				),
				el(
					'div',
					{ className: 'dsw-blocks-slot-col' },
					el( 'label', { htmlFor: 'dsw-blocks-slot-select' }, __( 'Delivery time', 'delivery-slots-for-woocommerce' ) ),
					el(
						'select',
						{
							id: 'dsw-blocks-slot-select',
							value: selectedSlot,
							onChange: onSlotChange,
							required: true,
							disabled: ! selectedDate,
						},
						el( 'option', { value: '' }, selectedDate
							? __( 'Select a delivery time…', 'delivery-slots-for-woocommerce' )
							: __( 'Select a date first', 'delivery-slots-for-woocommerce' ) ),
						timeOptionEls
					)
				)
			),
			status ? el( 'span', { className: 'dsw-blocks-status' }, status ) : null
		);

		// Until the anchor is found, render nothing rather than flashing the
		// field in the sidebar first and then relocating it.
		return anchor ? createPortal( content, anchor ) : null;
	}

	registerPlugin( 'dsw-delivery-slot', {
		render: function () {
			return el( ExperimentalOrderMeta, {}, el( DeliverySlotField ) );
		},
		scope: 'woocommerce-checkout'
	} );
} )();

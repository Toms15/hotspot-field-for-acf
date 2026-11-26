/**
 * Hotspot Field for ACF: frontend behaviour.
 *
 * Each Hotspot button opens its Popover through the native Popover API
 * (`popovertarget` + `popover="auto"`), which already provides Escape, light
 * dismiss, one open Popover at a time and the expanded state. This script
 * only:
 * - dispatches a bubbling, cancelable `hotspotfield:open` event on the button
 *   before its Popover opens (`preventDefault()` keeps it closed);
 * - places the open Popover next to its button, inside the viewport while
 *   the button is in view, and keeps it with the button when it scrolls out;
 * - links every button to its own Popover, as a fallback for markup echoed
 *   twice (for example from a cache), whose Popover IDs would repeat.
 */
( function () {
	'use strict';

	var BUTTON = '.hotspot-field__hotspot';
	var POPOVER = '.hotspot-field__popover';

	/** Space between the button and its Popover, and the viewport edges, in px. */
	var GAP = 8;

	/** The Hotspot Popover that is open, if any. */
	var open = null;

	if ( ! ( 'popover' in HTMLElement.prototype ) ) {
		return;
	}

	/**
	 * The Popover rendered right after a button.
	 *
	 * @param {HTMLElement} button Hotspot button.
	 * @return {HTMLElement|null} Its Popover.
	 */
	function popoverOf( button ) {
		var next = button.nextElementSibling;

		return next && next.matches( POPOVER ) ? next : null;
	}

	/**
	 * The button that opens a Popover.
	 *
	 * @param {HTMLElement} popover Popover.
	 * @return {HTMLElement|null} Its button.
	 */
	function buttonOf( popover ) {
		var previous = popover.previousElementSibling;

		return previous && previous.matches( BUTTON ) ? previous : null;
	}

	/**
	 * Makes a button open its own Popover. The renderer gives every Popover
	 * a unique ID, but the same markup echoed twice would repeat them and
	 * make the second copy's buttons open the first copy's Popovers.
	 *
	 * @param {HTMLElement} button Hotspot button.
	 */
	function link( button ) {
		var popover = popoverOf( button );

		if ( popover && button.popoverTargetElement !== popover ) {
			button.popoverTargetElement = popover;
		}
	}

	/**
	 * The Hotspot a button stands for.
	 *
	 * @param {HTMLElement} button Hotspot button.
	 * @return {{x: number, y: number, label: string, description: string}} Hotspot.
	 */
	function hotspotOf( button ) {
		try {
			return JSON.parse( button.getAttribute( 'data-hotspot' ) );
		} catch ( error ) {
			return null;
		}
	}

	/**
	 * Places an open Popover below its button, or above it when there is
	 * more room there. While the button is in view the Popover is kept
	 * inside the viewport; on an axis where the button has scrolled out of
	 * view it is not, so the Popover leaves with its button instead of
	 * staying pinned to the edge, and comes back with it.
	 *
	 * @param {HTMLElement} popover Popover.
	 */
	function place( popover ) {
		var button = buttonOf( popover );

		if ( ! button || ! popover.matches( ':popover-open' ) ) {
			return;
		}

		var anchor = button.getBoundingClientRect();
		var viewportWidth = document.documentElement.clientWidth;
		var viewportHeight = document.documentElement.clientHeight;

		popover.style.margin = '0';
		popover.style.inset = 'auto';
		popover.style.left = '0px';
		popover.style.top = '0px';

		var size = popover.getBoundingClientRect();
		var below = viewportHeight - anchor.bottom - GAP;
		var above = anchor.top - GAP;
		var top =
			below >= size.height + GAP || below >= above
				? anchor.bottom + GAP
				: anchor.top - GAP - size.height;
		var left = anchor.left + anchor.width / 2 - size.width / 2;

		if ( anchor.right > 0 && anchor.left < viewportWidth ) {
			left = Math.max( GAP, Math.min( left, viewportWidth - GAP - size.width ) );
		}

		if ( anchor.bottom > 0 && anchor.top < viewportHeight ) {
			top = Math.max( GAP, Math.min( top, viewportHeight - GAP - size.height ) );
		}

		popover.style.left = left + 'px';
		popover.style.top = top + 'px';
	}

	/**
	 * Places the open Hotspot Popover again, after scrolling or resizing.
	 */
	function placeOpen() {
		if ( open ) {
			place( open );
		}
	}

	document.querySelectorAll( BUTTON ).forEach( link );

	// Buttons rendered after the page loaded are linked before they act.
	document.addEventListener(
		'click',
		function ( event ) {
			var button = event.target instanceof Element ? event.target.closest( BUTTON ) : null;

			if ( button ) {
				link( button );
			}
		},
		true
	);

	// `beforetoggle` does not bubble: listen in the capture phase.
	document.addEventListener(
		'beforetoggle',
		function ( event ) {
			var popover = event.target;

			if ( 'open' !== event.newState || ! ( popover instanceof HTMLElement ) || ! popover.matches( POPOVER ) ) {
				return;
			}

			var button = buttonOf( popover );

			if ( ! button ) {
				return;
			}

			var opening = new CustomEvent( 'hotspotfield:open', {
				bubbles: true,
				cancelable: true,
				detail: { hotspot: hotspotOf( button ), button: button },
			} );

			if ( ! button.dispatchEvent( opening ) ) {
				event.preventDefault();
				return;
			}

			// The Popover is shown right after this event: place it before
			// it is painted.
			requestAnimationFrame( function () {
				place( popover );
			} );
		},
		true
	);

	// `toggle` does not bubble either.
	document.addEventListener(
		'toggle',
		function ( event ) {
			var popover = event.target;

			if ( ! ( popover instanceof HTMLElement ) || ! popover.matches( POPOVER ) ) {
				return;
			}

			if ( 'open' === event.newState ) {
				open = popover;
				place( popover );
			} else if ( open === popover ) {
				open = null;
			}
		},
		true
	);

	window.addEventListener( 'resize', placeOpen );
	window.addEventListener( 'scroll', placeOpen, { capture: true, passive: true } );
} )();

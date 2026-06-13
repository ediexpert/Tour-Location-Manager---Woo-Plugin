/**
 * Tour Location Manager — Frontend Script
 *
 * Handles expand/collapse of the location tree. Progressive enhancement:
 * if JS is disabled, .tlm-location-children blocks remain visible via
 * the .tlm-expanded fallback class added server-side when "Expand by
 * default" is enabled; otherwise they are collapsed by CSS and this
 * script reveals them on click.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggles = document.querySelectorAll( '.tlm-location-menu .tlm-toggle:not(.tlm-toggle-empty)' );

		toggles.forEach( function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var item = toggle.closest( '.tlm-location-item' );

				if ( ! item ) {
					return;
				}

				var expanded = item.classList.toggle( 'tlm-expanded' );
				toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
				toggle.querySelector( 'span' ).textContent = expanded ? '\u2212' : '+';
			} );
		} );
	} );
} )();

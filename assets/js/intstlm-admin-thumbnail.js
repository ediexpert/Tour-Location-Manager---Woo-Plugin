/* Tour Location Manager — taxonomy thumbnail media uploader */
(function ( $ ) {
	'use strict';

	var frame;

	$( document ).on( 'click', '.intstlm-upload-thumbnail', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: 'Select or Upload Location Thumbnail',
			button: { text: 'Use this image' },
			multiple: false,
			library: { type: 'image' },
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var sizes      = attachment.sizes || {};
			var size       = sizes.thumbnail || sizes.medium || sizes.full || { url: attachment.url };

			if ( ! size || ! size.url ) {
				return;
			}

			$( '#intstlm_thumbnail_id' ).val( attachment.id );

			var img = $( '<img>' ).attr( {
				src:   size.url,
				alt:   '',
				style: 'max-width:150px;display:block;margin-bottom:6px;border-radius:3px;',
			} );

			$( '#intstlm-thumbnail-preview' ).empty().append( img );
			$( '.intstlm-remove-thumbnail' ).show();
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.intstlm-remove-thumbnail', function ( e ) {
		e.preventDefault();
		$( '#intstlm_thumbnail_id' ).val( '' );
		$( '#intstlm-thumbnail-preview' ).empty();
		$( this ).hide();
	} );
}( jQuery ) );

/**
 * Theme Customizer layout control JS
 *
 */

( function( $ ) {
	
	// custom controls
	$(document).on('click', '.layout-button', function(){
		var clicked = $(this),
			parent = clicked.closest('.layouts-selector'),
			input = parent.find('.selected-layout');
		console.log( this );
		parent.find( '.layout-button' ).css('border', '1px solid transparent');

		clicked.css( 'border', '1px solid rgb(43, 166, 203)');
		$(input).val( clicked.data('option') ).trigger('change');

	});

} )( jQuery );

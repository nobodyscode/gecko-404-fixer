/* Editor placeholder for the 404 Suggestions block. The output is rendered in PHP. */
( function ( blocks, element, blockEditor, i18n ) {
	'use strict';

	blocks.registerBlockType( 'smart-404-redirect/suggestions', {
		edit: function () {
			return element.createElement(
				'p',
				blockEditor.useBlockProps(),
				i18n.__( 'Smart 404s shows related pages here on the 404 page.', 'smart-404-redirect' )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n ) );

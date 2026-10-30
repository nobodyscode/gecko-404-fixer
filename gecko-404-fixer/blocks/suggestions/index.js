/* Editor placeholder for the 404 Suggestions block. The output is rendered in PHP. */
( function ( blocks, element, blockEditor, i18n ) {
	'use strict';

	blocks.registerBlockType( 'gecko-404-fixer/suggestions', {
		edit: function () {
			return element.createElement(
				'p',
				blockEditor.useBlockProps(),
				i18n.__( 'Gecko 404 Fixer shows related pages here on the 404 page.', 'gecko-404-fixer' )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n ) );

/* Gecko 404 Fixer admin screen: the inline redirect form, its page search, and the fallback fields. */
( function () {
	'use strict';

	var list = document.getElementById( 'lgf404-pages' );
	var timer;

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest ? event.target.closest( '.lgf404-open-redirect' ) : null;

		if ( ! link ) {
			return;
		}

		event.preventDefault();

		var form = link.closest( 'tr' ).querySelector( '.lgf404-redirect-form' );

		form.hidden = ! form.hidden;

		if ( ! form.hidden ) {
			form.querySelector( '.lgf404-target' ).focus();
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		if ( ! event.target.classList || ! event.target.classList.contains( 'lgf404-target' ) ) {
			return;
		}

		var term = event.target.value.trim();

		window.clearTimeout( timer );

		// A pasted URL or path needs no search.
		if ( term.length < 2 || '/' === term.charAt( 0 ) || 0 === term.indexOf( 'http' ) || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}

		timer = window.setTimeout( function () {
			window.wp.apiFetch( {
				path: '/wp/v2/search?type=post&per_page=8&search=' + encodeURIComponent( term )
			} ).then( function ( results ) {
				list.textContent = '';

				results.forEach( function ( result ) {
					var option = document.createElement( 'option' );

					option.value = result.url;
					option.label = result.title;
					list.appendChild( option );
				} );
			} ).catch( function () {
				list.textContent = '';
			} );
		}, 200 );
	} );

	var fallback = document.getElementById( 'lgf404-fallback' );

	if ( fallback ) {
		var page = document.querySelector( '.lgf404-fallback-page' );
		var warning = document.querySelector( '.lgf404-fallback-warning' );
		var sync = function () {
			page.hidden = 'page' !== fallback.value;
			warning.hidden = 'none' === fallback.value;
		};

		fallback.addEventListener( 'change', sync );
		sync();
	}
}() );

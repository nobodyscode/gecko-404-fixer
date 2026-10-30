#!/usr/bin/env node
/*
 * Clicks through the admin screen as an admin would, with real nonces.
 * Usage: NODE_PATH=<dir with puppeteer-core> node tests/admin-flow.js http://127.0.0.1:9412
 */
const puppeteer = require( 'puppeteer-core' );

const base = process.argv[ 2 ];
const SCREEN = '/wp-admin/tools.php?page=smart-404s';
let fails = 0;
let checks = 0;

const check = ( name, ok, detail ) => {
	checks++;
	if ( ! ok ) {
		fails++;
	}
	console.log( ( ok ? '  PASS  ' : '  FAIL  ' ) + name + ( ok ? '' : '   -> ' + detail ) );
};

( async () => {
	const browser = await puppeteer.launch( { executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: 'new' } );
	const page = await browser.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	page.on( 'dialog', ( d ) => d.accept() );
	await page.setViewport( { width: 1280, height: 900 } );

	const go = ( url ) => page.goto( base + url, { waitUntil: 'networkidle0', timeout: 120000 } );
	const status = async ( url ) => ( await page.evaluate( async ( u ) => {
		const r = await fetch( u, { redirect: 'manual' } );
		return r.type === 'opaqueredirect' ? 301 : r.status;
	}, base + url ) );
	const row = ( text ) => page.evaluateHandle( ( t ) => [ ...document.querySelectorAll( '.wp-list-table tbody tr' ) ].find( ( r ) => r.textContent.includes( t ) ), text );

	await go( '/' );
	await go( SCREEN );
	check( 'screen opens', ( await page.title() ).includes( 'Smart 404s' ), await page.title() );

	// Produce log rows as a visitor would.
	for ( const url of [ '/flow-open-one-qq/', '/flow-open-two-qq/', '/how-to-fix-404-errorss/' ] ) {
		await page.goto( base + url, { waitUntil: 'domcontentloaded', timeout: 120000 } );
	}
	await go( SCREEN );
	check( 'visited 404 is in the log', !! ( await row( '/flow-open-one-qq' ) ).asElement() );

	// Redirect to… through the inline form.
	let tr = ( await row( '/flow-open-one-qq' ) ).asElement();
	await tr.$eval( '.lgs404-open-redirect', ( a ) => a.click() );
	check( 'inline form opens', await tr.$eval( '.lgs404-redirect-form', ( f ) => ! f.hidden ) );
	await tr.$eval( '.lgs404-target', ( i ) => {
		i.value = '/contact-us/';
	} );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0', timeout: 120000 } ), tr.$eval( '.lgs404-redirect-form', ( f ) => f.requestSubmit() ) ] );
	check( 'redirect saved notice', ( await page.content() ).includes( 'notice-success' ) );
	const hits = await page.evaluate( async ( u ) => ( await fetch( u, { redirect: 'follow' } ) ).url, base + '/flow-open-one-qq/' );
	check( 'manual redirect works', hits.includes( '/contact-us' ), hits );

	// An outside URL is refused.
	await go( SCREEN );
	tr = ( await row( '/flow-open-two-qq' ) ).asElement();
	await tr.$eval( '.lgs404-open-redirect', ( a ) => a.click() );
	await tr.$eval( '.lgs404-target', ( i ) => {
		i.value = 'https://evil.example/x';
	} );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0', timeout: 120000 } ), tr.$eval( '.lgs404-redirect-form', ( f ) => f.requestSubmit() ) ] );
	check( 'outside URL refused', ( await page.content() ).includes( 'notice-error' ) );

	// Mark as gone.
	tr = ( await row( '/flow-open-two-qq' ) ).asElement();
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0', timeout: 120000 } ), tr.$eval( '.lgs404_gone a', ( a ) => a.click() ) ] );
	check( 'marked as gone returns 410', 410 === await status( '/flow-open-two-qq/' ), await status( '/flow-open-two-qq/' ) );

	// Undo an automatic fix, then it stays a 404.
	await go( SCREEN );
	tr = ( await row( '/how-to-fix-404-errorss' ) ).asElement();
	check( 'automatic fix is listed with Undo', !! ( tr && await tr.$( '.lgs404_undo a' ) ) );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0', timeout: 120000 } ), tr.$eval( '.lgs404_undo a', ( a ) => a.click() ) ] );
	check( 'undo leaves a 404', 404 === await status( '/how-to-fix-404-errorss/' ), await status( '/how-to-fix-404-errorss/' ) );

	// A forged action with no nonce is refused.
	const forged = await page.evaluate( async ( u ) => ( await fetch( u ) ).status, base + '/wp-admin/admin-post.php?action=lgs404_rule&op=gone&path=%2Fhow-to-fix-404-errors' );
	check( 'action without a nonce is refused', 403 === forged, forged );

	// Settings.
	await go( SCREEN );
	check( 'page field hidden until needed', await page.$eval( '.lgs404-fallback-page', ( p ) => p.hidden ) );
	await page.select( '#lgs404-fallback', 'page' );
	check( 'page field appears', await page.$eval( '.lgs404-fallback-page', ( p ) => ! p.hidden ) );
	await page.select( '#lgs404-fallback', 'none' );
	await page.select( '#lgs404-strictness', 'strict' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'networkidle0', timeout: 120000 } ), page.$eval( '.lgs404-columns form[action="options.php"]', ( f ) => f.requestSubmit() ) ] );
	check( 'settings save', 'strict' === await page.$eval( '#lgs404-strictness', ( s ) => s.value ) );

	// Search and tabs.
	await go( SCREEN + '&view=open' );
	check( 'filter tab', await page.evaluate( () => ! document.querySelector( '.wp-list-table tbody' ).textContent.includes( 'flow-open-two-qq' ) ) );
	await go( SCREEN + '&s=flow-open-two' );
	check( 'search', await page.evaluate( () => document.querySelector( '.wp-list-table tbody' ).textContent.includes( 'flow-open-two-qq' ) ) );

	// Sorting, both ways. The busiest URL is the one reached most in this run.
	const hitCounts = () => page.$$eval( '.wp-list-table tbody td.column-hits', ( c ) => c.map( ( x ) => parseInt( x.textContent, 10 ) ) );
	await go( SCREEN + '&orderby=hits&order=asc' );
	const asc = await hitCounts();
	check( 'sorts by hits ascending', asc.length > 1 && asc.every( ( n, i ) => 0 === i || asc[ i - 1 ] <= n ), asc.join( ',' ) );
	await go( SCREEN + '&orderby=hits&order=desc' );
	const desc = await hitCounts();
	check( 'sorts by hits descending', desc.length > 1 && desc.every( ( n, i ) => 0 === i || desc[ i - 1 ] >= n ), desc.join( ',' ) );
	await go( SCREEN + "&orderby=hits%27&order=asc%27" );
	check( 'odd sort values are ignored', ( await hitCounts() ).length > 1 );

	// CTA links are tagged and open LinkGecko.
	const cta = await page.$eval( '.lgs404-cta a', ( a ) => a.href );
	check( 'CTA carries UTM tags', cta.includes( 'linkgecko.com' ) && cta.includes( 'utm_source=wp-plugin' ) && cta.includes( 'utm_campaign=smart-404s' ), cta );

	// The block is available to the editor.
	await go( '/wp-admin/site-editor.php' ).catch( () => {} );
	const registered = await page.evaluate( async ( u ) => {
		const r = await window.wp.apiFetch( { path: '/wp/v2/block-types/smart-404-redirect/suggestions' } ).catch( () => null );
		return r && r.name;
	}, base ).catch( () => null );
	check( 'block is registered', 'smart-404-redirect/suggestions' === registered, registered );

	check( 'no JavaScript errors from the plugin', ! errors.some( ( e ) => e.includes( 'lgs404' ) || e.includes( 'admin.js' ) ), errors.join( ' | ' ) );

	await browser.close();
	console.log( `\n${ checks - fails } of ${ checks } checks passed` );
	process.exit( fails ? 1 : 0 );
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 2 );
} );

#!/usr/bin/env node
/*
 * Takes the wordpress.org screenshots from a running demo site.
 * Usage: NODE_PATH=<dir with puppeteer-core> node bin/screenshots.js http://127.0.0.1:9410 <out dir>
 */
const puppeteer = require( 'puppeteer-core' );
const fs = require( 'fs' );
const path = require( 'path' );

const base = process.argv[ 2 ];
const out = process.argv[ 3 ];
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

const go = ( page, url ) => page.goto( base + url, { waitUntil: 'networkidle0', timeout: 120000 } );

( async () => {
	const browser = await puppeteer.launch( { executablePath: CHROME, headless: 'new' } );
	const page = await browser.newPage();
	await page.setViewport( { width: 1280, height: 800, deviceScaleFactor: 2 } );

	await go( page, '/' );
	await go( page, '/wp-admin/tools.php?page=smart-404s' );
	await page.screenshot( { path: path.join( out, 'screenshot-1.png' ) } );

	// The log, with one row's inline redirect form open.
	await page.evaluate( () => {
		const rows = [ ...document.querySelectorAll( '.wp-list-table tbody tr' ) ];
		const row = rows.find( ( r ) => r.textContent.includes( 'spring-rain-jackets-roundup' ) );
		row.querySelector( '.lgs404-redirect-form' ).hidden = false;
		row.querySelector( '.lgs404-target' ).value = '/best-rain-jackets-for-spring/';
		row.querySelector( '.row-actions' ).style.left = '0';
		row.querySelector( '.row-actions' ).style.position = 'static';
		document.querySelector( '.lgs404-panel' ).scrollIntoView();
		window.scrollBy( 0, -40 );
	} );
	await page.screenshot( { path: path.join( out, 'screenshot-2.png' ) } );

	// A deleted post, shown as the redirect and the page it lands on.
	await go( page, '/category/trail-gear/' );
	await page.evaluate( () => {
		document.getElementById( 'wpadminbar' )?.remove();
		document.documentElement.style.setProperty( 'margin-top', '0', 'important' );
	} );
	const landed = ( await page.screenshot( { encoding: 'base64' } ) );
	const compare = await browser.newPage();
	await compare.setViewport( { width: 1280, height: 800, deviceScaleFactor: 2 } );
	await compare.setContent( `<!doctype html><html><head><style>
		@import url('https://fonts.googleapis.com/css2?family=Geist:wght@400;600&family=Geist+Mono:wght@400;500&display=swap');
		*{box-sizing:border-box}body{margin:0;background:#f7f8f9;font-family:Geist,-apple-system,'Helvetica Neue',sans-serif;color:#0a0b0d;height:800px;overflow:hidden}
		.bar{display:flex;align-items:center;gap:14px;padding:18px 28px;background:#fff;border-bottom:1px solid #e6e6e8}
		.lab{font:500 11px 'Geist Mono',monospace;letter-spacing:.05em;text-transform:uppercase;color:#6f6f6f;margin-bottom:5px}
		.url{font:500 15px 'Geist Mono',monospace}.dead{color:#d92d20;text-decoration:line-through}
		.pill{background:#00f;color:#fff;border-radius:999px;font:500 12px 'Geist Mono',monospace;padding:5px 12px;white-space:nowrap}
		.arrow{font-size:22px;color:#00f}.shot{padding:18px 28px}.shot img{width:100%;display:block;border:1px solid #e6e6e8;border-radius:10px;box-shadow:0 8px 28px #0a0b0d14}
	</style></head><body>
		<div class="bar"><div><div class="lab">Deleted post</div><div class="url dead">/old-sleeping-bag-review/</div></div>
		<span class="pill">301</span><span class="arrow">→</span>
		<div><div class="lab">Its category</div><div class="url">/category/trail-gear/</div></div></div>
		<div class="shot"><img src="data:image/png;base64,${ landed }"></div>
	</body></html>`, { waitUntil: 'load', timeout: 20000 } ).catch( () => {} );
	await new Promise( ( r ) => setTimeout( r, 2000 ) );
	await compare.screenshot( { path: path.join( out, 'screenshot-3.png' ) } );
	await compare.close();

	// The real 404 page in the default theme.
	await go( page, '/hiking-boots-guide-2021/' );
	await page.evaluate( () => {
		document.getElementById( 'wpadminbar' )?.remove();
		document.documentElement.style.setProperty( 'margin-top', '0', 'important' );
		// Scrolled so the suggestions and the theme's own 404 message are both in view.
		const box = document.querySelector( '.lgs404-suggestions' ).getBoundingClientRect();
		window.scrollTo( 0, Math.max( 0, box.bottom + window.scrollY - innerHeight + 90 ) );
	} );
	await page.screenshot( { path: path.join( out, 'screenshot-4.png' ) } );

	// Settings.
	await go( page, '/wp-admin/tools.php?page=smart-404s' );
	await page.evaluate( () => {
		document.querySelector( '.lgs404-columns' ).scrollIntoView();
		window.scrollBy( 0, -40 );
	} );
	await page.screenshot( { path: path.join( out, 'screenshot-5.png' ) } );

	await browser.close();
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );

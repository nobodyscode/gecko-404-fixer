#!/usr/bin/env node
/*
 * Renders the logo into the plugin and the listing icon and banner.
 * Usage: NODE_PATH=<dir with puppeteer-core> node bin/brand-assets.js <out dir>
 */
const puppeteer = require( 'puppeteer-core' );
const fs = require( 'fs' );
const path = require( 'path' );
const { svg } = require( './logo.js' );

const out = process.argv[ 2 ];
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BLUE = '#0000ff';
const logo = svg();

const banner = ( w, h, k ) => `<!doctype html><html><head><style>
@import url('https://fonts.googleapis.com/css2?family=Geist:wght@500;600&family=Geist+Mono:wght@400;500&display=swap');
*{box-sizing:border-box}html,body{margin:0}
body{width:${ w }px;height:${ h }px;background:${ BLUE };color:#fff;font-family:Geist,-apple-system,'Helvetica Neue',sans-serif;position:relative;overflow:hidden;
background-image:radial-gradient(#ffffff2e ${ 1.1 * k }px,transparent ${ 1.3 * k }px);background-size:${ 22 * k }px ${ 22 * k }px}
.in{position:absolute;left:${ 32 * k }px;right:${ 32 * k }px;top:${ 24 * k }px;display:flex;align-items:center;gap:${ 22 * k }px}
.l{width:${ 128 * k }px;height:${ 128 * k }px;flex:none;border-radius:${ 18 * k }px;overflow:hidden;box-shadow:0 0 0 ${ 2 * k }px #ffffff59}.l svg{display:block;width:100%;height:100%}
.t{flex:1}.t h1{margin:0;font-weight:600;font-size:${ 44 * k }px;letter-spacing:-.025em;line-height:1.05;white-space:nowrap}
.t p{margin:${ 6 * k }px 0 0;font-size:${ 16 * k }px;font-weight:500;color:#ffffffe0;white-space:nowrap}
.m{margin-bottom:${ 8 * k }px;font:500 ${ 11 * k }px 'Geist Mono',monospace;letter-spacing:.05em;text-transform:uppercase}
.r{background:#fff;color:#0a0b0d;border-radius:${ 12 * k }px;padding:${ 12 * k }px ${ 14 * k }px;font:500 ${ 11 * k }px 'Geist Mono',monospace;flex:none}
.dead{color:#d92d20;text-decoration:line-through}.row{display:flex;align-items:center;gap:${ 7 * k }px;margin-top:${ 7 * k }px}
.p{background:${ BLUE };color:#fff;border-radius:999px;padding:${ 2 * k }px ${ 7 * k }px;font-size:${ 10 * k }px}
</style></head><body><div class="in"><div class="l">${ logo }</div><div class="t"><div class="m">by LinkGecko</div><h1>Smart 404s</h1><p>Fix 404 errors the right way</p></div>
<div class="r"><div class="dead">/how-to-fix-404-erors</div><div class="row"><span class="p">301</span><span>/how-to-fix-404-errors/</span></div></div></div></body></html>`;

( async () => {
	fs.mkdirSync( out, { recursive: true } );
	fs.writeFileSync( path.join( out, 'icon.svg' ), logo );
	fs.writeFileSync( path.join( __dirname, '../smart-404-redirect/assets/logo.svg' ), logo );

	const browser = await puppeteer.launch( { executablePath: CHROME, headless: 'new' } );
	const page = await browser.newPage();

	for ( const size of [ 128, 256 ] ) {
		await page.setViewport( { width: size, height: size } );
		await page.setContent( `<!doctype html><style>html,body{margin:0}svg{display:block;width:${ size }px;height:${ size }px}</style>${ logo }` );
		await page.screenshot( { path: path.join( out, `icon-${ size }x${ size }.png` ) } );
	}

	for ( const [ w, h, k ] of [ [ 772, 250, 1 ], [ 1544, 500, 2 ] ] ) {
		await page.setViewport( { width: w, height: h } );
		await page.setContent( banner( w, h, k ), { waitUntil: 'domcontentloaded' } );
		await Promise.race( [ page.evaluate( () => document.fonts.ready.then( () => 1 ) ), new Promise( ( r ) => setTimeout( r, 6000 ) ) ] );
		await page.screenshot( { path: path.join( out, `banner-${ w }x${ h }.png` ) } );
	}

	await browser.close();
} )().catch( ( error ) => {
	console.error( error );
	process.exit( 1 );
} );

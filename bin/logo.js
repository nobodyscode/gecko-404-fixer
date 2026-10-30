/*
 * The Gecko 404 Fixer logo: a close-up of the LinkGecko gecko in a cap, drawn on the same
 * kind of pixel grid as the LinkGecko mark. One source for every size.
 */
const COLORS = {
	'.': '#0000ff', // LinkGecko blue.
	W: '#ffffff',   // The gecko, white as in the brand mark.
	S: '#c9d3ff',   // Shade on the gecko.
	K: '#0a0b0d',   // LinkGecko ink: eyes and mouth.
	C: '#ff7a1a',   // Cap.
	B: '#c4561a',   // Cap brim, a step darker.
	H: '#ff9d57',   // Cap highlight.
	Y: '#ffd12f',   // The eye, with a gecko's slit pupil.
};

const ART = [
	'...............CC...............',
	'..........HHCCCCCCCCCC..........',
	'.........HHCCCCCCCCCCCC.........',
	'........HHCCCCCCCCCCCCCC........',
	'........HCCCCCCCCCCCCCCC........',
	'.......HHCCCCCCCCCCCCCCCC.......',
	'.......HCCCCCCCCCCCCCCCCC.......',
	'.......CCCCCCCCCCCCCCCCCC.......',
	'.....BBBBBBBBBBBBBBBBBBBBBB.....',
	'..WWWWWWBBBBBBBBBBBBBBBBWWWWWW..',
	'.WKKKKKWWWWWWWWWWWWWWWWWWKKKKKW.',
	'.KYYKYYKWWWWWWWWWWWWWWWWKYYKYYK.',
	'.KYYKYYKWWWWWWWWWWWWWWWWKYYKYYK.',
	'.KYYKYYKWWWWWWWWWWWWWWWWKYYKYYK.',
	'.KYYKYYKWWWWWWWWWWWWWWWWKYYKYYK.',
	'.WKKKKKWWWWWWWWWWWWWWWWWWKKKKKW.',
	'..WWWWWWWWWWWWWWWWWWWWWWWWWWWW..',
	'..WWWWWWWWWWWWWWWWWWWWWWWWWWWW..',
	'..WWWWWWWWWWWKWWWWKWWWWWWWWWWW..',
	'..WWWWWWWWWWWWWWWWWWWWWWWWWWWW..',
	'..WKWWWWWWWWWWWWWWWWWWWWWWWWKW..',
	'..WWKWWWWWWWWWWWWWWWWWWWWWWKWW..',
	'..WWWKKKKKKKKKKKKKKKKKKKKKKWWW..',
	'...WWWWWWWWWWWWWWWWWWWWWWWWWW...',
	'...WWWWWWWWWWWWWWWWWWWWWWWWWW...',
	'...WWWWWWWWWWWWWWWWWWWWWWWWWW...',
	'..WWWWWSSSSSSSSSSSSSSSSSSWWWWW..',
	'..WWWWSSSSSSSSSSSSSSSSSSSSWWWW..',
	'.WWWWSSSSSSSSSSSSSSSSSSSSSSWWWW.',
	'.WWWWSSSSSSSSSSSSSSSSSSSSSSWWWW.',
	'.WWWSSSSSSSSSSSSSSSSSSSSSSSSWWW.',
	'WWWWSSSSSSSSSSSSSSSSSSSSSSSSWWWW',
];

// "404" stitched on the front of the cap.
const DIGITS = { 4: [ '101', '101', '111', '001', '001' ], 0: [ '111', '101', '101', '101', '111' ] };
const STITCH = { x: 11, y: 2 };

function pixels() {
	const grid = ART.map( ( row ) => [ ...row ] );

	[ 4, 0, 4 ].forEach( ( digit, i ) => {
		DIGITS[ digit ].forEach( ( row, y ) => {
			[ ...row ].forEach( ( on, x ) => {
				if ( '1' === on ) {
					grid[ STITCH.y + y ][ STITCH.x + i * 4 + x ] = 'W';
				}
			} );
		} );
	} );

	return grid;
}

function svg() {
	const grid = pixels();
	let rects = '';

	grid.forEach( ( row, y ) => {
		let x = 0;

		while ( x < row.length ) {
			let run = 1;

			while ( x + run < row.length && row[ x + run ] === row[ x ] ) {
				run++;
			}

			if ( '.' !== row[ x ] ) {
				rects += `<rect x="${ x }" y="${ y }" width="${ run }" height="1" fill="${ COLORS[ row[ x ] ] }"/>`;
			}

			x += run;
		}
	} );

	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" shape-rendering="crispEdges"><rect width="32" height="32" fill="${ COLORS[ '.' ] }"/>${ rects }</svg>`;
}

module.exports = { svg };

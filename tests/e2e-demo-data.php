<?php
/**
 * Illustrative log entries for the wordpress.org screenshots. Test-only, never shipped.
 */

global $wpdb;

// Fixtures the end-to-end tests need but a screenshot should not show.
foreach ( array( 'trail-shoes-men', 'trail-shoes-man', 'uncategorised-note' ) as $lgs404_slug ) {
	$lgs404_fixture = get_page_by_path( $lgs404_slug, OBJECT, 'post' );
	if ( $lgs404_fixture ) {
		wp_delete_post( $lgs404_fixture->ID, true );
	}
}
$lgs404_gone = get_page_by_path( 'old-sleeping-bag-review', OBJECT, 'post' );
if ( $lgs404_gone ) {
	wp_delete_post( $lgs404_gone->ID, true );
}
foreach ( array( 'choosing-hiking-boots' => 'What to look for in fit, ankle support and grip before your next long walk.', 'best-rain-jackets-for-spring' => 'Eight waterproof shells tested on wet ridgelines, from featherweight to fully featured.', 'best-tents-of-2025' => 'Our pick of two-person tents for weight, space and staying dry.', 'best-tents-of-2024' => 'Last season\'s favourites, and which of them are still worth buying.' ) as $lgs404_slug => $lgs404_text ) {
	$lgs404_fixture = get_page_by_path( $lgs404_slug, OBJECT, 'post' );
	if ( $lgs404_fixture ) {
		wp_update_post( array( 'ID' => $lgs404_fixture->ID, 'post_content' => $lgs404_text ) );
	}
}

$lgs404_ago = static function ( $minutes ) {
	return gmdate( 'Y-m-d H:i:s', time() - $minutes * 60 );
};

$lgs404_add = static function ( $path, $hits, $minutes, $result, $rule = null, $domains = array() ) use ( $wpdb, $lgs404_ago ) {
	$external = array_sum( $domains );

	$wpdb->insert(
		$wpdb->prefix . 'lgs404_log',
		array(
			'url_hash'      => md5( $path ),
			'path'          => $path,
			'hits'          => $hits,
			'first_seen'    => $lgs404_ago( $minutes + 4000 ),
			'last_seen'     => $lgs404_ago( $minutes ),
			'result'        => $result,
			'checked_at'    => $lgs404_ago( $minutes ),
			'external_hits' => $external,
			'ref_domains'   => $domains ? wp_json_encode( $domains ) : '',
		)
	);

	if ( $rule ) {
		LinkGecko\Smart404\Store::save_rule( $path, $rule );
	}
};

$lgs404_post = static function ( $slug ) {
	$post = get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) );

	return array( 'origin' => 'near', 'target_type' => 'post', 'target_id' => $post ? $post->ID : 0 );
};
$lgs404_cat  = static function ( $slug ) {
	$term = get_term_by( 'slug', $slug, 'category' );

	return array( 'origin' => 'deleted', 'target_type' => 'term', 'target_id' => $term ? $term->term_id : 0 );
};

$lgs404_add( '/blog/how-to-fix-404-erors', 212, 6, 'redirected', $lgs404_post( 'how-to-fix-404-errors' ), array( 'hikingforum.example' => 41, 'outdoorweekly.example' => 12 ) );
$lgs404_add( '/retired-gear-guide-2019', 96, 22, 'redirected', $lgs404_cat( 'trail-gear' ), array( 'gearlab.example' => 18 ) );
$lgs404_add( '/old-sleeping-bag-review', 74, 47, 'redirected', $lgs404_cat( 'trail-gear' ), array( 'campfiretalk.example' => 9 ) );
$lgs404_add( '/choosing-hiking-bots', 63, 95, 'redirected', $lgs404_post( 'choosing-hiking-boots' ) );
$lgs404_add( '/spring-rain-jackets-roundup', 41, 130, '404', null, array( 'trailnotes.example' => 7 ) );
$lgs404_add( '/best-rain-jacket-for-spring', 37, 180, 'redirected', $lgs404_post( 'best-rain-jackets-for-spring' ), array( 'packlight.example' => 6 ) );
$lgs404_add( '/2018-summit-photo-contest', 22, 260, 'gone', array( 'kind' => 'gone' ) );
$lgs404_add( '/wnter-camping-basics', 19, 310, 'redirected', $lgs404_post( 'winter-camping-basics' ), array( 'snowcamp.example' => 4 ) );
$lgs404_add( '/members-area-signup', 15, 420, '404' );
$lgs404_add( '/packing-list-weekend-hiking', 12, 600, 'redirected', $lgs404_post( 'packing-list-weekend-hike' ) );

$lgs404_words = array( 'trail', 'summit', 'ridge', 'canyon', 'alpine', 'valley', 'forest', 'river', 'coast', 'meadow', 'peak', 'glacier' );
$lgs404_sites = array( 'ridgewalkers', 'thebackpacker', 'wildroutes', 'hillsidenotes', 'fellrunnersclub', 'campingcorner', 'gearcloset', 'summitlog', 'mapandcompass' );
$lgs404_kinds = array( 'guide', 'review', 'checklist', 'routes', 'tips', 'photos' );

// Enough rows behind the visible ones for realistic totals: 128 fixed, 9 open, 14 links saved.
for ( $lgs404_i = 0; $lgs404_i < 121; $lgs404_i++ ) {
	$lgs404_path = '/' . $lgs404_words[ $lgs404_i % 12 ] . '-' . $lgs404_kinds[ $lgs404_i % 6 ] . '-' . $lgs404_i;
	$lgs404_add( $lgs404_path, 1 + ( $lgs404_i % 9 ), 800 + $lgs404_i * 40, 'redirected', $lgs404_post( 'winter-camping-basics' ), $lgs404_i < 9 ? array( $lgs404_sites[ $lgs404_i ] . '.example' => 2 ) : array() );
}

for ( $lgs404_i = 0; $lgs404_i < 7; $lgs404_i++ ) {
	$lgs404_add( '/old-newsletter-issue-' . ( 40 + $lgs404_i ), 2 + $lgs404_i, 900 + $lgs404_i * 60, '404' );
}

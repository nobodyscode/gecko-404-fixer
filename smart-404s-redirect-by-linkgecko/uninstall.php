<?php
/**
 * Removes both tables and every option, on every site of a network.
 *
 * @package LinkGecko\Smart404
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the plugin's data from the current site.
 */
function lgs404_uninstall_site() {
	global $wpdb;

	foreach ( array( 'lgs404_redirects', 'lgs404_log' ) as $lgs404_table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $lgs404_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- removing the plugin's own tables at uninstall.
	}

	foreach ( array( 'lgs404_settings', 'lgs404_db_version', 'lgs404_content_changed', 'lgs404_review_dismissed' ) as $lgs404_option ) {
		delete_option( $lgs404_option );
	}

	foreach ( array( 'lgs404_recent', 'lgs404_budget_log', 'lgs404_budget_match', 'lgs404_budget_suggest' ) as $lgs404_transient ) {
		delete_transient( $lgs404_transient );
	}

	wp_clear_scheduled_hook( 'lgs404_daily' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $lgs404_site ) {
		switch_to_blog( $lgs404_site );
		lgs404_uninstall_site();
		restore_current_blog();
	}
} else {
	lgs404_uninstall_site();
}

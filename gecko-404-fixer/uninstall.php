<?php
/**
 * Removes both tables and every option, on every site of a network.
 *
 * @package LinkGecko\Fixer404
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the plugin's data from the current site.
 */
function lgf404_uninstall_site() {
	global $wpdb;

	foreach ( array( 'lgf404_redirects', 'lgf404_log' ) as $lgf404_table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $lgf404_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- removing the plugin's own tables at uninstall.
	}

	foreach ( array( 'lgf404_settings', 'lgf404_db_version', 'lgf404_content_changed', 'lgf404_review_dismissed' ) as $lgf404_option ) {
		delete_option( $lgf404_option );
	}

	foreach ( array( 'lgf404_recent', 'lgf404_budget_log', 'lgf404_budget_match', 'lgf404_budget_suggest' ) as $lgf404_transient ) {
		delete_transient( $lgf404_transient );
	}

	wp_clear_scheduled_hook( 'lgf404_daily' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $lgf404_site ) {
		switch_to_blog( $lgf404_site );
		lgf404_uninstall_site();
		restore_current_blog();
	}
} else {
	lgf404_uninstall_site();
}

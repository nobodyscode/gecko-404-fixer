<?php
/**
 * Tables, scheduling and housekeeping.
 *
 * @package LinkGecko\Smart404
 */

namespace LinkGecko\Smart404;

defined( 'ABSPATH' ) || exit;

class Install {

	const DB_VERSION = '1';
	const LOG_DAYS   = 90;
	const LOG_ROWS   = 10000;

	public static function activate(): void {
		self::install();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'lgs404_daily' );
	}

	/**
	 * Replacing plugin files does not fire activation, and a network activation or
	 * a new site in a network never fires it for that site, so this repairs both.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'lgs404_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	private static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$rules   = Store::rules_table();
		$log     = Store::log_table();

		dbDelta(
			"CREATE TABLE {$rules} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url_hash char(32) NOT NULL,
				path text NOT NULL,
				kind varchar(10) NOT NULL DEFAULT 'redirect',
				origin varchar(10) NOT NULL DEFAULT 'manual',
				target_type varchar(10) NOT NULL DEFAULT '',
				target_id bigint(20) unsigned NOT NULL DEFAULT 0,
				target_url text NOT NULL,
				source_post bigint(20) unsigned NOT NULL DEFAULT 0,
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				created datetime NOT NULL,
				last_hit datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY url_hash (url_hash),
				KEY source_post (source_post)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$log} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url_hash char(32) NOT NULL,
				path text NOT NULL,
				hits bigint(20) unsigned NOT NULL DEFAULT 1,
				first_seen datetime NOT NULL,
				last_seen datetime NOT NULL,
				result varchar(10) NOT NULL DEFAULT '404',
				checked_at datetime DEFAULT NULL,
				external_hits bigint(20) unsigned NOT NULL DEFAULT 0,
				ref_domains text NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY url_hash (url_hash),
				KEY last_seen (last_seen),
				KEY result (result)
			) {$charset};"
		);

		update_option( 'lgs404_db_version', self::DB_VERSION );

		if ( ! wp_next_scheduled( 'lgs404_daily' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'lgs404_daily' );
		}
	}

	/**
	 * Keeps the log small: 90 days and 10,000 rows, whichever is less. Rules made
	 * from traffic go too once nobody has hit them for a year.
	 */
	public static function trim(): void {
		global $wpdb;

		$log   = Store::log_table();
		$rules = Store::rules_table();

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen < %s', $log, gmdate( 'Y-m-d H:i:s', time() - self::LOG_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- housekeeping on the plugin's own table.

		$cutoff = $wpdb->get_var( $wpdb->prepare( 'SELECT last_seen FROM %i ORDER BY last_seen DESC LIMIT 1 OFFSET %d', $log, self::LOG_ROWS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- housekeeping on the plugin's own table.

		if ( $cutoff ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen <= %s', $log, $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- housekeeping on the plugin's own table.
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE origin IN ('near','core') AND last_hit < %s", $rules, gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- housekeeping on the plugin's own table.
	}
}

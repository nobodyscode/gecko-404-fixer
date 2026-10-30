<?php
/**
 * All reads and writes against the plugin's two tables.
 *
 * Direct queries are the point here: these are the plugin's own tables, and the
 * statements are all prepared.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 *
 * @package LinkGecko\Smart404
 */

namespace LinkGecko\Smart404;

defined( 'ABSPATH' ) || exit;

class Store {

	const KIND_REDIRECT = 'redirect';
	const KIND_GONE     = 'gone';
	const KIND_KEEP     = 'keep';

	const RESULT_FIXED    = 'redirected';
	const RESULT_OPEN     = '404';
	const RESULT_GONE     = 'gone';
	const RESULT_FALLBACK = 'fallback';

	const MAX_REF_DOMAINS = 10;

	public static function rules_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'lgs404_redirects';
	}

	public static function log_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'lgs404_log';
	}

	public static function hash( string $path ): string {
		return md5( $path );
	}

	public static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/* Rules ---------------------------------------------------------------- */

	public static function find_rule( string $path ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE url_hash = %s', self::rules_table(), self::hash( $path ) ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Creates or replaces the rule for a path.
	 *
	 * @param string $path   Site-relative path.
	 * @param array  $fields kind, origin, target_type, target_id, target_url, source_post.
	 */
	public static function save_rule( string $path, array $fields ): void {
		global $wpdb;

		$fields = array_merge(
			array(
				'kind'        => self::KIND_REDIRECT,
				'origin'      => 'manual',
				'target_type' => '',
				'target_id'   => 0,
				'target_url'  => '',
				'source_post' => 0,
			),
			$fields
		);

		$existing = self::find_rule( $path );

		if ( $existing ) {
			$wpdb->update( self::rules_table(), $fields, array( 'id' => $existing['id'] ) );

			return;
		}

		$wpdb->insert(
			self::rules_table(),
			array_merge(
				$fields,
				array(
					'url_hash' => self::hash( $path ),
					'path'     => $path,
					'created'  => self::now(),
				)
			)
		);
	}

	/**
	 * Saves an automatic rule, leaving anything an admin decided alone.
	 */
	public static function save_auto_rule( string $path, array $fields ): void {
		$existing = self::find_rule( $path );

		if ( $existing && 'manual' === $existing['origin'] ) {
			return;
		}

		self::save_rule( $path, $fields );
	}

	public static function delete_rule( string $path ): void {
		global $wpdb;

		$wpdb->delete( self::rules_table(), array( 'url_hash' => self::hash( $path ) ) );
	}

	public static function delete_deleted_post_rules( int $post_id ): void {
		global $wpdb;

		$wpdb->delete(
			self::rules_table(),
			array(
				'origin'      => 'deleted',
				'source_post' => $post_id,
			)
		);
	}

	public static function count_rule_hit( string $path ): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE url_hash = %s', self::rules_table(), self::now(), self::hash( $path ) ) );
	}

	/* Log ------------------------------------------------------------------ */

	public static function find_log( string $path ): ?array {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE url_hash = %s', self::log_table(), self::hash( $path ) ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Records a hit. One row per URL, whatever the traffic.
	 *
	 * @param string $path    Site-relative path.
	 * @param string $result  One of the RESULT_ constants.
	 * @param bool   $checked Whether matching ran for this hit.
	 */
	public static function log_hit( string $path, string $result, bool $checked = false ): void {
		global $wpdb;

		$now    = self::now();
		$domain = Url::referring_domain();
		$table  = self::log_table();
		$hash   = self::hash( $path );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET hits = hits + 1, last_seen = %s, result = %s, checked_at = COALESCE(NULLIF(%s, ''), checked_at) WHERE url_hash = %s",
				$table,
				$now,
				$result,
				$checked ? $now : '',
				$hash
			)
		);

		if ( ! $updated ) {
			// A scanner inventing URLs must not be able to grow the table freely.
			if ( ! self::take_budget( 'log', 300 ) ) {
				return;
			}

			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO %i (url_hash, path, hits, first_seen, last_seen, result, checked_at, external_hits, ref_domains) VALUES (%s, %s, 1, %s, %s, %s, NULLIF(%s, ''), 0, '')",
					$table,
					$hash,
					$path,
					$now,
					$now,
					$result,
					$checked ? $now : ''
				)
			);
		}

		if ( '' !== $domain ) {
			self::add_referrer( $path, $domain );
		}
	}

	private static function add_referrer( string $path, string $domain ): void {
		global $wpdb;

		$row = self::find_log( $path );

		if ( ! $row ) {
			return;
		}

		$domains = self::referrers( $row );

		if ( isset( $domains[ $domain ] ) || count( $domains ) < self::MAX_REF_DOMAINS ) {
			$domains[ $domain ] = ( $domains[ $domain ] ?? 0 ) + 1;
		}

		$wpdb->update(
			self::log_table(),
			array(
				'external_hits' => (int) $row['external_hits'] + 1,
				'ref_domains'   => wp_json_encode( $domains ),
			),
			array( 'id' => $row['id'] )
		);
	}

	/**
	 * @param array $row A log row.
	 * @return array<string,int> Domain => hits.
	 */
	public static function referrers( array $row ): array {
		$domains = json_decode( (string) ( $row['ref_domains'] ?? '' ), true );

		return is_array( $domains ) ? $domains : array();
	}

	public static function set_result( string $path, string $result ): void {
		global $wpdb;

		$wpdb->update( self::log_table(), array( 'result' => $result ), array( 'url_hash' => self::hash( $path ) ) );
	}

	/**
	 * Limits expensive work per minute. A flood of invented URLs then costs a capped
	 * number of queries, and normal sites never reach the limit.
	 */
	public static function take_budget( string $name, int $per_minute ): bool {
		$key  = 'lgs404_budget_' . $name;
		$used = (int) get_transient( $key );

		if ( $used >= $per_minute ) {
			return false;
		}

		set_transient( $key, $used + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/* Reports -------------------------------------------------------------- */

	public static function stats(): array {
		global $wpdb;

		$table = self::log_table();
		$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		return array(
			'fixed' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE result IN (%s, %s) AND last_seen >= %s', $table, self::RESULT_FIXED, self::RESULT_FALLBACK, $since ) ),
			'open'  => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE result = %s AND last_seen >= %s', $table, self::RESULT_OPEN, $since ) ),
			'saved' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE result = %s AND external_hits > 0 AND last_seen >= %s', $table, self::RESULT_FIXED, $since ) ),
		);
	}

	public static function fixed_total(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE result = %s', self::log_table(), self::RESULT_FIXED ) );
	}

	/**
	 * Rows that other sites sent visitors to, busiest first.
	 */
	public static function external_rows( int $limit = 8 ): array {
		global $wpdb;

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE external_hits > 0 ORDER BY external_hits DESC, last_seen DESC LIMIT %d', self::log_table(), $limit ), ARRAY_A );
	}

	/**
	 * One page of the log, with each row's rule.
	 *
	 * @param array $args view, search, orderby, order, per_page, page.
	 * @return array{rows: array, total: int}
	 */
	public static function log_page( array $args ): array {
		global $wpdb;

		$log   = self::log_table();
		$rules = self::rules_table();

		$views = array(
			'fixed' => self::RESULT_FIXED,
			'open'  => self::RESULT_OPEN,
			'gone'  => self::RESULT_GONE,
		);

		// An empty value switches its filter off, so the statement itself stays fixed.
		$result = $views[ $args['view'] ?? '' ] ?? '';
		$search = empty( $args['search'] ) ? '' : '%' . $wpdb->esc_like( strtolower( (string) $args['search'] ) ) . '%';

		$column = 'hits' === ( $args['orderby'] ?? '' ) ? 'hits' : 'last_seen';
		$order  = 'asc' === strtolower( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';
		$limit  = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$offset = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $limit );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i l WHERE ( %s = '' OR l.result = %s ) AND ( %s = '' OR l.path LIKE %s )",
				$log,
				$result,
				$result,
				$search,
				$search
			)
		);

		$rows = (array) $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $order can only be ASC or DESC, as chosen above.
			$wpdb->prepare(
				// $order can only be ASC or DESC, as chosen above.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT l.*, r.kind, r.origin, r.target_type, r.target_id, r.target_url FROM %i l LEFT JOIN %i r ON r.url_hash = l.url_hash WHERE ( %s = '' OR l.result = %s ) AND ( %s = '' OR l.path LIKE %s ) ORDER BY %i {$order}, l.id DESC LIMIT %d OFFSET %d",
				$log,
				$rules,
				$result,
				$result,
				$search,
				$search,
				$column,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}
}

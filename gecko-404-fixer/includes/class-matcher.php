<?php
/**
 * Works out where a broken URL should go, and resolves saved targets.
 *
 * @package LinkGecko\Fixer404
 */

namespace LinkGecko\Fixer404;

defined( 'ABSPATH' ) || exit;

class Matcher {

	/** How many published slugs one near-match check will read. */
	const MAX_CANDIDATES = 5000;

	/**
	 * Runs the checks in order and stops at the first hit. Saved rules, including
	 * deleted posts, were already tried by the handler.
	 *
	 * @return array|null Rule fields, or null when nothing is confident enough.
	 */
	public static function find( string $path ): ?array {
		$segment = Url::last_segment( $path );

		if ( '' === $segment ) {
			return null;
		}

		$scope = self::scope( $path );

		if ( null === $scope ) {
			return null;
		}

		if ( 'post' === $scope['type'] ) {
			$found = self::renamed_post( $segment, $scope['names'] ) ?? self::core_guess();

			if ( $found ) {
				return $found;
			}
		}

		return self::near_match( $segment, $scope );
	}

	/**
	 * What this URL could be: a post of certain types, or a term of one taxonomy.
	 * Keeps /product/x matching products and /category/x matching categories.
	 *
	 * @return array{type: string, names: string[]}|null Null for archives we leave alone.
	 */
	private static function scope( string $path ): ?array {
		$first  = Url::first_segment( $path );
		$nested = false !== strpos( trim( $path, '/' ), '/' );

		if ( $nested ) {
			foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
				$base = is_array( $taxonomy->rewrite ) ? (string) ( $taxonomy->rewrite['slug'] ?? '' ) : '';

				if ( '' !== $base && strtolower( trim( $base, '/' ) ) === $first ) {
					return array(
						'type'  => 'term',
						'names' => array( $taxonomy->name ),
					);
				}
			}

			foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
				$base = is_array( $type->rewrite ) ? (string) ( $type->rewrite['slug'] ?? '' ) : '';

				if ( '' !== $base && 'attachment' !== $type->name && 'post' !== $type->name && strtolower( trim( $base, '/' ) ) === $first ) {
					return array(
						'type'  => 'post',
						'names' => array( $type->name ),
					);
				}
			}

			global $wp_rewrite;

			if ( $wp_rewrite && in_array( $first, array( $wp_rewrite->author_base, $wp_rewrite->search_base, $wp_rewrite->pagination_base, $wp_rewrite->comments_base, $wp_rewrite->feed_base ), true ) ) {
				return null;
			}
		}

		return array(
			'type'  => 'post',
			'names' => array( 'post', 'page' ),
		);
	}

	/**
	 * WordPress remembers a post's earlier slugs. Only an unambiguous one counts.
	 *
	 * @param string   $segment Slug from the URL.
	 * @param string[] $types   Post types in scope.
	 */
	private static function renamed_post( string $segment, array $types ): ?array {
		global $wpdb;

		$in  = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- only runs once per broken URL, and the result is saved.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- $in holds only placeholders.
				"SELECT DISTINCT p.ID FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_wp_old_slug' AND m.meta_value = %s AND p.post_status = 'publish' AND p.post_type IN ({$in}) LIMIT 2",
				array_merge( array( $segment ), $types )
			)
		);

		if ( 1 !== count( $ids ) ) {
			return null;
		}

		return array(
			'origin'      => 'renamed',
			'target_type' => 'post',
			'target_id'   => (int) $ids[0],
		);
	}

	private static function core_guess(): ?array {
		if ( ! function_exists( 'redirect_guess_404_permalink' ) ) {
			return null;
		}

		$url = redirect_guess_404_permalink();

		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		return self::rule_for_url( $url, 'core' );
	}

	/**
	 * Saved by post ID where possible, so the rule follows later renames.
	 */
	public static function rule_for_url( string $url, string $origin ): array {
		$post_id = url_to_postid( $url );

		if ( $post_id ) {
			return array(
				'origin'      => $origin,
				'target_type' => 'post',
				'target_id'   => $post_id,
			);
		}

		return array(
			'origin'      => $origin,
			'target_type' => 'url',
			'target_url'  => Url::path_of( $url ),
		);
	}

	/**
	 * @param string $segment Slug from the URL.
	 * @param array  $scope   From scope().
	 */
	private static function near_match( string $segment, array $scope ): ?array {
		$rows = self::candidates( $segment, $scope );
		$hits = array();

		foreach ( $rows as $row ) {
			if ( self::qualifies( $segment, (string) $row['slug'], (string) Settings::get( 'strictness' ) ) ) {
				$hits[] = (int) $row['id'];
			}
		}

		if ( 1 !== count( $hits ) ) {
			return null;
		}

		return array(
			'origin'      => 'near',
			'target_type' => $scope['type'],
			'target_id'   => $hits[0],
		);
	}

	/**
	 * @return array<int,array{id: int|string, slug: string}>
	 */
	private static function candidates( string $segment, array $scope ): array {
		global $wpdb;

		$length = strlen( $segment );
		$band   = max( 2, (int) ceil( $length * 0.18 ) );
		$in     = implode( ',', array_fill( 0, count( $scope['names'] ), '%s' ) );
		$args   = array_merge( $scope['names'], array( max( 1, $length - $band ), $length + $band, self::MAX_CANDIDATES ) );

		// $in holds only placeholders, and each statement is prepared.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.DirectDatabaseQuery
		if ( 'term' === $scope['type'] ) {
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.term_id AS id, t.slug AS slug FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} x ON x.term_id = t.term_id WHERE x.count > 0 AND x.taxonomy IN ({$in}) AND LENGTH(t.slug) BETWEEN %d AND %d LIMIT %d",
					$args
				),
				ARRAY_A
			);
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID AS id, post_name AS slug FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$in}) AND post_name <> '' AND LENGTH(post_name) BETWEEN %d AND %d ORDER BY ID DESC LIMIT %d",
				$args
			),
			ARRAY_A
		);
		// phpcs:enable
	}

	/**
	 * Decides whether a slug is confidently the one meant. A wrong 301 is worse than
	 * an honest 404, so this leans towards saying no.
	 */
	public static function qualifies( string $requested, string $slug, string $strictness = 'normal' ): bool {
		$requested = strtolower( $requested );
		$slug      = strtolower( $slug );

		if ( $requested === $slug ) {
			return true;
		}

		$longest = max( strlen( $requested ), strlen( $slug ) );

		if ( min( strlen( $requested ), strlen( $slug ) ) < 5 || $longest > 255 ) {
			return false;
		}

		// post-1 and post-2, or best-of-2024 and best-of-2025, are different pages.
		if ( self::numbers( $requested ) !== self::numbers( $slug ) ) {
			return false;
		}

		$distance   = levenshtein( $requested, $slug );
		$similarity = 1 - $distance / $longest;

		if ( 'strict' === $strictness ) {
			return ( 1 === $distance && $longest >= 6 ) || $similarity >= 0.92;
		}

		// Two edits in a short slug can be a different word entirely.
		return 1 === $distance || ( 2 === $distance && $longest >= 8 ) || $similarity >= 0.85;
	}

	private static function numbers( string $text ): array {
		preg_match_all( '/\d+/', $text, $found );

		return $found[0];
	}

	/**
	 * Where a redirect rule points right now, or '' when that no longer exists.
	 */
	public static function resolve( array $rule ): string {
		switch ( $rule['target_type'] ) {
			case 'post':
				return self::post_url( (int) $rule['target_id'] );
			case 'term':
				return self::term_url( (int) $rule['target_id'] );
			case 'url':
				return '' === (string) $rule['target_url'] ? '' : Url::to_url( (string) $rule['target_url'] );
		}

		return '';
	}

	private static function post_url( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status || ! is_post_publicly_viewable( $post ) ) {
			return '';
		}

		$url = get_permalink( $post );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * An empty term's archive is itself a 404, so climb to a parent that has posts.
	 */
	private static function term_url( int $term_id ): string {
		$term = get_term( $term_id );

		while ( $term instanceof \WP_Term ) {
			if ( $term->count > 0 ) {
				$url = get_term_link( $term );

				return is_string( $url ) ? $url : '';
			}

			$term = $term->parent ? get_term( (int) $term->parent, $term->taxonomy ) : null;
		}

		return '';
	}
}

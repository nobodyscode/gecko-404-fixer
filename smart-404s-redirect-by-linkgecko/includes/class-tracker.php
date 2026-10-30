<?php
/**
 * Notices content changing, so the old URLs can be redirected before anyone hits
 * them. Nothing here runs on page views.
 *
 * @package LinkGecko\Smart404
 */

namespace LinkGecko\Smart404;

defined( 'ABSPATH' ) || exit;

class Tracker {

	/** @var array<int,string> Term ID => link before the edit. */
	private static array $term_links = array();

	public static function register(): void {
		add_action( 'post_updated', array( __CLASS__, 'post_updated' ), 10, 3 );
		add_action( 'wp_trash_post', array( __CLASS__, 'post_removed' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'post_removed' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'post_restored' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'status_changed' ), 10, 2 );
		add_action( 'edit_terms', array( __CLASS__, 'before_term_edit' ), 10, 2 );
		add_action( 'edited_term', array( __CLASS__, 'after_term_edit' ), 10, 3 );
		add_action( 'pre_delete_term', array( __CLASS__, 'term_removed' ), 10, 2 );
	}

	/**
	 * Core only remembers old slugs for non-hierarchical posts, and never for moved
	 * pages. This covers both.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $after   Post after the update.
	 * @param \WP_Post $before  Post before the update.
	 */
	public static function post_updated( $post_id, $after, $before ): void {
		if ( 'publish' !== $before->post_status || 'publish' !== $after->post_status || ! self::is_tracked( $after ) ) {
			return;
		}

		if ( $before->post_name === $after->post_name && $before->post_parent === $after->post_parent ) {
			return;
		}

		$old = Url::path_of( (string) get_permalink( $before ) );
		$new = Url::path_of( (string) get_permalink( $after ) );

		if ( '' !== $old && $old !== $new && '/' !== $old ) {
			Store::save_auto_rule(
				$old,
				array(
					'origin'      => 'renamed',
					'target_type' => 'post',
					'target_id'   => (int) $post_id,
				)
			);
		}
	}

	/**
	 * @param int $post_id Post being trashed or deleted.
	 */
	public static function post_removed( $post_id ): void {
		$post = get_post( $post_id );

		// A post already in the trash was handled when it got there.
		if ( ! $post || 'publish' !== $post->post_status || ! self::is_tracked( $post ) ) {
			return;
		}

		$path   = Url::path_of( (string) get_permalink( $post ) );
		$target = self::closest_parent( $post );

		if ( '' === $path || '/' === $path || ! $target ) {
			return;
		}

		Store::save_auto_rule(
			$path,
			array_merge(
				$target,
				array(
					'origin'      => 'deleted',
					'source_post' => (int) $post->ID,
				)
			)
		);
	}

	/**
	 * @param int $post_id Post taken back out of the trash.
	 */
	public static function post_restored( $post_id ): void {
		Store::delete_deleted_post_rules( (int) $post_id );
	}

	/**
	 * New content may be the right answer to a URL that had no match before.
	 *
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public static function status_changed( $new_status, $old_status ): void {
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			update_option( 'lgs404_content_changed', time(), false );
		}
	}

	/**
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function before_term_edit( $term_id, $taxonomy ): void {
		if ( self::is_public_taxonomy( $taxonomy ) ) {
			$link = get_term_link( (int) $term_id, $taxonomy );

			self::$term_links[ (int) $term_id ] = is_string( $link ) ? Url::path_of( $link ) : '';
		}
	}

	/**
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function after_term_edit( $term_id, $tt_id, $taxonomy ): void {
		$old = self::$term_links[ (int) $term_id ] ?? '';

		if ( '' === $old || ! self::is_public_taxonomy( $taxonomy ) ) {
			return;
		}

		clean_term_cache( (int) $term_id, $taxonomy );

		$link = get_term_link( (int) $term_id, $taxonomy );

		if ( is_string( $link ) && Url::path_of( $link ) !== $old ) {
			Store::save_auto_rule(
				$old,
				array(
					'origin'      => 'renamed',
					'target_type' => 'term',
					'target_id'   => (int) $term_id,
				)
			);
		}
	}

	/**
	 * A deleted term's URL goes to its parent, when it has one.
	 *
	 * @param int    $term_id  Term being deleted.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function term_removed( $term_id, $taxonomy ): void {
		$term = get_term( (int) $term_id, $taxonomy );

		if ( ! $term instanceof \WP_Term || ! $term->parent || ! self::is_public_taxonomy( $taxonomy ) ) {
			return;
		}

		$link = get_term_link( $term );

		if ( is_string( $link ) ) {
			Store::save_auto_rule(
				Url::path_of( $link ),
				array(
					'origin'      => 'deleted',
					'target_type' => 'term',
					'target_id'   => (int) $term->parent,
				)
			);
		}
	}

	private static function is_tracked( \WP_Post $post ): bool {
		$type = get_post_type_object( $post->post_type );

		return $type && $type->public && 'attachment' !== $post->post_type;
	}

	private static function is_public_taxonomy( string $taxonomy ): bool {
		$object = get_taxonomy( $taxonomy );

		return $object && $object->public;
	}

	/**
	 * The closest thing that still exists: parent page, primary category, or the
	 * post type's archive. Nothing, rather than a pointless default category.
	 */
	private static function closest_parent( \WP_Post $post ): ?array {
		foreach ( get_post_ancestors( $post ) as $ancestor ) {
			if ( 'publish' === get_post_status( $ancestor ) ) {
				return array(
					'target_type' => 'post',
					'target_id'   => (int) $ancestor,
				);
			}
		}

		$term = self::primary_term( $post );

		if ( $term ) {
			return array(
				'target_type' => 'term',
				'target_id'   => (int) $term,
			);
		}

		$archive = get_post_type_archive_link( $post->post_type );

		if ( is_string( $archive ) && 'post' !== $post->post_type ) {
			return array(
				'target_type' => 'url',
				'target_url'  => Url::path_of( $archive ),
			);
		}

		return null;
	}

	private static function primary_term( \WP_Post $post ): int {
		foreach ( array( '_yoast_wpseo_primary_category', 'rank_math_primary_category', '_yoast_wpseo_primary_product_cat', 'rank_math_primary_product_cat' ) as $key ) {
			$primary = (int) get_post_meta( $post->ID, $key, true );

			if ( $primary && get_term( $primary ) instanceof \WP_Term ) {
				return $primary;
			}
		}

		$default = (int) get_option( 'default_category' );

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->public || ! $taxonomy->hierarchical ) {
				continue;
			}

			$terms = get_the_terms( $post, $taxonomy->name );

			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( (int) $term->term_id !== $default ) {
					return (int) $term->term_id;
				}
			}
		}

		return 0;
	}
}

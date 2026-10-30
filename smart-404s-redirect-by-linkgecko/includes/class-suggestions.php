<?php
/**
 * The helpful part of a real 404: posts that look like what the visitor wanted,
 * placed inside the site's own theme.
 *
 * @package LinkGecko\Smart404
 */

namespace LinkGecko\Smart404;

defined( 'ABSPATH' ) || exit;

class Suggestions {

	const COUNT = 3;

	private static bool $printed = false;

	public static function register(): void {
		add_shortcode( 'smart_404s_suggestions', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );

		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		// Just above the footer works in every theme without touching its templates:
		// classic themes through get_footer, block themes through the footer part.
		add_action( 'get_footer', array( __CLASS__, 'print_automatic' ), 1 );
		add_filter( 'render_block_core/template-part', array( __CLASS__, 'before_footer_part' ), 10, 2 );
	}

	public static function register_block(): void {
		register_block_type(
			LGS404_DIR . 'blocks/suggestions',
			array( 'render_callback' => array( __CLASS__, 'shortcode' ) )
		);
	}

	public static function enqueue(): void {
		if ( self::is_active() ) {
			wp_enqueue_style( 'lgs404-front', LGS404_URL . 'assets/front.css', array(), LGS404_VERSION );
		}
	}

	public static function shortcode(): string {
		return self::render();
	}

	public static function print_automatic(): void {
		if ( self::is_active() ) {
			echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
		}
	}

	/**
	 * @param string $content Rendered template part.
	 * @param array  $block   The parsed block.
	 */
	public static function before_footer_part( $content, $block ): string {
		$slug = (string) ( $block['attrs']['slug'] ?? '' );

		if ( 0 === strpos( $slug, 'footer' ) && self::is_active() ) {
			return self::render() . $content;
		}

		return (string) $content;
	}

	private static function is_active(): bool {
		if ( ! is_404() || ! Settings::get( 'suggestions' ) ) {
			return false;
		}

		$path = Url::current_path();

		return '' !== $path && ! Url::is_probe( $path ) && ! Url::is_file( $path );
	}

	/**
	 * Empty anywhere but a 404, and printed once however it is placed.
	 */
	public static function render(): string {
		if ( self::$printed || ! is_404() ) {
			return '';
		}

		$posts = self::find( Url::last_segment( Url::current_path() ) );

		if ( ! $posts ) {
			return '';
		}

		self::$printed = true;

		$items = '';

		foreach ( $posts as $post ) {
			$items .= sprintf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( (string) get_permalink( $post ) ),
				esc_html( get_the_title( $post ) )
			);
		}

		return sprintf(
			'<nav class="lgs404-suggestions" aria-label="%1$s"><p class="lgs404-suggestions__title">%2$s</p><ul>%3$s</ul></nav>',
			esc_attr__( 'Suggested pages', 'smart-404s-redirect-by-linkgecko' ),
			esc_html__( 'Maybe you were looking for', 'smart-404s-redirect-by-linkgecko' ),
			$items
		);
	}

	/**
	 * Posts matching the words in the URL, then recent posts to make up the number.
	 *
	 * @param string $segment Slug from the URL.
	 * @return \WP_Post[]
	 */
	private static function find( string $segment ): array {
		$posts = array();
		$words = array_filter(
			explode( '-', $segment ),
			static function ( $word ) {
				return strlen( $word ) > 3;
			}
		);

		// Searching is the costly part, so a flood of 404s only gets recent posts.
		if ( $words && Store::take_budget( 'suggest', 120 ) ) {
			$posts = self::search( implode( ' ', array_slice( $words, 0, 5 ) ), array() );

			// WordPress wants every word to match, and a broken URL rarely manages
			// that. The longest words say the most about what was wanted.
			usort(
				$words,
				static function ( $a, $b ) {
					return strlen( $b ) - strlen( $a );
				}
			);

			foreach ( array_slice( $words, 0, 3 ) as $word ) {
				if ( count( $posts ) >= self::COUNT ) {
					break;
				}

				$posts = array_merge( $posts, self::search( $word, wp_list_pluck( $posts, 'ID' ) ) );
			}

			$posts = array_slice( $posts, 0, self::COUNT );
		}

		if ( count( $posts ) < self::COUNT ) {
			$seen = wp_list_pluck( $posts, 'ID' );

			foreach ( self::recent() as $post ) {
				if ( count( $posts ) < self::COUNT && ! in_array( $post->ID, $seen, true ) ) {
					$posts[] = $post;
				}
			}
		}

		return $posts;
	}

	/**
	 * @param string $terms   Search words.
	 * @param int[]  $exclude Posts already found.
	 * @return \WP_Post[]
	 */
	private static function search( string $terms, array $exclude ): array {
		return get_posts(
			array(
				's'                   => $terms,
				'post_type'           => array( 'post', 'page', 'product' ),
				'post_status'         => 'publish',
				'posts_per_page'      => self::COUNT,
				'post__not_in'        => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- a handful of IDs, on 404 pages only.
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
	}

	/**
	 * @return \WP_Post[]
	 */
	private static function recent(): array {
		$ids = get_transient( 'lgs404_recent' );

		if ( ! is_array( $ids ) ) {
			$ids = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => self::COUNT * 2,
					'fields'              => 'ids',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);

			set_transient( 'lgs404_recent', $ids, HOUR_IN_SECONDS );
		}

		return array_filter( array_map( 'get_post', $ids ) );
	}
}

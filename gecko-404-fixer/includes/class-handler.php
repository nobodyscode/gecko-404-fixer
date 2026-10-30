<?php
/**
 * The only code that runs on the front end, and only when WordPress is about to
 * show a 404.
 *
 * @package LinkGecko\Fixer404
 */

namespace LinkGecko\Fixer404;

defined( 'ABSPATH' ) || exit;

class Handler {

	/** A "no match" is trusted for this long, unless content is published sooner. */
	const RECHECK_AFTER = WEEK_IN_SECONDS;

	/** Near-match scans per minute. */
	const MATCH_BUDGET = 60;

	public static function handle(): void {
		if ( ! is_404() || ! self::is_page_request() ) {
			return;
		}

		$path = Url::current_path();

		if ( '' === $path || Url::is_probe( $path ) || apply_filters( 'lgf404_skip_request', false, $path ) ) {
			return;
		}

		$rule = Store::find_rule( $path );

		if ( $rule ) {
			if ( Store::KIND_GONE === $rule['kind'] ) {
				Store::log_hit( $path, Store::RESULT_GONE );
				status_header( 410 );

				return;
			}

			if ( Store::KIND_KEEP === $rule['kind'] ) {
				Store::log_hit( $path, Store::RESULT_OPEN );

				return;
			}

			if ( self::redirect_by_rule( $path, $rule ) ) {
				return;
			}

			// The target is gone, so this rule can only mislead from now on.
			Store::delete_rule( $path );
		}

		$checked = false;

		if ( Settings::get( 'auto_fix' ) && ! Url::is_file( $path ) && self::needs_check( $path ) && Store::take_budget( 'match', self::MATCH_BUDGET ) ) {
			$found   = Matcher::find( $path );
			$checked = true;

			if ( $found ) {
				$fields = array_merge( $found, array( 'kind' => Store::KIND_REDIRECT ) );

				if ( self::redirect_by_rule( $path, $fields, true ) ) {
					return;
				}
			}
		}

		if ( ! Url::is_file( $path ) && self::fallback( $path, $checked ) ) {
			return;
		}

		Store::log_hit( $path, Store::RESULT_OPEN, $checked );
	}

	/**
	 * Core guessed a redirect for this 404. Respects an admin's Undo or manual rule,
	 * and otherwise records the fix so it appears in the log.
	 *
	 * @param mixed $url The URL core is about to redirect to.
	 * @return mixed
	 */
	public static function observe_core( $url ) {
		if ( ! is_404() || ! is_string( $url ) || '' === $url || ! self::is_page_request() ) {
			return $url;
		}

		$path = Url::current_path();

		if ( '' === $path || Url::is_probe( $path ) ) {
			return $url;
		}

		$rule = Store::find_rule( $path );

		if ( $rule && ( 'manual' === $rule['origin'] || Store::KIND_REDIRECT !== $rule['kind'] ) ) {
			return false; // Stands aside; handle() applies what the admin chose.
		}

		if ( ! Settings::get( 'auto_fix' ) || Url::path_of( $url ) === $path ) {
			return $url;
		}

		if ( ! $rule ) {
			Store::save_auto_rule( $path, array_merge( Matcher::rule_for_url( $url, 'core' ), array( 'kind' => Store::KIND_REDIRECT ) ) );
		}

		Store::count_rule_hit( $path );
		Store::log_hit( $path, Store::RESULT_FIXED, true );

		return $url;
	}

	private static function is_page_request(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

		return in_array( $method, array( 'GET', 'HEAD' ), true ) && ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! is_customize_preview();
	}

	/**
	 * Each broken URL is checked once. It is checked again only when something new
	 * was published, or after a week.
	 */
	private static function needs_check( string $path ): bool {
		$log = Store::find_log( $path );

		if ( ! $log || empty( $log['checked_at'] ) ) {
			return true;
		}

		$checked = (int) strtotime( $log['checked_at'] . ' UTC' );

		return $checked < time() - self::RECHECK_AFTER || $checked <= (int) get_option( 'lgf404_content_changed', 0 );
	}

	/**
	 * @param string $path Requested path.
	 * @param array  $rule Saved or newly found rule.
	 * @param bool   $save Save the rule once it proves usable.
	 */
	private static function redirect_by_rule( string $path, array $rule, bool $save = false ): bool {
		$url = Matcher::resolve( $rule );

		if ( '' === $url || Url::path_of( $url ) === $path ) {
			return false;
		}

		if ( $save ) {
			Store::save_auto_rule( $path, $rule );
		}

		Store::count_rule_hit( $path );
		Store::log_hit( $path, Store::RESULT_FIXED, $save );

		self::send( $url );

		return true;
	}

	private static function fallback( string $path, bool $checked ): bool {
		$mode = Settings::get( 'fallback' );
		$url  = '';

		if ( 'home' === $mode ) {
			$url = home_url( '/' );
		} elseif ( 'page' === $mode ) {
			$page = (int) Settings::get( 'fallback_page' );
			$url  = 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : '';
		}

		if ( '' === $url || Url::path_of( $url ) === $path ) {
			return false;
		}

		Store::log_hit( $path, Store::RESULT_FALLBACK, $checked );
		self::send( $url );

		return true;
	}

	/**
	 * Sends the 301 and ends the request. Campaign tags and the like carry over.
	 *
	 * WP::handle_404() has already sent no-cache headers, so an Undo takes effect
	 * straight away instead of being stuck in browsers.
	 */
	private static function send( string $url ): void {
		// Only characters valid in a query string. sanitize_text_field() would strip
		// percent-encoding and change the values.
		$query = isset( $_SERVER['QUERY_STRING'] ) ? preg_replace( '/[^a-zA-Z0-9\-._~%!$&\'()*+,;=:@\/?\[\]]/', '', wp_unslash( $_SERVER['QUERY_STRING'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- restricted to valid characters here, and checked again by wp_safe_redirect().

		if ( '' !== $query ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
		}

		if ( wp_safe_redirect( $url, 301, 'Gecko 404 Fixer' ) ) {
			exit;
		}
	}
}

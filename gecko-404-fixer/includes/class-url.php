<?php
/**
 * Turns requests and form input into the normalised paths everything else uses.
 *
 * @package LinkGecko\Fixer404
 */

namespace LinkGecko\Fixer404;

defined( 'ABSPATH' ) || exit;

class Url {

	const MAX_PATH = 2000;

	/** Extensions that only scanners ask a WordPress site for. */
	const PROBE_EXTENSIONS = array( 'php', 'php3', 'php5', 'phtml', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'sh', 'sql', 'zip', 'gz', 'tgz', 'tar', 'rar', '7z', 'bak', 'old', 'orig', 'swp', 'env', 'ini', 'log', 'yml', 'yaml', 'toml', 'conf', 'config', 'dat', 'db', 'sqlite', 'pem', 'key' );

	/** Paths that only scanners ask for. Compared against the start of the path. */
	const PROBE_PREFIXES = array( '/wp-admin', '/wp-includes', '/wp-content/plugins', '/wp-content/themes', '/wp-login', '/xmlrpc', '/cgi-bin', '/vendor', '/phpmyadmin', '/pma', '/myadmin', '/mysql', '/adminer', '/administrator', '/admin.', '/actuator', '/boaform', '/owa', '/telescope', '/solr', '/console', '/autodiscover', '/remote', '/_ignition', '/server-status' );

	/** Browser and crawler noise nobody can act on. */
	const NOISE = array( '/favicon.ico', '/robots.txt', '/ads.txt', '/app-ads.txt', '/sellers.json', '/security.txt', '/humans.txt', '/browserconfig.xml', '/crossdomain.xml', '/sw.js', '/service-worker.js', '/manifest.json', '/site.webmanifest' );

	/**
	 * The current request as a site-relative path, or '' when it cannot be used.
	 * Query strings are never kept, because they can hold private tokens.
	 */
	public static function current_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised by from_uri() and escaped on output.

		return self::from_uri( (string) $uri );
	}

	public static function from_uri( string $uri ): string {
		$path = wp_parse_url( $uri, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path || strlen( $path ) > self::MAX_PATH ) {
			return '';
		}

		if ( '' === wp_check_invalid_utf8( $path ) ) {
			return '';
		}

		$path = preg_replace( '#/{2,}#', '/', $path );
		$home = wp_parse_url( home_url(), PHP_URL_PATH );

		if ( is_string( $home ) && '' !== trim( $home, '/' ) ) {
			$home = '/' . trim( $home, '/' );

			if ( 0 === stripos( $path, $home . '/' ) || 0 === strcasecmp( $path, $home ) ) {
				$path = substr( $path, strlen( $home ) );
			}
		}

		$path = strtolower( '/' . trim( $path, '/' ) );

		return preg_match( '/[\x00-\x1F\x7F]/', $path ) ? '' : $path;
	}

	/**
	 * Bot probes get no matching and no log row.
	 */
	public static function is_probe( string $path ): bool {
		if ( '/' === $path || in_array( $path, self::NOISE, true ) ) {
			return true;
		}

		foreach ( self::PROBE_PREFIXES as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}

		// Dotfiles and dot-folders: /.env, /.git/config, /.aws/credentials.
		if ( false !== strpos( $path, '/.' ) && 0 !== strpos( $path, '/.well-known/' ) ) {
			return true;
		}

		if ( 0 === strpos( $path, '/.well-known/' ) || false !== strpos( $path, 'wp-config' ) || 0 === strpos( basename( $path ), 'apple-touch-icon' ) ) {
			return true;
		}

		return in_array( self::extension( $path ), self::PROBE_EXTENSIONS, true ) || 'map' === self::extension( $path );
	}

	/**
	 * A missing image is worth logging, but redirecting it to an article is wrong.
	 */
	public static function is_file( string $path ): bool {
		$extension = self::extension( $path );

		return '' !== $extension && ! in_array( $extension, array( 'html', 'htm' ), true );
	}

	public static function extension( string $path ): string {
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		return preg_match( '/^[a-z0-9]{1,8}$/', $extension ) ? $extension : '';
	}

	/**
	 * The part of the path a slug would be: decoded, without .html, ready to compare.
	 */
	public static function last_segment( string $path ): string {
		$segment = (string) basename( $path );
		$segment = preg_replace( '/\.html?$/', '', $segment );
		$segment = sanitize_title( rawurldecode( (string) $segment ) );

		return strlen( $segment ) > 200 ? '' : $segment;
	}

	public static function first_segment( string $path ): string {
		$parts = explode( '/', trim( $path, '/' ) );

		return (string) $parts[0];
	}

	/**
	 * The referring site's bare domain, only when it is another site. Nothing else
	 * about the visit is kept.
	 */
	public static function referring_domain(): string {
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by esc_url_raw().

		return self::domain_from( (string) $referrer );
	}

	public static function domain_from( string $referrer ): string {
		$host = '' === $referrer ? '' : wp_parse_url( $referrer, PHP_URL_HOST );

		if ( ! is_string( $host ) || ! preg_match( '/^[a-z0-9.-]{1,190}\.[a-z]{2,}$/i', $host ) ) {
			return '';
		}

		$host = preg_replace( '/^www\./', '', strtolower( $host ) );
		$site = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );

		return $host === $site ? '' : $host;
	}

	/**
	 * Accepts a path or a same-site URL typed by an admin. Returns the site-relative
	 * path, or '' for anything else: redirects stay on this site.
	 */
	public static function target_from_input( string $input ): string {
		$input = trim( $input );

		if ( '' === $input ) {
			return '';
		}

		if ( preg_match( '#^[a-z][a-z0-9+.-]*:|^//#i', $input ) ) {
			$host = strtolower( (string) wp_parse_url( $input, PHP_URL_HOST ) );
			$site = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

			if ( preg_replace( '/^www\./', '', $host ) !== preg_replace( '/^www\./', '', $site ) ) {
				return '';
			}
		} elseif ( '/' !== $input[0] ) {
			$input = '/' . $input;
		}

		$path = self::from_uri( $input );

		return '' === $path ? '' : $path;
	}

	/**
	 * The path of a full URL on this site, normalised the same way as requests.
	 */
	public static function path_of( string $url ): string {
		return self::from_uri( $url );
	}

	/**
	 * Builds the URL for a site-relative path, with the trailing slash the site uses.
	 */
	public static function to_url( string $path ): string {
		return home_url( user_trailingslashit( $path ) );
	}
}

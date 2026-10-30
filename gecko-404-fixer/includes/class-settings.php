<?php
/**
 * The plugin's few settings, kept in one option.
 *
 * @package LinkGecko\Fixer404
 */

namespace LinkGecko\Fixer404;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'lgf404_settings';

	public static function defaults(): array {
		return array(
			'auto_fix'      => 1,
			'strictness'    => 'normal',
			'fallback'      => 'none',
			'fallback_page' => 0,
			'suggestions'   => 1,
		);
	}

	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * @param string $key One of the keys in defaults().
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * @param mixed $input Raw form values.
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'auto_fix'      => empty( $input['auto_fix'] ) ? 0 : 1,
			'strictness'    => 'strict' === ( $input['strictness'] ?? '' ) ? 'strict' : 'normal',
			'fallback'      => in_array( $input['fallback'] ?? '', array( 'none', 'home', 'page' ), true ) ? $input['fallback'] : 'none',
			'fallback_page' => absint( $input['fallback_page'] ?? 0 ),
			'suggestions'   => empty( $input['suggestions'] ) ? 0 : 1,
		);
	}
}

<?php
/**
 * Plugin Name:       Gecko 404 Fixer by LinkGecko – Fix 404 Errors with Automatic 301 Redirects
 * Plugin URI:        https://linkgecko.com/gecko-404-fixer/
 * Description:       Fix 404 errors automatically with smart 301 redirects to the right page, not your homepage.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            LinkGecko
 * Author URI:        https://linkgecko.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gecko-404-fixer
 *
 * @package LinkGecko\Fixer404
 */

defined( 'ABSPATH' ) || exit;

define( 'LGF404_VERSION', '1.0.0' );
define( 'LGF404_FILE', __FILE__ );
define( 'LGF404_DIR', plugin_dir_path( __FILE__ ) );
define( 'LGF404_URL', plugin_dir_url( __FILE__ ) );

/*
 * LinkGecko\Fixer404\Log_Table -> includes/class-log-table.php
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'LinkGecko\\Fixer404\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$name = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
		$file = LGF404_DIR . 'includes/class-' . $name . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'LinkGecko\Fixer404\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LinkGecko\Fixer404\Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		LinkGecko\Fixer404\Plugin::boot();
	}
);

<?php
/**
 * Plugin Name:       Smart 404s by LinkGecko – Fix 404 Errors with Automatic 301 Redirects
 * Plugin URI:        https://linkgecko.com/smart-404s/
 * Description:       Fix 404 errors automatically with smart 301 redirects to the right page, not your homepage.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            LinkGecko
 * Author URI:        https://linkgecko.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smart-404-redirect
 *
 * @package LinkGecko\Smart404
 */

defined( 'ABSPATH' ) || exit;

define( 'LGS404_VERSION', '1.0.0' );
define( 'LGS404_FILE', __FILE__ );
define( 'LGS404_DIR', plugin_dir_path( __FILE__ ) );
define( 'LGS404_URL', plugin_dir_url( __FILE__ ) );

/*
 * LinkGecko\Smart404\Log_Table -> includes/class-log-table.php
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'LinkGecko\\Smart404\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$name = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
		$file = LGS404_DIR . 'includes/class-' . $name . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'LinkGecko\Smart404\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LinkGecko\Smart404\Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		LinkGecko\Smart404\Plugin::boot();
	}
);

<?php
/**
 * Wires the plugin into WordPress.
 *
 * @package LinkGecko\Fixer404
 */

namespace LinkGecko\Fixer404;

defined( 'ABSPATH' ) || exit;

class Plugin {

	public static function boot(): void {
		Install::maybe_upgrade();
		Tracker::register();
		Suggestions::register();

		add_action( 'lgf404_daily', array( Install::class, 'trim' ) );

		if ( is_admin() ) {
			Admin::register();

			return;
		}

		// Late, so manual rules from other redirect and SEO plugins always win.
		add_action( 'template_redirect', array( Handler::class, 'handle' ), 999 );

		// Core fixes some 404s itself at priority 10. Watching those keeps them in
		// the log and lets Undo stop them.
		add_filter( 'old_slug_redirect_url', array( Handler::class, 'observe_core' ), 99 );
		add_filter( 'redirect_canonical', array( Handler::class, 'observe_core' ), 99 );
	}
}

<?php
/**
 * Test-only mu-plugin. Mounted by the end-to-end run, never shipped: it lets the
 * test script change content and read the plugin's tables as an admin would.
 */

// Stands in for any other plugin's redirect, at the two points SEO plugins use.
add_action(
	'wp',
	static function () {
		if ( false !== strpos( $_SERVER['REQUEST_URI'], 'how-to-fix-404-errwp' ) ) {
			wp_redirect( home_url( '/contact-us/' ), 301, 'Other plugin' );
			exit;
		}
	}
);
add_action(
	'template_redirect',
	static function () {
		if ( false !== strpos( $_SERVER['REQUEST_URI'], 'how-to-fix-404-errtr' ) ) {
			wp_redirect( home_url( '/contact-us/' ), 301, 'Other plugin' );
			exit;
		}
	}
);

add_action(
	'init',
	static function () {
		if ( empty( $_GET['lgf404_e2e'] ) ) {
			return;
		}

		global $wpdb;

		$op   = $_GET['lgf404_e2e'];
		$slug = $_GET['slug'] ?? '';
		$post = $slug ? get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) ) : null;
		$out  = array( 'ok' => true );

		switch ( $op ) {
			case 'rename':
				wp_update_post( array( 'ID' => $post->ID, 'post_name' => $_GET['to'] ) );
				break;
			case 'trash':
				wp_trash_post( $post->ID );
				break;
			case 'delete':
				wp_delete_post( $post->ID, true );
				break;
			case 'restore':
				$found = get_posts( array( 'post_status' => 'trash', 'name' => $slug . '__trashed', 'post_type' => 'any' ) );
				wp_untrash_post( $found[0]->ID );
				wp_publish_post( $found[0]->ID );
				break;
			case 'publish':
				wp_insert_post( array( 'post_title' => $_GET['title'], 'post_name' => $slug, 'post_status' => 'publish' ) );
				break;
			case 'rule':
				$path = '/' . trim( $_GET['path'], '/' );
				if ( 'undo' === $_GET['kind'] ) {
					LinkGecko\Fixer404\Store::save_rule( $path, array( 'kind' => 'keep' ) );
					LinkGecko\Fixer404\Store::set_result( $path, '404' );
				} elseif ( 'gone' === $_GET['kind'] ) {
					LinkGecko\Fixer404\Store::save_rule( $path, array( 'kind' => 'gone' ) );
				} else {
					LinkGecko\Fixer404\Store::save_rule( $path, array( 'target_type' => 'url', 'target_url' => $_GET['to'] ) );
				}
				break;
			case 'settings':
				update_option( 'lgf404_settings', array_merge( LinkGecko\Fixer404\Settings::all(), array( $_GET['key'] => $_GET['value'] ) ) );
				break;
			case 'log':
				$out = $wpdb->get_results( "SELECT path, hits, result, external_hits, ref_domains FROM {$wpdb->prefix}lgf404_log", ARRAY_A );
				break;
			case 'rules':
				$out = $wpdb->get_results( "SELECT path, kind, origin, target_type, target_url FROM {$wpdb->prefix}lgf404_redirects", ARRAY_A );
				break;
			case 'stats':
				$out = LinkGecko\Fixer404\Store::stats();
				break;
			case 'redirection':
				$schema = new Redirection\Database\Schema\Latest();
				$schema->install();
				$out   = Red_Item::create( array( 'url' => $_GET['from'], 'action_data' => array( 'url' => $_GET['to'] ), 'regex' => false, 'group_id' => 1, 'match_type' => 'url', 'action_type' => 'url', 'action_code' => 301 ) );
				$out   = is_wp_error( $out ) ? array( 'ok' => false, 'error' => $out->get_error_message() ) : array( 'ok' => true );
				break;
			case 'product':
				$id = (int) get_option( 'lgf404_test_product' );
				if ( 'rename' === $_GET['do'] ) {
					wp_update_post( array( 'ID' => $id, 'post_name' => 'crimson-rain-jacket' ) );
				} else {
					wp_trash_post( $id );
				}
				break;
			case 'product_cat':
				wp_update_term( (int) get_option( 'lgf404_test_cat' ), 'product_cat', array( 'slug' => 'wet-weather' ) );
				break;
			case 'plugins':
				$out = get_option( 'active_plugins' );
				break;
			case 'sapi':
				$out = array( 'sapi' => PHP_SAPI, 'red_is_cli' => function_exists( 'red_is_cli' ) ? red_is_cli() : null );
				break;
			case 'uninstall':
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				deactivate_plugins( 'gecko-404-fixer/gecko-404-fixer.php' );
				uninstall_plugin( 'gecko-404-fixer/gecko-404-fixer.php' );
				$out = array(
					'tables'  => $wpdb->get_col( "SHOW TABLES LIKE '%lgf404%'" ),
					'options' => $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%lgf404%'" ),
					'cron'    => wp_next_scheduled( 'lgf404_daily' ),
				);
				break;
			case 'reditems':
				$out = array(
					'items'   => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}redirection_items", ARRAY_A ),
					'groups'  => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}redirection_groups", ARRAY_A ),
					'options' => get_option( 'redirection_options' ),
				);
				break;
			case 'demo':
				require WP_CONTENT_DIR . '/e2e-demo-data.php';
				break;
			case 'trim':
				LinkGecko\Fixer404\Install::trim();
				break;
		}

		wp_send_json( $out );
	}
);

<?php
/**
 * Tools → Smart 404s. One page: results, the log, other sites' links, settings.
 *
 * @package LinkGecko\Smart404
 */

namespace LinkGecko\Smart404;

defined( 'ABSPATH' ) || exit;

class Admin {

	const SLUG = 'smart-404s';

	/** How many fixes the plugin should have earned before asking for a review. */
	const REVIEW_AFTER = 10;

	const LINKGECKO = 'https://linkgecko.com/';

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_lgs404_rule', array( __CLASS__, 'handle_rule' ) );
		add_action( 'admin_post_lgs404_dismiss_review', array( __CLASS__, 'dismiss_review' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LGS404_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function url(): string {
		return admin_url( 'tools.php?page=' . self::SLUG );
	}

	/**
	 * A LinkGecko link, tagged so its clicks can be measured.
	 */
	public static function linkgecko_url( string $placement ): string {
		return add_query_arg(
			array(
				'utm_source'   => 'wp-plugin',
				'utm_medium'   => 'admin',
				'utm_campaign' => 'smart-404s',
				'utm_content'  => $placement,
			),
			self::LINKGECKO
		);
	}

	public static function menu(): void {
		add_management_page(
			__( 'Smart 404s', 'smart-404s-redirect-by-linkgecko' ),
			__( 'Smart 404s', 'smart-404s-redirect-by-linkgecko' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param string[] $links Plugin row links.
	 * @return string[]
	 */
	public static function action_links( $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::url() ), esc_html__( 'Open', 'smart-404s-redirect-by-linkgecko' ) )
		);

		return $links;
	}

	public static function register_settings(): void {
		register_setting(
			'lgs404',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * @param string $hook Current admin screen.
	 */
	public static function enqueue( $hook ): void {
		if ( 'tools_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'lgs404-admin', LGS404_URL . 'assets/admin.css', array(), LGS404_VERSION );
		wp_enqueue_script( 'lgs404-admin', LGS404_URL . 'assets/admin.js', array( 'wp-api-fetch' ), LGS404_VERSION, true );
	}

	/**
	 * Saves a row action. Everything here is on the site's own URLs, by an admin.
	 */
	public static function handle_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'smart-404s-redirect-by-linkgecko' ), 403 );
		}

		check_admin_referer( 'lgs404_rule' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised by Url::from_uri() and Url::target_from_input().
		$op     = isset( $_REQUEST['op'] ) ? sanitize_key( wp_unslash( $_REQUEST['op'] ) ) : '';
		$path   = isset( $_REQUEST['path'] ) ? Url::from_uri( rawurldecode( (string) wp_unslash( $_REQUEST['path'] ) ) ) : '';
		$target = isset( $_POST['target'] ) ? Url::target_from_input( (string) wp_unslash( $_POST['target'] ) ) : '';
		// phpcs:enable

		$notice = 'saved';

		if ( '' === $path || '/' === $path ) {
			$notice = 'bad_path';
		} elseif ( 'gone' === $op ) {
			Store::save_rule( $path, array( 'kind' => Store::KIND_GONE ) );
			Store::set_result( $path, Store::RESULT_GONE );
		} elseif ( 'undo' === $op ) {
			// Kept as a rule so automatic fixing does not simply do it again.
			Store::save_rule( $path, array( 'kind' => Store::KIND_KEEP ) );
			Store::set_result( $path, Store::RESULT_OPEN );
		} elseif ( 'redirect' === $op ) {
			$notice = self::save_manual_redirect( $path, $target );
		}

		wp_safe_redirect( add_query_arg( 'lgs404_notice', $notice, self::url() ) );
		exit;
	}

	private static function save_manual_redirect( string $path, string $target ): string {
		if ( '' === $target ) {
			return 'bad_target';
		}

		if ( $target === $path ) {
			return 'loop';
		}

		$back = Store::find_rule( $target );

		if ( $back && Store::KIND_REDIRECT === $back['kind'] && Url::path_of( Matcher::resolve( $back ) ) === $path ) {
			return 'loop';
		}

		Store::save_rule(
			$path,
			array(
				'target_type' => 'url',
				'target_url'  => $target,
			)
		);
		Store::set_result( $path, Store::RESULT_FIXED );

		return 'saved';
	}

	public static function dismiss_review(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'smart-404s-redirect-by-linkgecko' ), 403 );
		}

		check_admin_referer( 'lgs404_dismiss_review' );
		update_option( 'lgs404_review_dismissed', 1, false );

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * What happened to a URL, in a few words.
	 *
	 * @param array $item Log row with its rule.
	 */
	public static function result_label( array $item ): string {
		$result = (string) $item['result'];

		if ( Store::RESULT_GONE === $result ) {
			return self::badge( __( 'Gone (410)', 'smart-404s-redirect-by-linkgecko' ), 'muted' );
		}

		if ( Store::RESULT_FALLBACK === $result ) {
			return self::badge( __( 'Sent to fallback', 'smart-404s-redirect-by-linkgecko' ), 'muted' );
		}

		if ( Store::RESULT_FIXED === $result ) {
			$url = empty( $item['target_type'] ) ? '' : Matcher::resolve( $item );

			return self::badge( self::origin_label( (string) ( $item['origin'] ?? '' ) ), 'fixed' )
				. ( '' === $url ? '' : sprintf( '<span class="lgs404-arrow">→ <a href="%1$s" target="_blank" rel="noopener">%2$s</a></span>', esc_url( $url ), esc_html( Url::path_of( $url ) ) ) );
		}

		if ( Store::KIND_KEEP === ( $item['kind'] ?? '' ) ) {
			return self::badge( __( '404, as you chose', 'smart-404s-redirect-by-linkgecko' ), 'muted' );
		}

		return self::badge( __( 'Still 404', 'smart-404s-redirect-by-linkgecko' ), 'open' );
	}

	private static function origin_label( string $origin ): string {
		$labels = array(
			'renamed' => __( 'Renamed', 'smart-404s-redirect-by-linkgecko' ),
			'deleted' => __( 'Deleted', 'smart-404s-redirect-by-linkgecko' ),
			'near'    => __( 'Typo fixed', 'smart-404s-redirect-by-linkgecko' ),
			'core'    => __( 'Matched', 'smart-404s-redirect-by-linkgecko' ),
			'manual'  => __( 'Your redirect', 'smart-404s-redirect-by-linkgecko' ),
		);

		return $labels[ $origin ] ?? __( 'Fixed', 'smart-404s-redirect-by-linkgecko' );
	}

	private static function badge( string $label, string $tone ): string {
		return sprintf( '<span class="lgs404-badge lgs404-badge--%1$s">%2$s</span>', esc_attr( $tone ), esc_html( $label ) );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stats    = Store::stats();
		$table    = new Log_Table();
		$external = Store::external_rows();
		$settings = Settings::all();

		$table->prepare_items();

		include LGS404_DIR . 'includes/view-admin.php';
	}

	private static function notice_text( string $key ): string {
		$notices = array(
			'saved'      => __( 'Saved.', 'smart-404s-redirect-by-linkgecko' ),
			'bad_path'   => __( 'That URL could not be read, so nothing changed.', 'smart-404s-redirect-by-linkgecko' ),
			'bad_target' => __( 'Redirects can only point to a page on this site. Nothing changed.', 'smart-404s-redirect-by-linkgecko' ),
			'loop'       => __( 'That would send visitors in a circle, so it was not saved.', 'smart-404s-redirect-by-linkgecko' ),
		);

		return $notices[ $key ] ?? '';
	}

	/**
	 * Printed by the view, from the redirect after a row action.
	 */
	public static function print_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which fixed message to show.
		$key  = isset( $_GET['lgs404_notice'] ) ? sanitize_key( wp_unslash( $_GET['lgs404_notice'] ) ) : '';
		$text = self::notice_text( $key );

		if ( '' !== $text ) {
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				'saved' === $key ? 'notice-success' : 'notice-error',
				esc_html( $text )
			);
		}
	}

	public static function should_ask_for_review(): bool {
		return ! get_option( 'lgs404_review_dismissed' ) && Store::fixed_total() >= self::REVIEW_AFTER;
	}
}

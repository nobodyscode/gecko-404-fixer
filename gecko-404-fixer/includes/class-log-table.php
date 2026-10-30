<?php
/**
 * The 404 error log, using the same table WordPress uses everywhere else.
 *
 * @package LinkGecko\Fixer404
 */

namespace LinkGecko\Fixer404;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Log_Table extends \WP_List_Table {

	const PER_PAGE = 20;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'lgf404-url',
				'plural'   => 'lgf404-urls',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'path'      => __( 'URL', 'gecko-404-fixer' ),
			'hits'      => __( 'Hits', 'gecko-404-fixer' ),
			'last_seen' => __( 'Last seen', 'gecko-404-fixer' ),
			'result'    => __( 'Result', 'gecko-404-fixer' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'hits'      => array( 'hits', true ),
			'last_seen' => array( 'last_seen', true ),
		);
	}

	protected function get_primary_column_name() {
		return 'path';
	}

	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters for a list table.
		$page = Store::log_page(
			array(
				'view'     => isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '',
				'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '',
				'per_page' => self::PER_PAGE,
				'page'     => $this->get_pagenum(),
			)
		);
		// phpcs:enable

		$this->items           = $page['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'path' );

		$this->set_pagination_args(
			array(
				'total_items' => $page['total'],
				'per_page'    => self::PER_PAGE,
			)
		);
	}

	protected function get_views() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$current = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		$views   = array(
			''      => __( 'All', 'gecko-404-fixer' ),
			'fixed' => __( 'Fixed', 'gecko-404-fixer' ),
			'open'  => __( 'Still 404', 'gecko-404-fixer' ),
			'gone'  => __( 'Gone', 'gecko-404-fixer' ),
		);
		$links   = array();

		foreach ( $views as $key => $label ) {
			$links[ '' === $key ? 'all' : $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( add_query_arg( array( 'view' => $key ), Admin::url() ) ),
				$current === $key ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}

		return $links;
	}

	public function no_items() {
		esc_html_e( 'No 404 errors logged yet. Gecko 404 Fixer only logs real visits, so this fills up as people and search engines reach broken URLs.', 'gecko-404-fixer' );
	}

	/**
	 * @param array  $item   Row.
	 * @param string $column Column key.
	 */
	protected function column_default( $item, $column ) {
		return esc_html( (string) ( $item[ $column ] ?? '' ) );
	}

	/**
	 * @param array $item Row.
	 */
	protected function column_path( $item ) {
		$path = (string) $item['path'];

		$output = sprintf( '<code class="lgf404-path">%s</code>', esc_html( $path ) );

		$output .= sprintf(
			'<form class="lgf404-redirect-form" method="post" action="%1$s" hidden>
				<input type="hidden" name="action" value="lgf404_rule" />
				<input type="hidden" name="op" value="redirect" />
				<input type="hidden" name="path" value="%2$s" />
				%3$s
				<label class="screen-reader-text" for="lgf404-target-%4$d">%5$s</label>
				<input id="lgf404-target-%4$d" class="lgf404-target regular-text" type="text" name="target" list="lgf404-pages" placeholder="%6$s" autocomplete="off" required />
				<button type="submit" class="lgf404-btn lgf404-btn--small">%7$s</button>
			</form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $path ),
			wp_nonce_field( 'lgf404_rule', '_wpnonce', true, false ),
			(int) $item['id'],
			esc_html__( 'Redirect to', 'gecko-404-fixer' ),
			esc_attr__( 'Start typing a title, or paste a URL on this site', 'gecko-404-fixer' ),
			esc_html__( 'Save redirect', 'gecko-404-fixer' )
		);

		return $output;
	}

	/**
	 * @param array  $item        Row.
	 * @param string $column_name Column key.
	 * @param string $primary     Primary column key.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = array(
			'lgf404_redirect' => sprintf(
				'<a href="#" class="lgf404-open-redirect">%s</a>',
				esc_html__( 'Redirect to…', 'gecko-404-fixer' )
			),
		);

		if ( Store::KIND_GONE !== ( $item['kind'] ?? '' ) ) {
			$actions['lgf404_gone'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::action_url( 'gone', (string) $item['path'] ) ),
				esc_html__( 'Mark as gone (410)', 'gecko-404-fixer' )
			);
		}

		$undoable = Store::KIND_GONE === ( $item['kind'] ?? '' ) || Store::RESULT_FIXED === $item['result'];

		if ( $undoable ) {
			$actions['lgf404_undo'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::action_url( 'undo', (string) $item['path'] ) ),
				esc_html__( 'Undo', 'gecko-404-fixer' )
			);
		}

		return $this->row_actions( $actions );
	}

	private static function action_url( string $op, string $path ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'lgf404_rule',
					'op'     => $op,
					'path'   => rawurlencode( $path ),
				),
				admin_url( 'admin-post.php' )
			),
			'lgf404_rule'
		);
	}

	/**
	 * @param array $item Row.
	 */
	protected function column_hits( $item ) {
		return esc_html( number_format_i18n( (int) $item['hits'] ) );
	}

	/**
	 * @param array $item Row.
	 */
	protected function column_last_seen( $item ) {
		return esc_html(
			sprintf(
				/* translators: %s: how long ago, such as "2 hours". */
				__( '%s ago', 'gecko-404-fixer' ),
				human_time_diff( (int) strtotime( $item['last_seen'] . ' UTC' ) )
			)
		);
	}

	/**
	 * @param array $item Row.
	 */
	protected function column_result( $item ) {
		return Admin::result_label( $item );
	}
}

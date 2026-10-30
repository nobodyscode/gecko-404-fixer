<?php
/**
 * The Tools → Smart 404s screen.
 *
 * @package LinkGecko\Smart404
 *
 * @var array                       $stats    From Store::stats().
 * @var \LinkGecko\Smart404\Log_Table $table    Prepared log table.
 * @var array                       $external Rows other sites sent visitors to.
 * @var array                       $settings Current settings.
 */

namespace LinkGecko\Smart404;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lgs404">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Smart 404s', 'smart-404-redirect' ); ?></h1>

	<header class="lgs404-head">
		<div class="lgs404-brand">
			<img src="<?php echo esc_url( LGS404_URL . 'assets/logo.svg' ); ?>" alt="" width="48" height="48" />
			<div>
				<strong><?php esc_html_e( 'Smart 404s', 'smart-404-redirect' ); ?></strong>
				<span class="lgs404-mono"><?php esc_html_e( 'Sends broken URLs to the right page', 'smart-404-redirect' ); ?></span>
			</div>
		</div>
		<a class="lgs404-by" href="<?php echo esc_url( Admin::linkgecko_url( 'header' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'by LinkGecko', 'smart-404-redirect' ); ?></a>
	</header>

	<?php Admin::print_notice(); ?>
	<?php settings_errors( 'lgs404' ); ?>

	<section class="lgs404-cards" aria-label="<?php esc_attr_e( 'Results from the last 30 days', 'smart-404-redirect' ); ?>">
		<div class="lgs404-card">
			<span class="lgs404-label"><?php esc_html_e( 'Fixed, 30 days', 'smart-404-redirect' ); ?></span>
			<span class="lgs404-number"><?php echo esc_html( number_format_i18n( $stats['fixed'] ) ); ?></span>
			<span class="lgs404-hint"><?php esc_html_e( 'Broken URLs now reaching a real page', 'smart-404-redirect' ); ?></span>
		</div>
		<div class="lgs404-card">
			<span class="lgs404-label"><?php esc_html_e( 'Still 404ing', 'smart-404-redirect' ); ?></span>
			<span class="lgs404-number"><?php echo esc_html( number_format_i18n( $stats['open'] ) ); ?></span>
			<span class="lgs404-hint"><?php esc_html_e( 'No confident match. Yours to decide below', 'smart-404-redirect' ); ?></span>
		</div>
		<div class="lgs404-card">
			<span class="lgs404-label"><?php esc_html_e( 'Links from other sites saved', 'smart-404-redirect' ); ?></span>
			<span class="lgs404-number"><?php echo esc_html( number_format_i18n( $stats['saved'] ) ); ?></span>
			<span class="lgs404-hint"><?php esc_html_e( 'Outside links that would have hit a dead page', 'smart-404-redirect' ); ?></span>
		</div>
	</section>

	<?php if ( Admin::should_ask_for_review() ) : ?>
		<div class="lgs404-review">
			<p>
				<?php esc_html_e( 'Smart 404s has fixed 10 or more broken URLs on this site. If it helped, a short review helps other people find it.', 'smart-404-redirect' ); ?>
			</p>
			<a class="lgs404-btn lgs404-btn--small" href="https://wordpress.org/support/plugin/smart-404-redirect/reviews/#new-post" target="_blank" rel="noopener"><?php esc_html_e( 'Leave a review', 'smart-404-redirect' ); ?></a>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lgs404_dismiss_review" />
				<?php wp_nonce_field( 'lgs404_dismiss_review' ); ?>
				<button type="submit" class="button-link"><?php esc_html_e( 'Dismiss', 'smart-404-redirect' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<section class="lgs404-panel">
		<div class="lgs404-panel__head">
			<h2><?php esc_html_e( '404 error log', 'smart-404-redirect' ); ?></h2>
			<form method="get" class="lgs404-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( Admin::SLUG ); ?>" />
				<?php $table->search_box( __( 'Search URLs', 'smart-404-redirect' ), 'lgs404-search' ); ?>
			</form>
		</div>
		<?php $table->views(); ?>
		<?php $table->display(); ?>
		<datalist id="lgs404-pages"></datalist>
	</section>

	<div class="lgs404-columns">
		<section class="lgs404-panel">
			<h2><?php esc_html_e( 'Links from other sites', 'smart-404-redirect' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Another site linked to a page that no longer exists. These links carry visitors and authority, so they are worth keeping alive.', 'smart-404-redirect' ); ?></p>

			<?php if ( $external ) : ?>
				<table class="lgs404-links">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'From', 'smart-404-redirect' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Dead URL', 'smart-404-redirect' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Lands on', 'smart-404-redirect' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $external as $lgs404_row ) : ?>
							<?php
							$lgs404_rule = Store::find_rule( $lgs404_row['path'] );
							$lgs404_url  = $lgs404_rule && Store::KIND_REDIRECT === $lgs404_rule['kind'] ? Matcher::resolve( $lgs404_rule ) : '';
							?>
							<tr>
								<td><?php echo esc_html( implode( ', ', array_keys( Store::referrers( $lgs404_row ) ) ) ); ?></td>
								<td><code class="lgs404-path"><?php echo esc_html( $lgs404_row['path'] ); ?></code></td>
								<td>
									<?php if ( '' !== $lgs404_url ) : ?>
										<code class="lgs404-path"><?php echo esc_html( Url::path_of( $lgs404_url ) ); ?></code>
									<?php else : ?>
										<span class="lgs404-badge lgs404-badge--open"><?php esc_html_e( 'Still 404', 'smart-404-redirect' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="lgs404-empty"><?php esc_html_e( 'Nothing yet. When another site sends someone to a broken URL, it appears here with where they ended up.', 'smart-404-redirect' ); ?></p>
			<?php endif; ?>

			<div class="lgs404-cta">
				<p><strong><?php esc_html_e( 'Links like these are worth having.', 'smart-404-redirect' ); ?></strong> <?php esc_html_e( 'LinkGecko finds the review sites, lists and threads worth getting your next ones from.', 'smart-404-redirect' ); ?></p>
				<a class="lgs404-btn" href="<?php echo esc_url( Admin::linkgecko_url( 'links-card' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Find your next links', 'smart-404-redirect' ); ?> <span aria-hidden="true">→</span></a>
			</div>
		</section>

		<section class="lgs404-panel">
			<h2><?php esc_html_e( 'Settings', 'smart-404-redirect' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'lgs404' ); ?>
				<?php $lgs404_option = Settings::OPTION; ?>

				<p class="lgs404-field">
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $lgs404_option ); ?>[auto_fix]" value="1" <?php checked( $settings['auto_fix'], 1 ); ?> />
						<strong><?php esc_html_e( 'Fix 404 errors automatically', 'smart-404-redirect' ); ?></strong>
					</label>
				</p>

				<p class="lgs404-field">
					<label for="lgs404-strictness"><strong><?php esc_html_e( 'How close a match must be', 'smart-404-redirect' ); ?></strong></label>
					<select id="lgs404-strictness" name="<?php echo esc_attr( $lgs404_option ); ?>[strictness]">
						<option value="normal" <?php selected( $settings['strictness'], 'normal' ); ?>><?php esc_html_e( 'Normal: fixes typos of up to two letters', 'smart-404-redirect' ); ?></option>
						<option value="strict" <?php selected( $settings['strictness'], 'strict' ); ?>><?php esc_html_e( 'Strict: only near-identical URLs', 'smart-404-redirect' ); ?></option>
					</select>
				</p>

				<p class="lgs404-field">
					<label for="lgs404-fallback"><strong><?php esc_html_e( 'When nothing matches', 'smart-404-redirect' ); ?></strong></label>
					<select id="lgs404-fallback" name="<?php echo esc_attr( $lgs404_option ); ?>[fallback]">
						<option value="none" <?php selected( $settings['fallback'], 'none' ); ?>><?php esc_html_e( 'Keep the 404, with suggestions (recommended)', 'smart-404-redirect' ); ?></option>
						<option value="home" <?php selected( $settings['fallback'], 'home' ); ?>><?php esc_html_e( 'Redirect to the homepage', 'smart-404-redirect' ); ?></option>
						<option value="page" <?php selected( $settings['fallback'], 'page' ); ?>><?php esc_html_e( 'Redirect to a page I choose', 'smart-404-redirect' ); ?></option>
					</select>
					<span class="description lgs404-fallback-warning"><?php esc_html_e( 'Google usually treats blanket redirects as soft 404s.', 'smart-404-redirect' ); ?></span>
				</p>

				<p class="lgs404-field lgs404-fallback-page">
					<label for="lgs404-fallback-page"><strong><?php esc_html_e( 'Page to redirect to', 'smart-404-redirect' ); ?></strong></label>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => esc_attr( $lgs404_option ) . '[fallback_page]',
							'id'                => 'lgs404-fallback-page',
							'selected'          => (int) $settings['fallback_page'],
							'show_option_none'  => esc_html__( 'Choose a page', 'smart-404-redirect' ),
							'option_none_value' => 0,
						)
					);
					?>
				</p>

				<p class="lgs404-field">
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $lgs404_option ); ?>[suggestions]" value="1" <?php checked( $settings['suggestions'], 1 ); ?> />
						<strong><?php esc_html_e( 'Suggest related pages on the 404 page', 'smart-404-redirect' ); ?></strong>
					</label>
				</p>

				<button type="submit" class="lgs404-btn"><?php esc_html_e( 'Save settings', 'smart-404-redirect' ); ?></button>
			</form>
		</section>
	</div>

	<footer class="lgs404-foot">
		<?php
		printf(
			/* translators: %s: link to LinkGecko. */
			esc_html__( 'Smart 404s is free and complete. It never contacts any outside service. Made by %s.', 'smart-404-redirect' ),
			'<a href="' . esc_url( Admin::linkgecko_url( 'footer' ) ) . '" target="_blank" rel="noopener">LinkGecko</a>'
		);
		?>
	</footer>
</div>

<?php
/**
 * The Tools → Gecko 404 Fixer screen.
 *
 * @package LinkGecko\Fixer404
 *
 * @var array                       $stats    From Store::stats().
 * @var \LinkGecko\Fixer404\Log_Table $table    Prepared log table.
 * @var array                       $external Rows other sites sent visitors to.
 * @var array                       $settings Current settings.
 */

namespace LinkGecko\Fixer404;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap lgf404">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Gecko 404 Fixer', 'gecko-404-fixer' ); ?></h1>

	<header class="lgf404-head">
		<div class="lgf404-brand">
			<img src="<?php echo esc_url( LGF404_URL . 'assets/logo.svg' ); ?>" alt="" width="48" height="48" />
			<div>
				<strong><?php esc_html_e( 'Gecko 404 Fixer', 'gecko-404-fixer' ); ?></strong>
				<span class="lgf404-mono"><?php esc_html_e( 'Sends broken URLs to the right page', 'gecko-404-fixer' ); ?></span>
			</div>
		</div>
		<a class="lgf404-by" href="<?php echo esc_url( Admin::linkgecko_url( 'header' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'by LinkGecko', 'gecko-404-fixer' ); ?></a>
	</header>

	<?php Admin::print_notice(); ?>
	<?php settings_errors( 'lgf404' ); ?>

	<section class="lgf404-cards" aria-label="<?php esc_attr_e( 'Results from the last 30 days', 'gecko-404-fixer' ); ?>">
		<div class="lgf404-card">
			<span class="lgf404-label"><?php esc_html_e( 'Fixed, 30 days', 'gecko-404-fixer' ); ?></span>
			<span class="lgf404-number"><?php echo esc_html( number_format_i18n( $stats['fixed'] ) ); ?></span>
			<span class="lgf404-hint"><?php esc_html_e( 'Broken URLs now reaching a real page', 'gecko-404-fixer' ); ?></span>
		</div>
		<div class="lgf404-card">
			<span class="lgf404-label"><?php esc_html_e( 'Still 404ing', 'gecko-404-fixer' ); ?></span>
			<span class="lgf404-number"><?php echo esc_html( number_format_i18n( $stats['open'] ) ); ?></span>
			<span class="lgf404-hint"><?php esc_html_e( 'No confident match. Yours to decide below', 'gecko-404-fixer' ); ?></span>
		</div>
		<div class="lgf404-card">
			<span class="lgf404-label"><?php esc_html_e( 'Links from other sites saved', 'gecko-404-fixer' ); ?></span>
			<span class="lgf404-number"><?php echo esc_html( number_format_i18n( $stats['saved'] ) ); ?></span>
			<span class="lgf404-hint"><?php esc_html_e( 'Outside links that would have hit a dead page', 'gecko-404-fixer' ); ?></span>
		</div>
	</section>

	<?php if ( Admin::should_ask_for_review() ) : ?>
		<div class="lgf404-review">
			<p>
				<?php esc_html_e( 'Gecko 404 Fixer has fixed 10 or more broken URLs on this site. If it helped, a short review helps other people find it.', 'gecko-404-fixer' ); ?>
			</p>
			<a class="lgf404-btn lgf404-btn--small" href="https://wordpress.org/support/plugin/gecko-404-fixer/reviews/#new-post" target="_blank" rel="noopener"><?php esc_html_e( 'Leave a review', 'gecko-404-fixer' ); ?></a>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lgf404_dismiss_review" />
				<?php wp_nonce_field( 'lgf404_dismiss_review' ); ?>
				<button type="submit" class="button-link"><?php esc_html_e( 'Dismiss', 'gecko-404-fixer' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<section class="lgf404-panel">
		<div class="lgf404-panel__head">
			<h2><?php esc_html_e( '404 error log', 'gecko-404-fixer' ); ?></h2>
			<form method="get" class="lgf404-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( Admin::SLUG ); ?>" />
				<?php $table->search_box( __( 'Search URLs', 'gecko-404-fixer' ), 'lgf404-search' ); ?>
			</form>
		</div>
		<?php $table->views(); ?>
		<?php $table->display(); ?>
		<datalist id="lgf404-pages"></datalist>
	</section>

	<div class="lgf404-columns">
		<section class="lgf404-panel">
			<h2><?php esc_html_e( 'Links from other sites', 'gecko-404-fixer' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Another site linked to a page that no longer exists. These links carry visitors and authority, so they are worth keeping alive.', 'gecko-404-fixer' ); ?></p>

			<?php if ( $external ) : ?>
				<table class="lgf404-links">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'From', 'gecko-404-fixer' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Dead URL', 'gecko-404-fixer' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Lands on', 'gecko-404-fixer' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $external as $lgf404_row ) : ?>
							<?php
							$lgf404_rule = Store::find_rule( $lgf404_row['path'] );
							$lgf404_url  = $lgf404_rule && Store::KIND_REDIRECT === $lgf404_rule['kind'] ? Matcher::resolve( $lgf404_rule ) : '';
							?>
							<tr>
								<td><?php echo esc_html( implode( ', ', array_keys( Store::referrers( $lgf404_row ) ) ) ); ?></td>
								<td><code class="lgf404-path"><?php echo esc_html( $lgf404_row['path'] ); ?></code></td>
								<td>
									<?php if ( '' !== $lgf404_url ) : ?>
										<code class="lgf404-path"><?php echo esc_html( Url::path_of( $lgf404_url ) ); ?></code>
									<?php else : ?>
										<span class="lgf404-badge lgf404-badge--open"><?php esc_html_e( 'Still 404', 'gecko-404-fixer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="lgf404-empty"><?php esc_html_e( 'Nothing yet. When another site sends someone to a broken URL, it appears here with where they ended up.', 'gecko-404-fixer' ); ?></p>
			<?php endif; ?>

			<div class="lgf404-cta">
				<p><strong><?php esc_html_e( 'Links like these are worth having.', 'gecko-404-fixer' ); ?></strong> <?php esc_html_e( 'LinkGecko finds the review sites, lists and threads worth getting your next ones from.', 'gecko-404-fixer' ); ?></p>
				<a class="lgf404-btn" href="<?php echo esc_url( Admin::linkgecko_url( 'links-card' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Find your next links', 'gecko-404-fixer' ); ?> <span aria-hidden="true">→</span></a>
			</div>
		</section>

		<section class="lgf404-panel">
			<h2><?php esc_html_e( 'Settings', 'gecko-404-fixer' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'lgf404' ); ?>
				<?php $lgf404_option = Settings::OPTION; ?>

				<p class="lgf404-field">
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $lgf404_option ); ?>[auto_fix]" value="1" <?php checked( $settings['auto_fix'], 1 ); ?> />
						<strong><?php esc_html_e( 'Fix 404 errors automatically', 'gecko-404-fixer' ); ?></strong>
					</label>
				</p>

				<p class="lgf404-field">
					<label for="lgf404-strictness"><strong><?php esc_html_e( 'How close a match must be', 'gecko-404-fixer' ); ?></strong></label>
					<select id="lgf404-strictness" name="<?php echo esc_attr( $lgf404_option ); ?>[strictness]">
						<option value="normal" <?php selected( $settings['strictness'], 'normal' ); ?>><?php esc_html_e( 'Normal: fixes typos of up to two letters', 'gecko-404-fixer' ); ?></option>
						<option value="strict" <?php selected( $settings['strictness'], 'strict' ); ?>><?php esc_html_e( 'Strict: only near-identical URLs', 'gecko-404-fixer' ); ?></option>
					</select>
				</p>

				<p class="lgf404-field">
					<label for="lgf404-fallback"><strong><?php esc_html_e( 'When nothing matches', 'gecko-404-fixer' ); ?></strong></label>
					<select id="lgf404-fallback" name="<?php echo esc_attr( $lgf404_option ); ?>[fallback]">
						<option value="none" <?php selected( $settings['fallback'], 'none' ); ?>><?php esc_html_e( 'Keep the 404, with suggestions (recommended)', 'gecko-404-fixer' ); ?></option>
						<option value="home" <?php selected( $settings['fallback'], 'home' ); ?>><?php esc_html_e( 'Redirect to the homepage', 'gecko-404-fixer' ); ?></option>
						<option value="page" <?php selected( $settings['fallback'], 'page' ); ?>><?php esc_html_e( 'Redirect to a page I choose', 'gecko-404-fixer' ); ?></option>
					</select>
					<span class="description lgf404-fallback-warning"><?php esc_html_e( 'Google usually treats blanket redirects as soft 404s.', 'gecko-404-fixer' ); ?></span>
				</p>

				<p class="lgf404-field lgf404-fallback-page">
					<label for="lgf404-fallback-page"><strong><?php esc_html_e( 'Page to redirect to', 'gecko-404-fixer' ); ?></strong></label>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => esc_attr( $lgf404_option ) . '[fallback_page]',
							'id'                => 'lgf404-fallback-page',
							'selected'          => (int) $settings['fallback_page'],
							'show_option_none'  => esc_html__( 'Choose a page', 'gecko-404-fixer' ),
							'option_none_value' => 0,
						)
					);
					?>
				</p>

				<p class="lgf404-field">
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $lgf404_option ); ?>[suggestions]" value="1" <?php checked( $settings['suggestions'], 1 ); ?> />
						<strong><?php esc_html_e( 'Suggest related pages on the 404 page', 'gecko-404-fixer' ); ?></strong>
					</label>
				</p>

				<button type="submit" class="lgf404-btn"><?php esc_html_e( 'Save settings', 'gecko-404-fixer' ); ?></button>
			</form>
		</section>
	</div>

	<footer class="lgf404-foot">
		<?php
		printf(
			/* translators: %s: link to LinkGecko. */
			esc_html__( 'Gecko 404 Fixer is free and complete. It never contacts any outside service. Made by %s.', 'gecko-404-fixer' ),
			'<a href="' . esc_url( Admin::linkgecko_url( 'footer' ) ) . '" target="_blank" rel="noopener">LinkGecko</a>'
		);
		?>
	</footer>
</div>

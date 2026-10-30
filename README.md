# Smart 404s by LinkGecko

Fixes WordPress 404 errors automatically: each broken URL gets a 301 to the page the visitor wanted, or stays an honest 404 with suggestions. Never a blanket redirect to the homepage. No account, and no external requests.

By [LinkGecko](https://linkgecko.com). Requires WordPress 6.2+ and PHP 7.4+, tested up to WordPress 7.1 and PHP 8.5.

Download the latest build from [Releases](../../releases).

## Layout

| Path | What it is |
|---|---|
| `smart-404-redirect/` | The plugin. This folder is what ships. |
| `wporg-assets/` | Icon, banner and screenshots for the SVN `assets` folder. Screenshots use sample data. |
| `tests/` | End-to-end tests against real WordPress. |
| `bin/` | Build and asset scripts. |

## How a 404 is handled

Only on a request WordPress is about to answer with a 404, on `template_redirect` at priority 999, so other redirect plugins go first:

1. A saved rule for this URL: redirect, 410, or a kept 404.
2. A renamed post, from `_wp_old_slug`.
3. WordPress's own guess.
4. A near match against published slugs of the same type. Only one candidate may qualify, and a difference in numbers never does (`best-of-2024` is not `best-of-2025`).
5. No match: the chosen fallback, by default a real 404 with suggestions.

Renamed and deleted posts, pages, products and terms are recorded when they change, before anyone hits the old URL. Requests for `.env`, `wp-config.php` and similar scanner targets are ignored entirely.

## Testing

```bash
tests/run-e2e.sh 8.5 latest 9400                                # latest PHP and WordPress
tests/run-e2e.sh 7.4 6.2 9401                                   # the minimum versions
tests/run-e2e.sh 8.3 latest 9402 tests/compat-blueprint.json    # classic theme, WooCommerce, Yoast, Rank Math, Redirection
tests/run-mysql.sh /tmp/lgs404-stack path/to/redirection        # MariaDB, with Redirection really running
NODE_PATH=<puppeteer-core> node tests/admin-flow.js <site url>  # the admin screen, with real nonces
```

Before any release, also run the real [Plugin Check](https://wordpress.org/plugins/plugin-check/) on a WordPress site, with `wp plugin check smart-404-redirect --include-experimental`. It crashes inside Playground, and its readme, header and block checks are not covered by running its code rulesets alone.

Playground uses SQLite and a CLI SAPI, so `run-mysql.sh` is the run that proves the SQL. Redirection also turns itself off under a CLI SAPI, so only that run exercises its rules.

## Publishing on wordpress.org

1. `bin/build.sh`
2. Upload `dist/smart-404-redirect-1.0.0-submission.zip` at <https://wordpress.org/plugins/developers/add/>. Its short name locks the `smart-404-redirect` slug. Check the slug in the form before submitting.
3. Once approved, commit `smart-404-redirect/*` to SVN `trunk`, copy it with `svn cp trunk tags/1.0.0`, and put `wporg-assets/*` in the top-level `assets` folder.
4. The SVN username is `"him anshu"`, with the space.

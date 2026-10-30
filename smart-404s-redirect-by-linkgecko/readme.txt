=== Smart 404s by LinkGecko – Fix 404 Errors with Automatic 301 Redirects ===
Contributors: him-anshu
Tags: 404, 404 error, redirect, 301 redirect, broken links
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fix 404 errors automatically. Smart 301 redirects send visitors to the right page, not your homepage. No setup, no soft 404s.

== Description ==

**Smart 404s fixes 404 errors on your WordPress site automatically, by sending each broken URL to the page the visitor actually wanted.**

Most "fix 404" plugins redirect every missing page to the homepage. Google treats those redirects as soft 404s, and visitors land somewhere they never asked for. Smart 404s does it properly:

* **Typo or changed URL?** A 301 redirect to the matching post or page.
* **Deleted post?** A 301 redirect to its category or parent page.
* **No good match?** A real 404, with a helpful page that suggests related posts.

Activate it once. There are no rules to write.

= How Smart 404s fixes a 404 error =

When someone requests a URL that doesn't exist, Smart 404s checks, in order:

1. **Fixed before?** The saved redirect is served instantly.
2. **Renamed post?** WordPress remembers old slugs. Smart 404s uses them.
3. **Deleted post?** The visitor goes to the closest parent that still exists.
4. **Near match?** A strict spelling check compares the URL with your published content. Only confident matches are redirected.
5. **Nothing matched?** The page stays a 404, so search engines get an honest answer, and the visitor sees suggestions instead of a dead end.

= Features =

* Automatic 301 redirects for mistyped URLs and broken links
* Redirects for renamed and deleted posts, pages and WooCommerce products
* Helpful 404 page suggestions that work with your existing theme
* 404 error log with hit counts, kept small automatically
* One-click manual fixes: redirect any 404, or mark it as gone (410)
* "Links from other sites" report: see which external links pointed at dead pages, and where they land now
* Ignores bot probes for files like .env and wp-config.php, so the log stays useful
* Optional fallback to redirect unmatched 404s to your homepage or any page
* Works alongside other redirect plugins: their rules always run first

= Fast and private =

Smart 404s only runs when WordPress is about to show a 404. Normal page loads are untouched. Each broken URL is checked once and the result is cached.

No IP addresses are stored. The plugin makes no external requests and needs no account.

= Should you redirect all 404s to the homepage? =

Usually not. A homepage redirect tells Google that the missing page and your homepage are the same thing. Google reports this as a soft 404, may ignore the redirect, and the visitor who wanted a specific article gets your front page instead.

Smart 404s redirects only when it finds the right page. If you still want a homepage fallback for everything else, you can switch it on in one click.

= Who it's for =

Bloggers, content sites, WooCommerce stores, and anyone who has migrated a site, changed permalinks or deleted old content.

= Made by LinkGecko =

Smart 404s is free and complete, with no account and no locked features. It's made by [LinkGecko](https://linkgecko.com), a link building platform. The "Links from other sites" report shows external links that hit broken pages. To find the review sites, lists and threads worth getting your next links from, try LinkGecko. The plugin itself never contacts LinkGecko.

== Installation ==

1. Go to Plugins → Add New, search for "Smart 404s", then install and activate.
2. Open Tools → Smart 404s. Automatic fixing is already on.
3. Come back in a few days to see which 404 errors were fixed.

== Frequently Asked Questions ==

= Does Smart 404s redirect every 404 to the homepage? =

No. It redirects when it finds a confident match, and sends deleted posts to their parent. Everything else stays an honest 404 with suggestions. You can turn on a homepage fallback in settings if you want one.

= What is a soft 404, and does this plugin fix it? =

A soft 404 is a missing page that doesn't return a real 404 status, often because it redirects to the homepage. Google flags these in Search Console. Smart 404s avoids them by redirecting only to relevant pages and returning a real 404 otherwise.

= Will it slow down my site? =

No. It does nothing on normal pages. On a 404 it runs once per broken URL, then serves the cached result.

= Does it work with WooCommerce? =

Yes. Renamed and deleted products and product categories are handled like posts.

= Can I use it with another redirect plugin or my SEO plugin's redirects? =

Yes. Smart 404s runs after other redirect rules, so it only handles URLs that would otherwise show a 404.

= How do I fix a 404 by hand? =

Go to Tools → Smart 404s, find the URL in the log, and choose "Redirect to…" or "Mark as gone (410)".

= Can I undo an automatic redirect? =

Yes. Every automatic redirect is listed in the log with an Undo action.

= The suggestions don't appear where I want them. Can I move them? =

Yes. They appear above the footer by default. To place them yourself, add the "404 Suggestions" block to your 404 template, or use the `[smart_404s_suggestions]` shortcode.

= Does it collect personal data? =

No IP addresses are stored. For visits from other websites, only the referring domain is saved. Nothing is sent outside your site.

== Screenshots ==

1. The Smart 404s dashboard: 404 errors fixed, still open, and links from other sites saved.
2. The 404 error log with hit counts and one-click fixes.
3. A deleted post redirected automatically to its category.
4. A helpful 404 page with suggested posts, using your own theme.
5. Settings: automatic fixing, match strictness and fallback options.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First release.

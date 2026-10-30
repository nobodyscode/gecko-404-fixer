#!/usr/bin/env python3
"""End-to-end checks against a running WordPress with the plugin active.

Usage: e2e.py http://127.0.0.1:9400
Requests are real HTTP, so these exercise the same path a visitor takes.
"""
import http.client, json, sys, urllib.parse

BASE = sys.argv[1].rstrip("/")
u = urllib.parse.urlparse(BASE)
fails = 0
checks = 0


def req(path, headers=None, method="GET"):
    c = http.client.HTTPConnection(u.hostname, u.port, timeout=120)
    headers = dict(headers or {})
    headers.setdefault("Cookie", "playground_auto_login_already_happened=1")
    c.request(method, path, headers=headers)
    r = c.getresponse()
    body = r.read().decode("utf-8", "replace")
    return r.status, (r.getheader("Location") or ""), r, body


def check(name, ok, detail=""):
    global fails, checks
    checks += 1
    if not ok:
        fails += 1
    print(("  PASS  " if ok else "  FAIL  ") + name + ("" if ok else "   -> " + str(detail)))


def expect(name, path, status, loc_contains=None, headers=None):
    s, loc, _, _ = req(path, headers)
    # Core adds a trailing slash to many 404s before anything else runs.
    if s == 301 and urllib.parse.urlparse(loc).path == path.split("?")[0] + "/":
        s, loc, _, _ = req(path.split("?")[0] + "/", headers)
    ok = s == status and (loc_contains is None or loc_contains in loc)
    check(f"{name}: {path} -> {status} {loc_contains or ''}", ok, f"{s} {loc}")


req("/")  # The first request after boot is Playground's own login redirect.

# Normal pages are untouched.
expect("live post", "/how-to-fix-404-errors/", 200)
expect("live page", "/destinations/lake-district/", 200)
expect("home", "/", 200)

# Near match.
expect("typo", "/how-to-fix-404-erors/", 301, "/how-to-fix-404-errors/")
expect("typo again, from the saved rule", "/how-to-fix-404-erors/", 301, "/how-to-fix-404-errors/")
expect("query string carries over", "/how-to-fix-404-erors/?utm_source=x&ref=y", 301, "utm_source=x")
expect("query string carries over, second tag", "/how-to-fix-404-erors/?utm_source=x&ref=y", 301, "ref=y")
# Either encoding of the space is the same value; Playground's server normalises it to +.
s, loc, _, _ = req("/how-to-fix-404-erors/?q=a%20b")
check("percent-encoded values survive", s == 301 and ("q=a%20b" in loc or "q=a+b" in loc), f"{s} {loc}")
expect("case differences", "/How-To-Fix-404-Erors/", 301, "/how-to-fix-404-errors/")

# Things that must stay honest 404s.
expect("no match", "/completely-unrelated-words-here/", 404)
expect("different year is a different page", "/best-tents-of-2023/", 404)
expect("nothing close", "/zzzzzz/", 404)
expect("short slug", "/abc/", 404)
expect("ambiguous between two candidates", "/trail-shoes-mxn/", 404)
expect("missing image is not redirected", "/images/how-to-fix-404-errors.jpg", 404)

# Another plugin's rule wins, whether it runs on wp or on template_redirect.
expect("other plugin on wp", "/how-to-fix-404-errwp/", 301, "/contact-us/")
expect("other plugin on template_redirect", "/how-to-fix-404-errtr/", 301, "/contact-us/")

# Probes get a 404, no matching and no log row.
for probe in ["/.env", "/wp-config.php", "/backup.sql", "/site.zip", "/wp-admin/nope.php", "/.git/config", "/phpmyadmin/", "/xmlrpc.php.bak"]:
    s, loc, _, _ = req(probe)
    moved = urllib.parse.urlparse(loc).path.rstrip("/") != probe.rstrip("/")
    check(f"probe {probe} is not redirected elsewhere", not (300 <= s < 400 and moved), f"{s} {loc}")

# Suggestions on a real 404 page, within the theme.
s, _, _, body = req("/hiking-boots-guide-xyz/")
check("real 404 status", s == 404, s)
check("suggestions on the 404 page", "lgf404-suggestions" in body, body[:200])
check("suggestions printed once", body.count('class="lgf404-suggestions"') == 1, body.count('class="lgf404-suggestions"'))
check("suggestion relates to the URL", "Choosing hiking boots" in body)
check("still inside the theme", "wp-block-template-part" in body or "site-footer" in body or "<footer" in body)
check("no front-end credit link", "linkgecko.com" not in body.lower())

s, _, _, body = req("/how-to-fix-404-errors/")
check("no suggestions on normal pages", "lgf404-suggestions" not in body)
check("no plugin CSS on normal pages", "lgf404-front" not in body)

# Referrer capture.
req("/choosing-hiking-bots/", {"Referer": "https://www.example-blog.com/some/private/page?x=1"})
req("/never-existed-page-abc/", {"Referer": "https://other-site.org/post"})

def helper(op, **params):
    s, _, _, body = req("/?" + urllib.parse.urlencode(dict(lgf404_e2e=op, **params)))
    try:
        return json.loads(body)
    except ValueError:
        check(f"helper {op} answered", False, body[:300])
        return {}


log = {row["path"]: row for row in helper("log")}
check("probes are not logged", not any(p in log for p in ["/.env", "/wp-config.php", "/backup.sql", "/.git/config", "/phpmyadmin"]), list(log))
check("a fix is logged as redirected", log.get("/how-to-fix-404-erors", {}).get("result") == "redirected", log.get("/how-to-fix-404-erors"))
check("one row per URL with a hit count", int(log.get("/how-to-fix-404-erors", {}).get("hits", 0)) >= 3, log.get("/how-to-fix-404-erors"))
check("query strings are never stored", not any("?" in p or "utm" in p for p in log), list(log))
check("no match is logged as 404", log.get("/completely-unrelated-words-here", {}).get("result") == "404")
check("missing image is logged, not fixed", log.get("/images/how-to-fix-404-errors.jpg", {}).get("result") == "404")
row = log.get("/never-existed-page-abc", {})
check("external referrer saved as a bare domain only", json.loads(row.get("ref_domains") or "{}") == {"other-site.org": 1}, row)
check("no path from the referrer is stored", "private" not in json.dumps(helper("log")))

# Rename a post: core and the saved rule both lead to the new URL.
helper("rename", slug="winter-camping-basics", to="snow-camping-basics")
expect("renamed post", "/winter-camping-basics/", 301, "/snow-camping-basics/")
expect("renamed post, new URL live", "/snow-camping-basics/", 200)

# Rename a page: core does not track these.
helper("rename", slug="destinations/lake-district", to="lakes")
expect("renamed child page", "/destinations/lake-district/", 301, "/destinations/lakes/")

# Trash then restore.
expect("before trash", "/old-sleeping-bag-review/", 200)
helper("trash", slug="old-sleeping-bag-review")
expect("trashed post goes to its category", "/old-sleeping-bag-review/", 301, "/category/trail-gear/")
helper("restore", slug="old-sleeping-bag-review")
expect("restored post is live again", "/old-sleeping-bag-review/", 200)
check("restoring removes the rule", not any(r["path"] == "/old-sleeping-bag-review" for r in helper("rules")))

# Permanent delete.
helper("delete", slug="packing-list-weekend-hike")
expect("deleted post goes to its category", "/packing-list-weekend-hike/", 301, "/category/guides/")

# A post only in the default category has nowhere sensible to go.
helper("delete", slug="uncategorised-note")
expect("deleted uncategorised post stays a 404", "/uncategorised-note/", 404)

# A child page's parent.
helper("delete", slug="destinations/lakes")
expect("deleted child page goes to its parent", "/destinations/lakes/", 301, "/destinations/")

# Undo must hold, against our matching and against core's guess.
expect("near match before undo", "/choosing-hiking-bootz/", 301, "/choosing-hiking-boots/")
helper("rule", path="/choosing-hiking-bootz", kind="undo")
expect("undo sticks", "/choosing-hiking-bootz/", 404)
expect("undo still sticks on the next hit", "/choosing-hiking-bootz/", 404)
helper("rule", path="/snow-camping", kind="undo")
expect("undo blocks core's guess too", "/snow-camping/", 404)

# Gone and manual redirects.
helper("rule", path="/retired-page", kind="gone")
expect("mark as gone", "/retired-page/", 410)
helper("rule", path="/moved-somewhere", kind="redirect", to="/contact-us")
expect("manual redirect", "/moved-somewhere/", 301, "/contact-us/")

# A no-match answer is cached, until something new is published.
expect("no match yet", "/autumn-trail-running/", 404)
helper("publish", slug="autumn-trail-runing", title="Autumn trail running")
expect("new content is picked up", "/autumn-trail-running/", 301, "/autumn-trail-runing/")

# Fallback, off by default.
helper("settings", key="fallback", value="home")
s, loc, _, _ = req("/no-such-thing-anywhere-here/")
check("homepage fallback", s == 301 and urllib.parse.urlparse(loc).path == "/", f"{s} {loc}")
expect("fallback leaves probes alone", "/.env", 404)
expect("fallback leaves missing files alone", "/images/missing.png", 404)
helper("settings", key="fallback", value="none")
expect("fallback off again", "/no-such-thing-anywhere-here/", 404)

# Auto-fix off.
helper("settings", key="auto_fix", value="0")
expect("auto-fix off leaves typos alone", "/best-rain-jackets-for-sprng/", 404)
expect("auto-fix off keeps manual rules", "/moved-somewhere/", 301, "/contact-us/")
helper("settings", key="auto_fix", value="1")

# Suggestions off.
helper("settings", key="suggestions", value="0")
s, _, _, body = req("/one-more-missing-page-qq/")
check("suggestions can be switched off", s == 404 and "lgf404-suggestions" not in body)
helper("settings", key="suggestions", value="1")

# A POST never triggers a redirect.
s, loc, _, _ = req("/how-to-fix-404-erors/", method="POST")
check("POST is not redirected", not (300 <= s < 400), f"{s} {loc}")

stats = helper("stats")
check("stats count fixes", int(stats.get("fixed", 0)) > 3, stats)
check("stats count open 404s", int(stats.get("open", 0)) > 0, stats)
helper("trim")
check("trim keeps recent rows", len(helper("log")) > 5)

if "redirection/redirection.php" in helper("plugins"):
    print("  -- running alongside: " + ", ".join(p.split("/")[0] for p in helper("plugins")))
    # Redirection turns itself off under a CLI SAPI, which is what Playground reports.
    if helper("sapi").get("red_is_cli"):
        print("  -- Redirection does not run under this SAPI; its rule cannot fire here")
    else:
        rule = helper("redirection", **{"from": "/how-to-fix-404-errrs", "to": "/contact-us/"})
        check("Redirection rule created", rule.get("ok"), rule)
        s_, loc_, r_, _ = req("/how-to-fix-404-errrs")
        check("Redirection's own rule beats ours", s_ == 301 and "/contact-us/" in loc_ and r_.getheader("X-Redirect-By") != "Gecko 404 Fixer", f"{s_} {loc_} {r_.getheader('X-Redirect-By')}")
    expect("we still handle what they do not", "/how-to-fix-404-errorz/", 301, "/how-to-fix-404-errors/")

if "woocommerce/woocommerce.php" in helper("plugins"):
    expect("product live", "/product/blue-rain-jacket/", 200)
    expect("product typo", "/product/blue-rain-jackt/", 301, "/product/blue-rain-jacket/")
    expect("product typo stays with products", "/product/how-to-fix-404-errorz/", 404)
    helper("product", do="rename")
    expect("renamed product", "/product/red-rain-jacket/", 301, "/product/crimson-rain-jacket/")
    helper("product", do="trash")
    expect("trashed product goes to its category", "/product/crimson-rain-jacket/", 301, "/product-category/rain-gear/")
    helper("product_cat")
    expect("renamed product category", "/product-category/rain-gear/", 301, "/product-category/wet-weather/")
    expect("product category typo", "/product-category/wet-wether/", 301, "/product-category/wet-weather/")

# Last, because it removes the plugin.
gone = helper("uninstall")
check("uninstall removes both tables", gone.get("tables") == [], gone)
check("uninstall removes every option and transient", [o for o in gone.get("options", []) if "lgf404_test" not in o] == [], gone)
check("uninstall removes the schedule", not gone.get("cron"), gone)

print(f"\n{checks - fails} of {checks} checks passed")
sys.exit(1 if fails else 0)

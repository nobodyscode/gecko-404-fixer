#!/usr/bin/env bash
# Builds both zips in dist/:
#   smart-404s-redirect-by-linkgecko-<v>.zip             the release itself
#   smart-404s-redirect-by-linkgecko-<v>-submission.zip  for the first wordpress.org upload only. Its
#     short name is what locks the smart-404s-redirect-by-linkgecko slug.
set -euo pipefail
cd "$(dirname "$0")/.."

V=$(grep -m1 "Version:" smart-404s-redirect-by-linkgecko/smart-404s-redirect-by-linkgecko.php | sed 's/.*Version: *//' | tr -d '\r')
rm -rf dist; mkdir -p dist
zip -rq "dist/smart-404s-redirect-by-linkgecko-$V.zip" smart-404s-redirect-by-linkgecko -x "*.DS_Store"

T=$(mktemp -d); cp -R smart-404s-redirect-by-linkgecko "$T/"
sed -i '' 's/^ \* Plugin Name: .*/ * Plugin Name:       Smart 404s Redirect by LinkGecko/' "$T/smart-404s-redirect-by-linkgecko/smart-404s-redirect-by-linkgecko.php"
sed -i '' '1s/.*/=== Smart 404s Redirect by LinkGecko ===/' "$T/smart-404s-redirect-by-linkgecko/readme.txt"
( cd "$T" && zip -rq "$OLDPWD/dist/smart-404s-redirect-by-linkgecko-$V-submission.zip" smart-404s-redirect-by-linkgecko -x "*.DS_Store" )
rm -rf "$T"
ls -la dist | awk 'NR>3{print $5, $9}'

#!/usr/bin/env bash
# Builds both zips in dist/:
#   smart-404-redirect-<v>.zip             the release itself
#   smart-404-redirect-<v>-submission.zip  for the first wordpress.org upload only. Its
#     short name is what locks the smart-404-redirect slug.
set -euo pipefail
cd "$(dirname "$0")/.."

V=$(grep -m1 "Version:" smart-404-redirect/smart-404-redirect.php | sed 's/.*Version: *//' | tr -d '\r')
rm -rf dist; mkdir -p dist
zip -rq "dist/smart-404-redirect-$V.zip" smart-404-redirect -x "*.DS_Store"

T=$(mktemp -d); cp -R smart-404-redirect "$T/"
sed -i '' 's/^ \* Plugin Name: .*/ * Plugin Name:       Smart 404 Redirect/' "$T/smart-404-redirect/smart-404-redirect.php"
sed -i '' '1s/.*/=== Smart 404 Redirect ===/' "$T/smart-404-redirect/readme.txt"
( cd "$T" && zip -rq "$OLDPWD/dist/smart-404-redirect-$V-submission.zip" smart-404-redirect -x "*.DS_Store" )
rm -rf "$T"
ls -la dist | awk 'NR>3{print $5, $9}'

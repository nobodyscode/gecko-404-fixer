#!/usr/bin/env bash
# Builds both zips in dist/:
#   gecko-404-fixer-<v>.zip             the release itself
#   gecko-404-fixer-<v>-submission.zip  for the first wordpress.org upload only. Its
#     short name is what locks the gecko-404-fixer slug.
set -euo pipefail
cd "$(dirname "$0")/.."

V=$(grep -m1 "Version:" gecko-404-fixer/gecko-404-fixer.php | sed 's/.*Version: *//' | tr -d '\r')
rm -rf dist; mkdir -p dist
zip -rq "dist/gecko-404-fixer-$V.zip" gecko-404-fixer -x "*.DS_Store"

T=$(mktemp -d); cp -R gecko-404-fixer "$T/"
sed -i '' 's/^ \* Plugin Name: .*/ * Plugin Name:       Gecko 404 Fixer/' "$T/gecko-404-fixer/gecko-404-fixer.php"
sed -i '' '1s/.*/=== Gecko 404 Fixer ===/' "$T/gecko-404-fixer/readme.txt"
( cd "$T" && zip -rq "$OLDPWD/dist/gecko-404-fixer-$V-submission.zip" gecko-404-fixer -x "*.DS_Store" )
rm -rf "$T"
ls -la dist | awk 'NR>3{print $5, $9}'

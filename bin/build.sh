#!/usr/bin/env bash
# Builds the release zip in dist/.
set -euo pipefail
cd "$(dirname "$0")/.."

V=$(grep -m1 "Version:" gecko-404-fixer/gecko-404-fixer.php | sed 's/.*Version: *//' | tr -d '\r')
rm -rf dist; mkdir -p dist
zip -rq "dist/gecko-404-fixer-$V.zip" gecko-404-fixer -x "*.DS_Store"
ls -la dist | awk 'NR>3{print $5, $9}'

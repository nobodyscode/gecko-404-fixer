#!/usr/bin/env bash
# Boots real WordPress in Playground and runs tests/e2e.py against it.
# Usage: tests/run-e2e.sh [php-version] [wp-version] [port] [extra blueprint]
set -uo pipefail
cd "$(dirname "$0")/.."
PHPV="${1:-8.5}"; WPV="${2:-latest}"; PORT="${3:-9400}"; BP="${4:-tests/e2e-blueprint.json}"
LOG="$(mktemp)"
npx -y @wp-playground/cli@latest server \
  --mount="$PWD/smart-404s-redirect-by-linkgecko:/wordpress/wp-content/plugins/smart-404s-redirect-by-linkgecko" \
  --mount="$PWD/tests/e2e-helper.php:/wordpress/wp-content/mu-plugins/e2e-helper.php" \
  --blueprint="$BP" --php="$PHPV" --wp="$WPV" --port="$PORT" --login >"$LOG" 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null' EXIT
for _ in $(seq 1 90); do
  grep -q "Ready!" "$LOG" && break
  kill -0 $PID 2>/dev/null || { echo "Playground exited:"; tail -20 "$LOG"; exit 2; }
  sleep 2
done
grep -q "Ready!" "$LOG" || { echo "Playground did not start:"; tail -20 "$LOG"; exit 2; }
echo "PHP $PHPV / WordPress $WPV"
python3 tests/e2e.py "http://127.0.0.1:$PORT"
RC=$?
grep -iE "PHP (Fatal|Warning|Deprecated|Notice)" "$LOG" | sort -u | head -20
exit $RC

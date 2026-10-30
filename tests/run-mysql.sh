#!/usr/bin/env bash
# Runs tests/e2e.py on a production-style stack: MariaDB and PHP's web server, in a
# throwaway directory. Playground uses SQLite and reports a CLI SAPI, so this is the
# run that proves the SQL, and lets Redirection really fire.
# Usage: tests/run-mysql.sh <work dir> [redirection plugin dir]
set -uo pipefail
cd "$(dirname "$0")/.."
ROOT="$PWD"; W="$1"; RED="${2:-}"; PORT=9420; DBPORT=33160; PHP=/opt/homebrew/bin/php
mkdir -p "$W/db" "$W/site"
mariadb-install-db --no-defaults --datadir="$W/db" --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1
mariadbd --no-defaults --datadir="$W/db" --port=$DBPORT --bind-address=127.0.0.1 --socket="/tmp/lgf404-$$.sock" --pid-file="$W/db.pid" --sql-mode=STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO >"$W/db.log" 2>&1 &
DBPID=$!
cleanup() { kill "${WEBPID:-}" "$DBPID" 2>/dev/null; }
trap cleanup EXIT
for _ in $(seq 1 30); do mariadb --no-defaults -h127.0.0.1 -P$DBPORT -uroot -e "select 1" >/dev/null 2>&1 && break; sleep 1; done
mariadb --no-defaults -h127.0.0.1 -P$DBPORT -uroot -e "create database if not exists wp; select version();"
[ -f "$W/wp.tgz" ] || curl -sL -o "$W/wp.tgz" https://wordpress.org/latest.tar.gz
tar -xzf "$W/wp.tgz" -C "$W/site" --strip-components=1
cat > "$W/site/wp-config.php" <<PHP
<?php
define('DB_NAME','wp');define('DB_USER','root');define('DB_PASSWORD','');define('DB_HOST','127.0.0.1:$DBPORT');define('DB_CHARSET','utf8mb4');define('DB_COLLATE','');
define('WP_HOME','http://127.0.0.1:$PORT');define('WP_SITEURL','http://127.0.0.1:$PORT');
define('AUTH_KEY','t');define('SECURE_AUTH_KEY','t');define('LOGGED_IN_KEY','t');define('NONCE_KEY','t');define('AUTH_SALT','t');define('SECURE_AUTH_SALT','t');define('LOGGED_IN_SALT','t');define('NONCE_SALT','t');
define('WP_DEBUG',true);define('WP_DEBUG_LOG',true);define('WP_DEBUG_DISPLAY',false);define('DISABLE_WP_CRON',true);
\$table_prefix='wp_';
if(!defined('ABSPATH')){define('ABSPATH',__DIR__.'/');}require_once ABSPATH.'wp-settings.php';
PHP
cat > "$W/router.php" <<'PHP'
<?php
$p = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( '/' !== $p && is_file( $_SERVER['DOCUMENT_ROOT'] . $p ) && '.php' !== substr( $p, -4 ) ) { return false; }
if ( preg_match( '#^/wp-admin/#', $p ) && is_file( $_SERVER['DOCUMENT_ROOT'] . $p ) ) { chdir( dirname( $_SERVER['DOCUMENT_ROOT'] . $p ) ); require $_SERVER['DOCUMENT_ROOT'] . $p; return; }
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
PHP
rm -rf "$W/site/wp-content/plugins/gecko-404-fixer"; cp -R "$ROOT/gecko-404-fixer" "$W/site/wp-content/plugins/"
mkdir -p "$W/site/wp-content/mu-plugins"; cp "$ROOT/tests/e2e-helper.php" "$W/site/wp-content/mu-plugins/"
[ -n "$RED" ] && cp -R "$RED" "$W/site/wp-content/plugins/redirection"
python3 - "$ROOT/tests/e2e-blueprint.json" "$W/seed.php" <<'PY'
import json,sys
bp=json.load(open(sys.argv[1]))
code=[s["code"] for s in bp["steps"] if s["step"]=="runPHP"][0].replace("<?php require '/wordpress/wp-load.php';","")
open(sys.argv[2],"w").write("<?php\ndefine('WP_INSTALLING',false);\n"+code)
PY
cat > "$W/install.php" <<PHP
<?php
\$_SERVER['HTTP_HOST']='127.0.0.1:$PORT'; define('WP_INSTALLING',true);
require '$W/site/wp-load.php'; require_once ABSPATH.'wp-admin/includes/upgrade.php';
wp_install('Northwind Journal','admin','a@example.com',true,'','test-password-only');
update_option('permalink_structure','/%postname%/');
PHP
cat > "$W/activate.php" <<PHP
<?php
\$_SERVER['HTTP_HOST']='127.0.0.1:$PORT';
require '$W/site/wp-load.php'; require_once ABSPATH.'wp-admin/includes/plugin.php';
var_dump(activate_plugin('gecko-404-fixer/gecko-404-fixer.php'));
if ('$RED') { var_dump(activate_plugin('redirection/redirection.php')); }
echo get_option('lgf404_db_version');
global \$wpdb; print_r(\$wpdb->get_col("SHOW TABLES LIKE '%lgf404%'"));
print_r(\$wpdb->get_results("SHOW CREATE TABLE {\$wpdb->prefix}lgf404_log", ARRAY_A));
PHP
$PHP "$W/install.php" 2>&1 | tail -3
$PHP "$W/activate.php" 2>&1 | tail -32 | cut -c1-160
( cd "$W" && { sed -n '1p' seed.php; echo "\$_SERVER['HTTP_HOST']='127.0.0.1:$PORT'; require '$W/site/wp-load.php';"; sed '1,2d' seed.php; } > seed2.php && $PHP seed2.php 2>&1 | tail -2 )
PHP_CLI_SERVER_WORKERS=4 $PHP -S 127.0.0.1:$PORT -t "$W/site" "$W/router.php" >"$W/web.log" 2>&1 &
WEBPID=$!
sleep 2
python3 tests/e2e.py "http://127.0.0.1:$PORT"
RC=$?
echo "--- debug.log, plugin lines:"; grep -iE "gecko-404-fixer|lgf404|WordPress database error" "$W/site/wp-content/debug.log" 2>/dev/null | sort -u | cut -c1-300 | head -20
echo "--- debug.log lines total: $(wc -l < "$W/site/wp-content/debug.log" 2>/dev/null)"
[ -n "${KEEP:-}" ] && { echo "kept running; press ctrl-c"; wait; }
exit $RC

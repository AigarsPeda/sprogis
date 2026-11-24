#!/usr/bin/env bash
# Run WP-CLI with the PHP configuration and database socket of Sprogis in Local.
set -euo pipefail
support="$HOME/Library/Application Support/Local"
runtime="${LOCAL_RUN_DIR:-$support/run/y-l9K7HJH}"
services="$support/lightning-services"
php="${LOCAL_PHP_BIN:-$services/php-8.2.30+1/bin/darwin-arm64/bin/php}"
mysql_bin="${LOCAL_MYSQL_BIN_DIR:-$services/mysql-8.0.35+4/bin/darwin-arm64/bin}"
wp_cli="${LOCAL_WP_CLI:-/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar}"
site="${LOCAL_WP_PATH:-$HOME/Local Sites/sprogis/app/public}"
export MYSQL_UNIX_PORT="$runtime/mysql/mysqld.sock"
export PATH="$mysql_bin:$PATH"
if [[ ! -S "$MYSQL_UNIX_PORT" ]]; then
    echo 'Start the Sprogis site in Local first.' >&2
    exit 1
fi
exec "$php" -c "$runtime/conf/php/php.ini" "$wp_cli" --path="$site" "$@"

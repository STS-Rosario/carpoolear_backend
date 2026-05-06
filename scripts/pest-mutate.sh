#!/usr/bin/env bash
# Run Pest mutation testing with pcov.directory scoped to this repo.
# Requires PHP >= 8.5 (same as composer.json "require.php").
#
# Composer often invokes scripts with an older system `php`; set PEST_PHP to PHP 8.5+, e.g. Herd:
#   export PEST_PHP="$HOME/Library/Application Support/Herd/bin/php85"
#   DB_DATABASE=testing1 composer test:mutate -- --path=app/Repository/Foo.php tests/Unit/...
#
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PEST_PHP:-php}"
if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.5.0", "<") ? 1 : 0);' 2>/dev/null; then
    ver="$("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"
    echo "test:mutate requires PHP >= 8.5; got ${PHP_BIN} (${ver})." >&2
    echo "Example: export PEST_PHP=\"\$HOME/Library/Application Support/Herd/bin/php85\"" >&2
    echo "Then: DB_DATABASE=testing1 composer test:mutate -- --path=app/... tests/..." >&2
    exit 1
fi

exec "$PHP_BIN" -d pcov.directory=. ./vendor/bin/pest --mutate "$@"

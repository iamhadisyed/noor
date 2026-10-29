#!/usr/bin/env bash
# Runs the portal end-to-end suite against a throwaway local server (real Laravel app, sqlite file, emails written to the log).
# Needs: php, node, and Playwright (`npm i -g playwright` + a browser). Usage: tests/e2e/run.sh [file-pattern, e.g. 20-guidance]
set -euo pipefail
cd "$(dirname "$0")/../.."
DB="$(mktemp -d)/e2e.sqlite"; touch "$DB"
export APP_ENV=local APP_DEBUG=true APP_URL=http://127.0.0.1:8099 DB_CONNECTION=sqlite DB_DATABASE="$DB" MAIL_MAILER=log LOG_CHANNEL=single \
  QUEUE_CONNECTION=sync CACHE_STORE=file SESSION_DRIVER=file SUPER_ADMIN_EMAILS=admin@kindo.test KINDO_OTP_COOLDOWN=1
mkdir -p storage/logs && : > storage/logs/laravel.log
php artisan migrate:fresh --seed --force >/dev/null
php artisan cache:clear >/dev/null   # the file cache outlives the throwaway database; old rate-limit counters would otherwise leak into this run
php artisan serve --host=127.0.0.1 --port=8099 >/dev/null 2>&1 &
SERVER=$!; trap 'kill $SERVER 2>/dev/null || true' EXIT
for i in $(seq 1 40); do curl -sf http://127.0.0.1:8099/portal/ >/dev/null && break; sleep 0.25; done
export NODE_PATH="${NODE_PATH:-$(npm root -g)}"
status=0
for t in tests/e2e/${1:-[0-9]*}.cjs; do echo "── $(basename "$t")"; node "$t" || status=1; done
exit $status

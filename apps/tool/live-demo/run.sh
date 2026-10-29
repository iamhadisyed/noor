#!/usr/bin/env bash
# Runs both apps' web builds against a real, throwaway Laravel server and takes phone-size screenshots of the real screens.
# Needs: php, flutter, node + Playwright (npm i -g playwright). Usage: apps/tool/live-demo/run.sh [--build]
#   --build   build both apps for the web first (about 3 minutes each)
# Output: demo/apps-live/parent and demo/apps-live/junior. Everything it starts is stopped when it ends.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; ROOT="$(cd "$HERE/../../.." && pwd)"
API=8098; PARENT=8101; JUNIOR=8102; WORK="$(mktemp -d)"
cd "$ROOT"

if [ "${1:-}" = "--build" ]; then
  for app in kindo_parent kindo_junior; do
    (cd "apps/$app" && flutter build web --release --no-web-resources-cdn --dart-define=KINDO_API=http://127.0.0.1:$API/api/v1 >/dev/null)
  done
fi

DB="$WORK/live.sqlite"; touch "$DB"
export APP_ENV=local APP_DEBUG=true APP_URL=http://127.0.0.1:$API DB_CONNECTION=sqlite DB_DATABASE="$DB" MAIL_MAILER=log LOG_CHANNEL=single \
  QUEUE_CONNECTION=sync CACHE_STORE=file SESSION_DRIVER=file KINDO_OTP_COOLDOWN=1 \
  CORS_ALLOWED_ORIGINS=http://127.0.0.1:$PARENT,http://127.0.0.1:$JUNIOR
mkdir -p storage/logs && : > storage/logs/laravel.log
php artisan migrate:fresh --seed --force >/dev/null
php artisan cache:clear >/dev/null
pids=()
cleanup() { for p in "${pids[@]}"; do kill "$p" 2>/dev/null || true; done; }
trap cleanup EXIT
php -S 127.0.0.1:$API -t public "$HERE/router.php" >/dev/null 2>&1 & pids+=($!)
(cd apps/kindo_parent/build/web && python3 -m http.server $PARENT --bind 127.0.0.1 >/dev/null 2>&1) & pids+=($!)
(cd apps/kindo_junior/build/web && python3 -m http.server $JUNIOR --bind 127.0.0.1 >/dev/null 2>&1) & pids+=($!)
for _ in $(seq 1 40); do curl -s -o /dev/null "http://127.0.0.1:$API/api/v1/meta" && break; sleep 0.25; done

pin_token() { curl -s -X POST "http://127.0.0.1:$API/api/v1/auth/pin" -H 'Accept: application/json' -H 'Content-Type: application/json' -d "{\"email\":\"$1\",\"pin\":\"$2\"}" | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])'; }
export NODE_PATH="${NODE_PATH:-$(npm root -g)}"
OUT="$ROOT/demo/apps-live"; mkdir -p "$OUT/parent" "$OUT/junior" "$OUT/cool-time"

# the whole contract, against this server (needs dart)
(cd apps/kindo_core && KINDO_BASE=http://127.0.0.1:$API/api/v1 KINDO_LOG="$ROOT/storage/logs/laravel.log" dart test -t live 2>&1 | tail -3) || true

# the demo accounts from the seeders: the demo parent has Pro and a week of activity; the demo child account holds two children
PARENT_TOKEN="$(pin_token demo.parent@kindo.local 135790)"
CHILD_TOKEN="$(pin_token demo.child@kindo.local 246801)"
STEPS="$HERE/steps-parent.json" node "$HERE/shots.cjs" parent $PARENT "$PARENT_TOKEN" "$OUT/parent"
STEPS="$HERE/steps-junior.json" node "$HERE/shots.cjs" junior $JUNIOR "$CHILD_TOKEN" "$OUT/junior"

# cool time: the parent turns on quiet hours for the whole day (through the real screen time API); the child's app then shows the calm screen
AYAN="$(curl -s "http://127.0.0.1:$API/api/v1/children" -H "Authorization: Bearer $PARENT_TOKEN" -H 'Accept: application/json' | python3 -c 'import sys,json;d=json.load(sys.stdin);d=d.get("data",d);print([c["id"] for c in d if c["name"]=="Ayan"][0])')"
curl -s -X PATCH "http://127.0.0.1:$API/api/v1/children/$AYAN/screen-time" -H "Authorization: Bearer $PARENT_TOKEN" -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{"enabled":true,"daily_minutes":60,"quiet_start":"00:00","quiet_end":"23:59"}' >/dev/null
STEPS="$HERE/steps-cool.json" node "$HERE/shots.cjs" junior $JUNIOR "$PARENT_TOKEN" "$OUT/cool-time"
rm -f "$OUT"/*/failed-*.png
echo "Screenshots are in $OUT"

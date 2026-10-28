#!/usr/bin/env bash
# Re-run the browser tests and copy their screenshots into demo/portal-desktop.
set -euo pipefail
cd "$(dirname "$0")/.."
bash tests/e2e/run.sh
cp tests/e2e/.out/*.png demo/portal-desktop/
echo "Updated demo/portal-desktop ($(ls demo/portal-desktop | wc -l) pictures)."

#!/usr/bin/env bash
# Laravel development server. Waits until start.sh has prepared .env and SQLite.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

for _ in $(seq 1 90); do
    if [[ -f /tmp/solar-system-web-ready && -f .env ]]; then
        break
    fi
    sleep 1
done

exec php artisan serve --host=0.0.0.0 --port=8000

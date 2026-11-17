#!/usr/bin/env bash
# Per-boot preparation. The dev server itself runs from the dev-server terminal.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if [[ ! -f .env ]]; then
    cp .env.example .env
fi
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction --ansi
fi

mkdir -p \
    database \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
touch database/database.sqlite
php artisan migrate --force --no-interaction --ansi
touch /tmp/solar-system-web-ready

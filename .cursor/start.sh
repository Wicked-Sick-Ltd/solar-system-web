#!/usr/bin/env bash
# Per-boot preparation. The dev server itself runs from the dev-server terminal.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=agent-env.sh
source "${SCRIPT_DIR}/agent-env.sh"

cd "${SCRIPT_DIR}/.."

ensure_agent_env
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

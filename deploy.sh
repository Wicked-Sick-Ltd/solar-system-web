#!/usr/bin/env bash
# Forge in-place release for an EXISTING installation with persistent accounts.
# First installation, backup hook contract and recovery: see DEPLOYMENT.md.
set -euo pipefail

: "${FORGE_SITE_PATH:?}"
: "${FORGE_SITE_BRANCH:?}"
: "${FORGE_COMPOSER:?}"
: "${FORGE_PHP:?}"
: "${FORGE_PHP_FPM:?}"
: "${ACCOUNT_BACKUP_HOOK:?Set an absolute executable backup hook; see DEPLOYMENT.md}"

if [[ "$ACCOUNT_BACKUP_HOOK" != /* || ! -x "$ACCOUNT_BACKUP_HOOK" ]]; then
    echo 'ACCOUNT_BACKUP_HOOK must be an absolute executable path.' >&2
    exit 1
fi

cd "$FORGE_SITE_PATH"
# Serialize the entire release, including backup and migrations.
exec 9>storage/framework/deploy.lock
flock -n 9 || { echo 'Another deployment is active.' >&2; exit 1; }

# Stop/drain the scheduler and workers before invoking this script. Maintenance
# protects web writes; a failure deliberately leaves the site down for recovery.
"$FORGE_PHP" artisan down --retry=60
trap 'echo "Release failed; site remains in maintenance. Follow DEPLOYMENT.md recovery." >&2' ERR
"$ACCOUNT_BACKUP_HOOK"

git pull --ff-only origin "$FORGE_SITE_BRANCH"
"$FORGE_COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build

# Load the intended environment, then migrate before new pages can run.
# Do not use optimize:clear here: it flushes application cache/lock entries.
"$FORGE_PHP" artisan config:clear
"$FORGE_PHP" artisan migrate --force
"$FORGE_PHP" artisan config:cache
"$FORGE_PHP" artisan route:cache
"$FORGE_PHP" artisan view:cache
"$FORGE_PHP" artisan event:cache

"$FORGE_PHP" artisan queue:restart
# Retain the server-wide FPM lock for other sites sharing this PHP service.
( flock -w 10 8 || exit 1
    sudo -n service "$FORGE_PHP_FPM" reload
) 8>/tmp/fpmlock
"$FORGE_PHP" artisan up
trap - ERR

# A failed upstream warm-up must not take a healthy release offline.
"$FORGE_PHP" artisan solar:warm-cache || true

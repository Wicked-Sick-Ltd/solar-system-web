#!/usr/bin/env bash
#
# Laravel Forge deploy script for Solar. The public hostname(s) are set in the
# Forge site (canonical URL comes from APP_URL), not here.
# Paste this into the site's "Deploy Script" in the Forge UI, or have Forge run
# it from the repo. Forge provides $FORGE_* variables at run time.
#
# This app has NO database — there is no `artisan migrate`. All data comes from
# the backend API; sessions/cache use Redis in production (see DEPLOYMENT.md).
set -euo pipefail

cd "$FORGE_SITE_PATH"

git pull origin "$FORGE_SITE_BRANCH"

# PHP dependencies (production, optimised autoloader)
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Front-end build (Node is available on Forge; public/build is not committed)
npm ci --no-audit --no-fund
npm run build

# Reload PHP-FPM with a lock so concurrent deploys don't collide
( flock -w 10 9 || exit 1
    echo 'Reloading PHP FPM...'
    sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    # Rebuild the framework caches against the new code/config.
    $FORGE_PHP artisan optimize:clear
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan event:cache

    # Pick up the new code in the queue worker, and warm the caches.
    $FORGE_PHP artisan queue:restart
    $FORGE_PHP artisan solar:warm-cache || true
fi

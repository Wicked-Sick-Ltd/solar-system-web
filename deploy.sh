#!/usr/bin/env bash
# Forge in-place release for an EXISTING installation with persistent accounts.
# First installation, backup hook contract and recovery: see DEPLOYMENT.md.
set -euo pipefail

: "${FORGE_SITE_PATH:?}"
: "${FORGE_SITE_BRANCH:?}"
: "${FORGE_COMPOSER:?}"
: "${FORGE_PHP:?}"
: "${FORGE_PHP_FPM:?}"
: "${RELEASE_COMMIT:?Set the reviewed full commit SHA; branch tips are not deployment approval}"
: "${ACCOUNT_BACKUP_HOOK:?Set an absolute executable backup hook; see DEPLOYMENT.md}"

if [[ "$ACCOUNT_BACKUP_HOOK" != /* || ! -x "$ACCOUNT_BACKUP_HOOK" ]]; then
    echo 'ACCOUNT_BACKUP_HOOK must be an absolute executable path.' >&2
    exit 1
fi

if [[ ! "$RELEASE_COMMIT" =~ ^[a-f0-9]{40}$ ]]; then
    echo 'RELEASE_COMMIT must be a full lowercase 40-character commit SHA.' >&2
    exit 1
fi

cd "$FORGE_SITE_PATH"
# Serialize the entire release, including backup and migrations.
# The lock is a sibling of the checkout. Creating it under storage/framework
# makes it an untracked file before this revision's gitignore is in effect,
# so the clean-checkout check below rejects every deploy.
site_root="${FORGE_SITE_PATH%/}"
exec 9>"${site_root}.deploy.lock"
flock -n 9 || { echo 'Another deployment is active.' >&2; exit 1; }
# Older copies of this script left an in-tree lock. It is not source.
rm -f -- storage/framework/deploy.lock

# Fetch does not alter running source. Reject local changes and an unreviewed tip.
checkout_status=$(git status --porcelain --untracked-files=normal)
if [[ -n "$checkout_status" ]]; then
    echo 'Deployment requires a clean checkout (including untracked files).' >&2
    exit 1
fi
git check-ref-format --branch "$FORGE_SITE_BRANCH" >/dev/null
git fetch --no-tags origin "refs/heads/$FORGE_SITE_BRANCH"
git cat-file -e "$RELEASE_COMMIT^{commit}"
git merge-base --is-ancestor "$RELEASE_COMMIT" FETCH_HEAD

# Stop/drain the scheduler and workers before invoking this script. Maintenance
# protects web writes; a failure deliberately leaves the site down for recovery.
"$FORGE_PHP" artisan down --retry=60 --render=errors::503
trap 'echo "Release failed; site remains in maintenance. Follow DEPLOYMENT.md recovery." >&2' ERR
"$ACCOUNT_BACKUP_HOOK"

git checkout --detach "$RELEASE_COMMIT"
if [[ "$(git rev-parse --verify HEAD)" != "$RELEASE_COMMIT" ]]; then
    echo 'Checkout did not match the reviewed revision.' >&2
    exit 1
fi
# Forge sets FORGE_COMPOSER to a command line, for example
# "php8.4 /usr/local/bin/composer", not a single executable path.
# Split on whitespace without globbing. FORGE_PHP is one binary,
# FORGE_PHP_FPM one service name, and FORGE_SITE_PATH / FORGE_SITE_BRANCH
# one field each; those stay quoted.
read -r -a forge_composer <<< "$FORGE_COMPOSER"
if [[ ${#forge_composer[@]} -eq 0 ]]; then
    echo 'FORGE_COMPOSER must be a composer command.' >&2
    exit 1
fi
"${forge_composer[@]}" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build

# Load the intended environment, then migrate before new pages can run.
# Do not use optimize:clear here: it flushes application cache/lock entries.
"$FORGE_PHP" artisan config:clear
"$FORGE_PHP" artisan migrate --force
bash scripts/release-deploy.sh prepare
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

# Publication verifies the live HTTPS endpoint's exact commit/version and DB.
# At this point code is active: publication failure requires investigation/retry,
# not a claim that maintenance still protects the site or an automatic rollback.
trap 'echo "Code is active but release publication failed. Inspect readiness and retry the publication hook; see docs/releases.md." >&2' ERR
FORGE_PHP="$FORGE_PHP" bash scripts/release-deploy.sh publish
trap - ERR

#!/usr/bin/env bash
# Run from the checked-out release directory; see docs/releases.md for ordering.
set -euo pipefail

revision=$(git rev-parse --verify HEAD)
if [[ ! "$revision" =~ ^[a-f0-9]{40}$ ]]; then
    echo 'Expected a full Git commit SHA.' >&2
    exit 1
fi
case "${1:-}" in
    prepare)
        # Atomic replacement prevents a partial revision from being cached.
        temporary=$(mktemp bootstrap/build-commit.XXXXXX)
        trap 'rm -f "$temporary"' EXIT
        printf '%s\n' "$revision" > "$temporary"
        chmod 0644 "$temporary"
        mv "$temporary" bootstrap/build-commit.txt
        ;;
    publish)
        if [[ ! -f bootstrap/build-commit.txt ]] || [[ "$(cat bootstrap/build-commit.txt)" != "$revision" ]]; then
            echo 'Missing or stale build revision. Run prepare before caching configuration.' >&2
            exit 1
        fi
        "${FORGE_PHP:-php}" artisan universe:releases:publish --commit="$revision"
        ;;
    *)
        echo 'Usage: bash scripts/release-deploy.sh prepare|publish' >&2
        exit 1
        ;;
esac

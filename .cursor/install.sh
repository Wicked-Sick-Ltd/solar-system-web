#!/usr/bin/env bash
# Idempotent, non-interactive bootstrap for Cursor Cloud Agents.
# Run from the repository root: bash .cursor/install.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=agent-env.sh
source "${SCRIPT_DIR}/agent-env.sh"

cd "${SCRIPT_DIR}/.."

export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a
export COMPOSER_NO_INTERACTION=1

php_ready() {
    command -v php >/dev/null 2>&1 || return 1
    php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' || return 1
    local ext
    for ext in mbstring xml curl dom ctype fileinfo intl pdo_sqlite bcmath zip imagick; do
        php -m | grep -qi "^${ext}$" || return 1
    done
}

apt_install() {
    local attempt
    for attempt in 1 2 3 4 5; do
        if sudo DEBIAN_FRONTEND=noninteractive apt-get install -y "$@"; then
            return 0
        fi
        echo "warning: apt-get install failed (attempt ${attempt}); retrying" >&2
        sleep $((attempt * 5))
        sudo apt-get update
    done
    return 1
}

install_php() {
    sudo apt-get update
    apt_install ca-certificates curl gnupg unzip git sqlite3 software-properties-common
    if ! apt-cache show php8.4-cli >/dev/null 2>&1; then
        sudo add-apt-repository -y ppa:ondrej/php
        sudo apt-get update
    fi
    apt_install \
        php8.4-cli \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-curl \
        php8.4-intl \
        php8.4-sqlite3 \
        php8.4-zip \
        php8.4-bcmath \
        php8.4-readline \
        php8.4-imagick \
        unzip \
        git \
        sqlite3 \
        tzdata \
        tzdata-legacy
    if [[ -x /usr/bin/php8.4 ]]; then
        sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.4 84
        sudo update-alternatives --set php /usr/bin/php8.4
    fi
}

install_composer() {
    if command -v composer >/dev/null 2>&1; then
        return 0
    fi
    # Pin from https://composer.github.io/installer.sig (checked 2026-11-19).
    # Fetching the sum at install time would share a trust root with the script.
    local installer="/tmp/composer-setup.php"
    local COMPOSER_INSTALLER_SHA384="c8b085408188070d5f52bcfe4ecfbee5f727afa458b2573b8eaaf77b3419b0bf2768dc67c86944da1544f06fa544fd47"
    curl -fsSL https://getcomposer.org/installer -o "${installer}"
    verify_digest sha384 "${installer}" "${COMPOSER_INSTALLER_SHA384}"
    php "${installer}" --install-dir=/tmp --filename=composer
    sudo mv /tmp/composer /usr/local/bin/composer
    sudo chmod 755 /usr/local/bin/composer
    rm -f "${installer}"
}

install_node() {
    local major
    major="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
    if command -v node >/dev/null 2>&1 && command -v npm >/dev/null 2>&1 && [[ "$major" -ge 22 ]]; then
        return 0
    fi
    local version="22.23.4"
    # SHA256 of node-v22.23.4-linux-x64.tar.xz from that release's SHASUMS256.txt
    # (checked 2026-11-19). A sum downloaded beside the tarball is not a pin.
    local tarball="/tmp/node.tar.xz"
    local NODE_LINUX_X64_SHA256="1805bcd35e6815559bd2eb97db438d3fdb0eb3a5e6a977b7c1badca76337e9ce"
    curl -fsSL "https://nodejs.org/dist/v${version}/node-v${version}-linux-x64.tar.xz" -o "${tarball}"
    verify_digest sha256 "${tarball}" "${NODE_LINUX_X64_SHA256}"
    sudo tar -xJf "${tarball}" -C /usr/local --strip-components=1
    rm -f "${tarball}"
    hash -r
}

# Flux and other composer.fluxui.dev packages are private. This app does not
# require them today; warn by name only when a lockfile or manifest does.
configure_private_composer_auth() {
    local manifest needs=0
    for manifest in composer.json composer.lock; do
        if [[ -f "$manifest" ]] && grep -Eq 'livewire/flux|composer\.fluxui\.dev' "$manifest"; then
            needs=1
        fi
    done
    if [[ "$needs" -eq 0 ]]; then
        return 0
    fi
    if [[ -n "${COMPOSER_AUTH:-}" ]]; then
        return 0
    fi
    if [[ -n "${FLUX_USERNAME:-}" && -n "${FLUX_LICENSE_KEY:-}" ]]; then
        composer config --global http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY" >/dev/null
        return 0
    fi
    local missing=()
    [[ -z "${FLUX_USERNAME:-}" ]] && missing+=(FLUX_USERNAME)
    [[ -z "${FLUX_LICENSE_KEY:-}" ]] && missing+=(FLUX_LICENSE_KEY)
    [[ -z "${COMPOSER_AUTH:-}" ]] && missing+=(COMPOSER_AUTH)
    echo "warning: private Composer package auth is missing: ${missing[*]}" >&2
    return 1
}

prepare_app() {
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
}

if ! php_ready; then
    install_php
fi
php_ready
# Pest accepts IANA backward aliases such as Asia/Calcutta. Ubuntu splits those
# out of tzdata, so a PHP install alone is not enough.
if ! dpkg -s tzdata-legacy >/dev/null 2>&1; then
    sudo apt-get update
    apt_install tzdata tzdata-legacy
fi

install_composer
install_node
configure_private_composer_auth

composer install --prefer-dist --no-interaction --no-progress --no-ansi
prepare_app

npm ci --no-audit --no-fund
npm run build

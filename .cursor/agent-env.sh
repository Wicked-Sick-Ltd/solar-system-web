#!/usr/bin/env bash
# Shared by .cursor/install.sh and .cursor/start.sh. Source this file; do not
# execute it. A cloud agent has no local catalogue API and serves plain HTTP.

# Replace every KEY= line in a dotenv file. Other lines, including comments, stay.
set_dotenv_assignment() {
    local key="$1"
    local value="$2"
    local file="$3"
    local tmp
    local found=0

    if [[ "${value}" == *$'\n'* || "${value}" == *$'\r'* ]]; then
        echo "error: ${key} contains a newline" >&2
        return 1
    fi

    tmp="$(mktemp)"
    while IFS= read -r line || [[ -n "${line}" ]]; do
        if [[ "${line}" == "${key}="* ]]; then
            printf '%s=%s\n' "${key}" "${value}" >> "${tmp}"
            found=1
        else
            printf '%s\n' "${line}" >> "${tmp}"
        fi
    done < "${file}"

    if [[ "${found}" -eq 0 ]]; then
        printf '%s=%s\n' "${key}" "${value}" >> "${tmp}"
    fi

    mv "${tmp}" "${file}"
}

# Create .env from .env.example only when it is missing. An existing file is
# left untouched, so an operator-supplied environment is preserved.
#
# A new file does not inherit the production example: APP_ENV=local stops
# AppServiceProvider from forcing HTTPS onto the HTTP server in serve.sh,
# APP_URL matches that server, and API_BASE_URL is the public catalogue from
# CONTRIBUTING.md. API_BASE_URL and APP_URL already present in the environment
# are written through. APP_ENV is always local for a newly created file.
ensure_agent_env() {
    local tmp
    local app_url="${APP_URL:-http://127.0.0.1:8000}"
    local api_base_url="${API_BASE_URL:-https://api.publicuniverse.net/api/v1}"

    if [[ -f .env ]]; then
        return 0
    fi

    if [[ ! -f .env.example ]]; then
        echo "error: .env.example is missing; cannot create a cloud-agent .env" >&2
        return 1
    fi

    tmp="$(mktemp)"
    cp .env.example "${tmp}"
    if ! set_dotenv_assignment APP_ENV local "${tmp}" \
        || ! set_dotenv_assignment APP_URL "${app_url}" "${tmp}" \
        || ! set_dotenv_assignment API_BASE_URL "${api_base_url}" "${tmp}"; then
        rm -f "${tmp}"
        return 1
    fi

    mv "${tmp}" .env
}

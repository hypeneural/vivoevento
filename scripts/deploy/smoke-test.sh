#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

SITE_URL="${SITE_URL:-https://eventovivo.com.br}"
ADMIN_URL="${ADMIN_URL:-https://admin.eventovivo.com.br}"
API_BASE_URL="${API_BASE_URL:-https://api.eventovivo.com.br}"
API_V1_URL="${API_V1_URL:-$API_BASE_URL/api/v1}"
WS_URL="${WS_URL:-https://ws.eventovivo.com.br}"
APP_ENV_FILE="${APP_ENV_FILE:-/var/www/eventovivo/shared/.env}"
REQUIRE_DEDICATED_HEALTH="${REQUIRE_DEDICATED_HEALTH:-1}"
SMOKE_LOGIN_IDENTIFIER="${SMOKE_LOGIN_IDENTIFIER:-}"
SMOKE_LOGIN_PASSWORD="${SMOKE_LOGIN_PASSWORD:-}"
SMOKE_UPLOAD_URL="${SMOKE_UPLOAD_URL:-}"
SMOKE_UPLOAD_FILE="${SMOKE_UPLOAD_FILE:-}"
SMOKE_UPLOAD_FIELD_NAME="${SMOKE_UPLOAD_FIELD_NAME:-file}"
SMOKE_WALL_BOOT_URL="${SMOKE_WALL_BOOT_URL:-}"
REVERB_APP_KEY="${REVERB_APP_KEY:-}"

log() {
    printf '[smoke] %s\n' "$*"
}

fail() {
    printf '[smoke] ERROR: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "missing command: $1"
}

http_status() {
    local url="$1"
    curl -k -sS -o /dev/null -w '%{http_code}' "$url"
}

assert_status() {
    local label="$1"
    local url="$2"
    local expected="$3"
    local status
    status="$(http_status "$url")"
    [[ "$status" == "$expected" ]] || fail "$label returned HTTP $status (expected $expected): $url"
    log "$label ok -> $status"
}

maybe_env_value() {
    local key="$1"
    local file="$2"

    [[ -f "$file" ]] || return 1
    awk -F= -v wanted="$key" '
        $1 == wanted {
            sub(/^[^=]+= */, "", $0)
            gsub(/^"/, "", $0)
            gsub(/"$/, "", $0)
            print $0
            exit
        }
    ' "$file"
}

probe_login() {
    [[ -n "$SMOKE_LOGIN_IDENTIFIER" && -n "$SMOKE_LOGIN_PASSWORD" ]] || return 0

    local payload
    local status
    payload="$(printf '{"login":"%s","password":"%s","device_name":"smoke-test"}' "$SMOKE_LOGIN_IDENTIFIER" "$SMOKE_LOGIN_PASSWORD")"

    status="$(
        curl -k -sS -o /dev/null -w '%{http_code}' \
            -H 'Accept: application/json' \
            -H 'Content-Type: application/json' \
            -X POST \
            --data "$payload" \
            "$API_V1_URL/auth/login"
    )"

    [[ "$status" == "200" ]] || fail "login probe returned HTTP $status"
    log "login probe ok -> $status"
}

probe_upload() {
    [[ -n "$SMOKE_UPLOAD_URL" && -n "$SMOKE_UPLOAD_FILE" ]] || return 0
    [[ -f "$SMOKE_UPLOAD_FILE" ]] || fail "upload file not found: $SMOKE_UPLOAD_FILE"

    local status
    status="$(
        curl -k -sS -o /dev/null -w '%{http_code}' \
            -X POST \
            -F "${SMOKE_UPLOAD_FIELD_NAME}=@${SMOKE_UPLOAD_FILE}" \
            "$SMOKE_UPLOAD_URL"
    )"

    [[ "$status" =~ ^2 ]] || fail "upload probe returned HTTP $status"
    log "upload probe ok -> $status"
}

probe_wall_boot() {
    [[ -n "$SMOKE_WALL_BOOT_URL" ]] || return 0
    assert_status "wall boot" "$SMOKE_WALL_BOOT_URL" "200"
}

probe_websocket() {
    local app_key="$REVERB_APP_KEY"
    local ws_http_url headers

    if [[ -z "$app_key" ]]; then
        app_key="$(maybe_env_value REVERB_APP_KEY "$APP_ENV_FILE" || true)"
    fi

    [[ -n "$app_key" ]] || fail "REVERB_APP_KEY is required for websocket smoke test"

    case "$WS_URL" in
        wss://*)
            ws_http_url="https://${WS_URL#wss://}"
            ;;
        ws://*)
            ws_http_url="http://${WS_URL#ws://}"
            ;;
        *)
            ws_http_url="$WS_URL"
            ;;
    esac

    headers="$(
        curl -k -sS -o /dev/null -D - --http1.1 \
            -H 'Connection: Upgrade' \
            -H 'Upgrade: websocket' \
            -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' \
            -H 'Sec-WebSocket-Version: 13' \
            "${ws_http_url%/}/app/${app_key}?protocol=7&client=smoke&version=1.0&flash=false"
    )"

    grep -q '101 Switching Protocols' <<<"$headers" || fail "websocket handshake did not return 101"
    log "websocket probe ok -> 101"
}

require_command curl
require_command grep

assert_status "landing" "$SITE_URL" "200"
assert_status "admin" "$ADMIN_URL" "200"
assert_status "api /up" "${API_BASE_URL%/}/up" "200"

if [[ "$REQUIRE_DEDICATED_HEALTH" == "1" ]]; then
    assert_status "api /health/live" "${API_BASE_URL%/}/health/live" "200"
    assert_status "api /health/ready" "${API_BASE_URL%/}/health/ready" "200"
else
    live_status="$(http_status "${API_BASE_URL%/}/health/live")"
    ready_status="$(http_status "${API_BASE_URL%/}/health/ready")"
    log "health/live status -> $live_status"
    log "health/ready status -> $ready_status"
fi

probe_login
probe_upload
probe_wall_boot
probe_websocket

log "smoke test completed"

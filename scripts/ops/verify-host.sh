#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

APP_ROOT="${APP_ROOT:-/var/www/eventovivo}"
NGINX_BIN="${NGINX_BIN:-nginx}"
PHP_BIN="${PHP_BIN:-php}"
PHP_FPM_BIN="${PHP_FPM_BIN:-php-fpm8.3}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NODE_BIN="${NODE_BIN:-node}"
NPM_BIN="${NPM_BIN:-npm}"
PSQL_BIN="${PSQL_BIN:-psql}"
SYSTEMD_ANALYZE_BIN="${SYSTEMD_ANALYZE_BIN:-systemd-analyze}"
REDIS_CLI_BIN="${REDIS_CLI_BIN:-redis-cli}"
PG_ISREADY_BIN="${PG_ISREADY_BIN:-pg_isready}"
POSTGRES_HOST="${POSTGRES_HOST:-127.0.0.1}"
POSTGRES_PORT="${POSTGRES_PORT:-5432}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"
TLS_CERT_PATH="${TLS_CERT_PATH:-/etc/ssl/certs/eventovivo-origin.crt}"
TLS_KEY_PATH="${TLS_KEY_PATH:-/etc/ssl/private/eventovivo-origin.key}"
USE_SUDO="${USE_SUDO:-1}"
REQUIRE_SHARED_ENV="${REQUIRE_SHARED_ENV:-0}"

usage() {
    cat <<'EOF'
Usage:
  verify-host.sh [options]

Options:
  --app-root PATH         Override /var/www/eventovivo
  --postgres-host HOST    Override 127.0.0.1
  --postgres-port PORT    Override 5432
  --redis-host HOST       Override 127.0.0.1
  --redis-port PORT       Override 6379
  --tls-cert PATH         Override /etc/ssl/certs/eventovivo-origin.crt
  --tls-key PATH          Override /etc/ssl/private/eventovivo-origin.key
  --require-shared-env    Fail if shared/.env is missing
  --no-sudo               Run commands directly
  --help                  Show this help
EOF
}

log() {
    printf '[verify-host] %s\n' "$*"
}

fail() {
    printf '[verify-host] ERROR: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "missing command: $1"
}

run_as_root() {
    if [[ "$USE_SUDO" == "1" && "$(id -u)" != "0" ]]; then
        sudo "$@"
    else
        "$@"
    fi
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-root)
            APP_ROOT="$2"
            shift 2
            ;;
        --postgres-host)
            POSTGRES_HOST="$2"
            shift 2
            ;;
        --postgres-port)
            POSTGRES_PORT="$2"
            shift 2
            ;;
        --redis-host)
            REDIS_HOST="$2"
            shift 2
            ;;
        --redis-port)
            REDIS_PORT="$2"
            shift 2
            ;;
        --tls-cert)
            TLS_CERT_PATH="$2"
            shift 2
            ;;
        --tls-key)
            TLS_KEY_PATH="$2"
            shift 2
            ;;
        --require-shared-env)
            REQUIRE_SHARED_ENV=1
            shift
            ;;
        --no-sudo)
            USE_SUDO=0
            shift
            ;;
        --help)
            usage
            exit 0
            ;;
        *)
            fail "unknown argument: $1"
            ;;
    esac
done

[[ "$APP_ROOT" = /* ]] || fail "APP_ROOT must be absolute"

require_command "$NGINX_BIN"
require_command "$PHP_BIN"
require_command "$PHP_FPM_BIN"
require_command "$COMPOSER_BIN"
require_command "$NODE_BIN"
require_command "$NPM_BIN"
require_command "$PSQL_BIN"
require_command "$SYSTEMD_ANALYZE_BIN"
require_command "$REDIS_CLI_BIN"
require_command "$PG_ISREADY_BIN"

log "Checking required runtime binaries"
run_as_root "$PHP_BIN" --version >/dev/null
run_as_root "$COMPOSER_BIN" --version >/dev/null
run_as_root "$NODE_BIN" --version >/dev/null
run_as_root "$NPM_BIN" --version >/dev/null
run_as_root "$PSQL_BIN" --version >/dev/null

log "Checking TLS assets required by nginx"
[[ -f "$TLS_CERT_PATH" ]] || fail "missing TLS certificate: $TLS_CERT_PATH"
[[ -f "$TLS_KEY_PATH" ]] || fail "missing TLS private key: $TLS_KEY_PATH"

log "Validating nginx configuration"
run_as_root "$NGINX_BIN" -t

if [[ -e /etc/nginx/sites-enabled/default || -L /etc/nginx/sites-enabled/default ]]; then
    fail "default nginx site is still enabled"
fi

log "Validating PHP-FPM pool configuration"
run_as_root "$PHP_FPM_BIN" -tt >/dev/null

log "Checking OPcache module availability"
run_as_root "$PHP_BIN" -m | grep -q '^Zend OPcache$' \
    || fail "Zend OPcache is not available in PHP"

log "Validating systemd units"
run_as_root "$SYSTEMD_ANALYZE_BIN" verify /etc/systemd/system/eventovivo-horizon.service
run_as_root "$SYSTEMD_ANALYZE_BIN" verify /etc/systemd/system/eventovivo-reverb.service
run_as_root "$SYSTEMD_ANALYZE_BIN" verify /etc/systemd/system/eventovivo-scheduler.service
run_as_root "$SYSTEMD_ANALYZE_BIN" verify /etc/systemd/system/eventovivo-scheduler.timer

log "Checking Redis availability"
[[ "$(run_as_root "$REDIS_CLI_BIN" -h "$REDIS_HOST" -p "$REDIS_PORT" ping)" == "PONG" ]] \
    || fail "redis ping failed on ${REDIS_HOST}:${REDIS_PORT}"

log "Checking PostgreSQL readiness"
run_as_root "$PG_ISREADY_BIN" -h "$POSTGRES_HOST" -p "$POSTGRES_PORT" >/dev/null

log "Checking app directory structure"
[[ -d "$APP_ROOT/releases" ]] || fail "missing $APP_ROOT/releases"
[[ -d "$APP_ROOT/shared/storage" ]] || fail "missing $APP_ROOT/shared/storage"
[[ -d "$APP_ROOT/shared/storage/app/public" ]] || fail "missing $APP_ROOT/shared/storage/app/public"
[[ -d "$APP_ROOT/shared/bootstrap-cache" ]] || fail "missing $APP_ROOT/shared/bootstrap-cache"
[[ -d "$APP_ROOT/scripts" ]] || fail "missing $APP_ROOT/scripts"
[[ -d "$APP_ROOT/shared/storage/framework/cache/data" ]] || fail "missing $APP_ROOT/shared/storage/framework/cache/data"
[[ -d "$APP_ROOT/shared/storage/framework/sessions" ]] || fail "missing $APP_ROOT/shared/storage/framework/sessions"
[[ -d "$APP_ROOT/shared/storage/framework/testing" ]] || fail "missing $APP_ROOT/shared/storage/framework/testing"
[[ -d "$APP_ROOT/shared/storage/framework/views" ]] || fail "missing $APP_ROOT/shared/storage/framework/views"

if [[ "$REQUIRE_SHARED_ENV" == "1" ]]; then
    [[ -f "$APP_ROOT/shared/.env" ]] || fail "missing $APP_ROOT/shared/.env"
    [[ -f "$APP_ROOT/shared/apps-web.env.production" ]] || fail "missing $APP_ROOT/shared/apps-web.env.production"
    [[ -f "$APP_ROOT/shared/apps-landing.env.production" ]] || fail "missing $APP_ROOT/shared/apps-landing.env.production"
    grep -q '^VITE_API_BASE_URL=' "$APP_ROOT/shared/apps-web.env.production" \
        || fail "missing VITE_API_BASE_URL in $APP_ROOT/shared/apps-web.env.production"
    grep -q '^VITE_REVERB_HOST=' "$APP_ROOT/shared/apps-web.env.production" \
        || fail "missing VITE_REVERB_HOST in $APP_ROOT/shared/apps-web.env.production"
    grep -q '^VITE_ADMIN_URL=' "$APP_ROOT/shared/apps-landing.env.production" \
        || fail "missing VITE_ADMIN_URL in $APP_ROOT/shared/apps-landing.env.production"
fi

log "Host verification completed"

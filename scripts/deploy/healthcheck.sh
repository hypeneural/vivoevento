#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

PHP_BIN="${PHP_BIN:-php}"

usage() {
    cat <<'EOF'
Usage:
  healthcheck.sh /absolute/path/to/release
EOF
}

fail() {
    printf '[healthcheck] ERROR: %s\n' "$*" >&2
    exit 1
}

[[ $# -eq 1 ]] || {
    usage
    exit 1
}

RELEASE_DIR="$1"
[[ "$RELEASE_DIR" = /* ]] || fail "release path must be absolute"
[[ -d "$RELEASE_DIR" ]] || fail "release directory not found: $RELEASE_DIR"

API_DIR="$RELEASE_DIR/apps/api"
WEB_DIR="$RELEASE_DIR/apps/web"
LANDING_DIR="$RELEASE_DIR/apps/landing"
WEB_DIST="$RELEASE_DIR/apps/web/dist/index.html"
LANDING_DIST="$RELEASE_DIR/apps/landing/dist/index.html"
WEB_ENV_FILE="$WEB_DIR/.env.production.local"
LANDING_ENV_FILE="$LANDING_DIR/.env.production.local"

[[ -f "$API_DIR/public/index.php" ]] || fail "missing API public entrypoint"
[[ -L "$API_DIR/.env" || -f "$API_DIR/.env" ]] || fail "missing API env file"
[[ -L "$API_DIR/storage" || -d "$API_DIR/storage" ]] || fail "missing API storage"
[[ -d "$API_DIR/storage/app/public" ]] || fail "missing API public storage root"
[[ -f "$WEB_DIST" ]] || fail "missing admin build"
[[ -f "$LANDING_DIST" ]] || fail "missing landing build"
[[ -f "$WEB_ENV_FILE" || -L "$WEB_ENV_FILE" ]] || fail "missing admin frontend env file"
[[ -f "$LANDING_ENV_FILE" || -L "$LANDING_ENV_FILE" ]] || fail "missing landing frontend env file"

(
    cd "$API_DIR"
    "$PHP_BIN" artisan about >/dev/null
)

# The app still uses public storage in parts of the pipeline. The deploy must
# ensure the public/storage link exists or an equivalent policy is in place.
[[ -L "$API_DIR/public/storage" || -d "$API_DIR/public/storage" ]] || fail "missing public/storage link"

WEB_API_BASE_URL="$(grep '^VITE_API_BASE_URL=' "$WEB_ENV_FILE" | tail -n 1 | cut -d= -f2- || true)"
WEB_REVERB_HOST="$(grep '^VITE_REVERB_HOST=' "$WEB_ENV_FILE" | tail -n 1 | cut -d= -f2- || true)"
[[ -n "$WEB_API_BASE_URL" ]] || fail "admin frontend env is missing VITE_API_BASE_URL"
[[ -n "$WEB_REVERB_HOST" ]] || fail "admin frontend env is missing VITE_REVERB_HOST"

if ! grep -R -F --quiet "$WEB_API_BASE_URL" "$RELEASE_DIR/apps/web/dist"; then
    fail "admin build does not contain VITE_API_BASE_URL=$WEB_API_BASE_URL"
fi

if ! grep -R -F --quiet "$WEB_REVERB_HOST" "$RELEASE_DIR/apps/web/dist"; then
    fail "admin build does not contain VITE_REVERB_HOST=$WEB_REVERB_HOST"
fi

printf '[healthcheck] release ok: %s\n' "$RELEASE_DIR"

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
WEB_DIST="$RELEASE_DIR/apps/web/dist/index.html"
LANDING_DIST="$RELEASE_DIR/apps/landing/dist/index.html"

[[ -f "$API_DIR/public/index.php" ]] || fail "missing API public entrypoint"
[[ -L "$API_DIR/.env" || -f "$API_DIR/.env" ]] || fail "missing API env file"
[[ -L "$API_DIR/storage" || -d "$API_DIR/storage" ]] || fail "missing API storage"
[[ -f "$WEB_DIST" ]] || fail "missing admin build"
[[ -f "$LANDING_DIST" ]] || fail "missing landing build"

(
    cd "$API_DIR"
    "$PHP_BIN" artisan about >/dev/null
)

# The app still uses public storage in parts of the pipeline. The deploy must
# ensure the public/storage link exists or an equivalent policy is in place.
[[ -L "$API_DIR/public/storage" || -d "$API_DIR/public/storage" ]] || fail "missing public/storage link"

printf '[healthcheck] release ok: %s\n' "$RELEASE_DIR"

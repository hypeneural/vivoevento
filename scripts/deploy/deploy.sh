#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

APP_ROOT="${APP_ROOT:-/var/www/eventovivo}"
SOURCE_DIR="${SOURCE_DIR:-$REPO_ROOT}"
RELEASE_NAME="${RELEASE_NAME:-$(date +%Y%m%d_%H%M%S)}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
SYSTEMCTL_BIN="${SYSTEMCTL_BIN:-systemctl}"
USE_SUDO="${USE_SUDO:-1}"
RUN_BUILD="${RUN_BUILD:-1}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
RUN_SMOKE_TEST="${RUN_SMOKE_TEST:-0}"

usage() {
    cat <<'EOF'
Usage:
  deploy.sh [options]

Options:
  --app-root PATH       Override /var/www/eventovivo
  --source PATH         Source repository to package into the release
  --release NAME        Explicit release name
  --keep N              Releases to keep after cleanup
  --skip-build          Skip frontend builds
  --skip-migrate        Skip php artisan migrate --force
  --run-smoke-test      Run smoke-test.sh after switching current
  --no-sudo             Call systemctl directly instead of sudo systemctl
  --help                Show this help
EOF
}

log() {
    printf '[deploy] %s\n' "$*"
}

fail() {
    printf '[deploy] ERROR: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "missing command: $1"
}

run_systemctl() {
    if [[ "$USE_SUDO" == "1" ]]; then
        sudo "$SYSTEMCTL_BIN" "$@"
    else
        "$SYSTEMCTL_BIN" "$@"
    fi
}

cleanup_old_releases() {
    local keep="$1"
    local current_target
    local releases=()

    [[ "$keep" =~ ^[0-9]+$ ]] || fail "KEEP_RELEASES must be numeric"
    current_target="$(readlink -f "$APP_ROOT/current" 2>/dev/null || true)"

    while IFS= read -r release_dir; do
        releases+=("$release_dir")
    done < <(find "$APP_ROOT/releases" -mindepth 1 -maxdepth 1 -type d | sort -r)

    if (( ${#releases[@]} <= keep )); then
        return 0
    fi

    for old_release in "${releases[@]:keep}"; do
        old_release="$(readlink -f "$old_release")"
        [[ -n "$old_release" ]] || continue
        [[ "$old_release" == "$current_target" ]] && continue
        [[ "$old_release" == "$(readlink -f "$RELEASE_DIR")" ]] && continue
        [[ "$old_release" == "$APP_ROOT/releases/"* ]] || fail "refusing to remove unexpected path: $old_release"
        log "Removing old release: $old_release"
        rm -rf -- "$old_release"
    done
}

prune_unreferenced_vite_assets() {
    local dist_dir="$1"
    local sw_file="$dist_dir/sw.js"
    local index_file="$dist_dir/index.html"

    [[ -d "$dist_dir/assets" ]] || return 0
    [[ -f "$sw_file" ]] || return 0

    while IFS= read -r asset; do
        local rel_path="${asset#"$dist_dir"/}"

        if grep -F --quiet "\"$rel_path\"" "$sw_file"; then
            continue
        fi

        if [[ -f "$index_file" ]] && grep -F --quiet "$rel_path" "$index_file"; then
            continue
        fi

        log "Removing unreferenced admin artifact: $rel_path"
        rm -f -- "$asset"
    done < <(find "$dist_dir/assets" -type f \( -name '*.js' -o -name '*.css' \))
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-root)
            APP_ROOT="$2"
            shift 2
            ;;
        --source)
            SOURCE_DIR="$2"
            shift 2
            ;;
        --release)
            RELEASE_NAME="$2"
            shift 2
            ;;
        --keep)
            KEEP_RELEASES="$2"
            shift 2
            ;;
        --skip-build)
            RUN_BUILD=0
            shift
            ;;
        --skip-migrate)
            RUN_MIGRATIONS=0
            shift
            ;;
        --run-smoke-test)
            RUN_SMOKE_TEST=1
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
[[ "$SOURCE_DIR" = /* ]] || fail "SOURCE_DIR must be absolute"
[[ -d "$SOURCE_DIR" ]] || fail "source directory not found: $SOURCE_DIR"

require_command rsync
require_command "$PHP_BIN"
require_command "$COMPOSER_BIN"
require_command "$NPM_BIN"
require_command "$SYSTEMCTL_BIN"

RELEASE_DIR="$APP_ROOT/releases/$RELEASE_NAME"
API_DIR="$RELEASE_DIR/apps/api"
WEB_DIR="$RELEASE_DIR/apps/web"
LANDING_DIR="$RELEASE_DIR/apps/landing"
WEB_ENV_FILE="${WEB_ENV_FILE:-$APP_ROOT/shared/apps-web.env.production}"
LANDING_ENV_FILE="${LANDING_ENV_FILE:-$APP_ROOT/shared/apps-landing.env.production}"
HEALTHCHECK_SCRIPT="$SCRIPT_DIR/healthcheck.sh"
SMOKE_TEST_SCRIPT="$SCRIPT_DIR/smoke-test.sh"

[[ ! -e "$RELEASE_DIR" ]] || fail "release already exists: $RELEASE_DIR"
[[ -f "$APP_ROOT/shared/.env" ]] || fail "missing shared env: $APP_ROOT/shared/.env"

mkdir -p \
    "$APP_ROOT/releases" \
    "$APP_ROOT/shared/storage/app/public" \
    "$APP_ROOT/shared/storage/framework/cache" \
    "$APP_ROOT/shared/storage/framework/cache/data" \
    "$APP_ROOT/shared/storage/framework/sessions" \
    "$APP_ROOT/shared/storage/framework/testing" \
    "$APP_ROOT/shared/storage/framework/views" \
    "$APP_ROOT/shared/storage/logs" \
    "$APP_ROOT/shared/bootstrap-cache" \
    "$APP_ROOT/scripts"

log "Creating release at $RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

rsync_args=(
    -a
    --exclude '.git'
    --exclude '.github'
    --exclude '.idea'
    --exclude '.vscode'
    --exclude 'node_modules'
    --exclude 'vendor'
    --exclude 'apps/api/storage'
    --exclude 'apps/api/bootstrap/cache'
)

if [[ "$RUN_BUILD" == "1" ]]; then
    rsync_args+=(
        --exclude 'apps/web/dist'
        --exclude 'apps/landing/dist'
    )
else
    [[ -f "$SOURCE_DIR/apps/web/dist/index.html" ]] || fail "missing admin dist in source while build is skipped"
    [[ -f "$SOURCE_DIR/apps/landing/dist/index.html" ]] || fail "missing landing dist in source while build is skipped"
fi

log "Syncing source code from $SOURCE_DIR"
rsync "${rsync_args[@]}" "$SOURCE_DIR/" "$RELEASE_DIR/"

[[ -d "$API_DIR" ]] || fail "release is missing apps/api"
[[ -d "$WEB_DIR" ]] || fail "release is missing apps/web"
[[ -d "$LANDING_DIR" ]] || fail "release is missing apps/landing"

log "Linking shared runtime assets"
ln -sfn "$APP_ROOT/shared/.env" "$API_DIR/.env"
rm -rf "$API_DIR/storage"
ln -sfn "$APP_ROOT/shared/storage" "$API_DIR/storage"
mkdir -p "$API_DIR/bootstrap/cache"

log "Linking shared frontend build env files"
[[ -f "$WEB_ENV_FILE" ]] || fail "missing admin frontend env: $WEB_ENV_FILE"
[[ -f "$LANDING_ENV_FILE" ]] || fail "missing landing frontend env: $LANDING_ENV_FILE"
ln -sfn "$WEB_ENV_FILE" "$WEB_DIR/.env.production.local"
ln -sfn "$LANDING_ENV_FILE" "$LANDING_DIR/.env.production.local"

log "Installing PHP dependencies"
(
    cd "$API_DIR"
    "$COMPOSER_BIN" install --no-dev --prefer-dist --optimize-autoloader --no-interaction

    # Keep the public storage link deterministic across releases. If the path
    # exists as a regular directory/file, fail instead of deleting user data.
    if [[ -L public/storage ]]; then
        rm -f public/storage
    elif [[ -e public/storage ]]; then
        fail "public/storage exists and is not a symlink in $API_DIR"
    fi

    "$PHP_BIN" artisan storage:link
)

if [[ "$RUN_BUILD" == "1" ]]; then
    log "Building admin frontend"
    (
        cd "$WEB_DIR"
        "$NPM_BIN" ci
        "$NPM_BIN" run build
    )

    log "Building landing frontend"
    (
        cd "$LANDING_DIR"
        "$NPM_BIN" ci
        "$NPM_BIN" run build
    )
fi

prune_unreferenced_vite_assets "$WEB_DIR/dist"

log "Warming Laravel caches"
(
    cd "$API_DIR"
    "$PHP_BIN" artisan config:clear
    "$PHP_BIN" artisan route:clear
    "$PHP_BIN" artisan view:clear
    "$PHP_BIN" artisan event:clear || true
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan view:cache
    "$PHP_BIN" artisan event:cache || true
)

if [[ "$RUN_MIGRATIONS" == "1" ]]; then
    log "Running migrations"
    (
        cd "$API_DIR"
        "$PHP_BIN" artisan migrate --force
    )
fi

log "Running release healthcheck"
bash "$HEALTHCHECK_SCRIPT" "$RELEASE_DIR"

log "Switching current symlink"
ln -sfn "$RELEASE_DIR" "$APP_ROOT/current"

log "Recycling long-lived processes"
run_systemctl reload php8.3-fpm
run_systemctl reload nginx
run_systemctl reload eventovivo-horizon
run_systemctl reload eventovivo-reverb

if [[ "$RUN_SMOKE_TEST" == "1" ]]; then
    log "Running smoke test"
    bash "$SMOKE_TEST_SCRIPT"
fi

cleanup_old_releases "$KEEP_RELEASES"

log "Release deployed successfully: $RELEASE_NAME"

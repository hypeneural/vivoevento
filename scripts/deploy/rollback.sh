#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

APP_ROOT="${APP_ROOT:-/var/www/eventovivo}"
PHP_BIN="${PHP_BIN:-php}"
SYSTEMCTL_BIN="${SYSTEMCTL_BIN:-systemctl}"
USE_SUDO="${USE_SUDO:-1}"
RUN_SMOKE_TEST="${RUN_SMOKE_TEST:-0}"
TARGET_RELEASE="${TARGET_RELEASE:-}"

usage() {
    cat <<'EOF'
Usage:
  rollback.sh [options]

Options:
  --app-root PATH       Override /var/www/eventovivo
  --release NAME        Explicit release name to restore
  --run-smoke-test      Run smoke-test.sh after rollback
  --no-sudo             Call systemctl directly instead of sudo systemctl
  --help                Show this help
EOF
}

log() {
    printf '[rollback] %s\n' "$*"
}

fail() {
    printf '[rollback] ERROR: %s\n' "$*" >&2
    exit 1
}

run_systemctl() {
    if [[ "$USE_SUDO" == "1" ]]; then
        sudo "$SYSTEMCTL_BIN" "$@"
    else
        "$SYSTEMCTL_BIN" "$@"
    fi
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-root)
            APP_ROOT="$2"
            shift 2
            ;;
        --release)
            TARGET_RELEASE="$2"
            shift 2
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
[[ -d "$APP_ROOT/releases" ]] || fail "missing releases directory: $APP_ROOT/releases"

current_target="$(readlink -f "$APP_ROOT/current" 2>/dev/null || true)"
[[ -n "$current_target" ]] || fail "current release is not set"

if [[ -n "$TARGET_RELEASE" ]]; then
    target_dir="$APP_ROOT/releases/$TARGET_RELEASE"
    [[ -d "$target_dir" ]] || fail "release not found: $target_dir"
else
    target_dir=""
    while IFS= read -r release_dir; do
        release_real="$(readlink -f "$release_dir")"
        [[ "$release_real" == "$current_target" ]] && continue
        target_dir="$release_dir"
        break
    done < <(find "$APP_ROOT/releases" -mindepth 1 -maxdepth 1 -type d | sort -r)
    [[ -n "$target_dir" ]] || fail "no previous release available for rollback"
fi

target_dir="$(readlink -f "$target_dir")"
[[ "$target_dir" == "$APP_ROOT/releases/"* ]] || fail "unexpected rollback target: $target_dir"

log "Repointing current to $target_dir"
ln -sfn "$target_dir" "$APP_ROOT/current"

log "Recycling long-lived processes"
run_systemctl reload php8.3-fpm
run_systemctl reload nginx
run_systemctl reload eventovivo-horizon
run_systemctl reload eventovivo-reverb

if [[ "$RUN_SMOKE_TEST" == "1" ]]; then
    "$SCRIPT_DIR/smoke-test.sh"
fi

log "Rollback completed"

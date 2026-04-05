#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="${REPO_ROOT:-$(cd "$SCRIPT_DIR/../.." && pwd)}"
APP_ROOT="${APP_ROOT:-/var/www/eventovivo}"
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/eventovivo-configs/$(date +%Y%m%d_%H%M%S)}"
RELOAD_SYSTEMD="${RELOAD_SYSTEMD:-1}"
PG_VERSION="${PG_VERSION:-16}"
REDIS_MAIN_CONF="${REDIS_MAIN_CONF:-/etc/redis/redis.conf}"
REDIS_CONF_DIR="${REDIS_CONF_DIR:-/etc/redis/redis.conf.d}"

usage() {
    cat <<'EOF'
Usage:
  sudo install-configs.sh [options]

Options:
  --repo-root PATH       Override repository root
  --app-root PATH        Override /var/www/eventovivo
  --backup-root PATH     Override backup directory
  --pg-version N         Override PostgreSQL major version (default: 16)
  --no-daemon-reload     Skip systemctl daemon-reload
  --help                 Show this help
EOF
}

log() {
    printf '[install-configs] %s\n' "$*"
}

fail() {
    printf '[install-configs] ERROR: %s\n' "$*" >&2
    exit 1
}

require_root() {
    [[ "$(id -u)" == "0" ]] || fail "this script must run as root"
}

backup_if_present() {
    local target="$1"
    local backup_path

    if [[ -e "$target" || -L "$target" ]]; then
        backup_path="$BACKUP_ROOT$target"
        mkdir -p "$(dirname "$backup_path")"
        cp -a "$target" "$backup_path"
    fi
}

install_file() {
    local source="$1"
    local target="$2"
    local mode="${3:-0644}"

    [[ -f "$source" ]] || fail "source file not found: $source"
    backup_if_present "$target"
    mkdir -p "$(dirname "$target")"
    install -m "$mode" "$source" "$target"
}

ensure_symlink() {
    local target="$1"
    local link_path="$2"

    backup_if_present "$link_path"
    mkdir -p "$(dirname "$link_path")"
    ln -sfn "$target" "$link_path"
}

ensure_line_present() {
    local file="$1"
    local line="$2"

    [[ -f "$file" ]] || fail "file not found: $file"

    if ! grep -Fqx "$line" "$file"; then
        backup_if_present "$file"
        printf '\n%s\n' "$line" >> "$file"
    fi
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --repo-root)
            REPO_ROOT="$2"
            shift 2
            ;;
        --app-root)
            APP_ROOT="$2"
            shift 2
            ;;
        --backup-root)
            BACKUP_ROOT="$2"
            shift 2
            ;;
        --pg-version)
            PG_VERSION="$2"
            shift 2
            ;;
        --no-daemon-reload)
            RELOAD_SYSTEMD=0
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

require_root
[[ "$REPO_ROOT" = /* ]] || fail "REPO_ROOT must be absolute"
[[ "$APP_ROOT" = /* ]] || fail "APP_ROOT must be absolute"
[[ -d "$REPO_ROOT/deploy" ]] || fail "deploy directory not found under: $REPO_ROOT"

mkdir -p "$BACKUP_ROOT" "$APP_ROOT/scripts"

log "Installing Nginx configuration"
install_file "$REPO_ROOT/deploy/nginx/nginx.conf" "/etc/nginx/nginx.conf"
install_file "$REPO_ROOT/deploy/nginx/conf.d/cloudflare-real-ip.conf" "/etc/nginx/conf.d/cloudflare-real-ip.conf"
install_file "$REPO_ROOT/deploy/nginx/sites/eventovivo-landing.conf" "/etc/nginx/sites-available/eventovivo-landing.conf"
install_file "$REPO_ROOT/deploy/nginx/sites/eventovivo-admin.conf" "/etc/nginx/sites-available/eventovivo-admin.conf"
install_file "$REPO_ROOT/deploy/nginx/sites/eventovivo-api.conf" "/etc/nginx/sites-available/eventovivo-api.conf"
install_file "$REPO_ROOT/deploy/nginx/sites/eventovivo-ws.conf" "/etc/nginx/sites-available/eventovivo-ws.conf"

ensure_symlink "/etc/nginx/sites-available/eventovivo-landing.conf" "/etc/nginx/sites-enabled/eventovivo-landing.conf"
ensure_symlink "/etc/nginx/sites-available/eventovivo-admin.conf" "/etc/nginx/sites-enabled/eventovivo-admin.conf"
ensure_symlink "/etc/nginx/sites-available/eventovivo-api.conf" "/etc/nginx/sites-enabled/eventovivo-api.conf"
ensure_symlink "/etc/nginx/sites-available/eventovivo-ws.conf" "/etc/nginx/sites-enabled/eventovivo-ws.conf"

if [[ -L /etc/nginx/sites-enabled/default || -e /etc/nginx/sites-enabled/default ]]; then
    log "Disabling default nginx site"
    backup_if_present "/etc/nginx/sites-enabled/default"
    rm -f /etc/nginx/sites-enabled/default
fi

log "Installing PHP runtime configuration"
install_file "$REPO_ROOT/deploy/php/opcache-production.ini" "/etc/php/8.3/fpm/conf.d/99-eventovivo-opcache.ini"
install_file "$REPO_ROOT/deploy/php-fpm/eventovivo.conf" "/etc/php/8.3/fpm/pool.d/eventovivo.conf"

log "Installing Redis configuration"
mkdir -p "$REDIS_CONF_DIR"
[[ -f "$REDIS_MAIN_CONF" ]] || fail "redis main config not found: $REDIS_MAIN_CONF"
ensure_line_present "$REDIS_MAIN_CONF" "include $REDIS_CONF_DIR/99-eventovivo.conf"
install_file "$REPO_ROOT/deploy/redis/eventovivo.conf" "$REDIS_CONF_DIR/99-eventovivo.conf"

log "Installing PostgreSQL configuration"
POSTGRES_CONF_DIR="/etc/postgresql/$PG_VERSION/main/conf.d"
mkdir -p "$POSTGRES_CONF_DIR"
install_file "$REPO_ROOT/deploy/postgresql/eventovivo.conf" "$POSTGRES_CONF_DIR/99-eventovivo.conf"

log "Installing systemd units"
install_file "$REPO_ROOT/deploy/systemd/eventovivo-horizon.service" "/etc/systemd/system/eventovivo-horizon.service"
install_file "$REPO_ROOT/deploy/systemd/eventovivo-reverb.service" "/etc/systemd/system/eventovivo-reverb.service"
install_file "$REPO_ROOT/deploy/systemd/eventovivo-scheduler.service" "/etc/systemd/system/eventovivo-scheduler.service"
install_file "$REPO_ROOT/deploy/systemd/eventovivo-scheduler.timer" "/etc/systemd/system/eventovivo-scheduler.timer"

log "Installing logrotate policy"
install_file "$REPO_ROOT/deploy/logrotate/eventovivo" "/etc/logrotate.d/eventovivo"

if [[ -f "$REPO_ROOT/deploy/sudoers/eventovivo-deploy-systemctl" ]]; then
    log "Installing deploy sudoers policy"
    install_file "$REPO_ROOT/deploy/sudoers/eventovivo-deploy-systemctl" "/etc/sudoers.d/eventovivo-deploy-systemctl" "0440"
fi

log "Installing deploy and ops scripts under $APP_ROOT/scripts"
for script in "$REPO_ROOT"/scripts/deploy/*.sh "$REPO_ROOT"/scripts/ops/*.sh; do
    [[ -f "$script" ]] || continue
    install_file "$script" "$APP_ROOT/scripts/$(basename "$script")" "0755"
done

if [[ "$RELOAD_SYSTEMD" == "1" ]]; then
    log "Reloading systemd daemon"
    systemctl daemon-reload
fi

log "Config installation completed"
log "Next steps: reload redis/postgresql if needed, then run verify-host.sh before enabling app services."

#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

APP_ROOT="${APP_ROOT:-/var/www/eventovivo}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
TIMEZONE_NAME="${TIMEZONE_NAME:-America/Sao_Paulo}"
ENABLE_UFW="${ENABLE_UFW:-0}"
ENABLE_UNATTENDED_UPGRADES="${ENABLE_UNATTENDED_UPGRADES:-1}"
INSTALL_NODESOURCE="${INSTALL_NODESOURCE:-1}"
NODE_MAJOR="${NODE_MAJOR:-24}"
ENABLE_SERVICES_NOW="${ENABLE_SERVICES_NOW:-1}"
INSTALL_PGVECTOR="${INSTALL_PGVECTOR:-1}"

usage() {
    cat <<'EOF'
Usage:
  sudo bootstrap-host.sh [options]

Options:
  --app-root PATH        Override /var/www/eventovivo
  --deploy-user NAME     Override deploy user
  --timezone TZ          Override timezone
  --enable-ufw           Enable ufw after configuring 22, 80 and 443
  --disable-nodesource   Do not configure NodeSource (default target: 24.x)
  --skip-pgvector        Skip postgresql-16-pgvector (not recommended while the current migration set requires vector)
  --install-pgvector     Force postgresql-16-pgvector installation
  --no-enable-services   Do not enable nginx/php-fpm/redis/postgresql now
  --help                 Show this help
EOF
}

log() {
    printf '[bootstrap-host] %s\n' "$*"
}

fail() {
    printf '[bootstrap-host] ERROR: %s\n' "$*" >&2
    exit 1
}

require_root() {
    [[ "$(id -u)" == "0" ]] || fail "this script must run as root"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-root)
            APP_ROOT="$2"
            shift 2
            ;;
        --deploy-user)
            DEPLOY_USER="$2"
            shift 2
            ;;
        --timezone)
            TIMEZONE_NAME="$2"
            shift 2
            ;;
        --enable-ufw)
            ENABLE_UFW=1
            shift
            ;;
        --disable-nodesource)
            INSTALL_NODESOURCE=0
            shift
            ;;
        --install-pgvector)
            INSTALL_PGVECTOR=1
            shift
            ;;
        --skip-pgvector)
            INSTALL_PGVECTOR=0
            shift
            ;;
        --no-enable-services)
            ENABLE_SERVICES_NOW=0
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
[[ "$APP_ROOT" = /* ]] || fail "APP_ROOT must be absolute"

log "Updating apt metadata"
apt-get update

log "Installing base packages"
apt-get install -y \
    ca-certificates \
    curl \
    fail2ban \
    git \
    gnupg \
    jq \
    logrotate \
    nginx \
    postgresql-contrib \
    redis-tools \
    redis-server \
    rsync \
    tzdata \
    ufw \
    unzip \
    php8.3-bcmath \
    php8.3-cli \
    php8.3-curl \
    php8.3-exif \
    php8.3-fpm \
    php8.3-gd \
    php8.3-intl \
    php8.3-mbstring \
    php8.3-opcache \
    php8.3-pgsql \
    php8.3-redis \
    php8.3-xml \
    php8.3-zip \
    postgresql-16 \
    postgresql-client-16 \
    composer

if [[ "$INSTALL_PGVECTOR" == "1" ]]; then
    log "Installing pgvector package for PostgreSQL 16"
    apt-get install -y postgresql-16-pgvector
fi

if [[ "$INSTALL_NODESOURCE" == "1" ]]; then
    node_major="$(node -v 2>/dev/null | sed -E 's/^v([0-9]+).*/\1/' || true)"
    if [[ -z "$node_major" || "$node_major" -lt "$NODE_MAJOR" ]]; then
        log "Configuring NodeSource ${NODE_MAJOR}.x repository"
        curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
        apt-get install -y nodejs
    else
        log "Node.js >= ${NODE_MAJOR} already available"
    fi
fi

if ! id -u "$DEPLOY_USER" >/dev/null 2>&1; then
    log "Creating deploy user: $DEPLOY_USER"
    adduser --disabled-password --gecos "" "$DEPLOY_USER"
fi

usermod -a -G www-data "$DEPLOY_USER"

log "Preparing app root at $APP_ROOT"
mkdir -p \
    "$APP_ROOT/releases" \
    "$APP_ROOT/shared/storage/app" \
    "$APP_ROOT/shared/storage/framework" \
    "$APP_ROOT/shared/storage/logs" \
    "$APP_ROOT/shared/bootstrap-cache" \
    "$APP_ROOT/shared/run/reverb" \
    "$APP_ROOT/scripts"

chown -R "$DEPLOY_USER":www-data "$APP_ROOT"
find "$APP_ROOT" -type d -exec chmod 775 {} \;

log "Configuring timezone"
timedatectl set-timezone "$TIMEZONE_NAME"

if [[ "$ENABLE_UNATTENDED_UPGRADES" == "1" ]]; then
    log "Installing unattended-upgrades"
    apt-get install -y unattended-upgrades
    cat >/etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
    systemctl enable --now apt-daily.timer
    systemctl enable --now apt-daily-upgrade.timer
fi

if [[ "$ENABLE_UFW" == "1" ]]; then
    log "Configuring ufw"
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
fi

if [[ "$ENABLE_SERVICES_NOW" == "1" ]]; then
    log "Enabling base services"
    systemctl enable --now nginx
    systemctl enable --now php8.3-fpm
    systemctl enable --now redis-server
    systemctl enable --now postgresql
    systemctl enable --now fail2ban
fi

log "Bootstrap completed"
log "Next steps: install repo configs, create shared/.env, provision PostgreSQL and Redis credentials, then run verify-host.sh."

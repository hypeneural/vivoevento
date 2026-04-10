#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'

APP_ROOT="${APP_ROOT:-/var/www/eventovivo}"
CURRENT_API_DIR="${CURRENT_API_DIR:-$APP_ROOT/current/apps/api}"
OUTPUT_DIR="${OUTPUT_DIR:-$APP_ROOT/shared/wall-video-homologation}"
PHP_BIN="${PHP_BIN:-php}"
CURL_BIN="${CURL_BIN:-curl}"
JQ_BIN="${JQ_BIN:-jq}"
DEVICE_CLASS="${DEVICE_CLASS:-}"
NETWORK_CLASS="${NETWORK_CLASS:-}"
BROWSER_LABEL="${BROWSER_LABEL:-chrome-desktop}"
WALL_LABEL="${WALL_LABEL:-wall-video}"
WALL_BOOT_URL="${WALL_BOOT_URL:-}"
API_BASE_URL="${API_BASE_URL:-}"
NOTES="${NOTES:-}"

usage() {
    cat <<'EOF'
Usage:
  homologate-wall-video.sh [options]

Options:
  --device-class LABEL   Required. Ex: desktop-forte, notebook-fraco, mini-pc
  --network-class LABEL  Required. Ex: saudavel, moderada, degradada
  --browser LABEL        Optional. Default: chrome-desktop
  --wall-label LABEL     Optional. Human label for the wall under test
  --wall-boot-url URL    Required. Public boot URL of the wall
  --api-base-url URL     Optional. Ex: https://api.eventovivo.com.br
  --output-dir PATH      Optional. Default: /var/www/eventovivo/shared/wall-video-homologation
  --notes TEXT           Optional. Extra operator notes to seed the report
  --help                 Show this help
EOF
}

log() {
    printf '[wall-video-homologation] %s\n' "$*"
}

fail() {
    printf '[wall-video-homologation] ERROR: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "missing command: $1"
}

http_status() {
    local url="$1"
    "$CURL_BIN" -k -sS -o /dev/null -w '%{http_code}' "$url"
}

slugify() {
    printf '%s' "$1" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+//; s/-+$//'
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --device-class)
            DEVICE_CLASS="$2"
            shift 2
            ;;
        --network-class)
            NETWORK_CLASS="$2"
            shift 2
            ;;
        --browser)
            BROWSER_LABEL="$2"
            shift 2
            ;;
        --wall-label)
            WALL_LABEL="$2"
            shift 2
            ;;
        --wall-boot-url)
            WALL_BOOT_URL="$2"
            shift 2
            ;;
        --api-base-url)
            API_BASE_URL="$2"
            shift 2
            ;;
        --output-dir)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        --notes)
            NOTES="$2"
            shift 2
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

[[ -n "$DEVICE_CLASS" ]] || fail "--device-class is required"
[[ -n "$NETWORK_CLASS" ]] || fail "--network-class is required"
[[ -n "$WALL_BOOT_URL" ]] || fail "--wall-boot-url is required"

require_command "$PHP_BIN"
require_command "$CURL_BIN"
require_command "$JQ_BIN"

mkdir -p "$OUTPUT_DIR"

timestamp_utc="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
timestamp_file="$(date -u +%Y%m%dT%H%M%SZ)"
device_slug="$(slugify "$DEVICE_CLASS")"
network_slug="$(slugify "$NETWORK_CLASS")"
browser_slug="$(slugify "$BROWSER_LABEL")"
wall_slug="$(slugify "$WALL_LABEL")"
report_path="$OUTPUT_DIR/${timestamp_file}-${wall_slug}-${device_slug}-${network_slug}-${browser_slug}.md"

tooling_status='SKIPPED'
tooling_summary='Current API release not available; media:tooling-status skipped.'
if [[ -d "$CURRENT_API_DIR" && -f "$CURRENT_API_DIR/artisan" ]]; then
    log "Running media:tooling-status from $CURRENT_API_DIR"
    if tooling_output="$(cd "$CURRENT_API_DIR" && "$PHP_BIN" artisan media:tooling-status 2>&1)"; then
        tooling_status='PASS'
        tooling_summary="$tooling_output"
    else
        tooling_status='FAIL'
        tooling_summary="$tooling_output"
    fi
fi

log "Fetching wall boot payload"
boot_json="$("$CURL_BIN" -k -sS "$WALL_BOOT_URL")"
boot_status='PASS'
wall_status="$(printf '%s' "$boot_json" | "$JQ_BIN" -r '.settings.status // .status // "unknown"')"
files_count="$(printf '%s' "$boot_json" | "$JQ_BIN" -r '(.files // []) | length')"
served_variant="$(printf '%s' "$boot_json" | "$JQ_BIN" -r '(.files[0].served_variant_key // "n/a")')"
preview_variant="$(printf '%s' "$boot_json" | "$JQ_BIN" -r '(.files[0].preview_variant_key // "n/a")')"
video_admission="$(printf '%s' "$boot_json" | "$JQ_BIN" -r '(.files[0].video_admission.status // "n/a")')"

health_live_status='SKIPPED'
health_ready_status='SKIPPED'
if [[ -n "$API_BASE_URL" ]]; then
    log "Checking health endpoints on $API_BASE_URL"
    health_live_status="$(http_status "${API_BASE_URL%/}/health/live")"
    health_ready_status="$(http_status "${API_BASE_URL%/}/health/ready")"
fi

cat >"$report_path" <<EOF
# Wall Video Homologation Report

- generated_at_utc: \`${timestamp_utc}\`
- wall_label: \`${WALL_LABEL}\`
- device_class: \`${DEVICE_CLASS}\`
- network_class: \`${NETWORK_CLASS}\`
- browser: \`${BROWSER_LABEL}\`
- wall_boot_url: \`${WALL_BOOT_URL}\`
- api_base_url: \`${API_BASE_URL:-n/a}\`

## Automated checks

- media_tooling_status: \`${tooling_status}\`
- wall_boot_fetch: \`${boot_status}\`
- api_health_live: \`${health_live_status}\`
- api_health_ready: \`${health_ready_status}\`

## Tooling evidence

\`\`\`text
${tooling_summary}
\`\`\`

## Wall boot evidence

- wall_status: \`${wall_status}\`
- files_count: \`${files_count}\`
- first_served_variant_key: \`${served_variant}\`
- first_preview_variant_key: \`${preview_variant}\`
- first_video_admission: \`${video_admission}\`

## Manual runtime checklist

- [ ] startup mostrou poster ou primeira frame sem tela preta prolongada
- [ ] video nao bloqueou a fila quando houve startup lento
- [ ] pausa e resume funcionaram com estado consistente
- [ ] reconnect do wall nao quebrou o playback atual
- [ ] troca de settings em tempo real refletiu no comportamento esperado
- [ ] item deletado durante playback saiu com motivo operacional coerente
- [ ] fila mista com imagem + video + anuncio nao entrou em corrida de scheduler
- [ ] manager mostrou phase, exit reason, failure reason e perfil de hardware/rede corretos
- [ ] analytics registraram \`video_start\` e \`video_first_frame\`
- [ ] analytics registraram \`video_complete\` ou \`video_interrupted_by_cap\` com causa coerente

## Operator notes

${NOTES:-_fill during homologation_}
EOF

log "Homologation report written to $report_path"

if [[ "$tooling_status" == 'FAIL' ]]; then
    fail "media:tooling-status failed; see report: $report_path"
fi

log "Wall video homologation scaffold completed"

#!/usr/bin/env bash
set -euo pipefail

API_DIR="${API_DIR:-apps/api}"

echo "Instalando ffmpeg via apt..."
sudo apt-get update
sudo apt-get install -y ffmpeg

FFMPEG_BIN="$(command -v ffmpeg)"
FFPROBE_BIN="$(command -v ffprobe)"

if [[ -z "${FFMPEG_BIN}" || -z "${FFPROBE_BIN}" ]]; then
  echo "ffmpeg/ffprobe nao foram encontrados apos a instalacao." >&2
  exit 1
fi

cat <<EOF

Configure no ambiente do ${API_DIR}:
MEDIA_FFMPEG_BIN=${FFMPEG_BIN}
MEDIA_FFPROBE_BIN=${FFPROBE_BIN}

Depois valide com:
cd ${API_DIR}
php artisan media:tooling-status
EOF

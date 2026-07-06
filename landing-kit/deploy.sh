#!/usr/bin/env bash
# ============================================================================
# Публикация лендинга на хостинг по SFTP/FTP одной командой.
# Запускать ЛОКАЛЬНО из Claude Code Desktop:  bash deploy.sh
#
# Заливает содержимое landing-kit/ в REMOTE_DIR на сервере, НЕ трогая
# папку с заявками (api/data) и служебные файлы. Настройки — в deploy.config
# (скопируйте из deploy.config.example, он не коммитится в git).
# ============================================================================
set -euo pipefail
cd "$(dirname "$0")"

CONF="./deploy.config"
if [ ! -f "$CONF" ]; then
  echo "❌ Нет deploy.config. Скопируйте: cp deploy.config.example deploy.config и заполните." >&2
  exit 1
fi
# shellcheck disable=SC1090
source "$CONF"

: "${SFTP_HOST:?Укажите SFTP_HOST в deploy.config}"
: "${SFTP_USER:?Укажите SFTP_USER в deploy.config}"
: "${REMOTE_DIR:?Укажите REMOTE_DIR в deploy.config}"
PROTO="${PROTO:-sftp}"          # sftp | ftp | ftps
PORT="${PORT:-22}"              # sftp обычно 22, ftp/ftps — 21

if ! command -v lftp >/dev/null 2>&1; then
  echo "❌ Нужен lftp. macOS: brew install lftp" >&2
  exit 1
fi

# Файлы, которые НЕ нужны на боевом сервере:
EXCLUDES="\
--exclude-glob deploy.sh \
--exclude-glob deploy.config \
--exclude-glob deploy.config.example \
--exclude-glob README.md \
--exclude-glob SETUP.md \
--exclude-glob .gitignore \
--exclude-glob api/config.example.php"

# Папку с заявками не синхронизируем вообще — чтобы не затереть/не скачать ПДн:
EXCLUDES="$EXCLUDES --exclude api/data/"

echo "→ Публикую в ${PROTO}://${SFTP_HOST}:${PORT}${REMOTE_DIR}"

lftp -u "${SFTP_USER},${SFTP_PASS:-}" -p "${PORT}" "${PROTO}://${SFTP_HOST}" <<EOF
set sftp:auto-confirm yes
set ftp:ssl-allow ${FTPS_ENFORCE:-no}
set net:max-retries 3
set net:timeout 15
mirror --reverse --verbose --continue --no-perms ${EXCLUDES} ./ "${REMOTE_DIR}"
bye
EOF

echo "✅ Готово. Проверьте: https://okk.easy-1c.ru"

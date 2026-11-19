#!/usr/bin/env bash
# Backup do banco do NEXO Chat (pg_dump compactado). Mantém 14 dias.
# Cron sugerido (todo dia às 3h):
#   0 3 * * * /opt/nexo-chat/deploy/backup.sh >> /var/log/nexo-chat-backup.log 2>&1
# Para guardar fora do servidor, defina BACKUP_S3 (ex.: s3://meu-bucket/nexo-chat).
set -euo pipefail
cd "$(dirname "$0")/.."

DEST="${BACKUP_DIR:-/var/backups/nexo-chat}"
mkdir -p "$DEST"
FILE="$DEST/nexo_chat_$(date +%Y%m%d_%H%M%S).sql.gz"

set -a; . ./.env; set +a
docker compose -f compose.prod.yaml exec -T postgres \
    pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" --no-owner | gzip > "$FILE"

find "$DEST" -name 'nexo_chat_*.sql.gz' -mtime +14 -delete

if [ -n "${BACKUP_S3:-}" ]; then
    aws s3 cp "$FILE" "$BACKUP_S3/" --only-show-errors
fi

echo "$(date '+%F %T') backup ok: $FILE ($(du -h "$FILE" | cut -f1))"

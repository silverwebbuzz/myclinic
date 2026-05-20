#!/usr/bin/env bash
# Cron: daily 3 AM — 0 3 * * * /path/to/myclinic/workers/backup.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

STAMP=$(date +%Y%m%d_%H%M%S)
OUT_DIR="${BACKUP_DIR:-$ROOT/storage/backups}"
mkdir -p "$OUT_DIR"

FILE="$OUT_DIR/manageclinic_${STAMP}.sql.gz"
mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-root}" \
  ${DB_PASSWORD:+-p"$DB_PASSWORD"} "${DB_DATABASE:-manageclinic}" | gzip > "$FILE"

find "$OUT_DIR" -name 'manageclinic_*.sql.gz' -mtime +14 -delete
echo "Backup written: $FILE"

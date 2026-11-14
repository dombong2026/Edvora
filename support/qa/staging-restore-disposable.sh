#!/usr/bin/env bash
# Operator-invoked disposable-only restore. WRITES to a new empty QA database.
set -Eeuo pipefail
set +x
umask 077

ARCHIVE="${1:-}"
DB="${2:-}"
CNF="${3:-}"
IDENTITY="${4:-}"
MYSQL="${MYSQL_BIN:-mysql}"

if [[ "${EDVORA_RESTORE_ACK:-}" != "DISPOSABLE_STAGING_DATABASE_ONLY" ]]; then
  echo 'BLOCKED: set EDVORA_RESTORE_ACK=DISPOSABLE_STAGING_DATABASE_ONLY'
  exit 2
fi
if [[ ! "$DB" =~ ^edvora_restore_[a-z0-9_]{3,40}$ ]]; then
  echo 'BLOCKED: destination must be an Edvora disposable restore schema'
  exit 2
fi
if [[ "$ARCHIVE" != /var/backups/edvora-staging/*.sql.age || -L "$ARCHIVE" || ! -s "$ARCHIVE" ]]; then
  echo 'BLOCKED: approved encrypted QA backup file required'
  exit 2
fi
if [[ -L "$CNF" || ! -f "$CNF" || "$(stat -c '%a' -- "$CNF")" != 600 ]]; then
  echo 'BLOCKED: mode-600 MySQL credentials file required'
  exit 2
fi
if [[ -L "$IDENTITY" || ! -f "$IDENTITY" || "$(stat -c '%a' -- "$IDENTITY")" != 600 ]]; then
  echo 'BLOCKED: mode-600 AGE identity file required'
  exit 2
fi
command -v age >/dev/null 2>&1 || exit 2
command -v "$MYSQL" >/dev/null 2>&1 || exit 2

CHECK="$("$MYSQL" --defaults-extra-file="$CNF" --batch --skip-column-names \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';")"
if [[ "$CHECK" != "0" ]]; then
  echo 'BLOCKED: restore destination must already exist and be empty'
  exit 2
fi

age -d -i "$IDENTITY" "$ARCHIVE" | "$MYSQL" --defaults-extra-file="$CNF" "$DB"

TABLES="$("$MYSQL" --defaults-extra-file="$CNF" --batch --skip-column-names \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB';")"
if ! [[ "$TABLES" =~ ^[0-9]+$ ]] || (( TABLES < 5 )); then
  echo 'RESTORE_VERIFY=FAILED: schema tables missing'
  exit 2
fi
echo "RESTORE_SCHEMA_TABLES=$TABLES"
echo 'ENCRYPTED_STAGING_RESTORE=PASS'
echo 'APPLICATION_LEVEL_RESTORE_ACCEPTANCE=PENDING'

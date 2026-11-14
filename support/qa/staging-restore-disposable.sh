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

# Reject damaged or tampered archives before a database connection is opened.
CHECKSUM="$ARCHIVE.sha256"
if [[ -L "$CHECKSUM" || ! -f "$CHECKSUM" || "$(stat -c '%a' -- "$CHECKSUM")" != 600 ]]; then
  echo 'BLOCKED: private matching checksum manifest is missing'
  exit 2
fi
EXPECTED="$(awk 'NR==1 {print $1}' "$CHECKSUM")"
ACTUAL="$(sha256sum -- "$ARCHIVE" | cut -d ' ' -f1)"
if [[ ! "$EXPECTED" =~ ^[a-f0-9]{64}$ || "$ACTUAL" != "$EXPECTED" ]]; then
  echo 'BLOCKED: encrypted archive SHA-256 integrity verification failed'
  exit 2
fi
echo 'ENCRYPTED_ARCHIVE_SHA256=PASS'

# A missing schema must never be treated as an existing empty disposable schema.
PRESENT="$("$MYSQL" --defaults-extra-file="$CNF" --batch --skip-column-names \
  -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DB';")"
if [[ "$PRESENT" != "1" ]]; then
  echo 'BLOCKED: the disposable destination schema must already exist'
  exit 2
fi

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
REQUIRED="$("$MYSQL" --defaults-extra-file="$CNF" --batch --skip-column-names \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB' AND table_name IN ('users','institutions','campuses','student_result_statements','internal_inbox_messages');")"
if [[ "$REQUIRED" != "5" ]]; then
  echo 'RESTORE_VERIFY=FAILED: expected Edvora identity and academic workflow tables missing'
  exit 2
fi
echo "RESTORE_SCHEMA_TABLES=$TABLES"
echo 'RESTORE_REQUIRED_TABLES=PASS'
echo 'ENCRYPTED_STAGING_RESTORE=PASS'
echo 'APPLICATION_LEVEL_RESTORE_ACCEPTANCE=PENDING'

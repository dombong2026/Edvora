#!/usr/bin/env bash
# Operator-invoked, isolated staging ONLY. Creates AGE-encrypted SQL backup.
set -Eeuo pipefail
set +x
umask 077

DB="${1:-}"
CNF="${2:-}"
DEST="${3:-}"
RECIPIENT="${AGE_RECIPIENT:-}"
MYSQLDUMP="${MYSQLDUMP_BIN:-mysqldump}"

if [[ ! "$DB" =~ ^edvora_(qa|staging)_[a-z0-9_]{3,40}$ ]]; then
  echo 'BLOCKED: source schema must be an explicitly named Edvora QA/staging schema'
  exit 2
fi
if [[ -z "$RECIPIENT" || ! "$RECIPIENT" =~ ^age1[a-z0-9]+$ ]]; then
  echo 'BLOCKED: supply a valid public AGE_RECIPIENT (never a private key)'
  exit 2
fi
if [[ -L "$CNF" || ! -f "$CNF" || "$(stat -c '%a' -- "$CNF")" != 600 ]]; then
  echo 'BLOCKED: a non-symlink mode-600 MySQL client defaults file is required'
  exit 2
fi
if [[ -L "$DEST" || ! -d "$DEST" || "$DEST" != /var/backups/edvora-staging ]]; then
  echo 'BLOCKED: private precreated /var/backups/edvora-staging directory required'
  exit 2
fi
if [[ "$(stat -c '%a' -- "$DEST")" != 700 ]]; then
  echo 'BLOCKED: backup destination directory must have mode 700'
  exit 2
fi
command -v age >/dev/null 2>&1 || { echo 'BLOCKED: age encryption unavailable'; exit 2; }
command -v "$MYSQLDUMP" >/dev/null 2>&1 || { echo 'BLOCKED: mysqldump unavailable'; exit 2; }

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
ARCHIVE="$DEST/$DB-$STAMP.sql.age"
TEMP="$(mktemp "$DEST/.edvora-backup.XXXXXXXX")"
trap 'rm -f -- "$TEMP"' EXIT

"$MYSQLDUMP" --defaults-extra-file="$CNF" \
  --single-transaction --routines --triggers --hex-blob \
  --set-gtid-purged=OFF --no-tablespaces "$DB" \
  | age -r "$RECIPIENT" -o "$TEMP"

test -s "$TEMP"
mv -- "$TEMP" "$ARCHIVE"
sha256sum -- "$ARCHIVE" > "$ARCHIVE.sha256"
chmod 600 "$ARCHIVE" "$ARCHIVE.sha256"
echo "ENCRYPTED_STAGING_BACKUP=PASS"
echo "ARCHIVE=$ARCHIVE"
echo 'RESTORE_NOT_VERIFIED=YES (run separate disposable restore drill)'

#!/usr/bin/env bash
# NON-MUTATING staging preflight. Does not install, migrate or deploy.
set -Eeuo pipefail
set +x
umask 077

APP="${1:-}"
PHP="${2:-}"
if [[ -z "$APP" || -z "$PHP" ]]; then
  echo 'USAGE: bash support/qa/aapanel-staging-readonly-audit.sh /www/wwwroot/<edvora-staging-or-qa-dir> /absolute/php'
  exit 2
fi
case "$APP" in
  /www/wwwroot/*) ;;
  *) echo 'BLOCKED: explicit /www/wwwroot staging directory required'; exit 2 ;;
esac
BASE="$(basename -- "$APP")"
if [[ "$BASE" != *edvora* || ( "$BASE" != *staging* && "$BASE" != *qa* ) ]]; then
  echo 'BLOCKED: staging/qa-specific Edvora directory required'
  exit 2
fi
if [[ -L "$APP" || ! -d "$APP" || ! -x "$PHP" ]]; then
  echo 'BLOCKED: directory or PHP missing, invalid, or symlinked'
  exit 2
fi
CANON="$(realpath -e -- "$APP")"
if [[ "$CANON" != "$APP" ]]; then
  echo 'BLOCKED: staging directory must be a canonical path'
  exit 2
fi
if [[ ! -f "$APP/artisan" || ! -f "$APP/public/index.php" || ! -s "$APP/composer.lock" || ! -s "$APP/package-lock.json" ]]; then
  echo 'BLOCKED: install or lockfiles incomplete'
  exit 2
fi
if [[ -L "$APP/.env" || -L "$APP/public" || -e "$APP/public/.env" || ! -f "$APP/.env" ]]; then
  echo 'BLOCKED: unsafe environment or missing private .env'
  exit 2
fi
if ! grep -Eq '^APP_ENV=(staging|testing)$' "$APP/.env" ||
   ! grep -Eq '^APP_DEBUG=false$' "$APP/.env"; then
  echo 'BLOCKED: environment must be staging/testing with debug disabled'
  exit 2
fi
if ! grep -Eq '^APP_URL=https://[^[:space:]]*edvora[^[:space:]]*(staging|qa)|^APP_URL=https://[^[:space:]]*(staging|qa)[^[:space:]]*edvora' "$APP/.env"; then
  echo 'BLOCKED: APP_URL must be a staging/QA HTTPS hostname'
  exit 2
fi

"$PHP" -r '
if (PHP_VERSION_ID < 80300) { fwrite(STDERR, "BLOCKED: PHP version too old\n"); exit(2); }
foreach (["pdo_mysql", "openssl", "mbstring", "fileinfo", "dom", "xml"] as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "BLOCKED: missing PHP extension\n"); exit(2);
    }
}
echo "PHP_STAGING_CAPABILITIES=PASS\n";
'
if command -v nginx >/dev/null 2>&1; then
  nginx -t >/dev/null 2>&1 || { echo 'BLOCKED: nginx syntax failed'; exit 2; }
  echo 'NGINX_SYNTAX=PASS'
else
  echo 'NGINX_SYNTAX=NOT_CHECKED (operator must verify configured binary)'
fi
if git -C "$APP" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  printf 'SOURCE_COMMIT=%s\n' "$(git -C "$APP" rev-parse HEAD)"
else
  echo 'SOURCE_COMMIT=UNAVAILABLE (verify approved release package)'
fi
printf 'APP_OWNER=%s\n' "$(stat -c '%U:%G' -- "$APP")"
echo 'EDVORA_STAGING_READONLY_PREFLIGHT=PASS'
echo 'NO_PRODUCTION_DEPLOYMENT=YES'

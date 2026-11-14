#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."

test -f artisan
test -f composer.json
test -f .env.example
test -f LICENSE
test -f docs/operations/ACCEPTANCE.md
test -f database/migrations/2026_11_14_000001_create_institution_access_tables.php

if command -v git >/dev/null 2>&1 && git ls-files --error-unmatch .env >/dev/null 2>&1; then
  echo "PREFLIGHT_BLOCKED: .env is tracked in Git"
  exit 2
fi
if test -e public/.env; then
  echo "PREFLIGHT_BLOCKED: public/.env must not exist"
  exit 2
fi

php -r '
if (PHP_VERSION_ID < 80300) {
    fwrite(STDERR, "PREFLIGHT_BLOCKED: PHP >= 8.3 required\n");
    exit(2);
}
foreach (["ctype", "fileinfo", "filter", "hash", "mbstring", "openssl", "pdo", "tokenizer", "xml", "dom"] as $ext) {
    if (!extension_loaded($ext)) {
        fwrite(STDERR, "PREFLIGHT_BLOCKED: missing extension " . $ext . "\n");
        exit(2);
    }
}
echo "PHP_RUNTIME_CHECK=PASS\n";
'
echo "EDVORA_READONLY_PREFLIGHT=PASS"

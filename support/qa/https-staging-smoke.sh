#!/usr/bin/env bash
# Explicit HTTPS-only QA probe: headers and login route, no authentication.
set -Eeuo pipefail
set +x
umask 077
URL="${1:-}"
if [[ ! "$URL" =~ ^https://[a-zA-Z0-9.-]+$ ||
      ( "$URL" != *edvora* || ( "$URL" != *staging* && "$URL" != *qa* ) ) ]]; then
  echo 'BLOCKED: explicit https://<edvora-staging-qa-host> is required'
  exit 2
fi
command -v curl >/dev/null 2>&1 || { echo 'BLOCKED: curl unavailable'; exit 2; }
HEADER="$(mktemp)"
trap 'rm -f -- "$HEADER"' EXIT
CODE="$(curl --proto '=https' --tlsv1.2 --max-redirs 0 \
  --connect-timeout 8 --max-time 20 --silent --show-error \
  --output /dev/null --dump-header "$HEADER" --write-out '%{http_code}' "$URL/login")"
if [[ "$CODE" != "200" ]]; then
  echo "BLOCKED: /login returned HTTP $CODE (expected 200)"
  exit 2
fi
if ! grep -qi '^x-content-type-options: nosniff' "$HEADER" ||
   ! grep -qi '^x-frame-options: DENY' "$HEADER" ||
   ! grep -qi '^referrer-policy: no-referrer' "$HEADER"; then
  echo 'BLOCKED: required security headers absent'
  exit 2
fi
echo 'STAGING_HTTPS_LOGIN=PASS'
echo 'STAGING_SECURITY_HEADERS=PASS'
echo 'NO_AUTHORIZED_MUTATIONS=YES'

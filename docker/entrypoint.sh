#!/bin/sh
set -eu

DB_HOST="${DB_HOST:-db}"
DB_USER="${DB_USER:-sa_user}"
DB_PASS="${DB_PASS:-sa_password}"
DB_NAME="${DB_NAME:-crevice_db}"

echo "[entrypoint] Waiting for DB at ${DB_HOST} ..."

i=0
while :; do
  if mysql --protocol=TCP --ssl=0 -h "${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" -D "${DB_NAME}" -e "SELECT 1" >/dev/null 2>&1; then
    echo "[entrypoint] DB auth OK"
    break
  fi

  i=$((i+1))
  if [ "$i" -eq 1 ] || [ $((i % 10)) -eq 0 ]; then
    echo "[entrypoint] Still waiting for DB auth (${i}s)..."
    mysql --protocol=TCP --ssl=0 -h "${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" -D "${DB_NAME}" -e "SELECT 1" 2>&1 || true
  fi

  if [ "$i" -ge 60 ]; then
    echo "[entrypoint] ERROR: Timed out waiting for DB auth"
    exit 1
  fi
  sleep 1
done

echo "[entrypoint] Starting Apache"
exec apache2-foreground
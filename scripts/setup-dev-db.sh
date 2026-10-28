#!/usr/bin/env bash
# Creates the local PostgreSQL role and databases from the values in .env (needs sudo).
set -euo pipefail
cd "$(dirname "$0")/.."
user=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2-)
pass=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2-)
db=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2-)

sudo -u postgres psql -v ON_ERROR_STOP=1 -v u="$user" -v p="$pass" -v db="$db" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'u', :'p')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'u') \gexec
SELECT format('ALTER ROLE %I PASSWORD %L', :'u', :'p') \gexec
SELECT format('CREATE DATABASE %I OWNER %I', :'db', :'u')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'db') \gexec
SELECT format('CREATE DATABASE %I OWNER %I', :'db' || '_test', :'u')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'db' || '_test') \gexec
SQL
echo "Done: role '$user', databases '$db' and '${db}_test'."

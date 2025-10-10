#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${DATABASE_URL:-}" ]]; then
  echo "DATABASE_URL must be set" >&2
  exit 1
fi

if ! command -v migrate >/dev/null 2>&1; then
  echo "'migrate' CLI not found. Install from https://github.com/golang-migrate/migrate/releases" >&2
  exit 1
fi

MIGRATIONS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")"/../../db/migrations && pwd)"

migrate -database "$DATABASE_URL" -path "$MIGRATIONS_DIR" "$@"

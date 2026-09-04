#!/usr/bin/env bash
#
# TRAC JHS SARMS — local dev bootstrap, Unix/macOS entry point.
#
# Forwards to the cross-platform PHP core in tools/dev-up.php so
# there is exactly one source of truth.
#
# Usage:
#   bash tools/dev-up.sh
#   # or, after chmod +x:
#   ./tools/dev-up.sh
#
# Requires PHP and PostgreSQL client tools (initdb, pg_ctl, psql,
# pg_isready) on PATH. See tools/dev-up.php for the full check.

set -euo pipefail

# Always run from the project root so relative paths in dev-up.php
# (./.pgdata, ./.pglogs, ./database/schema.sql) resolve correctly.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

exec php tools/dev-up.php

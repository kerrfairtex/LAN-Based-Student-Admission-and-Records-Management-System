# AGENTS.md

Operational notes for **deployers, maintainers, and AI agents** working
on TRAC JHS SARMS. The user-facing Quick Start is in `README.md`; this
file is the source of truth for environment-specific behavior.

## Repository conventions

- **Single source of truth for credentials:** README.md "Seed accounts"
  table. Both seed passwords are committed to `database/schema.sql` (bcrypt
  hashes verified). Do not change one without updating the other.
- **Single source of truth for table count:** README.md "Database schema"
  section (14 tables). If you add a table, update both this file's
  inventory and the README.
- **Embedded Postgres port:** 5433. Render Postgres port: 5432. Supabase
  pooler port: 6543. Never mix these up.
- **Public landing design source of truth:** `templates/landing.html`.
  Edit the template; do not duplicate the markup into `index.php`.

## Cursor Cloud / Termux / Linux dev setup

PHP 8.1+ and PostgreSQL client tools (`pg_ctl`, `initdb`, `psql`,
`pg_isready`) are required. No Composer, no npm.

```bash
bash tools/dev-up.sh
```

`tools/dev-up.sh` → `tools/dev-up.php` is the cross-platform single source
of truth for the local bootstrap. It:
- creates `.pgdata/` (gitignored) if missing,
- starts an embedded Postgres on 5433,
- imports `database/schema.sql` via `psql -f` (Postgres handles PL/pgSQL
  function bodies correctly — the previous PHP-side statement splitter
  was buggy on `$$ ... $$`),
- exports `DB_*` env to the dev server child process,
- exec's `php -S 0.0.0.0:8000` so the LAN can reach it.

`tools/dev-up.cmd` is the Windows shim that calls the same PHP file.

### Why port 5433 (not 6543)

The embedded cluster is started with `pg_ctl -o "-p 5433"`, which
overrides the `port = 6543` that `initdb` writes into the generated
`postgresql.conf`. Port 6543 is the Supabase pooler port and is
documented in the README/AGENTS historic context, but **local dev
always uses 5433**. Render production uses 5432.

### `dev-up.php` does NOT assume port 5433 is "ours"

If a foreign Postgres is already answering on 5433 when you run
`dev-up.php`, it will detect that and print a warning, then either
proceed (assuming it's a leftover from a prior session) or fail
loudly. If you have system Postgres on 5433 and don't want collisions,
edit `DB_PORT` in `tools/dev-up.php` to e.g. 5434.

## Database

- **Driver:** PostgreSQL (PHP DSN uses `pgsql:`). Connection is
  centralized in `config/database.php` and reads `DB_HOST` / `DB_PORT` /
  `DB_NAME` / `DB_USER` / `DB_PASS` / `DB_SCHEMA` / `DB_SSLMODE` from env.
- **Schema + seed data:** `database/schema.sql`. Tables live under
  `trac_jhs_sarms.<table>`; the connection sets `search_path` so
  unqualified names resolve there.
- **Migrations:** `database/migrations/` is upgrade-only.
  `002_phase2.sql` and `003_lis_csv.sql` are MySQL-flavored (do not run
  on fresh Postgres). `004_audit_retention.sql` onward are Postgres.

## Render production

- **Service:** `trac-jhs-sarms` (render.yaml provisions a Docker web
  service with `php:8.3-apache` + persistent disk at
  `/var/www/html/storage`).
- **Database:** managed Postgres on Render (`DB_PORT=5432`,
  `DB_SSLMODE=require`).
- **Healthcheck:** `/healthcheck.php` — returns 200 + JSON body with
  `db: reachable | unreachable`.
- **Deploys:** `render.yaml` sets `autoDeploy: false`; deployments are
  triggered via Render dashboard or GitHub Actions.

## Database maintenance

- **Audit retention** — `trac_jhs_sarms.purge_old_audit_logs()` deletes
  `audit_logs` rows older than `app_settings.audit_retention_days`
  (default 1825 = 5 years). Invoke manually from `psql`, via cron, or
  wire it into a Render scheduled job. Each run logs a NOTICE with the
  deleted count. To override the window per institution:
  `UPDATE app_settings SET setting_value = '3650' WHERE setting_key =
  'audit_retention_days';`

## Security

- `.user.ini` declares `display_errors = 0` but is **only honored on
  CGI/FastCGI/FPM PHP** (not Apache mod_php, which Render uses). In
  production, `display_errors` must also be turned off via the PHP
  config or the Apache/PHP-FPM ini. See audit §3.3 for the current
  live exposure of PHP warnings in HTML.
- Committed seed passwords (`Registrar@2026`, `Encoder@2026`) are
  public in this repo. Rotate on every fresh install via
  **Account → Change Password**.
- See `.htaccess` for path-deny and security-header rules. The
  PHP-built-in dev server ignores `.htaccess`; live's Apache enforces
  them.

## Known live issues (carryover from 2026-09-05 audit)

- `modules/admin/backup.php` exposes PHP warnings to HTML on live
  (scandir on `/backups/` returns permission denied). Local dev with
  `php -S` does NOT enforce `.htaccess`, so the equivalent local path
  may also leak. Fix in `config/app.php` defensively.
- `modules/admin/download_backup.php` returns 404 on live even though
  the file is committed. Deployed-commit vs. repo-commit drift;
  investigate Render's last build log before pushing a fix.

## Lint / test

- No automated test suite. Lint PHP with `php -l <file>`.
- All committed PHP files pass `php -l` as of 2026-09-05.
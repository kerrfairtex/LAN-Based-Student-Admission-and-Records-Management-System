# AGENTS.md

## Cursor Cloud specific instructions

TRAC JHS SARMS is a single PHP 8 + PostgreSQL app (no Composer/npm/build step). It is served
directly from the repo root. Standard setup/run steps live in `README.md`; the notes below cover
only the non-obvious, cloud-VM specific details.

### Services

| Service | Required | Start command | Notes |
|---------|----------|---------------|-------|
| PostgreSQL | Yes | `bash tools/dev-up.sh` (recommended) — or `sudo service postgresql start` (or use the in-repo `.pgdata` embedded cluster — see Gotchas) | Not started on boot; start it each session. The recommended path is `tools/dev-up.sh`, which boots an embedded Postgres on 5433, imports `database/schema.sql`, and execs `php -S 0.0.0.0:8000`. |
| PHP dev server | Yes | `php -S 0.0.0.0:8000` (run from repo root) | App at http://localhost:8000/ . Use instead of Apache/XAMPP. `tools/dev-up.sh` starts this for you. |

PHP 8.3 (`pdo_pgsql`, `mbstring`, `xml`) and PostgreSQL are provided by the VM snapshot — do
not reinstall them. There is **no MariaDB** in this project despite what older revisions of
this file claimed; the actual stack is Postgres + Supabase/Render.

### Database

- Driver: PostgreSQL (PHP DSN uses `pgsql:`). Connection is centralized in `config/database.php`
  and reads `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS` / `DB_SCHEMA` / `DB_SSLMODE`
  from env, with these defaults baked in:
  - `DB_HOST=localhost`, `DB_PORT=6543`, `DB_NAME=postgres`, `DB_USER=postgres`, `DB_PASS=`
    (empty), `DB_SCHEMA=trac_jhs_sarms`
  - `DB_SSLMODE` auto-resolves to `disable` for local connections and `require` for non-local
    (Supabase / Render managed Postgres).
- Schema + seed data live in `database/schema.sql`. Tables are namespaced under
  `trac_jhs_sarms.<table>`; the connection sets `search_path` so unqualified names resolve there.
- For local dev, `bash tools/dev-up.sh` handles initdb + start + import + dev server in one
  command. It binds the embedded cluster to port **5433** (not the 6543 default) and exports
  `DB_PORT=5433` so the DSN matches. Manual import: `psql -h 127.0.0.1 -p 5433 -U postgres
  -d postgres -f database/schema.sql` (or use the existing `tools/import_schema.php`).
- The files under `database/migrations/` are for upgrading older deployments. Note: `002_phase2.sql`
  and `003_lis_csv.sql` were authored against MySQL (`ENGINE=InnoDB`, `TIMESTAMP`, `USE`) and are
  **stale** relative to the current PostgreSQL schema. Don't run them on a fresh Postgres database
  — they will error. Only apply them to legacy MySQL installs that predate the pgsql migration.
  New migrations (`004_audit_retention.sql` onward) are written in PostgreSQL syntax.
- `render.yaml` provisions the production deploy target (https://trac-jhs-sarms.onrender.com)
  with `DB_PORT=5432` and `DB_SSLMODE=require` against a Render-managed Postgres instance; the
  schema is created in `trac_jhs_sarms` and `search_path` is set accordingly.
- Default app logins: bcrypt-hashed seed accounts whose plaintext credentials are
  not committed to this repository. The first operator to deploy must rotate
  every seed account through **Account → Change Password** immediately after
  first sign-in.

### Database maintenance

- **Audit retention** — `trac_jhs_sarms.purge_old_audit_logs()` deletes `audit_logs` rows older
  than `app_settings.audit_retention_days` (default 1825 = 5 years). Invoke manually from `psql`,
  via cron, or wire it into a Render scheduled job. Each run logs a NOTICE with the deleted count.
  To override the window per institution: `UPDATE app_settings SET setting_value = '3650' WHERE
  setting_key = 'audit_retention_days';`

### Lint / test

- No automated test suite exists. Lint PHP with `php -l <file>` (e.g. loop over `*.php`).
- All committed PHP files currently pass `php -l`. Earlier revisions flagged
  `modules/admin/settings.php` as broken; that was stale and the file is clean now.

### Gotchas

- The PHP built-in server does **not** enforce `.htaccess`, so the deny rules for `config/`,
  `includes/`, `database/`, and `backups/` are inactive in local dev. Fine for development; do not
  rely on them locally.
- The repo root contains `.pgdata/`, `.pglogs/`, `.pgrun/` — these are an embedded Postgres
  dev cluster. If `database/schema.sql` hasn't been imported there yet, run
  `sudo -u postgres psql < database/schema.sql` (or against whatever Postgres `DB_HOST` points to).
- `DB_PORT=6543` is the **Supabase pooler** port. Direct Postgres connections (Render, local
  `postgresql` service) use 5432. `render.yaml` already sets 5432 for production.
- The branch `audit-fixes-enrollment-transfers` (PR #12) is open and contains input-validation
  fixes for `modules/enrollment/assign.php` and `modules/transfers/{create,view}.php`, plus the
  audit-log filters in `modules/admin/audit.php` and the new `004_audit_retention.sql` migration.
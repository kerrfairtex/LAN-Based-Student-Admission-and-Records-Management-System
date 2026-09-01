# Audit-log retention — invocation

The retention function lives at `trac_jhs_sarms.purge_old_audit_logs()` and is created by
`database/migrations/004_audit_retention.sql`. It reads the window from `app_settings.audit_retention_days`
(default 1825 = 5 years) and DELETEs `audit_logs` rows older than the cutoff.

## One-shot manual run

```sql
-- Apply the migration (one-time, on first deploy):
\i database/migrations/004_audit_retention.sql

-- Run the purge:
SELECT trac_jhs_sarms.purge_old_audit_logs();

-- Returns the number of deleted rows. Also emits a NOTICE in the Postgres
-- log with the cutoff timestamp and deleted count, e.g.:
--   audit retention: deleted 412 rows older than 1825 days (cutoff=2021-08-15 ...)
```

## Tuning the retention window

```sql
-- 10-year retention:
UPDATE trac_jhs_sarms.app_settings
   SET setting_value = '3650'
 WHERE setting_key = 'audit_retention_days';

-- 1-year retention (DPA-minimal):
UPDATE trac_jhs_sarms.app_settings
   SET setting_value = '365'
 WHERE setting_key = 'audit_retention_days';

-- Inspect current setting:
SELECT setting_key, setting_value, updated_at
  FROM trac_jhs_sarms.app_settings
 WHERE setting_key = 'audit_retention_days';
```

## Scheduled invocation

### Render (managed Postgres) — Cron Job

Render Cron Jobs are configured per-service in the dashboard (no API for cron creation). Recommended:

| Field | Value |
|-------|-------|
| Name | `audit-retention-monthly` |
| Schedule | `0 3 1 * *` (03:00 UTC on the 1st of every month) |
| Command | `psql "$DATABASE_URL" -c "SELECT trac_jhs_sarms.purge_old_audit_logs();"` |
| Plan | Starter (free tier is fine for a monthly job) |

Set `DATABASE_URL` as a secret in the Cron Job's environment, or use the same env-var pattern
(`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE`) that the app uses.

### Linux server — crontab

```cron
# /etc/cron.d/trac-jhs-sarms-audit-retention
0 3 1 * *  www-data  psql "host=$DB_HOST port=$DB_PORT dbname=$DB_NAME user=$DB_USER sslmode=$DB_SSLMODE" \
  -v ON_ERROR_STOP=1 \
  -c "SELECT trac_jhs_sarms.purge_old_audit_logs();" \
  >> /var/log/trac-jhs-sarms/retention.log 2>&1
```

### In-app button

`modules/admin/audit.php` (registrar role) has a **Run Retention Now** button below the filter
form. It POSTs to itself, calls the function in-process, flashes the deleted count, and writes
its own `audit_log` entry (so the manual run is itself auditable). Useful for one-off cleanup or
testing.

## Verifying the function

```sql
-- Confirm it exists:
SELECT proname, prorettype::regtype
  FROM pg_proc
 WHERE pronamespace = 'trac_jhs_sarms'::regnamespace
   AND proname = 'purge_old_audit_logs';

-- Dry-run (no commit) by wrapping in a transaction you ROLLBACK:
BEGIN;
SELECT trac_jhs_sarms.purge_old_audit_logs();
ROLLBACK;
```

## Why a function instead of plain DELETE

- Window is configurable at runtime via `app_settings`, no SQL edit needed per institution.
- Encapsulates the WHERE clause in one place — drift between the running cron and the
  application code is eliminated.
- Returns the deleted count, so the caller (cron, app, ops) gets a structured result
  for logging / alerting.
- Pl/pgSQL makes future enhancements (e.g. archive-before-delete) a one-spot edit.
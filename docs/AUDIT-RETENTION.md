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

## Related: full-database backup strategy

The in-app `Database Backup` button (`modules/admin/backup.php`) produces a **logical dump**
that is portable and re-importable across environments. It is intentionally simple (TRUNCATE +
INSERT statements, no DDL) so it can be executed by the in-app restore flow without elevated
privileges. For institutional-grade backups where byte-for-byte fidelity matters (e.g. timestamps
with sub-second precision, bytea, custom domains), use `pg_dump` from the shell instead.

### When to use which

| Use case | Use in-app button | Use `pg_dump` |
|----------|-------------------|---------------|
| Quick on-site before risky change | yes | overkill |
| Routine registrar backup | yes | no |
| Sharing a sanitized copy for testing | yes (logical, easy to inspect) | no |
| Disaster-recovery full restore | possible but slower | preferred |
| Off-site / S3 archival | yes (smaller) | yes (binary is faster to restore) |
| Pre-upgrade snapshot | acceptable | strongly preferred |
| Compliance / audit trail | yes (human-readable) | yes |
| Restoring into a different schema / DB engine | no | requires pg_restore |

### `pg_dump` reference

```bash
# Plain-text logical dump (readable, re-importable via psql):
pg_dump "$DATABASE_URL" \
    --schema=trac_jhs_sarms \
    --no-owner \
    --no-privileges \
    --file=trac_jhs_sarms-$(date +%Y%m%d).sql

# Custom binary archive (smaller, faster restore, parallel-friendly):
pg_dump "$DATABASE_URL" \
    --schema=trac_jhs_sarms \
    --format=custom \
    --file=trac_jhs_sarms-$(date +%Y%m%d).dump

# Schema-only dump (useful for tracking migrations vs the codebase):
pg_dump "$DATABASE_URL" \
    --schema=trac_jhs_sarms \
    --schema-only \
    --file=trac_jhs_sarms-schema.sql

# Restore a custom-format archive (handles ordering, FKs, everything):
pg_restore --clean --if-exists --dbname="$DATABASE_URL" \
    trac_jhs_sarms-20260901.dump
```

### Render — manual backup before deploy

Render's dashboard does not expose `pg_dump` directly. Two options:

1. **From the Render Shell** (Settings → Shell): `pg_dump "$DATABASE_URL" --schema=trac_jhs_sarms -f /tmp/backup.sql`
2. **From an external machine with psql installed**: connect using the same env vars (`DB_HOST`,
   `DB_PORT=5432`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_SSLMODE=require`) and run `pg_dump`
   with the `connection_string` built from those values.

For automated off-host backups, schedule a Render Cron Job (`psql` is available in the env) that
runs `pg_dump ... | gzip | aws s3 cp` or similar. The in-app button does not replace this; it
complements it.

### Why the in-app dump uses `TRUNCATE ... CASCADE` + `INSERT VALUES`

- Schema is version-controlled (`database/schema.sql`); re-importing DDL from a dump creates
  drift between the schema file and the live DB.
- `TRUNCATE ... RESTART IDENTITY CASCADE` handles FK ordering automatically — no need for the
  MySQL-specific `SET FOREIGN_KEY_CHECKS=0` toggle.
- `INSERT VALUES` is portable and human-readable, so a registrar can spot a corruption in a
  backup file with a text editor if needed.

Trade-off: timestamps with sub-second precision and `bytea` columns may round-trip with reduced
fidelity. For those, use `pg_dump --format=custom` which preserves binary types exactly.
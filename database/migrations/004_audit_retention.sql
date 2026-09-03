-- Phase 4: Audit-log retention policy (Data Privacy Act compliance).
--
-- NOTE: original draft used `USE trac_jhs_sarms;` (MySQL syntax). That will
-- hard-error on Postgres. Replaced with an explicit `SET search_path` so the
-- migration runs against any Postgres instance regardless of session defaults.
SET search_path = trac_jhs_sarms, public;

-- Retention window in days. Default 1825 = 5 years. Adjust via:
--   UPDATE app_settings SET setting_value = '3650' WHERE setting_key = 'audit_retention_days';
INSERT INTO app_settings (setting_key, setting_value)
VALUES ('audit_retention_days', '1825')
ON CONFLICT (setting_key) DO NOTHING;

-- Purge function: deletes audit_logs older than the configured window.
-- Intended to be invoked periodically (cron, scheduled job, or manually).
-- Logs a NOTICE for each run so it shows up in Postgres logs.
CREATE OR REPLACE FUNCTION trac_jhs_sarms.purge_old_audit_logs()
RETURNS INTEGER
LANGUAGE plpgsql
AS $$
DECLARE
    retention_days INTEGER;
    cutoff_ts TIMESTAMPTZ;
    deleted_count INTEGER;
BEGIN
    BEGIN
        retention_days := (SELECT setting_value::INTEGER FROM trac_jhs_sarms.app_settings WHERE setting_key = 'audit_retention_days');
    EXCEPTION WHEN OTHERS THEN
        retention_days := 1825;
    END;

    IF retention_days IS NULL OR retention_days <= 0 THEN
        retention_days := 1825;
    END IF;

    cutoff_ts := NOW() - (retention_days || ' days')::INTERVAL;

    DELETE FROM trac_jhs_sarms.audit_logs WHERE created_at < cutoff_ts;
    GET DIAGNOSTICS deleted_count = ROW_COUNT;

    RAISE NOTICE 'audit retention: deleted % rows older than % days (cutoff=%)',
        deleted_count, retention_days, cutoff_ts;

    RETURN deleted_count;
END;
$$;
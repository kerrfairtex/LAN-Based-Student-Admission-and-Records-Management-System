-- Phase 6: Make audit_logs.user_id NULL-able so pre-auth events can be logged.
--
-- Audit findings from the post-deploy review showed login_failed events were
-- silently dropped because includes/functions.php's audit_log() had an early
-- `if (!isset($_SESSION['user'])) return;` guard — and login_failed happens
-- BEFORE any user is authenticated, by definition.
--
-- The fix on the PHP side removes the early return and binds NULL for pre-auth
-- events. For that to actually persist, audit_logs.user_id must allow NULL.
-- The FK to users(id) is preserved (ON DELETE SET NULL) so future deletes of
-- a user don't cascade-delete their audit trail — DPA-friendly.
--
-- Same retention behavior as the rest of audit_logs (see 004_audit_retention.sql
-- and the purge_old_audit_logs() function). NULL user_id rows are still eligible
-- for purge under the same retention window.

SET search_path = trac_jhs_sarms, public;

-- Drop the existing NOT NULL + FK, then re-add with ON DELETE SET NULL.
ALTER TABLE audit_logs
    DROP CONSTRAINT IF EXISTS fk_audit_user;

ALTER TABLE audit_logs
    ALTER COLUMN user_id DROP NOT NULL;

ALTER TABLE audit_logs
    ADD CONSTRAINT fk_audit_user
    FOREIGN KEY (user_id) REFERENCES trac_jhs_sarms.users(id)
    ON DELETE SET NULL;
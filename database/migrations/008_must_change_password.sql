-- Migration 008: force-password-change flag on users.
--
-- Additive and idempotent. Touches ONLY trac_jhs_sarms.users (the
-- production database is shared with other projects' schemas — never
-- rely on search_path). ADD COLUMN with a constant default is a
-- metadata-only change on Postgres 11+ (no table rewrite).
--
-- Set to 1 when a registrar creates a user or resets their password;
-- cleared when the user picks their own password. Seed accounts that
-- still use the public seed passwords are also forced to change at
-- sign-in by application logic (includes/auth.php), so no data UPDATE
-- is needed here.

ALTER TABLE trac_jhs_sarms.users
    ADD COLUMN IF NOT EXISTS must_change_password SMALLINT NOT NULL DEFAULT 0;

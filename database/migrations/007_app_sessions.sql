-- Migration 007: Add app_sessions table for PostgreSQL-backed sessions.
-- Required when deploying to Vercel (read-only filesystem).
-- On Render, the app can still use filesystem sessions if preferred.

CREATE TABLE IF NOT EXISTS trac_jhs_sarms.app_sessions (
    sess_id       VARCHAR(128) PRIMARY KEY,
    sess_data     BYTEA NOT NULL,
    sess_expire   TIMESTAMPTZ NOT NULL,
    sess_ip       INET,
    sess_ua       TEXT
);

-- Index for session garbage collection (expire-based cleanup)
CREATE INDEX IF NOT EXISTS idx_app_sessions_expire
    ON trac_jhs_sarms.app_sessions (sess_expire);

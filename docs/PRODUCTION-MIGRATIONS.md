# TRAC JHS SARMS — Migrations to run against production Supabase
# Project ref: rsuebvolfaqxtegulnud
# Schema:       trac_jhs_sarms (explicit; never rely on default search_path)
#
# CONNECTION DETAILS: get them from Render's dashboard for the
# trac-jhs-sarms service (Environment tab) — NOT from any local shell
# env vars. The local env was already shown to be unreliable for
# this project once tonight. The Render dashboard is the only
# authoritative source for DB_USER / DB_PASS / DB_HOST.
#
# Two connection options. Pick whichever resolves from your shell.
#
# OPTION A — Direct host (port 5432) — same target Render's runtime
#           uses per render.yaml: DB_PORT=5432, DB_SSLMODE=require.
#           DB_USER is typically the dedicated postgres role, no
#           project-ref suffix.
#
# OPTION B — Supabase pooler (port 6543) — transaction-mode. Required
#           username format on the pooler is postgres.<project-ref>
#           (this is normal Supabase behavior, not a discrepancy to
#           explain away). Fine for one-shot migrations; if you see
#           "SET search_path not allowed in this role" or transient
#           connection errors, fall back to Option A.
#
# In each psql invocation below:
#   - PGOPTIONS sets search_path BEFORE the connection so every
#     statement resolves trac_jhs_sarms.* without per-file SET.
#   - -v ON_ERROR_STOP=1 makes psql exit non-zero on the FIRST error
#     instead of continuing — critical for migrations, you do not
#     want half-applied state.
#   - Fill in <DB_USER> and <DB_PASSWORD> from Render's dashboard.
#     Do NOT pull these from any local shell env var — that source
#     has been confirmed unreliable for this project.

# =====================================================================
# STEP 1 — Create the public inquiry submissions table
# =====================================================================
# File: database/migrations/005_inquiries.sql
#
# Creates: inquiries(id, full_name, grade, contact_number, status,
#                    created_at, updated_at) + 2 indexes + grade CHECK
#                    constraint + updated_at trigger.
# Idempotent (uses CREATE TABLE/INDEX IF NOT EXISTS) — safe to re-run.
#
# OPTION A (direct):
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
  psql "host=db.rsuebvolfaqxtegulnud.supabase.co port=5432 dbname=postgres user=<DB_USER> sslmode=require" \
    -v ON_ERROR_STOP=1 \
    -f database/migrations/005_inquiries.sql

# OPTION B (pooler):
# PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
#   psql "host=aws-0-<region>.pooler.supabase.com port=6543 dbname=postgres user=<DB_USER> sslmode=require" \
#     -v ON_ERROR_STOP=1 \
#     -f database/migrations/005_inquiries.sql

# VERIFY (must show 8 columns + 2 indexes + 2 check constraints + trigger):
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
  psql "host=db.rsuebvolfaqxtegulnud.supabase.co port=5432 dbname=postgres user=<DB_USER> sslmode=require" \
    -c "\d trac_jhs_sarms.inquiries"

# VERIFY (must return 0 rows — table is new and empty):
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
  psql "host=db.rsuebvolfaqxtegulnud.supabase.co port=5432 dbname=postgres user=<DB_USER> sslmode=require" \
    -c "SELECT count(*) FROM trac_jhs_sarms.inquiries;"


# =====================================================================
# STEP 2 — Audit-log retention policy + purge function
# =====================================================================
# File: database/migrations/004_audit_retention.sql
#
# Inserts one row into app_settings (audit_retention_days=1825) and
# creates the trac_jhs_sarms.purge_old_audit_logs() function.
# The corrected version (commit 60d60b7) replaces MySQL's
# `USE trac_jhs_sarms;` with Postgres-native `SET search_path = ...`.
# Idempotent (INSERT uses ON CONFLICT DO NOTHING; CREATE OR REPLACE
# on the function).
#
# Note: if the app_settings row already exists with a different
# retention value (e.g., you changed it earlier), the INSERT will
# NOT overwrite — that's correct behavior. Check the verify query.
#
# OPTION A (direct):
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
  psql "host=db.rsuebvolfaqxtegulnud.supabase.co port=5432 dbname=postgres user=<DB_USER> sslmode=require" \
    -v ON_ERROR_STOP=1 \
    -f database/migrations/004_audit_retention.sql

# VERIFY (must show audit_retention_days=1825):
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
  psql "host=db.rsuebvolfaqxtegulnud.supabase.co port=5432 dbname=postgres user=<DB_USER> sslmode=require" \
    -c "SELECT setting_key, setting_value FROM trac_jhs_sarms.app_settings WHERE setting_key='audit_retention_days';"

# VERIFY (must run with no error; notice "deleted 0 rows" is fine):
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
  psql "host=db.rsuebvolfaqxtegulnud.supabase.co port=5432 dbname=postgres user=<DB_USER> sslmode=require" \
    -c "SELECT trac_jhs_sarms.purge_old_audit_logs();"


# =====================================================================
# STEP 3 — Make audit_logs.user_id nullable (required for #5 fix)
# =====================================================================
# File: database/migrations/006_audit_user_nullable.sql
#
# Drops NOT NULL on audit_logs.user_id, changes FK to
# ON DELETE SET NULL. This is a NEW migration that did not exist
# before this remediation — it was created because fixing
# login_failed logging required writing rows with user_id=NULL
# (failed logins happen BEFORE authentication, by definition).
#
# Idempotent: uses DROP CONSTRAINT IF EXISTS. Safe to re-run.
#
# CRITICAL: do NOT skip this. If you apply the code fix that
# writes login_failed rows without first applying this migration,
# production will start INSERT-failing on every failed login —
# turning a silent-success bug into a visible error.
#
# OPTION A (direct):
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
  psql "host=db.rsuebvolfaqxtegulnud.supabase.co port=5432 dbname=postgres user=<DB_USER> sslmode=require" \
    -v ON_ERROR_STOP=1 \
    -f database/migrations/006_audit_user_nullable.sql

# VERIFY (user_id column should now show Nullable=empty in the
# \d output below):
PGOPTIONS='-c search_path=trac_jhs_sarms,public' \
  psql "host=db.rsuebvolfaqxtegulnud.supabase.co port=5432 dbname=postgres user=<DB_USER> sslmode=require" \
    -c "\d trac_jhs_sarms.audit_logs"

# VERIFY (FK should now be ON DELETE SET NULL — confirm in the
# Foreign-key constraints section of the same \d output):


# =====================================================================
# After all three migrations succeed, confirm:
# =====================================================================
# - The 10 code commits are still unpushed on local main
#   (git log --oneline origin/main..main should show 10 hashes).
# - Tell Hermes to push so Render can deploy.
#
# Do NOT trigger the Render deploy yourself yet — Render autoDeploy
# is false in render.yaml per AGENTS.md, so a code push will NOT
# auto-deploy. The deploy is triggered separately (dashboard button,
# Render API POST, or GitHub Action). Hermes has the Render service
# ID (srv-da47lsbl550s73b5phug) and can do that step once you
# confirm you're ready.
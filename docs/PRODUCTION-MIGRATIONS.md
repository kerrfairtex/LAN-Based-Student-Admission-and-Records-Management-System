# TRAC JHS SARMS — Migrations to run against production Supabase
# Project ref: ebyepweqwihdvjecrufk
# Schema:      trac_jhs_sarms (every statement is schema-qualified)
#
# STATUS (2026-11-21): ALL MIGRATIONS APPLIED to production.
#   005 was applied earlier; 004, 006 and 007 were applied on
#   2026-11-21 in one transaction. Production has 15 tables, matching
#   database/schema.sql. Fresh installs need none of this — schema.sql
#   already includes 004–007. Use this runbook only to re-check
#   production or to upgrade another existing database.
#
# !! SHARED DATABASE !!
#   This Supabase database also holds other projects' schemas
#   (public, trac_library, smartcampus, rosariosis, admission, ...).
#   `public` has its own audit_logs table. Never run DROP/TRUNCATE or
#   unqualified DDL here, and touch only trac_jhs_sarms.*.
#
# CONNECTION: same target Render uses (Render dashboard → trac-jhs-sarms
# → Environment is the authoritative source for the password).
#   host     aws-0-ap-northeast-1.pooler.supabase.com
#   port     5432  (session pooler — use this for migrations;
#                   6543 is the transaction pooler)
#   dbname   postgres
#   user     postgres.ebyepweqwihdvjecrufk
#   sslmode  require
#
# Set the password in the environment instead of the command line, so it
# stays out of shell history:
#   read -rs PGPASSWORD && export PGPASSWORD
CONN="host=aws-0-ap-northeast-1.pooler.supabase.com port=5432 dbname=postgres user=postgres.ebyepweqwihdvjecrufk sslmode=require"

# Flags used below:
#   -v ON_ERROR_STOP=1   stop on the first error
#   --single-transaction all-or-nothing; no half-applied state
#   lock_timeout=10s     give up rather than block other projects

# =====================================================================
# 1. CHECK (read-only) — run this first, always
# =====================================================================
psql "$CONN" -X -At -F ' | ' <<'SQL'
select 'tables', count(*) from information_schema.tables
  where table_schema='trac_jhs_sarms' and table_type='BASE TABLE';      -- expect 15
select 'inquiries (005)', to_regclass('trac_jhs_sarms.inquiries') is not null;
select 'purge fn (004)', count(*) from pg_proc p join pg_namespace n on n.oid=p.pronamespace
  where n.nspname='trac_jhs_sarms' and p.proname='purge_old_audit_logs'; -- expect 1
select 'retention (004)', setting_value from trac_jhs_sarms.app_settings
  where setting_key='audit_retention_days';                             -- expect 1825
select 'audit user_id nullable (006)', is_nullable from information_schema.columns
  where table_schema='trac_jhs_sarms' and table_name='audit_logs'
    and column_name='user_id';                                          -- expect YES
select 'audit FK on delete (006)', delete_rule from information_schema.referential_constraints
  where constraint_schema='trac_jhs_sarms' and constraint_name='fk_audit_user'; -- expect SET NULL
select 'app_sessions (007)', to_regclass('trac_jhs_sarms.app_sessions') is not null;
SQL

# =====================================================================
# 2. APPLY — only for whichever checks above failed
# =====================================================================
# All files are Postgres, idempotent and schema-qualified:
#   004_audit_retention.sql     purge_old_audit_logs() + audit_retention_days=1825
#                               (ON CONFLICT DO NOTHING keeps a custom value)
#   005_inquiries.sql           inquiries table, indexes, trigger.
#                               NOT idempotent: its CREATE TRIGGER fails if the
#                               table already exists — skip it when the check
#                               shows inquiries already exists.
#   006_audit_user_nullable.sql audit_logs.user_id NULL-able, FK ON DELETE SET NULL
#                               (briefly locks trac_jhs_sarms.audit_logs)
#   007_app_sessions.sql        app_sessions table for DB-backed PHP sessions
#
# Do NOT run 002/003 — they are MySQL-flavored.
#
# Example (production as of 2026-11-21 needed 004, 006, 007):
PGOPTIONS='-c lock_timeout=10s' \
  psql "$CONN" -X -v ON_ERROR_STOP=1 --single-transaction \
    -f database/migrations/004_audit_retention.sql \
    -f database/migrations/006_audit_user_nullable.sql \
    -f database/migrations/007_app_sessions.sql

# Then re-run the CHECK in step 1.
#
# Do NOT "verify" by calling purge_old_audit_logs() — it DELETES audit rows
# older than the retention window. Run it only as intended maintenance
# (see docs/AUDIT-RETENTION.md).
#
# Tip: rehearse on a throwaway local cluster first (initdb + pg_ctl on a
# spare port, load the previous schema.sql from git, apply the
# migrations), so you see failures before they happen on the shared DB.

# =====================================================================
# 3. DEPLOY
# =====================================================================
# Database changes take effect immediately; no deploy needed for them.
# Code changes: render.yaml sets autoDeploy: false, so pushing to main
# does not deploy. Trigger it from the Render dashboard (service
# srv-da47lsbl550s73b5phug), the Render API, or GitHub Actions. Then confirm:
curl -sS https://trac-jhs-sarms.onrender.com/healthcheck.php   # expect "db":"reachable"

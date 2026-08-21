#!/bin/bash
# TRAC JHS SARMS container entrypoint (Render + Supabase)
#
# 1. Set PGOPTIONS so PHP's pgsql connection uses the trac_jhs_sarms schema
#    on the shared Supabase project (avoids colliding with other apps).
# 2. Bind Apache to Render's injected $PORT (default 10000).
# 3. Remove the mpm_event / mpm_worker enabled symlinks that the base
#    php:apache image ships alongside mpm_prefork (runtime, deterministic —
#    build-time removal is defeated by layer caching).
# 4. Boot Apache.

set -e

PORT="${PORT:-10000}"

# Point PHP's pgsql connections to the correct schema on Supabase
export PGOPTIONS="-c search_path=${DB_SCHEMA:-trac_jhs_sarms},public"

# Point Apache at the runtime port
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf

# Silence the FQDN warning (cosmetic but keeps logs clean)
if ! grep -q '^ServerName' /etc/apache2/apache2.conf; then
    echo "ServerName localhost" >> /etc/apache2/apache2.conf
fi

# MPM cleanup: keep only mpm_prefork (required by mod_php)
rm -f /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load \
      /etc/apache2/mods-enabled/mpm_worker.conf

exec apache2-foreground
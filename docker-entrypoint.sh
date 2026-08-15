#!/bin/bash
# TRAC JHS SARMS container entrypoint
#
# 1. Bind Apache to Railway's injected $PORT (default 8080). Apache's stock
#    ports.conf listens on 80, which Railway's proxy cannot reach -> 502.
# 2. Remove the mpm_event / mpm_worker enabled symlinks that the base
#    php:apache image ships alongside mpm_prefork (runtime, deterministic —
#    build-time removal is defeated by layer caching).
# 3. Boot Apache.

set -e

PORT="${PORT:-8080}"

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

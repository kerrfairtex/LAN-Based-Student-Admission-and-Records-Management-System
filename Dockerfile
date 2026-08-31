# TRAC JHS SARMS — production container (Render + Supabase Postgres)
# PHP 8.3 + Apache with pdo_pgsql and .htaccess support (AllowOverride All)

FROM php:8.3-apache

# System deps + PostgreSQL PHP extensions
RUN apt-get update && apt-get install -y libpq-dev && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_pgsql pgsql

# Allow .htaccess (deny rules for config/ includes/ database/ backups/)
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/sites-available/*.conf 2>/dev/null || true

# App lives at the web root
WORKDIR /var/www/html
COPY . .

# Persistent disk is mounted at /var/www/html/storage on Render.
# Symlink the runtime write paths the PHP code uses (.sessions/, uploads/, backups/)
# into the persistent disk so they survive container restarts and redeploys.
# In local dev (no /var/www/html/storage mount), the symlinks fall back to the
# in-project directories, which already exist or are auto-created by the app.
RUN mkdir -p /var/www/html/storage && \
    for d in .sessions backups uploads; do \
      rm -rf /var/www/html/$d && \
      ln -s /var/www/html/storage/$d /var/www/html/$d; \
    done

# Disable PHP opcache to prevent stale compiled bytecode
RUN echo "opcache.enable=0" > /usr/local/etc/php/conf.d/opcache-disable.ini

# Entrypoint: sets search_path for Supabase PG, binds Apache to $PORT,
# and resolves the base image's dual-MPM (event+prefork) conflict at runtime.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
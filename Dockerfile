# TRAC JHS SARMS — production container
# PHP 8.3 + Apache with pdo_mysql, mysqli, and .htaccess support (AllowOverride All)

FROM php:8.3-apache

# PHP extensions required by the app.
# MPM handling is done with rm -f (deterministic) instead of a2dismod, because
# a2dismod can silently fail and '|| true' masks it — the official php:apache
# image ships with BOTH mpm_event and mpm_prefork symlinked in mods-enabled.
RUN docker-php-ext-install pdo_mysql mysqli \
    && rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork rewrite headers

# Allow .htaccess (deny rules for config/ includes/ database/ backups/)
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/sites-available/*.conf 2>/dev/null || true

# App lives at the web root
WORKDIR /var/www/html
COPY . .

# DocumentRoot /var/www/html already; index.php is the landing page
EXPOSE 80

# The base php:apache image ships with BOTH mpm_event and mpm_prefork
# symlinked. Build-time a2dismod/rm can be defeated by cached layers, so the
# deterministic fix runs at container start: remove event/worker symlinks,
# then boot Apache with prefork (required by mod_php).
CMD ["bash", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf && apache2-foreground"]

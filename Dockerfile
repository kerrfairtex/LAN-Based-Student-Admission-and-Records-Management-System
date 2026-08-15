# TRAC JHS SARMS — production container
# PHP 8.3 + Apache with pdo_mysql, mysqli, and .htaccess support (AllowOverride All)

FROM php:8.3-apache

# PHP extensions required by the app
RUN docker-php-ext-install pdo_mysql mysqli

# Allow .htaccess (deny rules for config/ includes/ database/ backups/)
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/sites-available/*.conf 2>/dev/null || true

# App lives at the web root
WORKDIR /var/www/html
COPY . .

# Entrypoint: binds Apache to Railway's $PORT and resolves the base image's
# dual-MPM (event+prefork) conflict at runtime. Must be executable.
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# DocumentRoot /var/www/html already; index.php is the landing page
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

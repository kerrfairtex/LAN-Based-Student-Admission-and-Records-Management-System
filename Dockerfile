# TRAC JHS SARMS — production container
# PHP 8.3 + Apache with pdo_mysql, mysqli, and .htaccess support (AllowOverride All)

FROM php:8.3-apache

# PHP extensions required by the app.
# NOTE: a2dismod/a2enmod are chained with ';' and explicit '|| true' so no
# && / || precedence ambiguity can silently skip a step.
RUN docker-php-ext-install pdo_mysql mysqli \
    && { a2dismod -f mpm_event 2>/dev/null || true; \
         a2dismod -f mpm_worker 2>/dev/null || true; \
         a2enmod mpm_prefork; \
         a2enmod rewrite headers; }

# Allow .htaccess (deny rules for config/ includes/ database/ backups/)
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/sites-available/*.conf 2>/dev/null || true

# App lives at the web root
WORKDIR /var/www/html
COPY . .

# DocumentRoot /var/www/html already; index.php is the landing page
EXPOSE 80

CMD ["apache2-foreground"]

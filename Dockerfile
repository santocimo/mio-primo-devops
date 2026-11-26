FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
COPY bootstrap.php ./
COPY config/ ./config/
COPY src/ ./src/
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

FROM php:8.2-apache

COPY docker/apache-businessregistry.conf /etc/apache2/conf-available/businessregistry.conf

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers setenvif \
    && a2enconf businessregistry

COPY --from=dependencies /app/vendor/ /var/www/html/vendor/
COPY --chown=www-data:www-data *.php /var/www/html/
COPY --chown=www-data:www-data api/ /var/www/html/api/
COPY --chown=www-data:www-data config/ /var/www/html/config/
COPY --chown=www-data:www-data inc/ /var/www/html/inc/
COPY --chown=www-data:www-data src/ /var/www/html/src/
COPY --chown=www-data:www-data comuni.csv comuni.json .htaccess /var/www/html/

RUN mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/logs
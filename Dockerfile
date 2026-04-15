FROM php:8.2-apache

# 1. Installa le estensioni per il database
RUN docker-php-ext-install pdo pdo_mysql

# 2. Abilita mod_rewrite per le API senza .php
RUN a2enmod rewrite && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 3. Copia i file nella cartella di Apache
COPY . /var/www/html/

# 4. Imposta i permessi corretti per i file
RUN chown -R www-data:www-data /var/www/html/
FROM php:8.2-apache

# 1. Installa le estensioni per il database
RUN docker-php-ext-install pdo pdo_mysql

# 2. Copia l'index.php (e gli altri file) nella cartella di Apache
# Questo è il passaggio che mancava!
COPY . /var/www/html/

# 3. Imposta i permessi corretti per i file
RUN chown -R www-data:www-data /var/www/html/
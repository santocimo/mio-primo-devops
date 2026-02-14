FROM php:8.2-apache
# Installa i driver per far parlare PHP con MySQL
RUN docker-php-ext-install pdo pdo_mysql
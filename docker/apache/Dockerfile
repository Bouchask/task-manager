FROM php:8.2-apache
RUN apt-get update && apt-get install -y libsqlite3-dev && docker-php-ext-install pdo pdo_sqlite
COPY app/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html && chmod -R 775 /var/www/html
EXPOSE 80

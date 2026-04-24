FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql
COPY app/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html && chmod -R 775 /var/www/html
EXPOSE 80

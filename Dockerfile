FROM php:8.2-apache-bookworm
RUN docker-php-ext-install pdo_mysql
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /var/www/html
COPY . .

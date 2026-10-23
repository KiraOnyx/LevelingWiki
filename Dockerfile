FROM php:8.2-apache-bookworm
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /var/www/html
COPY . .

RUN mkdir -p /var/www/storage/uploads && chown -R www-data:www-data /var/www/storage

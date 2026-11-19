FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers \
    && printf '<Directory /var/www/html/storage>\n    Require all denied\n</Directory>\n' > /etc/apache2/conf-available/storage-protection.conf \
    && a2enconf storage-protection

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/storage/evidence \
    && chown -R www-data:www-data /var/www/html/storage

EXPOSE 80


FROM php:7.4-apache

RUN apt-get update -y && apt-get install -y openssl git
RUN docker-php-ext-install pdo pdo_mysql
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN a2enmod rewrite

EXPOSE 80

WORKDIR /var/www/html

CMD [ "bash", "/var/www/html/startup.sh" ]
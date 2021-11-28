ARG PHP_VERSION
FROM php:$PHP_VERSION
RUN pecl install ast-1.0.16 && docker-php-ext-enable ast
RUN curl https://getcomposer.org/download/latest-2.x/composer.phar -o /usr/bin/composer.phar && chmod a+x /usr/bin/composer.phar
WORKDIR /phan
RUN apt-get update && apt-get install -y unzip parallel colordiff && apt-get clean

ADD composer.json composer.lock ./
RUN composer.phar install && composer.phar clear-cache

# This adds another 30MB to the installation size
# ADD internal/paratest internal/paratest
# RUN cd internal/paratest && ./install.sh

ADD . .

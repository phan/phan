ARG PHP_IMAGE
ARG PHP_VERSION
FROM php:$PHP_VERSION
RUN pecl install ast-1.1.3 && docker-php-ext-enable ast

# Suppress deprecation warnings on PHP 8.5+ due to symfony/string __wakeup()/__sleep()
# TODO: Remove this once Symfony 6.5+ adds PHP 8.5 compatibility
RUN php -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);' && \
    echo "error_reporting = E_ALL & ~E_DEPRECATED" > /usr/local/etc/php/conf.d/suppress-php85-deprecations.ini || true
RUN curl https://getcomposer.org/download/latest-2.x/composer.phar -o /usr/bin/composer.phar && chmod a+x /usr/bin/composer.phar
WORKDIR /phan
RUN apt-get update && apt-get install -y unzip parallel colordiff sqlite3 && apt-get clean

ADD composer.json composer.lock ./
ADD patches patches
RUN composer.phar install && composer.phar clear-cache

# This adds another 30MB to the installation size
# ADD internal/paratest internal/paratest
# RUN cd internal/paratest && ./install.sh

ADD . .

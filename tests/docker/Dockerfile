ARG PHP_IMAGE
ARG PHP_VERSION
FROM $PHP_IMAGE:$PHP_VERSION
RUN pecl install ast-1.1.3 && docker-php-ext-enable ast
# Use Composer 2.8.x for PHP 8.5 compatibility (symfony/process __serialize support)
RUN curl https://getcomposer.org/download/2.8.3/composer.phar -o /usr/bin/composer.phar && chmod a+x /usr/bin/composer.phar
WORKDIR /phan
RUN apt-get update && apt-get install -y unzip parallel colordiff sqlite3 && apt-get clean

ADD composer.json composer.lock ./
ADD patches patches
RUN composer.phar install && composer.phar clear-cache

ADD . .

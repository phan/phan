ARG PHP_IMAGE
ARG PHP_VERSION
FROM $PHP_IMAGE:$PHP_VERSION
RUN pecl install ast-1.1.3 && docker-php-ext-enable ast
RUN curl https://getcomposer.org/download/2.9.8/composer.phar -o /usr/bin/composer.phar && echo "59b2c50e10cafa0d8efc19ede9a326d782f096c674a26baf98cf042ce23de890  /usr/bin/composer.phar" | sha256sum -c && chmod a+x /usr/bin/composer.phar
WORKDIR /phan
RUN apt-get update && apt-get install -y unzip parallel colordiff sqlite3 && apt-get clean

ADD composer.json composer.lock ./
RUN composer.phar install && composer.phar clear-cache

ADD . .

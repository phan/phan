ARG PHP_IMAGE
ARG PHP_VERSION
FROM $PHP_IMAGE:$PHP_VERSION
RUN pecl install ast-1.1.3 && docker-php-ext-enable ast
RUN curl -fSL https://getcomposer.org/download/2.10.1/composer.phar -o /usr/bin/composer.phar && echo "345b9c6a98da5c30dcbd4b0d99fc8710bf0ae98a3898eea18f7b2ad9dec93f06  /usr/bin/composer.phar" | sha256sum -c && chmod a+x /usr/bin/composer.phar
WORKDIR /phan
RUN apt-get update && apt-get install -y unzip parallel colordiff sqlite3 && apt-get clean

ADD composer.json composer.lock ./
RUN composer.phar install && composer.phar clear-cache

ADD . .

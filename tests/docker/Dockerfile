ARG PHP_IMAGE
ARG PHP_VERSION
FROM $PHP_IMAGE:$PHP_VERSION
# unzip must be present before pie runs: pie unpacks the extension's release archive with it.
RUN apt-get update && apt-get install -y unzip parallel colordiff sqlite3 && apt-get clean
# Install php-ast with pie (https://github.com/php/pie), the PHP Foundation's replacement for `pecl install`.
# pie builds the tagged release against this image's PHP and enables it in the conf.d directory.
RUN curl -fSL https://github.com/php/pie/releases/download/1.5.3/pie.phar -o /usr/local/bin/pie && echo "34a1315073b6089d4cdf4cc5a8295ecc6d36cfde683ef785582f7da0d74f127b  /usr/local/bin/pie" | sha256sum -c && chmod a+x /usr/local/bin/pie
RUN pie install nikic/php-ast:1.1.3
RUN curl -fSL https://getcomposer.org/download/2.10.1/composer.phar -o /usr/bin/composer.phar && echo "345b9c6a98da5c30dcbd4b0d99fc8710bf0ae98a3898eea18f7b2ad9dec93f06  /usr/bin/composer.phar" | sha256sum -c && chmod a+x /usr/bin/composer.phar
WORKDIR /phan

ADD composer.json composer.lock ./
RUN composer.phar install && composer.phar clear-cache

ADD . .

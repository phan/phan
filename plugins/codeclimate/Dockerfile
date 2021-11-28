# Based on ablyler's https://github.com/ablyler/docker-php7ast/blob/master/Dockerfile, which is out of date.
# The original Dockerfile's license is below; the Dockerfile has been modified.
#
# The MIT License (MIT)
#
# Copyright (c) 2015 Andy Blyler
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.


FROM php:8.0.6-fpm-alpine3.13

WORKDIR /usr/src/app

RUN adduser -u 9000 -D app

ENV LAST_MODIFIED_DATE 2021-11-27

ENV PHP_AST_VERSION=1.0.16

RUN apk add --no-cache \
	php8 && \
	test -d /etc/php8/conf.d || ((test -e /etc/php8/conf.d && rm /etc/php8/conf.d) && mkdir /etc/php8/conf.d)	&& \
	apk add --no-cache \
	php8-bcmath \
	php8-ctype \
	php8-curl \
	php8-gd \
	php8-gettext \
	php8-iconv \
	php8-intl \
	php8-json \
	php8-ldap \
	php8-mbstring \
	php8-mysqlnd \
	php8-opcache \
	php8-openssl \
	php8-pdo_mysql \
	php8-pdo_pgsql \
	php8-pdo_sqlite \
	php8-pgsql \
	php8-phar \
	php8-session \
	php8-soap \
	php8-sockets \
	php8-sqlite3 \
	php8-tidy \
	php8-tokenizer \
	php8-xml \
	php8-xmlreader \
	php8-xsl \
	php8-zip \
	php8-zlib

RUN apk add git

RUN apk add --no-cache bash \
      autoconf \
      openssl \
      make \
      build-base \
      php8-dev \
	&&  git clone https://github.com/nikic/php-ast.git \
    && cd php-ast \
    && phpize8 \
    && ./configure \
    && make install \
    && echo 'extension=ast.so' >/usr/local/etc/php/php.ini \
    && cd .. && rm -rf php-ast \
    apk del bash \
      autoconf \
      openssl \
      make \
      build-base \
      php8-dev \
      wget

COPY composer.json composer.lock ./
RUN apk add --no-cache curl && \
    curl -sS https://getcomposer.org/installer | php && \
    ./composer.phar install --no-dev --optimize-autoloader && \
    rm composer.phar && \
    apk del curl

COPY .phan .phan
COPY src src

COPY plugins/codeclimate/ast.ini /etc/php8/conf.d/
COPY plugins/codeclimate/engine /usr/src/app/plugins/codeclimate/engine

USER app

CMD ["/usr/src/app/plugins/codeclimate/engine"]

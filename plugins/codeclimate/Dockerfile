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


FROM alpine:3.6

WORKDIR /usr/src/app

RUN adduser -u 9000 -D app

ENV LAST_MODIFIED_DATE 2017-11-05

ENV PHP_AST_VERSION=0.1.6

# Increment the last digit when updating codeclimate docker images.
ENV PHAN_ITERATION 0.10.2-dev-1

RUN apk add --no-cache \
	php7 && \
	test -d /etc/php7/conf.d || ((test -e /etc/php7/conf.d && rm /etc/php7/conf.d) && mkdir /etc/php7/conf.d)	&& \
	apk add --no-cache \
	php7-bcmath \
	php7-curl \
	php7-gd \
	php7-gettext \
	php7-iconv \
	php7-intl \
	php7-json \
	php7-ldap \
	php7-mbstring \
	php7-mcrypt \
	php7-mysqlnd \
	php7-opcache \
	php7-openssl \
	php7-pdo_mysql \
	php7-pdo_pgsql \
	php7-pdo_sqlite \
	php7-pgsql \
	php7-phar \
	php7-session \
	php7-soap \
	php7-sockets \
	php7-sqlite3 \
	php7-tidy \
	php7-tokenizer \
	php7-xml \
	php7-xmlreader \
	php7-xmlrpc \
	php7-xsl \
	php7-zip \
	php7-zlib

RUN apk add --no-cache bash \
      autoconf \
      openssl \
      make \
      build-base \
      php7-dev \
      wget && \
    wget -O php-ast.tar.gz https://github.com/nikic/php-ast/archive/v${PHP_AST_VERSION}.tar.gz && \
    tar -zxvf php-ast.tar.gz && \
    cd php-ast-${PHP_AST_VERSION} && \
    export CFLAGS=-O2 && \
    phpize7 && \
    ./configure --prefix=/usr --with-php-config=/usr/bin/php-config7 && \
    make -j3 && \
    make test && \
    make install && \
    cd .. && \
    rm -Rf php-ast-${PHP_AST_VERSION} php-ast.tar.gz && \
    apk del bash \
      autoconf \
      openssl \
      make \
      build-base \
      php7-dev \
      wget
COPY plugins/codeclimate/ast.ini /etc/php7/conf.d/
COPY plugins/codeclimate/engine /usr/src/app/plugins/codeclimate/engine

COPY composer.json composer.lock ./
RUN apk add --no-cache curl && \
    curl -sS https://getcomposer.org/installer | php && \
    ./composer.phar install --no-dev --optimize-autoloader && \
    rm composer.phar && \
    apk del curl

COPY .phan .phan
COPY src src

USER app

CMD ["/usr/src/app/plugins/codeclimate/engine"]

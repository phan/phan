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

ADD repositories /etc/lder/Dockerfileapk

RUN apk --update add bash \
	wget \
	curl \
	git \
	grep \
	php7 \
	php7-bcmath \
	php7-curl \
	php7-intl \
	php7-json \
	php7-gettext \
	php7-gd \
	php7-ldap \
	php7-mbstring \
	php7-mcrypt \
	php7-mysqlnd \
	php7-opcache \
	php7-openssl \
	php7-pgsql \
	php7-phar \
	php7-pdo_mysql \
	php7-pdo_pgsql \
	php7-pdo_sqlite \
	php7-session \
	php7-soap \
	php7-sockets \
	php7-sqlite3 \
	php7-tidy \
	php7-xsl \
	php7-xml \
	php7-xmlreader \
	php7-xmlrpc \
	php7-zip \
	php7-zlib

RUN apk --update add bash \
	autoconf \
	make \
	build-base \
	php7-dev && \
	wget -O v0.1.6.tar.gz https://github.com/nikic/php-ast/archive/v0.1.6.tar.gz && \
	tar -zxvf v0.1.6.tar.gz && \
	cd php-ast-0.1.6 && \
	export CFLAGS=-O2 && \
	phpize7 && \
	./configure --prefix=/usr --with-php-config=/usr/bin/php-config7 && \
	make -j3 && \
	make install && \
	cd .. && \
	apk del php7-dev \
	autoconf \
	make \
	build-base

RUN php --version

ADD ast.ini /etc/php7/conf.d

WORKDIR /usr/src/app

RUN git clone https://github.com/phan/phan.git .
COPY engine /usr/src/app/plugins/codeclimate/engine

RUN apk --update add php7-tokenizer

RUN curl -sS https://getcomposer.org/installer | php && \
    /usr/src/app/composer.phar install --no-dev --optimize-autoloader

RUN adduser -u 9000 -D app
USER app

CMD ["/usr/src/app/plugins/codeclimate/engine"]

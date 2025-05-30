ARG PHP_IMAGE
ARG PHP_VERSION
FROM php:$PHP_VERSION
RUN pecl install ast-1.1.2 && docker-php-ext-enable ast
RUN curl https://getcomposer.org/download/latest-2.x/composer.phar -o /usr/bin/composer.phar && chmod a+x /usr/bin/composer.phar
WORKDIR /phan
RUN apt-get update && apt-get install -y unzip parallel colordiff sqlite3 && apt-get clean

# intl
RUN apt-get install -y zlib1g-dev libicu-dev g++ \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

# ldap
RUN apt-get install -y libldap2-dev \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install ldap

# zip
RUN apt-get install -y libzip-dev  zip \
    # docker-php-ext-configure zip --with-libzip is required only for < 7.3
    && if [[ $PHP_VERSION == 7.2* ]] ; then \
       docker-php-ext-configure zip --with-libzip; \
    fi && docker-php-ext-install zip

# pgsql
RUN apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pgsql pdo pdo_pgsql

# sockets
RUN docker-php-ext-install sockets

# bcmath
RUN docker-php-ext-install bcmath

ADD composer.json composer.lock ./
RUN composer.phar install && composer.phar clear-cache

# This adds another 30MB to the installation size
# ADD internal/paratest internal/paratest
# RUN cd internal/paratest && ./install.sh

ADD . .

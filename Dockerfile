FROM php:7.4-cli
RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng-dev \
        libzip-dev \
        zip \
        unzip \
        git \
    && docker-php-ext-install -j$(nproc) gd sockets bcmath \
    && pecl install ds pcov \
    && docker-php-ext-enable ds

ARG WITH_XDEBUG=false

RUN if [ $WITH_XDEBUG = "true" ] ; then \
        pecl install channel://pecl.php.net/xdebug-3.0.1; \
        docker-php-ext-enable xdebug; \
fi;
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /opt/project

COPY composer.json composer.lock phpunit.xml.dist phpunit-bootstrap.php psalm.xml .php_cs ./
COPY src/ src/
COPY tests/ tests/
COPY tools/ tools/


RUN composer install  && \
    composer install --working-dir=tools/php-cs-fixer && \
    composer install --working-dir=tools/psalm




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
        wget \
    && docker-php-ext-install -j$(nproc) gd sockets bcmath \
    && pecl install ds pcov \
    && docker-php-ext-enable ds \
    && wget https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 \
    && mv test-reporter-latest-linux-amd64 /usr/bin/cc-test-reporter  \
    && chmod +x /usr/bin/cc-test-reporter

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /opt/project

COPY composer.json ./

RUN composer install

COPY phpunit.xml.dist phpunit.coverage.xml.dist psalm.xml .php-cs-fixer.php ./
COPY src/ src/
COPY tests/ tests/
COPY .git/ .git/




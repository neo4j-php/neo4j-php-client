FROM php:8.1-cli
RUN apt-get update
RUN apt-get install -y \
    libzip-dev \
    unzip \
    git \
    wget
RUN docker-php-ext-install -j$(nproc) bcmath sockets
RUN wget https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64
RUN mv test-reporter-latest-linux-amd64 /usr/bin/cc-test-reporter
RUN chmod +x /usr/bin/cc-test-reporter
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /opt/project

COPY composer.json ./

RUN composer install

COPY phpunit.xml.dist phpunit.coverage.xml.dist psalm.xml .php-cs-fixer.dist.php LICENSE README.md ./
COPY src/ src/
COPY tests/ tests/
COPY .git/ .git/




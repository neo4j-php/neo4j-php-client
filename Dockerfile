ARG PHP_VERSION=8.1

FROM php:${PHP_VERSION}-cli

# PECL often returns "No releases available" for xdebug. Install a pinned
# release from GitHub so Testkit and integration images can build without it.
ARG XDEBUG_VERSION=3.5.3

RUN apt-get update \
    && apt-get install -y \
        libzip-dev \
        unzip \
        git \
        wget \
    && docker-php-ext-install -j$(nproc) bcmath sockets \
    && curl -fsSL "https://github.com/xdebug/xdebug/archive/refs/tags/${XDEBUG_VERSION}.tar.gz" -o /tmp/xdebug.tar.gz \
    && tar -xzf /tmp/xdebug.tar.gz -C /tmp \
    && docker-php-ext-configure "/tmp/xdebug-${XDEBUG_VERSION}" --enable-xdebug \
    && docker-php-ext-install -j$(nproc) "/tmp/xdebug-${XDEBUG_VERSION}" \
    && rm -rf /tmp/xdebug.tar.gz "/tmp/xdebug-${XDEBUG_VERSION}" \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /opt/project

ARG PHP_VERSION=8.4.7

FROM php:${PHP_VERSION}-cli

# Set environment variables
ENV PATH="/usr/local/go/bin:${PATH}" \
    COMPOSER_ALLOW_SUPERUSER=1 \
    DEBIAN_FRONTEND=noninteractive

# Install dependencies, Go, PHP extensions, Python venv and tools
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        git \
        wget \
        curl \
        tar \
        gcc \
        make \
        ca-certificates \
        build-essential \
        pkg-config \
        software-properties-common \
        python3-venv \
        python3-pip \
        python3-setuptools \
    # Install Go 1.22.0
    && curl -LO https://golang.org/dl/go1.22.0.linux-amd64.tar.gz \
    && tar -C /usr/local -xzf go1.22.0.linux-amd64.tar.gz \
    && rm go1.22.0.linux-amd64.tar.gz \
    && ln -s /usr/local/go/bin/go /usr/bin/go \
    # Install PHP extensions
    && docker-php-ext-install -j$(nproc) bcmath sockets \
    # Install CodeClimate Test Reporter
    && wget -O /usr/bin/cc-test-reporter https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 \
    && chmod +x /usr/bin/cc-test-reporter \
    # Install and enable Xdebug
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    # Install Composer
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    # Cleanup
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /opt/project

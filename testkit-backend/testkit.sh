#!/bin/bash

TESTKIT_VERSION=5.0

export TEST_NEO4J_HOST=localhost
export TEST_NEO4J_USER=neo4j
export TEST_NEO4J_PASS=test
export TEST_DRIVER_NAME=php

TEST_DRIVER_REPO=$(realpath ..)
export TEST_DRIVER_REPO

echo "TEST_DRIVER_REPO: $TEST_DRIVER_REPO"

if [ "$1" == "--clean" ]; then
    if [ -d testkit ]; then
        rm -rf testkit
    fi
fi

if [ ! -d testkit ]; then
    git clone git@github.com:neo4j-drivers/testkit.git
    if [ "$(cd testkit && git branch --show-current)" != "${TESTKIT_VERSION}" ]; then
        (cd testkit && git checkout ${TESTKIT_VERSION})
    fi
else
    (cd testkit && git pull)
fi

cd testkit || (echo 'cannot cd into testkit' && exit 1)
exec python3 main.py --tests UNIT_TESTS

#!/bin/bash

TESTKIT_VERSION=5.0

export TEST_NEO4J_HOST=neo4j
export TEST_NEO4J_USER=neo4j
export TEST_NEO4J_PASS=testtest
export TEST_DRIVER_NAME=php


TEST_DRIVER_REPO=$(realpath ..)
export TEST_DRIVER_REPO

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
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# exec python3 main.py --tests UNIT_TESTS
exec python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks

#!/bin/bash

TESTKIT_VERSION=5.0

[ -z "$TEST_NEO4J_HOST" ] && export TEST_NEO4J_HOST=neo4j
[ -z "$TEST_NEO4J_USER" ] && export TEST_NEO4J_USER=neo4j
[ -z "$TEST_NEO4J_PASS" ] && export TEST_NEO4J_PASS=testtest
[ -z "$TEST_DRIVER_NAME" ] && export TEST_DRIVER_NAME=php

[ -z "$TEST_DRIVER_REPO" ] && TEST_DRIVER_REPO=$(realpath ..) && export TEST_DRIVER_REPO

if [ "$1" == "--clean" ]; then
    if [ -d testkit ]; then
        rm -rf testkit
    fi
fi

if [ ! -d testkit ]; then
    git clone https://github.com/neo4j-drivers/testkit.git
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

# python3 main.py --tests UNIT_TESTS

echo "Starting tests..."

EXIT_CODE=0

python3 -m unittest tests.neo4j.test_authentication.TestAuthenticationBasic || EXIT_CODE=1

python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks || EXIT_CODE=1

python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_nested || EXIT_CODE=1

# Uncomment to run this test as well
# python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_recover_from_fail_on_streaming || EXIT_CODE=1

exit $EXIT_CODE


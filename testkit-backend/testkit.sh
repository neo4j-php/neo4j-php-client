#!/bin/bash

TESTKIT_VERSION=5.0

[ -z "$TEST_NEO4J_HOST" ] && export TEST_NEO4J_HOST=neo4j
[ -z "$TEST_NEO4J_USER" ] && export TEST_NEO4J_USER=neo4j
[ -z "$TEST_NEO4J_PASS" ] && export TEST_NEO4J_PASS=testtest
[ -z "$TEST_NEO4J_VERSION" ] && export TEST_NEO4J_VERSION=5.23
[ -z "$TEST_DRIVER_NAME" ] && export TEST_DRIVER_NAME=php
[ -z "$TEST_STUB_HOST" ] && export TEST_STUB_HOST=host.docker.internal


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

#TestBookmarksV4
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_on_unused_sessions_are_returned
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_session_run
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_tx_run
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_sequence_of_writing_and_reading_tx


#TestBookmarksV5
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_bookmarks_can_be_set || EXIT_CODE=1
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_last_bookmark || EXIT_CODE=1
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_bookmarks_write_tx || EXIT_CODE=1
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_sequence_of_writing_and_reading_tx || EXIT_CODE=1
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_multiple_bookmarks_write_tx || EXIT_CODE=1
#test_summary

exit $EXIT_CODE


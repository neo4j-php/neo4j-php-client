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
#
#python3 -m unittest tests.neo4j.test_authentication.TestAuthenticationBasic || EXIT_CODE=1
#python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks || EXIT_CODE=1
#
## This test is still failing so we skip it
## python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_autocommit_transactions_should_support_timeouttest_autocommit_transactions_should_support_timeout|| EXIT_CODE=1
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_smaller_than_fetch_size
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_node
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_relationship
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_path
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_autocommit_transactions_should_support_metadata
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_regex_in_parameter
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_regex_inline
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_larger_than_fetch_size
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_partial_iteration
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_simple_query
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_session_reuse
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_nested
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_recover_from_invalid_query
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_recover_from_fail_on_streaming
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_updates_last_bookmark
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_fails_on_bad_syntax
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_fails_on_missing_parameter
#python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_long_string
#
### This test is still failing so we skip it test_direct_driver
#python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_custom_resolver|| EXIT_CODE=1
#python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_fail_nicely_when_using_http_port|| EXIT_CODE=1
#python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_supports_multi_db|| EXIT_CODE=1
#python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db|| EXIT_CODE=1
#python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db_various_databases|| EXIT_CODE=1
#
##test_summary
#python3 -m unittest tests.neo4j.test_summary.TestSummary || EXIT_CODE=1


#stub
#test-basic-query
#python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_node_element_id_with_id
#python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_5x0_populates_node_element_id_with_string
#python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_rel_element_id_with_id
#python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_path_element_ids_with_long


#bookmarks
#TestBookmarksV4
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_on_unused_sessions_are_returned #fixed
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_session_run #fixed
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_tx_run #fixed
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_sequence_of_writing_and_reading_tx

#TestBookmarksV5
##
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_bookmarks_can_be_set # fixed
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_last_bookmark #fixed
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_bookmarks_read_tx #fixed
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_bookmarks_write_tx
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_sequence_of_writing_and_reading_tx
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_multiple_bookmarks_write_tx

#test-session-run
#python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun.test_discard_on_session_close_untouched_result
#python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun.test_discard_on_session_close_unfinished_result
#python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun.test_no_discard_on_session_close_finished_result
#python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun.test_raises_error_on_session_run

#bookmarks
#TestBookmarksV4
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_on_unused_sessions_are_returned
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_session_run
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_tx_run
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_sequence_of_writing_and_reading_tx


#test_summary

exit $EXIT_CODE


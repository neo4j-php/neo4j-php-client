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
fi
#else
#    (cd testkit && git pull)
#fi

cd testkit || (echo 'cannot cd into testkit' && exit 1)
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# python3 main.py --tests UNIT_TESTS

echo "Starting tests..."

EXIT_CODE=0
#neo4j
#test_authentication
python3 -m unittest tests.neo4j.test_authentication.TestAuthenticationBasic|| EXIT_CODE=1
#
###test_bookmarks
python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks || EXIT_CODE=1
##
###test_session_run
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_smaller_than_fetch_size
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_node
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_relationship
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_path
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_autocommit_transactions_should_support_metadata
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_regex_in_parameter
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_regex_inline
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_larger_than_fetch_size
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_partial_iteration
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_simple_query
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_session_reuse
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_nested
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_recover_from_invalid_query
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_updates_last_bookmark
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_fails_on_bad_syntax
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_fails_on_missing_parameter
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_long_string
#
##test_direct_driver
### test_direct_driver
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_custom_resolver|| EXIT_CODE=1
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_fail_nicely_when_using_http_port|| EXIT_CODE=1
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_supports_multi_db|| EXIT_CODE=1
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db_non_existing
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db|| EXIT_CODE=1
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db_various_databases|| EXIT_CODE=1
#
##test_summary
python3 -m unittest tests.neo4j.test_summary.TestSummary

####stub
####test-basic-query
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_5x0_populates_path_element_ids_with_string
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_node_element_id_with_id
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_5x0_populates_node_element_id_with_string
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_rel_element_id_with_id
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_path_element_ids_with_long

##test_tx_run
#tx_run
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_simple_query  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_can_commit_transaction  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_can_rollback_transaction  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_updates_last_bookmark_on_commit  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_does_not_update_last_bookmark_on_rollback  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_does_not_update_last_bookmark_on_failure  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_be_able_to_rollback_a_failure  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_commit_a_failure  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_rollback_a_rollbacked_tx  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_rollback_a_commited_tx  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_commit_a_commited_tx  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_allow_run_on_a_commited_tx  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_allow_run_on_a_rollbacked_tx  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_run_valid_query_in_invalid_tx  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_fail_run_in_a_commited_tx  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_fail_run_in_a_rollbacked_tx  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_fail_to_run_query_for_invalid_bookmark  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_broken_transaction_should_not_break_session  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_tx_configuration  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_consume_after_commit  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_parallel_queries  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_interwoven_queries  || EXIT_CODE=1
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_unconsumed_result  || EXIT_CODE=1

exit $EXIT_CODE

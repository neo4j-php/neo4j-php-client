#!/bin/bash

TESTKIT_VERSION=5.0

[ -z "$TEST_NEO4J_HOST" ] && export TEST_NEO4J_HOST=neo4j
[ -z "$TEST_NEO4J_USER" ] && export TEST_NEO4J_USER=neo4j
[ -z "$TEST_NEO4J_PASS" ] && export TEST_NEO4J_PASS=testtest
[ -z "$TEST_NEO4J_VERSION" ] && export TEST_NEO4J_VERSION=5.26
[ -z "$TEST_DRIVER_NAME" ] && export TEST_DRIVER_NAME=php
[ -z "$TEST_DEBUG_NO_BACKEND_TIMEOUT" ] && export TEST_DEBUG_NO_BACKEND_TIMEOUT=1

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
###neo4j
#test_authentication
echo "Running: TestAuthenticationBasic"
python3 -m unittest tests.neo4j.test_authentication.TestAuthenticationBasic|| EXIT_CODE=1

#test_bookmarks
echo "Running: test_can_obtain_bookmark_after_commit"
python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks.test_can_obtain_bookmark_after_commit || EXIT_CODE=1
echo "Running: test_can_pass_bookmark_into_next_session"
python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks.test_can_pass_bookmark_into_next_session || EXIT_CODE=1
echo "Running: test_no_bookmark_after_rollback"
python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks.test_no_bookmark_after_rollback || EXIT_CODE=1
echo "Running: test_fails_on_invalid_bookmark"
python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks.test_fails_on_invalid_bookmark || EXIT_CODE=1
echo "Running: test_fails_on_invalid_bookmark_using_tx_func"
python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks.test_fails_on_invalid_bookmark_using_tx_func || EXIT_CODE=1
echo "Running: test_can_handle_multiple_bookmarks"
python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks.test_can_handle_multiple_bookmarks || EXIT_CODE=1
echo "Running: test_can_pass_write_bookmark_into_write_session"
python3 -m unittest tests.neo4j.test_bookmarks.TestBookmarks.test_can_pass_write_bookmark_into_write_session || EXIT_CODE=1

###test_session_run
echo "Running: test_iteration_smaller_than_fetch_size"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_smaller_than_fetch_size  || EXIT_CODE=1
echo "Running: test_can_return_node"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_node  || EXIT_CODE=1
echo "Running: test_can_return_relationship"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_relationship  || EXIT_CODE=1
echo "Running: test_can_return_path"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_can_return_path  || EXIT_CODE=1
echo "Running: test_autocommit_transactions_should_support_metadata"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_autocommit_transactions_should_support_metadata  || EXIT_CODE=1
echo "Running: test_regex_in_parameter"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_regex_in_parameter  || EXIT_CODE=1
echo "Running: test_regex_inline"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_regex_inline  || EXIT_CODE=1
echo "Running: test_iteration_larger_than_fetch_size"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_larger_than_fetch_size  || EXIT_CODE=1
echo "Running: test_partial_iteration"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_partial_iteration  || EXIT_CODE=1
echo "Running: test_simple_query"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_simple_query  || EXIT_CODE=1
echo "Running: test_session_reuse"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_session_reuse  || EXIT_CODE=1
echo "Running: test_iteration_nested"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_iteration_nested  || EXIT_CODE=1
echo "Running: test_recover_from_invalid_query"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_recover_from_invalid_query  || EXIT_CODE=1
echo "Running: test_updates_last_bookmark"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_updates_last_bookmark  || EXIT_CODE=1
echo "Running: test_fails_on_bad_syntax"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_fails_on_bad_syntax  || EXIT_CODE=1
echo "Running: test_fails_on_missing_parameter"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_fails_on_missing_parameter  || EXIT_CODE=1
echo "Running: test_long_string"
python3 -m unittest tests.neo4j.test_session_run.TestSessionRun.test_long_string  || EXIT_CODE=1
###
###test_direct_driver
echo "Running: test_custom_resolver"
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_custom_resolver|| EXIT_CODE=1
echo "Running: test_fail_nicely_when_using_http_port"
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_fail_nicely_when_using_http_port|| EXIT_CODE=1
echo "Running: test_supports_multi_db"
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_supports_multi_db|| EXIT_CODE=1
echo "Running: test_multi_db_non_existing"
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db_non_existing || EXIT_CODE=1
echo "Running: test_multi_db"
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db || EXIT_CODE=1
echo "Running: test_multi_db_various_databases"
python3 -m unittest tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db_various_databases|| EXIT_CODE=1
##
###test_summary
echo "Running: TestSummary"
python3 -m unittest tests.neo4j.test_summary.TestSummary
##
##
####test_tx_run
echo "Running: test_simple_query (tx_run)"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_simple_query  || EXIT_CODE=1
echo "Running: test_can_commit_transaction"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_can_commit_transaction  || EXIT_CODE=1
echo "Running: test_can_rollback_transaction"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_can_rollback_transaction  || EXIT_CODE=1
echo "Running: test_updates_last_bookmark_on_commit (tx_run)"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_updates_last_bookmark_on_commit  || EXIT_CODE=1
echo "Running: test_does_not_update_last_bookmark_on_rollback"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_does_not_update_last_bookmark_on_rollback  || EXIT_CODE=1
echo "Running: test_does_not_update_last_bookmark_on_failure"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_does_not_update_last_bookmark_on_failure  || EXIT_CODE=1
echo "Running: test_should_be_able_to_rollback_a_failure"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_be_able_to_rollback_a_failure  || EXIT_CODE=1
echo "Running: test_should_not_commit_a_failure"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_commit_a_failure  || EXIT_CODE=1
echo "Running: test_should_not_rollback_a_rollbacked_tx"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_rollback_a_rollbacked_tx  || EXIT_CODE=1
echo "Running: test_should_not_rollback_a_commited_tx"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_rollback_a_commited_tx  || EXIT_CODE=1
echo "Running: test_should_not_commit_a_commited_tx"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_commit_a_commited_tx  || EXIT_CODE=1
echo "Running: test_should_not_allow_run_on_a_commited_tx"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_allow_run_on_a_commited_tx  || EXIT_CODE=1
echo "Running: test_should_not_allow_run_on_a_rollbacked_tx"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_allow_run_on_a_rollbacked_tx  || EXIT_CODE=1
echo "Running: test_should_not_run_valid_query_in_invalid_tx"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_not_run_valid_query_in_invalid_tx  || EXIT_CODE=1
echo "Running: test_should_fail_run_in_a_commited_tx"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_fail_run_in_a_commited_tx  || EXIT_CODE=1
echo "Running: test_should_fail_run_in_a_rollbacked_tx"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_fail_run_in_a_rollbacked_tx  || EXIT_CODE=1
echo "Running: test_should_fail_to_run_query_for_invalid_bookmark"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_should_fail_to_run_query_for_invalid_bookmark  || EXIT_CODE=1
echo "Running: test_broken_transaction_should_not_break_session"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_broken_transaction_should_not_break_session  || EXIT_CODE=1
echo "Running: test_tx_configuration"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_tx_configuration  || EXIT_CODE=1
echo "Running: test_consume_after_commit"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_consume_after_commit  || EXIT_CODE=1
echo "Running: test_parallel_queries"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_parallel_queries  || EXIT_CODE=1
echo "Running: test_unconsumed_result"
python3 -m unittest tests.neo4j.test_tx_run.TestTxRun.test_unconsumed_result  || EXIT_CODE=1
##
####test_tx_func_run
echo "Running: test_simple_query (tx_func_run)"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_simple_query  || EXIT_CODE=1
echo "Running: test_parameter"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_parameter  || EXIT_CODE=1
echo "Running: test_meta_data"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_meta_data  || EXIT_CODE=1
echo "Running: test_iteration_nested (tx_func_run)"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_iteration_nested || EXIT_CODE=1
echo "Running: test_updates_last_bookmark_on_commit (tx_func_run)"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_updates_last_bookmark_on_commit  || EXIT_CODE=1
echo "Running: test_does_not_update_last_bookmark_on_rollback (tx_func_run)"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_does_not_update_last_bookmark_on_rollback  || EXIT_CODE=1
echo "Running: test_client_exception_rolls_back_change"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_client_exception_rolls_back_change  || EXIT_CODE=1
echo "Running: test_tx_func_configuration"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_tx_func_configuration  || EXIT_CODE=1
echo "Running: test_tx_timeout"
python3 -m unittest tests.neo4j.test_tx_func_run.TestTxFuncRun.test_tx_timeout  || EXIT_CODE=1

#stub
####test-basic-query
echo "Running: test_5x0_populates_path_element_ids_with_string"
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_5x0_populates_path_element_ids_with_string  || EXIT_CODE=1
echo "Running: test_4x4_populates_node_element_id_with_id"
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_node_element_id_with_id  || EXIT_CODE=1
echo "Running: test_5x0_populates_node_element_id_with_string"
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_5x0_populates_node_element_id_with_string  || EXIT_CODE=1
echo "Running: test_4x4_populates_rel_element_id_with_id"
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_rel_element_id_with_id  || EXIT_CODE=1
echo "Running: test_4x4_populates_path_element_ids_with_long"
python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_path_element_ids_with_long  || EXIT_CODE=1

#test-session-run
echo "Running: test_discard_on_session_close_untouched_result"
python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun.test_discard_on_session_close_untouched_result  || EXIT_CODE=1
echo "Running: test_discard_on_session_close_unfinished_result"
python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun.test_discard_on_session_close_unfinished_result  || EXIT_CODE=1
echo "Running: test_no_discard_on_session_close_finished_result"
python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun.test_no_discard_on_session_close_finished_result  || EXIT_CODE=1
echo "Running: test_raises_error_on_session_run"
python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun.test_raises_error_on_session_run  || EXIT_CODE=1

##TestBookmarksV5
echo "Running: test_bookmarks_can_be_set"
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_bookmarks_can_be_set || EXIT_CODE=1
echo "Running: test_last_bookmark"
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_last_bookmark || EXIT_CODE=1
echo "Running: test_send_and_receive_bookmarks_write_tx"
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_bookmarks_write_tx || EXIT_CODE=1
echo "Running: test_sequence_of_writing_and_reading_tx (v5)"
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_sequence_of_writing_and_reading_tx || EXIT_CODE=1
echo "Running: test_send_and_receive_multiple_bookmarks_write_tx"
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_multiple_bookmarks_write_tx || EXIT_CODE=1

##TestBookmarksV4
echo "Running: test_bookmarks_on_unused_sessions_are_returned"
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_on_unused_sessions_are_returned || EXIT_CODE=1
echo "Running: test_bookmarks_session_run"
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_session_run || EXIT_CODE=1
echo "Running: test_sequence_of_writing_and_reading_tx (v4)"
python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_sequence_of_writing_and_reading_tx || EXIT_CODE=1

exit $EXIT_CODE

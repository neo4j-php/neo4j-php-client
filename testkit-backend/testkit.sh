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
pip install -r requirements.txt > /dev/null 2>&1

echo ""
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║                     Neo4j PHP Driver TestKit Suite                        ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""




## Run all tests in a single command with verbose output
python3 -m unittest -v \
    tests.neo4j.test_authentication.TestAuthenticationBasic \
\
    tests.neo4j.test_bookmarks.TestBookmarks.test_can_handle_multiple_bookmarks \
    tests.neo4j.test_bookmarks.TestBookmarks.test_can_obtain_bookmark_after_commit \
    tests.neo4j.test_bookmarks.TestBookmarks.test_can_pass_bookmark_into_next_session \
    tests.neo4j.test_bookmarks.TestBookmarks.test_can_pass_write_bookmark_into_write_session \
    tests.neo4j.test_bookmarks.TestBookmarks.test_fails_on_invalid_bookmark \
    tests.neo4j.test_bookmarks.TestBookmarks.test_fails_on_invalid_bookmark_using_tx_func \
    tests.neo4j.test_bookmarks.TestBookmarks.test_no_bookmark_after_rollback \
\
    tests.neo4j.test_direct_driver.TestDirectDriver.test_custom_resolver \
    tests.neo4j.test_direct_driver.TestDirectDriver.test_fail_nicely_when_using_http_port \
    tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db \
    tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db_non_existing \
    tests.neo4j.test_direct_driver.TestDirectDriver.test_multi_db_various_databases \
    tests.neo4j.test_direct_driver.TestDirectDriver.test_supports_multi_db \
\
    tests.neo4j.test_session_run.TestSessionRun.test_autocommit_transactions_should_support_metadata \
    tests.neo4j.test_session_run.TestSessionRun.test_can_return_node \
    tests.neo4j.test_session_run.TestSessionRun.test_can_return_path \
    tests.neo4j.test_session_run.TestSessionRun.test_can_return_relationship \
    tests.neo4j.test_session_run.TestSessionRun.test_fails_on_bad_syntax \
    tests.neo4j.test_session_run.TestSessionRun.test_fails_on_missing_parameter \
    tests.neo4j.test_session_run.TestSessionRun.test_iteration_larger_than_fetch_size \
    tests.neo4j.test_session_run.TestSessionRun.test_iteration_nested \
    tests.neo4j.test_session_run.TestSessionRun.test_iteration_smaller_than_fetch_size \
    tests.neo4j.test_session_run.TestSessionRun.test_long_string \
    tests.neo4j.test_session_run.TestSessionRun.test_partial_iteration \
    tests.neo4j.test_session_run.TestSessionRun.test_recover_from_invalid_query \
    tests.neo4j.test_session_run.TestSessionRun.test_regex_in_parameter \
    tests.neo4j.test_session_run.TestSessionRun.test_regex_inline \
    tests.neo4j.test_session_run.TestSessionRun.test_session_reuse \
    tests.neo4j.test_session_run.TestSessionRun.test_simple_query \
    tests.neo4j.test_session_run.TestSessionRun.test_updates_last_bookmark \
\
    tests.neo4j.test_summary.TestSummary \
\
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_client_exception_rolls_back_change \
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_does_not_update_last_bookmark_on_rollback \
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_iteration_nested \
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_meta_data \
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_parameter \
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_simple_query \
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_tx_func_configuration \
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_tx_timeout \
    tests.neo4j.test_tx_func_run.TestTxFuncRun.test_updates_last_bookmark_on_commit \
    tests.neo4j.test_tx_run.TestTxRun.test_broken_transaction_should_not_break_session  \
    tests.neo4j.test_tx_run.TestTxRun.test_can_commit_transaction \
    tests.neo4j.test_tx_run.TestTxRun.test_can_rollback_transaction \
    tests.neo4j.test_tx_run.TestTxRun.test_consume_after_commit \
    tests.neo4j.test_tx_run.TestTxRun.test_does_not_update_last_bookmark_on_failure \
    tests.neo4j.test_tx_run.TestTxRun.test_does_not_update_last_bookmark_on_rollback \
    tests.neo4j.test_tx_run.TestTxRun.test_parallel_queries \
    tests.neo4j.test_tx_run.TestTxRun.test_should_be_able_to_rollback_a_failure \
    tests.neo4j.test_tx_run.TestTxRun.test_should_not_allow_run_on_a_commited_tx \
    tests.neo4j.test_tx_run.TestTxRun.test_should_not_allow_run_on_a_rollbacked_tx \
    tests.neo4j.test_tx_run.TestTxRun.test_should_not_commit_a_commited_tx \
    tests.neo4j.test_tx_run.TestTxRun.test_should_not_commit_a_failure \
    tests.neo4j.test_tx_run.TestTxRun.test_should_not_rollback_a_commited_tx \
    tests.neo4j.test_tx_run.TestTxRun.test_should_not_rollback_a_rollbacked_tx \
    tests.neo4j.test_tx_run.TestTxRun.test_should_not_run_valid_query_in_invalid_tx \
    tests.neo4j.test_tx_run.TestTxRun.test_should_fail_run_in_a_commited_tx \
    tests.neo4j.test_tx_run.TestTxRun.test_should_fail_run_in_a_rollbacked_tx \
    tests.neo4j.test_tx_run.TestTxRun.test_should_fail_to_run_query_for_invalid_bookmark \
    tests.neo4j.test_tx_run.TestTxRun.test_simple_query \
    tests.neo4j.test_tx_run.TestTxRun.test_tx_configuration \
    tests.neo4j.test_tx_run.TestTxRun.test_unconsumed_result \
    tests.neo4j.test_tx_run.TestTxRun.test_updates_last_bookmark_on_commit \
\
    tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_node_element_id_with_id  \
    tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_path_element_ids_with_long \
    tests.stub.basic_query.test_basic_query.TestBasicQuery.test_4x4_populates_rel_element_id_with_id \
    tests.stub.basic_query.test_basic_query.TestBasicQuery.test_5x0_populates_node_element_id_with_string \
    tests.stub.basic_query.test_basic_query.TestBasicQuery.test_5x0_populates_path_element_ids_with_string \
\
    tests.stub.session_run.test_session_run.TestSessionRun.test_discard_on_session_close_unfinished_result \
    tests.stub.session_run.test_session_run.TestSessionRun.test_discard_on_session_close_untouched_result \
    tests.stub.session_run.test_session_run.TestSessionRun.test_no_discard_on_session_close_finished_result \
    tests.stub.session_run.test_session_run.TestSessionRun.test_raises_error_on_session_run \
\
    tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_on_unused_sessions_are_returned \
    tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_bookmarks_session_run \
    tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4.test_sequence_of_writing_and_reading_tx \
\
    tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_bookmarks_can_be_set \
    tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_last_bookmark \
    tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_sequence_of_writing_and_reading_tx \
    tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_bookmarks_write_tx \
    tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5.test_send_and_receive_multiple_bookmarks_write_tx \
\
    tests.stub.connectivity_check.test_get_server_info.TestGetServerInfo.test_direct_no_server \
    tests.stub.connectivity_check.test_get_server_info.TestGetServerInfo.test_direct_raises_error \
    tests.stub.connectivity_check.test_get_server_info.TestGetServerInfo.test_direct \
    tests.stub.connectivity_check.test_get_server_info.TestGetServerInfo.test_routing_no_server \
    tests.stub.connectivity_check.test_get_server_info.TestGetServerInfo.test_routing_raises_error \
    tests.stub.connectivity_check.test_get_server_info.TestGetServerInfo.test_routing \
\
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_in_time \
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_timeout_unmanaged_tx \
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_timeout_unmanaged_tx_should_fail_subsequent_usage_after_timeout \
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_in_time_unmanaged_tx \
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_in_time_managed_tx_retry \
\
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestRoutingConnectionRecvTimeout.test_in_time \
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestRoutingConnectionRecvTimeout.test_in_time_unmanaged_tx \
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestRoutingConnectionRecvTimeout.test_in_time_managed_tx_retry


EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "╔════════════════════════════════════════════════════════════════════════════╗"
    echo "║                          ✓ ALL TESTS PASSED                                ║"
    echo "╚════════════════════════════════════════════════════════════════════════════╝"
else
    echo "╔════════════════════════════════════════════════════════════════════════════╗"
    echo "║                          ✗ SOME TESTS FAILED                               ║"
    echo "╚════════════════════════════════════════════════════════════════════════════╝"
fi
echo ""

exit $EXIT_CODE

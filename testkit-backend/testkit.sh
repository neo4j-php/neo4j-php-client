#!/bin/bash

[ -z "$TEST_NEO4J_HOST" ] && export TEST_NEO4J_HOST=neo4j
[ -z "$TEST_NEO4J_USER" ] && export TEST_NEO4J_USER=neo4j
[ -z "$TEST_NEO4J_PASS" ] && export TEST_NEO4J_PASS=testtest
[ -z "$TEST_NEO4J_VERSION" ] && export TEST_NEO4J_VERSION=5.26
[ -z "$TEST_DRIVER_NAME" ] && export TEST_DRIVER_NAME=php
[ -z "$TEST_DEBUG_NO_BACKEND_TIMEOUT" ] && export TEST_DEBUG_NO_BACKEND_TIMEOUT=1

[ -z "$TEST_DRIVER_REPO" ] && TEST_DRIVER_REPO=$(realpath ..) && export TEST_DRIVER_REPO

# Use realpath for robustness instead of relative paths
TESTKIT_DIR=$(realpath ../testkit)
if [ ! -d "$TESTKIT_DIR" ]; then
    echo "ERROR: testkit directory not found at $TESTKIT_DIR"
    exit 1
fi

cd "$TESTKIT_DIR" || (echo 'cannot cd into testkit' && exit 1)

# Verify tests directory exists
if [ ! -d "$TESTKIT_DIR/tests" ]; then
    echo "ERROR: tests directory not found at $TESTKIT_DIR/tests"
    exit 1
fi

python3 -m venv venv
source venv/bin/activate

# Explicitly set PYTHONPATH to ensure module discovery
export PYTHONPATH="${PYTHONPATH}:${TESTKIT_DIR}"

pip install -r requirements.txt

echo ""
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║                     Neo4j PHP Driver TestKit Suite                        ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""




## Run all tests in a single command with verbose output
python3 -m unittest -v \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_disconnect_on_pull \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_disconnect_on_tx_run \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_disconnect_on_tx_pull \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_disconnect_session_on_tx_commit \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_client_says_goodbye \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_disconnect_session_on_pull_after_record \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_disconnect_on_tx_begin \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_disconnect_session_on_tx_pull_after_record \
    tests.stub.disconnects.test_disconnects.TestDisconnects.test_fail_on_reset \


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

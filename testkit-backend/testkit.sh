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
    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_timeout_unmanaged_tx \

#    tests.stub.connectivity_check.test_get_server_info.TestGetServerInfo.test_routing \
#\
#    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_in_time \
#    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_timeout_unmanaged_tx \
#    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_timeout_unmanaged_tx_should_fail_subsequent_usage_after_timeout \
#    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_in_time_unmanaged_tx \
#    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout.test_in_time_managed_tx_retry \
#\
#    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestRoutingConnectionRecvTimeout.test_in_time \
#    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestRoutingConnectionRecvTimeout.test_in_time_unmanaged_tx \
#    tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestRoutingConnectionRecvTimeout.test_in_time_managed_tx_retry


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

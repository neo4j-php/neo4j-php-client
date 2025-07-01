#!/bin/bash

set -ex

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

if [ ! -f ./tlsserver/tlsserver ]; then
    (cd ./tlsserver && go build -o tlsserver main.go)
fi

CERT_DIR=$(realpath ./tests/tls)
(cd certgen && go run main.go "${CERT_DIR}")

# python3 main.py --tests UNIT_TESTS

echo "Starting tests..."


#tlstest_secure_server
#
#python3 -m unittest tests.tls.test_client_certificate.TestClientCertificate
#python3 -m unittest tests.tls.test_client_certificate.TestClientCertificateRotation
#python3 -m unittest tests.tls.test_explicit_options.TestExplicitSslOptions
#python3 -m unittest tests.tls.test_secure_scheme.TestSecureScheme
#python3 -m unittest tests.tls.test_secure_scheme.TestTrustSystemCertsConfig
#python3 -m unittest tests.tls.test_secure_scheme.TestTrustCustomCertsConfig
#python3 -m unittest tests.tls.test_self_signed_scheme.TestSelfSignedScheme
#python3 -m unittest tests.tls.test_self_signed_scheme.TestTrustAllCertsConfig
#python3 -m unittest tests.tls.test_tls_versions.TestTlsVersions
#python3 -m unittest tests.tls.test_unsecure_scheme.TestUnsecureScheme.test_secure_server

#STUB

#bookmarks
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v4.TestBookmarksV4
#python3 -m unittest tests.stub.bookmarks.test_bookmarks_v5.TestBookmarksV5

#basic_query
#python3 -m unittest tests.stub.basic_query.test_basic_query.TestBasicQuery

#authorization
#python3 -m unittest tests.stub.authorization.test_auth_token_manager.TestAuthTokenManager5x1
#python3 -m unittest tests.stub.authorization.test_authorization.TestAuthorizationV4x3
#python3 -m unittest tests.stub.authorization.test_basic_auth_manager.TestBasicAuthManager5x1
#python3 -m unittest tests.stub.authorization.test_bearer_auth_manager.TestBearerAuthManager5x1
#python3 -m unittest tests.stub.authorization.test_user_switching.TestUserSwitchingV5x1

#configuration_hints
#python3 -m unittest tests.stub.configuration_hints.test_connection_recv_timeout_seconds.TestDirectConnectionRecvTimeout

##connectivity_check
#python3 -m unittest tests.stub.connectivity_check.test_get_server_info.TestGetServerInfo
#python3 -m unittest tests.stub.connectivity_check.test_verify_connectivity.TestVerifyConnectivity

#disconnects
#python3 -m unittest tests.stub.disconnects.test_disconnects.TestDisconnects

#driver_execute_query
#python3 -m unittest tests.stub.driver_execute_query.test_driver_execute_query.TestDriverExecuteQuery

#driver_parameters
  #telemetry
#  python3 -m unittest tests.stub.driver_parameters.telemetry.test_telemetry.TestTelemetry

#python3 -m unittest tests.stub.driver_parameters.test_bookmark_manager.TestNeo4jBookmarkManager
#python3 -m unittest tests.stub.driver_parameters.test_client_agent_strings.TestClientAgentStringsV5x2
#python3 -m unittest tests.stub.driver_parameters.test_connection_acquisition_timeout_ms.TestConnectionAcquisitionTimeoutMs
#python3 -m unittest tests.stub.driver_parameters.test_liveness_check.TestLivenessCheck
#python3 -m unittest tests.stub.driver_parameters.test_max_connection_pool_size.TestMaxConnectionPoolSize

#errors
#python3 -m unittest tests.stub.errors.test_errors.TestError5x6

#homedb
#python3 -m unittest tests.stub.homedb.test_homedb.TestHomeDbWithCache

#iteration
#python3 -m unittest tests.stub.iteration.test_iteration_session_run.TestIterationSessionRun.test_all_slow_connection
#python3 -m unittest tests.stub.iteration.test_iteration_session_run.TestIterationSessionRun  #failed one->test_all_slow_connection
#python3 -m unittest tests.stub.iteration.test_iteration_tx_run.TestIterationTxRun
#python3 -m unittest tests.stub.iteration.test_result_list.TestResultList
#python3 -m unittest tests.stub.iteration.test_result_optional_single.TestResultSingleOptional
#python3 -m unittest tests.stub.iteration.test_result_peek.TestResultPeek
#python3 -m unittest tests.stub.iteration.test_result_scope.TestResultScope #5 errors
#python3 -m unittest tests.stub.iteration.test_result_single.TestResultSingle



#notifications_config
#python3 -m unittest tests.stub.notifications_config.test_driver_notifications_config.TestDriverNotificationsConfig
#python3 -m unittest tests.stub.notifications_config.test_notification_mapping.TestNotificationMapping


#optimizations
#python3 -m unittest tests.stub.optimizations.test_optimizations.TestOptimizations

#retry
#python3 -m unittest tests.stub.retry.test_retry.TestRetry
#python3 -m unittest tests.stub.retry.test_retry_clustering.TestRetryClustering


#routing
#python3 -m unittest tests.stub.routing.test_no_routing_v3.NoRoutingV3
#python3 -m unittest tests.stub.routing.test_no_routing_v4x2.NoRoutingV4x2
#python3 -m unittest tests.stub.routing.test_routing_v3.RoutingV3
#python3 -m unittest tests.stub.routing.test_routing_v4x2.RoutingV4x2
#python3 -m unittest tests.stub.routing.test_routing_v4x3.RoutingV4x3
#python3 -m unittest tests.stub.routing.test_routing_v4x4.RoutingV4x4
#python3 -m unittest tests.stub.routing.test_routing_v5x0.RoutingV5x0

#server_side_routing
#python3 -m unittest tests.stub.server_side_routing.test_server_side_routing.TestServerSideRouting

#session_run
#python3 -m unittest tests.stub.session_run.test_session_run.TestSessionRun

#session_run_parameters
#python3 -m unittest tests.stub.session_run_parameters.test_session_run_parameters.TestSessionRunParameters

#summary
#python3 -m unittest tests.stub.summary.test_summary._TestSummaryBase
#python3 -m unittest tests.stub.summary.test_summary._TestSummaryDiscardMixin
#python3 -m unittest tests.stub.summary.test_summary.TestSummaryBasicInfo
#python3 -m unittest tests.stub.summary.test_summary.TestSummaryBasicInfoDiscard
#python3 -m unittest tests.stub.summary.test_summary.TestSummaryNotifications4x4
#python3 -m unittest tests.stub.summary.test_summary.TestSummaryNotifications4x4Discard


#transport
#python3 -m unittest tests.stub.transport.test_handshakes.TestHandshakeManifest
#python3 -m unittest tests.stub.transport.test_transport.TestTransport

#tx_begin_parameters
#python3 -m unittest tests.stub.tx_begin_parameters.test_tx_begin_parameters.TestTxBeginParameters

#tx_lifetime
#python3 -m unittest tests.stub.tx_lifetime.test_tx_lifetime.TestTxLifetime #12 errors

#tx_run
python3 -m unittest tests.stub.tx_run.test_tx_run.TestTxRun

#types
#python3 -m unittest tests.stub.types.test_temporal_types._TestTemporalTypes
#python3 -m unittest tests.stub.types.test_temporal_types.TestTemporalTypesV3x0
#python3 -m unittest tests.stub.types.test_temporal_types.TestTemporalTypesV4x2
#python3 -m unittest tests.stub.types.test_temporal_types.TestTemporalTypesV4x3
#python3 -m unittest tests.stub.types.test_temporal_types.TestTemporalTypesV4x4
#python3 -m unittest tests.stub.types.test_temporal_types.TestTemporalTypesV5x0
#python3 -m unittest tests.stub.types.test_temporal_types.TestTemporalTypesV4x3
#python3 -m unittest tests.stub.types.test_temporal_types.TestTemporalTypesV4x4


#versions
#python3 -m unittest tests.stub.versions.test_versions.TestProtocolVersions #1 ERROR




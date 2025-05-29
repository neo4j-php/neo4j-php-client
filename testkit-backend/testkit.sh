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
python3 -m unittest tests.tls.test_unsecure_scheme.TestUnsecureScheme.test_secure_server

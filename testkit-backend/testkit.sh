#!/bin/bash

export TEST_NEO4J_HOST=localhost
export TEST_NEO4J_USER=neo4j
export TEST_NEO4J_PASS=test
export TEST_DRIVER_NAME=php

cd ../../testkit || (echo 'cannot cd into testkit' && exit)
exec python3 -m unittest -v "$@"

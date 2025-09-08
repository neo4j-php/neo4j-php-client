#!/usr/bin/env bash

docker compose up -d testkit_backend
docker compose logs -f testkit_backend

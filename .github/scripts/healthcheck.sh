#!/bin/bash
set -e

echo "$(date): Attempting health check..."
HEALTH_STATUS=$(curl -s -H "Authorization: Basic bmVvNGo6dGVzdHRlc3Q=" localhost:7474/db/system/cluster/status | tee /proc/1/fd/1 | grep -oP '"healthy":\s*\K[^,}]*' | sed 's/"//g')

if [ "$HEALTH_STATUS" = "true" ]; then
    echo "$(date): Neo4j is healthy!"
    exit 0
else
    echo "$(date): Neo4j is not healthy yet."
    exit 1
fi

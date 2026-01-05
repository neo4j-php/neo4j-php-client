#!/bin/bash

# Validate that testkit commit matches the pinned version in parent repo
# This prevents running tests with mismatched testkit commits
# Handles both local and Docker environments

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR/.."

if [ -f "/.dockerenv" ]; then
    git config --global --add safe.directory "$PROJECT_ROOT" 2>/dev/null || true
    git config --global --add safe.directory "$PROJECT_ROOT/testkit" 2>/dev/null || true
fi

# Check if testkit directory exists
if [ ! -d "$PROJECT_ROOT/testkit" ]; then
    echo " ERROR: testkit directory not found"
    echo "   Initialize with: cd testkit-backend && ./testkit.sh"
    exit 1
fi

EXPECTED_COMMIT=$(cd "$PROJECT_ROOT" && git ls-tree HEAD testkit 2>/dev/null | awk '{print $3}' || echo "")

if [ -z "$EXPECTED_COMMIT" ]; then
    if [ -f "$PROJECT_ROOT/.gitmodules" ]; then
        EXPECTED_COMMIT=$(cd "$PROJECT_ROOT" && git config -f .gitmodules --get submodule.testkit.path > /dev/null 2>&1 && git rev-parse HEAD:testkit 2>/dev/null || echo "")
    fi

    if [ -z "$EXPECTED_COMMIT" ]; then
        echo "  WARNING: Could not determine expected testkit commit"
        echo "   Skipping testkit validation (testkit may not be a submodule)"
        exit 0
    fi
fi

ACTUAL_COMMIT=$(cd "$PROJECT_ROOT/testkit" && git rev-parse HEAD 2>/dev/null || echo "UNKNOWN")

if [ -n "$EXPECTED_COMMIT" ] && [ "$EXPECTED_COMMIT" != "UNKNOWN" ] && [ "$ACTUAL_COMMIT" != "UNKNOWN" ]; then
    if [ "$EXPECTED_COMMIT" != "$ACTUAL_COMMIT" ]; then
        echo " ERROR: Testkit commit mismatch!"
        echo "   Expected commit: $EXPECTED_COMMIT"
        echo "   Actual commit:   $ACTUAL_COMMIT"
        echo ""
        echo "   Fix this by running:"
        echo "   cd testkit && git fetch && git checkout $EXPECTED_COMMIT && cd -"
        echo ""
        echo "   Or to re-initialize testkit:"
        echo "   cd testkit-backend && ./testkit.sh --clean"
        exit 1
    fi
fi

if [ -n "$EXPECTED_COMMIT" ] && [ "$EXPECTED_COMMIT" != "UNKNOWN" ]; then
    echo "✅ Testkit commit validated (${ACTUAL_COMMIT:0:7})"
else
    echo "✅ Testkit validation passed (running in Docker or testkit not a submodule)"
fi

exit 0

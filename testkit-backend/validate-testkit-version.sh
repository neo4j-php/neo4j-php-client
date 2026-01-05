#!/bin/bash

# Validate that testkit is a git submodule with correct commit
# This prevents running tests with mismatched testkit versions

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR/.."

# Docker environment: configure git safe directory
if [ -f "/.dockerenv" ]; then
    git config --global --add safe.directory "$PROJECT_ROOT" 2>/dev/null || true
    git config --global --add safe.directory "$PROJECT_ROOT/testkit" 2>/dev/null || true
fi

if [ ! -d "$PROJECT_ROOT/testkit" ]; then
    echo " ERROR: testkit directory not found"
    echo "   Initialize with: cd testkit-backend && ./testkit.sh"
    exit 1
fi

EXPECTED_COMMIT=$(cd "$PROJECT_ROOT" && git ls-tree HEAD testkit 2>/dev/null | awk '{print $3}' || echo "")

if [ -z "$EXPECTED_COMMIT" ]; then
    echo " ERROR: Could not determine expected testkit commit"
    echo "   testkit must be configured as a git submodule"
    echo "   Ensure .gitmodules is properly configured and run:"
    echo "   cd $PROJECT_ROOT && git submodule add https://github.com/neo4j-drivers/testkit.git testkit"
    exit 1
fi

ACTUAL_COMMIT=$(cd "$PROJECT_ROOT/testkit" && git rev-parse HEAD 2>/dev/null || echo "UNKNOWN")

if [ "$ACTUAL_COMMIT" = "UNKNOWN" ]; then
    echo " ERROR: Could not read testkit commit (git rev-parse failed)"
    echo "   Testkit may be corrupted. Try re-initializing:"
    echo "   cd testkit-backend && ./testkit.sh --clean"
    exit 1
fi

TESTKIT_STATUS=$(cd "$PROJECT_ROOT/testkit" && git status --porcelain 2>/dev/null | wc -l)
if [ "$TESTKIT_STATUS" -gt 0 ]; then
    echo "  WARNING: testkit has uncommitted changes!"
    echo ""
    echo "   Status of testkit:"
    cd "$PROJECT_ROOT/testkit" && git status --short | sed 's/^/      /'
    echo ""
    echo "   This may cause inconsistent test results. Consider:"
    echo "   1. Committing changes if they're intentional:"
    echo "      cd $PROJECT_ROOT/testkit && git add . && git commit -m 'message'"
    echo "   2. Discarding changes if they're temporary:"
    echo "      cd $PROJECT_ROOT/testkit && git checkout . && git clean -fd"
    echo ""
fi

if [ "$EXPECTED_COMMIT" != "$ACTUAL_COMMIT" ]; then
    echo " ERROR: Testkit commit mismatch!"
    echo "   Expected commit: $EXPECTED_COMMIT"
    echo "   Actual commit:   $ACTUAL_COMMIT"
    echo ""

    if [ -n "$CI" ] || [ -n "$GITHUB_ACTIONS" ] || [ -n "$GITLAB_CI" ] || [ -n "$CIRCLECI" ]; then
        echo "     Running in CI environment - attempting automatic submodule update..."
        git submodule update --checkout 2>/dev/null || true

        ACTUAL_COMMIT=$(cd "$PROJECT_ROOT/testkit" && git rev-parse HEAD 2>/dev/null || echo "UNKNOWN")
        if [ "$EXPECTED_COMMIT" = "$ACTUAL_COMMIT" ]; then
            echo "    Submodule updated successfully!"
            echo "   Testkit is now at: ${ACTUAL_COMMIT:0:7}"
            exit 0
        fi
    fi

    echo "   Fix this by running one of:"
    echo ""
    echo "   1. Update the submodule to the pinned commit:"
    echo "      cd $PROJECT_ROOT && git submodule update --checkout"
    echo ""
    echo "   2. Re-initialize testkit completely:"
    echo "      cd testkit-backend && ./testkit.sh --clean"
    echo ""
    echo "   3. Manually update to the expected commit:"
    echo "      cd $PROJECT_ROOT/testkit && git fetch && git checkout $EXPECTED_COMMIT && cd -"
    exit 1
fi

echo " Testkit commit validated (${ACTUAL_COMMIT:0:7})"
exit 0

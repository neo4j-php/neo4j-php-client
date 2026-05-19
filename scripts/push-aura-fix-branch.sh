#!/usr/bin/env bash
# Push fix/aura-database-routing to the private remote (run from repo root).
set -euo pipefail

REMOTE="${1:-nagels}"
BRANCH="fix/aura-database-routing"

echo "Remote: ${REMOTE}"
echo "Branch: ${BRANCH}"
git status -sb

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo ""
    echo "You have uncommitted changes. Commit first, for example:"
    echo "  git add src/Neo4j/HomeDatabaseCache.php src/Neo4j/Neo4jConnectionPool.php src/Bolt/Session.php src/Databags/SessionConfiguration.php"
    echo "  git commit -m \"Fix Aura routing and DatabaseNotFound on writeTransaction\""
    exit 1
fi

git push -u "${REMOTE}" "${BRANCH}"
echo ""
echo "Done. In Laravel composer.json use:"
echo "  \"laudis/neo4j-php-client\": \"dev-fix/aura-database-routing as 3.4.4\""

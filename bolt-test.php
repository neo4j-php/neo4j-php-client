<?php

/**
 * Minimal Bolt driver smoke test (README example).
 *
 * Uses .env when present (Aura: StreamSocket + SSL). Otherwise localhost via Socket.
 *
 *   cp .env.example .env   # for Aura
 *   php bolt-test.php
 */

declare(strict_types=1);

use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$envPath = __DIR__ . '/.env';

if (is_file($envPath)) {
    (new Dotenv())->load($envPath);
} else {
    fwrite(STDERR, "WARNING: .env not found at {$envPath}\n");
}

$uri = trim((string) ($_ENV['NEO4J_URI'] ?? $_SERVER['NEO4J_URI'] ?? getenv('NEO4J_URI') ?: ''));

if ($uri === '') {
    $uri = trim((string) ($_ENV['CONNECTION'] ?? $_SERVER['CONNECTION'] ?? getenv('CONNECTION') ?: ''));
}

if ($uri === '') {
    fwrite(STDERR, "ERROR: NEO4J_URI is empty/unset\n");
    fwrite(STDERR, "Check {$envPath} has an uncommented line: NEO4J_URI=neo4j+s://...\n");
    fwrite(STDERR, "Quote the value if it contains ? or #: NEO4J_URI=\"neo4j+s://...?database=neo4j\"\n");
    exit(2);
}

/*
|--------------------------------------------------------------------------
| ENV DEBUG OUTPUT
|--------------------------------------------------------------------------
*/

echo "\n=== ENV DEBUG ===\n";

echo "BASE URI: ";
var_dump($_ENV['NEO4J_URI'] ?? null);

echo "USERNAME: ";
var_dump($_ENV['NEO4J_USERNAME'] ?? null);

echo "PASSWORD LENGTH: ";
var_dump(strlen($_ENV['NEO4J_PASSWORD'] ?? ''));

echo "DATABASE: ";
var_dump($_ENV['NEO4J_DATABASE'] ?? null);

echo "\n=== FINAL URI ===\n";
var_dump($uri);

echo "\n==================\n";

// Aura often uses a non-default database name. If you don't pass the right DB,
// Aura can reply to ROUTE without a routing table and some client versions crash.

$parts = parse_url($uri);
parse_str($parts['query'] ?? '', $uriQuery);

$database = $_ENV['NEO4J_DATABASE'] ?? ($uriQuery['database'] ?? null);

if ($database === null || $database === '') {
    $host = $parts['host'] ?? '';
    $user = $parts['user'] ?? '';

    // Aura Free (legacy): DB name is often the instance id, not "neo4j".
    if (
        $user !== ''
        && $user !== 'neo4j'
        && preg_match('/\.databases\.neo4j\.io$/', $host) === 1
    ) {
        $database = $user;
        fwrite(STDERR, "INFO: inferring database from Bolt principal: {$database}\n");
    }
}

echo "\nURI ?database= (driver reads this via SessionConfiguration::fromUri): ";
var_export($uriQuery['database'] ?? null);
echo "\nSESSION DATABASE (script override, else driver default): ";
var_export($database);
echo "\n";

$logger = new Logger('neo4j');

$logger->pushHandler(
    new Monolog\Handler\StreamHandler(
        'php://stdout',
        Logger::DEBUG
    )
);

$config = DriverConfiguration::default()
    ->withLogger(LogLevel::DEBUG, $logger);

$driver = Driver::create($uri, $config);

// Driver resolves database from ?database=, explicit withDatabase(), or Aura Free principal+hostname.
$session = $driver->createSession(
    $database !== null && $database !== ''
        ? SessionConfiguration::default()->withDatabase($database)
        : null
);

$session->run('RETURN 1');

echo "OK: RETURN 1 succeeded\n";


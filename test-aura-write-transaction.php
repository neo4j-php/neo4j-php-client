<?php

/**
 * Aura regression script for managed transactions (writeTransaction / readTransaction).
 *
 * Mirrors a typical Laravel service-provider setup:
 *   ClientBuilder + SessionConfiguration::default()->withDatabase($instanceId)
 *
 * Usage: php test-aura-write-transaction.php
 *
 * Requires .env: NEO4J_URI, NEO4J_USERNAME, NEO4J_PASSWORD, NEO4J_DATABASE (Aura instance id)
 */

require 'vendor/autoload.php';

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Neo4j\HomeDatabaseCache;

/**
 * @param array<string, string> $env
 */
function loadEnvFile(string $path, array &$env = []): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        $env[$name] = $value;
        $_ENV[$name] = $value;
        putenv($name.'='.$value);
    }
}

/**
 * Same pattern as Laravel execute(): run() or managed transaction by write flag.
 */
function execute(ClientInterface $client, Statement $statement, bool $isWrite): mixed
{
    if (!$isWrite && preg_match('/\b(CREATE|MERGE|DELETE|SET|REMOVE)\b/i', $statement->getText())) {
        $isWrite = true;
    }

    if (!$isWrite) {
        return $client->run(
            $statement->getText(),
            $statement->getParameters()
        );
    }

    $query = $statement->getText();
    $params = $statement->getParameters();

    return $client->writeTransaction(
        static function (TransactionInterface $tsx) use ($query, $params) {
            return $tsx->run($query, $params);
        }
    );
}

function pass(string $label): void
{
    echo "PASS  {$label}\n";
}

function fail(string $label, Throwable $e): void
{
    echo "FAIL  {$label}\n";
    echo "      {$e->getMessage()}\n";
}

function section(string $title): void
{
    echo "\n=====================================\n";
    echo "{$title}\n";
    echo "=====================================\n";
}

loadEnvFile(__DIR__.'/.env');

$uri = $_ENV['NEO4J_URI'] ?? null;
$username = $_ENV['NEO4J_USERNAME'] ?? null;
$password = $_ENV['NEO4J_PASSWORD'] ?? null;
$database = $_ENV['NEO4J_DATABASE'] ?? null;

if (!is_string($uri) || !is_string($username) || !is_string($password)) {
    fwrite(STDERR, "Missing NEO4J_URI, NEO4J_USERNAME, or NEO4J_PASSWORD in .env\n");
    exit(1);
}

$auraHost = parse_url($uri, PHP_URL_HOST);
if (is_string($auraHost)) {
    HomeDatabaseCache::clear($auraHost);
}

$label = 'AuraWriteTxTest_'.bin2hex(random_bytes(4));

section('Configuration');
echo "URI: {$uri}\n";
echo "Database: ".($database ?? '(not set)')."\n";
echo "Test label: {$label}\n";

$timeout = (int) ($_ENV['NEO4J_TIMEOUT'] ?? 300);
$fetchSize = (int) ($_ENV['NEO4J_FETCH_SIZE'] ?? 1000);

$sessionConfig = SessionConfiguration::default()
    ->withFetchSize($fetchSize);

if ($database !== null && $database !== '') {
    $sessionConfig = $sessionConfig->withDatabase($database);
}

$client = ClientBuilder::create()
    ->withDriver('default', $uri, Authenticate::basic($username, $password))
    ->withDefaultTransactionConfiguration(
        TransactionConfiguration::default()->withTimeout($timeout)
    )
    ->withDefaultSessionConfiguration($sessionConfig)
    ->build();

$failed = 0;

section('Connectivity');
try {
    if ($client->verifyConnectivity() !== true) {
        throw new RuntimeException('verifyConnectivity() returned false');
    }
    pass('verifyConnectivity()');
} catch (Throwable $e) {
    fail('verifyConnectivity()', $e);
    exit(1);
}

section('writeTransaction() — CREATE');
try {
    $result = $client->writeTransaction(
        static function (TransactionInterface $tsx) use ($label) {
            return $tsx->run(
                'CREATE (n:AuraWriteTxTest {label: $label}) RETURN n.label AS label',
                ['label' => $label]
            );
        }
    );
    $created = $result->first()->get('label');
    if ($created !== $label) {
        throw new RuntimeException("Expected label {$label}, got {$created}");
    }
    pass('writeTransaction CREATE');
} catch (Throwable $e) {
    fail('writeTransaction CREATE', $e);
    ++$failed;
}

section('readTransaction() — MATCH');
try {
    $result = $client->readTransaction(
        static function (TransactionInterface $tsx) use ($label) {
            return $tsx->run(
                'MATCH (n:AuraWriteTxTest {label: $label}) RETURN count(n) AS c',
                ['label' => $label]
            );
        }
    );
    $count = $result->first()->get('c');
    if ((int) $count < 1) {
        throw new RuntimeException("Expected at least 1 node, got {$count}");
    }
    pass("readTransaction MATCH (count={$count})");
} catch (Throwable $e) {
    fail('readTransaction MATCH', $e);
    ++$failed;
}

section('run() — read');
try {
    $result = $client->run(
        'MATCH (n:AuraWriteTxTest {label: $label}) RETURN count(n) AS c',
        ['label' => $label]
    );
    $count = $result->first()->get('c');
    if ((int) $count < 1) {
        throw new RuntimeException("Expected at least 1 node, got {$count}");
    }
    pass("run() read (count={$count})");
} catch (Throwable $e) {
    fail('run() read', $e);
    ++$failed;
}

section('execute() helper — write via writeTransaction');
try {
    $result = execute(
        $client,
        new Statement(
            'MATCH (n:AuraWriteTxTest {label: $label}) SET n.checked = true RETURN n.checked AS checked',
            ['label' => $label]
        ),
        true
    );
    if ($result->first()->get('checked') !== true) {
        throw new RuntimeException('SET did not persist checked=true');
    }
    pass('execute() write path');
} catch (Throwable $e) {
    fail('execute() write path', $e);
    ++$failed;
}

section('execute() helper — read via run()');
try {
    $result = execute(
        $client,
        new Statement(
            'MATCH (n:AuraWriteTxTest {label: $label}) RETURN n.checked AS checked',
            ['label' => $label]
        ),
        false
    );
    if ($result->first()->get('checked') !== true) {
        throw new RuntimeException('Expected checked=true after SET');
    }
    pass('execute() read path');
} catch (Throwable $e) {
    fail('execute() read path', $e);
    ++$failed;
}

section('Cleanup');
try {
    $deleted = $client->writeTransaction(
        static function (TransactionInterface $tsx) use ($label) {
            return $tsx->run(
                'MATCH (n:AuraWriteTxTest {label: $label}) DETACH DELETE n RETURN count(n) AS deleted',
                ['label' => $label]
            );
        }
    );
    pass('cleanup DETACH DELETE (deleted='.$deleted->first()->get('deleted').')');
} catch (Throwable $e) {
    fail('cleanup', $e);
    ++$failed;
}

section('Summary');
if ($failed === 0) {
    echo "All tests passed.\n";
    exit(0);
}

echo "{$failed} test(s) failed.\n";
exit(1);

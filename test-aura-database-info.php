<?php

require 'vendor/autoload.php';

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Databags\SessionConfiguration;
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

loadEnvFile(__DIR__.'/.env');

$uri = $_ENV['NEO4J_URI'];
$username = $_ENV['NEO4J_USERNAME'];
$password = $_ENV['NEO4J_PASSWORD'];

echo "=====================================\n";
echo "Aura Connection Information\n";
echo "=====================================\n";

echo "URI: {$uri}\n";
echo "Username: {$username}\n";
echo "ENV Database: " . ($_ENV['NEO4J_DATABASE'] ?? 'not set') . "\n";

$auraHost = parse_url($uri, PHP_URL_HOST);
if (is_string($auraHost)) {
    HomeDatabaseCache::clear($auraHost);
}

echo "\n=====================================\n";
echo "TEST 1 - Basic Connectivity\n";
echo "=====================================\n";

try {

    $client = ClientBuilder::create()
        ->withDriver(
            'default',
            $uri,
            Authenticate::basic($username, $password)
        )
        ->withDefaultSessionConfiguration(
            SessionConfiguration::default()
                ->withDatabase($_ENV['NEO4J_DATABASE'] ?? null)
        )
        ->build();

    var_dump($client->verifyConnectivity());

} catch (Throwable $e) {

    echo "Connectivity FAILED:\n";
    echo $e->getMessage() . "\n";

    exit(1);
}

echo "\n=====================================\n";
echo "TEST 2 - Simple Query Without Explicit DB\n";
echo "=====================================\n";

try {

    $result = $client->run('RETURN 1 AS test');

    foreach ($result as $record) {
        var_dump($record);
    }

} catch (Throwable $e) {

    echo "Simple query FAILED:\n";
    echo $e->getMessage() . "\n";
}

echo "\n=====================================\n";
echo "TEST 3 - Database Info\n";
echo "=====================================\n";

try {

    $result = $client->run('CALL db.info()');

    foreach ($result as $record) {
        var_dump($record);
    }

} catch (Throwable $e) {

    echo "db.info() FAILED:\n";
    echo $e->getMessage() . "\n";
}

echo "\n=====================================\n";
echo "TEST 4 - SHOW DATABASES\n";
echo "=====================================\n";

try {

    $result = $client->run('SHOW DATABASES');

    foreach ($result as $record) {
        var_dump($record);
    }

} catch (Throwable $e) {

    echo "SHOW DATABASES FAILED:\n";
    echo $e->getMessage() . "\n";
}

echo "\n=====================================\n";
echo "TEST 5 - Explicit database = ENV value\n";
echo "=====================================\n";

try {

    $explicitDbClient = ClientBuilder::create()
        ->withDriver(
            'default',
            $uri,
            Authenticate::basic($username, $password)
        )
        ->withDefaultSessionConfiguration(
            SessionConfiguration::default()
                ->withDatabase($_ENV['NEO4J_DATABASE'])
        )
        ->build();

    $result = $explicitDbClient->run(
        'RETURN "explicit env db works" AS message'
    );

    foreach ($result as $record) {
        var_dump($record);
    }

} catch (Throwable $e) {

    echo "Explicit ENV DB FAILED:\n";
    echo $e->getMessage() . "\n";
}

echo "\n=====================================\n";
echo "TEST 6 - Explicit database = neo4j\n";
echo "=====================================\n";

try {

    $neo4jClient = ClientBuilder::create()
        ->withDriver(
            'default',
            $uri,
            Authenticate::basic($username, $password)
        )
        ->withDefaultSessionConfiguration(
            SessionConfiguration::default()
                ->withDatabase('neo4j')
        )
        ->build();

    $result = $neo4jClient->run(
        'RETURN "neo4j db works" AS message'
    );

    foreach ($result as $record) {
        var_dump($record);
    }

} catch (Throwable $e) {

    echo "Explicit neo4j DB FAILED:\n";
    echo $e->getMessage() . "\n";
}

echo "\n=====================================\n";
echo "DONE\n";
echo "=====================================\n";

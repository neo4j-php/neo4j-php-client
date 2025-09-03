<?php
require_once 'vendor/autoload.php';

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Authentication\Authenticate;

$client = ClientBuilder::create()
    ->withDriver('bolt', 'bolt://localhost:7687',
        Authenticate::basic('neo4j', 'testtest')
    )
    ->build();

try {
    $result = $client->run('RETURN "Hello World" as message');
    echo "Connection successful!\n";
    print_r($result->first()->get('message'));
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>

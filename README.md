# Laudis Neo4j PHP Client

[![Latest Stable Version](https://poser.pugx.org/laudis/neo4j-php-client/v)](//packagist.org/packages/laudis/neo4j-php-client)
[![Total Downloads](https://poser.pugx.org/laudis/neo4j-php-client/downloads)](//packagist.org/packages/laudis/neo4j-php-client)
[![Monthly Downloads](https://poser.pugx.org/laudis/neo4j-php-client/d/monthly)](//packagist.org/packages/laudis/neo4j-php-client)
[![Maintainability](https://api.codeclimate.com/v1/badges/275c2269aa54c2c43210/maintainability)](https://codeclimate.com/github/laudis-technologies/neo4j-php-client/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/275c2269aa54c2c43210/test_coverage)](https://codeclimate.com/github/laudis-technologies/neo4j-php-client/test_coverage)
[![MIT License](https://img.shields.io/apm/l/atomic-design-ui.svg?)](https://github.com/laudis-technologies/neo4j-php-client/blob/main/LICENSE)

## Installation

Install via composer:

```bash
composer require laudis/neo4j-php-client
```

The HTTP protocol requires [psr-7](https://www.php-fig.org/psr/psr-7/), [psr-17](https://www.php-fig.org/psr/psr-17/) and [psr-18](https://www.php-fig.org/psr/psr-18/) implementations. If there are not any available, composer can install them.

```bash
composer require nyholm/psr7 nyholm/psr7-server kriswallsmith/buzz
```

## General usage

### Initializing client

```php
$client = Laudis\Neo4j\ClientBuilder::create()
    ->addHttpConnection('backup', 'http://neo4j:password@localhost')
    ->addBoltConnection('default', 'bolt://neo4j:password@localhost')
    ->setDefaultConnection('default')
    ->build();
```

The default connection is the first registered connection. `setDefaultConnection` overrides this behaviour.

### Sending a Cypher Query

Sending a query is done by sending the cypher with optional parameters and a connection alias.

```php
$client->run(
    'MERGE (user {email: $email})', //The query is a required parameter
    ['email' => 'abc@hotmail.com'],  //Parameters can be optionally added
    'backup' //The default connection can be overridden
);
```

Or by using a statement object.

```php
use Laudis\Neo4j\Databags\Statement;

$statement = new Statement('MERGE (user {email: $email})', ['email' => 'abc@hotmail.com']);
$client->runStatement($statement, 'default');
```

### Reading a Result

A result is a simple vector, with hashmaps representing a record.

```php
foreach ($client->run('UNWIND range(1, 9) as x RETURN x') as $item) {
    echo $item->get('x');
}
```
will echo `123456789`.

The Map representing the Record can only contain null, scalar or array values. Each array can then only contain null, scalar or array values, ad infinitum.

## Example project

An example project exists on the [neo4j github](https://github.com/neo4j-examples/movies-neo4j-php-client). It uses Slim and neo4j-php-client to build an api for the classic movies example of neo4j.

## Diving Deeper

### Running multiple queries at once

The `runStatements` method will run all the statements at once. This method is an essential tool to reduce the number of database calls.

```php
use Laudis\Neo4j\Databags\Statement;

$results = $client->runStatements([
    Statement::create('MATCH (x) RETURN x LIMIT 100'),
    Statement::create('MERGE (x:Person {email: $email})', ['email' => 'abc@hotmail.com'])
]);
```

The returned value is a vector containing result vectors.

```php
$results->first(); //Contains the first result vector
$results->get(0); //Contains the first result vector
$result->get(1); //Contains the second result vector
```

### Opening a transaction

The `openTransaction` method will start a transaction over the relevant connection.

```php
use Laudis\Neo4j\Databags\Statement;

$tsx = $client->openTransaction(
    // This is an optional set of statements to execute while opening the transaction
    [Statement::create('MERGE (x:Person({email: $email})', ['email' => 'abc@hotmail.com'])],
    'backup' // This is the optional connection alias
);
```

**Note that `openTransaction` only returns the transaction object, not the results of the provided statements.**

The transaction can run statements just like the client object as long as it is still open.

```php
$result = $tsx->run('MATCH (x) RETURN x LIMIT 100');
$result = $tsx->runStatement(Statement::create('MATCH (x) RETURN x LIMIT 100'));
$results = $tsx->runStatements([Statement::create('MATCH (x) RETURN x LIMIT 100')]);
```

They can be committed or rolled back at will:

```php
$tsx->rollback();
```

```php
$tsx->commit([Statement::create('MATCH (x) RETURN x LIMIT 100')]);
```

### Differentiating between parameter type

Cypher has lists and maps. This notion can be problematic as the standard php arrays encapsulate both. When you provide an empty array as a parameter, it will be impossible to determine if it is an empty list or map.

The `ParameterHelper` class is the ideal companion for this:

```php
use Laudis\Neo4j\ParameterHelper;

$client->run('MATCH (x) WHERE x.slug in $listOrMap RETURN x', ['listOrMap' => ParameterHelper::asList([])]); // will return an empty set
$client->run('MATCH (x) WHERE x.slug in $listOrMap RETURN x', ['listOrMap' => ParameterHelper::asMap([])]); // will error
$client->run('MATCH (x) WHERE x.slug in $listOrMap RETURN x', ['listOrMap' => []]); // will return an empty set
```

This helper can also be used to make intent explicit.

### Providing custom injections

`addHttpConnection` and `addBoltConnection` each accept their respective injections.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Laudis\Neo4j\Network\Bolt\BoltInjections;
use Laudis\Neo4j\Network\Http\HttpInjections;

$client = Laudis\Neo4j\ClientBuilder::create()
    ->addHttpConnection('backup', 'http://neo4j:password@localhost', HttpInjections::create()->withClient(static function () {
        $handler = HandlerStack::create(new CurlHandler());
        $handler->push(Middleware::cookies());
        return new Client(['handler' => $handler]);
    }))
    ->addBoltConnection('default', 'neo4j:password@localhost', BoltInjections::create()->withDatabase('tags'))
    ->build();
```

Wrapping the injections in a callable will enforce lazy initialization.

### Clusters and routing

Cluster support is always available when directly connecting to the responsible servers. If you do not need to fine tune these connections, autorouting is available for all connections:

```php
use Laudis\Neo4j\Network\Bolt\BoltInjections;
use Laudis\Neo4j\Network\Http\HttpInjections;

$client = Laudis\Neo4j\ClientBuilder::create()
    ->addBoltConnection(
        'main',
        'bolt://neo4j:password@any-server-with-routing-information',
         BoltInjections::create()->withAutoRouting(true)
     )
    ->addHttpConnection(
        'http',
        'http://neo4j:password@any-server-with-routing-information',
         HttpInjections::create()->withAutoRouting(true)
     )
    ->addBoltConnection('backup', 'bolt://neo4j:password@non-replicated-server')
    ->setDefaultConnection('main')
    ->build();

$client->run('RETURN 1'); // will run over a random follower found in the routing table
$client->run('MERGE (x:Y {z: 0)'); //will run over a random leader found in the routing table
$client->run('RETURN 1', [], 'backup'); //will run over the non replicated server
$client->run('RETURN 1', [], 'main'); //will be autorouted
$client->run('RETURN 1', [], 'http'); //will be autorouted
```

## Final Remarks

### Filosophy

This client tries to strike a balance between extensibility, performance and clean code. All elementary classes have an interface. These provide infinite options to extend or change the implementation.

This library does not use any custom result classes but uses php-ds instead. These data structures are competent, flexible and fast. It furthermore provides a consistent interface and works seamlessly with other iterables.

Flexibility is maintained where possible by making all parameters iterables if they are a container of sorts. This means you can pass parameters as an array, \Ds\Map or any other object which implements the \Iterator or \IteratorAggregate. These examples are all valid:

```php
use Ds\Map;

// Vanilla flavour
$client->run('MATCH (x {slug: $slug})', ['slug' => 'a']);
// php-ds implementation
$client->run('MATCH (x {slug: $slug})', new Map(['slug' => 'a']));
// laravel style
$client->run('MATCH (x {slug: $slug})', collect(['slug' => 'a']));
```

### Neo4j Version Support

| **Version** | **Tested**  |
|-------------|-------------|
| 3.0 +       |   Yes       |
| 4.0 +       |   Yes       |

### Neo4j Feature Support

| **Feature**          | **Supported?** |
|----------------------|----------------|
| Auth                 |  Yes           |
| Transactions         |  Yes           |
| Http Protocol        |  Yes           |
| Bolt Protocol        |  Yes           |
| Cluster              |  Yes           |
| Graph Representation |  Roadmap       |

## Requirements

* PHP >= 7.4
* A Neo4j database (minimum version 2.3)
* ext-bcmath *
* ext-sockets *
* ext-json **
* ext-ds ***

(*) Needed to implement the bolt protocol

(**) Needed to implement the http protocol

(***) Needed for optimal performance

## Roadmap

### Support for graph representation instead of simple records

Version 2.0 will have graph representation support. The interface for this is not yet set in stone, but will be somthing akin to this:

```php
$client = $clientBuilder->withGraph($client);
$result = $client->run('MATCH (x:Article) - [:ContainsReferenceTo] -> (y:Article)');

$node = $result->getGraph()->enter(HasLabel::create('Article')->and(HasAttribute::create('slug', 'neo4j-is-awesome')))->first();

foreach ($node->relationships() as $relationship) {
    if (!$relationship->endNode()->relationships()->isEmpty()) {
        echo 'multi level path detected' . "\n";
    }
}
```
### Support for statistics

Neo4j has the option to return statement statistics as well. These will be supported in version 2.0 with an api like this:

```php
// This will create a client which will wrap the results and statistics in a single object.
$client = $clientBuilder->withStatistics($client);
$result = $client->run('MERGE (x:Node {id: $id}) RETURN x.id as id', ['id' => Uuid::v4()]);
echo $result->getStatistics()->getNodesCreated() . "\n"; // will echo 1 or 0.
echo $result->getResults()->first()->get('id') . "\n"; // will echo the id generated by the Uuid::v4() method
```

Statistics aggregate like this:

```php
$results = $client->runStatements([
 Statement::create('MERGE (x:Node {id: $id}) RETURN x', ['id' => Uuid::v4()]),
 Statement::create('MERGE (p:Person {email: $email})', ['email' => 'abc@hotmail.com'])
]);

$total = Statistics::aggregate($results);
echo $total->getNodesCreated(); // will echo 0, 1 or 2.
```

### Result decoration

Statistics and graph representation are ways of decorating a result. They can be chained like this:

```php
$client = $clientBuilder->withGraph($client);
$client = $clientBuilder->withStatistics($client);

$result = $client->run('MATCH (x:Article) RETURN x.slug as slug LIMIT 1');
$statistics = $result->getStatistics();
$graph = $result->getResults()->getGraph();
$records = $result->getResults()->getResults();
```

This way maximum flexibility is guaranteed. Type safety will be enforced by using psalm templates.

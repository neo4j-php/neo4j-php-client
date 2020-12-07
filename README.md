# Laudis Neo4j PHP Client

## Installation and basic usage

### installing

Install via composer:

```bash
composer require laudis/neo4j-php-client
```

If you want to use the http protocol but haven't installed any of the psr7, psr17 and psr18 implementations yet:

```bash
composer require guzzlehttp/guzzle guzzlehttp/psr7 http-interop/http-factory-guzzle
```

### Initializing client

```php
$client = Laudis\Neo4j\ClientBuilder::create()
    ->addHttpConnection('backup', 'http://neo4j:password@localhost')
    ->addBoltConnection('default', 'neo4j:password@localhost')
    ->setDefaultConnection('default')
    ->build();
```

The default connection is the first registered connection, unless it is overridden with the `setDefaultConnection` method.

### Sending a Cypher Query

Sending a query is done by sending the cypher with optional parameters and a connection alias

```php
$client->run(
    'MERGE (user {email: $email})', //The query is a required parameter
    ['email' => 'abc@hotmail.com'],  //Parameters can be optionally added
    'backup' //The default connection can be overridden
);
```

Or by using a statement object

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
will echo `123456789`

The Map representing the Record can only contain null, scalar or array values. Each array can then only contain null, scalar or array values, ad infinitum.

## Diving Deeper

### Running multiple queries at once

You can run multiple statements at once by simple wrapping a bunch of statements in an iterable object and passing it to the `runStatements` method.

```php
use Laudis\Neo4j\Databags\Statement;

$results = $client->runStatements([
    Statement::create('MATCH (x) RETURN x LIMIT 100'),
    Statement::create('MERGE (x:Person {email: $email})', ['email' => 'abc@hotmail.com'])
]);
```

The results of each query will be wrapped in another vector:

```php
$results->first(); //Contain the first result vector
$results->get(0); //Contain the first result vector
$result->get(1); //Contains the second result vector
```

### Opening a transaction

Transactions can be started by opening one with the client.
```php
use Laudis\Neo4j\Databags\Statement;

$tsx = $client->openTransaction(
    // This is an optional set of statements to execute while opening the transaction
    [Statement::create('MERGE (x:Person({email: $email})', ['email' => 'abc@hotmail.com'])],
    'backup' // This is the optional connection alias
);
```

**Note that the optional set of statements will not have their result returned to you, as the transaction will be returned instead**

You can then further execute statements on the transaction.

```php
$result = $tsx->runStatement('MATCH (x) RETURN x LIMIT 100');
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

*Note that the optional set of statements provided while comitting the transaction will not return the results.

### Providing custom injections

Each connection can be configured with custom injections.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Laudis\Neo4j\Network\Bolt\BoltInjections;use Laudis\Neo4j\Network\Http\HttpInjections;

$client = Laudis\Neo4j\ClientBuilder::create()
    ->addHttpConnection('backup', 'http://neo4j:password@localhost', HttpInjections::create()->withClient(static function () {
        $handler = HandlerStack::create(new CurlHandler());
        $handler->push(Middleware::cookies());
        return new Client(['handler' => $handler]);
    }))
    ->addBoltConnection('default', 'neo4j:password@localhost', BoltInjections::create()->withDatabase('tags'))
    ->build();
```

Custom injections can be wrapped around a callable for lazy initialization as can be found in the example above.

## Final Remarks

### Filosophy

This client tries to strike a balance between extensibility, performance and clean code. All classes are marked final but where there is an interface, injections and decorators can be used.

I also chose not to implement custom resultsets but use the php-ds extension or polyfill instead. This is because these datastructures are a lot more capable than I will ever be able to make them. Php ds has a consistent interface, works nicely with psalm, has all features you can really want from a simple container and is incredibly fast.

Flexibility is maintained where possible by making all parameters iterables if they are a container of sorts. This means you can pass parameters as an array, \Ds\Map or any other object which implements the \Iterator or \IteratorAggregate. These are all valid:

```php
// Vanilla flavour
$client->run('MATCH (x {slug: $slug})', ['slug' => 'a']);
// php-ds implementation
$client->run('MATCH (x {slug: $slug})', new \Ds\Map(['slug' => 'a']));
// laravel style
$client->run('MATCH (x {slug: $slug})', collect(['slug' => 'a']));
```

### Neo4j Version Support

| **Version** | **Tested**  |
|-------------|-------------|
| 2.3         |   Yes       |
| 3.0 +       |   Yes       |
| 4.0 +       |   Yes       |

### Neo4j Feature Support

| **Feature**          | **Supported?** |
|----------------------|----------------|
| Auth                 |  Yes           |
| Transactions         |  Yes           |
| Http Protocol        |  Yes           |
| Bolt Protocol        |  Yes           |
| Cluster              |  Roadmap       |
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

### Cluster support

Version 2.0 will have cluster support. The interface for this is not yet set in stone.

### Support for graph representation instead of simple records.

Version 2.0 will have graph representation suppport. The inteface for this is not yet set in stone, but will be somthing akin to this:

```php
$graph = $client->graphOf('MATCH (x:Article) - [:ContainsReferenceTo] -> (y:Article)');

$node = $graph->enter(HasLabel::create('Article')->and(HasAttribute::create('slug', 'neo4j-is-awesome')));

foreach ($node->relationships() as $relationship) {
    if (!$relationship->endNode()->relationships()->isEmpty()) {
        echo 'multi level reference detected' . "\n";
    }
}
```

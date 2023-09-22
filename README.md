# Neo4j PHP Client and Driver

[![GitHub](https://img.shields.io/github/license/neo4j-php/neo4j-php-client)](https://github.com/laudis-technologies/neo4j-php-client/blob/main/LICENSE)
[![Code Climate maintainability](https://img.shields.io/codeclimate/maintainability/laudis-technologies/neo4j-php-client)](https://codeclimate.com/github/laudis-technologies/neo4j-php-client/maintainability)
[![Packagist PHP Version Support (custom server)](https://img.shields.io/packagist/php-v/laudis/neo4j-php-client)](https://packagist.org/packages/laudis/neo4j-php-client)
[![Latest Stable Version](https://poser.pugx.org/laudis/neo4j-php-client/v)](https://packagist.org/packages/laudis/neo4j-php-client)

[![Packagist Downloads](https://img.shields.io/packagist/dt/laudis/neo4j-php-client)](https://packagist.org/packages/laudis/neo4j-php-client/stats)
[![Packagist Downloads](https://img.shields.io/packagist/dm/laudis/neo4j-php-client)](https://packagist.org/packages/laudis/neo4j-php-client/stats)

## Control to worlds' most powerful graph database
- Pick and choose your drivers with easy configuration
- Intuitive API
- Extensible
- Designed, built and tested under close supervision with the official neo4j driver team
- Validated with [testkit](https://github.com/neo4j-drivers/testkit)
- Fully typed with [psalm](https://psalm.dev/)
- Bolt, HTTP and auto routed drivers available

## See the driver in action

 - [An implementation of the class movie database](https://github.com/neo4j-examples/movies-neo4j-php-client). It uses Slim and neo4j-php-client to build an API for the classic movie's example of neo4j.
 - [An complete implementation of the realworld example](https://github.com/neo4j-examples/php-laravel-neo4j-realworld-example). It uses Laravel to implement the [real world example](https://github.com/gothinkster/realworld) project, _the mother of all demo apps_.
 - [The friends api](https://github.com/neo4j-examples/friends-php-client) for the world's most corny example project leveraging the power of Neo4j.

For some more detailed write-ups you can refer to these blogposts:

 - [How to build a JSON RESTful API with Neo4j, PHP and Open API](https://medium.com/neo4j/how-to-build-a-json-restful-api-with-neo4j-php-and-openapi-e45bf0a8956)
 - [Building a Web App with Neo4j, AuraDB and PHP](https://medium.com/neo4j/building-a-web-app-with-neo4j-auradb-and-php-990deca0d213)
 - [Connect to Neo4j with PHP](https://medium.com/neo4j/connect-to-neo4j-with-php-e10e24afedff)
 - [Enterprise level PHP and Neo4j](https://medium.com/neo4j/enterprise-level-php-and-neo4j-e467a789e6b4)

Or watch any of these [videos](https://www.youtube.com/watch?v=qwz5XVtbfSY&list=PL9Hl4pk2FsvViI9wmdDpRS7tZ8V6j4uJs).

## Start your driving experience in three easy steps

### Step 1: install via composer

```bash
composer require laudis/neo4j-php-client
```
Find more details [here](#in-depth-requirements)

### Step 2: create a client

```php
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

$client = ClientBuilder::create()
    ->withDriver('bolt', 'bolt+s://user:password@localhost') // creates a bolt driver
    ->withDriver('https', 'https://test.com', Authenticate::basic('user', 'password')) // creates an http driver
    ->withDriver('neo4j', 'neo4j://neo4j.test.com?database=my-database', Authenticate::oidc('token')) // creates an auto routed driver with an OpenID Connect token
    ->withDefaultDriver('bolt')
    ->build();
```

You have now created a client with **bolt, HTTPS and neo4j drivers**. The default driver that the client will use is **bolt**.

Read more about the URLs and how to use them to configure drivers [here](#in-depth-configuration).

### Step 3: run a transaction

```php
use Laudis\Neo4j\Contracts\TransactionInterface;

$result = $client->writeTransaction(static function (TransactionInterface $tsx) {
    $result = $tsx->run('MERGE (x {y: "z"}:X) return x');
    return $result->first()->get('x')['y'];
});

echo $result; // echos 'z'
```

## Decide how to send your Cypher queries

You can control the driver using three different approaches:
- *Auto committed queries* (easiest and most intuitive)
- *Transaction functions* (most portable)
- *Unmanaged transactions* (for the highest degree of control)


### Auto committed queries

Auto committed queries are the most straightforward and most intuitive but have many drawbacks when running complex business logic or within a high availability environment.

#### Run a simple cypher query

```php
$client->run(
    'MERGE (user {email: $email})', //The query is a required parameter
    ['email' => 'abc@hotmail.com'],  //Requests can be optionally added
    'backup' //The default connection can be overridden
);
```

#### Run a statement object:

```php
use Laudis\Neo4j\Databags\Statement;

$statement = new Statement('MERGE (user {email: $email})', ['email' => 'abc@hotmail.com']);
$client->runStatement($statement, 'default');
```

#### Running multiple queries at once

The `runStatements` method will run all the statements at once. This method is an essential tool to reduce the number of database calls, especially when using the HTTP protocol.

```php
use Laudis\Neo4j\Databags\Statement;

$results = $client->runStatements([
    Statement::create('MATCH (x) RETURN x LIMIT 100'),
    Statement::create('MERGE (x:Person {email: $email})', ['email' => 'abc@hotmail.com'])
]);
```

### Transaction functions

Transaction functions are the **de facto** standard when using the driver. It is the most portable as it is resistant to a lot of the pitfalls when first developing with high availability solutions such as [Neo4j aura](https://neo4j.com/blog/neo4j-aura-enterprise-ga-release/) or a [cluster](https://neo4j.com/docs/operations-manual/current/clustering/).

The driver manages transaction functions:
- It **re-executes** the function in case of a [transient error](https://neo4j.com/docs/status-codes/current/#_classifications).
- It **commits** the transaction on successful execution
- It **rolls back** the transaction in case of a timeout.
- It **routes** the execution to a relevant follower or leader server when the neo4j protocol is enabled.

> ATTENTION: Because of the automatic retry functionality, the function should produce the same result on subsequent recalls, or in more technical terms: should be **idempotent**. Always remember this when designing the execution logic within the function.

Some examples:

```php
use Laudis\Neo4j\Contracts\TransactionInterface;

// Do a simple merge and return the result
$result = $client->writeTransaction(static function (TransactionInterface $tsx) {
    $result = $tsx->run('MERGE (x {y: "z"}:X) return x');
    return $result->first()->get('x')['y'];
});

// Will result in an error
$client->readTransaction(static function (TransactionInterface $tsx) {
    $tsx->run('MERGE (x {y: "z"}:X) return x');
});

// This is a poorly designed transaction function
$client->writeTransaction(static function (TransactionInterface $tsx) use ($externalCounter) {
    $externalCounter->incrementNodesCreated();
    $tsx->run('MERGE (x {y: $id}:X) return x', ['id' => Uuid::v4()]);
});

// This achieves the same effect but is safe in case it should be retried. The function is now idempotent.
$id = Uuid::v4();
$client->writeTransaction(static function (TransactionInterface $tsx) use ($id) {
    $tsx->run('MERGE (x {y: $id}:X) return x', ['id' => $id]);
});
$externalCounter->incrementNodesCreated();
```

### Unmanaged transactions

If you need lower-level access to the drivers' capabilities, then you want unmanaged transactions. They allow for completely controllable commits and rollbacks.

#### Opening a transaction

The `beginTransaction` method will start a transaction with the relevant driver.

```php
use Laudis\Neo4j\Databags\Statement;

$tsx = $client->beginTransaction(
    // This is an optional set of statements to execute while opening the transaction
    [Statement::create('MERGE (x:Person({email: $email})', ['email' => 'abc@hotmail.com'])],
    'backup' // This is the optional connection alias
);
```

> Note that `beginTransaction` only returns the transaction object, not the results of the provided statements.

#### Running statements within a transaction

The transaction can run statements just like the client object as long as it is still open.

```php
$result = $tsx->run('MATCH (x) RETURN x LIMIT 100');
$result = $tsx->runStatement(Statement::create('MATCH (x) RETURN x LIMIT 100'));
$results = $tsx->runStatements([Statement::create('MATCH (x) RETURN x LIMIT 100')]);
```

#### Finish a transaction

Rollback a transaction:

```php
$tsx->rollback();
```

Commit a transaction:

```php
$tsx->commit([Statement::create('MATCH (x) RETURN x LIMIT 100')]);
```

## Accessing the results

Results are returned in a standard format of rows and columns:

```php
// Results are a CypherList
$results = $client->run('MATCH (node:Node) RETURN node, node.id AS id');

// A row is a CypherMap
foreach ($results as $result) {
    // Returns a Node
    $node = $result->get('node');

    echo $node->getProperty('id');
    echo $result->get('id');
}
```

Cypher values and types map to these php types and classes:

| Cypher         | Php                                           |
|----------------|-----------------------------------------------|
| null           | * `null`                                      |
| string         | * `string`                                    |
| integer        | * `int`                                       |
| float          | * `float`                                     |
| boolean        | * `bool`                                      |
| Map            | * `\Laudis\Neo4j\Types\CypherMap`             |
| List           | * `\Laudis\Neo4j\Types\CypherList`            |
| Point          | * `\Laudis\Neo4j\Contracts\PointInterface` ** |
| Date           | * `\Laudis\Neo4j\Types\Date`                  |
| Time           | * `\Laudis\Neo4j\Types\Time`                  |
| LocalTime      | * `\Laudis\Neo4j\Types\LocalTime`             |
| DateTime       | * `\Laudis\Neo4j\Types\DateTime`              |
| DateTimeZoneId | * `\Laudis\Neo4j\Types\DateTimeZoneId`        |
| LocalDateTime  | * `\Laudis\Neo4j\Types\LocalDateTime`         |
| Duration       | * `\Laudis\Neo4j\Types\Duration`              |
| Node           | `\Laudis\Neo4j\Types\Node`                    |
| Relationship   | `\Laudis\Neo4j\Types\Relationship`            |
| Path           | `\Laudis\Neo4j\Types\Path`                    |

(*) These items can also be used as parameters in the bolt protocol and will automatically be converted by the driver, so they can be used in Cypher.

Besides these examples, `\DateTimeInterface` will map to `DateTimeZoneId` in Cypher. An empty or list-type `array` will be converted to a cypher `List`, and an `associative array` will be converted to a `map`.

(**) A point can be one of four types implementing PointInterface: `\Laudis\Neo4j\Types\CartesianPoint` `\Laudis\Neo4j\Types\Cartesian3DPoint` `\Laudis\Neo4j\Types\WGS84Point` `\Laudis\Neo4j\Types\WGS843DPoint`

## Diving Deeper

### Differentiating between parameter type

Cypher has lists and maps. This notion can be problematic as the standard php arrays encapsulate both. When you provide an empty array as a parameter, it will be impossible to determine an empty list or map.

The `ParameterHelper` class is the ideal companion for this:

```php
use Laudis\Neo4j\ParameterHelper;

$client->run('MATCH (x) WHERE x.slug in $listOrMap RETURN x', ['listOrMap' => ParameterHelper::asList([])]); // will return an empty CypherList
$client->run('MATCH (x) WHERE x.slug in $listOrMap RETURN x', ['listOrMap' => ParameterHelper::asMap([])]); // will error
$client->run('MATCH (x) WHERE x.slug in $listOrMap RETURN x', ['listOrMap' => []]); // will return an empty CypherList
```

### Version compatibility matrix

| **Driver Version** | **PHP Version** | **Neo4j Version** |
|--------------------|-----------------|-------------------|
| ^2.8               | 7.4, ^8.0       | ^3.5, ^4.0        |
| ^3.0               | ^8.0            | ^4.0, ^5.0        |

### Neo4j Feature Support

| **Feature**          | **Supported?** |
|----------------------|----------------|
| Authentication       | Yes            |
| Transactions         | Yes            |
| Http Protocol        | Yes            |
| Bolt Protocol        | Yes            |
| Cluster              | Yes            |
| Aura                 | Yes            |
| Jolt Protocol        | Yes            |
| Bookmarks            | Yes            |

## In-depth requirements

* PHP >= 7.4
* A Neo4j database (minimum version 3.5)
* ext-bcmath *
* ext-json **
* ext-sockets ***

(*) Needed to implement the bolt protocol

(**) Needed to implement the http protocol

(***) Can be installed for optimal bolt protocol performance


If you plan on using the HTTP drivers, make sure you have [psr-7](https://www.php-fig.org/psr/psr-7/), [psr-17](https://www.php-fig.org/psr/psr-17/) and [psr-18](https://www.php-fig.org/psr/psr-18/) implementations included into the project. If you don't have any, you can install them via composer:

```bash
composer require nyholm/psr7 nyholm/psr7-server kriswallsmith/buzz
```

## Result formats/hydration

In order to make the results of the bolt protocol and the http uniform, the driver provides result formatters (aka hydrators). The client is configurable with these formatters. You can even implement your own.

The default formatter is the `\Laudis\Neo4j\Formatters\OGMFormatter`, which is explained extensively in [the result format section](#accessing-the-results).

The driver provides three formatters by default, which are all found in the Formatter namespace:
 - `\Laudis\Neo4j\Formatter\BasicFormatter` which erases all the Cypher types and simply returns every value in the resulting map as a [scalar](https://www.php.net/manual/en/function.is-scalar.php), null or array value.
 - `\Laudis\Neo4j\Formatter\OGMFormatter` which maps the cypher types to php types as explained [here](#accessing-the-results).
 - `\Laudis\Neo4j\Formatter\SummarizedResultFormatter` which decorates any formatter and adds an extensive result summary.

The client builder provides an easy way to change the formatter:

```php
$client = \Laudis\Neo4j\ClientBuilder::create()
    ->withFormatter(\Laudis\Neo4j\Formatter\SummarizedResultFormatter::create())
    ->build();

/**
 * The client will now return a result, decorated with a summary.
 *
 * @var \Laudis\Neo4j\Databags\SummarizedResult $results
 */
$summarisedResult = $client->run('MATCH (x) RETURN x');

// The summary contains extensive information such as counters for changed values in the database,
// information on the database, potential notifications, timing, a (profiled) plan, the type of query
// and information on the server itself.
$summary = $summarisedResult->getSummary();
// The result is exactly the same as the default.
$result = $summarisedResult->getResult();
```

In order to use a custom formatter, implement the `Laudis\Neo4j\Contracts\FormatterInterface` and provide it when using the client builder.

## Concepts

The driver API described [here](https://neo4j.com/docs/driver-manual/current/) is the main target of the driver. Because of this, the client is nothing more than a driver manager. The driver creates sessions. A session runs queries through a transaction.

Because of this behaviour, you can access each concept starting from the client like this:

```php
use Laudis\Neo4j\ClientBuilder;

// A builder is responsible for configuring the client on a high level.
$builder = ClientBuilder::create();
// A client manages the drivers as configured by the builder.
$client = $builder->build();
// A driver manages connections and sessions.
$driver = $client->getDriver('default');
// A session manages transactions.
$session = $driver->createSession();
// A transaction is the atomic unit of the driver where are the cypher queries are chained.
$transaction = $session->beginTransaction();
// A transaction runs the actual queries
$transaction->run('MATCH (x) RETURN count(x)');
```

If you need complete control, you can control each object with custom configuration objects.

### Client

A **client** manages **drivers** and routes the queries to the correct drivers based on preconfigured **aliases**.

### Driver

The **driver** object is the thread-safe backbone that gives access to Neo4j. It owns a connection pool and can spawn **sessions** for carrying out work.

```php
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Databags\DriverConfiguration;

$driver = Driver::create(
    uri: 'neo4j://user:mypassword@Localhost:7687',
    configuration: DriverConfiguration::create()->withUserAgent('MyApp/1.0.0')
);
```

### Session

**Sessions** are lightweight containers for causally chained sequences of **transactions**. They borrow **connections** from the connection pool as required and chain transactions using **bookmarks**.

```php
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;

$session = $driver->createSession(SessionConfiguration::create()
    ->withDatabase('my-database')
    ->withAccessMode(AccessMode::READ())
);
```

### Transaction

**Transactions** are atomic units of work that may contain one or more **query**. Each transaction is bound to a single **connection** and is represented in the causal chain by a **bookmark**.

### Statement

**Queries** are executable units within **transactions** and consist of a Cypher string and a keyed parameter set. Each query outputs a **result** that may contain zero or more **records**.

### Result

A **result** contains the output from a **query**, made up of header metadata, content **records** and summary metadata. In Neo4j 4.0 and above, applications have control over the flow of result data.


## In-depth configuration

### Url Schemes

The URL scheme is the easiest way to configure the driver.

Configuration format:
```
'<scheme>://<user>:<password>@<host>:<port>?database=<database>'
```

Default configuration:
```
bolt://localhost:7687?database=neo4j
```

#### Scheme configuration matrix

This library supports three drivers: bolt, HTTP and neo4j. The scheme part of the url determines the driver.

| driver | scheme | valid certificate | self-signed certificate                      | function                      |
|--------|--------|-------------------|----------------------------------------------|-------------------------------|
| neo4j  | neo4j  | neo4j+s           | neo4j+ssc                                    | Client side routing over bolt |
| bolt   | bolt   | bolt+s            | bolt+ssc                                     | Single server over bolt       |
| http   | http   | https             | configured through PSR Client implementation | Single server over HTTP       |

### Configuration objects

A driver, session and transaction can be configured using configuration objects. An overview of the configuration options can be found here:

| name              | concept     | description                                                                      | class                      |
|-------------------|-------------|----------------------------------------------------------------------------------|----------------------------|
| user agent        | driver      | The user agent used to identify the client to the neo4j server.                  | `DriverConfiguration`      |
| Http PSR Bindings | driver      | The relevant PSR implementation used by the driver when using the HTTP protocol. | `DriverConfiguration`      |
| database          | session     | The database to connect to.                                                      | `SessionConfiguration`     |
| fetch size        | session     | The amount of rows to fetch at once.                                             | `SessionConfiguration`     |
| access mode       | session     | The default mode when accessing the server.                                      | `SessionConfiguration`     |
| bookmarks         | session     | The bookmarks used in the session. (experimental)                                | `SessionConfiguration`     |
| metadata          | transaction | The metadata used during the transaction. (experimental)                         | `TransactionConfiguration` |
| timeout           | transaction | The maximum amount of time before timing out.                                    | `TransactionConfiguration` |

Code Example:

```php
use \Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;

$client = \Laudis\Neo4j\ClientBuilder::create()
    ->withDefaultDriverConfiguration(DriverConfiguration::default()->withUserAgent('MyApp/1.0.0'))
    ->withDefaultSessionConfiguration(SessionConfiguration::default()->withDatabase('app-database'))
    ->withDefaultTransactionConfiguration(TransactionConfiguration::default()->withTimeout(5.0))
    ->build();

// The client will run the query on a driver with the provided config,
// which spawns a session with the provided session config
// and runs the query in a transaction with the provided transaction config
$client->run('MATCH (x) RETURN count(x) AS count');

// More granular control can be achieved by requesting the concepts yourself:
$tsx = $client->getDriver('default')
    ->createSession(SessionConfiguration::default()->withDatabase('management-database'))
    ->beginTransaction(null, TransactionConfiguration::default()->withTimeout(200));

$tsx->run('SOME REALLY LONG MANAGEMENT QUERY');

$tsx->commit();
```

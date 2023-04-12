<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Common\Uri;

require __DIR__.'/../vendor/autoload.php';

$connection = $_SERVER['CONNECTION'] ?? 'neo4j://neo4j:testtest@localhost';
$connection = Uri::create($connection);

echo '================================================================================'.PHP_EOL;
echo 'CLEANING DATABASE neo4j OVER CONNECTION: ';
echo $connection->withUserInfo('')->__toString().PHP_EOL;

Driver::create($connection)->createSession()->run('MATCH (x) DETACH DELETE x');

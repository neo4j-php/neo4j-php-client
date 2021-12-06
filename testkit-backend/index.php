<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Laudis\Neo4j\TestkitBackend\Backend;

require_once __DIR__.'/../vendor/autoload.php';

$backend = Backend::boot();
do {
    $backend->handle();
} while (true);

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

use Laudis\Neo4j\TestkitBackend\Actions\GetFeatures;
use Laudis\Neo4j\TestkitBackend\Actions\StartTest;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => static function () {
        $logger = new Logger('testkit-backend');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        return $logger;
    },

    'GetFeatures' => static function () {
        return new GetFeatures();
    },

    'StartTest' => static function () {
        return new StartTest();
    },
];

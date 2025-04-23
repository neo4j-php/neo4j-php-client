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

use Laudis\Neo4j\TestkitBackend\Handlers\GetFeatures;
use Laudis\Neo4j\TestkitBackend\Handlers\StartTest;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => static function () {
        $logger = new Logger('testkit-backend');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        return $logger;
    },

    GetFeatures::class => static function () {
        $featuresConfig = require __DIR__.'/features.php';

        return new GetFeatures($featuresConfig);
    },

    StartTest::class => static function () {
        $acceptedTests = require __DIR__.'/blacklist.php';

        return new StartTest($acceptedTests);
    },

    MainRepository::class => static function () {
        return new MainRepository(
            [],
            [],
            [],
            [],
        );
    },
];

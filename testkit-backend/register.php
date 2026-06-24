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

use Laudis\Neo4j\TestkitBackend\CallbackRegistry;
use Laudis\Neo4j\TestkitBackend\Handlers\GetFeatures;
use Laudis\Neo4j\TestkitBackend\Handlers\StartTest;
use Laudis\Neo4j\TestkitBackend\IdGenerator;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\RequestFactory;
use Laudis\Neo4j\TestkitBackend\Socket;
use Laudis\Neo4j\TestkitBackend\TestkitCallbackDispatcher;
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

    IdGenerator::class => static function () {
        return new IdGenerator();
    },

    CallbackRegistry::class => static function () {
        return new CallbackRegistry();
    },

    Socket::class => static function () {
        return Socket::fromEnvironment();
    },

    RequestFactory::class => static function () {
        return new RequestFactory();
    },

    TestkitCallbackDispatcher::class => static function (Psr\Container\ContainerInterface $c) {
        return new TestkitCallbackDispatcher(
            $c->get(Socket::class),
            $c->get(LoggerInterface::class),
            $c->get(CallbackRegistry::class),
            $c,
            $c->get(RequestFactory::class),
        );
    },
];

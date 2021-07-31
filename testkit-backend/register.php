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

use Ds\Map;
use Laudis\Neo4j\TestkitBackend\Handlers\DriverClose;
use Laudis\Neo4j\TestkitBackend\Handlers\GetFeatures;
use Laudis\Neo4j\TestkitBackend\Handlers\NewDriver;
use Laudis\Neo4j\TestkitBackend\Handlers\NewSession;
use Laudis\Neo4j\TestkitBackend\Handlers\ResultNext;
use Laudis\Neo4j\TestkitBackend\Handlers\SessionClose;
use Laudis\Neo4j\TestkitBackend\Handlers\SessionRun;
use Laudis\Neo4j\TestkitBackend\Handlers\StartTest;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => static function () {
        $logger = new Logger('testkit-backend');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        return $logger;
    },

    'GetFeatures' => static function () {
        $featuresConfig = require __DIR__.'/features.php';

        return new GetFeatures($featuresConfig);
    },

    'StartTest' => static function () {
        $acceptedTests = require __DIR__.'/acceptedTests.php';

        return new StartTest($acceptedTests);
    },

    'NewDriver' => static function (ContainerInterface $container) {
        return new NewDriver($container->get('drivers'));
    },

    'NewSession' => static function (ContainerInterface $container) {
        return new NewSession($container->get('drivers'), $container->get('sessions'));
    },

    'SessionRun' => static function (ContainerInterface $container) {
        return new SessionRun($container->get('sessions'), $container->get('results'));
    },

    'ResultNext' => static function (ContainerInterface $container) {
        return new ResultNext($container->get('results'));
    },

    'SessionClose' => static function (ContainerInterface $container) {
        return new SessionClose($container->get('sessions'));
    },

    'DriverClose' => static function (ContainerInterface $container) {
        return new DriverClose($container->get('drivers'));
    },

    'drivers' => static function () {
        return new Map();
    },

    'sessions' => static function () {
        return new Map();
    },

    'results' => static function () {
        return new Map();
    },
];

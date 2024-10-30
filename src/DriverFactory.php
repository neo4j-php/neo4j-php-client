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

namespace Laudis\Neo4j;

use function in_array;
use function is_string;

use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Http\HttpDriver;
use Laudis\Neo4j\Neo4j\Neo4jDriver;
use Psr\Http\Message\UriInterface;

/**
 * Factory for creating drivers directly.
 *
 * @psalm-import-type OGMResults from OGMFormatter
 */
final class DriverFactory
{
    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return (
     *           func_num_args() is 4
     *           ? DriverInterface<U>
     *           : DriverInterface<OGMResults>
     *           )
     */
    public static function create(string|UriInterface $uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, ?FormatterInterface $formatter = null): DriverInterface
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }
        /** @psalm-suppress ImpureMethodCall Uri is immutable */
        $scheme = $uri->getScheme();
        $scheme = $scheme === '' ? 'bolt' : $scheme;

        if (in_array($scheme, ['bolt', 'bolt+s', 'bolt+ssc'])) {
            return self::createBoltDriver($uri, $configuration, $authenticate, $formatter);
        }

        if (in_array($scheme, ['neo4j', 'neo4j+s', 'neo4j+ssc'])) {
            return self::createNeo4jDriver($uri, $configuration, $authenticate, $formatter);
        }

        return self::createHttpDriver($uri, $configuration, $authenticate, $formatter);
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return (
     *           func_num_args() is 4
     *           ? DriverInterface<U>
     *           : DriverInterface<OGMResults>
     *           )
     */
    private static function createBoltDriver(string|UriInterface $uri, ?DriverConfiguration $configuration, ?AuthenticateInterface $authenticate, ?FormatterInterface $formatter = null): DriverInterface
    {
        if ($formatter !== null) {
            return BoltDriver::create($uri, $configuration, $authenticate, $formatter);
        }

        return BoltDriver::create($uri, $configuration, $authenticate);
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return (
     *           func_num_args() is 4
     *           ? DriverInterface<U>
     *           : DriverInterface<OGMResults>
     *           )
     */
    private static function createNeo4jDriver(string|UriInterface $uri, ?DriverConfiguration $configuration, ?AuthenticateInterface $authenticate, ?FormatterInterface $formatter = null): DriverInterface
    {
        if ($formatter !== null) {
            return Neo4jDriver::create($uri, $configuration, $authenticate, $formatter);
        }

        return Neo4jDriver::create($uri, $configuration, $authenticate);
    }

    /**
     * @template U
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return (
     *           func_num_args() is 4
     *           ? DriverInterface<U>
     *           : DriverInterface<OGMResults>
     *           )
     *
     * @pure
     */
    private static function createHttpDriver(string|UriInterface $uri, ?DriverConfiguration $configuration, ?AuthenticateInterface $authenticate, ?FormatterInterface $formatter = null): DriverInterface
    {
        if ($formatter !== null) {
            return HttpDriver::create($uri, $configuration, $authenticate, $formatter);
        }

        return HttpDriver::create($uri, $configuration, $authenticate);
    }
}

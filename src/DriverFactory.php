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
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Exception\UnsupportedScheme;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Neo4j\Neo4jDriver;
use Psr\Http\Message\UriInterface;

/**
 * Factory for creating drivers directly.
 *
 * @psalm-import-type OGMResults from SummarizedResultFormatter
 */
final class DriverFactory
{
    /**
     * @throws UnsupportedScheme
     */
    public static function create(string|UriInterface $uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, ?SummarizedResultFormatter $formatter = null): DriverInterface
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

        throw UnsupportedScheme::make($scheme, ['bolt', 'bolt+s', 'bolt+ssc', 'neo4j', 'neo4j+s', 'neo4j+ssc']);
    }

    private static function createBoltDriver(string|UriInterface $uri, ?DriverConfiguration $configuration, ?AuthenticateInterface $authenticate, ?SummarizedResultFormatter $formatter = null): DriverInterface
    {
        if ($formatter !== null) {
            return BoltDriver::create($uri, $configuration, $authenticate, $formatter);
        }

        return BoltDriver::create($uri, $configuration, $authenticate);
    }

    private static function createNeo4jDriver(string|UriInterface $uri, ?DriverConfiguration $configuration, ?AuthenticateInterface $authenticate, ?SummarizedResultFormatter $formatter = null): DriverInterface
    {
        if ($formatter !== null) {
            return Neo4jDriver::create($uri, $configuration, $authenticate, $formatter);
        }

        return Neo4jDriver::create($uri, $configuration, $authenticate);
    }
}

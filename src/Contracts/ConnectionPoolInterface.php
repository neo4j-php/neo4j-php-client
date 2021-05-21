<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Contracts;


use Laudis\Neo4j\Enum\AccessMode;
use Psr\Http\Message\UriInterface;

/**
 * @template T
 */
interface ConnectionPoolInterface
{
    /**
     * @return T
     */
    public function acquire(UriInterface $uri, AccessMode $mode);
}

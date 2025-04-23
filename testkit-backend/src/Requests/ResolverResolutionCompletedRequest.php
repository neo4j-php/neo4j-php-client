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

namespace Laudis\Neo4j\TestkitBackend\Requests;

use Symfony\Component\Uid\Uuid;

final class ResolverResolutionCompletedRequest
{
    private Uuid $requestId;
    /** @var iterable<string> */
    private iterable $addresses;

    /**
     * @param iterable<string> $addresses
     */
    public function __construct(Uuid $requestId, iterable $addresses)
    {
        $this->requestId = $requestId;
        $this->addresses = $addresses;
    }

    public function getRequestId(): Uuid
    {
        return $this->requestId;
    }

    /**
     * @return iterable<string>
     */
    public function getAddresses(): iterable
    {
        return $this->addresses;
    }
}

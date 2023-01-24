<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class ResolverResolutionCompletedRequest
{
    /**
     * @param iterable<string> $addresses
     */
    public function __construct(private Uuid $requestId, private iterable $addresses)
    {
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

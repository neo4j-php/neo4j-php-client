<?php
declare(strict_types=1);


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

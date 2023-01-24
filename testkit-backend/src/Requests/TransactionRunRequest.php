<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class TransactionRunRequest
{
    /** @var iterable<string, array{name: string, data: array{value: iterable|scalar|null}}> */
    private iterable $params;

    /**
     * @param iterable<string, array{name: string, data: array{value: iterable|scalar|null}}>|null $params
     */
    public function __construct(private Uuid $txId, private string $cypher, ?iterable $params = null)
    {
        $this->params = $params ?? [];
    }

    public function getTxId(): Uuid
    {
        return $this->txId;
    }

    public function getCypher(): string
    {
        return $this->cypher;
    }

    /**
     * @return iterable<string, array{name: string, data: array{value: iterable|scalar|null}}>
     */
    public function getParams(): iterable
    {
        return $this->params;
    }
}

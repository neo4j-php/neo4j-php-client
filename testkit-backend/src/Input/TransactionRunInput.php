<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Input;


use Symfony\Component\Uid\Uuid;

final class TransactionRunInput
{
    private Uuid $txId;
    private string $cypher;
    private array $params;

    public function __construct(Uuid $txId, string $cypher, array $params)
    {
        $this->txId = $txId;
        $this->cypher = $cypher;
        $this->params = $params;
    }

    public function getTxId(): Uuid
    {
        return $this->txId;
    }

    public function getCypher(): string
    {
        return $this->cypher;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}

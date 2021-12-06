<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class TransactionRollbackRequest
{
    private Uuid $txId;

    public function __construct(Uuid $txId)
    {
        $this->txId = $txId;
    }

    public function getTxId(): Uuid
    {
        return $this->txId;
    }
}

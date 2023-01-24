<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class TransactionCommitRequest
{
    public function __construct(private Uuid $txId)
    {
    }

    public function getTxId(): Uuid
    {
        return $this->txId;
    }
}

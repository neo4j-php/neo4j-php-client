<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class ResultNextRequest
{
    private Uuid $resultId;

    public function __construct(Uuid $resultId)
    {
        $this->resultId = $resultId;
    }

    public function getResultId(): Uuid
    {
        return $this->resultId;
    }
}

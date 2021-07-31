<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Input;


use Symfony\Component\Uid\Uuid;

final class ResultConsumeInput
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

<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend;

use Ds\Map;
use Iterator;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Symfony\Component\Uid\Uuid;
use Traversable;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class MainRepository
{
    /** @var Map<string, DriverInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>> */
    private Map $drivers;
    /** @var Map<string, SessionInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>> */
    private Map $sessions;
    /** @var Map<string, SummarizedResult<CypherList<CypherMap<OGMTypes>>>|TestkitResponseInterface> */
    private Map $records;
    /** @var Map<string, Iterator<int, CypherMap<OGMTypes>>> */
    private Map $recordIterators;
    /** @var Map<string, UnmanagedTransactionInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>> */
    private Map $transactions;

    /**
     * @param Map<string, DriverInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>>               $drivers
     * @param Map<string, SessionInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>>              $sessions
     * @param Map<string, SummarizedResult<CypherList<CypherMap<OGMTypes>>>|TestkitResponseInterface>       $records
     * @param Map<string, UnmanagedTransactionInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>> $transactions
     */
    public function __construct(Map $drivers, Map $sessions, Map $records, Map $transactions)
    {
        $this->drivers = $drivers;
        $this->sessions = $sessions;
        $this->records = $records;
        $this->transactions = $transactions;
        $this->recordIterators = new Map();
    }

    /**
     * @param DriverInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>> $driver
     */
    public function addDriver(Uuid $id, DriverInterface $driver): void
    {
        $this->drivers->put($id->toRfc4122(), $driver);
    }

    public function removeDriver(Uuid $id): void
    {
        $this->drivers->remove($id->toRfc4122());
    }

    /**
     * @return Iterator<int, CypherMap<OGMTypes>>
     */
    public function getIterator(Uuid $id): Iterator
    {
        return $this->recordIterators->get($id->toRfc4122());
    }

    /**
     * @return DriverInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>
     */
    public function getDriver(Uuid $id): DriverInterface
    {
        return $this->drivers->get($id->toRfc4122());
    }

    /**
     * @param SessionInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>> $session
     */
    public function addSession(Uuid $id, SessionInterface $session): void
    {
        $this->sessions->put($id->toRfc4122(), $session);
    }

    public function removeSession(Uuid $id): void
    {
        $this->sessions->remove($id->toRfc4122());
    }

    /**
     * @return SessionInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>
     */
    public function getSession(Uuid $id): SessionInterface
    {
        return $this->sessions->get($id->toRfc4122());
    }

    /**
     * @param SummarizedResult<CypherList<CypherMap<OGMTypes>>>|TestkitResponseInterface $result
     */
    public function addRecords(Uuid $id, $result): void
    {
        $this->records->put($id->toRfc4122(), $result);
        if ($result instanceof SummarizedResult) {
            /** @var SummarizedResult<CypherList<CypherMap<OGMTypes>>> $result */
            $this->recordIterators->put($id->toRfc4122(), $result->getResult()->getIterator());
        }
    }

    public function removeRecords(Uuid $id): void
    {
        $this->records->remove($id->toRfc4122());
    }

    /**
     * @return SummarizedResult<CypherList<CypherMap<OGMTypes>>>|TestkitResponseInterface
     */
    public function getRecords(Uuid $id)
    {
        return $this->records->get($id->toRfc4122());
    }

    /**
     * @param UnmanagedTransactionInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>> $transaction
     */
    public function addTransaction(Uuid $id, UnmanagedTransactionInterface $transaction): void
    {
        $this->transactions->put($id->toRfc4122(), $transaction);
    }

    public function removeTransaction(Uuid $id): void
    {
        $this->transactions->remove($id->toRfc4122());
    }

    /**
     * @return UnmanagedTransactionInterface<SummarizedResult<CypherList<CypherMap<OGMTypes>>>>
     */
    public function getTransaction(Uuid $id): UnmanagedTransactionInterface
    {
        return $this->transactions->get($id->toRfc4122());
    }
}

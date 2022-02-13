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

use Iterator;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Symfony\Component\Uid\Uuid;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class MainRepository
{
    /** @var array<string, DriverInterface<SummarizedResult<CypherMap<OGMTypes>>>> */
    private array $drivers;
    /** @var array<string, SessionInterface<SummarizedResult<CypherMap<OGMTypes>>>> */
    private array $sessions;
    /** @var array<string, SummarizedResult<CypherMap<OGMTypes>>|TestkitResponseInterface> */
    private array $records;
    /** @var array<string, Iterator<int, CypherMap<OGMTypes>>> */
    private array $recordIterators;
    /** @var array<string, UnmanagedTransactionInterface<SummarizedResult<CypherMap<OGMTypes>>>> */
    private array $transactions;
    /** @var array<string, Uuid> */
    private array $sessionToTransactions = [];

    /**
     * @param array<string, DriverInterface<SummarizedResult<CypherMap<OGMTypes>>>>               $drivers
     * @param array<string, SessionInterface<SummarizedResult<CypherMap<OGMTypes>>>>              $sessions
     * @param array<string, SummarizedResult<CypherMap<OGMTypes>>|TestkitResponseInterface>       $records
     * @param array<string, UnmanagedTransactionInterface<SummarizedResult<CypherMap<OGMTypes>>>> $transactions
     */
    public function __construct(array $drivers, array $sessions, array $records, array $transactions)
    {
        $this->drivers = $drivers;
        $this->sessions = $sessions;
        $this->records = $records;
        $this->transactions = $transactions;
        $this->recordIterators = [];
    }

    /**
     * @param DriverInterface<SummarizedResult<CypherMap<OGMTypes>>> $driver
     */
    public function addDriver(Uuid $id, DriverInterface $driver): void
    {
        $this->drivers[$id->toRfc4122()] = $driver;
    }

    public function removeDriver(Uuid $id): void
    {
        unset($this->drivers[$id->toRfc4122()]);
    }

    /**
     * @return Iterator<int, CypherMap<OGMTypes>>
     */
    public function getIterator(Uuid $id): Iterator
    {
        return $this->recordIterators[$id->toRfc4122()];
    }

    /**
     * @return DriverInterface<SummarizedResult<CypherMap<OGMTypes>>>
     */
    public function getDriver(Uuid $id): DriverInterface
    {
        return $this->drivers[$id->toRfc4122()];
    }

    /**
     * @param SessionInterface<SummarizedResult<CypherMap<OGMTypes>>> $session
     */
    public function addSession(Uuid $id, SessionInterface $session): void
    {
        $this->sessions[$id->toRfc4122()] = $session;
    }

    public function removeSession(Uuid $id): void
    {
        unset($this->sessions[$id->toRfc4122()]);
    }

    /**
     * @return SessionInterface<SummarizedResult<CypherMap<OGMTypes>>>
     */
    public function getSession(Uuid $id): SessionInterface
    {
        return $this->sessions[$id->toRfc4122()];
    }

    /**
     * @param SummarizedResult<CypherMap<OGMTypes>>|TestkitResponseInterface $result
     */
    public function addRecords(Uuid $id, $result): void
    {
        $this->records[$id->toRfc4122()] = $result;
        if ($result instanceof SummarizedResult) {
            /** @var SummarizedResult<CypherMap<OGMTypes>> $result */
            $this->recordIterators[$id->toRfc4122()] = $result;
        }
    }

    public function removeRecords(Uuid $id): void
    {
        unset($this->records[$id->toRfc4122()]);
    }

    /**
     * @return SummarizedResult<CypherMap<OGMTypes>>|TestkitResponseInterface
     */
    public function getRecords(Uuid $id)
    {
        return $this->records[$id->toRfc4122()];
    }

    /**
     * @param UnmanagedTransactionInterface<SummarizedResult<CypherMap<OGMTypes>>> $transaction
     */
    public function addTransaction(Uuid $id, UnmanagedTransactionInterface $transaction): void
    {
        $this->transactions[$id->toRfc4122()] = $transaction;
    }

    public function removeTransaction(Uuid $id): void
    {
        unset($this->transactions[$id->toRfc4122()]);
    }

    /**
     * @return UnmanagedTransactionInterface<SummarizedResult<CypherMap<OGMTypes>>>
     */
    public function getTransaction(Uuid $id): UnmanagedTransactionInterface
    {
        return $this->transactions[$id->toRfc4122()];
    }

    public function bindTransactionToSession(Uuid $sessionId, Uuid $transactionId): void
    {
        $this->sessionToTransactions[$sessionId->toRfc4122()] = $transactionId;
    }

    public function detachTransactionFromSession(Uuid $sessionId): void
    {
        unset($this->sessionToTransactions[$sessionId->toRfc4122()]);
    }

    public function getTsxIdFromSession(Uuid $sessionId): Uuid
    {
        return $this->sessionToTransactions[$sessionId->toRfc4122()];
    }
}

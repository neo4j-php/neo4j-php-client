<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
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

    /** @var array<string, bool> */
    private array $iteratorFetchedFirst;

    /** @var array<string, bool> After ResultPeek advanced the iterator, ResultNext must not advance again. */
    private array $peekPrimed = [];

    /**
     * Count of {@see Iterator::next()} calls owed before the next read: one per record already returned
     * to TestKit without advancing the shared iterator (advancing immediately would run the next Bolt pull
     * too early — e.g. disconnect tests expect the second pull on the second {@see ResultNext}, not after the first).
     *
     * @var array<string, int>
     */
    private array $pendingIteratorNextCount = [];

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
        $driver = $this->drivers[$id->toRfc4122()] ?? null;
        if ($driver !== null) {
            $driver->closeConnections();
        }
        unset($this->drivers[$id->toRfc4122()]);
    }

    /**
     * @return Iterator<int, CypherMap<OGMTypes>>
     */
    public function getIterator(Uuid $id): Iterator
    {
        return $this->recordIterators[$id->toRfc4122()];
    }

    public function getIteratorFetchedFirst(Uuid $id): bool
    {
        return $this->iteratorFetchedFirst[$id->toRfc4122()] ?? false;
    }

    public function setIteratorFetchedFirst(Uuid $id, bool $value): void
    {
        $this->iteratorFetchedFirst[$id->toRfc4122()] = $value;
    }

    /**
     * ResultPeek advanced the iterator; the following ResultNext must skip its leading {@see Iterator::next()}.
     */
    public function setPeekPrimed(Uuid $id, bool $value): void
    {
        if ($value) {
            $this->peekPrimed[$id->toRfc4122()] = true;
        } else {
            unset($this->peekPrimed[$id->toRfc4122()]);
        }
    }

    public function consumePeekPrimed(Uuid $id): bool
    {
        $key = $id->toRfc4122();
        if (!array_key_exists($key, $this->peekPrimed)) {
            return false;
        }
        unset($this->peekPrimed[$key]);

        return true;
    }

    /**
     * After returning a record from {@see ResultNext}, the iterator must advance before the next read;
     * defer that advance so the Bolt layer does not pull until the following ResultNext or Result.list().
     */
    public function addPendingIteratorNext(Uuid $id): void
    {
        $key = $id->toRfc4122();
        $this->pendingIteratorNextCount[$key] = ($this->pendingIteratorNextCount[$key] ?? 0) + 1;
    }

    /**
     * Applies deferred {@see Iterator::next()} calls (e.g. before the next ResultNext or before Result.list()).
     */
    public function drainPendingIteratorNexts(Uuid $id, Iterator $iterator): void
    {
        $key = $id->toRfc4122();
        $n = $this->pendingIteratorNextCount[$key] ?? 0;
        if ($n === 0) {
            return;
        }
        unset($this->pendingIteratorNextCount[$key]);
        for ($i = 0; $i < $n; ++$i) {
            $iterator->next();
        }
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
        $key = $id->toRfc4122();
        $this->records[$key] = $result;
        if ($result instanceof SummarizedResult) {
            /** @var SummarizedResult<CypherMap<OGMTypes>> $result */
            $this->recordIterators[$key] = $result;
        } else {
            unset($this->recordIterators[$key]);
        }
    }

    public function removeRecords(Uuid $id): void
    {
        $key = $id->toRfc4122();
        unset(
            $this->records[$key],
            $this->recordIterators[$key],
            $this->iteratorFetchedFirst[$key],
            $this->peekPrimed[$key],
            $this->pendingIteratorNextCount[$key]
        );
    }

    /**
     * @return SummarizedResult|TestkitResponseInterface
     */
    public function getRecords(Uuid $id)
    {
        return $this->records[$id->toRfc4122()];
    }

    public function addTransaction(Uuid $id, SessionInterface|UnmanagedTransactionInterface $transaction): void
    {
        $this->transactions[$id->toRfc4122()] = $transaction;
    }

    public function removeTransaction(Uuid $id): void
    {
        unset($this->transactions[$id->toRfc4122()]);
    }

    public function getTransaction(Uuid $id): UnmanagedTransactionInterface|SessionInterface|null
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

    public function addBufferedRecords(string $id, array $records): void
    {
        $this->records[$id] = $records;
    }
}

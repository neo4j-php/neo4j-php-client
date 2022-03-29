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

namespace Laudis\Neo4j\Common;

use Bolt\protocol\V3;
use Bolt\protocol\V4;
use Laudis\Neo4j\Bolt\ServerStateTransition;
use Laudis\Neo4j\Bolt\ServerStateTransitionRepository;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * @implements ConnectionInterface<V3>
 *
 * @psalm-import-type BoltMeta from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class BoltConnection implements ConnectionInterface
{
    private ?V3 $boltProtocol;
    /** @psalm-readonly */
    private ConnectionConfiguration $config;
    /** @psalm-readonly */
    private BoltFactory $factory;

    private int $ownerCount = 0;
    private string $expectedState = 'READY';
    /** @var list<callable(list<ServerStateTransition>): void> */
    private array $beforeTransitionEventListeners = [];
    /** @var list<callable(ServerStateTransition): void> */
    private array $afterTransitionEventListeners = [];
    private ServerStateTransitionRepository $transitions;

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        BoltFactory $factory,
        ?V3 $boltProtocol,
        ConnectionConfiguration $config
    ) {
        $this->factory = $factory;
        $this->boltProtocol = $boltProtocol;
        $this->transitions = ServerStateTransitionRepository::getInstance();
        $this->config = $config;
    }

    /**
     * @psalm-mutation-free
     */
    public function getImplementation(): V3
    {
        if ($this->boltProtocol === null) {
            throw new RuntimeException('Connection is closed');
        }

        return $this->boltProtocol;
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAgent(): string
    {
        return $this->config->getServerAgent();
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAddress(): UriInterface
    {
        return $this->config->getServerAddress();
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerVersion(): string
    {
        return $this->config->getServerVersion();
    }

    /**
     * @psalm-mutation-free
     */
    public function getProtocol(): ConnectionProtocol
    {
        return $this->config->getProtocol();
    }

    /**
     * @psalm-mutation-free
     */
    public function getAccessMode(): AccessMode
    {
        return $this->config->getAccessMode();
    }

    /**
     * @psalm-mutation-free
     */
    public function getDatabaseInfo(): DatabaseInfo
    {
        return $this->config->getDatabaseInfo();
    }

    /**
     * @psalm-mutation-free
     */
    public function isOpen(): bool
    {
        return $this->boltProtocol !== null;
    }

    public function open(): void
    {
        $this->boltProtocol = $this->factory->build()[0];
    }

    public function setTimeout(float $timeout): void
    {
        $this->factory->getConnection()->setTimeout($timeout);
    }

    public function close(): void
    {
        if ($this->ownerCount === 0) {
            $this->boltProtocol = null;
        }
    }

    public function reset(): void
    {
        if ($this->boltProtocol) {
            $this->boltProtocol->reset();
            $this->boltProtocol = $this->factory->build()[0];
        }
    }

    /**
     * @param string|null $database the database to connect to
     * @param float|null  $timeout  timeout in seconds
     */
    public function begin(?string $database, ?float $timeout): void
    {
        if ($this->boltProtocol === null) {
            throw new RuntimeException('Cannot begin on a closed connection');
        }

        $transitions = $this->transitions->getAvailableTransitionsForStateAndMessage($this->expectedState, 'BEGIN');

        $this->triggerBeforeEvents($transitions);

        try {
            $this->boltProtocol->begin($this->buildExtra($database, $timeout));
            $transition = $this->getSuccessTransition($transitions);

            $this->expectedState = $transition->getNewState() ?? 'READY';
        } catch (Throwable $e) {
            $transition = $this->getFailureTransition($transitions);
            $this->expectedState = $transition->getNewState() ?? 'READY';

            throw $e;
        } finally {
            if (isset($transition)) {
                $this->triggerAfterEvents($transition);
            }
        }
    }

    /**
     * @return BoltMeta
     */
    public function run(string $text, array $parameters, ?string $database, ?float $timeout): array
    {
        if ($this->boltProtocol === null) {
            throw new RuntimeException('Cannot run on a closed connection');
        }

        /** @var BoltMeta */
        return $this->boltProtocol->run($text, $parameters, $this->buildExtra($database, $timeout));
    }

    public function commit(): void
    {
        if ($this->boltProtocol === null) {
            throw new RuntimeException('Cannot commit on a closed connection');
        }

        $this->boltProtocol->commit();
    }

    public function rollback(): void
    {
        if ($this->boltProtocol === null) {
            throw new RuntimeException('Cannot commit on a closed connection');
        }

        $this->boltProtocol->rollback();
    }

    /**
     * @return non-empty-list<list>
     */
    public function pull(?int $qid, ?int $fetchSize): array
    {
        if ($this->boltProtocol === null) {
            throw new RuntimeException('Cannot pull on a closed connection');
        }

        $extra = [];
        if ($fetchSize) {
            $extra['n'] = $fetchSize;
        }

        if ($qid) {
            $extra['qid'] = $qid;
        }

        if (!$this->boltProtocol instanceof V4) {
            /** @var non-empty-list<list> */
            return $this->boltProtocol->pullAll($extra);
        }

        /** @var non-empty-list<list> */
        return $this->boltProtocol->pull($extra);
    }

    /**
     * @psalm-mutation-free
     */
    public function getDriverConfiguration(): DriverConfiguration
    {
        return $this->driverConfiguration;
    }

    public function __destruct()
    {
        $this->ownerCount = 0;
        $this->close();
    }

    public function incrementOwner(): void
    {
        ++$this->ownerCount;
    }

    public function decrementOwner(): void
    {
        --$this->ownerCount;
    }

    private function buildExtra(?string $database, ?float $timeout): array
    {
        $extra = [];
        if ($database) {
            $extra['db'] = $database;
        }
        if ($timeout) {
            $extra['tx_timeout'] = $timeout * 1000;
        }

        return $extra;
    }

    /**
     * @param callable(list<ServerStateTransition>): void $listener
     */
    public function bindBeforeTransitionEventListener($listener): void
    {
        $this->beforeTransitionEventListeners[] = $listener;
    }

    /**
     * @param callable(ServerStateTransition): void $listener
     */
    private function bindAfterTransitionEventListener($listener): void
    {
        $this->afterTransitionEventListeners[] = $listener;
    }

    /**
     * @param list<ServerStateTransition> $states
     *
     * @return ServerStateTransition
     */
    private function getFailureTransition(array $states): ServerStateTransition
    {
        return $this->getTransitionForResponse($states, 'FAILURE');
    }

    /**
     * @param list<ServerStateTransition> $states
     *
     * @return ServerStateTransition
     */
    private function getSuccessTransition(array $states): ServerStateTransition
    {
        return $this->getTransitionForResponse($states, 'SUCCESS');
    }

    /**
     * @param list<ServerStateTransition> $states
     *
     * @return ServerStateTransition
     */
    private function getTransitionForResponse(array $states, string $response): ServerStateTransition
    {
        foreach ($states as $state) {
            if ($state->getServerResponse() === $response) {
                return $state;
            }
        }

        throw new RuntimeException("Cannot find $response transition");
    }

    public function triggerAfterEvents(ServerStateTransition $transition): void
    {
        foreach ($this->afterTransitionEventListeners as $listener) {
            $listener($transition);
        }
    }

    /**
     * @param list<ServerStateTransition> $transitions
     */
    public function triggerBeforeEvents(array $transitions): void
    {
        foreach ($this->beforeTransitionEventListeners as $listener) {
            $listener($transitions);
        }
    }
}

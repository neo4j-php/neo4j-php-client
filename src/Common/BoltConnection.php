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

use BadMethodCallException;
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
use LogicException;
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
    private ?string $expectedState = 'READY';
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
        if ($this->boltProtocol !== null) {
            throw new BadMethodCallException('Cannot open a connection that is already open');
        }

        $this->boltProtocol = $this->factory->build()[0];
    }

    public function setTimeout(float $timeout): void
    {
        $this->factory->getConnection()->setTimeout($timeout);
    }

    public function close(): void
    {
        $this->handleMessage('GOODBYE', function () {
            $this->boltProtocol->goodbye();
        });

        $this->boltProtocol = null;
        $this->beforeTransitionEventListeners = [];
        $this->afterTransitionEventListeners = [];
    }

    public function reset(): void
    {
        $this->handleMessage('RESET', function () {
            $this->boltProtocol->reset();
        });

        $this->boltProtocol = $this->factory->build()[0];
        $this->beforeTransitionEventListeners = [];
        $this->afterTransitionEventListeners = [];
    }

    /**
     * @param string|null $database the database to connect to
     * @param float|null  $timeout  timeout in seconds
     */
    public function begin(?string $database, ?float $timeout): void
    {
        $this->handleMessage('BEGIN', function () use ($database, $timeout) {
            $this->boltProtocol->begin($this->buildExtra($database, $timeout));
        });
    }

    /**
     * @template T
     *
     * @param string        $message the bolt message we are trying to send
     * @param callable(): T $action  the actual action to send the message
     *
     * @return T
     */
    private function handleMessage(string $message, $action)
    {
        if ($this->boltProtocol === null || $this->expectedState === null) {
            throw new LogicException("Cannot send \"$message\" message on a closed connection");
        }

        // First, we fetch the available transitions for the given state of the server
        // and the intended message to send.
        $transitions = $this->transitions->getAvailableTransitionsForStateAndMessage($this->expectedState, $message);

        // We notify the event listeners before sending the message.
        // Since we don't know whether it will fail or not, we need to send all
        // possible transitions.
        $this->triggerBeforeEvents($transitions);

        try {
            $tbr = $action();

            // If no exceptions are thrown, we know the underlying bolt library
            // received a success response, making sure we can use the success transition
            $transition = $this->getSuccessTransition($transitions);
            $this->expectedState = $transition->getNewState();
        } catch (Throwable $e) {
            // If an an exception is thrown, we know the underlying bolt library
            // received a failure response, making sure we can use the failure transition
            // and propagate the exception further
            $transition = $this->getFailureTransition($transitions);
            $this->expectedState = $transition->getNewState();

            throw $e;
        } finally {
            // In the end, all listeners need to be able to handle the state transition,
            // regardless of the call stack.
            $this->triggerAfterEvents($transition);
        }

        return $tbr;
    }

    /**
     * @return BoltMeta
     */
    public function run(string $text, array $parameters, ?string $database, ?float $timeout): array
    {
        return $this->handleMessage('RUN', function () use ($text, $parameters, $database, $timeout) {
            return $this->boltProtocol->run($text, $parameters, $this->buildExtra($database, $timeout));
        });
    }

    public function commit(): void
    {
        $this->handleMessage('COMMIT', fn () => $this->boltProtocol->commit());
    }

    public function rollback(): void
    {
        $this->handleMessage('ROLLBACK', fn () => $this->boltProtocol->commit());
    }

    /**
     * @return non-empty-list<list>
     */
    public function pull(?int $qid, ?int $fetchSize): array
    {
        return $this->handleMessage('PULL', function () use ($qid, $fetchSize) {
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
        });
    }

    /**
     * @psalm-mutation-free
     */
    public function getDriverConfiguration(): DriverConfiguration
    {
        return $this->config->getDriverConfiguration();
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

    public function getExpectedState(): ?string
    {
        return $this->expectedState;
    }

    /**
     * @param list<ServerStateTransition> $states
     */
    private function getFailureTransition(array $states): ServerStateTransition
    {
        return $this->getTransitionForResponse($states, 'FAILURE');
    }

    /**
     * @param list<ServerStateTransition> $states
     */
    private function getSuccessTransition(array $states): ServerStateTransition
    {
        return $this->getTransitionForResponse($states, 'SUCCESS');
    }

    /**
     * @param list<ServerStateTransition> $states
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

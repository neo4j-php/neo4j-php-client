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
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * @implements ConnectionInterface<V3>
 *
 * @psalm-import-type BoltMeta from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class BoltConnection implements ConnectionInterface
{
    private ?V3 $boltProtocol;
    /** @psalm-readonly */
    private string $serverAgent;
    /** @psalm-readonly */
    private UriInterface $serverAddress;
    /** @psalm-readonly */
    private string $serverVersion;
    /** @psalm-readonly */
    private ConnectionProtocol $protocol;
    /** @psalm-readonly */
    private AccessMode $accessMode;
    /** @psalm-readonly */
    private DatabaseInfo $databaseInfo;
    /** @psalm-readonly */
    private BoltFactory $factory;
    /** @psalm-readonly */
    private DriverConfiguration $driverConfiguration;
    private int $ownerCount = 0;
    private string $expectedState = 'READY';

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        string $serverAgent,
        UriInterface $serverAddress,
        string $serverVersion,
        ConnectionProtocol $protocol,
        AccessMode $accessMode,
        DatabaseInfo $databaseInfo,
        BoltFactory $factory,
        ?V3 $boltProtocol,
        DriverConfiguration $config
    ) {
        $this->serverAgent = $serverAgent;
        $this->serverAddress = $serverAddress;
        $this->serverVersion = $serverVersion;
        $this->protocol = $protocol;
        $this->accessMode = $accessMode;
        $this->databaseInfo = $databaseInfo;
        $this->factory = $factory;
        $this->boltProtocol = $boltProtocol;
        $this->driverConfiguration = $config;
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
        return $this->serverAgent;
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAddress(): UriInterface
    {
        return $this->serverAddress;
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    /**
     * @psalm-mutation-free
     */
    public function getProtocol(): ConnectionProtocol
    {
        return $this->protocol;
    }

    /**
     * @psalm-mutation-free
     */
    public function getAccessMode(): AccessMode
    {
        return $this->accessMode;
    }

    /**
     * @psalm-mutation-free
     */
    public function getDatabaseInfo(): DatabaseInfo
    {
        return $this->databaseInfo;
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

        $this->boltProtocol->begin($this->buildExtra($database, $timeout));
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
}

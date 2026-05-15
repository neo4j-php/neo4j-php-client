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

namespace Laudis\Neo4j\Bolt;

use Laudis\Neo4j\Authentication\BoltHelloMetadata;
use Laudis\Neo4j\Bolt\Messages\BoltBeginMessage;
use Laudis\Neo4j\Bolt\Messages\BoltCommitMessage;
use Laudis\Neo4j\Bolt\Messages\BoltDiscardMessage;
use Laudis\Neo4j\Bolt\Messages\BoltGoodbyeMessage;
use Laudis\Neo4j\Bolt\Messages\BoltHelloMessage;
use Laudis\Neo4j\Bolt\Messages\BoltLogoffMessage;
use Laudis\Neo4j\Bolt\Messages\BoltLogonMessage;
use Laudis\Neo4j\Bolt\Messages\BoltPullMessage;
use Laudis\Neo4j\Bolt\Messages\BoltResetMessage;
use Laudis\Neo4j\Bolt\Messages\BoltRollbackMessage;
use Laudis\Neo4j\Bolt\Messages\BoltRunMessage;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\Neo4jBookmarkManagerHooksInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;

/**
 * Factory class for creating Bolt protocol messages.
 */
class BoltMessageFactory
{
    public function __construct(
        private readonly BoltConnection $connection,
        private readonly ?Neo4jLogger $logger = null,
    ) {
    }

    public function createResetMessage(): BoltResetMessage
    {
        return new BoltResetMessage($this->connection, $this->logger);
    }

    public function createBeginMessage(array $extra): BoltBeginMessage
    {
        return new BoltBeginMessage($this->connection, $extra, $this->logger);
    }

    public function createDiscardMessage(array $extra): BoltDiscardMessage
    {
        return new BoltDiscardMessage($this->connection, $extra, $this->logger);
    }

    public function createRunMessage(string $text, array $parameters, array $extra): BoltRunMessage
    {
        return new BoltRunMessage($this->connection, $text, $parameters, $extra, $this->logger);
    }

    public function createCommitMessage(BookmarkHolder $bookmarkHolder, ?Neo4jBookmarkManagerHooksInterface $bookmarkManagerHooks = null, bool $neo4jSharedManagedBookmarks = false): BoltCommitMessage
    {
        return new BoltCommitMessage($this->connection, $this->logger, $bookmarkHolder, $bookmarkManagerHooks, $neo4jSharedManagedBookmarks);
    }

    public function createRollbackMessage(): BoltRollbackMessage
    {
        return new BoltRollbackMessage($this->connection, $this->logger);
    }

    public function createPullMessage(array $extra): BoltPullMessage
    {
        return new BoltPullMessage($this->connection, $extra, $this->logger);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function createHelloMessage(array $extra): BoltHelloMessage
    {
        $extra = BoltHelloMetadata::withUtcPatchIfSupported($this->connection, $extra);

        return new BoltHelloMessage($this->connection, $extra, $this->logger);
    }

    public function createLogonMessage(array $credentials): BoltLogonMessage
    {
        /** @var array<string, mixed> $credentials */

        return new BoltLogonMessage($this->connection, $credentials, $this->logger);
    }

    public function createLogoffMessage(): BoltLogoffMessage
    {
        return new BoltLogoffMessage($this->connection, $this->logger);
    }

    public function createGoodbyeMessage(): BoltGoodbyeMessage
    {
        return new BoltGoodbyeMessage($this->connection, $this->logger);
    }
}

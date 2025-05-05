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

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
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
use Laudis\Neo4j\Databags\BookmarkHolder;

/**
 * Factory class for creating Bolt protocol messages.
 */
class BoltMessageFactory
{
    public function __construct(
        private readonly V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol,
        private readonly ?Neo4jLogger $logger = null,
    ) {
    }

    public function createResetMessage(): BoltResetMessage
    {
        return new BoltResetMessage($this->protocol, $this->logger);
    }

    public function createBeginMessage(array $extra): BoltBeginMessage
    {
        return new BoltBeginMessage($this->protocol, $extra, $this->logger);
    }

    public function createDiscardMessage(array $extra): BoltDiscardMessage
    {
        return new BoltDiscardMessage($this->protocol, $extra, $this->logger);
    }

    public function createRunMessage(string $text, array $parameters, array $extra): BoltRunMessage
    {
        return new BoltRunMessage($this->protocol, $text, $parameters, $extra, $this->logger);
    }

    public function createCommitMessage(BookmarkHolder $bookmarkHolder): BoltCommitMessage
    {
        return new BoltCommitMessage($this->protocol, $this->logger, $bookmarkHolder);
    }

    public function createRollbackMessage(): BoltRollbackMessage
    {
        return new BoltRollbackMessage($this->protocol, $this->logger);
    }

    public function createPullMessage(array $extra): BoltPullMessage
    {
        return new BoltPullMessage($this->protocol, $extra, $this->logger);
    }

    public function createHelloMessage(array $extra): BoltHelloMessage
    {
        /** @var array<string, mixed> $extra */

        return new BoltHelloMessage($this->protocol, $extra, $this->logger);
    }

    public function createLogonMessage(array $credentials): BoltLogonMessage
    {
        /** @var array<string, mixed> $credentials */

        return new BoltLogonMessage($this->protocol, $credentials, $this->logger);
    }

    public function createLogoffMessage(): BoltLogoffMessage
    {
        return new BoltLogoffMessage($this->protocol, $this->logger);
    }

    public function createGoodbyeMessage(): BoltGoodbyeMessage
    {
        return new BoltGoodbyeMessage($this->protocol, $this->logger);
    }
}

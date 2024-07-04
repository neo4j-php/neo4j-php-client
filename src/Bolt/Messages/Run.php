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

namespace Laudis\Neo4j\Bolt\Messages;

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Enum\AccessMode;

/**
 * @psalm-readonly
 *
 * @internal
 *
 * @see https://neo4j.com/docs/bolt/current/bolt/message/#messages-run
 */
class Run extends TransactionMessage
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $txMetadata
     * @param list<string>         $notificationsDisabledCategories
     */
    public function __construct(
        private readonly string $text,
        private readonly array $parameters,
        BookmarkHolder $bookmarks,
        int|null $txTimeout,
        array|null $txMetadata,
        AccessMode|null $mode,
        string|null $database,
        string|null $impersonatedUser,
        string|null $notificationsMinimumSeverity,
        array $notificationsDisabledCategories
    ) {
        parent::__construct($bookmarks, $txTimeout, $txMetadata, $mode, $database, $impersonatedUser, $notificationsMinimumSeverity, $notificationsDisabledCategories);
    }

    public function sendWithPreDecoratedExtraData(array $extra, V4_4|V5|V5_1|V5_2|V5_3|V5_4 $bolt): void
    {
        $bolt->run($this->text, $this->parameters, $extra);
    }
}

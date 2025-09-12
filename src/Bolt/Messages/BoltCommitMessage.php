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

use Bolt\enum\ServerState;
use Bolt\protocol\Response;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Psr\Log\LogLevel;

final class BoltCommitMessage extends BoltMessage
{
    public function __construct(
        BoltConnection $connection,
        private readonly ?Neo4jLogger $logger,
        private readonly BookmarkHolder $bookmarks,
    ) {
        parent::__construct($connection);
    }

    public function send(): BoltCommitMessage
    {
        $this->logger?->log(LogLevel::DEBUG, 'COMMIT');
        $this->connection->protocol()->commit();

        return $this;
    }

    public function getResponse(): Response
    {
        $response = parent::getResponse();

        // TODO: This is an issue with the underlying bolt library.
        // The serverState should be READY after a successful commit but
        // it's still in TX_STREAMING if the results were not consumed
        //
        // This should be removed once it's fixed
        $this->connection->protocol()->serverState = ServerState::READY;

        /** @var array{bookmark?: string} $content */
        $content = $response->content;
        $bookmark = $content['bookmark'] ?? '';

        if (trim($bookmark) !== '') {
            $this->bookmarks->setBookmark(new Bookmark([$bookmark]));
        }

        $this->connection->protocol()->serverState = ServerState::READY;

        return $response;
    }
}

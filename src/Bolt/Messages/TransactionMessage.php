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
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Enum\AccessMode;

/**
 * @mixin Run
 */
abstract class TransactionMessage
{
    /**
     * @param array<Bookmark>      $bookmarks
     * @param array<string, mixed> $txMetadata
     * @param list<string>         $notificationsDisabledCategories
     */
    public function __construct(
        private array $bookmarks,
        private int|null $txTimeout,
        private array $txMetadata,
        private AccessMode|null $mode,
        private string|null $database,
        private string|null $impersonatedUser,
        private string|null $notificationsMinimumSeverity,
        private array $notificationsDisabledCategories
    ) {}

    abstract protected function sendWithPreDecoratedExtraData(array $extra, V4_4|V5|V5_1|V5_2|V5_3 $bolt): void;

    public function send(V4_4|V5|V5_1|V5_2|V5_3 $bolt): void
    {
        $extra = $this->basicTransactionExtra();

        $this->sendWithPreDecoratedExtraData($extra, $bolt);
    }

    private function basicTransactionExtra(): array
    {
        $extra = [];
        if ($this->bookmarks !== []) {
            $extra['bookmarks'] = $this->bookmarks;
        }

        if ($this->mode !== null) {
            $extra['mode'] = AccessMode::WRITE() === $this->mode ? 'w' : 'r';
        }

        if ($this->txTimeout !== null) {
            $extra['tx_timeout'] = $this->txTimeout;
        }

        if ($this->txMetadata !== []) {
            $extra['tx_metadata'] = $this->txMetadata;
        }

        if ($this->database !== null) {
            $extra['db'] = $this->database;
        }

        if ($this->impersonatedUser !== null) {
            $extra['imp_user'] = $this->impersonatedUser;
        }

        if ($this->notificationsMinimumSeverity !== null) {
            $extra['notifications_minimum_severity'] = $this->notificationsMinimumSeverity;
        }

        if ($this->notificationsDisabledCategories !== []) {
            $extra['notifications_disabled_categories'] = $this->notificationsDisabledCategories;
        }

        return $extra;
    }
}

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
use Laudis\Neo4j\Contracts\MessageInterface;

/**
 * @internal
 *
 * @see https://neo4j.com/docs/bolt/current/bolt/message/#messages-route
 */
class Route implements MessageInterface
{
    public function __construct(
        private array $routing,
        private array $bookmarks,
        private string|null $database,
        private string|null $impersonatedUser,
    ) {}

    public function send(V4_4|V5|V5_2|V5_1|V5_3 $bolt): void
    {
        $extra = [];

        if ($this->database !== null) {
            $extra['db'] = $this->database;
        }

        if ($this->impersonatedUser !== null) {
            $extra['imp_user'] = $this->impersonatedUser;
        }

        $bolt->route($this->routing, $this->bookmarks, $extra);
    }
}

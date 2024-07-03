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
use Laudis\Neo4j\Contracts\MessageInterface;

/**
 * @internal
 *
 * @see https://neo4j.com/docs/bolt/current/bolt/message/#messages-discard
 */
class Discard extends AbstractMessage implements MessageInterface
{
    public function __construct(
        private readonly int $n,
        private readonly int|null $qid
    ) {}

    public function send(V4_4|V5|V5_2|V5_1|V5_3|V5_4 $bolt): void
    {
        $extra = ['n' => $this->n];
        if ($this->qid !== null) {
            $extra['qid'] = $this->qid;
        }

        $bolt->discard($extra);
    }
}

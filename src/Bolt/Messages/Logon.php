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
use LogicException;

class Logon extends AbstractMessage implements MessageInterface
{
    /**
     * @param array{scheme: string}&array<string, string> $auth
     */
    public function __construct(
        private readonly array $auth
    ) {}

    public function send(V4_4|V5|V5_2|V5_1|V5_3|V5_4 $bolt): void
    {
        if ($bolt instanceof V4_4 || $bolt instanceof V5) {
            throw new LogicException('Cannot run logon on bolt version 5.0 or lower. Version detected: '.$bolt->getVersion());
        }

        $bolt->logon($this->auth);
    }
}

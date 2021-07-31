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

namespace Laudis\Neo4j\TestkitBackend\Actions;

use Ds\Map;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;

final class SessionClose implements ActionInterface
{
    private Map $sessions;

    public function __construct(Map $sessions)
    {
        $this->sessions = $sessions;
    }

    public function handle(array $data): array
    {
        $this->sessions->remove($data['sessionId']);

        return [
            'name' => 'Session',
            'data' => [
                'id' => $data['sessionId'],
            ],
        ];
    }
}

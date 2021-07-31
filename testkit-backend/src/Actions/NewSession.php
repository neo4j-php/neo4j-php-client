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
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use Symfony\Component\Uid\Uuid;

final class NewSession implements ActionInterface
{
    /** @var Map<string, DriverInterface> */
    private Map $drivers;
    private Map $sessions;

    /**
     * @param Map<string, DriverInterface> $drivers
     */
    public function __construct(Map $drivers, Map $sessions)
    {
        $this->drivers = $drivers;
        $this->sessions = $sessions;
    }

    public function handle(array $data): array
    {
        /**
         * @var DriverInterface $driver
         */
        $driver = $this->drivers->get($data['driverId']);

        $config = SessionConfiguration::default();
        if (($data['accessMode'] ?? null) !== null) {
            $config = $config->withAccessMode($data['accessMode'] === 'r' ? AccessMode::READ() : AccessMode::WRITE());
        }

        if (($data['bookmarks'] ?? null) !== null) {
            $config = $config->withBookmarks($data['bookmarks']);
        }

        if (($data['database'] ?? null) !== null) {
            $config = $config->withDatabase($data['database']);
        }

        if (($data['fetchSize'] ?? null) !== null) {
            $config = $config->withFetchSize($data['fetchSize']);
        }

        $session = $driver->createSession($config);
        $id = Uuid::v4()->toRfc4122();
        $this->sessions->put($id, $session);

        return [
            'name' => 'Session',
            'data' => [
                'id' => $id,
            ],
        ];
    }
}

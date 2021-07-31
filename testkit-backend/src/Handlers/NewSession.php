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

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Ds\Map;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use Laudis\Neo4j\TestkitBackend\Requests\NewSessionRequest;
use Laudis\Neo4j\TestkitBackend\Responses\SessionResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ActionInterface<NewSessionRequest>
 */
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

    /**
     * @param NewSessionRequest $request
     */
    public function handle($request): SessionResponse
    {
        $driver = $this->drivers->get($request->getDriverId()->toRfc4122());

        $config = SessionConfiguration::default()
            ->withAccessMode($request->getAccessMode() === 'r' ? AccessMode::READ() : AccessMode::WRITE());

        if ($request->getBookmarks() !== null) {
            $config = $config->withBookmarks($request->getBookmarks());
        }

        if ($request->getDatabase() !== null) {
            $config = $config->withDatabase($request->getDatabase());
        }

        if ($request->getFetchSize() !== null) {
            $config = $config->withFetchSize($request->getFetchSize());
        }

        $session = $driver->createSession($config);
        $id = Uuid::v4();
        $this->sessions->put($id->toRfc4122(), $session);

        return new SessionResponse($id);
    }
}

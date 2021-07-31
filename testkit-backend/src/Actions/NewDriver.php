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
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\DriverFactory;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use Symfony\Component\Uid\Uuid;

final class NewDriver implements ActionInterface
{
    private Map $drivers;

    public function __construct(Map $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * @psalm-suppress PossiblyUndefinedStringArrayOffset
     */
    public function handle(array $data): array
    {
        ['principal' => $user, 'credentials' => $pass] = $data['authorizationToken']['data'];

        $driver = DriverFactory::create($data['uri'], null, Authenticate::basic($user, $pass));
        $id = Uuid::v4()->toRfc4122();
        $this->drivers->put($id, $driver);

        return [
            'name' => 'Driver',
            'data' => [
                'id' => $id,
            ],
        ];
    }
}

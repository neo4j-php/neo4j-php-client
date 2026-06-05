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

namespace Laudis\Neo4j\Authentication;

use Exception;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\BoltMessageFactory;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;

use function sprintf;

final class NoAuth implements AuthenticateInterface
{
    public function __construct(
        private readonly ?Neo4jLogger $logger,
    ) {
    }

    /**
     * @throws Exception
     *
     * @return array{server: string, connection_id: string, hints: list}
     */
    public function authenticateBolt(BoltConnection $connection, string $userAgent): array
    {
        $factory = $this->createMessageFactory($connection);

        if ($connection->getProtocol()->compare(ConnectionProtocol::BOLT_V5_1()) >= 0) {
            $helloMetadata = ['user_agent' => $userAgent];

            $factory->createHelloMessage($helloMetadata)->send()->getResponse();

            $response = $factory->createLogonMessage(['scheme' => 'none'])->send()->getResponse();

            /** @var array{server: string, connection_id: string, hints: list} */
            return $response->content;
        }

        $helloMetadata = [
            'user_agent' => $userAgent,
            'scheme' => 'none',
        ];

        $response = $factory->createHelloMessage($helloMetadata)->send()->getResponse();

        /** @var array{server: string, connection_id: string, hints: list} */
        return $response->content;
    }

    public function toString(UriInterface $uri): string
    {
        return sprintf('No Auth %s:%s', $uri->getHost(), $uri->getPort() ?? '');
    }

    private function createMessageFactory(BoltConnection $connection): BoltMessageFactory
    {
        return new BoltMessageFactory($connection, $this->logger);
    }
}

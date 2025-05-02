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

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Exception;
use Laudis\Neo4j\Bolt\BoltMessageFactory;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Common\ResponseHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\UriInterface;

/**
 * Authenticates connections using a basic username and password.
 */
final class BasicAuth implements AuthenticateInterface
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly ?Neo4jLogger $logger,
    ) {
    }

    /**
     * @throws Exception
     *
     * @return array{server: string, connection_id: string, hints: list}
     */
    public function authenticateBolt(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol, string $userAgent): array
    {
        $factory = $this->createMessageFactory($protocol);

        if (method_exists($protocol, 'logon')) {
            $helloMetadata = ['user_agent' => $userAgent];

            $factory->createHelloMessage($helloMetadata)->send();
            $response = ResponseHelper::getResponse($protocol);

            $credentials = [
                'scheme' => 'basic',
                'principal' => $this->username,
                'credentials' => $this->password,
            ];

            $factory->createLogonMessage($credentials)->send();
            ResponseHelper::getResponse($protocol);

            /** @var array{server: string, connection_id: string, hints: list} */
            return $response->content;
        }

        $helloMetadata = [
            'user_agent' => $userAgent,
            'scheme' => 'basic',
            'principal' => $this->username,
            'credentials' => $this->password,
        ];

        $factory->createHelloMessage($helloMetadata)->send();

        /** @var array{server: string, connection_id: string, hints: list} */
        return ResponseHelper::getResponse($protocol)->content;
    }

    /**
     * @throws Exception
     */
    public function logoff(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol): void
    {
        $factory = $this->createMessageFactory($protocol);
        $factory->createLogoffMessage()->send();
        ResponseHelper::getResponse($protocol);
    }

    public function toString(UriInterface $uri): string
    {
        return sprintf('Basic %s:%s@%s:%s', $this->username, '######', $uri->getHost(), $uri->getPort() ?? '');
    }

    /**
     * Helper to create message factory.
     */
    private function createMessageFactory(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol): BoltMessageFactory
    {
        return new BoltMessageFactory($protocol, $this->logger);
    }
}

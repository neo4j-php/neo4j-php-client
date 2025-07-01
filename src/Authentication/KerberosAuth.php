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
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;

use function sprintf;

/**
 * Authenticates connections using a kerberos token.
 */
final class KerberosAuth implements AuthenticateInterface
{
    public function __construct(
        private readonly string $token,
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

        $this->logger?->log(LogLevel::DEBUG, 'HELLO', ['user_agent' => $userAgent]);

        $factory->createHelloMessage(['user_agent' => $userAgent])->send()->getResponse();

        $this->logger?->log(LogLevel::DEBUG, 'LOGON', ['scheme' => 'kerberos', 'principal' => '']);

        $response = $factory->createLogonMessage([
            'scheme' => 'kerberos',
            'principal' => '',
            'credentials' => $this->token,
        ])->send()->getResponse();

        /**
         * @var array{server: string, connection_id: string, hints: list}
         */
        return $response->content;
    }

    public function toString(UriInterface $uri): string
    {
        return sprintf('Kerberos %s@%s:%s', $this->token, $uri->getHost(), $uri->getPort() ?? '');
    }

    /**
     * Helper to create the message factory.
     */
    private function createMessageFactory(BoltConnection $connection): BoltMessageFactory
    {
        return new BoltMessageFactory($connection, $this->logger);
    }
}

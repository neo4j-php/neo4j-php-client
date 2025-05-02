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
use Psr\Http\Message\RequestInterface;
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

    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface
    {
        $this->logger?->log(LogLevel::DEBUG, 'Authenticating using KerberosAuth');

        return $request->withHeader('Authorization', 'Kerberos '.$this->token)
            ->withHeader('User-Agent', $userAgent);
    }

    /**
     * @throws Exception
     *
     * @return array{server: string, connection_id: string, hints: list}
     */
    public function authenticateBolt(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol, string $userAgent): array
    {
        $factory = $this->createMessageFactory($protocol);

        $this->logger?->log(LogLevel::DEBUG, 'HELLO', ['user_agent' => $userAgent]);

        $factory->createHelloMessage(['user_agent' => $userAgent])->send();

        $response = ResponseHelper::getResponse($protocol);

        $this->logger?->log(LogLevel::DEBUG, 'LOGON', ['scheme' => 'kerberos', 'principal' => '']);

        $factory->createLogonMessage([
            'scheme' => 'kerberos',
            'principal' => '',
            'credentials' => $this->token,
        ])->send();

        ResponseHelper::getResponse($protocol);

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
    private function createMessageFactory(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol): BoltMessageFactory
    {
        return new BoltMessageFactory($protocol, $this->logger);
    }
}

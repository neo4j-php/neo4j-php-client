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

use function explode;

use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\UriInterface;

use function substr_count;

/**
 * Factory responsible for creating authentication logic.
 *
 * @psalm-immutable
 */
final class Authenticate
{
    /**
     * Authenticate using a provided username and password.
     *
     * @pure
     */
    public static function basic(string $username, string $password, ?Neo4jLogger $logger = null): BasicAuth
    {
        return new BasicAuth($username, $password, $logger);
    }

    /**
     * Authenticate using a kerberos token.
     *
     * @pure
     */
    public static function kerberos(string $token, ?Neo4jLogger $logger = null): KerberosAuth
    {
        return new KerberosAuth($token, $logger);
    }

    /**
     * Authenticate using a OpenID Connect token.
     *
     * @pure
     */
    public static function oidc(string $token, ?Neo4jLogger $logger = null): OpenIDConnectAuth
    {
        return new OpenIDConnectAuth($token, $logger);
    }

    /**
     * Don't authenticate at all.
     *
     * @pure
     */
    public static function disabled(?Neo4jLogger $logger = null): NoAuth
    {
        return new NoAuth($logger);
    }

    /**
     * Authenticate from information found in the url.
     *
     * @pure
     */
    public static function fromUrl(UriInterface $uri, ?Neo4jLogger $logger = null): AuthenticateInterface
    {
        /**
         * @psalm-suppress ImpureMethodCall Uri is a pure object:
         *
         * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects
         */
        $userInfo = $uri->getUserInfo();

        if (substr_count($userInfo, ':') === 1) {
            /** @var array{0: string, 1: string} $explode */
            $explode = explode(':', $userInfo);
            [$user, $pass] = $explode;

            return self::basic($user, $pass, $logger);
        }

        return self::disabled($logger);
    }
}

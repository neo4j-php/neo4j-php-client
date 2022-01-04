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

namespace Laudis\Neo4j\Authentication;

use function explode;
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
    public static function basic(string $username, string $password): BasicAuth
    {
        return new BasicAuth($username, $password);
    }

    /**
     * Authenticate using a kerberos token.
     *
     * @pure
     */
    public static function kerberos(string $token): KerberosAuth
    {
        return new KerberosAuth($token);
    }

    /**
     * Authenticate using a OpenID Connect token.
     *
     * @pure
     */
    public static function oidc(string $token): OpenIDConnectAuth
    {
        return new OpenIDConnectAuth($token);
    }

    /**
     * Don't authenticate at all.
     *
     * @pure
     */
    public static function disabled(): NoAuth
    {
        return new NoAuth();
    }

    /**
     * Authenticate from information found in the url.
     *
     * @pure
     */
    public static function fromUrl(UriInterface $uri): AuthenticateInterface
    {
        /**
         * @psalm-suppress ImpureMethodCall Uri is a pure object:
         *
         * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects
         */
        $userInfo = $uri->getUserInfo();

        if (substr_count($userInfo, ':') === 1) {
            [$user, $pass] = explode(':', $userInfo);

            return self::basic($user, $pass);
        }

        return self::disabled();
    }
}

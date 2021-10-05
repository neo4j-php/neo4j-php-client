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
     * Don't authenticate at all.
     *
     * @pure
     */
    public static function disabled(): NoAuth
    {
        return new NoAuth();
    }

    /**
     * Authenticate using the information found in the url.
     *
     * @pure
     */
    public static function fromUrl(): UrlAuth
    {
        return new UrlAuth();
    }
}

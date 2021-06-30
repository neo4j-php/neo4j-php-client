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

final class Authenticate
{
    /**
     * @psalm-mutation-free
     */
    public static function basic(string $username, string $password): BasicAuth
    {
        return new BasicAuth($username, $password);
    }

    /**
     * @psalm-mutation-free
     */
    public static function kerberos(string $token): KerberosAuth
    {
        return new KerberosAuth($token);
    }

    /**
     * @psalm-mutation-free
     */
    public static function disabled(): NoAuth
    {
        return new NoAuth();
    }

    /**
     * @psalm-mutation-free
     */
    public static function fromUrl(): UrlAuth
    {
        return new UrlAuth();
    }
}

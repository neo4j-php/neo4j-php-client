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

namespace Laudis\Neo4j\Common;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

use const DNS_A;
use const DNS_AAAA;

use function dns_get_record;

use Generator;
use Laudis\Neo4j\Contracts\AddressResolverInterface;
use Throwable;

class DNSAddressResolver implements AddressResolverInterface
{
    /**
     * @return Generator<string>
     */
    public function getAddresses(string $host): Generator
    {
        // By using the generator pattern we make sure to call the heavy DNS IO operations
        // as late as possible
        yield $host;

        try {
            /** @var list<array{ip?: string|null}>|false $records */
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
        } catch (Throwable) {
            $records = []; // Failed DNS queries should not halt execution
        }

        if ($records === false) {
            $records = [];
        }

        if (count($records) === 0) {
            yield from $this->tryReverseLookup($host);
        } else {
            $records = array_map(static fn (array $x) => $x['ip'] ?? '', $records);
            $records = array_filter($records, static fn (string $x) => $x !== '');
            yield from array_values(array_unique($records));
        }
    }

    /**
     * @return iterable<string>
     */
    private function tryReverseLookup(string $host): iterable
    {
        try {
            /** @var list<array{target?: string|null}>|false $records */
            $records = dns_get_record($host.'.in-addr.arpa');
        } catch (Throwable) {
            $records = []; // Failed DNS queries should not halt execution
        }

        if ($records === false) {
            $records = [];
        }

        if (count($records) !== 0) {
            $records = array_map(static fn (array $x) => $x['target'] ?? '', $records);
            $records = array_filter($records, static fn (string $x) => $x !== '');
            yield from array_values(array_unique($records));
        }
    }
}

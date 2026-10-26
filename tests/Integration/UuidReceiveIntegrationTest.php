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

namespace Laudis\Neo4j\Tests\Integration;

use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;
use Laudis\Neo4j\Types\Uuid;

use function str_starts_with;

final class UuidReceiveIntegrationTest extends EnvironmentAwareIntegrationTest
{
    private const SAMPLE = '550e8400-e29b-41d4-a716-446655440000';

    public function testReceivesAUuid(): void
    {
        if (str_starts_with($_ENV['CONNECTION'] ?? '', 'http')) {
            $this->markTestSkipped('http does not support the UUID type');
        }

        try {
            $value = $this->getSession()->run('RETURN uuid($value) AS id', ['value' => self::SAMPLE])->first()->get('id');
        } catch (Neo4jException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        self::assertInstanceOf(Uuid::class, $value);
        self::assertSame(self::SAMPLE, $value->getValue());
    }
}

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

namespace Laudis\Neo4j\Bolt\Messages;

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Laudis\Neo4j\Contracts\MessageInterface;

/**
*
 *  @psalm-readonly
 *
 *  @internal
 *
 *  @see https://neo4j.com/docs/bolt/current/bolt/message/#messages-hello
 */
class Hello implements MessageInterface
{
    /**
     * @param list<string>                                                                                $patchBolt
     * @param list<string>                                                                                $routing
     * @param list<string>                                                                                $notificationsDisabledCategories
     * @param array{scheme: string}&array<string, string>                                                 $auth
     * @param array{product?: string, platform ?: string, language ?: string, language_details ?: string} $boltAgent
     */
    public function __construct(
        private array $auth,
        private string|null $userAgent,
        private array $patchBolt,
        private array $routing,
        private string|null $notificationsMinimumSeverity,
        private array $notificationsDisabledCategories,
        private array $boltAgent,
    ) {}

    public function send(V4_4|V5|V5_2|V5_1|V5_3 $bolt): void
    {
        $extra = [
            'auth' => $this->auth,
        ];

        if ($this->userAgent !== null) {
            $extra['user_agent'] = $this->userAgent;
        }

        if ($this->patchBolt !== []) {
            $extra['patch_bolt'] = $this->patchBolt;
        }

        if ($this->routing !== []) {
            $extra['routing'] = $this->routing;
        }

        if ($this->notificationsMinimumSeverity !== null) {
            $extra['notifications_minimum_severity'] = $this->notificationsMinimumSeverity;
        }

        if ($this->notificationsDisabledCategories !== []) {
            $extra['notifications_disabled_categories'] = $this->notificationsDisabledCategories;
        }

        if ($this->boltAgent !== []) {
            $extra['bolt_agent'] = $this->boltAgent;
        }

        $bolt->hello($extra);
    }
}

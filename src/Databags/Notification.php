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

namespace Laudis\Neo4j\Databags;

/**
 * Representation for notifications found when executing a query. A notification can be visualized in a client pinpointing problems or other information about the query.
 *
 * @psalm-immutable
 */
final class Notification
{
    public function __construct(
        private readonly string $code,
        private readonly string $description,
        private readonly ?InputPosition $inputPosition,
        private readonly string $severity,
        private readonly string $title
    ) {}

    /**
     * Returns a notification code for the discovered issue.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Returns a longer description of the notification.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * The position in the query where this notification points to.
     * Not all notifications have a unique position to point to and in that case the position would be set to null.
     */
    public function getInputPosition(): ?InputPosition
    {
        return $this->inputPosition;
    }

    /**
     * The severity level of the notification.
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Returns a short summary of the notification.
     */
    public function getTitle(): string
    {
        return $this->title;
    }
}

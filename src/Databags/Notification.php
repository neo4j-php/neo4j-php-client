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

namespace Laudis\Neo4j\Databags;

final class Notification
{
    private string $code;
    private string $description;
    private InputPosition $inputPosition;
    private string $severity;
    private string $title;

    public function __construct(
        string $code,
        string $description,
        InputPosition $inputPosition,
        string $severity,
        string $title
    ) {
        $this->code = $code;
        $this->description = $description;
        $this->inputPosition = $inputPosition;
        $this->severity = $severity;
        $this->title = $title;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getInputPosition(): InputPosition
    {
        return $this->inputPosition;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}

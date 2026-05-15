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

namespace Laudis\Neo4j\TestkitBackend;

use const JSON_THROW_ON_ERROR;

use JsonException;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\BookmarksConsumerCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Requests\BookmarksSupplierCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BookmarksConsumerRequestResponse;
use Laudis\Neo4j\TestkitBackend\Responses\BookmarksSupplierRequestResponse;

use const PHP_EOL;

use RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * Sends intermediate TestKit responses and reads the matching client request (supplier / consumer callbacks).
 */
final class ClientProtocolBridge
{
    public function __construct(
        private readonly Socket $socket,
        private readonly RequestFactory $factory,
    ) {
    }

    /**
     * @throws JsonException
     *
     * @return list<string>
     */
    public function requestSupplierBookmarks(Uuid $bookmarkManagerId): array
    {
        $callbackId = Uuid::v4();
        $this->sendResponse(new BookmarksSupplierRequestResponse($callbackId, $bookmarkManagerId));
        $request = $this->readDecodedRequest();
        if (!$request instanceof BookmarksSupplierCompletedRequest) {
            throw new RuntimeException('Expected BookmarksSupplierCompleted, got '.get_debug_type($request));
        }

        return $request->bookmarks;
    }

    /**
     * @param list<string> $bookmarks
     *
     * @throws JsonException
     */
    public function requestBookmarksConsumer(Uuid $bookmarkManagerId, array $bookmarks): void
    {
        $callbackId = Uuid::v4();
        $this->sendResponse(new BookmarksConsumerRequestResponse($callbackId, $bookmarkManagerId, $bookmarks));
        $request = $this->readDecodedRequest();
        if (!$request instanceof BookmarksConsumerCompletedRequest) {
            throw new RuntimeException('Expected BookmarksConsumerCompleted, got '.get_debug_type($request));
        }
    }

    /**
     * @throws JsonException
     */
    private function readDecodedRequest(): object
    {
        $message = $this->socket->readMessage();
        if ($message === null) {
            throw new RuntimeException('Expected callback request, got null');
        }

        /** @var array{name: string, data: array<string, mixed>} $payload */
        $payload = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

        return $this->factory->create($payload['name'], $payload['data']);
    }

    private function sendResponse(TestkitResponseInterface $response): void
    {
        $message = json_encode($response, JSON_THROW_ON_ERROR);
        $this->socket->write('#response begin'.PHP_EOL);
        $this->socket->write($message.PHP_EOL);
        $this->socket->write('#response end'.PHP_EOL);
    }
}

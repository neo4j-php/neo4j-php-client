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

namespace Laudis\Neo4j\Bolt;

use Bolt\enum\ServerState;
use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Bolt\Messages\Begin;
use Laudis\Neo4j\Bolt\Messages\Commit;
use Laudis\Neo4j\Bolt\Messages\Discard;
use Laudis\Neo4j\Bolt\Messages\Reset;
use Laudis\Neo4j\Bolt\Messages\Rollback;
use Laudis\Neo4j\Bolt\Messages\Route;
use Laudis\Neo4j\Bolt\Messages\Run;
use Laudis\Neo4j\Bolt\Responses\CommitResponse;
use Laudis\Neo4j\Bolt\Responses\ResultSuccessResponse;
use Laudis\Neo4j\Bolt\Responses\RouteResponse;
use Laudis\Neo4j\Bolt\Responses\RunResponse;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Common\ResponseHelper;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\MessageInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\QueryTypeEnum;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Results\Result;
use Laudis\Neo4j\Results\ResultCursor;
use Laudis\Neo4j\Types\CypherList;
use Throwable;
use WeakReference;

/**
 * @internal
 *
 * @psalm-suppress PossiblyUndefinedStringArrayOffset We temporarily suppress these warnings as we are translating the weakly typed bolt library to the driver.
 * @psalm-suppress MixedArgument
 */
final class BoltConnection
{
    /**
     * @note We are using references to "subscribed results" to maintain backwards compatibility and try and strike
     *       a balance between performance and ease of use.
     *       The connection will discard or pull the results depending on the server state transition. This way end
     *       users don't have to worry about consuming result sets, it will all happen behind te scenes. There are some
     *       edge cases where the result set will be pulled or discarded when it is not strictly necessary, and we
     *       should introduce a "manual" mode later down the road to allow the end users to optimise the result
     *       consumption themselves.
     *
     * @var list<WeakReference<ResultCursor>>
     */
    private array $subscribedResults = [];

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly V4_4|V5|V5_1|V5_2|V5_3  $boltProtocol,
        private readonly ConnectionConfiguration $config
    )
    {
    }

    /**
     * @return ConnectionConfiguration
     */
    public function getConfig(): ConnectionConfiguration
    {
        return $this->config;
    }

    /**
     * @psalm-mutation-free
     */
    public function isOpen(): bool
    {
        return !in_array($this->protocol()->serverState, [ServerState::DISCONNECTED, ServerState::DEFUNCT], true);
    }

    public function consumeResults(): void
    {
        foreach ($this->subscribedResults as $result) {
            $result = $result->get();
            if ($result !== null) {
                $result->consume();
            }
        }

        $this->subscribedResults = [];
    }

    /**
     * Resets the connection.
     *
     * Any of the preconditioned states are: 'READY', 'STREAMING', 'TX_READY', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     * Sends signal: 'INTERRUPT'
     */
    public function reset(): void
    {
        $this->sendMessage(new Reset());
        $this->subscribedResults = [];
    }

    public function sendMessage(MessageInterface $message): Response
    {
        $message->send($this->protocol());

        $response = $this->getResponse();

        $this->assertNoFailure($response);

        return $response;
    }

    public function getResponse(): Response
    {
        $response = $this->protocol()->getResponse();
        if ($response->signature === Signature::FAILURE) {
            throw Neo4jException::fromBoltResponse($response);
        }

        return $response;
    }

    /**
     * Begins a transaction.
     *
     *
     * @param array<string, mixed> $txMetadata
     * @param list<string> $notificationsDisabledCategories
     *
     * Any of the preconditioned states are: 'READY', 'INTERRUPTED'.
     */
    public function begin(
        BookmarkHolder  $bookmarks,
        int|null        $txTimeout,
        array           $txMetadata,
        AccessMode|null $mode,
        string|null     $database,
        string|null     $impersonatedUser,
        string|null     $notificationsMinimumSeverity,
        array           $notificationsDisabledCategories
    ): void
    {
        $this->consumeResults();

        $this->sendMessage(new Begin($bookmarks, $txTimeout, $txMetadata, $mode, $database, $impersonatedUser, $notificationsMinimumSeverity, $notificationsDisabledCategories));
    }

    /**
     * Discards a result.
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     */
    public function discard(int $n, ?int $qid): ResultSuccessResponse
    {
        $result = $this->sendMessage(new Discard($n, $qid));

        return $this->createResultSuccessResponse($result);
    }

    /**
     * Runs a query/statement.
     *
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $txMetadata
     * @param list<string> $notificationsDisabledCategories
     *
     * Any of the preconditioned states are: 'STREAMING', 'TX_STREAMING', 'FAILED', 'INTERRUPTED'.
     */
    public function run(
        string $text,
        array $parameters,
        BookmarkHolder  $bookmarks,
        int|null        $txTimeout,
        array|null      $txMetadata,
        AccessMode|null $mode,
        string|null     $database,
        string|null     $impersonatedUser,
        string|null     $notificationsMinimumSeverity,
        array|null      $notificationsDisabledCategories
    ): RunResponse {
        $response = $this->sendMessage(new Run($text, $parameters, $bookmarks, $txTimeout, $txMetadata, $mode, $database, $impersonatedUser, $notificationsMinimumSeverity, $notificationsDisabledCategories));

        return new RunResponse(
            $response->content['fields'],
            $response->content['t_first'],
            $response->content['qid'],
        );
    }

    /**
     * Commits a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function commit(): CommitResponse
    {
        $this->consumeResults();

        $response = $this->sendMessage(new Commit());

        return new CommitResponse(array_key_exists('bookmark', $response->content) ? new Bookmark($response->content['bookmark']) : null);
    }

    /**
     * Rolls back a transaction.
     *
     * Any of the preconditioned states are: 'TX_READY', 'INTERRUPTED'.
     */
    public function rollback(): void
    {
        $this->consumeResults();

        $this->sendMessage(new Rollback());
    }

    public function protocol(): V4_4|V5|V5_1|V5_2|V5_3|V5_4
    {
        return $this->boltProtocol;
    }

    public function __destruct()
    {
        try {
            if ($this->boltProtocol->serverState === ServerState::FAILED && $this->isOpen()) {
                if ($this->protocol()->serverState === ServerState::STREAMING || $this->protocol()->serverState === ServerState::TX_STREAMING) {
                    $this->consumeResults();
                }

                $this->protocol()->goodbye();

                unset($this->boltProtocol); // has to be set to null as the sockets don't recover nicely contrary to what the underlying code might lead you to believe;
            }
        } catch (Throwable) {
        }
    }

    public function getServerState(): ServerState
    {
        return $this->protocol()->serverState;
    }

    public function subscribeResult(CypherList $result): void
    {
        $this->subscribedResults[] = WeakReference::create($result);
    }

    private function assertNoFailure(Response $response): void
    {
        if ($response->signature === Signature::FAILURE) {
            $this->protocol()->reset()->getResponse(); // what if the reset fails? what should be expected behaviour?
            throw Neo4jException::fromBoltResponse($response);
        }
    }

    /**
     * @param Response $response
     * @return ResultSuccessResponse
     */
    private function createResultSuccessResponse(Response $response): ResultSuccessResponse
    {
        return new ResultSuccessResponse(
            has_more: $response->content['has_more'],
            bookmark: array_key_exists('bookmark', $response->content) ? new Bookmark($response->content['bookmark']) : null,
            db: $response->content['db'] ?? null,
            notification: $response->content['notification'] ?? null,
            plan: $response->content['plan'] ?? null,
            profile: $response->content['profile'] ?? null,
            stats: $response->content['stats'] ?? null,
            t_last: $response->content['t_last'] ?? null,
            t_first: $response->content['t_first'] ?? null,
            type: array_key_exists('type', $response->content) ? QueryTypeEnum::from($response->content['type']) : null,
        );
    }

    public function route(): RouteResponse
    {
        $response = $this->sendMessage(new Route());
        return new RouteResponse();
    }
}

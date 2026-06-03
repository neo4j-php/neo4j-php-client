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

namespace Laudis\Neo4j\Neo4j;

use Bolt\error\ConnectException;
use Exception;

use function is_string;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\Session;
use Laudis\Neo4j\Common\DNSAddressResolver;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AddressResolverInterface;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Bolt\BoltMessageFactory;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\ServerInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\BoltTelemetryApi;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Exception\ConnectionPoolException;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Psr\Http\Message\UriInterface;
use Throwable;
use Psr\Log\LogLevel;

/**
 * Driver for auto client-side routing.
 *
 * @psalm-import-type OGMResults from SummarizedResultFormatter
 */
final class Neo4jDriver implements DriverInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly UriInterface $parsedUrl,
        private readonly Neo4jConnectionPool $pool,
        private readonly SummarizedResultFormatter $formatter,
    ) {
    }

    /**
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public static function create(string|UriInterface $uri, ?DriverConfiguration $configuration = null, ?AuthenticateInterface $authenticate = null, ?SummarizedResultFormatter $formatter = null, ?AddressResolverInterface $resolver = null): self
    {
        if (is_string($uri)) {
            $uri = Uri::create($uri);
        }

        $configuration ??= DriverConfiguration::default();
        $authenticate ??= Authenticate::fromUrl($uri, $configuration->getLogger());
        $resolver ??= new DNSAddressResolver();
        $semaphore = $configuration->getSemaphoreFactory()->create($uri, $configuration);

        /** @psalm-suppress InvalidArgument */
        return new self(
            $uri,
            Neo4jConnectionPool::create($uri, $authenticate, $configuration, $resolver, $semaphore),
            $formatter ?? SummarizedResultFormatter::create(),
        );
    }

    /**
     * @psalm-mutation-free
     *
     * @throws Exception
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $config ??= SessionConfiguration::default();
        $config = $config->merge(SessionConfiguration::fromUri($this->parsedUrl, $this->pool->getLogger()));

        return new Session($config, $this->pool, $this->formatter);
    }

    public function verifyConnectivity(?SessionConfiguration $config = null): bool
    {
        $config ??= SessionConfiguration::default();
        try {
            GeneratorHelper::getReturnFromGenerator($this->pool->acquire($config));
        } catch (ConnectException|ConnectionPoolException $e) {
            $this->pool->getLogger()?->log(LogLevel::WARNING, 'Could not connect to server on URI '.$this->parsedUrl->__toString(), ['error' => $e]);

            return false;
        }

        return true;
    }

    public function getServerInfo(?SessionConfiguration $config = null): ServerInfo
    {
        $config ??= SessionConfiguration::default();

        // Use READ access mode to connect to a follower (read server)
        if ($config->getAccessMode() === null) {
            $config = $config->withAccessMode(AccessMode::READ());
        }

        $connection = GeneratorHelper::getReturnFromGenerator($this->pool->acquire($config));

        return new ServerInfo(
            $connection->getServerAddress(),
            $connection->getProtocol(),
            $connection->getServerAgent()
        );
    }

    public function closeConnections(): void
    {
        $this->pool->close();
    }

    /**
     * @param array<string, mixed>      $parameters
     * @param array<string, mixed>|null $config
     */
    public function executeQuery(string $cypher, array $parameters = [], ?array $config = null): SummarizedResult
    {
        $sessionConfig = SessionConfiguration::fromUri($this->parsedUrl, $this->pool->getLogger());
        $config ??= [];

        if (array_key_exists('database', $config) && $config['database'] !== null) {
            $sessionConfig = $sessionConfig->withDatabase($config['database']);
        }

        $accessMode = AccessMode::READ();
        if (array_key_exists('routing', $config) && $config['routing'] === 'w') {
            $accessMode = AccessMode::WRITE();
        }
        $sessionConfig = $sessionConfig->withAccessMode($accessMode);

        $tsxConfig = TransactionConfiguration::default();
        if (array_key_exists('timeout', $config) && $config['timeout'] !== null) {
            $tsxConfig = $tsxConfig->withTimeout((float) $config['timeout']);
        }

        $txMeta = $config['txMeta'] ?? null;
        $maxConnectionAttempts = 3;
        $connectionAttempt = 0;
        $lastException = null;

        while ($connectionAttempt < $maxConnectionAttempts) {
            $connection = GeneratorHelper::getReturnFromGenerator($this->pool->acquire($sessionConfig));
            $messageFactory = new BoltMessageFactory($connection, $this->pool->getLogger());
            $transientAttempts = 0;

            try {
                while ($transientAttempts < $maxConnectionAttempts) {
                    try {
                        $connection->sendTelemetryIfNeeded(BoltTelemetryApi::DRIVER_EXECUTE_QUERY);

                        $formattedParameters = ParameterHelper::formatParameters($parameters, $connection->getProtocol())->toArray();
                        $bookmarkHolder = new BookmarkHolder(Bookmark::from([]));

                        $connection->begin(
                            $sessionConfig->getDatabase(),
                            $tsxConfig->getTimeout(),
                            $bookmarkHolder,
                            $txMeta,
                        );

                        $meta = $connection->run(
                            $cypher,
                            $formattedParameters,
                            $sessionConfig->getDatabase(),
                            $tsxConfig->getTimeout(),
                            $bookmarkHolder,
                            $sessionConfig->getAccessMode(),
                            $txMeta,
                        );

                        $result = $this->formatter->formatBoltResult(
                            $meta,
                            new BoltResult($connection, $sessionConfig->getFetchSize(), $meta['qid'] ?? -1),
                            $connection,
                            microtime(true),
                            0.0,
                            new Statement($cypher, $parameters),
                            $bookmarkHolder,
                        );
                        $result->preload();

                        $messageFactory->createCommitMessage($bookmarkHolder)->send()->getResponse();

                        return $result;
                    } catch (Throwable $e) {
                        $lastException = $e;
                        if ($this->isTransientExecuteQueryError($e)) {
                            ++$transientAttempts;
                            $connection->reset();

                            continue;
                        }

                        throw $e;
                    }
                }

                throw $lastException ?? new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.General', 'executeQuery failed after transient retries')]);
            } catch (Throwable $e) {
                $lastException = $e;
                ++$connectionAttempt;
                if (!$this->isConnectionExecuteQueryError($e) || $connectionAttempt >= $maxConnectionAttempts) {
                    throw $e;
                }
                $this->pool->clearRoutingTable($sessionConfig);
                $this->pool->close();
            } finally {
                $this->pool->release($connection);
            }
        }

        throw $lastException ?? new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.General', 'executeQuery failed after maximum retries')]);
    }

    private function isTransientExecuteQueryError(Throwable $e): bool
    {
        return $e instanceof Neo4jException && $e->getClassification() === 'TransientError';
    }

    private function isConnectionExecuteQueryError(Throwable $e): bool
    {
        if ($e instanceof ConnectException) {
            return true;
        }

        if ($e instanceof Neo4jException) {
            if ($e->getNeo4jCode() === 'Neo.ClientError.Cluster.NotALeader' || $e->getTitle() === 'NotALeader') {
                return true;
            }
        }

        $message = strtolower($e instanceof Neo4jException ? ($e->getNeo4jMessage() ?? '') : $e->getMessage());

        return str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection closed')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection timeout');
    }
}

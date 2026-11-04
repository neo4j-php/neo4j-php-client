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

use function get_debug_type;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitCallbackResponseInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitCallbackResultInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

use const PHP_EOL;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * Sends callback responses to the TestKit frontend and waits for completion requests.
 */
final class TestkitCallbackDispatcher
{
    public function __construct(
        private readonly Socket $socket,
        private readonly LoggerInterface $logger,
        private readonly CallbackRegistry $callbackRegistry,
        private readonly ContainerInterface $container,
        private readonly RequestFactory $factory,
    ) {
    }

    public function dispatch(TestkitCallbackResponseInterface $callbackResponse): TestkitCallbackResultInterface
    {
        $callbackId = $callbackResponse->getCallbackId();
        $this->callbackRegistry->registerPending($callbackId);
        $this->sendResponse($callbackResponse);

        while (true) {
            $message = $this->socket->readMessage();

            if ($message === null) {
                throw new RuntimeException('Unexpected end of stream while waiting for callback '.$callbackId);
            }

            [$handler, $request] = $this->extractRequest($message);
            $response = $handler->handle($request);
            if ($response !== null) {
                $this->sendResponse($response);
            }

            if ($this->callbackRegistry->hasCompleted($callbackId)) {
                return $this->callbackRegistry->takeCompleted($callbackId);
            }
        }
    }

    private function sendResponse(TestkitResponseInterface $response): void
    {
        $message = json_encode($response, JSON_THROW_ON_ERROR);

        $this->logger->debug('Sending: '.$this->cutoffStringForLogging($message));
        $this->socket->write('#response begin'.PHP_EOL);
        $this->socket->write($message.PHP_EOL);
        $this->socket->write('#response end'.PHP_EOL);
    }

    /**
     * @return array{0: RequestHandlerInterface, 1: object}
     */
    private function extractRequest(string $message): array
    {
        $this->logger->debug('Received: '.$this->cutoffStringForLogging($message));
        /** @var array{name: string, data: iterable<array|scalar|null>} $response */
        $response = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

        $handler = $this->loadRequestHandler($response['name']);
        $request = $this->factory->create($response['name'], $response['data']);

        return [$handler, $request];
    }

    private function loadRequestHandler(string $name): RequestHandlerInterface
    {
        $action = $this->container->get('Laudis\\Neo4j\\TestkitBackend\\Handlers\\'.$name);
        if (!$action instanceof RequestHandlerInterface) {
            throw new UnexpectedValueException(sprintf('Expected action to be an instance of %s, received %s instead', RequestHandlerInterface::class, get_debug_type($action)));
        }

        return $action;
    }

    private function cutoffStringForLogging(string $message): string
    {
        if (mb_strlen($message) > 1000) {
            return substr($message, 0, 1000).'### Long message cut for brevity';
        }

        return $message;
    }
}

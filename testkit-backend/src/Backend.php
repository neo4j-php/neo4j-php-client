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

namespace Laudis\Neo4j\TestkitBackend;

use DI\ContainerBuilder;
use Exception;
use function get_debug_type;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;
use const PHP_EOL;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use UnexpectedValueException;

final class Backend
{
    private Socket $socket;
    private LoggerInterface $logger;
    private ContainerInterface $container;
    private RequestFactory $factory;

    public function __construct(
        Socket $socket,
        LoggerInterface $logger,
        ContainerInterface $container,
        RequestFactory $factory
    ) {
        $this->socket = $socket;
        $this->logger = $logger;
        $this->container = $container;
        $this->factory = $factory;
    }

    /**
     * @throws Exception
     */
    public static function boot(): self
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions(__DIR__.'/../register.php');
        $builder->useAutowiring(true);
        $container = $builder->build();

        $logger = $container->get(LoggerInterface::class);
        $logger->info('Booting testkit backend ...');
        Socket::setupEnvironment();
        $tbr = new self(Socket::fromEnvironment(), $logger, $container, new RequestFactory());
        $logger->info('Testkit booted');

        return $tbr;
    }

    /**
     * @throws JsonException
     */
    public function handle(): void
    {
        while (true) {
            $message = $this->socket->readMessage();
            if ($message === null) {
                $this->socket->reset();
                continue;
            }

            [$handler, $request] = $this->extractRequest($message);

            try {
                $this->properSendoff($handler->handle($request));
            } catch (Throwable $e) {
                $this->logger->error($e->__toString());
                $this->properSendoff(new BackendErrorResponse($e->getMessage()));
            }
        }
    }

    private function loadRequestHandler(string $name): RequestHandlerInterface
    {
        $action = $this->container->get('Laudis\\Neo4j\\TestkitBackend\\Handlers\\'.$name);
        if (!$action instanceof RequestHandlerInterface) {
            $str = printf(
                'Expected action to be an instance of %s, received %s instead',
                RequestHandlerInterface::class,
                get_debug_type($action)
            );
            throw new UnexpectedValueException($str);
        }

        return $action;
    }

    /**
     * @param string $message
     */
    private function properSendoff(TestkitResponseInterface $response): void
    {
        $message = json_encode($response, JSON_THROW_ON_ERROR);

        $this->logger->debug('Sending: '.$message);
        $this->socket->write('#response begin'.PHP_EOL);
        $this->socket->write($message.PHP_EOL);
        $this->socket->write('#response end'.PHP_EOL);
    }

    /**
     * @return array{0: RequestHandlerInterface, 1: object}
     */
    private function extractRequest(string $message): array
    {
        $this->logger->debug('Received: '.$message);
        /** @var array{name: string, data: iterable<array|scalar|null>} $response */
        $response = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

        $handler = $this->loadRequestHandler($response['name']);
        $request = $this->factory->create($response['name'], $response['data']);

        return [$handler, $request];
    }
}

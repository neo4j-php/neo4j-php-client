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
use const JSON_THROW_ON_ERROR;
use JsonException;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use const PHP_EOL;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use function substr;
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
        $message = '';

        while (true) {
            try {
                $buffer = $this->socket->read();

                if (!str_starts_with($buffer, '#')) {
                    $message .= substr($buffer, 0, -1);
                }
                if ($buffer === '#request end'.PHP_EOL) {
                    break;
                }
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'socket_read() failed: reason: Connection reset by peer') {
                    $this->logger->info('Connection reset by peer, resetting socket...');
                    $this->socket->reset();
                    $this->logger->info('Socket reset successfully');

                    continue;
                }

                throw $e;
            }
        }

        $this->logger->debug('Received: '.$message);
        /** @var array{name: string, data: array} $response */
        $response = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

        $handler = $this->loadRequestHandler($response['name']);
        $request = $this->factory->create($response['name'], $response['data']);

        $message = json_encode($handler->handle($request), JSON_THROW_ON_ERROR);
        $this->logger->debug('Sent: '.$message);

        $this->socket->write('#response begin'.PHP_EOL);
        $this->socket->write($message.PHP_EOL);
        $this->socket->write('#response end'.PHP_EOL);
    }

    private function loadRequestHandler(string $name): ActionInterface
    {
        $action = $this->container->get($name);
        if (!$action instanceof ActionInterface) {
            $str = printf(
                'Expected action to be an instance of %s, received %s instead',
                ActionInterface::class,
                get_debug_type($action)
            );
            throw new UnexpectedValueException($str);
        }

        return $action;
    }
}

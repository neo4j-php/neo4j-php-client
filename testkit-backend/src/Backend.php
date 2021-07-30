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
use function is_array;
use function is_string;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use const PHP_EOL;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use UnexpectedValueException;

final class Backend
{
    private Socket $socket;
    private LoggerInterface $logger;
    private ContainerInterface $container;

    public function __construct(Socket $socket, LoggerInterface $logger, ContainerInterface $container)
    {
        $this->socket = $socket;
        $this->logger = $logger;
        $this->container = $container;
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
        $tbr = new self(Socket::fromEnvironment(), $logger, $container);
        $logger->info('Testkit booted');

        return $tbr;
    }

    /**
     * @throws JsonException
     */
    public function handle(): void
    {
        $this->logger->debug('Accepting connection');
        $message = '';

        while (true) {
            try {
                $buffer = $this->socket->read();
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'socket_read() failed: reason: Connection reset by peer') {
                    $this->logger->info('Connection reset by peer, resetting socket...');
                    $this->socket->reset();
                    $this->logger->info('Socket reset successfully');

                    continue;
                }

                throw $e;
            }
            if (!str_starts_with($buffer, '#')) {
                $message .= substr($buffer, 0, -1);
            }
            if ($buffer === '#request end'.PHP_EOL) {
                break;
            }
        }

        $this->logger->debug($message);
        $response = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($response)) {
            throw new RuntimeException('Did not receive an array');
        }

        $name = $response['name'] ?? null;
        if (!is_string($name)) {
            throw new RuntimeException('Did not receive a name');
        }

        $action = $this->loadAction($name);

        $message = json_encode($action->handle($response), JSON_THROW_ON_ERROR);
        $this->logger->debug($message);

        $this->socket->write('#response begin'.PHP_EOL);
        $this->socket->write($message.PHP_EOL);
        $this->socket->write('#response end'.PHP_EOL);
    }

    private function loadAction(string $name): ActionInterface
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

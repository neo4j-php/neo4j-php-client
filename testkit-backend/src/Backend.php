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
use function json_decode;
use const JSON_THROW_ON_ERROR;
use JsonException;
use const PHP_EOL;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

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

    public static function boot(): self
    {
        Socket::setupEnvironment();

        $builder = new ContainerBuilder();
        $builder->addDefinitions(__DIR__.'/../register.php');
        $builder->useAutowiring(true);
        $container = $builder->build();

        return new self(Socket::fromAddressAndPort(), $container->get(LoggerInterface::class), $container);
    }

    /**
     * @throws JsonException
     */
    public function handle(): void
    {
        $this->logger->debug('Accepting connection');
        $message = '';

        while (true) {
            $buffer = $this->socket->read();
            if (!str_starts_with($buffer, '#')) {
                $message .= substr($buffer, 0, -1);
            }
            if ($buffer === '#request end'.PHP_EOL) {
                break;
            }
        }

        $this->logger->debug($message);
        $response = json_decode($message, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($response['name'])) {
            throw new RuntimeException('Did not receive a name');
        }

        $action = $this->container->get($response['name']);

        $message = json_encode($action->handle($response), JSON_THROW_ON_ERROR);
        $this->logger->debug($message);

        $this->socket->write('#response begin'.PHP_EOL);
        $this->socket->write($message.PHP_EOL);
        $this->socket->write('#response end'.PHP_EOL);
    }
}

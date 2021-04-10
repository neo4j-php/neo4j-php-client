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

namespace Laudis\Neo4j\Network\Bolt;

use Bolt\Bolt;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\ConnectionManager;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfig;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\BasicFormatter;

/**
 * @psalm-type ParsedUrl = array{fragment?: string, host?: string, pass?: string, path?: string, port?: int, query?: string, scheme?: string, user?: string}
 *
 * @implements DriverInterface<Bolt>
 */
final class BoltDriver implements DriverInterface
{
    /** @var ParsedUrl */
    private array $parsedUrl;
    private string $userAgent;
    private AuthenticateInterface $auth;
    private ConnectionManager $manager;
    private string $defaultDatabase;

    /**
     * @param ParsedUrl $parsedUrl
     */
    public function __construct(array $parsedUrl, string $userAgent, AuthenticateInterface $auth, ConnectionManager $manager, string $defaultDatabase = 'neo4j')
    {
        $this->parsedUrl = $parsedUrl;
        $this->userAgent = $userAgent;
        $this->auth = $auth;
        $this->manager = $manager;
        $this->defaultDatabase = $defaultDatabase;
    }

    /**
     * @throws Exception
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface
    {
        $config ??= SessionConfiguration::create($this->defaultDatabase);

        return new Session($this, $config, new BasicFormatter());
    }

    public function acquireConnection(SessionConfiguration $sessionConfig, TransactionConfig $tsxConfig): Bolt
    {
        try {
            $bolt = new Bolt($this->manager->acquireConnection($this->parsedUrl));
            $this->auth->authenticateBolt($bolt, $this->parsedUrl, $this->userAgent);
        } catch (Exception $e) {
            throw new Neo4jException(new Vector([new Neo4jError($e->getMessage(), '')]), $e);
        }

        return $bolt;
    }
}

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

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\NewClientCertificateProviderRequest;
use Laudis\Neo4j\TestkitBackend\Responses\ClientCertificateProviderResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<NewClientCertificateProviderRequest>
 */
final class NewClientCertificateProvider implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param NewClientCertificateProviderRequest $request
     */
    public function handle($request): ClientCertificateProviderResponse
    {
        $id = Uuid::v4();

        // Initialize the certificate provider with proper structure
        $certificateData = [
            'certificates' => [],
            'current_index' => 0,
            'rotation_enabled' => true,
            'created_at' => time(),
        ];

        // Store the certificate provider in the repository
        // This will be used later when certificates are requested
        $this->repository->addClientCertificateProvider($id, $certificateData);

        return new ClientCertificateProviderResponse($id);
    }
}

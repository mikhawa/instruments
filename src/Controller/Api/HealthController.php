<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point de contrôle de l'API : utilisé par le front React et la supervision.
 */
final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'indisponible';
        }

        return $this->json(
            ['status' => 'ok', 'database' => $database],
            'ok' === $database ? 200 : 503,
        );
    }
}

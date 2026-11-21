<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function check(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
            $dbStatus = 'connected';
            $statusCode = Response::HTTP_OK;
            $status = 'ok';
        } catch (\Throwable $e) {
            $dbStatus = 'disconnected';
            $statusCode = Response::HTTP_SERVICE_UNAVAILABLE;
            $status = 'unhealthy';
        }

        return new JsonResponse([
            'status' => $status,
            'database' => $dbStatus,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $statusCode);
    }
}

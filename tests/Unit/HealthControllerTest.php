<?php

namespace App\Tests\Unit;

use App\Controller\HealthController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class HealthControllerTest extends TestCase
{
    public function testHealthCheckSuccesQuandBaseConnectee(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1');

        $controller = new HealthController();
        $response = $controller->check($connection);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        $data = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('ok', $data['status']);
        $this->assertEquals('connected', $data['database']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testHealthCheckDegradeQuandBaseDeconnectee(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willThrowException(new \Exception('Connection failed'));

        $controller = new HealthController();
        $response = $controller->check($connection);

        $this->assertEquals(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('unhealthy', $data['status']);
        $this->assertEquals('disconnected', $data['database']);
    }
}

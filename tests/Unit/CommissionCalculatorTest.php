<?php

namespace App\Tests\Unit;

use App\Entity\ConfigCommission;
use App\Repository\ConfigCommissionRepository;
use App\Service\Pricing\CommissionCalculator;
use PHPUnit\Framework\TestCase;

class CommissionCalculatorTest extends TestCase
{
    public function testCalculerAvecCommissionActive(): void
    {
        $config = new ConfigCommission();
        $config->setTaux('15.00');
        $config->setEstActive(true);

        $repo = $this->createMock(ConfigCommissionRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['estActive' => true])
            ->willReturn($config);

        $calculator = new CommissionCalculator($repo);
        $result = $calculator->calculer(200.0);

        $this->assertEquals(200.0, $result['base']);
        $this->assertEquals(15.0, $result['taux']);
        $this->assertEquals(30.0, $result['commission']); // 200 * 15% = 30
        $this->assertEquals(230.0, $result['total']);     // 200 + 30 = 230
    }

    public function testCalculerAvecTauxParDefaut(): void
    {
        $repo = $this->createMock(ConfigCommissionRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['estActive' => true])
            ->willReturn(null);

        $calculator = new CommissionCalculator($repo);
        $result = $calculator->calculer(100.0);

        $this->assertEquals(100.0, $result['base']);
        $this->assertEquals(12.0, $result['taux']);        // Repli par défaut = 12%
        $this->assertEquals(12.0, $result['commission']);  // 100 * 12% = 12
        $this->assertEquals(112.0, $result['total']);      // 100 + 12 = 112
    }

    public function testCalculerAvecArrondi(): void
    {
        $config = new ConfigCommission();
        $config->setTaux('12.50');
        $config->setEstActive(true);

        $repo = $this->createMock(ConfigCommissionRepository::class);
        $repo->method('findOneBy')->willReturn($config);

        $calculator = new CommissionCalculator($repo);
        $result = $calculator->calculer(33.33);

        $this->assertEquals(33.33, $result['base']);
        $this->assertEquals(4.17, $result['commission']); // round(33.33 * 12.5 / 100, 2) = 4.17
        $this->assertEquals(37.50, $result['total']);     // 33.33 + 4.17 = 37.50
    }
}

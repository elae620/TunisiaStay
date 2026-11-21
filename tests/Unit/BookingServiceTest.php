<?php

namespace App\Tests\Unit;

use App\Entity\Logement;
use App\Entity\Utilisateur;
use App\Repository\ConfigCommissionRepository;
use App\Service\Booking\AvailabilityChecker;
use App\Service\Booking\BookingService;
use App\Service\Pricing\CommissionCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BookingServiceTest extends TestCase
{
    public function testCreerReservationQuandDisponible(): void
    {
        $checker = $this->createMock(AvailabilityChecker::class);
        $checker->method('isAvailable')->willReturn(true);

        $configRepo = $this->createMock(ConfigCommissionRepository::class);
        $configRepo->method('findOneBy')->willReturn(null); // Taux par défaut = 12%
        $commissionCalc = new CommissionCalculator($configRepo);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $bookingService = new BookingService($checker, $commissionCalc, $em);

        $logement = new Logement();
        $logement->setPrixParNuit('100.00');
        $logement->setDevise('TND');

        $voyageur = new Utilisateur();
        $voyageur->setEmail('voyageur@example.com');

        $checkIn = new \DateTime('2026-06-01');
        $checkOut = new \DateTime('2026-06-05'); // 4 nuits

        $reservation = $bookingService->creer($logement, $voyageur, $checkIn, $checkOut, 2);

        $this->assertEquals(4, $reservation->getNbNuits());
        $this->assertEquals('400', $reservation->getMontantBase());
        $this->assertEquals('12', $reservation->getTauxCommission());
        $this->assertEquals('48', $reservation->getMontantCommission()); // 400 * 12% = 48
        $this->assertEquals('448', $reservation->getMontantTotal());    // 400 + 48 = 448
        $this->assertEquals('en_attente', $reservation->getStatut());
        $this->assertEquals('TND', $reservation->getDevise());
    }

    public function testCreerReservationQuandIndisponibleLeveException(): void
    {
        $checker = $this->createMock(AvailabilityChecker::class);
        $checker->method('isAvailable')->willReturn(false);

        $configRepo = $this->createMock(ConfigCommissionRepository::class);
        $commissionCalc = new CommissionCalculator($configRepo);
        $em = $this->createMock(EntityManagerInterface::class);

        $bookingService = new BookingService($checker, $commissionCalc, $em);

        $logement = new Logement();
        $voyageur = new Utilisateur();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ce logement n\'est pas disponible pour ces dates.');

        $bookingService->creer(
            $logement,
            $voyageur,
            new \DateTime('2026-06-01'),
            new \DateTime('2026-06-05'),
            2
        );
    }
}

<?php

namespace App\Repository;
use App\Entity\Logement;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }
    // Réservations d'un propriétaire (via ses logements)
    public function findByProprietaire(Utilisateur $proprietaire): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.logement', 'l')
            ->where('l.proprietaire = :proprietaire')
            ->setParameter('proprietaire', $proprietaire)
            ->orderBy('r.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findConflicts(Logement $logement, \DateTime $checkIn, \DateTime $checkOut): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.logement = :logement')
            ->andWhere('r.statut IN (:statuts)')
            ->andWhere('r.dateArrivee < :checkOut AND r.dateDepart > :checkIn')
            ->setParameter('logement', $logement)
            ->setParameter('statuts', ['confirme', 'en_attente'])
            ->setParameter('checkIn', $checkIn)
            ->setParameter('checkOut', $checkOut)
            ->getQuery()
            ->getResult();
    }
    // Réservations d'un voyageur
    public function findByVoyageur(Utilisateur $voyageur): array
    {
        return $this->findBy(
            ['voyageur' => $voyageur],
            ['creeLe' => 'DESC']
        );
    }

    public function findByDateRange(?string $dateDebut, ?string $dateFin): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.creeLe', 'DESC');

        if ($dateDebut) {
            $qb->andWhere('r.dateArrivee >= :dateDebut')
               ->setParameter('dateDebut', new \DateTime($dateDebut));
        }

        if ($dateFin) {
            $qb->andWhere('r.dateDepart <= :dateFin')
               ->setParameter('dateFin', new \DateTime($dateFin));
        }

        return $qb->getQuery()->getResult();
    }

    public function findActiveByLogement(Logement $logement): array
    {
        return $this->findBy(
            ['logement' => $logement, 'statut' => ['confirme', 'en_attente']]
        );
    }

    /**
     * Réservations validées et passées d'un voyageur pour un logement donné.
     * Statuts acceptés : 'confirme', 'termine', 'terminee'
     * Avec dateDepart <= maintenant.
     *
     * @return Reservation[]
     */
    public function findValidatedPastReservations(Utilisateur $voyageur, Logement $logement): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('r')
            ->where('r.voyageur = :voyageur')
            ->andWhere('r.logement = :logement')
            ->andWhere('r.statut IN (:statuts)')
            ->andWhere('r.dateDepart <= :now')
            ->setParameter('voyageur', $voyageur)
            ->setParameter('logement', $logement)
            ->setParameter('statuts', ['confirme', 'termine', 'terminee'])
            ->setParameter('now', $now)
            ->orderBy('r.dateDepart', 'DESC')
            ->getQuery()
            ->getResult();
    }
    /**
     * Automatically mark past confirmed reservations as 'termine'.
     * Call this before querying revenues/commissions to ensure data is up-to-date.
     */
    public function autoCompleteReservations(): int
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.statut', ':newStatut')
            ->where('r.statut = :confirme')
            ->andWhere('r.dateDepart <= :now')
            ->setParameter('newStatut', 'termine')
            ->setParameter('confirme', 'confirme')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    // Revenus d'un propriétaire
    public function getTotalRevenusProprietaire(Utilisateur $proprietaire): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.montantBase - r.montantCommission) as total')
            ->join('r.logement', 'l')
            ->where('l.proprietaire = :proprietaire')
            ->andWhere('r.statut IN (:statuts)')
            ->setParameter('proprietaire', $proprietaire)
            ->setParameter('statuts', ['termine', 'confirme'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    // Revenus globaux de la plateforme (commissions)
    public function getTotalPlatformCommissions(): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.montantCommission) as total')
            ->where('r.statut IN (:statuts)')
            ->setParameter('statuts', ['termine', 'confirme'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }
    //    /**
    //     * @return Reservation[] Returns an array of Reservation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Reservation
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

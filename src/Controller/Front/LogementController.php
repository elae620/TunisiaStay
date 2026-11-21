<?php
// src/Controller/Front/LogementController.php

namespace App\Controller\Front;

use App\Entity\Logement;
use App\Repository\AvisRepository;
use App\Repository\FavoriRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LogementController extends AbstractController
{
    #[Route('/logement/{id}-{slug}', name: 'app_logement_show')]
    public function show(
        Logement $logement,
        string $slug,
        AvisRepository $avisRepo,
        ReservationRepository $reservationRepo,
        FavoriRepository $favoriRepo
    ): Response {
        // Rediriger si slug incorrect
        if ($logement->getSlug() !== $slug) {
            return $this->redirectToRoute('app_logement_show', [
                'id'   => $logement->getId(),
                'slug' => $logement->getSlug(),
            ], 301);
        }

        $avis = $avisRepo->findBy(
            ['logement' => $logement, 'estVisible' => true],
            ['creeLe' => 'DESC']
        );

        // Dates bloquées (JSON pour le calendrier JS)
        $reservationsActives = $reservationRepo->findActiveByLogement($logement);
        $datesBloquees = [];
        foreach ($reservationsActives as $resa) {
            $period = new \DatePeriod(
                $resa->getDateArrivee(),
                new \DateInterval('P1D'),
                $resa->getDateDepart()
            );
            foreach ($period as $dt) {
                $datesBloquees[] = $dt->format('Y-m-d');
            }
        }

        // Favori status
        $isFavori = false;
        if ($this->getUser()) {
            $isFavori = (bool) $favoriRepo->findOneBy([
                'voyageur' => $this->getUser(),
                'logement' => $logement,
            ]);
        }

        // Auto-compléter les réservations passées
        $reservationRepo->autoCompleteReservations();

        // Vérification de l'éligibilité à déposer un avis (réservation validée dont la date est déjà passée)
        $peutLaisserAvis = false;
        $reservationEligible = null;
        $aDejaDonneAvis = false;

        $user = $this->getUser();
        if ($user) {
            $pastReservations = $reservationRepo->findValidatedPastReservations($user, $logement);
            if (!empty($pastReservations)) {
                foreach ($pastReservations as $r) {
                    // Vérifier que cet utilisateur n'a pas déjà laissé un avis pour cette résa
                    $dejaAvis = $avisRepo->findOneBy(['reservation' => $r]);
                    if (!$dejaAvis) {
                        $reservationEligible = $r;
                        break;
                    }
                }

                $totalAvisUser = $avisRepo->count(['voyageur' => $user, 'logement' => $logement]);
                if ($reservationEligible && $totalAvisUser < count($pastReservations)) {
                    $peutLaisserAvis = true;
                } else {
                    $aDejaDonneAvis = true;
                }
            }
        }

        return $this->render('front/logement_show.html.twig', [
            'logement'             => $logement,
            'avis'                 => $avis,
            'datesBloquees'        => json_encode($datesBloquees),
            'isFavori'             => $isFavori,
            'peutLaisserAvis'      => $peutLaisserAvis,
            'reservationEligible'  => $reservationEligible,
            'aDejaDonneAvis'       => $aDejaDonneAvis,
            'noteMoyenne'          => count($avis) > 0
                ? round(array_sum(array_map(fn($a) => $a->getNote(), $avis)) / count($avis), 1)
                : null,
        ]);
    }
}
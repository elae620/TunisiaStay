<?php
// src/Controller/Front/ReviewController.php

namespace App\Controller\Front;

use App\Entity\Avis;
use App\Entity\Logement;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\AvisRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ReviewController extends AbstractController
{
    #[Route('/avis/nouveau/logement/{id}', name: 'app_review_logement_new', methods: ['POST'])]
    public function newFromLogement(
        Logement $logement,
        Request $request,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepo,
        AvisRepository $avisRepo
    ): Response {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour laisser un avis.');
        }

        // Auto-compléter les réservations passées
        $reservationRepo->autoCompleteReservations();

        // Récupérer les réservations validées et terminées de l'utilisateur pour ce logement
        $pastReservations = $reservationRepo->findValidatedPastReservations($user, $logement);
        if (empty($pastReservations)) {
            $this->addFlash('error', 'Vous devez avoir effectué une réservation validée et terminée pour pouvoir laisser un avis.');
            return $this->redirectToRoute('app_logement_show', [
                'id'   => $logement->getId(),
                'slug' => $logement->getSlug(),
            ]);
        }

        // Trouver la réservation éligible sans avis
        $reservationEligible = null;
        $requestedResaId = $request->request->get('reservation_id');
        if ($requestedResaId) {
            foreach ($pastReservations as $r) {
                if ($r->getId() === (int) $requestedResaId) {
                    $dejaAvis = $avisRepo->findOneBy(['reservation' => $r]);
                    if (!$dejaAvis) {
                        $reservationEligible = $r;
                    }
                    break;
                }
            }
        }

        if (!$reservationEligible) {
            foreach ($pastReservations as $r) {
                $dejaAvis = $avisRepo->findOneBy(['reservation' => $r]);
                if (!$dejaAvis) {
                    $reservationEligible = $r;
                    break;
                }
            }
        }

        $totalAvisUser = $avisRepo->count(['voyageur' => $user, 'logement' => $logement]);
        if (!$reservationEligible || $totalAvisUser >= count($pastReservations)) {
            $this->addFlash('error', 'Vous avez déjà déposé un avis pour votre séjour.');
            return $this->redirectToRoute('app_logement_show', [
                'id'   => $logement->getId(),
                'slug' => $logement->getSlug(),
            ]);
        }

        $note = (int) $request->request->get('note', 0);
        if ($note < 1 || $note > 5) {
            $this->addFlash('error', 'Veuillez sélectionner une note entre 1 et 5 étoiles.');
            return $this->redirectToRoute('app_logement_show', [
                'id'   => $logement->getId(),
                'slug' => $logement->getSlug(),
            ]);
        }

        $avis = new Avis();
        $avis->setVoyageur($user)
             ->setLogement($logement)
             ->setReservation($reservationEligible)
             ->setNote($note)
             ->setCommentaire($request->request->get('commentaire'))
             ->setNoteNettete($request->request->get('note_nettete') ? (int) $request->request->get('note_nettete') : null)
             ->setNoteEmplacement($request->request->get('note_emplacement') ? (int) $request->request->get('note_emplacement') : null)
             ->setNoteRapportQualite($request->request->get('note_rapport_qualite') ? (int) $request->request->get('note_rapport_qualite') : null)
             ->setEstVisible(true)
             ->setCreeLe(new \DateTime());

        $em->persist($avis);
        $em->flush();

        $this->addFlash('success', '⭐ Merci pour votre avis !');
        return $this->redirectToRoute('app_logement_show', [
            'id'   => $logement->getId(),
            'slug' => $logement->getSlug(),
        ]);
    }

    #[Route('/avis/nouveau/{id}', name: 'app_review_new', methods: ['POST'])]
    public function new(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $em,
        AvisRepository $avisRepo
    ): Response {
        if ($reservation->getVoyageur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier que la réservation est validée et que sa date est passée
        $isValidee = in_array($reservation->getStatut(), ['confirme', 'termine', 'terminee']);
        $now = new \DateTime();
        $isDatePassee = $reservation->getDateDepart() <= $now;

        if (!$isValidee || !$isDatePassee) {
            $this->addFlash('error', 'Vous ne pouvez laisser un avis que pour une réservation validée et terminée.');
            return $this->redirectToRoute('app_logement_show', [
                'id'   => $reservation->getLogement()->getId(),
                'slug' => $reservation->getLogement()->getSlug(),
            ]);
        }

        // Vérifier qu'un avis n'a pas déjà été laissé pour cette réservation
        if ($avisRepo->findOneBy(['reservation' => $reservation])) {
            $this->addFlash('error', 'Vous avez déjà laissé un avis pour cette réservation.');
            return $this->redirectToRoute('app_logement_show', [
                'id'   => $reservation->getLogement()->getId(),
                'slug' => $reservation->getLogement()->getSlug(),
            ]);
        }

        $note = (int) $request->request->get('note', 0);
        if ($note < 1 || $note > 5) {
            $this->addFlash('error', 'Veuillez sélectionner une note entre 1 et 5 étoiles.');
            return $this->redirectToRoute('app_logement_show', [
                'id'   => $reservation->getLogement()->getId(),
                'slug' => $reservation->getLogement()->getSlug(),
            ]);
        }

        $avis = new Avis();
        $avis->setVoyageur($this->getUser())
             ->setLogement($reservation->getLogement())
             ->setReservation($reservation)
             ->setNote($note)
             ->setCommentaire($request->request->get('commentaire'))
             ->setNoteNettete($request->request->get('note_nettete') ? (int) $request->request->get('note_nettete') : null)
             ->setNoteEmplacement($request->request->get('note_emplacement') ? (int) $request->request->get('note_emplacement') : null)
             ->setNoteRapportQualite($request->request->get('note_rapport_qualite') ? (int) $request->request->get('note_rapport_qualite') : null)
             ->setEstVisible(true)
             ->setCreeLe(new \DateTime());

        $em->persist($avis);
        $em->flush();

        $this->addFlash('success', '⭐ Merci pour votre avis !');
        return $this->redirectToRoute('app_logement_show', [
            'id'   => $reservation->getLogement()->getId(),
            'slug' => $reservation->getLogement()->getSlug(),
        ]);
    }
}
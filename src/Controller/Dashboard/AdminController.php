<?php
// src/Controller/Dashboard/AdminController.php

namespace App\Controller\Dashboard;

use App\Entity\Logement;
use App\Entity\Utilisateur;
use App\Repository\LogementRepository;
use App\Repository\ReservationRepository;
use App\Entity\ConfigCommission;
use App\Repository\ConfigCommissionRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(
        LogementRepository $logements,
        ReservationRepository $reservations,
        UtilisateurRepository $utilisateurs
    ): Response {
        // Auto-complete past confirmed reservations
        $reservations->autoCompleteReservations();

        $currentMonth = new \DateTimeImmutable('first day of this month');
        $startDate = $currentMonth->modify('-5 months');
        $monthlyStats = [];

        for ($offset = 0; $offset < 6; ++$offset) {
            $month = $startDate->modify(sprintf('+%d months', $offset));
            $monthlyStats[$month->format('Y-m')] = [
                'label' => $month->format('m/Y'),
                'reservations' => 0,
                'utilisateurs' => 0,
                'logements' => 0,
            ];
        }

        foreach ([
            [$reservations, 'r', 'reservations'],
            [$utilisateurs, 'u', 'utilisateurs'],
            [$logements, 'l', 'logements'],
        ] as [$repository, $alias, $metric]) {
            $records = $repository->createQueryBuilder($alias)
                ->select(sprintf('%s.creeLe AS creeLe', $alias))
                ->andWhere(sprintf('%s.creeLe >= :startDate', $alias))
                ->setParameter('startDate', $startDate)
                ->getQuery()
                ->getScalarResult();

            foreach ($records as $record) {
                $monthKey = (new \DateTimeImmutable((string) $record['creeLe']))->format('Y-m');
                if (isset($monthlyStats[$monthKey])) {
                    ++$monthlyStats[$monthKey][$metric];
                }
            }
        }

        $plotTop = 24;
        $plotBottom = 220;
        $plotLeft = 64;
        $plotRight = 760;
        $maxValue = max(array_map(
            static fn (array $month): int => max($month['reservations'], $month['utilisateurs'], $month['logements']),
            $monthlyStats
        ));
        $chartMax = max(4, (int) ceil($maxValue / 4) * 4);
        $months = array_values($monthlyStats);
        $seriesConfig = [
            'reservations' => ['label' => 'dashboard.reservations', 'color' => '#2E86AB'],
            'utilisateurs' => ['label' => 'dashboard.utilisateurs', 'color' => '#8B5CF6'],
            'logements' => ['label' => 'nav.logements', 'color' => '#F0A500'],
        ];
        $series = [];

        foreach ($seriesConfig as $metric => $config) {
            $path = '';
            $lastIndex = count($months) - 1;

            foreach ($months as $index => $month) {
                $x = $plotLeft + ($plotRight - $plotLeft) * $index / $lastIndex;
                $y = $plotBottom - $month[$metric] / $chartMax * ($plotBottom - $plotTop);

                if ($index === 0) {
                    $path = sprintf('M %.2F %.2F', $x, $y);
                    continue;
                }

                $previousX = $plotLeft + ($plotRight - $plotLeft) * ($index - 1) / $lastIndex;
                $previousY = $plotBottom - $months[$index - 1][$metric] / $chartMax * ($plotBottom - $plotTop);
                $controlX = ($previousX + $x) / 2;
                $path .= sprintf(' C %.2F %.2F, %.2F %.2F, %.2F %.2F', $controlX, $previousY, $controlX, $y, $x, $y);
            }

            $series[] = [
                'label' => $config['label'],
                'color' => $config['color'],
                'path' => $path,
                'points' => array_map(
                    static fn (int $index, array $month): array => [
                        'x' => $plotLeft + ($plotRight - $plotLeft) * $index / $lastIndex,
                        'y' => $plotBottom - $month[$metric] / $chartMax * ($plotBottom - $plotTop),
                        'value' => $month[$metric],
                    ],
                    array_keys($months),
                    $months
                ),
            ];
        }

        $chartTicks = [];
        for ($index = 0; $index <= 4; ++$index) {
            $chartTicks[] = [
                'value' => (int) ($chartMax * $index / 4),
                'y' => $plotBottom - ($plotBottom - $plotTop) * $index / 4,
            ];
        }

        return $this->render('dashboard/admin/index.html.twig', [
            'totalLogements'        => $logements->count([]),
            'logementsPending'      => $logements->findBy(['statut' => 'en_attente']),
            'reservationsPending'   => $reservations->findBy(['statut' => 'en_attente'], ['creeLe' => 'DESC']),
            'totalReservations'     => $reservations->count([]),
            'totalUtilisateurs'     => $utilisateurs->count([]),
            'dernieresReservations' => $reservations->findBy([], ['creeLe' => 'DESC'], 5),
            'revenusTotaux'         => $reservations->getTotalPlatformCommissions(),
            'dashboardChart' => [
                'months' => $months,
                'ticks' => $chartTicks,
                'series' => $series,
            ],
        ]);
    }

    #[Route('/admin/logements', name: 'admin_logements')]
    public function logements(LogementRepository $repo): Response
    {
        return $this->render('dashboard/admin/logements.html.twig', [
            'logements' => $repo->findBy([], ['creeLe' => 'DESC']),
        ]);
    }

    #[Route('/admin/logement/{id}/valider', name: 'admin_logement_valider')]
    public function valider(Logement $logement, EntityManagerInterface $em): Response
    {
        $logement->setStatut('publie');
        $em->flush();
        $this->addFlash('success', '✅ Logement validé et publié !');
        return $this->redirectToRoute('admin_logements');
    }

    #[Route('/admin/logement/{id}/rejeter', name: 'admin_logement_rejeter')]
    public function rejeter(Logement $logement, EntityManagerInterface $em): Response
    {
        $logement->setStatut('suspendu');
        $em->flush();
        $this->addFlash('success', 'Logement suspendu.');
        return $this->redirectToRoute('admin_logements');
    }

    #[Route('/admin/utilisateurs', name: 'admin_utilisateurs')]
    public function utilisateurs(UtilisateurRepository $repo): Response
    {
        return $this->render('dashboard/admin/utilisateurs.html.twig', [
            'utilisateurs' => $repo->findBy([], ['creeLe' => 'DESC']),
        ]);
    }

    #[Route('/admin/utilisateur/{id}/supprimer', name: 'admin_utilisateur_supprimer', methods: ['POST'])]
    public function supprimerUtilisateur(
        int $id,
        UtilisateurRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $utilisateur = $repo->find($id);

        if (!$utilisateur) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_utilisateurs');
        }

        // Empêcher l'admin de se supprimer lui-même
        if ($utilisateur === $this->getUser()) {
            $this->addFlash('error', '⚠️ Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_utilisateurs');
        }

        // Empêcher la suppression d'un autre admin
        if (in_array('ROLE_ADMIN', $utilisateur->getRoles())) {
            $this->addFlash('error', '⛔ Impossible de supprimer un compte administrateur.');
            return $this->redirectToRoute('admin_utilisateurs');
        }

        $nomComplet = $utilisateur->getPrenom() . ' ' . $utilisateur->getNom();
        $em->remove($utilisateur);
        $em->flush();

        $this->addFlash('success', '🗑️ L\'utilisateur "' . $nomComplet . '" a été supprimé avec succès.');
        return $this->redirectToRoute('admin_utilisateurs');
    }

    #[Route('/admin/reservations', name: 'admin_reservations')]
    public function reservations(Request $request, ReservationRepository $repo): Response
    {
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $reservations = $repo->findByDateRange($dateDebut, $dateFin);

        return $this->render('dashboard/admin/reservations.html.twig', [
            'reservations' => $reservations,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);
    }

    #[Route('/admin/commissions', name: 'admin_commissions')]
    public function commissions(ReservationRepository $repo): Response
    {
        // Auto-complete past confirmed reservations
        $repo->autoCompleteReservations();

        // Get all completed reservations where a commission was earned
        $reservationsTerminees = $repo->createQueryBuilder('r')
            ->where('r.statut IN (:statuts)')
            ->setParameter('statuts', ['termine', 'confirme'])
            ->orderBy('r.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('dashboard/admin/commissions.html.twig', [
            'totalCommissions' => $repo->getTotalPlatformCommissions(),
            'reservations' => $reservationsTerminees,
        ]);
    }

    #[Route('/admin/parametres', name: 'admin_parametres')]
    public function parametres(Request $request, ConfigCommissionRepository $configRepo, EntityManagerInterface $em): Response
    {
        $config = $configRepo->findOneBy(['estActive' => true]);
        
        if (!$config) {
            $config = new ConfigCommission();
            $config->setTaux('12.00'); // Taux par défaut
            $em->persist($config);
        }

        if ($request->isMethod('POST')) {
            $nouveauTaux = $request->request->get('commission_rate');
            if ($nouveauTaux !== null && is_numeric($nouveauTaux) && $nouveauTaux >= 0 && $nouveauTaux <= 100) {
                // Désactiver l'ancienne config
                $config->setEstActive(false);

                // Créer une nouvelle config (historisation)
                $nouvelleConfig = new ConfigCommission();
                $nouvelleConfig->setTaux((string) $nouveauTaux);
                $em->persist($nouvelleConfig);
                $em->flush();

                $this->addFlash('success', '✅ Taux de commission mis à jour avec succès !');
                return $this->redirectToRoute('admin_parametres');
            } else {
                $this->addFlash('error', 'Le taux de commission est invalide.');
            }
        }

        return $this->render('dashboard/admin/parametres.html.twig', [
            'commission_rate' => $config->getTaux()
        ]);
    }
}
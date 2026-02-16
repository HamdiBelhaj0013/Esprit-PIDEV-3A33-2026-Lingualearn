<?php
// src/Module/Support/Controller/BackOffice/DashboardController.php

namespace App\Module\Support\Controller\BackOffice;

use App\Module\Support\Service\ReclamationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/support')]
//#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_support_back_dashboard_index')]
    public function index(ReclamationService $reclamationService): Response
    {
        $stats = $reclamationService->getStatistics();

        return $this->render('support/back/dashboard/index.html.twig', [
            'stats' => $stats,
        ]);
    }
}

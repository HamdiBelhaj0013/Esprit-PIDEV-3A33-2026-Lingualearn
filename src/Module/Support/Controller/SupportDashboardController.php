<?php
// src/Module/Support/Controller/SupportDashboardController.php

namespace App\Module\Support\Controller;

use App\Module\Support\Service\ReclamationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/support')]
#[IsGranted('ROLE_ADMIN')]
class SupportDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_support_dashboard')]
    public function index(ReclamationService $reclamationService): Response
    {
        $stats = $reclamationService->getStatistics();

        return $this->render('support/dashboard/index.html.twig', [
            'stats' => $stats,
        ]);
    }
}

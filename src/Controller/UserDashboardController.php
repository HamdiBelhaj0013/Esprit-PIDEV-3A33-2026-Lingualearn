<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard', name: 'user_')]
#[IsGranted('ROLE_USER')]
class UserDashboardController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function index(): Response
    {
        return $this->render('user/dashboard/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}

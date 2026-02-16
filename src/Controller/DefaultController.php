<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * Homepage - Shows landing page for guests, redirects authenticated users
     */
    #[Route('/', name: 'app_homepage')]
    public function index(): Response
    {
        // If user is already logged in, redirect based on role
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }

            return $this->redirectToRoute('user_dashboard');
        }

        // Show landing page for non-authenticated users
        // If you want to redirect to login instead, uncomment the next line:
        // return $this->redirectToRoute('app_login');

        return $this->render('default/index.html.twig');
    }
}

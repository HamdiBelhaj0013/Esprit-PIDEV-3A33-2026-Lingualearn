<?php

namespace App\Controller;

use App\Module\PedagogicalContent\Repository\PlatformLanguageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    public function __construct(
        private PlatformLanguageRepository $languageRepository
    ) {}

    /**
     * Homepage — Shows landing page for guests, redirects authenticated users
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

        // Fetch only enabled languages from the database
        $languages = $this->languageRepository->findEnabled();

        return $this->render('default/index.html.twig', [
            'languages' => $languages,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pedagogical-content')]
class PedagogicalContentController extends AbstractController
{
    #[Route('', name: 'admin_pedagogical_content_index')]
    public function index(): Response
    {
        return $this->render('pedagogical_content/index.html.twig');
    }
}
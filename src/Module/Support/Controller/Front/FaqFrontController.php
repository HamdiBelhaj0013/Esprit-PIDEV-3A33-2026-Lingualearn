<?php
// src/Module/Support/Controller/Front/FaqFrontController.php

namespace App\Module\Support\Controller\Front;

use App\Module\Support\Service\FAQService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/support/faq')]
#[IsGranted('ROLE_USER')]
class FaqFrontController extends AbstractController
{
    #[Route('/', name: 'app_user_faq_index', methods: ['GET'])]
    public function index(FAQService $faqService, Request $request): Response
    {
        $subject = $request->query->get('subject');
        $search  = $request->query->get('search');

        $result   = $faqService->searchPaginated($search, $subject, 'submittedAt', 'DESC', 1, 100);
        $subjects = $faqService->getSubjects();

        return $this->render('support/front/faq/index.html.twig', [
            'faqs'             => $result['items'],
            'subjects'         => $subjects,
            'selected_subject' => $subject,
            'search'           => $search,
        ]);
    }
}
<?php
// src/Module/Support/Controller/FrontOffice/FAQController.php

namespace App\Module\Support\Controller\FrontOffice;

use App\Module\Support\Service\FAQService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/support/faq')]
class FAQController extends AbstractController
{
    #[Route('/', name: 'front_faq_index', methods: ['GET'])]
    public function index(FAQService $faqService, Request $request): Response
    {
        $subject = $request->query->get('subject');
        $search = $request->query->get('search');
        $sort = $request->query->get('sort', 'submittedAt');
        $order = $request->query->get('order', 'DESC');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);

        $result = $faqService->searchPaginated($search, $subject, $sort, $order, $page, $limit);
        $subjects = $faqService->getSubjects();

        return $this->render('support/front/faq/index.html.twig', [
            'faqs' => $result['items'],
            'subjects' => $subjects,
            'selected_subject' => $subject,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'page' => $result['page'],
            'pages' => $result['pages'],
            'total' => $result['total'],
            'limit' => $limit,
        ]);
    }
}
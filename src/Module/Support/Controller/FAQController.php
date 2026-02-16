<?php
// src/Module/Support/Controller/FAQController.php

namespace App\Module\Support\Controller;

use App\Module\Support\Entity\FAQ;
use App\Module\Support\Form\FAQType;
use App\Module\Support\Service\FAQService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/support/faq')]
class FAQController extends AbstractController
{
    #[Route('/', name: 'app_faq_index', methods: ['GET'])]
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

        return $this->render('support/faq/index.html.twig', [
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

    #[Route('/new', name: 'app_faq_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, FAQService $faqService): Response
    {
        $faq = new FAQ();
        $form = $this->createForm(FAQType::class, $faq);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $faqService->create($faq);
            $this->addFlash('success', 'FAQ créée avec succès.');
            return $this->redirectToRoute('app_faq_index');
        }

        return $this->render('support/faq/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_faq_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, FAQ $faq, FAQService $faqService): Response
    {
        $form = $this->createForm(FAQType::class, $faq);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $faqService->update($faq);
            $this->addFlash('success', 'FAQ mise à jour avec succès.');
            return $this->redirectToRoute('app_faq_index');
        }

        return $this->render('support/faq/edit.html.twig', [
            'faq' => $faq,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_faq_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, FAQ $faq, FAQService $faqService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$faq->getId(), $request->request->get('_token'))) {
            $faqService->delete($faq);
            $this->addFlash('success', 'FAQ supprimée avec succès.');
        }

        return $this->redirectToRoute('app_faq_index');
    }
}

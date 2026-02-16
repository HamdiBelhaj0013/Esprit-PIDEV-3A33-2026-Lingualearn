<?php
// src/Module/Support/Controller/ReclamationController.php

namespace App\Module\Support\Controller;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Entity\SupportResponse;
use App\Module\Support\Form\ReclamationType;
use App\Module\Support\Form\SupportResponseType;
use App\Module\Support\Service\ReclamationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/support/reclamations')]
class ReclamationController extends AbstractController
{
    public function __construct(
        private ReclamationService $reclamationService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_reclamation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        $sort = $request->query->get('sort', 'submittedAt');
        $order = $request->query->get('order', 'DESC');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);

        // Admin sees all reclamations, regular users see only their own
        $user = $this->isGranted('ROLE_ADMIN') ? null : $this->getUser();
        
        $result = $this->reclamationService->searchPaginated($search, $status, $user, $sort, $order, $page, $limit);

        return $this->render('support/reclamation/index.html.twig', [
            'reclamations' => $result['items'],
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'order' => $order,
            'page' => $result['page'],
            'pages' => $result['pages'],
            'total' => $result['total'],
            'limit' => $limit,
        ]);
    }

    #[Route('/new', name: 'app_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $reclamation = new Reclamation();
        $reclamation->setUser($this->getUser());
        
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->reclamationService->create($reclamation);
            $this->addFlash('success', 'Votre réclamation a été envoyée avec succès.');
            return $this->redirectToRoute('app_reclamation_index');
        }

        return $this->render('support/reclamation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_reclamation_show', methods: ['GET', 'POST'])]
    public function show(Request $request, Reclamation $reclamation): Response
    {
        // Check access: admins can view all, users can view only their own
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($reclamation->getUser() && $this->getUser()) {
                if ($reclamation->getUser()->getId() !== $this->getUser()->getId()) {
                    throw $this->createAccessDeniedException();
                }
            }
        }

        // Handle response submission
        $response = new SupportResponse();
        $response->setAuthor($this->getUser());

        $form = $this->createForm(SupportResponseType::class, $response);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->reclamationService->addResponse($reclamation, $response);

            // Update status if specified (admin feature)
            $newStatus = $form->get('status')->getData();
            if ($newStatus && $this->isGranted('ROLE_ADMIN')) {
                $statusValue = is_object($newStatus) && property_exists($newStatus, 'value') 
                    ? $newStatus->value 
                    : (string) $newStatus;
                $reclamation->setStatus($statusValue);
                $this->entityManager->flush();
            }

            $this->addFlash('success', 'Réponse envoyée avec succès.');
            return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
        }

        return $this->render('support/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'can_modify' => $this->reclamationService->canModify($reclamation, $this->getUser()),
            'response_form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation): Response
    {
        if (!$this->reclamationService->canModify($reclamation, $this->getUser())) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier cette réclamation.');
            return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
        }

        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Réclamation mise à jour avec succès.');
            return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
        }

        return $this->render('support/reclamation/edit.html.twig', [
            'form' => $form->createView(),
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation): Response
    {
        // Only admins or owners can delete
        $canDelete = $this->isGranted('ROLE_ADMIN') || 
                     $this->reclamationService->canModify($reclamation, $this->getUser());

        if (!$canDelete) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette réclamation.');
            return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
        }

        if ($this->isCsrfTokenValid('delete'.$reclamation->getId(), $request->request->get('_token'))) {
            $this->reclamationService->delete($reclamation);
            $this->addFlash('success', 'Réclamation supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_reclamation_index');
    }
}

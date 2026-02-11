<?php
// src/Module/Support/Controller/BackOffice/AdminReclamationController.php

namespace App\Module\Support\Controller\BackOffice;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Entity\SupportResponse;
use App\Module\Support\Form\SupportResponseType;
use App\Module\Support\Service\ReclamationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
// Removed IsGranted to disable authentication requirement for now
#[Route('/admin/support/reclamations')]
class AdminReclamationController extends AbstractController
{
    #[Route('/', name: 'admin_reclamation_index', methods: ['GET'])]
    public function index(ReclamationService $reclamationService, Request $request): Response
    {
        $status = $request->query->get('status');
        $reclamations = $status 
            ? $reclamationService->findByStatus($status)
            : $reclamationService->findAll();

        return $this->render('support/back/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
            'current_status' => $status,
        ]);
    }

    #[Route('/{id}', name: 'admin_reclamation_show', methods: ['GET', 'POST'])]
    public function show(
        Reclamation $reclamation, 
        Request $request, 
        ReclamationService $reclamationService,
        EntityManagerInterface $entityManager
    ): Response {
        $response = new SupportResponse();
        $response->setAuthor($this->getUser());
        
        $form = $this->createForm(SupportResponseType::class, $response);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reclamationService->addResponse($reclamation, $response);
            
            // Mettre à jour le statut si spécifié
            $newStatus = $form->get('status')->getData();
            if ($newStatus) {
                $statusValue = is_object($newStatus) && property_exists($newStatus, 'value') ? $newStatus->value : (string) $newStatus;
                $reclamation->setStatus($statusValue);
                $entityManager->flush();
            }

            $this->addFlash('success', 'Réponse envoyée avec succès.');
            return $this->redirectToRoute('admin_reclamation_show', ['id' => $reclamation->getId()]);
        }

        return $this->render('support/back/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, ReclamationService $reclamationService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$reclamation->getId(), $request->request->get('_token'))) {
            $reclamationService->delete($reclamation);
            $this->addFlash('success', 'Réclamation supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_reclamation_index');
    }
}
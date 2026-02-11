<?php
namespace App\Module\Support\Controller\FrontOffice;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Entity\SupportResponse;
use App\Module\Support\Form\ReclamationType;
use App\Module\Support\Form\SupportResponseType;
use App\Module\Support\Service\ReclamationService;
use App\Module\Support\Service\FAQService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
// Removed IsGranted to disable authentication requirement for now
#[Route('/support/reclamations')]
class ReclamationController extends AbstractController
{
    public function __construct(
        private ReclamationService $reclamationService,
        private FAQService $faqService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'front_reclamation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        $sort = $request->query->get('sort', 'submittedAt');
        $order = $request->query->get('order', 'DESC');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);

        $user = $this->getUser();
        $result = $this->reclamationService->searchPaginated($search, $status, $user, $sort, $order, $page, $limit);

        return $this->render('support/front/reclamation/index.html.twig', [
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

    #[Route('/new', name: 'front_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $reclamation = new Reclamation();
        $reclamation->setUser($this->getUser());
        
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->reclamationService->create($reclamation);
            $this->addFlash('success', 'Votre réclamation a été envoyée avec succès.');
            return $this->redirectToRoute('front_reclamation_index');
        }

        // FAQ liées au sujet sélectionné
        $subject = $request->get('subject') ?? $form->get('subject')->getData();
        $relatedFAQs = $subject ? $this->faqService->getFAQsBySubject($subject) : [];

        return $this->render('support/front/reclamation/new.html.twig', [
            'form' => $form->createView(),
            'related_faqs' => $relatedFAQs,
            'selected_subject' => $subject,
        ]);
    }

    #[Route('/{id}', name: 'front_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        // If users exist, ensure only the owner can view; otherwise allow (no auth)
        if ($reclamation->getUser() && $this->getUser()) {
            if ($reclamation->getUser()->getId() !== $this->getUser()->getId()) {
                throw $this->createAccessDeniedException();
            }
        }

        // Prepare response form so anyone can reply (author may be null)
        $response = new SupportResponse();
        $response->setAuthor($this->getUser());
        $form = $this->createForm(SupportResponseType::class, $response);

        return $this->render('support/front/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'can_modify' => $this->reclamationService->canModify($reclamation, $this->getUser()),
            'response_form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/reply', name: 'front_reclamation_reply', methods: ['GET', 'POST'])]
    public function reply(Request $request, Reclamation $reclamation): Response
    {
        $response = new SupportResponse();
        $response->setAuthor($this->getUser());

        $form = $this->createForm(SupportResponseType::class, $response);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->reclamationService->addResponse($reclamation, $response);

            $newStatus = $form->get('status')->getData();
            if ($newStatus) {
                // If using enum, extract value
                $statusValue = is_object($newStatus) && property_exists($newStatus, 'value') ? $newStatus->value : (string) $newStatus;
                $reclamation->setStatus($statusValue);
                $this->entityManager->flush();
            }

            $this->addFlash('success', 'Réponse envoyée avec succès.');
            return $this->redirectToRoute('front_reclamation_show', ['id' => $reclamation->getId()]);
        }

        // If form not submitted or invalid, re-display show with form errors
        return $this->render('support/front/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'can_modify' => $this->reclamationService->canModify($reclamation, $this->getUser()),
            'response_form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'front_reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation): Response
    {
        if (!$this->reclamationService->canModify($reclamation, $this->getUser())) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier cette réclamation.');
            return $this->redirectToRoute('front_reclamation_show', ['id' => $reclamation->getId()]);
        }

        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Réclamation mise à jour avec succès.');
            return $this->redirectToRoute('front_reclamation_show', ['id' => $reclamation->getId()]);
        }

        return $this->render('support/front/reclamation/edit.html.twig', [
            'form' => $form->createView(),
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/{id}/delete', name: 'front_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation, ReclamationService $reclamationService): Response
    {
        // Vérifier que l'utilisateur peut modifier cette réclamation
        if (!$reclamationService->canModify($reclamation, $this->getUser())) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette réclamation.');
            return $this->redirectToRoute('front_reclamation_show', ['id' => $reclamation->getId()]);
        }

        // Vérifier le token CSRF
        if ($this->isCsrfTokenValid('delete'.$reclamation->getId(), $request->request->get('_token'))) {
            $reclamationService->delete($reclamation);
            $this->addFlash('success', 'Réclamation supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('front_reclamation_index');
    }
}
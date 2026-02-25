<?php

namespace App\Module\Support\Controller\Back;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Entity\SupportResponse;
use App\Module\Support\Service\NotificationService;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/admin/support')]
#[IsGranted('ROLE_ADMIN')]
class AdminResponseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService    $notificationService,
        #[Autowire(service: 'state_machine.support_ticket')]
        private WorkflowInterface      $supportTicketWorkflow
    ) {}

    // ── Admin répond à un ticket ──
    #[Route('/reclamations/{id}/respond', name: 'admin_support_respond', methods: ['POST'])]
    public function respond(Request $request, Reclamation $reclamation): Response
    {
        /** @var User $admin */
        $admin = $this->getUser();

        $message   = trim((string) $request->request->get('message', ''));
        $newStatus = $request->request->get('status');

        if ($message === '') {
            $this->addFlash('error', 'Le message ne peut pas être vide.');
            return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
        }

        // ── Ajouter la réponse ──
        $response = new SupportResponse();
        $response->setMessage($message);
        $response->setAuthor($admin);
        $response->setReclamation($reclamation);
        $response->setRespondedAt(new \DateTime());
        $this->entityManager->persist($response);

        // ── Notification → user : réponse ajoutée ──
        // (Assure-toi que cette méthode existe dans NotificationService)
        $this->notificationService->notifyResponseAdded($reclamation);

        // ── Changement de statut via Workflow ──
        $oldStatus = $reclamation->getStatus();

        if ($newStatus && $newStatus !== $oldStatus) {
            $transition = match ([$oldStatus, $newStatus]) {
                ['PENDING', 'IN_PROGRESS']  => 'start_processing',
                ['PENDING', 'RESOLVED']     => 'resolve',
                ['PENDING', 'CLOSED']       => 'close',
                ['IN_PROGRESS', 'RESOLVED'] => 'resolve',
                ['IN_PROGRESS', 'CLOSED']   => 'close',
                ['RESOLVED', 'IN_PROGRESS'] => 'reopen',
                ['RESOLVED', 'CLOSED']      => 'close',
                ['CLOSED', 'IN_PROGRESS']   => 'reopen',
                default => null,
            };

            if ($transition && $this->supportTicketWorkflow->can($reclamation, $transition)) {
                $this->supportTicketWorkflow->apply($reclamation, $transition);

                // ── Notification → user : statut changé ──
                // Plus fiable d'envoyer le statut réel après apply()
                $this->notificationService->notifyStatusChanged($reclamation, $reclamation->getStatus());
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', '✅ Réponse envoyée ! Le user a été notifié.');
        return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
    }
}
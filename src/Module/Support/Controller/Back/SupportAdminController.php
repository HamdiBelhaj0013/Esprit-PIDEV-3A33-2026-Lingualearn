<?php

namespace App\Module\Support\Controller\Back;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Entity\SupportResponse;
use App\Module\Support\Repository\AuditLogRepository;
use App\Module\Support\Service\AuditLogService;
use App\Module\Support\Service\NotificationService;
use App\Module\Support\Service\SupportStatsService;
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
class SupportAdminController extends AbstractController
{
    public function __construct(
        private SupportStatsService    $statsService,
        private AuditLogRepository     $auditLogRepository,
        private AuditLogService        $auditLogService,
        private EntityManagerInterface $entityManager,
        private NotificationService    $notificationService,
        #[Autowire(service: 'state_machine.support_ticket')]
        private WorkflowInterface      $supportTicketWorkflow
    ) {}

    // =========================================================
    // STATS
    // =========================================================
    #[Route('/stats', name: 'admin_support_stats', methods: ['GET'])]
    public function stats(): Response
    {
        return $this->render('support/back/stats.html.twig', [
            'stats' => $this->statsService->getFullStats(),
        ]);
    }

    // =========================================================
    // AUDIT TRAIL
    // =========================================================
    #[Route('/audit', name: 'admin_support_audit', methods: ['GET'])]
    public function audit(Request $request): Response
    {
        $action = $request->query->get('action');
        $limit  = (int) $request->query->get('limit', 50);

        $logs = $action
            ? $this->auditLogRepository->findBy(['action' => $action], ['createdAt' => 'DESC'], $limit)
            : $this->auditLogRepository->findRecent($limit);

        $actionCounts = [
            'CREATED'             => $this->auditLogRepository->countByAction('CREATED'),
            'UPDATED'             => $this->auditLogRepository->countByAction('UPDATED'),
            'STATUS_CHANGED'      => $this->auditLogRepository->countByAction('STATUS_CHANGED'),
            'RESPONSE_ADDED'      => $this->auditLogRepository->countByAction('RESPONSE_ADDED'),
            'DELETED'             => $this->auditLogRepository->countByAction('DELETED'),
            'USER_BANNED'         => $this->auditLogRepository->countByAction('USER_BANNED'),
            'SPAM_BLOCKED'        => $this->auditLogRepository->countByAction('SPAM_BLOCKED'),
            'SATISFACTION_RATED'  => $this->auditLogRepository->countByAction('SATISFACTION_RATED'),
        ];

        return $this->render('support/back/audit.html.twig', [
            'logs'           => $logs,
            'actionCounts'   => $actionCounts,
            'selectedAction' => $action,
        ]);
    }

    // =========================================================
    // RÉPONDRE + WORKFLOW + NOTIFICATION
    // =========================================================
    #[Route('/reclamations/{id}/respond', name: 'admin_support_respond', methods: ['POST'])]
    public function respond(Request $request, Reclamation $reclamation): Response
    {
        /** @var User $admin */
        $admin      = $this->getUser();
        $message    = trim($request->request->get('message', ''));
        $transition = $request->request->get('transition');

        if (empty($message)) {
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

        // ── Audit réponse ──
        $this->auditLogService->logResponseAdded($reclamation, $admin);

        // ── Notification user ──
        $this->notificationService->notifyResponseAdded($reclamation);

        // ── Transition Workflow (si sélectionnée) ──
        if ($transition && $this->supportTicketWorkflow->can($reclamation, $transition)) {
            $oldStatus = $reclamation->getStatus();
            $this->supportTicketWorkflow->apply($reclamation, $transition);
            $newStatus = $reclamation->getStatus();

            // Audit statut
            $this->auditLogService->logStatusChanged($reclamation, $oldStatus, $newStatus, $admin);

            // Notification statut
            $this->notificationService->notifyStatusChanged($reclamation, $newStatus);
        }

        $this->entityManager->flush();

        $this->addFlash('success', '✅ Réponse envoyée ! Le user a été notifié.');
        return $this->redirectToRoute('app_reclamation_show', ['id' => $reclamation->getId()]);
    }
}
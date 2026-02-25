<?php

namespace App\Module\Support\Controller\Front;

use App\Module\Support\Entity\Notification;
use App\Module\Support\Service\NotificationService;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/support/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationService    $notificationService,
        private EntityManagerInterface $entityManager
    ) {}

    // ── Page notifications ──
    #[Route('', name: 'app_user_notifications', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user          = $this->getUser();
        $notifications = $this->notificationService->getForUser($user, 50);

        // Marquer toutes comme lues à l'ouverture
        $this->notificationService->markAllAsRead($user);

        return $this->render('support/front/notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    // ── API : compter les non lues (pour badge temps réel) ──
    #[Route('/unread-count', name: 'app_user_notifications_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $count = $this->notificationService->countUnread($user);

        return $this->json(['count' => $count]);
    }

    // ── Marquer une notification comme lue ──
    #[Route('/{id}/read', name: 'app_user_notification_read', methods: ['POST'])]
    public function markRead(Notification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($notification->getUser() !== $user) {
            return $this->json(['error' => 'Forbidden'], 403);
        }

        $this->notificationService->markAsRead($notification);

        // Rediriger vers le ticket si disponible
        $redirectUrl = $notification->getReclamation()
            ? $this->generateUrl('app_user_reclamation_show', ['id' => $notification->getReclamation()->getId()])
            : $this->generateUrl('app_user_notifications');

        return $this->json(['redirect' => $redirectUrl]);
    }
}
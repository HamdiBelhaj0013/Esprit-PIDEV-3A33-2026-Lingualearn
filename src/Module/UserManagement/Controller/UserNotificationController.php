<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Entity\Notification;
use App\Module\UserManagement\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Handles notification actions triggered by the USER on their own profile.
 * Isolated inside UserManagement — no Support module dependency.
 */
#[Route('/user/notifications', name: 'user_notification')]
#[IsGranted('ROLE_USER')]
class UserNotificationController extends AbstractController
{
    public function __construct(
        private NotificationService    $notificationService,
        private EntityManagerInterface $entityManager,
    ) {}

    // ─── Mark a single notification as read ──────────────────

    #[Route('/{id}/read', name: '_read', methods: ['POST'])]
    public function markRead(Request $request, Notification $notification): Response
    {
        // Ensure the notification belongs to the logged-in user
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('user_notif_read_' . $notification->getId(), $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('user_profile');
        }

        $this->notificationService->markAsRead($notification);

        return $this->redirectToRoute('user_profile', ['_fragment' => 'notifications']);
    }

    // ─── Mark ALL notifications as read ──────────────────────

    #[Route('/read-all', name: 's_read_all', methods: ['POST'])]
    public function markAllRead(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('user_notif_read_all', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('user_profile');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();
        $this->notificationService->markAllAsRead($user);

        $this->addFlash('success', 'All notifications marked as read.');
        return $this->redirectToRoute('user_profile', ['_fragment' => 'notifications']);
    }

    // ─── User replies to a notification ──────────────────────

    #[Route('/{id}/reply', name: '_reply', methods: ['POST'])]
    public function reply(Request $request, Notification $notification): Response
    {
        // Ensure the notification belongs to the logged-in user
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('user_notif_reply_' . $notification->getId(), $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('user_profile');
        }

        $replyMessage = trim($request->request->get('reply', ''));

        if (empty($replyMessage)) {
            $this->addFlash('error', 'Reply cannot be empty.');
            return $this->redirectToRoute('user_profile', ['_fragment' => 'notifications']);
        }

        // Save the user reply as a new notification (type = 'info', sender = 'user')
        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->getUser();

        $reply = new Notification();
        $reply->setUser($user);
        $reply->setType($notification->getType());
        $reply->setMessage($replyMessage);
        $reply->setIsRead(true); // user's own reply is already "read" by definition
        $reply->setMetadata([
            'sender'   => 'user',
            'reply_to' => $notification->getId(),
            'sent_at'  => (new \DateTime())->format(\DateTime::ATOM),
        ]);

        $this->entityManager->persist($reply);

        // Mark the original notification as read when user replies
        $notification->setIsRead(true);

        $this->entityManager->flush();

        $this->addFlash('success', 'Reply sent.');
        return $this->redirectToRoute('user_profile', ['_fragment' => 'notifications']);
    }
}

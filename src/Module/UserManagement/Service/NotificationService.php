<?php

namespace App\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\Notification;
use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles all notification logic for the UserManagement module.
 * Fully isolated — no dependency on the Support module.
 */
class NotificationService
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    // =========================================================
    //  READ
    // =========================================================

    /**
     * Get recent notifications for a user (used by admin notify page & user dashboard).
     *
     * @return Notification[]
     */
    public function getForUser(User $user, int $limit = 20): array
    {
        return $this->notificationRepository->findForUser($user, $limit);
    }

    /**
     * Alias kept for compatibility with UserService::getRecentNotifications().
     *
     * @return Notification[]
     */
    public function getRecentNotifications(User $user, int $limit = 10): array
    {
        return $this->getForUser($user, $limit);
    }

    /**
     * Count unread notifications for a user.
     */
    public function countUnread(User $user): int
    {
        return $this->notificationRepository->countUnread($user);
    }

    // =========================================================
    //  SEND
    // =========================================================

    /**
     * Send a notification from admin to a user.
     */
    public function sendFromAdmin(
        User   $user,
        string $message,
        string $type,
        int    $adminId,
    ): Notification {
        $allowed = ['info', 'warning', 'success', 'premium', 'system'];
        if (!in_array($type, $allowed, true)) {
            $type = 'info';
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setMetadata([
            'sender'   => 'admin',
            'admin_id' => $adminId,
            'sent_at'  => (new \DateTime())->format(\DateTime::ATOM),
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Send a reply from admin to an existing notification thread.
     */
    public function replyFromAdmin(
        Notification $original,
        string       $replyMessage,
        int          $adminId,
    ): Notification {
        $reply = new Notification();
        $reply->setUser($original->getUser());
        $reply->setType($original->getType());
        $reply->setMessage($replyMessage);
        $reply->setMetadata([
            'sender'       => 'admin',
            'admin_id'     => $adminId,
            'reply_to'     => $original->getId(),
            'sent_at'      => (new \DateTime())->format(\DateTime::ATOM),
        ]);

        $this->entityManager->persist($reply);

        // Mark the original as read when admin replies
        $original->setIsRead(true);

        $this->entityManager->flush();

        return $reply;
    }

    // =========================================================
    //  MARK AS READ
    // =========================================================

    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->entityManager->flush();
    }

    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllReadForUser($user);
    }
}

<?php
// src/Module/Support/Service/NotificationService.php

namespace App\Module\Support\Service;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Repository\ReclamationRepository;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private PusherService $pusherService,
        private ReclamationRepository $reclamationRepository,
        private EntityManagerInterface $em
    ) {}

    /**
     * Returns notifications (reclamation updates) for a given user.
     */
    public function getForUser(User $user): array
    {
        $reclamations = $this->reclamationRepository->findBy(
            ['user' => $user],
            ['submittedAt' => 'DESC']
        );

        $notifications = [];

        foreach ($reclamations as $reclamation) {
            foreach ($reclamation->getResponses() as $response) {
                $notifications[] = new class($reclamation, $response) {
                    public string $type      = 'info';
                    public bool   $isRead    = false;
                    public string $message;
                    public object $reclamation;
                    public \DateTimeInterface $createdAt;
                    public int $id;

                    public function __construct($reclamation, $response) {
                        $this->id          = $response->getId();
                        $this->message     = 'L\'admin a répondu à votre réclamation : ' . $reclamation->getSubject();
                        $this->reclamation = $reclamation;
                        $this->createdAt   = $response->getRespondedAt() ?? $reclamation->getSubmittedAt() ?? new \DateTime();
                    }

                    public function getId(): int { return $this->id; }
                    public function getType(): string { return $this->type; }
                    public function isRead(): bool { return $this->isRead; }
                    public function getMessage(): string { return $this->message; }
                    public function getReclamation(): object { return $this->reclamation; }
                    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
                };
            }
        }

        // Sort by createdAt DESC
        usort($notifications, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $notifications;
    }

    /**
     * Mark all reclamations as read for a user (resets isLate flag as acknowledgement)
     */
    public function markAllAsRead(User $user): void
    {
        $reclamations = $this->reclamationRepository->findBy(['user' => $user]);

        foreach ($reclamations as $reclamation) {
            $reclamation->setIsLate(false);
        }

        $this->em->flush();
    }

    /**
     * Appelée quand l'admin envoie une réponse
     */
    public function notifyResponseAdded(Reclamation $reclamation): void
    {
        $user = $reclamation->getUser();
        if (!$user) return;

        $this->pusherService->trigger(
            'notifications-user-' . $user->getId(),
            'new-response',
            [
                'type'           => 'response',
                'title'          => '💬 Nouvelle réponse',
                'message'        => 'L\'admin a répondu à votre réclamation #' . $reclamation->getId(),
                'reclamation_id' => $reclamation->getId(),
                'url'            => '/support/reclamations/' . $reclamation->getId(),
                'time'           => date('H:i'),
            ]
        );
    }

    /**
     * Appelée quand le statut change
     */
    public function notifyStatusChanged(Reclamation $reclamation, string $newStatus): void
    {
        $user = $reclamation->getUser();
        if (!$user) return;

        $statusLabels = [
            'PENDING'     => '⏳ En attente',
            'IN_PROGRESS' => '🔄 En cours',
            'RESOLVED'    => '✅ Résolu',
            'CLOSED'      => '🔒 Fermé',
        ];

        $this->pusherService->trigger(
            'notifications-user-' . $user->getId(),
            'status-changed',
            [
                'type'           => 'status',
                'title'          => '🔔 Statut mis à jour',
                'message'        => 'Votre réclamation #' . $reclamation->getId() . ' est maintenant : ' . ($statusLabels[$newStatus] ?? $newStatus),
                'reclamation_id' => $reclamation->getId(),
                'url'            => '/support/reclamations/' . $reclamation->getId(),
                'time'           => date('H:i'),
            ]
        );
    }
}

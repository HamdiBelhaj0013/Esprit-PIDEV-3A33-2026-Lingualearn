<?php
// src/Module/Support/Service/NotificationService.php

namespace App\Module\Support\Service;

use App\Module\Support\Entity\Reclamation;

class NotificationService
{
    public function __construct(
        private PusherService $pusherService
    ) {}

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
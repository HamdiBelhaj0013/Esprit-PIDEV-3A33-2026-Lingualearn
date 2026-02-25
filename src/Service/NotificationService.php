<?php

namespace App\Service;

use Pusher\Pusher;

class NotificationService
{
    private Pusher $pusher;

    public function __construct(
    private string $appId,
    private string $key,
    private string $secret,
    private string $cluster
) {
    $this->pusher = new Pusher(
        $this->key,
        $this->secret,
        $this->appId,
        [
            'cluster' => $this->cluster,
            'useTLS' => true
        ]
    );
}

    /**
     * Envoie une notification via Pusher
     *
     * @param array $data Tableau contenant :
     *   - message : string
     *   - publicationId : int (optionnel)
     *   - type : string (optionnel, ex: reaction, comment)
     */
    public function sendNotification(array $data): void
    {
        $this->pusher->trigger('notifications-channel', 'new-notification', $data);
    }
}
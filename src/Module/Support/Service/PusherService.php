<?php

namespace App\Module\Support\Service;

use Pusher\Pusher;

class PusherService
{
    private Pusher $pusher;

    public function __construct(
        private string $appKey,
        private string $appSecret,
        private string $appId,
        private string $cluster
    ) {
        $this->pusher = new Pusher(
            $appKey,
            $appSecret,
            $appId,
            ['cluster' => $cluster, 'useTLS' => true]
        );
    }

    public function trigger(string $channel, string $event, array $data): void
    {
        try {
            $this->pusher->trigger($channel, $event, $data);
        } catch (\Throwable $e) {
            // silently fail — ne bloque pas l'app
        }
    }
}

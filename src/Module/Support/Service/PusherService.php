<?php
// src/Module/Support/Service/PusherService.php

namespace App\Module\Support\Service;

use Pusher\Pusher;

class PusherService
{
    private Pusher $pusher;

    public function __construct()
    {
        $this->pusher = new Pusher(
            $_ENV['PUSHER_KEY']    ?? '5f6021b614f01111799d',
            $_ENV['PUSHER_SECRET'] ?? '2d3ff2faeaadd0925e70',
            $_ENV['PUSHER_APP_ID'] ?? '2119719',
            ['cluster' => $_ENV['PUSHER_CLUSTER'] ?? 'eu', 'useTLS' => true]
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
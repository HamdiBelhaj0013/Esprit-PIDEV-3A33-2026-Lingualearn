<?php

namespace App\Module\Support\Service;

use App\Module\Support\Repository\ReclamationRepository;
use App\Module\UserManagement\Entity\User;

class SpamDetectionService
{
    private const MAX_TICKETS    = 3;
    private const TIME_WINDOW    = 10; // minutes

    public function __construct(
        private ReclamationRepository $reclamationRepository
    ) {}

    public function isSpam(User $user): bool
    {
        $since = new \DateTime("-" . self::TIME_WINDOW . " minutes");
        $count = $this->reclamationRepository->countRecentByUser($user, $since);
        return $count >= self::MAX_TICKETS;
    }

    public function getRemainingMinutes(User $user): int
    {
        $since   = new \DateTime("-" . self::TIME_WINDOW . " minutes");
        $oldest  = $this->reclamationRepository->findOldestRecentByUser($user, $since);
        if (!$oldest) return 0;
        $diff = (new \DateTime())->diff($oldest->getSubmittedAt()->modify("+" . self::TIME_WINDOW . " minutes"));
        return max(0, ($diff->i) + ($diff->h * 60));
    }
}
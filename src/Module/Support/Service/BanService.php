<?php

namespace App\Module\Support\Service;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class BanService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function banUser(User $user, string $reason, int $days = 7): void
    {
        $bannedUntil = new \DateTime();
        $bannedUntil->modify('+' . $days . ' days');

        $user->setIsBanned(true);
        $user->setBanReason($reason);
        $user->setBannedAt(new \DateTime());
        $user->setBannedUntil($bannedUntil);
        $this->entityManager->flush();
    }

    public function unbanUser(User $user): void
    {
        $user->setIsBanned(false);
        $user->setBanReason(null);
        $user->setBannedAt(null);
        $user->setBannedUntil(null);
        $this->entityManager->flush();
    }

    public function getRemainingDays(User $user): int
    {
        if (!$user->getBannedUntil()) return 0;
        $now  = new \DateTime();
        $diff = $now->diff($user->getBannedUntil());
        return max(0, $diff->days);
    }

    public function getRemainingHours(User $user): int
    {
        if (!$user->getBannedUntil()) return 0;
        $now      = new \DateTime();
        $diff     = $now->diff($user->getBannedUntil());
        return max(0, ($diff->days * 24) + $diff->h);
    }
}
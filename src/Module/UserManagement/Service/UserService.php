<?php

namespace App\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\LearningStats;
use App\Module\UserManagement\Entity\Notification;
use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private StripeService $stripeService,
    ) {
        // Break the circular dependency: StripeService needs UserService
        // for adminGrantPremium/adminRevokePremium, but cannot take it
        // in its own constructor (that would be circular). We push ourselves
        // into StripeService here after both are fully constructed.
        $this->stripeService->setUserService($this);
    }

    // ── Original methods (unchanged) ─────────────────────────

    public function createUser(
        string $email,
        string $plainPassword,
        string $firstName,
        string $lastName
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setStatus('active');
        $user->setSubscriptionPlan('FREE');
        // isPremium is computed from subscriptionPlan + subscriptionExpiry;
        // no need to call setPremium() — updatePremiumStatus() handles it.

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $stats = new LearningStats();
        $stats->setUser($user);
        $user->setLearningStats($stats);

        $this->entityManager->persist($user);
        $this->entityManager->persist($stats);
        $this->entityManager->flush();

        return $user;
    }

    public function updateUser(User $user, string $firstName, string $lastName, ?string $plainPassword = null): User
    {
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        if ($plainPassword) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        $this->entityManager->flush();
        return $user;
    }

    public function deleteUser(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function activateUser(User $user): void
    {
        $user->setStatus('active');
        $this->entityManager->flush();
    }

    public function suspendUser(User $user): void
    {
        $user->setStatus('suspended');
        $this->entityManager->flush();
    }

    public function upgradeToPremium(User $user, string $plan, \DateTimeInterface $expiryDate): void
    {
        // ORDER MATTERS:
        // 1. Set expiry first — updatePremiumStatus() inside setSubscriptionPlan()
        //    needs it already set to correctly compute isPremium = true.
        // 2. Set plan second — this triggers updatePremiumStatus(), which reads
        //    the expiry we just set and sets isPremium = true automatically.
        // 3. Do NOT call setPremium(true) — it is a no-op by design.
        //    isPremium is governed exclusively by updatePremiumStatus().
        $user->setSubscriptionExpiry($expiryDate);
        $user->setSubscriptionPlan($plan);
        $user->setLastPaymentStatus('success');
        $this->entityManager->flush();
    }

    public function downgradeToFree(User $user): void
    {
        // ORDER MATTERS:
        // setSubscriptionPlan('FREE') triggers updatePremiumStatus().
        // At that point expiry may still be set, but plan='FREE' alone
        // is enough for updatePremiumStatus() to resolve isPremium = false.
        // setSubscriptionExpiry(null) then fires updatePremiumStatus() again
        // as a double-clean. No need to call setPremium(false) — it is a no-op.
        $user->setSubscriptionPlan('FREE');
        $user->setSubscriptionExpiry(null);
        $this->entityManager->flush();
    }

    public function checkExpiredSubscriptions(): int
    {
        $now          = new \DateTime();
        $expiredUsers = $this->userRepository->findExpiringSubscriptions($now);
        $count        = 0;

        foreach ($expiredUsers as $user) {
            $this->downgradeToFree($user);
            $count++;
        }

        return $count;
    }

    public function getAllUsers(int $page = 1, int $limit = 20): array
    {
        return $this->userRepository->findBy([], ['createdAt' => 'DESC'], $limit, ($page - 1) * $limit);
    }

    public function getTotalUsersCount(): int
    {
        return $this->userRepository->count([]);
    }

    public function searchUsers(string $query): array
    {
        return $this->userRepository->searchUsers($query);
    }

    public function getUserStatistics(): array
    {
        $startOfMonth = new \DateTime('first day of this month midnight');
        $now          = new \DateTime();

        return [
            'total'        => $this->userRepository->count([]),
            'active'       => $this->userRepository->countByStatus('active'),
            'suspended'    => $this->userRepository->countByStatus('suspended'),
            'deleted'      => $this->userRepository->countByStatus('deleted'),
            'premium'      => count($this->userRepository->findPremiumUsers()),
            'newThisMonth' => $this->userRepository->countBetweenDates($startOfMonth, $now),
        ];
    }

    // ── NEW: Advanced métier methods ─────────────────────────

    /**
     * FEATURE 3 — Learning stats
     * Lazily creates a LearningStats record if the user doesn't have one yet.
     * Ensures stats always exist before the admin tries to edit them.
     */
    public function initLearningStats(User $user): LearningStats
    {
        $stats = $user->getLearningStats();

        if (!$stats) {
            $stats = new LearningStats();
            $stats->setUser($user);
            $user->setLearningStats($stats);
            $this->entityManager->persist($stats);
            $this->entityManager->flush();
        }

        return $stats;
    }

    /**
     * FEATURE 4 — Notifications
     * Returns the N most recent notifications for a user, newest first.
     * Used in the notify form to show context before sending.
     *
     * @return Notification[]
     */
    public function getRecentNotifications(User $user, int $limit = 10): array
    {
        return $this->entityManager
            ->getRepository(Notification::class)
            ->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                $limit
            );
    }

    /**
     * FEATURE 4 — Notifications
     * Marks all unread notifications for a user as read.
     * Can be called from a user-facing controller or a console command.
     */
    public function markAllNotificationsRead(User $user): int
    {
        $unread = $this->entityManager
            ->getRepository(Notification::class)
            ->findBy(['user' => $user, 'isRead' => false]);

        $count = 0;
        foreach ($unread as $notification) {
            $notification->setIsRead(true);
            $count++;
        }

        $this->entityManager->flush();
        return $count;
    }

    /**
     * FEATURE 2 — CSV export
     * Returns aggregated stats per subscription plan.
     * Used in the export header / summary row.
     *
     * @return array{plan: string, count: int}[]
     */
    public function getSubscriptionBreakdown(): array
    {
        return $this->userRepository->countBySubscriptionPlan();
    }

    /**
     * FEATURE 6 — Admin password reset
     * Validates & applies a new plain password set by an admin.
     * Throws \InvalidArgumentException for bad input so the caller
     * (controller or API) can handle it uniformly.
     */
    public function adminResetPassword(User $user, string $plainPassword): void
    {
        if (strlen(trim($plainPassword)) < 6) {
            throw new \InvalidArgumentException('Password must be at least 6 characters.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }

    /**
     * FEATURE 1 — Bulk actions
     * Processes an array of user IDs with the given action.
     * Returns a count of affected rows.
     * Skips the $currentUser so an admin cannot lock themselves out.
     *
     * @param int[]  $ids
     * @param string $action  'activate' | 'suspend' | 'delete'
     */
    public function bulkAction(array $ids, string $action, User $currentUser): int
    {
        if (!in_array($action, ['activate', 'suspend', 'delete'], true)) {
            throw new \InvalidArgumentException("Invalid bulk action: $action");
        }

        $users     = $this->userRepository->findBy(['id' => $ids]);
        $processed = 0;

        foreach ($users as $user) {
            // Never let the admin wipe their own account in bulk
            if ($user->getId() === $currentUser->getId()) {
                continue;
            }

            match ($action) {
                'activate' => $this->activateUser($user),
                'suspend'  => $this->suspendUser($user),
                'delete'   => $this->deleteUser($user),
            };

            $processed++;
        }

        return $processed;
    }
}

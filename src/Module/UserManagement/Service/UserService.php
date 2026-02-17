<?php

namespace App\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Entity\LearningStats;
use App\Module\UserManagement\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Create a new user
     */
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
        $user->setPremium(false);

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        // Create learning stats
        $learningStats = new LearningStats();
        $learningStats->setUser($user);
        $user->setLearningStats($learningStats);

        $this->entityManager->persist($user);
        $this->entityManager->persist($learningStats);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Update user profile
     */
    public function updateUser(
        User $user,
        string $firstName,
        string $lastName,
        ?string $plainPassword = null
    ): User {
        $user->setFirstName($firstName);
        $user->setLastName($lastName);

        if ($plainPassword) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
        }

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Delete user (hard delete)
     */
    public function deleteUser(User $user): void
    {
        // The User entity has cascade:['persist','remove'] on the learningStats
        // OneToOne relation, so Doctrine removes it automatically.
        // Manually removing it first would throw a "detached entity" error.
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    /**
     * Activate user
     */
    public function activateUser(User $user): void
    {
        $user->setStatus('active');
        $this->entityManager->flush();
    }

    /**
     * Suspend user
     */
    public function suspendUser(User $user): void
    {
        $user->setStatus('suspended');
        $this->entityManager->flush();
    }

    /**
     * Upgrade to premium
     */
    public function upgradeToPremium(
        User $user,
        string $plan,
        \DateTimeInterface $expiryDate
    ): void {
        $user->setSubscriptionPlan($plan);
        $user->setSubscriptionExpiry($expiryDate);
        $user->setPremium(true);
        $user->setLastPaymentStatus('success');

        $this->entityManager->flush();
    }

    /**
     * Downgrade to free
     */
    public function downgradeToFree(User $user): void
    {
        $user->setSubscriptionPlan('FREE');
        $user->setSubscriptionExpiry(null);
        $user->setPremium(false);

        $this->entityManager->flush();
    }

    /**
     * Check and update expired subscriptions
     */
    public function checkExpiredSubscriptions(): int
    {
        $now = new \DateTime();
        $expiredUsers = $this->userRepository->findExpiringSubscriptions($now);

        $count = 0;
        foreach ($expiredUsers as $user) {
            $this->downgradeToFree($user);
            $count++;
        }

        return $count;
    }

    /**
     * Get all users with pagination
     */
    public function getAllUsers(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        return $this->userRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );
    }

    /**
     * Get total users count
     */
    public function getTotalUsersCount(): int
    {
        return $this->userRepository->count([]);
    }

    /**
     * Search users
     */
    public function searchUsers(string $query): array
    {
        return $this->userRepository->searchUsers($query);
    }

    /**
     * Get user statistics
     */
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
            // Used by the index template stats strip
            'newThisMonth' => $this->userRepository->countBetweenDates($startOfMonth, $now),
        ];
    }
}

<?php

namespace App\Module\UserManagement\Repository;

use App\Module\UserManagement\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Find all active users
     *
     * @return User[]
     */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find premium users
     *
     * @return User[]
     */
    public function findPremiumUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isPremium = :premium')
            ->andWhere('u.status = :status')
            ->setParameter('premium', true)
            ->setParameter('status', 'active')
            ->orderBy('u.subscriptionExpiry', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users with expiring subscriptions
     *
     * @param \DateTimeInterface $date
     * @return User[]
     */
    public function findExpiringSubscriptions(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isPremium = :premium')
            ->andWhere('u.subscriptionExpiry <= :date')
            ->andWhere('u.subscriptionExpiry IS NOT NULL')
            ->setParameter('premium', true)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search users by name or email
     *
     * @param string $query
     * @return User[]
     */
    public function searchUsers(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :query')
            ->orWhere('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count users by status
     *
     * @param string $status
     * @return int
     */
    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find user with learning stats
     *
     * @param int $id
     * @return User|null
     */
    public function findWithStats(int $id): ?User
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.learningStats', 'ls')
            ->addSelect('ls')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count users created between dates
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return int
     */
    public function countBetweenDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get user registration statistics
     *
     * @param int $days Number of days to look back
     * @return array
     */
    public function getRegistrationStats(int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('u')
            ->select('DATE(u.createdAt) as date, COUNT(u.id) as count')
            ->where('u.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Advanced user search, filter & sort for admin.
     *
     * Filters:
     * - search: Name/email search.
     * - status: 'active', 'suspended', 'deleted'.
     * - role: User role (authorization, not subscription).
     * - isPremium: Premium status (true/false, canonical for business).
     * - subscriptionPlan: 'FREE', 'PREMIUM_MONTHLY', 'PREMIUM_YEARLY'.
     * - sort: Sort field.
     * - direction: ASC/DESC.
     *
     * @param array $criteria
     * @param int $page
     * @param int $limit
     * @return array [User[], totalCount]
     */
    public function findAdvanced(array $criteria, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('u');

        // Search by name or email
        if (!empty($criteria['search'])) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('u.email', ':term'),
                    $qb->expr()->like('u.firstName', ':term'),
                    $qb->expr()->like('u.lastName', ':term')
                )
            )
                ->setParameter('term', '%' . $criteria['search'] . '%');
        }

        // Filter by status
        if (!empty($criteria['status'])) {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', $criteria['status']);
        }

        // Filter by role (Doctrine array field stored as JSON)
        if (!empty($criteria['role'])) {
            // Use LIKE for JSON array field matching
            $qb->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"' . $criteria['role'] . '"%');
        }

        // Filter by premium status (true/false, canonical business logic)
        if (isset($criteria['isPremium']) && $criteria['isPremium'] !== '') {
            // Normalizes for values such as "0", "1", true, false, etc.
            $value = is_string($criteria['isPremium']) ? ($criteria['isPremium'] === '1' ? true : false) : (bool) $criteria['isPremium'];
            $qb->andWhere('u.isPremium = :isPremium')
                ->setParameter('isPremium', $value);
        }

        // Filter by subscription plan
        if (!empty($criteria['subscriptionPlan'])) {
            $qb->andWhere('u.subscriptionPlan = :plan')
                ->setParameter('plan', $criteria['subscriptionPlan']);
        }

        // Sort handling
        $allowedSorts = [
            'u.createdAt', 'u.firstName', 'u.lastName',
            'u.email', 'u.status', 'u.isPremium', 'u.subscriptionPlan'
        ];
        $sortField = $criteria['sort'] ?? 'u.createdAt';
        $direction = $criteria['direction'] ?? 'DESC';

        if (!in_array($sortField, $allowedSorts, true)) {
            $sortField = 'u.createdAt';
        }
        if (!in_array(strtoupper($direction), ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }
        $qb->orderBy($sortField, $direction);

        // Pagination
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $users = $qb->getQuery()->getResult();

        // Clone for counting total (no pagination, no order)
        $qbCount = clone $qb;
        $qbCount->resetDQLPart('orderBy')
            ->setFirstResult(null)
            ->setMaxResults(null)
            ->select('COUNT(u.id)');
        $total = (int) $qbCount->getQuery()->getSingleScalarResult();

        return [$users, $total];
    }

    /**
     * Get EntityManager for external queries
     *
     * @return \Doctrine\ORM\EntityManagerInterface
     */
    public function getEntityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        return parent::getEntityManager();
    }
}

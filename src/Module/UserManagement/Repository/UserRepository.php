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

    // ── Original methods (unchanged) ─────────────────────────

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /** @return User[] */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return User[] */
    public function findPremiumUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isPremium = :premium')
            ->andWhere('u.status = :status')
            ->setParameter('premium', true)
            ->setParameter('status', 'active')
            ->orderBy('u.subscriptionExpiry', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return User[] */
    public function findExpiringSubscriptions(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.isPremium = :premium')
            ->andWhere('u.subscriptionExpiry <= :date')
            ->andWhere('u.subscriptionExpiry IS NOT NULL')
            ->setParameter('premium', true)
            ->setParameter('date', $date)
            ->getQuery()->getResult();
    }

    /** @return User[] */
    public function searchUsers(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.email LIKE :query')
            ->orWhere('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.status = :status')
            ->setParameter('status', $status)
            ->getQuery()->getSingleScalarResult();
    }

    public function findWithStats(int $id): ?User
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.learningStats', 'ls')
            ->addSelect('ls')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult();
    }

    public function countBetweenDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()->getSingleScalarResult();
    }

    public function getRegistrationStats(int $days = 30): array
    {
        return $this->createQueryBuilder('u')
            ->select('DATE(u.createdAt) as date, COUNT(u.id) as count')
            ->where('u.createdAt >= :startDate')
            ->setParameter('startDate', new \DateTime("-{$days} days"))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Advanced filter + sort + pagination — original logic preserved.
     *
     * @return array{0: User[], 1: int}
     */
    public function findAdvanced(array $criteria, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('u');

        if (!empty($criteria['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('u.email', ':term'),
                $qb->expr()->like('u.firstName', ':term'),
                $qb->expr()->like('u.lastName', ':term')
            ))->setParameter('term', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('u.status = :status')->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['role'])) {
            $qb->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"' . $criteria['role'] . '"%');
        }

        if (isset($criteria['isPremium']) && $criteria['isPremium'] !== '') {
            $value = is_string($criteria['isPremium'])
                ? ($criteria['isPremium'] === '1')
                : (bool) $criteria['isPremium'];
            $qb->andWhere('u.isPremium = :isPremium')->setParameter('isPremium', $value);
        }

        if (!empty($criteria['subscriptionPlan'])) {
            $qb->andWhere('u.subscriptionPlan = :plan')->setParameter('plan', $criteria['subscriptionPlan']);
        }

        $allowedSorts = ['u.createdAt', 'u.firstName', 'u.lastName', 'u.email', 'u.status', 'u.isPremium', 'u.subscriptionPlan'];
        $sortField    = in_array($criteria['sort'] ?? '', $allowedSorts, true) ? $criteria['sort'] : 'u.createdAt';
        $direction    = in_array(strtoupper($criteria['direction'] ?? ''), ['ASC', 'DESC'], true) ? strtoupper($criteria['direction']) : 'DESC';

        $qb->orderBy($sortField, $direction)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $users = $qb->getQuery()->getResult();

        $qbCount = clone $qb;
        $total   = (int) $qbCount->resetDQLPart('orderBy')
            ->setFirstResult(null)
            ->setMaxResults(null)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [$users, $total];
    }

    // ── NEW method required by UserService::getSubscriptionBreakdown() ────

    /**
     * FEATURE 2 — CSV export summary
     * Returns user count grouped by subscriptionPlan.
     *
     * @return array{plan: string, count: int}[]
     */
    public function countBySubscriptionPlan(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.subscriptionPlan AS plan, COUNT(u.id) AS count')
            ->groupBy('u.subscriptionPlan')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getEntityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        return parent::getEntityManager();
    }

    // ── Subscription integrity helpers ────────────────────────

    /**
     * Count users who are BOTH isPremium = true AND have a real Stripe
     * subscription ID. This excludes users who were manually granted premium
     * by an admin without going through Stripe, so revenue figures stay accurate.
     */
    public function countActivePayingSubscribers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isPremium = :premium')
            ->andWhere('u.stripeSubscriptionId IS NOT NULL')
            ->setParameter('premium', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users who were granted premium manually by an admin
     * (isPremium = true but NO Stripe subscription attached).
     */
    public function countAdminGrantedPremium(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isPremium = :premium')
            ->andWhere('u.stripeSubscriptionId IS NULL')
            ->setParameter('premium', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active paying subscribers filtered by plan (MONTHLY or YEARLY).
     * Used for accurate per-plan MRR calculations in the dashboard.
     */
    public function countActivePayingSubscribersByPlan(string $plan): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isPremium = :premium')
            ->andWhere('u.stripeSubscriptionId IS NOT NULL')
            ->andWhere('u.subscriptionPlan = :plan')
            ->setParameter('premium', true)
            ->setParameter('plan', $plan)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users who became paying subscribers (got a stripeSubscriptionId)
     * within the given date range. Used to estimate MRR growth month-over-month.
     */
    public function countNewPayingSubscribersBetween(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.stripeSubscriptionId IS NOT NULL')
            ->andWhere('u.createdAt >= :start')
            ->andWhere('u.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }
    // ── Dashboard chart helpers ───────────────────────────────

    /**
     * Earliest user registration date — used to set the default chart range
     * so bulk-imported users are always visible from day one.
     */
    public function getEarliestRegistrationDate(): ?\DateTimeImmutable
    {
        $conn = $this->getEntityManager()->getConnection();
        $val  = $conn->executeQuery('SELECT MIN(created_at) FROM users')->fetchOne();
        return $val ? new \DateTimeImmutable($val) : null;
    }

    /**
     * Daily registrations in one SQL query with gap-filling in PHP.
     * Returns {labels: [...], data: [...]} ready for Chart.js.
     */
    public function getDailyChartData(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->executeQuery(
            "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
             FROM users
             WHERE created_at >= :s AND created_at <= :e
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            [
                's' => (new \DateTime('@'.$start->getTimestamp()))->format('Y-m-d 00:00:00'),
                'e' => (new \DateTime('@'.$end->getTimestamp()))->format('Y-m-d 23:59:59'),
            ]
        )->fetchAllAssociative();

        $byDay = [];
        foreach ($rows as $r) { $byDay[$r['day']] = (int) $r['cnt']; }

        $labels = $data = [];
        $cur    = new \DateTime((new \DateTime('@'.$start->getTimestamp()))->format('Y-m-d'));
        $last   = new \DateTime((new \DateTime('@'.$end->getTimestamp()))->format('Y-m-d'));
        while ($cur <= $last) {
            $key      = $cur->format('Y-m-d');
            $labels[] = $cur->format('M d');
            $data[]   = $byDay[$key] ?? 0;
            $cur->modify('+1 day');
        }
        return ['labels' => $labels, 'data' => $data, 'total' => array_sum($data)];
    }

    /**
     * Monthly registrations in one SQL query with gap-filling.
     * $months = how many months back from today.
     */
    public function getMonthlyChartData(int $months): array
    {
        $startDate = (new \DateTime('first day of this month midnight'))->modify("-{$months} months");
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->executeQuery(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS mo, COUNT(*) AS cnt
             FROM users
             WHERE created_at >= :s
             GROUP BY mo
             ORDER BY mo ASC",
            ['s' => $startDate->format('Y-m-d H:i:s')]
        )->fetchAllAssociative();

        $byMonth = [];
        foreach ($rows as $r) { $byMonth[$r['mo']] = (int) $r['cnt']; }

        $labels = $data = [];
        $cur = clone $startDate;
        $now = new \DateTime('first day of this month midnight');
        while ($cur <= $now) {
            $key      = $cur->format('Y-m');
            $labels[] = $cur->format('M Y');
            $data[]   = $byMonth[$key] ?? 0;
            $cur->modify('+1 month');
        }
        return ['labels' => $labels, 'data' => $data, 'total' => array_sum($data)];
    }

    /**
     * Subscription plan distribution for the doughnut chart.
     */
    public function getPlanDistribution(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.subscriptionPlan AS plan, COUNT(u.id) AS cnt')
            ->groupBy('u.subscriptionPlan')
            ->getQuery()->getResult();
        $out = ['FREE' => 0, 'MONTHLY' => 0, 'YEARLY' => 0];
        foreach ($rows as $r) { $out[$r['plan']] = (int) $r['cnt']; }
        return $out;
    }

    /**
     * Users registered in last N days.
     */
    public function countRecentRegistrations(int $days): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :since')
            ->setParameter('since', new \DateTime("-{$days} days"))
            ->getQuery()->getSingleScalarResult();
    }

}

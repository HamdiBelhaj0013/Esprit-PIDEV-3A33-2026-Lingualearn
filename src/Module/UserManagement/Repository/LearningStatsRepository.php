<?php

namespace App\Module\UserManagement\Repository;

use App\Module\UserManagement\Entity\LearningStats;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LearningStats>
 */
class LearningStatsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearningStats::class);
    }

    /**
     * Find top learners by XP
     *
     * @param int $limit
     * @return LearningStats[]
     */
    public function findTopLearners(int $limit = 10): array
    {
        return $this->createQueryBuilder('ls')
            ->join('ls.user', 'u')
            ->where('u.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('ls.totalXP', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently active users
     *
     * @param \DateTimeInterface $since
     * @return LearningStats[]
     */
    public function findRecentlyActive(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('ls')
            ->where('ls.lastStudySession >= :since')
            ->setParameter('since', $since)
            ->orderBy('ls.lastStudySession', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get average study time
     *
     * @return float
     */
    public function getAverageStudyTime(): float
    {
        $result = $this->createQueryBuilder('ls')
            ->select('AVG(ls.totalMinutesStudied)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }
}

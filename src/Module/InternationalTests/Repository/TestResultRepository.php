<?php

namespace App\Module\InternationalTests\Repository;

use App\Module\InternationalTests\Entity\TestResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TestResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestResult::class);
    }

    /** Meilleur score d'un user pour un niveau + langue */
    public function getBestScoreByUserAndLevel(int $userId, string $level, int $langId): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('MAX(r.overallScore) as best')
            ->join('r.mockTest', 'm')
            ->where('r.user = :userId')
            ->andWhere('m.level = :level')
            ->andWhere('m.platformLanguage = :langId')
            ->andWhere('m.isActive = true')
            ->setParameter('userId', $userId)
            ->setParameter('level', $level)
            ->setParameter('langId', $langId)
            ->getQuery()
            ->getSingleResult();

        return $result['best'] !== null ? (float)$result['best'] : null;
    }

    /** Meilleur score d'un user pour un test spécifique */
    public function getBestScoreByUserAndTest(int $userId, int $testId): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('MAX(r.overallScore) as best')
            ->where('r.user = :userId')
            ->andWhere('r.mockTest = :testId')
            ->setParameter('userId', $userId)
            ->setParameter('testId', $testId)
            ->getQuery()
            ->getSingleResult();

        return $result['best'] !== null ? (float)$result['best'] : null;
    }

    /** Tous les résultats d'un user triés par date */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.mockTest', 'm')
            ->where('r.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.dateTaken', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Recherche avec filtres (back-office) */
    public function findWithFilters(
        ?string $searchTerm = null,
        ?int    $userId = null,
        string  $sortBy = 'dateTaken',
        string  $sortOrder = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.mockTest', 'm')
            ->leftJoin('r.user', 'u')
            ->addSelect('m', 'u');

        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('m.title', ':search'),
                    $qb->expr()->like('u.email', ':search')
                )
            )->setParameter('search', '%' . $searchTerm . '%');
        }

        if ($userId) {
            $qb->andWhere('r.user = :userId')
                ->setParameter('userId', $userId);
        }

        $allowed = ['id', 'overallScore', 'dateTaken', 'updatedAt'];
        if (in_array($sortBy, $allowed)) {
            $qb->orderBy('r.' . $sortBy, strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC');
        } else {
            $qb->orderBy('r.dateTaken', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    public function getStatistics(): array
    {
        $total = $this->count([]);
        $qb    = $this->createQueryBuilder('r')
            ->select('AVG(r.overallScore) as avgScore, MAX(r.overallScore) as maxScore')
            ->getQuery()
            ->getSingleResult();

        return [
            'total'    => $total,
            'avgScore' => round($qb['avgScore'] ?? 0, 2),
            'maxScore' => $qb['maxScore'] ?? 0,
        ];
    }
}

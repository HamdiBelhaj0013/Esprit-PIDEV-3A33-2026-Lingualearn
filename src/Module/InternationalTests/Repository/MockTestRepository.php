<?php

namespace App\Module\InternationalTests\Repository;

use App\Module\InternationalTests\Entity\MockTest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MockTestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MockTest::class);
    }

    /** Trouver les tests actifs par niveau ET langue */
    public function findActiveByLevelAndLanguage(string $level, int $langId): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.level = :level')
            ->andWhere('m.isActive = true')
            ->andWhere('m.platformLanguage = :langId')
            ->setParameter('level', $level)
            ->setParameter('langId', $langId)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Compter les tests actifs par niveau pour une langue */
    public function countActiveByLevelAndLanguage(int $langId): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('m.level, COUNT(m.id) as total')
            ->where('m.isActive = true')
            ->andWhere('m.platformLanguage = :langId')
            ->setParameter('langId', $langId)
            ->groupBy('m.level')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['level']] = (int)$row['total'];
        }
        return $counts;
    }

    /** Ancien findActiveByLevel (compatibilité) */
    public function findActiveByLevel(string $level): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.level = :level')
            ->andWhere('m.isActive = true')
            ->setParameter('level', $level)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** countActiveByLevel (compatibilité) */
    public function countActiveByLevel(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('m.level, COUNT(m.id) as total')
            ->where('m.isActive = true')
            ->groupBy('m.level')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['level']] = (int)$row['total'];
        }
        return $counts;
    }

    /** Types de test par niveau */
    public function findTestTypesByLevel(string $level): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('DISTINCT m.testType')
            ->where('m.level = :level')
            ->andWhere('m.isActive = true')
            ->setParameter('level', $level)
            ->orderBy('m.testType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'testType');
    }

    /** Recherche avec filtres (back-office) */
    public function findWithFilters(
        ?string $searchTerm = null,
        ?string $testType = null,
        ?bool   $isActive = null,
        string  $sortBy = 'createdAt',
        string  $sortOrder = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.testQuestions', 'q')
            ->addSelect('q');

        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('m.title', ':search'),
                    $qb->expr()->like('m.testType', ':search')
                )
            )->setParameter('search', '%' . $searchTerm . '%');
        }

        if ($testType) {
            $qb->andWhere('m.testType = :testType')
                ->setParameter('testType', $testType);
        }

        if ($isActive !== null) {
            $qb->andWhere('m.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        $allowed = ['id', 'title', 'testType', 'level', 'durationMinutes', 'createdAt', 'updatedAt'];
        if (in_array($sortBy, $allowed)) {
            $qb->orderBy('m.' . $sortBy, strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC');
        } else {
            $qb->orderBy('m.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    public function findAllTestTypes(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('DISTINCT m.testType')
            ->orderBy('m.testType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'testType');
    }

    public function getStatistics(): array
    {
        $total    = $this->count([]);
        $active   = $this->count(['isActive' => true]);
        $inactive = $this->count(['isActive' => false]);

        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(q.id) as totalQuestions')
            ->leftJoin('m.testQuestions', 'q')
            ->getQuery()
            ->getSingleResult();

        return [
            'total'          => $total,
            'active'         => $active,
            'inactive'       => $inactive,
            'totalQuestions' => $qb['totalQuestions'] ?? 0,
        ];
    }

    public function findPaginatedWithFilters(
        int     $page = 1,
        int     $limit = 10,
        ?string $searchTerm = null,
        ?string $testType = null,
        ?bool   $isActive = null,
        string  $sortBy = 'createdAt',
        string  $sortOrder = 'DESC'
    ): array {
        $offset  = ($page - 1) * $limit;
        $results = $this->findWithFilters($searchTerm, $testType, $isActive, $sortBy, $sortOrder);
        $total   = count($results);

        return [
            'data'  => array_slice($results, $offset, $limit),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }
}

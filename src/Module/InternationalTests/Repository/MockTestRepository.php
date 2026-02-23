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

    /**
     * Recherche avec filtre et tri
     */
    public function findWithFilters(
        ?string $searchTerm = null,
        ?string $testType = null,
        ?bool $isActive = null,
        string $sortBy = 'createdAt',
        string $sortOrder = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.testQuestions', 'q')
            ->addSelect('q');

        // Recherche par texte
        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('m.title', ':search'),
                    $qb->expr()->like('m.testType', ':search')
                )
            )->setParameter('search', '%' . $searchTerm . '%');
        }

        // Filtre par type de test
        if ($testType) {
            $qb->andWhere('m.testType = :testType')
                ->setParameter('testType', $testType);
        }

        // Filtre par statut actif/inactif
        if ($isActive !== null) {
            $qb->andWhere('m.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        // Tri
        $allowedSortFields = ['id', 'title', 'testType', 'durationMinutes', 'createdAt', 'updatedAt'];
        if (in_array($sortBy, $allowedSortFields)) {
            $qb->orderBy('m.' . $sortBy, strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC');
        } else {
            $qb->orderBy('m.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtenir tous les types de test uniques
     */
    public function findAllTestTypes(): array
    {
        $result = $this->createQueryBuilder('m')
            ->select('DISTINCT m.testType')
            ->orderBy('m.testType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'testType');
    }

    /**
     * Statistiques des tests
     */
    public function getStatistics(): array
    {
        $total = $this->count([]);
        $active = $this->count(['isActive' => true]);
        $inactive = $this->count(['isActive' => false]);

        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(q.id) as totalQuestions')
            ->leftJoin('m.testQuestions', 'q')
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'totalQuestions' => $qb['totalQuestions'] ?? 0
        ];
    }

    /**
     * Recherche paginée
     */
    public function findPaginatedWithFilters(
        int $page = 1,
        int $limit = 10,
        ?string $searchTerm = null,
        ?string $testType = null,
        ?bool $isActive = null,
        string $sortBy = 'createdAt',
        string $sortOrder = 'DESC'
    ): array {
        $offset = ($page - 1) * $limit;

        $results = $this->findWithFilters(
            $searchTerm,
            $testType,
            $isActive,
            $sortBy,
            $sortOrder
        );

        $total = count($results);
        $paginated = array_slice($results, $offset, $limit);

        return [
            'data' => $paginated,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
}
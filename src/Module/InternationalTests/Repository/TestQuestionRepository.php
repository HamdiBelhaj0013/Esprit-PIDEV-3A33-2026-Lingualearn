<?php

namespace App\Module\InternationalTests\Repository;

use App\Module\InternationalTests\Entity\TestQuestion;
use App\Module\InternationalTests\Entity\MockTest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TestQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestQuestion::class);
    }

    /**
     * Recherche avec filtre et tri
     */
    public function findWithFilters(
        ?string $searchTerm = null,
        ?string $sectionCategory = null,
        ?int $mockTestId = null,
        ?bool $isActive = null,
        string $sortBy = 'createdAt',
        string $sortOrder = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('q')
            ->leftJoin('q.mockTest', 'm')
            ->addSelect('m');

        // Recherche par texte
        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('q.questionText', ':search'),
                    $qb->expr()->like('q.sectionCategory', ':search'),
                    $qb->expr()->like('m.title', ':search')
                )
            )->setParameter('search', '%' . $searchTerm . '%');
        }

        // Filtre par section
        if ($sectionCategory) {
            $qb->andWhere('q.sectionCategory = :section')
                ->setParameter('section', $sectionCategory);
        }

        // Filtre par test
        if ($mockTestId) {
            $qb->andWhere('q.mockTest = :mockTestId')
                ->setParameter('mockTestId', $mockTestId);
        }

        // Filtre par statut actif/inactif
        if ($isActive !== null) {
            $qb->andWhere('q.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        // Tri
        $allowedSortFields = ['id', 'questionText', 'sectionCategory', 'points', 'createdAt', 'updatedAt'];
        if (in_array($sortBy, $allowedSortFields)) {
            $qb->orderBy('q.' . $sortBy, strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC');
        } elseif ($sortBy === 'mockTest') {
            $qb->orderBy('m.title', strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC');
        } else {
            $qb->orderBy('q.createdAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtenir toutes les catégories uniques
     */
    public function findAllSectionCategories(): array
    {
        $result = $this->createQueryBuilder('q')
            ->select('DISTINCT q.sectionCategory')
            ->orderBy('q.sectionCategory', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'sectionCategory');
    }

    /**
     * Statistiques des questions
     */
    public function getStatistics(): array
    {
        $total = $this->count([]);
        $active = $this->count(['isActive' => true]);
        $inactive = $this->count(['isActive' => false]);

        $qb = $this->createQueryBuilder('q')
            ->select('COUNT(DISTINCT q.mockTest) as totalMockTests, SUM(q.points) as totalPoints')
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'totalMockTests' => $qb['totalMockTests'] ?? 0,
            'totalPoints' => $qb['totalPoints'] ?? 0
        ];
    }

    /**
     * Recherche paginée
     */
    public function findPaginatedWithFilters(
        int $page = 1,
        int $limit = 10,
        ?string $searchTerm = null,
        ?string $sectionCategory = null,
        ?int $mockTestId = null,
        ?bool $isActive = null,
        string $sortBy = 'createdAt',
        string $sortOrder = 'DESC'
    ): array {
        $offset = ($page - 1) * $limit;

        $results = $this->findWithFilters(
            $searchTerm,
            $sectionCategory,
            $mockTestId,
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
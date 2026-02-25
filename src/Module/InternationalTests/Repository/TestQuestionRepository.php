<?php

namespace App\Module\InternationalTests\Repository;

use App\Module\InternationalTests\Entity\TestQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TestQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestQuestion::class);
    }

    /**
     * ─── NOUVEAU : 10 questions actives aléatoires pour un MockTest ───
     * C'est LA méthode clé pour le front-office.
     * Peu importe combien de questions existent, on en renvoie toujours $limit.
     */
    public function findRandomQuestions(int $mockTestId, int $limit = 10): array
    {
        // Récupère toutes les questions actives du test
        $questions = $this->createQueryBuilder('q')
            ->where('q.mockTest = :mockTestId')
            ->andWhere('q.isActive = true')
            ->setParameter('mockTestId', $mockTestId)
            ->getQuery()
            ->getResult();

        // Mélange aléatoirement côté PHP (plus portable que RAND() en DQL)
        shuffle($questions);

        // Retourne seulement le nombre demandé
        return array_slice($questions, 0, $limit);
    }

    /**
     * ─── NOUVEAU : Questions aléatoires par section/catégorie ───
     * Utile si on veut équilibrer les sections (ex: 3 Reading + 3 Grammar + 4 Vocab)
     */
    public function findRandomQuestionsBySection(int $mockTestId, string $section, int $limit = 5): array
    {
        $questions = $this->createQueryBuilder('q')
            ->where('q.mockTest = :mockTestId')
            ->andWhere('q.isActive = true')
            ->andWhere('q.sectionCategory = :section')
            ->setParameter('mockTestId', $mockTestId)
            ->setParameter('section', $section)
            ->getQuery()
            ->getResult();

        shuffle($questions);
        return array_slice($questions, 0, $limit);
    }

    /**
     * Recherche avec filtre et tri (back-office)
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

        if ($searchTerm) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('q.questionText', ':search'),
                    $qb->expr()->like('q.sectionCategory', ':search'),
                    $qb->expr()->like('m.title', ':search')
                )
            )->setParameter('search', '%' . $searchTerm . '%');
        }

        if ($sectionCategory) {
            $qb->andWhere('q.sectionCategory = :section')
                ->setParameter('section', $sectionCategory);
        }

        if ($mockTestId) {
            $qb->andWhere('q.mockTest = :mockTestId')
                ->setParameter('mockTestId', $mockTestId);
        }

        if ($isActive !== null) {
            $qb->andWhere('q.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

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
     * Obtenir toutes les catégories uniques (back-office)
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
     * Statistiques des questions (back-office)
     */
    public function getStatistics(): array
    {
        $total    = $this->count([]);
        $active   = $this->count(['isActive' => true]);
        $inactive = $this->count(['isActive' => false]);

        $qb = $this->createQueryBuilder('q')
            ->select('COUNT(DISTINCT q.mockTest) as totalMockTests, SUM(q.points) as totalPoints')
            ->getQuery()
            ->getSingleResult();

        return [
            'total'          => $total,
            'active'         => $active,
            'inactive'       => $inactive,
            'totalMockTests' => $qb['totalMockTests'] ?? 0,
            'totalPoints'    => $qb['totalPoints'] ?? 0,
        ];
    }

    /**
     * Recherche paginée (back-office)
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
        $offset  = ($page - 1) * $limit;
        $results = $this->findWithFilters($searchTerm, $sectionCategory, $mockTestId, $isActive, $sortBy, $sortOrder);
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

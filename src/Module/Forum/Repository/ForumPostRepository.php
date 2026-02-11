<?php

namespace App\Module\Forum\Repository;

use App\Module\Forum\Entity\ForumPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumPost>
 */
class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPost::class);
    }

    public function save(ForumPost $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ForumPost $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recherche avec pagination et filtres
     */
    public function findWithPagination(int $page, int $limit, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.' . ($filters['sortField'] ?? 'postedAt'), $filters['sortOrder'] ?? 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        // Filtre par mot-clé
        if (!empty($filters['keyword'])) {
            $qb->andWhere('p.title LIKE :keyword OR p.content LIKE :keyword')
               ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        // Filtre par auteur
        if (!empty($filters['authorId'])) {
            $qb->andWhere('p.authorId = :authorId')
               ->setParameter('authorId', $filters['authorId']);
        }

        // Filtre par langue
        if (!empty($filters['platformLanguageId'])) {
            $qb->andWhere('p.platformLanguageId = :platformLanguageId')
               ->setParameter('platformLanguageId', $filters['platformLanguageId']);
        }

        // Filtre par statut
        if (isset($filters['isActive']) && $filters['isActive'] !== '') {
            $qb->andWhere('p.isActive = :isActive')
               ->setParameter('isActive', (bool)$filters['isActive']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de posts selon les filtres
     */
    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        if (!empty($filters['keyword'])) {
            $qb->andWhere('p.title LIKE :keyword OR p.content LIKE :keyword')
               ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        if (!empty($filters['authorId'])) {
            $qb->andWhere('p.authorId = :authorId')
               ->setParameter('authorId', $filters['authorId']);
        }

        if (!empty($filters['platformLanguageId'])) {
            $qb->andWhere('p.platformLanguageId = :platformLanguageId')
               ->setParameter('platformLanguageId', $filters['platformLanguageId']);
        }

        if (isset($filters['isActive']) && $filters['isActive'] !== '') {
            $qb->andWhere('p.isActive = :isActive')
               ->setParameter('isActive', (bool)$filters['isActive']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Statistiques globales
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('p');
        
        return [
            'total' => $this->count([]),
            'active' => $this->count(['isActive' => true]),
            'inactive' => $this->count(['isActive' => false]),
            'totalViews' => (int) $qb->select('SUM(p.viewCount)')->getQuery()->getSingleScalarResult() ?? 0,
            'totalReplies' => (int) $this->createQueryBuilder('p')->select('SUM(p.replyCount)')->getQuery()->getSingleScalarResult() ?? 0,
        ];
    }

    /**
     * Recherche par mot-clé
     */
    public function searchByKeyword(string $keyword): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.title LIKE :keyword OR p.content LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('p.postedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Activer/désactiver un post
     */
    public function toggleStatus(ForumPost $post): void
    {
        $post->setIsActive(!$post->isActive());
        $this->getEntityManager()->flush();
    }

    /**
     * Compte total
     */
    public function countAll(): int
    {
        return $this->count([]);
    }
}
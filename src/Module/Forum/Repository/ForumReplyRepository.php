<?php

namespace App\Module\Forum\Repository;

use App\Module\Forum\Entity\ForumReply;
use App\Module\Forum\Entity\ForumPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumReply>
 */
class ForumReplyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumReply::class);
    }

    public function save(ForumReply $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ForumReply $entity, bool $flush = false): void
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
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.post', 'p')
            ->addSelect('p')
            ->orderBy('r.' . ($filters['sortField'] ?? 'repliedAt'), $filters['sortOrder'] ?? 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        // Filtre par mot-clé
        if (!empty($filters['keyword'])) {
            $qb->andWhere('r.content LIKE :keyword')
               ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        // Filtre par auteur
        if (!empty($filters['authorId'])) {
            $qb->andWhere('r.authorId = :authorId')
               ->setParameter('authorId', $filters['authorId']);
        }

        // Filtre par post
        if (!empty($filters['postId'])) {
            $qb->andWhere('r.post = :postId')
               ->setParameter('postId', $filters['postId']);
        }

        // Filtre par statut
        if (isset($filters['isActive']) && $filters['isActive'] !== '') {
            $qb->andWhere('r.isActive = :isActive')
               ->setParameter('isActive', (bool)$filters['isActive']);
        }

        // Filtre par meilleure réponse
        if (isset($filters['isBestAnswer']) && $filters['isBestAnswer'] !== '') {
            $qb->andWhere('r.isBestAnswer = :isBestAnswer')
               ->setParameter('isBestAnswer', (bool)$filters['isBestAnswer']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de réponses selon les filtres
     */
    public function countByFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');

        if (!empty($filters['keyword'])) {
            $qb->andWhere('r.content LIKE :keyword')
               ->setParameter('keyword', '%' . $filters['keyword'] . '%');
        }

        if (!empty($filters['authorId'])) {
            $qb->andWhere('r.authorId = :authorId')
               ->setParameter('authorId', $filters['authorId']);
        }

        if (!empty($filters['postId'])) {
            $qb->andWhere('r.post = :postId')
               ->setParameter('postId', $filters['postId']);
        }

        if (isset($filters['isActive']) && $filters['isActive'] !== '') {
            $qb->andWhere('r.isActive = :isActive')
               ->setParameter('isActive', (bool)$filters['isActive']);
        }

        if (isset($filters['isBestAnswer']) && $filters['isBestAnswer'] !== '') {
            $qb->andWhere('r.isBestAnswer = :isBestAnswer')
               ->setParameter('isBestAnswer', (bool)$filters['isBestAnswer']);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Statistiques globales
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->count([]),
            'active' => $this->count(['isActive' => true]),
            'inactive' => $this->count(['isActive' => false]),
            'bestAnswers' => $this->count(['isBestAnswer' => true]),
        ];
    }

    /**
     * Recherche par mot-clé
     */
    public function searchByKeyword(string $keyword): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.post', 'p')
            ->addSelect('p')
            ->where('r.content LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('r.repliedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Activer/désactiver une réponse
     */
    public function toggleStatus(ForumReply $reply): void
    {
        $reply->setIsActive(!$reply->isActive());
        $this->getEntityManager()->flush();
    }

    /**
     * Marquer comme meilleure réponse
     */
    public function markAsBestAnswer(ForumReply $reply): void
    {
        // Retirer le statut de meilleure réponse des autres réponses du même post
        $this->createQueryBuilder('r')
            ->update()
            ->set('r.isBestAnswer', ':false')
            ->where('r.post = :post')
            ->setParameter('false', false)
            ->setParameter('post', $reply->getPost())
            ->getQuery()
            ->execute();

        // Marquer cette réponse comme meilleure
        $reply->setIsBestAnswer(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouver les réponses d'un post
     */
    public function findByPost(ForumPost $post, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.post = :post')
            ->setParameter('post', $post)
            ->orderBy('r.repliedAt', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('r.isActive = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte total
     */
    public function countAll(): int
    {
        return $this->count([]);
    }
}
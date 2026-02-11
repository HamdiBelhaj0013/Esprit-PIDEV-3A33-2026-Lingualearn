<?php

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quiz>
 *
 * @method Quiz|null find($id, $lockMode = null, $lockVersion = null)
 * @method Quiz|null findOneBy(array $criteria, array $orderBy = null)
 * @method Quiz[]    findAll()
 * @method Quiz[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
    }

    /**
     * Filtrer les quiz par recherche, statut et tri.
     *
     * @param string $search Mot-clé pour titre
     * @param string|null $status 'active', 'inactive' ou null pour tous
     * @param string $sortField Champ pour trier ('id','title','createdAt','updatedAt','enabled')
     * @param string $sortOrder 'ASC' ou 'DESC'
     * @return Quiz[]
     */
    public function findByFilter(string $search = '', ?string $status = null, string $sortField = 'id', string $sortOrder = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('q');

        if ($search !== '') {
            $qb->andWhere('q.title LIKE :search OR q.description LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }

        if ($status === 'active') {
            $qb->andWhere('q.enabled = :enabled')
               ->setParameter('enabled', true);
        } elseif ($status === 'inactive') {
            $qb->andWhere('q.enabled = :enabled')
               ->setParameter('enabled', false);
        }

        $allowedFields = ['id', 'title', 'createdAt', 'updatedAt', 'enabled'];
        if (!in_array($sortField, $allowedFields)) {
            $sortField = 'id';
        }

        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('q.' . $sortField, $sortOrder);

        return $qb->getQuery()->getResult();
    }
}

<?php

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\Exercice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercice>
 *
 * @method Exercice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Exercice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Exercice[]    findAll()
 * @method Exercice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExerciceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercice::class);
    }

    public function save(Exercice $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        if ($flush) {
            $em->flush();
        }
    }

    public function remove(Exercice $entity, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Filtrer les exercices par recherche et par IA, avec tri.
     *
     * @param string $search Mot-clé pour type/question
     * @param bool|null $ai Filtrer par exercice généré par IA
     * @param string $sortField Champ pour trier ('id','type','question','aiGenerated','enabled')
     * @param string $sortOrder 'ASC' ou 'DESC'
     * @return Exercice[]
     */
    public function findByFilter(string $search = '', ?bool $ai = null, string $sortField = 'id', string $sortOrder = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('e');

        // Filtrage par mot-clé
        if ($search !== '') {
            $qb->andWhere('e.question LIKE :search OR e.type LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }

        // Filtrage par IA
        if ($ai !== null) {
            $qb->andWhere('e.aiGenerated = :ai')
               ->setParameter('ai', $ai);
        }

        // Vérifier que le champ de tri est valide
        $allowedFields = ['id', 'type', 'question', 'aiGenerated', 'enabled'];
        if (!in_array($sortField, $allowedFields)) {
            $sortField = 'id';
        }

        // Vérifier l'ordre
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('e.' . $sortField, $sortOrder);

        return $qb->getQuery()->getResult();
    }
}

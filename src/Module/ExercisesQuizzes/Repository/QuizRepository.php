<?php

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\PedagogicalContent\Entity\Lesson;
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
     * Filtrer les quiz par recherche, statut, leçon et tri.
     *
     * @param string $search Mot-clé pour titre / description
     * @param string|null $status 'active', 'inactive' ou null
     * @param string $sortField Champ de tri ('id','title','createdAt','updatedAt','enabled')
     * @param string $sortOrder 'ASC' ou 'DESC'
     * @param int|null $lessonId Filtrer par leçon (optionnel)
     * @return Quiz[]
     */
    public function findByFilter(
        string $search = '',
        ?string $status = null,
        string $sortField = 'id',
        string $sortOrder = 'DESC',
        ?int $lessonId = null
    ): array {
        $qb = $this->createQueryBuilder('q');

        if ($search !== '') {
            $qb->andWhere('q.title LIKE :search OR q.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($status === 'active') {
            $qb->andWhere('q.enabled = :enabled')
               ->setParameter('enabled', true);
        } elseif ($status === 'inactive') {
            $qb->andWhere('q.enabled = :enabled')
               ->setParameter('enabled', false);
        }

        if ($lessonId !== null) {
            $qb->andWhere('q.lesson = :lessonId')
               ->setParameter('lessonId', $lessonId);
        }

        $allowedFields = ['id', 'title', 'createdAt', 'updatedAt', 'enabled'];
        if (!in_array($sortField, $allowedFields)) {
            $sortField = 'id';
        }
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy('q.' . $sortField, $sortOrder);

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne le premier quiz activé rattaché à une leçon (pour le front apprenant).
     */
    public function findOneByLessonAndEnabled(Lesson $lesson): ?Quiz
    {
        return $this->createQueryBuilder('q')
            ->where('q.lesson = :lesson')
            ->andWhere('q.enabled = :enabled')
            ->setParameter('lesson', $lesson)
            ->setParameter('enabled', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Quiz activés dont la leçon appartient à un cours d'une des langues données (pour la page Practice).
     *
     * @param int[] $platformLanguageIds IDs des langues (ex. langues auxquelles l'utilisateur est inscrit)
     * @return Quiz[]
     */
    public function findEnabledByPlatformLanguageIds(array $platformLanguageIds): array
    {
        if (empty($platformLanguageIds)) {
            return [];
        }

        return $this->createQueryBuilder('q')
            ->innerJoin('q.lesson', 'l')
            ->innerJoin('l.course', 'c')
            ->innerJoin('c.platformLanguage', 'pl')
            ->where('q.enabled = :enabled')
            ->andWhere('pl.id IN (:ids)')
            ->setParameter('enabled', true)
            ->setParameter('ids', $platformLanguageIds)
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les quiz activés qui ont une leçon (pour la page Practice : afficher les quiz disponibles).
     *
     * @return Quiz[]
     */
    public function findEnabledWithLesson(): array
    {
        return $this->createQueryBuilder('q')
            ->innerJoin('q.lesson', 'l')
            ->where('q.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les quiz activés (avec ou sans leçon) pour afficher la même liste qu'au back-office.
     *
     * @return Quiz[]
     */
    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('q.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

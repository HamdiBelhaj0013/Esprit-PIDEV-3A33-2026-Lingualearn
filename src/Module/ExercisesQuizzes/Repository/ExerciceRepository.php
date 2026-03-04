<?php

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\Quiz;
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
     * Filtrer les exercices par recherche, IA et quiz, avec tri.
     *
     * @param string $search Mot-clé pour type/question
     * @param bool|null $ai Filtrer par exercice généré par IA
     * @param string $sortField Champ pour trier ('id','type','question','aiGenerated','enabled')
     * @param string $sortOrder 'ASC' ou 'DESC'
     * @param int|null $quizId Filtrer par quiz (optionnel)
     * @return Exercice[]
     */
    public function findByFilter(string $search = '', ?bool $ai = null, string $sortField = 'id', string $sortOrder = 'DESC', ?int $quizId = null): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($search !== '') {
            $qb->andWhere('e.question LIKE :search OR e.type LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }

        if ($ai !== null) {
            $qb->andWhere('e.aiGenerated = :ai')
               ->setParameter('ai', $ai);
        }

        if ($quizId !== null) {
            $qb->andWhere('e.quiz = :quizId')
               ->setParameter('quizId', $quizId);
        }

        $allowedFields = ['id', 'type', 'question', 'aiGenerated', 'enabled'];
        if (!in_array($sortField, $allowedFields)) {
            $sortField = 'id';
        }

        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('e.' . $sortField, $sortOrder);

        return $qb->getQuery()->getResult();
    }

    /**
     * Exercices activés d'un quiz (requête directe en base pour refléter l'état réel).
     *
     * @return Exercice[]
     */
    public function findEnabledByQuiz(Quiz $quiz): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.quiz = :quiz')
            ->andWhere('e.enabled = :enabled')
            ->setParameter('quiz', $quiz)
            ->setParameter('enabled', true)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve des exercices activés qui ont au moins un des skillCodes donné.
     *
     * @param string[] $skillCodes
     * @param int|null $maxDifficulty Difficulté max (1-5), null = pas de filtre
     * @param int[] $excludeIds IDs d'exercices à exclure
     * @return Exercice[]
     */
    public function findBySkillCodes(array $skillCodes, ?int $maxDifficulty = null, array $excludeIds = [], int $limit = 20): array
    {
        if ($skillCodes === []) {
            $all = $this->findBy(['enabled' => true], ['id' => 'ASC'], min($limit * 2, 500));
            $filtered = array_slice(array_filter($all, fn ($e) => !in_array($e->getId(), $excludeIds, true)), 0, $limit);
            return array_values($filtered);
        }

        $qb = $this->createQueryBuilder('e')
            ->where('e.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(min($limit * 3, 500));

        if ($excludeIds !== []) {
            $qb->andWhere('e.id NOT IN (:excludeIds)')->setParameter('excludeIds', $excludeIds);
        }
        if ($maxDifficulty !== null) {
            $qb->andWhere('e.difficulty <= :maxDiff')->setParameter('maxDiff', max(1, min(5, $maxDifficulty)));
        }

        $candidates = $qb->getQuery()->getResult();
        $result = [];
        foreach ($candidates as $e) {
            $skills = $e->getSkillCodes();
            if ($skills === []) {
                continue;
            }
            $overlap = array_intersect($skillCodes, $skills);
            if ($overlap !== [] && !in_array($e->getId(), $excludeIds, true)) {
                $result[] = $e;
                if (count($result) >= $limit) {
                    break;
                }
            }
        }
        return $result;
    }
}

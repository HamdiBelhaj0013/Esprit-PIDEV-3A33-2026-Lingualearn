<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\QuizAttempt;
use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\UserManagement\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<QuizAttempt> */
class QuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAttempt::class);
    }

    public function findForUser(int $attemptId, User $user): ?QuizAttempt
    {
        return $this->createQueryBuilder('a')
            ->where('a.id = :id')
            ->andWhere('a.user = :user')
            ->setParameter('id', $attemptId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getWithExerciseAttempts(int $attemptId, User $user): ?QuizAttempt
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.exerciseAttempts', 'ea')
            ->addSelect('ea')
            ->leftJoin('ea.exercise', 'ex')
            ->addSelect('ex')
            ->where('a.id = :id')
            ->andWhere('a.user = :user')
            ->setParameter('id', $attemptId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(QuizAttempt $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** Dernière tentative pour ce user + quiz (pour affichage "dernier score"). Ne considère que les tentatives terminées. */
    public function findLatestForUserAndQuiz(User $user, Quiz $quiz): ?QuizAttempt
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.quiz = :quiz')
            ->andWhere('a.finishedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('quiz', $quiz)
            ->orderBy('a.finishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Meilleur score enregistré pour ce user + quiz (max des bestScore des tentatives terminées). */
    public function getBestScoreForUserAndQuiz(User $user, Quiz $quiz): int
    {
        $value = $this->createQueryBuilder('a')
            ->select('MAX(a.bestScore)')
            ->where('a.user = :user')
            ->andWhere('a.quiz = :quiz')
            ->andWhere('a.finishedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('quiz', $quiz)
            ->getQuery()
            ->getSingleScalarResult();
        return (int) ($value ?? 0);
    }

    /**
     * Tentatives terminées pour un utilisateur (avec exerciseAttempts + exercise), pour analyse de compétences.
     *
     * @return QuizAttempt[]
     */
    public function findFinishedForUserWithDetails(User $user, int $limit = 200): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.exerciseAttempts', 'ea')
            ->addSelect('ea')
            ->leftJoin('ea.exercise', 'ex')
            ->addSelect('ex')
            ->where('a.user = :user')
            ->andWhere('a.finishedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('a.finishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

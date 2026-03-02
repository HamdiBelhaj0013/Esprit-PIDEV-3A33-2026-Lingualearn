<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\ExerciseAiFeedback;
use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\QuizAttempt;
use App\Module\UserManagement\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ExerciseAiFeedback> */
class ExerciseAiFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseAiFeedback::class);
    }

    public function findOneByUserAttemptExerciseAndHash(
        User $user,
        QuizAttempt $attempt,
        Exercice $exercise,
        string $promptHash
    ): ?ExerciseAiFeedback {
        return $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->andWhere('f.quizAttempt = :attempt')
            ->andWhere('f.exercise = :exercise')
            ->andWhere('f.promptHash = :hash')
            ->setParameter('user', $user)
            ->setParameter('attempt', $attempt)
            ->setParameter('exercise', $exercise)
            ->setParameter('hash', $promptHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ExerciseAiFeedback[] indexés par exercise_id
     */
    public function findByUserAndAttempt(User $user, QuizAttempt $attempt): array
    {
        $list = $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->andWhere('f.quizAttempt = :attempt')
            ->setParameter('user', $user)
            ->setParameter('attempt', $attempt)
            ->getQuery()
            ->getResult();

        $byExercise = [];
        foreach ($list as $feedback) {
            $ex = $feedback->getExercise();
            if ($ex !== null) {
                $byExercise[$ex->getId()] = $feedback;
            }
        }
        return $byExercise;
    }

    public function save(ExerciseAiFeedback $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

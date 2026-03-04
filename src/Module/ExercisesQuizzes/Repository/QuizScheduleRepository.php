<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\ExercisesQuizzes\Entity\QuizSchedule;
use App\Module\UserManagement\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizSchedule>
 */
class QuizScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizSchedule::class);
    }

    /**
     * Charge en une requête les planifications SCHEDULED pour un utilisateur et une liste de quiz.
     * Retourne un map quizId => QuizSchedule (un seul enregistrement actif par quiz/user).
     *
     * @param int[] $quizIds
     * @return array<int, QuizSchedule|null>
     */
    public function getScheduleMapForUserAndQuizIds(User $user, array $quizIds): array
    {
        if ($quizIds === []) {
            return [];
        }

        $schedules = $this->createQueryBuilder('s')
            ->where('s.student = :user')
            ->andWhere('s.quiz IN (:quizIds)')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('quizIds', $quizIds)
            ->setParameter('status', QuizSchedule::STATUS_SCHEDULED)
            ->getQuery()
            ->getResult();

        $map = array_fill_keys($quizIds, null);
        foreach ($schedules as $schedule) {
            $quizId = $schedule->getQuiz()?->getId();
            if ($quizId !== null) {
                $map[$quizId] = $schedule;
            }
        }
        return $map;
    }

    /**
     * Dernière planification (tous statuts) pour user + quiz (pour affichage "Terminé" avec date).
     */
    public function findLatestByUserAndQuiz(User $user, Quiz $quiz): ?QuizSchedule
    {
        return $this->createQueryBuilder('s')
            ->where('s.student = :user')
            ->andWhere('s.quiz = :quiz')
            ->setParameter('user', $user)
            ->setParameter('quiz', $quiz)
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneScheduledByUserAndQuiz(User $user, Quiz $quiz): ?QuizSchedule
    {
        return $this->createQueryBuilder('s')
            ->where('s.student = :user')
            ->andWhere('s.quiz = :quiz')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('quiz', $quiz)
            ->setParameter('status', QuizSchedule::STATUS_SCHEDULED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

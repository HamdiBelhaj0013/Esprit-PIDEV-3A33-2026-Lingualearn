<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\ExercisesQuizzes\Entity\QuizSchedule;
use App\Module\ExercisesQuizzes\Repository\QuizScheduleRepository;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service métier : planification des quiz pour l'étudiant (type Google Calendar).
 * Sécurité : l'étudiant ne peut planifier que pour lui-même.
 */
final class QuizPlanningService
{
    public function __construct(
        private EntityManagerInterface $em,
        private QuizScheduleRepository $quizScheduleRepository,
    ) {
    }

    /**
     * Planifier un quiz (création). Interdit si déjà travaillé (vérifié par le contrôleur).
     */
    public function schedule(User $student, Quiz $quiz, \DateTimeImmutable $scheduledAt, ?string $note = null): QuizSchedule
    {
        $schedule = new QuizSchedule();
        $schedule->setStudent($student);
        $schedule->setQuiz($quiz);
        $schedule->setScheduledAt($scheduledAt);
        $schedule->setNote($note);
        $schedule->setStatus(QuizSchedule::STATUS_SCHEDULED);

        $this->em->persist($schedule);
        $this->em->flush();

        return $schedule;
    }

    /**
     * Reporter une planification (date/heure + note). Uniquement si SCHEDULED et même étudiant.
     */
    public function reschedule(QuizSchedule $schedule, User $student, \DateTimeImmutable $scheduledAt, ?string $note = null): void
    {
        if ($schedule->getStudent()?->getId() !== $student->getId()) {
            throw new \InvalidArgumentException('Seul l\'étudiant concerné peut modifier cette planification.');
        }
        if ($schedule->getStatus() !== QuizSchedule::STATUS_SCHEDULED) {
            throw new \InvalidArgumentException('Seule une planification en attente peut être modifiée.');
        }

        $schedule->setScheduledAt($scheduledAt);
        $schedule->setNote($note);
        $this->em->flush();
    }

    /**
     * Annuler une planification. Uniquement si SCHEDULED et même étudiant.
     */
    public function cancel(QuizSchedule $schedule, User $student): void
    {
        if ($schedule->getStudent()?->getId() !== $student->getId()) {
            throw new \InvalidArgumentException('Seul l\'étudiant concerné peut annuler cette planification.');
        }
        if ($schedule->getStatus() !== QuizSchedule::STATUS_SCHEDULED) {
            throw new \InvalidArgumentException('Seule une planification en attente peut être annulée.');
        }

        $schedule->setStatus(QuizSchedule::STATUS_CANCELLED);
        $this->em->flush();
    }

    /**
     * Carte quizId => QuizSchedule (SCHEDULED uniquement) pour un utilisateur et une liste de quiz.
     * Requête optimisée en batch.
     *
     * @param int[] $quizIds
     * @return array<int, QuizSchedule|null>
     */
    public function getScheduleMap(User $student, array $quizIds): array
    {
        return $this->quizScheduleRepository->getScheduleMapForUserAndQuizIds($student, $quizIds);
    }

    /**
     * Passe en DONE la planification SCHEDULED pour ce user et ce quiz (appelé après soumission du quiz).
     * Sans effet si aucune planification SCHEDULED.
     */
    public function markDoneForUserAndQuiz(User $student, Quiz $quiz): void
    {
        $schedule = $this->quizScheduleRepository->findOneScheduledByUserAndQuiz($student, $quiz);
        if ($schedule === null) {
            return;
        }
        $schedule->setStatus(QuizSchedule::STATUS_DONE);
        $this->em->flush();
    }
}

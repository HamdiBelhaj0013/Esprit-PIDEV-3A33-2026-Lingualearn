<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\ExercisesQuizzes\Entity\QuizSchedule;
use App\Module\ExercisesQuizzes\Form\QuizScheduleType;
use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\QuizAttemptRepository;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\ExercisesQuizzes\Repository\QuizScheduleRepository;
use App\Module\ExercisesQuizzes\Service\QuizPlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Planification des quiz (type Google Calendar) pour l'étudiant.
 * L'étudiant ne peut planifier que les quiz qu'il a le droit de passer (liste practice).
 */
#[IsGranted('ROLE_USER')]
#[Route('/learn/practice/quiz', name: 'learn_practice_quiz_', requirements: ['id' => '\d+'])]
class UserQuizScheduleController extends AbstractController
{
    public function __construct(
        private QuizRepository $quizRepository,
        private ExerciceRepository $exerciceRepository,
        private QuizAttemptRepository $quizAttemptRepository,
        private QuizScheduleRepository $quizScheduleRepository,
        private QuizPlanningService $quizPlanningService,
    ) {
    }

    /**
     * Formulaire : planifier ou modifier la planification. Interdit si déjà travaillé.
     */
    #[Route('/{id}/schedule', name: 'schedule', methods: ['GET', 'POST'])]
    public function schedule(int $id, Request $request): Response
    {
        $user = $this->getUser();
        $quiz = $this->quizRepository->find($id);
        if (!$quiz || !$quiz->isEnabled()) {
            $this->addFlash('warning', 'Quiz introuvable ou inactif.');
            return $this->redirectToRoute('learn_practice');
        }

        $lesson = $quiz->getLesson();
        if (!$lesson) {
            $this->addFlash('warning', 'Ce quiz n\'est pas lié à une leçon.');
            return $this->redirectToRoute('learn_practice');
        }

        $exercices = $this->exerciceRepository->findEnabledByQuiz($quiz);
        if (empty($exercices)) {
            $this->addFlash('warning', 'Ce quiz n\'a pas d\'exercices activés.');
            return $this->redirectToRoute('learn_practice');
        }

        $hasAttempted = $this->quizAttemptRepository->findLatestForUserAndQuiz($user, $quiz) !== null;
        if ($hasAttempted) {
            $this->addFlash('warning', 'Vous avez déjà passé ce quiz. La planification n\'est plus disponible.');
            return $this->redirectToRoute('learn_practice');
        }

        $existing = $this->quizScheduleRepository->findOneScheduledByUserAndQuiz($user, $quiz);
        $schedule = $existing ?? new QuizSchedule();
        if (!$existing) {
            $schedule->setStudent($user);
            $schedule->setQuiz($quiz);
            $schedule->setScheduledAt((new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0));
        }

        $form = $this->createForm(QuizScheduleType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $scheduledAt = $schedule->getScheduledAt();
            $note = $schedule->getNote();
            if ($scheduledAt === null) {
                $this->addFlash('danger', 'Veuillez choisir une date.');
                return $this->redirectToRoute('learn_practice_quiz_schedule', ['id' => $id]);
            }
            if ($scheduledAt instanceof \DateTime) {
                $scheduledAt = \DateTimeImmutable::createFromMutable($scheduledAt);
            }
            if ($scheduledAt <= new \DateTimeImmutable()) {
                $this->addFlash('danger', 'La date doit être dans le futur.');
                return $this->redirectToRoute('learn_practice_quiz_schedule', ['id' => $id]);
            }

            try {
                if ($existing) {
                    $this->quizPlanningService->reschedule($schedule, $user, $scheduledAt, $note);
                    $this->addFlash('success', 'Planification mise à jour.');
                } else {
                    $this->quizPlanningService->schedule($user, $quiz, $scheduledAt, $note);
                    $this->addFlash('success', 'Quiz planifié.');
                }
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
            return $this->redirectToRoute('learn_practice');
        }

        return $this->render('exercises_quizzes/learn/schedule_form.html.twig', [
            'quiz' => $quiz,
            'lesson' => $lesson,
            'form' => $form,
            'is_edit' => $existing !== null,
        ]);
    }

    /**
     * Annuler une planification (POST pour sécurité).
     */
    #[Route('/schedule/{scheduleId}/cancel', name: 'schedule_cancel', methods: ['POST'], requirements: ['scheduleId' => '\d+'])]
    public function cancelSchedule(int $scheduleId, Request $request): Response
    {
        $user = $this->getUser();
        $schedule = $this->quizScheduleRepository->find($scheduleId);
        if (!$schedule) {
            $this->addFlash('warning', 'Planification introuvable.');
            return $this->redirectToRoute('learn_practice');
        }
        if ($schedule->getStudent()?->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès non autorisé.');
            return $this->redirectToRoute('learn_practice');
        }
        if ($request->request->get('_token') && !$this->isCsrfTokenValid('schedule_cancel_' . $schedule->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('learn_practice');
        }

        try {
            $this->quizPlanningService->cancel($schedule, $user);
            $this->addFlash('success', 'Planification annulée.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('learn_practice');
    }
}

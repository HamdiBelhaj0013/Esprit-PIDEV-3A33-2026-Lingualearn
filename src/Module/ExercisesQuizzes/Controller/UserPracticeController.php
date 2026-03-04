<?php

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\QuizAttemptRepository;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\ExercisesQuizzes\Service\QuizPlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page Practice : liste des quiz (front apprenant).
 * "Déjà joué" et scores sont basés sur QuizAttempt (par quiz), pas sur UserLessonStatus (par leçon).
 */
#[IsGranted('ROLE_USER')]
class UserPracticeController extends AbstractController
{
    public function __construct(
        private QuizRepository $quizRepository,
        private ExerciceRepository $exerciceRepository,
        private QuizAttemptRepository $quizAttemptRepository,
        private QuizPlanningService $quizPlanningService,
    ) {}

    #[Route('/learn/practice', name: 'learn_practice', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        $quizzes = $this->quizRepository->findEnabledWithLesson();

        $quizIds = array_map(fn ($q) => $q->getId(), $quizzes);
        $scheduleMap = $this->quizPlanningService->getScheduleMap($user, $quizIds);

        $items = [];
        foreach ($quizzes as $quiz) {
            $lesson = $quiz->getLesson();
            if (!$lesson) {
                continue;
            }
            $course = $lesson->getCourse();
            $language = $course?->getPlatformLanguage();
            if (!$course || !$language) {
                continue;
            }

            $latestAttempt = $this->quizAttemptRepository->findLatestForUserAndQuiz($user, $quiz);
            $hasAttempted = $latestAttempt !== null;
            $bestScore = $hasAttempted ? $this->quizAttemptRepository->getBestScoreForUserAndQuiz($user, $quiz) : 0;
            $lastScore = $latestAttempt?->getScore();

            $enabledExercices = $this->exerciceRepository->findEnabledByQuiz($quiz);
            $exerciseCount = count($enabledExercices);

            if ($exerciseCount === 0) {
                continue;
            }

            $items[] = [
                'quiz' => $quiz,
                'lesson' => $lesson,
                'course' => $course,
                'language' => $language,
                'bestScore' => $bestScore,
                'lastScore' => $lastScore,
                'hasAttempted' => $hasAttempted,
                'exerciseCount' => $exerciseCount,
                'playable' => true,
                'passed' => $quiz->getPassingScore() !== null && $bestScore >= $quiz->getPassingScore(),
                'schedule' => $scheduleMap[$quiz->getId()] ?? null,
                'canSchedule' => !$hasAttempted,
            ];
        }

        return $this->render('exercises_quizzes/learn/practice.html.twig', [
            'items' => $items,
        ]);
    }
}

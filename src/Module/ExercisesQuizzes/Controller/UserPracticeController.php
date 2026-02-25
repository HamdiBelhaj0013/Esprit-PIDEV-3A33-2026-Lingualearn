<?php

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
<<<<<<< Updated upstream
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\ExercisesQuizzes\Repository\UserLessonStatusRepository;
=======
use App\Module\ExercisesQuizzes\Repository\QuizAttemptRepository;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\ExercisesQuizzes\Service\QuizPlanningService;
>>>>>>> Stashed changes
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
<<<<<<< Updated upstream
 * Page Practice : liste des quiz accessibles à l'utilisateur et avancement.
=======
 * Page Practice : liste des quiz (front apprenant).
 * "Déjà joué" et scores sont basés sur QuizAttempt (par quiz), pas sur UserLessonStatus (par leçon).
>>>>>>> Stashed changes
 */
#[IsGranted('ROLE_USER')]
class UserPracticeController extends AbstractController
{
    public function __construct(
        private QuizRepository $quizRepository,
        private ExerciceRepository $exerciceRepository,
<<<<<<< Updated upstream
        private UserLessonStatusRepository $userLessonStatusRepository,
=======
        private QuizAttemptRepository $quizAttemptRepository,
        private QuizPlanningService $quizPlanningService,
>>>>>>> Stashed changes
    ) {}

    #[Route('/learn/practice', name: 'learn_practice', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

<<<<<<< Updated upstream
        $enrolledLanguageIds = [];
        foreach ($user->getUserLanguages() as $ul) {
            $enrolledLanguageIds[] = $ul->getPlatformLanguage()->getId();
        }

        // Afficher tous les quiz activés (même liste qu'au back-office) ; "Commencer le quiz" si leçon + exercices activés
        $quizzes = $this->quizRepository->findAllEnabled();
=======
        $quizzes = $this->quizRepository->findEnabledWithLesson();

        $quizIds = array_map(fn ($q) => $q->getId(), $quizzes);
        $scheduleMap = $this->quizPlanningService->getScheduleMap($user, $quizIds);
>>>>>>> Stashed changes

        $items = [];
        foreach ($quizzes as $quiz) {
            $lesson = $quiz->getLesson();
<<<<<<< Updated upstream
            $course = $lesson?->getCourse();
            $language = $course?->getPlatformLanguage();

            $status = $lesson ? $this->userLessonStatusRepository->findOneBy([
                'user' => $user,
                'lesson' => $lesson,
            ]) : null;
            $bestScore = $status ? $status->getBestQuizScore() : 0;
            $lastScore = $status ? $status->getLastQuizScore() : null;
            $enabledExercices = $this->exerciceRepository->findEnabledByQuiz($quiz);
            $exerciseCount = count($enabledExercices);

            $playable = $lesson !== null && $exerciseCount > 0;
=======
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
>>>>>>> Stashed changes

            $items[] = [
                'quiz' => $quiz,
                'lesson' => $lesson,
                'course' => $course,
                'language' => $language,
                'bestScore' => $bestScore,
                'lastScore' => $lastScore,
<<<<<<< Updated upstream
                'hasAttempted' => $status !== null,
                'exerciseCount' => $exerciseCount,
                'playable' => $playable,
                'passed' => $quiz->getPassingScore() !== null && $bestScore >= $quiz->getPassingScore(),
=======
                'hasAttempted' => $hasAttempted,
                'exerciseCount' => $exerciseCount,
                'playable' => true,
                'passed' => $quiz->getPassingScore() !== null && $bestScore >= $quiz->getPassingScore(),
                'schedule' => $scheduleMap[$quiz->getId()] ?? null,
                'canSchedule' => !$hasAttempted,
>>>>>>> Stashed changes
            ];
        }

        return $this->render('exercises_quizzes/learn/practice.html.twig', [
            'items' => $items,
        ]);
    }
}

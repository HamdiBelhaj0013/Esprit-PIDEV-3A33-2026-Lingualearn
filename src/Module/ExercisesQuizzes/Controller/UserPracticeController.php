<?php

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\ExercisesQuizzes\Repository\UserLessonStatusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page Practice : liste des quiz accessibles à l'utilisateur et avancement.
 */
#[IsGranted('ROLE_USER')]
class UserPracticeController extends AbstractController
{
    public function __construct(
        private QuizRepository $quizRepository,
        private ExerciceRepository $exerciceRepository,
        private UserLessonStatusRepository $userLessonStatusRepository,
    ) {}

    #[Route('/learn/practice', name: 'learn_practice', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        $enrolledLanguageIds = [];
        foreach ($user->getUserLanguages() as $ul) {
            $enrolledLanguageIds[] = $ul->getPlatformLanguage()->getId();
        }

        // Afficher tous les quiz activés (même liste qu'au back-office) ; "Commencer le quiz" si leçon + exercices activés
        $quizzes = $this->quizRepository->findAllEnabled();

        $items = [];
        foreach ($quizzes as $quiz) {
            $lesson = $quiz->getLesson();
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

            $items[] = [
                'quiz' => $quiz,
                'lesson' => $lesson,
                'course' => $course,
                'language' => $language,
                'bestScore' => $bestScore,
                'lastScore' => $lastScore,
                'hasAttempted' => $status !== null,
                'exerciseCount' => $exerciseCount,
                'playable' => $playable,
                'passed' => $quiz->getPassingScore() !== null && $bestScore >= $quiz->getPassingScore(),
            ];
        }

        return $this->render('exercises_quizzes/learn/practice.html.twig', [
            'items' => $items,
        ]);
    }
}

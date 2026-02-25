<?php

namespace App\Module\ExercisesQuizzes\Controller;

<<<<<<< Updated upstream
use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\ExercisesQuizzes\Entity\UserLessonStatus;
use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\ExercisesQuizzes\Repository\UserLessonStatusRepository;
use App\Module\PedagogicalContent\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
=======
use App\Module\ExercisesQuizzes\Entity\ExerciseAttempt;
use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\QuizAttempt;
use App\Module\ExercisesQuizzes\Entity\UserLessonStatus;
use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\ExerciseAiFeedbackRepository;
use App\Module\ExercisesQuizzes\Repository\QuizAttemptRepository;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\ExercisesQuizzes\Repository\UserLessonStatusRepository;
use App\Module\ExercisesQuizzes\Service\QuizPlanningService;
use App\Module\ExercisesQuizzes\Service\SecondChanceService;
use App\Module\PedagogicalContent\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
>>>>>>> Stashed changes
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
<<<<<<< Updated upstream
 * Contrôleur front : jouer un quiz lié à une leçon (sans modifier les autres modules).
=======
 * Front : jouer un quiz lié à une leçon.
>>>>>>> Stashed changes
 */
#[IsGranted('ROLE_USER')]
#[Route('/learn/lesson/{id}/quiz', name: 'learn_lesson_quiz_', requirements: ['id' => '\d+'])]
class UserQuizController extends AbstractController
{
    private const SESSION_QUIZ_RESULT = 'learn_quiz_result_';
<<<<<<< Updated upstream
=======
    private const GRAPH_QUIZ_ATTEMPT = 'quiz_attempt';
>>>>>>> Stashed changes

    public function __construct(
        private QuizRepository $quizRepository,
        private ExerciceRepository $exerciceRepository,
        private UserLessonStatusRepository $userLessonStatusRepository,
<<<<<<< Updated upstream
        private EntityManagerInterface $em,
    ) {}

    /**
     * Affiche le quiz de la leçon (formulaire des exercices).
     */
    #[Route('', name: 'play', methods: ['GET'])]
    public function play(Lesson $lesson): Response
    {
        $user = $this->getUser();

        $quiz = $this->quizRepository->findOneByLessonAndEnabled($lesson);
=======
        private QuizAttemptRepository $quizAttemptRepository,
        private ExerciseAiFeedbackRepository $aiFeedbackRepository,
        private EntityManagerInterface $em,
        private QuizPlanningService $quizPlanningService,
        private StateMachineFactoryInterface $stateMachineFactory,
        private SecondChanceService $secondChanceService,
    ) {}

    #[Route('', name: 'play', methods: ['GET'])]
    public function play(Request $request, Lesson $lesson): Response
    {
        $quizId = $request->query->getInt('quiz', 0);
        if ($quizId > 0) {
            $quiz = $this->quizRepository->find($quizId);
            if (!$quiz || !$quiz->isEnabled() || $quiz->getLesson()?->getId() !== $lesson->getId()) {
                $this->addFlash('warning', 'Quiz introuvable pour cette leçon.');
                return $this->redirectToRoute('learn_practice');
            }
        } else {
            $quiz = $this->quizRepository->findOneByLessonAndEnabled($lesson);
        }

>>>>>>> Stashed changes
        if (!$quiz) {
            $this->addFlash('warning', 'Aucun quiz disponible pour cette leçon.');
            return $this->redirectToRoute('learn_practice');
        }

        $exercices = $this->exerciceRepository->findEnabledByQuiz($quiz);
        if (empty($exercices)) {
            $this->addFlash('warning', 'Ce quiz n\'a pas encore d\'exercices activés. Activez des exercices dans le back-office.');
            return $this->redirectToRoute('learn_practice');
        }

        $course = $lesson->getCourse();
        $language = $course->getPlatformLanguage();

        return $this->render('exercises_quizzes/learn/quiz_play.html.twig', [
            'quiz' => $quiz,
            'lesson' => $lesson,
            'course' => $course,
            'language' => $language,
            'exercices' => $exercices,
        ]);
    }

<<<<<<< Updated upstream
    /**
     * Soumet les réponses, calcule le score, met à jour UserLessonStatus, redirige vers la page résultat.
     */
=======
>>>>>>> Stashed changes
    #[Route('/submit', name: 'submit', methods: ['POST'])]
    public function submit(Request $request, Lesson $lesson): Response
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('learn_quiz_submit', $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
<<<<<<< Updated upstream
            return $this->redirectToRoute('learn_lesson_quiz_play', ['id' => $lesson->getId()]);
=======
            $params = ['id' => $lesson->getId()];
            if ($request->request->getInt('_quiz_id', 0) > 0) {
                $params['quiz'] = $request->request->get('_quiz_id');
            }
            return $this->redirectToRoute('learn_lesson_quiz_play', $params);
>>>>>>> Stashed changes
        }

        $quiz = $this->quizRepository->findOneByLessonAndEnabled($lesson);
        if (!$quiz) {
            $this->addFlash('warning', 'Aucun quiz disponible.');
            return $this->redirectToRoute('learn_practice');
        }

<<<<<<< Updated upstream
=======
        $quizId = $request->request->getInt('_quiz_id', 0);
        if ($quizId > 0) {
            $quizByRequest = $this->quizRepository->find($quizId);
            if ($quizByRequest && $quizByRequest->isEnabled() && $quizByRequest->getLesson()?->getId() === $lesson->getId()) {
                $quiz = $quizByRequest;
            }
        }

>>>>>>> Stashed changes
        $exercices = $this->exerciceRepository->findEnabledByQuiz($quiz);
        if (empty($exercices)) {
            $this->addFlash('warning', 'Ce quiz n\'a pas d\'exercices activés.');
            return $this->redirectToRoute('learn_practice');
        }

        $answers = $request->request->all('answers') ?? [];
<<<<<<< Updated upstream
        $correct = 0;
=======
        $total = count($exercices);
        $correct = 0;

        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);
        $attempt->setLesson($lesson);
        $attempt->setAttemptNumber(1);
        $attempt->setState(QuizAttempt::STATE_IN_PROGRESS);
        $this->em->persist($attempt);

>>>>>>> Stashed changes
        foreach ($exercices as $exercice) {
            $userAnswer = $answers[$exercice->getId()] ?? '';
            $normalizedUser = Exercice::normalizeOption((string) $userAnswer);
            $normalizedCorrect = Exercice::normalizeOption((string) $exercice->getCorrectAnswer());
<<<<<<< Updated upstream
            if ($normalizedUser !== '' && $normalizedUser === $normalizedCorrect) {
                $correct++;
            }
        }

        $total = count($exercices);
        $score = $total > 0 ? (int) round(($correct / $total) * 100) : 0;
        $passed = $score >= $quiz->getPassingScore();
=======
            $isCorrect = $normalizedUser !== '' && $normalizedUser === $normalizedCorrect;
            if ($isCorrect) {
                $correct++;
            }
            $ea = new ExerciseAttempt();
            $ea->setQuizAttempt($attempt);
            $ea->setExercise($exercice);
            $ea->setIsCorrect($isCorrect);
            $ea->setGivenAnswer($userAnswer !== '' ? $userAnswer : null);
            $ea->setPoints($isCorrect ? 1 : 0);
            $this->em->persist($ea);
            $attempt->addExerciseAttempt($ea);
        }

        $score = $total > 0 ? (int) round(($correct / $total) * 100) : 0;
        $passingScore = $quiz->getPassingScore();
        $passed = $passingScore !== null && $score >= $passingScore;
        $attempt->setScore($score);
        $attempt->setBestScore($score);
        $attempt->setFinishedAt(new \DateTimeImmutable());

        $sm = $this->stateMachineFactory->get($attempt, self::GRAPH_QUIZ_ATTEMPT);
        if ($sm->can('finish_first')) {
            $sm->apply('finish_first');
        }
        if ($sm->can('unlock_second')) {
            $sm->apply('unlock_second');
        }
        $this->em->flush();
>>>>>>> Stashed changes

        $status = $this->userLessonStatusRepository->findOneBy(['user' => $user, 'lesson' => $lesson]);
        if (!$status) {
            $status = new UserLessonStatus();
            $status->setUser($user);
            $status->setLesson($lesson);
            $this->em->persist($status);
        }
<<<<<<< Updated upstream
        $status->setLastQuizScore($score);
        if ($score > $status->getBestQuizScore()) {
            $status->setBestQuizScore($score);
        }
        $this->em->flush();

=======
        if (method_exists($status, 'setLastQuizScore')) {
            $status->setLastQuizScore($score);
        }
        if ($attempt->getBestScore() > $status->getBestQuizScore()) {
            $status->setBestQuizScore($attempt->getBestScore());
        }
        $this->em->flush();

        $this->quizPlanningService->markDoneForUserAndQuiz($user, $quiz);

>>>>>>> Stashed changes
        $request->getSession()->set(self::SESSION_QUIZ_RESULT . $lesson->getId(), [
            'score' => $score,
            'correct' => $correct,
            'total' => $total,
            'passed' => $passed,
            'passingScore' => $quiz->getPassingScore(),
<<<<<<< Updated upstream
=======
            'attemptId' => $attempt->getId(),
>>>>>>> Stashed changes
        ]);

        return $this->redirectToRoute('learn_lesson_quiz_result', ['id' => $lesson->getId()]);
    }

<<<<<<< Updated upstream
    /**
     * Affiche le résultat du quiz (données en session).
     */
    #[Route('/result', name: 'result', methods: ['GET'])]
    public function result(Request $request, Lesson $lesson): Response
    {
        $user = $this->getUser();

=======
    #[Route('/result', name: 'result', methods: ['GET'])]
    public function result(Request $request, Lesson $lesson): Response
    {
>>>>>>> Stashed changes
        $key = self::SESSION_QUIZ_RESULT . $lesson->getId();
        $result = $request->getSession()->get($key);
        if (!$result) {
            $this->addFlash('info', 'Aucun résultat de quiz enregistré.');
            return $this->redirectToRoute('learn_practice');
        }
<<<<<<< Updated upstream
        $request->getSession()->remove($key);
=======
        // Ne pas supprimer la clé de session pour permettre le rechargement (feedback IA en cache)
        $attempt = null;
        $wrongExerciseAttempts = [];
        $aiFeedbackByExerciseId = [];
        if (!empty($result['attemptId'])) {
            $attempt = $this->quizAttemptRepository->getWithExerciseAttempts((int) $result['attemptId'], $this->getUser());
            if ($attempt !== null) {
                foreach ($attempt->getExerciseAttempts() as $ea) {
                    if (!$ea->getIsCorrect()) {
                        $wrongExerciseAttempts[] = $ea;
                    }
                }
                $aiFeedbackByExerciseId = $this->aiFeedbackRepository->findByUserAndAttempt($this->getUser(), $attempt);
            }
        }
>>>>>>> Stashed changes

        $course = $lesson->getCourse();
        $language = $course->getPlatformLanguage();

        return $this->render('exercises_quizzes/learn/quiz_result.html.twig', [
            'lesson' => $lesson,
            'course' => $course,
            'language' => $language,
            'result' => $result,
<<<<<<< Updated upstream
        ]);
    }

    private function isEnrolledInLesson(object $user, Lesson $lesson): bool
    {
        $course = $lesson->getCourse();
        $platformLanguage = $course->getPlatformLanguage();
        foreach ($user->getUserLanguages() as $ul) {
            if ($ul->getPlatformLanguage()->getId() === $platformLanguage->getId()) {
                return true;
            }
        }
        return false;
    }

=======
            'attempt' => $attempt,
            'wrongExerciseAttempts' => $wrongExerciseAttempts,
            'aiFeedbackByExerciseId' => $aiFeedbackByExerciseId,
        ]);
    }

    #[Route('/attempt/{attemptId}/second-chance/start', name: 'second_chance_start', methods: ['GET'], requirements: ['attemptId' => '\d+'])]
    public function secondChanceStart(Lesson $lesson, int $attemptId): Response
    {
        $attempt = $this->quizAttemptRepository->getWithExerciseAttempts($attemptId, $this->getUser());
        if (!$attempt) {
            $this->addFlash('danger', 'Tentative introuvable ou accès refusé.');
            return $this->redirectToRoute('learn_practice');
        }
        if ($attempt->getState() !== QuizAttempt::STATE_SECOND_CHANCE_AVAILABLE) {
            $this->addFlash('warning', 'La deuxième chance n\'est plus disponible pour cette tentative.');
            return $this->redirectToRoute('learn_practice');
        }
        $exercise = $this->secondChanceService->startSecondChance($attempt);
        if (!$exercise) {
            $this->addFlash('warning', 'Impossible de démarrer la deuxième chance.');
            return $this->redirectToRoute('learn_practice');
        }
        return $this->redirectToRoute('learn_lesson_quiz_second_chance_play', [
            'id' => $lesson->getId(),
            'attemptId' => $attempt->getId(),
        ]);
    }

    #[Route('/attempt/{attemptId}/second-chance/play', name: 'second_chance_play', methods: ['GET'], requirements: ['attemptId' => '\d+'])]
    public function secondChancePlay(Lesson $lesson, int $attemptId): Response
    {
        $attempt = $this->quizAttemptRepository->getWithExerciseAttempts($attemptId, $this->getUser());
        if (!$attempt) {
            $this->addFlash('danger', 'Tentative introuvable ou accès refusé.');
            return $this->redirectToRoute('learn_practice');
        }
        if ($attempt->getState() !== QuizAttempt::STATE_SECOND_CHANCE_IN_PROGRESS) {
            $this->addFlash('warning', 'Cette session de rattrapage n\'est plus disponible.');
            return $this->redirectToRoute('learn_practice');
        }
        $exercise = $attempt->getSecondChanceExercise();
        if (!$exercise) {
            $this->addFlash('warning', 'Exercice de rattrapage introuvable.');
            return $this->redirectToRoute('learn_practice');
        }
        $course = $lesson->getCourse();
        $language = $course->getPlatformLanguage();
        return $this->render('exercises_quizzes/learn/second_chance_play.html.twig', [
            'lesson' => $lesson,
            'course' => $course,
            'language' => $language,
            'attempt' => $attempt,
            'exercise' => $exercise,
        ]);
    }

    #[Route('/attempt/{attemptId}/second-chance/submit', name: 'second_chance_submit', methods: ['POST'], requirements: ['attemptId' => '\d+'])]
    public function secondChanceSubmit(Request $request, Lesson $lesson, int $attemptId): Response
    {
        $attempt = $this->quizAttemptRepository->getWithExerciseAttempts($attemptId, $this->getUser());
        if (!$attempt) {
            $this->addFlash('danger', 'Tentative introuvable ou accès refusé.');
            return $this->redirectToRoute('learn_practice');
        }
        if ($attempt->getState() !== QuizAttempt::STATE_SECOND_CHANCE_IN_PROGRESS) {
            $this->addFlash('warning', 'Cette session de rattrapage n\'est plus disponible.');
            return $this->redirectToRoute('learn_practice');
        }
        if (!$this->isCsrfTokenValid('learn_quiz_second_chance', $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('learn_lesson_quiz_second_chance_play', ['id' => $lesson->getId(), 'attemptId' => $attemptId]);
        }
        $givenAnswer = $request->request->get('answer', '');
        $this->secondChanceService->submitSecondChance($attempt, (string) $givenAnswer);

        $status = $this->userLessonStatusRepository->findOneBy(['user' => $this->getUser(), 'lesson' => $lesson]);
        if ($status && $attempt->getBestScore() > $status->getBestQuizScore()) {
            $status->setBestQuizScore($attempt->getBestScore());
            $this->em->flush();
        }

        $total = $attempt->getExerciseAttempts()->count();
        $correct = 0;
        foreach ($attempt->getExerciseAttempts() as $ea) {
            if ($ea->getIsCorrect()) {
                $correct++;
            }
        }
        $byExercise = [];
        foreach ($attempt->getExerciseAttempts() as $ea) {
            $ex = $ea->getExercise();
            if ($ex) {
                $byExercise[$ex->getId()] = ($byExercise[$ex->getId()] ?? false) || $ea->getIsCorrect();
            }
        }
        $correctDistinct = count(array_filter($byExercise));
        $totalDistinct = count($byExercise);
        $passed = $attempt->getQuiz() && $attempt->getBestScore() >= $attempt->getQuiz()->getPassingScore();

        $request->getSession()->set(self::SESSION_QUIZ_RESULT . $lesson->getId(), [
            'score' => $attempt->getScore(),
            'correct' => $correctDistinct,
            'total' => $totalDistinct,
            'passed' => $passed,
            'passingScore' => $attempt->getQuiz() ? $attempt->getQuiz()->getPassingScore() : 0,
            'attemptId' => $attempt->getId(),
            'secondChanceUsed' => true,
            'bestScore' => $attempt->getBestScore(),
        ]);

        return $this->redirectToRoute('learn_lesson_quiz_result', ['id' => $lesson->getId()]);
    }
>>>>>>> Stashed changes
}

<?php

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\ExercisesQuizzes\Entity\UserLessonStatus;
use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\ExercisesQuizzes\Repository\UserLessonStatusRepository;
use App\Module\PedagogicalContent\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur front : jouer un quiz lié à une leçon (sans modifier les autres modules).
 */
#[IsGranted('ROLE_USER')]
#[Route('/learn/lesson/{id}/quiz', name: 'learn_lesson_quiz_', requirements: ['id' => '\d+'])]
class UserQuizController extends AbstractController
{
    private const SESSION_QUIZ_RESULT = 'learn_quiz_result_';

    public function __construct(
        private QuizRepository $quizRepository,
        private ExerciceRepository $exerciceRepository,
        private UserLessonStatusRepository $userLessonStatusRepository,
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

    /**
     * Soumet les réponses, calcule le score, met à jour UserLessonStatus, redirige vers la page résultat.
     */
    #[Route('/submit', name: 'submit', methods: ['POST'])]
    public function submit(Request $request, Lesson $lesson): Response
    {
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('learn_quiz_submit', $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('learn_lesson_quiz_play', ['id' => $lesson->getId()]);
        }

        $quiz = $this->quizRepository->findOneByLessonAndEnabled($lesson);
        if (!$quiz) {
            $this->addFlash('warning', 'Aucun quiz disponible.');
            return $this->redirectToRoute('learn_practice');
        }

        $exercices = $this->exerciceRepository->findEnabledByQuiz($quiz);
        if (empty($exercices)) {
            $this->addFlash('warning', 'Ce quiz n\'a pas d\'exercices activés.');
            return $this->redirectToRoute('learn_practice');
        }

        $answers = $request->request->all('answers') ?? [];
        $correct = 0;
        foreach ($exercices as $exercice) {
            $userAnswer = $answers[$exercice->getId()] ?? '';
            $normalizedUser = Exercice::normalizeOption((string) $userAnswer);
            $normalizedCorrect = Exercice::normalizeOption((string) $exercice->getCorrectAnswer());
            if ($normalizedUser !== '' && $normalizedUser === $normalizedCorrect) {
                $correct++;
            }
        }

        $total = count($exercices);
        $score = $total > 0 ? (int) round(($correct / $total) * 100) : 0;
        $passed = $score >= $quiz->getPassingScore();

        $status = $this->userLessonStatusRepository->findOneBy(['user' => $user, 'lesson' => $lesson]);
        if (!$status) {
            $status = new UserLessonStatus();
            $status->setUser($user);
            $status->setLesson($lesson);
            $this->em->persist($status);
        }
        $status->setLastQuizScore($score);
        if ($score > $status->getBestQuizScore()) {
            $status->setBestQuizScore($score);
        }
        $this->em->flush();

        $request->getSession()->set(self::SESSION_QUIZ_RESULT . $lesson->getId(), [
            'score' => $score,
            'correct' => $correct,
            'total' => $total,
            'passed' => $passed,
            'passingScore' => $quiz->getPassingScore(),
        ]);

        return $this->redirectToRoute('learn_lesson_quiz_result', ['id' => $lesson->getId()]);
    }

    /**
     * Affiche le résultat du quiz (données en session).
     */
    #[Route('/result', name: 'result', methods: ['GET'])]
    public function result(Request $request, Lesson $lesson): Response
    {
        $user = $this->getUser();

        $key = self::SESSION_QUIZ_RESULT . $lesson->getId();
        $result = $request->getSession()->get($key);
        if (!$result) {
            $this->addFlash('info', 'Aucun résultat de quiz enregistré.');
            return $this->redirectToRoute('learn_practice');
        }
        $request->getSession()->remove($key);

        $course = $lesson->getCourse();
        $language = $course->getPlatformLanguage();

        return $this->render('exercises_quizzes/learn/quiz_result.html.twig', [
            'lesson' => $lesson,
            'course' => $course,
            'language' => $language,
            'result' => $result,
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

}

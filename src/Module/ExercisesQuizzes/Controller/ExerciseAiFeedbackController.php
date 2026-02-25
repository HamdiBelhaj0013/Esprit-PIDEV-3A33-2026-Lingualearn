<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Entity\ExerciseAttempt;
use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\QuizAttempt;
use App\Module\ExercisesQuizzes\Repository\QuizAttemptRepository;
use App\Module\ExercisesQuizzes\Service\AiFeedbackService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoint pour générer / récupérer le feedback IA sur une réponse incorrecte.
 * POST /learn/quiz/attempt/{attemptId}/exercise/{exerciseId}/ai-feedback
 */
#[IsGranted('ROLE_USER')]
#[Route('/learn/quiz/attempt/{attemptId}/exercise/{exerciseId}', name: 'learn_quiz_ai_feedback_', requirements: ['attemptId' => '\d+', 'exerciseId' => '\d+'])]
class ExerciseAiFeedbackController extends AbstractController
{
    public function __construct(
        private QuizAttemptRepository $quizAttemptRepository,
        private AiFeedbackService $aiFeedbackService,
    ) {}

    #[Route('/ai-feedback', name: 'generate', methods: ['POST'])]
    public function generate(int $attemptId, int $exerciseId): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non autorisé'], Response::HTTP_UNAUTHORIZED);
        }

        $attempt = $this->quizAttemptRepository->getWithExerciseAttempts($attemptId, $user);
        if (!$attempt) {
            return new JsonResponse(['error' => 'Tentative introuvable ou accès refusé.'], Response::HTTP_NOT_FOUND);
        }

        $exerciseAttempt = null;
        foreach ($attempt->getExerciseAttempts() as $ea) {
            $ex = $ea->getExercise();
            if ($ex && $ex->getId() === $exerciseId) {
                $exerciseAttempt = $ea;
                break;
            }
        }

        if (!$exerciseAttempt instanceof ExerciseAttempt) {
            return new JsonResponse(['error' => 'Exercice introuvable dans cette tentative.'], Response::HTTP_NOT_FOUND);
        }

        $exercise = $exerciseAttempt->getExercise();
        if (!$exercise instanceof Exercice) {
            return new JsonResponse(['error' => 'Exercice introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($exerciseAttempt->getIsCorrect()) {
            return new JsonResponse([
                'message' => 'Cet exercice est correct, pas d\'explication d\'erreur nécessaire.',
                'explanation' => null,
                'correction' => null,
                'tip' => null,
                'example' => null,
            ]);
        }

        $feedback = $this->aiFeedbackService->getOrGenerate($user, $attempt, $exercise, $exerciseAttempt);

        if ($feedback === null) {
            return new JsonResponse([
                'message' => 'Explication IA indisponible (service temporairement indisponible ou token non configuré).',
                'explanation' => null,
                'correction' => null,
                'tip' => null,
                'example' => null,
            ], Response::HTTP_OK);
        }

        return new JsonResponse([
            'message' => null,
            'explanation' => $feedback->getAiExplanation(),
            'correction' => $feedback->getAiCorrection(),
            'tip' => $feedback->getAiTip(),
            'example' => $feedback->getAiExample(),
        ]);
    }
}

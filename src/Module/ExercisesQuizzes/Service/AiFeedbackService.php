<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

use App\Module\ExercisesQuizzes\Client\AiExplanationClientInterface;
use App\Module\ExercisesQuizzes\Entity\ExerciseAiFeedback;
use App\Module\ExercisesQuizzes\Entity\ExerciseAttempt;
use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\QuizAttempt;
use App\Module\ExercisesQuizzes\Repository\ExerciseAiFeedbackRepository;
use App\Module\UserManagement\Entity\User;

/**
 * Service métier : récupère ou génère le feedback IA pour une réponse incorrecte.
 * Si API indisponible => retourne null (ne jamais casser la page).
 */
class AiFeedbackService
{
    public function __construct(
        private AiPromptBuilderService $promptBuilder,
        private AiFeedbackParserService $parser,
        private AiExplanationClientInterface $explanationClient,
        private ExerciseAiFeedbackRepository $feedbackRepository,
        private string $hfModel = 'google/flan-t5-large',
    ) {}

    /**
     * @param array{exerciseType?: string, question?: string, studentAnswer?: string, correctAnswer?: string, difficulty?: int, choices?: array} $context
     */
    public function getOrGenerate(User $user, QuizAttempt $attempt, Exercice $exercise, ExerciseAttempt $exerciseAttempt, array $context = []): ?ExerciseAiFeedback
    {
        $exerciseType = $context['exerciseType'] ?? $exercise->getType() ?? 'multiple_choice';
        $question = $context['question'] ?? $exercise->getQuestion() ?? '';
        $studentAnswer = $context['studentAnswer'] ?? $exerciseAttempt->getGivenAnswer() ?? '';
        $correctAnswer = $context['correctAnswer'] ?? $exercise->getCorrectAnswer() ?? '';
        $difficulty = $context['difficulty'] ?? $exercise->getDifficulty();
        $choices = $context['choices'] ?? $exercise->getOptions();

        $prompt = $this->promptBuilder->build(
            $exerciseType,
            $question,
            (string) $studentAnswer,
            (string) $correctAnswer,
            $difficulty,
            $choices,
            'fr'
        );

        $promptHash = hash('sha256', $prompt);

        $existing = $this->feedbackRepository->findOneByUserAttemptExerciseAndHash($user, $attempt, $exercise, $promptHash);
        if ($existing !== null) {
            return $existing;
        }

        $raw = $this->explanationClient->generate($prompt);
        if ($raw === null || $raw === '') {
            return null;
        }

        $parsed = $this->parser->parse($raw);

        $feedback = new ExerciseAiFeedback();
        $feedback->setUser($user);
        $feedback->setQuizAttempt($attempt);
        $feedback->setExercise($exercise);
        $feedback->setIsCorrect($exerciseAttempt->getIsCorrect());
        $feedback->setStudentAnswer((string) $studentAnswer);
        $feedback->setCorrectAnswer((string) $correctAnswer);
        $feedback->setAiExplanation($parsed['explanation']);
        $feedback->setAiCorrection($parsed['correction']);
        $feedback->setAiTip($parsed['tip']);
        $feedback->setAiExample($parsed['example']);
        $feedback->setProvider('huggingface');
        $feedback->setModel($this->hfModel);
        $feedback->setPromptHash($promptHash);
        $feedback->setUpdatedAt(new \DateTimeImmutable());

        $this->feedbackRepository->save($feedback, true);

        return $feedback;
    }
}

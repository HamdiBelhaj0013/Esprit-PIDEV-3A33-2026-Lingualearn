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
use Psr\Log\LoggerInterface;

class AiFeedbackService
{
    public function __construct(
        private AiPromptBuilderService $promptBuilder,
        private AiFeedbackParserService $parser,
        private AiExplanationClientInterface $explanationClient,
        private ExerciseAiFeedbackRepository $feedbackRepository,
        private LoggerInterface $logger,
        private string $hfModel = 'google/flan-t5-large',
    ) {}

    /**
     * @param array{exerciseType?: string, question?: string, studentAnswer?: string, correctAnswer?: string, difficulty?: int, choices?: array} $context
     */
    public function getOrGenerate(
        User $user,
        QuizAttempt $attempt,
        Exercice $exercise,
        ExerciseAttempt $exerciseAttempt,
        array $context = []
    ): ?ExerciseAiFeedback {
        $exerciseType  = $context['exerciseType'] ?? $exercise->getType() ?? 'multiple_choice';
        $question      = $context['question'] ?? $exercise->getQuestion() ?? '';
        $studentAnswer = $context['studentAnswer'] ?? $exerciseAttempt->getGivenAnswer() ?? '';
        $correctAnswer = $context['correctAnswer'] ?? $exercise->getCorrectAnswer() ?? '';
        $difficulty    = $context['difficulty'] ?? $exercise->getDifficulty();
        $choices       = $context['choices'] ?? $exercise->getOptions();

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

        // LOG: prompt construit
        $this->logger->info('AI feedback: prompt built', [
            'userId' => method_exists($user, 'getId') ? $user->getId() : null,
            'attemptId' => method_exists($attempt, 'getId') ? $attempt->getId() : null,
            'exerciseId' => method_exists($exercise, 'getId') ? $exercise->getId() : null,
            'promptHash' => $promptHash,
            'promptLen' => strlen($prompt),
            'model' => $this->hfModel,
        ]);

        $existing = $this->feedbackRepository->findOneByUserAttemptExerciseAndHash($user, $attempt, $exercise, $promptHash);
        if ($existing !== null) {
            $this->logger->info('AI feedback: cache hit', [
                'promptHash' => $promptHash,
            ]);
            return $existing;
        }

        try {
            $raw = $this->explanationClient->generate($prompt);
        } catch (\Throwable $e) {
            // LOG: erreur réelle
            $this->logger->error('AI feedback: explanation client threw exception', [
                'promptHash' => $promptHash,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            return null;
        }

        if ($raw === null || trim($raw) === '') {
            $this->logger->warning('AI feedback: empty response from explanation client', [
                'promptHash' => $promptHash,
            ]);
            return null;
        }

        // LOG: aperçu réponse brute
        $this->logger->info('AI feedback: raw response received', [
            'promptHash' => $promptHash,
            'rawLen' => strlen($raw),
            'rawPreview' => mb_substr($raw, 0, 300),
        ]);

        try {
            $parsed = $this->parser->parse($raw);
        } catch (\Throwable $e) {
            $this->logger->error('AI feedback: parser failed', [
                'promptHash' => $promptHash,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'rawPreview' => mb_substr($raw, 0, 500),
            ]);
            return null;
        }

        // LOG: parsed (optionnel)
        $this->logger->info('AI feedback: parsed result', [
            'promptHash' => $promptHash,
            'hasExplanation' => !empty($parsed['explanation'] ?? null),
            'hasCorrection' => !empty($parsed['correction'] ?? null),
        ]);

        $feedback = new ExerciseAiFeedback();
        $feedback->setUser($user);
        $feedback->setQuizAttempt($attempt);
        $feedback->setExercise($exercise);
        $feedback->setIsCorrect($exerciseAttempt->getIsCorrect());
        $feedback->setStudentAnswer((string) $studentAnswer);
        $feedback->setCorrectAnswer((string) $correctAnswer);
        $feedback->setAiExplanation($parsed['explanation'] ?? null);
        $feedback->setAiCorrection($parsed['correction'] ?? null);
        $feedback->setAiTip($parsed['tip'] ?? null);
        $feedback->setAiExample($parsed['example'] ?? null);
        $feedback->setProvider('huggingface');
        $feedback->setModel($this->hfModel);
        $feedback->setPromptHash($promptHash);
        $feedback->setUpdatedAt(new \DateTimeImmutable());

        $this->feedbackRepository->save($feedback, true);

        $this->logger->info('AI feedback: saved', [
            'promptHash' => $promptHash,
        ]);

        return $feedback;
    }
}
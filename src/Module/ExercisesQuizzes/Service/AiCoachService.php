<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

use App\Module\ExercisesQuizzes\Client\AiCoachClientInterface;
use App\Module\ExercisesQuizzes\Entity\RecommendationSession;
use App\Module\ExercisesQuizzes\Repository\RecommendationSessionRepository;
use Psr\Log\LoggerInterface;

/**
 * Construit le prompt et appelle le client IA (Ollama) pour générer un feedback.
 * L'IA n'a pas accès aux IDs d'exercices : elle explique les faiblesses et propose un plan.
 */
class AiCoachService
{
    public function __construct(
        private AiCoachClientInterface $aiCoachClient,
        private RecommendationSessionRepository $recommendationSessionRepository,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Génère le feedback IA pour la session et le met à jour en base.
     * En cas d'échec (Ollama indisponible, timeout, etc.), la session reste sans aiFeedback et aucune exception n'est propagée.
     */
    public function generateAndAttachFeedback(RecommendationSession $session): void
    {
        $weakCodes = $session->getWeakSkillCodes();
        if ($weakCodes === []) {
            return;
        }

        $prompt = $this->buildPrompt($weakCodes);

        try {
            $feedback = $this->aiCoachClient->generateFeedback([
                'prompt' => $prompt,
                'timeout' => 30,
            ]);
            if ($feedback !== '') {
                $session->setAiFeedback($feedback);
                $this->recommendationSessionRepository->save($session, true);
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('AI coach feedback generation failed', [
                'message' => $e->getMessage(),
                'session_id' => $session->getId(),
            ]);
            // Ne pas propager : l'utilisateur peut continuer sans feedback IA
        }
    }

    private function buildPrompt(array $weakSkillCodes): string
    {
        $skillsList = implode(', ', array_map(fn ($c) => '"' . $c . '"', $weakSkillCodes));
        return <<<PROMPT
Tu es un coach pédagogique. L'apprenant a des points faibles sur les compétences suivantes : {$skillsList}.

En 2 à 4 phrases courtes en français :
1) Explique pourquoi ces compétences sont importantes.
2) Propose un plan de révision simple (sans citer d'exercices précis ni d'IDs).
3) Donne un conseil pratique pour s'entraîner.

Réponds uniquement en texte clair, sans listes numérotées excessives.
PROMPT;
    }
}

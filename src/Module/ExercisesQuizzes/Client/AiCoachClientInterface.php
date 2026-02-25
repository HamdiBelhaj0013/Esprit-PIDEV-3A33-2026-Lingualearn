<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Client;

/**
 * Client pour générer un feedback IA (Hugging Face ou Ollama).
 * Retourne une chaîne vide si le service est indisponible (aucune exception).
 */
interface AiCoachClientInterface
{
    /**
     * Génère un texte de feedback (explications des faiblesses + plan de révision + conseils).
     *
     * @param array<string, mixed> $payload prompt, timeout, etc.
     * @return string Texte du feedback, ou chaîne vide si indisponible
     */
    public function generateFeedback(array $payload): string;
}

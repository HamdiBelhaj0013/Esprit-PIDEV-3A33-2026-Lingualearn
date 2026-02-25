<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Client;

/**
 * Client pour générer une explication IA (ex: Hugging Face Inference API).
 * Retourne null en cas d'indisponibilité (ne jamais casser la page).
 */
interface AiExplanationClientInterface
{
    /**
     * Génère une réponse texte à partir du prompt (ex: modèle de langage).
     *
     * @return string|null Réponse brute, ou null si API indisponible / rate limit / erreur
     */
    public function generate(string $prompt): ?string;
}

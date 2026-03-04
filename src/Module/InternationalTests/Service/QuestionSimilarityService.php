<?php

namespace App\Module\InternationalTests\Service;

use App\Module\InternationalTests\Repository\TestQuestionRepository;

/**
 * QuestionSimilarityService
 * ─────────────────────────────────────────────────────────────
 * Métier Avancé #4 — Détection de doublons de questions
 *
 * Compare le vecteur d'une nouvelle question avec tous les
 * embeddings existants en base via la similarité cosinus.
 *
 * Formule cosinus :
 *   similarity = (A · B) / (||A|| × ||B||)
 *   Résultat entre 0 (aucun rapport) et 1 (identique)
 *
 * Seuil : 0.85 (85%) → doublon détecté
 * ─────────────────────────────────────────────────────────────
 */
class QuestionSimilarityService
{
    // Seuil de similarité à partir duquel on considère un doublon
    public const SIMILARITY_THRESHOLD = 0.85;

    // Nombre maximum de doublons retournés dans l'alerte
    private const MAX_DUPLICATES = 3;

    public function __construct(
        private TestQuestionRepository $questionRepository,
        private GeminiEmbeddingService $embeddingService
    ) {}

    /**
     * Vérifie si une question est un doublon d'une question existante.
     *
     * @param string   $questionText   Texte de la nouvelle question
     * @param int|null $excludeId      ID à exclure (pour l'édition)
     *
     * @return array{
     *   hasDuplicate: bool,
     *   duplicates: array,
     *   embedding: float[]|null,
     *   checkedCount: int
     * }
     */
    public function checkForDuplicates(string $questionText, ?int $excludeId = null): array
    {
        // Générer l'embedding de la nouvelle question
        $newEmbedding = $this->embeddingService->generateEmbedding($questionText);

        if (!$newEmbedding) {
            return [
                'hasDuplicate' => false,
                'duplicates'   => [],
                'embedding'    => null,
                'checkedCount' => 0,
                'error'        => 'Could not generate embedding. API may be unavailable.',
            ];
        }

        // Récupérer toutes les questions qui ont un embedding en base
        $existingQuestions = $this->questionRepository->findAllWithEmbedding($excludeId);

        $duplicates   = [];
        $checkedCount = 0;

        foreach ($existingQuestions as $question) {
            $existingEmbedding = $question->getEmbedding();

            if (!$existingEmbedding || !is_array($existingEmbedding)) {
                continue;
            }

            $checkedCount++;
            $similarity = $this->cosineSimilarity($newEmbedding, $existingEmbedding);

            if ($similarity >= self::SIMILARITY_THRESHOLD) {
                $duplicates[] = [
                    'id'             => $question->getId(),
                    'questionText'   => $question->getQuestionText(),
                    'questionType'   => $question->getQuestionType(),
                    'mockTestTitle'  => $question->getMockTest()?->getTitle() ?? 'N/A',
                    'similarity'     => round($similarity * 100, 1), // en pourcentage
                    'similarityRaw'  => $similarity,
                    'sectionCategory'=> $question->getSectionCategory(),
                ];
            }
        }

        // Trier par similarité décroissante
        usort($duplicates, fn($a, $b) => $b['similarityRaw'] <=> $a['similarityRaw']);

        // Limiter le nombre de doublons retournés
        $duplicates = array_slice($duplicates, 0, self::MAX_DUPLICATES);

        return [
            'hasDuplicate' => !empty($duplicates),
            'duplicates'   => $duplicates,
            'embedding'    => $newEmbedding,
            'checkedCount' => $checkedCount,
        ];
    }

    /**
     * Calcule la similarité cosinus entre deux vecteurs.
     *
     * @param float[] $vectorA
     * @param float[] $vectorB
     * @return float Valeur entre 0.0 et 1.0
     */
    public function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $length = min(count($vectorA), count($vectorB));

        if ($length === 0) {
            return 0.0;
        }

        $dotProduct  = 0.0;
        $normA       = 0.0;
        $normB       = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $a = (float) $vectorA[$i];
            $b = (float) $vectorB[$i];

            $dotProduct += $a * $b;
            $normA      += $a * $a;
            $normB      += $b * $b;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator === 0.0) {
            return 0.0;
        }

        // Clamp entre 0 et 1 pour éviter les erreurs d'arrondi flottant
        return min(1.0, max(0.0, $dotProduct / $denominator));
    }

    /**
     * Retourne le label de similarité pour l'affichage UI.
     */
    public static function getSimilarityLabel(float $similarity): string
    {
        return match(true) {
            $similarity >= 0.95 => 'Quasi-identique',
            $similarity >= 0.90 => 'Très similaire',
            $similarity >= 0.85 => 'Similaire',
            default             => 'Différente',
        };
    }

    /**
     * Retourne la couleur Bootstrap selon le niveau de similarité.
     */
    public static function getSimilarityColor(float $similarity): string
    {
        return match(true) {
            $similarity >= 0.95 => 'danger',
            $similarity >= 0.90 => 'warning',
            $similarity >= 0.85 => 'orange',
            default             => 'success',
        };
    }
}

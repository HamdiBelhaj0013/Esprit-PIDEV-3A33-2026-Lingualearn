<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

/**
 * Parse la réponse brute de l'IA en champs explanation / correction / tip / example.
 * Fallback: tout dans explanation si JSON invalide.
 */
class AiFeedbackParserService
{
    /**
     * @return array{explanation: string, correction: string, tip: string, example: string}
     */
    public function parse(string $rawResponse): array
    {
        $out = [
            'explanation' => '',
            'correction' => '',
            'tip' => '',
            'example' => '',
        ];

        $trimmed = trim($rawResponse);
        $trimmed = $this->extractJsonFromText($trimmed);

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            $out['explanation'] = trim($rawResponse) ?: 'Explication non disponible.';
            return $out;
        }

        $out['explanation'] = isset($decoded['explanation']) ? trim((string) $decoded['explanation']) : '';
        $out['correction'] = isset($decoded['correction']) ? trim((string) $decoded['correction']) : '';
        $out['tip'] = isset($decoded['tip']) ? trim((string) $decoded['tip']) : '';
        $out['example'] = isset($decoded['example']) ? trim((string) $decoded['example']) : '';

        if ($out['explanation'] === '' && ($out['correction'] !== '' || $out['tip'] !== '' || $out['example'] !== '')) {
            $out['explanation'] = trim($rawResponse);
        }
        if ($out['explanation'] === '') {
            $out['explanation'] = trim($rawResponse) ?: 'Explication non disponible.';
        }

        return $out;
    }

    private function extractJsonFromText(string $text): string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return $text;
        }
        $depth = 0;
        $end = -1;
        for ($i = $start; $i < strlen($text); $i++) {
            $c = $text[$i];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        if ($end >= $start) {
            return substr($text, $start, $end - $start + 1);
        }
        return $text;
    }
}

<?php

namespace App\Module\Support\Service;

class BadWordService
{
    // Liste des mots interdits
    private array $badWords = [
        // Insultes françaises
        'merde', 'putain', 'connard', 'connasse', 'salope', 'enculé',
        'fils de pute', 'va te faire', 'nique', 'niquer', 'baise',
        'couille', 'foutre', 'bite', 'con', 'conne', 'pute', 'pd',
        'tapette', 'grosse vache', 'abruti', 'idiot', 'imbécile',
        'crétin', 'débile', 'mongol', 'attardé', 'bâtard','tester bad word',

        // Insultes anglaises
        'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'dick',
        'pussy', 'cunt', 'motherfucker', 'faggot', 'whore', 'slut',
        'damn', 'crap', 'stupid', 'idiot', 'moron',

        // Menaces
        'je vais te tuer', 'je vais te trouver', 'tu vas mourir',
        'mort aux', 'tuer',
    ];

    /**
     * Vérifie si le texte contient un bad word (mot entier uniquement)
     * \b = word boundary → "technique" ne déclenchera PAS "nique"
     */
    public function containsBadWord(string $text): bool
    {
        $text = strtolower($this->removeAccents($text));

        foreach ($this->badWords as $word) {
            $pattern = '/\b' . preg_quote(strtolower($word), '/') . '\b/u';
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne la liste des mots interdits détectés dans le texte
     */
    public function getDetectedWords(string $text): array
    {
        $text     = strtolower($this->removeAccents($text));
        $detected = [];

        foreach ($this->badWords as $word) {
            $pattern = '/\b' . preg_quote(strtolower($word), '/') . '\b/u';
            if (preg_match($pattern, $text)) {
                $detected[] = $word;
            }
        }

        return array_unique($detected);
    }

    /**
     * Retourne le texte avec les bad words masqués (ex: m***e)
     */
    public function maskBadWords(string $text): string
    {
        $lower = strtolower($this->removeAccents($text));

        foreach ($this->badWords as $word) {
            $pattern = '/\b' . preg_quote(strtolower($word), '/') . '\b/ui';
            $mask    = substr($word, 0, 1) . str_repeat('*', strlen($word) - 1);
            $text    = preg_replace($pattern, $mask, $text);
        }

        return $text;
    }

    /**
     * Supprime les accents pour normaliser le texte avant analyse
     * Ex: "débiLE" → "debile"
     */
    private function removeAccents(string $text): string
    {
        $accents = [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','î'=>'i','ï'=>'i','í'=>'i',
            'ò'=>'o','ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ç'=>'c','ñ'=>'n',
            'À'=>'A','Â'=>'A','Ä'=>'A',
            'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'Î'=>'I','Ï'=>'I',
            'Ô'=>'O','Ö'=>'O',
            'Û'=>'U','Ü'=>'U',
            'Ç'=>'C',
        ];

        return strtr($text, $accents);
    }

    /**
     * Retourne la liste complète des bad words (pour admin)
     */
    public function getBadWords(): array
    {
        return $this->badWords;
    }
}
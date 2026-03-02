<?php

namespace App\Service;

class BadWordChecker
{
    private array $badWords = [
        // Français
        'merde', 'putain', 'connard', 'connasse', 'salope', 'enculé',
        'encule', 'fdp', 'tg', 'ntm', 'pd', 'batard', 'bâtard',
        'idiot', 'imbécile', 'imbecile', 'abruti', 'crétin', 'cretin',
        'con', 'conne', 'nique', 'niquer', 'pute', 'bordel', 'chiotte',
        'chiottes', 'couille', 'couilles', 'bite', 'bites', 'cul',
        'fesse', 'fesses', 'zob', 'branleur', 'branlette',
        'ta gueule', 'va te faire', 'fils de pute',

        // Anglais
        'fuck', 'fucking', 'fucker', 'shit', 'bitch', 'bastard',
        'asshole', 'ass', 'damn', 'crap', 'dick', 'cock', 'pussy',
        'whore', 'slut', 'nigger', 'faggot', 'retard', 'idiot',
        'moron', 'loser', 'stupid', 'dumbass', 'bullshit',
    ];

    /**
     * Vérifie si le texte contient des bad words
     */
    public function containsBadWords(string $text): bool
    {
        $textLower = mb_strtolower($text);

        foreach ($this->badWords as $word) {
            // On cherche le mot entier (avec limites de mot)
            $pattern = '/\b' . preg_quote(mb_strtolower($word), '/') . '\b/iu';
            if (preg_match($pattern, $textLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne la liste des bad words trouvés dans le texte
     */
    public function getBadWordsFound(string $text): array
    {
        $found = [];
        $textLower = mb_strtolower($text);

        foreach ($this->badWords as $word) {
            $pattern = '/\b' . preg_quote(mb_strtolower($word), '/') . '\b/iu';
            if (preg_match($pattern, $textLower)) {
                $found[] = $word;
            }
        }

        return $found;
    }
}
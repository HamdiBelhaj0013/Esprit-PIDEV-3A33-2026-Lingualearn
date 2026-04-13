<?php

namespace App\Service;

use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationService
{
    public function translatePublication(string $titre, string $contenu, string $targetLang): array
    {
        try {
            $translator = new GoogleTranslate($targetLang);
            $translator->setOptions([
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            return [
                'titre' => $translator->translate($titre) ?? $titre,
                'contenu' => $translator->translate($contenu) ?? $contenu,
            ];
        } catch (\Exception $e) {
            // Fallback silencieux — retourne le texte original si Google bloque
            return [
                'titre' => $titre,
                'contenu' => $contenu,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function translateCommentaire(string $contenu, string $targetLang): string
    {
        try {
            $translator = new GoogleTranslate($targetLang);
            $translator->setOptions([
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            return $translator->translate($contenu) ?? $contenu;
        } catch (\Exception $e) {
            return $contenu; // Retourne original si erreur
        }
    }

    public function getSupportedLanguages(): array
    {
        return [
            'en' => '🇬🇧 Anglais',
            'ar' => '🇸🇦 Arabe',
            'es' => '🇪🇸 Espagnol',
            'de' => '🇩🇪 Allemand',
            'fr' => '🇫🇷 Français',
            'it' => '🇮🇹 Italien',
        ];
    }
}

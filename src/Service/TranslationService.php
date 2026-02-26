<?php

namespace App\Service;

use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationService
{
    public function translatePublication(string $titre, string $contenu, string $targetLang): array
    {
        try {
            $translator = new GoogleTranslate($targetLang);
            
            return [
                'titre' => $translator->translate($titre) ?? $titre,
                'contenu' => $translator->translate($contenu) ?? $contenu,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Erreur de traduction publication: ' . $e->getMessage());
        }
    }

    public function translateCommentaire(string $contenu, string $targetLang): string
    {
        try {
            $translator = new GoogleTranslate($targetLang);
            return $translator->translate($contenu) ?? $contenu;
        } catch (\Exception $e) {
            throw new \Exception('Erreur de traduction commentaire: ' . $e->getMessage());
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
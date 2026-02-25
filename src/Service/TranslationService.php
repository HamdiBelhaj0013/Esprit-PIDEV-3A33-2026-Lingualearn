<?php

namespace App\Service;

use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationService
{
    private GoogleTranslate $translator;

    public function __construct()
    {
        $this->translator = new GoogleTranslate();
    }

    public function translatePublication(string $titre, string $contenu, string $targetLang): array
    {
        $this->translator->setTarget($targetLang);

        return [
            'titre'  => $this->translator->translate($titre) ?? $titre,
            'contenu' => $this->translator->translate($contenu) ?? $contenu,
        ];
    }

    public function translateCommentaire(string $contenu, string $targetLang): string
    {
        $this->translator->setTarget($targetLang);
        return $this->translator->translate($contenu) ?? $contenu;
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
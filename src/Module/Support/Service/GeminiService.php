<?php

namespace App\Module\Support\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    private string $apiKey = 'AIzaSyDEhPLXaF4OwjTj6zJnXN97mEOw7sC9xFc';
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function correctText(string $text): string
    {
        $prompt = "Tu es un correcteur orthographique. "
                . "Corrige UNIQUEMENT les fautes d'orthographe et de grammaire. "
                . "Réponds SEULEMENT avec le texte corrigé, rien d'autre. "
                . "Texte: " . $text;

        return trim($this->callGemini($prompt));
    }

    public function translateText(string $text, string $targetLang = 'en'): string
    {
        $langNames = [
            'en' => 'English',
            'ar' => 'Arabic',
            'fr' => 'French',
            'es' => 'Spanish',
            'de' => 'German',
        ];

        $langName = $langNames[$targetLang] ?? 'English';

        $prompt = "Translate the following text to {$langName}. "
                . "Output ONLY the translated text, nothing else. "
                . "Do not add any explanation, quotes, or extra words. "
                . "Text to translate: " . $text;

        return trim($this->callGemini($prompt));
    }

    private function callGemini(string $prompt): string
    {
        // Pas de try/catch ici — les erreurs remontent au Controller qui retourne un JSON d'erreur
        $response = $this->httpClient->request('POST', $this->apiUrl . '?key=' . $this->apiKey, [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => [
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => $prompt]]
                    ]
                ],
                'generationConfig' => [
                    'temperature'     => 0.1,
                    'maxOutputTokens' => 2048,
                ],
            ],
        ]);

        $data   = $response->toArray();
        $result = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return trim($result, '"\'');
    }
}
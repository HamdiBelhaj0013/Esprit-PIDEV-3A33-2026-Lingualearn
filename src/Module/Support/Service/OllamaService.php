<?php

namespace App\Module\Support\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaService
{
    private string $ollamaUrl = 'http://localhost:11434/api/generate';
    private string $model     = 'phi3'; // change en llama3.2 si tu l'as installé

    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function analyzeEmotion(string $text): array
    {
        $prompt = "Analyze the emotion in this text. "
                . "Respond ONLY with a JSON object: "
                . "{\"emotion\": \"happy|angry|frustrated|sad|neutral\", \"confidence\": \"high|medium|low\"} "
                . "Text: " . $text;

        try {
            $response = $this->httpClient->request('POST', $this->ollamaUrl, [
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 30,
                'json'    => [
                    'model'  => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                ],
            ]);

            $data   = $response->toArray();
            $result = json_decode($data['response'] ?? '{}', true);
            return $this->buildResult($result['emotion'] ?? 'neutral', $result['confidence'] ?? 'medium', 'ollama');

        } catch (\Throwable $e) {
            return $this->fallbackAnalysis($text);
        }
    }

    private function buildResult(string $emotion, string $confidence, string $source): array
    {
        $validEmotions = ['happy', 'angry', 'frustrated', 'sad', 'neutral'];
        if (!in_array($emotion, $validEmotions)) $emotion = 'neutral';

        $map = [
            'happy'      => ['label' => 'Satisfait',  'emoji' => '😊', 'color' => 'green',  'priority' => 1],
            'angry'      => ['label' => 'En colère',  'emoji' => '😡', 'color' => 'red',    'priority' => 5],
            'frustrated' => ['label' => 'Frustré',    'emoji' => '😤', 'color' => 'orange', 'priority' => 4],
            'sad'        => ['label' => 'Déçu',       'emoji' => '😔', 'color' => 'blue',   'priority' => 3],
            'neutral'    => ['label' => 'Neutre',     'emoji' => '😐', 'color' => 'gray',   'priority' => 2],
        ];

        return array_merge($map[$emotion], [
            'emotion'    => $emotion,
            'confidence' => $confidence,
            'source'     => $source,
        ]);
    }

    private function fallbackAnalysis(string $text): array
    {
        $text = mb_strtolower($text);
        $scores = ['angry' => 0, 'frustrated' => 0, 'sad' => 0, 'happy' => 0, 'neutral' => 1];

        $keywords = [
            'angry'      => ['colère', 'inacceptable', 'honte', 'nul', 'horrible', 'furieux', 'énervé'],
            'frustrated' => ['frustrant', 'impossible', 'encore', 'toujours', 'problème', 'bloqué'],
            'sad'        => ['déçu', 'triste', 'dommage', 'malheureusement', 'décevant'],
            'happy'      => ['merci', 'super', 'excellent', 'parfait', 'satisfait', 'génial', 'top'],
        ];

        foreach ($keywords as $emotion => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) $scores[$emotion] += 2;
            }
        }

        $dominant = array_keys($scores, max($scores))[0];
        return $this->buildResult($dominant, 'low', 'fallback');
    }

    public function isAvailable(): bool
    {
        try {
            $r = $this->httpClient->request('GET', 'http://localhost:11434/api/tags', ['timeout' => 3]);
            return $r->getStatusCode() === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

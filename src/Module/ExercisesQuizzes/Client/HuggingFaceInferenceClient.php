<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HuggingFaceInferenceClient implements AiExplanationClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $model,
        private string $apiToken,
        private int $timeout = 30,
        private ?LoggerInterface $logger = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function generate(string $prompt): ?string
    {
        if (trim($prompt) === '') {
            return null;
        }

        if ($this->apiToken === '' || $this->apiToken === '0') {
            $this->logger?->warning('Hugging Face: no API token configured');
            return null;
        }

        // ✅ Router endpoint (NOUVEAU STANDARD HF)
        $url = $this->baseUrl . '/v1/chat/completions';

        $options = [
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ],
                ],
                'max_tokens' => 400,
                'temperature' => 0.4,
                'stream' => false,
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $url, $options);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status >= 400) {
                $this->logger?->warning('Hugging Face: request failed', [
                    'status' => $status,
                    'body' => $data,
                ]);
                return null;
            }

            // ✅ Format Chat Completions
            if (isset($data['choices'][0]['message']['content'])) {
                return trim((string) $data['choices'][0]['message']['content']);
            }

            if (isset($data['error'])) {
                $this->logger?->warning('Hugging Face: API error in body', [
                    'error' => is_string($data['error']) ? substr($data['error'], 0, 300) : $data['error'],
                ]);
                return null;
            }

            $this->logger?->warning('Hugging Face: unexpected response format', [
                'keys' => array_keys((array)$data),
            ]);

            return null;

        } catch (\Throwable $e) {
            $this->logger?->error('Hugging Face: exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }
}
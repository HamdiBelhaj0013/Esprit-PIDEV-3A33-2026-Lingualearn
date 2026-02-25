<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client coach IA via Hugging Face (router) — format Chat Completions.
 * Retourne une chaîne vide en cas d'indisponibilité (pas d'exception).
 * @see https://router.huggingface.co
 */
class HuggingFaceAiCoachClient implements AiCoachClientInterface
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

    public function generateFeedback(array $payload): string
    {
        $prompt = $payload['prompt'] ?? '';
        if ($prompt === '') {
            return '';
        }

        if ($this->apiToken === '' || $this->apiToken === '0') {
            $this->logger?->debug('Hugging Face Coach: no API token');
            return '';
        }

        $url = $this->baseUrl . '/v1/chat/completions';
        $timeout = (int) ($payload['timeout'] ?? $this->timeout);

        $options = [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'stream' => false,
                'max_tokens' => 400,
                'temperature' => 0.4,
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $url, $options);
            $status = $response->getStatusCode();

            if ($status >= 400) {
                $this->logger?->warning('Hugging Face Coach: request failed', ['status' => $status]);
                return '';
            }

            $data = $response->toArray();
            if (isset($data['error']) && \is_string($data['error'])) {
                $this->logger?->warning('Hugging Face Coach: API error in body', ['error' => substr($data['error'], 0, 200)]);
                return '';
            }

            if (isset($data['choices'][0]['message']['content'])) {
                return trim((string) $data['choices'][0]['message']['content']);
            }

            return '';
        } catch (\Throwable $e) {
            $this->logger?->warning('Hugging Face Coach: error', ['message' => $e->getMessage()]);
            return '';
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client pour l'API Hugging Face (router) — format Chat Completions (OpenAI-compatible).
 * Ne jamais logger le token.
 * @see https://router.huggingface.co
 */
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
        if ($this->apiToken === '' || $this->apiToken === '0') {
            $this->logger?->debug('Hugging Face: no API token configured');
            return null;
        }

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
                    ['role' => 'user', 'content' => $prompt],
                ],
                'stream' => false,
                'max_tokens' => 350,
                'temperature' => 0.4,
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $url, $options);
            $status = $response->getStatusCode();

            if ($status === 401 || $status === 403) {
                $this->logger?->warning('Hugging Face: invalid or missing token', ['status' => $status]);
                return null;
            }

            if ($status === 400) {
                try {
                    $body = $response->toArray(false);
                    $this->logger?->warning('Hugging Face: bad request (400)', [
                        'error' => $body['error'] ?? $body['message'] ?? json_encode($body),
                    ]);
                } catch (\Throwable) {
                    $this->logger?->warning('Hugging Face: bad request (400)', ['body' => $response->getContent(false)]);
                }
                return null;
            }

            if ($status === 429) {
                $this->logger?->warning('Hugging Face: rate limit exceeded');
                return null;
            }

            if ($status === 503) {
                $this->logger?->info('Hugging Face: model loading (503), retry once');
                sleep(2);
                $response = $this->httpClient->request('POST', $url, $options);
                $status = $response->getStatusCode();
                if ($status >= 400) {
                    $this->logger?->warning('Hugging Face: retry failed', ['status' => $status]);
                    return null;
                }
            }

            if ($status >= 400) {
                $this->logger?->warning('Hugging Face: request failed', ['status' => $status]);
                return null;
            }

            $data = $response->toArray();

            // Réponse 200 mais erreur métier (ex. modèle en chargement)
            if (isset($data['error']) && \is_string($data['error'])) {
                $this->logger?->warning('Hugging Face: API error in body', ['error' => substr($data['error'], 0, 200)]);
                return null;
            }

            if (isset($data['choices'][0]['message']['content'])) {
                return trim((string) $data['choices'][0]['message']['content']);
            }

            $this->logger?->warning('Hugging Face: unexpected response shape', [
                'keys' => array_keys($data),
                'has_choices' => isset($data['choices']),
            ]);
            return null;
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportException $e) {
            $this->logger?->warning('Hugging Face: transport error', ['message' => $e->getMessage()]);
            return null;
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            $this->logger?->warning('Hugging Face: client error', ['status' => $status]);
            return null;
        } catch (\Symfony\Contracts\HttpClient\Exception\ServerException $e) {
            $this->logger?->warning('Hugging Face: server error', ['status' => $e->getResponse()->getStatusCode()]);
            return null;
        } catch (\Throwable $e) {
            $this->logger?->error('Hugging Face: unexpected error', ['message' => $e->getMessage()]);
            return null;
        }
    }
}

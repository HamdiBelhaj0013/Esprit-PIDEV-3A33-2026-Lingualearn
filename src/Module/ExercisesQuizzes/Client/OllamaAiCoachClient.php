<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Client;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation du client IA via l'API locale Ollama (http://localhost:11434/api/generate).
 * Aucune clé API, zéro coût. Modèle configurable (ex: mistral, llama2).
 */
class OllamaAiCoachClient implements AiCoachClientInterface
{
    private const DEFAULT_TIMEOUT = 30;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl = 'http://localhost:11434',
        private string $model = 'mistral',
        private ?LoggerInterface $logger = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function generateFeedback(array $payload): string
    {
        $model = $payload['model'] ?? $this->model;
        $prompt = $payload['prompt'] ?? '';
        if ($prompt === '') {
            throw new \InvalidArgumentException('Payload must contain a non-empty "prompt" key.');
        }

        $body = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        $url = $this->baseUrl . '/api/generate';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $body,
                'timeout' => $payload['timeout'] ?? self::DEFAULT_TIMEOUT,
            ]);
            $data = $response->toArray();
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportException $e) {
            $this->logger?->warning('Ollama request failed (transport)', [
                'message' => $e->getMessage(),
                'url' => $this->baseUrl,
            ]);
            throw new \RuntimeException('Service d\'IA indisponible (Ollama non démarré ou inaccessible). Vous pouvez continuer sans feedback IA.', 0, $e);
        } catch (\Symfony\Contracts\HttpClient\Exception\ServerException $e) {
            $this->logger?->warning('Ollama server error', ['status' => $e->getResponse()->getStatusCode()]);
            throw new \RuntimeException('Service d\'IA temporairement indisponible. Vous pouvez continuer sans feedback IA.', 0, $e);
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            $this->logger?->warning('Ollama client error', ['status' => $status]);
            if ($status === 404) {
                throw new \RuntimeException('Modèle IA non trouvé. Vérifiez qu\'Ollama a bien le modèle installé (ex: ollama pull mistral).', 0, $e);
            }
            throw new \RuntimeException('Requête vers le service d\'IA invalide. Vous pouvez continuer sans feedback IA.', 0, $e);
        } catch (\Exception $e) {
            $this->logger?->error('Ollama unexpected error', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Erreur lors de l\'appel au service d\'IA. Vous pouvez continuer sans feedback IA.', 0, $e);
        }

        return trim((string) ($data['response'] ?? ''));
    }
}

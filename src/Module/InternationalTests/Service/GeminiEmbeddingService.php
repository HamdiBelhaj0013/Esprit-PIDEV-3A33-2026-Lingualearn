<?php

namespace App\Module\InternationalTests\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * GeminiEmbeddingService
 * ─────────────────────────────────────────────────────────────
 * Génère des embeddings vectoriels via l'API Gemini
 * (text-embedding-004) — quota SÉPARÉ des autres modèles Gemini.
 */
class GeminiEmbeddingService
{
    private const API_URL        = 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent';
    private const TIMEOUT_SECONDS = 10; // ← NOUVEAU : timeout strict pour éviter le freeze PHP

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        private string              $geminiApiKey
    ) {}

    /**
     * Génère l'embedding d'un texte.
     * Retourne un tableau de 768 floats ou null en cas d'erreur.
     *
     * @param string $text Le texte à vectoriser
     * @return float[]|null
     */
    public function generateEmbedding(string $text): ?array
    {
        $text = $this->cleanText($text);

        if (empty($text)) {
            $this->logger->warning('GeminiEmbeddingService: empty text provided');
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'query'   => ['key' => $this->geminiApiKey],
                'timeout' => self::TIMEOUT_SECONDS, // ← NOUVEAU : coupe la requête après 10s
                'json'    => [
                    'model'    => 'models/text-embedding-004',
                    'content'  => [
                        'parts' => [['text' => $text]]
                    ],
                    'taskType' => 'SEMANTIC_SIMILARITY',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                $this->logger->warning('GeminiEmbeddingService: rate limit hit (429)');
                return null;
            }

            if ($statusCode !== 200) {
                $this->logger->error('GeminiEmbeddingService: unexpected HTTP status', ['status' => $statusCode]);
                return null;
            }

            $data      = $response->toArray();
            $embedding = $data['embedding']['values'] ?? null;

            if (!$embedding || !is_array($embedding)) {
                $this->logger->error('GeminiEmbeddingService: invalid response structure', ['data' => $data]);
                return null;
            }

            $this->logger->info('GeminiEmbeddingService: embedding generated', [
                'dimensions' => count($embedding),
                'textLength' => strlen($text),
            ]);

            return array_map('floatval', $embedding);

        } catch (\Exception $e) {
            // Inclut les TimeoutException — on log et on retourne null proprement
            $this->logger->error('GeminiEmbeddingService: API call failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Nettoie le texte avant vectorisation.
     */
    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return mb_substr($text, 0, 2000);
    }
}

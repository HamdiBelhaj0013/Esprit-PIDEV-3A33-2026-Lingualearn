<?php

namespace App\Module\InternationalTests\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * DeepgramTranscriptionService — Transcription vocale via Deepgram
 * Remplace AssemblyAiService — API gratuite, rapide, multilingue
 */
class DeepgramTranscriptionService
{
    private const API_URL        = 'https://api.deepgram.com/v1/listen';
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        private string              $deepgramApiKey
    ) {}

    /**
     * Transcrit un audio base64 via Deepgram
     * Compatible avec l'interface attendue par MockTestFrontController
     */
    public function transcribe(string $audioData, string $languageName = 'english'): array
    {
        try {
            $audioBinary = base64_decode($audioData);

            if (!$audioBinary || strlen($audioBinary) < 100) {
                throw new \Exception('Audio data too short or invalid');
            }

            $this->logger->info('Deepgram: starting transcription', [
                'audioSize' => strlen($audioBinary),
                'language'  => $languageName,
            ]);

            // Mapping langue → code Deepgram
            $langCode = $this->mapLanguageCode($languageName);

            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Token ' . $this->deepgramApiKey,
                    'Content-Type'  => 'audio/webm',
                ],
                'query' => [
                    'language'   => $langCode,
                    'punctuate'  => 'true',
                    'model'      => 'nova-2',
                    'smart_format' => 'true',
                ],
                'body'    => $audioBinary,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $statusCode = $response->getStatusCode();
            $data       = $response->toArray(false);

            $this->logger->info('Deepgram: response received', ['status' => $statusCode]);

            if ($statusCode !== 200) {
                throw new \Exception('Deepgram API error: HTTP ' . $statusCode . ' — ' . json_encode($data));
            }

            // Extraire le texte transcrit
            $transcript = $data['results']['channels'][0]['alternatives'][0]['transcript'] ?? '';
            $confidence = $data['results']['channels'][0]['alternatives'][0]['confidence'] ?? null;
            $words      = $data['results']['channels'][0]['alternatives'][0]['words']      ?? [];
            $duration   = $data['metadata']['duration'] ?? null;

            $this->logger->info('Deepgram: transcription success', [
                'text'       => substr($transcript, 0, 100),
                'confidence' => $confidence,
                'duration'   => $duration,
            ]);

            return [
                'success'    => true,
                'text'       => $transcript,
                'confidence' => $confidence,
                'words'      => $words,
                'duration'   => $duration,
                'error'      => null,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Deepgram transcription failed', ['error' => $e->getMessage()]);
            return [
                'success'    => false,
                'text'       => '',
                'confidence' => null,
                'words'      => [],
                'duration'   => null,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Calcule les métriques de fluidité à partir des mots transcrits
     * Compatible avec l'interface AssemblyAiService
     */
    public function computeFluencyMetrics(array $words, float $duration): array
    {
        if (empty($words) || $duration <= 0) {
            return ['wordsPerMinute' => 0, 'avgConfidence' => 0, 'pauseCount' => 0, 'fluencyScore' => 0];
        }

        $wpm         = round((count($words) / $duration) * 60);
        $confidences = array_column($words, 'confidence');
        $avgConf     = count($confidences) > 0
            ? round(array_sum($confidences) / count($confidences), 3)
            : 0;

        // Deepgram utilise 'start' et 'end' en secondes (pas millisecondes)
        $pauseCount = 0;
        for ($i = 1; $i < count($words); $i++) {
            $gap = ($words[$i]['start'] ?? 0) - ($words[$i - 1]['end'] ?? 0);
            if ($gap > 1.0) { // pause > 1 seconde
                $pauseCount++;
            }
        }

        $confScore    = $avgConf * 40;
        $rateScore    = ($wpm >= 80 && $wpm <= 180) ? 40 : ($wpm >= 50 ? 25 : 10);
        $fluencyScore = min(100, max(0, round($confScore + $rateScore + max(0, 20 - $pauseCount * 3))));

        return [
            'wordsPerMinute' => $wpm,
            'avgConfidence'  => $avgConf,
            'pauseCount'     => $pauseCount,
            'fluencyScore'   => $fluencyScore,
        ];
    }

    /**
     * Mappe le nom de langue vers le code Deepgram
     */
    private function mapLanguageCode(string $languageName): string
    {
        $map = [
            'english'  => 'en',
            'french'   => 'fr',
            'français' => 'fr',
            'spanish'  => 'es',
            'german'   => 'de',
            'italian'  => 'it',
            'arabic'   => 'ar',
            'portuguese' => 'pt',
            'dutch'    => 'nl',
            'hindi'    => 'hi',
            'japanese' => 'ja',
            'chinese'  => 'zh',
        ];

        return $map[strtolower(trim($languageName))] ?? 'en';
    }
}

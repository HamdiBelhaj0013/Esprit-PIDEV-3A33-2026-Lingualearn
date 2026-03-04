<?php

namespace App\Module\InternationalTests\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * AssemblyAiService — Transcription vocale via AssemblyAI
 */
class AssemblyAiService
{
    private const BASE_URL        = 'https://api.assemblyai.com/v2';
    private const POLL_INTERVAL   = 2;
    private const MAX_POLLS       = 30;
    private const TIMEOUT_SECONDS = 15; // ← NOUVEAU : timeout par requête HTTP

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        private string              $assemblyAiApiKey
    ) {}

    public function transcribe(string $audioData, string $languageName = 'english'): array
    {
        try {
            $audioBinary = base64_decode($audioData);

            // ← CORRIGÉ : seuil abaissé à 100 bytes (évite de bloquer les audios courts)
            if (!$audioBinary || strlen($audioBinary) < 100) {
                throw new \Exception('Audio data too short or invalid (min 100 bytes required)');
            }

            $this->logger->info('AssemblyAI: starting transcription', [
                'audioSize' => strlen($audioBinary),
                'language'  => $languageName,
            ]);

            $uploadUrl    = $this->uploadAudio($audioBinary);
            $transcriptId = $this->requestTranscript($uploadUrl);
            $result       = $this->pollTranscript($transcriptId);

            return [
                'success'    => true,
                'text'       => $result['text']           ?? '',
                'confidence' => $result['confidence']     ?? null,
                'words'      => $result['words']          ?? [],
                'duration'   => $result['audio_duration'] ?? null,
                'error'      => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('AssemblyAI transcription failed', ['error' => $e->getMessage()]);
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

    private function uploadAudio(string $audioBinary): string
    {
        $response = $this->httpClient->request('POST', self::BASE_URL . '/upload', [
            'headers' => [
                'authorization' => $this->assemblyAiApiKey,
                'content-type'  => 'application/octet-stream',
            ],
            'body'    => $audioBinary,
            'timeout' => self::TIMEOUT_SECONDS,
        ]);

        $statusCode = $response->getStatusCode();
        $rawBody    = $response->getContent(false);

        $this->logger->info('AssemblyAI upload response', [
            'status' => $statusCode,
            'body'   => substr($rawBody, 0, 200),
        ]);

        if ($statusCode !== 200) {
            throw new \Exception('AssemblyAI upload failed: HTTP ' . $statusCode . ' — ' . $rawBody);
        }

        $data = json_decode($rawBody, true);
        if (!isset($data['upload_url'])) {
            throw new \Exception('AssemblyAI upload failed: no upload_url in response');
        }

        return $data['upload_url'];
    }

    private function requestTranscript(string $audioUrl): string
    {
        // ← CORRIGÉ : 'speech_model' (singulier, string) au lieu de 'speech_models' (array invalide)
        $payload = [
            'audio_url'   => $audioUrl,
            'punctuate'   => true,
            'format_text' => true,
        ];

        $response = $this->httpClient->request('POST', self::BASE_URL . '/transcript', [
            'headers' => [
                'authorization' => $this->assemblyAiApiKey,
                'content-type'  => 'application/json',
            ],
            'json'    => $payload,
            'timeout' => self::TIMEOUT_SECONDS,
        ]);

        $statusCode = $response->getStatusCode();
        $rawBody    = $response->getContent(false);

        $this->logger->info('AssemblyAI transcript response', [
            'status' => $statusCode,
            'body'   => $rawBody,
        ]);

        if ($statusCode !== 200) {
            throw new \Exception('AssemblyAI transcript failed: HTTP ' . $statusCode . ' — ' . $rawBody);
        }

        $data = json_decode($rawBody, true);
        if (!isset($data['id'])) {
            throw new \Exception('AssemblyAI transcript failed: no ID in response');
        }

        return $data['id'];
    }

    private function pollTranscript(string $transcriptId): array
    {
        for ($i = 0; $i < self::MAX_POLLS; $i++) {
            sleep(self::POLL_INTERVAL);

            $response = $this->httpClient->request('GET', self::BASE_URL . '/transcript/' . $transcriptId, [
                'headers' => ['authorization' => $this->assemblyAiApiKey],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $data   = $response->toArray();
            $status = $data['status'] ?? 'unknown';

            $this->logger->debug('AssemblyAI poll', ['attempt' => $i + 1, 'status' => $status]);

            if ($status === 'completed') return $data;
            if ($status === 'error') {
                throw new \Exception('AssemblyAI transcription error: ' . ($data['error'] ?? 'unknown'));
            }
        }
        throw new \Exception('AssemblyAI timeout after ' . (self::MAX_POLLS * self::POLL_INTERVAL) . 's');
    }

    public function computeFluencyMetrics(array $words, float $duration): array
    {
        if (empty($words) || $duration <= 0) {
            return ['wordsPerMinute' => 0, 'avgConfidence' => 0, 'pauseCount' => 0, 'fluencyScore' => 0];
        }

        $wpm         = round((count($words) / $duration) * 60);
        $confidences = array_column($words, 'confidence');
        $avgConf     = count($confidences) > 0 ? round(array_sum($confidences) / count($confidences), 3) : 0;

        $pauseCount = 0;
        for ($i = 1; $i < count($words); $i++) {
            if ((($words[$i]['start'] ?? 0) - ($words[$i - 1]['end'] ?? 0)) > 1000) {
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
}

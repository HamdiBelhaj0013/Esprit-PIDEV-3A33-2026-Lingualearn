<?php

namespace App\Module\InternationalTests\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiWritingService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $geminiApiKey
    ) {
    }

    /**
     * Generate a writing topic based on level and language
     */
    public function generateWritingTopic(string $level, string $languageName): array
    {
        $prompt = $this->buildTopicPrompt($level, $languageName);

        try {
            $response = $this->callGeminiApi($prompt, 0.7);

            $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $decoded = json_decode($this->cleanJson($content), true);
            if ($decoded && isset($decoded['topic'])) {
                return $decoded;
            }

            // Fallback: return raw content
            return [
                'topic'        => $content,
                'instructions' => "Write your essay in $languageName.",
                'wordCount'    => $this->getWordCountForLevel($level),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Gemini topic generation failed', ['error' => $e->getMessage()]);

            return [
                'topic'        => "Write about your daily routine and hobbies.",
                'instructions' => "Write your essay in $languageName.",
                'wordCount'    => $this->getWordCountForLevel($level),
            ];
        }
    }

    /**
     * Correct a writing essay using Gemini AI
     */
    public function correctWriting(string $topic, string $userResponse, string $level, string $languageName): array
    {
        $prompt = $this->buildCorrectionPrompt($topic, $userResponse, $level, $languageName);

        try {
            $response = $this->callGeminiApi($prompt, 0.2);

            $raw = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Log raw response for debugging
            $this->logger->debug('Gemini correction raw response', ['content' => $raw]);

            $content = $this->cleanJson($raw);
            $result  = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Gemini JSON decode error', [
                    'json_error' => json_last_error_msg(),
                    'raw'        => $raw,
                    'cleaned'    => $content,
                ]);
                throw new \Exception('JSON decode failed: ' . json_last_error_msg());
            }

            if (!$result || !isset($result['note'])) {
                $this->logger->error('Gemini response missing "note" field', ['result' => $result]);
                throw new \Exception('Invalid JSON structure from Gemini: missing "note"');
            }

            $note = (float) $result['note'];

            return [
                'note'        => $note,
                'feedback'    => $result['feedback']    ?? 'No feedback provided',
                'grammar'     => $result['grammar']     ?? 'No grammar analysis',
                'coherence'   => $result['coherence']   ?? 'No coherence analysis',
                'vocabulary'  => $result['vocabulary']  ?? 'No vocabulary analysis',
                'suggestions' => $result['suggestions'] ?? [],
                'passed'      => $note >= 10.0,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Gemini correction failed', ['error' => $e->getMessage()]);

            return [
                'note'        => 10.0,
                'feedback'    => 'Unable to process correction. Please try again.',
                'grammar'     => 'Analysis unavailable',
                'coherence'   => 'Analysis unavailable',
                'vocabulary'  => 'Analysis unavailable',
                'suggestions' => ['Please resubmit your essay'],
                'passed'      => false,
            ];
        }
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    /**
     * Strip markdown code fences and extract the first JSON object found.
     */
    private function cleanJson(string $content): string
    {
        // Remove ```json ... ``` or ``` ... ``` fences
        $content = preg_replace('/```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/```/', '', $content);

        // Extract the first complete JSON object { ... }
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        return trim($content);
    }

    /**
     * Call the Gemini API.
     * Use responseMimeType = application/json to force clean JSON output.
     * temperature: low (0.2) for correction (deterministic), higher (0.7) for topic generation.
     */
    private function callGeminiApi(string $prompt, float $temperature = 0.3): array
    {
        $response = $this->httpClient->request('POST', self::GEMINI_API_URL, [
            'query' => ['key' => $this->geminiApiKey],
            'json'  => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'      => $temperature,
                    'maxOutputTokens'  => 2048,
                    'responseMimeType' => 'application/json', // Force raw JSON — no markdown wrapping
                ],
            ],
        ]);

        $data = $response->toArray();

        // Log any finish reason that is not STOP (e.g. SAFETY, MAX_TOKENS)
        $finishReason = $data['candidates'][0]['finishReason'] ?? null;
        if ($finishReason && $finishReason !== 'STOP') {
            $this->logger->warning('Gemini non-STOP finish reason', ['finishReason' => $finishReason]);
        }

        return $data;
    }

    private function buildTopicPrompt(string $level, string $languageName): string
    {
        $wordCount = $this->getWordCountForLevel($level);

        return <<<PROMPT
You are a language teacher creating a writing exercise for a {$level} level student learning {$languageName}.

Generate a writing topic suitable for this level. Return ONLY a raw JSON object (no markdown, no code blocks) with this exact structure:
{
    "topic": "The main writing topic/question",
    "instructions": "Clear instructions for the student",
    "wordCount": {$wordCount}
}

Make the topic engaging, culturally appropriate, and suitable for the {$level} level.
PROMPT;
    }

    private function buildCorrectionPrompt(string $topic, string $userResponse, string $level, string $languageName): string
    {
        return <<<PROMPT
IMPORTANT: Respond with ONLY a raw JSON object. No markdown. No explanation. No code blocks. Start your response with { and end with }.

You are an expert {$languageName} language teacher correcting a {$level} level student's essay.

TOPIC: {$topic}

STUDENT'S RESPONSE:
{$userResponse}

Evaluate this essay and return a JSON object with this EXACT structure:
{
    "note": 15.5,
    "feedback": "Overall feedback about the essay",
    "grammar": "Analysis of grammar mistakes and quality",
    "coherence": "Analysis of text structure and coherence",
    "vocabulary": "Analysis of vocabulary richness and appropriateness",
    "suggestions": ["Suggestion 1", "Suggestion 2", "Suggestion 3"],
    "passed": true
}

Grading criteria:
- note: Score out of 20 (float, e.g. 13.5). Pass threshold is 10/20.
- passed: true if note >= 10, false otherwise.
- Be fair but constructive.
- Adapt expectations to the {$level} level.
PROMPT;
    }

    private function getWordCountForLevel(string $level): int
    {
        return match (strtolower($level)) {
            'beginner'     => 100,
            'intermediate' => 200,
            'advanced'     => 300,
            default        => 150,
        };
    }
}

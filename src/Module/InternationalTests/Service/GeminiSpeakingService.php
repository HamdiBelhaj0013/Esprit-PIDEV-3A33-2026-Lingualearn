<?php

namespace App\Module\InternationalTests\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * GeminiSpeakingService
 * Gère la conversation speaking en 5 échanges + évaluation finale
 */
class GeminiSpeakingService // uses Groq API internally
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    // Sujets en anglais — Gemini les traduit dans la langue du test via le prompt
    private const SUBJECTS_BY_LEVEL = [
        'Beginner'     => ['daily routine', 'favorite food', 'family', 'hobbies', 'your city'],
        'Intermediate' => ['travel experiences', 'technology in daily life', 'work and career', 'social media', 'environment'],
        'Advanced'     => ['globalization', 'AI ethics', 'cultural identity', 'economic inequality', 'future of education'],
    ];

    /**
     * Maps localized (French, etc.) language names → canonical English names for the LLM prompt.
     * The LLM must receive "English", "French", "Spanish"… not "Anglais", "Français", "Espagnol".
     */
    private const LANGUAGE_NAME_MAP = [
        // French-localized names
        'anglais'     => 'English',
        'français'    => 'French',
        'espagnol'    => 'Spanish',
        'allemand'    => 'German',
        'arabe'       => 'Arabic',
        'italien'     => 'Italian',
        'portugais'   => 'Portuguese',
        'chinois'     => 'Chinese',
        'japonais'    => 'Japanese',
        'russe'       => 'Russian',
        'turc'        => 'Turkish',
        'néerlandais' => 'Dutch',
        'coréen'      => 'Korean',
        // Already-English names (passthrough)
        'english'     => 'English',
        'french'      => 'French',
        'spanish'     => 'Spanish',
        'german'      => 'German',
        'arabic'      => 'Arabic',
        'italian'     => 'Italian',
        'portuguese'  => 'Portuguese',
        'chinese'     => 'Chinese',
        'japanese'    => 'Japanese',
        'russian'     => 'Russian',
        'turkish'     => 'Turkish',
        'dutch'       => 'Dutch',
        'korean'      => 'Korean',
    ];

    /**
     * Normalizes any localized language name to its canonical English equivalent.
     * Falls back to ucfirst of the original value if not in the map.
     */
    private function normalizeLanguageName(string $languageName): string
    {
        $key = mb_strtolower(trim($languageName));
        return self::LANGUAGE_NAME_MAP[$key] ?? ucfirst($languageName);
    }

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        private string              $groqApiKey
    ) {}

    // ═══════════════════════════════════════════════
    // 1. DÉMARRER LA CONVERSATION
    // ═══════════════════════════════════════════════

    public function startConversation(string $level, string $languageName): array
    {
        $languageName = $this->normalizeLanguageName($languageName);

        $subjects = self::SUBJECTS_BY_LEVEL[$level] ?? self::SUBJECTS_BY_LEVEL['Intermediate'];
        $subject  = $subjects[array_rand($subjects)];

        $prompt = <<<PROMPT
You are a friendly language examiner conducting a {$languageName} speaking test for a {$level} level student.

CRITICAL RULE: You MUST write ALL your responses EXCLUSIVELY in {$languageName}.
Do NOT use English at all (except for JSON keys). Every single value must be in {$languageName}.

The conversation topic is: "{$subject}" — translate this topic name into {$languageName} for the "subject" field.

Start the conversation with:
1. A warm, brief welcome (1 sentence) — written in {$languageName}
2. Introduce the topic clearly — in {$languageName}
3. Ask your FIRST question (open-ended, appropriate for {$level} level) — in {$languageName}

IMPORTANT: Return ONLY a raw JSON object. No markdown. No backticks. No explanation before or after. Start your response directly with { and end with }.

Example of expected output:
{"subject":"Routine quotidienne","welcome":"Bonjour ! Bienvenue à votre test oral.","firstQuestion":"Pouvez-vous me décrire votre journée typique ?","instructions":"Parlez clairement et prenez votre temps."}

Now return the JSON object (JSON keys stay in English, ALL values in {$languageName}):
{
  "subject": "The topic name translated into {$languageName}",
  "welcome": "Your welcome message IN {$languageName}",
  "firstQuestion": "Your first question IN {$languageName}",
  "instructions": "Brief instruction for the student IN {$languageName}"
}

Rules:
- "subject" field: translate "{$subject}" into {$languageName}
- ALL other text values MUST be in {$languageName} — mandatory
- Question must be open-ended (no yes/no)
- Difficulty appropriate for {$level}
- Start your response with { and end with }
PROMPT;

        try {
            $response = $this->callGemini($prompt);
            $content  = $this->cleanJson($response);
            $result   = json_decode($content, true);
            if (!$result || !isset($result['firstQuestion'])) throw new \Exception('Invalid response');
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('GeminiSpeaking::startConversation failed', ['error' => $e->getMessage()]);

            // Fallback multilingue
            $fallbacks = [
                'english'    => ['Hello! Welcome to your speaking test.', "Can you tell me about: {$subject}?", 'Speak clearly and take your time.'],
                'french'     => ['Bonjour ! Bienvenue à votre test oral.', "Parlez-moi de : {$subject} ?", 'Parlez clairement et prenez votre temps.'],
                'français'   => ['Bonjour ! Bienvenue à votre test oral.', "Parlez-moi de : {$subject} ?", 'Parlez clairement et prenez votre temps.'],
                'spanish'    => ['¡Hola! Bienvenido a tu prueba oral.', "Háblame de: {$subject}?", 'Habla claramente y tómate tu tiempo.'],
                'german'     => ['Hallo! Willkommen zu Ihrem Sprechtest.', "Erzählen Sie mir von: {$subject}?", 'Sprechen Sie klar und nehmen Sie sich Zeit.'],
                'arabic'     => ['مرحباً! أهلاً بك في اختبار المحادثة.', "أخبرني عن: {$subject}?", 'تحدث بوضوح وخذ وقتك.'],
            ];

            $key      = strtolower(trim($languageName));
            $msgs     = $fallbacks[$key] ?? $fallbacks['english'];

            return [
                'subject'       => $subject,
                'welcome'       => $msgs[0],
                'firstQuestion' => $msgs[1],
                'instructions'  => $msgs[2],
            ];
        }
    }

    // ═══════════════════════════════════════════════
    // 2. CONTINUER LA CONVERSATION
    // ═══════════════════════════════════════════════

    public function continueConversation(array $history, string $userAnswer, int $exchangeNumber, string $level, string $languageName): array
    {
        $languageName = $this->normalizeLanguageName($languageName);

        $historyText      = $this->formatHistory($history);
        $isLast           = $exchangeNumber >= 5;
        $lastInstruction  = $isLast
            ? "This is the LAST exchange (5/5). React to the student's answer and give a brief, warm closing statement. No more questions."
            : "React briefly to the student's answer (1-2 sentences, encouraging) and ask the NEXT question to keep the conversation going about the same topic.";
        $nextQuestionHint = $isLast ? 'null' : 'Your next question';
        $isFinishedStr    = $isLast ? 'true' : 'false';

        $prompt = <<<PROMPT
You are a language examiner conducting a {$languageName} speaking test (exchange {$exchangeNumber}/5).

CRITICAL RULE: You MUST write ALL your responses EXCLUSIVELY in {$languageName}.
Do NOT use English at all. Every single word must be in {$languageName}.

Conversation so far:
{$historyText}

Student's latest answer: "{$userAnswer}"

{$lastInstruction}

IMPORTANT: Return ONLY a raw JSON object. No markdown. No backticks. No explanation. Start your response with { and end with }.

Example of expected output format:
{"reaction":"Très bien !","nextQuestion":"Quelle est votre activité préférée le week-end ?","isFinished":false}

Now return the JSON object (keys in English, ALL values in {$languageName}):
{
  "reaction": "Your brief reaction IN {$languageName}",
  "nextQuestion": "{$nextQuestionHint}",
  "isFinished": {$isFinishedStr}
}

Rules:
- ALL text values MUST be in {$languageName} — mandatory
- Level: {$level}
- Be natural, warm, and encouraging
- React specifically to what the student said
- Return ONLY the JSON object, starting with {
PROMPT;

        try {
            $response = $this->callGemini($prompt);
            $this->logger->info('GeminiSpeaking::continueConversation raw response', ['raw' => substr($response, 0, 500)]);
            $content  = $this->cleanJson($response);
            $result   = json_decode($content, true);
            if (!$result || !isset($result['reaction'])) {
                $this->logger->error('GeminiSpeaking::continueConversation JSON decode failed', [
                    'raw'        => substr($response, 0, 500),
                    'cleaned'    => substr($content, 0, 500),
                    'json_error' => json_last_error_msg(),
                ]);
                throw new \Exception('Invalid response: ' . json_last_error_msg());
            }
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('GeminiSpeaking::continueConversation failed', ['error' => $e->getMessage()]);

            // Fallback dynamique selon langue et numéro d'échange
            $followUpQuestions = [
                'english' => [
                    1 => "That's great! Can you give me more details about that?",
                    2 => "Interesting! How does that affect your daily life?",
                    3 => "I see! What do you think are the main challenges with this?",
                    4 => "Good point! Can you compare this with your past experience?",
                ],
                'french'   => [
                    1 => "C'est intéressant ! Pouvez-vous me donner plus de détails ?",
                    2 => "Très bien ! Comment cela influence-t-il votre quotidien ?",
                    3 => "Je vois ! Quels sont selon vous les principaux défis ?",
                    4 => "Bonne réponse ! Pouvez-vous comparer avec votre expérience passée ?",
                ],
                'français' => [
                    1 => "C'est intéressant ! Pouvez-vous me donner plus de détails ?",
                    2 => "Très bien ! Comment cela influence-t-il votre quotidien ?",
                    3 => "Je vois ! Quels sont selon vous les principaux défis ?",
                    4 => "Bonne réponse ! Pouvez-vous comparer avec votre expérience passée ?",
                ],
                'spanish' => [
                    1 => "¡Interesante! ¿Puedes darme más detalles?",
                    2 => "¡Muy bien! ¿Cómo afecta esto tu vida diaria?",
                    3 => "¡Entiendo! ¿Cuáles son los principales desafíos?",
                    4 => "¡Buena respuesta! ¿Puedes compararlo con tu experiencia pasada?",
                ],
            ];

            $langKey   = strtolower(trim($languageName));
            $questions = $followUpQuestions[$langKey] ?? $followUpQuestions['english'];
            $question  = $questions[$exchangeNumber] ?? $questions[1];

            $reactions = [
                'english' => "Thank you for your answer.",
                'french'  => "Merci pour votre réponse.",
                'français'=> "Merci pour votre réponse.",
                'spanish' => "Gracias por su respuesta.",
            ];
            $reaction = $reactions[$langKey] ?? $reactions['english'];

            return [
                'reaction'     => $reaction,
                'nextQuestion' => $isLast ? null : $question,
                'isFinished'   => $isLast,
            ];
        }
    }

    // ═══════════════════════════════════════════════
    // 3. ÉVALUATION FINALE
    // ═══════════════════════════════════════════════

    public function evaluateConversation(array $history, array $fluencyMetrics, string $level, string $languageName): array
    {
        $languageName = $this->normalizeLanguageName($languageName);

        $historyText   = $this->formatHistory($history);
        $fluencyInfo   = json_encode($fluencyMetrics, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
You are an expert {$languageName} language examiner. Evaluate this speaking test conversation for a {$level} level student.

CRITICAL RULE: Write ALL feedback and text values EXCLUSIVELY in {$languageName}.

Full conversation transcript:
{$historyText}

Fluency metrics from audio analysis:
{$fluencyInfo}

Provide a comprehensive evaluation. Return ONLY a JSON object (keys in English, ALL values in {$languageName}):
{
  "note": 15.5,
  "grammar": "Detailed grammar analysis IN {$languageName}",
  "vocabulary": "Vocabulary analysis IN {$languageName}",
  "fluency": "Fluency analysis IN {$languageName}",
  "coherence": "Coherence analysis IN {$languageName}",
  "pronunciation": "Pronunciation analysis IN {$languageName}",
  "strengths": ["strength 1 in {$languageName}", "strength 2 in {$languageName}"],
  "improvements": ["area 1 in {$languageName}", "area 2 in {$languageName}"],
  "overall_feedback": "Overall feedback paragraph IN {$languageName}",
  "passed": true,
  "cefr_level": "B1"
}

Grading (out of 20):
- Grammar accuracy: 5 points
- Vocabulary richness: 4 points
- Fluency & pronunciation: 4 points
- Coherence & content: 4 points
- Task completion: 3 points

Rules:
- passed: true if note >= 10
- cefr_level: estimated CEFR level (A1, A2, B1, B2, C1, C2)
- ALL text values MUST be in {$languageName} — mandatory
- Consider {$level} level expectations
- Return ONLY JSON, no markdown
PROMPT;

        try {
            $response = $this->callGemini($prompt);
            $content  = $this->cleanJson($response);
            $result   = json_decode($content, true);
            if (!$result || !isset($result['note'])) throw new \Exception('Invalid evaluation response');

            return array_merge([
                'note'             => 10.0,
                'grammar'          => 'Analysis unavailable',
                'vocabulary'       => 'Analysis unavailable',
                'fluency'          => 'Analysis unavailable',
                'coherence'        => 'Analysis unavailable',
                'pronunciation'    => 'Analysis unavailable',
                'strengths'        => [],
                'improvements'     => [],
                'overall_feedback' => 'Evaluation completed.',
                'passed'           => false,
                'cefr_level'       => 'N/A',
            ], $result);

        } catch (\Exception $e) {
            $this->logger->error('GeminiSpeaking::evaluateConversation failed', ['error' => $e->getMessage()]);
            return [
                'note'             => 10.0,
                'grammar'          => 'Evaluation unavailable',
                'vocabulary'       => 'Evaluation unavailable',
                'fluency'          => 'Evaluation unavailable',
                'coherence'        => 'Evaluation unavailable',
                'pronunciation'    => 'Evaluation unavailable',
                'strengths'        => [],
                'improvements'     => ['Please try again'],
                'overall_feedback' => 'Unable to evaluate. Score estimated.',
                'passed'           => false,
                'cefr_level'       => 'N/A',
            ];
        }
    }

    // ═══════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════

    private function callGemini(string $prompt, int $maxRetries = 2): string
    {
        $lastError = null;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->groqApiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'timeout' => 20,
                    'json'    => [
                        'model'       => 'llama-3.3-70b-versatile',
                        'messages'    => [
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => 0.75,
                        'max_tokens'  => 1024,
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 429) {
                    $this->logger->warning('Groq rate limit (429), retrying...', ['attempt' => $attempt]);
                    sleep($attempt * 3);
                    continue;
                }

                $data = $response->toArray();
                return $data['choices'][0]['message']['content'] ?? '';

            } catch (\Exception $e) {
                $lastError = $e;
                $this->logger->error('Groq API error', ['attempt' => $attempt, 'error' => $e->getMessage()]);
                if ($attempt < $maxRetries) {
                    sleep(2);
                }
            }
        }
        throw $lastError ?? new \Exception('Groq API failed after ' . $maxRetries . ' attempts');
    }

    private function cleanJson(string $content): string
    {
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        // Extraire le JSON si entouré de texte parasite
        if (!str_starts_with($content, '{')) {
            $start = strpos($content, '{');
            $end   = strrpos($content, '}');
            if ($start !== false && $end !== false) {
                $content = substr($content, $start, $end - $start + 1);
            }
        }

        $this->logger->debug('GeminiSpeaking: raw JSON', ['preview' => substr($content, 0, 200)]);

        return $content;
    }

    private function formatHistory(array $history): string
    {
        return implode("\n", array_map(fn($h) =>
            ($h['role'] === 'gemini' ? 'Examiner' : 'Student') . ': ' . $h['text'],
            $history));
    }
}

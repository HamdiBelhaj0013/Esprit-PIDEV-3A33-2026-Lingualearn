<?php

namespace App\Module\InternationalTests\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * ─────────────────────────────────────────────────────────────────
 * GeminiListeningService
 * ─────────────────────────────────────────────────────────────────
 *
 * Génère dynamiquement le contenu d'un test Listening via Gemini AI :
 *  - Un texte audio adapté au niveau et à la langue
 *  - Des questions mixtes (QCM + réponse courte) sur ce texte
 *  - L'évaluation des réponses ouvertes de l'étudiant
 */
class GeminiListeningService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    // Nombre de questions par type selon le niveau
    private const QUESTION_CONFIG = [
        'Beginner'     => ['qcm' => 3, 'open' => 2, 'words' => 80],
        'Intermediate' => ['qcm' => 4, 'open' => 3, 'words' => 150],
        'Advanced'     => ['qcm' => 4, 'open' => 4, 'words' => 220],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        private string              $geminiApiKey
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // GÉNÉRATION DU CONTENU LISTENING COMPLET
    // ═══════════════════════════════════════════════════════════════

    /**
     * Génère un passage audio + questions mixtes pour un test Listening.
     *
     * @return array{
     *   audioText: string,
     *   audioType: string,
     *   questions: array,
     *   wordCount: int,
     *   level: string,
     *   language: string
     * }
     */
    public function generateListeningContent(string $level, string $languageName): array
    {
        $config = self::QUESTION_CONFIG[$level] ?? self::QUESTION_CONFIG['Intermediate'];
        $prompt = $this->buildGenerationPrompt($level, $languageName, $config);

        try {
            $response = $this->callGeminiApi($prompt);
            $content  = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Nettoyer les blocs markdown
            $content = preg_replace('/```json\s*/i', '', $content);
            $content = preg_replace('/```\s*$/',      '', $content);
            $content = trim($content);

            $result = json_decode($content, true);

            if (!$result || !isset($result['audioText'], $result['questions'])) {
                throw new \Exception('Invalid Gemini response structure');
            }

            // Normaliser les questions
            $result['questions'] = $this->normalizeQuestions($result['questions']);
            $result['level']     = $level;
            $result['language']  = $languageName;
            $result['wordCount'] = str_word_count($result['audioText']);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('GeminiListeningService::generateListeningContent failed', [
                'error' => $e->getMessage(),
                'level' => $level,
            ]);

            return $this->buildFallbackContent($level, $languageName, $config);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // ÉVALUATION DES RÉPONSES OUVERTES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Évalue les réponses ouvertes de l'étudiant.
     * Les QCM sont corrigés côté PHP — seules les questions "open" arrivent ici.
     *
     * @param array $openQuestions  [{questionText, correctAnswer, userAnswer}, ...]
     * @param string $level
     * @param string $languageName
     *
     * @return array{
     *   evaluations: array,
     *   openScore: float,
     *   feedback: string
     * }
     */
    public function evaluateOpenAnswers(array $openQuestions, string $level, string $languageName): array
    {
        if (empty($openQuestions)) {
            return ['evaluations' => [], 'openScore' => 0.0, 'feedback' => ''];
        }

        $prompt = $this->buildEvaluationPrompt($openQuestions, $level, $languageName);

        try {
            $response = $this->callGeminiApi($prompt);
            $content  = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $content = preg_replace('/```json\s*/i', '', $content);
            $content = preg_replace('/```\s*$/',      '', $content);
            $content = trim($content);

            $result = json_decode($content, true);

            if (!$result || !isset($result['evaluations'])) {
                throw new \Exception('Invalid evaluation response');
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('GeminiListeningService::evaluateOpenAnswers failed', [
                'error' => $e->getMessage(),
            ]);

            // Fallback : toutes les réponses ouvertes comptent pour moitié
            $evals = array_map(fn($q) => [
                'isCorrect' => false,
                'score'     => 0.5,
                'feedback'  => 'Unable to evaluate automatically.',
            ], $openQuestions);

            return [
                'evaluations' => $evals,
                'openScore'   => 0.5,
                'feedback'    => 'Automatic evaluation unavailable. Score estimated.',
            ];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CORRECTION COMPLÈTE (QCM + open) → score final /20
    // ═══════════════════════════════════════════════════════════════

    /**
     * Corrige toutes les réponses et retourne le score final sur 20.
     * Appelé depuis le controller lors de la soumission.
     *
     * @param array  $questions   Questions générées par Gemini (depuis la session)
     * @param array  $userAnswers ['questionIndex' => 'userAnswer', ...]
     * @param string $level
     * @param string $languageName
     *
     * @return array{
     *   score: float,
     *   details: array,
     *   openEvaluation: array,
     *   passed: bool
     * }
     */
    public function scoreSubmission(
        array  $questions,
        array  $userAnswers,
        string $level,
        string $languageName
    ): array {
        $qcmCorrect  = 0;
        $qcmTotal    = 0;
        $openItems   = [];
        $details     = [];

        foreach ($questions as $idx => $question) {
            $userAnswer = trim($userAnswers[$idx] ?? '');
            $type       = $question['type'] ?? 'qcm';

            if ($type === 'qcm') {
                $qcmTotal++;
                $expected  = strtoupper(trim($question['correctAnswer'] ?? ''));
                $given     = strtoupper($userAnswer);
                $isCorrect = $given === $expected;

                if ($isCorrect) $qcmCorrect++;

                $details[] = [
                    'index'           => $idx,
                    'type'            => 'qcm',
                    'questionText'    => $question['questionText'],
                    'options'         => $question['options'] ?? [],
                    'userAnswer'      => $userAnswer,
                    'correctAnswer'   => $question['correctAnswer'],
                    'isCorrect'       => $isCorrect,
                    'sectionCategory' => 'Listening',
                    'points'          => 1,
                ];
            } else {
                // open — on collecte pour Gemini
                $openItems[] = [
                    'index'         => $idx,
                    'questionText'  => $question['questionText'],
                    'correctAnswer' => $question['correctAnswer'] ?? '',
                    'userAnswer'    => $userAnswer,
                ];

                $details[] = [
                    'index'           => $idx,
                    'type'            => 'open',
                    'questionText'    => $question['questionText'],
                    'userAnswer'      => $userAnswer,
                    'correctAnswer'   => $question['correctAnswer'] ?? '',
                    'isCorrect'       => null, // sera rempli après évaluation Gemini
                    'sectionCategory' => 'Listening',
                    'points'          => 1,
                ];
            }
        }

        // Évaluation des questions ouvertes par Gemini
        $openEvaluation = $this->evaluateOpenAnswers($openItems, $level, $languageName);
        $openScore      = $openEvaluation['openScore'] ?? 0;

        // Enrichir les détails avec les résultats open
        $openEvalIdx = 0;
        foreach ($details as &$d) {
            if ($d['type'] === 'open') {
                $eval              = $openEvaluation['evaluations'][$openEvalIdx] ?? [];
                $d['isCorrect']    = $eval['isCorrect'] ?? false;
                $d['aiFeedback']   = $eval['feedback']  ?? '';
                $d['aiScore']      = $eval['score']      ?? 0;
                $openEvalIdx++;
            }
        }
        unset($d);

        // Score final /20
        $totalQ    = count($questions);
        $qcmWeight = $qcmTotal / max($totalQ, 1);
        $openWeight = (count($openItems)) / max($totalQ, 1);

        $qcmScore20  = $qcmTotal  > 0 ? ($qcmCorrect / $qcmTotal) * 20 * $qcmWeight  : 0;
        $openScore20 = count($openItems) > 0 ? $openScore * 20 * $openWeight : 0;

        $finalScore = round($qcmScore20 + $openScore20, 2);

        return [
            'score'          => $finalScore,
            'details'        => $details,
            'openEvaluation' => $openEvaluation,
            'passed'         => $finalScore >= 10,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // PROMPTS
    // ═══════════════════════════════════════════════════════════════

    private function buildGenerationPrompt(string $level, string $languageName, array $config): string
    {
        $qcmCount  = $config['qcm'];
        $openCount = $config['open'];
        $words     = $config['words'];
        $total     = $qcmCount + $openCount;

        $audioTypes = match($level) {
            'Beginner'     => 'a simple conversation, an announcement, or a short description',
            'Intermediate' => 'a dialogue, a radio segment, an interview, or a short lecture',
            'Advanced'     => 'a podcast excerpt, a news report, a debate, or a complex interview',
            default        => 'a conversation or dialogue',
        };

        return <<<PROMPT
You are a professional {$languageName} language exam designer creating a Listening comprehension test for a {$level} level student.

Generate ONLY a valid JSON object (no markdown, no explanations) with this EXACT structure:

{
  "audioText": "The text that will be read aloud to the student. Write {$words} words approximately. Make it {$audioTypes}. Write it naturally as spoken language, in {$languageName}.",
  "audioType": "conversation|announcement|interview|podcast|lecture",
  "questions": [
    {
      "type": "qcm",
      "questionText": "A question about the audio content",
      "options": {
        "A": "First option",
        "B": "Second option",
        "C": "Third option",
        "D": "Fourth option"
      },
      "correctAnswer": "A"
    },
    {
      "type": "open",
      "questionText": "A question requiring a short written answer",
      "correctAnswer": "The expected answer (keywords accepted)",
      "options": {}
    }
  ]
}

Rules:
- Generate exactly {$qcmCount} questions with type "qcm" and {$openCount} questions with type "open" ({$total} total)
- All questions MUST be answerable from the audioText only
- correctAnswer for QCM must be exactly one of: A, B, C, or D
- correctAnswer for open questions: write the key expected words/phrase
- Difficulty must match {$level} level
- Language of questions: {$languageName}
- Return ONLY the JSON, nothing else
PROMPT;
    }

    private function buildEvaluationPrompt(array $openQuestions, string $level, string $languageName): string
    {
        $questionsJson = json_encode($openQuestions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a {$languageName} language teacher evaluating a {$level} student's listening comprehension answers.

Here are the open-ended questions, expected answers, and the student's answers:
{$questionsJson}

Evaluate each answer and return ONLY a JSON object (no markdown):
{
  "evaluations": [
    {
      "isCorrect": true,
      "score": 1.0,
      "feedback": "Short feedback about this specific answer"
    }
  ],
  "openScore": 0.75,
  "feedback": "General feedback about the student's open answers overall"
}

Rules:
- "score" is between 0.0 and 1.0 (partial credit is allowed: 0.5 for partially correct)
- "isCorrect" is true if score >= 0.5
- "openScore" is the average of all individual scores
- Be lenient with spelling mistakes if the meaning is correct
- Consider the {$level} level when grading
- Return ONLY the JSON, nothing else
PROMPT;
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function callGeminiApi(string $prompt): array
    {
        $response = $this->httpClient->request('POST', self::GEMINI_API_URL, [
            'query' => ['key' => $this->geminiApiKey],
            'json'  => [
                'contents' => [[
                    'parts' => [['text' => $prompt]]
                ]],
                'generationConfig' => [
                    'temperature'     => 0.8,
                    'maxOutputTokens' => 3000,
                ],
            ],
        ]);

        return $response->toArray();
    }

    /**
     * S'assure que chaque question a les champs requis.
     */
    private function normalizeQuestions(array $questions): array
    {
        return array_map(function (array $q, int $idx) {
            return [
                'index'         => $idx,
                'type'          => $q['type']          ?? 'qcm',
                'questionText'  => $q['questionText']  ?? 'Question ' . ($idx + 1),
                'options'       => $q['options']       ?? [],
                'correctAnswer' => $q['correctAnswer'] ?? '',
            ];
        }, $questions, array_keys($questions));
    }

    /**
     * Contenu de secours si Gemini échoue.
     */
    private function buildFallbackContent(string $level, string $languageName, array $config): array
    {
        return [
            'audioText'  => "Good morning, everyone. Today we are going to talk about daily routines and how people organize their time. Many people start their day with breakfast and a cup of coffee. Then they go to work or school. In the afternoon, they often meet friends or do some sport. In the evening, most people enjoy watching television or reading a book before going to sleep.",
            'audioType'  => 'lecture',
            'questions'  => [
                ['index' => 0, 'type' => 'qcm',  'questionText' => 'What do many people do in the morning?', 'options' => ['A' => 'Have breakfast', 'B' => 'Watch television', 'C' => 'Do sport', 'D' => 'Read a book'], 'correctAnswer' => 'A'],
                ['index' => 1, 'type' => 'qcm',  'questionText' => 'When do people often meet friends?', 'options' => ['A' => 'Morning', 'B' => 'Night', 'C' => 'Afternoon', 'D' => 'Weekend'], 'correctAnswer' => 'C'],
                ['index' => 2, 'type' => 'open', 'questionText' => 'What do most people do in the evening?', 'correctAnswer' => 'watch television or read a book', 'options' => []],
            ],
            'level'      => $level,
            'language'   => $languageName,
            'wordCount'  => 80,
        ];
    }
}

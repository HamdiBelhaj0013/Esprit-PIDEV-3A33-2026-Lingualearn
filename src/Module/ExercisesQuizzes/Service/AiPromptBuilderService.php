<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

/**
 * Construit le prompt pour l'IA (explication d'erreur). Sortie attendue : texte seul (explication courte).
 */
class AiPromptBuilderService
{
    public function build(
        string $exerciseType,
        string $question,
        string $studentAnswer,
        string $correctAnswer,
        int $difficulty = 3,
        array $choices = [],
        string $outputLanguage = 'fr'
    ): string {
        $quizType = $this->getQuizTypeForPrompt($exerciseType);

        return <<<PROMPT
You are an educational assistant.

Your task is to explain briefly why the user's answer is incorrect.

Quiz type: {$quizType}
Question: {$question}
Correct answer: {$correctAnswer}
User answer: {$studentAnswer}

GLOBAL RULES:
- Maximum 2 short sentences.
- Maximum 35 words.
- Simple language.
- No greetings.
- No bullet points.
- Do NOT mention blank numbers, positions, or indices.
- Return ONLY the explanation text.

If quiz_type = "Fill in the blank":

IMPORTANT RULES (STRICT):

1) The answers are entered as ONE STRING separated by "/".
   Example:
   "Breakfast /Delicious/ Allergic/ Recipe/ Ingredients/ Chicken/ Pepper/ Fruits /Meat/ soop"

2) Split BOTH correct_answer and user_answer ONLY using "/".

3) Trim spaces around each item.

4) Compare answers strictly by index order.

5) Identify ONLY items where:
   lowercase(trim(user_item)) ≠ lowercase(trim(correct_item))

6) If only ONE item differs:
   → Mention ONLY the incorrect word and the correct word.
   → Give a short reason (meaning or spelling).
   → Do NOT mention its position.

7) If multiple items differ:
   → Briefly explain only those incorrect words.
   → Do NOT mention positions.

8) NEVER assume the same wrong word appears in other blanks.

9) NEVER reconstruct the full sentence.

10) NEVER explain blanks that are already correct.

Output:
Return only the short explanation.
Respond in {$outputLanguage}.
PROMPT;
    }

    private function getQuizTypeForPrompt(string $type): string
    {
        return match ($type) {
            'multiple_choice' => 'QCM',
            'true_false' => 'True/False',
            'fill_blanks' => 'Fill in the blank',
            'translation' => 'Translation',
            default => $type,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

use App\Module\ExercisesQuizzes\Entity\QuizAttempt;

/**
 * Guards pour la state machine QuizAttempt (Deuxième chance).
 */
final class QuizAttemptStateMachineGuard
{
    /**
     * Débloquer la 2ème chance uniquement s'il existe au moins un exercice faux.
     */
    public function canUnlockSecond(QuizAttempt $attempt): bool
    {
        return $attempt->countWrongExercises() > 0;
    }

    /**
     * Démarrer la 2ème chance uniquement si pas déjà utilisée (exercice de rattrapage pas encore choisi ou pas encore fait).
     * On autorise start_second tant qu'on est en second_chance_available (pas encore passé en in_progress).
     */
    public function canStartSecond(QuizAttempt $attempt): bool
    {
        return $attempt->getState() === QuizAttempt::STATE_SECOND_CHANCE_AVAILABLE;
    }
}

<?php

namespace App\Module\InternationalTests\Service;

use App\Module\InternationalTests\Entity\MockTest;

/**
 * MockTestValidator
 * Service métier contenant les règles de validation du module InternationalTests.
 * Conçu pour être facilement testable (pas de BDD, pas de session).
 */
class MockTestValidator
{
    public const PASSING_SCORE = 10.0;
    public const MAX_SCORE     = 20.0;

    /** Règle 1 : La durée doit être > 0 */
    public function validateDuration(MockTest $mockTest): bool
    {
        if ($mockTest->getDurationMinutes() <= 0) {
            throw new \InvalidArgumentException('La durée du test doit être supérieure à 0 minute.');
        }
        return true;
    }

    /** Règle 2 : Le titre ne peut pas être vide */
    public function validateTitle(MockTest $mockTest): bool
    {
        if (empty(trim($mockTest->getTitle()))) {
            throw new \InvalidArgumentException('Le titre du test est obligatoire.');
        }
        return true;
    }

    /** Règle 3 : Le niveau doit être Beginner, Intermediate ou Advanced */
    public function validateLevel(string $level): bool
    {
        if (!in_array($level, MockTest::LEVELS)) {
            throw new \InvalidArgumentException("Niveau invalide : $level");
        }
        return true;
    }

    /** Règle 4 : La catégorie doit être QCM, Writing, Speaking ou Listening */
    public function validateCategory(string $category): bool
    {
        if (!in_array($category, MockTest::TEST_TYPES)) {
            throw new \InvalidArgumentException("Catégorie invalide : $category");
        }
        return true;
    }

    /** Règle 5 : Le score doit être entre 0 et 20 */
    public function validateScore(float $score): bool
    {
        if ($score < 0 || $score > self::MAX_SCORE) {
            throw new \InvalidArgumentException('Le score doit être compris entre 0 et 20.');
        }
        return true;
    }

    /** Règle 6 : Un résultat est réussi si score >= 10 */
    public function isPassed(float $score): bool
    {
        return $score >= self::PASSING_SCORE;
    }

    /** Règle 7 : Calcul pénalité selon secondes de dépassement */
    public function computePenalty(int $overageSeconds): int
    {
        if ($overageSeconds < 0) {
            throw new \InvalidArgumentException('Les secondes de dépassement ne peuvent pas être négatives.');
        }
        if ($overageSeconds <= 30)  return 5;
        if ($overageSeconds <= 120) return 15;
        if ($overageSeconds <= 300) return 30;
        return 50;
    }

    /** Règle 8 : Application pénalité sur le score */
    public function applyPenalty(float $score, int $penaltyPct): float
    {
        if ($penaltyPct < 0 || $penaltyPct > 100) {
            throw new \InvalidArgumentException('Le pourcentage de pénalité doit être entre 0 et 100.');
        }
        return round($score * (1 - $penaltyPct / 100), 2);
    }
}

<?php

namespace App\Module\InternationalTests\Service;

use App\Module\InternationalTests\Entity\MockTest;
use App\Module\InternationalTests\Entity\TestResult;
use Symfony\Component\HttpFoundation\Request;

/**
 * ─────────────────────────────────────────────────────────────────
 * ExamTimeGuardService — Métier Avancé #2
 * ─────────────────────────────────────────────────────────────────
 *
 * Service qui sécurise le chronomètre côté SERVEUR.
 *
 * Problème actuel : le timer est uniquement en JavaScript.
 * Un étudiant peut désactiver JS, modifier le DOM, ou utiliser
 * les DevTools pour contourner le timer et soumettre hors délai.
 *
 * Ce service résout ce problème en :
 *  1. Validant que la soumission arrive dans le délai autorisé
 *  2. Appliquant des pénalités graduelles selon le dépassement
 *  3. Détectant les comportements suspects (soumission trop rapide)
 *  4. Calculant le score final ajusté selon le temps réel utilisé
 *  5. Générant un rapport de conformité temporelle
 */
class ExamTimeGuardService
{
    // Tolérance réseau : secondes de grâce acceptées après expiration
    private const NETWORK_TOLERANCE_SECONDS = 10;

    // Seuils de pénalité (% de dépassement du temps → % de pénalité score)
    private const PENALTY_TIERS = [
        ['max_overage' => 30,   'penalty_pct' => 5],    // 0-30s de retard → -5%
        ['max_overage' => 120,  'penalty_pct' => 15],   // 30s-2min → -15%
        ['max_overage' => 300,  'penalty_pct' => 30],   // 2-5min → -30%
        ['max_overage' => PHP_INT_MAX, 'penalty_pct' => 50], // +5min → -50%
    ];

    // Seuil de suspicion : soumission trop rapide (en % du temps alloué)
    private const MIN_TIME_RATIO = 0.05; // moins de 5% du temps = suspect

    /**
     * Valide la conformité temporelle d'une soumission et retourne
     * un rapport complet avec le score ajusté si pénalité applicable.
     *
     * @param MockTest $mockTest   Le test soumis
     * @param Request  $request    La requête HTTP (pour accéder à la session)
     * @param float    $rawScore   Le score calculé avant ajustement temps
     *
     * @return array{
     *   isValid: bool,
     *   isSuspicious: bool,
     *   elapsedSeconds: int,
     *   allowedSeconds: int,
     *   overageSeconds: int,
     *   timeRatio: float,
     *   penaltyPct: int,
     *   adjustedScore: float,
     *   verdict: string,
     *   verdictLabel: string,
     *   verdictColor: string
     * }
     */
    public function validateSubmission(
        MockTest $mockTest,
        Request  $request,
        float    $rawScore
    ): array {
        $session        = $request->getSession();
        $startedAt      = $session->get('mock_test_started_at_' . $mockTest->getId());
        $now            = time();
        $allowedSeconds = $mockTest->getDurationMinutes() * 60;

        // Si pas de session de démarrage → on ne peut pas valider
        if (!$startedAt) {
            return $this->buildReport(
                isValid:        false,
                isSuspicious:   true,
                elapsed:        0,
                allowed:        $allowedSeconds,
                rawScore:       $rawScore,
                verdict:        'no_session',
                verdictLabel:   'No session found',
                verdictColor:   'red'
            );
        }

        $elapsedSeconds = $now - (int) $startedAt;
        $overageSeconds = max(0, $elapsedSeconds - $allowedSeconds - self::NETWORK_TOLERANCE_SECONDS);
        $timeRatio      = $allowedSeconds > 0 ? round($elapsedSeconds / $allowedSeconds, 3) : 0;

        // Cas 1 : soumission trop rapide (suspect)
        $isSuspicious = $timeRatio < self::MIN_TIME_RATIO && $elapsedSeconds < 30;

        // Cas 2 : dans les délais (avec tolérance réseau)
        if ($overageSeconds <= 0) {
            return $this->buildReport(
                isValid:        true,
                isSuspicious:   $isSuspicious,
                elapsed:        $elapsedSeconds,
                allowed:        $allowedSeconds,
                rawScore:       $rawScore,
                verdict:        $isSuspicious ? 'suspicious' : 'on_time',
                verdictLabel:   $isSuspicious ? 'Submitted too fast' : 'On time',
                verdictColor:   $isSuspicious ? 'orange' : 'green',
                overageSeconds: 0,
                penaltyPct:     0,
                adjustedScore:  $isSuspicious ? $this->applyFlatPenalty($rawScore, 10) : $rawScore
            );
        }

        // Cas 3 : dépassement → calcul pénalité graduée
        $penaltyPct    = $this->computePenaltyTier($overageSeconds);
        $adjustedScore = $this->applyPercentPenalty($rawScore, $penaltyPct);

        return $this->buildReport(
            isValid:        true,
            isSuspicious:   false,
            elapsed:        $elapsedSeconds,
            allowed:        $allowedSeconds,
            rawScore:       $rawScore,
            verdict:        'overtime',
            verdictLabel:   'Submitted late (-' . $penaltyPct . '%)',
            verdictColor:   'orange',
            overageSeconds: $overageSeconds,
            penaltyPct:     $penaltyPct,
            adjustedScore:  $adjustedScore
        );
    }

    /**
     * Vérifie si une soumission est trop tardive pour être acceptée.
     * Au-delà de 10 minutes de retard → soumission refusée.
     */
    public function isSubmissionAcceptable(MockTest $mockTest, Request $request): bool
    {
        $session        = $request->getSession();
        $startedAt      = $session->get('mock_test_started_at_' . $mockTest->getId());
        $now            = time();
        $allowedSeconds = $mockTest->getDurationMinutes() * 60;
        $maxAcceptable  = $allowedSeconds + 600; // +10 minutes max absolu (tolérance réseau)

        if (!$startedAt) return false;

        return ($now - (int) $startedAt) <= $maxAcceptable;
    }

    /**
     * Calcule le temps réel utilisé par l'étudiant pour le test,
     * en secondes et en format lisible.
     */
    public function getElapsedTime(MockTest $mockTest, Request $request): array
    {
        $session   = $request->getSession();
        $startedAt = $session->get('mock_test_started_at_' . $mockTest->getId());

        if (!$startedAt) {
            return ['seconds' => 0, 'formatted' => 'N/A', 'ratio' => 0];
        }

        $elapsed  = time() - (int) $startedAt;
        $allowed  = $mockTest->getDurationMinutes() * 60;
        $ratio    = $allowed > 0 ? round(min($elapsed / $allowed, 1) * 100, 1) : 0;
        $minutes  = intdiv($elapsed, 60);
        $seconds  = $elapsed % 60;

        return [
            'seconds'   => $elapsed,
            'formatted' => sprintf('%d min %02d sec', $minutes, $seconds),
            'ratio'     => $ratio,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Détermine le palier de pénalité selon les secondes de dépassement.
     */
    private function computePenaltyTier(int $overageSeconds): int
    {
        foreach (self::PENALTY_TIERS as $tier) {
            if ($overageSeconds <= $tier['max_overage']) {
                return $tier['penalty_pct'];
            }
        }
        return 50; // fallback max
    }

    /**
     * Applique une pénalité en pourcentage sur le score.
     * Ex: score=14, penaltyPct=15 → 14 * 0.85 = 11.9
     */
    private function applyPercentPenalty(float $score, int $penaltyPct): float
    {
        return round($score * (1 - $penaltyPct / 100), 2);
    }

    /**
     * Applique une pénalité fixe en points.
     * Ex: score=14, flatPenalty=10 → 14 * 0.90 = 12.6
     */
    private function applyFlatPenalty(float $score, int $penaltyPct): float
    {
        return round($score * (1 - $penaltyPct / 100), 2);
    }

    /**
     * Construit le tableau de rapport complet.
     */
    private function buildReport(
        bool   $isValid,
        bool   $isSuspicious,
        int    $elapsed,
        int    $allowed,
        float  $rawScore,
        string $verdict,
        string $verdictLabel,
        string $verdictColor,
        int    $overageSeconds = 0,
        int    $penaltyPct     = 0,
        float  $adjustedScore  = 0.0
    ): array {
        if ($adjustedScore === 0.0 && $penaltyPct === 0) {
            $adjustedScore = $rawScore;
        }

        $timeRatio = $allowed > 0 ? round($elapsed / $allowed, 3) : 0;

        return [
            'isValid'        => $isValid,
            'isSuspicious'   => $isSuspicious,
            'elapsedSeconds' => $elapsed,
            'allowedSeconds' => $allowed,
            'overageSeconds' => $overageSeconds,
            'timeRatio'      => $timeRatio,
            'timeUsedPct'    => round(min($timeRatio * 100, 100), 1),
            'penaltyPct'     => $penaltyPct,
            'rawScore'       => $rawScore,
            'adjustedScore'  => $adjustedScore,
            'verdict'        => $verdict,
            'verdictLabel'   => $verdictLabel,
            'verdictColor'   => $verdictColor,
        ];
    }
}

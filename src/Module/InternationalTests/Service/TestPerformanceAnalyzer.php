<?php

namespace App\Module\InternationalTests\Service;

use App\Module\InternationalTests\Entity\MockTest;
use App\Module\InternationalTests\Entity\TestResult;
use App\Module\InternationalTests\Repository\TestResultRepository;
use App\Module\UserManagement\Entity\User;

/**
 * ─────────────────────────────────────────────────────────────────
 * TestPerformanceAnalyzer — Métier Avancé #1
 * ─────────────────────────────────────────────────────────────────
 *
 * Service qui calcule une analyse de performance multi-dimensionnelle
 * pour un utilisateur à partir de tous ses résultats de tests.
 *
 * Responsabilités :
 *  - Calcul des scores moyens par niveau, par type et par catégorie
 *  - Détection de la progression dans le temps (amélioration / régression)
 *  - Identification des sections faibles sur l'ensemble des tests
 *  - Calcul d'un indice de régularité (consistance du niveau)
 *  - Génération d'un rapport de recommandations personnalisées
 *  - Comparaison du score utilisateur vs moyenne plateforme
 */
class TestPerformanceAnalyzer
{
    // Seuils de classification des performances
    private const EXCELLENT_THRESHOLD = 16.0;
    private const GOOD_THRESHOLD      = 12.0;
    private const AVERAGE_THRESHOLD   =  8.0;
    private const TOTAL_SCORE         = 20.0;
    private const PASS_SCORE          = 10.0;

    // Nombre minimum de tests pour calculer une tendance fiable
    private const MIN_TESTS_FOR_TREND = 3;

    public function __construct(
        private readonly TestResultRepository $resultRepository
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // MÉTHODE PRINCIPALE — rapport complet
    // ═══════════════════════════════════════════════════════════════

    /**
     * Génère le rapport de performance complet pour un utilisateur.
     * C'est la méthode appelée depuis le controller.
     *
     * @return array{
     *   globalStats: array,
     *   byLevel: array,
     *   byTestType: array,
     *   byCategory: array,
     *   progressionTrend: array,
     *   weaknessSections: array,
     *   consistencyIndex: float,
     *   performanceGrade: string,
     *   recommendations: array,
     *   platformComparison: array,
     *   streakData: array
     * }
     */
    public function analyze(User $user): array
    {
        $results = $this->resultRepository->findByUser($user->getId());

        if (empty($results)) {
            return $this->buildEmptyReport();
        }

        return [
            'globalStats'        => $this->computeGlobalStats($results),
            'byLevel'            => $this->computeByLevel($results),
            'byTestType'         => $this->computeByTestType($results),
            'byCategory'         => $this->computeByCategory($results),
            'progressionTrend'   => $this->computeProgressionTrend($results),
            'weaknessSections'   => $this->computeWeaknessSections($results),
            'consistencyIndex'   => $this->computeConsistencyIndex($results),
            'performanceGrade'   => $this->computePerformanceGrade($results),
            'recommendations'    => $this->generateRecommendations($results),
            'platformComparison' => $this->computePlatformComparison($results),
            'streakData'         => $this->computeStreakData($results),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // STATS GLOBALES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Calcule les statistiques globales de base.
     */
    private function computeGlobalStats(array $results): array
    {
        $scores     = array_map(fn(TestResult $r) => $r->getOverallScore(), $results);
        $total      = count($results);
        $sum        = array_sum($scores);
        $avgScore   = $total > 0 ? round($sum / $total, 2) : 0;
        $bestScore  = !empty($scores) ? max($scores) : 0;
        $worstScore = !empty($scores) ? min($scores) : 0;
        $passed     = count(array_filter($scores, fn($s) => $s >= self::PASS_SCORE));
        $passRate   = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

        // Variance : mesure la dispersion des scores
        $variance = 0;
        if ($total > 1) {
            $squaredDiffs = array_map(fn($s) => pow($s - $avgScore, 2), $scores);
            $variance     = round(array_sum($squaredDiffs) / $total, 2);
        }

        return [
            'total'      => $total,
            'avgScore'   => $avgScore,
            'bestScore'  => $bestScore,
            'worstScore' => $worstScore,
            'passed'     => $passed,
            'failed'     => $total - $passed,
            'passRate'   => $passRate,
            'variance'   => $variance,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // ANALYSE PAR NIVEAU
    // ═══════════════════════════════════════════════════════════════

    /**
     * Calcule les stats par niveau (Beginner / Intermediate / Advanced).
     * Inclut le score moyen, le meilleur score et le taux de réussite.
     */
    private function computeByLevel(array $results): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $level = $result->getMockTest()?->getLevel() ?? 'Unknown';
            if (!isset($grouped[$level])) {
                $grouped[$level] = ['scores' => [], 'total' => 0, 'passed' => 0];
            }
            $score = $result->getOverallScore();
            $grouped[$level]['scores'][] = $score;
            $grouped[$level]['total']++;
            if ($score >= self::PASS_SCORE) {
                $grouped[$level]['passed']++;
            }
        }

        $byLevel = [];
        // Ordonner par niveau croissant
        $order = [MockTest::LEVEL_BEGINNER, MockTest::LEVEL_INTERMEDIATE, MockTest::LEVEL_ADVANCED];

        foreach ($order as $level) {
            if (!isset($grouped[$level])) {
                $byLevel[$level] = [
                    'level'    => $level,
                    'total'    => 0,
                    'avgScore' => null,
                    'best'     => null,
                    'passRate' => null,
                    'status'   => 'not_started',
                ];
                continue;
            }

            $data     = $grouped[$level];
            $avg      = round(array_sum($data['scores']) / $data['total'], 1);
            $best     = max($data['scores']);
            $passRate = round(($data['passed'] / $data['total']) * 100, 1);

            $byLevel[$level] = [
                'level'    => $level,
                'total'    => $data['total'],
                'avgScore' => $avg,
                'best'     => $best,
                'passRate' => $passRate,
                'status'   => $best >= self::PASS_SCORE ? 'passed' : 'failed',
            ];
        }

        return $byLevel;
    }

    // ═══════════════════════════════════════════════════════════════
    // ANALYSE PAR TYPE DE TEST (GMAT, IELTS, DELF...)
    // ═══════════════════════════════════════════════════════════════

    private function computeByTestType(array $results): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $type = $result->getMockTest()?->getTestType() ?? 'Unknown';
            if (!isset($grouped[$type])) {
                $grouped[$type] = ['scores' => [], 'count' => 0];
            }
            $grouped[$type]['scores'][] = $result->getOverallScore();
            $grouped[$type]['count']++;
        }

        $byType = [];
        foreach ($grouped as $type => $data) {
            $avg = round(array_sum($data['scores']) / $data['count'], 1);
            $byType[] = [
                'type'     => $type,
                'count'    => $data['count'],
                'avgScore' => $avg,
                'best'     => max($data['scores']),
                'label'    => $this->classifyScore($avg),
            ];
        }

        // Trier par nombre de tests décroissant
        usort($byType, fn($a, $b) => $b['count'] <=> $a['count']);

        return $byType;
    }

    // ═══════════════════════════════════════════════════════════════
    // ANALYSE PAR CATÉGORIE (QCM, Writing, Speaking, Listening)
    // ═══════════════════════════════════════════════════════════════

    private function computeByCategory(array $results): array
    {
        $grouped = [];

        foreach ($results as $result) {
            $category = $result->getMockTest()?->getTestCategory() ?? 'QCM';
            if (!isset($grouped[$category])) {
                $grouped[$category] = ['scores' => [], 'count' => 0];
            }
            $grouped[$category]['scores'][] = $result->getOverallScore();
            $grouped[$category]['count']++;
        }

        $byCategory = [];
        foreach ($grouped as $category => $data) {
            $avg = round(array_sum($data['scores']) / $data['count'], 1);
            $byCategory[] = [
                'category' => $category,
                'count'    => $data['count'],
                'avgScore' => $avg,
                'pct'      => round(($avg / self::TOTAL_SCORE) * 100),
                'label'    => $this->classifyScore($avg),
            ];
        }

        return $byCategory;
    }

    // ═══════════════════════════════════════════════════════════════
    // TENDANCE DE PROGRESSION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Analyse si l'utilisateur s'améliore, stagne ou régresse.
     *
     * Algorithme : régression linéaire simple sur les N derniers scores
     * triés par date. La pente (slope) indique la tendance.
     *
     * slope > 0.2  → amélioration
     * slope < -0.2 → régression
     * sinon        → stable
     */
    private function computeProgressionTrend(array $results): array
    {
        // Trier par date croissante
        $sorted = $results;
        usort($sorted, fn($a, $b) => $a->getDateTaken() <=> $b->getDateTaken());

        $scores = array_map(fn(TestResult $r) => $r->getOverallScore(), $sorted);
        $n      = count($scores);

        // Historique pour le graphique (max 10 derniers)
        $history = [];
        $recent  = array_slice($sorted, -10);
        foreach ($recent as $r) {
            $history[] = [
                'date'  => $r->getDateTaken()?->format('d/m'),
                'score' => $r->getOverallScore(),
                'test'  => $r->getMockTest()?->getTitle() ?? '',
            ];
        }

        if ($n < self::MIN_TESTS_FOR_TREND) {
            return [
                'trend'        => 'insufficient_data',
                'slope'        => 0,
                'trendLabel'   => 'Insufficient data',
                'trendPercent' => 0,
                'history'      => $history,
            ];
        }

        // Régression linéaire : y = a*x + b
        $xMean = ($n - 1) / 2;
        $yMean = array_sum($scores) / $n;

        $numerator   = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator   += ($i - $xMean) * ($scores[$i] - $yMean);
            $denominator += pow($i - $xMean, 2);
        }

        $slope = $denominator > 0 ? round($numerator / $denominator, 3) : 0;

        // Taux de progression en % par rapport au score total
        $trendPercent = round(($slope / self::TOTAL_SCORE) * 100, 1);

        if ($slope > 0.2) {
            $trend      = 'improving';
            $trendLabel = 'Improving ↗';
        } elseif ($slope < -0.2) {
            $trend      = 'declining';
            $trendLabel = 'Declining ↘';
        } else {
            $trend      = 'stable';
            $trendLabel = 'Stable →';
        }

        return [
            'trend'        => $trend,
            'slope'        => $slope,
            'trendLabel'   => $trendLabel,
            'trendPercent' => abs($trendPercent),
            'history'      => $history,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTIONS LES PLUS FAIBLES (agrégation de tous les tests)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Agrège les aiWeaknessReport de tous les tests passés
     * pour identifier les sections globalement faibles.
     */
    private function computeWeaknessSections(array $results): array
    {
        $aggregated = [];

        foreach ($results as $result) {
            $report = $result->getAiWeaknessReport();
            if (empty($report)) continue;

            foreach ($report as $section) {
                $name = $section['section'] ?? 'Unknown';
                if (!isset($aggregated[$name])) {
                    $aggregated[$name] = ['correct' => 0, 'total' => 0, 'appearances' => 0];
                }
                $aggregated[$name]['correct']     += $section['correct'] ?? 0;
                $aggregated[$name]['total']        += $section['total'] ?? 0;
                $aggregated[$name]['appearances']++;
            }
        }

        $sections = [];
        foreach ($aggregated as $name => $data) {
            $pct = $data['total'] > 0
                ? round(($data['correct'] / $data['total']) * 100)
                : 0;

            $sections[] = [
                'section'     => $name,
                'score'       => $pct,
                'correct'     => $data['correct'],
                'total'       => $data['total'],
                'appearances' => $data['appearances'],
                'status'      => $pct >= 60 ? 'good' : ($pct >= 40 ? 'average' : 'weak'),
            ];
        }

        // Trier par score croissant (les plus faibles en premier)
        usort($sections, fn($a, $b) => $a['score'] <=> $b['score']);

        return $sections;
    }

    // ═══════════════════════════════════════════════════════════════
    // INDICE DE RÉGULARITÉ (CONSISTANCE)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Calcule un indice de 0 à 100 qui mesure la consistance du niveau.
     *
     * Un utilisateur régulier a des scores proches les uns des autres.
     * Formule : 100 - (écart-type normalisé × 5)
     * Plus l'écart-type est faible, plus le score de consistance est élevé.
     */
    private function computeConsistencyIndex(array $results): float
    {
        if (count($results) < 2) return 100.0;

        $scores = array_map(fn(TestResult $r) => $r->getOverallScore(), $results);
        $avg    = array_sum($scores) / count($scores);

        $squaredDiffs = array_map(fn($s) => pow($s - $avg, 2), $scores);
        $stdDev       = sqrt(array_sum($squaredDiffs) / count($scores));

        // Normaliser : un écart-type de 5/20 = très irrégulier
        $consistency = max(0, round(100 - ($stdDev / self::TOTAL_SCORE * 500), 1));

        return min(100.0, $consistency);
    }

    // ═══════════════════════════════════════════════════════════════
    // NOTE GLOBALE DE PERFORMANCE (A+ à F)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Attribue une note globale basée sur le score moyen pondéré
     * par le niveau des tests (Advanced vaut plus que Beginner).
     */
    private function computePerformanceGrade(array $results): string
    {
        if (empty($results)) return 'N/A';

        $weightedSum   = 0;
        $totalWeights  = 0;
        $levelWeights  = [
            MockTest::LEVEL_BEGINNER     => 1,
            MockTest::LEVEL_INTERMEDIATE => 2,
            MockTest::LEVEL_ADVANCED     => 3,
        ];

        foreach ($results as $result) {
            $level  = $result->getMockTest()?->getLevel() ?? MockTest::LEVEL_BEGINNER;
            $weight = $levelWeights[$level] ?? 1;
            $weightedSum  += $result->getOverallScore() * $weight;
            $totalWeights += $weight;
        }

        $weightedAvg = $totalWeights > 0 ? $weightedSum / $totalWeights : 0;
        $pct         = ($weightedAvg / self::TOTAL_SCORE) * 100;

        return match(true) {
            $pct >= 95 => 'A+',
            $pct >= 85 => 'A',
            $pct >= 75 => 'B+',
            $pct >= 65 => 'B',
            $pct >= 55 => 'C+',
            $pct >= 50 => 'C',
            $pct >= 40 => 'D',
            default    => 'F',
        };
    }

    // ═══════════════════════════════════════════════════════════════
    // RECOMMANDATIONS PERSONNALISÉES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Génère une liste de recommandations basées sur les données de performance.
     * Chaque recommandation a un type (warning/info/success) et un message.
     */
    private function generateRecommendations(array $results): array
    {
        $recommendations = [];
        $globalStats     = $this->computeGlobalStats($results);
        $weaknesses      = $this->computeWeaknessSections($results);
        $trend           = $this->computeProgressionTrend($results);
        $byLevel         = $this->computeByLevel($results);
        $consistency     = $this->computeConsistencyIndex($results);

        // Recommandation 1 : sections faibles
        $weakSections = array_filter($weaknesses, fn($s) => $s['status'] === 'weak');
        if (!empty($weakSections)) {
            $names = array_map(fn($s) => $s['section'], array_slice($weakSections, 0, 2));
            $recommendations[] = [
                'type'    => 'warning',
                'icon'    => '⚠️',
                'message' => 'Focus on: ' . implode(' and ', $names) . ' — your weakest sections.',
            ];
        }

        // Recommandation 2 : tendance en déclin
        if ($trend['trend'] === 'declining') {
            $recommendations[] = [
                'type'    => 'warning',
                'icon'    => '📉',
                'message' => 'Your scores are declining. Consider reviewing fundamentals.',
            ];
        }

        // Recommandation 3 : progression positive
        if ($trend['trend'] === 'improving') {
            $recommendations[] = [
                'type'    => 'success',
                'icon'    => '📈',
                'message' => 'Great progression! Keep up the rhythm.',
            ];
        }

        // Recommandation 4 : irrégularité
        if ($consistency < 50) {
            $recommendations[] = [
                'type'    => 'info',
                'icon'    => '📊',
                'message' => 'Your scores are inconsistent. Try to study more regularly.',
            ];
        }

        // Recommandation 5 : niveau à débloquer
        if (isset($byLevel[MockTest::LEVEL_BEGINNER]) &&
            $byLevel[MockTest::LEVEL_BEGINNER]['best'] >= self::PASS_SCORE &&
            ($byLevel[MockTest::LEVEL_INTERMEDIATE]['total'] ?? 0) === 0) {
            $recommendations[] = [
                'type'    => 'info',
                'icon'    => '🔓',
                'message' => 'You\'ve unlocked Intermediate! Start the next level.',
            ];
        }

        // Recommandation 6 : taux de réussite faible
        if ($globalStats['passRate'] < 40 && $globalStats['total'] >= 3) {
            $recommendations[] = [
                'type'    => 'warning',
                'icon'    => '🎯',
                'message' => 'Pass rate below 40%. Practice more before retaking tests.',
            ];
        }

        // Recommandation 7 : excellent score moyen
        if ($globalStats['avgScore'] >= self::EXCELLENT_THRESHOLD) {
            $recommendations[] = [
                'type'    => 'success',
                'icon'    => '🏆',
                'message' => 'Excellent average score! You\'re ready for advanced challenges.',
            ];
        }

        return $recommendations;
    }

    // ═══════════════════════════════════════════════════════════════
    // COMPARAISON PLATEFORME
    // ═══════════════════════════════════════════════════════════════

    /**
     * Compare le score moyen de l'utilisateur avec la moyenne globale
     * de la plateforme (tous les utilisateurs confondus).
     */
    private function computePlatformComparison(array $userResults): array
    {
        $platformStats = $this->resultRepository->getStatistics();
        $platformAvg   = $platformStats['avgScore'] ?? 0;

        $userScores = array_map(fn(TestResult $r) => $r->getOverallScore(), $userResults);
        $userAvg    = count($userScores) > 0
            ? round(array_sum($userScores) / count($userScores), 2)
            : 0;

        $diff       = round($userAvg - $platformAvg, 2);
        $percentile = $platformAvg > 0
            ? round(($userAvg / self::TOTAL_SCORE) * 100, 1)
            : 0;

        return [
            'userAvg'      => $userAvg,
            'platformAvg'  => $platformAvg,
            'diff'         => $diff,
            'diffLabel'    => $diff >= 0 ? '+' . $diff . ' above average' : $diff . ' below average',
            'isAboveAvg'   => $diff >= 0,
            'percentile'   => $percentile,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // STREAK (SÉRIE DE JOURS CONSÉCUTIFS)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Calcule la série actuelle et la plus longue série de jours
     * consécutifs où l'utilisateur a passé au moins un test.
     */
    private function computeStreakData(array $results): array
    {
        if (empty($results)) {
            return ['current' => 0, 'longest' => 0, 'lastActivity' => null];
        }

        // Extraire les dates uniques (par jour)
        $dates = [];
        foreach ($results as $result) {
            $date = $result->getDateTaken()?->format('Y-m-d');
            if ($date) {
                $dates[$date] = true;
            }
        }

        $dates = array_keys($dates);
        sort($dates);

        $today       = (new \DateTime())->format('Y-m-d');
        $yesterday   = (new \DateTime('-1 day'))->format('Y-m-d');
        $lastDate    = end($dates);

        // Streak actuelle
        $currentStreak = 0;
        if ($lastDate === $today || $lastDate === $yesterday) {
            $currentStreak = 1;
            $checkDate     = new \DateTime($lastDate);

            for ($i = count($dates) - 2; $i >= 0; $i--) {
                $checkDate->modify('-1 day');
                if ($dates[$i] === $checkDate->format('Y-m-d')) {
                    $currentStreak++;
                } else {
                    break;
                }
            }
        }

        // Plus longue streak
        $longestStreak = 1;
        $tempStreak    = 1;
        for ($i = 1; $i < count($dates); $i++) {
            $prev = new \DateTime($dates[$i - 1]);
            $curr = new \DateTime($dates[$i]);
            $diff = (int) $prev->diff($curr)->days;

            if ($diff === 1) {
                $tempStreak++;
                $longestStreak = max($longestStreak, $tempStreak);
            } else {
                $tempStreak = 1;
            }
        }

        return [
            'current'      => $currentStreak,
            'longest'      => $longestStreak,
            'lastActivity' => $lastDate,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function classifyScore(float $score): string
    {
        return match(true) {
            $score >= self::EXCELLENT_THRESHOLD => 'excellent',
            $score >= self::GOOD_THRESHOLD      => 'good',
            $score >= self::AVERAGE_THRESHOLD   => 'average',
            default                             => 'poor',
        };
    }

    private function buildEmptyReport(): array
    {
        return [
            'globalStats'        => ['total' => 0, 'avgScore' => 0, 'bestScore' => 0, 'worstScore' => 0, 'passed' => 0, 'failed' => 0, 'passRate' => 0, 'variance' => 0],
            'byLevel'            => [],
            'byTestType'         => [],
            'byCategory'         => [],
            'progressionTrend'   => ['trend' => 'insufficient_data', 'slope' => 0, 'trendLabel' => 'No data', 'trendPercent' => 0, 'history' => []],
            'weaknessSections'   => [],
            'consistencyIndex'   => 0.0,
            'performanceGrade'   => 'N/A',
            'recommendations'    => [],
            'platformComparison' => ['userAvg' => 0, 'platformAvg' => 0, 'diff' => 0, 'diffLabel' => '', 'isAboveAvg' => false, 'percentile' => 0],
            'streakData'         => ['current' => 0, 'longest' => 0, 'lastActivity' => null],
        ];
    }
}

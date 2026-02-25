<?php

namespace App\Module\Support\Service;

use App\Module\Support\Entity\AuditLog;
use App\Module\Support\Repository\AuditLogRepository;
use App\Module\Support\Repository\ReclamationRepository;
use App\Module\UserManagement\Repository\UserRepository;

class SupportStatsService
{
    public function __construct(
        private ReclamationRepository $reclamationRepository,
        private AuditLogRepository    $auditLogRepository,
        private UserRepository        $userRepository
    ) {}

    public function getFullStats(): array
    {
        $all = $this->reclamationRepository->findAll();

        // ── Statistiques de base ──
        $total      = count($all);
        $pending    = count(array_filter($all, fn($r) => $r->getStatus() === 'PENDING'));
        $inProgress = count(array_filter($all, fn($r) => $r->getStatus() === 'IN_PROGRESS'));
        $resolved   = count(array_filter($all, fn($r) => $r->getStatus() === 'RESOLVED'));
        $closed     = count(array_filter($all, fn($r) => $r->getStatus() === 'CLOSED'));

        // ── Taux de résolution ──
        $resolutionRate = $total > 0 ? round(($resolved + $closed) / $total * 100, 1) : 0;

        // ── Temps moyen de résolution ──
        $avgResolutionHours = $this->calculateAvgResolutionTime($all);

        // ── Tickets par sujet ──
        $bySubject = [];
        foreach ($all as $r) {
            $subject = $r->getSubject() ?? 'autre';
            $bySubject[$subject] = ($bySubject[$subject] ?? 0) + 1;
        }
        arsort($bySubject);

        // ── Tickets des 7 derniers jours ──
        $last7Days = $this->getTicketsPerDay(7);

        // ── Tickets des 30 derniers jours ──
        $last30Days = $this->getTicketsLast30Days($all);

        // ── Stats audit ──
        $totalBans      = $this->auditLogRepository->countByAction(AuditLog::ACTION_BANNED);
        $totalSpam      = $this->auditLogRepository->countByAction(AuditLog::ACTION_SPAM_BLOCKED);
        $recentActivity = $this->auditLogRepository->findRecent(10);

        // ── Utilisateurs avec le plus de réclamations ──
        $topUsers = $this->getTopUsers($all, 5);

        return [
            // Base
            'total'              => $total,
            'pending'            => $pending,
            'in_progress'        => $inProgress,
            'resolved'           => $resolved,
            'closed'             => $closed,
            'resolution_rate'    => $resolutionRate,
            'avg_resolution_h'   => $avgResolutionHours,

            // Distribution
            'by_subject'         => $bySubject,
            'last_7_days'        => $last7Days,
            'last_30_days'       => $last30Days,

            // Sécurité
            'total_bans'         => $totalBans,
            'total_spam_blocked' => $totalSpam,

            // Activité
            'recent_activity'    => $recentActivity,
            'top_users'          => $topUsers,
        ];
    }

    private function calculateAvgResolutionTime(array $reclamations): float
    {
        $times = [];
        foreach ($reclamations as $r) {
            if (in_array($r->getStatus(), ['RESOLVED', 'CLOSED']) && $r->getSubmittedAt()) {
                $responses = $r->getResponses();
                if ($responses->count() > 0) {
                    $firstResponse = $responses->first();
                    $diff = $r->getSubmittedAt()->diff($firstResponse->getRespondedAt());
                    $times[] = ($diff->days * 24) + $diff->h;
                }
            }
        }
        return count($times) > 0 ? round(array_sum($times) / count($times), 1) : 0;
    }

    private function getTicketsPerDay(int $days): array
    {
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date  = new \DateTime("-{$i} days");
            $label = $date->format('d/m');
            $result[$label] = 0;
        }

        $since = new \DateTime("-{$days} days");
        $tickets = $this->reclamationRepository->findAll();

        foreach ($tickets as $t) {
            if ($t->getSubmittedAt() >= $since) {
                $label = $t->getSubmittedAt()->format('d/m');
                if (isset($result[$label])) {
                    $result[$label]++;
                }
            }
        }

        return $result;
    }

    private function getTicketsLast30Days(array $all): int
    {
        $since = new \DateTime('-30 days');
        return count(array_filter($all, fn($r) => $r->getSubmittedAt() >= $since));
    }

    private function getTopUsers(array $reclamations, int $limit): array
    {
        $userCounts = [];
        foreach ($reclamations as $r) {
            if ($r->getUser()) {
                $email = $r->getUser()->getEmail();
                $userCounts[$email] = ($userCounts[$email] ?? 0) + 1;
            }
        }
        arsort($userCounts);
        return array_slice($userCounts, 0, $limit, true);
    }
}
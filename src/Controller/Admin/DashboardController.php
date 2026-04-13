<?php

namespace App\Controller\Admin;

use App\Module\UserManagement\Repository\UserRepository;
use App\Module\UserManagement\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private UserRepository         $userRepository,
        private EntityManagerInterface $entityManager,
        private StripeService          $stripeService,
    ) {}

    #[Route('', name: 'admin_index')]
    public function redirectToDashboard(): Response
    {
        return $this->redirectToRoute('admin_dashboard');
    }

    // =========================================================
    //  MAIN DASHBOARD
    // =========================================================

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(Request $request): Response
    {
        // ── Date range ────────────────────────────────────────────────
        // Default start = earliest user ever registered so bulk SQL imports
        // always appear on the chart from day one — no data is ever hidden.
        $earliest  = $this->userRepository->getEarliestRegistrationDate();
        $defaultStart = $earliest
            ? \DateTime::createFromImmutable($earliest)
            : new \DateTime('-30 days');

        $startDate = $request->query->get('start_date')
            ? new \DateTime($request->query->get('start_date'))
            : $defaultStart;

        $endDate = $request->query->get('end_date')
            ? new \DateTime($request->query->get('end_date'))
            : new \DateTime();

        // ── User statistics ───────────────────────────────────────────
        $userStats = $this->getUserStatistics($startDate, $endDate);

        // ── Content (safe — returns 0 when entity class doesn't exist) ─
        $courseCount   = $this->getEntityCount('App\Module\PedagogicalContent\Entity\Course') ?? 0;
        $exerciseCount = $this->getEntityCount('App\Module\ExercisesQuizzes\Entity\Exercise') ?? 0;
        $courseStats   = [
            'published' => $this->getEntityCountByField('App\Module\PedagogicalContent\Entity\Course', 'status', 'published') ?? 0,
            'draft'     => $this->getEntityCountByField('App\Module\PedagogicalContent\Entity\Course', 'status', 'draft') ?? 0,
        ];

        // ── Forum ─────────────────────────────────────────────────────
        $forumPostCount  = $this->getEntityCount('App\Entity\ForumPost') ?? 0;
        $forumReplyCount = $this->getEntityCount('App\Entity\ForumReply') ?? 0;
        $forumStats      = $this->getForumStatistics();

        // ── Enrollments / ratings ─────────────────────────────────────
        $totalEnrollments = $this->getEntityCount('App\Module\PedagogicalContent\Entity\Enrollment') ?? 0;
        $averageRating    = $this->getAverageRating();

        // ── Recent signups ────────────────────────────────────────────
        // FIX: findBy() lazy-loads relations when the template iterates users.
        // findRecentRegistrations() eager-loads learningStats + userLanguages in one query.
        $recentRegistrations = $this->userRepository->findRecentRegistrations(10);

        // ── Growth chart — auto-pick granularity ──────────────────────
        // ≤ 31 days  → daily   |   > 31 days → monthly
        $diffDays       = max(1, (int) $startDate->diff($endDate)->days);
        $userGrowthData = $diffDays <= 31
            ? $this->userRepository->getDailyChartData($startDate, $endDate)
            : $this->userRepository->getMonthlyChartData((int) ceil($diffDays / 30));

        // ── Revenue & plan distribution ───────────────────────────────
        $revenueData      = $this->getRevenueChartData();
        $planDistribution = $this->userRepository->getPlanDistribution();
        $revenueStats     = $this->getRevenueStatistics();

        // ── Alert counts ──────────────────────────────────────────────
        $pendingUsersCount   = $this->userRepository->countByStatus('pending');
        $flaggedContentCount = 0;
        $openTicketsCount    = 0;

        // ── System health ─────────────────────────────────────────────
        $systemHealth = $this->getSystemHealthMetrics();

        return $this->render('admin/dashboard/index.html.twig', [
            'userStats'           => $userStats,
            'courseCount'         => $courseCount,
            'exerciseCount'       => $exerciseCount,
            'courseStats'         => $courseStats,
            'forumPostCount'      => $forumPostCount,
            'forumReplyCount'     => $forumReplyCount,
            'forumStats'          => $forumStats,
            'averageRating'       => $averageRating,
            'totalEnrollments'    => $totalEnrollments,
            'recentRegistrations' => $recentRegistrations,
            'userGrowthData'      => $userGrowthData,
            'revenueData'         => $revenueData,
            'planDistribution'    => $planDistribution,
            'revenueStats'        => $revenueStats,
            'pendingUsersCount'   => $pendingUsersCount,
            'flaggedContentCount' => $flaggedContentCount,
            'openTicketsCount'    => $openTicketsCount,
            'systemHealth'        => $systemHealth,
            'dateRange'           => ['start' => $startDate, 'end' => $endDate],
        ]);
    }

    // =========================================================
    //  CHART DATA — AJAX endpoint
    // =========================================================

    #[Route('/dashboard/chart-data', name: 'admin_dashboard_chart_data')]
    public function getChartData(Request $request): JsonResponse
    {
        $period = $request->query->get('period', '7d');

        return match ($period) {
            '7d'  => $this->json($this->userRepository->getDailyChartData(
                new \DateTime('-7 days'), new \DateTime())),
            '30d' => $this->json($this->userRepository->getDailyChartData(
                new \DateTime('-30 days'), new \DateTime())),
            '90d' => $this->json($this->userRepository->getMonthlyChartData(3)),
            '1y'  => $this->json($this->userRepository->getMonthlyChartData(12)),
            'all' => $this->json($this->getAllTimeChartData()),
            default => $this->json($this->userRepository->getDailyChartData(
                new \DateTime('-7 days'), new \DateTime())),
        };
    }

    // =========================================================
    //  CSV EXPORT
    // =========================================================

    #[Route('/dashboard/export', name: 'admin_dashboard_export')]
    public function export(): Response
    {
        $data = $this->generateReportData();

        $response = new Response($this->generateCSV($data));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="dashboard-report-' . date('Y-m-d') . '.csv"'
        );
        return $response;
    }

    // =========================================================
    //  PRIVATE HELPERS
    // =========================================================

    private function getAllTimeChartData(): array
    {
        $earliest = $this->userRepository->getEarliestRegistrationDate();
        if (!$earliest) {
            return ['labels' => [], 'data' => [], 'total' => 0];
        }
        $months = max(1, (int) ceil($earliest->diff(new \DateTimeImmutable())->days / 30));
        return $this->userRepository->getMonthlyChartData($months);
    }

    private function getEntityCount(string $class): ?int
    {
        try {
            if (!class_exists($class)) return null;
            return $this->entityManager->getRepository($class)->count([]);
        } catch (\Exception) { return null; }
    }

    private function getEntityCountByField(string $class, string $field, mixed $value): ?int
    {
        try {
            if (!class_exists($class)) return null;
            return $this->entityManager->getRepository($class)->count([$field => $value]);
        } catch (\Exception) { return null; }
    }

    private function getUserStatistics(\DateTime $startDate, \DateTime $endDate): array
    {
        $total     = $this->userRepository->count([]);
        $active    = $this->userRepository->countByStatus('active');
        $suspended = $this->userRepository->countByStatus('suspended');
        $deleted   = $this->userRepository->countByStatus('deleted');

        $payingPremium = $this->userRepository->countActivePayingSubscribers();
        $adminGranted  = $this->userRepository->countAdminGrantedPremium();
        $premium       = $payingPremium + $adminGranted;

        // Period trend: compare registrations this period vs the same-length period before
        $periodDays  = max(1, (int) $startDate->diff($endDate)->days);
        $prevStart   = (clone $startDate)->modify("-{$periodDays} days");
        $currentNew  = $this->userRepository->countBetweenDates($startDate, $endDate);
        $previousNew = $this->userRepository->countBetweenDates($prevStart, $startDate);
        $trend       = $previousNew > 0
            ? round((($currentNew - $previousNew) / $previousNew) * 100, 1)
            : ($currentNew > 0 ? 100.0 : 0.0);

        return [
            'total'         => $total,
            'active'        => $active,
            'suspended'     => $suspended,
            'deleted'       => $deleted,
            'premium'       => $premium,
            'payingPremium' => $payingPremium,
            'adminGranted'  => $adminGranted,
            'totalTrend'    => $trend,
            'newInPeriod'   => $currentNew,
            'newLast7'      => $this->userRepository->countRecentRegistrations(7),
            'newLast30'     => $this->userRepository->countRecentRegistrations(30),
        ];
    }

    private function getForumStatistics(): array
    {
        try {
            $repo  = $this->entityManager->getRepository('App\Module\Forum\Entity\ForumPost');
            $count = (int) $repo->createQueryBuilder('fp')
                ->select('COUNT(fp.id)')
                ->where('fp.createdAt >= :today')
                ->setParameter('today', new \DateTime('today'))
                ->getQuery()->getSingleScalarResult();
            return ['todayPosts' => $count, 'mostActiveUser' => 'N/A'];
        } catch (\Exception) {
            return ['todayPosts' => 0, 'mostActiveUser' => 'N/A'];
        }
    }

    private function getAverageRating(): ?float
    {
        // Returns null until a rating entity exists — template handles gracefully
        return null;
    }

    private function getRevenueChartData(): array
    {
        $monthly    = $this->userRepository->countActivePayingSubscribersByPlan('MONTHLY');
        $yearly     = $this->userRepository->countActivePayingSubscribersByPlan('YEARLY');
        $monthlyMRR = round($monthly * 9.99, 2);
        $yearlyMRR  = round($yearly * (99.99 / 12), 2);
        return [
            'labels' => ['Monthly ($9.99)', 'Yearly ($8.33/mo)', 'Free'],
            'data'   => [$monthlyMRR, $yearlyMRR, 0],
        ];
    }

    private function getRevenueStatistics(): array
    {
        $now          = new \DateTime();
        $startOfMonth = new \DateTime('first day of this month midnight');
        $startOfLast  = (clone $startOfMonth)->modify('-1 month');

        $monthlyNow = $this->userRepository->countActivePayingSubscribersByPlan('MONTHLY');
        $yearlyNow  = $this->userRepository->countActivePayingSubscribersByPlan('YEARLY');
        $thisMonth  = round(($monthlyNow * 9.99) + ($yearlyNow * (99.99 / 12)), 2);

        $newThis = $this->userRepository->countNewPayingSubscribersBetween($startOfMonth, $now);
        $newLast = $this->userRepository->countNewPayingSubscribersBetween($startOfLast, $startOfMonth);
        $lastMonth = max(0, round($thisMonth - ($newThis * 9.99) + ($newLast * 9.99), 2));

        $growth = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : ($thisMonth > 0 ? 100.0 : 0.0);

        return [
            'thisMonth'    => $thisMonth,
            'lastMonth'    => $lastMonth,
            'growth'       => $growth,
            'monthlyCount' => $monthlyNow,
            'yearlyCount'  => $yearlyNow,
        ];
    }

    private function getSystemHealthMetrics(): array
    {
        return [
            'serverLoad'   => $this->getServerLoad(),
            'databaseSize' => $this->getDatabaseSize(),
            'cacheHitRate' => 95,
            'lastBackup'   => new \DateTime('-6 hours'),
        ];
    }

    private function getServerLoad(): int
    {
        if (function_exists('sys_getloadavg')) {
            return min((int) (sys_getloadavg()[0] * 10), 100);
        }
        return 12;
    }

    private function getDatabaseSize(): string
    {
        try {
            $conn = $this->entityManager->getConnection();
            $res  = $conn->executeQuery(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size
                 FROM information_schema.TABLES WHERE table_schema = :db",
                ['db' => $conn->getDatabase()]
            )->fetchAssociative();
            return ($res['size'] ?? '0') . ' MB';
        } catch (\Exception) { return 'N/A'; }
    }

    private function generateReportData(): array
    {
        $paying  = $this->userRepository->countActivePayingSubscribers();
        $admin   = $this->userRepository->countAdminGrantedPremium();
        $monthly = $this->userRepository->countActivePayingSubscribersByPlan('MONTHLY');
        $yearly  = $this->userRepository->countActivePayingSubscribersByPlan('YEARLY');
        $mrr     = round(($monthly * 9.99) + ($yearly * (99.99 / 12)), 2);
        $gross   = round(($monthly * 9.99) + ($yearly * 99.99), 2);
        $tax     = round($gross * 0.18, 2);

        return [
            ['Metric', 'Value'],
            ['--- Users ---', ''],
            ['Total',                   $this->userRepository->count([])],
            ['Active',                  $this->userRepository->countByStatus('active')],
            ['Suspended',               $this->userRepository->countByStatus('suspended')],
            ['Deleted',                 $this->userRepository->countByStatus('deleted')],
            ['New last 7 days',         $this->userRepository->countRecentRegistrations(7)],
            ['New last 30 days',        $this->userRepository->countRecentRegistrations(30)],
            ['--- Subscriptions ---', ''],
            ['Premium total',           $paying + $admin],
            ['Stripe subscribers',      $paying],
            ['Admin-granted',           $admin],
            ['Monthly plan',            $monthly],
            ['Yearly plan',             $yearly],
            ['--- Revenue ---', ''],
            ['MRR',                     $mrr],
            ['Gross billed',            $gross],
            ['Tax 18%',                 $tax],
            ['Net revenue',             round($gross - $tax, 2)],
            ['--- Content ---', ''],
            ['Courses',    $this->getEntityCount('App\Module\PedagogicalContent\Entity\Course') ?? 0],
            ['Exercises',  $this->getEntityCount('App\Module\ExercisesQuizzes\Entity\Exercise') ?? 0],
            ['Enrollments',$this->getEntityCount('App\Module\PedagogicalContent\Entity\Enrollment') ?? 0],
        ];
    }

    private function generateCSV(array $data): string
    {
        $fp = fopen('php://temp', 'r+');
        foreach ($data as $row) { fputcsv($fp, $row); }
        rewind($fp);
        $out = stream_get_contents($fp);
        fclose($fp);
        return $out;
    }
}

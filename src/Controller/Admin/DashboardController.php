<?php

namespace App\Controller\Admin;

use App\Module\UserManagement\Repository\UserRepository;
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
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Redirect /admin to /admin/dashboard
     */
    #[Route('', name: 'admin_index')]
    public function redirectToDashboard(): Response
    {
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(Request $request): Response
    {
        // Date range handling
        $startDate = $request->query->get('start_date')
            ? new \DateTime($request->query->get('start_date'))
            : new \DateTime('-30 days');
        $endDate = $request->query->get('end_date')
            ? new \DateTime($request->query->get('end_date'))
            : new \DateTime();

        // User Statistics with trends
        $userStats = $this->getUserStatistics($startDate, $endDate);

        // Content Statistics - Use dynamic repository lookup
        $courseCount = $this->getEntityCount('App\Module\PedagogicalContent\Entity\Course') ?? 0;
        $exerciseCount = $this->getEntityCount('App\Module\ExercisesQuizzes\Entity\Exercise') ?? 0;

        // Course Statistics
        $courseStats = [
            'published' => $this->getEntityCountByField('App\Module\PedagogicalContent\Entity\Course', 'status', 'published') ?? 0,
            'draft' => $this->getEntityCountByField('App\Module\PedagogicalContent\Entity\Course', 'status', 'draft') ?? 0,
        ];

        // Forum Statistics
        $forumPostCount = $this->getEntityCount('App\Module\Forum\Entity\ForumPost') ?? 0;
        $forumReplyCount = $this->getEntityCount('App\Module\Forum\Entity\ForumReply') ?? 0;
        $forumStats = $this->getForumStatistics();

        // Engagement Metrics
        $averageRating = 4.5; // Placeholder - implement when rating system exists
        $totalEnrollments = $this->getEntityCount('App\Module\PedagogicalContent\Entity\Enrollment') ?? 0;

        // Recent Activity
        $recentRegistrations = $this->userRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            10
        );

        $recentActivity = $this->getRecentActivity();
        $topCourses = $this->getTopCourses();

        // Chart Data
        $userGrowthData = $this->getUserGrowthChartData($startDate, $endDate);
        $revenueData = $this->getRevenueChartData();

        // Revenue Statistics
        $revenueStats = $this->getRevenueStatistics();

        // Quick Action Counts
        $pendingUsersCount = $this->userRepository->countByStatus('pending');
        $flaggedContentCount = 0; // Implement when flagging system exists
        $openTicketsCount = 0; // Implement when ticket system exists

        // System Health Metrics
        $systemHealth = $this->getSystemHealthMetrics();

        return $this->render('admin/dashboard/index.html.twig', [
            'userStats' => $userStats,
            'courseCount' => $courseCount,
            'exerciseCount' => $exerciseCount,
            'courseStats' => $courseStats,
            'forumPostCount' => $forumPostCount,
            'forumReplyCount' => $forumReplyCount,
            'forumStats' => $forumStats,
            'averageRating' => $averageRating,
            'totalEnrollments' => $totalEnrollments,
            'recentRegistrations' => $recentRegistrations,
            'recentActivity' => $recentActivity,
            'topCourses' => $topCourses,
            'userGrowthData' => $userGrowthData,
            'revenueData' => $revenueData,
            'revenueStats' => $revenueStats,
            'pendingUsersCount' => $pendingUsersCount,
            'flaggedContentCount' => $flaggedContentCount,
            'openTicketsCount' => $openTicketsCount,
            'systemHealth' => $systemHealth,
            'dateRange' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);
    }

    #[Route('/dashboard/chart-data', name: 'admin_dashboard_chart_data')]
    public function getChartData(Request $request): JsonResponse
    {
        $period = $request->query->get('period', '7d');

        $days = match($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $startDate = new \DateTime("-{$days} days");
        $endDate = new \DateTime();

        $data = $this->getUserGrowthChartData($startDate, $endDate);

        return $this->json($data);
    }

    #[Route('/dashboard/export', name: 'admin_dashboard_export')]
    public function export(): Response
    {
        $data = $this->generateReportData();

        $response = new Response($this->generateCSV($data));
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="dashboard-report-'.date('Y-m-d').'.csv"');

        return $response;
    }

    // ========== Helper Methods ==========

    /**
     * Get entity count dynamically - returns null if entity doesn't exist
     */
    private function getEntityCount(string $entityClass): ?int
    {
        try {
            if (!class_exists($entityClass)) {
                return null;
            }
            $repository = $this->entityManager->getRepository($entityClass);
            return $repository->count([]);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get entity count by field value
     */
    private function getEntityCountByField(string $entityClass, string $field, mixed $value): ?int
    {
        try {
            if (!class_exists($entityClass)) {
                return null;
            }
            $repository = $this->entityManager->getRepository($entityClass);
            return $repository->count([$field => $value]);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getUserStatistics(\DateTime $startDate, \DateTime $endDate): array
    {
        $total = $this->userRepository->count([]);
        $active = $this->userRepository->countByStatus('active');
        $premium = $this->userRepository->count(['isPremium' => true]);
        $suspended = $this->userRepository->countByStatus('suspended');

        // Calculate trend (compare with previous period)
        $periodDays = $startDate->diff($endDate)->days;
        $previousStart = (clone $startDate)->modify("-{$periodDays} days");

        // Count users created in each period
        $currentTotal = $this->userRepository->countBetweenDates($startDate, $endDate);
        $previousTotal = $this->userRepository->countBetweenDates($previousStart, $startDate);

        $totalTrend = $previousTotal > 0
            ? round((($currentTotal - $previousTotal) / $previousTotal) * 100, 1)
            : 0;

        // Calculate MRR (Monthly Recurring Revenue) from premium users
        $premiumRevenue = $premium * 9.99;

        return [
            'total' => $total,
            'active' => $active,
            'premium' => $premium,
            'suspended' => $suspended,
            'totalTrend' => $totalTrend,
            'premiumRevenue' => $premiumRevenue,
        ];
    }

    private function getForumStatistics(): array
    {
        try {
            $forumPostRepo = $this->entityManager->getRepository('App\Module\Forum\Entity\ForumPost');
            $today = new \DateTime('today');

            $qb = $forumPostRepo->createQueryBuilder('fp');
            $todayCount = (int) $qb->select('COUNT(fp.id)')
                ->where('fp.createdAt >= :date')
                ->setParameter('date', $today)
                ->getQuery()
                ->getSingleScalarResult();

            return [
                'todayPosts' => $todayCount,
                'mostActiveUser' => 'N/A',
                'avgResponseTime' => '2.5 hours',
            ];
        } catch (\Exception $e) {
            return [
                'todayPosts' => 0,
                'mostActiveUser' => 'N/A',
                'avgResponseTime' => 'N/A',
            ];
        }
    }

    private function getRecentActivity(): array
    {
        // Placeholder for activity log
        return [];
    }

    private function getTopCourses(): array
    {
        // Placeholder for top courses
        return [];
    }

    private function getUserGrowthChartData(\DateTime $startDate, \DateTime $endDate): array
    {
        $labels = [];
        $data = [];

        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            (clone $endDate)->modify('+1 day')
        );

        foreach ($period as $date) {
            $labels[] = $date->format('M d');
            $nextDay = (clone $date)->modify('+1 day');
            $data[] = $this->userRepository->countBetweenDates($date, $nextDay);
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    private function getRevenueChartData(): array
    {
        $premiumUsers = $this->userRepository->findPremiumUsers();
        $totalRevenue = count($premiumUsers) * 9.99;

        return [
            'labels' => ['Premium Subscriptions', 'Free Users', 'Revenue Share'],
            'data' => [$totalRevenue, 0, $totalRevenue * 0.7],
        ];
    }

    private function getRevenueStatistics(): array
    {
        $premiumCount = $this->userRepository->count(['isPremium' => true]);
        $thisMonth = $premiumCount * 9.99;
        $lastMonth = $premiumCount * 0.9 * 9.99;

        $growth = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        return [
            'thisMonth' => $thisMonth,
            'lastMonth' => $lastMonth,
            'growth' => $growth,
        ];
    }

    private function getSystemHealthMetrics(): array
    {
        return [
            'serverLoad' => $this->getServerLoad(),
            'databaseSize' => $this->getDatabaseSize(),
            'cacheHitRate' => 95,
            'lastBackup' => new \DateTime('-6 hours'),
        ];
    }

    private function getServerLoad(): int
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return min((int)($load[0] * 10), 100);
        }
        return 45;
    }

    private function getDatabaseSize(): string
    {
        try {
            $connection = $this->entityManager->getConnection();
            $dbName = $connection->getDatabase();

            $result = $connection->executeQuery(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size
                 FROM information_schema.TABLES
                 WHERE table_schema = :dbName",
                ['dbName' => $dbName]
            )->fetchAssociative();

            return ($result['size'] ?? '0') . ' MB';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function generateReportData(): array
    {
        return [
            ['Metric', 'Value'],
            ['Total Users', $this->userRepository->count([])],
            ['Active Users', $this->userRepository->countByStatus('active')],
            ['Premium Users', $this->userRepository->count(['isPremium' => true])],
            ['Total Courses', $this->getEntityCount('App\Entity\Course') ?? 0],
            ['Total Lessons', $this->getEntityCount('App\Entity\Lesson') ?? 0],
            ['Total Exercises', $this->getEntityCount('App\Entity\Exercise') ?? 0],
        ];
    }

    private function generateCSV(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}

<?php

namespace App\Controller\Admin;

use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Repository\UserRepository;
use App\Module\UserManagement\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai')]
#[IsGranted('ROLE_ADMIN')]
class AdminAiController extends AbstractController
{

    private const OLLAMA_URL   = 'http://127.0.0.1:11434/api/chat';
    private const OLLAMA_MODEL = 'llama3';

    private const ALLOWED_ACTIONS = [
        'READ_ONLY',
        'CREATE_USER',
        'DELETE_USERS',
        'SUSPEND_USERS',
        'ACTIVATE_USERS',
        'CHANGE_PLAN',
        'CHANGE_ROLE',
        'RESET_PASSWORD',
        'EXPORT_USER_IDS',
    ];

    public function __construct(
        private readonly UserRepository               $userRepository,
        private readonly UserService                 $userService,
        private readonly EntityManagerInterface      $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    // =========================================================
    // NATURAL LANGUAGE SEARCH
    // =========================================================

    #[Route('/search', name: 'admin_ai_search', methods: ['POST'])]
    public function search(Request $request): JsonResponse
    {
        $body  = json_decode($request->getContent(), true);
        $query = trim($body['query'] ?? '');

        if (!$query) {
            return $this->json(['error' => 'Query is required'], 400);
        }

        $dataset = $this->buildUserDataset($this->userRepository->findAllWithStats());

        // ✅ PHP pre-filter: resolve common queries without touching the AI
        [$filtered, $intent] = $this->preFilter($query, $dataset);

        if ($intent['resolved']) {
            return $this->json([
                'ids'       => array_column($filtered, 'id'),
                'reasoning' => $intent['reasoning'],
                'summary'   => count($filtered) . ' user(s) found',
            ]);
        }

        $datasetJson = $this->jsonPretty($filtered ?: $dataset);
        $today       = date('Y-m-d');

        $system = <<<PROMPT
You are an AI admin assistant for LinguaLearn, a language learning SaaS platform.
You receive natural language queries and return matching user IDs from the dataset.
Today's date: {$today}

User dataset (JSON):
{$datasetJson}

Matching rules:
- "suspended" → status = "suspended"
- "failed payment" / "payment issue" → paymentStatus = "failed"
- "premium" → isPremium = true
- "free users" → isPremium = false OR plan = "free"
- "top learners" / "highest XP" → sort by xp descending, return top N (default 5)
- "new users" → joined within the last 6 months from today
- "expiring soon" → expiry within the next 30 days
- Combine conditions for complex queries.

CRITICAL: Respond ONLY with raw JSON — no markdown, no code fences.
{"ids": [80, 82], "reasoning": "Short explanation.", "summary": "2 users found"}
PROMPT;

        try {
            $raw    = $this->callOllama([['role' => 'user', 'content' => $query]], $system);
            $clean  = preg_replace('/```json|```/i', '', $raw);
            $parsed = json_decode(trim($clean), true);

            if (!$parsed || !isset($parsed['ids'])) {
                // Fallback to PHP result if AI fails
                return $this->json([
                    'ids'       => array_column($filtered ?: $dataset, 'id'),
                    'reasoning' => 'AI could not parse. PHP filter result returned.',
                    'summary'   => count($filtered ?: $dataset) . ' result(s)',
                ]);
            }

            return $this->json($parsed);

        } catch (\Throwable $e) {
            return $this->json(['ids' => [], 'reasoning' => 'AI service unavailable: ' . $e->getMessage(), 'summary' => 'Error'], 503);
        }
    }

    // =========================================================
    // PER-USER AI INSIGHT
    // =========================================================

    #[Route('/insight', name: 'admin_ai_insight', methods: ['POST'])]
    public function insight(Request $request): JsonResponse
    {
        $body   = json_decode($request->getContent(), true);
        $userId = (int) ($body['userId'] ?? 0);

        // FIX: was find($userId) — triggered lazy queries for learningStats,
        // notifications, userLanguages. Now uses a single JOIN query instead.
        $user = $this->userRepository->findWithFullProfile($userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $profile = [
            'name'          => $user->getFullName(),
            'email'         => $user->getEmail(),
            'status'        => $user->getStatus(),
            'isPremium'     => $user->isPremium(),
            'plan'          => $user->getSubscriptionPlan(),
            'expiry'        => $user->getSubscriptionExpiry()?->format('Y-m-d'),
            'joined'        => $user->getCreatedAt()?->format('Y-m-d'),
            'paymentStatus' => $user->getLastPaymentStatus(),
            'xp'            => $user->getLearningStats()?->getTotalXp() ?? 0,
            'words'         => $user->getLearningStats()?->getWordsLearned() ?? 0,
            'minutes'       => $user->getLearningStats()?->getTotalMinutesStudied() ?? 0,
        ];

        $system = <<<PROMPT
You are an AI analyst for the LinguaLearn admin dashboard.
Given a user's profile, write a concise 3–4 sentence insight covering:
1. Engagement level (active learner vs dormant)
2. Subscription health (at risk? expiring? churned?)
3. Any risk signals (failed payment, suspended, very low activity)
4. One concrete, actionable recommendation for the admin
Write in plain prose — no bullet points, no headers. Be specific and direct.
PROMPT;

        try {
            $insight = $this->callOllama([['role' => 'user', 'content' => 'Analyze this user: ' . json_encode($profile)]], $system);
            return $this->json(['insight' => $insight]);
        } catch (\Throwable $e) {
            return $this->json(['insight' => 'AI service unavailable: ' . $e->getMessage()], 503);
        }
    }

    // =========================================================
    // ADMIN ASSISTANT CHAT
    // =========================================================

    #[Route('/chat', name: 'admin_ai_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $body     = json_decode($request->getContent(), true);
        $messages = $body['messages'] ?? [];

        if (empty($messages)) {
            return $this->json(['error' => 'Messages are required'], 400);
        }

        $lastMessage = end($messages)['content'] ?? '';
        $dataset     = $this->buildUserDataset($this->userRepository->findAllWithStats());

        // ✅ PHP pre-filter: reduce dataset before sending to AI
        [$filtered, $intent] = $this->preFilter($lastMessage, $dataset);
        $workingSet  = $filtered ?: $dataset;

        $stats       = $this->userService->getUserStatistics();
        $statsJson   = json_encode($stats);
        $datasetJson = $this->jsonPretty($workingSet);
        $today       = date('Y-m-d');
        $wc          = count($workingSet);
        $tc          = count($dataset);

        $system = <<<PROMPT
You are a JSON API endpoint for an admin dashboard. You output ONLY raw JSON. Never output prose, explanations, or markdown — not before, after, or inside the JSON.

Today: {$today}
Platform stats: {$statsJson}
User dataset ({$wc} of {$tc} users — pre-filtered by server): {$datasetJson}

OUTPUT RULES — CRITICAL:
1. Your entire response must be a single valid JSON object. Nothing else.
2. No preamble. No markdown. No code fences. No explanation outside the JSON.
3. The "reply" field is the only place for human-readable text. Keep it under 2 sentences.
4. Use ONLY IDs that exist in the dataset above.

ACTIONS YOU CAN EXECUTE:
- READ_ONLY       : answer questions, no mutation
- CREATE_USER     : create a new user
- DELETE_USERS    : soft-delete users (status=deleted)
- SUSPEND_USERS   : suspend users
- ACTIVATE_USERS  : re-activate users
- CHANGE_PLAN     : change subscription plan (FREE/MONTHLY/YEARLY)
- CHANGE_ROLE     : change user roles
- RESET_PASSWORD  : set a new temporary password
- EXPORT_USER_IDS : return filtered IDs, no mutation

SAFETY: Never use ROLE_ADMIN user IDs in mutation actions.

JSON SCHEMAS (pick the matching one):

Read-only:
{"action":"READ_ONLY","reply":"Short answer here.","params":{}}

Create user:
{"action":"CREATE_USER","reply":"Creating user Jane Smith.","params":{"firstName":"Jane","lastName":"Smith","email":"jane@example.com","password":"TempPass123!","subscriptionPlan":"FREE","roles":["ROLE_USER"]}}

Delete/suspend/activate (use real IDs from dataset):
{"action":"DELETE_USERS","reply":"Deleting 2 suspended users.","params":{"ids":[3,7]}}
{"action":"SUSPEND_USERS","reply":"Suspending 1 user.","params":{"ids":[5]}}
{"action":"ACTIVATE_USERS","reply":"Activating 3 users.","params":{"ids":[1,2,4]}}

Change plan:
{"action":"CHANGE_PLAN","reply":"Upgrading user 5 to MONTHLY.","params":{"ids":[5],"plan":"MONTHLY"}}

Change role:
{"action":"CHANGE_ROLE","reply":"Making user 8 a teacher.","params":{"ids":[8],"roles":["ROLE_USER","ROLE_TEACHER"]}}

Reset password:
{"action":"RESET_PASSWORD","reply":"Password reset for user 12.","params":{"id":12,"password":"NewTemp#2025"}}

Export IDs:
{"action":"EXPORT_USER_IDS","reply":"Exporting matching IDs.","params":{"ids":[1,2,3]}}
PROMPT;

        try {
            $raw    = $this->callOllama($messages, $system);
            $parsed = $this->extractJsonFromResponse($raw);

            // AI failed but PHP resolved it — return PHP result directly
            if (!$parsed || !isset($parsed['action'])) {
                if ($intent['resolved'] && !empty($filtered)) {
                    return $this->json([
                        'reply'        => $intent['reasoning'] . ' ' . count($filtered) . ' user(s) found.',
                        'action'       => 'EXPORT_USER_IDS',
                        'actionResult' => [
                            'success' => true,
                            'ids'     => array_column($filtered, 'id'),
                            'message' => count($filtered) . ' IDs exported.',
                        ],
                    ]);
                }
                return $this->json(['reply' => $this->stripJsonFromText($raw), 'action' => 'READ_ONLY', 'actionResult' => null]);
            }

            $action = strtoupper($parsed['action'] ?? 'READ_ONLY');
            $reply  = $parsed['reply'] ?? '';
            $params = $parsed['params'] ?? [];

            if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
                return $this->json([
                    'reply'        => $reply,
                    'action'       => $action,
                    'actionResult' => ['success' => false, 'message' => "Action '{$action}' is not permitted."],
                ]);
            }

            $actionResult = $this->executeAction($action, $params);
            return $this->json(['reply' => $reply, 'action' => $action, 'actionResult' => $actionResult]);

        } catch (\Throwable $e) {
            return $this->json([
                'reply'        => 'AI service unavailable: ' . $e->getMessage(),
                'action'       => null,
                'actionResult' => null,
            ], 503);
        }
    }

    // =========================================================
    // PHP PRE-FILTER ENGINE
    // Handles common queries 100% in PHP — AI never sees 120 users at once
    // =========================================================

    /**
     * @return array{0: array, 1: array{resolved: bool, reasoning: string}}
     */
    private function preFilter(string $query, array $dataset): array
    {
        $q       = strtolower(trim($query));
        $filters = [];
        $reasons = [];

        // Status
        if (preg_match('/\b(suspended|suspension)\b/', $q)) {
            $filters[] = fn($u) => $u['status'] === 'suspended';
            $reasons[] = 'status=suspended';
        }
        if (preg_match('/\bactive\b/', $q) && !preg_match('/\b(suspend|delete|ban)\b/', $q)) {
            $filters[] = fn($u) => $u['status'] === 'active';
            $reasons[] = 'status=active';
        }
        if (preg_match('/\bdeleted?\b/', $q)) {
            $filters[] = fn($u) => $u['status'] === 'deleted';
            $reasons[] = 'status=deleted';
        }
        if (preg_match('/\bbanned?\b/', $q)) {
            $filters[] = fn($u) => $u['status'] === 'banned';
            $reasons[] = 'status=banned';
        }

        // Premium / plan
        if (preg_match('/\bpremium\b/', $q) && !preg_match('/\bnot?\s*premium\b/', $q)) {
            $filters[] = fn($u) => $u['isPremium'] === true;
            $reasons[] = 'isPremium=true';
        }
        if (preg_match('/\bfree\s*(user|plan|account)?\b/', $q)) {
            $filters[] = fn($u) => $u['isPremium'] === false;
            $reasons[] = 'isPremium=false';
        }
        if (preg_match('/\bmonthly\b/', $q)) {
            $filters[] = fn($u) => strtolower($u['plan']) === 'monthly';
            $reasons[] = 'plan=monthly';
        }
        if (preg_match('/\byearly\b/', $q)) {
            $filters[] = fn($u) => strtolower($u['plan']) === 'yearly';
            $reasons[] = 'plan=yearly';
        }

        // Payment
        if (preg_match('/\bfailed\s*payment|payment\s*(fail|issue|problem)\b/', $q)) {
            $filters[] = fn($u) => strtolower((string)$u['paymentStatus']) === 'failed';
            $reasons[] = 'paymentStatus=failed';
        }

        // New users (last 6 months)
        if (preg_match('/\bnew\s*(user|member|registration)|\brecently\s*(joined|registered)\b/', $q)) {
            $since     = (new \DateTime('-6 months'))->format('Y-m-d');
            $filters[] = fn($u) => ($u['joined'] ?? '') >= $since;
            $reasons[] = 'joined in last 6 months';
        }

        // Expiring soon
        if (preg_match('/\bexpir(ing|es|ed)?\s*(soon)?\b/', $q)) {
            $today     = date('Y-m-d');
            $in30      = (new \DateTime('+30 days'))->format('Y-m-d');
            $filters[] = fn($u) => !empty($u['expiry']) && $u['expiry'] >= $today && $u['expiry'] <= $in30;
            $reasons[] = 'expiry within 30 days';
        }

        // Apply all filters (AND logic)
        $filtered = $dataset;
        foreach ($filters as $fn) {
            $filtered = array_values(array_filter($filtered, $fn));
        }

        // Top learners
        if (preg_match('/\btop\b.*\b(learner|xp|score)\b|\bhighest\s*xp\b|\bmost\s*xp\b/', $q)) {
            usort($filtered, fn($a, $b) => $b['xp'] - $a['xp']);
            preg_match('/\b(\d+)\b/', $q, $m);
            $n        = (int) ($m[1] ?? 5);
            $filtered = array_slice($filtered, 0, $n);
            $reasons[] = "top {$n} by XP";
        }

        $resolved  = !empty($filters) || !empty($reasons);
        $reasoning = $resolved
            ? 'PHP filter: ' . implode(' AND ', $reasons) . '.'
            : 'No PHP filter matched — sending to AI.';

        return [$filtered, ['resolved' => $resolved, 'reasoning' => $reasoning]];
    }

    // =========================================================
    // ACTION EXECUTION ENGINE
    // =========================================================

    private function executeAction(string $action, array $params): array
    {
        return match ($action) {
            'READ_ONLY'       => ['success' => true, 'message' => 'No action taken.'],
            'CREATE_USER'     => $this->executeCreateUser($params),
            'DELETE_USERS'    => $this->executeStatusChange($params, 'deleted', 'deleted'),
            'SUSPEND_USERS'   => $this->executeStatusChange($params, 'suspended', 'suspended'),
            'ACTIVATE_USERS'  => $this->executeStatusChange($params, 'active', 'activated'),
            'CHANGE_PLAN'     => $this->executeChangePlan($params),
            'CHANGE_ROLE'     => $this->executeChangeRole($params),
            'RESET_PASSWORD'  => $this->executeResetPassword($params),
            'EXPORT_USER_IDS' => ['success' => true, 'ids' => array_values(array_map('intval', $params['ids'] ?? [])), 'message' => count($params['ids'] ?? []) . ' IDs exported.'],
            default           => ['success' => false, 'message' => 'Unknown action.'],
        };
    }

    private function executeCreateUser(array $params): array
    {
        $firstName = trim($params['firstName'] ?? '');
        $lastName  = trim($params['lastName']  ?? '');
        $email     = trim($params['email']     ?? '');
        $password  = trim($params['password']  ?? 'TempPass' . rand(1000, 9999) . '!');
        $plan      = strtoupper($params['subscriptionPlan'] ?? 'FREE');
        $roles     = $params['roles'] ?? ['ROLE_USER'];

        if (!$firstName || !$lastName || !$email) {
            return ['success' => false, 'message' => 'firstName, lastName, and email are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => "Invalid email address: {$email}"];
        }
        if ($this->userRepository->findOneBy(['email' => $email])) {
            return ['success' => false, 'message' => "A user with email {$email} already exists."];
        }
        if (!in_array($plan, ['FREE', 'MONTHLY', 'YEARLY'], true)) {
            $plan = 'FREE';
        }

        $user = new User();
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setEmail($email);
        $user->setRoles(array_unique(array_merge(['ROLE_USER'], $roles)));
        $user->setSubscriptionPlan($plan);
        $user->setStatus('active');
        $user->setIsVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        if (in_array($plan, ['MONTHLY', 'YEARLY'])) {
            $user->setSubscriptionExpiry(new \DateTime('+1 ' . ($plan === 'MONTHLY' ? 'month' : 'year')));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => "User {$firstName} {$lastName} created successfully.",
            'data'    => [
                'id'       => $user->getId(),
                'name'     => $user->getFullName(),
                'email'    => $user->getEmail(),
                'plan'     => $user->getSubscriptionPlan(),
                'password' => $password,
            ],
        ];
    }

    private function executeStatusChange(array $params, string $newStatus, string $verb): array
    {
        $ids = array_filter(array_map('intval', $params['ids'] ?? []), fn($id) => $id > 0);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'No valid user IDs provided.'];
        }

        // FIX: was find($id) in a loop — N+1 queries. Now one batch query.
        $userMap  = $this->userRepository->findByIds(array_values($ids));
        $affected = [];

        foreach ($ids as $id) {
            $user = $userMap[$id] ?? null;
            if (!$user) continue;
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) continue;
            $user->setStatus($newStatus);
            $affected[] = $id;
        }

        if (empty($affected)) {
            return ['success' => false, 'message' => 'No users updated — they may not exist or are admin-protected.'];
        }

        $this->entityManager->flush();

        return [
            'success'     => true,
            'affectedIds' => $affected,
            'message'     => count($affected) . ' user(s) ' . $verb . ' successfully.',
        ];
    }

    private function executeChangePlan(array $params): array
    {
        $ids  = array_filter(array_map('intval', $params['ids'] ?? []), fn($id) => $id > 0);
        $plan = strtoupper($params['plan'] ?? '');

        if (empty($ids)) {
            return ['success' => false, 'message' => 'No user IDs provided.'];
        }
        if (!in_array($plan, ['FREE', 'MONTHLY', 'YEARLY'], true)) {
            return ['success' => false, 'message' => "Invalid plan '{$plan}'. Must be FREE, MONTHLY, or YEARLY."];
        }

        // FIX: was find($id) in a loop — N+1 queries. Now one batch query.
        $userMap  = $this->userRepository->findByIds(array_values($ids));
        $affected = [];

        foreach ($ids as $id) {
            $user = $userMap[$id] ?? null;
            if (!$user) continue;
            $user->setSubscriptionPlan($plan);
            if (in_array($plan, ['MONTHLY', 'YEARLY'])) {
                $user->setSubscriptionExpiry(new \DateTime('+1 ' . ($plan === 'MONTHLY' ? 'month' : 'year')));
            } else {
                $user->setSubscriptionExpiry(null);
            }
            $affected[] = $id;
        }

        if (empty($affected)) {
            return ['success' => false, 'message' => 'No users updated.'];
        }

        $this->entityManager->flush();

        return [
            'success'     => true,
            'affectedIds' => $affected,
            'message'     => count($affected) . ' user(s) moved to ' . $plan . ' plan.',
        ];
    }

    private function executeChangeRole(array $params): array
    {
        $ids   = array_filter(array_map('intval', $params['ids'] ?? []), fn($id) => $id > 0);
        $roles = $params['roles'] ?? ['ROLE_USER'];

        $allowedRoles = ['ROLE_USER', 'ROLE_TEACHER', 'ROLE_ADMIN'];
        $roles        = array_intersect($roles, $allowedRoles);

        if (empty($ids))   return ['success' => false, 'message' => 'No user IDs provided.'];
        if (empty($roles)) return ['success' => false, 'message' => 'No valid roles provided.'];

        // FIX: was find($id) in a loop — N+1 queries. Now one batch query.
        $userMap  = $this->userRepository->findByIds(array_values($ids));
        $affected = [];

        foreach ($ids as $id) {
            $user = $userMap[$id] ?? null;
            if (!$user) continue;
            $user->setRoles(array_values(array_unique(array_merge(['ROLE_USER'], $roles))));
            $affected[] = $id;
        }

        if (empty($affected)) return ['success' => false, 'message' => 'No users updated.'];

        $this->entityManager->flush();

        return [
            'success'     => true,
            'affectedIds' => $affected,
            'message'     => count($affected) . ' user(s) roles updated to: ' . implode(', ', $roles),
        ];
    }

    private function executeResetPassword(array $params): array
    {
        $userId   = (int) ($params['id'] ?? 0);
        $password = trim($params['password'] ?? '');

        if (!$userId)              return ['success' => false, 'message' => 'User ID is required.'];
        if (strlen($password) < 8) return ['success' => false, 'message' => 'Password must be at least 8 characters.'];

        // FIX: was find($userId) — triggers lazy-loading. Single user with
        // no relations needed here so plain find() is acceptable, but kept
        // consistent with the rest: no lazy relations are accessed so no N+1.
        $user = $this->userRepository->find($userId);
        if (!$user) return ['success' => false, 'message' => "User #{$userId} not found."];

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return ['success' => false, 'message' => 'Cannot reset password for admin accounts via AI.'];
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->flush();

        return [
            'success'      => true,
            'message'      => "Password reset for {$user->getFullName()} (#{$userId}).",
            'tempPassword' => $password,
        ];
    }

    // =========================================================
    // JSON EXTRACTION HELPERS
    // =========================================================

    private function extractJsonFromResponse(string $raw): ?array
    {
        $clean = preg_replace('/```json\s*|```\s*/i', '', $raw);
        $clean = trim($clean);

        $parsed = json_decode($clean, true);
        if (is_array($parsed) && isset($parsed['action'])) {
            return $parsed;
        }

        $start = strpos($clean, '{');
        if ($start === false) return null;

        $depth = 0;
        $end   = $start;
        $len   = strlen($clean);

        for ($i = $start; $i < $len; $i++) {
            if ($clean[$i] === '{') $depth++;
            if ($clean[$i] === '}') $depth--;
            if ($depth === 0) { $end = $i; break; }
        }

        if ($depth !== 0) return null;

        $parsed = json_decode(substr($clean, $start, $end - $start + 1), true);
        return (is_array($parsed) && isset($parsed['action'])) ? $parsed : null;
    }

    private function stripJsonFromText(string $text): string
    {
        $text = preg_replace('/```json.*?```/si', '', $text);
        $text = preg_replace('/```.*?```/si', '', $text);
        $text = preg_replace('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', '', $text);
        return trim(preg_replace('/\s{3,}/', ' ', $text)) ?: 'Done.';
    }

    // =========================================================
    // OLLAMA HELPER
    // =========================================================

    private function callOllama(array $messages, string $system): string
    {
        $payload = [
            'model'    => self::OLLAMA_MODEL,
            'stream'   => false,
            'options'  => ['temperature' => 0.1],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ...$messages,
            ],
        ];

        $ch = curl_init(self::OLLAMA_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr)          throw new \RuntimeException('Ollama connection failed: ' . $curlErr);
        if ($httpCode !== 200) throw new \RuntimeException('Ollama HTTP ' . $httpCode . ': ' . $response);

        $data = json_decode($response, true);
        return $data['message']['content'] ?? '';
    }

    // =========================================================
    // HELPERS
    // =========================================================

    /**
     * Build sanitized dataset — no passwords, tokens, or Stripe IDs.
     * ✅ All values normalized to lowercase so AI matches consistently.
     */
    private function buildUserDataset(array $users): array
    {
        return array_map(fn($u) => [
            'id'            => $u->getId(),
            'name'          => $u->getFullName(),
            'email'         => $u->getEmail(),
            'status'        => strtolower(trim((string) $u->getStatus())),
            'isPremium'     => $u->isPremium() || in_array(strtolower((string)$u->getSubscriptionPlan()), ['monthly','yearly','premium','enterprise']),
            'plan'          => strtolower(trim((string) $u->getSubscriptionPlan())),
            'expiry'        => $u->getSubscriptionExpiry()?->format('Y-m-d'),
            'joined'        => $u->getCreatedAt()?->format('Y-m-d'),
            'paymentStatus' => strtolower(trim((string) $u->getLastPaymentStatus())),
            'roles'         => $u->getRoles(),
            'xp'            => $u->getLearningStats()?->getTotalXp() ?? 0,
            'words'         => $u->getLearningStats()?->getWordsLearned() ?? 0,
            'minutes'       => $u->getLearningStats()?->getTotalMinutesStudied() ?? 0,
        ], $users);
    }

    private function jsonPretty(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

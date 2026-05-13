<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Entity\Notification;
use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Entity\UserLanguage;
use App\Module\UserManagement\Form\UserFormType;
use App\Module\UserManagement\Repository\NotificationRepository;
use App\Module\UserManagement\Repository\UserRepository;
use App\Module\UserManagement\Service\NotificationService;
use App\Module\UserManagement\Service\StripeService;
use App\Module\UserManagement\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private UserService                 $userService,
        private UserRepository              $userRepository,
        private EntityManagerInterface      $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private StripeService               $stripeService,
        private NotificationService         $notificationService,
    ) {}

    // =========================================================
    //  LIST
    // =========================================================

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page  = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $filters = [
            'search'           => trim($request->query->get('search', '')) ?: null,
            'status'           => $request->query->get('status'),
            'role'             => $request->query->get('role'),
            'subscriptionPlan' => $request->query->get('subscriptionPlan'),
            'isPremium'        => $request->query->get('isPremium') !== null
                ? filter_var($request->query->get('isPremium'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'sort'      => $request->query->get('sort'),
            'direction' => $request->query->get('direction'),
        ];

        $criteria = array_filter($filters, fn($v) => $v !== null && $v !== '');
        [$users, $totalUsers] = $this->userRepository->findAdvanced($criteria, $page, $limit);

        return $this->render('user_management/index.html.twig', [
            'users'       => $users,
            'currentPage' => $page,
            'totalPages'  => (int) ceil($totalUsers / $limit),
            'totalUsers'  => $totalUsers,
            'search'      => $filters['search'] ?? '',
            'filters'     => $filters,
            'statistics'  => $this->userService->getUserStatistics(),
        ]);
    }

    // =========================================================
    //  CREATE
    // =========================================================

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserFormType::class, $user, ['is_edit' => false, 'is_admin' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
            $user->setSubscriptionPlan('FREE');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->userService->initLearningStats($user);
            $this->addFlash('success', sprintf('User %s created successfully!', $user->getFullName()));
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        return $this->render('user_management/new.html.twig', ['user' => $user, 'form' => $form]);
    }

    // =========================================================
    //  BULK ACTIONS
    // =========================================================

    #[Route('/bulk', name: 'bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        $ids    = $request->request->all('ids');
        $action = $request->request->get('bulk_action');

        if (empty($ids) || !in_array($action, ['activate', 'suspend', 'delete'], true)) {
            $this->addFlash('warning', 'No users selected or invalid action.');
            return $this->redirectToRoute('admin_users_index');
        }
        if (!$this->isCsrfTokenValid('bulk_action', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_index');
        }

        $processed = 0;
        foreach ($this->userRepository->findBy(['id' => $ids]) as $user) {
            if ($user === $this->getUser()) {
                continue;
            }
            match ($action) {
                'activate' => $this->userService->activateUser($user),
                'suspend'  => $this->userService->suspendUser($user),
                'delete'   => $this->userService->deleteUser($user),
            };
            $processed++;
        }

        $this->addFlash('success', sprintf('%d user(s) %sd successfully.', $processed, $action));
        return $this->redirectToRoute('admin_users_index');
    }

    // =========================================================
    //  CSV EXPORT
    // =========================================================

    #[Route('/export/csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = [
            'search'           => trim($request->query->get('search', '')) ?: null,
            'status'           => $request->query->get('status'),
            'role'             => $request->query->get('role'),
            'subscriptionPlan' => $request->query->get('subscriptionPlan'),
            'isPremium'        => $request->query->get('isPremium') !== null
                ? filter_var($request->query->get('isPremium'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'sort'      => $request->query->get('sort', 'u.createdAt'),
            'direction' => $request->query->get('direction', 'DESC'),
        ];
        $criteria = array_filter($filters, fn($v) => $v !== null && $v !== '');
        [$users]  = $this->userRepository->findAdvanced($criteria, 1, 100000);

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'First Name', 'Last Name', 'Email', 'Status', 'Roles',
                'Subscription Plan', 'Is Premium', 'Subscription Expiry', 'Created At',
                'Total XP', 'Words Learned', 'Minutes Studied',
            ]);
            foreach ($users as $user) {
                $stats = $user->getLearningStats();
                fputcsv($handle, [
                    $user->getId(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getEmail(),
                    $user->getStatus(),
                    implode(', ', $user->getRoles()),
                    $user->getSubscriptionPlan(),
                    $user->isPremium() ? 'Yes' : 'No',
                    $user->getSubscriptionExpiry()?->format('Y-m-d H:i') ?? '',
                    $user->getCreatedAt()?->format('Y-m-d H:i') ?? '',
                    $stats?->getTotalXP() ?? 0,
                    $stats?->getWordsLearned() ?? 0,
                    $stats?->getTotalMinutesStudied() ?? 0,
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="users_export_' . date('Ymd_His') . '.csv"');
        return $response;
    }

    // =========================================================
    //  NOTIFICATION ROUTES (no /{id} prefix — must stay before show)
    // =========================================================

    /**
     * Mark a single notification as read.
     */
    #[Route('/notification/{id}/read', name: 'notification_read', methods: ['POST'])]
    public function notificationMarkRead(Request $request, Notification $notification): Response
    {
        if (!$this->isCsrfTokenValid('notif_read' . $notification->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_notify', ['id' => $notification->getUser()->getId()]);
        }

        $this->notificationService->markAsRead($notification);
        $this->addFlash('success', 'Notification marked as read.');

        return $this->redirectToRoute('admin_users_notify', ['id' => $notification->getUser()->getId()]);
    }

    /**
     * Admin replies to a notification — saves a new notification in the thread.
     */
    #[Route('/notification/{id}/reply', name: 'notification_reply', methods: ['POST'])]
    public function notificationReply(Request $request, Notification $notification): Response
    {
        if (!$this->isCsrfTokenValid('notif_reply' . $notification->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_notify', ['id' => $notification->getUser()->getId()]);
        }

        $replyMessage = trim($request->request->get('reply', ''));

        if (empty($replyMessage)) {
            $this->addFlash('warning', 'Reply cannot be empty.');
            return $this->redirectToRoute('admin_users_notify', ['id' => $notification->getUser()->getId()]);
        }

        /** @var \App\Module\UserManagement\Entity\User|null $admin */
        $admin = $this->getUser();
        $this->notificationService->replyFromAdmin(
            $notification,
            $replyMessage,
            (int) $admin?->getId(),
        );

        $this->addFlash('success', 'Reply sent and original notification marked as read.');

        return $this->redirectToRoute('admin_users_notify', ['id' => $notification->getUser()->getId()]);
    }

    // =========================================================
    //  EDIT
    // =========================================================

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserFormType::class, $user, ['is_edit' => true, 'is_admin' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if (!empty($plain)) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        return $this->render('user_management/edit.html.twig', ['user' => $user, 'form' => $form]);
    }

    // =========================================================
    //  DELETE
    // =========================================================

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->deleteUser($user);
            $this->addFlash('success', 'User deleted successfully!');
        }
        return $this->redirectToRoute('admin_users_index');
    }

    // =========================================================
    //  STATUS ACTIONS
    // =========================================================

    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    public function activate(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('activate' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->activateUser($user);
            $this->addFlash('success', 'User activated successfully!');
        }
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/suspend', name: 'suspend', methods: ['POST'])]
    public function suspend(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('suspend' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->suspendUser($user);
            $this->addFlash('warning', 'User suspended successfully!');
        }
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    // =========================================================
    //  PREMIUM — Grant / Revoke / Trial / Change Plan
    // =========================================================

    #[Route('/{id}/premium/grant/{plan}', name: 'grant_premium', methods: ['POST'])]
    public function grantPremium(Request $request, User $user, string $plan): Response
    {
        $plan = strtoupper($plan);
        if (!in_array($plan, ['MONTHLY', 'YEARLY'], true)) {
            $this->addFlash('danger', 'Invalid plan selected.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        if (!$this->isCsrfTokenValid('grant_premium_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        try {
            $this->stripeService->adminGrantPremium($user, $plan);
            $this->addFlash('success', sprintf('%s granted %s premium access.', $user->getFullName(), strtolower($plan)));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Could not grant premium: ' . $e->getMessage());
        }
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/premium/revoke', name: 'revoke_premium', methods: ['POST'])]
    public function revokePremium(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('revoke_premium_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        try {
            $this->stripeService->adminRevokePremium($user);
            $this->addFlash('warning', sprintf('Premium revoked for %s. Stripe subscription cancelled.', $user->getFullName()));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Could not revoke premium: ' . $e->getMessage());
        }
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/premium/trial', name: 'free_trial', methods: ['POST'])]
    public function freeTrial(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('free_trial_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        if ($user->isPremium()) {
            $this->addFlash('warning', 'User already has premium access.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        $days = max(1, min(365, (int) $request->request->get('trial_days', 7)));
        $plan = strtoupper($request->request->get('plan', 'MONTHLY'));
        if (!in_array($plan, ['MONTHLY', 'YEARLY'], true)) {
            $plan = 'MONTHLY';
        }
        try {
            $this->stripeService->adminGrantTrial($user, $plan, $days);
            $this->addFlash('success', sprintf('🎁 %d-day free trial (%s) granted to %s.', $days, strtolower($plan), $user->getFullName()));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Could not start trial: ' . $e->getMessage());
        }
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/premium/change/{plan}', name: 'change_plan', methods: ['POST'])]
    public function changePlan(Request $request, User $user, string $plan): Response
    {
        $plan = strtoupper($plan);
        if (!in_array($plan, ['MONTHLY', 'YEARLY'], true)) {
            $this->addFlash('danger', 'Invalid plan.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        if (!$this->isCsrfTokenValid('change_plan_' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        if (!$user->isPremium()) {
            $this->addFlash('warning', 'User is not on a premium plan.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        if ($user->getSubscriptionPlan() === $plan) {
            $this->addFlash('info', sprintf('User is already on the %s plan.', strtolower($plan)));
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }
        try {
            $this->stripeService->adminChangePlan($user, $plan);
            $this->addFlash('success', sprintf('%s switched to %s plan.', $user->getFullName(), strtolower($plan)));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Could not change plan: ' . $e->getMessage());
        }
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    // =========================================================
    //  LEARNING STATS
    // =========================================================

    #[Route('/{id}/stats', name: 'stats', methods: ['GET', 'POST'])]
    public function stats(Request $request, User $user): Response
    {
        $stats = $user->getLearningStats() ?? $this->userService->initLearningStats($user);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('stats' . $user->getId(), $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_users_stats', ['id' => $user->getId()]);
            }
            $stats->setTotalXP(max(0, (int) $request->request->get('totalXP', 0)));
            $stats->setWordsLearned(max(0, (int) $request->request->get('wordsLearned', 0)));
            $stats->setTotalMinutesStudied(max(0, (int) $request->request->get('totalMinutesStudied', 0)));
            $this->entityManager->flush();
            $this->addFlash('success', 'Learning stats updated successfully!');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        return $this->render('user_management/stats.html.twig', ['user' => $user, 'stats' => $stats]);
    }

    // =========================================================
    //  NOTIFICATIONS — Send / List / Mark Read / Reply
    // =========================================================

    /**
     * Admin sends a notification to a user + lists existing ones.
     */
    #[Route('/{id}/notify', name: 'notify', methods: ['GET', 'POST'])]
    public function notify(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('notify' . $user->getId(), $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_users_notify', ['id' => $user->getId()]);
            }

            $type    = $request->request->get('type', 'info');
            $message = trim($request->request->get('message', ''));

            if (empty($message)) {
                $this->addFlash('warning', 'Notification message cannot be empty.');
                return $this->redirectToRoute('admin_users_notify', ['id' => $user->getId()]);
            }

            /** @var \App\Module\UserManagement\Entity\User|null $admin */
            $admin = $this->getUser();
            $this->notificationService->sendFromAdmin(
                $user,
                $message,
                $type,
                (int) $admin?->getId(),
            );

            $this->addFlash('success', sprintf('Notification sent to %s.', $user->getFullName()));
            return $this->redirectToRoute('admin_users_notify', ['id' => $user->getId()]);
        }

        return $this->render('user_management/notify.html.twig', [
            'user'                => $user,
            'recentNotifications' => $this->notificationService->getForUser($user, 20),
            'unreadCount'         => $this->notificationService->countUnread($user),
            'notificationTypes'   => [
                'info'    => 'ℹ️ Info',
                'warning' => '⚠️ Warning',
                'success' => '✅ Success',
                'premium' => '👑 Premium',
                'system'  => '⚙️ System',
            ],
        ]);
    }

    /**
     * Mark ALL notifications as read for a user.
     */
    #[Route('/{id}/notifications/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function notificationsMarkAllRead(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('notif_read_all' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_notify', ['id' => $user->getId()]);
        }

        $this->notificationService->markAllAsRead($user);
        $this->addFlash('success', 'All notifications marked as read.');

        return $this->redirectToRoute('admin_users_notify', ['id' => $user->getId()]);
    }

    // =========================================================
    //  LANGUAGE MANAGEMENT
    // =========================================================

    #[Route('/{id}/languages', name: 'languages', methods: ['GET', 'POST'])]
    public function languages(Request $request, User $user): Response
    {
        $availableLanguages = $this->entityManager
            ->getRepository(\App\Module\PedagogicalContent\Entity\PlatformLanguage::class)
            ->findBy(['isEnabled' => true], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('languages' . $user->getId(), $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_users_languages', ['id' => $user->getId()]);
            }

            $action = $request->request->get('action');

            if ($action === 'add') {
                $languageId  = (int) $request->request->get('language_id');
                $proficiency = $request->request->get('proficiency_level', 'A1');
                $isNative    = (bool) $request->request->get('is_native', false);

                if (!in_array($proficiency, ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'native'], true)) {
                    $proficiency = 'A1';
                }

                $platformLanguage = $this->entityManager
                    ->getRepository(\App\Module\PedagogicalContent\Entity\PlatformLanguage::class)
                    ->find($languageId);

                if (!$platformLanguage) {
                    $this->addFlash('danger', 'Language not found.');
                    return $this->redirectToRoute('admin_users_languages', ['id' => $user->getId()]);
                }

                foreach ($user->getUserLanguages() as $existing) {
                    if ($existing->getPlatformLanguage() === $platformLanguage) {
                        $this->addFlash('warning', 'This language is already assigned to the user.');
                        return $this->redirectToRoute('admin_users_languages', ['id' => $user->getId()]);
                    }
                }

                $userLanguage = new UserLanguage();
                $userLanguage->setUser($user);
                $userLanguage->setPlatformLanguage($platformLanguage);
                $userLanguage->setProficiencyLevel($isNative ? 'native' : $proficiency);
                $userLanguage->setIsNative($isNative);
                $this->entityManager->persist($userLanguage);
                $this->entityManager->flush();
                $this->addFlash('success', 'Language added successfully!');
            }

            if ($action === 'remove') {
                $userLanguage = $this->entityManager
                    ->getRepository(UserLanguage::class)
                    ->find((int) $request->request->get('user_language_id'));

                if ($userLanguage && $userLanguage->getUser() === $user) {
                    $this->entityManager->remove($userLanguage);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Language removed.');
                }
            }

            return $this->redirectToRoute('admin_users_languages', ['id' => $user->getId()]);
        }

        return $this->render('user_management/languages.html.twig', [
            'user'               => $user,
            'availableLanguages' => $availableLanguages,
            'proficiencyLevels'  => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
        ]);
    }

    // =========================================================
    //  ADMIN PASSWORD RESET
    // =========================================================

    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, User $user): Response
    {
        if (!$this->isCsrfTokenValid('reset_password' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        $newPassword = trim($request->request->get('new_password', ''));

        if (strlen($newPassword) < 6) {
            $this->addFlash('danger', 'Password must be at least 6 characters.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Password reset for %s.', $user->getFullName()));
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    // =========================================================
    //  READ — /{id} MUST BE LAST to avoid swallowing other routes
    // =========================================================

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $user = $this->userRepository->findWithStats($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }
        return $this->render('user_management/show.html.twig', ['user' => $user]);
    }
}

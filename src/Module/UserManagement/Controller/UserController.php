<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Entity\Notification;
use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Entity\UserLanguage;
use App\Module\UserManagement\Form\UserFormType;
use App\Module\UserManagement\Repository\UserRepository;
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
        private UserService $userService,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    // =========================================================
    //  CORE CRUD  (unchanged from original)
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
            'sort'             => $request->query->get('sort'),
            'direction'        => $request->query->get('direction'),
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

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserFormType::class, $user, ['is_edit' => false, 'is_admin' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->addFlash('success', 'User created successfully!');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('user_management/new.html.twig', ['user' => $user, 'form' => $form]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $user = $this->userRepository->findWithStats($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('user_management/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserFormType::class, $user, ['is_edit' => true, 'is_admin' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->has('plainPassword')) {
                $plain = $form->get('plainPassword')->getData();
                if ($plain) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
                }
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        return $this->render('user_management/edit.html.twig', ['user' => $user, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->deleteUser($user);
            $this->addFlash('success', 'User deleted successfully!');
        }
        return $this->redirectToRoute('admin_users_index');
    }

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

    #[Route('/{id}/premium/upgrade', name: 'premium_upgrade', methods: ['POST'])]
    public function upgradePremium(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('premium' . $user->getId(), $request->request->get('_token'))) {
            $plan   = $request->request->get('plan', 'MONTHLY');
            $expiry = new \DateTime();
            $expiry->modify($plan === 'YEARLY' ? '+1 year' : '+1 month');
            $this->userService->upgradeToPremium($user, $plan, $expiry);
            $this->addFlash('success', 'User upgraded to premium successfully!');
        }
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/premium/downgrade', name: 'premium_downgrade', methods: ['POST'])]
    public function downgradePremium(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('downgrade' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->downgradeToFree($user);
            $this->addFlash('info', 'User downgraded to free plan.');
        }
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    // =========================================================
    //  ADVANCED FEATURE 1 — BULK ACTIONS
    //  Suspend / activate / delete multiple users at once.
    //  Receives a JSON-encoded list of IDs + action via POST.
    // =========================================================

    #[Route('/bulk', name: 'bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        // IDs come from checkbox form (array) or JS fetch (JSON body)
        $ids    = $request->request->all('ids');          // from HTML form
        $action = $request->request->get('bulk_action');

        if (empty($ids) || !in_array($action, ['activate', 'suspend', 'delete'], true)) {
            $this->addFlash('warning', 'No users selected or invalid action.');
            return $this->redirectToRoute('admin_users_index');
        }

        // Validate CSRF once for the bulk operation
        if (!$this->isCsrfTokenValid('bulk_action', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_index');
        }

        $users     = $this->userRepository->findBy(['id' => $ids]);
        $processed = 0;

        foreach ($users as $user) {
            // Never let admin remove their own account via bulk
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
    //  ADVANCED FEATURE 2 — CSV EXPORT
    //  Streams a CSV of ALL users matching the current filters.
    //  No memory limit issues — uses StreamedResponse + fputcsv.
    // =========================================================

    #[Route('/export/csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        // Re-use the same filter logic as index() so export matches the view
        $filters = [
            'search'           => trim($request->query->get('search', '')) ?: null,
            'status'           => $request->query->get('status'),
            'role'             => $request->query->get('role'),
            'subscriptionPlan' => $request->query->get('subscriptionPlan'),
            'isPremium'        => $request->query->get('isPremium') !== null
                ? filter_var($request->query->get('isPremium'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            'sort'             => $request->query->get('sort', 'u.createdAt'),
            'direction'        => $request->query->get('direction', 'DESC'),
        ];
        $criteria = array_filter($filters, fn($v) => $v !== null && $v !== '');

        // Fetch all (page 1, large limit — adjust if you have millions of users)
        [$users] = $this->userRepository->findAdvanced($criteria, 1, 100000);

        $filename = 'users_export_' . date('Ymd_His') . '.csv';

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens it correctly
            fputs($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'ID', 'First Name', 'Last Name', 'Email',
                'Status', 'Roles', 'Subscription Plan',
                'Is Premium', 'Subscription Expiry',
                'Created At', 'Total XP', 'Words Learned', 'Minutes Studied',
            ]);

            foreach ($users as $user) {
                /** @var User $user */
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
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    // =========================================================
    //  ADVANCED FEATURE 3 — LEARNING STATS MANAGEMENT
    //  Admin can manually edit XP / words / minutes for a user.
    //  Useful for correcting data or awarding bonus XP.
    // =========================================================

    #[Route('/{id}/stats', name: 'stats', methods: ['GET', 'POST'])]
    public function stats(Request $request, User $user): Response
    {
        $stats = $user->getLearningStats();

        // Create stats record if it doesn't exist yet (lazy init)
        if (!$stats) {
            $stats = $this->userService->initLearningStats($user);
        }

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

        return $this->render('user_management/stats.html.twig', [
            'user'  => $user,
            'stats' => $stats,
        ]);
    }

    // =========================================================
    //  ADVANCED FEATURE 4 — NOTIFICATION SENDER
    //  Admin sends a targeted notification to a specific user.
    //  Uses the existing Notification entity — no new table needed.
    // =========================================================

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

            // Allowed types to prevent arbitrary data
            if (!in_array($type, ['info', 'warning', 'success', 'premium', 'system'], true)) {
                $type = 'info';
            }

            $notification = new Notification();
            $notification->setUser($user);
            $notification->setType($type);
            $notification->setMessage($message);
            $notification->setMetadata([
                'sender'    => 'admin',
                'admin_id'  => $this->getUser()?->getId(),
                'sent_at'   => (new \DateTime())->format(\DateTime::ATOM),
            ]);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Notification sent to %s.', $user->getFullName()));
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        // Show last 10 notifications for context
        $recentNotifications = $this->userService->getRecentNotifications($user, 10);

        return $this->render('user_management/notify.html.twig', [
            'user'                => $user,
            'recentNotifications' => $recentNotifications,
            'notificationTypes'   => [
                'info'    => 'ℹ️ Info',
                'warning' => '⚠️ Warning',
                'success' => '✅ Success',
                'premium' => '👑 Premium',
                'system'  => '⚙️ System',
            ],
        ]);
    }

    // =========================================================
    //  ADVANCED FEATURE 5 — USER LANGUAGE MANAGEMENT
    //  Assign languages + proficiency levels to a user.
    //  Fully uses the existing UserLanguage & Language entities.
    // =========================================================

    #[Route('/{id}/languages', name: 'languages', methods: ['GET', 'POST'])]
    public function languages(Request $request, User $user): Response
    {
        // Show all enabled platform languages for the dropdown
        $availableLanguages = $this->entityManager
            ->getRepository(\App\Module\PedagogicalContent\Entity\PlatformLanguage::class)
            ->findBy(['isEnabled' => true], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('languages' . $user->getId(), $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid security token.');
                return $this->redirectToRoute('admin_users_languages', ['id' => $user->getId()]);
            }

            $action = $request->request->get('action');

            // --- Add a language ---
            if ($action === 'add') {
                $languageId  = (int) $request->request->get('language_id');
                $proficiency = $request->request->get('proficiency_level', 'A1');
                $isNative    = (bool) $request->request->get('is_native', false);

                $allowedLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'native'];
                if (!in_array($proficiency, $allowedLevels, true)) {
                    $proficiency = 'A1';
                }

                $platformLanguage = $this->entityManager
                    ->getRepository(\App\Module\PedagogicalContent\Entity\PlatformLanguage::class)
                    ->find($languageId);

                if (!$platformLanguage) {
                    $this->addFlash('danger', 'Language not found.');
                    return $this->redirectToRoute('admin_users_languages', ['id' => $user->getId()]);
                }

                // Prevent duplicates
                foreach ($user->getUserLanguages() as $existing) {
                    if ($existing->getPlatformLanguage() === $platformLanguage) {
                        $this->addFlash('warning', 'This language is already assigned to the user.');
                        return $this->redirectToRoute('admin_users_languages', ['id' => $user->getId()]);
                    }
                }

                $userLanguage = new \App\Module\UserManagement\Entity\UserLanguage();
                $userLanguage->setUser($user);
                $userLanguage->setPlatformLanguage($platformLanguage);
                $userLanguage->setProficiencyLevel($isNative ? 'native' : $proficiency);
                $userLanguage->setIsNative($isNative);

                $this->entityManager->persist($userLanguage);
                $this->entityManager->flush();

                $this->addFlash('success', 'Language added successfully!');
            }

            // --- Remove a language ---
            if ($action === 'remove') {
                $userLanguageId = (int) $request->request->get('user_language_id');
                $userLanguage   = $this->entityManager
                    ->getRepository(\App\Module\UserManagement\Entity\UserLanguage::class)
                    ->find($userLanguageId);

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
    //  ADVANCED FEATURE 6 — PASSWORD RESET BY ADMIN
    //  Admin sets a temporary password for a user.
    //  More controlled than a "forgot password" email flow.
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

        $hashed = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashed);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Password reset for %s.', $user->getFullName()));
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }
}

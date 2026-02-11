<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Form\UserFormType;
use App\Module\UserManagement\Repository\UserRepository;
use App\Module\UserManagement\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users', name: 'admin_users_')]
/**#[IsGranted('ROLE_ADMIN')]**/
class UserController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * List all users
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $filters = [
            'search'           => trim($request->query->get('search', '')) ?: null,
            'status'           => $request->query->get('status'),
            'role'             => $request->query->get('role'),
            'subscriptionPlan' => $request->query->get('subscriptionPlan'),
            'isPremium'        => $request->query->get('isPremium') !== null ? filter_var($request->query->get('isPremium'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            'sort'             => $request->query->get('sort'),
            'direction'        => $request->query->get('direction'),
        ];

        $criteria = array_filter($filters, fn($v) => $v !== null && $v !== '');

        [$users, $totalUsers] = $this->userRepository->findAdvanced($criteria, $page, $limit);

        $totalPages = (int) ceil($totalUsers / $limit);

        return $this->render('user_management/index.html.twig', [
            'users'       => $users,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'totalUsers'  => $totalUsers,
            'search'      => $filters['search'] ?? '',
            'filters'     => $filters,
            'statistics'  => $this->userService->getUserStatistics(),
        ]);
    }

    /**
     * Create new user
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserFormType::class, $user, [
            'is_edit'  => false,
            'is_admin' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash and set password from the unmapped plainPassword field
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Persist the user — roles, subscriptionPlan, subscriptionExpiry,
            // status are already set on $user by the form via setters.
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'User created successfully!');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('user_management/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * Show user details
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $user = $this->userRepository->findWithStats($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('user_management/show.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * Edit user
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserFormType::class, $user, [
            'is_edit'  => true,
            'is_admin' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userService->updateUser(
                $user,
                $user->getFirstName(),
                $user->getLastName()
            );

            $this->addFlash('success', 'User updated successfully!');

            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        return $this->render('user_management/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * Delete user (hard delete)
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->deleteUser($user);
            $this->addFlash('success', 'User deleted successfully!');
        }

        return $this->redirectToRoute('admin_users_index');
    }

    /**
     * Activate user
     */
    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    public function activate(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('activate' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->activateUser($user);
            $this->addFlash('success', 'User activated successfully!');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    /**
     * Suspend user
     */
    #[Route('/{id}/suspend', name: 'suspend', methods: ['POST'])]
    public function suspend(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('suspend' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->suspendUser($user);
            $this->addFlash('warning', 'User suspended successfully!');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    /**
     * Upgrade to premium
     */
    #[Route('/{id}/premium/upgrade', name: 'premium_upgrade', methods: ['POST'])]
    public function upgradePremium(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('premium' . $user->getId(), $request->request->get('_token'))) {
            $plan = $request->request->get('plan', 'MONTHLY');

            $expiryDate = new \DateTime();
            if ($plan === 'YEARLY') {
                $expiryDate->modify('+1 year');
            } else {
                $expiryDate->modify('+1 month');
            }

            $this->userService->upgradeToPremium($user, $plan, $expiryDate);
            $this->addFlash('success', 'User upgraded to premium successfully!');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    /**
     * Downgrade to free
     */
    #[Route('/{id}/premium/downgrade', name: 'premium_downgrade', methods: ['POST'])]
    public function downgradePremium(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('downgrade' . $user->getId(), $request->request->get('_token'))) {
            $this->userService->downgradeToFree($user);
            $this->addFlash('info', 'User downgraded to free plan successfully!');
        }

        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }
}

<?php

namespace App\Controller;

use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use App\Module\UserManagement\Entity\UserLanguage;
use App\Module\UserManagement\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard', name: 'user_')]
#[IsGranted('ROLE_USER')]
class UserDashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->userRepository->findWithStats($this->getUser()->getId());

        return $this->render('user/dashboard/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        /** @var \App\Module\UserManagement\Entity\User $user */
        $user = $this->userRepository->findWithStats($this->getUser()->getId());

        // Show only enabled platform languages that the user hasn't enrolled in yet
        $enrolledIds = array_map(
            fn(UserLanguage $ul) => $ul->getPlatformLanguage()->getId(),
            $user->getUserLanguages()->toArray()
        );

        $availableLanguages = $this->em
            ->getRepository(PlatformLanguage::class)
            ->createQueryBuilder('pl')
            ->where('pl.isEnabled = true')
            ->andWhere('pl.id NOT IN (:enrolled)')
            ->setParameter('enrolled', count($enrolledIds) ? $enrolledIds : [0])
            ->orderBy('pl.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('user/dashboard/profile.html.twig', [
            'user'               => $user,
            'availableLanguages' => $availableLanguages,
        ]);
    }

    #[Route('/profile/update', name: 'profile_update', methods: ['POST'])]
    public function updateProfile(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('profile_update', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('user_profile');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user      = $this->getUser();
        $firstName = trim($request->request->get('first_name', ''));
        $lastName  = trim($request->request->get('last_name', ''));

        if (empty($firstName) || empty($lastName)) {
            $this->addFlash('error', 'First and last name are required.');
            return $this->redirectToRoute('user_profile');
        }

        if (mb_strlen($firstName) < 2 || mb_strlen($lastName) < 2) {
            $this->addFlash('error', 'Names must be at least 2 characters.');
            return $this->redirectToRoute('user_profile');
        }

        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $this->em->flush();

        $this->addFlash('success', 'Profile updated successfully!');
        return $this->redirectToRoute('user_profile');
    }

    #[Route('/profile/password', name: 'profile_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$this->isCsrfTokenValid('profile_password', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('user_profile');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user            = $this->getUser();
        $currentPassword = $request->request->get('current_password', '');
        $newPassword     = trim($request->request->get('new_password', ''));
        $confirmPassword = trim($request->request->get('confirm_password', ''));

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Current password is incorrect.');
            return $this->redirectToRoute('user_profile');
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', 'New password must be at least 6 characters.');
            return $this->redirectToRoute('user_profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Passwords do not match.');
            return $this->redirectToRoute('user_profile');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $this->em->flush();

        $this->addFlash('success', 'Password changed successfully!');
        return $this->redirectToRoute('user_profile');
    }

    /**
     * Add a PlatformLanguage to the user's profile (enrollment from profile page).
     */
    #[Route('/profile/languages/add', name: 'language_add', methods: ['POST'])]
    public function addLanguage(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('add_language', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('user_profile');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user        = $this->getUser();
        $languageId  = (int) $request->request->get('language_id');
        $proficiency = $request->request->get('proficiency_level', 'A1');
        $isNative    = (bool) $request->request->get('is_native', false);

        $allowedLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        if (!in_array($proficiency, $allowedLevels, true)) {
            $proficiency = 'A1';
        }

        $platformLanguage = $this->em
            ->getRepository(PlatformLanguage::class)
            ->find($languageId);

        if (!$platformLanguage || !$platformLanguage->isEnabled()) {
            $this->addFlash('error', 'Language not found or not available.');
            return $this->redirectToRoute('user_profile');
        }

        // Prevent duplicates
        foreach ($user->getUserLanguages() as $existing) {
            if ($existing->getPlatformLanguage() === $platformLanguage) {
                $this->addFlash('error', 'You already have this language in your profile.');
                return $this->redirectToRoute('user_profile');
            }
        }

        $userLanguage = new UserLanguage();
        $userLanguage->setUser($user);
        $userLanguage->setPlatformLanguage($platformLanguage);
        $userLanguage->setProficiencyLevel($isNative ? 'native' : $proficiency);
        $userLanguage->setIsNative($isNative);

        $this->em->persist($userLanguage);
        $this->em->flush();

        $this->addFlash('success', $platformLanguage->getName() . ' added to your profile!');
        return $this->redirectToRoute('user_profile');
    }

    /**
     * Remove a language from the user's profile.
     */
    #[Route('/profile/languages/{id}/remove', name: 'language_remove', methods: ['POST'])]
    public function removeLanguage(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('remove_lang_' . $id, $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('user_profile');
        }

        /** @var \App\Module\UserManagement\Entity\User $user */
        $user         = $this->getUser();
        $userLanguage = $this->em->getRepository(UserLanguage::class)->find($id);

        if (!$userLanguage || $userLanguage->getUser() !== $user) {
            $this->addFlash('error', 'Language not found.');
            return $this->redirectToRoute('user_profile');
        }

        $this->em->remove($userLanguage);
        $this->em->flush();

        $this->addFlash('success', 'Language removed from your profile.');
        return $this->redirectToRoute('user_profile');
    }
}

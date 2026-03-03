<?php

namespace App\Controller;

use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Service\FaceRecognitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class SetupController extends AbstractController
{
    #[Route('/setup/create-admin', name: 'setup_create_admin')]
    public function createAdmin(
        Request                     $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface      $entityManager,
    ): Response {
        $error   = null;
        $success = false;
        $token   = null;
        $userId  = null;

        if ($request->isMethod('POST')) {
            $email           = $request->request->get('email');
            $password        = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');
            $firstName       = $request->request->get('first_name');
            $lastName        = $request->request->get('last_name');

            if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
                $error = 'All fields are required.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($existingUser) {
                    $error = 'An account with this email already exists.';
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFirstName($firstName);
                    $user->setLastName($lastName);
                    $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
                    $user->setStatus('active');
                    $user->setSubscriptionPlan('FREE');
                    $user->setPassword($passwordHasher->hashPassword($user, $password));

                    $entityManager->persist($user);
                    $entityManager->flush();

                    // ── Generate one-time setup token for face enrollment ──
                    $token  = bin2hex(random_bytes(16));
                    $userId = $user->getId();

                    $request->getSession()->set('setup_enroll_token',   $token);
                    $request->getSession()->set('setup_enroll_user_id', $userId);

                    $success = true;
                }
            }
        }

        return $this->render('setup/create_admin.html.twig', [
            'error'   => $error,
            'success' => $success,
            'token'   => $token,   // null when form not yet submitted
            'user_id' => $userId,  // null when form not yet submitted
        ]);
    }
}

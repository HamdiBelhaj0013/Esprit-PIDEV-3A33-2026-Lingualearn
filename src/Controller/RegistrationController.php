<?php

namespace App\Controller;

use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $verificationService,
    ): Response {
        // If already logged in, redirect
        if ($this->getUser()) {
            return $this->redirectToRoute('app_homepage');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email           = trim($request->request->get('email', ''));
            $password        = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');
            $firstName       = trim($request->request->get('first_name', ''));
            $lastName        = trim($request->request->get('last_name', ''));

            // Validation
            if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
                $error = 'All fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($existingUser) {
                    $error = 'An account with this email already exists.';
                } else {
                    // Create new user — NOT verified yet
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFirstName($firstName);
                    $user->setLastName($lastName);
                    $user->setRoles(['ROLE_USER']);
                    $user->setStatus('active');
                    $user->setSubscriptionPlan('FREE'); // isPremium computed from plan+expiry
                    $user->setIsVerified(false);   // explicitly unverified

                    $hashedPassword = $passwordHasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);

                    $entityManager->persist($user);
                    $entityManager->flush();

                    // Send verification email
                    try {
                        $verificationService->sendVerificationEmail($user);
                    } catch (\Throwable $e) {
                        // Log the error but don't block registration
                        // The user can request a new link from the notice page
                    }

                    return $this->redirectToRoute('email_verify_notice');
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'error' => $error,
        ]);
    }
}

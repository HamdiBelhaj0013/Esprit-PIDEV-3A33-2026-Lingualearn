<?php

namespace App\Controller;

use App\Module\UserManagement\Entity\User;
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
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $error = null;
        $success = false;

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');
            $firstName = $request->request->get('first_name');
            $lastName = $request->request->get('last_name');

            // Basic validation
            if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
                $error = 'All fields are required.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                // Check if user already exists
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($existingUser) {
                    $error = 'An account with this email already exists.';
                } else {
                    // Create admin user
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFirstName($firstName);
                    $user->setLastName($lastName);
                    $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']); // Admin role
                    $user->setStatus('active');
                    $user->setPremium(true); // Give admin premium

                    // Hash password
                    $hashedPassword = $passwordHasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);

                    // Save to database
                    $entityManager->persist($user);
                    $entityManager->flush();

                    $success = true;
                }
            }
        }

        return $this->render('setup/create_admin.html.twig', [
            'error' => $error,
            'success' => $success,
        ]);
    }
}

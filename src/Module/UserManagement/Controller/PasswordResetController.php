<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService   $resetService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/forgot-password', name: 'password_reset_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', $request->request->get('_csrf_token'))) {
                $this->addFlash('danger', 'Invalid form submission.');
                return $this->redirectToRoute('password_reset_request');
            }

            $email = trim($request->request->get('email', ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Please enter a valid email address.');
                return $this->redirectToRoute('password_reset_request');
            }

            try {
                $this->resetService->sendResetEmail($email);
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Error: ' . $e->getMessage());
                return $this->redirectToRoute('password_reset_request');
            }

            $this->addFlash('success', 'If that email is registered, a reset link has been sent. Check your inbox.');
            return $this->redirectToRoute('password_reset_request');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password', name: 'password_reset_confirm', methods: ['GET', 'POST'])]
    public function confirm(Request $request): Response
    {
        $token = $request->query->get('token') ?? $request->request->get('token', '');
        $email = $request->query->get('email') ?? $request->request->get('email', '');

        if ($request->isMethod('GET')) {
            if (!$token || !$email) {
                $this->addFlash('danger', 'Invalid or missing reset link.');
                return $this->redirectToRoute('password_reset_request');
            }

            /** @var User|null $user */
            $user = $this->em->getRepository(User::class)
                ->findOneBy(['email' => $email, 'passwordResetToken' => $token]);

            if (!$user || !$user->isPasswordResetTokenValid()) {
                $this->addFlash('danger', 'This link is invalid or has expired. Please request a new one.');
                return $this->redirectToRoute('password_reset_request');
            }

            return $this->render('security/reset_password.html.twig', [
                'token' => $token,
                'email' => $email,
            ]);
        }

        // POST
        if (!$this->isCsrfTokenValid('reset_password', $request->request->get('_csrf_token'))) {
            $this->addFlash('danger', 'Invalid form submission.');
            return $this->redirectToRoute('password_reset_request');
        }

        $newPassword     = $request->request->get('new_password', '');
        $confirmPassword = $request->request->get('confirm_password', '');

        if (strlen($newPassword) < 6) {
            $this->addFlash('danger', 'Password must be at least 6 characters long.');
            return $this->render('security/reset_password.html.twig', ['token' => $token, 'email' => $email]);
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('danger', 'The passwords do not match.');
            return $this->render('security/reset_password.html.twig', ['token' => $token, 'email' => $email]);
        }

        if (!$this->resetService->resetPassword($email, $token, $newPassword)) {
            $this->addFlash('danger', 'This link is invalid or has expired. Please request a new one.');
            return $this->redirectToRoute('password_reset_request');
        }

        $this->addFlash('success', '✔ Your password has been updated. You can now log in.');
        return $this->redirectToRoute('app_login');
    }
}

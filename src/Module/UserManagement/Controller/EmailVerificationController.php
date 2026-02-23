<?php

namespace App\Module\UserManagement\Controller;

use App\Module\UserManagement\Entity\User;
use App\Module\UserManagement\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerificationService $verificationService,
        private readonly EntityManagerInterface   $em,
    ) {}

    /**
     * Shown immediately after registration.
     * Route: GET /verify/notice
     */
    #[Route('/verify/notice', name: 'email_verify_notice')]
    public function notice(): Response
    {
        return $this->render('security/verify_notice.html.twig');
    }

    /**
     * The URL the user clicks in their inbox.
     * Route: GET /verify/email?token=...&email=...
     */
    #[Route('/verify/email', name: 'email_verify', methods: ['GET'])]
    public function verify(Request $request): Response
    {
        $token = $request->query->get('token', '');
        $email = $request->query->get('email', '');

        if (!$token || !$email) {
            $this->addFlash('danger', 'Invalid verification link.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->verificationService->verifyEmailToken($email, $token)) {
            $this->addFlash('danger', 'This verification link is invalid or has expired.');
            return $this->redirectToRoute('email_verify_resend_form');
        }

        $this->addFlash('success', '✓ Your email has been verified! You can now log in.');
        return $this->redirectToRoute('app_login');
    }

    /**
     * Form to request a fresh verification link.
     * Route: GET|POST /verify/resend
     */
    #[Route('/verify/resend', name: 'email_verify_resend_form', methods: ['GET', 'POST'])]
    public function resend(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));

            /** @var User|null $user */
            $user = $this->em->getRepository(User::class)
                             ->findOneBy(['email' => $email]);

            // Only send if user exists and is not already verified
            if ($user && !$user->isVerified()) {
                try {
                    $this->verificationService->sendVerificationEmail($user);
                } catch (\Throwable) {
                    $this->addFlash('danger', 'Could not send email. Please try again later.');
                    return $this->redirectToRoute('email_verify_resend_form');
                }
            }

            // Generic message — never reveal whether the email exists
            $this->addFlash('success', 'If that address is registered and unverified, a new link has been sent.');
            return $this->redirectToRoute('email_verify_resend_form');
        }

        return $this->render('security/verify_resend.html.twig');
    }
}

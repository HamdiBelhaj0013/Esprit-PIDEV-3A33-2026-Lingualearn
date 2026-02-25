<?php

namespace App\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailVerificationService
{
    /** Token lifetime: 24 hours */
    private const TOKEN_TTL_SECONDS = 86_400;

    public function __construct(
        private readonly EntityManagerInterface  $em,
        private readonly MailerInterface         $mailer,
        private readonly UrlGeneratorInterface   $router,
        private readonly Environment             $twig,
        private readonly string                  $mailerFrom,
    ) {}

    /**
     * Attach a fresh verification token to $user and send the email.
     * Call this right after registration.
     */
    public function sendVerificationEmail(User $user): void
    {
        $token = bin2hex(random_bytes(32));           // 64-char hex string

        $user->setEmailVerificationToken($token);
        $user->setEmailVerificationTokenExpiresAt(
            new \DateTime(sprintf('+%d seconds', self::TOKEN_TTL_SECONDS))
        );
        $this->em->flush();

        $verifyUrl = $this->router->generate(
            'email_verify',
            ['token' => $token, 'email' => $user->getEmail()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = $this->twig->render('emails/verify_email.html.twig', [
            'user'      => $user,
            'verifyUrl' => $verifyUrl,
        ]);

        $message = (new Email())
            ->from($this->mailerFrom)
            ->to((string) $user->getEmail())
            ->subject('Verify your LinguaLearn account')
            ->html($html);

        $this->mailer->send($message);
    }

    /**
     * Validate the token from the URL query string.
     * On success marks the user as verified and wipes the token.
     *
     * @return bool  true = verified, false = invalid / expired
     */
    public function verifyEmailToken(string $email, string $token): bool
    {
        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)
                         ->findOneBy(['email' => $email]);

        if (!$user) {
            return false;
        }

        // Constant-time comparison prevents timing attacks
        if (!hash_equals((string) $user->getEmailVerificationToken(), $token)) {
            return false;
        }

        if (!$user->isEmailVerificationTokenValid()) {
            return false;
        }

        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationTokenExpiresAt(null);
        $this->em->flush();

        return true;
    }
}

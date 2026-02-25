<?php

namespace App\Module\UserManagement\Service;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class PasswordResetService
{
    /** Token lifetime: 1 hour */
    private const TOKEN_TTL_SECONDS = 3_600;

    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly MailerInterface             $mailer,
        private readonly UrlGeneratorInterface       $router,
        private readonly Environment                 $twig,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string                      $mailerFrom,
    ) {}

    /**
     * Look up the user by email, generate a reset token, and email them.
     * Silently does nothing if the email is not registered — callers should
     * always show a generic success message to avoid leaking user existence.
     */
    public function sendResetEmail(string $email): void
    {
        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)
                         ->findOneBy(['email' => $email]);

        if (!$user) {
            return;   // do NOT tell the caller whether the address exists
        }

        $token = bin2hex(random_bytes(32));

        $user->setPasswordResetToken($token);
        $user->setPasswordResetTokenExpiresAt(
            new \DateTime(sprintf('+%d seconds', self::TOKEN_TTL_SECONDS))
        );
        $this->em->flush();

        $resetUrl = $this->router->generate(
            'password_reset_confirm',
            ['token' => $token, 'email' => $user->getEmail()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = $this->twig->render('emails/reset_password.html.twig', [
            'user'     => $user,
            'resetUrl' => $resetUrl,
        ]);

        $message = (new Email())
            ->from($this->mailerFrom)
            ->to((string) $user->getEmail())
            ->subject('Reset your LinguaLearn password')
            ->html($html);

        $this->mailer->send($message);
    }

    /**
     * Validate the token, hash and save the new password, then wipe the token.
     *
     * @return bool  true = password changed, false = invalid / expired token
     */
    public function resetPassword(string $email, string $token, string $newPlainPassword): bool
    {
        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)
                         ->findOneBy(['email' => $email]);

        if (!$user) {
            return false;
        }

        if (!hash_equals((string) $user->getPasswordResetToken(), $token)) {
            return false;
        }

        if (!$user->isPasswordResetTokenValid()) {
            return false;
        }

        $hashed = $this->passwordHasher->hashPassword($user, $newPlainPassword);
        $user->setPassword($hashed);
        $user->setPasswordResetToken(null);
        $user->setPasswordResetTokenExpiresAt(null);
        $this->em->flush();

        return true;
    }
}

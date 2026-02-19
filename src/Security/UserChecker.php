<?php

namespace App\Security;

use App\Module\UserManagement\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getStatus() !== 'active') {
            throw new CustomUserMessageAuthenticationException(
                'Your account has been suspended. Please contact support.'
            );
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException(
                'unverified_email'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void {}
}

<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();
        $roles = $user->getRoles();

        // Check if user has admin role
        if (in_array('ROLE_ADMIN', $roles, true)) {
            // Admin user - redirect to admin dashboard
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        // Regular user - redirect to user dashboard
        return new RedirectResponse($this->urlGenerator->generate('user_dashboard'));
    }
}

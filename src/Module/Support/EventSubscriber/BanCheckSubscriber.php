<?php

namespace App\Module\Support\EventSubscriber;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class BanCheckSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface  $tokenStorage,
        private RouterInterface        $router,
        private EntityManagerInterface $entityManager
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 5]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;

        $token = $this->tokenStorage->getToken();
        if (!$token) return;

        $user = $token->getUser();
        if (!$user || !($user instanceof User)) return;

        // Vérifier si le ban est expiré → débannir automatiquement
        if ($user->getIsBanned() && $user->getBannedUntil() !== null) {
            if ($user->getBannedUntil() < new \DateTime()) {
                $user->setIsBanned(false);
                $user->setBanReason(null);
                $user->setBannedAt(null);
                $user->setBannedUntil(null);
                $this->entityManager->flush();
                return; // User débanni, laisser passer
            }
        }

        if (!$user->isBanned()) return;

        $route   = $event->getRequest()->attributes->get('_route');
        $allowed = ['app_logout', 'app_banned'];
        if (in_array($route, $allowed)) return;

        $event->setResponse(new RedirectResponse($this->router->generate('app_banned')));
    }
}
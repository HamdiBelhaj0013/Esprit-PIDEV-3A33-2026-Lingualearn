<?php

namespace App\Module\Support\Controller\Front;

use App\Module\Support\Service\BanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BannedController extends AbstractController
{
    public function __construct(private BanService $banService) {}

    #[Route('/banned', name: 'app_banned')]
    public function banned(): Response
    {
        $user = $this->getUser();

        if (!$user || !method_exists($user, 'isBanned') || !$user->isBanned()) {
            return $this->redirectToRoute('user_dashboard');
        }

        return $this->render('support/front/banned.html.twig', [
            'reason'        => $user->getBanReason(),
            'bannedAt'      => $user->getBannedAt(),
            'bannedUntil'   => $user->getBannedUntil(),
            'remainingDays' => $this->banService->getRemainingDays($user),
            'remainingHours'=> $this->banService->getRemainingHours($user),
        ]);
    }
}
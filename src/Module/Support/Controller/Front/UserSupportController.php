<?php

namespace App\Module\Support\Controller\Front;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Form\ReclamationFrontType;
use App\Module\Support\Service\BadWordService;
use App\Module\Support\Service\BanService;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/support/reclamations')]
#[IsGranted('ROLE_USER')]
class UserSupportController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BadWordService         $badWordService,
        private BanService             $banService,
        private RateLimiterFactory     $supportTicketSpamLimiter
    ) {}

    // =========================================================
    // INDEX
    // =========================================================
    #[Route('', name: 'app_user_reclamation_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isBanned()) {
            return $this->redirectToRoute('app_support_banned');
        }

        $reclamations = $this->entityManager
            ->getRepository(Reclamation::class)
            ->findBy(['user' => $user], ['submittedAt' => 'DESC']);

        return $this->render('support/front/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    // =========================================================
    // NEW — Symfony Form + Rate Limiter
    // =========================================================
    #[Route('/new', name: 'app_user_reclamation_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isBanned()) {
            $this->redirectToRoute('app_banned');
        }

        $reclamation = new Reclamation();
        $form = $this->createForm(ReclamationFrontType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ── ANTI-SPAM via symfony/rate-limiter ──
            $limiter = $this->supportTicketSpamLimiter->create('user_' . $user->getId());
            $limit   = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
                $minutes    = (int) ceil($retryAfter / 60);
                $this->addFlash('error', "Trop de réclamations envoyées. Réessayez dans {$minutes} minute(s).");
                return $this->render('support/front/reclamation/new.html.twig', [
                    'form'                => $form->createView(),
                    'spam_blocked'        => true,
                    'retry_after_minutes' => $minutes,
                ]);
            }

            // ── BAD WORDS ──
            $fullText = $reclamation->getSubject() . ' ' . $reclamation->getMessageBody();
            if ($this->badWordService->containsBadWord($fullText)) {
                $detectedWords = $this->badWordService->getDetectedWords($fullText);
                $this->banService->banUserForBadWords($user, $detectedWords);
                return $this->redirectToRoute('app_support_banned');
            }

            // ── Sauvegarde ──
            $reclamation->setUser($user);
            $this->entityManager->persist($reclamation);
            $this->entityManager->flush();

            $this->addFlash('success', 'Réclamation soumise avec succès !');
            return $this->redirectToRoute('app_user_reclamation_index');
        }

        return $this->render('support/front/reclamation/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    #[Route('/{id}', name: 'app_user_reclamation_show', methods: ['GET'])]
    public function show(Reclamation $reclamation): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($reclamation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('support/front/reclamation/show.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    // =========================================================
    // EDIT
    // =========================================================
    #[Route('/{id}/edit', name: 'app_user_reclamation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reclamation $reclamation): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($reclamation->getUser() !== $user || $reclamation->getStatus() !== 'PENDING') {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ReclamationFrontType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Réclamation modifiée avec succès !');
            return $this->redirectToRoute('app_user_reclamation_show', ['id' => $reclamation->getId()]);
        }

        return $this->render('support/front/reclamation/edit.html.twig', [
            'reclamation' => $reclamation,
            'form'        => $form->createView(),
        ]);
    }

    // =========================================================
    // DELETE
    // =========================================================
    #[Route('/{id}/delete', name: 'app_user_reclamation_delete', methods: ['POST'])]
    public function delete(Request $request, Reclamation $reclamation): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($reclamation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete' . $reclamation->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($reclamation);
            $this->entityManager->flush();
            $this->addFlash('success', 'Réclamation supprimée.');
        }

        return $this->redirectToRoute('app_user_reclamation_index');
    }
}

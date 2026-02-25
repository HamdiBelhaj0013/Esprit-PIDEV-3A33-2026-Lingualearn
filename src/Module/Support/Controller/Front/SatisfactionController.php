<?php

namespace App\Module\Support\Controller\Front;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Service\AuditLogService;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/support/reclamations')]
#[IsGranted('ROLE_USER')]
class SatisfactionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogService        $auditLogService
    ) {}

    #[Route('/{id}/rate', name: 'app_reclamation_rate', methods: ['GET', 'POST'])]
    public function rate(Request $request, Reclamation $reclamation): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($reclamation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$reclamation->canBeRated()) {
            $this->addFlash('error', 'Ce ticket ne peut pas être noté.');
            return $this->redirectToRoute('app_user_reclamation_index');
        }

        if ($request->isMethod('POST')) {
            $score   = (int) $request->request->get('score');
            $comment = trim($request->request->get('comment', ''));

            if ($score < 1 || $score > 5) {
                $this->addFlash('error', 'Veuillez choisir une note entre 1 et 5.');
                return $this->render('support/front/reclamation/rate.html.twig', [
                    'reclamation' => $reclamation,
                ]);
            }

            $reclamation->setSatisfactionScore($score);
            $reclamation->setSatisfactionComment($comment ?: null);
            $reclamation->setSatisfactionRatedAt(new \DateTime());
            $this->entityManager->flush();

            // AuditLogService custom trace la satisfaction
            $this->auditLogService->logSatisfactionRated($reclamation, $user, $score);

            $this->addFlash('success', 'Merci pour votre retour ! Votre avis nous aide à améliorer notre support. ⭐');
            return $this->redirectToRoute('app_user_reclamation_index');
        }

        return $this->render('support/front/reclamation/rate.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }
}
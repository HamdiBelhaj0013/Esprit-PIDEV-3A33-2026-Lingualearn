<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Publication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Stichoza\GoogleTranslate\GoogleTranslate;
use App\Service\BadWordChecker;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Service\TranslationService ;

class CommentaireController extends AbstractController
{
    #[Route('/commentaire/ajouter/{id}', name: 'ajouter_commentaire', methods: ['POST'])]
public function ajouter(
    Publication $publication,
    Request $request,
    EntityManagerInterface $em,
    BadWordChecker $badWordChecker,
    MailerInterface $mailer
): Response {
    $contenu = $request->request->get('contenu');
    $origin = $request->request->get('origin'); // "admin" or "front"

    if (!$contenu) {
        $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
        return $origin === 'admin'
            ? $this->redirectToRoute('ressourcesA')
            : $this->redirectToRoute('ressources');
    }

    if ($badWordChecker->containsBadWords($contenu)) {

    $this->addFlash('error', '⚠️ Votre commentaire contient des mots inappropriés et n\'a pas été publié.');

    $user = $this->getUser();

    if ($user) {
        $email = (new Email())
    ->from('benali.mohamedzh@gmail.com')
    ->to($user->getEmail())
    ->subject('⚠️ Avertissement - Commentaire inapproprié')
    ->html('
        <div style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 30px;">
            <div style="max-width: 600px; margin: auto; background: #ffffff; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">

                <h2 style="color: #e74c3c; margin-bottom: 20px;">
                    ⚠️ Commentaire non publié
                </h2>

                <p style="font-size: 15px; color: #333;">
                    Bonjour,
                </p>

                <p style="font-size: 15px; color: #555; line-height: 1.6;">
                    Votre commentaire contient des mots inappropriés et n\'a pas été publié.
                    Merci de respecter les règles de notre communauté.
                </p>

                <div style="margin: 25px 0; padding: 15px; background-color: #fff3cd; border-left: 5px solid #ffc107; border-radius: 5px;">
                    <strong>Rappel :</strong> Tout contenu offensant est automatiquement bloqué.
                </div>

                <p style="font-size: 14px; color: #777;">
                    Cordialement,<br>
                    <strong>L’équipe LinguaLearn</strong>
                </p>

            </div>
        </div>
    ');
        $mailer->send($email);

        $this->addFlash('warning', 'Un email d’avertissement vous a été envoyé.');
    }

    return $origin === 'admin'
        ? $this->redirectToRoute('ressourcesA')
        : $this->redirectToRoute('ressources');
}

    // Sauvegarde du commentaire
    $commentaire = new Commentaire();
    $commentaire->setContenuC($contenu);
    $commentaire->setPublication($publication);
    $commentaire->setDateCom(new \DateTime());

    $em->persist($commentaire);
    $em->flush();

    $this->addFlash('success', 'Commentaire ajouté avec succès.');

    return $origin === 'admin'
        ? $this->redirectToRoute('ressourcesA', ['highlight_id' => $commentaire->getId()])
        : $this->redirectToRoute('ressources', ['highlight_id' => $commentaire->getId()]);
}

      // --- Méthode pour supprimer un commentaire ---
    #[Route('/commentaire/supprimer/{id}', name: 'supprimer_commentaire', methods: ['POST'])]
    public function supprimer(Commentaire $commentaire, Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_comment'.$commentaire->getId(), $token)) {
            $em->remove($commentaire);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

         $redirect = $request->request->get('redirect');
    if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_commentaires');
    } else {
        return $this->redirectToRoute('ressources'); // FrontOffice
    }
    }

    // --- Méthode pour modifier un commentaire ---
    #[Route('/commentaire/modifier/{id}', name: 'modifier_commentaire', methods: ['POST'])]
    public function modifier(Commentaire $commentaire, Request $request, EntityManagerInterface $em): Response
    {
        $nouveauContenu = $request->request->get('contenu');

        if (!$nouveauContenu) {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
            return $this->redirectToRoute('ressources');
        }

        $commentaire->setContenuC($nouveauContenu);
        $em->flush();

        $this->addFlash('success', 'Commentaire modifié avec succès.');
         // Redirection dynamique
          $redirect = $request->request->get('redirect');

    if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_commentaires');
    } else {
        return $this->redirectToRoute('ressources');
    }
    }



     #[Route('/admin/commentaires', name: 'admin_gestion_commentaires')]
    public function gestionCommentaires(EntityManagerInterface $em, Request $request): Response
    {
        $commentaires = $em->getRepository(Commentaire::class)
            ->findBy([], ['dateCom' => 'DESC']);

               // Récupérer l'ID de publication pour le highlight et le convertir en int
    $highlightPubId = $request->query->get('pub_id');
    $highlightPubId = $highlightPubId ? (int) $highlightPubId : null;

    return $this->render('admin/ressource/gestion_commentaires.html.twig', [
        'commentaires' => $commentaires,
        'highlightPubId' => $highlightPubId,
    ]);


    }

// ✅ MODIFIÉ — utilise TranslationService
    #[Route('/commentaire/traduire/{id}/{lang}', name: 'traduire_commentaire')]
    public function traduireCommentaire(
        int $id,
        string $lang,
        EntityManagerInterface $em,
        TranslationService $translationService
    ): JsonResponse {
        $commentaire = $em->getRepository(Commentaire::class)->find($id);

        if (!$commentaire) {
            return $this->json(['error' => 'Commentaire introuvable'], 404);
        }

        try {
            $contenuTraduit = $translationService->translateCommentaire(
                $commentaire->getContenuC(),
                $lang
            );
            return $this->json(['contenu' => $contenuTraduit]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}


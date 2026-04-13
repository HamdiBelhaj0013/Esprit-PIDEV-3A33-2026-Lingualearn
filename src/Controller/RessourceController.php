<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Stichoza\GoogleTranslate\GoogleTranslate;
use App\Service\NotificationService;
use App\Service\TranslationService ;
use App\Entity\Commentaire;

class RessourceController extends AbstractController
{

   #[Route('/ressources', name: 'ressources')]
public function index(EntityManagerInterface $em, Request $request): Response
{
    $page = $request->query->getInt('page', 1);
    $limit = 2; // 👈 2 publications par page
    $offset = ($page - 1) * $limit;

    $repository = $em->getRepository(Publication::class);

    // Total des publications
    $totalPublications = $repository->count([]);

    // Publications paginées
    $publications = $repository->createQueryBuilder('p')
        ->orderBy('p.datePub', 'DESC')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();

    // Nombre total de pages
    $totalPages = ceil($totalPublications / $limit);

    return $this->render('frontoffice/ressource/ressources.html.twig', [
        'publications' => $publications,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'pusher_key' => $_ENV['PUSHER_APP_KEY'],
        'pusher_cluster' => $_ENV['PUSHER_APP_CLUSTER'],
    ]);
}

    #[Route('/ajout-publication', name: 'ajout_publication')]
    public function ajouter(): Response
    {
        return $this->render('frontoffice/ressource/ajout_publication.html.twig');
    }


    #[Route('/commentaire/traduire/{id}/{lang}', name: 'traduire_commentaire')]
public function traduireCommentaire(
    Commentaire $commentaire,
    string $lang,
    TranslationService $translationService
): JsonResponse {
    try {
        $result = $translationService->translateCommentaire(
            $commentaire->getContenuC(),
            $lang
        );
        return $this->json(['contenu' => $result]);
    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], 500);
    }
}

#[Route('/traitement-publication', name: 'traitement_publication', methods: ['GET','POST'])]
public function traitement(
    Request $request,
    EntityManagerInterface $em,
    NotificationService $notificationService
): Response {
     $redirect = $request->request->get('redirect');

    $titre = trim($request->request->get('titre'));
    $contenu = trim($request->request->get('contenu'));
    $media = $request->files->get('media');

    // 🔴 Validation
    if (empty($titre) || strlen($titre) < 5) {
        $this->addFlash('error', 'Le titre doit contenir au moins 5 caractères.');
         $redirect = $request->request->get('redirect');
    if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }
        return $this->redirectToRoute('ajout_publication');
    }

    if (empty($contenu) || strlen($contenu) < 10) {
        $this->addFlash('error', 'Le contenu doit contenir au moins 10 caractères.');
        if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }
        return $this->redirectToRoute('ajout_publication');
    }

    if ($media) {
        $allowedMimeTypes = ['image/jpeg', 'image/png'];
        if (!in_array($media->getMimeType(), $allowedMimeTypes)) {
            $this->addFlash('error', 'Format d’image invalide (jpg, png uniquement).');
            if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }
        return $this->redirectToRoute('ajout_publication');
        }

        if ($media->getSize() > 2 * 1024 * 1024) {
            $this->addFlash('error', 'L’image ne doit pas dépasser 2 Mo.');
            if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }
        return $this->redirectToRoute('ajout_publication');
        }

        
    }




   /* $user = $this->getUser();

    if (!$user) {
        throw $this->createAccessDeniedException('Vous devez être connecté pour publier.');
    }*/
    $user = $em->getRepository(User::class)->find(1);
    if (!$user) {
        throw $this->createNotFoundException('Utilisateur statique non trouvé.');
    }

    $publication = new Publication();
    $publication->setTitrePub($request->request->get('titre'));
    $publication->setContenuPub($request->request->get('contenu'));
    $publication->setTypePub('image');
    $publication->setDatePub(new \DateTime());
    $publication->setUser($user); // ✅ FIX PRINCIPAL

    // Dans la fonction traitement(...)
$media = $request->files->get('media');
$mediaAiFilename = $request->request->get('media_ai_filename');

if ($media) {
    // Cas 1 : L'utilisateur a choisi un fichier sur son PC
    $filename = uniqid() . '.' . $media->guessExtension();
    $media->move($this->getParameter('kernel.project_dir') . '/public/uploads', $filename);
    $publication->setLienPub($filename);
} elseif (!empty($mediaAiFilename)) {
    // Cas 2 : L'utilisateur utilise l'image déjà générée par l'IA
    // L'image est déjà dans /uploads car genererImage la sauvegarde physiquement
    $publication->setLienPub($mediaAiFilename);
}


   /* if ($media) {
        $filename = uniqid() . '.' . $media->guessExtension();
        $media->move(
            $this->getParameter('kernel.project_dir') . '/public/uploads',
            $filename
        );
        $publication->setLienPub($filename);
    }*/

    $em->persist($publication);
    $em->flush();

        // 🔔 Envoyer notification Pusher
        
    $notificationService->sendNotification([
        'message' => $user->getFullName() . ' a commenté : ' . substr($contenu, 0, 50),
        'publicationId' => $publication->getId(),
        'type' => 'comment'
    ]);


    $redirect = $request->request->get('redirect');
    if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }

    return $this->redirectToRoute('ressources');
}



    #[Route('/publication/supprimer/{id}', name: 'supprimer_publication', methods: ['POST'])]
public function supprimer(
    Publication $publication,
    EntityManagerInterface $em,
    Request $request
): Response {
    // Sécurité CSRF
    if ($this->isCsrfTokenValid('delete'.$publication->getId(), $request->request->get('_token'))) {
        // Supprimer le fichier si existe
        if ($publication->getLienPub()) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $publication->getLienPub();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $em->remove($publication);

        if (!$publication) {
    $this->addFlash('error', 'Publication introuvable.');
    return $this->redirectToRoute('ressources');
}

$this->addFlash('success', 'Publication supprimée avec succès.');



        $em->flush();
    }

     $redirect = $request->request->get('redirect');

    if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }


    return $this->redirectToRoute('ressources');
}


#[Route('/publication/modifier/{id}', name: 'modifier_publication', methods: ['GET','POST'])]
public function modifier(
    Publication $publication,
    Request $request,
    EntityManagerInterface $em
): Response {
    $redirect = $request->request->get('redirect');
    if ($request->isMethod('POST')) {
        $titre = $request->request->get('titre');
        $contenu = $request->request->get('contenu');
        $media = $request->files->get('media');

        $publication->setTitrePub($titre);
        $publication->setContenuPub($contenu);
        

        if ($media) {
            $filename = uniqid() . '.' . $media->guessExtension();
            $media->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads',
                $filename
            );
            // Supprime l'ancien fichier si existe
            if ($publication->getLienPub()) {
                $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $publication->getLienPub();
                if (file_exists($oldPath)) unlink($oldPath);
            }
            $publication->setLienPub($filename);
        }

        if (empty($titre) || strlen($titre) < 5) {
    $this->addFlash('error', 'Titre invalide (min 5 caractères).');
    if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }


        return $this->redirectToRoute('ressources');
}

if (empty($contenu) || strlen($contenu) < 10) {
    $this->addFlash('error', 'Contenu invalide (min 10 caractères).');
    if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }


        return $this->redirectToRoute('ressources');
}

if ($media) {
    if (!in_array($media->getMimeType(), ['image/jpeg','image/png'])) {
        $this->addFlash('error', 'Image invalide.');
       if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }


        return $this->redirectToRoute('ressources');
    }
}


        $em->flush();


         $redirect = $request->request->get('redirect');
    if ($redirect === 'admin') {
        return $this->redirectToRoute('admin_gestion_publications');
    }


        return $this->redirectToRoute('ressources');
    }

    return $this->render('frontoffice/ressource/modifier_publication.html.twig', [
        'publication' => $publication
    ]);
}







#[Route('/admin/gestion-publications', name: 'admin_gestion_publications')]
public function gestionPublications(EntityManagerInterface $em,Request $request): Response
{
    $publications = $em->getRepository(Publication::class)
        ->findBy([], ['datePub' => 'DESC']);

   $highlightPubId = $request->query->get('highlightPubId');

    return $this->render('admin/ressource/gestion_publication.html.twig', [
        'publications' => $publications,
        'highlightPubId' => $highlightPubId,
    ]);
}

    #[Route('ressourcesA', name: 'ressourcesA')]
    public function typeEVAdmin(): Response
    {
        return $this->render('admin/ressource/ressource.html.twig');
    }

#[Route('/publication/react/{id}/{type}', name: 'react_publication', methods: ['POST'])]
public function react(
    Publication $publication,
    string $type,
    EntityManagerInterface $em,
    NotificationService $notificationService
): JsonResponse {

    if ($type === 'like') {

        $publication->setLikes($publication->getLikes() + 1);

        if ($publication->getDislikes() > 0) {
            $publication->setDislikes($publication->getDislikes() - 1);
        }
    }

    if ($type === 'dislike') {

        $publication->setDislikes($publication->getDislikes() + 1);

        if ($publication->getLikes() > 0) {
            $publication->setLikes($publication->getLikes() - 1);
        }
    }

    $em->flush();

  // Envoyer notification
    $notificationService->sendNotification([
        'message' => $type === 'like' ? 'Cette publication a reçu un like 👍' : 'Cette publication a reçu un dislike 👎',
        'publicationId' => $publication->getId(),
        'type' => 'reaction'
    ]);

    return $this->json([
        'likes' => $publication->getLikes(),
        'dislikes' => $publication->getDislikes()
    ]);
}


#[Route('/publication/ameliorer', name: 'ameliorer_publication', methods: ['POST'])]
public function ameliorer(Request $request, HttpClientInterface $client): JsonResponse
{
    $titre = $request->request->get('titre', '');
    $contenu = $request->request->get('contenu', '');

    $apiKey = $_ENV['GEMINI_API_KEY'];

    if (!$apiKey) {
        return $this->json(['error' => 'Clé API Gemini manquante'], 500);
    }

    $prompt = "Améliore ce titre et ce contenu pour qu'ils soient clairs, engageants et professionnels.
    Répond uniquement en JSON avec les clés 'titre' et 'contenu'.

    Titre : $titre
    Contenu : $contenu";

    try {

       $response = $client->request('POST',
    'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $apiKey,
        ],
        'json' => [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ]
    ]
);
        $data = $response->toArray();

        $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Nettoyage Markdown
        $aiText = str_replace(['```json', '```'], '', $aiText);
        $aiText = trim($aiText);

        $amelioration = json_decode($aiText, true);

        if (!$amelioration) {
            return $this->json(['error' => 'Réponse IA invalide: ' . $aiText], 500);
        }

        return $this->json([
            'titre' => $amelioration['titre'] ?? $titre,
            'contenu' => $amelioration['contenu'] ?? $contenu
        ]);

    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], 500);
    }
}

#[Route('/publication/generer-image', name: 'generer_image_ai', methods: ['POST'])]
public function genererImage(Request $request, HttpClientInterface $client): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $titre = $data['titre'] ?? '';
    $contenu = $data['contenu'] ?? '';

    // On utilise Gemini pour créer le prompt parfait
    $promptAI = "Generate a short, descriptive English prompt for an image about: $titre. Context: $contenu. Style: professional, digital art.";

    // Au lieu de chercher un endpoint Gemini Image qui n'existe pas en mode "Free Tier" simple,
    // on utilise le prompt généré pour appeler un service de génération d'image gratuit.
    
    // Nettoyage du prompt pour l'URL
    $finalPrompt = urlencode($promptAI);
    $imageUrl = "https://pollinations.ai/p/" . $finalPrompt . "?width=512&height=512&seed=42&model=flux";

    try {
        // On télécharge l'image générée
        $imageContent = file_get_contents($imageUrl);
        
        if ($imageContent === false) {
            return $this->json(['error' => 'Erreur lors de la récupération de l\'image'], 500);
        }

        $fileName = uniqid() . '.png';
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $fileName;

        file_put_contents($filePath, $imageContent);

        return $this->json(['image_url' => '/uploads/' . $fileName]);

    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], 500);
    }
}


#[Route('/quiz/chat', name: 'app_quiz_chat', methods: ['POST'])]
    public function chat(Request $request, HttpClientInterface $client): JsonResponse
    {
        $message = $request->request->get('message');
        if (!$message) {
            return new JsonResponse(['reply' => 'Message vide']);
        }

        try {
            $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $_ENV['GROQ_API_KEY'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es l\'assistant FutureMap. Réponds toujours en français et conseille sur les études et métiers.'],
                        ['role' => 'user', 'content' => $message],
                    ],
                    'stream' => false,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode !== 200) {
                return new JsonResponse([
                    'reply' => "Erreur API Groq ($statusCode)",
                    'error' => $content
                ]);
            }

            $data = json_decode($content, true);
            $reply = $data['choices'][0]['message']['content'] ?? 'Aucune réponse.';

            return new JsonResponse(['reply' => $reply]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'reply' => 'Erreur serveur',
                'error' => $e->getMessage(),
            ]);
        }
    }

// ✅ MODIFIÉ — utilise TranslationService
    #[Route('/publication/traduire/{id}/{lang}', name: 'traduire_publication')]
    public function traduirePublication(
        Publication $publication,
        string $lang,
        TranslationService $translationService
    ): JsonResponse {
        try {
            $result = $translationService->translatePublication(
                $publication->getTitrePub(),
                $publication->getContenuPub(),
                $lang
            );
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }


#[Route('/publication/signal/{id}', name: 'signal_publication', methods: ['POST'])]
public function signal(Publication $publication, EntityManagerInterface $em): JsonResponse
{
    $publication->setReportPub($publication->getReportPub() + 1);
    $em->flush();

    return $this->json([
        'report' => $publication->getReportPub()
    ]);
}


#[Route('/admin/publication/{id}', name: 'admin_show_publication')]
public function showPublication(Publication $publication): Response
{
    return $this->render('admin/ressource/show_publication.html.twig', [
        'publication' => $publication
    ]);
}

#[Route('/publication/flouter/{id}', name: 'publication_flouter', methods: ['POST'])]
public function flouter(Publication $publication, EntityManagerInterface $em ,NotificationService $notificationService ): JsonResponse
{
    
    // Si publication est déjà floutée, on enlève le flou
    if ($publication->getFloue() > 0) {
        $publication->setFloue(0);
    } else {
        $publication->setFloue(1); // ou incrémente si tu veux cumulatif
    }

    $em->persist($publication);
    $em->flush();

     // 🔔 Envoyer notification Pusher
    $notificationService->sendNotification([
        'message' => $this->getUser() ? $this->getUser()->getFullName() : 'Un utilisateur' . ' a signalé une publication',
        'publicationId' => $publication->getId(),
        'type' => 'signal'
    ]);

    return $this->json(['floue' => $publication->getFloue()]);
}



}

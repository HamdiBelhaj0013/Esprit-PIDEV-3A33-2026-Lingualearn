<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Controller;

use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use App\Module\PedagogicalContent\Form\PlatformLanguageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pedagogical-content/languages')]
class PlatformLanguageController extends AbstractController
{
    #[Route('', name: 'admin_language_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'name');
        $order = $request->query->get('order', 'ASC');
        $enabled = $request->query->get('enabled', '');
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        $queryBuilder = $em->getRepository(PlatformLanguage::class)->createQueryBuilder('l');

        // Recherche
        if (!empty($search)) {
            $queryBuilder->andWhere('l.name LIKE :search OR l.code LIKE :search')
                         ->setParameter('search', '%' . $search . '%');
        }

        // Filtrer par statut d'activation
        if ($enabled !== '') {
            $queryBuilder->andWhere('l.isEnabled = :enabled')
                         ->setParameter('enabled', (bool)$enabled);
        }

        // Tri
        $validSortFields = ['name', 'code'];
        $validOrder = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        if (in_array($sort, $validSortFields)) {
            $queryBuilder->orderBy('l.' . $sort, $validOrder);
        } else {
            $queryBuilder->orderBy('l.name', 'ASC');
        }

        $languages = $queryBuilder->getQuery()->getResult();

        // Réponse AJAX
        if ($isAjax) {
            return new JsonResponse([
                'html' => $this->renderView('pedagogical_content/language/_table.html.twig', [
                    'languages' => $languages,
                ]),
                'count' => count($languages),
            ]);
        }

        return $this->render('pedagogical_content/language/index.html.twig', [
            'languages' => $languages,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'enabled' => $enabled,
        ]);
    }

    #[Route('/toggle/{id}', name: 'admin_language_toggle', methods: ['POST'])]
    public function toggle(PlatformLanguage $language, EntityManagerInterface $em, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('toggle_' . $language->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token invalide'], 403);
        }

        $language->setIsEnabled(!$language->isEnabled());
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => $language->isEnabled() ? 'Langue activée' : 'Langue désactivée',
            'isEnabled' => $language->isEnabled(),
        ]);
    }

    #[Route('/new', name: 'admin_language_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $language = new PlatformLanguage();
        $form = $this->createForm(PlatformLanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($language);
            $em->flush();

            $this->addFlash('success', 'Langue créée avec succès !');
            return $this->redirectToRoute('admin_language_index');
        }

        return $this->render('pedagogical_content/language/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_language_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PlatformLanguage $language, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PlatformLanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Langue modifiée avec succès !');
            return $this->redirectToRoute('admin_language_index');
        }

        return $this->render('pedagogical_content/language/edit.html.twig', [
            'form' => $form,
            'language' => $language,
        ]);
    }

    #[Route('/{id}', name: 'admin_language_delete', methods: ['POST'])]
    public function delete(Request $request, PlatformLanguage $language, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $language->getId(), $request->request->get('_token'))) {
            $em->remove($language);
            $em->flush();
            $this->addFlash('success', 'Langue supprimée avec succès !');
        }

        return $this->redirectToRoute('admin_language_index');
    }

    #[Route('/{id}', name: 'admin_language_show', methods: ['GET'])]
    public function show(PlatformLanguage $language): Response
    {
        return $this->render('pedagogical_content/language/show.html.twig', [
            'language' => $language,
        ]);
    }
}
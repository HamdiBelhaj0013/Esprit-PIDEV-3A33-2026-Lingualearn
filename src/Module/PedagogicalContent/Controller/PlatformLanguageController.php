<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Controller;

use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use App\Module\PedagogicalContent\Form\PlatformLanguageType;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pedagogical-content/languages')]
class PlatformLanguageController extends AbstractController
{
    #[Route('', name: 'admin_language_index', methods: ['GET'])]
public function index(Request $request, EntityManagerInterface $em, PaginatorInterface $paginator): Response
{
    $search  = (string) $request->query->get('search', '');
    $sort    = (string) $request->query->get('sort', 'name');
    $order   = (string) $request->query->get('order', 'ASC');
    $enabled = (string) $request->query->get('enabled', '');

    $qb = $em->getRepository(PlatformLanguage::class)->createQueryBuilder('l');

    if ($search !== '') {
        $qb->andWhere('l.name LIKE :search OR l.code LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }

    if ($enabled !== '') {
        $qb->andWhere('l.isEnabled = :enabled')
           ->setParameter('enabled', $enabled === '1');
    }

    $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

    switch ($sort) {
        case 'code':
            $qb->orderBy('l.code', $order);
            break;
        case 'name':
        default:
            $qb->orderBy('l.name', $order);
            break;
    }

    $qb->addOrderBy('l.id', 'ASC');

    $pagination = $paginator->paginate(
        $qb->getQuery(),                 // ✅ getQuery() (plus stable)
        $request->query->getInt('page', 1),
        5
    );

    return $this->render('pedagogical_content/language/index.html.twig', [
        'pagination' => $pagination,
        'search'     => $search,
        'sort'       => $sort,
        'order'      => $order,
        'enabled'    => $enabled,
    ]);
}
    #[Route('/toggle/{id}', name: 'admin_language_toggle', methods: ['POST'])]
    public function toggle(PlatformLanguage $language, EntityManagerInterface $em, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('toggle_' . $language->getId(), (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token invalide'], 403);
        }

        $language->setIsEnabled(!$language->isEnabled());
        $em->flush();

        return new JsonResponse([
            'success'   => true,
            'message'   => $language->isEnabled() ? 'Langue activée' : 'Langue désactivée',
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
        if ($this->isCsrfTokenValid('delete' . $language->getId(), (string) $request->request->get('_token'))) {
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
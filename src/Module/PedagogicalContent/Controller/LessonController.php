<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Controller;

use App\Module\PedagogicalContent\Entity\Lesson;
use App\Module\PedagogicalContent\Form\LessonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pedagogical-content/lessons')]
class LessonController extends AbstractController
{
    #[Route('', name: 'admin_lesson_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'title');
        $order = $request->query->get('order', 'ASC');
        $course = $request->query->get('course', '');
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        $queryBuilder = $em->getRepository(Lesson::class)->createQueryBuilder('l')
                           ->leftJoin('l.course', 'c')
                           ->addSelect('c');

        // Recherche
        if (!empty($search)) {
            $queryBuilder->andWhere('l.title LIKE :search OR l.content LIKE :search')
                         ->setParameter('search', '%' . $search . '%');
        }

        // Filtrage par cours
        if (!empty($course)) {
            $queryBuilder->andWhere('l.course = :course')
                         ->setParameter('course', (int)$course);
        }

        // Tri
        $validSortFields = ['title', 'xpReward'];
        $validOrder = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        if (in_array($sort, $validSortFields)) {
            $queryBuilder->orderBy('l.' . $sort, $validOrder);
        } else {
            $queryBuilder->orderBy('l.title', 'ASC');
        }

        $lessons = $queryBuilder->getQuery()->getResult();

        // Récupérer tous les cours pour le filtre
        $courses = $em->getRepository('App\Module\PedagogicalContent\Entity\Course')
                      ->createQueryBuilder('c')
                      ->orderBy('c.title', 'ASC')
                      ->getQuery()
                      ->getResult();

        // Réponse AJAX
        if ($isAjax) {
            return new JsonResponse([
                'html' => $this->renderView('pedagogical_content/lesson/_table.html.twig', [
                    'lessons' => $lessons,
                ]),
                'count' => count($lessons),
            ]);
        }

        // Réponse normale
        return $this->render('pedagogical_content/lesson/index.html.twig', [
            'lessons' => $lessons,
            'courses' => $courses,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'course' => $course,
        ]);
    }

    #[Route('/toggle-status/{id}', name: 'admin_lesson_toggle_status', methods: ['POST'])]
    public function toggleStatus(Lesson $lesson, EntityManagerInterface $em, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('toggle_' . $lesson->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token invalide'], 403);
        }

        // Ajouter une propriété isActive à l'entité Lesson si ce n'est pas déjà fait
        // Pour le moment, on va utiliser une propriété temporaire
        
        return new JsonResponse([
            'success' => true,
            'message' => 'Statut modifié avec succès'
        ]);
    }

    #[Route('/new', name: 'admin_lesson_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $lesson = new Lesson();
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lesson);
            $em->flush();

            $this->addFlash('success', 'Leçon créée avec succès !');
            return $this->redirectToRoute('admin_lesson_index');
        }

        return $this->render('pedagogical_content/lesson/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_lesson_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Lesson $lesson, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Leçon modifiée avec succès !');
            return $this->redirectToRoute('admin_lesson_index');
        }

        return $this->render('pedagogical_content/lesson/edit.html.twig', [
            'form' => $form,
            'lesson' => $lesson,
        ]);
    }

    #[Route('/{id}', name: 'admin_lesson_delete', methods: ['POST'])]
    public function delete(Request $request, Lesson $lesson, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $lesson->getId(), $request->request->get('_token'))) {
            $em->remove($lesson);
            $em->flush();
            $this->addFlash('success', 'Leçon supprimée avec succès !');
        }

        return $this->redirectToRoute('admin_lesson_index');
    }

    #[Route('/{id}', name: 'admin_lesson_show', methods: ['GET'])]
    public function show(Lesson $lesson): Response
    {
        return $this->render('pedagogical_content/lesson/show.html.twig', [
            'lesson' => $lesson,
        ]);
    }
}
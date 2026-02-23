<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Controller;

use App\Module\PedagogicalContent\Entity\Course;
use App\Module\PedagogicalContent\Form\CourseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/pedagogical-content/courses')]
class CourseController extends AbstractController
{
    #[Route('', name: 'admin_course_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'title');
        $order = $request->query->get('order', 'ASC');
        $level = $request->query->get('level', '');
        $status = $request->query->get('status', '');
        $language = $request->query->get('language', '');
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        $queryBuilder = $em->getRepository(Course::class)->createQueryBuilder('c')
            ->leftJoin('c.platformLanguage', 'l')
            ->addSelect('l');

        // Recherche
        if (!empty($search)) {
            $queryBuilder->andWhere('c.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Filtrer par niveau
        if (!empty($level)) {
            $queryBuilder->andWhere('c.level = :level')
                ->setParameter('level', $level);
        }

        // Filtrer par statut
        if (!empty($status)) {
            $queryBuilder->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        // Filtrer par langue
        if (!empty($language)) {
            $queryBuilder->andWhere('c.platformLanguage = :language')
                ->setParameter('language', (int)$language);
        }

        // Tri
        $validSortFields = ['title', 'level', 'status'];
        $validOrder = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        if (in_array($sort, $validSortFields)) {
            $queryBuilder->orderBy('c.' . $sort, $validOrder);
        } else {
            $queryBuilder->orderBy('c.title', 'ASC');
        }

        $courses = $queryBuilder->getQuery()->getResult();

        // Récupérer les langues pour le filtre
        $languages = $em->getRepository('App\Module\PedagogicalContent\Entity\PlatformLanguage')
            ->createQueryBuilder('l')
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Réponse AJAX
        if ($isAjax) {
            return new JsonResponse([
                'html' => $this->renderView('pedagogical_content/course/_table.html.twig', [
                    'courses' => $courses,
                ]),
                'count' => count($courses),
            ]);
        }

        return $this->render('pedagogical_content/course/index.html.twig', [
            'courses' => $courses,
            'languages' => $languages,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
            'level' => $level,
            'status' => $status,
            'language' => $language,
        ]);
    }

    #[Route('/new', name: 'admin_course_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $course = new Course();
        $course->setAuthor($this->getUser());
        $course->setPublishedAt(new \DateTime());

        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();

            $this->addFlash('success', 'Cours créé avec succès !');
            return $this->redirectToRoute('admin_course_index');
        }

        return $this->render('pedagogical_content/course/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_course_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Cours modifié avec succès !');
            return $this->redirectToRoute('admin_course_index');
        }

        return $this->render('pedagogical_content/course/edit.html.twig', [
            'form' => $form,
            'course' => $course,
        ]);
    }

    #[Route('/{id}', name: 'admin_course_delete', methods: ['POST'])]
    public function delete(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->request->get('_token'))) {
            $em->remove($course);
            $em->flush();
            $this->addFlash('success', 'Cours supprimé avec succès !');
        }

        return $this->redirectToRoute('admin_course_index');
    }

    #[Route('/{id}', name: 'admin_course_show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        return $this->render('pedagogical_content/course/show.html.twig', [
            'course' => $course,
        ]);
    }
}

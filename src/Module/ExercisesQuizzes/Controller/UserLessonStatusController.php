<?php

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Entity\UserLessonStatus;
use App\Module\ExercisesQuizzes\Form\UserLessonStatusType;
use App\Module\ExercisesQuizzes\Repository\UserLessonStatusRepository;
use App\Module\UserManagement\Entity\User;
use App\Module\PedagogicalContent\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/user-lesson-status', name: 'app_exercises_quizzes_user_lesson_status_')]
final class UserLessonStatusController extends AbstractController
{
    private UserLessonStatusRepository $repository;
    private EntityManagerInterface $em;

    public function __construct(UserLessonStatusRepository $repository, EntityManagerInterface $em)
    {
        $this->repository = $repository;
        $this->em = $em;
    }

    // LIST ALL
    #[Route('/', name: 'list', methods: ['GET'])]
    public function index(): Response
    {
        $statuses = $this->repository->findAll();

        return $this->render('exercises_quizzes/user_lesson_status/index.html.twig', [
            'statuses' => $statuses,
        ]);
    }

    // CREATE NEW
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $status = new UserLessonStatus();
        $form = $this->createForm(UserLessonStatusType::class, $status);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($status);
            $this->em->flush();

            $this->addFlash('success', 'Nouveau statut créé avec succès.');
            return $this->redirectToRoute('app_exercises_quizzes_user_lesson_status_list');
        }

        return $this->render('exercises_quizzes/user_lesson_status/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // EDIT
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserLessonStatus $status): Response
    {
        $form = $this->createForm(UserLessonStatusType::class, $status);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Statut modifié avec succès.');
            return $this->redirectToRoute('app_exercises_quizzes_user_lesson_status_list');
        }

        return $this->render('exercises_quizzes/user_lesson_status/edit.html.twig', [
            'status' => $status,
            'form' => $form->createView(),
        ]);
    }

    // DELETE
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, UserLessonStatus $status): Response
    {
        if ($this->isCsrfTokenValid('delete'.$status->getId(), $request->request->get('_token'))) {
            $this->em->remove($status);
            $this->em->flush();
            $this->addFlash('success', 'Statut supprimé avec succès.');
        }

        return $this->redirectToRoute('app_exercises_quizzes_user_lesson_status_list');
    }
}
<?php

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\ExercisesQuizzes\Form\QuizType;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/quizzes', name: 'quiz_')]  // ← CHANGÉ ICI
class QuizController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('exercises_quizzes/quiz/index.html.twig');
    }

    #[Route('/ajax/list', name: 'ajax_list', methods: ['GET'])]
    public function ajaxList(Request $request, QuizRepository $quizRepository): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', null);
        $sortField = $request->query->get('sortField', 'id');
        $sortOrder = $request->query->get('sortOrder', 'DESC');

        $quizzes = $quizRepository->findByFilter($search, $status, $sortField, $sortOrder);

        $data = [];
        foreach ($quizzes as $quiz) {
            $data[] = [
                'id' => $quiz->getId(),
                'title' => $quiz->getTitle(),
                'exerciseCount' => count($quiz->getExercices()),
                'createdAt' => $quiz->getCreatedAt() ? $quiz->getCreatedAt()->format('d/m/Y') : '-',
                'updatedAt' => $quiz->getUpdatedAt() ? $quiz->getUpdatedAt()->format('d/m/Y H:i') : '-',
                'enabled' => $quiz->isEnabled(),
                'showUrl' => $this->generateUrl('quiz_show', ['id' => $quiz->getId()]),
                'editUrl' => $this->generateUrl('quiz_edit', ['id' => $quiz->getId()]),
                'deleteUrl' => $this->generateUrl('quiz_delete', ['id' => $quiz->getId()]),
                'toggleUrl' => $this->generateUrl('quiz_toggle_status', ['id' => $quiz->getId()]),
            ];
        }

        return new JsonResponse([
            'quizzes' => $data,
            'total' => count($data)
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(Quiz $quiz, EntityManagerInterface $em): JsonResponse
    {
        $quiz->setEnabled(!$quiz->isEnabled());
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'enabled' => $quiz->isEnabled()
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $quiz = new Quiz();
        $form = $this->createForm(QuizType::class, $quiz);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $quiz->setCreatedAt(new \DateTimeImmutable());
            $quiz->setUpdatedAt(new \DateTimeImmutable());

            $em->persist($quiz);
            $em->flush();

            $this->addFlash('success', 'Quiz created successfully.');

            return $this->redirectToRoute('quiz_show', ['id' => $quiz->getId()]);
        }

        return $this->render('exercises_quizzes/quiz/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Quiz $quiz): Response
    {
        return $this->render('exercises_quizzes/quiz/show.html.twig', [
            'quiz' => $quiz,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Quiz $quiz, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(QuizType::class, $quiz);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quiz->setUpdatedAt(new \DateTimeImmutable());

            $em->flush();

            $this->addFlash('success', 'Quiz updated successfully.');

            return $this->redirectToRoute('quiz_show', ['id' => $quiz->getId()]);
        }

        return $this->render('exercises_quizzes/quiz/edit.html.twig', [
            'quiz' => $quiz,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Quiz $quiz, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$quiz->getId(), $request->request->get('_token'))) {
            $em->remove($quiz);
            $em->flush();

            $this->addFlash('success', 'Quiz deleted successfully.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('quiz_index');
    }
}
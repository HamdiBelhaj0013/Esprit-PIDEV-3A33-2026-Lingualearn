<?php

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\ExercisesQuizzes\Form\QuizType;
use App\Module\ExercisesQuizzes\Repository\QuizRepository;
use App\Module\PedagogicalContent\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/quizzes', name: 'quiz_')]
class QuizController extends AbstractController
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(LessonRepository $lessonRepository): Response
    {
        $lessons = $lessonRepository->findBy([], ['title' => 'ASC']);
        return $this->render('exercises_quizzes/quiz/index.html.twig', [
            'lessons' => $lessons,
        ]);
    }

    #[Route('/ajax/list', name: 'ajax_list', methods: ['GET'])]
    public function ajaxList(Request $request, QuizRepository $quizRepository): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', null);
        $lessonId = $request->query->get('lesson_id');
        $lessonIdFilter = is_numeric($lessonId) ? (int) $lessonId : null;
        $sortField = $request->query->get('sortField', 'id');
        $sortOrder = $request->query->get('sortOrder', 'DESC');

        $quizzes = $quizRepository->findByFilter($search, $status, $sortField, $sortOrder, $lessonIdFilter);

        $data = [];
        foreach ($quizzes as $quiz) {
            $lesson = $quiz->getLesson();
            $data[] = [
                'id' => $quiz->getId(),
                'title' => $quiz->getTitle(),
                'lessonId' => $lesson?->getId(),
                'lessonTitle' => $lesson?->getTitle(),
                'exerciseCount' => count($quiz->getExercices()),
                'createdAt' => $quiz->getCreatedAt() ? $quiz->getCreatedAt()->format('d/m/Y') : '-',
                'updatedAt' => $quiz->getUpdatedAt() ? $quiz->getUpdatedAt()->format('d/m/Y H:i') : '-',
                'enabled' => $quiz->isEnabled(),
                'showUrl' => $this->generateUrl('quiz_show', ['id' => $quiz->getId()]),
                'editUrl' => $this->generateUrl('quiz_edit', ['id' => $quiz->getId()]),
                'deleteUrl' => $this->generateUrl('quiz_delete', ['id' => $quiz->getId()]),
                'deleteToken' => $this->csrfTokenManager->getToken('delete' . $quiz->getId())->getValue(),
                'toggleUrl' => $this->generateUrl('quiz_toggle_status', ['id' => $quiz->getId()]),
                'exercisesUrl' => $this->generateUrl('exercice_index', []) . '?quiz_id=' . $quiz->getId(),
            ];
        }

        return new JsonResponse([
            'quizzes' => $data,
            'total' => count($data),
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

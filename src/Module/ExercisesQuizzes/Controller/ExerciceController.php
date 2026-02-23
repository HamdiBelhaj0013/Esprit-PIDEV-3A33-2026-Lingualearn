<?php

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Form\ExerciceType;
use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/exercice', name: 'exercice_')]
final class ExerciceController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // La table sera remplie via AJAX
        return $this->render('exercises_quizzes/exercice/index.html.twig');
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, ExerciceRepository $exerciceRepository): Response
    {
        $exercice = new Exercice();
        $form = $this->createForm(ExerciceType::class, $exercice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les options depuis le textarea non mappé via le formulaire
            $optionsData = $form->get('options')->getData() ?? '';
            $options = array_filter(array_map('trim', explode("\n", $optionsData)));
            $exercice->setOptions($options);

            $exerciceRepository->save($exercice, true);
            $this->addFlash('success', 'Exercice créé avec succès.');
            return $this->redirectToRoute('exercice_index');
        }

        return $this->render('exercises_quizzes/exercice/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Exercice $exercice, ExerciceRepository $exerciceRepository): Response
    {
        $form = $this->createForm(ExerciceType::class, $exercice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer les options depuis le textarea non mappé via le formulaire
            $optionsData = $form->get('options')->getData() ?? '';
            $options = array_filter(array_map('trim', explode("\n", $optionsData)));
            $exercice->setOptions($options);

            $exerciceRepository->save($exercice, true);
            $this->addFlash('success', 'Exercice modifié avec succès.');
            return $this->redirectToRoute('exercice_index');
        }

        return $this->render('exercises_quizzes/exercice/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Exercice $exercice, ExerciceRepository $exerciceRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$exercice->getId(), $request->request->get('_token'))) {
            $exerciceRepository->remove($exercice, true);
            $this->addFlash('success', 'Exercice supprimé.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('exercice_index');
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Exercice $exercice): Response
    {
        return $this->render('exercises_quizzes/exercice/show.html.twig', [
            'exercice' => $exercice,
        ]);
    }

    // ================= AJAX =================

    #[Route('/ajax/list', name: 'ajax_list', methods: ['GET'])]
    public function ajaxList(Request $request, ExerciceRepository $exerciceRepository): JsonResponse
    {
        $search = $request->query->get('search', '');
        $aiParam = $request->query->get('ai', null);
        $ai = $aiParam !== '' ? (bool)$aiParam : null;

        $sortField = $request->query->get('sortField', 'id');
        $sortOrder = $request->query->get('sortOrder', 'DESC');

        $exercices = $exerciceRepository->findByFilter($search, $ai, $sortField, $sortOrder);

        $data = array_map(fn(Exercice $ex) => [
            'id' => $ex->getId(),
            'type' => $ex->getType(),
            'question' => $ex->getQuestion(),
            'aiGenerated' => $ex->isAiGenerated(),
            'enabled' => $ex->isEnabled(),
        ], $exercices);

        return $this->json(['data' => $data]);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Exercice $exercice, ExerciceRepository $exerciceRepository): JsonResponse
    {
        $exercice->setEnabled(!$exercice->isEnabled());
        $exerciceRepository->save($exercice, true);

        return $this->json(['enabled' => $exercice->isEnabled()]);
    }
}

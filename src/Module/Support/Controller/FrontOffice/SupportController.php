<?php
namespace App\Module\Support\Controller\FrontOffice;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Entity\FAQ;
use App\Module\Support\Form\ReclamationType;
use App\Module\Support\Form\FAQType;
use App\Module\Support\Service\ReclamationService;
use App\Module\Support\Service\FAQService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/support')]
class SupportController extends AbstractController
{
    public function __construct(
        private ReclamationService $reclamationService,
        private FAQService $faqService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'front_support_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        // Show all reclamations (no authentication required)
        $reclamations = $this->reclamationService->findAll();

        // Get FAQs and subjects for the FAQ tab
        $faqs = $this->faqService->findAll();
        $subjects = $this->faqService->getSubjects();

        return $this->render('support/front/dashboard.html.twig', [
            'reclamations' => $reclamations,
            'faqs' => $faqs,
            'subjects' => $subjects,
        ]);
    }
}

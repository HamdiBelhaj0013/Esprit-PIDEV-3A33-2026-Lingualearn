<?php

namespace App\Module\InternationalTests\Controller\Front;

use App\Module\InternationalTests\Repository\CertificateRepository;
use App\Module\InternationalTests\Service\CertificateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mock-tests/certificate')]
class CertificateController extends AbstractController
{
    /**
     * Page HTML publique de vérification du certificat (sans login requis)
     */
    #[Route('/{code}', name: 'certificate_view', methods: ['GET'])]
    public function view(
        string $code,
        CertificateRepository $certificateRepo
    ): Response {
        $certificate = $certificateRepo->findByUniqueCode($code);

        if (!$certificate) {
            throw $this->createNotFoundException('Certificate not found or invalid verification code.');
        }

        return $this->render('internationaltests/certificate/view.html.twig', [
            'certificate' => $certificate,
        ]);
    }

    /**
     * Téléchargement du PDF du certificat
     */
    #[Route('/{code}/download', name: 'certificate_download', methods: ['GET'])]
    public function download(
        string $code,
        CertificateRepository $certificateRepo,
        CertificateService $certificateService
    ): Response {
        $certificate = $certificateRepo->findByUniqueCode($code);

        if (!$certificate) {
            throw $this->createNotFoundException('Certificate not found or invalid verification code.');
        }

        $pdfContent = $certificateService->generatePdf($certificate);

        $filename = sprintf(
            'LinguaLearn_Certificate_%s_%s.pdf',
            $certificate->getLanguage()->getName(),
            $certificate->getUser()->getLastName()
        );

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }
}


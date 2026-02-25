<?php

namespace App\Module\InternationalTests\Service;

use App\Module\InternationalTests\Entity\Certificate;
use App\Module\InternationalTests\Entity\MockTest;
use App\Module\InternationalTests\Repository\CertificateRepository;
use App\Module\InternationalTests\Repository\TestResultRepository;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class CertificateService
{
    private const PASS_SCORE = 10;

    public function __construct(
        private TestResultRepository $testResultRepo,
        private CertificateRepository $certificateRepo,
        private EntityManagerInterface $em,
        private Environment $twig
    ) {
    }

    /**
     * Vérifie si l'utilisateur a réussi les 3 niveaux pour une langue donnée
     */
    public function hasPassedAllLevels(User $user, int $languageId): bool
    {
        $levels = [MockTest::LEVEL_BEGINNER, MockTest::LEVEL_INTERMEDIATE, MockTest::LEVEL_ADVANCED];
        
        foreach ($levels as $level) {
            $bestScore = $this->testResultRepo->getBestScoreByUserAndLevel(
                $user->getId(),
                $level,
                $languageId
            );
            
            if ($bestScore === null || $bestScore < self::PASS_SCORE) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Calcule le score moyen des 3 niveaux
     */
    public function calculateAverageScore(User $user, int $languageId): float
    {
        $levels = [MockTest::LEVEL_BEGINNER, MockTest::LEVEL_INTERMEDIATE, MockTest::LEVEL_ADVANCED];
        $total = 0;
        
        foreach ($levels as $level) {
            $bestScore = $this->testResultRepo->getBestScoreByUserAndLevel(
                $user->getId(),
                $level,
                $languageId
            );
            $total += $bestScore ?? 0;
        }
        
        return round($total / 3, 2);
    }

    /**
     * Génère ou récupère un certificat existant
     */
    public function generateOrGetCertificate(User $user, int $languageId): ?Certificate
    {
        // Vérifier si un certificat existe déjà
        $existing = $this->certificateRepo->findByUserAndLanguage($user->getId(), $languageId);
        if ($existing) {
            return $existing;
        }

        // Vérifier si l'utilisateur a réussi les 3 niveaux
        if (!$this->hasPassedAllLevels($user, $languageId)) {
            return null;
        }

        // Créer le certificat
        $certificate = new Certificate();
        $certificate->setUser($user);
        $certificate->setLanguage($this->em->getReference('App\Module\PedagogicalContent\Entity\PlatformLanguage', $languageId));
        $certificate->setAvgScore($this->calculateAverageScore($user, $languageId));

        $this->em->persist($certificate);
        $this->em->flush();

        return $certificate;
    }

    /**
     * Génère le PDF du certificat
     */
    public function generatePdf(Certificate $certificate): string
    {
        $html = $this->twig->render('internationaltests/certificate/pdf.html.twig', [
            'certificate' => $certificate,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Vérifie si un certificat peut être généré après cette soumission
     */
    public function checkAndGenerateCertificate(User $user, int $languageId): ?Certificate
    {
        return $this->generateOrGetCertificate($user, $languageId);
    }
}


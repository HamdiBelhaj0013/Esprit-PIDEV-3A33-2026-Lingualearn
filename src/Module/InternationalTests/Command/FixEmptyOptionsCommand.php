<?php

namespace App\Module\InternationalTests\Command;

use App\Module\InternationalTests\Repository\TestQuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-empty-options',
    description: 'Ajoute des options génériques aux questions QCM qui ont des options vides'
)]
class FixEmptyOptionsCommand extends Command
{
    public function __construct(
        private TestQuestionRepository $questionRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔧 Correction des options vides pour les questions QCM');

        // Récupérer toutes les questions QCM avec options vides
        $questions = $this->questionRepository->createQueryBuilder('q')
            ->where('q.questionType IN (:types)')
            ->andWhere('q.options = :empty OR q.options IS NULL')
            ->setParameter('types', ['qcm', 'qcm_single', 'qcm_multiple', 'reading'])
            ->setParameter('empty', '[]')
            ->getQuery()
            ->getResult();

        if (empty($questions)) {
            $io->success('✅ Aucune question avec options vides trouvée !');
            return Command::SUCCESS;
        }

        $io->note(sprintf('📊 %d question(s) avec options vides trouvée(s)', count($questions)));

        $io->section('Ajout des options génériques...');

        $count = 0;
        foreach ($questions as $question) {
            $questionType = $question->getQuestionType();
            
            // Options génériques selon le type
            if ($questionType === 'reading') {
                $options = [
                    'A' => 'True',
                    'B' => 'False',
                    'C' => 'Not mentioned',
                    'D' => 'Cannot determine'
                ];
            } else {
                $options = [
                    'A' => 'Option A',
                    'B' => 'Option B',
                    'C' => 'Option C',
                    'D' => 'Option D'
                ];
            }

            $question->setOptions($options);
            
            // Si pas de réponse correcte, mettre 'A' par défaut
            if (empty($question->getCorrectAnswer())) {
                $question->setCorrectAnswer('A');
            }

            $count++;
            
            $io->writeln(sprintf(
                '  ✓ Question #%d: "%s" → Options ajoutées',
                $question->getId(),
                substr($question->getQuestionText(), 0, 50) . '...'
            ));
        }

        $this->entityManager->flush();

        $io->newLine();
        $io->success(sprintf('✅ %d question(s) corrigée(s) avec succès !', $count));
        
        $io->warning([
            '⚠️  IMPORTANT:',
            'Les options ajoutées sont GÉNÉRIQUES (Option A, Option B, etc.)',
            'Vous devez maintenant éditer chaque question via l\'interface admin',
            'pour mettre les vraies options professionnelles.',
            '',
            'Interface admin: http://127.0.0.1:8000/admin/test-questions'
        ]);

        return Command::SUCCESS;
    }
}


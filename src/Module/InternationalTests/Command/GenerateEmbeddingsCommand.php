<?php

namespace App\Module\InternationalTests\Command;

use App\Module\InternationalTests\Repository\TestQuestionRepository;
use App\Module\InternationalTests\Service\GeminiEmbeddingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-embeddings',
    description: 'Génère les embeddings vectoriels pour toutes les questions sans embedding (détection de doublons)'
)]
class GenerateEmbeddingsCommand extends Command
{
    // Pause entre chaque appel API pour respecter le rate limit Gemini
    private const SLEEP_MS = 500; // 500ms = 2 appels/seconde max

    public function __construct(
        private TestQuestionRepository $questionRepository,
        private GeminiEmbeddingService  $embeddingService,
        private EntityManagerInterface  $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Régénère les embeddings même pour les questions qui en ont déjà un'
        );
        $this->addOption(
            'batch',
            'b',
            InputOption::VALUE_OPTIONAL,
            'Nombre de questions à traiter par batch (flush)',
            20
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $batch = (int) $input->getOption('batch');

        $io->title('🧠 Génération des Embeddings — Détection de Doublons');

        // Récupérer les questions à traiter
        if ($force) {
            $questions = $this->questionRepository->findAll();
            $io->note(sprintf('Mode FORCE : %d question(s) à retraiter', count($questions)));
        } else {
            $questions = $this->questionRepository->findWithoutEmbedding();
            $io->note(sprintf('%d question(s) sans embedding trouvée(s)', count($questions)));
        }

        if (empty($questions)) {
            $io->success('✅ Toutes les questions ont déjà un embedding !');
            return Command::SUCCESS;
        }

        $io->progressStart(count($questions));

        $success = 0;
        $failed  = 0;
        $count   = 0;

        foreach ($questions as $question) {
            $text      = $question->getQuestionText();
            $embedding = $this->embeddingService->generateEmbedding($text);

            if ($embedding) {
                $question->setEmbedding($embedding);
                $success++;
            } else {
                $io->warning(sprintf(
                    'Question #%d : échec génération embedding ("%s…")',
                    $question->getId(),
                    mb_substr($text, 0, 40)
                ));
                $failed++;
            }

            $count++;
            $io->progressAdvance();

            // Flush par batch pour éviter la saturation mémoire
            if ($count % $batch === 0) {
                $this->entityManager->flush();
            }

            // Respecter le rate limit Gemini (2 req/sec max)
            usleep(self::SLEEP_MS * 1000);
        }

        // Flush final
        $this->entityManager->flush();
        $io->progressFinish();

        $io->newLine();
        $io->success(sprintf(
            '✅ Terminé ! %d embedding(s) généré(s) avec succès. %d échec(s).',
            $success,
            $failed
        ));

        if ($failed > 0) {
            $io->warning('Relancez la commande pour les questions en échec (rate limit temporaire).');
        }

        $io->info([
            'La détection de doublons est maintenant active.',
            'Chaque nouvelle question créée obtiendra son embedding automatiquement.',
        ]);

        return Command::SUCCESS;
    }
}

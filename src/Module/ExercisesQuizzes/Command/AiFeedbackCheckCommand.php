<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Command;

use App\Module\ExercisesQuizzes\Client\AiExplanationClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Vérifie que le token Hugging Face et l'API sont configurés pour les explications IA.
 * À lancer après avoir défini HUGGINGFACE_API_TOKEN dans .env.local puis cache:clear.
 */
#[AsCommand(
    name: 'app:ai-feedback:check',
    description: 'Vérifie la configuration Hugging Face pour les explications IA des erreurs de quiz.',
)]
class AiFeedbackCheckCommand extends Command
{
    public function __construct(
        private AiExplanationClientInterface $explanationClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Vérification explications IA (Hugging Face)');

        $io->section('Test d\'appel à l\'API');
        $prompt = 'Réponds en une phrase : quelle est la capitale de la France ?';
        $result = $this->explanationClient->generate($prompt);

        if ($result !== null && $result !== '') {
            $io->success('L\'API répond correctement.');
            $io->text('Exemple de réponse : ' . mb_substr($result, 0, 120) . (mb_strlen($result) > 120 ? '…' : ''));
            return Command::SUCCESS;
        }

        $io->error('L\'API n\'a pas renvoyé de réponse (explication IA indisponible).');
        $io->note([
            '1. Vérifiez que HUGGINGFACE_API_TOKEN est défini dans .env.local (token avec permission « Make calls to Inference Providers »).',
            '2. Exécutez : php bin/console cache:clear',
            '3. Consultez les logs (var/log/dev.log) pour les messages « Hugging Face: … ».',
        ]);
        return Command::FAILURE;
    }
}

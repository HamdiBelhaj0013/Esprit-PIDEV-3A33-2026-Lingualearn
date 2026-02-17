<?php

namespace App\Module\UserManagement\Command;

use App\Module\UserManagement\Service\UserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * FEATURE — Subscription expiry checker & auto-downgrade.
 *
 * Run manually:
 *   php bin/console app:subscriptions:check-expired
 *
 * Schedule via cron (daily at midnight):
 *   0 0 * * * /usr/bin/php /var/www/html/bin/console app:subscriptions:check-expired >> /var/log/lingua_subscriptions.log 2>&1
 *
 * Or via Symfony Scheduler (if you have symfony/scheduler installed):
 *   Add this command to your Schedule class with a RecurringMessage::every('1 day', ...).
 */
#[AsCommand(
    name: 'app:subscriptions:check-expired',
    description: 'Finds all users whose premium subscription has expired and downgrades them to the FREE plan.',
)]
class CheckExpiredSubscriptionsCommand extends Command
{
    public function __construct(private UserService $userService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Preview which users would be downgraded without making any changes.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $isDry = $input->getOption('dry-run');

        $io->title('LinguaLearn — Subscription Expiry Checker');

        if ($isDry) {
            $io->warning('DRY RUN — no changes will be saved to the database.');
        }

        if ($isDry) {
            // In dry-run mode, just count without touching the DB
            $io->note('Dry-run mode: calling checkExpiredSubscriptions() is skipped.');
            $io->success('Dry run complete. Use without --dry-run to apply changes.');
            return Command::SUCCESS;
        }

        $count = $this->userService->checkExpiredSubscriptions();

        if ($count === 0) {
            $io->success('No expired subscriptions found. All users are up to date.');
        } else {
            $io->success(sprintf('%d user(s) downgraded to FREE plan.', $count));
        }

        return Command::SUCCESS;
    }
}

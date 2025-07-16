<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-notifications',
    description: 'Nettoie les anciennes notifications lues',
)]
class CleanupNotificationsCommand extends Command
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nettoyage des anciennes notifications');

        $count = $this->notificationService->cleanupOldNotifications();

        $io->success(sprintf('%d notification(s) ancienne(s) supprimée(s).', $count));

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use App\Entity\Task;
use App\Enum\TaskStatut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-task-completion-dates',
    description: 'Met à jour les dates de complétion pour les tâches terminées',
)]
class UpdateTaskCompletionDatesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Mise à jour des dates de complétion des tâches');

        // Récupérer toutes les tâches terminées sans date de complétion
        $tasks = $this->entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->where('t.statut = :statut')
            ->andWhere('t.dateCompletion IS NULL')
            ->setParameter('statut', TaskStatut::TERMINER)
            ->getQuery()
            ->getResult();

        $count = count($tasks);
        $io->info(sprintf('Trouvé %d tâches à mettre à jour', $count));

        if ($count === 0) {
            $io->success('Aucune tâche à mettre à jour');
            return Command::SUCCESS;
        }

        $progressBar = $io->createProgressBar($count);
        $progressBar->start();

        foreach ($tasks as $task) {
            // Utiliser la date de dernière modification ou la date actuelle
            if (method_exists($task, 'getUpdatedAt') && $task->getUpdatedAt()) {
                $task->setDateCompletion($task->getUpdatedAt());
            } else {
                $task->setDateCompletion(new \DateTime());
            }

            $progressBar->advance();
        }

        // Enregistrer les modifications
        $this->entityManager->flush();

        $progressBar->finish();
        $io->newLine(2);
        $io->success('Toutes les tâches ont été mises à jour avec succès');

        return Command::SUCCESS;
    }
}

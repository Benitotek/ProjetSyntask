<?php
// src/Command/FixUserRolesCommand.php
namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Security\RoleConverter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-user-roles',
    description: 'Fix user roles based on their role enum',
)]
class FixUserRolesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private RoleConverter $roleConverter;

    public function __construct(EntityManagerInterface $entityManager, RoleConverter $roleConverter)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->roleConverter = $roleConverter;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userRepository = $this->entityManager->getRepository(User::class);
        $users = $userRepository->findAll();

        $io->progressStart(count($users));
        $updatedCount = 0;

        foreach ($users as $user) {
            $roleEnum = $user->getRole();

            if ($roleEnum) {
                $roles = $this->roleConverter->convertEnumToRoles($roleEnum);
                $user->setRoles($roles);
                $updatedCount++;
            } else {
                // Si pas d'enum, au moins ROLE_USER
                $user->setRoles(['ROLE_USER']);
            }

            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf('Roles fixed successfully for %d users.', $updatedCount));

        return Command::SUCCESS;
    }
}

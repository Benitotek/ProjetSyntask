<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
class HashUserPasswordsCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->setName('app:hash-user-passwords') // 👈 obligatoire ici
            ->setDescription('Hash tous les mots de passe en clair de la base user.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userRepo = $this->entityManager->getRepository(User::class);
        $users = $userRepo->findAll();

        foreach ($users as $user) {
            $plainPassword = $user->getMdp(); // ou getPassword()

            if (!preg_match('/^$2[aby]$/', $plainPassword)) {
                $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setMdp($hashed);
                $output->writeln("Haché pour {$user->getEmail()}");
            } else {
                $output->writeln("Déjà haché : {$user->getEmail()}");
            }
        }

        $this->entityManager->flush();
        $output->writeln("Tous les mots de passe ont été traités.");

        return Command::SUCCESS;
    }
}
// #[AsCommand(
//     name: 'app:hash-user-passwords',
//     description: 'Hash tous les mots de passe en clair de la base user.',
// )]
// class HashUserPasswordsCommand extends Command
// {
//     private EntityManagerInterface $entityManager;
//     private UserPasswordHasherInterface $passwordHasher;

//     public function construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
//     {
//         parent::construct();
//         $this->entityManager = $entityManager;
//         $this->passwordHasher = $passwordHasher;
//     }

//     protected function execute(InputInterface $input, OutputInterface $output): int
//     {
//         $userRepo = $this->entityManager->getRepository(User::class);
//         $users = $userRepo->findAll();

//         foreach ($users as $user) {
//             $plainPassword = $user->getMdp(); // ou getPassword(), selon ton code

//             // Ne pas re-hacher si déjà sécurisé
//             if (!preg_match('/^$2[aby]$/', $plainPassword)) {
//                 $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
//                 $user->setMdp($hashed);
//                 $output->writeln("Haché pour {$user->getEmail()}");
//             } else {
//                 $output->writeln("Déjà haché : {$user->getEmail()}");
//             }
//         }

//         $this->entityManager->flush();

//         $output->writeln("Tous les mots de passe ont été traités.");
//         return Command::SUCCESS;
//     }
// }




// class HashUserPasswordsCommand extends Command
// {
//     private EntityManagerInterface $entityManager;
//     private UserPasswordHasherInterface $passwordHasher;

//     public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
//     {
//         parent::__construct();
//         $this->entityManager = $entityManager;
//         $this->passwordHasher = $passwordHasher;
//     }

//     protected function configure(): void
//     {
//         $this
//             ->setName('app:hash-user-passwords') // 👈 obligatoire ici
//             ->setDescription('Hash tous les mots de passe en clair de la base user.');
//     }

//     protected function execute(InputInterface $input, OutputInterface $output): int
//     {
//         $userRepo = $this->entityManager->getRepository(User::class);
//         $users = $userRepo->findAll();

//         foreach ($users as $user) {
//             $plainPassword = $user->getMdp(); // ou getPassword()

//             if (!preg_match('/^\$2[aby]\$/', $plainPassword)) {
//                 $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
//                 $user->setMdp($hashed);
//                 $output->writeln("Haché pour {$user->getEmail()}");
//             } else {
//                 $output->writeln("Déjà haché : {$user->getEmail()}");
//             }
//         }

//         $this->entityManager->flush();
//         $output->writeln("Tous les mots de passe ont été traités.");

//         return Command::SUCCESS;
//     }
// }
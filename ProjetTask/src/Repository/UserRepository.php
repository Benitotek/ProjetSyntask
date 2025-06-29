<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\Userstatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Trouver les utilisateurs par rôle (string-based filter only)
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', $role)
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime un utilisateur
     */
    public function delete(User $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }

    public function updatePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setMdp($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $this->updatePassword($user, $newHashedPassword);
    }

    /**
     * Compter les utilisateurs actifs
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.statut != :statut_inactif')
            ->setParameter('statut_inactif', Userstatut::INACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouver tous les chefs de projet
     */
    public function findChefsProjets(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', 'ROLE_CHEF_PROJET')
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver tous les utilisateurs actifs (filtrable par statut)
     */
    public function findActiveUsers(?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.statut != :statut_inactif')
            ->setParameter('statut_inactif', Userstatut::INACTIF)
            ->orderBy('u.nom', 'ASC');

        if ($statut) {
            $statutEnum = Userstatut::tryFrom($statut);
            if ($statutEnum !== null) {
                $qb->andWhere('u.statut = :statut')
                    ->setParameter('statut', $statutEnum);
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Met à jour les rôles de tous les utilisateurs en fonction de leur statut
     */
    public function updateAllUserRoles(): int
    {
        $users = $this->findAll();
        $count = 0;

        foreach ($users as $user) {
            if ($user->getstatut() !== null) {
                $this->synchronizeRoleAndstatut($user);
                $this->getEntityManager()->persist($user);
                $count++;
            }
        }

        $this->getEntityManager()->flush();
        return $count;
    }

    /**
     * Synchronise les rôles avec le statut (ex: promotion automatique)
     * Ce stub est laissé pour être implémenté selon votre logique métier.
     */
    private function synchronizeRoleAndstatut(User $user): void
    {
        // Implémentez la logique métier ici
    }
}

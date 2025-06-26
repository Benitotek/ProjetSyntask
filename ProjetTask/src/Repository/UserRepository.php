<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Trouver les utilisateurs avec un rôle spécifique
     * Cette méthode utilise le champ 'role' de votre entité User
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
     * Sauvegarde un utilisateur
     */
    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    /**
     * Supprime un utilisateur
     */
    public function delete(User $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }
    /**
     * Met à jour le mot de passe d'un utilisateur
     * @throws UnsupportedUserException si l'utilisateur n'est pas une instance de User
     */
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
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setMdp($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouver les utilisateurs avec un rôle spécifique
     * Attention: cette fonction utilise le champ 'role' (au singulier) de l'entité User
     */

    /**
     * trouver et compter les utilisateurs actifs
     */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.estActif = true')
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.estActif = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findChefsProjets(): array
    {
        return $this->findByRole('ROLE_CHEF_DE_PROJET');

        // return $this->createQueryBuilder('u')
        //     ->where('u.role LIKE :role')
        //     ->andWhere('u.estActif = true')
        //     ->setParameter('role', '%ROLE_CHEF_PROJET%')
        //     ->orderBy('u.nom', 'ASC')
        //     ->getQuery()
        //     ->getResult();
    }
}

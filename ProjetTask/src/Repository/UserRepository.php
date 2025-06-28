<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Trouver les utilisateurs par statut/rôle
     * @param array $roles 
     * @return User[]
     */
    public function findByRole(string $role): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%' . $role . '%')
            ->orderBy('u.nom', 'ASC');
        return $qb->getQuery()->getResult();
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
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setMdp($newHashedPassword);
        $this->save($user);
    }

    /**
     * Compter les utilisateurs actifs (avec un compte non désactivé)
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.statut != :status_inactif')
            ->setParameter('status_inactif', UserStatus::INACTIF)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouver tous les chefs de projets
     */
    public function findChefsProjets(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.statut = :status')
            ->setParameter('status', UserStatus::CHEF_PROJET)
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver tous les utilisateurs actifs (filtrable par rôle)
     */
    public function findActiveUsers(?string $role = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.statut != :status_inactif')
            ->setParameter('status_inactif', UserStatus::INACTIF)
            ->orderBy('u.nom', 'ASC');

        if ($role) {
            try {
                $statusValue = UserStatus::from($role);
                $qb->andWhere('u.statut = :role')
                    ->setParameter('role', $statusValue);
            } catch (\ValueError $e) {
                // Ignorer le filtre si le rôle n'est pas valide
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
            if ($user->getStatut() !== null) {
                $this->synchronizeRoleAndStatus($user);
                $this->getEntityManager()->persist($user);
                $count++;
            }
        }

        $this->getEntityManager()->flush();
        return $count;
    }
}

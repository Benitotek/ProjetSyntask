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
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    /**
     * Trouver les utilisateurs par statut/rôle
     * CORRECTION: Utilisation de statut au lieu de roles
     */
    public function findByRole(string $role): array
    {
        try {
            // Convertir la chaîne de rôle en valeur enum
            $statusValue = UserStatus::from($role);

            return $this->createQueryBuilder('u')
                ->where('u.statut = :status')
                ->setParameter('status', $statusValue)
                ->orderBy('u.nom', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\ValueError $e) {
            // Si le rôle ne correspond pas à une valeur d'enum valide
            return [];
        }
    }
    /**
     * Sauvegarde un utilisateur
     */
    public function save(User $user): void
    { // Synchroniser le rôle et le statut avant la sauvegarde
        $this->synchronizeRoleAndStatus($user);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    /**
     * Synchroniser le rôle avec le statut de l'utilisateur
     */
    private function synchronizeRoleAndStatus(User $user): void
    {
        // Synchroniser le role avec le statut
        if ($user->getStatut() !== null) {
            // Mapper le statut vers un rôle Symfony
            $rolePrefix = 'ROLE_';
            $roleValue = '';

            switch ($user->getStatut()) {
                case UserStatus::ADMIN:
                    $roleValue = $rolePrefix . 'ADMIN';
                    break;
                case UserStatus::DIRECTEUR:
                    $roleValue = $rolePrefix . 'DIRECTEUR';
                    break;
                case UserStatus::CHEF_PROJET:
                    $roleValue = $rolePrefix . 'CHEF_DE_PROJET';
                    break;
                case UserStatus::EMPLOYE:
                    $roleValue = $rolePrefix . 'EMPLOYE';
                    break;
                default:
                    // Pour les autres statuts, utiliser ROLE_USER
                    $roleValue = 'ROLE_USER';
            }

            if (!empty($roleValue)) {
                $user->setRole($roleValue);
            }
        }
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
            ->where('u.statut != :status_inactive')
            ->setParameter('status_inactive', UserStatus::INACTIF)
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
            ->where('u.statut != :status_inactive')
            ->setParameter('status_inactive', UserStatus::INACTIF)
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

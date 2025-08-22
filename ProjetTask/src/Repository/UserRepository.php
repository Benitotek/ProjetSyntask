<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\Userstatut;
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
    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgrade(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setMdp($newHashedPassword);

        $this->save($user, true);
    }

    /**
     * Recherche des utilisateurs par nom, prénom ou email
     */
    public function searchByTerm(string $term): array
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.nom) LIKE LOWER(:term) OR LOWER(u.prenom) LIKE LOWER(:term) OR LOWER(u.email) LIKE LOWER(:term)')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des utilisateurs qui ne sont pas membres d'un projet spécifique
     */
    public function searchNonProjectMembers(string $term, Project $project): array
    {
        $qb = $this->createQueryBuilder('u');

        return $qb
            ->where('LOWER(u.nom) LIKE LOWER(:term) OR LOWER(u.prenom) LIKE LOWER(:term) OR LOWER(u.email) LIKE LOWER(:term)')
            ->andWhere($qb->expr()->notIn(
                'u',
                $this->createQueryBuilder('m')
                    ->select('IDENTITY(m)')
                    ->from('App:Project', 'p')
                    ->join('p.membres', 'm')
                    ->where('p = :project')
                    ->getDQL()
            ))
            ->andWhere('u.roles LIKE :role_employe OR u.roles LIKE :role_chef')
            ->setParameter('term', '%' . $term . '%')
            ->setParameter('project', $project)
            ->setParameter('role_employe', '%ROLE_EMPLOYE%')
            ->setParameter('role_chef', '%ROLE_CHEF_PROJET%')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les membres d'un projet spécifique (utilisateurs assignés au projet)
     */
    public function findProjectMembers(Project $project): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.projets', 'p')
            ->where('p = :project')
            ->setParameter('project', $project)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs qui sont assignés à des tâches dans un projet spécifique
     */
    public function findUsersWithTasksInProject(Project $project): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.assignedTasks', 't')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->groupBy('u.id')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les employés disponibles (qui n'ont pas atteint leur capacité maximale de tâches)
     */
    public function findAvailableEmployees(int $maxTasks = 5): array
    {
        $qb = $this->createQueryBuilder('u');

        return $qb
            ->leftJoin('u.assignedTasks', 't', 'WITH', 't.statut != :completed')
            ->where('u.roles LIKE :role')
            ->groupBy('u.id')
            ->having($qb->expr()->lt('COUNT(t.id)', ':maxTasks'))
            ->setParameter('role', '%ROLE_EMPLOYE%')
            ->setParameter('completed', 'COMPLETEE')
            ->setParameter('maxTasks', $maxTasks)
            ->orderBy('COUNT(t.id)', 'ASC')
            ->getQuery()
            ->getResult();
    }
    /** 
     * Compter les utilisateurs par rôle
     */
    public function countByRole(string $roleValue): int
    {
        // Convertir la string en enum
        $roleEnum = UserRole::from($roleValue);

        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role = :role')
            ->setParameter('role', $roleEnum)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouver les utilisateurs par rôle
     */
    public function findByRole(string $roleValue): array
    {
        //  Convertir la string en enum
        $roleEnum = UserRole::from($roleValue);

        return $this->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', $roleEnum)
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
     * Trouver tous les chefs de project
     */
    public function findChefsprojects(): array
    {
        //  Utiliser l'enum au lieu de string
        return $this->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', UserRole::CHEF_PROJET)
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
    public function updateAllUserrole(): int
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

    public function findUsersByProject(Project $project): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.projets', 'p')
            ->where('p = :project')
            ->setParameter('project', $project)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

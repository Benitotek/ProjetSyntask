<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 *
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }
    // MAJ V2-V3 date du 02/07/2025

    /**
     * Trouve les projects dont un utilisateur est chef ou membre, avec filtrage par statut optionnel
     * 
     * @param User $user L'utilisateur concerné
     * @param string $statut Le statut des projects à retourner ('tous' pour tous les projects)
     * @return Project[] Retourne un tableau d'objets Project
     */

    /**
     * Trouve les projects récents avec stats
     * 
     * @param User|null $user Si fourni, limite aux projects de l'utilisateur
     * @param int $limit Nombre maximum de projects à retourner
     * @return Project[]
     */
    public function findRecentWithStats(?User $user = null, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.tasks', 't')
            ->addSelect('COUNT(t.id) AS taskCount')
            ->addSelect('SUM(CASE WHEN t.statut = \'TERMINE\' THEN 1 ELSE 0 END) AS completedTasks')
            ->groupBy('p.id')
            ->orderBy('p.dateCreation', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->leftJoin('p.membres', 'm')
                ->where('p.chefproject = :user')
                ->orWhere('m = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les projects où l'utilisateur est chef de project
     * 
     * @param User $user Le chef de project
     * @return Project[]
     */
    public function findByChefDeproject(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.chefproject = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les projects où l'utilisateur est membre
     * 
     * @param User $user L'utilisateur membre
     * @return Project[]
     */
    public function findByMembre(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.membres', 'm')
            ->where('m = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les projects assignés à un utilisateur (où il est membre mais pas chef)
     * 
     * @param User $user L'utilisateur assigné
     * @return Project[]
     */
    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.membres', 'm')
            ->where('m = :user')
            ->andWhere('p.chefproject != :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte tous les projects
     * 
     * @return int Le nombre de projects
     */
    public function countAll(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les projects avec un statut spécifique
     * 
     * @param array $statuts Tableau des statuts à compter
     * @return int Le nombre de projects correspondant aux statuts
     */
    public function countBystatut(array $statuts): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.statut IN (:statuts)')
            ->setParameter('statuts', $statuts)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les projects récents
     * 
     * @param int $limit Nombre maximum de projects à retourner
     * @return Project[]
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère des statistiques budgétaires sur les projects
     * 
     * @return array Tableau associatif de statistiques
     */
    public function getProjectsWithBudgetStats(): array
    {
        // Cette méthode dépend de la structure de notre entité Project
        // Si votre entité Project n'a pas de champ budget, il faut adapter cette méthode
        return $this->createQueryBuilder('p')
            ->select('p.id', 'p.titre', 'p.budget')
            ->orderBy('p.budget', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findProjectsAsMember(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.membres', 'm')
            ->where('m.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les projects où l'utilisateur est membre avec un statut spécifique
     */
    public function findProjectsAsMemberBystatut(User $user, string $statut): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.membres', 'm')
            ->where('m.id = :userId')
            ->andWhere('p.statut = :statut')
            ->setParameter('userId', $user->getId())
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les projects liés à un utilisateur (chef de project ou membre)
     */
    public function findProjectsByUser(User $user, string $statut = 'tous'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.membres', 'm')
            ->where('p.chefproject = :user')
            ->orWhere('m.id = :userId')
            ->setParameter('user', $user)
            ->setParameter('userId', $user->getId());

        // Filtrer par statut si ce n'est pas "tous"
        if ($statut !== 'tous') {
            $qb->andWhere('p.statut = :statut')
                ->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }


    // Trouve les projects par statut

    public function findByStatut(string $statut): array
    {
        return $this->findBy(['statut' => $statut]);
    }

    /**
     * Trouve les projects non archivés
     */
    public function findActiveProjects(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.estArchive = :archived')
            ->setParameter('archived', false)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les projects d'un utilisateur non archivés
     */
    public function findActiveProjectsByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.membres', 'm')
            ->where('p.chefproject = :user')
            ->orWhere('m.id = :userId')
            ->andWhere('p.estArchive = :archived')
            ->setParameter('user', $user)
            ->setParameter('userId', $user->getId())
            ->setParameter('archived', false)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
    // Trouve les projects par date de création

    public function findProjectsByDateCreation(\DateTimeInterface $dateCreation): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.dateCreation = :dateCreation')
            ->setParameter('dateCreation', $dateCreation)
            ->getQuery()
            ->getResult();
    }
    // Trouve les projects par date de fin Réelle

    public function findProjectsByDateFin(\DateTimeInterface $daterelle): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.datereelle = :datereelle')
            ->setParameter('datereelle', $daterelle)
            ->getQuery()
            ->getResult();
    }
    // Trouve les projects par date de fin prévue

    public function findProjectsByDateFinPrevue(\DateTimeInterface $dateButoir): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.datedateButoir = :datedateButoir')
            ->setParameter('datedateButoir', $dateButoir)
            ->getQuery()
            ->getResult();
    }


    public function findWithKanbanData(int $projectId): ?Project
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.taskLists', 'tl')
            ->leftJoin('tl.tasks', 't')
            ->leftJoin('t.assignedUsers', 'au')
            ->addSelect('tl', 't', 'au')
            ->where('p.id = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('tl.positionColumn', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}




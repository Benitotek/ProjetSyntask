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
        // Cette méthode dépend de la structure de votre entité Project
        // Si votre entité Project n'a pas de champ budget, vous devrez adapter cette méthode
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
}



// Version 2 mes avec quelque erreur 

// namespace App\Repository;

// use App\Entity\Project;
// use App\Entity\User;
// use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
// use Doctrine\Persistence\ManagerRegistry;

// /**
//  * @extends ServiceEntityRepository<Project>
//  *
//  * @method Project|null find($id, $lockMode = null, $lockVersion = null)
//  * @method Project|null findOneBy(array $criteria, array $orderBy = null)
//  * @method Project[]    findAll()
//  * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
//  */
// class ProjectRepository extends ServiceEntityRepository
// {
//     public function __construct(ManagerRegistry $registry)
//     {
//         parent::__construct($registry, Project::class);
//     }
//     // MAJ V2-V3 date du 02/07/2025

//     /**
//      * Trouve les projects dont un utilisateur est chef ou membre, avec filtrage par statut optionnel
//      * 
//      * @param User $user L'utilisateur concerné
//      * @param string $statut Le statut des projects à retourner ('tous' pour tous les projects)
//      * @return Project[] Retourne un tableau d'objets Project
//      */
//     public function findProjectsByUser(User $user, string $statut = 'tous'): array
//     {
//         $qb = $this->createQueryBuilder('p')
//             ->leftJoin('p.membres', 'm')
//             ->where('p.Chef_project = :user')
//             ->orWhere('m = :user')
//             ->setParameter('user', $user)
//             ->orderBy('p.dateCreation', 'DESC');

//         if ($statut !== 'tous') {
//             $qb->andWhere('p.statut = :statut')
//                 ->setParameter('statut', $statut);
//         }

//         return $qb->getQuery()->getResult();
//     }

//     /**
//      * Trouve les projects récents avec stats
//      * 
//      * @param User|null $user Si fourni, limite aux projects de l'utilisateur
//      * @param int $limit Nombre maximum de projects à retourner
//      * @return Project[]
//      */
//     public function findRecentWithStats(?User $user = null, int $limit = 5): array
//     {
//         $qb = $this->createQueryBuilder('p')
//             ->leftJoin('p.tasks', 't')
//             ->addSelect('COUNT(t.id) AS taskCount')
//             ->addSelect('SUM(CASE WHEN t.statut = \'TERMINE\' THEN 1 ELSE 0 END) AS completedTasks')
//             ->groupBy('p.id')
//             ->orderBy('p.dateCreation', 'DESC')
//             ->setMaxResults($limit);

//         if ($user) {
//             $qb->leftJoin('p.membres', 'm')
//                 ->where('p.Chef_project = :user')
//                 ->orWhere('m = :user')
//                 ->setParameter('user', $user);
//         }

//         return $qb->getQuery()->getResult();
//     }

//     /**
//      * Trouve les projects où l'utilisateur est chef de project
//      * 
//      * @param User $user Le chef de project
//      * @return Project[]
//      */
//     public function findByChefDeproject(User $user): array
//     {
//         return $this->createQueryBuilder('p')
//             ->where('p.Chef_project = :user')
//             ->setParameter('user', $user)
//             ->orderBy('p.dateCreation', 'DESC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Trouve les projects où l'utilisateur est membre
//      * 
//      * @param User $user L'utilisateur membre
//      * @return Project[]
//      */
//     public function findByMembre(User $user): array
//     {
//         return $this->createQueryBuilder('p')
//             ->leftJoin('p.membres', 'm')
//             ->where('m = :user')
//             ->setParameter('user', $user)
//             ->orderBy('p.dateCreation', 'DESC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Trouve les projects assignés à un utilisateur (où il est membre mais pas chef)
//      * 
//      * @param User $user L'utilisateur assigné
//      * @return Project[]
//      */
//     public function findByAssignedUser(User $user): array
//     {
//         return $this->createQueryBuilder('p')
//             ->leftJoin('p.membres', 'm')
//             ->where('m = :user')
//             ->andWhere('p.Chef_project != :user')
//             ->setParameter('user', $user)
//             ->orderBy('p.dateCreation', 'DESC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Compte tous les projects
//      * 
//      * @return int Le nombre de projects
//      */
//     public function countAll(): int
//     {
//         return $this->createQueryBuilder('p')
//             ->select('COUNT(p.id)')
//             ->getQuery()
//             ->getSingleScalarResult();
//     }

//     /**
//      * Compte les projects avec un statut spécifique
//      * 
//      * @param array $statuts Tableau des statuts à compter
//      * @return int Le nombre de projects correspondant aux statuts
//      */
//     public function countBystatut(array $statuts): int
//     {
//         return $this->createQueryBuilder('p')
//             ->select('COUNT(p.id)')
//             ->where('p.statut IN (:statuts)')
//             ->setParameter('statuts', $statuts)
//             ->getQuery()
//             ->getSingleScalarResult();
//     }

//     /**
//      * Trouve les projects récents
//      * 
//      * @param int $limit Nombre maximum de projects à retourner
//      * @return Project[]
//      */
//     public function findRecent(int $limit = 5): array
//     {
//         return $this->createQueryBuilder('p')
//             ->orderBy('p.dateCreation', 'DESC')
//             ->setMaxResults($limit)
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Récupère des statistiques budgétaires sur les projects
//      * 
//      * @return array Tableau associatif de statistiques
//      */
//     public function getProjectsWithBudgetStats(): array
//     {
//         // Cette méthode dépend de la structure de votre entité Project
//         // Si votre entité Project n'a pas de champ budget, vous devrez adapter cette méthode
//         return $this->createQueryBuilder('p')
//             ->select('p.id', 'p.titre', 'p.budget')
//             ->orderBy('p.budget', 'DESC')
//             ->getQuery()
//             ->getResult();
//     }
// }

// VERSION 1 STABLES mais avec des soucis ou manques date du new changement le 02/07/2025

// /**
//  * Compter tous les projects
//  */
// public function countAll(): int
// {
//     return $this->createQueryBuilder('p')
//         ->select('COUNT(p.id)')
//         ->getQuery()
//         ->getSingleScalarResult();
// }

// /**
//  * Trouver les projects récents
//  */
// public function findRecent(int $limit = 5): array
// {
//     return $this->createQueryBuilder('p')
//         ->orderBy('p.dateCreation', 'DESC')
//         ->setMaxResults($limit)
//         ->getQuery()
//         ->getResult();
// }

// // /**
// //  * Trouver les projects récents avec statistiques
// //  */
// public function findRecentWithStats(User $user, int $limit = 5): array
// {
//     // Cette méthode doit être implémentée selon vos besoins
//     // Par exemple, elle pourrait renvoyer des projects avec le nombre de tâches par statut
//     return $this->findByChef_project($user, $limit);
// }

// /**
//  * Trouver les projects par chef de project
//  * (Méthode déplacée ou renommée pour éviter les doublons)
//  */
// public function findByChef_project(User $user, $limit): array
// {
//     $qb = $this->createQueryBuilder('p')
//         ->where('p.Chef_project = :user')
//         ->setParameter('user', $user)
//         ->orderBy('p.dateCreation', 'DESC');

//     if ($limit) {
//         $qb->setMaxResults($limit);
//     }

//     return $qb->getQuery()->getResult();
// }

// /**
//  * Trouver les projects où l'utilisateur est membre
//  */
// public function findProjectsAsMember(User $user): array
// {
//     return $this->createQueryBuilder('p')
//         ->join('p.membres', 'm')
//         ->where('m = :user')
//         ->setParameter('user', $user)
//         ->getQuery()
//         ->getResult();
// }
// /**
//  * Trouve les projects où l'utilisateur est membre et qui ont un statut spécifique
//  */
// public function findProjectsAsMemberBystatut(User $user, string $statut): array
// {
//     return $this->createQueryBuilder('p')
//         ->join('p.membres', 'm')
//         ->where('m = :user')
//         ->andWhere('p.statut = :statut')
//         ->setParameter('user', $user)
//         ->setParameter('statut', $statut)
//         ->getQuery()
//         ->getResult();
// }

// /**
//  * Obtenir des statistiques budgétaires sur les projects (requête SQL brute)
//  */
// public function getProjectsWithBudgetStatsRaw(): array
// {
//     // Cette méthode doit retourner des statistiques budgétaires
//     // Par exemple, regrouper les projects par statut et calculer le budget total
//     $conn = $this->getEntityManager()->getConnection();

//     $sql = '
//         SELECT 
//             statut, 
//             COUNT(id) as count, 
//             SUM(budget) as totalBudget 
//         FROM 
//             project 
//         GROUP BY 
//             statut
//     ';

//     $stmt = $conn->prepare($sql);
//     $result = $stmt->executeQuery();

//     return $result->fetchAllAssociative();
// }

// public function findByAssignedUser(User $user): array
// {
//     return $this->createQueryBuilder('p')
//         ->join('p.membres', 'm')
//         ->where('m = :user OR p.Chef_project = :user')
//         ->setParameter('user', $user)
//         ->orderBy('p.dateCreation', 'DESC')
//         ->getQuery()
//         ->getResult();
// }
// public function findWithKanbanData(int $projectId): ?Project
// {
//     return $this->createQueryBuilder('p')
//         ->leftJoin('p.taskLists', 'tl')
//         ->leftJoin('tl.tasks', 't')
//         ->leftJoin('t.assignedUsers', 'au')
//         ->addSelect('tl', 't', 'au')
//         ->where('p.id = :projectId')
//         ->setParameter('projectId', $projectId)
//         ->orderBy('tl.positionColumn', 'ASC')
//         ->addOrderBy('t.position', 'ASC')
//         ->getQuery()
//         ->getOneOrNullResult();
// }

/**
 * Trouve les projects récents d'un utilisateur avec leurs statistiques
 */
// public function findRecentWithStats(User $user, int $limit = 5): array
// {
//     return $this->createQueryBuilder('p')
//         ->leftJoin('p.tasks', 't')
//         ->leftJoin('p.membres', 'm')
//         ->addSelect('COUNT(t.id) as taskCount')
//         ->where('m = :user OR p.chefDeproject = :user')
//         ->setParameter('user', $user)
//         ->groupBy('p.id')
//         ->orderBy('p.dateCreation', 'DESC')
//         ->setMaxResults($limit)
//         ->getQuery()
//         ->getResult();
// }

/**
 * Statistiques pour le dashboard directeur
 */
//     public function getProjectsWithBudgetStats(): array
//     {
//         return $this->createQueryBuilder('p')
//             ->select('p.statut', 'COUNT(p.id) as count', 'SUM(COALESCE(p.budget, 0)) as totalBudget')
//             ->groupBy('p.statut')
//             ->getQuery()
//             ->getResult();
//     }

//     public function findBystatut(array $statutes): array
//     {
//         return $this->createQueryBuilder('p')
//             ->where('p.statut IN (:statutes)')
//             ->setParameter('statutes', $statutes)
//             ->orderBy('p.dateCreation', 'DESC')
//             ->getQuery()
//             ->getResult();
//     }
//     /**
//      * Compter les projects par statut
//      */
//     public function countBystatut(array $statuts): int
//     {
//         return $this->createQueryBuilder('p')
//             ->select('COUNT(p.id)')
//             ->where('p.statut IN (:statuts)')
//             ->setParameter('statut', $statuts)
//             ->getQuery()
//             ->getSingleScalarResult();
//     }


//     public function findWithStats(): array
//     {
//         return $this->createQueryBuilder('p')
//             ->leftJoin('p.tasks', 't')
//             ->addSelect('t')
//             ->orderBy('p.dateCreation', 'DESC')
//             ->getQuery()
//             ->getResult();
//     }

//     public function findActiveProjects(): array
//     {
//         return $this->createQueryBuilder('p')
//             ->where('p.statut IN (:statutes)')
//             ->setParameter('statutes', [Project::STATUT_EN_COURS, Project::STATUT_EN_ATTENTE])
//             ->orderBy('p.dateCreation', 'DESC')
//             ->getQuery()
//             ->getResult();
//     }
//     public function findArchivedProjects(): array
//     {
//         return $this->createQueryBuilder('p')
//             ->andWhere('p.estArchive = :val')
//             ->setParameter('val', true)
//             ->orderBy('p.dateMaj', 'DESC') // optionnel si tu as un champ updatedAt
//             ->getQuery()
//             ->getResult();
//     }

//     public function findByReference(string $reference): ?Project
//     {
//         return $this->createQueryBuilder('p')
//             ->andWhere('p.reference = :ref')
//             ->setParameter('ref', $reference)
//             ->getQuery()
//             ->getOneOrNullResult();
//     }

//     public function findProjectsByUser(User $user): array
//     {
//         return $this->createQueryBuilder('p')
//             ->where('p.Chef_project = :user')
//             ->orWhere(':user MEMBER OF p.membres')
//             ->setParameter('user', $user)
//             ->orderBy('p.dateCreation', 'DESC')
//             ->getQuery()
//             ->getResult();
//     }
// }

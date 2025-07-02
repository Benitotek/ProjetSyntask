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
    /**
     * Compter tous les projets
     */
    public function countAll(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
     * Trouver les projets récents
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * Trouver les projets récents avec statistiques
    //  */
    public function findRecentWithStats(User $user, int $limit = 5): array
    {
        // Cette méthode doit être implémentée selon vos besoins
        // Par exemple, elle pourrait renvoyer des projets avec le nombre de tâches par statut
        return $this->findByChef_Projet($user, $limit);
    }

    /**
     * Trouver les projets par chef de projet
     * (Méthode déplacée ou renommée pour éviter les doublons)
     */
    public function findByChef_Projet(User $user, $limit): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.Chef_Projet = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateCreation', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }


    /**
     * Trouver les projets où l'utilisateur est membre
     */
    public function findByMembre(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.membres', 'm')
            ->where('m.id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir des statistiques budgétaires sur les projets (requête SQL brute)
     */
    public function getProjectsWithBudgetStatsRaw(): array
    {
        // Cette méthode doit retourner des statistiques budgétaires
        // Par exemple, regrouper les projets par statut et calculer le budget total
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT 
                statut, 
                COUNT(id) as count, 
                SUM(budget) as totalBudget 
            FROM 
                project 
            GROUP BY 
                statut
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }




    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.membres', 'm')
            ->where('m = :user OR p.Chef_Projet = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateCreation', 'DESC')
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

    /**
     * Trouve les projets récents d'un utilisateur avec leurs statistiques
     */
    // public function findRecentWithStats(User $user, int $limit = 5): array
    // {
    //     return $this->createQueryBuilder('p')
    //         ->leftJoin('p.tasks', 't')
    //         ->leftJoin('p.membres', 'm')
    //         ->addSelect('COUNT(t.id) as taskCount')
    //         ->where('m = :user OR p.chefDeProjet = :user')
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
    public function getProjectsWithBudgetStats(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.statut', 'COUNT(p.id) as count', 'SUM(COALESCE(p.budget, 0)) as totalBudget')
            ->groupBy('p.statut')
            ->getQuery()
            ->getResult();
    }

    public function findBystatut(array $statutes): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut IN (:statutes)')
            ->setParameter('statutes', $statutes)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
    /**
     * Compter les projets par statut
     */
    public function countBystatut(array $statuts): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.statut IN (:statuts)')
            ->setParameter('statut', $statuts)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function findWithStats(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tasks', 't')
            ->addSelect('t')
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveProjects(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut IN (:statutes)')
            ->setParameter('statutes', [Project::STATUT_EN_COURS, Project::STATUT_EN_ATTENTE])
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findArchivedProjects(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.estArchive = :val')
            ->setParameter('val', true)
            ->orderBy('p.dateMaj', 'DESC') // optionnel si tu as un champ updatedAt
            ->getQuery()
            ->getResult();
    }

    public function findByReference(string $reference): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.reference = :ref')
            ->setParameter('ref', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findProjectsByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.Chef_Projet = :user')
            ->orWhere(':user MEMBER OF p.membres')
            ->setParameter('user', $user)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findByChefDeProjet(User $chef): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.chefDeProjet = :chef')
            ->setParameter('chef', $chef)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.membres', 'm')
            ->where('m = :user OR p.chefDeProjet = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(array $statuses): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(array $statuses): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.statut IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
            ->where('p.statut IN (:statuses)')
            ->setParameter('statuses', [Project::STATUT_EN_COURS, Project::STATUT_EN_ATTENTE])
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findArchivedProjects(): array
{
return $this->createQueryBuilder('p')
->andWhere('p.estArchive = :val')
->setParameter('val', true)
->orderBy('p.updatedAt', 'DESC') // optionnel si tu as un champ updatedAt
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
}
    //    /**
    //     * @return Project[] Returns an array of Project objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Project
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }


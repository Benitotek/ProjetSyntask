<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 *
 * @method Activity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Activity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Activity[]    findAll()
 * @method Activity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * Récupère les activités récentes
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les activités d'un utilisateur
     */
    public function findByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les activités liées à un projet
     */
    public function findByProject(string $projectId, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.target = :projectId AND (a.type LIKE :projectType)')
            ->setParameter('projectId', $projectId)
            ->setParameter('projectType', 'project_%')
            ->orderBy('a.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les activités liées à une tâche
     */
    public function findByTask(string $taskId, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.target = :taskId AND (a.type LIKE :taskType)')
            ->setParameter('taskId', $taskId)
            ->setParameter('taskType', 'task_%')
            ->orderBy('a.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

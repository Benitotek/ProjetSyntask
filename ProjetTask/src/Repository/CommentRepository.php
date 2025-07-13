<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 *
 * @method Comment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Comment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Comment[]    findAll()
 * @method Comment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */

class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Trouver les commentaires d'une tâche, ordonnés par date de création
     */
    public function findByTask(Task $task): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.task = :task')
            ->setParameter('task', $task)
            ->orderBy('c.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }
    /**
     * Trouve tous les commentaires d'un utilisateur
     */
    public function findByUser(User $user, int $limit = 10)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.auteur = :user')
            ->setParameter('user', $user)
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Cherche les commentaires contenant un texte spécifique
     */
    public function searchByContent(string $searchTerm)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.contenu LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les commentaires récents pour le tableau de bord
     */
    public function findRecentComments(int $limit = 5)
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    /**
     * Trouver les commentaires récents pour un utilisateur
     * (commentaires sur les tâches auxquelles l'utilisateur est assigné ou qu'il a créées)
     */
    public function findRecentForUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.task', 't')
            ->andWhere('t.assignedUser = :user OR t.createdBy = :user')
            ->andWhere('c.auteur != :user') // Exclure les commentaires de l'utilisateur lui-même
            ->setParameter('user', $user)
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le nombre de commentaires d'une tâche
     */
    public function countByTask(Task $task): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.task = :task')
            ->setParameter('task', $task)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }
    
    /**
     * Trouve tous les commentaires pour une tâche donnée
     */
    public function findByTask(Task $task, array $orderBy = ['dateCreation' => 'DESC'])
    {
        return $this->findBy(['task' => $task], $orderBy);
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
}


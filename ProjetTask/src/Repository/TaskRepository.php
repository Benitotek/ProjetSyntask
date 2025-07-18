<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Entity\TaskList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }
    /**
     * Trouve les activités récentes
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    /**
     * Retourne toutes les tâches de chaque utilisateur qui possède le rôle ROLE_EMPLOYEE.
     * (Utilisable en mode admin pour le calendrier global.)
     *
     * @return Task[]
     */
    public function findAllEmployeeTasks(): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.assignedUser', 'u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_EMPLOYEE%')
            ->getQuery()
            ->getResult();
    }
    /**
     * Trouve toutes les tâches d'un project, triées par colonne puis position
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.taskList', 'tl')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('tl.positionColumn', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches assignées à un utilisateur
     */
    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.assignedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la prochaine position disponible dans une colonne
     * 
     * @param TaskList $taskList La colonne concernée
     * @return int La prochaine position
     */

    public function findNextPositionInColumn(TaskList $taskList): int
    {
        $result = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.taskList = :taskList')
            ->setParameter('taskList', $taskList)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? $result + 1 : 1;
    }

    /**
     * Trouve les tâches en retard
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.dateButoir < :today')
            ->andWhere('t.statut != :statut')
            ->setParameter('today', new \DateTime())
            ->setParameter('statut', 'TERMINE')
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches avec une échéance proche (dans les 3 jours)
     */
    public function findTasksWithDeadlineApproaching(): array
    {
        $today = new \DateTime();
        $threeDaysLater = new \DateTime('+3 days');

        return $this->createQueryBuilder('t')
            ->where('t.dateButoir BETWEEN :today AND :threeDaysLater')
            ->andWhere('t.statut != :statut')
            ->setParameter('today', $today)
            ->setParameter('threeDaysLater', $threeDaysLater)
            ->setParameter('statut', 'TERMINE')
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }


    /**
     * Réorganise les positions après suppression
     */
    public function reorganizePositionsInColumn(TaskList $column, int $deletedPosition): void
    {
        $entityManager = $this->getEntityManager();

        $tasksToUpdate = $this->createQueryBuilder('t')
            ->where('t.taskList = :column')
            ->andWhere('t.position > :pos')
            ->setParameter('column', $column)
            ->setParameter('pos', $deletedPosition)
            ->getQuery()
            ->getResult();

        foreach ($tasksToUpdate as $task) {
            $task->setPosition($task->getPosition() - 1);
        }

        $entityManager->flush();
    }

    /**
     * Déplace une tâche dans une colonne et ajuste les positions
     */
    public function moveTaskToColumn(Task $task, TaskList $newColumn, int $newPosition): void
    {
        $em = $this->getEntityManager();
        $oldColumn = $task->getTaskList();
        $oldPosition = $task->getPosition();

        if ($oldColumn === $newColumn) {
            if ($oldPosition < $newPosition) {
                $tasks = $this->createQueryBuilder('t')
                    ->where('t.taskList = :column')
                    ->andWhere('t.position > :oldPos AND t.position <= :newPos')
                    ->andWhere('t != :task')
                    ->setParameter('column', $oldColumn)
                    ->setParameter('oldPos', $oldPosition)
                    ->setParameter('newPos', $newPosition)
                    ->setParameter('task', $task)
                    ->getQuery()->getResult();

                foreach ($tasks as $t) {
                    $t->setPosition($t->getPosition() - 1);
                }
            } elseif ($oldPosition > $newPosition) {
                $tasks = $this->createQueryBuilder('t')
                    ->where('t.taskList = :column')
                    ->andWhere('t.position >= :newPos AND t.position < :oldPos')
                    ->andWhere('t != :task')
                    ->setParameter('column', $oldColumn)
                    ->setParameter('oldPos', $oldPosition)
                    ->setParameter('newPos', $newPosition)
                    ->setParameter('task', $task)
                    ->getQuery()->getResult();

                foreach ($tasks as $t) {
                    $t->setPosition($t->getPosition() + 1);
                }
            }
        } else {
            $tasksOld = $this->createQueryBuilder('t')
                ->where('t.taskList = :oldColumn')
                ->andWhere('t.position > :oldPos')
                ->setParameter('oldColumn', $oldColumn)
                ->setParameter('oldPos', $oldPosition)
                ->getQuery()->getResult();

            foreach ($tasksOld as $t) {
                $t->setPosition($t->getPosition() - 1);
            }

            $tasksNew = $this->createQueryBuilder('t')
                ->where('t.taskList = :newColumn')
                ->andWhere('t.position >= :newPos')
                ->setParameter('newColumn', $newColumn)
                ->setParameter('newPos', $newPosition)
                ->getQuery()->getResult();

            foreach ($tasksNew as $t) {
                $t->setPosition($t->getPosition() + 1);
            }
        }

        $task->setTaskList($newColumn);
        $task->setPosition($newPosition);
        $em->flush();
    }

    /**
     * Trouve les tâches par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('t.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches par priorité
     */
    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.priorite = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('t.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dernières tâches créées
     */
    public function findRecentTasks(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    // Récupère les tâches à rendre dans les 7 jours pour un utilisateur

    public function findUpcomingDueDatesForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.dateButoir > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }
    // Récupère les tâches assignées à un utilisateur
    // Retourne les tâches pour un user
    public function findAssignedToUser(UserInterface $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.assignedUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    // Retourne les tâches par projet ET user
    public function findByProjectAndUser(int $projectId, UserInterface $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.projet = :projectId')
            ->andWhere('t.assignedUser = :user')
            ->setParameter('projectId', $projectId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}

// namespace App\Repository;

// use App\Entity\Project;
// use App\Entity\Task;
// use App\Entity\User;
// use App\Entity\TaskList;
// use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
// use Doctrine\Persistence\ManagerRegistry;

// /**
//  * @extends ServiceEntityRepository<Task>
//  *
//  * @method Task|null find($id, $lockMode = null, $lockVersion = null)
//  * @method Task|null findOneBy(array $criteria, array $orderBy = null)
//  * @method Task[]    findAll()
//  * @method Task[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
//  */
// class TaskRepository extends ServiceEntityRepository
// {
//     public function __construct(ManagerRegistry $registry)
//     {
//         parent::__construct($registry, Task::class);
//     }
//     // version 2 et 3 en date du 02/07/2025
//     /**
//      * Trouve toutes les tâches d'un project, ordonnées par colonne puis position
//      * 
//      * @param Project $project Le project concerné
//      * @return Task[] Retourne un tableau d'objets Task
//      */
//     public function findByProject(Project $project): array
//     {
//         return $this->createQueryBuilder('t')
//             ->join('t.taskList', 'tl')
//             ->where('t.project = :project')
//             ->setParameter('project', $project)
//             ->orderBy('tl.positionColumn', 'ASC')
//             ->addOrderBy('t.position', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Trouve toutes les tâches assignées à un utilisateur
//      * 
//      * @param User $user L'utilisateur concerné
//      * @return Task[] Retourne un tableau d'objets Task
//      */
//     public function findByAssignedUser(User $user): array
//     {
//         return $this->createQueryBuilder('t')
//             ->where('t.assignedUser = :user')
//             ->setParameter('user', $user)
//             ->orderBy('t.dateEcheance', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Trouve la prochaine position disponible dans une colonne
//      * 
//      * @param TaskList $taskList La colonne concernée
//      * @return int La prochaine position
//      */
//     public function findNextPositionInColumn(TaskList $taskList): int
//     {
//         $result = $this->createQueryBuilder('t')
//             ->select('MAX(t.position)')
//             ->where('t.taskList = :taskList')
//             ->setParameter('taskList', $taskList)
//             ->getQuery()
//             ->getSingleScalarResult();

//         return $result ? $result + 1 : 1;
//     }

//     /**
//      * Trouve les tâches en retard
//      * 
//      * @return Task[] Retourne un tableau d'objets Task
//      */
//     public function findOverdue(): array
//     {
//         $today = new \DateTime();

//         return $this->createQueryBuilder('t')
//             ->where('t.dateEcheance < :today')
//             ->andWhere('t.statut != :statut')
//             ->setParameter('today', $today)
//             ->setParameter('statut', 'TERMINE')
//             ->orderBy('t.dateEcheance', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Trouve les tâches avec une échéance proche (dans les 3 jours)
//      * 
//      * @return Task[] Retourne un tableau d'objets Task
//      */
//     public function findTasksWithDeadlineApproaching(): array
//     {
//         $today = new \DateTime();
//         $threeDaysLater = new \DateTime('+3 days');

//         return $this->createQueryBuilder('t')
//             ->where('t.dateEcheance BETWEEN :today AND :threeDaysLater')
//             ->andWhere('t.statut != :statut')
//             ->setParameter('today', $today)
//             ->setParameter('threeDaysLater', $threeDaysLater)
//             ->setParameter('statut', 'TERMINE')
//             ->orderBy('t.dateEcheance', 'ASC')
//             ->getQuery()
//             ->getResult();
//     }

//     /**
//      * Déplace une tâche vers une colonne et réorganise les positions
//      * 
//      * @param Task $task La tâche à déplacer
//      * @param TaskList $newColumn La nouvelle colonne
//      * @param int $newPosition La nouvelle position dans la colonne
//      */
//     public function moveTaskToColumn(Task $task, TaskList $newColumn, int $newPosition): void
//     {
//         $entityManager = $this->getEntityManager();

//         // Ancienne colonne et position
//         $oldColumn = $task->getTaskList();
//         $oldPosition = $task->getPosition();

//         // Si on reste dans la même colonne
//         if ($oldColumn === $newColumn) {
//             if ($oldPosition < $newPosition) {
//                 // Décaler vers le bas les tâches entre l'ancienne et la nouvelle position
//                 $tasksToMove = $this->createQueryBuilder('t')
//                     ->where('t.taskList = :column')
//                     ->andWhere('t.position > :oldPos AND t.position <= :newPos')
//                     ->andWhere('t != :task')
//                     ->setParameter('column', $oldColumn)
//                     ->setParameter('oldPos', $oldPosition)
//                     ->setParameter('newPos', $newPosition)
//                     ->setParameter('task', $task)
//                     ->getQuery()
//                     ->getResult();

//                 foreach ($tasksToMove as $taskToMove) {
//                     $taskToMove->setPosition($taskToMove->getPosition() - 1);
//                 }
//             } else if ($oldPosition > $newPosition) {
//                 // Décaler vers le haut les tâches entre la nouvelle et l'ancienne position
//                 $tasksToMove = $this->createQueryBuilder('t')
//                     ->where('t.taskList = :column')
//                     ->andWhere('t.position >= :newPos AND t.position < :oldPos')
//                     ->andWhere('t != :task')
//                     ->setParameter('column', $oldColumn)
//                     ->setParameter('oldPos', $oldPosition)
//                     ->setParameter('newPos', $newPosition)
//                     ->setParameter('task', $task)
//                     ->getQuery()
//                     ->getResult();

//                 foreach ($tasksToMove as $taskToMove) {
//                     $taskToMove->setPosition($taskToMove->getPosition() + 1);
//                 }
//             }
//         } else {
//             // Si on change de colonne

//             // 1. Décaler les tâches dans l'ancienne colonne
//             $tasksInOldColumn = $this->createQueryBuilder('t')
//                 ->where('t.taskList = :column')
//                 ->andWhere('t.position > :pos')
//                 ->andWhere('t != :task')
//                 ->setParameter('column', $oldColumn)
//                 ->setParameter('pos', $oldPosition)
//                 ->setParameter('task', $task)
//                 ->getQuery()
//                 ->getResult();

//             foreach ($tasksInOldColumn as $taskToMove) {
//                 $taskToMove->setPosition($taskToMove->getPosition() - 1);
//             }

//             // 2. Décaler les tâches dans la nouvelle colonne
//             $tasksInNewColumn = $this->createQueryBuilder('t')
//                 ->where('t.taskList = :column')
//                 ->andWhere('t.position >= :pos')
//                 ->andWhere('t != :task')
//                 ->setParameter('column', $newColumn)
//                 ->setParameter('pos', $newPosition)
//                 ->setParameter('task', $task)
//                 ->getQuery()
//                 ->getResult();

//             foreach ($tasksInNewColumn as $taskToMove) {
//                 $taskToMove->setPosition($taskToMove->getPosition() + 1);
//             }
//         }

//         // Mettre à jour la tâche
//         $task->setTaskList($newColumn);
//         $task->setPosition($newPosition);

//         $entityManager->flush();
//     }

//     /**
//      * Réorganise les positions des tâches dans une colonne après suppression
//      * 
//      * @param TaskList $column La colonne à réorganiser
//      * @param int $deletedPosition La position de la tâche supprimée
//      */
//     public function reorganizePositionsInColumn(TaskList $column, int $deletedPosition): void
//     {
//         $entityManager = $this->getEntityManager();

//         $tasksToUpdate = $this->createQueryBuilder('t')
//             ->where('t.taskList = :column')
//             ->andWhere('t.position > :pos')
//             ->setParameter('column', $column)
//             ->setParameter('pos', $deletedPosition)
//             ->getQuery()
//             ->getResult();

//         foreach ($tasksToUpdate as $task) {
//             $task->setPosition($task->getPosition() - 1);
//         }

//         $entityManager->flush();
//     }
// }
